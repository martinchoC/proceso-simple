/**
 * JavaScript: Recibos y Cobranzas con Retenciones
 * Módulo 4: Estudio Contable (minigestion_recibos.js)
 */

$(document).ready(function() {
    let tablaRecibos = null;

    const formatoMoneda = new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
        minimumFractionDigits: 2
    });

    function initTablaRecibos() {
        const domConfig = '<"row align-items-center p-2 mb-0"<"col-12 col-md-6"l><"col-12 col-md-6 text-end"f>>' +
                          '<"row"<"col-12"tr>>' +
                          '<"row align-items-center p-2 mt-2"<"col-12 col-md-5"i><"col-12 col-md-7 d-flex justify-content-end"p>>';

        tablaRecibos = $('#tablaRecibos').DataTable({
            responsive: true,
            dom: domConfig,
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json',
                emptyTable: 'No hay recibos emitidos para esta empresa'
            },
            order: [[0, 'desc']],
            columns: [
                { data: 'f_emision' },
                { 
                    data: null,
                    render: function(d) {
                        const pv = String(d.comprobante_pv || 1).padStart(4, '0');
                        const nro = String(d.comprobante_nro || 0).padStart(8, '0');
                        return `REC-${pv}-${nro}`;
                    }
                },
                { data: 'cliente_nombre' },
                { data: 'cliente_cuit' },
                { 
                    data: 'importe_total',
                    className: 'text-end fw-bold text-primary',
                    render: d => formatoMoneda.format(d || 0)
                },
                { 
                    data: 'tabla_estado_registro_id',
                    className: 'text-center',
                    render: () => '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Cobrado</span>'
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

    function cargarClientesSelect() {
        const empresaId = $('#selEmpresaRecibo').val();
        $.ajax({
            url: 'minigestion_recibos_ajax.php',
            type: 'GET',
            data: { accion: 'listar_clientes', empresa_id: empresaId },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    let html = '<option value="">-- Seleccionar Cliente --</option>';
                    (resp.data || []).forEach(function(c) {
                        html += `<option value="${c.entidad_id}">${c.entidad_nombre} (CUIT: ${c.cuit})</option>`;
                    });
                    $('#rec_entidad_id').html(html);
                }
            }
        });
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

    cargarClientesSelect();
    cargarRecibos();

    $('#selEmpresaRecibo').on('change', function() {
        cargarClientesSelect();
        cargarRecibos();
    });

    $('#btnNuevoRecibo').on('click', function() {
        $('#formRecibo')[0].reset();
        $('#lblTotalRet, #lblNetoCobro').text('$ 0,00');
        $('#modalRecibo').modal('show');
    });

    // Auto-cálculo de neto a cobrar deduciendo retenciones
    $('#rec_total, .ret-importe').on('input', function() {
        const total = parseFloat($('#rec_total').val()) || 0;
        let totRet = 0;
        $('.ret-importe').each(function() {
            totRet += parseFloat($(this).val()) || 0;
        });
        const neto = total - totRet;
        $('#lblTotalRet').text(formatoMoneda.format(totRet));
        $('#lblNetoCobro').text(formatoMoneda.format(neto));
    });

    // Emitir Recibo
    $('#formRecibo').on('submit', function(e) {
        e.preventDefault();
        const formData = $(this).serialize();
        $('#btnEmitirReciboSubmit').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Emitiendo...');

        $.ajax({
            url: 'minigestion_recibos_ajax.php?accion=emitir_recibo',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(resp) {
                $('#btnEmitirReciboSubmit').prop('disabled', false).html('<i class="fas fa-check-circle me-1"></i>Emitir Recibo y Asentar');
                if (resp.success) {
                    $('#modalRecibo').modal('hide');
                    Swal.fire({
                        title: 'Recibo Emitido',
                        text: `Se emitió el Recibo N° ${resp.data.comprobante_nro} por ${formatoMoneda.format(resp.data.importe_total)} y se generó el asiento contable automático.`,
                        icon: 'success'
                    });
                    cargarRecibos();
                } else {
                    Swal.fire('Error', resp.message || 'No se pudo emitir el recibo.', 'error');
                }
            },
            error: function() {
                $('#btnEmitirReciboSubmit').prop('disabled', false).html('<i class="fas fa-check-circle me-1"></i>Emitir Recibo y Asentar');
                Swal.fire('Error', 'Fallo al procesar la solicitud.', 'error');
            }
        });
    });
});
