/**
 * JavaScript: Cierre de Ejercicio Contable
 * Módulo 4: Estudio Contable (contabilidad_cierre.js)
 */

$(document).ready(function() {
    let tablaCierres = null;

    const formatoMoneda = { format: v => Fmt.moneda(v) };

    function initTablaCierres() {
        const domConfig = '<"row align-items-center p-2 mb-0"<"col-12 col-md-6"l><"col-12 col-md-6 text-end"f>>' +
                          '<"row"<"col-12"tr>>' +
                          '<"row align-items-center p-2 mt-2"<"col-12 col-md-5"i><"col-12 col-md-7 d-flex justify-content-end"p>>';

        tablaCierres = $('#tablaCierres').DataTable({
            responsive: true,
            dom: domConfig,
            language: {
                url: window.APP_ASSETS_URL + 'js/datatables-i18n-1.13.7/es-ES.json',
                emptyTable: 'No hay cierres de ejercicio registrados'
            },
            order: [[0, 'desc']],
            columns: [
                { 
                    data: 'anio_ejercicio',
                    className: 'fw-bold text-center'
                },
                { data: 'f_inicio' },
                { data: 'f_cierre' },
                { 
                    data: 'resultado_neto_ejercicio',
                    className: 'text-end fw-bold',
                    render: function(d) {
                        const val = parseFloat(d) || 0;
                        const color = val >= 0 ? 'text-success' : 'text-danger';
                        return `<span class="${color}">${formatoMoneda.format(val)}</span>`;
                    }
                },
                { 
                    data: 'asiento_refundicion_id',
                    render: d => parseInt(d, 10) > 0 ? `<span class="badge bg-secondary">Asiento #${d}</span>` : '-'
                },
                { 
                    data: 'asiento_cierre_patrimonial_id',
                    render: d => parseInt(d, 10) > 0 ? `<span class="badge bg-secondary">Asiento #${d}</span>` : '-'
                },
                { data: 'usuario_cierre_nombre' },
                { data: 'created_at' },
                { 
                    data: 'tabla_estado_registro_id',
                    className: 'text-center',
                    render: function(d) {
                        return '<span class="badge bg-success"><i class="fas fa-lock me-1"></i>Cerrado</span>';
                    }
                }
            ],
            initComplete: function() {
                new $.fn.dataTable.Buttons(tablaCierres, {
                    buttons: [
                        { extend: 'copyHtml5', title: 'Cierres_Contables' },
                        { extend: 'excelHtml5', title: 'Cierres_Contables' },
                        { extend: 'csvHtml5', title: 'Cierres_Contables' },
                        { extend: 'pdfHtml5', title: 'Cierres_Contables' },
                        { extend: 'print', title: 'Cierres_Contables' }
                    ]
                });
            }
        });

        // Enlace de botones exportación
        $('#btnCierresRecargar').on('click', () => cargarHistorialCierres());
        $('#btnCierresCopiar').on('click', () => tablaCierres.button('.buttons-copy').trigger());
        $('#btnCierresExcel').on('click', () => tablaCierres.button('.buttons-excel').trigger());
        $('#btnCierresPdf').on('click', () => tablaCierres.button('.buttons-pdf').trigger());
        $('#btnCierresCsv').on('click', () => tablaCierres.button('.buttons-csv').trigger());
        $('#btnCierresImprimir').on('click', () => tablaCierres.button('.buttons-print').trigger());
    }

    initTablaCierres();

    function cargarHistorialCierres() {
        const empresaId = $('#selEmpresaCierre').val();
        $.ajax({
            url: 'contabilidad_cierre_ajax.php',
            type: 'GET',
            data: { accion: 'listar_cierres', empresa_id: empresaId },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    tablaCierres.clear().rows.add(resp.data || []).draw();
                }
            }
        });
    }

    // Fechas del ejercicio: salen del mes de cierre de Parámetros de Empresa (ejercicio N = el que termina en el año N)
    function cargarRangoEjercicio() {
        $.ajax({
            url: 'contabilidad_cierre_ajax.php',
            type: 'GET',
            data: { accion: 'rango_ejercicio', empresa_id: $('#selEmpresaCierre').val(), anio_ejercicio: $('#txtAnioCierre').val() },
            dataType: 'json',
            success: function(resp) {
                if (resp.success && resp.data) {
                    $('#txtFechaInicio').val(resp.data.desde);
                    $('#txtFechaCierre').val(resp.data.hasta);
                }
            }
        });
    }

    cargarHistorialCierres();
    cargarRangoEjercicio();

    $('#selEmpresaCierre').on('change', function() {
        // La empresa del cliente pasa a ser el cliente activo del estudio (estudio_cliente_activo.js)
        if (window.EstudioCliente) { EstudioCliente.guardar($(this).find('option:selected').data('cliente')); }
        cargarHistorialCierres();
        cargarRangoEjercicio();
        resetDiagnostico();
    });

    $('#txtAnioCierre').on('change', function() {
        cargarRangoEjercicio();
        resetDiagnostico();
    });

    function resetDiagnostico() {
        $('#valTotalDebe, #valTotalHaber, #valDiferencia, #valResultadoNeto').text('$ 0,00');
        $('#badgeEstadoCuadre').removeClass('bg-success bg-danger').addClass('bg-secondary').text('Sin evaluar');
        $('#btnEjecutarCierre').prop('disabled', true);
    }

    // Diagnosticar Pre-Balance
    $('#btnDiagnosticar').on('click', function() {
        const empresaId = $('#selEmpresaCierre').val();
        const anio = $('#txtAnioCierre').val();

        Swal.fire({
            title: 'Analizando balance...',
            text: 'Verificando sumas, saldos y cuadratura contable.',
            didOpen: () => Swal.showLoading(),
            allowOutsideClick: false
        });

        $.ajax({
            url: 'contabilidad_cierre_ajax.php',
            type: 'GET',
            data: {
                accion: 'calcular_balance',
                empresa_id: empresaId,
                anio_ejercicio: anio
            },
            dataType: 'json',
            success: function(resp) {
                Swal.close();
                if (resp.success && resp.data && resp.data.totales) {
                    const t = resp.data.totales;
                    $('#valTotalDebe').text(formatoMoneda.format(t.total_debe));
                    $('#valTotalHaber').text(formatoMoneda.format(t.total_haber));
                    $('#valDiferencia').text(formatoMoneda.format(t.diferencia_cuadre));
                    $('#valResultadoNeto').text(formatoMoneda.format(t.resultado_neto));

                    if (t.esta_cuadrado) {
                        $('#badgeEstadoCuadre').removeClass('bg-secondary bg-danger').addClass('bg-success').html('<i class="fas fa-check-circle me-1"></i>Balance Cuadrado');
                        $('#btnEjecutarCierre').prop('disabled', false);
                        Swal.fire('Diagnóstico Exitoso', 'El balance se encuentra cuadrado. Puede proceder con el cierre.', 'success');
                    } else {
                        $('#badgeEstadoCuadre').removeClass('bg-secondary bg-success').addClass('bg-danger').html('<i class="fas fa-exclamation-triangle me-1"></i>Descuadrado');
                        $('#btnEjecutarCierre').prop('disabled', true);
                        Swal.fire('Atención', `Existe una diferencia de ${formatoMoneda.format(t.diferencia_cuadre)} entre el Debe y el Haber. Corrija los asientos previos antes de cerrar.`, 'warning');
                    }
                } else {
                    Swal.fire('Error', resp.error || resp.message || 'No se pudo calcular el balance.', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Fallo de conexión al calcular balance.', 'error');
            }
        });
    });

    // Ejecutar Cierre
    $('#btnEjecutarCierre').on('click', function() {
        const anio = $('#txtAnioCierre').val();
        const empresaId = $('#selEmpresaCierre').val();

        Swal.fire({
            title: `¿Confirmar Cierre de Ejercicio ${anio}?`,
            text: 'Se generarán de forma irreversible el asiento de refundición de cuentas de resultado y el asiento de cierre patrimonial.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, Ejecutar Cierre',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Procesando cierre contable...',
                    didOpen: () => Swal.showLoading(),
                    allowOutsideClick: false
                });

                $.ajax({
                    url: 'contabilidad_cierre_ajax.php',
                    type: 'POST',
                    data: {
                        accion: 'ejecutar_cierre',
                        empresa_id: empresaId,
                        anio_ejercicio: anio
                    },
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.success) {
                            Swal.fire({
                                title: '¡Ejercicio Cerrado!',
                                text: `Se crearon el Asiento de Refundición #${resp.data.asiento_refundicion_id} y Asiento Patrimonial #${resp.data.asiento_patrimonial_id}.`,
                                icon: 'success'
                            });
                            cargarHistorialCierres();
                            resetDiagnostico();
                        } else {
                            Swal.fire('Error al Cerrar', resp.error || resp.message || 'No se pudo completar el cierre.', 'error');
                        }
                    },
                    error: function() {
                        Swal.fire('Error', 'Fallo al procesar la solicitud de cierre.', 'error');
                    }
                });
            }
        });
    });
});
