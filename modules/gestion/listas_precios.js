$(document).ready(function () {
    var tabla;
    var tablaReglas;
    var tablaProductosLista;
    var currentPage = 0;
    var currentOrder = [[1, 'asc']];
    var currentSearch = '';
    
    // ========== FUNCIONES DE DATATABLE ==========
    function inicializarDataTable() {
        if ($.fn.DataTable.isDataTable('#tablaListasPrecios')) {
            $('#tablaListasPrecios').DataTable().destroy();
            $('#tablaListasPrecios tbody').empty();
        }

        tabla = $('#tablaListasPrecios').DataTable({
            ajax: {
                url: 'listas_precios_ajax.php',
                type: 'GET',
                data: {
                    accion: 'listar',
                    empresa_idx: empresa_id,
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
                    title: 'Listas de Precios',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7, 8],
                        orthogonal: 'export'
                    }
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> PDF',
                    className: 'btn btn-danger btn-sm',
                    title: 'Listas de Precios',
                    orientation: 'landscape',
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
                    title: 'Listas_Precios',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print"></i> Imprimir',
                    className: 'btn btn-secondary btn-sm',
                    title: 'Listas de Precios',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7, 8],
                        stripHtml: false
                    }
                }
            ],
            columns: [
                { data: 'lista_precio_id', className: 'text-center' },
                { 
                    data: 'lista_precio_codigo',
                    render: function(data, type, row) {
                        if (type === 'export') return data || '';
                        return data || '<span class="text-muted">-</span>';
                    }
                },
                { 
                    data: 'lista_precio_nombre',
                    render: function(data, type, row) {
                        if (type === 'export') return data || '';
                        return `<div>${escapeHtml(data || '')}</div>
                                <small class="text-muted">${escapeHtml(row.lista_precio_codigo || '')}</small>`;
                    }
                },
                { data: 'origen_nombre', className: 'text-center', defaultContent: '-' },
                { data: 'moneda_nombre', className: 'text-center', defaultContent: '-' },
                { 
                    data: null,
                    render: function(data, type, row) {
                        if (type === 'export') return row.lista_base_nombre || '-';
                        return row.lista_base_nombre ? `${escapeHtml(row.lista_base_nombre)}<br><small class="text-muted">${escapeHtml(row.lista_base_codigo || '')}</small>` : '-';
                    }
                },
                { 
                    data: 'requiere_recalculo',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (type === 'export') return data ? 'Sí' : 'No';
                        return data ? '<span class="badge bg-warning">Requiere</span>' : '<span class="badge bg-secondary">No</span>';
                    }
                },
                { 
                    data: 'f_ultimo_recalculo',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (type === 'export') return data || '';
                        if (!data) return '-';
                        return formatDateTime(data);
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
                        return `<span class="badge ${data.bg_clase || 'bg-secondary'}">${escapeHtml(estado)}</span>`;
                    }
                },
                { 
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    width: '150px',
                    render: function(data, type, row) {
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
                                var listaInfo = `${escapeHtml(row.lista_precio_codigo || '')} - ${escapeHtml(row.lista_precio_nombre || '')}`;
                                
                                botonesDinamicos += `<button type="button" class="btn ${claseBoton} btn-accion-dinamico" 
                                                            title="${escapeHtml(titulo)}" 
                                                            data-id="${row.lista_precio_id}" 
                                                            data-accion="${accionJs}"
                                                            data-confirmable="${esConfirmable}"
                                                            data-lista="${escapeHtml(listaInfo)}">
                                                            ${icono}
                                                        </button>`;
                            });
                        }
                        
                        return `<div class="btn-group" role="group">${botonesDinamicos}</div>`;
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
                }).container().appendTo($('#tablaListasPrecios_wrapper .col-md-6:eq(1)'));

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

    function inicializarDataTableReglas() {
        if ($.fn.DataTable.isDataTable('#tablaReglas')) {
            $('#tablaReglas').DataTable().destroy();
            $('#tablaReglas tbody').empty();
        }

        tablaReglas = $('#tablaReglas').DataTable({
            ajax: {
                url: 'listas_precios_ajax.php',
                type: 'GET',
                data: function(d) {
                    d.accion = 'listar_reglas';
                    d.empresa_idx = empresa_id;
                    d.pagina_id = pagina_id;
                    d.lista_precio_id = $('#lista_precio_id').val();
                },
                dataSrc: ''
            },
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>' +
                '<"clear">',
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            columns: [
                { data: 'lista_precio_regla_id', className: 'text-center' },
                { data: 'regla_nombre' },
                { data: 'regla_valor_tipo_nombre', className: 'text-center', defaultContent: '-' },
                { 
                    data: 'valor_ajuste',
                    className: 'text-end',
                    render: function(data, type, row) {
                        if (type === 'export') return parseFloat(data).toFixed(6);
                        return formatNumber(data, 6);
                    }
                },
                { data: 'prioridad', className: 'text-center' },
                { 
                    data: 'f_desde',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (type === 'export') return data || '';
                        return formatDateYMD(data);
                    }
                },
                { 
                    data: 'f_hasta',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (type === 'export') return data || '';
                        return formatDateYMD(data);
                    }
                },
                { 
                    data: 'es_promocion',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (type === 'export') return data ? 'Sí' : 'No';
                        return data ? '<span class="badge bg-success">Promoción</span>' : '<span class="badge bg-secondary">Normal</span>';
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
                        return `<span class="badge ${data.bg_clase || 'bg-secondary'}">${escapeHtml(estado)}</span>`;
                    }
                },
                { 
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    width: '120px',
                    render: function(data, type, row) {
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
                                var reglaInfo = `${escapeHtml(row.regla_nombre || '')}`;
                                
                                botonesDinamicos += `<button type="button" class="btn ${claseBoton} btn-accion-regla" 
                                                            title="${escapeHtml(titulo)}" 
                                                            data-id="${row.lista_precio_regla_id}" 
                                                            data-accion="${accionJs}"
                                                            data-confirmable="${esConfirmable}"
                                                            data-regla="${escapeHtml(reglaInfo)}">
                                                            ${icono}
                                                        </button>`;
                            });
                        }
                        
                        var botonEditar = `<button type="button" class="btn btn-sm btn-warning me-1 btn-editar-regla" 
                                                    title="Editar Regla" 
                                                    data-id="${row.lista_precio_regla_id}">
                                                    <i class="fas fa-edit"></i>
                                                </button>`;
                        
                        return `<div class="btn-group" role="group">${botonEditar}${botonesDinamicos}</div>`;
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json'                
            },
            responsive: true
        });
    }

    function inicializarDataTableProductos() {
        if ($.fn.DataTable.isDataTable('#tablaProductosLista')) {
            $('#tablaProductosLista').DataTable().destroy();
            $('#tablaProductosLista tbody').empty();
        }

        tablaProductosLista = $('#tablaProductosLista').DataTable({
            ajax: {
                url: 'listas_precios_ajax.php',
                type: 'GET',
                data: function(d) {
                    d.accion = 'obtener_productos_lista';
                    d.empresa_idx = empresa_id;
                    d.lista_precio_id = $('#lista_precio_id').val();
                },
                dataSrc: ''
            },
            dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                '<"row"<"col-sm-12"tr>>' +
                '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>' +
                '<"clear">',
            pageLength: 25,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            columns: [
                { data: 'lista_precio_producto_id', className: 'text-center' },
                { data: 'producto_codigo', defaultContent: '-' },
                { data: 'producto_nombre' },
                { 
                    data: 'precio_origen',
                    className: 'text-end',
                    render: function(data, type, row) {
                        return formatNumber(data, 2);  // Cambiado a 2 decimales
                    }
                },
                { 
                    data: 'porcentaje_general_aplicado',
                    className: 'text-end',
                    render: function(data, type, row) {
                        return formatNumber(data, 2) + '%';  // Cambiado a 2 decimales
                    }
                },
                { 
                    data: 'importe_general_aplicado',
                    className: 'text-end',
                    render: function(data, type, row) {
                        return formatNumber(data, 2);  // Cambiado a 2 decimales
                    }
                },
                { 
                    data: 'precio_final',
                    className: 'text-end',
                    render: function(data, type, row) {
                        if (row.es_manual == 1) {
                            return `<span class="text-warning fw-bold">${formatNumber(data, 2)} <i class="fas fa-hand-holding-usd" title="Precio manual"></i></span>`;
                        }
                        return formatNumber(data, 2);  // Cambiado a 2 decimales
                    }
                },
                { 
                    data: 'f_desde',
                    className: 'text-center',
                    render: function(data, type, row) {
                        return formatDateYMD(data);
                    }
                },
                { 
                    data: 'f_hasta',
                    className: 'text-center',
                    render: function(data, type, row) {
                        return formatDateYMD(data);
                    }
                },
                { 
                    data: 'estado_info',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (!data || !data.estado_registro) {
                            return '<span class="text-dark">Sin estado</span>';
                        }
                        return `<span class="badge ${data.bg_clase || 'bg-secondary'}">${escapeHtml(data.estado_registro)}</span>`;
                    }
                },
                { 
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    width: '80px',
                    render: function(data, type, row) {
                        return `<button type="button" class="btn btn-sm btn-warning btn-editar-precio-manual" 
                                        data-id="${row.lista_precio_producto_id}"
                                        data-producto="${escapeHtml(row.producto_nombre)}"
                                        data-precio="${row.precio_final}">
                                        <i class="fas fa-edit"></i>
                                    </button>`;
                    }
                }
            ],
            language: {
                url: '//cdn.datatables.net/plug-ins/2.1.8/i18n/es-ES.json'                
            },
            responsive: true
        });
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

        $('#btnRecalcularPrecios').off('click').on('click', function() {
            var lista_precio_id = $('#lista_precio_id').val();
            console.log("Recalcular precios para lista ID:", lista_precio_id); // Depuración
            
            if (!lista_precio_id || lista_precio_id == '') {
                Swal.fire({
                    icon: "warning",
                    title: "Guarde la lista primero",
                    text: "Debe guardar la lista de precios antes de recalcular precios",
                    confirmButtonText: "Entendido"
                });
                return;
            }
            
            Swal.fire({
                title: '¿Recalcular precios?',
                text: "Esta acción recalculará todos los precios de la lista aplicando las reglas activas",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, recalcular',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    console.log("Enviando solicitud de recálculo...");
                    $.post('listas_precios_ajax.php', {
                        accion: 'recalcular_precios',
                        lista_precio_id: lista_precio_id,
                        empresa_idx: empresa_id
                    }, function(res) {
                        console.log("Respuesta:", res);
                        if (res.success) {
                            Swal.fire({
                                icon: "success",
                                title: "Recalculado",
                                text: res.message,
                                showConfirmButton: false,
                                timer: 2000
                            });
                            if (tablaProductosLista) {
                                tablaProductosLista.ajax.reload();
                            }
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: res.error || "Error al recalcular precios",
                                confirmButtonText: "Entendido"
                            });
                        }
                    }, 'json').fail(function(xhr) {
                        console.error("Error AJAX:", xhr);
                        Swal.fire({
                            icon: "error",
                            title: "Error de conexión",
                            text: "No se pudo conectar con el servidor",
                            confirmButtonText: "Entendido"
                        });
                    });
                }
            });
        });
    }

    function cargarBotonAgregar() {
        $.get('listas_precios_ajax.php', {
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
                        ${icono}${escapeHtml(botonAgregar.nombre_funcion)}
                    </button>`
                );
            } else {
                $('#contenedor-boton-agregar').html(
                    '<button type="button" class="btn btn-primary" id="btnNuevo">' +
                    '<i class="fas fa-plus me-1"></i>Nueva Lista</button>'
                );
            }
        }, 'json');
    }

    function formatNumber(number, decimals = 2) {
        if (number === null || number === undefined || number === '') return '0.00';
        var num = parseFloat(number);
        if (isNaN(num)) return '0.00';
        return num.toLocaleString('es-AR', {
            minimumFractionDigits: 2,  // Cambiado de decimals a 2
            maximumFractionDigits: 2   // Cambiado de decimals a 2
        });
    }

    function formatDateYMD(dateStr) {
        if (!dateStr) return '';
        // Manejar fecha "0000-00-00"
        if (dateStr === '0000-00-00' || dateStr === '0000-00-00 00:00:00') return '';
        var parts = dateStr.split('-');
        if (parts.length === 3) {
            // Verificar que sea una fecha válida
            var year = parseInt(parts[0]);
            var month = parseInt(parts[1]);
            var day = parseInt(parts[2]);
            if (year > 0 && month > 0 && month <= 12 && day > 0 && day <= 31) {
                return `${parts[2]}/${parts[1]}/${parts[0]}`;
            }
        }
        return dateStr;
    }

    function formatDateTime(dateTimeStr) {
        if (!dateTimeStr) return '';
        if (dateTimeStr === '0000-00-00' || dateTimeStr === '0000-00-00 00:00:00') return '';
        var date = new Date(dateTimeStr);
        // Verificar si la fecha es válida
        if (isNaN(date.getTime())) return '';
        return date.toLocaleString('es-AR');
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) return '';
        // Convertir a string si es número u otro tipo
        var str = String(text);
        return str
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function cargarCombosFormulario() {
        $.get('listas_precios_ajax.php', { 
            accion: 'obtener_origenes'
        }, function(data) {
            var options = '<option value="">Seleccionar origen</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.lista_precio_origen_id}">${escapeHtml(item.lista_precio_origen_nombre)}</option>`;
                });
            }
            $('#lista_precio_origen_id').html(options);
        }, 'json');

        $.get('listas_precios_ajax.php', { 
            accion: 'obtener_monedas',
            empresa_idx: empresa_id
        }, function(data) {
            var options = '<option value="">Seleccionar moneda</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    var selected = item.es_moneda_base == 1 ? 'selected' : '';
                    options += `<option value="${item.moneda_id}" ${selected}>${escapeHtml(item.moneda)} (${escapeHtml(item.simbolo)})</option>`;
                });
            }
            $('#moneda_id').html(options);
        }, 'json');

        cargarListasBase();
    }

    function cargarListasBase(exclude_id) {
        var url = 'listas_precios_ajax.php?accion=obtener_listas_base&empresa_idx=' + empresa_id;
        if (exclude_id) {
            url += '&exclude_id=' + exclude_id;
        }
        $.get(url, function(data) {
            var options = '<option value="">Sin lista base</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.lista_precio_id}">${escapeHtml(item.lista_precio_codigo)} - ${escapeHtml(item.lista_precio_nombre)}</option>`;
                });
            }
            $('#lista_base_id').html(options);
        }, 'json');
    }

   function cargarCombosRegla(lista_precio_id) {
    // Cargar tipos de valor regla
    $.get('listas_precios_ajax.php', { 
        accion: 'obtener_tipos_valor_regla'
    }, function(data) {
        var options = '<option value="">Seleccionar tipo valor</option>';
        if (data && data.length > 0) {
            data.forEach(function(item) {
                // Asegurar que los valores se conviertan correctamente
                var id = item.lista_precio_regla_valor_tipo_id;
                var nombre = item.lista_precio_regla_valor_tipo_nombre || '';
                options += `<option value="${id}">${escapeHtml(nombre)}</option>`;
            });
        }
        $('#lista_precio_regla_valor_tipo_id').html(options);
    }, 'json').fail(function(xhr) {
        console.error('Error cargando tipos valor:', xhr.responseText);
    });

    // Cargar productos
    $.get('listas_precios_ajax.php', { 
        accion: 'obtener_productos',
        empresa_idx: empresa_id
    }, function(data) {
        var options = '<option value="">Todos los productos</option>';
        if (data && data.length > 0) {
            data.forEach(function(item) {
                var id = item.producto_id;
                var codigo = item.producto_codigo || '';
                var nombre = item.producto_nombre || '';
                options += `<option value="${id}">${escapeHtml(codigo)} - ${escapeHtml(nombre)}</option>`;
            });
        }
        $('#producto_id').html(options);
    }, 'json').fail(function(xhr) {
        console.error('Error cargando productos:', xhr.responseText);
    });

    // Cargar marcas
    $.get('listas_precios_ajax.php', { 
        accion: 'obtener_marcas',
        empresa_idx: empresa_id
    }, function(data) {
        var options = '<option value="">Todas las marcas</option>';
        if (data && data.length > 0) {
            data.forEach(function(item) {
                var id = item.marca_id;
                var nombre = item.marca_nombre || '';
                options += `<option value="${id}">${escapeHtml(nombre)}</option>`;
            });
        }
        $('#marca_id').html(options);
    }, 'json').fail(function(xhr) {
        console.error('Error cargando marcas:', xhr.responseText);
    });

    // Cargar categorías
    $.get('listas_precios_ajax.php', { 
        accion: 'obtener_categorias',
        empresa_idx: empresa_id
    }, function(data) {
        var options = '<option value="">Todas las categorías</option>';
        if (data && data.length > 0) {
            data.forEach(function(item) {
                var id = item.producto_categoria_id;
                var nombre = item.producto_categoria_nombre || '';
                options += `<option value="${id}">${escapeHtml(nombre)}</option>`;
            });
        }
        $('#producto_categoria_id').html(options);
    }, 'json').fail(function(xhr) {
        console.error('Error cargando categorías:', xhr.responseText);
    });

    // Cargar tipos de producto
    $.get('listas_precios_ajax.php', { 
        accion: 'obtener_tipos_producto',
        empresa_idx: empresa_id
    }, function(data) {
        var options = '<option value="">Todos los tipos</option>';
        if (data && data.length > 0) {
            data.forEach(function(item) {
                var id = item.producto_tipo_id;
                var nombre = item.producto_tipo_nombre || '';
                options += `<option value="${id}">${escapeHtml(nombre)}</option>`;
            });
        }
        $('#producto_tipo_id').html(options);
    }, 'json').fail(function(xhr) {
        console.error('Error cargando tipos producto:', xhr.responseText);
    });

    // Cargar entidades
    $.get('listas_precios_ajax.php', { 
        accion: 'obtener_entidades',
        empresa_idx: empresa_id
    }, function(data) {
        var options = '<option value="">Todas las entidades</option>';
        if (data && data.length > 0) {
            data.forEach(function(item) {
                var id = item.entidad_id;
                var nombre = item.entidad_nombre || '';
                var documento = item.entidad_nro_documento || '';
                options += `<option value="${id}">${escapeHtml(nombre)} (${escapeHtml(documento)})</option>`;
            });
        }
        $('#entidad_id').html(options);
    }, 'json').fail(function(xhr) {
        console.error('Error cargando entidades:', xhr.responseText);
    });
}

    function cargarModelosPorMarca(marca_id) {
        if (!marca_id) {
            $('#modelo_id').html('<option value="">Seleccione una marca</option>');
            $('#submodelo_id').html('<option value="">Seleccione un modelo primero</option>');
            return;
        }
        
        $.get('listas_precios_ajax.php', { 
            accion: 'obtener_modelos_por_marca',
            empresa_idx: empresa_id,
            marca_id: marca_id
        }, function(data) {
            var options = '<option value="">Todos los modelos</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.modelo_id}">${escapeHtml(item.modelo_nombre)}</option>`;
                });
            }
            $('#modelo_id').html(options);
            $('#submodelo_id').html('<option value="">Seleccione un modelo primero</option>');
        }, 'json');
    }

    // Agregar función para cargar submodelos por modelo
    function cargarSubmodelosPorModelo(modelo_id) {
        if (!modelo_id) {
            $('#submodelo_id').html('<option value="">Seleccione un modelo</option>');
            return;
        }
        
        $.get('listas_precios_ajax.php', { 
            accion: 'obtener_submodelos_por_modelo',
            empresa_idx: empresa_id,
            modelo_id: modelo_id
        }, function(data) {
            var options = '<option value="">Todos los submodelos</option>';
            if (data && data.length > 0) {
                data.forEach(function(item) {
                    options += `<option value="${item.submodelo_id}">${escapeHtml(item.submodelo_nombre)}</option>`;
                });
            }
            $('#submodelo_id').html(options);
        }, 'json');
    }

    function resetModal() {
        $('#formListaPrecio')[0].reset();
        $('#lista_precio_id').val('');
        $('#formListaPrecio').removeClass('was-validated');
        $('#requiere_recalculo').prop('checked', false);
        $('#f_ultimo_recalculo').val('');
    }

    function resetModalRegla() {
        $('#formRegla')[0].reset();
        $('#lista_precio_regla_id').val('');
        $('#formRegla').removeClass('was-validated');
        $('#es_promocion').prop('checked', false);
        $('#permite_acumulacion').prop('checked', false);
        $('#prioridad').val(100);
        
        // Fecha actual en formato YYYY-MM-DD
        var today = new Date();
        var year = today.getFullYear();
        var month = String(today.getMonth() + 1).padStart(2, '0');
        var day = String(today.getDate()).padStart(2, '0');
        $('#f_desde').val(year + '-' + month + '-' + day);
        $('#f_hasta').val('');
        
        $('#producto_id').val('');
        $('#marca_id').val('');
        $('#modelo_id').html('<option value="">Seleccione una marca primero</option>');
        $('#submodelo_id').html('<option value="">Seleccione un modelo primero</option>');
        $('#producto_categoria_id').val('');
        $('#producto_tipo_id').val('');
        $('#entidad_id').val('');
    }

    $(document).on('click', '#btnNuevo', function () {
        resetModal();
        $('#modalLabel').text('Nueva Lista de Precio');
        cargarCombosFormulario();

        var modal = new bootstrap.Modal(document.getElementById('modalListaPrecio'));
        modal.show();
        
        // Limpiar tabs internas
        $('#reglasContent').empty();
        $('#productosContent').empty();
    });

    // Eventos para cascada de marca -> modelo -> submodelo
        $(document).on('change', '#marca_id', function() {
            var marca_id = $(this).val();
            cargarModelosPorMarca(marca_id);
        });

        $(document).on('change', '#modelo_id', function() {
            var modelo_id = $(this).val();
            cargarSubmodelosPorModelo(modelo_id);
        });

    $(document).on('click', '#btnNuevaRegla', function () {
        var lista_precio_id = $('#lista_precio_id').val();
        
        if (!lista_precio_id || lista_precio_id == '') {
            Swal.fire({
                icon: "warning",
                title: "Guarde la lista primero",
                text: "Debe guardar la lista de precios antes de agregar reglas",
                confirmButtonText: "Entendido"
            });
            return;
        }
        
        resetModalRegla();
        $('#reglaModalLabel').text('Nueva Regla');
        $('#regla_lista_precio_id').val(lista_precio_id);
        cargarCombosRegla(lista_precio_id);

        var modal = new bootstrap.Modal(document.getElementById('modalRegla'));
        modal.show();
    });

    $(document).on('click', '.btn-editar-regla', function() {
        var id = $(this).data('id');
        
        $.get('listas_precios_ajax.php', {
            accion: 'obtener_regla',
            lista_precio_regla_id: id,
            empresa_idx: empresa_id
        }, function(res) {
            if (res && res.lista_precio_regla_id) {
                resetModalRegla();
                cargarCombosRegla(res.lista_precio_id);
                
                setTimeout(function() {
                    $('#lista_precio_regla_id').val(res.lista_precio_regla_id);
                    $('#regla_lista_precio_id').val(res.lista_precio_id);
                    $('#regla_nombre').val(res.regla_nombre || '');
                    $('#regla_descripcion').val(res.descripcion || '');
                    $('#lista_precio_regla_valor_tipo_id').val(res.lista_precio_regla_valor_tipo_id);
                    $('#valor_ajuste').val(res.valor_ajuste);
                    $('#prioridad').val(res.prioridad || 100);
                    
                   // Manejar fechas inválidas
                    var fDesde = (res.f_desde && res.f_desde !== '0000-00-00') ? res.f_desde : '';
                    var fHasta = (res.f_hasta && res.f_hasta !== '0000-00-00') ? res.f_hasta : '';
                    $('#f_desde').val(fDesde);  // Esto debería funcionar
                    $('#f_hasta').val(fHasta);
                    
                    if (res.es_promocion == 1) $('#es_promocion').prop('checked', true);
                    if (res.permite_acumulacion == 1) $('#permite_acumulacion').prop('checked', true);
                    
                    if (res.producto_id) $('#producto_id').val(res.producto_id);
                    if (res.marca_id) {
                        $('#marca_id').val(res.marca_id);
                        cargarModelosPorMarca(res.marca_id);
                        setTimeout(function() {
                            if (res.modelo_id) {
                                $('#modelo_id').val(res.modelo_id);
                                cargarSubmodelosPorModelo(res.modelo_id);
                                setTimeout(function() {
                                    if (res.submodelo_id) $('#submodelo_id').val(res.submodelo_id);
                                }, 300);
                            }
                        }, 300);
                    }
                    if (res.producto_categoria_id) $('#producto_categoria_id').val(res.producto_categoria_id);
                    if (res.producto_tipo_id) $('#producto_tipo_id').val(res.producto_tipo_id);
                    if (res.entidad_id) $('#entidad_id').val(res.entidad_id);
                    
                    $('#reglaModalLabel').text('Editar Regla');
                    
                    var modal = new bootstrap.Modal(document.getElementById('modalRegla'));
                    modal.show();
                }, 500);
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos de la regla",
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json').fail(function(xhr) {
            console.error('Error al obtener regla:', xhr.responseText);
            Swal.fire({
                icon: "error",
                title: "Error",
                text: "Error al cargar los datos de la regla",
                confirmButtonText: "Entendido"
            });
        });
    });

    $(document).on('click', '.btn-editar-precio-manual', function() {
        var id = $(this).data('id');
        var productoNombre = $(this).data('producto');
        var precioActual = $(this).data('precio');
        
        $('#precio_manual_lista_precio_producto_id').val(id);
        $('#precio_manual_producto_nombre').val(productoNombre);
        $('#precio_actual_mostrar').val(formatNumber(precioActual, 6));
        $('#precio_manual').val('');
        $('#precio_manual_observaciones').val('');
        
        var modal = new bootstrap.Modal(document.getElementById('modalPrecioManual'));
        modal.show();
    });

    $('#btnGuardarPrecioManual').click(function() {
        var id = $('#precio_manual_lista_precio_producto_id').val();
        var precioManual = $('#precio_manual').val();
        var observaciones = $('#precio_manual_observaciones').val();
        
        if (!precioManual || parseFloat(precioManual) <= 0) {
            Swal.fire({
                icon: "error",
                title: "Error",
                text: "Ingrese un precio manual válido",
                confirmButtonText: "Entendido"
            });
            return;
        }
        
        $.post('listas_precios_ajax.php', {
            accion: 'actualizar_precio_manual',
            lista_precio_producto_id: id,
            precio_manual: precioManual,
            observaciones: observaciones,
            empresa_idx: empresa_id
        }, function(res) {
            if (res.success) {
                Swal.fire({
                    icon: "success",
                    title: "Actualizado",
                    text: res.message,
                    showConfirmButton: false,
                    timer: 1500
                });
                var modal = bootstrap.Modal.getInstance(document.getElementById('modalPrecioManual'));
                modal.hide();
                if (tablaProductosLista) {
                    tablaProductosLista.ajax.reload();
                }
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: res.error || "Error al actualizar precio",
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json');
    });

    $(document).on('click', '.btn-accion-dinamico', function () {
        var id = $(this).data('id');
        var accionJs = $(this).data('accion');
        var confirmable = $(this).data('confirmable');
        var listaInfo = $(this).data('lista') || 'Lista #' + id;

        if (accionJs === 'editar') {
            cargarDatosParaEditar(id);
        } else if (accionJs === 'visualizar') {
            cargarDatosParaVisualizar(id);
        } else if (confirmable == 1) {
            Swal.fire({
                title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                html: `¿Está seguro de <strong>${accionJs}</strong> la lista de precios<br>
                    <strong>${escapeHtml(listaInfo)}</strong>?`,
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
                    ejecutarAccion(id, accionJs, listaInfo);
                }
            });
        } else {
            ejecutarAccion(id, accionJs, listaInfo);
        }
    });

    $(document).on('click', '.btn-accion-regla', function () {
        var id = $(this).data('id');
        var accionJs = $(this).data('accion');
        var confirmable = $(this).data('confirmable');
        var reglaInfo = $(this).data('regla') || 'Regla #' + id;

        if (confirmable == 1) {
            Swal.fire({
                title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                html: `¿Está seguro de <strong>${accionJs}</strong> la regla<br>
                    <strong>${escapeHtml(reglaInfo)}</strong>?`,
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
                    ejecutarAccionRegla(id, accionJs, reglaInfo);
                }
            });
        } else {
            ejecutarAccionRegla(id, accionJs, reglaInfo);
        }
    });

    function cargarDatosParaEditar(id) {
        $.get('listas_precios_ajax.php', {
            accion: 'obtener',
            lista_precio_id: id,
            empresa_idx: empresa_id
        }, function (res) {
            if (res && res.lista_precio_id) {
                resetModal();
                cargarCombosFormulario();
                cargarListasBase(id);

                setTimeout(function() {
                    $('#lista_precio_id').val(res.lista_precio_id);
                    $('#lista_precio_codigo').val(res.lista_precio_codigo);
                    $('#lista_precio_nombre').val(res.lista_precio_nombre);
                    $('#descripcion').val(res.descripcion || '');
                    $('#lista_precio_origen_id').val(res.lista_precio_origen_id);
                    $('#moneda_id').val(res.moneda_id || '');
                    $('#lista_base_id').val(res.lista_base_id || '');
                    $('#observaciones').val(res.observaciones || '');
                    if (res.requiere_recalculo == 1) $('#requiere_recalculo').prop('checked', true);
                    $('#f_ultimo_recalculo').val(res.f_ultimo_recalculo || '');
                    $('#modalLabel').text('Editar Lista de Precio');
                    
                    // Inicializar tabs internas después de cargar datos
                    inicializarDataTableReglas();
                    inicializarDataTableProductos();
                }, 500);

                var modal = new bootstrap.Modal(document.getElementById('modalListaPrecio'));
                modal.show();
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos de la lista",
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json');
    }

    function cargarDatosParaVisualizar(id) {
        $.get('listas_precios_ajax.php', {
            accion: 'obtener',
            lista_precio_id: id,
            empresa_idx: empresa_id
        }, function (res) {
            if (res && res.lista_precio_id) {
                resetModal();
                cargarCombosFormulario();

                setTimeout(function() {
                    $('#lista_precio_id').val(res.lista_precio_id);
                    $('#lista_precio_codigo').val(res.lista_precio_codigo);
                    $('#lista_precio_nombre').val(res.lista_precio_nombre);
                    $('#descripcion').val(res.descripcion || '');
                    $('#lista_precio_origen_id').val(res.lista_precio_origen_id);
                    $('#moneda_id').val(res.moneda_id || '');
                    $('#lista_base_id').val(res.lista_base_id || '');
                    $('#observaciones').val(res.observaciones || '');
                    if (res.requiere_recalculo == 1) $('#requiere_recalculo').prop('checked', true);
                    $('#f_ultimo_recalculo').val(res.f_ultimo_recalculo || '');
                    $('#modalLabel').text('Visualizar Lista de Precio');

                    $('#formListaPrecio :input').prop('disabled', true);
                    $('#btnGuardar').hide();
                    $('.modal-footer .btn-secondary').hide();
                    
                    inicializarDataTableReglas();
                    inicializarDataTableProductos();
                }, 500);

                var modal = new bootstrap.Modal(document.getElementById('modalListaPrecio'));
                modal.show();

                $('#modalListaPrecio').off('hidden.bs.modal').on('hidden.bs.modal', function () {
                    $('#formListaPrecio :input').prop('disabled', false);
                    $('#btnGuardar').show();
                    $('.modal-footer .btn-secondary').show();
                });
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos de la lista",
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json');
    }

    function ejecutarAccion(id, accionJs, listaInfo) {
        var savedState = {
            page: tabla.page(),
            order: tabla.order(),
            search: tabla.search()
        };

        $.post('listas_precios_ajax.php', {
            accion: 'ejecutar_accion',
            lista_precio_id: id,
            accion_js: accionJs,
            empresa_idx: empresa_id,
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
                        text: res.message || `Lista "${listaInfo}" actualizada correctamente`,
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
                    text: res.error || `Error al ${accionJs} la lista`,
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

    function ejecutarAccionRegla(id, accionJs, reglaInfo) {
        $.post('listas_precios_ajax.php', {
            accion: 'ejecutar_accion_regla',
            regla_id: id,
            accion_js: accionJs,
            empresa_idx: empresa_id,
            pagina_id: pagina_id
        }, function (res) {
            if (res.success) {
                if (tablaReglas) {
                    tablaReglas.ajax.reload();
                }
                if (tablaProductosLista) {
                    tablaProductosLista.ajax.reload();
                }

                Swal.fire({
                    icon: "success",
                    title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                    text: res.message || `Regla "${reglaInfo}" actualizada correctamente`,
                    showConfirmButton: false,
                    timer: 1500,
                    toast: true,
                    position: 'top-end'
                });
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: res.error || `Error al ${accionJs} la regla`,
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
            console.error('Error en ejecutarAccionRegla:', xhr.responseText);
        });
    }

    // ========== GUARDAR ==========
    $('#btnGuardar').click(function() {
        var form = document.getElementById('formListaPrecio');
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }
        
        var id = $('#lista_precio_id').val();
        var accionBackend = id ? 'editar' : 'agregar';
        
        var btnGuardar = $(this);
        var originalText = btnGuardar.html();
        btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');
        
        var formData = new FormData();
        formData.append('accion', accionBackend);
        formData.append('empresa_idx', empresa_id);
        formData.append('pagina_id', pagina_id);
        formData.append('lista_precio_id', $('#lista_precio_id').val() || '');
        formData.append('lista_precio_codigo', $('#lista_precio_codigo').val() || '');
        formData.append('lista_precio_nombre', $('#lista_precio_nombre').val() || '');
        formData.append('descripcion', $('#descripcion').val() || '');
        formData.append('lista_precio_origen_id', $('#lista_precio_origen_id').val() || '');
        formData.append('lista_base_id', $('#lista_base_id').val() || '');
        formData.append('moneda_id', $('#moneda_id').val() || '');
        if ($('#requiere_recalculo').is(':checked')) formData.append('requiere_recalculo', '1');
        formData.append('f_ultimo_recalculo', $('#f_ultimo_recalculo').val() || '');
        formData.append('observaciones', $('#observaciones').val() || '');
        
        var savedState = {
            page: tabla ? tabla.page() : 0,
            order: tabla ? tabla.order() : [[1, 'asc']],
            search: tabla ? tabla.search() : ''
        };
        
        $.ajax({
            url: 'listas_precios_ajax.php',
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
                    
                    cargarListasBase();
                    
                    // Actualizar el ID en el campo oculto si es nuevo
                    if (res.lista_precio_id) {
                        $('#lista_precio_id').val(res.lista_precio_id);
                        // Inicializar tabs después de guardar nueva lista
                        inicializarDataTableReglas();
                        inicializarDataTableProductos();
                    }
                    
                    Swal.fire({
                        icon: "success",
                        title: "¡Guardado!",
                        text: res.message || "Lista de precio guardada correctamente",
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });
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
                Swal.fire({
                    icon: "error",
                    title: "Error de conexión",
                    text: "Error al comunicarse con el servidor",
                    confirmButtonText: "Entendido"
                });
            }
        });
    });

    $('#btnGuardarRegla').click(function() {
        var form = document.getElementById('formRegla');
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }
        
        var id = $('#lista_precio_regla_id').val();
        var accionBackend = id ? 'editar_regla' : 'agregar_regla';
        
        var btnGuardar = $(this);
        var originalText = btnGuardar.html();
        btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');
        
        var formData = new FormData();
        formData.append('accion', accionBackend);
        formData.append('empresa_idx', empresa_id);
        formData.append('lista_precio_regla_id', $('#lista_precio_regla_id').val() || '');
        formData.append('lista_precio_id', $('#regla_lista_precio_id').val() || '');
        formData.append('regla_nombre', $('#regla_nombre').val() || '');
        formData.append('descripcion', $('#regla_descripcion').val() || '');
        formData.append('lista_precio_regla_tipo_id', $('#lista_precio_regla_tipo_id').val() || '');
        formData.append('lista_precio_regla_valor_tipo_id', $('#lista_precio_regla_valor_tipo_id').val() || '');
        formData.append('valor_ajuste', $('#valor_ajuste').val() || '0');
        formData.append('producto_id', $('#producto_id').val() || '');
        formData.append('producto_marca_id', $('#producto_marca_id').val() || '');
        formData.append('producto_categoria_id', $('#producto_categoria_id').val() || '');
        formData.append('producto_grupo_id', $('#producto_grupo_id').val() || '');
        formData.append('entidad_id', $('#entidad_id').val() || '');
        formData.append('prioridad', $('#prioridad').val() || '100');
        formData.append('f_desde', $('#f_desde').val() || '');
        formData.append('f_hasta', $('#f_hasta').val() || '');
        if ($('#es_promocion').is(':checked')) formData.append('es_promocion', '1');
        if ($('#permite_acumulacion').is(':checked')) formData.append('permite_acumulacion', '1');
        
        $.ajax({
            url: 'listas_precios_ajax.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                btnGuardar.prop('disabled', false).html(originalText);
                
                if (res.resultado) {
                    if (tablaReglas) {
                        tablaReglas.ajax.reload();
                    }
                    if (tablaProductosLista) {
                        tablaProductosLista.ajax.reload();
                    }
                    
                    Swal.fire({
                        icon: "success",
                        title: "¡Guardado!",
                        text: res.message || "Regla guardada correctamente",
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });
                    
                    var modalEl = document.getElementById('modalRegla');
                    var modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();
                    
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
        var modalDialog = $('#modalListaPrecio .modal-dialog');
        var btnIcon = $(this).find('i');
        if (modalDialog.hasClass('modal-fullscreen')) {
            modalDialog.removeClass('modal-fullscreen');
            btnIcon.removeClass('fa-compress').addClass('fa-expand');
        } else {
            modalDialog.addClass('modal-fullscreen');
            btnIcon.removeClass('fa-expand').addClass('fa-compress');
        }
    });

    $('#btnToggleFullscreenRegla').click(function() {
        var modalDialog = $('#modalRegla .modal-dialog');
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