/**
 * JavaScript: Mini-Gestión de Clientes
 * Módulo 4: Estudio Contable (minigestion.js)
 */

$(document).ready(function() {
    let tablaVentas = null;
    let tablaCompras = null;
    let clienteActual = null;

    const formatoMoneda = new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
        minimumFractionDigits: 2
    });

    // Inicializar DataTables con el patrón canónico sin botones de texto por defecto
    function initDataTables() {
        const domConfig = '<"row align-items-center p-2 mb-0"<"col-12 col-md-6"l><"col-12 col-md-6 text-end"f>>' +
                          '<"row"<"col-12"tr>>' +
                          '<"row align-items-center p-2 mt-2"<"col-12 col-md-5"i><"col-12 col-md-7 d-flex justify-content-end"p>>';

        const languageConfig = {
            url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/es-ES.json',
            emptyTable: 'No hay comprobantes registrados para este cliente y período'
        };

        // 1. Tabla Ventas
        tablaVentas = $('#tablaVentas').DataTable({
            responsive: true,
            dom: domConfig,
            language: languageConfig,
            order: [[0, 'desc']],
            columns: [
                { data: 'f_emision' },
                { 
                    data: null,
                    render: function(d) {
                        const pv = String(d.punto_venta || 1).padStart(4, '0');
                        const nro = String(d.numero || 0).padStart(8, '0');
                        const letra = d.letra || 'A';
                        return `<span class="badge bg-secondary me-1">${letra}</span> ${d.tipo_nombre} ${pv}-${nro}`;
                    }
                },
                { data: 'contraparte' },
                { data: 'cuit_contraparte' },
                { 
                    data: 'neto_gravado',
                    className: 'text-end fw-semibold',
                    render: d => formatoMoneda.format(d || 0)
                },
                { 
                    data: 'importe_iva',
                    className: 'text-end text-danger fw-semibold',
                    render: d => formatoMoneda.format(d || 0)
                },
                { 
                    data: 'no_gravado_exento',
                    className: 'text-end',
                    render: d => formatoMoneda.format(d || 0)
                },
                { 
                    data: 'importe_total',
                    className: 'text-end text-primary fw-bold',
                    render: d => formatoMoneda.format(d || 0)
                },
                { 
                    data: 'origen',
                    render: function(d) {
                        return d === 'ERP' 
                            ? '<span class="badge bg-success"><i class="fas fa-network-wired me-1"></i>ERP</span>'
                            : '<span class="badge bg-info"><i class="fas fa-edit me-1"></i>Mini-Gestión</span>';
                    }
                },
                { 
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function(d) {
                        if (d.origen === 'MANUAL') {
                            return `
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary btn-sm btn-editar-comp" data-id="${d.id}" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm btn-anular-comp" data-id="${d.id}" title="Anular">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            `;
                        } else {
                            return `
                                <span class="badge bg-light text-secondary border">Solo Lectura ERP</span>
                            `;
                        }
                    }
                }
            ],
            initComplete: function() {
                new $.fn.dataTable.Buttons(tablaVentas, {
                    buttons: [
                        { extend: 'copyHtml5', title: 'MiniGestion_Ventas' },
                        { extend: 'excelHtml5', title: 'MiniGestion_Ventas' },
                        { extend: 'csvHtml5', title: 'MiniGestion_Ventas' },
                        { extend: 'pdfHtml5', title: 'MiniGestion_Ventas', orientation: 'landscape' },
                        { extend: 'print', title: 'MiniGestion_Ventas' }
                    ]
                });
            }
        });

        // 2. Tabla Compras
        tablaCompras = $('#tablaCompras').DataTable({
            responsive: true,
            dom: domConfig,
            language: languageConfig,
            order: [[0, 'desc']],
            columns: [
                { data: 'f_emision' },
                { 
                    data: null,
                    render: function(d) {
                        const pv = String(d.punto_venta || 1).padStart(4, '0');
                        const nro = String(d.numero || 0).padStart(8, '0');
                        const letra = d.letra || 'A';
                        return `<span class="badge bg-secondary me-1">${letra}</span> ${d.tipo_nombre} ${pv}-${nro}`;
                    }
                },
                { data: 'contraparte' },
                { data: 'cuit_contraparte' },
                { 
                    data: 'neto_gravado',
                    className: 'text-end fw-semibold',
                    render: d => formatoMoneda.format(d || 0)
                },
                { 
                    data: 'importe_iva',
                    className: 'text-end text-success fw-semibold',
                    render: d => formatoMoneda.format(d || 0)
                },
                { 
                    data: 'percepciones',
                    className: 'text-end',
                    render: d => formatoMoneda.format(d || 0)
                },
                { 
                    data: 'importe_total',
                    className: 'text-end text-success fw-bold',
                    render: d => formatoMoneda.format(d || 0)
                },
                { 
                    data: 'origen',
                    render: function(d) {
                        return d === 'ERP' 
                            ? '<span class="badge bg-success"><i class="fas fa-network-wired me-1"></i>ERP</span>'
                            : '<span class="badge bg-info"><i class="fas fa-edit me-1"></i>Mini-Gestión</span>';
                    }
                },
                { 
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function(d) {
                        if (d.origen === 'MANUAL') {
                            return `
                                <div class="btn-group btn-group-sm">
                                    <button class="btn btn-outline-primary btn-sm btn-editar-comp" data-id="${d.id}" title="Editar">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm btn-anular-comp" data-id="${d.id}" title="Anular">
                                        <i class="fas fa-trash-alt"></i>
                                    </button>
                                </div>
                            `;
                        } else {
                            return `
                                <span class="badge bg-light text-secondary border">Solo Lectura ERP</span>
                            `;
                        }
                    }
                }
            ],
            initComplete: function() {
                new $.fn.dataTable.Buttons(tablaCompras, {
                    buttons: [
                        { extend: 'copyHtml5', title: 'MiniGestion_Compras' },
                        { extend: 'excelHtml5', title: 'MiniGestion_Compras' },
                        { extend: 'csvHtml5', title: 'MiniGestion_Compras' },
                        { extend: 'pdfHtml5', title: 'MiniGestion_Compras', orientation: 'landscape' },
                        { extend: 'print', title: 'MiniGestion_Compras' }
                    ]
                });
            }
        });

        // Enlace de botones de exportación Ventas
        $('#btnVentasRecargar').on('click', () => cargarDatos());
        $('#btnVentasCopiar').on('click', () => tablaVentas.button('.buttons-copy').trigger());
        $('#btnVentasExcel').on('click', () => tablaVentas.button('.buttons-excel').trigger());
        $('#btnVentasPdf').on('click', () => tablaVentas.button('.buttons-pdf').trigger());
        $('#btnVentasCsv').on('click', () => tablaVentas.button('.buttons-csv').trigger());
        $('#btnVentasImprimir').on('click', () => tablaVentas.button('.buttons-print').trigger());

        // Enlace de botones de exportación Compras
        $('#btnComprasRecargar').on('click', () => cargarDatos());
        $('#btnComprasCopiar').on('click', () => tablaCompras.button('.buttons-copy').trigger());
        $('#btnComprasExcel').on('click', () => tablaCompras.button('.buttons-excel').trigger());
        $('#btnComprasPdf').on('click', () => tablaCompras.button('.buttons-pdf').trigger());
        $('#btnComprasCsv').on('click', () => tablaCompras.button('.buttons-csv').trigger());
        $('#btnComprasImprimir').on('click', () => tablaCompras.button('.buttons-print').trigger());
    }

    initDataTables();

    // Eventos de Filtro
    $('#selCliente').on('change', function() {
        const clienteId = $(this).val();
        if (!clienteId) {
            $('#bannerModo').hide();
            limpiarTablas();
            return;
        }
        cargarDatos();
    });

    $('#filtroPeriodo').on('change', function() {
        if ($('#selCliente').val()) {
            cargarDatos();
        }
    });

    $('#btnActualizarDatos').on('click', function() {
        if ($('#selCliente').val()) {
            cargarDatos();
        } else {
            Swal.fire('Atención', 'Seleccione un cliente para ver sus comprobantes.', 'info');
        }
    });

    $('#btnLimpiarFiltros').on('click', function() {
        $('#filtroPeriodo').val('');
        if ($('#selCliente').val()) {
            cargarDatos();
        }
    });

    // Cargar información completa del cliente y sus comprobantes
    function cargarDatos() {
        const clienteId = $('#selCliente').val();
        const periodo = ($('#filtroPeriodo').val() || '').replace('-', '');

        if (!clienteId) return;

        // 1. Obtener Info Cliente y Configurar Modo
        $.ajax({
            url: 'minigestion_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_cliente_info', cliente_id: clienteId },
            dataType: 'json',
            success: function(resp) {
                if (resp.success && resp.data) {
                    clienteActual = resp.data;
                    actualizarBannerModo(clienteActual);
                    cargarVentas(clienteId, periodo);
                    cargarCompras(clienteId, periodo);
                    cargarTotales(clienteId, periodo);
                } else {
                    Swal.fire('Error', resp.message || 'No se pudo obtener la información del cliente.', 'error');
                }
            },
            error: function() {
                Swal.fire('Error', 'Fallo de conexión al consultar cliente.', 'error');
            }
        });
    }

    function actualizarBannerModo(c) {
        const $banner = $('#bannerModo');
        $banner.removeClass('alert-secondary alert-success alert-primary').show();

        if (c.modo === 'ERP') {
            $banner.addClass('alert-success');
            $('#bannerIcono').html('<i class="fas fa-network-wired text-success"></i>');
            $('#bannerTitulo').html(`Modo Visualización / Auditoría ERP: <strong>${c.razon_social}</strong>`);
            $('#bannerDescripcion').html(`Este cliente opera directamente en el ERP vinculado: <strong>${c.empresa_vinculada_nombre || 'Empresa ID ' + c.empresa_vinculada_id}</strong>. Los comprobantes se leen en tiempo real de su subdiario.`);
            $('#bannerBadge').html('<span class="badge bg-success fs-7"><i class="fas fa-lock me-1"></i>Solo Lectura ERP</span>');

            $('#btnNuevaVenta, #btnNuevaCompra').hide();
            $('#badgeAvisoErpVentas, #badgeAvisoErpCompras').show();
        } else {
            $banner.addClass('alert-primary');
            $('#bannerIcono').html('<i class="fas fa-edit text-primary"></i>');
            $('#bannerTitulo').html(`Modo Carga Mini-Gestión: <strong>${c.razon_social}</strong>`);
            $('#bannerDescripcion').html(`Cliente externo del estudio contable (no utiliza el sistema de gestión). El estudio carga sus facturas para liquidación de impuestos y generación de libros.`);
            $('#bannerBadge').html('<span class="badge bg-primary fs-7"><i class="fas fa-plus-circle me-1"></i>Modo Carga Activa</span>');

            $('#btnNuevaVenta, #btnNuevaCompra').show();
            $('#badgeAvisoErpVentas, #badgeAvisoErpCompras').hide();
        }
    }

    function cargarVentas(clienteId, periodo) {
        $.ajax({
            url: 'minigestion_ajax.php',
            type: 'GET',
            data: { accion: 'listar_ventas', cliente_id: clienteId, periodo_fiscal: periodo },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    tablaVentas.clear().rows.add(resp.data || []).draw();
                }
            }
        });
    }

    function cargarCompras(clienteId, periodo) {
        $.ajax({
            url: 'minigestion_ajax.php',
            type: 'GET',
            data: { accion: 'listar_compras', cliente_id: clienteId, periodo_fiscal: periodo },
            dataType: 'json',
            success: function(resp) {
                if (resp.success) {
                    tablaCompras.clear().rows.add(resp.data || []).draw();
                }
            }
        });
    }

    function cargarTotales(clienteId, periodo) {
        $.ajax({
            url: 'minigestion_ajax.php',
            type: 'GET',
            data: { accion: 'totales_fiscales', cliente_id: clienteId, periodo_fiscal: periodo },
            dataType: 'json',
            success: function(resp) {
                if (resp.success && resp.data) {
                    const t = resp.data;
                    $('#kpiVentasNeto').text(formatoMoneda.format(t.ventas_neto));
                    $('#kpiIvaDebito').text(formatoMoneda.format(t.iva_debito));
                    $('#kpiComprasNeto').text(formatoMoneda.format(t.compras_neto));
                    $('#kpiPosicionIva').text(formatoMoneda.format(Math.abs(t.posicion_final)));

                    $('#kpiCantVentas').text(`${t.cant_ventas} comprobantes`);
                    $('#kpiCantCompras').text(`${t.cant_compras} comprobantes`);

                    if (t.posicion_final > 0) {
                        $('#kpiSaldoDetalle').removeClass('bg-secondary bg-success').addClass('bg-danger').text('A Ingresar (AFIP)');
                    } else if (t.posicion_final < 0) {
                        $('#kpiSaldoDetalle').removeClass('bg-secondary bg-danger').addClass('bg-success').text('Saldo Técnico a Favor');
                    } else {
                        $('#kpiSaldoDetalle').removeClass('bg-success bg-danger').addClass('bg-secondary').text('Equilibrado');
                    }

                    // Pestaña Resumen
                    $('#resVentasNeto').text(formatoMoneda.format(t.ventas_neto));
                    $('#resIvaDebito').text(formatoMoneda.format(t.iva_debito));
                    $('#resComprasNeto').text(formatoMoneda.format(t.compras_neto));
                    $('#resIvaCredito').text(formatoMoneda.format(t.iva_credito));
                    $('#resSaldoTecnico').text(formatoMoneda.format(t.saldo_tecnico));
                    $('#resPercepciones').text(formatoMoneda.format(t.percepciones));
                    $('#resPosicionFinal').text(formatoMoneda.format(t.posicion_final));
                }
            }
        });
    }

    function limpiarTablas() {
        tablaVentas.clear().draw();
        tablaCompras.clear().draw();
        $('#kpiVentasNeto, #kpiIvaDebito, #kpiComprasNeto, #kpiPosicionIva').text('$ 0,00');
        $('#kpiCantVentas, #kpiCantCompras').text('0 comprobantes');
        $('#kpiSaldoDetalle').removeClass('bg-success bg-danger').addClass('bg-secondary').text('Sin selección');
    }

    // Modal de Carga de Comprobante Manual
    $('#btnNuevaVenta').on('click', function() {
        abrirModalComprobante('VENTA');
    });

    $('#btnNuevaCompra').on('click', function() {
        abrirModalComprobante('COMPRA');
    });

    function abrirModalComprobante(tipo, datos = null) {
        if (!clienteActual) {
            Swal.fire('Atención', 'Seleccione un cliente primero.', 'warning');
            return;
        }

        $('#formComprobante')[0].reset();
        $('#comp_id').val(datos ? datos.comprobante_id : 0);
        $('#comp_cliente_id').val(clienteActual.cliente_id);
        $('#comp_tipo_operacion').val(tipo);
        $('#comp_tipo_op_display').val(tipo === 'VENTA' ? 'VENTA (Emitido por el Cliente)' : 'COMPRA (Recibido por el Cliente)');

        if (tipo === 'VENTA') {
            $('#modalComprobanteLabel').html('<i class="fas fa-sign-out-alt me-1 text-primary"></i>Cargar Comprobante de Venta');
            $('#lblContraparteCuit').text('CUIT Cliente Receptor');
            $('#lblContraparteNombre').text('Razón Social Cliente Receptor');
        } else {
            $('#modalComprobanteLabel').html('<i class="fas fa-sign-in-alt me-1 text-success"></i>Cargar Comprobante de Compra');
            $('#lblContraparteCuit').text('CUIT Proveedor Emisor');
            $('#lblContraparteNombre').text('Razón Social Proveedor Emisor');
        }

        if (datos) {
            $('#comp_f_emision').val(datos.f_emision);
            $('#comp_tipo_id').val(datos.comprobante_tipo_id);
            $('#comp_letra').val(datos.comprobante_letra);
            $('#comp_pv').val(datos.punto_venta);
            $('#comp_nro').val(datos.comprobante_nro);
            $('#comp_cuit').val(datos.cuit_contraparte);
            $('#comp_razon').val(datos.razon_social_contraparte);
            $('#comp_neto').val(datos.neto_gravado);
            $('#comp_alicuota').val(datos.alicuota_iva);
            $('#comp_iva').val(datos.importe_iva);
            $('#comp_nograv').val(datos.no_gravado_exento);
            $('#comp_percep_iibb').val(datos.percepcion_iibb);
            $('#comp_percep_iva').val(datos.percepcion_iva);
            $('#comp_total').val(datos.importe_total);
            $('#comp_obs').val(datos.observaciones || '');
        } else {
            $('#comp_f_emision').val(new Date().toISOString().substring(0, 10));
            $('#comp_pv').val(1);
            $('#comp_neto, #comp_iva, #comp_nograv, #comp_percep_iibb, #comp_percep_iva, #comp_total').val('0.00');
        }

        $('#modalComprobante').modal('show');
    }

    // Auto-cálculo de IVA y Totales en Modal
    $('#comp_neto, #comp_alicuota').on('input change', function() {
        const neto = parseFloat($('#comp_neto').val()) || 0;
        const alic = parseFloat($('#comp_alicuota').val()) || 0;
        const iva = Math.round((neto * alic / 100) * 100) / 100;
        $('#comp_iva').val(iva.toFixed(2));
        recalcularTotalModal();
    });

    $('#comp_nograv, #comp_percep_iibb, #comp_percep_iva, #comp_iva').on('input change', function() {
        recalcularTotalModal();
    });

    function recalcularTotalModal() {
        const neto = parseFloat($('#comp_neto').val()) || 0;
        const iva = parseFloat($('#comp_iva').val()) || 0;
        const nograv = parseFloat($('#comp_nograv').val()) || 0;
        const pIibb = parseFloat($('#comp_percep_iibb').val()) || 0;
        const pIva = parseFloat($('#comp_percep_iva').val()) || 0;
        const tot = neto + iva + nograv + pIibb + pIva;
        $('#comp_total').val(tot.toFixed(2));
    }

    $('#comp_tipo_id').on('change', function() {
        const letra = $(this).find('option:selected').data('letra') || 'A';
        $('#comp_letra').val(letra);
    });

    // Guardar Comprobante Manual (POST)
    $('#formComprobante').on('submit', function(e) {
        e.preventDefault();

        const formData = $(this).serialize();
        $('#btnGuardarComprobante').prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: 'minigestion_ajax.php?accion=guardar_comprobante',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(resp) {
                $('#btnGuardarComprobante').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Comprobante');
                if (resp.success) {
                    $('#modalComprobante').modal('hide');
                    Swal.fire('Guardado', 'Comprobante registrado exitosamente.', 'success');
                    cargarDatos();
                } else {
                    Swal.fire('Error', resp.message || 'No se pudo guardar el comprobante.', 'error');
                }
            },
            error: function() {
                $('#btnGuardarComprobante').prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Comprobante');
                Swal.fire('Error', 'Fallo al procesar la solicitud.', 'error');
            }
        });
    });

    // Editar Comprobante Manual
    $(document).on('click', '.btn-editar-comp', function() {
        const compId = $(this).data('id');
        $.ajax({
            url: 'minigestion_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_comprobante', comprobante_id: compId },
            dataType: 'json',
            success: function(resp) {
                if (resp.success && resp.data) {
                    abrirModalComprobante(resp.data.tipo_operacion, resp.data);
                } else {
                    Swal.fire('Error', 'No se pudo cargar el comprobante.', 'error');
                }
            }
        });
    });

    // Anular Comprobante Manual
    $(document).on('click', '.btn-anular-comp', function() {
        const compId = $(this).data('id');
        Swal.fire({
            title: '¿Anular comprobante?',
            text: 'Esta acción no se puede deshacer.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, anular',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: 'minigestion_ajax.php',
                    type: 'POST',
                    data: { accion: 'anular_comprobante', comprobante_id: compId },
                    dataType: 'json',
                    success: function(resp) {
                        if (resp.success) {
                            Swal.fire('Anulado', 'El comprobante ha sido anulado.', 'success');
                            cargarDatos();
                        } else {
                            Swal.fire('Error', resp.message || 'No se pudo anular.', 'error');
                        }
                    }
                });
            }
        });
    });

    // Botones de exportación consolidada en Tab 3
    $('#btnExportarLibroVentas').on('click', function() {
        tablaVentas.button('.buttons-excel').trigger();
    });

    $('#btnExportarLibroCompras').on('click', function() {
        tablaCompras.button('.buttons-excel').trigger();
    });
});
