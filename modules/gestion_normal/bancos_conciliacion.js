$(document).ready(function () {
    const AJAX = 'bancos_conciliacion_ajax.php';
    const { esc, num, fecha } = Bco;
    const selL = new Set();
    const selM = new Set();
    let datos = { lineas: [], movimientos: [] };
    let conceptos = [];
    let puedeClasificar = false;
    let puedeAltaConcepto = false;   // función "agregar" de Conceptos Bancarios
    let puedeRecibo = false;         // función "agregar" de Cobranzas: botón Generar recibo en las transferencias de clientes
    let puedeReciboConfirmar = false; // además "confirmar" de Cobranzas y "agregar" (Conciliar) de esta página
    let tablaL, tablaM, tablaH, tablaP, tablaPB, tablaPT;

    // La conciliación es por mes: el período sale del mes y el año elegidos (por defecto, el mes actual). Pendientes de
    // Conciliación y Conciliaciones tienen cada una su selector, pero es un solo período: cambiarlo en una cambia la otra.
    const hoy = new Date();
    const iso = (d) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().substring(0, 10);
    const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    $('.sel-mes').html(MESES.map((n, i) => `<option value="${i}">${n}</option>`).join(''));
    let anios = '';
    for (let a = hoy.getFullYear() + 1; a >= hoy.getFullYear() - 5; a--) anios += `<option value="${a}">${a}</option>`;
    $('.sel-anio').html(anios);
    function fijarPeriodo(a, m) {
        a = parseInt(a, 10);
        m = parseInt(m, 10);
        if (!$(`.sel-anio:first option[value="${a}"]`).length) $('.sel-anio').append(`<option value="${a}">${a}</option>`);
        $('.sel-anio').val(String(a));
        $('.sel-mes').val(String(m));
        $('#desde').val(iso(new Date(a, m, 1)));
        $('#hasta').val(iso(new Date(a, m + 1, 0)));
    }
    fijarPeriodo(hoy.getFullYear(), hoy.getMonth());
    $(document).on('change', '.sel-mes, .sel-anio', function () {
        const $p = $(this).closest('.bco-periodo');
        fijarPeriodo($p.find('.sel-anio').val(), $p.find('.sel-mes').val());
        cargar();
    });

    const filtro = () => ({ cuenta_id: $('#cuenta_id').val(), desde: $('#desde').val(), hasta: $('#hasta').val() });
    const filtroDatos = () => Object.assign(filtro(), { ignoradas: $('#verIgnoradas').is(':checked') ? 1 : 0 });

    const check = (tipo) => (d, t, r) => {
        const id = tipo === 'L' ? r.extracto_id : r.tesoreria_movimiento_id;
        const set = tipo === 'L' ? selL : selM;
        return `<input type="checkbox" class="form-check-input sel-${tipo}" value="${id}" ${set.has(String(id)) ? 'checked' : ''}>`;
    };

    // Concepto de la línea: lista desplegable en la fila (quien tiene "Clasificar líneas"); se guarda al cambiarla
    function conceptoCelda(d, t, r) {
        if (t !== 'display') return d || '';
        const gasto = r.naturaleza === 'GASTO_BANCARIO' ? ' <span class="badge bg-light text-dark border" title="Se registra en Gastos Bancarios">gasto</span>' : '';
        if (r.estado === 'IGNORADA') return '<span class="badge bg-secondary">Ignorada</span> ' + esc(d || '');
        if (!puedeClasificar) return !d ? '<span class="badge bg-warning text-dark">Sin concepto</span>' : esc(d) + gasto;
        const lista = conceptos.slice();
        if (r.concepto_id && !lista.some(k => String(k.concepto_id) === String(r.concepto_id))) {
            lista.push({ concepto_id: r.concepto_id, concepto: d + ' (inactivo)', naturaleza: r.naturaleza });
        }
        const opciones = '<option value="">Sin concepto</option>' + lista.map(k =>
            `<option value="${k.concepto_id}" ${String(k.concepto_id) === String(r.concepto_id || '') ? 'selected' : ''}>${esc(k.concepto)}${k.naturaleza === 'GASTO_BANCARIO' ? ' (gasto)' : ''}</option>`).join('') +
            (puedeAltaConcepto ? '<option value="__nuevo">+ Nuevo concepto…</option>' : '');
        const origen = r.concepto_origen === 'MANUAL' ? 'Asignado a mano' : (r.concepto_id ? 'Asignado por una regla' : 'Elegí el concepto');
        return `<select class="form-select form-select-sm sel-concepto ${r.concepto_id ? '' : 'border-warning'}" style="min-width: 11rem"
                    data-id="${r.extracto_id}" data-previo="${r.concepto_id || ''}" title="${origen}" aria-label="Concepto">${opciones}</select>`;
    }

    tablaL = $('#tablaExtracto').DataTable({
        data: [], paging: false, info: false, order: [[1, 'asc']], scrollY: '55vh', scrollCollapse: true,
        columns: [
            { data: null, orderable: false, className: 'text-center', render: check('L') },
            { data: 'f_movimiento', className: 'text-nowrap', render: (d, t) => t === 'display' ? fecha(d) : d },
            { data: 'descripcion', render: (d, t, r) => t === 'display' ? esc(d) + reciboCelda(r) : d },
            { data: 'concepto', render: conceptoCelda },
            { data: 'importe', className: 'text-end', render: (d, t) => t === 'display' ? Bco.importe(d) : Number(d) },
            { data: 'referencia', className: 'ui-detalle small', render: (d) => esc(d || '') }
        ],
        createdRow: (row, r) => { if (r.naturaleza === 'GASTO_BANCARIO' || r.estado === 'IGNORADA') $(row).addClass('text-muted'); },
        language: Bco.idioma()
    });
    tablaM = $('#tablaTesoreria').DataTable({
        data: [], paging: false, info: false, order: [[1, 'asc']], scrollY: '55vh', scrollCollapse: true,
        columns: [
            { data: null, orderable: false, className: 'text-center', render: check('M') },
            { data: 'f_movimiento', className: 'text-nowrap', render: (d, t) => t === 'display' ? fecha(d) : d },
            { data: 'concepto', render: (d, t, r) => esc(d) + (r.cuit ? ` <span class="text-muted small">${esc(r.cuit)}</span>` : '') },
            { data: 'importe', className: 'text-end', render: (d, t) => t === 'display' ? Bco.importe(d) : Number(d) },
            { data: 'medio_pago', className: 'ui-detalle small', render: (d, t, r) => esc(d || '') + (r.cheque_numero ? ' N° ' + esc(r.cheque_numero) : '') }
        ],
        language: Bco.idioma()
    });

    // En pantallas anchas (tablas lado a lado) las dos tablas ocupan todo el alto que queda hasta el pie de la ventana
    function ajustarAlto() {
        const $cuerpos = $('#tablaExtracto, #tablaTesoreria').closest('.dataTables_scrollBody');
        if (!$cuerpos.length || !$('#tabPendientes').is(':visible')) return;
        if (window.innerWidth < 1200) { $cuerpos.css('max-height', '55vh'); return; }
        const arriba = $cuerpos.first().offset().top - window.scrollY;
        const pie = $('.app-footer').outerHeight() || 0;
        $cuerpos.css('max-height', Math.max(220, window.innerHeight - arriba - pie - 24) + 'px');
    }
    $(window).on('resize', ajustarAlto);
    if (window.ResizeObserver) {
        const obs = new ResizeObserver(ajustarAlto);
        ['sugerencias', 'barraAcciones'].forEach(id => { const el = document.getElementById(id); if (el) obs.observe(el); });
    }
    // Las tablas con scroll se armaron con la solapa oculta: al mostrarla se recalculan los anchos de las columnas
    $('#btnTabPendientes').on('shown.bs.tab', () => { tablaL.columns.adjust(); tablaM.columns.adjust(); ajustarAlto(); });
    ajustarAlto();

    $.getJSON(AJAX, { accion: 'opciones', empresa_idx: window.EMPRESA_ID }, (res) => {
        const d = res.data || {};
        conceptos = d.conceptos || [];
        puedeClasificar = (d.funciones || []).includes('clasificar');
        puedeAltaConcepto = !!d.alta_concepto;
        puedeRecibo = !!d.recibo;
        puedeReciboConfirmar = !!d.recibo_confirmar && (d.funciones || []).includes('agregar');
        $('#btnNuevoConcepto').toggleClass('d-none', !puedeAltaConcepto);
        BcoConceptoRapido.opciones(d.productos, d.jurisdicciones);
        Bco.opcionesSelect($('#cuenta_id'), d.cuentas || [], 'tesoreria_cuenta_id', (c) => c.tesoreria_cuenta + (c.numero ? ` (${c.numero})` : ''));
        (d.funciones || []).forEach(f => $(`[data-funcion="${f}"]`).removeClass('d-none'));
        iniciarResumen();
        cargar();
    });

    // mantener = true (después de editar una fila): la página y las dos tablas quedan donde estaban
    function cargar(mantener) {
        if (!$('#cuenta_id').val()) return;
        const $cuerpos = $('#tablaExtracto, #tablaTesoreria').closest('.dataTables_scrollBody');
        const pos = mantener === true ? { ventana: window.scrollY, cuerpos: $cuerpos.map((i, el) => el.scrollTop).get() } : null;
        $('#sugerencias').empty();
        Bco.pedir(AJAX, Object.assign({ accion: 'datos' }, filtroDatos()), { metodo: 'GET' }).then((res) => {
            datos = res.data;
            // Lo marcado sigue marcado si la línea / el movimiento sigue pendiente
            const idsL = new Set(datos.lineas.map(l => String(l.extracto_id)));
            const idsM = new Set(datos.movimientos.map(x => String(x.tesoreria_movimiento_id)));
            [...selL].forEach(id => { if (!idsL.has(id)) selL.delete(id); });
            [...selM].forEach(id => { if (!idsM.has(id)) selM.delete(id); });
            tablaL.clear().rows.add(datos.lineas).draw();
            tablaM.clear().rows.add(datos.movimientos).draw();
            if (pos) {
                $cuerpos.each((i, el) => { el.scrollTop = pos.cuerpos[i]; });
                window.scrollTo(0, pos.ventana);
            }
            kpis(datos.totales);
            pintarPendientes();
            actualizarSeleccion();
            ajustarAlto();
            if (tablaH) tablaH.ajax.reload();
            if (tablaP) tablaP.ajax.reload();
        }).catch(() => {});
    }

    function kpis(t) {
        // Compactos: etiqueta e importe en una línea, el detalle chico debajo
        const kpi = (et, val, sub, clase = '') => `<div class="col-6 col-lg-3"><div class="ui-kpi" title="${esc(sub)}">
            <div class="d-flex justify-content-between align-items-baseline gap-2"><span class="ui-kpi-etiqueta text-truncate">${et}</span><span class="ui-kpi-valor text-nowrap ${clase}">${val}</span></div>
            <div class="ui-kpi-sub text-truncate">${sub}</div></div></div>`;
        $('#kpis').html(
            kpi('Saldo según el banco', t.saldo_banco == null ? '—' : num(t.saldo_banco), t.saldo_banco_fecha ? 'al ' + fecha(t.saldo_banco_fecha) : 'sin extracto importado') +
            kpi('Saldo según tesorería', num(t.saldo_tesoreria), 'al ' + fecha($('#hasta').val())) +
            kpi('Banco sin conciliar', num(t.extracto_pendiente), `+ gastos del banco ${num(t.gastos_pendientes)}` + (t.sin_concepto ? ` · ${t.sin_concepto} sin concepto` : ''), t.sin_concepto ? 'text-warning' : '') +
            kpi('Tesorería sin conciliar', num(t.tesoreria_pendiente), 'movimientos del período'));
        $('#totExtracto').text(`${datos.lineas.length} líneas`);
        $('#totTesoreria').text(`${datos.movimientos.length} movimientos`);
    }

    function suma(lista, set, clave) {
        return lista.filter(r => set.has(String(r[clave]))).reduce((s, r) => s + Number(r.importe), 0);
    }

    function actualizarSeleccion() {
        const se = suma(datos.lineas, selL, 'extracto_id');
        const st = suma(datos.movimientos, selM, 'tesoreria_movimiento_id');
        const dif = Math.round((se - st) * 100) / 100;
        $('#selExtracto').text(num(se));
        $('#selTesoreria').text(num(st));
        $('#selDiferencia').text(num(dif)).toggleClass('text-success', selL.size > 0 && selM.size > 0 && dif === 0).toggleClass('text-danger', dif !== 0);
        $('#btnConciliar').prop('disabled', !(selL.size && selM.size && dif === 0));
    }

    $(document).on('change', '.sel-concepto', function () {
        const $s = $(this);
        const v = $s.val();
        if (v === '__nuevo') {
            $s.val($s.data('previo'));
            const linea = datos.lineas.find(l => String(l.extracto_id) === String($s.data('id')));
            return abrirConcepto(linea ? [linea] : []);
        }
        const pedido = v ? { accion: 'clasificar', operacion: 'asignar', concepto_id: v, extracto_ids: [$s.data('id')] }
                        : { accion: 'clasificar', operacion: 'quitar', extracto_ids: [$s.data('id')] };
        $s.prop('disabled', true);
        Bco.pedir(AJAX, pedido).then((res) => {
            const d = res.data || {};
            if (d.mismas) Swal.fire({ icon: 'success', title: 'Concepto guardado', text: res.message });
            else Bco.ok(v ? 'Concepto guardado' : 'Concepto quitado');
            cargar(true);
        }).catch(() => $s.val($s.data('previo')).prop('disabled', false));
    });

    $(document).on('change', '.sel-L, .sel-M', function () {
        const set = $(this).hasClass('sel-L') ? selL : selM;
        this.checked ? set.add(this.value) : set.delete(this.value);
        actualizarSeleccion();
    });

    $('#cuenta_id, #verIgnoradas').on('change', cargar);
    $('#btnRecargar').on('click', cargar);

    $('#btnConciliar').on('click', function () {
        const btn = $(this).prop('disabled', true);
        Bco.pedir(AJAX, Object.assign({ accion: 'agregar', extracto_ids: [...selL], movimiento_ids: [...selM] }, filtro()))
            .then((res) => { Bco.ok(res.message); cargar(); })
            .catch(() => btn.prop('disabled', false));
    });

    $('#btnAuto').on('click', function () {
        Bco.pedir(AJAX, Object.assign({ accion: 'conciliar_auto' }, filtro())).then((res) => {
            Swal.fire({ icon: res.data.conciliadas ? 'success' : 'info', title: res.message, text: (res.data.errores || []).join(' ') });
            cargar();
        }).catch(() => {});
    });

    // Sugerencias: cada una se acepta y se concilia con un clic
    const TIPOS = { AUTO: ['success', 'Segura'], SUGERIDA: ['info', 'Probable'], GRUPO: ['warning', 'Agrupada'] };
    let sugerencias = [];
    $('#btnSugerir').on('click', function () {
        Bco.pedir(AJAX, Object.assign({ accion: 'sugerir' }, filtro()), { metodo: 'GET' }).then((res) => {
            sugerencias = res.data || [];
            if (!sugerencias.length) return $('#sugerencias').html('<div class="alert alert-light border py-2">No hay sugerencias: conciliá a mano marcando los dos lados.</div>');
            $('#sugerencias').html(`<div class="card border-0 shadow-sm"><div class="card-body p-2"><table class="table table-sm small mb-0">
                <thead><tr><th></th><th>Banco</th><th>Tesorería</th><th class="text-end">Importe</th><th></th></tr></thead><tbody>` +
                sugerencias.map((s, i) => `<tr><td><span class="badge bg-${TIPOS[s.tipo][0]}" title="${esc(s.motivo)}">${TIPOS[s.tipo][1]}</span></td>
                    <td>${s.lineas.map(l => fecha(l.f_movimiento) + ' ' + esc(l.descripcion)).join('<br>')}</td>
                    <td>${s.movimientos.map(m => fecha(m.f_movimiento) + ' ' + esc(m.concepto)).join('<br>')}</td>
                    <td class="text-end">${Bco.importe(s.importe)}</td>
                    <td class="text-end"><button type="button" class="btn btn-sm btn-outline-success btn-aceptar" data-i="${i}" ${$('#btnConciliar').hasClass('d-none') ? 'disabled' : ''}>Conciliar</button></td></tr>`).join('') +
                '</tbody></table></div></div>');
        }).catch(() => {});
    });
    $(document).on('click', '.btn-aceptar', function () {
        const s = sugerencias[$(this).data('i')];
        $(this).prop('disabled', true);
        Bco.pedir(AJAX, Object.assign({ accion: 'agregar', extracto_ids: s.extracto_ids, movimiento_ids: s.movimiento_ids, observaciones: 'Sugerencia: ' + s.motivo }, filtro()))
            .then(() => { $(this).closest('tr').remove(); Bco.ok('Conciliado'); const sug = $('#sugerencias').html(); cargar(); $('#sugerencias').html(sug); })
            .catch(() => $(this).prop('disabled', false));
    });

    // Clasificación de las líneas marcadas
    $(document).on('click', '[data-op]', function (e) {
        e.preventDefault();
        if (!selL.size) return Swal.fire({ icon: 'info', title: 'Marcá líneas del banco.' });
        const op = $(this).data('op');
        let previo = Promise.resolve(0);
        if (op === 'asignar') {
            const opciones = {};
            conceptos.forEach(k => { opciones[k.concepto_id] = k.concepto + (k.naturaleza === 'GASTO_BANCARIO' ? ' (gasto del banco)' : ''); });
            previo = Swal.fire({ title: 'Concepto', input: 'select', inputOptions: opciones, inputPlaceholder: 'Elegí el concepto', showCancelButton: true,
                                 confirmButtonText: 'Asignar', cancelButtonText: 'Cancelar', inputValidator: (v) => !v ? 'Elegí el concepto.' : undefined })
                .then(r => r.isConfirmed ? r.value : Promise.reject('cancelado'));
        }
        previo.then((concepto) => Bco.pedir(AJAX, { accion: 'clasificar', operacion: op, concepto_id: concepto, extracto_ids: [...selL] }))
            .then((res) => { Swal.fire({ icon: 'success', title: 'Listo', text: res.message }); cargar(); })
            .catch(() => {});
    });

    // Recibos desde el extracto: la línea trae r.cobranza cuando su CUIT es de un cliente (bco_cobranzas_marcar)
    function reciboCelda(r) {
        const k = r.cobranza;
        if (!k || !puedeRecibo) return '';
        const cliente = `<span class="text-muted small"> · ${esc(k.entidad_nombre)}</span>`;
        if (k.estado === 'RECIBO_CARGADO') return cliente + ' <span class="badge bg-light text-dark border" title="El recibo ya está cargado en tesorería: conciliala con Sugerencias">Recibo cargado</span>';
        if (k.estado === 'BORRADOR') return cliente + ` <span class="badge bg-secondary" title="Confirmalo en Cobranzas (cobranza ${k.cobranza_id}) y después conciliá la línea">Recibo en borrador</span>`;
        return cliente + ` <button type="button" class="btn btn-sm btn-outline-success py-0 px-1 ms-1 btn-recibo" data-id="${r.extracto_id}" title="Generar el recibo de cobranza de esta transferencia"><i class="bi bi-receipt me-1"></i>Generar recibo</button>`;
    }

    let recibo = null;   // datos del modal (cobranza_preparar)
    const nroComp = (p) => `${esc(p.comprobante_tipo)} ${String(p.comprobante_pv).padStart(4, '0')}-${String(p.comprobante_nro).padStart(8, '0')}`;

    $(document).on('click', '.btn-recibo', function () {
        const btn = $(this).prop('disabled', true);
        Bco.pedir(AJAX, { accion: 'cobranza_preparar', extracto_id: $(this).data('id') }, { metodo: 'GET' }).then((res) => {
            recibo = res.data;
            const l = recibo.linea;
            $('#formRecibo')[0].reset();
            $('#reciboCliente').text(`${recibo.cliente.entidad_nombre} (${recibo.cliente.cuit})`);
            $('#reciboFecha').text(fecha(l.f_movimiento));
            $('#reciboImporte').html(Bco.importe(l.importe));
            $('#reciboDescripcion').text(l.descripcion);
            // Aviso (no bloquea): otras cobranzas del cliente en el mes de la transferencia
            const mes = recibo.cobranzas_mes || [];
            $('#reciboAviso').html(mes.length ? `<div class="alert alert-warning py-2 small"><i class="bi bi-exclamation-triangle me-1"></i>
                Este cliente ya tiene ${mes.length === 1 ? 'una cobranza' : mes.length + ' cobranzas'} en el mes: ` +
                mes.map(k => `${k.numero ? 'recibo ' + esc(k.numero) : 'borrador ' + k.cobranza_id} del ${fecha(k.f_emision)} por ${num(k.importe_total)}`).join('; ') +
                '. Revisá que no sea la misma.</div>' : '');
            Bco.opcionesSelect($('#reciboPv'), recibo.puntos_venta || [], 'punto_venta_id',
                (p) => `${p.nombre} - PV ${String(p.codigo_fiscal || p.punto_venta_id).padStart(4, '0')}`, 'Elegí el punto de venta');
            if ((recibo.puntos_venta || []).length === 1) $('#reciboPv').val(String(recibo.puntos_venta[0].punto_venta_id));
            Bco.opcionesSelect($('#reciboMedio'), recibo.medios || [], 'medio_pago_id', 'medio_pago');
            cargarTiposRecibo();
            const prop = {};
            (recibo.propuesta || []).forEach(i => { prop[i.comprobante_destino_id] = i.importe_imputado; });
            $('#tablaReciboImp tbody').html((recibo.pendientes || []).map(p => {
                const v = prop[p.comprobante_id];
                return `<tr><td><input type="checkbox" class="form-check-input imp-sel" ${v ? 'checked' : ''}></td>
                    <td class="text-nowrap">${nroComp(p)}</td><td class="text-nowrap">${fecha(p.f_emision)}</td>
                    <td class="text-end">${Bco.importe(p.importe_pendiente)}</td>
                    <td><input type="number" class="form-control form-control-sm text-end imp-monto" step="0.01" min="0" max="${Number(p.importe_pendiente)}"
                         data-id="${p.comprobante_id}" data-saldo="${Number(p.importe_pendiente)}" value="${v ? Number(v).toFixed(2) : ''}" ${v ? '' : 'disabled'}></td></tr>`;
            }).join('') || '<tr><td colspan="5" class="text-muted">El cliente no tiene facturas pendientes hasta esa fecha: el recibo queda a cuenta.</td></tr>');
            // Confirmar por defecto solo si vino imputado (quien no imputa ni confirma, lo deja en Borrador)
            $('#reciboConfirmar').prop('checked', puedeReciboConfirmar && (recibo.propuesta || []).length > 0).prop('disabled', !puedeReciboConfirmar);
            totalesRecibo();
            $('#modalRecibo').modal('show');
        }).catch(() => {}).finally(() => btn.prop('disabled', false));
    });

    function cargarTiposRecibo() {
        const pv = $('#reciboPv').val();
        if (!pv) return Bco.opcionesSelect($('#reciboTipo'), [], 'comprobante_tipo_id', 'comprobante_tipo', 'Elegí el punto de venta');
        Bco.pedir(AJAX, { accion: 'cobranza_tipos', punto_venta_id: pv }, { metodo: 'GET' }).then((res) => {
            Bco.opcionesSelect($('#reciboTipo'), res.data || [], 'comprobante_tipo_id', 'comprobante_tipo');
        }).catch(() => {});
    }
    $('#reciboPv').on('change', cargarTiposRecibo);

    function totalesRecibo() {
        if (!recibo) return;
        let imputado = 0;
        $('#tablaReciboImp .imp-monto:enabled').each(function () { imputado += Number(this.value) || 0; });
        imputado = Math.round(imputado * 100) / 100;
        const resto = Math.round((Number(recibo.linea.importe) - imputado) * 100) / 100;
        $('#reciboImputado').text(num(imputado));
        $('#reciboACuenta').text(num(resto)).toggleClass('text-danger', resto < 0);
        $('#btnReciboGuardar').html($('#reciboConfirmar').is(':checked')
            ? '<i class="bi bi-check2-all me-1"></i>Confirmar y conciliar' : '<i class="fas fa-save me-1"></i>Guardar en Borrador');
    }
    $(document).on('change', '#tablaReciboImp .imp-sel', function () {
        const $m = $(this).closest('tr').find('.imp-monto');
        if (this.checked) {
            // Propone lo que falta imputar, hasta el saldo de la factura
            let imputado = 0;
            $('#tablaReciboImp .imp-monto:enabled').each(function () { imputado += Number(this.value) || 0; });
            const resto = Math.max(0, Number(recibo.linea.importe) - imputado);
            $m.prop('disabled', false).val(Math.min(Number($m.data('saldo')), resto).toFixed(2));
        } else {
            $m.prop('disabled', true).val('');
        }
        totalesRecibo();
    });
    $(document).on('input', '#tablaReciboImp .imp-monto', totalesRecibo);
    $('#reciboConfirmar').on('change', totalesRecibo);

    $('#formRecibo').on('submit', function (e) {
        e.preventDefault();
        if (!$('#reciboPv').val() || !$('#reciboTipo').val() || !$('#reciboMedio').val()) return UiSimple.aviso('Elegí el punto de venta, el tipo de recibo y el medio.');
        const imputaciones = [];
        let error = '';
        $('#tablaReciboImp .imp-monto:enabled').each(function () {
            const v = Math.round((Number(this.value) || 0) * 100) / 100;
            if (v <= 0) return;
            if (v > Number($(this).data('saldo')) + 0.004) error = 'Lo imputado a una factura supera su saldo.';
            imputaciones.push({ comprobante_destino_id: $(this).data('id'), importe_imputado: v });
        });
        if (!error && imputaciones.reduce((s, i) => s + i.importe_imputado, 0) > Number(recibo.linea.importe) + 0.004) error = 'Lo imputado supera el importe de la transferencia.';
        if (error) return UiSimple.aviso(error);
        const btn = $('#btnReciboGuardar').prop('disabled', true);
        Bco.pedir(AJAX, {
            accion: 'cobranza_generar', extracto_id: recibo.linea.extracto_id, punto_venta_id: $('#reciboPv').val(), comprobante_tipo_id: $('#reciboTipo').val(),
            medio_pago_id: $('#reciboMedio').val(), observaciones: $('#reciboObs').val(), imputaciones, confirmar: $('#reciboConfirmar').is(':checked') ? 1 : 0
        }).then((res) => {
            $('#modalRecibo').modal('hide');
            Swal.fire({ icon: 'success', title: res.data.confirmado ? 'Recibo confirmado' : 'Recibo en Borrador', text: res.message });
            cargar(true);
        }).catch(() => {}).finally(() => btn.prop('disabled', false));
    });

    // Alta rápida de un concepto: si se abre desde una línea (o con líneas marcadas), al guardarlo se les asigna
    function abrirConcepto(lineas) {
        BcoConceptoRapido.abrir({
            ajax: AJAX, lineas: puedeClasificar ? lineas : [],
            alGuardar: (k) => {
                conceptos.push(k);
                conceptos.sort((a, b) => (Number(a.orden) - Number(b.orden)) || String(a.concepto).localeCompare(String(b.concepto)));
                cargar(true);
            }
        });
    }
    $('#btnNuevoConcepto').on('click', () => abrirConcepto(datos.lineas.filter(l => selL.has(String(l.extracto_id)) && l.estado !== 'IGNORADA')));

    // Resumen: estado de la conciliación por cuenta y mes entre los años elegidos (por defecto, el año en curso).
    // "Ver" abre el mes en Conciliaciones; "Conciliar", en Pendientes de Conciliación.
    const cantidad = (n, uno, varios) => Number(n) ? `<span class="text-muted small">${n} ${Number(n) === 1 ? uno : varios}</span> ` : '';
    const nada = '<span class="text-muted">—</span>';
    const ESTADOS_MES = {
        CONCILIADO: '<span class="badge bg-success">Conciliado</span>',
        PENDIENTE: '<span class="badge bg-warning text-dark">Pendiente</span>',
        SIN_EXTRACTO: '<span class="badge bg-secondary" title="No se importó el extracto de ese mes">Sin extracto</span>'
    };
    let aniosR = '';
    for (let a = hoy.getFullYear(); a >= hoy.getFullYear() - 9; a--) aniosR += `<option value="${a}">${a}</option>`;
    $('#resumenDesde, #resumenHasta').html(aniosR).val(String(hoy.getFullYear()));
    $('#btnTabResumen').on('shown.bs.tab', () => { if (tablaP) tablaP.ajax.reload(); });
    function iniciarResumen() {
        tablaP = $('#tablaPeriodos').DataTable({
            ajax: { url: AJAX, data: (d) => Object.assign(d, { accion: 'periodos', empresa_idx: window.EMPRESA_ID, cuenta_id: $('#cuenta_id').val(),
                                                              solo_cuenta: $('#periodosSoloCuenta').is(':checked') ? 1 : 0,
                                                              anio_desde: $('#resumenDesde').val(), anio_hasta: $('#resumenHasta').val() }), dataSrc: Bco.datos },
            order: [[1, 'asc'], [0, 'asc']], pageLength: 25,
            columns: [
                // Cuenta en una sola línea y período corto (mes abreviado, "en curso" como ícono): filas de una línea
                { data: 'cuenta', className: 'text-nowrap', render: (d, t, r) => esc(d) + (r.cuenta_numero ? ` <span class="text-muted small">${esc(r.cuenta_numero)}</span>` : '') },
                { data: 'mes', className: 'text-nowrap', render: (d, t, r) => {
                    if (t !== 'display') return d;
                    const [a, m] = String(d).split('-');
                    const mes = MESES[parseInt(m, 10) - 1];
                    return `<span title="${mes} ${a}">${mes.slice(0, 3)} ${a}</span>`
                        + (r.en_curso ? ' <i class="fas fa-clock text-primary small" title="Mes en curso"></i>' : '');
                } },
                { data: 'saldo_banco', className: 'text-end text-nowrap', render: (d, t) => t !== 'display' ? Number(d) : (d === null ? nada : Bco.importe(d)) },
                { data: 'saldo_tesoreria', className: 'text-end text-nowrap', render: (d, t) => t !== 'display' ? Number(d) : Bco.importe(d) },
                { data: 'diferencia', className: 'text-end text-nowrap', render: (d, t) => t !== 'display' ? Number(d)
                    : (d === null ? nada : (Math.abs(Number(d)) < 0.005 ? '<span class="text-success">0</span>' : Bco.importe(d))) },
                { data: 'conciliadas', className: 'text-center text-nowrap', render: (d, t, r) => t !== 'display' ? Number(d)
                    : (r.total ? `${d} de ${r.total}` + (r.ignoradas ? ` <span class="text-muted small" title="Líneas ignoradas">(${r.ignoradas} ign.)</span>` : '') : nada) },
                { data: 'importe_lineas', className: 'text-end text-nowrap', render: (d, t, r) => t !== 'display' ? Number(d)
                    : (r.lineas ? cantidad(r.lineas, 'línea', 'líneas') + Bco.importe(d)
                        + (r.sin_concepto ? ` <span class="badge bg-warning text-dark" title="Líneas sin concepto">${r.sin_concepto} sin concepto</span>` : '') : nada) },
                { data: 'importe_gastos', className: 'text-end text-nowrap', render: (d, t, r) => t !== 'display' ? Number(d)
                    : (r.gastos ? cantidad(r.gastos, 'línea', 'líneas') + Bco.importe(d) : nada) },
                { data: 'importe_movimientos', className: 'text-end text-nowrap', render: (d, t, r) => t !== 'display' ? Number(d)
                    : (r.movimientos ? cantidad(r.movimientos, 'movimiento', 'movimientos') + Bco.importe(d) : nada) },
                { data: 'estado', className: 'text-center', render: (d, t) => t !== 'display' ? d : (ESTADOS_MES[d] || esc(d)) },
                { data: null, orderable: false, className: 'text-center text-nowrap', render: (d, t, r) =>
                    `<button type="button" class="btn btn-sm btn-outline-secondary btn-ir-periodo" data-solapa="btnTabHistorial" data-cuenta="${r.cuenta_id}" data-mes="${esc(r.mes)}">Ver</button>`
                    + (r.estado === 'PENDIENTE' ? ` <button type="button" class="btn btn-sm btn-outline-primary btn-ir-periodo" data-solapa="btnTabPendientes" data-cuenta="${r.cuenta_id}" data-mes="${esc(r.mes)}">Conciliar</button>` : '') }
            ],
            language: Bco.idioma()
        });
    }
    $('#periodosSoloCuenta').on('change', () => { if (tablaP) tablaP.ajax.reload(); });
    $('#resumenDesde, #resumenHasta').on('change', function () {
        if (Number($('#resumenDesde').val()) > Number($('#resumenHasta').val())) return UiSimple.aviso('El año "desde" es posterior al año "hasta".');
        if (tablaP) tablaP.ajax.reload();
    });

    $(document).on('click', '.btn-ir-periodo', function () {
        const [a, m] = String($(this).data('mes')).split('-');
        $('#cuenta_id').val(String($(this).data('cuenta')));
        fijarPeriodo(a, parseInt(m, 10) - 1);
        bootstrap.Tab.getOrCreateInstance(document.getElementById($(this).data('solapa') || 'btnTabPendientes')).show();
        cargar();
    });

    // Conciliaciones: saldos del mes (kpis), lo conciliado en el período y, de solo lectura, lo que queda pendiente
    // (las mismas líneas y movimientos de Pendientes de Conciliación, sin las ignoradas)
    function pintarPendientes() {
        if (!tablaPB) return;
        const lineas = datos.lineas.filter(l => l.estado !== 'IGNORADA');
        tablaPB.clear().rows.add(lineas).draw();
        tablaPT.clear().rows.add(datos.movimientos).draw();
        $('#totPendBanco').text(`${lineas.length} líneas`);
        $('#totPendTesoreria').text(`${datos.movimientos.length} movimientos`);
    }
    $('#btnTabHistorial').on('shown.bs.tab', function () {
        if (tablaH) {
            tablaPB.columns.adjust();
            tablaPT.columns.adjust();
            return tablaH.ajax.reload();
        }
        tablaPB = $('#tablaPendBanco').DataTable({
            data: [], order: [[0, 'asc']], pageLength: 25,
            columns: [
                { data: 'f_movimiento', className: 'text-nowrap', render: (d, t) => t === 'display' ? fecha(d) : d },
                { data: 'descripcion', render: (d) => esc(d) },
                { data: 'concepto', render: (d, t, r) => !d ? '<span class="badge bg-warning text-dark">Sin concepto</span>'
                    : esc(d) + (r.naturaleza === 'GASTO_BANCARIO' ? ' <span class="badge bg-light text-dark border" title="Se registra en Gastos Bancarios">gasto</span>' : '') },
                { data: 'importe', className: 'text-end', render: (d, t) => t === 'display' ? Bco.importe(d) : Number(d) }
            ],
            language: Bco.idioma()
        });
        tablaPT = $('#tablaPendTesoreria').DataTable({
            data: [], order: [[0, 'asc']], pageLength: 25,
            columns: [
                { data: 'f_movimiento', className: 'text-nowrap', render: (d, t) => t === 'display' ? fecha(d) : d },
                { data: 'concepto', render: (d, t, r) => esc(d) + (r.cuit ? ` <span class="text-muted small">${esc(r.cuit)}</span>` : '') },
                { data: 'importe', className: 'text-end', render: (d, t) => t === 'display' ? Bco.importe(d) : Number(d) }
            ],
            language: Bco.idioma()
        });
        pintarPendientes();
        tablaH = $('#tablaHistorial').DataTable({
            ajax: { url: AJAX, data: (d) => Object.assign(d, { accion: 'listar', empresa_idx: window.EMPRESA_ID }, filtro()), dataSrc: Bco.datos },
            order: [], pageLength: 25,
            columns: [
                { data: 'conciliacion_id', className: 'text-center' },
                { data: 'f_conciliacion', className: 'text-nowrap', render: (d) => fecha(d) },
                { data: 'origen', render: (d, t, r) => d === 'GASTOS'
                    ? `<span class="badge bg-warning text-dark">Gastos bancarios</span>${r.comprobante_nro ? ' <span class="small text-muted">Resumen n° ' + esc(r.comprobante_nro) + '</span>' : ''}`
                    : '<span class="badge bg-info text-dark">Contra tesorería</span>' },
                { data: 'lineas', className: 'text-center' },
                { data: 'movimientos', className: 'text-center' },
                { data: 'importe_extracto', className: 'text-end', render: (d) => Bco.importe(d) },
                { data: 'observaciones', className: 'ui-detalle small', render: (d, t, r) => esc(d || '') + (r.motivo_deshacer ? ' · Deshecha: ' + esc(r.motivo_deshacer) : '') },
                { data: 'estado_registro', className: 'text-center', render: (d, t, r) => Bco.estado(r) },
                { data: 'botones', orderable: false, className: 'text-center text-nowrap', render: (d, t, r) =>
                    Bco.botonesFila(r.origen === 'GASTOS' ? (d || []).filter(b => b.accion_js !== 'deshacer') : d, r.conciliacion_id, 'Conciliación ' + r.conciliacion_id) }
            ],
            language: Bco.idioma()
        });
    });

    $(document).on('click', '#tablaHistorial .btn-accion', function () {
        const id = $(this).data('id');
        const accion = $(this).data('accion');
        if (accion === 'visualizar') {
            Bco.pedir(AJAX, { accion: 'obtener', conciliacion_id: id }, { metodo: 'GET' }).then((res) => {
                const k = res.data;
                $('#modalDetalleLabel').text('Conciliación ' + id);
                $('#detalle').html(`<h6>Banco</h6><table class="table table-sm small">` + k.lineas.map(l => `<tr><td>${fecha(l.f_movimiento)}</td><td>${esc(l.descripcion)}</td>
                    <td class="text-end">${Bco.importe(l.importe)}</td></tr>`).join('') + `</table><h6>Tesorería</h6><table class="table table-sm small">` +
                    k.movimientos.map(m => `<tr><td>${fecha(m.f_movimiento)}</td><td>${esc(m.concepto)}</td><td class="text-end">${Bco.importe(m.importe)}</td></tr>`).join('') + '</table>' +
                    (k.observaciones ? `<p class="small text-muted mb-0">${esc(k.observaciones)}</p>` : ''));
                $('#modalDetalle').modal('show');
            }).catch(() => {});
            return;
        }
        Bco.confirmar('¿Deshacer la conciliación?', 'Las líneas y los movimientos vuelven a quedar pendientes.', true)
            .then((motivo) => Bco.pedir(AJAX, { accion: 'ejecutar_accion', accion_js: accion, conciliacion_id: id, motivo }))
            .then((res) => { Bco.ok(res.message); cargar(); })
            .catch(() => {});
    });
});
