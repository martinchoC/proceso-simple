$(document).ready(function () {
    const AJAX = 'bancos_gastos_ajax.php';
    const { esc, num, fecha } = Bco;
    const excluir = new Set();
    let cuentas = [];
    let resumen = null;
    let tablaH;
    let pendientes = [];
    let puedeGenerar = false;
    let sel = null;   // mes abierto en A generar: { cuenta, mes }
    let puntosVenta = [];   // PV con la OP habilitada (vacío = el servidor lo asigna)
    const CLAVE_PV = 'bco_gastos_op_pv_' + window.EMPRESA_ID;
    const opPv = () => Number($('#opPuntoVenta').val()) || 0;
    const opPvTexto = () => opPv() ? ` La orden de pago sale por el punto de venta ${$('#opPuntoVenta option:selected').text()}.` : '';

    const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    const mesTexto = (m) => m ? `${MESES[Number(m.substring(5, 7)) - 1]} ${m.substring(0, 4)}` : '';
    const nombreCuenta = (c) => c ? c.tesoreria_cuenta + (c.numero ? ` (${c.numero})` : '') : '';
    const aMes = (d) => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`;
    const cuentaDe = (id) => cuentas.find(c => String(c.tesoreria_cuenta_id) === String(id));
    const claveSel = () => sel ? `${sel.cuenta}|${sel.mes}` : '';

    // Período de cada solapa: A generar sin límite (que no quede ningún mes viejo oculto), Generados los últimos 12 meses
    const hoy = new Date();
    $('#histDesde').val(aMes(new Date(hoy.getFullYear(), hoy.getMonth() - 11, 1)));
    $('#histHasta').val(aMes(hoy));

    /** Filtros de una solapa ('gen' / 'hist'); valida que el período no esté invertido. */
    function filtro(p) {
        const f = { cuenta_id: $(`#${p}Cuenta`).val() || 0, desde: $(`#${p}Desde`).val(), hasta: $(`#${p}Hasta`).val() };
        if (f.desde && f.hasta && f.desde > f.hasta) {
            UiSimple.aviso('El mes "desde" es posterior al mes "hasta".');
            return null;
        }
        return f;
    }

    $.getJSON(AJAX, { accion: 'opciones', empresa_idx: window.EMPRESA_ID }, (res) => {
        const d = res.data || {};
        cuentas = d.cuentas || [];
        Bco.opcionesSelect($('#genCuenta'), cuentas, 'tesoreria_cuenta_id', nombreCuenta, 'Todas las cuentas');
        Bco.opcionesSelect($('#histCuenta'), cuentas, 'tesoreria_cuenta_id', nombreCuenta, 'Todas las cuentas');
        puntosVenta = d.puntos_venta || [];
        $('#opPuntoVenta').html(puntosVenta.map(p => `<option value="${p.punto_venta_id}">${esc(String(p.codigo_fiscal).padStart(5, '0') + ' - ' + p.nombre)}</option>`).join(''));
        $('#grupoOpPv').toggleClass('d-none', !puntosVenta.length);
        try {
            const guardado = localStorage.getItem(CLAVE_PV);
            if (guardado && puntosVenta.some(p => String(p.punto_venta_id) === guardado)) $('#opPuntoVenta').val(guardado);
        } catch (e) { /* sin almacenamiento: queda el primero */ }
        (d.funciones || []).forEach(f => $(`[data-funcion="${f}"]`).removeClass('d-none'));
        puedeGenerar = (d.funciones || []).includes('agregar');
        tablaH = $('#tablaHistorial').DataTable({
            ajax: {
                url: AJAX,
                data: (x) => Object.assign(x, { accion: 'listar', empresa_idx: window.EMPRESA_ID }, filtro('hist') || {}),
                dataSrc: Bco.datos
            },
            order: [], pageLength: 12,
            columns: [
                { data: 'conciliacion_id', className: 'text-center ui-detalle' },
                { data: 'f_conciliacion', className: 'text-nowrap', render: (x) => fecha(x) },
                { data: 'tesoreria_cuenta', render: (x, t, r) => esc(x ? x + (r.cuenta_numero ? ` (${r.cuenta_numero})` : '') : '') },
                { data: 'comprobante_nro', render: (x, t, r) => x ? `Resumen bancario ${String(r.comprobante_pv).padStart(5, '0')}-${String(x).padStart(8, '0')}` : '' },
                { data: 'lineas', className: 'text-center' },
                { data: 'importe_extracto', className: 'text-end', render: (x) => Bco.importe(x) },
                { data: 'observaciones', className: 'ui-detalle small', render: (x, t, r) => esc(x || '') + (r.motivo_deshacer ? ' · Deshecho: ' + esc(r.motivo_deshacer) : '') },
                { data: 'estado_registro', className: 'text-center', render: (x, t, r) => Bco.estado(r) },
                { data: 'botones', orderable: false, className: 'text-center text-nowrap', render: (x, t, r) => Bco.botonesFila(x, r.conciliacion_id, 'Gastos ' + r.f_conciliacion) }
            ],
            language: Bco.idioma()
        });
        // Al entrar se abre el mes pendiente más antiguo
        cargarPendientes().then(() => seleccionar(primerGenerable()));
    });

    $('#btnTabGenerados').on('shown.bs.tab', () => { if (tablaH) tablaH.columns.adjust().responsive.recalc(); });

    // ---------- Solapa A generar ----------

    const primerGenerable = () => {
        const p = pendientes.find(x => Number(x.lineas) > 0);
        return p ? { cuenta: p.tesoreria_cuenta_id, mes: p.mes } : null;
    };
    const pendienteDe = (cuenta, mes) => pendientes.find(p => String(p.tesoreria_cuenta_id) === String(cuenta) && p.mes === mes);

    function cargarPendientes() {
        const f = filtro('gen');
        if (!f) return Promise.resolve();
        return Bco.pedir(AJAX, Object.assign({ accion: 'pendientes' }, f), { metodo: 'GET' }).then((res) => {
            pendientes = res.data || [];
            mostrarPendientes();
        }).catch(() => { pendientes = []; mostrarPendientes(); });
    }

    function mostrarPendientes() {
        const generables = pendientes.filter(p => Number(p.lineas) > 0);
        $('#cantPendientes').text(generables.length).toggleClass('d-none', !generables.length);
        $('#btnGenerarTodos').toggleClass('d-none', !puedeGenerar || generables.length < 2)
            .html(`<i class="bi bi-lightning-charge me-1"></i>Generar todos (${generables.length})`);
        if (!pendientes.length) {
            const conFiltro = $('#genCuenta').val() || $('#genDesde').val() || $('#genHasta').val();
            $('#pendientes').html(`<div class="text-success small p-2"><i class="bi bi-check2-circle me-1"></i>${conFiltro
                ? 'No quedan gastos del banco sin registrar en la cuenta y el período elegidos.'
                : 'Está todo generado: no quedan gastos del banco sin registrar.'}</div>`);
            return;
        }
        $('#pendientes').html(`<div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead class="table-light"><tr>
                <th>Cuenta</th><th>Mes</th><th class="text-center">Gastos</th><th class="text-end">Importe</th><th class="text-center">Sin concepto</th><th class="text-end">Acciones</th>
            </tr></thead><tbody>` +
            pendientes.map(p => {
                const clave = `${p.tesoreria_cuenta_id}|${p.mes}`;
                const sinConcepto = Number(p.sin_concepto)
                    ? `<span class="badge bg-warning text-dark" title="Líneas del mes sin concepto: si son gastos del banco, asignales el concepto en Conciliación Bancaria o en Extractos Bancarios > Movimientos">${p.sin_concepto}</span>` : '';
                const gen = Number(p.lineas) > 0;
                return `<tr class="${clave === claveSel() ? 'table-active' : ''}" data-clave="${clave}">
                    <td>${esc(nombreCuenta(p))}</td>
                    <td class="text-nowrap text-capitalize">${esc(mesTexto(p.mes))}</td>
                    <td class="text-center">${gen ? p.lineas : '<span class="text-muted">—</span>'}</td>
                    <td class="text-end font-monospace">${gen ? num(p.importe) : ''}</td>
                    <td class="text-center">${sinConcepto}</td>
                    <td class="text-end text-nowrap">
                        <button type="button" class="btn btn-sm btn-outline-primary btn-ver-mes" data-cuenta="${p.tesoreria_cuenta_id}" data-mes="${p.mes}" title="Ver las líneas del mes">Ver</button>
                        ${puedeGenerar && gen ? `<button type="button" class="btn btn-sm btn-success ms-1 btn-generar-mes" data-cuenta="${p.tesoreria_cuenta_id}" data-mes="${p.mes}">Generar</button>` : ''}
                    </td></tr>`;
            }).join('') + '</tbody></table></div>');
    }

    /** Abre (o cierra, con null) el detalle de un mes pendiente. */
    function seleccionar(s) {
        sel = s;
        excluir.clear();
        $('#pendientes tr[data-clave]').each(function () { $(this).toggleClass('table-active', $(this).data('clave') === claveSel()); });
        $('#detalleMes').toggleClass('d-none', !sel);
        if (sel) cargarDetalle();
    }

    $(document).on('click', '.btn-ver-mes', function () {
        seleccionar({ cuenta: $(this).data('cuenta'), mes: $(this).data('mes') });
        $('html, body').animate({ scrollTop: $('#detalleMes').offset().top - 80 }, 200);
    });

    $('#genCuenta, #genDesde, #genHasta').on('change', () => refrescarPendientes());
    $('#opPuntoVenta').on('change', function () { try { localStorage.setItem(CLAVE_PV, this.value); } catch (e) { /* sin almacenamiento */ } });
    $('#genRecargar').on('click', () => refrescarPendientes(true));

    /** Recarga la lista de pendientes; si el mes abierto ya no está (generado o fuera del filtro), abre el primero. */
    function refrescarPendientes(conservarExclusiones) {
        return cargarPendientes().then(() => {
            if (sel && pendienteDe(sel.cuenta, sel.mes)) {
                if (conservarExclusiones) cargarDetalle(); else seleccionar(sel);
            } else {
                seleccionar(primerGenerable());
            }
        });
    }

    /** Después de generar o deshacer: las dos solapas al día. */
    function refrescar() {
        refrescarPendientes();
        if (tablaH) tablaH.ajax.reload(null, false);
    }

    // Generar desde la lista: todos los gastos del mes, sin exclusiones (para excluir líneas, "Ver")
    $(document).on('click', '.btn-generar-mes', function () {
        const cuenta = $(this).data('cuenta');
        const mes = $(this).data('mes');
        const p = pendienteDe(cuenta, mes);
        Bco.confirmar('¿Generar el resumen bancario?', `${nombreCuenta(p)}, ${mesTexto(mes)}: ${p.lineas} gastos por $ ${num(p.importe)}. Se confirma el comprobante, se emite la orden de pago por débito en cuenta y se concilian las líneas.${opPvTexto()}`)
            .then(() => { $(this).prop('disabled', true); return Bco.pedir(AJAX, { accion: 'agregar', cuenta_id: cuenta, mes, excluir: [], op_punto_venta_id: opPv() }); })
            .then((res) => { Swal.fire({ icon: 'success', title: 'Comprobante generado', text: res.message }); refrescar(); })
            .catch(() => refrescar());
    });

    $('#btnGenerarTodos').on('click', function () {
        const lista = pendientes.filter(p => Number(p.lineas) > 0);
        const total = lista.reduce((s, p) => s + Number(p.importe), 0);
        Bco.confirmar(`¿Generar ${lista.length} resúmenes bancarios?`, `Uno por cuenta y mes de la lista, con todos sus gastos: $ ${num(total)} en total.${opPvTexto()}`)
            .then(async () => {
                const btn = $(this).prop('disabled', true);
                const ok = [];
                const fallas = [];
                for (const p of lista) {
                    btn.html(`<span class="spinner-border spinner-border-sm me-1"></span>${ok.length + fallas.length + 1} de ${lista.length}…`);
                    try {
                        await Bco.pedir(AJAX, { accion: 'agregar', cuenta_id: p.tesoreria_cuenta_id, mes: p.mes, excluir: [], op_punto_venta_id: opPv() }, { silencioso: true });
                        ok.push(p);
                    } catch (r) {
                        fallas.push(`${nombreCuenta(p)}, ${mesTexto(p.mes)}: ${Bco.mensajeDe(r)}`);
                    }
                }
                btn.prop('disabled', false);
                Swal.fire({
                    icon: fallas.length ? (ok.length ? 'warning' : 'error') : 'success',
                    title: ok.length ? `Se generaron ${ok.length} de ${lista.length}` : 'No se generó ninguno',
                    html: fallas.length ? '<div class="text-start small">' + fallas.map(f => `<div class="mb-1">${esc(f)}</div>`).join('') + '</div>' : ''
                });
                refrescar();
            }).catch(() => {});
    });

    function cargarDetalle() {
        if (!sel) return;
        const cta = cuentaDe(sel.cuenta) || {};
        $('#proveedorNombre').text(cta.proveedor_nombre || 'sin definir');
        $('#tituloMes').html(`<i class="bi bi-calendar3 me-1"></i>${esc(nombreCuenta(cta))} · <span class="text-capitalize">${esc(mesTexto(sel.mes))}</span>`);
        Bco.pedir(AJAX, { accion: 'resumen', cuenta_id: sel.cuenta, mes: sel.mes, excluir: [...excluir] }, { metodo: 'GET' }).then((res) => {
            resumen = res.data;
            mostrar(resumen);
        }).catch(() => {});
    }

    function mostrar(r) {
        $('#avisos').html(Bco.listaAvisos(r.bloqueos, r.avisos));
        const incluidas = r.incluidas.length;
        const kpi = (et, val, sub) => `<div class="col-4"><div class="ui-kpi"><div class="ui-kpi-etiqueta">${et}</div><div class="ui-kpi-valor">${val}</div><div class="ui-kpi-sub">${sub}</div></div></div>`;
        $('#kpis').html(kpi('Total del comprobante', num(r.total), r.f_emision ? 'con fecha ' + fecha(r.f_emision) + ' (último movimiento incluido)' : 'sin líneas') +
            kpi('IVA', num(r.iva_extracto), r.iva_extracto > 0 ? 'declarado en el extracto' : 'el extracto no trae IVA: va como no gravado') +
            kpi('Líneas', incluidas, `de ${r.lineas.length} gastos del banco del mes`));

        $('#comprobante').html(`<table class="table table-sm small mb-2"><thead><tr><th>Producto</th><th class="text-end">Neto</th><th class="text-end">IVA</th><th class="text-end">Total</th></tr></thead><tbody>` +
            r.detalles.map(d => `<tr><td>${esc(d.producto_nombre)} <span class="text-muted">(${d.lineas})</span>${d.sin_iva == 1 ? ' <span class="badge bg-light text-dark border">No gravado</span>' : ''}</td>
                <td class="text-end font-monospace">${num(d.neto + d.no_gravado)}</td><td class="text-end font-monospace">${num(d.iva)}</td><td class="text-end font-monospace">${num(d.total)}</td></tr>`).join('') +
            r.impuestos.map(i => `<tr><td>${esc(i.impuesto_tipo)}${i.jurisdiccion ? ' · ' + esc(i.jurisdiccion) : ''}</td><td></td><td></td><td class="text-end font-monospace">${num(i.importe)}</td></tr>`).join('') +
            `</tbody><tfoot><tr class="fw-semibold"><td>Total</td><td></td><td></td><td class="text-end font-monospace">${num(r.total)}</td></tr></tfoot></table>
            <div class="small text-muted">Se cancela con una orden de pago automática por débito en ${esc(r.cuenta.tesoreria_cuenta)}.</div>`);

        $('#lineas').html(r.lineas.length ? `<table class="table table-sm small mb-0"><thead class="sticky-top bg-white"><tr><th></th><th>Fecha</th><th>Descripción</th><th>Concepto</th><th class="text-end">Importe</th></tr></thead><tbody>` +
            r.lineas.map(l => {
                const motivo = l.motivo && l.motivo !== 'Excluida por el usuario' ? l.motivo : null;
                return `<tr class="${l.incluida ? '' : 'text-muted'}"><td><input type="checkbox" class="form-check-input sel-linea" value="${l.extracto_id}"
                        ${l.incluida ? 'checked' : ''} ${motivo ? 'disabled' : ''}></td><td class="text-nowrap">${fecha(l.f_movimiento)}</td>
                    <td>${esc(l.descripcion)}${motivo ? `<div class="text-warning">${esc(motivo)}</div>` : ''}</td><td>${esc(l.concepto)}</td>
                    <td class="text-end">${Bco.importe(l.importe)}</td></tr>`;
            }).join('') + '</tbody></table>' : '<div class="text-muted small p-2">No hay gastos del banco clasificados y sin registrar en el mes.</div>');

        $('#btnGenerar').prop('disabled', !!r.bloqueos.length || !incluidas);
    }

    $(document).on('change', '.sel-linea', function () {
        this.checked ? excluir.delete(this.value) : excluir.add(this.value);
        cargarDetalle();
    });

    $('#btnGenerar').on('click', function () {
        Bco.confirmar('¿Generar el resumen bancario?', `Total $ ${num(resumen.total)}: se confirma el comprobante, se emite la orden de pago por débito en cuenta y se concilian ${resumen.incluidas.length} líneas.${opPvTexto()}`)
            .then(() => { $(this).prop('disabled', true); return Bco.pedir(AJAX, { accion: 'agregar', cuenta_id: sel.cuenta, mes: sel.mes, excluir: [...excluir], op_punto_venta_id: opPv() }); })
            .then((res) => { Swal.fire({ icon: 'success', title: 'Comprobante generado', text: res.message }); refrescar(); })
            .catch(() => cargarDetalle());
    });

    // Proveedor del banco (de la cuenta del mes abierto)
    let buscando;
    $('#btnProveedor').on('click', () => { $('#buscarProveedor').val('banco'); buscar(); $('#modalProveedor').modal('show'); });
    $('#buscarProveedor').on('input', () => { clearTimeout(buscando); buscando = setTimeout(buscar, 300); });
    function buscar() {
        Bco.pedir(AJAX, { accion: 'proveedores', q: $('#buscarProveedor').val() }, { metodo: 'GET' }).then((res) => {
            $('#listaProveedores').html((res.data || []).map(p => `<button type="button" class="list-group-item list-group-item-action btn-prov" data-id="${p.entidad_id}"
                data-nombre="${esc(p.entidad_nombre)}">${esc(p.entidad_nombre)} <span class="text-muted small">${esc(p.cuit || '')}</span></button>`).join('')
                || '<div class="text-muted small">Sin resultados.</div>');
        }).catch(() => {});
    }
    $(document).on('click', '.btn-prov', function () {
        if (!sel) return;
        const id = $(this).data('id');
        const nombre = $(this).data('nombre');
        Bco.pedir(AJAX, { accion: 'proveedor', cuenta_id: sel.cuenta, entidad_id: id }).then((res) => {
            const cta = cuentaDe(sel.cuenta);
            if (cta) { cta.proveedor_entidad_id = id; cta.proveedor_nombre = nombre; }
            $('#modalProveedor').modal('hide');
            Bco.ok(res.message);
            cargarDetalle();
        }).catch(() => {});
    });

    // ---------- Solapa Generados ----------

    $('#histCuenta, #histDesde, #histHasta').on('change', () => { if (tablaH && filtro('hist')) tablaH.ajax.reload(); });
    $('#histRecargar').on('click', () => { if (tablaH && filtro('hist')) tablaH.ajax.reload(null, false); });

    $(document).on('click', '#tablaHistorial .btn-accion', function () {
        const id = $(this).data('id');
        const accion = $(this).data('accion');
        if (accion === 'visualizar') {
            Bco.pedir(AJAX, { accion: 'obtener', conciliacion_id: id }, { metodo: 'GET' }).then((res) => {
                const k = res.data;
                $('#modalDetalleLabel').text('Gastos conciliados ' + id);
                $('#detalle').html('<table class="table table-sm small">' + k.lineas.map(l => `<tr><td>${fecha(l.f_movimiento)}</td><td>${esc(l.descripcion)}</td>
                    <td>${esc(l.concepto || '')}</td><td class="text-end">${Bco.importe(l.importe)}</td></tr>`).join('') + '</table><h6>Débito en tesorería</h6><table class="table table-sm small">' +
                    k.movimientos.map(m => `<tr><td>${fecha(m.f_movimiento)}</td><td>${esc(m.concepto)}</td><td class="text-end">${Bco.importe(m.importe)}</td></tr>`).join('') + '</table>');
                $('#modalDetalle').modal('show');
            }).catch(() => {});
            return;
        }
        Bco.confirmar('¿Deshacer los gastos?', 'Se anula el resumen bancario (y su débito en tesorería) y las líneas vuelven a quedar disponibles en A generar.', true)
            .then((motivo) => Bco.pedir(AJAX, { accion: 'ejecutar_accion', accion_js: accion, conciliacion_id: id, motivo }))
            .then((res) => { Swal.fire({ icon: 'success', title: 'Listo', text: res.message }); refrescar(); })
            .catch(() => {});
    });
});
