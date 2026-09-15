$(document).ready(function () {
    const empresa_idx = EMPRESA_ID;
    const pagina_idx = PAGINA_ID;
    
    var tabla;
    var currentPage = 0;
    var currentOrder = [[5, 'desc']]; // f_emision, del más nuevo al más viejo
    var currentSearch = '';

    // Filtros de la vista de listado (sucursal, punto de venta y accesos rápidos de estado)
    var filtroListadoSucursalId = '';
    var filtroListadoPuntoVentaId = '';
    var filtroListadoEstadosRapidos = []; // ej. [5, 10]
    
    // Variables para manejo de detalles
    var detalles = [];
    var pedidoConfirmado = false; // true cuando tabla_estado_registro_id > 5: cambia "Cant." de la tabla de detalle a solo lectura
    var clienteActualId = null;
    var clienteSucursalActualId = null;
    var clienteCondicionComercial = null; // { lista_precio_id, condicion_pago_id, cliente_descuento_general, limite_credito }

    // Solapa "Remitos": ventas_remitos.php/.js/.model tienen su propio pagina_idx
    // (88 por defecto ahí) y su propio motor de estados, totalmente independiente
    // del de este módulo (65 acá) — nunca reutilizar la variable `pagina_idx` de
    // pedidos en llamadas a ventas_remitos_ajax.php.
    var REMITOS_PAGINA_IDX = 88;
    var remitosPedidoActual = [];    // remitos ya generados para este pedido (respuesta cruda)
    var remitoPendientesPedido = []; // líneas pendientes de ESTE pedido (respuesta cruda)
    var remitoDetallesNuevo = [];    // líneas elegidas para el remito en construcción
    var remitoEditandoId = null;     // null = "Cargar Remito Nuevo"; con valor = editando ese remito
    var pedidoPuntoVentaId = null;   // PV del pedido — el remito siempre lo respeta, nunca se elige otro
    var pedidoPuntoVentaNombre = '';
    var remitoLineasOriginalesPorVpd = {}; // venta_pedido_detalle_id -> cantidad que el remito en edición ya tenía reservada (corrige el pendiente mostrado)

    // Buscador de producto por etiquetas (mismo patrón que ventas_remitos y que el ABM
    // de productos): cada palabra se convierte en un "tag" dentro del campo al presionar
    // espacio, y los resultados aparecen debajo del campo (no en un desplegable flotante),
    // cada uno con su propia cantidad y su propio botón de agregar, estilo "carrito".
    var tagsProducto = [];
    var tagsProductoInput = $('#busqueda_producto');
    var tagsProductoContainer = $('#busqueda_producto_container');
    var ultimosResultadosBusqueda = [];

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
            pageLength: 10,
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
                        if (data.entidad_id && !data.entidad_nombre) {
                            console.warn('[DIAGNOSTICO] Pedido con entidad_id pero sin entidad_nombre:', JSON.parse(JSON.stringify(data)));
                        }
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
                        // 'sort'/'filter'/'type'/'export' reciben el ISO crudo (YYYY-MM-DD):
                        // ordena y filtra bien. Solo 'display' formatea a DD/MM/YYYY.
                        if (type !== 'display') {
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
                        if (type !== 'display' || !data) {
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
                    $('#tablaVentasPedidos_length').addClass('dataTables_length_custom');
                    $('#tablaVentasPedidos_filter').addClass('dataTables_filter_custom');
                    
                    if ($('#tablaVentasPedidos_length').html().trim() === '') {
                        var selectHtml = '<label>Mostrar <select name="tablaVentasPedidos_length" aria-controls="tablaVentasPedidos" class="form-select form-select-sm"><option value="10" selected="">10</option><option value="25">25</option><option value="50">50</option><option value="100">100</option><option value="-1">Todos</option></select> registros</label>';
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

                // Filtro por sucursal: recarga el combo de punto de venta en cascada
                // (mismo patrón que el formulario de alta/edición) y redibuja la tabla.
                $('#filtro_sucursal').on('change', function() {
                    filtroListadoSucursalId = $(this).val();
                    filtroListadoPuntoVentaId = '';
                    cargarFiltroPuntosVenta(filtroListadoSucursalId);
                    tabla.draw();
                });

                $('#filtro_punto_venta').on('change', function() {
                    filtroListadoPuntoVentaId = $(this).val();
                    tabla.draw();
                });

                // Accesos rápidos por estado (multi-selección: Pend. preparación / Entrega parcial).
                // Reconocen visualmente el filtro activo y filtran por tabla_estado_registro_id real,
                // no por el texto mostrado en la columna Estado.
                $('.btn-filtro-estado-rapido').on('click', function() {
                    var estadoId = parseInt($(this).data('estado-id'), 10);
                    $(this).toggleClass('active');

                    var idx = filtroListadoEstadosRapidos.indexOf(estadoId);
                    if (idx === -1) {
                        filtroListadoEstadosRapidos.push(estadoId);
                    } else {
                        filtroListadoEstadosRapidos.splice(idx, 1);
                    }
                    tabla.draw();
                });

                $('#btnLimpiarFiltrosRapidos').on('click', function() {
                    filtroListadoEstadosRapidos = [];
                    $('.btn-filtro-estado-rapido').removeClass('active');
                    tabla.draw();
                });

                cargarFiltroSucursales();
                cargarFiltroPuntosVenta('');

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

    // Combos del filtro de listado (independientes de los del formulario de alta/edición:
    // #filtro_sucursal / #filtro_punto_venta vs #sucursal_id / #punto_venta_id).
    function cargarFiltroSucursales() {
        $.get('ventas_pedidos_ajax.php', {
            accion: 'obtener_sucursales_empresa',
            empresa_idx: empresa_idx
        }, function(data) {
            var options = '<option value="">Todas las sucursales</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.sucursal_id}">${item.sucursal_nombre}</option>`;
                });
            }
            $('#filtro_sucursal').html(options);
        }, 'json');
    }

    function cargarFiltroPuntosVenta(sucursalId) {
        if (!sucursalId) {
            $('#filtro_punto_venta').html('<option value="">Todos los PV</option>');
            return;
        }

        $.get('ventas_pedidos_ajax.php', {
            accion: 'obtener_puntos_venta',
            sucursal_id: sucursalId,
            empresa_idx: empresa_idx
        }, function(data) {
            var options = '<option value="">Todos los PV</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.punto_venta_id}">${item.punto_venta_nombre}</option>`;
                });
            }
            $('#filtro_punto_venta').html(options);
        }, 'json');
    }

    // Predicado custom de DataTables: se evalúa contra la fila cruda (aData), no contra el HTML
    // ya renderizado de cada columna, así el filtro de estado usa tabla_estado_registro_id real
    // en vez de comparar el texto que se ve en pantalla. Se registra una sola vez (fuera de
    // inicializarDataTable, que solo se llama una vez, pero así queda a salvo de duplicarse si
    // algún día se vuelve a invocar).
    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex, rowData) {
        if (settings.nTable.id !== 'tablaVentasPedidos') {
            return true;
        }

        if (filtroListadoSucursalId && String(rowData.sucursal_id) !== String(filtroListadoSucursalId)) {
            return false;
        }

        if (filtroListadoPuntoVentaId && String(rowData.punto_venta_id) !== String(filtroListadoPuntoVentaId)) {
            return false;
        }

        if (filtroListadoEstadosRapidos.length > 0 &&
            filtroListadoEstadosRapidos.indexOf(parseInt(rowData.tabla_estado_registro_id, 10)) === -1) {
            return false;
        }

        return true;
    });

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

    function cargarTiposComprobante(puntoVentaId, callback) {
        if (!puntoVentaId) {
            $('#comprobante_tipo_id').html('<option value="">Primero seleccione punto de venta</option>');
            $('#comprobante_tipo_id').prop('disabled', true);
            if (callback) callback();
            return;
        }

        $.ajax({
            url: 'ventas_pedidos_ajax.php',
            type: 'GET',
            data: {
                accion: 'obtener_comprobantes_tipos',
                punto_venta_id: puntoVentaId,
                pagina_idx: pagina_idx,
                empresa_idx: empresa_idx
            },
            dataType: 'json',
            success: function(data) {
                if (data && data.length > 0) {
                    var options = data.length === 1 ? '' : '<option value="">Seleccionar</option>';
                    data.forEach(function(item) {
                        options += `<option value="${item.comprobante_tipo_id}">${item.comprobante_tipo}</option>`;
                    });
                    $('#comprobante_tipo_id').html(options);
                    $('#comprobante_tipo_id').prop('disabled', false);
                    if (data.length === 1) {
                        $('#comprobante_tipo_id').val(data[0].comprobante_tipo_id);
                    }
                } else {
                    $('#comprobante_tipo_id').html('<option value="">Sin tipos habilitados para este punto de venta</option>');
                    $('#comprobante_tipo_id').prop('disabled', true);
                }
                if (callback) callback();
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("Error cargando tipos de comprobante:", textStatus, errorThrown);
                $('#comprobante_tipo_id').html('<option value="">Error al cargar</option>');
                $('#comprobante_tipo_id').prop('disabled', true);
                if (callback) callback();
            }
        });
    }

    function cargarClientesYSucursales(callback) {
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
                if (typeof callback === 'function') callback();
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("Error cargando clientes y sucursales:", textStatus, errorThrown);
                $('#entidad_combo').html('<option value="">Error al cargar</option>');
                if (typeof callback === 'function') callback();
            }
        });
    }

    $('#sucursal_id').on('change', function() {
        var sucursalId = $(this).val();
        cargarPuntosVenta(sucursalId, function() {
            // Al cambiar de sucursal el punto de venta anterior ya no es válido,
            // así que los tipos de comprobante tampoco: se limpian hasta elegir PV de nuevo.
            cargarTiposComprobante(null);
        });
    });

    $('#punto_venta_id').on('change', function() {
        var puntoVentaId = $(this).val();
        cargarTiposComprobante(puntoVentaId);
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

        // El filtro de búsqueda (tags + resultados) corresponde a la lista de precios
        // del cliente anterior: se limpia al cambiar de cliente para no ofrecer
        // precios/IVA que ya no corresponden.
        resetBusquedaProducto();
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
                cargarClientesYSucursales(function() {
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
                    if (tagsProducto.length === 0) {
                        $('#resultados_busqueda').empty();
                    }

                    // Encadenado acá adentro (no en paralelo): cargarPendientesRemitoPedido()
                    // necesita clienteActualId ya seteado arriba. Si corría independiente,
                    // esta llamada a veces terminaba antes que el callback de clientes
                    // (consulta más liviana) y abortaba en silencio sin mandar la petición.
                    pedidoPuntoVentaId = res.punto_venta_id;
                    pedidoPuntoVentaNombre = res.punto_venta_nombre;
                    fijarPuntoVentaRemito(pedidoPuntoVentaId, pedidoPuntoVentaNombre, function () {
                        cargarTiposComprobanteRemito(pedidoPuntoVentaId, function () {
                            cargarPendientesRemitoPedido();
                        });
                    });
                });
                
                $('#venta_pedido_id').val(res.venta_pedido_id);
                $('#comprobante_nro').val(res.comprobante_nro);

                mostrarTabRemitos(true);
                cargarRemitosDelPedido(res.venta_pedido_id);
                $('#remito_f_emision').val(new Date().toISOString().split('T')[0]);
                
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
                    $('#moneda_id').val(res.moneda_id);
                    $('#condicion_pago_id').val(res.condicion_pago_id);
                    
                    if (res.sucursal_id) {
                        $('#sucursal_id').val(res.sucursal_id);
                        cargarPuntosVenta(res.sucursal_id, function() {
                            if (res.punto_venta_id) {
                                $('#punto_venta_id').val(res.punto_venta_id);
                                cargarTiposComprobante(res.punto_venta_id, function() {
                                    $('#comprobante_tipo_id').val(res.comprobante_tipo_id);
                                });
                            }
                        });
                    }
                    
                    if (res.detalles && res.detalles.length > 0) {
                        pedidoConfirmado = (parseInt(res.tabla_estado_registro_id) || 0) > 5;
                        detalles = res.detalles.map(function(detalle, index) {
                            return {
                                detalle_idx: index,
                                venta_pedido_detalle_id: detalle.venta_pedido_detalle_id,
                                producto_id: detalle.producto_id,
                                producto_codigo: detalle.producto_codigo,
                                producto_nombre: detalle.producto_nombre,
                                compatibilidad_texto: detalle.compatibilidad_texto || '',
                                cantidad: detalle.cantidad,
                                cantidad_entregada: detalle.cantidad_entregada || 0,
                                precio_unitario: detalle.precio_unitario,
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

                    // La solapa Remitos NO se bloquea: cargar, editar o cancelar un remito
                    // sigue siendo una acción válida aunque el pedido esté en modo
                    // visualización (ej. "Entrega Parcial" — quedan productos por
                    // entregar). Se excluye de raíz (con .not()) en vez de re-habilitar
                    // después, porque la lista de remitos generados se arma por AJAX y
                    // podía terminar de renderizarse antes o después de este disable
                    // según qué tan rápido respondiera el servidor — con .not() no
                    // importa el orden, nunca llega a deshabilitarse.
                    $('#formVentaPedido :input').not('#remitos *').prop('disabled', true);
                    $('.btn-eliminar-detalle, #btnNuevoProductoRapido, #btnLimpiarTagsProducto, #btnNuevoCliente').prop('disabled', true);

                    $('.btn-eliminar-detalle, #btnNuevoProductoRapido').hide();
                    $('#busqueda_producto').prop('disabled', true);

                    $('.card-info').hide(); // tarjeta 'Agregar Producto' (antes decía .card-primary, que apunta a la tarjeta de pestañas completa)
                    $('#btnNuevoProductoRapido').hide();

                    $('#btnGuardar').hide();
                    $('.modal-footer .btn-secondary').hide();

                    $('.btn-secondary[data-bs-dismiss="modal"]').hide();

                    $('#btnToggleFullscreen').prop('disabled', false);

                }, 500);

                var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalVentaPedido'), { backdrop: 'static', keyboard: false });
                modal.show();

                $('#modalVentaPedido').off('hidden.bs.modal').on('hidden.bs.modal', function () {
                    $('#formVentaPedido :input').prop('disabled', false);
                    $('#btnGuardar').show();
                    $('.modal-footer .btn-secondary').show();
                    $('.btn-eliminar-detalle, #btnNuevoProductoRapido, #btnLimpiarTagsProducto, #btnNuevoCliente').prop('disabled', false);

                    $('.card-info').show(); // tarjeta 'Agregar Producto'
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

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function renderizarResultadosVacio(mensajeHtml) {
        $('#resultados_busqueda').html(`<div class="text-center text-muted small p-2">${mensajeHtml}</div>`);
    }

    function renderizarResultadosBusqueda(productos) {
        ultimosResultadosBusqueda = productos || [];
        var cont = $('#resultados_busqueda');

        if (ultimosResultadosBusqueda.length === 0) {
            renderizarResultadosVacio('<i class="fas fa-circle-info me-1"></i>No se encontraron productos para ese filtro.');
            return;
        }

        var html = `<table class="table table-sm table-bordered table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th class="text-center">IVA</th>
                    <th class="text-end">Precio Ref.</th>
                    <th class="text-center" width="110">Cantidad</th>
                </tr>
            </thead>
            <tbody>`;

        ultimosResultadosBusqueda.forEach(function (item, index) {
            var ivaPorcentaje = parseFloat(item.iva_porcentaje || 0);
            var precio = parseFloat(item.precio_neto || 0);

            // La cantidad inicial del filtro refleja lo que ya está cargado en el
            // pedido para este producto (0 si todavía no se agregó), en vez de
            // arrancar siempre en 1.
            var detalleExistente = detalles.find(function (d) { return d.producto_id == item.producto_id; });
            var cantidadInicial = detalleExistente ? (parseFloat(detalleExistente.cantidad) || 0) : 0;

            html += `<tr class="resultado-libre-fila">
                <td>${item.producto_codigo || ''}</td>
                <td>${item.producto_nombre || ''}
                    ${item.compatibilidad_texto ? `<small class="text-muted d-block">${escapeHtml(item.compatibilidad_texto)}</small>` : ''}
                </td>
                <td class="text-center">${ivaPorcentaje.toFixed(2)}%</td>
                <td class="text-end">$${formatMoneda(precio)}</td>
                <td>
                    <div class="cantidad-stepper mx-auto">
                        <button type="button" class="btn-stepper btn-cantidad-filtro-menos" data-id="${item.producto_id}" tabindex="-1">&minus;</button>
                        <input type="number" class="cantidad-stepper-input input-cantidad-producto"
                            value="${cantidadInicial.toFixed(2)}" step="0.01" min="0">
                        <button type="button" class="btn-stepper btn-cantidad-filtro-mas" data-id="${item.producto_id}" tabindex="-1">+</button>
                    </div>
                </td>
            </tr>`;
        });

        html += '</tbody></table>';
        cont.html(html);
    }

    function inicializarBuscadorTagsProducto() {
        tagsProductoContainer.on('click', function (e) {
            if (e.target === this || $(e.target).is('#busqueda_producto_container')) tagsProductoInput.focus();
        });

        tagsProductoInput.on('input', function () {
            var value = $(this).val().trim();
            if (value.includes(' ')) {
                var palabras = value.split(/\s+/);
                palabras.forEach(function (palabra) {
                    if (palabra.length > 0) agregarTagProducto(palabra);
                });
                $(this).val('');
                ejecutarBusquedaProducto();
            }
        });

        tagsProductoInput.on('keydown', function (e) {
            var value = $(this).val().trim();
            if (e.key === ' ' || e.key === 'Space') {
                e.preventDefault();
                if (value.length > 0) {
                    agregarTagProducto(value);
                    $(this).val('');
                    ejecutarBusquedaProducto();
                }
            } else if (e.key === 'Backspace' && value === '' && tagsProducto.length > 0) {
                eliminarTagProducto(tagsProducto.length - 1);
                ejecutarBusquedaProducto();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (value.length > 0) {
                    agregarTagProducto(value);
                    $(this).val('');
                    ejecutarBusquedaProducto();
                }
            } else if (e.key === 'Escape') {
                $(this).blur();
            }
        });

        tagsProductoInput.on('paste', function () {
            setTimeout(function () {
                var value = tagsProductoInput.val().trim();
                if (value) {
                    var palabras = value.split(/\s+/);
                    palabras.forEach(function (palabra) {
                        if (palabra.length > 0) agregarTagProducto(palabra);
                    });
                    tagsProductoInput.val('');
                    ejecutarBusquedaProducto();
                }
            }, 10);
        });
    }

    function agregarTagProducto(texto) {
        texto = texto.trim();
        if (!texto) return;
        var duplicado = tagsProducto.some(function (tag) { return tag.toLowerCase() === texto.toLowerCase(); });
        if (duplicado) { tagsProductoInput.val(''); return; }
        tagsProducto.push(texto);
        renderizarTagsProducto();
        tagsProductoInput.val('');
        tagsProductoInput.focus();
    }

    function eliminarTagProducto(index) {
        if (index >= 0 && index < tagsProducto.length) {
            tagsProducto.splice(index, 1);
            renderizarTagsProducto();
        }
    }

    function limpiarTagsProducto() {
        tagsProducto = [];
        renderizarTagsProducto();
    }

    function renderizarTagsProducto() {
        tagsProductoContainer.find('.tag-item').remove();
        tagsProducto.forEach(function (tag, index) {
            var tagHtml = `
                <span class="tag-item" data-index="${index}">
                    <span class="tag-text">${escapeHtml(tag)}</span>
                    <span class="tag-remove" data-index="${index}" title="Eliminar"><i class="fas fa-times"></i></span>
                </span>
            `;
            tagsProductoContainer.find('#busqueda_producto').before(tagHtml);
        });
        tagsProductoContainer.find('.tag-remove').off('click').on('click', function (e) {
            e.stopPropagation();
            var index = parseInt($(this).data('index'));
            eliminarTagProducto(index);
            ejecutarBusquedaProducto();
        });
    }

    function ejecutarBusquedaProducto() {
        sincronizarPanelLateral();

        if (!clienteActualId) {
            renderizarResultadosVacio('<i class="fas fa-arrow-up me-1"></i>Seleccione un cliente primero');
            return;
        }
        if (tagsProducto.length === 0) {
            $('#resultados_busqueda').empty();
            return;
        }

        var q = tagsProducto.join(' ');
        $.ajax({
            url: 'ventas_pedidos_ajax.php',
            type: 'GET',
            data: { accion: 'buscar_productos_cliente', entidad_id: clienteActualId, q: q, empresa_idx: empresa_idx },
            dataType: 'json',
            success: function (res) {
                if (res && res.error === 'sin_lista_precios') {
                    renderizarResultadosVacio(
                        `<span class="text-danger"><i class="fas fa-triangle-exclamation me-1"></i>
                        Este cliente no tiene una lista de precios vigente asignada
                        (gestion__entidades_condiciones_clientes). No se pueden buscar ni
                        agregar productos hasta configurarla.</span>`
                    );
                    return;
                }
                renderizarResultadosBusqueda((res && res.productos) ? res.productos : []);
            },
            error: function (jqXHR, textStatus, errorThrown) {
                console.error('Error en buscar_productos_cliente:', textStatus, errorThrown);
                console.error('Respuesta cruda del servidor:', jqXHR.responseText);
                renderizarResultadosVacio(
                    '<span class="text-danger"><i class="fas fa-triangle-exclamation me-1"></i>Error del servidor al buscar productos.</span>'
                );
            }
        });
    }

    function resetBusquedaProducto() {
        tagsProducto = [];
        renderizarTagsProducto();
        tagsProductoInput.val('');
        sincronizarPanelLateral();
        if (clienteActualId) {
            $('#resultados_busqueda').empty();
        } else {
            renderizarResultadosVacio('<i class="fas fa-arrow-up me-1"></i>Seleccione un cliente primero');
        }
    }

    $('#btnLimpiarTagsProducto').on('click', function () {
        limpiarTagsProducto();
        resetBusquedaProducto();
        tagsProductoInput.focus();
    });

    inicializarBuscadorTagsProducto();

    // +/- de la fila de resultados de búsqueda: agregan o quitan una unidad
    // directamente del pedido al clickear, sin pasar por el botón "Agregar".
    // A diferencia del stepper de las líneas ya cargadas, acá no se pide
    // confirmación al llegar a 0 — es la interacción rápida de "ir tocando"
    // mientras se recorre el filtro.
    function ajustarCantidadDesdeFiltro(productoId, delta) {
        if (!clienteActualId) {
            Swal.fire({
                icon: "warning",
                title: "Seleccione cliente",
                text: "Debe seleccionar un cliente primero",
                confirmButtonText: "Entendido"
            });
            return;
        }

        var existente = detalles.find(function (d) { return d.producto_id == productoId; });

        if (existente) {
            var nuevaCantidad = Math.round(((parseFloat(existente.cantidad) || 0) + delta) * 100) / 100;
            if (nuevaCantidad <= 0) {
                detalles = detalles.filter(function (d) { return d.detalle_idx != existente.detalle_idx; });
            } else {
                existente.cantidad = nuevaCantidad;
                existente.neto_gravado = existente.cantidad * existente.precio_unitario_neto;
                existente.iva_importe = existente.neto_gravado * (existente.iva_porcentaje / 100);
                existente.total_linea = existente.neto_gravado + existente.iva_importe + (existente.no_gravado || 0) + (existente.exento || 0);
            }
        } else {
            if (delta <= 0) return; // nada cargado, no hay qué restar

            var item = ultimosResultadosBusqueda.find(function (p) { return p.producto_id == productoId; });
            if (!item) return;

            var precioBruto = parseFloat(item.precio_final || 0);
            var descuentoGeneralPct = parseFloat(item.descuento_general_pct || 0);
            var descuentoGeneralImporte = precioBruto * (descuentoGeneralPct / 100);
            var precioUnitarioNeto = precioBruto - descuentoGeneralImporte;
            var iva = parseFloat(item.iva_porcentaje || 0);
            var ivaId = item.iva_alicuota_id || null;

            var netoGravado = delta * precioUnitarioNeto;
            var ivaImporte = netoGravado * (iva / 100);

            detalles.push({
                detalle_idx: 'temp_' + new Date().getTime() + '_' + Math.random(),
                venta_pedido_detalle_id: 0,
                producto_id: productoId,
                producto_codigo: item.producto_codigo,
                producto_nombre: item.producto_nombre,
                compatibilidad_texto: item.compatibilidad_texto || '',
                cantidad: delta,
                cantidad_entregada: 0,
                precio_unitario: precioBruto,
                descuento_general_pct: descuentoGeneralPct,
                descuento_general: descuentoGeneralImporte,
                precio_unitario_neto: precioUnitarioNeto,
                no_gravado: 0,
                exento: 0,
                iva_alicuota_id: ivaId ? parseInt(ivaId) : null,
                iva_porcentaje: iva,
                neto_gravado: netoGravado,
                iva_importe: ivaImporte,
                total_linea: netoGravado + ivaImporte
            });
        }

        renderizarDetalles();
        actualizarTotales();

        var actualizado = detalles.find(function (d) { return d.producto_id == productoId; });
        var cantidadMostrada = actualizado ? (parseFloat(actualizado.cantidad) || 0) : 0;
        $('.btn-cantidad-filtro-mas[data-id="' + productoId + '"]').closest('tr').find('.input-cantidad-producto').val(cantidadMostrada.toFixed(2));
    }

    $(document).on('click', '.btn-cantidad-filtro-menos', function () {
        ajustarCantidadDesdeFiltro(parseInt($(this).data('id')), -1);
    });

    $(document).on('click', '.btn-cantidad-filtro-mas', function () {
        ajustarCantidadDesdeFiltro(parseInt($(this).data('id')), 1);
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
            sincronizarPanelLateral();
            actualizarVisibilidadCargarRemitoNuevo();
            return;
        }
        
        var html = `
        <table class="table table-sm table-bordered table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Detalle</th>
                    <th class="text-center">Cant.</th>
                    <th class="text-center">Pendiente</th>
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

            // Una vez confirmado el pedido, "Cant." pasa a ser un dato fijo (no
            // tiene sentido seguir editando cantidades ahí); antes de confirmar
            // sigue siendo el stepper +/- de siempre.
            var celdaCantidad = pedidoConfirmado
                ? `<span class="fw-bold">${formatMoneda(detalle.cantidad)}</span>`
                : `<div class="cantidad-stepper mx-auto">
                        <button type="button" class="btn-stepper btn-cantidad-menos" data-idx="${detalle.detalle_idx}" tabindex="-1">&minus;</button>
                        <input type="number" class="cantidad-stepper-input input-cantidad-detalle"
                            data-idx="${detalle.detalle_idx}" value="${detalle.cantidad}" step="0.01" min="0">
                        <button type="button" class="btn-stepper btn-cantidad-mas" data-idx="${detalle.detalle_idx}" tabindex="-1">+</button>
                    </div>`;
            
            html += `
            <tr class="${claseFila}" data-idx="${detalle.detalle_idx}">
                <td>${detalle.producto_codigo || ''}</td>
                <td>
                    <div class="fw-bold">${nombreProducto.substring(0, 35)}${nombreProducto.length > 35 ? '...' : ''}</div>
                    ${detalle.compatibilidad_texto ? `<small class="text-muted d-block">${escapeHtml(detalle.compatibilidad_texto)}</small>` : ''}
                    ${esNuevo ? '<span class="badge bg-info ms-2">Nuevo</span>' : ''}
                </td>
                <td class="text-center">${celdaCantidad}</td>
                <td class="text-center">${(function () {
                    var pendiente = (parseFloat(detalle.cantidad) || 0) - (parseFloat(detalle.cantidad_entregada) || 0);
                    if (pendiente < 0.0001) return formatMoneda(0);
                    return `<span class="text-danger fw-bold">${formatMoneda(pendiente)}</span>`;
                })()}</td>
                <td class="text-end">$${formatMoneda(detalle.precio_unitario_neto)}</td>
                <td class="text-center">${detalle.iva_porcentaje.toFixed(2)}%</td>
                <td class="text-end">$${formatMoneda(detalle.iva_importe)}</td>
                <td class="text-end fw-bold text-success">$${formatMoneda(detalle.total_linea)}</td>
                <td class="text-center">
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
        sincronizarPanelLateral();
        actualizarVisibilidadCargarRemitoNuevo();
    }

    // Panel lateral compacto (Código, Detalle, Precio Neto, Total) que se muestra
    // al costado del buscador mientras el filtro está activo, para ver de un
    // vistazo lo ya incorporado sin perder de vista los resultados de búsqueda.
    // Al limpiar el filtro, se oculta y vuelve la tabla completa de abajo.
    function renderizarDetalleLateral() {
        var cont = $('#contenedor_detalle_lateral');

        if (detalles.length === 0) {
            cont.html('<div class="text-muted small text-center p-2">Todavía no hay productos en el pedido.</div>');
            return;
        }

        var html = `<table class="table table-sm table-bordered table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Detalle</th>
                    <th class="text-center">Cantidad</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>`;

        detalles.forEach(function (detalle) {
            var nombreProducto = detalle.producto_nombre || '';
            html += `<tr>
                <td>${detalle.producto_codigo || ''}</td>
                <td>${nombreProducto.substring(0, 25)}${nombreProducto.length > 25 ? '...' : ''}</td>
                <td class="text-center">${formatMoneda(detalle.cantidad)}</td>
                <td class="text-end fw-bold">$${formatMoneda(detalle.total_linea)}</td>
            </tr>`;
        });

        html += '</tbody></table>';
        cont.html(html);
    }

    function sincronizarPanelLateral() {
        var filtroActivo = tagsProducto.length > 0;
        if (filtroActivo) {
            $('#col_detalle_lateral, #col_detalle_lateral_titulo').removeClass('d-none');
            $('#zona_detalle_completa').addClass('d-none');
            renderizarDetalleLateral();
        } else {
            $('#col_detalle_lateral, #col_detalle_lateral_titulo').addClass('d-none');
            $('#zona_detalle_completa').removeClass('d-none');
        }
    }

    // Sube/baja la cantidad de una línea ya cargada, recalculando neto/IVA/total.
    // Si la nueva cantidad es 0 (o menos), pregunta si se desea eliminar el producto
    // en lugar de dejar una línea en cero; si no se confirma, se restaura la cantidad
    // que tenía antes del cambio.
    function cambiarCantidadDetalle(idx, nuevaCantidad) {
        var detalle = detalles.find(function (d) { return d.detalle_idx == idx; });
        if (!detalle) return;

        if (isNaN(nuevaCantidad)) {
            renderizarDetalles();
            return;
        }

        // Mientras el pedido sigue editable (estado <= 5), se puede seguir ajustando
        // la cantidad, pero nunca por debajo de lo que ya salió físicamente por remito.
        var yaEntregado = parseFloat(detalle.cantidad_entregada) || 0;
        if (nuevaCantidad < yaEntregado - 0.0001) {
            Swal.fire({
                icon: 'warning',
                title: 'Cantidad inválida',
                text: 'No se puede bajar de ' + formatMoneda(yaEntregado) + ': ya se remitieron esa cantidad de unidades.',
                confirmButtonText: 'Entendido'
            });
            renderizarDetalles();
            return;
        }

        if (nuevaCantidad <= 0) {
            Swal.fire({
                title: '¿Eliminar producto?',
                text: 'La cantidad llegó a 0. ¿Desea quitar "' + (detalle.producto_nombre || '') + '" del pedido?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    detalles = detalles.filter(function (d) { return d.detalle_idx != idx; });
                    renderizarDetalles();
                    actualizarTotales();
                    Swal.fire({ icon: 'success', title: 'Eliminado', showConfirmButton: false, timer: 1200, toast: true, position: 'top-end' });
                } else {
                    renderizarDetalles();
                }
            });
            return;
        }

        detalle.cantidad = nuevaCantidad;
        detalle.neto_gravado = detalle.cantidad * detalle.precio_unitario_neto;
        detalle.iva_importe = detalle.neto_gravado * (detalle.iva_porcentaje / 100);
        detalle.total_linea = detalle.neto_gravado + detalle.iva_importe + (detalle.no_gravado || 0) + (detalle.exento || 0);

        renderizarDetalles();
        actualizarTotales();
    }

    // Los botones +/- suman o restan una unidad entera; para cantidades con decimales
    // (ej. productos que se venden por peso) se puede escribir el valor exacto en el campo.
    $(document).on('click', '.btn-cantidad-menos', function () {
        var idx = $(this).data('idx');
        var detalle = detalles.find(function (d) { return d.detalle_idx == idx; });
        if (!detalle) return;
        cambiarCantidadDetalle(idx, Math.round(((parseFloat(detalle.cantidad) || 0) - 1) * 100) / 100);
    });

    $(document).on('click', '.btn-cantidad-mas', function () {
        var idx = $(this).data('idx');
        var detalle = detalles.find(function (d) { return d.detalle_idx == idx; });
        if (!detalle) return;
        cambiarCantidadDetalle(idx, Math.round(((parseFloat(detalle.cantidad) || 0) + 1) * 100) / 100);
    });

    $(document).on('change', '.input-cantidad-detalle', function () {
        var idx = $(this).data('idx');
        cambiarCantidadDetalle(idx, parseFloat($(this).val()));
    });

    function formatMoneda(valor) {
        return (valor || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function actualizarTotales() {
        var totalNeto = 0;
        var totalNoGravado = 0;
        var totalExento = 0;
        var totalImpuestos = 0;
        var totalDescuentos = 0;
        var totalCantidad = 0;
        
        detalles.forEach(function(detalle) {
            totalNeto += detalle.neto_gravado || 0;
            totalImpuestos += detalle.iva_importe || 0;
            totalNoGravado += detalle.no_gravado || 0;
            totalExento += detalle.exento || 0;
            totalDescuentos += (detalle.descuento_general || 0) * (detalle.cantidad || 0);
            totalCantidad += parseFloat(detalle.cantidad) || 0;
        });
        
        var totalGeneral = totalNeto + totalImpuestos + totalNoGravado + totalExento;
        var totalBruto = totalNeto + totalDescuentos;
        
        $('#total_neto').val(totalNeto.toFixed(2));
        $('#descuentos').val(totalDescuentos.toFixed(2));
        $('#no_gravado').val(totalNoGravado.toFixed(2));
        $('#exento').val(totalExento.toFixed(2));
        $('#impuestos').val(totalImpuestos.toFixed(2));
        $('#total').val(totalGeneral.toFixed(2));

        // Mismo resumen, replicado en la solapa "Datos del Pedido"
        $('#bruto_display_resumen').text(formatMoneda(totalBruto));
        $('#descuento_display_resumen').text(formatMoneda(totalDescuentos));
        $('#total_neto_display_resumen').text(formatMoneda(totalNeto));
        $('#impuestos_display_resumen').text(formatMoneda(totalImpuestos));
        $('#total_display_resumen').text(formatMoneda(totalGeneral));
        $('#cantidad_display_resumen').text(formatMoneda(totalCantidad));

        // Y en la versión compacta arriba de "Agregar Producto" (solapa Productos)
        $('#bruto_display_resumen_mini').text(formatMoneda(totalBruto));
        $('#descuento_display_resumen_mini').text(formatMoneda(totalDescuentos));
        $('#total_neto_display_resumen_mini').text(formatMoneda(totalNeto));
        $('#impuestos_display_resumen_mini').text(formatMoneda(totalImpuestos));
        $('#total_display_resumen_mini').text(formatMoneda(totalGeneral));
        $('#cantidad_display_resumen_mini').text(formatMoneda(totalCantidad));

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
        var detalle = detalles.find(function (d) { return d.detalle_idx == idx; });
        if (!detalle) return;

        var yaEntregado = parseFloat(detalle.cantidad_entregada) || 0;
        if (yaEntregado > 0.0001) {
            Swal.fire({
                icon: 'warning',
                title: 'No se puede eliminar',
                text: 'Ya se remitieron ' + formatMoneda(yaEntregado) + ' unidades de este producto. Reducí la cantidad hasta ese valor en vez de eliminarlo.',
                confirmButtonText: 'Entendido'
            });
            return;
        }
        
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

        // El combo de tipo de comprobante ya no se carga acá de forma estática:
        // depende del punto de venta elegido (ver cargarTiposComprobante), así que
        // arranca vacío hasta que el usuario seleccione sucursal + punto de venta.
        $('#comprobante_tipo_id').html('<option value="">Primero seleccione punto de venta</option>');
        $('#comprobante_tipo_id').prop('disabled', true);

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
        pedidoConfirmado = false;
        pedidoPuntoVentaId = null;
        pedidoPuntoVentaNombre = '';
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
        resetBusquedaProducto();

        $('.btn-secondary[data-bs-dismiss="modal"]').show();

        $('.btn-eliminar-detalle, #btnNuevoProductoRapido, #btnLimpiarTagsProducto').show().prop('disabled', false);
        $('#busqueda_producto').prop('disabled', false);

        $('#btnImprimirDesdeEdicion').remove();
        
        window.sucursalIdEditar = null;

        resetSolapaRemitos();
    }

    $(document).on('click', '#btnNuevo', function () {
        resetModal();
        $('#modalLabel').text('Nuevo Pedido de Venta');
        cargarCombosFormulario();
        cargarClientesYSucursales();
        
        var today = new Date().toISOString().split('T')[0];
        $('#f_emision').val(today);

        var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalVentaPedido'), { backdrop: 'static', keyboard: false });
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
                cargarClientesYSucursales(function() {
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
                    if (tagsProducto.length === 0) {
                        $('#resultados_busqueda').empty();
                    }

                    // Encadenado acá adentro (no en paralelo): ver comentario equivalente
                    // en cargarPedidoParaVisualizar.
                    pedidoPuntoVentaId = res.punto_venta_id;
                    pedidoPuntoVentaNombre = res.punto_venta_nombre;
                    fijarPuntoVentaRemito(pedidoPuntoVentaId, pedidoPuntoVentaNombre, function () {
                        cargarTiposComprobanteRemito(pedidoPuntoVentaId, function () {
                            cargarPendientesRemitoPedido();
                        });
                    });
                });
                
                $('#venta_pedido_id').val(res.venta_pedido_id);
                $('#comprobante_nro').val(res.comprobante_nro);

                mostrarTabRemitos(true);
                cargarRemitosDelPedido(res.venta_pedido_id);
                $('#remito_f_emision').val(new Date().toISOString().split('T')[0]);
                
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
                    $('#moneda_id').val(res.moneda_id);
                    $('#condicion_pago_id').val(res.condicion_pago_id);
                    
                    if (res.sucursal_id) {
                        $('#sucursal_id').val(res.sucursal_id);
                        cargarPuntosVenta(res.sucursal_id, function() {
                            if (res.punto_venta_id) {
                                $('#punto_venta_id').val(res.punto_venta_id);
                                cargarTiposComprobante(res.punto_venta_id, function() {
                                    $('#comprobante_tipo_id').val(res.comprobante_tipo_id);
                                });
                            }
                        });
                    }
                    
                    if (res.detalles && res.detalles.length > 0) {
                        pedidoConfirmado = (parseInt(res.tabla_estado_registro_id) || 0) > 5;
                        detalles = res.detalles.map(function(detalle, index) {
                            return {
                                detalle_idx: index,
                                venta_pedido_detalle_id: detalle.venta_pedido_detalle_id,
                                producto_id: detalle.producto_id,
                                producto_codigo: detalle.producto_codigo,
                                producto_nombre: detalle.producto_nombre,
                                compatibilidad_texto: detalle.compatibilidad_texto || '',
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
                    
                    if (pedidoConfirmado) {
                        $('.btn-secondary[data-bs-dismiss="modal"]').hide();
                        $('.btn-eliminar-detalle, #btnNuevoProductoRapido, #btnLimpiarTagsProducto').hide();
                        $('#busqueda_producto').prop('disabled', true);
                    } else {
                        $('.btn-secondary[data-bs-dismiss="modal"]').show();
                        $('.btn-eliminar-detalle, #btnNuevoProductoRapido, #btnLimpiarTagsProducto').show();
                        $('#busqueda_producto').prop('disabled', false);
                    }
                    
                }, 500);

                var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalVentaPedido'), { backdrop: 'static', keyboard: false });
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

    // ============================================================
    // SOLAPA "REMITOS"
    // ============================================================
    // Todo lo que sigue pega directo contra ventas_remitos_ajax.php (cross-file),
    // reutilizando el motor de remitos tal cual está — no se duplica numeración,
    // sincronización de comprobante, ni actualización de cantidad_entregada.
    // La única función nueva del lado del servidor es obtenerRemitosDePedido()
    // en ventas_pedidos_model.php, que solo LISTA (no escribe nada).

    // Vuelve a traer cantidad_entregada por línea desde el pedido (sin recargar
    // todo el modal) para que "Entregado" y la visibilidad de "Cargar Remito
    // Nuevo" queden al día apenas se guarda/cancela un remito desde esta solapa.
    function refrescarCantidadesEntregadas(pedidoId) {
        if (!pedidoId) return;
        $.ajax({
            url: 'ventas_pedidos_ajax.php',
            type: 'GET',
            data: { accion: 'obtener', venta_pedido_id: pedidoId, empresa_idx: empresa_idx },
            dataType: 'json',
            success: function (res) {
                if (!res || !res.detalles) return;
                res.detalles.forEach(function (d) {
                    var local = detalles.find(function (x) { return x.venta_pedido_detalle_id == d.venta_pedido_detalle_id; });
                    if (local) local.cantidad_entregada = parseFloat(d.cantidad_entregada) || 0;
                });
                renderizarDetalles();
            }
        });
    }

    function mostrarTabRemitos(mostrar) {
        $('#tab-item-remitos').toggle(!!mostrar);
    }

    // Se calcula directo de cantidad vs. cantidad_entregada por línea, no de un
    // texto de estado tipo "Entrega Total" — comparar contra una etiqueta hardcodeada
    // es frágil (ya nos pasó con 'confirmar' vs 'confirmar_pedido'); esto es la
    // fuente de verdad real.
    function actualizarVisibilidadCargarRemitoNuevo() {
        var entregaCompleta = detalles.length > 0 && detalles.every(function (d) {
            return (parseFloat(d.cantidad_entregada) || 0) >= (parseFloat(d.cantidad) || 0) - 0.0001;
        });
        $('#card-cargar-remito-nuevo').toggle(!entregaCompleta);
    }

    function resetSolapaRemitos() {
        remitosPedidoActual = [];
        remitoPendientesPedido = [];
        remitoDetallesNuevo = [];
        remitoEditandoId = null;
        remitoLineasOriginalesPorVpd = {};
        $('#contenedor-remitos-pedido').html('<div class="text-muted small text-center p-3">Todavía no hay remitos generados para este pedido.</div>');
        $('#contador-remitos').text('0');
        $('#remito_punto_venta_id').html('<option value="">Seleccionar</option>');
        $('#remito_comprobante_tipo_id').html('<option value="">Primero seleccione punto de venta</option>');
        $('#remito_observaciones').val('');
        $('#contenedor-remito-pendientes').html('<div class="text-muted small p-2">Elegí el punto de venta para ver las líneas pendientes de este pedido.</div>');
        $('#titulo-card-remito').html('<i class="fas fa-plus-circle me-2"></i>Cargar Remito Nuevo');
        $('#btnCancelarEdicionRemito').addClass('d-none');
        $('#card-cargar-remito-nuevo').show();
        renderizarRemitoDetalleNuevo();
        mostrarTabRemitos(false);
    }

    function formatFechaCorta(fecha) {
        if (!fecha) return '';
        var partes = fecha.split('-');
        if (partes.length !== 3) return fecha;
        return partes[2] + '/' + partes[1] + '/' + partes[0];
    }

    // ---------- Remitos ya generados para este pedido ----------
    function cargarRemitosDelPedido(pedidoId) {
        if (!pedidoId) {
            remitosPedidoActual = [];
            renderizarRemitosPedido();
            return;
        }
        $.ajax({
            url: 'ventas_pedidos_ajax.php',
            type: 'GET',
            data: {
                accion: 'obtener_remitos_pedido',
                venta_pedido_id: pedidoId,
                pagina_idx_remitos: REMITOS_PAGINA_IDX,
                empresa_idx: empresa_idx
            },
            dataType: 'json',
            success: function (res) {
                remitosPedidoActual = res || [];
                renderizarRemitosPedido();
            },
            error: function () {
                $('#contenedor-remitos-pedido').html(
                    '<div class="text-danger small p-2"><i class="fas fa-triangle-exclamation me-1"></i>Error al consultar los remitos de este pedido.</div>'
                );
            }
        });
    }

    function renderizarRemitosPedido() {
        $('#contador-remitos').text(remitosPedidoActual.length);

        if (remitosPedidoActual.length === 0) {
            $('#contenedor-remitos-pedido').html('<div class="text-muted small text-center p-3">Todavía no hay remitos generados para este pedido.</div>');
            return;
        }

        var html = '';
        remitosPedidoActual.forEach(function (remito) {
            var estado = remito.estado_info || {};
            var numero = remito.comprobante_nro > 0
                ? `${remito.comprobante_tipo || 'Remito'} #${remito.comprobante_nro}`
                : `${remito.comprobante_tipo || 'Remito'} (sin numerar)`;

            var botonesHtml = '';
            (remito.botones || []).forEach(function (boton) {
                var claseBoton = 'btn-sm me-1 ';
                if (boton.bg_clase && boton.text_clase) {
                    claseBoton += boton.bg_clase + ' ' + boton.text_clase;
                } else if (boton.color_clase) {
                    claseBoton += boton.color_clase;
                } else {
                    claseBoton += 'btn-outline-primary';
                }
                var icono = boton.icono_clase ? `<i class="${boton.icono_clase}"></i>` : '';
                var titulo = boton.descripcion || boton.nombre_funcion;

                if (boton.accion_js === 'editar') {
                    botonesHtml += `<button type="button" class="btn ${claseBoton} btn-editar-remito-inline"
                        data-id="${remito.venta_remito_id}"
                        title="${titulo}">${icono}</button>`;
                } else if (boton.accion_js === 'visualizar') {
                    botonesHtml += `<button type="button" class="btn ${claseBoton} btn-ver-resumen-remito"
                        data-id="${remito.venta_remito_id}"
                        title="${titulo}">${icono}</button>`;
                } else {
                    botonesHtml += `<button type="button" class="btn ${claseBoton} btn-accion-remito"
                        data-id="${remito.venta_remito_id}"
                        data-accion="${boton.accion_js}"
                        data-confirmable="${boton.es_confirmable || 0}"
                        data-comprobante="${numero}"
                        title="${titulo}">${icono}</button>`;
                }
            });

            var filasDetalle = '';
            (remito.detalles || []).forEach(function (d) {
                filasDetalle += `<tr>
                    <td>${d.producto_codigo || ''}</td>
                    <td>${d.producto_nombre || ''}</td>
                    <td class="text-center">${formatMoneda(d.cantidad)}</td>
                    <td class="text-end">$${formatMoneda(d.importe_linea)}</td>
                </tr>`;
            });

            // Confirmado (numerado) = arranca resumido/colapsado, con botón para
            // expandir. Sin numerar (borrador) sigue expandido siempre, porque
            // suele ser el que se está terminando de armar.
            var confirmado = remito.comprobante_nro > 0;
            var collapseId = 'collapse-remito-' + remito.venta_remito_id;
            var botonToggle = confirmado
                ? `<button type="button" class="btn btn-sm btn-outline-secondary me-2 btn-toggle-remito-resumen"
                        data-bs-toggle="collapse" data-bs-target="#${collapseId}" title="Ver/ocultar detalle">
                        <i class="fas fa-chevron-down"></i>
                    </button>`
                : '';

            html += `<div class="border rounded mb-2 p-2">
                <div class="d-flex justify-content-between align-items-center flex-wrap gap-1 mb-1">
                    <div>
                        ${botonToggle}
                        <strong>${numero}</strong>
                        <span class="text-muted small ms-2">${formatFechaCorta(remito.f_emision)}</span>
                        <span class="badge ${estado.bg_clase || 'bg-dark'} ${estado.text_clase || 'text-white'} ms-2">${estado.estado_registro || 'Sin estado'}</span>
                    </div>
                    <div>${botonesHtml}</div>
                </div>
                <div class="collapse${confirmado ? '' : ' show'}" id="${collapseId}">
                    <table class="table table-sm table-bordered mb-0">
                        <thead class="table-light">
                            <tr><th>Código</th><th>Producto</th><th class="text-center">Cant.</th><th class="text-end">Importe</th></tr>
                        </thead>
                        <tbody>${filasDetalle}</tbody>
                    </table>
                </div>
            </div>`;
        });

        $('#contenedor-remitos-pedido').html(html);
    }

    // Solo gira el ícono; el propio Bootstrap ya maneja mostrar/ocultar vía
    // data-bs-toggle="collapse" en el botón.
    $(document).on('click', '.btn-toggle-remito-resumen', function () {
        $(this).find('i').toggleClass('fa-chevron-down fa-chevron-up');
    });

    $(document).on('click', '.btn-accion-remito', function () {
        var remitoId = $(this).data('id');
        var accionJs = $(this).data('accion');
        var confirmable = $(this).data('confirmable');
        var comprobanteInfo = $(this).data('comprobante') || ('Remito #' + remitoId);

        function ejecutar() {
            $.post('ventas_remitos_ajax.php', {
                accion: 'ejecutar_accion',
                venta_remito_id: remitoId,
                accion_js: accionJs,
                empresa_idx: empresa_idx,
                pagina_idx: REMITOS_PAGINA_IDX
            }, function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: res.message || 'Acción ejecutada', showConfirmButton: false, timer: 1500, toast: true, position: 'top-end' });
                    var pedidoId = $('#venta_pedido_id').val();
                    cargarRemitosDelPedido(pedidoId);
                    cargarPendientesRemitoPedido();
                    refrescarCantidadesEntregadas(pedidoId);
                    if (tabla) tabla.ajax.reload(null, false);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.error || 'Error al ejecutar la acción', confirmButtonText: 'Entendido' });
                }
            }, 'json').fail(function () {
                Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con el servidor', confirmButtonText: 'Entendido' });
            });
        }

        if (confirmable == 1) {
            Swal.fire({
                title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                html: `¿Está seguro de <strong>${accionJs}</strong> el remito<br><strong>${comprobanteInfo}</strong>?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: `Sí, ${accionJs}`,
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                allowOutsideClick: false
            }).then(function (result) {
                if (result.isConfirmed) ejecutar();
            });
        } else {
            ejecutar();
        }
    });

    // ---------- Editar remito inline (sin saltar a ventas_remitos.php) ----------
    // Reutiliza la MISMA tarjeta "Cargar Remito Nuevo": la llena con los datos del
    // remito elegido y cambia su modo a edición (título, botón de guardar y un
    // botón para cancelar y volver a "nuevo"). editarRemitoVenta() en el backend
    // ya rechaza editar un remito confirmado/numerado, así que ese botón no
    // aparece para esos casos (viene de obtenerBotonesPorEstadoRemito).
    // ---------- Ver resumen de un remito, inline (sin pestaña nueva) ----------
    // Es de solo lectura, así que no hace falta reconstruir el formulario completo
    // de ventas_remitos (con el riesgo de ids duplicados que eso implica) — alcanza
    // con traer los datos y mostrarlos en un resumen.
    function verResumenRemitoInline(remitoId) {
        $.ajax({
            url: 'ventas_remitos_ajax.php',
            type: 'GET',
            data: { accion: 'obtener', venta_remito_id: remitoId, empresa_idx: empresa_idx },
            dataType: 'json',
            success: function (res) {
                if (!res || !res.venta_remito_id) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Error al obtener datos del remito', confirmButtonText: 'Entendido' });
                    return;
                }

                var numero = res.comprobante_nro > 0
                    ? `${res.comprobante_tipo || 'Remito'} #${res.comprobante_nro}`
                    : `${res.comprobante_tipo || 'Remito'} (sin numerar)`;

                var filasDetalle = '';
                var total = 0;
                (res.detalles || []).forEach(function (d) {
                    var importe = parseFloat(d.importe_linea) || 0;
                    total += importe;
                    filasDetalle += `<tr>
                        <td>${d.producto_codigo || ''}</td>
                        <td class="text-start">${d.producto_nombre || ''}</td>
                        <td class="text-center">${formatMoneda(d.cantidad)}</td>
                        <td class="text-end">$${formatMoneda(importe)}</td>
                    </tr>`;
                });

                var html = `
                    <div class="text-start small">
                        <div class="row g-2 mb-2">
                            <div class="col-6"><strong>Punto de Venta:</strong> ${res.punto_venta_nombre || '-'}</div>
                            <div class="col-6"><strong>Fecha:</strong> ${formatFechaCorta(res.f_emision)}</div>
                            <div class="col-6"><strong>Cliente:</strong> ${res.entidad_nombre || '-'}</div>
                            <div class="col-6"><strong>Depósito:</strong> ${res.boca_nombre || '-'}</div>
                        </div>
                        ${res.observaciones ? `<div class="mb-2"><strong>Observaciones:</strong> ${escapeHtml(res.observaciones)}</div>` : ''}
                        <table class="table table-sm table-bordered mb-0">
                            <thead class="table-light">
                                <tr><th>Código</th><th>Producto</th><th class="text-center">Cant.</th><th class="text-end">Importe</th></tr>
                            </thead>
                            <tbody>${filasDetalle}</tbody>
                            <tfoot>
                                <tr class="fw-bold"><td colspan="3" class="text-end">Total</td><td class="text-end">$${formatMoneda(total)}</td></tr>
                            </tfoot>
                        </table>
                    </div>`;

                Swal.fire({
                    title: numero,
                    html: html,
                    width: 650,
                    confirmButtonText: 'Cerrar'
                });
            },
            error: function () {
                Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo obtener el remito', confirmButtonText: 'Entendido' });
            }
        });
    }

    $(document).on('click', '.btn-ver-resumen-remito', function () {
        verResumenRemitoInline($(this).data('id'));
    });

    function cargarRemitoParaEditarDesdePedido(remitoId) {
        $.ajax({
            url: 'ventas_remitos_ajax.php',
            type: 'GET',
            data: { accion: 'obtener', venta_remito_id: remitoId, empresa_idx: empresa_idx },
            dataType: 'json',
            success: function (res) {
                if (!res || !res.venta_remito_id) {
                    Swal.fire({ icon: 'error', title: 'Error', text: 'Error al obtener datos del remito', confirmButtonText: 'Entendido' });
                    return;
                }

                remitoEditandoId = res.venta_remito_id;
                remitoLineasOriginalesPorVpd = {};

                remitoDetallesNuevo = (res.detalles || []).map(function (d) {
                    if (d.venta_pedido_detalle_id) {
                        remitoLineasOriginalesPorVpd[d.venta_pedido_detalle_id] =
                            (remitoLineasOriginalesPorVpd[d.venta_pedido_detalle_id] || 0) + (parseFloat(d.cantidad) || 0);
                    }
                    return {
                        venta_pedido_detalle_id: d.venta_pedido_detalle_id,
                        producto_id: d.producto_id,
                        producto_codigo: d.producto_codigo,
                        producto_nombre: d.producto_nombre,
                        cantidad: parseFloat(d.cantidad) || 0
                    };
                });
                renderizarRemitoDetalleNuevo();

                $('#remito_f_emision').val(res.f_emision);
                $('#remito_observaciones').val(res.observaciones);

                fijarPuntoVentaRemito(pedidoPuntoVentaId, pedidoPuntoVentaNombre, function () {
                    cargarTiposComprobanteRemito(pedidoPuntoVentaId, function () {
                        $('#remito_comprobante_tipo_id').val(res.comprobante_tipo_id);
                        cargarPendientesRemitoPedido();
                    });
                });

                $('#titulo-card-remito').html('<i class="fas fa-pen me-2"></i>Editando Remito' +
                    (res.comprobante_nro > 0 ? ' #' + res.comprobante_nro : ' (sin numerar)'));
                $('#btnCancelarEdicionRemito').removeClass('d-none');
                // Editar un remito ya cargado sigue siendo válido aunque la entrega del
                // pedido esté completa (ej. corregir algo) — se fuerza a visible incluso
                // si actualizarVisibilidadCargarRemitoNuevo() la había ocultado.
                $('#card-cargar-remito-nuevo').show();
                $('#tab-remitos').get(0).click();

                Swal.fire({ icon: 'info', title: 'Remito cargado para editar', showConfirmButton: false, timer: 1200, toast: true, position: 'top-end' });
            },
            error: function () {
                Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo obtener el remito', confirmButtonText: 'Entendido' });
            }
        });
    }

    $(document).on('click', '.btn-editar-remito-inline', function () {
        cargarRemitoParaEditarDesdePedido($(this).data('id'));
    });

    function cancelarEdicionRemito() {
        remitoEditandoId = null;
        remitoLineasOriginalesPorVpd = {};
        remitoDetallesNuevo = [];
        renderizarRemitoDetalleNuevo();
        $('#remito_observaciones').val('');
        $('#remito_punto_venta_id').val('');
        $('#remito_comprobante_tipo_id').html('<option value="">Primero seleccione punto de venta</option>');
        $('#remito_f_emision').val(new Date().toISOString().split('T')[0]);
        $('#titulo-card-remito').html('<i class="fas fa-plus-circle me-2"></i>Cargar Remito Nuevo');
        $('#btnCancelarEdicionRemito').addClass('d-none');
        actualizarVisibilidadCargarRemitoNuevo();
        cargarPendientesRemitoPedido();
    }

    $(document).on('click', '#btnCancelarEdicionRemito', function () {
        cancelarEdicionRemito();
    });

    // ---------- Cargar remito nuevo ----------
    // El remito siempre respeta el punto de venta del propio pedido — no se
    // elige de una lista, para no poder remitir desde un depósito distinto al
    // que corresponde a este pedido/cliente.
    function fijarPuntoVentaRemito(puntoVentaId, puntoVentaNombre, callback) {
        if (puntoVentaId) {
            $('#remito_punto_venta_id').html(
                `<option value="${puntoVentaId}" selected>${puntoVentaNombre || ('PV ' + puntoVentaId)}</option>`
            ).prop('disabled', true);
        } else {
            $('#remito_punto_venta_id').html('<option value="">Pedido sin punto de venta asignado</option>').prop('disabled', true);
        }
        if (typeof callback === 'function') callback();
    }

    function cargarTiposComprobanteRemito(puntoVentaId, callback) {
        if (!puntoVentaId) {
            $('#remito_comprobante_tipo_id').html('<option value="">Primero seleccione punto de venta</option>');
            if (typeof callback === 'function') callback();
            return;
        }
        $.ajax({
            url: 'ventas_remitos_ajax.php',
            type: 'GET',
            data: {
                accion: 'obtener_comprobantes_tipos',
                punto_venta_id: puntoVentaId,
                pagina_idx: REMITOS_PAGINA_IDX,
                empresa_idx: empresa_idx
            },
            dataType: 'json',
            success: function (data) {
                if (data && data.length > 0) {
                    var options = data.length === 1 ? '' : '<option value="">Seleccionar</option>';
                    data.forEach(function (item) {
                        options += `<option value="${item.comprobante_tipo_id}">${item.comprobante_tipo}</option>`;
                    });
                    $('#remito_comprobante_tipo_id').html(options);
                    if (data.length === 1) $('#remito_comprobante_tipo_id').val(data[0].comprobante_tipo_id);
                } else {
                    $('#remito_comprobante_tipo_id').html('<option value="">Sin tipos habilitados para este punto de venta</option>');
                }
                if (typeof callback === 'function') callback();
            },
            error: function () {
                $('#remito_comprobante_tipo_id').html('<option value="">Error al cargar</option>');
                if (typeof callback === 'function') callback();
            }
        });
    }

    $(document).on('change', '#remito_punto_venta_id', function () {
        cargarTiposComprobanteRemito($(this).val());
        cargarPendientesRemitoPedido();
    });

    function cargarPendientesRemitoPedido() {
        var pedidoId = $('#venta_pedido_id').val();
        var puntoVentaId = $('#remito_punto_venta_id').val();

        if (!clienteActualId || !pedidoId) {
            remitoPendientesPedido = [];
            renderizarPendientesRemitoPedido();
            return;
        }

        $.ajax({
            url: 'ventas_remitos_ajax.php',
            type: 'GET',
            data: {
                accion: 'obtener_pedidos_pendientes_cliente',
                entidad_id: clienteActualId,
                pedido_id: pedidoId,
                punto_venta_id: puntoVentaId || '',
                empresa_idx: empresa_idx
            },
            dataType: 'json',
            success: function (data) {
                remitoPendientesPedido = data || [];

                // Al editar: una línea que ESTE remito cubrió por completo (sin nada
                // pendiente en otro lado) no viene en la respuesta, porque el backend
                // todavía no revirtió su cantidad_entregada — recién se revierte al
                // guardar. Sin este agregado, sacarla del borrador no la vuelve a
                // mostrar como disponible para remitir. Se completa con los datos que
                // ya tiene la solapa Productos (misma fuente que "Pendiente" de esa
                // tabla), con pendiente=0 de base: la corrección de
                // renderizarPendientesRemitoPedido() ya suma lo que este remito tenía
                // reservado para llegar al total real.
                if (remitoEditandoId) {
                    Object.keys(remitoLineasOriginalesPorVpd).forEach(function (vpdId) {
                        var yaListada = remitoPendientesPedido.some(function (p) {
                            return p.venta_pedido_detalle_id == vpdId;
                        });
                        if (yaListada) return;

                        var lineaPedido = detalles.find(function (d) {
                            return d.venta_pedido_detalle_id == vpdId;
                        });
                        if (!lineaPedido) return;

                        remitoPendientesPedido.push({
                            venta_pedido_detalle_id: lineaPedido.venta_pedido_detalle_id,
                            producto_id: lineaPedido.producto_id,
                            producto_codigo: lineaPedido.producto_codigo,
                            producto_nombre: lineaPedido.producto_nombre,
                            pendiente: 0,
                            ubicaciones_detalle: []
                        });
                    });
                }

                renderizarPendientesRemitoPedido();
            },
            error: function () {
                $('#contenedor-remito-pendientes').html(
                    '<div class="text-danger small p-2"><i class="fas fa-triangle-exclamation me-1"></i>Error al consultar los pendientes.</div>'
                );
            }
        });
    }

    function cantidadYaEnBorradorRemito(vpdId) {
        var total = 0;
        remitoDetallesNuevo.forEach(function (d) {
            if (d.venta_pedido_detalle_id == vpdId) total += parseFloat(d.cantidad) || 0;
        });
        return total;
    }

    // Igual que "renderUbicacionesPendiente" de ventas_remitos.js — mismas clases de
    // badge, que se copiaron al CSS de esta página para que se vean igual.
    function renderUbicacionesRemitoPendiente(ubicaciones) {
        if (!ubicaciones || !ubicaciones.length) {
            return '<span class="text-muted small">Sin ubicación</span>';
        }
        var html = '<div class="ubicaciones-pendiente-container">';
        ubicaciones.forEach(function (u) {
            var partes = [];
            if (u.seccion) partes.push(`<span class="badge badge-ubicacion badge-seccion">${u.seccion}</span>`);
            if (u.estanteria) partes.push(`<span class="badge badge-ubicacion badge-estanteria">${u.estanteria}</span>`);
            if (u.estante) partes.push(`<span class="badge badge-ubicacion badge-estante">${u.estante}</span>`);
            if (u.posicion) partes.push(`<span class="badge badge-ubicacion badge-posicion">${u.posicion}</span>`);
            if (partes.length > 0) {
                html += `<div class="ubicacion-item d-flex flex-wrap align-items-center gap-1">${partes.join(' ')}</div>`;
            }
        });
        html += '</div>';
        return html;
    }

    function renderizarPendientesRemitoPedido() {
        var cont = $('#contenedor-remito-pendientes');

        var filas = [];
        remitoPendientesPedido.forEach(function (linea) {
            var yaEnBorrador = cantidadYaEnBorradorRemito(linea.venta_pedido_detalle_id);
            // En modo edición, linea.pendiente ya viene descontando lo que ESTE MISMO
            // remito reservó la vez anterior (todavía no se revirtió, eso pasa recién
            // al guardar). Sin esta corrección se restaría dos veces: una en el propio
            // "pendiente" de la base, y otra acá al restar el borrador. Se suma de
            // vuelta lo que este remito ya tenía para esa línea (mismo criterio que
            // "pendiente_disponible" en obtenerRemitoVentaPorId).
            var reservadoPorEsteRemito = (remitoEditandoId && remitoLineasOriginalesPorVpd[linea.venta_pedido_detalle_id])
                ? remitoLineasOriginalesPorVpd[linea.venta_pedido_detalle_id] : 0;
            var pendienteBase = linea.pendiente + reservadoPorEsteRemito;
            var pendienteEfectivo = Math.max(0, pendienteBase - yaEnBorrador);
            if (pendienteEfectivo <= 0.0001) return;
            filas.push({ linea: linea, pendienteEfectivo: pendienteEfectivo });
        });

        if (filas.length === 0) {
            cont.html('<div class="text-muted small p-2">No hay líneas pendientes de este pedido para remitir.</div>');
            return;
        }

        var html = `<table class="table table-sm table-bordered table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Ubicación</th>
                    <th class="text-end">Pendiente</th>
                    <th class="text-center" width="110">Cantidad</th>
                </tr>
            </thead>
            <tbody>`;

        filas.forEach(function (item) {
            var linea = item.linea;
            var pendienteEfectivo = item.pendienteEfectivo;
            var cantidadEnBorrador = cantidadYaEnBorradorRemito(linea.venta_pedido_detalle_id);

            html += `<tr>
                <td>${linea.producto_codigo || ''}</td>
                <td>${linea.producto_nombre || ''}</td>
                <td>${renderUbicacionesRemitoPendiente(linea.ubicaciones_detalle)}</td>
                <td class="text-end">${formatMoneda(pendienteEfectivo)}</td>
                <td>
                    <div class="cantidad-stepper mx-auto">
                        <button type="button" class="btn-stepper btn-cantidad-remito-pendiente-menos" data-vpd-id="${linea.venta_pedido_detalle_id}" tabindex="-1">&minus;</button>
                        <input type="number" class="cantidad-stepper-input" value="${cantidadEnBorrador.toFixed(2)}" step="0.01" min="0" readonly>
                        <button type="button" class="btn-stepper btn-cantidad-remito-pendiente-mas" data-vpd-id="${linea.venta_pedido_detalle_id}" data-producto-id="${linea.producto_id}" data-codigo="${linea.producto_codigo || ''}" data-nombre="${linea.producto_nombre || ''}" tabindex="-1">+</button>
                    </div>
                </td>
            </tr>`;
        });

        html += '</tbody></table>';
        cont.html(html);
    }

    // +/- de la fila de pendientes del remito: agregan o quitan una unidad
    // directamente del borrador al clickear, mismo patrón que el filtro de
    // productos de la solapa Productos — sin botón "Agregar" aparte.
    function ajustarCantidadRemitoPendiente(vpdId, delta, datosLinea) {
        var existente = remitoDetallesNuevo.find(function (d) { return d.venta_pedido_detalle_id == vpdId; });

        if (existente) {
            var nuevaCantidad = Math.round(((parseFloat(existente.cantidad) || 0) + delta) * 100) / 100;
            if (nuevaCantidad <= 0) {
                remitoDetallesNuevo = remitoDetallesNuevo.filter(function (d) { return d !== existente; });
            } else {
                existente.cantidad = nuevaCantidad;
            }
        } else {
            if (delta <= 0) return; // nada cargado todavía, no hay qué restar
            remitoDetallesNuevo.push({
                venta_pedido_detalle_id: vpdId,
                producto_id: datosLinea.producto_id,
                producto_codigo: datosLinea.producto_codigo,
                producto_nombre: datosLinea.producto_nombre,
                cantidad: delta
            });
        }

        renderizarRemitoDetalleNuevo();
        renderizarPendientesRemitoPedido();
    }

    $(document).on('click', '.btn-cantidad-remito-pendiente-menos', function () {
        ajustarCantidadRemitoPendiente(parseInt($(this).data('vpd-id')), -1);
    });

    $(document).on('click', '.btn-cantidad-remito-pendiente-mas', function () {
        var btn = $(this);
        ajustarCantidadRemitoPendiente(parseInt(btn.data('vpd-id')), 1, {
            producto_id: btn.data('producto-id'),
            producto_codigo: btn.data('codigo'),
            producto_nombre: btn.data('nombre')
        });
    });

    function renderizarRemitoDetalleNuevo() {
        var cont = $('#contenedor-remito-detalle-nuevo');

        if (remitoDetallesNuevo.length === 0) {
            cont.html('<div class="text-muted small text-center p-3 border rounded bg-light">Todavía no agregaste líneas a este remito.</div>');
            return;
        }

        var html = `<table class="table table-sm table-bordered mb-0">
            <thead class="table-light">
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th class="text-center">Cant.</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>`;

        remitoDetallesNuevo.forEach(function (d, idx) {
            html += `<tr>
                <td>${d.producto_codigo || ''}</td>
                <td>${d.producto_nombre || ''}</td>
                <td class="text-center">${formatMoneda(d.cantidad)}</td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm btn-danger btn-quitar-remito-nuevo" data-idx="${idx}" title="Quitar">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            </tr>`;
        });

        html += '</tbody></table>';
        cont.html(html);
    }

    $(document).on('click', '.btn-quitar-remito-nuevo', function () {
        remitoDetallesNuevo.splice($(this).data('idx'), 1);
        renderizarRemitoDetalleNuevo();
        renderizarPendientesRemitoPedido();
    });

    $(document).on('click', '#btnGuardarRemitoDesdePedido', function () {
        var btn = $(this);
        var pedidoId = $('#venta_pedido_id').val();

        if (!$('#remito_punto_venta_id').val()) {
            Swal.fire({ icon: 'warning', title: 'Falta punto de venta', text: 'Elegí el punto de venta (depósito) del remito', confirmButtonText: 'Entendido' });
            return;
        }
        if (!$('#remito_comprobante_tipo_id').val()) {
            Swal.fire({ icon: 'warning', title: 'Falta tipo de comprobante', text: 'Elegí el tipo de comprobante del remito', confirmButtonText: 'Entendido' });
            return;
        }
        if (!$('#remito_f_emision').val()) {
            Swal.fire({ icon: 'warning', title: 'Falta la fecha', text: 'La fecha de emisión es obligatoria', confirmButtonText: 'Entendido' });
            return;
        }
        if (remitoDetallesNuevo.length === 0) {
            Swal.fire({ icon: 'warning', title: 'Sin líneas', text: 'Agregá al menos un producto al remito', confirmButtonText: 'Entendido' });
            return;
        }

        var detallesParaEnviar = remitoDetallesNuevo.map(function (d) {
            return {
                producto_id: d.producto_id,
                venta_pedido_detalle_id: d.venta_pedido_detalle_id,
                cantidad: d.cantidad
            };
        });

        var originalText = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        var datosPost = {
            accion: remitoEditandoId ? 'editar' : 'agregar',
            punto_venta_id: $('#remito_punto_venta_id').val(),
            comprobante_tipo_id: $('#remito_comprobante_tipo_id').val(),
            entidad_id: clienteActualId,
            entidad_sucursal_id: clienteSucursalActualId || '',
            f_emision: $('#remito_f_emision').val(),
            observaciones: $('#remito_observaciones').val(),
            detalles: JSON.stringify(detallesParaEnviar),
            empresa_idx: empresa_idx,
            pagina_idx: REMITOS_PAGINA_IDX
        };
        if (remitoEditandoId) {
            datosPost.venta_remito_id = remitoEditandoId;
        }

        $.ajax({
            url: 'ventas_remitos_ajax.php',
            type: 'POST',
            data: datosPost,
            dataType: 'json',
            success: function (res) {
                btn.prop('disabled', false).html(originalText);
                if (res.resultado) {
                    Swal.fire({ icon: 'success', title: remitoEditandoId ? '¡Remito actualizado!' : '¡Remito guardado!', showConfirmButton: false, timer: 1500, toast: true, position: 'top-end' });

                    if (remitoEditandoId) {
                        cancelarEdicionRemito(); // vuelve la tarjeta a modo "nuevo"
                    } else {
                        remitoDetallesNuevo = [];
                        renderizarRemitoDetalleNuevo();
                        $('#remito_observaciones').val('');
                        cargarPendientesRemitoPedido();
                    }

                    cargarRemitosDelPedido(pedidoId);
                    refrescarCantidadesEntregadas(pedidoId);
                    if (tabla) tabla.ajax.reload(null, false);
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: res.error || 'Error al guardar el remito', confirmButtonText: 'Entendido' });
                }
            },
            error: function () {
                btn.prop('disabled', false).html(originalText);
                Swal.fire({ icon: 'error', title: 'Error de conexión', text: 'No se pudo conectar con el servidor', confirmButtonText: 'Entendido' });
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