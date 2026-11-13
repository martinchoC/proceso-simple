/**
 * JavaScript: Importar resumen bancario dentro de Mini-Gestión (minigestion_banco.js). Módulo 4, página 417.
 * Usa importar_banco_ajax.php: combos, cliente, preparar, analizar, importar. El archivo se manda de nuevo en analizar
 * e importar (el servidor no guarda el extracto; la lectura con IA queda en caché por archivo).
 * El cliente es el elegido en Mini-Gestión (#selCliente); el botón aparece si el cliente tiene empresa en el ERP con
 * permiso de escritura de compras.
 */
$(function () {
    const URL = 'importar_banco_ajax.php';
    const modalEl = document.getElementById('modalImportarBanco');
    if (!modalEl) { return; }
    const modal = new bootstrap.Modal(modalEl);
    let combos = { clientes: [], plantillas: [], bancos: [], formatos: [], ia: false };
    let proveedores = [];
    let analisis = null;
    let asignaciones = {};   // nro_linea -> concepto_id elegido a mano
    let huboCambios = false;

    function esc(t) { return $('<div>').text(t == null ? '' : String(t)).html(); }
    function plata(v) { return Fmt.moneda(Number(v || 0)); }
    function clienteId() { return String($('#selCliente').val() || ''); }
    function cliente() { return combos.clientes.find(function (x) { return String(x.cliente_id) === clienteId(); }) || null; }
    function falla(r) { Swal.fire('No se pudo completar', (r && r.message) || 'Fallo en la comunicación con el servidor.', 'warning'); }
    function cuit() { return String($('#ibCuit').val() || '').replace(/\D/g, ''); }
    function archivo() { return ($('#ibArchivo')[0].files || [])[0] || null; }

    function datos(accion, extra) {
        const fd = new FormData();
        fd.append('cliente_id', clienteId());
        fd.append('banco_id', $('#ibBanco').val() || '');
        fd.append('formato_id', $('#ibLector').val() || '0');
        fd.append('proveedor_cuit', cuit());
        fd.append('proveedor_nombre', $('#ibProveedorNombre').val() || '');
        fd.append('asignaciones', JSON.stringify(asignaciones));
        if (archivo()) { fd.append('archivo', archivo()); }
        Object.keys(extra || {}).forEach(function (k) { fd.append(k, extra[k]); });
        return $.ajax({ url: URL + '?accion=' + accion, method: 'POST', data: fd, processData: false, contentType: false, dataType: 'json',
                        headers: { 'X-CSRF-Token': window.IA_CSRF || '' } });
    }

    // ---- combos: clientes con escritura de compras, bancos, formatos del estudio, plantillas
    $.getJSON(URL, { accion: 'combos' }, function (r) {
        if (!r.success) { return; }   // sin la función: el botón no aparece
        combos = r.data;
        const $b = $('#ibBanco').empty().append($('<option>').val('').text('— Elegí el banco —'));
        (combos.bancos || []).forEach(function (b) { $b.append($('<option>').val(b.banco_id).text(b.banco)); });
        const $p = $('#ibPlantilla').empty().append($('<option>').val('').text('— Plantilla —'));
        (combos.plantillas || []).forEach(function (p) { $p.append($('<option>').val(p.plantilla_id).text(p.nombre + ' (' + p.empresa + ')')); });
        mostrarBoton();
    });
    function mostrarBoton() { $('#btnImportarBanco').toggleClass('d-none', !cliente()); }
    $('#selCliente').on('change', mostrarBoton);

    function cargarLectores() {
        const banco = String($('#ibBanco').val() || '');
        const $l = $('#ibLector').empty();
        (combos.formatos || []).filter(function (f) { return String(f.banco_id) === banco; })
            .forEach(function (f) { $l.append($('<option>').val(f.formato_id).text('Formato: ' + f.formato)); });
        $l.append($('<option>').val('0').text(combos.ia ? 'Leer con IA' : 'Leer con IA (el estudio no tiene clave de IA)'));
    }

    function limpiarAnalisis() {
        analisis = null;
        $('#ibCardMeses, #ibCardMovs').addClass('d-none');
        $('#ibExtractoInfo').empty();
    }
    function actualizarLeer() { $('#ibLeer').prop('disabled', !(archivo() && $('#ibBanco').val() && cuit().length === 11)); }

    $('#btnImportarBanco').on('click', function () {
        const c = cliente();
        if (!c) { return; }
        asignaciones = {};
        limpiarAnalisis();
        $('#ibArchivo').val('');
        $('#ibBanco').val('');
        cargarLectores();
        $('#ibCuit, #ibProveedorNombre').val('');
        $('#ibClienteNombre').text(c.razon_social);
        $('#ibCardPrerreq').addClass('d-none');
        $.getJSON(URL, { accion: 'cliente', cliente_id: c.cliente_id }, function (r) {
            if (!r.success) { falla(r); return; }
            proveedores = r.data.proveedores || [];
            const $s = $('#ibProveedorSel').empty().append($('<option>').val('').text('— Nuevo o por CUIT —'));
            proveedores.forEach(function (p) { $s.append($('<option>').val(p.entidad_id).text(p.entidad_nombre + (p.cuit ? ' (' + p.cuit + ')' : ''))); });
            pintarDiagnostico(r.data.diagnostico, []);
        });
        modal.show();
    });
    modalEl.addEventListener('hidden.bs.modal', function () {
        if (huboCambios) { huboCambios = false; $('#btnActualizarDatos').trigger('click'); }
    });

    $('#ibBanco').on('change', function () {
        cargarLectores();
        const nombre = $('#ibBanco option:selected').text();
        if (!$('#ibProveedorSel').val() && $(this).val()) { $('#ibProveedorNombre').val(nombre); }
        limpiarAnalisis();
        actualizarLeer();
    });
    $('#ibLector, #ibArchivo').on('change', function () { asignaciones = {}; limpiarAnalisis(); actualizarLeer(); });
    $('#ibCuit').on('input', actualizarLeer);
    $('#ibProveedorSel').on('change', function () {
        const p = proveedores.find(function (x) { return String(x.entidad_id) === String($(this).val()); }, this);
        if (p) { $('#ibCuit').val(p.cuit || ''); $('#ibProveedorNombre').val(p.entidad_nombre); }
        actualizarLeer();
    });

    // ---- prerrequisitos de la empresa del cliente
    function pintarDiagnostico(d, pasos) {
        const items = [];
        if (d.tipo_crb) { items.push('Tipo de comprobante "Resumen bancario" (C_RB, código 039)'); }
        if (d.crb_debito) { items.push('El Resumen bancario pide cuenta debitada y la empresa no lleva tesorería'); }
        if (d.no_gravado) { items.push('Alícuota "No gravado"'); }
        if (d.sucursal) { items.push('Sucursal'); }
        if (d.conceptos) { items.push('Conceptos y reglas bancarias'); }
        (d.incompletos || []).forEach(function (t) { items.push('Concepto ' + t); });
        const $b = $('#ibPrerreq').empty();
        (pasos || []).forEach(function (p) {
            $b.append($('<div>').append($('<i>').addClass('fas me-1 ' + (p.ok ? 'fa-check-circle text-success' : 'fa-times-circle text-danger')))
                .append($('<strong>').text(p.paso + ': ')).append(document.createTextNode(p.detalle || '')));
        });
        if (d.ok) {
            $b.append($('<div class="text-success">').html('<i class="fas fa-check me-1"></i>La empresa tiene lo necesario para cargar resúmenes bancarios.'));
        } else {
            const $ul = $('<ul class="mb-0">');
            items.forEach(function (t) { $ul.append($('<li>').text(t)); });
            $b.append($('<div class="text-warning-emphasis">').text('Le falta a la empresa del cliente (Preparar lo copia de la plantilla o del estudio):')).append($ul);
        }
        $('#ibPrepararBox').toggleClass('d-none', !!d.ok);
        $('#ibCardPrerreq').removeClass('d-none');
    }

    $('#ibPreparar').on('click', function () {
        const $btn = $(this).prop('disabled', true);
        $.ajax({ url: URL + '?accion=preparar', method: 'POST', dataType: 'json', headers: { 'X-CSRF-Token': window.IA_CSRF || '' },
                 data: { cliente_id: clienteId(), plantilla_id: $('#ibPlantilla').val() || 0 } }).done(function (r) {
            if (!r.data) { falla(r); return; }
            huboCambios = true;
            pintarDiagnostico(r.data.diagnostico, r.data.pasos);
            if (r.success && analisis) { analizar(); }
        }).fail(function () { falla(); }).always(function () { $btn.prop('disabled', false); });
    });

    // ---- análisis
    function analizar() {
        Swal.fire({ title: 'Leyendo el resumen...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
        datos('analizar').done(function (r) {
            Swal.close();
            if (!r.success) { falla(r); return; }
            analisis = r.data;
            pintarDiagnostico(analisis.diagnostico, []);
            pintarExtracto();
            pintarMeses();
            pintarMovimientos();
            $('#ibRecalcular').prop('disabled', true);
        }).fail(function () { Swal.close(); falla(); });
    }
    $('#ibLeer').on('click', analizar);
    $('#ibRecalcular').on('click', analizar);

    function pintarExtracto() {
        const x = analisis.extracto;
        const prov = analisis.proveedor;
        let html = '<span class="badge text-bg-info">' + esc(Fmt.fecha(x.f_desde)) + ' al ' + esc(Fmt.fecha(x.f_hasta)) + '</span>'
            + (x.cuenta_archivo ? ' Cuenta ' + esc(x.cuenta_archivo) + ' ·' : '')
            + ' Débitos ' + plata(x.debitos) + ' · Créditos ' + plata(x.creditos)
            + ' · Proveedor: ' + (prov.existe ? esc(prov.nombre) : '<em>se da de alta al cargar</em>')
            + ' · Comprobantes con PV ' + String(analisis.pv).padStart(5, '0');
        if ((x.avisos || []).length) {
            html += '<div class="text-warning-emphasis mt-1">' + x.avisos.map(esc).join('<br>') + '</div>';
        }
        $('#ibExtractoInfo').html(html);
    }

    function pintarMeses() {
        const listo = analisis.diagnostico.ok;
        $('#ibTablaMeses tbody').html((analisis.meses || []).map(function (m) {
            let neto = 0, iva = 0, ng = 0;
            (m.detalles || []).forEach(function (d) {
                if (Number(d.iva) > 0) { neto += Number(d.neto); } else { ng += Number(d.neto); }
                iva += Number(d.iva); ng += Number(d.no_gravado);
            });
            const percep = (m.impuestos || []).map(function (i) {
                return esc(i.impuesto_tipo + (i.jurisdiccion ? ' ' + i.jurisdiccion : '')) + ' ' + plata(i.importe);
            }).join('<br>');
            const marcado = m.generable && listo && !m.incompleto && !(m.existentes || []).length;
            const estado = m.bloqueos.length
                ? '<span class="text-danger">' + m.bloqueos.map(esc).join('<br>') + '</span>'
                : '<span class="badge text-bg-success">Listo</span>' + (m.avisos.length ? '<br><span class="text-warning-emphasis">' + m.avisos.map(esc).join('<br>') + '</span>' : '');
            return '<tr><td>' + (m.generable && listo ? '<input type="checkbox" class="ib-mes" value="' + esc(m.mes) + '"' + (marcado ? ' checked' : '') + '>' : '') + '</td>'
                + '<td>' + esc(m.mes) + '</td><td>' + esc(m.f_emision ? Fmt.fecha(m.f_emision) : '') + '</td><td>' + m.lineas_gasto + '</td>'
                + '<td class="text-end">' + plata(neto) + '</td><td class="text-end">' + plata(iva) + '</td><td class="text-end">' + plata(ng) + '</td>'
                + '<td class="small">' + percep + '</td><td class="text-end fw-semibold">' + plata(m.total) + '</td><td class="small">' + estado + '</td></tr>';
        }).join('') || '<tr><td colspan="10" class="text-center text-muted py-2">El resumen no tiene movimientos.</td></tr>');
        actualizarImportar();
        $('#ibCardMeses').removeClass('d-none');
    }
    function actualizarImportar() { $('#ibImportar').prop('disabled', !$('.ib-mes:checked').length); }
    $(document).on('change', '.ib-mes', actualizarImportar);

    function opcionesConcepto(sel) {
        const grupos = { GASTO_BANCARIO: 'Gastos del banco (van al resumen)', MOVIMIENTO_PROPIO: 'Movimientos propios (no van)' };
        let html = '<option value="">— Sin concepto —</option>';
        Object.keys(grupos).forEach(function (g) {
            html += '<optgroup label="' + esc(grupos[g]) + '">';
            (analisis.conceptos || []).filter(function (k) { return k.naturaleza === g; }).forEach(function (k) {
                html += '<option value="' + k.concepto_id + '"' + (String(k.concepto_id) === String(sel || '') ? ' selected' : '') + '>' + esc(k.concepto) + '</option>';
            });
            html += '</optgroup>';
        });
        return html;
    }

    function pintarMovimientos() {
        const solo = $('#ibSoloPendientes').is(':checked');
        const movs = analisis.movimientos || [];
        const pendientes = movs.filter(function (m) { return !m.concepto_id && m.importe < 0; }).length;
        $('#ibMovsResumen').text('(' + movs.length + (pendientes ? ', ' + pendientes + ' débitos sin concepto' : '') + ')');
        $('#ibTablaMovs tbody').html(movs.filter(function (m) { return !solo || !m.concepto_id; }).map(function (m) {
            const sin = !m.concepto_id;
            const clase = sin && m.importe < 0 ? ' class="table-warning"' : (m.naturaleza === 'GASTO_BANCARIO' ? ' class="table-light"' : '');
            return '<tr' + clase + '><td class="text-nowrap">' + esc(Fmt.fecha(m.f_movimiento)) + '</td>'
                + '<td class="small">' + esc(m.descripcion) + (m.referencia ? '<br><span class="text-muted">' + esc(m.referencia) + '</span>' : '') + '</td>'
                + '<td class="text-end text-nowrap ' + (m.importe < 0 ? 'text-danger' : 'text-success') + '">' + plata(m.importe) + '</td>'
                + '<td><select class="form-select form-select-sm ib-concepto" data-linea="' + m.nro_linea + '">' + opcionesConcepto(m.concepto_id) + '</select>'
                + (m.origen === 'MANUAL' ? '<span class="small text-primary">elegido a mano</span>' : '') + '</td></tr>';
        }).join('') || '<tr><td colspan="4" class="text-center text-muted py-2">Sin movimientos para mostrar.</td></tr>');
        $('#ibCardMovs').removeClass('d-none');
    }
    $('#ibSoloPendientes').on('change', function () { if (analisis) { pintarMovimientos(); } });
    $(document).on('change', '.ib-concepto', function () {
        const linea = String($(this).data('linea'));
        const v = parseInt($(this).val(), 10);
        if (v > 0) { asignaciones[linea] = v; } else { delete asignaciones[linea]; }
        $('#ibRecalcular').prop('disabled', false);
        $('#ibImportar').prop('disabled', true);   // los totales cambian: recalcular antes de cargar
    });

    // ---- importar
    $('#ibImportar').on('click', function () {
        const meses = $('.ib-mes:checked').map(function () { return this.value; }).get();
        const confirmar = $('#ibConfirmar').is(':checked');
        Swal.fire({ title: 'Cargar ' + meses.length + (meses.length === 1 ? ' resumen' : ' resúmenes'),
            text: confirmar ? 'Se cargan y confirman con su asiento: entran al Libro IVA del cliente.' : 'Quedan en Borrador; se confirman desde "Importar de ARCA" > Compras en Borrador.',
            showCancelButton: true, confirmButtonText: 'Cargar', cancelButtonText: 'Cancelar' }).then(function (q) {
            if (!q.isConfirmed) { return; }
            Swal.fire({ title: 'Cargando...', allowOutsideClick: false, didOpen: function () { Swal.showLoading(); } });
            datos('importar', { meses: JSON.stringify(meses), confirmar: confirmar ? 1 : 0 }).done(function (r) {
                if (!r.data) { falla(r); return; }
                huboCambios = true;
                const d = r.data;
                const conf = d.confirmacion || {};
                const problemas = (d.errores || []).concat((conf.items || []).filter(function (i) { return i.estado !== 'confirmada'; })
                    .map(function (i) { return '#' + i.factura_proveedor_id + ': ' + i.mensaje; }));
                Swal.fire({ icon: problemas.length ? 'warning' : 'success', title: r.success ? 'Listo' : 'No se pudo completar',
                    html: esc(r.message) + ((d.creados || []).length ? '<div class="text-start small mt-2">' + d.creados.map(function (c) {
                        return esc(c.mes + ': Resumen bancario ' + c.numero + ' por ' + plata(c.total)); }).join('<br>') + '</div>' : '')
                        + (problemas.length ? '<div class="text-start small mt-2 text-danger">' + problemas.map(esc).join('<br>') + '</div>' : '') })
                    .then(function () { asignaciones = {}; analizar(); });   // los meses cargados quedan como "Ya está cargado"
            }).fail(function () { Swal.close(); falla(); });
        });
    });
});
