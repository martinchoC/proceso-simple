$(document).ready(function () {
    const empresa_idx = EMPRESA_ID;
    const pagina_idx = PAGINA_ID;
    
    var tabla;
    var currentPage = 0;
    var currentOrder = [[1, 'asc']];
    var currentSearch = '';
    
    // Variables para manejo de detalles
    var detalles = [];
    var clienteActualId = null;
    var clienteSucursalActualId = null;
    var clienteCondicionComercial = null; // { lista_precio_id, condicion_pago_id, cliente_descuento_general, limite_credito }
    var timeoutBusqueda = null;
    var selectedIndex = -1;

    // Trae la condición comercial vigente del cliente (gestion__entidades_condiciones_clientes).
    // Si autoAplicarCondicionPago es true, además completa #condicion_pago_id (uso: selección manual
    // de cliente). Si es false, solo guarda el dato para calcular descuentos al agregar productos,
    // sin pisar la condición de pago ya guardada en el pedido (uso: cargar un pedido existente).
    function cargarCondicionesComercialesCliente(entidadId, autoAplicarCondicionPago) {
        clienteCondicionComercial = null;

        if (!entidadId) {
            $('#descuento_general_pct').val('0');
            return;
        }

        $.get('ventas_pedidos_ajax.php', {
            accion: 'obtener_condiciones_cliente',
            entidad_id: entidadId,
            empresa_idx: empresa_idx
        }, function(res) {
            if (res && res.success && res.data) {
                clienteCondicionComercial = res.data;

                if (autoAplicarCondicionPago && res.data.condicion_pago_id) {
                    var opcion = $('#condicion_pago_id option[value="' + res.data.condicion_pago_id + '"]');
                    if (opcion.length) {
                        $('#condicion_pago_id').val(res.data.condicion_pago_id);
                    }
                }

                if (autoAplicarCondicionPago) {
                    var descuentoPct = parseFloat(res.data.cliente_descuento_general) || 0;
                    $('#descuento_general_pct').val(descuentoPct);

                    // Si ya había productos agregados (cambio de cliente sobre un pedido en curso),
                    // recalcular cada línea con el nuevo descuento general.
                    if (detalles.length > 0) {
                        recalcularDetallesConDescuento(descuentoPct);
                    }
                }
            } else if (autoAplicarCondicionPago) {
                $('#descuento_general_pct').val('0');
            }
        }, 'json');
    }

    // Recalcula descuento_general, precio_unitario_neto, neto_gravado, iva_importe y total_linea
    // de cada línea ya agregada, a partir de su precio_unitario (bruto, sin tocar) y el nuevo
    // porcentaje de descuento general del cliente.
    function recalcularDetallesConDescuento(descuentoPct) {
        detalles.forEach(function(detalle) {
            var descuentoGeneralImporte = detalle.precio_unitario * (descuentoPct / 100);
            var precioUnitarioNeto = detalle.precio_unitario - descuentoGeneralImporte;
            var netoGravado = detalle.cantidad * precioUnitarioNeto;
            var ivaImporte = netoGravado * (detalle.iva_porcentaje / 100);

            detalle.descuento_general_pct = descuentoPct;
            detalle.descuento_general = descuentoGeneralImporte;
            detalle.precio_unitario_neto = precioUnitarioNeto;
            detalle.neto_gravado = netoGravado;
            detalle.iva_importe = ivaImporte;
            detalle.total_linea = netoGravado + ivaImporte + (detalle.no_gravado || 0) + (detalle.exento || 0);
        });

        renderizarDetalles();
        actualizarTotales();
    }

    // ========== FUNCIONES DE DATATABLE CON FILTROS POR COLUMNA ==========
    function inicializarDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaVentasPedidos')) {
            $('#tablaVentasPedidos').DataTable().destroy();
            $('#tablaVentasPedidos tbody').empty();
        }

        tabla = $('#tablaVentasPedidos').DataTable({
            ajax: {
                url: 'ventas_pedidos_ajax.php',
                type: 'GET',
                data: {
                    accion: 'listar',
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                },
                dataSrc: ''
            },
            stateSave: true,
            stateSaveParams: function (settings, data) {
                data.page = currentPage;
                data.order = currentOrder;

                if (currentSearch !== '-1' && currentSearch !== '') {
                    data.search = { search: currentSearch };
                } else {
                    data.search = { search: '' };
                }

                delete data.columns;
                return data;
            },
            stateLoadParams: function (settings, data) {
                if (data.page !== undefined) currentPage = data.page;
                if (data.order !== undefined && data.order.length > 0) currentOrder = data.order;

                if (data.search && data.search.search !== undefined) {
                    var searchValue = data.search.search;
                    if (searchValue === '-1' || searchValue === '-1' || searchValue === '') {
                        currentSearch = '';
                    } else {
                        currentSearch = searchValue;
                    }
                } else {
                    currentSearch = '';
                }

                data.search = { search: currentSearch };
            },
            stateLoadCallback: function (settings) {
                var savedData = localStorage.getItem('DataTables_' + settings.sInstance);
                if (savedData) {
                    var data = JSON.parse(savedData);

                    if (data.search && (data.search.search === '-1' || data.search.search === '')) {
                        data.search.search = '';
                    }

                    if (data.columns) {
                        $.each(data.columns, function (i, col) {
                            if (col.search && col.search.search === '-1') {
                                col.search.search = '';
                            }
                        });
                    }

                    return data;
                }
                return null;
            },
            dom: '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>' +
                '<"clear">',
            pageLength: 50,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            
            columns: [
                {
                    data: 'comprobante_tipo',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (type === 'export') {
                            return data || '';
                        }
                        return `<span>${data || ''}</span>`;
                    }
                },
                {
                    data: 'sucursal_nombre',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (type === 'export') {
                            return data || '';
                        }
                        return `<span>${data || ''}</span>`;
                    }
                },
                {
                    data: 'punto_venta_nombre',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (type === 'export') {
                            return data || '';
                        }
                        return `<span>${data || ''}</span>`;
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    render: function(data, type, row) {
                        let numero = '';
                        numero += row.comprobante_nro || '';
                        if (type === 'export') {
                            return numero;
                        }
                        return `<span>${numero}</span>`;
                    }
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        if (type === 'export') {
                            return data.entidad_nombre || '';
                        }
                        return `<div>${data.entidad_nombre || ''}</div>
                                <small class="text-muted">${data.entidad_fantasia || ''}</small>`;
                    }
                },
                {
                    data: 'f_emision',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (type === 'export') {
                            return data;
                        }
                        if (!data) return '';
                        let parts = data.split('-');
                        if (parts.length === 3) {
                            return `${parts[2]}/${parts[1]}/${parts[0]}`;
                        }
                        return data;
                    }
                },
                {
                    data: 'f_entrega_estimada',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (type === 'export' || !data) {
                            return data || '';
                        }
                        let parts = data.split('-');
                        if (parts.length === 3) {
                            return `${parts[2]}/${parts[1]}/${parts[0]}`;
                        }
                        return data;
                    }
                },
                {
                    data: 'total',
                    className: 'text-end',
                    render: function(data, type, row) {
                        var valor = parseFloat(data) || 0;
                        if (type === 'export') {
                            return valor.toFixed(2);
                        }
                        if (type === 'sort' || type === 'filter') {
                            return valor;
                        }
                        return `<span class="text-primary">$${valor.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>`;
                    }
                },
                {
                    data: 'estado_info',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (type === 'export') {
                            return (data && data.estado_registro) ? data.estado_registro : '';
                        }
                        
                        var estadoTexto = (data && data.estado_registro) ? data.estado_registro : '';
                        return `<span>${estadoTexto}</span>`;
                    }
                },
                {
                    data: 'botones',
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    width: '250px',
                    render: function(data, type, row) {
                        var botones = '';

                        if (data && data.length > 0) {
                            var editarBoton = '';
                            var otrosBotones = '';

                            data.forEach(boton => {
                                var claseBoton = 'btn-sm me-1 ';
                                if (boton.bg_clase && boton.text_clase) {
                                    claseBoton += boton.bg_clase + ' ' + boton.text_clase;
                                } else if (boton.color_clase) {
                                    claseBoton += boton.color_clase;
                                } else {
                                    claseBoton += 'btn-outline-primary';
                                }

                                var titulo = boton.descripcion || boton.nombre_funcion;
                                var accionJs = boton.accion_js;
                                var icono = boton.icono_clase ? `<i class="${boton.icono_clase}"></i>` : '';
                                var esConfirmable = boton.es_confirmable || 0;
                                
                                var comprobanteInfo = `${row.comprobante_tipo || ''} -${row.comprobante_nro || ''}`;
                                var clienteInfo = row.entidad_nombre || row.entidad_fantasia || '';

                                var botonHtml = `<button type="button" class="btn ${claseBoton} btn-accion" 
                                                title="${titulo}" 
                                                data-id="${row.venta_pedido_id}" 
                                                data-accion="${accionJs}"
                                                data-confirmable="${esConfirmable}"
                                                data-comprobante="${comprobanteInfo}"
                                                data-cliente="${clienteInfo}">
                                                ${icono}
                                            </button>`;

                                if (accionJs === 'editar') {
                                    editarBoton = botonHtml;
                                } else if (accionJs === 'imprimir') {
                                    otrosBotones += botonHtml;
                                } else {
                                    otrosBotones += botonHtml;
                                }
                            });

                            botones = editarBoton + otrosBotones;
                        } else {
                            botones = '<span class="text-muted small">Sin acciones</span>';
                        }

                        return `<div class="btn-group" role="group">${botones}</div>`;
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json'                
            },
            order: currentOrder,
            responsive: true,
            createdRow: function (row, data, dataIndex) {
                if (data.estado_info && data.estado_info.codigo_estandar === 'CONFIRMADO') {
                    $(row).addClass('table-success');
                } else if (data.estado_info && data.estado_info.codigo_estandar === 'CANCELADO') {
                    $(row).addClass('table-danger');
                } else if (data.estado_info && data.estado_info.codigo_estandar === 'PENDIENTE') {
                    $(row).addClass('table-warning');
                }
            },
            initComplete: function () {
                setTimeout(function() {
                    var lengthControl = $('#tablaVentasPedidos_length').detach();
                    $('#tablaVentasPedidos_length').replaceWith(lengthControl);
                    
                    var filterControl = $('#tablaVentasPedidos_filter').detach();
                    $('#tablaVentasPedidos_filter').replaceWith(filterControl);
                    
                    $('#tablaVentasPedidos_length').addClass('dataTables_length_custom');
                    $('#tablaVentasPedidos_filter').addClass('dataTables_filter_custom');
                    
                    if ($('#tablaVentasPedidos_length').html().trim() === '') {
                        var selectHtml = '<label>Mostrar <select name="tablaVentasPedidos_length" aria-controls="tablaVentasPedidos" class="form-select form-select-sm"><option value="10">10</option><option value="25">25</option><option value="50" selected="">50</option><option value="100">100</option><option value="-1">Todos</option></select> registros</label>';
                        $('#tablaVentasPedidos_length').html(selectHtml);
                        
                        $('#tablaVentasPedidos_length select').on('change', function() {
                            tabla.page.len($(this).val()).draw();
                        });
                    }
                    
                    if ($('#tablaVentasPedidos_filter').html().trim() === '') {
                        var filterHtml = '<label>Buscar:<input type="search" class="form-control form-control-sm" placeholder="" aria-controls="tablaVentasPedidos"></label>';
                        $('#tablaVentasPedidos_filter').html(filterHtml);
                        
                        $('#tablaVentasPedidos_filter input').on('keyup', function() {
                            tabla.search($(this).val()).draw();
                        });
                    }
                }, 100);

                $('#filtro_cliente').on('keyup', function() {
                    tabla.column(4).search(this.value).draw();
                });

                $('#filtro_estado').on('keyup', function() {
                    tabla.column(8).search(this.value).draw();
                });

                var buttons = new $.fn.dataTable.Buttons(tabla, {
                    buttons: ['excelHtml5', 'pdfHtml5', 'csvHtml5', 'print']
                }).container().appendTo($('#tablaVentasPedidos_wrapper .col-md-6:eq(1)'));

                $(tabla.table().container()).on('page.dt', function (e) {
                    currentPage = tabla.page();
                });

                $(tabla.table().container()).on('order.dt', function (e, settings, details) {
                    currentOrder = tabla.order();
                });

                $(tabla.table().container()).on('search.dt', function (e, settings) {
                    currentSearch = tabla.search();
                });

                setTimeout(function () {
                    var searchInput = $('.dataTables_filter input');
                    if (searchInput.val() === '-1' || searchInput.val() === '') {
                        searchInput.val('');
                        currentSearch = '';

                        var savedData = localStorage.getItem('DataTables_' + tabla.settings()[0].sInstance);
                        if (savedData) {
                            var data = JSON.parse(savedData);
                            if (data.search && (data.search.search === '-1' || data.search.search === '')) {
                                data.search.search = '';
                                localStorage.setItem('DataTables_' + tabla.settings()[0].sInstance, JSON.stringify(data));
                            }
                        }
                    }
                }, 100);
            }
        });

        inicializarEventos();
    }

    function inicializarEventos() {
        $('#btnRecargar').off('click').on('click', function () {
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            var savedState = {
                page: tabla.page(),
                order: tabla.order(),
                search: tabla.search()
            };

            tabla.ajax.reload(function (json) {
                if (savedState.page !== undefined) {
                    tabla.page(savedState.page).draw('page');
                }
                if (savedState.search && savedState.search !== '') {
                    tabla.search(savedState.search).draw();
                }
                btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
            }, false);
        });

        $('#btnExportarExcel').on('click', function() {
            tabla.button('.buttons-excel').trigger();
        });

        $('#btnExportarPDF').on('click', function() {
            tabla.button('.buttons-pdf').trigger();
        });

        $('#btnExportarCSV').on('click', function() {
            tabla.button('.buttons-csv').trigger();
        });

        $('#btnExportarPrint').on('click', function() {
            tabla.button('.buttons-print').trigger();
        });
    }

    function cargarBotonAgregar() {
        console.log('Cargando botón agregar...');
        $.get('ventas_pedidos_ajax.php', {
            accion: 'obtener_boton_agregar',
            pagina_idx: pagina_idx
        }, function (botonAgregar) {
            console.log('Botón agregar recibido:', botonAgregar);
            if (botonAgregar && botonAgregar.nombre_funcion) {
                var icono = botonAgregar.icono_clase ? `<i class="${botonAgregar.icono_clase} me-1"></i>` : '';

                var colorClase = 'btn-primary';
                if (botonAgregar.bg_clase && botonAgregar.text_clase) {
                    colorClase = botonAgregar.bg_clase + ' ' + botonAgregar.text_clase;
                } else if (botonAgregar.color_clase) {
                    colorClase = botonAgregar.color_clase;
                }

                var htmlBoton = `<button type="button" class="btn ${colorClase}" id="btnNuevo">
                                    ${icono}${botonAgregar.nombre_funcion}
                                </button>`;
                console.log('HTML botón:', htmlBoton);
                $('#contenedor-boton-agregar').html(htmlBoton);
            } else {
                // Fallback: mostrar un botón por defecto
                $('#contenedor-boton-agregar').html(
                    '<button type="button" class="btn btn-primary" id="btnNuevo">' +
                    '<i class="fas fa-plus me-1"></i>Nuevo Pedido</button>'
                );
            }
        }, 'json').fail(function(xhr, status, error) {
            console.error('Error cargando botón agregar:', error);
            // Fallback en caso de error
            $('#contenedor-boton-agregar').html(
                '<button type="button" class="btn btn-primary" id="btnNuevo">' +
                '<i class="fas fa-plus me-1"></i>Nuevo Pedido</button>'
            );
        });
    }

    function cargarPuntosVenta(sucursalId, callback) {
        if (!sucursalId) {
            $('#punto_venta_id').html('<option value="">Primero seleccione sucursal</option>');
            $('#punto_venta_id').prop('disabled', true);
            if (callback) callback();
            return;
        }
        
        $.ajax({
            url: 'ventas_pedidos_ajax.php',
            type: 'GET',
            data: {
                accion: 'obtener_puntos_venta',
                sucursal_id: sucursalId,
                empresa_idx: empresa_idx
            },
            dataType: 'json',
            success: function(data) {
                var options = '<option value="">Seleccionar punto de venta</option>';
                
                if (data && data.length > 0) {
                    data.forEach(function(item) {
                        options += `<option value="${item.punto_venta_id}">${item.punto_venta_nombre} (${item.punto_venta_codigo})</option>`;
                    });
                    $('#punto_venta_id').prop('disabled', false);
                } else {
                    options = '<option value="">No hay puntos de venta disponibles</option>';
                    $('#punto_venta_id').prop('disabled', true);
                }
                
                $('#punto_venta_id').html(options);
                
                if (callback) callback();
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("Error cargando puntos de venta:", textStatus, errorThrown);
                $('#punto_venta_id').html('<option value="">Error al cargar</option>');
                $('#punto_venta_id').prop('disabled', true);
                if (callback) callback();
            }
        });
    }

    function cargarClientesYSucursales() {
        $.ajax({
            url: 'ventas_pedidos_ajax.php',
            type: 'GET',
            data: {
                accion: 'obtener_clientes_con_sucursales',
                empresa_idx: empresa_idx
            },
            dataType: 'json',
            success: function(data) {
                var options = '<option value="">Seleccionar cliente o sucursal</option>';
                
                if (data && data.length > 0) {
                    data.forEach(function(item) {
                        if (item.sucursales && item.sucursales.length > 0) {
                            item.sucursales.forEach(function(sucursal) {
                                options += `<option value="S-${sucursal.sucursal_id}" data-entidad-id="${item.entidad_id}" data-sucursal-id="${sucursal.sucursal_id}">${item.entidad_nombre} - ${sucursal.sucursal_nombre}</option>`;
                            });
                        } else {
                            options += `<option value="P-${item.entidad_id}" data-entidad-id="${item.entidad_id}" data-sucursal-id="">${item.entidad_nombre}</option>`;
                        }
                    });
                } else {
                    options = '<option value="">No hay clientes disponibles</option>';
                }
                
                $('#entidad_combo').html(options);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("Error cargando clientes y sucursales:", textStatus, errorThrown);
                $('#entidad_combo').html('<option value="">Error al cargar</option>');
            }
        });
    }

    $('#sucursal_id').on('change', function() {
        var sucursalId = $(this).val();
        cargarPuntosVenta(sucursalId);
    });

    $('#entidad_combo').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var entidadId = selectedOption.data('entidad-id');
        var sucursalId = selectedOption.data('sucursal-id');
        var valorCombo = $(this).val();
        
        if (valorCombo) {
            var partes = valorCombo.split('-');
            var tipo = partes[0];
            
            if (tipo === 'P') {
                $('#entidad_id').val(entidadId);
                $('#entidad_sucursal_id').val('');
                clienteActualId = parseInt(entidadId);
                clienteSucursalActualId = null;
                
                var clienteNombre = selectedOption.text();
                $('#cliente_actual_nombre').text(clienteNombre);
                cargarCondicionesComercialesCliente(clienteActualId, true);
                
            } else if (tipo === 'S') {
                $('#entidad_id').val(entidadId);
                $('#entidad_sucursal_id').val(sucursalId);
                clienteActualId = parseInt(entidadId);
                clienteSucursalActualId = parseInt(sucursalId);
                
                var textoCompleto = selectedOption.text();
                $('#cliente_actual_nombre').text(textoCompleto);
                cargarCondicionesComercialesCliente(clienteActualId, true);
            }
            
        } else {
            $('#entidad_id').val('');
            $('#entidad_sucursal_id').val('');
            clienteActualId = null;
            clienteSucursalActualId = null;
            clienteCondicionComercial = null;
            $('#cliente_actual_nombre').text('No seleccionado');
        }
    });

    function imprimirComprobante(pedidoId) {
        var url = 'ventas_pedidos_print.php?venta_pedido_id=' + pedidoId + '&empresa_idx=' + empresa_idx;
        window.open(url, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
    }

    function cargarPedidoParaVisualizar(pedidoId) {
        $.ajax({
            url: 'ventas_pedidos_ajax.php',
            type: 'GET',
            data: {
                accion: 'obtener',
                venta_pedido_id: pedidoId,
                empresa_idx: empresa_idx
            },
            dataType: 'json',
            success: function (res) {
            if (res && res.venta_pedido_id) {
                resetModal();
                
                cargarCombosFormulario();
                cargarClientesYSucursales();
                
                $('#venta_pedido_id').val(res.venta_pedido_id);
                $('#comprobante_nro').val(res.comprobante_nro);
                
                $('#f_emision').val(res.f_emision);
                $('#f_entrega_estimada').val(res.f_entrega_estimada);
                $('#direccion_entrega').val(res.direccion_entrega);
                $('#observaciones').val(res.observaciones);
                $('#tipo_cambio').val(res.tipo_cambio || '1.000000');
                $('#descuento_general_pct').val(res.descuento_general_pct || '0');
                $('#total_neto').val(res.subtotal || 0);
                $('#no_gravado').val(res.no_gravado || 0);
                $('#exento').val(res.exento || 0);
                $('#impuestos').val(res.impuestos || 0);
                $('#total').val(res.total || 0);
                
                $('#total_neto_display').text(parseFloat(res.subtotal || 0).toFixed(2));
                $('#no_gravado_display').text(parseFloat(res.no_gravado || 0).toFixed(2));
                $('#exento_display').text(parseFloat(res.exento || 0).toFixed(2));
                $('#impuestos_display').text(parseFloat(res.impuestos || 0).toFixed(2));
                $('#total_display').text(parseFloat(res.total || 0).toFixed(2));

                $('#modalLabel').text('Visualizar Pedido de Venta');

                setTimeout(function() {
                    $('#comprobante_tipo_id').val(res.comprobante_tipo_id);
                    $('#moneda_id').val(res.moneda_id);
                    $('#condicion_pago_id').val(res.condicion_pago_id);
                    
                    if (res.sucursal_id) {
                        $('#sucursal_id').val(res.sucursal_id);
                        cargarPuntosVenta(res.sucursal_id, function() {
                            if (res.punto_venta_id) {
                                $('#punto_venta_id').val(res.punto_venta_id);
                            }
                        });
                    }
                    
                    if (res.entidad_id) {
                        clienteActualId = res.entidad_id;
                        cargarCondicionesComercialesCliente(res.entidad_id, false);
                        
                        if (res.entidad_sucursal_id && res.entidad_sucursal_id > 0) {
                            $('#entidad_combo').val('S-' + res.entidad_sucursal_id);
                            clienteSucursalActualId = parseInt(res.entidad_sucursal_id);
                        } else {
                            $('#entidad_combo').val('P-' + res.entidad_id);
                            clienteSucursalActualId = null;
                        }
                        
                        var textoSeleccionado = $('#entidad_combo option:selected').text();
                        $('#cliente_actual_nombre').text(textoSeleccionado || 'No seleccionado');
                    }
                    
                    if (res.detalles && res.detalles.length > 0) {
                        detalles = res.detalles.map(function(detalle, index) {
                            return {
                                detalle_idx: index,
                                venta_pedido_detalle_id: detalle.venta_pedido_detalle_id,
                                producto_id: detalle.producto_id,
                                producto_codigo: detalle.producto_codigo,
                                producto_nombre: detalle.producto_nombre,
                                cantidad: detalle.cantidad,
                                cantidad_entregada: detalle.cantidad_entregada || 0,
                                precio_unitario: detalle.precio_unitario,
                                no_gravado: detalle.no_gravado || 0,
                                exento: detalle.exento || 0,
                                iva_alicuota_id: detalle.iva_alicuota_id,
                                iva_porcentaje: detalle.iva_porcentaje,
                                neto_gravado: detalle.neto_gravado,
                                iva_importe: detalle.iva_importe,
                                total_linea: detalle.total_linea
                            };
                        });
                        renderizarDetalles();
                        actualizarTotales();
                    }

                    $('#formVentaPedido :input').prop('disabled', true);
                    $('.btn-eliminar-detalle, .btn-editar-detalle, #btnAgregarProducto, #btnNuevoProductoRapido, #btnNuevoCliente').prop('disabled', true);
                    
                    $('.btn-editar-detalle, .btn-eliminar-detalle, #btnAgregarProducto, #btnNuevoProductoRapido').hide();
                    $('#busqueda_producto').prop('disabled', true);
                    
                    $('.card-primary').hide();
                    $('#btnNuevoProductoRapido').hide();
                    
                    $('#btnGuardar').hide();
                    $('.modal-footer .btn-secondary').hide();
                    
                    $('.btn-secondary[data-bs-dismiss="modal"]').hide();
                    
                    $('#btnToggleFullscreen').prop('disabled', false);
                    
                }, 500);

                var modal = new bootstrap.Modal(document.getElementById('modalVentaPedido'));
                modal.show();

                $('#modalVentaPedido').off('hidden.bs.modal').on('hidden.bs.modal', function () {
                    $('#formVentaPedido :input').prop('disabled', false);
                    $('#btnGuardar').show();
                    $('.modal-footer .btn-secondary').show();
                    $('.btn-eliminar-detalle, .btn-editar-detalle, #btnAgregarProducto, #btnNuevoProductoRapido, #btnNuevoCliente').prop('disabled', false);
                    
                    $('.card-primary').show();
                    $('#btnNuevoProductoRapido').show();
                    
                    $('.btn-secondary[data-bs-dismiss="modal"]').show();
                });

            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos del pedido",
                    confirmButtonText: "Entendido"
                });
            }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error('Error en obtener (visualizar):', textStatus, errorThrown);
                console.error('Respuesta cruda del servidor:', jqXHR.responseText);
                Swal.fire({
                    icon: "error",
                    title: "Error al visualizar el pedido",
                    text: "Revisá la consola del navegador (F12) para ver el detalle.",
                    confirmButtonText: "Entendido"
                });
            }
        });
    }

    $(document).on('click', '.btn-accion', function () {
        var pedidoId = $(this).data('id');
        var accionJs = $(this).data('accion');
        var confirmable = $(this).data('confirmable');
        var comprobanteInfo = $(this).data('comprobante') || 'Pedido #' + pedidoId;
        var clienteInfo = $(this).data('cliente') || '';

        if (accionJs === 'editar') {
            cargarPedidoParaEditar(pedidoId);
        } else if (accionJs === 'visualizar') {
            cargarPedidoParaVisualizar(pedidoId);
        } else if (accionJs === 'imprimir') {
            imprimirComprobante(pedidoId);
        } else if (confirmable == 1) {
            Swal.fire({
                title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                html: `¿Está seguro de <strong>${accionJs}</strong> el pedido<br>
                    <strong>${comprobanteInfo}</strong>?<br>
                    <small class="text-muted">Cliente: ${clienteInfo}</small>`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: `Sí, ${accionJs}`,
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    ejecutarAccion(pedidoId, accionJs, comprobanteInfo);
                }
            });
        } else {
            ejecutarAccion(pedidoId, accionJs, comprobanteInfo);
        }
    });

    function ejecutarAccion(pedidoId, accionJs, comprobanteInfo) {
        var savedState = {
            page: tabla.page(),
            order: tabla.order(),
            search: tabla.search()
        };

        $.post('ventas_pedidos_ajax.php', {
            accion: 'ejecutar_accion',
            venta_pedido_id: pedidoId,
            accion_js: accionJs,
            empresa_idx: empresa_idx,
            pagina_idx: pagina_idx
        }, function (res) {
            if (res.success) {
                tabla.ajax.reload(function (json) {
                    if (savedState.page !== undefined) {
                        tabla.page(savedState.page).draw('page');
                    }
                    if (savedState.search && savedState.search !== '') {
                        tabla.search(savedState.search).draw();
                    }

                    var mensaje = res.message || `Pedido "${comprobanteInfo}" actualizado correctamente`;
                    
                    Swal.fire({
                        icon: "success",
                        title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                        text: mensaje,
                        showConfirmButton: false,
                        timer: 2000,
                        toast: true,
                        position: 'top-end'
                    });
                }, false);
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: res.error || `Error al ${accionJs} el pedido`,
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json').fail(function(xhr) {
            Swal.fire({
                icon: "error",
                title: "Error de conexión",
                text: "No se pudo conectar con el servidor",
                confirmButtonText: "Entendido"
            });
            console.error('Error en ejecutarAccion:', xhr.responseText);
        });
    }

    $('#busqueda_producto').on('input', function() {
        var q = $(this).val().trim();
        var resultadosDiv = $('#resultados_busqueda');
        
        if (!clienteActualId) {
            resultadosDiv.hide();
            return;
        }
        
        if (q.length < 2) {
            resultadosDiv.hide();
            return;
        }
        
        clearTimeout(timeoutBusqueda);
        timeoutBusqueda = setTimeout(function() {
            $.ajax({
                url: 'ventas_pedidos_ajax.php',
                type: 'GET',
                data: {
                    accion: 'buscar_productos_cliente',
                    entidad_id: clienteActualId,
                    q: q,
                    empresa_idx: empresa_idx
                },
                dataType: 'json',
                success: function(res) {
                    resultadosDiv.empty().hide();
                    selectedIndex = -1;

                    if (res && res.error === 'sin_lista_precios') {
                        resultadosDiv.append(
                            `<div class="list-group-item text-danger small">
                                <i class="fas fa-triangle-exclamation me-1"></i>
                                Este cliente no tiene una lista de precios vigente asignada
                                (gestion__entidades_condiciones_clientes). No se pueden buscar ni
                                agregar productos hasta configurarla.
                            </div>`
                        );
                        resultadosDiv.show();
                        return;
                    }

                    var data = (res && res.productos) ? res.productos : [];

                    if (data && data.length > 0) {
                        data.forEach(function(item, index) {
                            resultadosDiv.append(
                                `<a href="#" class="list-group-item list-group-item-action" 
                                   data-index="${index}"
                                   data-id="${item.producto_id}"
                                   data-codigo="${item.producto_codigo}"
                                   data-nombre="${item.producto_nombre}"
                                   data-iva-id="${item.iva_alicuota_id}"
                                   data-iva="${item.iva_porcentaje || 21}"
                                   data-precio="${item.precio_final || ''}">
                                    <strong>${item.producto_codigo}</strong> - ${item.producto_nombre}
                                </a>`
                            );
                        });
                        resultadosDiv.show();
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Error en buscar_productos_cliente:', textStatus, errorThrown);
                    console.error('Respuesta cruda del servidor:', jqXHR.responseText);
                    resultadosDiv.empty();
                    resultadosDiv.append(
                        `<div class="list-group-item text-danger small">
                            <i class="fas fa-triangle-exclamation me-1"></i>
                            Error del servidor al buscar productos. Revisá la consola del navegador
                            (F12) para ver el detalle.
                        </div>`
                    );
                    resultadosDiv.show();
                }
            });
        }, 300);
    });

    $('#busqueda_producto').on('keydown', function(e) {
        var resultados = $('#resultados_busqueda .list-group-item');
        
        if (resultados.length === 0) return;
        
        if (e.keyCode === 40) {
            e.preventDefault();
            if (selectedIndex < resultados.length - 1) {
                selectedIndex++;
            } else {
                selectedIndex = 0;
            }
            actualizarSeleccion(resultados);
        }
        else if (e.keyCode === 38) {
            e.preventDefault();
            if (selectedIndex > 0) {
                selectedIndex--;
            } else {
                selectedIndex = resultados.length - 1;
            }
            actualizarSeleccion(resultados);
        }
        else if (e.keyCode === 13 && selectedIndex >= 0) {
            e.preventDefault();
            $(resultados[selectedIndex]).click();
        }
    });

    function actualizarSeleccion(resultados) {
        resultados.removeClass('active');
        $(resultados[selectedIndex]).addClass('active');
        
        var container = $('#resultados_busqueda');
        var selectedElement = $(resultados[selectedIndex]);
        var containerScrollTop = container.scrollTop();
        var containerHeight = container.height();
        var elementTop = selectedElement.position().top;
        var elementHeight = selectedElement.outerHeight();
        
        if (elementTop < 0) {
            container.scrollTop(containerScrollTop + elementTop);
        } else if (elementTop + elementHeight > containerHeight) {
            container.scrollTop(containerScrollTop + (elementTop + elementHeight - containerHeight));
        }
    }

    $(document).on('click', '#resultados_busqueda .list-group-item', function(e) {
        e.preventDefault();
        
        var item = $(this);
        var productoId = item.data('id');
        var productoCodigo = item.data('codigo');
        var productoNombre = item.data('nombre');
        var iva = item.data('iva');
        var ivaId = item.data('iva-id');
        var precioLista = item.data('precio');
        
        $('#busqueda_producto').val(productoCodigo + ' - ' + productoNombre);
        $('#producto_seleccionado_id').val(productoId);
        $('#producto_codigo_seleccionado').val(productoCodigo);
        $('#producto_nombre_seleccionado').val(productoNombre);
        $('#producto_iva').val(iva);
        $('#producto_iva_id').val(ivaId);

        // El precio viene fijo de la lista de precios del cliente y el % de IVA del
        // producto (iva_alicuota_id); ninguno de los dos se vuelve a consultar ni se
        // deja editar acá.
        $('#producto_precio').val(precioLista || '0');
        calcularIvaImporte();
        
        $('#resultados_busqueda').hide();
        selectedIndex = -1;
    });

    $(document).on('click', function(e) {
        if (!$(e.target).closest('#busqueda_producto, #resultados_busqueda').length) {
            $('#resultados_busqueda').hide();
            selectedIndex = -1;
        }
    });

    function calcularIvaImporte() {
        var cantidad = parseFloat($('#producto_cantidad').val()) || 0;
        var precio = parseFloat($('#producto_precio').val()) || 0;
        var iva = parseFloat($('#producto_iva').val()) || 0;

        var descuentoGeneralPct = (clienteCondicionComercial && clienteCondicionComercial.cliente_descuento_general)
            ? parseFloat(clienteCondicionComercial.cliente_descuento_general) : 0;
        var precioNeto = precio - (precio * descuentoGeneralPct / 100);

        var netoGravado = cantidad * precioNeto;
        var ivaImporte = netoGravado * (iva / 100);

        $('#producto_iva_importe').val(ivaImporte.toFixed(2));
    }

    $('#producto_cantidad, #producto_precio, #producto_iva, #producto_no_gravado, #producto_exento').on('input', function() {
        calcularIvaImporte();
    });

    function obtenerIdIva(porcentaje) {
        switch(parseFloat(porcentaje)) {
            case 21: return 1;
            case 10.5: return 2;
            case 27: return 3;
            case 0: return 4;
            default: return 1;
        }
    }

    $('#btnAgregarProducto').click(function() {
        if (!clienteActualId) {
            Swal.fire({
                icon: "warning",
                title: "Seleccione cliente",
                text: "Debe seleccionar un cliente primero",
                confirmButtonText: "Entendido"
            });
            return;
        }
        
        var productoId = $('#producto_seleccionado_id').val();
        if (!productoId) {
            Swal.fire({
                icon: "warning",
                title: "Producto requerido",
                text: "Debe seleccionar un producto de la lista",
                confirmButtonText: "Entendido"
            });
            return;
        }
        
        var cantidad = parseFloat($('#producto_cantidad').val());
        var precio = parseFloat($('#producto_precio').val()) || 0;
        var iva = parseFloat($('#producto_iva').val());
        var ivaId = $('#producto_iva_id').val() || obtenerIdIva(iva);
        var ivaImporte = parseFloat($('#producto_iva_importe').val()) || 0;
        var noGravado = parseFloat($('#producto_no_gravado').val()) || 0;
        var exento = parseFloat($('#producto_exento').val()) || 0;
        
        if (cantidad <= 0) {
            Swal.fire({
                icon: "warning",
                title: "Cantidad inválida",
                text: "La cantidad debe ser mayor a 0",
                confirmButtonText: "Entendido"
            });
            return;
        }
        
        var productoCodigo = $('#producto_codigo_seleccionado').val() || '';
        var productoNombre = $('#producto_nombre_seleccionado').val() || $('#busqueda_producto').val();
        
        var descuentoGeneralPct = (clienteCondicionComercial && clienteCondicionComercial.cliente_descuento_general)
            ? parseFloat(clienteCondicionComercial.cliente_descuento_general) : 0;
        var descuentoGeneralImporte = precio * (descuentoGeneralPct / 100);
        var precioUnitarioNeto = precio - descuentoGeneralImporte;

        var netoGravado = cantidad * precioUnitarioNeto;
        var ivaImporte = netoGravado * (iva / 100);
        var totalLinea = netoGravado + ivaImporte + noGravado + exento;
        
        var nuevoDetalle = {
            detalle_idx: 'temp_' + new Date().getTime(),
            venta_pedido_detalle_id: 0,
            producto_id: parseInt(productoId),
            producto_codigo: productoCodigo,
            producto_nombre: productoNombre,
            cantidad: cantidad,
            cantidad_entregada: 0,
            precio_unitario: precio,
            descuento_general_pct: descuentoGeneralPct,
            descuento_general: descuentoGeneralImporte,
            precio_unitario_neto: precioUnitarioNeto,
            no_gravado: noGravado,
            exento: exento,
            iva_alicuota_id: parseInt(ivaId),
            iva_porcentaje: iva,
            neto_gravado: netoGravado,
            iva_importe: ivaImporte,
            total_linea: totalLinea
        };
        
        detalles.push(nuevoDetalle);
        renderizarDetalles();
        actualizarTotales();
        
        $('#busqueda_producto').val('');
        $('#producto_seleccionado_id').val('');
        $('#producto_codigo_seleccionado').val('');
        $('#producto_nombre_seleccionado').val('');
        $('#producto_iva_id').val('');
        $('#producto_cantidad').val('1.00');
        $('#producto_precio').val('');
        $('#producto_iva').val('');
        $('#producto_iva_importe').val('0.00');
        $('#producto_no_gravado').val('0.00');
        $('#producto_exento').val('0.00');
        
        $('#busqueda_producto').focus();
    });

    function renderizarDetalles() {
        $('#contenedor-detalles').empty();
        
        if (detalles.length === 0) {
            var htmlVacio = `
            <div class="detalles-vacio">
                <i class="fas fa-box-open"></i>
                <p class="mb-0">No hay productos agregados</p>
                <small class="text-muted">Seleccione un producto para comenzar</small>
            </div>`;
            $('#contenedor-detalles').html(htmlVacio);
            return;
        }
        
        var html = `
        <table class="table table-sm table-bordered table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Detalle</th>
                    <th class="text-center">Cant.</th>
                    <th class="text-end">Precio Unit.</th>
                    <th class="text-end">Desc. %</th>
                    <th class="text-end">Desc. $</th>
                    <th class="text-end">Precio Neto</th>
                    <th class="text-center">IVA %</th>
                    <th class="text-end">IVA $</th>
                    <th class="text-end">Total</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>`;
        
        detalles.forEach(function(detalle) {
            var esNuevo = detalle.venta_pedido_detalle_id === 0;
            var claseFila = esNuevo ? 'table-info' : '';
            
            var nombreProducto = detalle.producto_nombre || '';
            
            html += `
            <tr class="${claseFila}" data-idx="${detalle.detalle_idx}">
                <td>${detalle.producto_codigo || ''}</td>
                <td>
                    <div class="fw-bold">${nombreProducto.substring(0, 35)}${nombreProducto.length > 35 ? '...' : ''}</div>
                    ${esNuevo ? '<span class="badge bg-info ms-2">Nuevo</span>' : ''}
                </td>
                <td class="text-center">${formatMoneda(detalle.cantidad)}</td>
                <td class="text-end">$${formatMoneda(detalle.precio_unitario)}</td>
                <td class="text-end">${formatMoneda(detalle.descuento_general_pct)}%</td>
                <td class="text-end">$${formatMoneda(detalle.descuento_general)}</td>
                <td class="text-end">$${formatMoneda(detalle.precio_unitario_neto)}</td>
                <td class="text-center">${detalle.iva_porcentaje.toFixed(2)}%</td>
                <td class="text-end">$${formatMoneda(detalle.iva_importe)}</td>
                <td class="text-end fw-bold text-success">$${formatMoneda(detalle.total_linea)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-warning btn-editar-detalle" 
                            data-idx="${detalle.detalle_idx}" title="Editar">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button type="button" class="btn btn-sm btn-danger btn-eliminar-detalle" 
                            data-idx="${detalle.detalle_idx}" title="Eliminar">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
        });
        
        html += `
            </tbody>
        </table>`;
        
        $('#contenedor-detalles').html(html);
    }

    function formatMoneda(valor) {
        return (valor || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function actualizarTotales() {
        var totalNeto = 0;
        var totalNoGravado = 0;
        var totalExento = 0;
        var totalImpuestos = 0;
        var totalDescuentos = 0;
        
        detalles.forEach(function(detalle) {
            totalNeto += detalle.neto_gravado || 0;
            totalImpuestos += detalle.iva_importe || 0;
            totalNoGravado += detalle.no_gravado || 0;
            totalExento += detalle.exento || 0;
            totalDescuentos += (detalle.descuento_general || 0) * (detalle.cantidad || 0);
        });
        
        var totalGeneral = totalNeto + totalImpuestos + totalNoGravado + totalExento;
        var totalBruto = totalNeto + totalDescuentos;
        
        $('#total_neto').val(totalNeto.toFixed(2));
        $('#descuentos').val(totalDescuentos.toFixed(2));
        $('#no_gravado').val(totalNoGravado.toFixed(2));
        $('#exento').val(totalExento.toFixed(2));
        $('#impuestos').val(totalImpuestos.toFixed(2));
        $('#total').val(totalGeneral.toFixed(2));
        
        $('#total_neto_display').text(formatMoneda(totalNeto));
        $('#no_gravado_display').text(formatMoneda(totalNoGravado));
        $('#exento_display').text(formatMoneda(totalExento));
        $('#impuestos_display').text(formatMoneda(totalImpuestos));
        $('#total_display').text(formatMoneda(totalGeneral));

        // Mismo resumen, replicado en la solapa "Datos del Pedido"
        $('#bruto_display_resumen').text(formatMoneda(totalBruto));
        $('#descuento_display_resumen').text(formatMoneda(totalDescuentos));
        $('#total_neto_display_resumen').text(formatMoneda(totalNeto));
        $('#impuestos_display_resumen').text(formatMoneda(totalImpuestos));
        $('#total_display_resumen').text(formatMoneda(totalGeneral));

        $('#contador-productos').text(detalles.length);
    }

    $('#btnToggleFullscreen').click(function() {
        var modalDialog = $('#modalVentaPedido .modal-dialog');
        var btnIcon = $(this).find('i');
        
        if (modalDialog.hasClass('modal-fullscreen')) {
            modalDialog.removeClass('modal-fullscreen');
            btnIcon.removeClass('fa-compress').addClass('fa-expand');
        } else {
            modalDialog.addClass('modal-fullscreen');
            btnIcon.removeClass('fa-expand').addClass('fa-compress');
        }
    });

    $(document).on('click', '.btn-eliminar-detalle', function() {
        var idx = $(this).data('idx');
        
        Swal.fire({
            title: '¿Eliminar producto?',
            text: "Esta acción no se puede deshacer",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                detalles = detalles.filter(function(item) {
                    return item.detalle_idx != idx;
                });
                renderizarDetalles();
                actualizarTotales();
                
                Swal.fire({
                    icon: 'success',
                    title: 'Eliminado',
                    text: 'Producto eliminado del detalle',
                    showConfirmButton: false,
                    timer: 1500,
                    toast: true,
                    position: 'top-end'
                });
            }
        });
    });

    $(document).on('click', '.btn-editar-detalle', function() {
        var idx = $(this).data('idx');
        var detalle = detalles.find(function(item) {
            return item.detalle_idx == idx;
        });

        if (detalle) {
            $('#busqueda_producto').val((detalle.producto_codigo ? detalle.producto_codigo + ' - ' : '') + detalle.producto_nombre);
            $('#producto_seleccionado_id').val(detalle.producto_id);
            $('#producto_codigo_seleccionado').val(detalle.producto_codigo || '');
            $('#producto_nombre_seleccionado').val(detalle.producto_nombre);
            $('#producto_iva_id').val(detalle.iva_alicuota_id);
            $('#producto_cantidad').val(detalle.cantidad);
            $('#producto_precio').val(detalle.precio_unitario);
            $('#producto_iva').val(detalle.iva_porcentaje);
            $('#producto_iva_importe').val(detalle.iva_importe);
            $('#producto_no_gravado').val(detalle.no_gravado || 0);
            $('#producto_exento').val(detalle.exento || 0);
            
            detalles = detalles.filter(function(item) {
                return item.detalle_idx != idx;
            });
            renderizarDetalles();
            actualizarTotales();
            
            $('#busqueda_producto').focus();
        }
    });

    function cargarCombosFormulario() {
        $.get('ventas_pedidos_ajax.php', { 
            accion: 'obtener_sucursales_empresa',
            empresa_idx: empresa_idx 
        }, function(data) {
            let options = '<option value="">Seleccionar sucursal</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.sucursal_id}">${item.sucursal_nombre}</option>`;
                });
            }
            $('#sucursal_id').html(options);
        }, 'json');

        $.get('ventas_pedidos_ajax.php', { accion: 'obtener_comprobantes_tipos' }, function(data) {
            if (data && data.length > 0) {
                if (data.length === 1) {
                    var options = `<option value="${data[0].comprobante_tipo_id}" selected>${data[0].comprobante_tipo}</option>`;
                    $('#comprobante_tipo_id').html(options);
                } else {
                    var options = '<option value="">Seleccionar</option>';
                    data.forEach(function(item) {
                        options += `<option value="${item.comprobante_tipo_id}">${item.comprobante_tipo}</option>`;
                    });
                    $('#comprobante_tipo_id').html(options);
                }
            }
        }, 'json');

        $.get('ventas_pedidos_ajax.php', { accion: 'obtener_monedas' }, function(data) {
            var options = '<option value="">Seleccionar moneda</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    var selected = item.es_moneda_base == 1 ? 'selected' : '';
                    options += `<option value="${item.moneda_id}" data-cotizacion="${item.cotizacion_actual}" ${selected}>${item.moneda} (${item.simbolo})</option>`;
                });
            }
            $('#moneda_id').html(options);
            
            var selectedMoneda = $('#moneda_id').find('option:selected');
            var cotizacion = selectedMoneda.data('cotizacion');
            if (cotizacion) {
                $('#tipo_cambio').val(cotizacion);
            }
            
            $('#moneda_id').off('change').on('change', function() {
                var selected = $(this).find('option:selected');
                var cotizacion = selected.data('cotizacion');
                if (cotizacion) {
                    $('#tipo_cambio').val(cotizacion);
                }
            });
        }, 'json');

        $.get('ventas_pedidos_ajax.php', { 
            accion: 'obtener_condiciones_pago',
            empresa_idx: empresa_idx 
        }, function(data) {
            if (data && data.length > 0) {
                var options = '';
                data.forEach(function(item) {
                    options += `<option value="${item.condicion_pago_id}">${item.codigo} - ${item.condicion_pago}</option>`;
                });
                $('#condicion_pago_id').html(options);
                
                if (!$('#condicion_pago_id').val()) {
                    $('#condicion_pago_id').val(data[0].condicion_pago_id);
                }
            } else {
                $('#condicion_pago_id').html('<option value="">No hay condiciones</option>');
            }
        }, 'json');
    }

    function resetModal() {
        $('#formVentaPedido')[0].reset();
        $('#venta_pedido_id').val('');
        $('#entidad_id').val('');
        $('#entidad_sucursal_id').val('');
        $('#entidad_combo').prop('disabled', false).removeAttr('title');
        $('#punto_venta_id').html('<option value="">Primero seleccione sucursal</option>');
        $('#punto_venta_id').prop('disabled', true);
        $('#tipo_cambio').val('1.000000');
        $('#f_entrega_estimada').val(new Date().toISOString().split('T')[0]);
        $('#descuento_general_pct').val('0');
        $('#descuentos').val('0');
        $('#total_neto').val('0');
        $('#no_gravado').val('0');
        $('#exento').val('0');
        $('#impuestos').val('0');
        $('#total').val('0');
        $('#formVentaPedido').removeClass('was-validated');
        
        detalles = [];
        clienteActualId = null;
        clienteSucursalActualId = null;
        clienteCondicionComercial = null;
        renderizarDetalles();
        actualizarTotales();
        
        $('#entidad_combo').html('<option value="">Seleccionar cliente o sucursal</option>');
        $('#cliente_actual_nombre').text('No seleccionado');
        
        $('.btn-secondary[data-bs-dismiss="modal"]').show();
        
        $('.btn-editar-detalle, .btn-eliminar-detalle, #btnAgregarProducto, #btnNuevoProductoRapido').show();
        $('#busqueda_producto').prop('disabled', false);
        
        $('#btnImprimirDesdeEdicion').remove();
        
        window.sucursalIdEditar = null;
    }

    $(document).on('click', '#btnNuevo', function () {
        resetModal();
        $('#modalLabel').text('Nuevo Pedido de Venta');
        cargarCombosFormulario();
        cargarClientesYSucursales();
        
        var today = new Date().toISOString().split('T')[0];
        $('#f_emision').val(today);

        var modal = new bootstrap.Modal(document.getElementById('modalVentaPedido'));
        modal.show();
    });

    function cargarPedidoParaEditar(pedidoId) {
        $.get('ventas_pedidos_ajax.php', {
            accion: 'obtener',
            venta_pedido_id: pedidoId,
            empresa_idx: empresa_idx
        }, function (res) {
            if (res && res.venta_pedido_id) {
                resetModal();
                
                cargarCombosFormulario();
                cargarClientesYSucursales();
                
                $('#venta_pedido_id').val(res.venta_pedido_id);
                $('#comprobante_nro').val(res.comprobante_nro);
                
                $('#f_emision').val(res.f_emision);
                $('#f_entrega_estimada').val(res.f_entrega_estimada);
                $('#direccion_entrega').val(res.direccion_entrega);
                $('#observaciones').val(res.observaciones);
                $('#tipo_cambio').val(res.tipo_cambio || '1.000000');
                $('#descuento_general_pct').val(res.descuento_general_pct || '0');
                $('#total_neto').val(res.subtotal || 0);
                $('#no_gravado').val(res.no_gravado || 0);
                $('#exento').val(res.exento || 0);
                $('#impuestos').val(res.impuestos || 0);
                $('#total').val(res.total || 0);
                
                $('#total_neto_display').text(parseFloat(res.subtotal || 0).toFixed(2));
                $('#no_gravado_display').text(parseFloat(res.no_gravado || 0).toFixed(2));
                $('#exento_display').text(parseFloat(res.exento || 0).toFixed(2));
                $('#impuestos_display').text(parseFloat(res.impuestos || 0).toFixed(2));
                $('#total_display').text(parseFloat(res.total || 0).toFixed(2));
                $('#modalLabel').text('Editar Pedido de Venta');

                if (res.comprobante_nro && res.comprobante_nro > 0) {
                    $('.row.mt-2.mb-3 .col-12').append(
                        `<button type="button" class="btn btn-info btn-sm px-4 ms-2" id="btnImprimirDesdeEdicion">
                            <i class="fas fa-print me-1"></i>Imprimir
                        </button>`
                    );
                    
                    $('#btnImprimirDesdeEdicion').off('click').on('click', function() {
                        imprimirComprobante(pedidoId);
                    });
                }

                setTimeout(function() {
                    $('#comprobante_tipo_id').val(res.comprobante_tipo_id);
                    $('#moneda_id').val(res.moneda_id);
                    $('#condicion_pago_id').val(res.condicion_pago_id);
                    
                    if (res.sucursal_id) {
                        $('#sucursal_id').val(res.sucursal_id);
                        cargarPuntosVenta(res.sucursal_id, function() {
                            if (res.punto_venta_id) {
                                $('#punto_venta_id').val(res.punto_venta_id);
                            }
                        });
                    }
                    
                    if (res.entidad_id) {
                        clienteActualId = res.entidad_id;
                        cargarCondicionesComercialesCliente(res.entidad_id, false);
                        
                        if (res.entidad_sucursal_id && res.entidad_sucursal_id > 0) {
                            $('#entidad_combo').val('S-' + res.entidad_sucursal_id);
                            clienteSucursalActualId = parseInt(res.entidad_sucursal_id);
                        } else {
                            $('#entidad_combo').val('P-' + res.entidad_id);
                            clienteSucursalActualId = null;
                        }
                        
                        var textoSeleccionado = $('#entidad_combo option:selected').text();
                        $('#cliente_actual_nombre').text(textoSeleccionado || 'No seleccionado');
                        // El cliente queda fijo una vez guardado el pedido; no se puede cambiar en edición.
                        $('#entidad_combo').prop('disabled', true).attr('title', 'El cliente no se puede modificar una vez guardado el pedido');
                    }
                    
                    if (res.detalles && res.detalles.length > 0) {
                        detalles = res.detalles.map(function(detalle, index) {
                            return {
                                detalle_idx: index,
                                venta_pedido_detalle_id: detalle.venta_pedido_detalle_id,
                                producto_id: detalle.producto_id,
                                producto_codigo: detalle.producto_codigo,
                                producto_nombre: detalle.producto_nombre,
                                cantidad: detalle.cantidad,
                                cantidad_entregada: detalle.cantidad_entregada || 0,
                                precio_unitario: detalle.precio_unitario,
                                descuento_general_pct: detalle.descuento_general_pct || 0,
                                descuento_general: detalle.descuento_general || 0,
                                precio_unitario_neto: detalle.precio_unitario_neto || detalle.precio_unitario,
                                no_gravado: detalle.no_gravado || 0,
                                exento: detalle.exento || 0,
                                iva_alicuota_id: detalle.iva_alicuota_id,
                                iva_porcentaje: detalle.iva_porcentaje,
                                neto_gravado: detalle.neto_gravado,
                                iva_importe: detalle.iva_importe,
                                total_linea: detalle.total_linea
                            };
                        });
                        renderizarDetalles();
                        actualizarTotales();
                    }
                    
                    if (res.comprobante_nro && res.comprobante_nro > 0) {
                        $('.btn-secondary[data-bs-dismiss="modal"]').hide();
                        $('.btn-editar-detalle, .btn-eliminar-detalle, #btnAgregarProducto, #btnNuevoProductoRapido').hide();
                        $('#busqueda_producto').prop('disabled', true);
                    } else {
                        $('.btn-secondary[data-bs-dismiss="modal"]').show();
                        $('.btn-editar-detalle, .btn-eliminar-detalle, #btnAgregarProducto, #btnNuevoProductoRapido').show();
                        $('#busqueda_producto').prop('disabled', false);
                    }
                    
                }, 500);

                var modal = new bootstrap.Modal(document.getElementById('modalVentaPedido'));
                modal.show();
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos del pedido",
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json');
    }

    $('#btnGuardar').click(function() {
        var form = document.getElementById('formVentaPedido');
        var fEmision = $('#f_emision').val();
        var fEntrega = $('#f_entrega_estimada').val();

        if (fEntrega && fEntrega < fEmision) {
            Swal.fire({
                icon: "warning",
                title: "Fecha inválida",
                text: "La fecha de entrega estimada debe ser mayor o igual a la fecha de emisión",
                confirmButtonText: "Entendido"
            });
            return false;
        }
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }
        
        if (detalles.length === 0) {
            Swal.fire({
                icon: "warning",
                title: "Detalles requeridos",
                text: "Debe agregar al menos un producto al detalle",
                confirmButtonText: "Entendido"
            });
            return false;
        }
        
        var id = $('#venta_pedido_id').val();
        var accionBackend = id ? 'editar' : 'agregar';
        
        var btnGuardar = $(this);
        var originalText = btnGuardar.html();
        btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');
        
        if (!clienteActualId) {
            Swal.fire({
                icon: "warning",
                title: "Cliente requerido",
                text: "Debe seleccionar un cliente",
                confirmButtonText: "Entendido"
            });
            btnGuardar.prop('disabled', false).html(originalText);
            return false;
        }
        
        var formData = new FormData();
        formData.append('accion', accionBackend);
        formData.append('empresa_idx', empresa_idx);
        formData.append('pagina_idx', pagina_idx);
        formData.append('venta_pedido_id', $('#venta_pedido_id').val() || '');
        formData.append('sucursal_id', $('#sucursal_id').val() || '');
        formData.append('punto_venta_id', $('#punto_venta_id').val() || '');
        formData.append('entidad_id', clienteActualId);
        formData.append('entidad_sucursal_id', clienteSucursalActualId !== null ? clienteSucursalActualId : '');
        formData.append('comprobante_tipo_id', $('#comprobante_tipo_id').val() || '');
        
        formData.append('comprobante_nro', $('#comprobante_nro').val() || '0');
        formData.append('f_emision', $('#f_emision').val() || '');
        formData.append('f_entrega_estimada', $('#f_entrega_estimada').val() || '');
        formData.append('condicion_pago_id', $('#condicion_pago_id').val() || '');
        formData.append('moneda_id', $('#moneda_id').val() || '');
        formData.append('tipo_cambio', $('#tipo_cambio').val() || '1.000000');
        formData.append('direccion_entrega', $('#direccion_entrega').val() || '');
        formData.append('observaciones', $('#observaciones').val() || '');
        formData.append('subtotal', $('#total_neto').val() || '0');
        formData.append('descuento_general_pct', $('#descuento_general_pct').val() || '0');
        formData.append('descuentos', $('#descuentos').val() || '0');
        formData.append('no_gravado', $('#no_gravado').val() || '0');
        formData.append('exento', $('#exento').val() || '0');
        formData.append('impuestos', $('#impuestos').val() || '0');
        formData.append('total', $('#total').val() || '0');
        formData.append('detalles', JSON.stringify(detalles));

        var savedState = {
            page: tabla ? tabla.page() : 0,
            order: tabla ? tabla.order() : [[1, 'asc']],
            search: tabla ? tabla.search() : ''
        };
        
        $.ajax({
            url: 'ventas_pedidos_ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                btnGuardar.prop('disabled', false).html(originalText);
                
                if (res.resultado) {
                    if (tabla) {
                        tabla.ajax.reload(function(json) {
                            if (savedState.page !== undefined) {
                                tabla.page(savedState.page).draw('page');
                            }
                            if (savedState.search && savedState.search !== '') {
                                tabla.search(savedState.search).draw();
                            }
                        }, false);
                    }
                    
                    Swal.fire({
                        icon: "success",
                        title: "¡Guardado!",
                        text: "Pedido de venta guardado correctamente",
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });
                    
                    var modalEl = document.getElementById('modalVentaPedido');
                    var modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) {
                        modal.hide();
                    } else {
                        modal = new bootstrap.Modal(modalEl);
                        modal.hide();
                    }
                    
                    $('body').removeClass('modal-open');
                    $('.modal-backdrop').remove();
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || "Error al guardar los datos",
                        confirmButtonText: "Entendido"
                    });
                }
            },
            error: function(xhr, status, error) {
                btnGuardar.prop('disabled', false).html(originalText);
                
                console.error("Error AJAX:", error);
                console.error("Respuesta:", xhr.responseText);
                
                Swal.fire({
                    icon: "error",
                    title: "Error de conexión",
                    text: "Error al comunicarse con el servidor",
                    confirmButtonText: "Entendido"
                });
            }
        });
    });

    function cargarDatosProductoRapido() {
        $.get('ventas_pedidos_ajax.php', { 
            accion: 'obtener_categorias_productos',
            empresa_idx: empresa_idx 
        }, function(data) {
            var options = '<option value="">Seleccionar categoría</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.producto_categoria_id}">${item.categoria_nombre}</option>`;
                });
            }
            $('#producto_categoria_id_rapido').html(options);
        }, 'json');

        $.get('ventas_pedidos_ajax.php', { accion: 'obtener_unidades_medida' }, function(data) {
            var options = '<option value="">Seleccionar unidad</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.unidad_medida_id}">${item.unidad_nombre}</option>`;
                });
            }
            $('#unidad_medida_id_rapido').html(options);
        }, 'json');
        
        $.get('ventas_pedidos_ajax.php', { accion: 'obtener_alicuotas_iva' }, function(data) {
            var options = '';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    var selected = item.iva_alicuota_id == 1 ? 'selected' : '';
                    options += `<option value="${item.iva_alicuota_id}" data-porcentaje="${item.porcentaje}" ${selected}>${item.iva_alicuota} (${item.porcentaje}%)</option>`;
                });
            }
            $('#iva_alicuota_id_rapido').html(options);
        }, 'json');
    }

    $(document).on('click', '#btnNuevoProductoRapido', function() {
        if (!clienteActualId) {
            Swal.fire({
                icon: "warning",
                title: "Seleccione cliente",
                text: "Debe seleccionar un cliente primero",
                confirmButtonText: "Entendido"
            });
            return;
        }

        $('#formNuevoProductoRapido')[0].reset();
        $('#formNuevoProductoRapido').removeClass('was-validated');
        
        cargarDatosProductoRapido();
        
        var modal = new bootstrap.Modal(document.getElementById('modalNuevoProductoRapido'));
        modal.show();
    });

    $(document).on('click', '#btnGuardarProductoRapido', function() {
        var form = document.getElementById('formNuevoProductoRapido');
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }

        var btn = $(this);
        var originalText = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        var data = {
            accion: 'agregar_producto_rapido',
            empresa_idx: empresa_idx,
            producto_codigo: $('#producto_codigo_rapido').val(),
            producto_nombre: $('#producto_nombre_rapido').val(),
            codigo_barras: $('#codigo_barras_rapido').val(),
            producto_descripcion: $('#producto_descripcion_rapido').val(),
            producto_categoria_id: $('#producto_categoria_id_rapido').val(),
            iva_alicuota_id: $('#iva_alicuota_id_rapido').val(),
            unidad_medida_id: $('#unidad_medida_id_rapido').val() || null
        };

        $.ajax({
            url: 'ventas_pedidos_ajax.php',
            type: 'POST',
            data: data,
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    var modalProducto = bootstrap.Modal.getInstance(document.getElementById('modalNuevoProductoRapido'));
                    modalProducto.hide();
                    
                    Swal.fire({
                        icon: "success",
                        title: "Producto creado",
                        text: "El producto fue creado exitosamente",
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || "Error al crear el producto",
                        confirmButtonText: "Entendido"
                    });
                }
                btn.prop('disabled', false).html(originalText);
            },
            error: function() {
                Swal.fire({
                    icon: "error",
                    title: "Error de conexión",
                    text: "Error al comunicarse con el servidor",
                    confirmButtonText: "Entendido"
                });
                btn.prop('disabled', false).html(originalText);
            }
        });
    });

    inicializarDataTable();
    cargarBotonAgregar();

    $('[title]').tooltip({
        trigger: 'hover',
        placement: 'top'
    });
});