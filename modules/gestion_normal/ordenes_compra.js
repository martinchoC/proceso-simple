$(document).ready(function () {
    const empresa_idx = window.EMPRESA_ID; // empresa activa (header1.php)
    const pagina_idx = 51;
    
    var tabla;
    var currentPage = 0;
    var currentOrder = [[1, 'asc']];
    var currentSearch = '';
    var filtroEstadoRapido = '';
    
    // Extensión de búsqueda DataTable para filtrado rápido por estado desde el dashboard
    $.fn.dataTable.ext.search.push(function (settings, data, dataIndex, rowData) {
        if (settings.sTableId !== 'tablaOrdenesCompra') return true;
        if (filtroEstadoRapido === '' || filtroEstadoRapido === null || filtroEstadoRapido === undefined) {
            return true;
        }
        
        if (rowData) {
            // Comparar por tabla_estado_registro_id
            if (rowData.tabla_estado_registro_id !== undefined && String(rowData.tabla_estado_registro_id) === String(filtroEstadoRapido)) {
                return true;
            }
            // Comparar por nombre de estado_registro
            var estadoNombre = (rowData.estado_info && rowData.estado_info.estado_registro) ? rowData.estado_info.estado_registro : rowData.estado_registro;
            if (estadoNombre && String(estadoNombre).trim().toLowerCase() === String(filtroEstadoRapido).trim().toLowerCase()) {
                return true;
            }
        }
        return false;
    });
    
    // Variables para manejo de detalles
    var detalles = [];
    var proveedorActualId = null;
    // Compra por presentación (doc 27): solo si la página cargó assets/js/compras-presentaciones.js (indicador)
    var CPRE = window.ComprasPres || null;
    var proveedorSucursalActualId = null;
    var timeoutBusqueda = null;
    var selectedIndex = -1;

    // ========== FUNCIONES DE DATATABLE CON FILTROS POR COLUMNA ==========
    function inicializarDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaOrdenesCompra')) {
            $('#tablaOrdenesCompra').DataTable().destroy();
            $('#tablaOrdenesCompra tbody').empty();
        }

        tabla = $('#tablaOrdenesCompra').DataTable({
            ajax: {
                url: 'ordenes_compra_ajax.php',
                type: 'GET',
                data: {
                    accion: 'listar',
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                },
                dataSrc: function (json) {
                    if (Array.isArray(json)) {
                        actualizarDashboardEstados(json);
                    }
                    return json;
                }
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
                '<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>' +
                '<"clear">',
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],

            
            columns: [
                {
                    data: 'orden_compra_id',
                    className: 'ui-detalle'
                },
                {
                    data: 'comprobante_tipo',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (type === 'export') {
                            return data || '';
                        }
                        let circuitBadge = '';
                        if (row.tipo_circuito === 'CONTRATO_AVANCE') {
                            circuitBadge = `<br><span class="badge text-white" style="background-color: #6f42c1; font-size: 0.7rem;" title="Contrato c/ Certificación de Avance"><i class="fas fa-tasks me-1"></i>Obra/Avance</span>`;
                        } else if (row.tipo_circuito === 'CONTRATO_RECURRENTE' || parseInt(row.es_contrato) === 1) {
                            circuitBadge = `<br><span class="badge bg-info text-dark" style="font-size: 0.7rem;" title="Contrato Recurrente"><i class="fas fa-file-contract me-1"></i>Contrato</span>`;
                        }
                        return `<span>${data || ''}</span>${circuitBadge}`;
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
                        var nombreMostrar = data.entidad_fantasia || data.entidad_nombre || '';
                        if (type === 'export') {
                            return nombreMostrar;
                        }
                        return `<div>${nombreMostrar}</div>`;
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
                        return Fmt.fecha(data);
                    }
                },
                {
                    data: 'f_entrega_estimada',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (type === 'export') {
                            return data || '';
                        }
                        if (row.tipo_circuito && row.tipo_circuito !== 'BIENES' && row.fecha_fin) {
                            let hoy = new Date();
                            hoy.setHours(0,0,0,0);
                            let vto = new Date(row.fecha_fin + 'T00:00:00');
                            let diffDays = Math.round((vto - hoy) / (1000 * 60 * 60 * 24));
                            let preaviso = parseInt(row.dias_preaviso_rescision) || 30;
                            let vigBadge = '';
                            if (diffDays < 0) {
                                vigBadge = `<br><span class="badge bg-danger" style="font-size: 0.68rem;" title="Contrato Vencido"><i class="fas fa-exclamation-triangle"></i> Vencido</span>`;
                            } else if (diffDays <= preaviso) {
                                vigBadge = `<br><span class="badge bg-warning text-dark" style="font-size: 0.68rem;" title="En período de preaviso de rescisión"><i class="fas fa-clock"></i> Preaviso (${diffDays}d)</span>`;
                            } else {
                                vigBadge = `<br><span class="badge bg-success" style="font-size: 0.68rem;" title="Vigente"><i class="fas fa-check"></i> Vigente (${diffDays}d)</span>`;
                            }
                            return `<span title="Vencimiento de Contrato">${Fmt.fecha(row.fecha_fin)}</span>${vigBadge}`;
                        }
                        return data ? Fmt.fecha(data) : '';
                    }
                },
                {
                    data: 'total',
                    className: 'text-end',
                    render: function(data, type, row) {
                        if (type === 'export') {
                            return parseFloat(data).toFixed(2);
                        }
                        return `<span class="text-primary">$${Fmt.numero(parseFloat(data), 2)}</span>`;
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
                                var proveedorInfo = row.entidad_nombre || row.entidad_fantasia || '';

                                var botonHtml = `<button type="button" class="btn ${claseBoton} btn-accion" 
                                                title="${titulo}" 
                                                data-id="${row.orden_compra_id}" 
                                                data-accion="${accionJs}"
                                                data-confirmable="${esConfirmable}"
                                                data-comprobante="${comprobanteInfo}"
                                                data-proveedor="${proveedorInfo}">
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

                        if ((row.tipo_circuito && row.tipo_circuito !== 'BIENES') || parseInt(row.es_contrato) === 1) {
                            var btnCert = `<button type="button" class="btn btn-outline-info btn-sm ms-1 btn-ver-certificaciones" title="Seguimiento y Certificaciones de Avance" data-id="${row.orden_compra_id}"><i class="fas fa-clipboard-check"></i></button>`;
                            botones = (botones === '<span class="text-muted small">Sin acciones</span>' ? '' : botones) + btnCert;
                        }

                        return `<div class="btn-group" role="group">${botones}</div>`;
                    }
                }
            ],
            language: {
                url: window.APP_ASSETS_URL + 'js/datatables-i18n-2.1.8/es-ES.json',
                lengthMenu: 'Mostrar _MENU_ registros',
                info: 'Página _PAGE_ de _PAGES_',
                infoEmpty: 'Página 0 de 0',
                infoFiltered: '(filtrado de _MAX_ registros)',
                paginate: {
                    first: 'Primero',
                    previous: 'Anterior',
                    next: 'Siguiente',
                    last: 'Último'
                }
            },

            order: currentOrder,
            responsive: true,
            createdRow: function (row, data, dataIndex) {
                if (data.estado_info && data.estado_info.codigo_estandar === 'CERRADO') {
                    $(row).addClass('table-success');
                } else if (data.estado_info && data.estado_info.codigo_estandar === 'CANCELADO') {
                    $(row).addClass('table-danger');
                } else if (data.estado_info && data.estado_info.codigo_estandar === 'PENDIENTE') {
                    $(row).addClass('table-warning');
                }
            },
            initComplete: function () {
                // Agregar filtros por columna
                this.api().columns().every(function () {
                    var column = this;
                    var header = $(column.header());
                    
                    // Crear input de filtro
                    var input = $('<input type="text" class="column-filter" placeholder="Filtrar...">')
                        .appendTo(header)
                        .on('keyup change', function () {
                            if (column.search() !== this.value) {
                                column.search(this.value).draw();
                            }
                        });
                });

                // Mover controles de longitud (Mostrar X registros) y búsqueda al header
                setTimeout(function() {
                    
                    
                    // Aplicar estilos para que se vean bien en el header
                    $('#tablaOrdenesCompra_length').addClass('dataTables_length_custom');
                    $('#tablaOrdenesCompra_filter').addClass('dataTables_filter_custom');
                    
                    // Si el control de longitud está vacío, lo llenamos manualmente
                    if ($('#tablaOrdenesCompra_length').html().trim() === '') {
                        var selectHtml = '<label title="Filas por página"><select name="tablaOrdenesCompra_length" aria-controls="tablaOrdenesCompra" class="form-select form-select-sm"><option value="10">10</option><option value="25">25</option><option value="50" selected="">50</option><option value="100">100</option><option value="-1">Todos</option></select></label>';
                        $('#tablaOrdenesCompra_length').html(selectHtml);
                        
                        // Agregar evento al select
                        $('#tablaOrdenesCompra_length select').on('change', function() {
                            tabla.page.len($(this).val()).draw();
                        });
                    }
                    
                    // Si el control de búsqueda está vacío, lo llenamos manualmente
                    if ($('#tablaOrdenesCompra_filter').html().trim() === '') {
                        var filterHtml = '<label><input type="search" class="form-control form-control-sm" placeholder="Buscar…" aria-controls="tablaOrdenesCompra"></label>';
                        $('#tablaOrdenesCompra_filter').html(filterHtml);
                        
                        // Agregar evento al input
                        $('#tablaOrdenesCompra_filter input').on('keyup', function() {
                            tabla.search($(this).val()).draw();
                        });
                    }
                }, 100);


                // Configurar botones de exportación
                var buttons = new $.fn.dataTable.Buttons(tabla, {
                    buttons: ['excelHtml5', 'pdfHtml5', 'csvHtml5', 'print']
                }).container().appendTo($('#tablaOrdenesCompra_wrapper .col-md-6:eq(1)'));

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

        // Eventos para botones de exportación
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

        // Evento clic en tarjetas del dashboard de estados
        $(document).on('click', '.card-kpi-estado', function () {
            var estadoId = $(this).data('estado-id');
            
            if (String(filtroEstadoRapido) === String(estadoId) && estadoId !== '') {
                // Si ya está activo el filtro y hace clic de nuevo, vuelve a Todas
                filtroEstadoRapido = '';
            } else {
                filtroEstadoRapido = (estadoId !== undefined && estadoId !== null) ? estadoId : '';
            }

            $('.card-kpi-estado').removeClass('active');
            if (filtroEstadoRapido === '') {
                $('.card-kpi-estado[data-estado-id=""]').addClass('active');
                $('#btnLimpiarFiltroEstado').addClass('d-none');
            } else {
                $(`.card-kpi-estado[data-estado-id="${filtroEstadoRapido}"]`).addClass('active');
                $('#btnLimpiarFiltroEstado').removeClass('d-none');
            }

            if (tabla) {
                tabla.draw();
            }
        });

        // Evento clic en botón "Ver Todas"
        $(document).on('click', '#btnLimpiarFiltroEstado', function () {
            filtroEstadoRapido = '';
            $('.card-kpi-estado').removeClass('active');
            $('.card-kpi-estado[data-estado-id=""]').addClass('active');
            $('#btnLimpiarFiltroEstado').addClass('d-none');
            if (tabla) {
                tabla.draw();
            }
        });
    }

    // ========== DASHBOARD RÁPIDO DE ESTADOS ==========
    function formatearMontoAr(val) {
        return Fmt.numberFormat({ minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(parseFloat(val) || 0);
    }

    function obtenerIconoEstado(nombre, codigo) {
        var str = ((nombre || '') + ' ' + (codigo || '')).toLowerCase();
        if (str.includes('borrador') || str.includes('nueva') || str.includes('iniciad')) return 'fas fa-pencil-alt';
        if (str.includes('emitid') || str.includes('confirmad') || str.includes('aprobad')) return 'fas fa-check-circle';
        if (str.includes('proceso') || str.includes('pendient') || str.includes('parcial')) return 'fas fa-hourglass-half';
        if (str.includes('cumplid') || str.includes('completad') || str.includes('recibid') || str.includes('cerrad')) return 'fas fa-truck-loading';
        if (str.includes('anulad') || str.includes('cancelad') || str.includes('rechazad')) return 'fas fa-ban';
        return 'fas fa-tag';
    }

    function obtenerColorBadge(nombre, codigo, bgClase) {
        if (bgClase && bgClase.trim() !== '') return bgClase;
        var str = ((nombre || '') + ' ' + (codigo || '')).toLowerCase();
        if (str.includes('borrador')) return 'bg-secondary';
        if (str.includes('emitid') || str.includes('confirmad')) return 'bg-primary';
        if (str.includes('aprobad')) return 'bg-info text-dark';
        if (str.includes('proceso') || str.includes('pendient')) return 'bg-warning text-dark';
        if (str.includes('cumplid') || str.includes('completad') || str.includes('recibid')) return 'bg-success';
        if (str.includes('anulad') || str.includes('cancelad')) return 'bg-danger';
        return 'bg-secondary';
    }

    function actualizarDashboardEstados(datos) {
        if (!datos || !Array.isArray(datos)) return;

        var totalRegistros = datos.length;
        var totalMonto = 0;
        var resumenPorEstado = {};

        datos.forEach(function (row) {
            var monto = parseFloat(row.total) || 0;
            totalMonto += monto;

            var estadoId = row.tabla_estado_registro_id || 0;
            var estadoNombre = (row.estado_info && row.estado_info.estado_registro) ? row.estado_info.estado_registro : (row.estado_registro || 'Sin estado');
            var codigoEstandar = (row.estado_info && row.estado_info.codigo_estandar) ? row.estado_info.codigo_estandar : (row.codigo_estandar || '');
            var bgClase = (row.estado_info && row.estado_info.bg_clase) ? row.estado_info.bg_clase : '';

            var key = estadoId + '_' + estadoNombre;
            if (!resumenPorEstado[key]) {
                resumenPorEstado[key] = {
                    id: estadoId,
                    nombre: estadoNombre,
                    codigo: codigoEstandar,
                    bgClase: bgClase,
                    count: 0,
                    monto: 0
                };
            }
            resumenPorEstado[key].count++;
            resumenPorEstado[key].monto += monto;
        });

        var isTodasActive = (filtroEstadoRapido === '' || filtroEstadoRapido === null || filtroEstadoRapido === undefined);
        var html = '';

        // Tarjeta "Todas"
        html += `
            <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                <div class="card h-100 card-kpi-estado ${isTodasActive ? 'active' : ''}" data-estado-id="" role="button" title="Mostrar todas las órdenes">
                    <div class="card-body p-2 d-flex flex-column justify-content-between">
                        <div class="d-flex align-items-center justify-content-between">
                            <span class="badge bg-dark text-white px-2 py-1"><i class="fas fa-list me-1"></i>Todas</span>
                            <span class="kpi-count text-dark">${totalRegistros}</span>
                        </div>
                        <div class="kpi-monto text-primary mt-2 text-end">$ ${formatearMontoAr(totalMonto)}</div>
                    </div>
                </div>
            </div>
        `;

        // Tarjetas por cada estado encontrado
        Object.values(resumenPorEstado).forEach(function (est) {
            var isActive = (String(filtroEstadoRapido) === String(est.id) || String(filtroEstadoRapido).toLowerCase() === String(est.nombre).toLowerCase());
            var icono = obtenerIconoEstado(est.nombre, est.codigo);
            var badgeClase = obtenerColorBadge(est.nombre, est.codigo, est.bgClase);

            html += `
                <div class="col-6 col-sm-4 col-md-3 col-lg-2">
                    <div class="card h-100 card-kpi-estado ${isActive ? 'active' : ''}" data-estado-id="${est.id}" data-estado-nombre="${est.nombre}" role="button" title="Filtrar por ${est.nombre}">
                        <div class="card-body p-2 d-flex flex-column justify-content-between">
                            <div class="d-flex align-items-center justify-content-between">
                                <span class="badge ${badgeClase} px-2 py-1"><i class="${icono} me-1"></i>${est.nombre}</span>
                                <span class="kpi-count text-dark">${est.count}</span>
                            </div>
                            <div class="kpi-monto text-secondary mt-2 text-end">$ ${formatearMontoAr(est.monto)}</div>
                        </div>
                    </div>
                </div>
            `;
        });

        $('#contenedorDashboardEstados').html(html);

        if (isTodasActive) {
            $('#btnLimpiarFiltroEstado').addClass('d-none');
        } else {
            $('#btnLimpiarFiltroEstado').removeClass('d-none');
        }
    }

    function cargarBotonAgregar() {
        $.get('ordenes_compra_ajax.php', {
            accion: 'obtener_boton_agregar',
            pagina_idx: pagina_idx
        }, function (botonAgregar) {
            if (botonAgregar && botonAgregar.nombre_funcion) {
                var icono = botonAgregar.icono_clase ? `<i class="${botonAgregar.icono_clase} me-1"></i>` : '';

                var colorClase = 'btn-primary';
                if (botonAgregar.bg_clase && botonAgregar.text_clase) {
                    colorClase = botonAgregar.bg_clase + ' ' + botonAgregar.text_clase;
                } else if (botonAgregar.color_clase) {
                    colorClase = botonAgregar.color_clase;
                }

                $('#contenedor-boton-agregar').html(
                    `<button type="button" class="btn ${colorClase}" id="btnNuevo">
                ${icono}${botonAgregar.nombre_funcion}
             </button>`
                );
            } else {
                $('#contenedor-boton-agregar').html(
                    '<button type="button" class="btn btn-primary" id="btnNuevo">' +
                    '<i class="fas fa-plus me-1"></i>Nueva Orden</button>'
                );
            }
        }, 'json');
    }

    // ========== FUNCIÓN PARA CARGAR PUNTOS DE VENTA POR SUCURSAL (con callback) ==========
    function cargarPuntosVenta(sucursalId, callback) {
        if (!sucursalId) {
            $('#punto_venta_id').html('<option value="">Primero seleccione sucursal</option>');
            $('#punto_venta_id').prop('disabled', true);
            if (callback) callback();
            return;
        }
        
        $.ajax({
            url: 'ordenes_compra_ajax.php',
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
                console.log("Puntos de venta cargados:", data);
                
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

    // ========== FUNCIÓN PARA CARGAR PROVEEDORES Y SUCURSALES EN COMBO UNIFICADO ==========
    function cargarProveedoresYSucursales() {
        $.ajax({
            url: 'ordenes_compra_ajax.php',
            type: 'GET',
            data: {
                accion: 'obtener_proveedores_con_sucursales',
                empresa_idx: empresa_idx
            },
            dataType: 'json',
            success: function(data) {
                var options = '<option value="">Seleccionar proveedor o sucursal</option>';
                
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
                    options = '<option value="">No hay proveedores disponibles</option>';
                }
                
                $('#entidad_combo').html(options);
                console.log("Proveedores y sucursales cargados:", data);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("Error cargando proveedores y sucursales:", textStatus, errorThrown);
                $('#entidad_combo').html('<option value="">Error al cargar</option>');
            }
        });
    }

    // ========== EVENTO CHANGE DE SUCURSAL ==========
    $('#sucursal_id').on('change', function() {
        var sucursalId = $(this).val();
        cargarPuntosVenta(sucursalId);
    });

    // ========== EVENTO CHANGE DEL COMBO UNIFICADO ==========
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
                proveedorActualId = parseInt(entidadId);
                proveedorSucursalActualId = null;
                
                var proveedorNombre = selectedOption.text();
                $('#proveedor_actual_nombre').text(proveedorNombre);
                
            } else if (tipo === 'S') {
                $('#entidad_id').val(entidadId);
                $('#entidad_sucursal_id').val(sucursalId);
                proveedorActualId = parseInt(entidadId);
                proveedorSucursalActualId = parseInt(sucursalId);
                
                var textoCompleto = selectedOption.text();
                $('#proveedor_actual_nombre').text(textoCompleto);
            }
            
            console.log("Proveedor seleccionado:", proveedorActualId, "Sucursal:", proveedorSucursalActualId);
            
        } else {
            $('#entidad_id').val('');
            $('#entidad_sucursal_id').val('');
            proveedorActualId = null;
            proveedorSucursalActualId = null;
            $('#proveedor_actual_nombre').text('No seleccionado');
        }
    });

    // ========== FUNCIÓN PARA IMPRIMIR COMPROBANTE ==========
    function imprimirComprobante(ordenId) {
        var url = 'ordenes_compra_print.php?orden_compra_id=' + ordenId + '&empresa_idx=' + empresa_idx;
        window.open(url, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
    }

    // ========== VISUALIZAR ORDEN (SOLO LECTURA) ==========
    function cargarOrdenParaVisualizar(ordenId) {
        $.get('ordenes_compra_ajax.php', {
            accion: 'obtener',
            orden_compra_id: ordenId,
            empresa_idx: empresa_idx
        }, function (res) {
            console.log("Orden recibida para visualizar:", res);
            
            if (res && res.orden_compra_id) {
                resetModal();
                
                cargarCombosFormulario();
                cargarProveedoresYSucursales();
                
                $('#orden_compra_id').val(res.orden_compra_id);
                $('#comprobante_nro').val(res.comprobante_nro);

                // Campos de Contratos y Servicios
                $('#tipo_circuito').val(res.tipo_circuito || 'BIENES').trigger('change');
                $('#contrato_nro_legal').val(res.contrato_nro_legal || '');
                $('#fecha_inicio').val(res.fecha_inicio || '');
                $('#fecha_fin').val(res.fecha_fin || '');
                $('#dias_preaviso_rescision').val(res.dias_preaviso_rescision !== undefined ? res.dias_preaviso_rescision : 30);
                $('#tiene_renovacion_automatica').prop('checked', parseInt(res.tiene_renovacion_automatica) === 1);
                $('#pct_anticipo_financiero').val(res.pct_anticipo_financiero || '0.00');
                $('#pct_fondo_reparo').val(res.pct_fondo_reparo || '0.00');
                $('#responsable_tecnico_id').val(res.responsable_tecnico_id || '');
                
                $('#f_emision').val(res.f_emision);
                $('#f_entrega_estimada').val(res.f_entrega_estimada);
                $('#direccion_entrega').val(res.direccion_entrega);
                $('#observaciones').val(res.observaciones);
                $('#tipo_cambio').val(res.tipo_cambio || '1.000000');
                $('#total_neto').val(res.subtotal || 0);
                $('#no_gravado').val(res.no_gravado || 0);
                $('#exento').val(res.exento || 0);
                $('#impuestos').val(res.impuestos || 0);
                $('#total').val(res.total || 0);
                
                $('#total_neto_display').text(Fmt.numero(parseFloat(res.subtotal || 0), 2));
                $('#no_gravado_display').text(Fmt.numero(parseFloat(res.no_gravado || 0), 2));
                $('#exento_display').text(Fmt.numero(parseFloat(res.exento || 0), 2));
                $('#impuestos_display').text(Fmt.numero(parseFloat(res.impuestos || 0), 2));
                $('#total_display').text(Fmt.numero(parseFloat(res.total || 0), 2));

                $('#modalLabel').text('Visualizar Orden de Compra');

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
                        proveedorActualId = res.entidad_id;
                        
                        if (res.entidad_sucursal_id && res.entidad_sucursal_id > 0) {
                            $('#entidad_combo').val('S-' + res.entidad_sucursal_id);
                            proveedorSucursalActualId = parseInt(res.entidad_sucursal_id);
                        } else {
                            $('#entidad_combo').val('P-' + res.entidad_id);
                            proveedorSucursalActualId = null;
                        }
                        
                        var textoSeleccionado = $('#entidad_combo option:selected').text();
                        $('#proveedor_actual_nombre').text(textoSeleccionado || 'No seleccionado');
                    }
                    
                    if (res.detalles && res.detalles.length > 0) {
                        detalles = res.detalles.map(function(detalle, index) {
                            return $.extend({
                                detalle_idx: index,
                                ordenes_compra_detalle_id: detalle.ordenes_compra_detalle_id,
                                producto_id: detalle.producto_id,
                                producto_nombre: detalle.producto_nombre,
                                cantidad: detalle.cantidad,
                                cantidad_recibida: parseFloat(detalle.cantidad_recibida) || 0,
                                precio_unitario: detalle.precio_unitario,
                                no_gravado: detalle.no_gravado || 0,
                                exento: detalle.exento || 0,
                                iva_alicuota_id: detalle.iva_alicuota_id,
                                iva_porcentaje: detalle.iva_porcentaje,
                                neto_gravado: detalle.neto_gravado,
                                iva_importe: detalle.iva_importe,
                                total_linea: detalle.total_linea
                            }, CPRE ? CPRE.foto(detalle) : {});
                        });
                        renderizarDetalles();
                        actualizarTotales();
                    }

                    // --- MODO SOLO LECTURA ---
                    $('#formOrdenCompra :input').prop('disabled', true);
                    $('.btn-eliminar-detalle, .btn-editar-detalle, #btnAgregarProducto, #btnNuevoProductoRapido, #btnNuevoProveedor').prop('disabled', true);
                    
                    // Ocultar botones de acciones de productos
                    $('.btn-editar-detalle, .btn-eliminar-detalle, #btnAgregarProducto, #btnNuevoProductoRapido').hide();
                    $('#busqueda_producto').prop('disabled', true);
                    
                    $('.card-primary').hide();
                    $('#btnNuevoProductoRapido').hide();
                    
                    $('#btnGuardar').hide();
                    $('.modal-footer .btn-secondary').hide();
                    
                    // Ocultar botón cancelar
                    $('.btn-secondary[data-bs-dismiss="modal"]').hide();
                    
                    $('#btnToggleFullscreen').prop('disabled', false);
                    
                }, 500);

                var modal = new bootstrap.Modal(document.getElementById('modalOrdenCompra'));
                modal.show();

                $('#modalOrdenCompra').off('hidden.bs.modal').on('hidden.bs.modal', function () {
                    $('#formOrdenCompra :input').prop('disabled', false);
                    $('#btnGuardar').show();
                    $('.modal-footer .btn-secondary').show();
                    $('.btn-eliminar-detalle, .btn-editar-detalle, #btnAgregarProducto, #btnNuevoProductoRapido, #btnNuevoProveedor').prop('disabled', false);
                    
                    $('.card-primary').show();
                    $('#btnNuevoProductoRapido').show();
                    
                    // Restaurar botón cancelar
                    $('.btn-secondary[data-bs-dismiss="modal"]').show();
                });

            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos de la orden",
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json');
    }

    // ========== MANEJADOR DE ACCIONES DE BOTONES ==========
    $(document).on('click', '.btn-accion', function () {
        var ordenId = $(this).data('id');
        var accionJs = $(this).data('accion');
        var confirmable = $(this).data('confirmable');
        var comprobanteInfo = $(this).data('comprobante') || 'Orden #' + ordenId;
        var proveedorInfo = $(this).data('proveedor') || '';

        if (accionJs === 'editar') {
            cargarOrdenParaEditar(ordenId);
        } else if (accionJs === 'visualizar') {
            cargarOrdenParaVisualizar(ordenId);
        } else if (accionJs === 'imprimir') {
            imprimirComprobante(ordenId);
        } else if (confirmable == 1) {
            Swal.fire({
                title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                html: `¿Está seguro de <strong>${accionJs}</strong> la orden<br>
                    <strong>${comprobanteInfo}</strong>?<br>
                    <small class="text-muted">Proveedor: ${proveedorInfo}</small>`,
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
                    ejecutarAccion(ordenId, accionJs, comprobanteInfo);
                }
            });
        } else {
            ejecutarAccion(ordenId, accionJs, comprobanteInfo);
        }
    });

    // Función para ejecutar cualquier acción del backend
    function ejecutarAccion(ordenId, accionJs, comprobanteInfo) {
        var savedState = {
            page: tabla.page(),
            order: tabla.order(),
            search: tabla.search()
        };

        $.post('ordenes_compra_ajax.php', {
            accion: 'ejecutar_accion',
            orden_compra_id: ordenId,
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

                    var mensaje = res.message || `Orden "${comprobanteInfo}" actualizada correctamente`;
                    
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
                    text: res.error || `Error al ${accionJs} la orden`,
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

    // ========== FUNCIONES DE BÚSQUEDA DE PRODUCTOS ==========
    $('#busqueda_producto').on('input', function() {
        var q = $(this).val().trim();
        var resultadosDiv = $('#resultados_busqueda');
        
        if (!proveedorActualId) {
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
                url: 'ordenes_compra_ajax.php',
                type: 'GET',
                data: {
                    accion: 'buscar_productos_proveedor',
                    entidad_id: proveedorActualId,
                    q: q,
                    empresa_idx: empresa_idx
                },
                dataType: 'json',
                success: function(data) {
                    resultadosDiv.empty().hide();
                    selectedIndex = -1;
                    
                    if (data && data.length > 0) {
                        data.forEach(function(item, index) {
                            var texto = item.producto_codigo + ' - ' + item.producto_nombre;
                            var vinculado = parseInt(item.vinculado) === 1;
                            resultadosDiv.append(
                                `<a href="#" class="list-group-item list-group-item-action" 
                                   data-index="${index}"
                                   data-id="${item.producto_id}"
                                   data-iva-id="${item.iva_alicuota_id}"
                                   data-iva="${item.iva_porcentaje || 21}"
                                   data-vinculado="${vinculado ? 1 : 0}"
                                   data-texto="${escapeHtml(texto)}">
                                    <strong>${escapeHtml(String(item.producto_codigo))}</strong> - ${escapeHtml(item.producto_nombre)}
                                    ${vinculado
                                        ? (item.codigo_proveedor ? '<br><small>Código Proveedor: ' + escapeHtml(item.codigo_proveedor) + '</small>' : '')
                                        : '<span class="badge bg-warning text-dark ms-1">No vinculado al proveedor</span>'}
                                </a>`
                            );
                        });
                        resultadosDiv.show();
                    }
                }
            });
        }, 300);
    });

    // Navegación con teclas (flechas arriba/abajo y Enter)
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

    // Seleccionar producto de la búsqueda
    $(document).on('click', '#resultados_busqueda .list-group-item', function(e) {
        e.preventDefault();
        
        var item = $(this);
        $('#resultados_busqueda').hide();
        selectedIndex = -1;

        if (parseInt(item.data('vinculado')) === 1) {
            seleccionarProductoBusqueda(item);
            return;
        }

        var modalEl = document.getElementById('modalOrdenCompra');
        var modalInst = modalEl ? (bootstrap.Modal.getInstance(modalEl) || bootstrap.Modal.getOrCreateInstance(modalEl)) : null;

        // Producto que el proveedor no tiene vinculado: ofrecer cargar su código
        Swal.fire({
            icon: 'question',
            title: 'Producto no vinculado al proveedor',
            html: `<p class="mb-2"><strong>${escapeHtml(String(item.data('texto')))}</strong></p>
                   <p class="mb-0 small">Ingrese el código que usa este proveedor para vincularlo, o continúe sin vincular.</p>`,
            input: 'text',
            inputPlaceholder: 'Código del proveedor',
            inputAttributes: { maxlength: 50 },
            showDenyButton: true,
            showCancelButton: true,
            confirmButtonText: 'Vincular y usar',
            denyButtonText: 'Usar sin vincular',
            cancelButtonText: 'Cancelar',
            showLoaderOnConfirm: true,
            didOpen: function() {
                if (modalInst && modalInst._focustrap) {
                    modalInst._focustrap.deactivate();
                }
                $(document).off('focusin.bs.modal');
                if (modalEl) {
                    modalEl.removeAttribute('tabindex');
                }
                var input = Swal.getInput();
                if (input) {
                    setTimeout(function() {
                        input.focus();
                    }, 50);
                }
            },
            willClose: function() {
                if (modalEl) {
                    modalEl.setAttribute('tabindex', '-1');
                }
                if (modalInst && modalInst._focustrap) {
                    modalInst._focustrap.activate();
                }
            },
            inputValidator: function(valor) {
                if (!valor || !valor.trim()) {
                    return 'Ingrese el código del proveedor';
                }
            },
            preConfirm: function(codigo) {
                return $.post('ordenes_compra_ajax.php', {
                    accion: 'vincular_producto_proveedor',
                    producto_id: item.data('id'),
                    entidad_id: proveedorActualId,
                    codigo_proveedor: codigo.trim(),
                    empresa_idx: empresa_idx
                }, null, 'json').then(function(res) {
                    if (!res || !res.success) {
                        Swal.showValidationMessage((res && (res.message || res.error)) || 'No se pudo vincular el producto');
                        return false;
                    }
                    return res;
                }, function() {
                    Swal.showValidationMessage('No se pudo conectar con el servidor');
                    return false;
                });
            },
            allowOutsideClick: function() { return !Swal.isLoading(); }
        }).then(function(result) {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Producto vinculado',
                    showConfirmButton: false,
                    timer: 1500,
                    toast: true,
                    position: 'top-end'
                });
                seleccionarProductoBusqueda(item);
            } else if (result.isDenied) {
                seleccionarProductoBusqueda(item);
            } else {
                $('#busqueda_producto').focus();
            }
        });
    });

    function seleccionarProductoBusqueda(item) {
        var productoId = item.data('id');
        var iva = item.data('iva');
        var ivaId = item.data('iva-id');
        var textoCompleto = String(item.data('texto') || item.text().trim());
        
        $('#busqueda_producto').val(textoCompleto);
        $('#producto_seleccionado_id').val(productoId);
        $('#producto_iva').val(iva);
        $('#producto_iva_id').val(ivaId);
        
        $.get('ordenes_compra_ajax.php', {
            accion: 'obtener_ultimo_precio',
            producto_id: productoId,
            entidad_id: proveedorActualId,
            empresa_idx: empresa_idx
        }, function(res) {
            if (res.success && res.precio) {
                $('#producto_precio').val(res.precio);
            }
            calcularIvaImporte();
            if (CPRE) CPRE.cargar(productoId);
        }, 'json');
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    // Ocultar resultados al hacer clic fuera
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#busqueda_producto, #resultados_busqueda').length) {
            $('#resultados_busqueda').hide();
            selectedIndex = -1;
        }
    });

    // Calcular IVA Importe automáticamente
    if (CPRE) CPRE.init({ ajaxUrl: 'ordenes_compra_ajax.php', empresaIdx: empresa_idx, onCambio: function () { calcularIvaImporte(); } });

    function calcularIvaImporte() {
        var cantidad = parseFloat($('#producto_cantidad').val()) || 0;
        var precio = parseFloat($('#producto_precio').val()) || 0;
        var iva = parseFloat($('#producto_iva').val()) || 0;

        var netoGravado = cantidad * precio;
        var ivaImporte = netoGravado * (iva / 100);

        $('#producto_iva_importe').val(ivaImporte.toFixed(2));
    }

    $('#producto_cantidad, #producto_precio, #producto_iva, #producto_no_gravado, #producto_exento').on('input', function() {
        calcularIvaImporte();
    });

    // ========== FUNCIONES DE AGREGADO DE PRODUCTOS (PERMITE PRECIO CERO) ==========
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
        if (!proveedorActualId) {
            Swal.fire({
                icon: "warning",
                title: "Seleccione proveedor",
                text: "Debe seleccionar un proveedor primero",
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
        var precio = parseFloat($('#producto_precio').val()) || 0; // PERMITE CERO
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
        
        var productoText = $('#busqueda_producto').val();
        
        var netoGravado = cantidad * precio; 
        var ivaImporte = netoGravado * (iva / 100);
        var totalLinea = netoGravado + ivaImporte + noGravado + exento;
        
        var nuevoDetalle = {
            detalle_idx: 'temp_' + new Date().getTime(),
            ordenes_compra_detalle_id: 0,
            producto_id: parseInt(productoId),
            producto_nombre: productoText,
            cantidad: cantidad,
            precio_unitario: precio,
            no_gravado: noGravado,
            exento: exento,
            iva_alicuota_id: parseInt(ivaId),
            iva_porcentaje: iva,
            neto_gravado: netoGravado,
            iva_importe: ivaImporte,
            total_linea: totalLinea
        };
        
        if (CPRE) nuevoDetalle = CPRE.convertir(nuevoDetalle); // cajas → unidades + foto
        detalles.push(nuevoDetalle);
        renderizarDetalles();
        actualizarTotales();
        if (CPRE) CPRE.limpiar();
        
        // Limpiar campos
        $('#busqueda_producto').val('');
        $('#producto_seleccionado_id').val('');
        $('#producto_iva_id').val('');
        $('#producto_cantidad').val('1.00');
        $('#producto_precio').val('');
        $('#producto_iva').val('21');
        $('#producto_iva_importe').val('0.00');
        $('#producto_no_gravado').val('0.00');
        $('#producto_exento').val('0.00');
        
        $('#busqueda_producto').focus();
    });

    // ========== FUNCIONES DE RENDERIZADO DE DETALLES ==========
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
                    <th>Producto</th>
                    <th class="text-center">Cant.</th>
                    <th class="text-end">Precio</th>
                    <th class="text-center">IVA %</th>
                    <th class="text-end">IVA $</th>
                    <th class="text-end">No Grav.</th>
                    <th class="text-end">Exento</th>
                    <th class="text-end">Neto</th>
                    <th class="text-end">Total</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody>`;
        
        detalles.forEach(function(detalle) {
            var esNuevo = detalle.ordenes_compra_detalle_id === 0;
            var claseFila = esNuevo ? 'table-info' : '';
            
            var nombreProducto = detalle.producto_nombre || '';
            
            html += `
            <tr class="${claseFila}" data-idx="${detalle.detalle_idx}">
                <td>
                    <div class="fw-bold">${nombreProducto.substring(0, 35)}${nombreProducto.length > 35 ? '...' : ''}</div>
                    ${esNuevo ? '<span class="badge bg-info ms-2">Nuevo</span>' : ''}
                </td>
                <td class="text-center">${Fmt.numero(detalle.cantidad, 2)}${CPRE ? CPRE.etiqueta(detalle) : ''}
                    ${detalle.cantidad_recibida > 0 ? '<br><small class="text-primary" title="Recibido en facturas confirmadas">Recib.: ' + detalle.cantidad_recibida.toFixed(2) + '</small>' : ''}</td>
                <td class="text-end">$${Fmt.numero(detalle.precio_unitario, 4)}</td>
                <td class="text-center">${Fmt.numero(detalle.iva_porcentaje, 2)}%</td>
                <td class="text-end">$${Fmt.numero((detalle.iva_importe || 0), 2)}</td>
                <td class="text-end">$${Fmt.numero((detalle.no_gravado || 0), 2)}</td>
                <td class="text-end">$${Fmt.numero((detalle.exento || 0), 2)}</td>
                <td class="text-end">$${Fmt.numero((detalle.neto_gravado || 0), 2)}</td>
                <td class="text-end fw-bold text-success">$${Fmt.numero(detalle.total_linea, 2)}</td>
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

    // ========== FUNCIÓN ACTUALIZAR TOTALES ==========
    function actualizarTotales() {
        var totalNeto = 0;
        var totalNoGravado = 0;
        var totalExento = 0;
        var totalImpuestos = 0;
        
        detalles.forEach(function(detalle) {
            totalNeto += detalle.neto_gravado || 0;
            totalImpuestos += detalle.iva_importe || 0;
            totalNoGravado += detalle.no_gravado || 0;
            totalExento += detalle.exento || 0;
        });
        
        var totalGeneral = totalNeto + totalImpuestos + totalNoGravado + totalExento;
        
        $('#total_neto').val(totalNeto.toFixed(2));
        $('#no_gravado').val(totalNoGravado.toFixed(2));
        $('#exento').val(totalExento.toFixed(2));
        $('#impuestos').val(totalImpuestos.toFixed(2));
        $('#total').val(totalGeneral.toFixed(2));
        
        $('#total_neto_display').text(Fmt.numero(totalNeto, 2));
        $('#no_gravado_display').text(Fmt.numero(totalNoGravado, 2));
        $('#exento_display').text(Fmt.numero(totalExento, 2));
        $('#impuestos_display').text(Fmt.numero(totalImpuestos, 2));
        $('#total_display').text(Fmt.numero(totalGeneral, 2));
    }

    // ========== FUNCIONES DE PANTALLA COMPLETA ==========
    $('#btnToggleFullscreen').click(function() {
        var modalDialog = $('#modalOrdenCompra .modal-dialog');
        var btnIcon = $(this).find('i');
        
        if (modalDialog.hasClass('modal-fullscreen')) {
            modalDialog.removeClass('modal-fullscreen');
            btnIcon.removeClass('fa-compress').addClass('fa-expand');
        } else {
            modalDialog.addClass('modal-fullscreen');
            btnIcon.removeClass('fa-expand').addClass('fa-compress');
        }
    });

    // Eliminar detalle
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

    // Editar detalle
    $(document).on('click', '.btn-editar-detalle', function() {
        var idx = $(this).data('idx');
        var detalle = detalles.find(function(item) {
            return item.detalle_idx == idx;
        });

        if (detalle) {
            $('#busqueda_producto').val(detalle.producto_nombre);
            $('#producto_seleccionado_id').val(detalle.producto_id);
            $('#producto_iva_id').val(detalle.iva_alicuota_id);
            $('#producto_cantidad').val(detalle.cantidad);
            $('#producto_precio').val(detalle.precio_unitario);
            $('#producto_iva').val(detalle.iva_porcentaje);
            $('#producto_iva_importe').val(detalle.iva_importe);
            $('#producto_no_gravado').val(detalle.no_gravado || 0);
            $('#producto_exento').val(detalle.exento || 0);
            // Compra por presentación: la línea vuelve al formulario en su presentación (o por unidad)
            if (CPRE) CPRE.cargar(detalle.producto_id, detalle.presentacion_id || null, detalle.presentacion_cantidad, detalle.presentacion_precio, true);
            
            detalles = detalles.filter(function(item) {
                return item.detalle_idx != idx;
            });
            renderizarDetalles();
            actualizarTotales();
            
            $('#busqueda_producto').focus();
        }
    });

    // ========== FUNCIONES DE CARGA DE COMBOS ==========
    function cargarCombosFormulario() {
        $.get('ordenes_compra_ajax.php', { accion: 'obtener_usuarios' }, function(data) {
            let options = '<option value="">Seleccionar inspector asignado</option>';
            if (data && data.length > 0) {
                data.forEach(function(u) {
                    options += `<option value="${u.usuario_id}">${u.usuario_nombre}</option>`;
                });
            }
            $('#responsable_tecnico_id').html(options);
        }, 'json');

        $.get('ordenes_compra_ajax.php', { 
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
            console.log("Sucursales cargadas:", data);
        }, 'json');

        $.get('ordenes_compra_ajax.php', { accion: 'obtener_comprobantes_tipos' }, function(data) {
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

        $.get('ordenes_compra_ajax.php', { accion: 'obtener_monedas' }, function(data) {
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

        $.get('ordenes_compra_ajax.php', { 
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

    // ========== FUNCIONES DEL MODAL ==========
    function resetModal() {
        $('#formOrdenCompra')[0].reset();
        $('#orden_compra_id').val('');
        $('#entidad_id').val('');
        $('#entidad_sucursal_id').val('');
        $('#punto_venta_id').html('<option value="">Primero seleccione sucursal</option>');
        $('#punto_venta_id').prop('disabled', true);
        $('#tipo_cambio').val('1.000000');
        $('#total_neto').val('0');
        $('#no_gravado').val('0');
        $('#exento').val('0');
        $('#impuestos').val('0');
        $('#total').val('0');
        $('#formOrdenCompra').removeClass('was-validated');

        // Reset campos de contrato
        $('#tipo_circuito').val('BIENES').trigger('change');
        $('#contrato_nro_legal').val('');
        $('#fecha_inicio').val('');
        $('#fecha_fin').val('');
        $('#dias_preaviso_rescision').val('30');
        $('#tiene_renovacion_automatica').prop('checked', false);
        $('#pct_anticipo_financiero').val('0.00');
        $('#pct_fondo_reparo').val('0.00');
        $('#responsable_tecnico_id').val('');
        
        detalles = [];
        proveedorActualId = null;
        proveedorSucursalActualId = null;
        renderizarDetalles();
        actualizarTotales();
        
        $('#entidad_combo').html('<option value="">Seleccionar proveedor o sucursal</option>');
        $('#proveedor_actual_nombre').text('No seleccionado');
        
        // Restaurar botón cancelar
        $('.btn-secondary[data-bs-dismiss="modal"]').show();
        
        // Restaurar botones de acciones de productos
        $('.btn-editar-detalle, .btn-eliminar-detalle, #btnAgregarProducto, #btnNuevoProductoRapido').show();
        $('#busqueda_producto').prop('disabled', false);
        
        // Eliminar botón de imprimir si existe
        $('#btnImprimirDesdeEdicion').remove();
        
        window.sucursalIdEditar = null;
    }

    $(document).on('click', '#btnNuevo', function () {
        resetModal();
        $('#modalLabel').text('Nueva Orden de Compra');
        cargarCombosFormulario();
        cargarProveedoresYSucursales();
        
        var today = new Date().toISOString().split('T')[0];
        $('#f_emision').val(today);

        var modal = new bootstrap.Modal(document.getElementById('modalOrdenCompra'));
        modal.show();
    });

    // ========== CARGA DE ORDEN PARA EDITAR (CON BOTÓN IMPRIMIR) ==========
    function cargarOrdenParaEditar(ordenId) {
        $.get('ordenes_compra_ajax.php', {
            accion: 'obtener',
            orden_compra_id: ordenId,
            empresa_idx: empresa_idx
        }, function (res) {
            console.log("Orden recibida:", res);
            
            if (res && res.orden_compra_id) {
                resetModal();
                
                cargarCombosFormulario();
                cargarProveedoresYSucursales();
                
                $('#orden_compra_id').val(res.orden_compra_id);
                $('#comprobante_nro').val(res.comprobante_nro);

                // Campos de Contratos y Servicios
                $('#tipo_circuito').val(res.tipo_circuito || 'BIENES').trigger('change');
                $('#contrato_nro_legal').val(res.contrato_nro_legal || '');
                $('#fecha_inicio').val(res.fecha_inicio || '');
                $('#fecha_fin').val(res.fecha_fin || '');
                $('#dias_preaviso_rescision').val(res.dias_preaviso_rescision !== undefined ? res.dias_preaviso_rescision : 30);
                $('#tiene_renovacion_automatica').prop('checked', parseInt(res.tiene_renovacion_automatica) === 1);
                $('#pct_anticipo_financiero').val(res.pct_anticipo_financiero || '0.00');
                $('#pct_fondo_reparo').val(res.pct_fondo_reparo || '0.00');
                $('#responsable_tecnico_id').val(res.responsable_tecnico_id || '');
                
                $('#f_emision').val(res.f_emision);
                $('#f_entrega_estimada').val(res.f_entrega_estimada);
                $('#direccion_entrega').val(res.direccion_entrega);
                $('#observaciones').val(res.observaciones);
                $('#tipo_cambio').val(res.tipo_cambio || '1.000000');
                $('#total_neto').val(res.subtotal || 0);
                $('#no_gravado').val(res.no_gravado || 0);
                $('#exento').val(res.exento || 0);
                $('#impuestos').val(res.impuestos || 0);
                $('#total').val(res.total || 0);
                
                $('#total_neto_display').text(Fmt.numero(parseFloat(res.subtotal || 0), 2));
                $('#no_gravado_display').text(Fmt.numero(parseFloat(res.no_gravado || 0), 2));
                $('#exento_display').text(Fmt.numero(parseFloat(res.exento || 0), 2));
                $('#impuestos_display').text(Fmt.numero(parseFloat(res.impuestos || 0), 2));
                $('#total_display').text(Fmt.numero(parseFloat(res.total || 0), 2));
                $('#modalLabel').text('Editar Orden de Compra');

                // Verificar si la orden ya tiene número para mostrar botón imprimir
                if (res.comprobante_nro && res.comprobante_nro > 0) {
                    $('.row.mt-2.mb-3 .col-12').append(
                        `<button type="button" class="btn btn-info btn-sm px-4 ms-2" id="btnImprimirDesdeEdicion">
                            <i class="fas fa-print me-1"></i>Imprimir
                        </button>`
                    );
                    
                    $('#btnImprimirDesdeEdicion').off('click').on('click', function() {
                        imprimirComprobante(ordenId);
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
                                console.log("Punto de venta seleccionado:", res.punto_venta_id);
                            }
                        });
                    }
                    
                    if (res.entidad_id) {
                        proveedorActualId = res.entidad_id;
                        
                        if (res.entidad_sucursal_id && res.entidad_sucursal_id > 0) {
                            $('#entidad_combo').val('S-' + res.entidad_sucursal_id);
                            proveedorSucursalActualId = parseInt(res.entidad_sucursal_id);
                        } else {
                            $('#entidad_combo').val('P-' + res.entidad_id);
                            proveedorSucursalActualId = null;
                        }
                        
                        var textoSeleccionado = $('#entidad_combo option:selected').text();
                        $('#proveedor_actual_nombre').text(textoSeleccionado || 'No seleccionado');
                    }
                    
                    if (res.detalles && res.detalles.length > 0) {
                        detalles = res.detalles.map(function(detalle, index) {
                            return $.extend({
                                detalle_idx: index,
                                ordenes_compra_detalle_id: detalle.ordenes_compra_detalle_id,
                                producto_id: detalle.producto_id,
                                producto_nombre: detalle.producto_nombre,
                                cantidad: detalle.cantidad,
                                cantidad_recibida: parseFloat(detalle.cantidad_recibida) || 0,
                                precio_unitario: detalle.precio_unitario,
                                no_gravado: detalle.no_gravado || 0,
                                exento: detalle.exento || 0,
                                iva_alicuota_id: detalle.iva_alicuota_id,
                                iva_porcentaje: detalle.iva_porcentaje,
                                neto_gravado: detalle.neto_gravado,
                                iva_importe: detalle.iva_importe,
                                total_linea: detalle.total_linea
                            }, CPRE ? CPRE.foto(detalle) : {});
                        });
                        renderizarDetalles();
                        actualizarTotales();
                    }
                    
                    // Verificar si la orden está confirmada (tiene número) para ocultar botón cancelar y acciones
                    if (res.comprobante_nro && res.comprobante_nro > 0) {
                        $('.btn-secondary[data-bs-dismiss="modal"]').hide();
                        // Ocultar botones de acciones de productos
                        $('.btn-editar-detalle, .btn-eliminar-detalle, #btnAgregarProducto, #btnNuevoProductoRapido').hide();
                        $('#busqueda_producto').prop('disabled', true);
                    } else {
                        $('.btn-secondary[data-bs-dismiss="modal"]').show();
                        $('.btn-editar-detalle, .btn-eliminar-detalle, #btnAgregarProducto, #btnNuevoProductoRapido').show();
                        $('#busqueda_producto').prop('disabled', false);
                    }
                    
                }, 500);

                var modal = new bootstrap.Modal(document.getElementById('modalOrdenCompra'));
                modal.show();
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos de la orden",
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json');
    }

    // ========== GUARDAR ORDEN ==========
    $('#btnGuardar').click(function() {
        var form = document.getElementById('formOrdenCompra');
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
        
        var id = $('#orden_compra_id').val();
        var accionBackend = id ? 'editar' : 'agregar';
        
        var btnGuardar = $(this);
        var originalText = btnGuardar.html();
        btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');
        
        if (!proveedorActualId) {
            Swal.fire({
                icon: "warning",
                title: "Proveedor requerido",
                text: "Debe seleccionar un proveedor",
                confirmButtonText: "Entendido"
            });
            btnGuardar.prop('disabled', false).html(originalText);
            return false;
        }
        
        var formData = new FormData();
        formData.append('accion', accionBackend);
        formData.append('empresa_idx', empresa_idx);
        formData.append('pagina_idx', pagina_idx);
        formData.append('orden_compra_id', $('#orden_compra_id').val() || '');
        formData.append('sucursal_id', $('#sucursal_id').val() || '');
        formData.append('punto_venta_id', $('#punto_venta_id').val() || '');
        formData.append('entidad_id', proveedorActualId);
        formData.append('entidad_sucursal_id', proveedorSucursalActualId !== null ? proveedorSucursalActualId : '');
        formData.append('comprobante_tipo_id', $('#comprobante_tipo_id').val() || '');
        formData.append('tipo_circuito', $('#tipo_circuito').val() || 'BIENES');
        formData.append('es_contrato', $('#tipo_circuito').val() !== 'BIENES' ? '1' : '0');
        formData.append('contrato_nro_legal', $('#contrato_nro_legal').val() || '');
        formData.append('fecha_inicio', $('#fecha_inicio').val() || '');
        formData.append('fecha_fin', $('#fecha_fin').val() || '');
        formData.append('dias_preaviso_rescision', $('#dias_preaviso_rescision').val() || '30');
        formData.append('tiene_renovacion_automatica', $('#tiene_renovacion_automatica').is(':checked') ? '1' : '0');
        formData.append('requiere_certificacion_avance', $('#tipo_circuito').val() === 'CONTRATO_AVANCE' ? '1' : '0');
        formData.append('pct_anticipo_financiero', $('#pct_anticipo_financiero').val() || '0');
        formData.append('pct_fondo_reparo', $('#pct_fondo_reparo').val() || '0');
        formData.append('responsable_tecnico_id', $('#responsable_tecnico_id').val() || '');
        
        formData.append('comprobante_nro', $('#comprobante_nro').val() || '0');
        formData.append('f_emision', $('#f_emision').val() || '');
        formData.append('f_entrega_estimada', $('#f_entrega_estimada').val() || '');
        formData.append('condicion_pago_id', $('#condicion_pago_id').val() || '');
        formData.append('moneda_id', $('#moneda_id').val() || '');
        formData.append('tipo_cambio', $('#tipo_cambio').val() || '1.000000');
        formData.append('direccion_entrega', $('#direccion_entrega').val() || '');
        formData.append('observaciones', $('#observaciones').val() || '');
        formData.append('subtotal', $('#total_neto').val() || '0');
        formData.append('no_gravado', $('#no_gravado').val() || '0');
        formData.append('exento', $('#exento').val() || '0');
        formData.append('impuestos', $('#impuestos').val() || '0');
        formData.append('total', $('#total').val() || '0');
        formData.append('detalles', JSON.stringify(detalles));

        console.log("=== DATOS ENVIADOS ===");
        for (var pair of formData.entries()) {
            console.log(pair[0] + ': "' + pair[1] + '"');
        }
        
        var savedState = {
            page: tabla ? tabla.page() : 0,
            order: tabla ? tabla.order() : [[1, 'asc']],
            search: tabla ? tabla.search() : ''
        };
        
        $.ajax({
            url: 'ordenes_compra_ajax.php',
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
                        text: "Orden de compra guardada correctamente",
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });
                    
                    var modalEl = document.getElementById('modalOrdenCompra');
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

    // ========== PRODUCTO RÁPIDO ==========
    function cargarDatosProductoRapido() {
        $.get('ordenes_compra_ajax.php', { 
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

        $.get('ordenes_compra_ajax.php', { accion: 'obtener_unidades_medida' }, function(data) {
            var options = '<option value="">Seleccionar unidad</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.unidad_medida_id}">${item.unidad_nombre}</option>`;
                });
            }
            $('#unidad_medida_id_rapido').html(options);
        }, 'json');
        
        $.get('ordenes_compra_ajax.php', { accion: 'obtener_alicuotas_iva' }, function(data) {
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
        if (!proveedorActualId) {
            Swal.fire({
                icon: "warning",
                title: "Seleccione proveedor",
                text: "Debe seleccionar un proveedor primero",
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
            unidad_medida_id: $('#unidad_medida_id_rapido').val() || null,
            codigo_proveedor: $('#codigo_proveedor_rapido').val(),
            entidad_id: proveedorActualId
        };

        $.ajax({
            url: 'ordenes_compra_ajax.php',
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

    // ========== CONTRATOS Y CERTIFICACIONES DE AVANCE ==========
    var contratoActualId = null;
    var contratoActualData = null;

    $('#tipo_circuito').on('change', function() {
        var v = $(this).val();
        if (v === 'BIENES') {
            $('#seccionContrato').slideUp(200);
            $('#seccionAvanceParams').hide();
        } else if (v === 'CONTRATO_RECURRENTE') {
            $('#seccionContrato').slideDown(200);
            $('#badgeModalCircuito').text('Contrato Recurrente / Abono').removeClass('bg-purple text-white').addClass('bg-info text-dark');
            $('#seccionAvanceParams').slideUp(200);
        } else if (v === 'CONTRATO_AVANCE') {
            $('#seccionContrato').slideDown(200);
            $('#badgeModalCircuito').text('Contrato con Certificación de Avance').removeClass('bg-info text-dark').addClass('bg-purple text-white').css('background-color', '#6f42c1');
            $('#seccionAvanceParams').slideDown(200);
        }
    });

    // Abrir Modal de Certificaciones y Seguimiento
    $(document).on('click', '.btn-ver-certificaciones', function() {
        var ocId = $(this).data('id');
        contratoActualId = ocId;
        cargarSeguimientoContrato(ocId);
    });

    function cargarSeguimientoContrato(ocId) {
        $.get('ordenes_compra_ajax.php', {
            accion: 'contrato_resumen',
            orden_compra_id: ocId,
            empresa_idx: empresa_idx
        }, function(res) {
            if (!res || !res.success || !res.data) {
                Swal.fire({ icon: 'error', title: 'Error', text: res.error || 'No se pudo cargar el resumen del contrato' });
                return;
            }
            var d = res.data;
            contratoActualData = d;

            var c = d.contrato || {};
            var v = d.vigencia || {};
            var cert = d.certificaciones || {};
            var f = d.financiero || {};

            $('#cert_header_titulo').text((c.tipo_circuito === 'CONTRATO_AVANCE' ? 'Contrato de Obra/Avance' : 'Contrato') + ' #' + (c.comprobante_nro || c.orden_compra_id) + (c.contrato_nro_legal ? ' (' + c.contrato_nro_legal + ')' : ''));
            $('#cert_header_proveedor').text(c.entidad_fantasia || c.entidad_nombre || 'Proveedor no especificado');
            
            var vigTxt = (c.fecha_inicio ? Fmt.fecha(c.fecha_inicio) : '—') + ' al ' + (c.fecha_fin ? Fmt.fecha(c.fecha_fin) : 'Indefinido');
            $('#cert_header_vigencia').text(vigTxt);

            var badgeVig = '';
            if (v.esta_vencido) {
                badgeVig = '<span class="badge bg-danger"><i class="fas fa-exclamation-triangle me-1"></i>Vencido</span>';
            } else if (v.en_ventana_preaviso) {
                badgeVig = '<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>En Preaviso (' + v.dias_restantes + ' días)</span>';
            } else if (v.dias_restantes !== null) {
                badgeVig = '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Vigente (' + v.dias_restantes + ' días)</span>';
            }
            $('#cert_header_badge_vigencia').html(badgeVig);

            var sim = c.moneda_simbolo || '$';
            $('#cert_kpi_total').text(sim + Fmt.numero(f.monto_total_contratado || 0, 2));
            $('#cert_kpi_certificado').text(sim + Fmt.numero(cert.total_bruto_certificado || 0, 2));
            $('#cert_kpi_facturado').text(sim + Fmt.numero(f.total_facturado || 0, 2));
            $('#cert_kpi_saldo').text(sim + Fmt.numero(f.saldo_por_ejecutar || 0, 2));

            // Barras de progreso
            var pctFisico = Math.min(100, Math.max(0, cert.avance_fisico_pct || 0));
            var pctFact = Math.min(100, Math.max(0, f.pct_facturado || 0));

            $('#cert_bar_fisico_txt').text(pctFisico.toFixed(2) + '%');
            $('#cert_bar_fisico').css('width', pctFisico + '%');

            $('#cert_bar_facturado_txt').text(pctFact.toFixed(2) + '%');
            $('#cert_bar_facturado').css('width', pctFact + '%');

            cargarTablaCertificaciones(ocId, sim);

            var modal = new bootstrap.Modal(document.getElementById('modalCertificaciones'));
            modal.show();
        }, 'json');
    }

    function cargarTablaCertificaciones(ocId, sim) {
        sim = sim || '$';
        var tbody = $('#tbodyCertificaciones');
        tbody.html('<tr><td colspan="12" class="text-center py-3"><i class="fas fa-spinner fa-spin me-1"></i>Cargando historial...</td></tr>');

        $.get('ordenes_compra_ajax.php', {
            accion: 'certificaciones_listar',
            orden_compra_id: ocId,
            empresa_idx: empresa_idx
        }, function(res) {
            tbody.empty();
            if (!res || !res.success || !Array.isArray(res.data) || res.data.length === 0) {
                tbody.html('<tr><td colspan="12" class="text-center text-muted py-3">No hay certificaciones registradas para este contrato.</td></tr>');
                return;
            }

            res.data.forEach(function(r) {
                var estadoBadge = '';
                var st = parseInt(r.tabla_estado_registro_id);
                if (st === 3) {
                    estadoBadge = '<span class="badge bg-secondary">Borrador</span>';
                } else if (st === 4) {
                    estadoBadge = '<span class="badge bg-warning text-dark">En Revisión</span>';
                } else if (st === 5) {
                    estadoBadge = '<span class="badge bg-success">Aprobado</span>';
                } else if (st === 12) {
                    estadoBadge = '<span class="badge bg-primary">Facturado</span>';
                } else {
                    estadoBadge = '<span class="badge bg-light text-dark">Estado ' + st + '</span>';
                }

                var facInfo = '—';
                if (r.factura_pv && r.factura_nro) {
                    facInfo = (r.factura_tipo_codigo || 'FAC') + ' ' + String(r.factura_pv).padStart(4, '0') + '-' + String(r.factura_nro).padStart(8, '0');
                }

                var btnAprobar = '';
                if (st === 3 || st === 4) {
                    btnAprobar = `<button type="button" class="btn btn-sm btn-outline-success btn-aprobar-certificacion" data-id="${r.certificacion_id}" title="Aprobar técnicamente y habilitar para facturar"><i class="fas fa-check"></i></button>`;
                }

                var tr = `<tr>
                    <td class="text-center fw-bold">#${r.nro_certificado}</td>
                    <td class="text-center">${r.fecha_medicion ? Fmt.fecha(r.fecha_medicion) : '—'}</td>
                    <td class="text-center">${r.periodo_mes || '—'}</td>
                    <td class="text-center fw-bold" style="color: #6f42c1;">${parseFloat(r.avance_acumulado_pct).toFixed(2)}%</td>
                    <td class="text-center">${parseFloat(r.avance_periodo_pct).toFixed(2)}%</td>
                    <td class="text-end">${sim}${Fmt.numero(r.monto_bruto_periodo, 2)}</td>
                    <td class="text-end text-muted">-${sim}${Fmt.numero(r.descuento_anticipo, 2)}</td>
                    <td class="text-end text-muted">-${sim}${Fmt.numero(r.fondo_reparo_retenido, 2)}</td>
                    <td class="text-end fw-bold text-success">${sim}${Fmt.numero(r.monto_neto_facturable, 2)}</td>
                    <td class="text-center">${estadoBadge}</td>
                    <td class="text-center small">${facInfo}</td>
                    <td class="text-center">${btnAprobar}</td>
                </tr>`;
                tbody.append(tr);
            });
        }, 'json');
    }

    // Submodal Nueva Certificación
    $('#btnNuevaCertificacionModal').on('click', function() {
        if (!contratoActualData) return;
        var oc = contratoActualData.contrato || {};
        var cert = contratoActualData.certificaciones || {};

        $('#formNuevaCertificacion')[0].reset();
        $('#cert_orden_compra_id').val(contratoActualId);
        $('#cert_certificacion_id').val('');

        var hoy = new Date().toISOString().split('T')[0];
        $('#cert_fecha_medicion').val(hoy);
        $('#cert_periodo_mes').val(hoy.substring(0, 7));

        var prevPct = parseFloat(cert.avance_fisico_pct || 0);
        $('#cert_avance_acumulado_pct').val(prevPct).attr('min', prevPct);

        recalcularPreviewCertificacion(prevPct);

        var submodal = new bootstrap.Modal(document.getElementById('modalNuevaCertificacion'));
        submodal.show();
    });

    $('#cert_avance_acumulado_pct').on('input', function() {
        var val = parseFloat($(this).val()) || 0;
        recalcularPreviewCertificacion(val);
    });

    function recalcularPreviewCertificacion(acumuladoActual) {
        if (!contratoActualData) return;
        var oc = contratoActualData.contrato || {};
        var cert = contratoActualData.certificaciones || {};

        var totalContrato = parseFloat(oc.total || 0);
        var prevPct = parseFloat(cert.avance_fisico_pct || 0);
        var pctAnticipo = parseFloat(oc.pct_anticipo_financiero || 0);
        var pctReparo = parseFloat(oc.pct_fondo_reparo || 0);

        var pctPeriodo = Math.max(0, acumuladoActual - prevPct);
        var montoBruto = (pctPeriodo / 100) * totalContrato;
        var descAnticipo = montoBruto * (pctAnticipo / 100);
        var fondoReparo = montoBruto * (pctReparo / 100);
        var neto = Math.max(0, montoBruto - descAnticipo - fondoReparo);

        var sim = oc.moneda_simbolo || '$';
        $('#calc_cert_pct_periodo').text(pctPeriodo.toFixed(2) + '%');
        $('#calc_cert_bruto').text(sim + Fmt.numero(montoBruto, 2));
        $('#calc_cert_deducciones').text('-' + sim + Fmt.numero(descAnticipo + fondoReparo, 2));
        $('#calc_cert_neto').text(sim + Fmt.numero(neto, 2));
    }

    $('#btnGuardarCertificacion').on('click', function() {
        var form = document.getElementById('formNuevaCertificacion');
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }

        var btn = $(this);
        var orig = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        var postData = $('#formNuevaCertificacion').serializeArray();
        postData.push({ name: 'accion', value: 'certificacion_guardar' });
        postData.push({ name: 'empresa_idx', value: empresa_idx });

        $.post('ordenes_compra_ajax.php', postData, function(res) {
            btn.prop('disabled', false).html(orig);
            if (res && res.resultado) {
                var submodalEl = document.getElementById('modalNuevaCertificacion');
                var submodal = bootstrap.Modal.getInstance(submodalEl);
                if (submodal) submodal.hide();

                Swal.fire({
                    icon: 'success',
                    title: 'Certificación Guardada',
                    text: 'Certificado #' + (res.nro_certificado || '') + ' registrado correctamente.',
                    timer: 1500,
                    showConfirmButton: false,
                    toast: true
                });

                // Recargar el resumen y la tabla del contrato
                cargarSeguimientoContrato(contratoActualId);
            } else {
                Swal.fire({ icon: 'error', title: 'Error', text: (res ? res.error : 'Error desconocido al guardar certificación') });
            }
        }, 'json').fail(function() {
            btn.prop('disabled', false).html(orig);
            Swal.fire({ icon: 'error', title: 'Error', text: 'Error al comunicarse con el servidor' });
        });
    });

    // Acción para Aprobar Certificación
    $(document).on('click', '.btn-aprobar-certificacion', function() {
        var certId = $(this).data('id');
        Swal.fire({
            title: '¿Aprobar Certificación?',
            text: 'La aprobación técnica habilitará inmediatamente la facturación de este certificado en compras y en el portal de proveedores.',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, aprobar',
            cancelButtonText: 'Cancelar',
            confirmButtonColor: '#198754'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.post('ordenes_compra_ajax.php', {
                    accion: 'certificacion_aprobar',
                    certificacion_id: certId,
                    empresa_idx: empresa_idx
                }, function(res) {
                    if (res && res.resultado) {
                        Swal.fire({
                            icon: 'success',
                            title: '¡Certificado Aprobado!',
                            text: res.message || 'Habilitado para facturar',
                            timer: 1500,
                            showConfirmButton: false,
                            toast: true
                        });
                        cargarSeguimientoContrato(contratoActualId);
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: (res ? res.error : 'No se pudo aprobar la certificación') });
                    }
                }, 'json');
            }
        });
    });

    // ========== INICIALIZACIÓN ==========
    inicializarDataTable();
    cargarBotonAgregar();

    $('[title]').tooltip({
        trigger: 'hover',
        placement: 'top'
    });
});