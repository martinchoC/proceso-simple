$(document).ready(function () {
    const empresa_idx = 2;
    const pagina_id = 81;
    
    var tabla;
    var currentPage = 0;
    var currentOrder = [[1, 'asc']];
    var currentSearch = '';
    
    // ========== FUNCIONES DE DATATABLE ==========
    function inicializarDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaProductosCostos')) {
            $('#tablaProductosCostos').DataTable().destroy();
            $('#tablaProductosCostos tbody').empty();
        }

        tabla = $('#tablaProductosCostos').DataTable({
            ajax: {
                url: 'productos_costos_ajax.php',
                type: 'GET',
                data: {
                    accion: 'listar',
                    empresa_idx: empresa_idx,
                    pagina_id: pagina_id
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
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>' +
                '<"clear">',
            pageLength: 50,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel"></i> Excel',
                    className: 'btn btn-success btn-sm',
                    title: 'Productos Costos',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7, 8],
                        orthogonal: 'export'
                    }
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> PDF',
                    className: 'btn btn-danger btn-sm',
                    title: 'Productos Costos',
                    orientation: 'portrait',
                    pageSize: 'A4',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7, 8],
                        orthogonal: 'export'
                    }
                },
                {
                    extend: 'csvHtml5',
                    text: '<i class="fas fa-file-csv"></i> CSV',
                    className: 'btn btn-primary btn-sm',
                    title: 'Productos_Costos',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print"></i> Imprimir',
                    className: 'btn btn-secondary btn-sm',
                    title: 'Productos Costos',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7, 8],
                        stripHtml: false
                    }
                }
            ],
            columns: [
                { data: 'producto_costo_id', className: 'text-center' },
                { 
                    data: null,
                    render: function(data, type, row) {
                        if (type === 'export') return row.producto_codigo || '';
                        return row.producto_codigo || '<span class="text-muted">-</span>';
                    }
                },
                { 
                    data: null,
                    render: function(data, type, row) {
                        if (type === 'export') return row.producto_nombre || '';
                        return `<div>${row.producto_nombre || ''}</div>
                                <small class="text-muted">${row.producto_codigo || ''}</small>`;
                    }
                },
                { 
                    data: 'costo_actual',
                    className: 'text-end',
                    render: function(data, type, row) {
                        if (type === 'export') return parseFloat(data).toFixed(6);
                        return `<span class="text-primary">${formatNumber(data, 6)}</span>`;
                    }
                },
                { data: 'moneda', className: 'text-center', defaultContent: '' },
                { data: 'origen_nombre', className: 'text-center', defaultContent: '' },
                { 
                    data: null,
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (type === 'export') return row.comprobante_id || '';
                        return row.comprobante_id ? `<a href="#" class="link-comprobante" data-id="${row.comprobante_id}">${row.comprobante_id}</a>` : '-';
                    }
                },
                { 
                    data: 'f_actualizacion',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (type === 'export') return data;
                        if (!data) return '';
                        let parts = data.split('-');
                        if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
                        return data;
                    }
                },
                { 
                    data: 'estado_info',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (!data || !data.estado_registro) {
                            if (type === 'export') return 'Sin estado';
                            return '<span class="text-dark">Sin estado</span>';
                        }
                        var estado = data.estado_registro;
                        if (type === 'export') return estado;
                        return `<span class="badge ${data.bg_clase || 'bg-secondary'}">${estado}</span>`;
                    }
                },
                { 
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    width: '250px',
                    render: function(data, type, row) {
                        // Botón de historial SIEMPRE visible
                        var botonHistorial = `<button type="button" class="btn btn-sm btn-info me-1 btn-historial" 
                                                    title="Ver Historial de Costos" 
                                                    data-id="${row.producto_costo_id}"
                                                    data-producto="${escapeHtml(row.producto_nombre || '')} (${escapeHtml(row.producto_codigo || '')})">
                                                <i class="fas fa-history"></i>
                                            </button>`;
                        
                        // Botones dinámicos según estado
                        var botonesDinamicos = '';
                        if (row.botones && row.botones.length > 0) {
                            row.botones.forEach(function(boton) {
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
                                var productoInfo = `${row.producto_codigo || ''} - ${row.producto_nombre || ''}`;
                                
                                if (accionJs !== 'historial') {  // Excluir historial de los botones dinámicos
                                    botonesDinamicos += `<button type="button" class="btn ${claseBoton} btn-accion-dinamico" 
                                                                title="${titulo}" 
                                                                data-id="${row.producto_costo_id}" 
                                                                data-accion="${accionJs}"
                                                                data-confirmable="${esConfirmable}"
                                                                data-producto="${productoInfo}">
                                                                ${icono}
                                                            </button>`;
                                }
                            });
                        }
                        
                        return `<div class="btn-group" role="group">${botonHistorial}${botonesDinamicos}</div>`;
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json'                
            },
            order: currentOrder,
            responsive: true,
            initComplete: function () {
                var buttons = new $.fn.dataTable.Buttons(tabla, {
                    buttons: ['excelHtml5', 'pdfHtml5', 'csvHtml5', 'print']
                }).container().appendTo($('#tablaProductosCostos_wrapper .col-md-6:eq(1)'));

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
    }

    function cargarBotonAgregar() {
        $.get('productos_costos_ajax.php', {
            accion: 'obtener_boton_agregar',
            pagina_id: pagina_id
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
                    '<i class="fas fa-plus me-1"></i>Nuevo Costo</button>'
                );
            }
        }, 'json');
    }

    function formatNumber(number, decimals = 2) {
        if (number === null || number === undefined || number === '') return '0.00';
        var num = parseFloat(number);
        if (isNaN(num)) return '0.00';
        return num.toLocaleString('es-AR', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals
        });
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        var parts = dateStr.split('-');
        if (parts.length === 3) return `${parts[2]}/${parts[1]}/${parts[0]}`;
        return dateStr;
    }

    function formatDateTime(dateTimeStr) {
        if (!dateTimeStr) return '';
        var date = new Date(dateTimeStr);
        return date.toLocaleString('es-AR');
    }

    function escapeHtml(text) {
        if (!text) return '';
        return text
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function cargarCombosFormulario() {
        // Cargar productos
        $.get('productos_costos_ajax.php', { 
            accion: 'obtener_productos',
            empresa_idx: empresa_idx 
        }, function(data) {
            var options = '<option value="">Seleccionar producto</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.producto_id}">${item.producto_codigo} - ${item.producto_nombre}</option>`;
                });
            }
            $('#producto_id').html(options);
        }, 'json');

        // Cargar monedas
        $.get('productos_costos_ajax.php', { 
            accion: 'obtener_monedas',
            empresa_idx: empresa_idx 
        }, function(data) {
            var options = '<option value="">Seleccionar moneda</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    var selected = item.es_moneda_base == 1 ? 'selected' : '';
                    options += `<option value="${item.moneda_id}" ${selected}>${item.moneda} (${item.simbolo})</option>`;
                });
            }
            $('#moneda_id').html(options);
        }, 'json');

        // Cargar orígenes de costo
        $.get('productos_costos_ajax.php', { 
            accion: 'obtener_origenes'
        }, function(data) {
            var options = '<option value="">Seleccionar origen</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.producto_costo_origen_id}">${item.producto_costo_origen_nombre}</option>`;
                });
            }
            $('#producto_costo_origen_id').html(options);
        }, 'json');
    }

    function resetModal() {
        $('#formProductoCostos')[0].reset();
        $('#producto_costo_id').val('');
        $('#formProductoCostos').removeClass('was-validated');
        
        var today = new Date().toISOString().split('T')[0];
        $('#f_actualizacion').val(today);
    }

    $(document).on('click', '#btnNuevo', function () {
        resetModal();
        $('#modalLabel').text('Nuevo Producto Costo');
        cargarCombosFormulario();

        var modal = new bootstrap.Modal(document.getElementById('modalProductoCostos'));
        modal.show();
    });
    // Event handler para el botón de historial
    $(document).on('click', '.btn-historial', function() {
        var id = $(this).data('id');
        var productoNombre = $(this).data('producto') || 'Producto';
        
        $('#historial_producto_nombre').text(productoNombre);
        
        // Mostrar loading en la tabla
        $('#tablaHistorial tbody').html('<tr><td colspan="8" class="text-center">Cargando historial...</td></tr>');
        
        $.get('productos_costos_ajax.php', {
            accion: 'obtener_historial',
            producto_costo_id: id,
            empresa_idx: empresa_idx
        }, function(res) {
            if (res.success) {
                var tbody = $('#tablaHistorial tbody');
                tbody.empty();
                
                if (res.historial && res.historial.length > 0) {
                    res.historial.forEach(function(item) {
                        var row = `<tr>
                            <td class="text-center">${item.producto_costo_historial_id}</td>
                            <td class="text-end">${formatNumber(item.costo_anterior, 6)}</td>
                            <td class="text-end">${formatNumber(item.costo_nuevo, 6)}</td>
                            <td>${escapeHtml(item.origen_nombre || '-')}</td>
                            <td class="text-center">${item.comprobante_id || '-'}</td>
                            <td class="text-center">${formatDate(item.f_desde)}</td>
                            <td>${escapeHtml(item.observaciones || '-')}</td>
                            <td class="text-center">${formatDateTime(item.creado_en)}</td>
                        </tr>`;
                        tbody.append(row);
                    });
                } else {
                    tbody.html('<tr><td colspan="8" class="text-center">No hay historial de costos registrado</td></tr>');
                }
                
                var modal = new bootstrap.Modal(document.getElementById('modalHistorial'));
                modal.show();
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: res.error || "Error al cargar historial",
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json').fail(function() {
            $('#tablaHistorial tbody').html('<tr><td colspan="8" class="text-center text-danger">Error al cargar historial</td></tr>');
        });
    });

    // Event handler para botones dinámicos (editar, inactivar, activar, etc.)
    $(document).on('click', '.btn-accion-dinamico', function () {
        var id = $(this).data('id');
        var accionJs = $(this).data('accion');
        var confirmable = $(this).data('confirmable');
        var productoInfo = $(this).data('producto') || 'Costo #' + id;

        if (accionJs === 'editar') {
            cargarDatosParaEditar(id);
        } else if (accionJs === 'visualizar') {
            cargarDatosParaVisualizar(id);
        } else if (confirmable == 1) {
            Swal.fire({
                title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                html: `¿Está seguro de <strong>${accionJs}</strong> el costo del producto<br>
                    <strong>${productoInfo}</strong>?`,
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
                    ejecutarAccion(id, accionJs, productoInfo);
                }
            });
        } else {
            ejecutarAccion(id, accionJs, productoInfo);
        }
    });
    // ========== MANEJADOR DE ACCIONES DE BOTONES ==========
    $(document).on('click', '.btn-accion', function () {
        var id = $(this).data('id');
        var accionJs = $(this).data('accion');
        var confirmable = $(this).data('confirmable');
        var productoInfo = $(this).data('producto') || 'Costo #' + id;

        if (accionJs === 'editar') {
            cargarDatosParaEditar(id);
        } else if (accionJs === 'visualizar') {
            cargarDatosParaVisualizar(id);
        } else if (accionJs === 'historial') {
            verHistorial(id);
        } else if (confirmable == 1) {
            Swal.fire({
                title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                html: `¿Está seguro de <strong>${accionJs}</strong> el costo del producto<br>
                    <strong>${productoInfo}</strong>?`,
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
                    ejecutarAccion(id, accionJs, productoInfo);
                }
            });
        } else {
            ejecutarAccion(id, accionJs, productoInfo);
        }
    });

    function cargarDatosParaEditar(id) {
        $.get('productos_costos_ajax.php', {
            accion: 'obtener',
            producto_costo_id: id,
            empresa_idx: empresa_idx
        }, function (res) {
            if (res && res.producto_costo_id) {
                resetModal();
                cargarCombosFormulario();

                setTimeout(function() {
                    $('#producto_costo_id').val(res.producto_costo_id);
                    $('#producto_id').val(res.producto_id);
                    $('#moneda_id').val(res.moneda_id);
                    $('#costo_actual').val(res.costo_actual);
                    $('#producto_costo_origen_id').val(res.producto_costo_origen_id);
                    $('#comprobante_id').val(res.comprobante_id);
                    $('#f_actualizacion').val(res.f_actualizacion);
                    $('#observaciones').val(res.observaciones);
                    $('#modalLabel').text('Editar Producto Costo');
                }, 500);

                var modal = new bootstrap.Modal(document.getElementById('modalProductoCostos'));
                modal.show();
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos del costo",
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json');
    }

    function cargarDatosParaVisualizar(id) {
        $.get('productos_costos_ajax.php', {
            accion: 'obtener',
            producto_costo_id: id,
            empresa_idx: empresa_idx
        }, function (res) {
            if (res && res.producto_costo_id) {
                resetModal();
                cargarCombosFormulario();

                setTimeout(function() {
                    $('#producto_costo_id').val(res.producto_costo_id);
                    $('#producto_id').val(res.producto_id);
                    $('#moneda_id').val(res.moneda_id);
                    $('#costo_actual').val(res.costo_actual);
                    $('#producto_costo_origen_id').val(res.producto_costo_origen_id);
                    $('#comprobante_id').val(res.comprobante_id);
                    $('#f_actualizacion').val(res.f_actualizacion);
                    $('#observaciones').val(res.observaciones);
                    $('#modalLabel').text('Visualizar Producto Costo');

                    // Modo solo lectura
                    $('#formProductoCostos :input').prop('disabled', true);
                    $('#btnGuardar').hide();
                    $('.modal-footer .btn-secondary').hide();
                }, 500);

                var modal = new bootstrap.Modal(document.getElementById('modalProductoCostos'));
                modal.show();

                $('#modalProductoCostos').off('hidden.bs.modal').on('hidden.bs.modal', function () {
                    $('#formProductoCostos :input').prop('disabled', false);
                    $('#btnGuardar').show();
                    $('.modal-footer .btn-secondary').show();
                });
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos del costo",
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json');
    }

    // Variables para el historial
var historialCurrentPage = 1;
var historialProductoCostoId = null;
var historialFechaDesde = '';
var historialFechaHasta = '';

function verHistorial(id) {
    historialProductoCostoId = id;
    historialCurrentPage = 1;
    historialFechaDesde = '';
    historialFechaHasta = '';
    
    // Limpiar filtros
    $('#historial_fecha_desde').val('');
    $('#historial_fecha_hasta').val('');
    
    cargarHistorial();
}

function cargarHistorial() {
    if (!historialProductoCostoId) return;
    
    // Mostrar loading
    $('#tablaHistorial tbody').html('<tr><td colspan="8" class="text-center">Cargando historial...<td></tr>');
    $('#historial-pagination').empty();
    $('#historial-info').text('');
    
    $.get('productos_costos_ajax.php', {
        accion: 'obtener_historial_paginado',
        producto_costo_id: historialProductoCostoId,
        empresa_idx: empresa_idx,
        page: historialCurrentPage,
        fecha_desde: historialFechaDesde,
        fecha_hasta: historialFechaHasta
    }, function(res) {
        if (res.success) {
            $('#historial_producto_nombre').text(res.producto_nombre);
            
            var tbody = $('#tablaHistorial tbody');
            tbody.empty();
            
            if (res.historial && res.historial.length > 0) {
                res.historial.forEach(function(item) {
                    var row = `<tr>
                        <td class="text-center">${item.producto_costo_historial_id}</td>
                        <td class="text-end">${formatNumber(item.costo_anterior, 6)}</td>
                        <td class="text-end">${formatNumber(item.costo_nuevo, 6)}</td>
                        <td>${escapeHtml(item.origen_nombre || '-')}</td>
                        <td class="text-center">${item.comprobante_id || '-'}</td>
                        <td class="text-center">${formatDate(item.f_desde)}</td>
                        <td>${escapeHtml(item.observaciones || '-')}</td>
                        <td class="text-center">${formatDateTime(item.creado_en)}</td>
                    </table>`;
                    tbody.append(row);
                });
            } else {
                tbody.html('<tr><td colspan="8" class="text-center">No hay historial de costos registrado</td></tr>');
            }
            
            // Mostrar info de paginación
            var pag = res.pagination;
            $('#historial-info').text(`Mostrando ${pag.from} al ${pag.to} de ${pag.total} registros`);
            
            // Generar paginador
            generarPaginador(pag.current_page, pag.total_pages);
            
            var modal = new bootstrap.Modal(document.getElementById('modalHistorial'));
            modal.show();
        } else {
            Swal.fire({
                icon: "error",
                title: "Error",
                text: res.error || "Error al cargar historial",
                confirmButtonText: "Entendido"
            });
        }
    }, 'json').fail(function() {
        $('#tablaHistorial tbody').html('<tr><td colspan="8" class="text-center text-danger">Error al cargar historial</td></tr>');
    });
}

function generarPaginador(currentPage, totalPages) {
    var paginationUl = $('#historial-pagination');
    paginationUl.empty();
    
    if (totalPages <= 1) return;
    
    // Botón Anterior
    var prevDisabled = currentPage <= 1 ? 'disabled' : '';
    paginationUl.append(`
        <li class="page-item ${prevDisabled}">
            <a class="page-link" href="#" data-page="${currentPage - 1}" tabindex="-1">Anterior</a>
        </li>
    `);
    
    // Calcular页码范围
    var startPage = Math.max(1, currentPage - 2);
    var endPage = Math.min(totalPages, currentPage + 2);
    
    if (startPage > 1) {
        paginationUl.append(`
            <li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>
        `);
        if (startPage > 2) {
            paginationUl.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
        }
    }
    
    for (var i = startPage; i <= endPage; i++) {
        var active = (i === currentPage) ? 'active' : '';
        paginationUl.append(`
            <li class="page-item ${active}">
                <a class="page-link" href="#" data-page="${i}">${i}</a>
            </li>
        `);
    }
    
    if (endPage < totalPages) {
        if (endPage < totalPages - 1) {
            paginationUl.append(`<li class="page-item disabled"><span class="page-link">...</span></li>`);
        }
        paginationUl.append(`
            <li class="page-item"><a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a></li>
        `);
    }
    
    // Botón Siguiente
    var nextDisabled = currentPage >= totalPages ? 'disabled' : '';
    paginationUl.append(`
        <li class="page-item ${nextDisabled}">
            <a class="page-link" href="#" data-page="${currentPage + 1}">Siguiente</a>
        </li>
    `);
    
    // Evento click en paginación
    $('.pagination .page-link').off('click').on('click', function(e) {
        e.preventDefault();
        var page = parseInt($(this).data('page'));
        if (!isNaN(page) && page !== historialCurrentPage && page >= 1 && page <= totalPages) {
            historialCurrentPage = page;
            cargarHistorial();
        }
    });
}

// Evento para filtrar historial
$(document).on('click', '#btnFiltrarHistorial', function() {
    historialFechaDesde = $('#historial_fecha_desde').val();
    historialFechaHasta = $('#historial_fecha_hasta').val();
    historialCurrentPage = 1;
    cargarHistorial();
});

// Limpiar filtros al cerrar el modal
$('#modalHistorial').on('hidden.bs.modal', function() {
    historialFechaDesde = '';
    historialFechaHasta = '';
    historialCurrentPage = 1;
    historialProductoCostoId = null;
});

    function ejecutarAccion(id, accionJs, productoInfo) {
        var savedState = {
            page: tabla.page(),
            order: tabla.order(),
            search: tabla.search()
        };

        $.post('productos_costos_ajax.php', {
            accion: 'ejecutar_accion',
            producto_costo_id: id,
            accion_js: accionJs,
            empresa_idx: empresa_idx,
            pagina_id: pagina_id
        }, function (res) {
            if (res.success) {
                tabla.ajax.reload(function (json) {
                    if (savedState.page !== undefined) {
                        tabla.page(savedState.page).draw('page');
                    }
                    if (savedState.search && savedState.search !== '') {
                        tabla.search(savedState.search).draw();
                    }

                    Swal.fire({
                        icon: "success",
                        title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                        text: res.message || `Costo "${productoInfo}" actualizado correctamente`,
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });
                }, false);
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: res.error || `Error al ${accionJs} el costo`,
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

    // ========== GUARDAR ==========
    $('#btnGuardar').click(function() {
        var form = document.getElementById('formProductoCostos');
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }
        
        var id = $('#producto_costo_id').val();
        var accionBackend = id ? 'editar' : 'agregar';
        
        var btnGuardar = $(this);
        var originalText = btnGuardar.html();
        btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');
        
        var formData = new FormData();
        formData.append('accion', accionBackend);
        formData.append('empresa_idx', empresa_idx);
        formData.append('pagina_id', pagina_id);
        formData.append('producto_costo_id', $('#producto_costo_id').val() || '');
        formData.append('producto_id', $('#producto_id').val() || '');
        formData.append('moneda_id', $('#moneda_id').val() || '');
        formData.append('costo_actual', $('#costo_actual').val() || '0');
        formData.append('producto_costo_origen_id', $('#producto_costo_origen_id').val() || '');
        formData.append('comprobante_id', $('#comprobante_id').val() || '');
        formData.append('f_actualizacion', $('#f_actualizacion').val() || '');
        formData.append('observaciones', $('#observaciones').val() || '');
        
        var savedState = {
            page: tabla ? tabla.page() : 0,
            order: tabla ? tabla.order() : [[1, 'asc']],
            search: tabla ? tabla.search() : ''
        };
        
        $.ajax({
            url: 'productos_costos_ajax.php',
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
                        text: "Producto costo guardado correctamente",
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });
                    
                    var modalEl = document.getElementById('modalProductoCostos');
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

    // Pantalla completa
    $('#btnToggleFullscreen').click(function() {
        var modalDialog = $('#modalProductoCostos .modal-dialog');
        var btnIcon = $(this).find('i');
        
        if (modalDialog.hasClass('modal-fullscreen')) {
            modalDialog.removeClass('modal-fullscreen');
            btnIcon.removeClass('fa-compress').addClass('fa-expand');
        } else {
            modalDialog.addClass('modal-fullscreen');
            btnIcon.removeClass('fa-expand').addClass('fa-compress');
        }
    });

    // ========== INICIALIZACIÓN ==========
    inicializarDataTable();
    cargarBotonAgregar();

    $('[title]').tooltip({
        trigger: 'hover',
        placement: 'top'
    });
});