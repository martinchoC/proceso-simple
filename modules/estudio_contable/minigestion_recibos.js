/**
 * JavaScript: Recibos y Cobranzas con Retenciones
 * Módulo 4: Estudio Contable (minigestion_recibos.js)
 * El recibo es una cobranza de Tesorería (minigestion_recibos_model.php): punto de venta, tipo, medios de cobro de
 * la empresa y retenciones sufridas como medios de tipo Retención.
 */

$(document).ready(function() {
    let tablaRecibos = null;
    let maestros = { clientes: [], puntos_venta: [], medios: [], cuentas: [], retenciones: [], jurisdicciones: [] };

    const formatoMoneda = { format: v => Fmt.moneda(v) };
    const esc = v => $('<div>').text(v == null ? '' : String(v)).html();

    function initTablaRecibos() {
        const domConfig = '<"row align-items-center p-2 mb-0"<"col-12 col-md-6"l><"col-12 col-md-6 text-end"f>>' +
                          '<"row"<"col-12"tr>>' +
                          '<"row align-items-center p-2 mt-2"<"col-12 col-md-5"i><"col-12 col-md-7 d-flex justify-content-end"p>>';

        tablaRecibos = $('#tablaRecibos').DataTable({
            responsive: true,
            dom: domConfig,
            language: {
                url: window.APP_ASSETS_URL + 'js/datatables-i18n-1.13.7/es-ES.json',
                emptyTable: 'No hay recibos emitidos para esta empresa'
            },
            order: [[0, 'desc']],
            columns: [
                { data: 'f_emision' },
                { data: 'comprobante_tipo', defaultContent: '' },
                { data: 'numero' },
                { data: 'cliente_nombre', defaultContent: '' },
                { data: 'cliente_cuit', defaultContent: '' },
                {
                    data: 'total_retenciones',
                    className: 'text-end',
                    render: d => parseFloat(d) > 0 ? formatoMoneda.format(d) : '-'
                },
                {
                    data: 'importe_total',
                    className: 'text-end fw-bold text-primary',
                    render: d => formatoMoneda.format(d || 0)
                },
                {
                    data: 'estado_registro',
                    className: 'text-center',
                    render: function(d, t, row) {
                        const cod = row.codigo_estandar || '';
                        const color = cod === 'ANULADO' ? 'bg-danger' : (cod === 'BORRADOR' ? 'bg-secondary' : 'bg-success');
                        return `<span class="badge ${color}">${esc(d || '')}</span>`;
                    }
                }
            ],
            initComplete: function() {
                new $.fn.dataTable.Buttons(tablaRecibos, {
                    buttons: [
                        { extend: 'copyHtml5', title: 'Recibos_Cobranzas' },
                        { extend: 'excelHtml5', title: 'Recibos_Cobranzas' },
                        { extend: 'csvHtml5', title: 'Recibos_Cobranzas' },
                        { extend: 'pdfHtml5', title: 'Recibos_Cobranzas' },
                        { extend: 'print', title: 'Recibos_Cobranzas' }
                    ]
                });
            }
        });

        $('#btnRecibosRecargar').on('click', () => cargarRecibos());
        $('#btnRecibosCopiar').on('click', () => tablaRecibos.button('.buttons-copy').trigger());
        $('#btnRecibosExcel').on('click', () => tablaRecibos.button('.buttons-excel').trigger());
        $('#btnRecibosPdf').on('click', () => tablaRecibos.button('.buttons-pdf').trigger());
        $('#btnRecibosCsv').on('click', () => tablaRecibos.button('.buttons-csv').trigger());
        $('#btnRecibosImprimir').on('click', () => tablaRecibos.button('.buttons-print').trigger());
    }

    initTablaRecibos();

    function opciones(lista, valor, texto, vacio) {
        let html = vacio ? `<option value="">${esc(vacio)}</option>` : '';
        (lista || []).forEach(x => { html += `<option value="${esc(x[valor])}">${esc(texto(x))}</option>`; });
        return html;
    }

    function cargarMaestros() {
        $.ajax({
            url: 'minigestion_recibos_ajax.php',
            type: 'GET',
            data: { accion: 'maestros', empresa_id: $('#selEmpresaRecibo').val() },
            dataType: 'json',
            success: function(resp) {
                if (!resp.success) {
                    maestros = { clientes: [], puntos_venta: [], medios: [], cuentas: [], retenciones: [], jurisdicciones: [] };
                    $('#btnNuevoRecibo').prop('disabled', true).attr('title', resp.error || resp.message || '');
                    return;
                }
                maestros = resp.data;
                $('#btnNuevoRecibo').prop('disabled', false).removeAttr('title');
                $('#rec_entidad_id').html(opciones(maestros.clientes, 'entidad_id', c => `${c.entidad_nombre} (CUIT: ${c.cuit || '-'})`, '-- Seleccionar Cliente --'));
                $('#rec_pv').html(opciones(maestros.puntos_venta, 'punto_venta_id',
                    p => `${String(p.codigo_fiscal || '').padStart(4, '0')} - ${p.nombre || ''}${p.sucursal_nombre ? ' (' + p.sucursal_nombre + ')' : ''}`));
                $('#rec_medio_pago').html(opciones(maestros.medios, 'medio_pago_id', m => m.medio_pago));
                armarRetenciones();
                cargarTipos();
                filtrarCuentas();
            }
        });
    }

    function armarRetenciones() {
        const provincias = opciones(maestros.jurisdicciones, 'jurisdiccion_id', j => j.jurisdiccion_nombre, '-- Provincia --');
        let html = '';
        (maestros.retenciones || []).forEach(function(r, i) {
            html += `<div class="row g-2 align-items-center mb-1 rec-ret" data-medio="${esc(r.medio_pago_id)}">
                        <div class="col-md-3"><span class="small fw-semibold">${esc(r.medio_pago)}:</span></div>
                        <div class="col-md-3"><input type="text" class="form-control form-control-sm ret-cert" placeholder="N° Certificado"></div>
                        <div class="col-md-3">${parseInt(r.es_iibb, 10) === 1
                            ? `<select class="form-select form-select-sm ret-jur">${provincias}</select>` : ''}</div>
                        <div class="col-md-3"><input type="number" step="0.01" min="0" class="form-control form-control-sm text-end ret-importe" placeholder="Importe $ (0.00)"></div>
                     </div>`;
        });
        $('#rec_retenciones').html(html || '<div class="small text-muted mb-1">La empresa no tiene medios de cobro de tipo Retención (Tesorería &gt; Medios de Pago).</div>');
    }

    function cargarTipos() {
        const pv = $('#rec_pv').val();
        if (!pv) { $('#rec_tipo').html(''); return; }
        $.ajax({
            url: 'minigestion_recibos_ajax.php',
            type: 'GET',
            data: { accion: 'tipos_por_punto_venta', empresa_id: $('#selEmpresaRecibo').val(), punto_venta_id: pv },
            dataType: 'json',
            success: function(resp) {
                $('#rec_tipo').html(opciones(resp.success ? resp.data : [], 'comprobante_tipo_id', t => t.comprobante_tipo));
            }
        });
    }

    // Cuentas de tesorería del tipo que pide el medio (caja, banco...); en efectivo puede quedar vacía: va la caja de la sucursal
    function filtrarCuentas() {
        const medio = (maestros.medios || []).find(m => String(m.medio_pago_id) === String($('#rec_medio_pago').val()));
        const tipo = medio ? medio.tesoreria_cuenta_tipo : null;
        const cuentas = (maestros.cuentas || []).filter(c => !tipo || c.cuenta_tipo === tipo);
        const vacio = medio && (medio.medio_pago_tipo === 'EFECTIVO' || tipo === 'CAJA') ? '-- Caja de la sucursal --' : null;
        $('#rec_cuenta').html(opciones(cuentas, 'tesoreria_cuenta_id', c => c.tesoreria_cuenta, vacio));
        if (medio && medio.tesoreria_cuenta_id) $('#rec_cuenta').val(String(medio.tesoreria_cuenta_id));
    }

    function cargarRecibos() {
        const empresaId = $('#selEmpresaRecibo').val();
        $('#rec_empresa_id').val(empresaId);

        $.ajax({
            url: 'minigestion_recibos_ajax.php',
            type: 'GET',
            data: { accion: 'listar', empresa_id: empresaId },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    tablaRecibos.clear().rows.add(resp.data || []).draw();
                }
            }
        });
    }

    cargarMaestros();
    cargarRecibos();

    $('#selEmpresaRecibo').on('change', function() {
        cargarMaestros();
        cargarRecibos();
    });
    $('#rec_pv').on('change', cargarTipos);
    $('#rec_medio_pago').on('change', filtrarCuentas);

    function recalcular() {
        const total = parseFloat($('#rec_total').val()) || 0;
        let totRet = 0;
        $('#rec_retenciones .ret-importe').each(function() {
            totRet += parseFloat($(this).val()) || 0;
        });
        $('#lblTotalRet').text(formatoMoneda.format(totRet));
        $('#lblNetoCobro').text(formatoMoneda.format(total - totRet));
    }

    $('#btnNuevoRecibo').on('click', function() {
        $('#formRecibo')[0].reset();
        $('#rec_retenciones input').val('');
        $('#rec_retenciones select').val('');
        cargarTipos();
        filtrarCuentas();
        recalcular();
        $('#modalRecibo').modal('show');
    });

    // Auto-cálculo de neto a cobrar deduciendo retenciones
    $(document).on('input', '#rec_total, #rec_retenciones .ret-importe', recalcular);

    // Emitir Recibo
    $('#formRecibo').on('submit', function(e) {
        e.preventDefault();
        const datos = {
            accion: 'emitir_recibo',
            empresa_id: $('#selEmpresaRecibo').val(),
            entidad_id: $('#rec_entidad_id').val(),
            f_emision: $('#rec_f_emision').val(),
            punto_venta_id: $('#rec_pv').val(),
            comprobante_tipo_id: $('#rec_tipo').val(),
            medio_pago_id: $('#rec_medio_pago').val(),
            tesoreria_cuenta_id: $('#rec_cuenta').val(),
            importe_total: $('#rec_total').val(),
            imputar: $('#rec_imputar').is(':checked') ? 1 : 0,
            observaciones: $('#formRecibo [name="observaciones"]').val(),
            retenciones: []
        };
        $('#rec_retenciones .rec-ret').each(function() {
            const importe = parseFloat($(this).find('.ret-importe').val()) || 0;
            if (importe > 0) {
                datos.retenciones.push({
                    medio_pago_id: $(this).data('medio'),
                    importe: importe,
                    certificado: $(this).find('.ret-cert').val(),
                    jurisdiccion_id: $(this).find('.ret-jur').val() || 0
                });
            }
        });

        const textoBoton = '<i class="fas fa-check-circle me-1"></i>Emitir Recibo y Asentar';
        $('#btnEmitirReciboSubmit').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Emitiendo...');

        $.ajax({
            url: 'minigestion_recibos_ajax.php',
            type: 'POST',
            data: datos,
            dataType: 'json',
            success: function(resp) {
                $('#btnEmitirReciboSubmit').prop('disabled', false).html(textoBoton);
                if (resp.success) {
                    const d = resp.data;
                    $('#modalRecibo').modal('hide');
                    let texto = `Se emitió el Recibo ${d.numero} por ${formatoMoneda.format(d.importe_total)} con su asiento contable.`;
                    if (d.importe_a_cuenta > 0.004) texto += ` ${formatoMoneda.format(d.importe_a_cuenta)} quedaron a cuenta del cliente.`;
                    Swal.fire({ title: 'Recibo Emitido', text: texto, icon: 'success' });
                    cargarRecibos();
                } else {
                    Swal.fire('Error', resp.error || resp.message || 'No se pudo emitir el recibo.', 'error');
                }
            },
            error: function() {
                $('#btnEmitirReciboSubmit').prop('disabled', false).html(textoBoton);
                Swal.fire('Error', 'Fallo al procesar la solicitud.', 'error');
            }
        });
    });
});
