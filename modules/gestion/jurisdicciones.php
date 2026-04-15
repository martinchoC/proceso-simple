<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Jurisdicciones";
$currentPage = 'jurisdicciones';
$modudo_idx = 2;
$pagina_idx = 74; // ID de página para Jurisdicciones

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-map-marker-alt me-2"></i>Jurisdicciones
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Gestión</a></li>
                        <li class="breadcrumb-item"><a href="#">Configuración</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Jurisdicciones</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">

            <div class="content-wrapper">
                <section class="content">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <div id="contenedor-boton-agregar" class="d-inline"></div>
                                        <div class="float-end">
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                                    id="btnRecargar" title="Recargar tabla">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-success dropdown-toggle"
                                                    data-bs-toggle="dropdown" aria-expanded="false"
                                                    title="Exportar datos">
                                                    <i class="fas fa-file-export"></i> Exportar
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="#" id="btnExportarExcel"><i
                                                                class="fas fa-file-excel text-success"></i> Excel</a>
                                                    </li>
                                                    <li><a class="dropdown-item" href="#" id="btnExportarPDF"><i
                                                                class="fas fa-file-pdf text-danger"></i> PDF</a></li>
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                    <li><a class="dropdown-item" href="#" id="btnExportarCSV"><i
                                                                class="fas fa-file-csv text-primary"></i> CSV</a></li>
                                                    <li><a class="dropdown-item" href="#" id="btnExportarPrint"><i
                                                                class="fas fa-print text-secondary"></i> Imprimir</a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <!-- DataTable -->
                                        <table id="tablaJurisdicciones" class="table table-striped table-bordered"
                                            style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="50">ID</th>
                                                    <th width="100">Código</th>
                                                    <th width="200">Nombre</th>
                                                    <th width="120">Tipo</th>
                                                    <th width="120">País</th>
                                                    <th width="120">Provincia</th>
                                                    <th width="120">Localidad</th>
                                                    <th width="150">Organismo Recaudador</th>
                                                    <th width="80" class="text-center">Requiere Padrón</th>
                                                    <th width="100">Estado</th>
                                                    <th width="150" class="text-center">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Modal para crear/editar Jurisdicción -->
            <div class="modal fade" id="modalJurisdiccion" tabindex="-1" aria-labelledby="modalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Jurisdicción</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formJurisdiccion" class="needs-validation" novalidate>
                                <input type="hidden" id="jurisdiccion_id" name="jurisdiccion_id" />
                                
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="jurisdiccion_codigo" class="form-label">Código *</label>
                                        <input type="text" class="form-control" id="jurisdiccion_codigo" 
                                            name="jurisdiccion_codigo" maxlength="10" required>
                                        <div class="invalid-feedback">El código es obligatorio (máx. 10 caracteres)</div>
                                    </div>
                                    
                                    <div class="col-md-8 mb-3">
                                        <label for="jurisdiccion_nombre" class="form-label">Nombre *</label>
                                        <input type="text" class="form-control" id="jurisdiccion_nombre" 
                                            name="jurisdiccion_nombre" maxlength="100" required>
                                        <div class="invalid-feedback">El nombre es obligatorio (máx. 100 caracteres)</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="jurisdiccion_tipo_id" class="form-label">Tipo de Jurisdicción *</label>
                                        <select class="form-select" id="jurisdiccion_tipo_id" name="jurisdiccion_tipo_id" required>
                                            <option value="">Seleccione un tipo...</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione un tipo de jurisdicción</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="pais_id" class="form-label">País</label>
                                        <select class="form-select" id="pais_id" name="pais_id">
                                            <option value="">Seleccione un país...</option>
                                        </select>
                                        <div class="form-text">Opcional</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="provincia_id" class="form-label">Provincia</label>
                                        <select class="form-select" id="provincia_id" name="provincia_id">
                                            <option value="">Seleccione una provincia...</option>
                                        </select>
                                        <div class="form-text">Opcional</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="localidad_id" class="form-label">Localidad</label>
                                        <select class="form-select" id="localidad_id" name="localidad_id">
                                            <option value="">Seleccione una localidad...</option>
                                        </select>
                                        <div class="form-text">Opcional</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label for="organismo_recaudador" class="form-label">Organismo Recaudador</label>
                                        <input type="text" class="form-control" id="organismo_recaudador" 
                                            name="organismo_recaudador" maxlength="100">
                                        <div class="form-text">Opcional</div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="codigo_externo" class="form-label">Código Externo</label>
                                        <input type="text" class="form-control" id="codigo_externo" 
                                            name="codigo_externo" maxlength="20">
                                        <div class="form-text">Opcional</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Configuración</label>
                                        <div class="border rounded p-3 bg-light">
                                            <div class="form-check form-switch mb-2">
                                                <input class="form-check-input" type="checkbox" 
                                                    id="requiere_padron" name="requiere_padron" value="1">
                                                <label class="form-check-label" for="requiere_padron">
                                                    <i class="fas fa-id-card text-primary me-1"></i>Requiere Padrón
                                                </label>
                                            </div>
                                            <div class="form-group mt-2">
                                                <label for="orden" class="form-label">Orden</label>
                                                <input type="number" class="form-control" id="orden" 
                                                    name="orden" value="1" min="1">
                                                <div class="form-text">Orden de visualización</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" id="btnGuardar">
                                <i class="fas fa-save me-1"></i>Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Estilos personalizados -->
    <style>
        .dt-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }

        .dt-button-collection .dropdown-menu {
            margin-top: 5px;
        }

        .dataTables_wrapper .dt-buttons {
            float: right;
            margin-top: 5px;
        }

        .dropdown-menu .dropdown-item i {
            width: 20px;
            text-align: center;
            margin-right: 8px;
        }

        .badge-tipo {
            font-size: 0.85em;
            padding: 4px 8px;
        }

        .form-check-input:checked {
            background-color: #0d6efd;
            border-color: #0d6efd;
        }
    </style>

    <script>
        $(document).ready(function () {
            // Variables de contexto
            const empresa_idx = <?php echo isset($_GET['empresa_id']) ? $_GET['empresa_id'] : 2; ?>;
            const pagina_idx = <?php echo $pagina_idx; ?>;

            // Variables para mantener el estado del DataTable
            var tabla;
            var currentPage = 0;
            var currentOrder = [[0, 'asc']];
            var currentSearch = '';
            
            function cargarTiposJurisdiccion() {
                $.ajax({
                    url: 'jurisdicciones_ajax.php',
                    type: 'GET',
                    data: {
                        accion: 'listar_tipos_jurisdiccion'
                    },
                    dataType: 'json',
                    success: function(response) {
                        var select = $('#jurisdiccion_tipo_id');
                        select.empty();
                        select.append('<option value="">Seleccione un tipo...</option>');
                        
                        if (response && response.length > 0) {
                            $.each(response, function(index, tipo) {
                                select.append('<option value="' + tipo.jurisdiccion_tipo_id + '">' + 
                                    escapeHtml(tipo.jurisdiccion_tipo) + '</option>');
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error al cargar tipos de jurisdicción:', error);
                    }
                });
            }

            function cargarPaises() {
                $.ajax({
                    url: 'jurisdicciones_ajax.php',
                    type: 'GET',
                    data: {
                        accion: 'listar_paises'
                    },
                    dataType: 'json',
                    success: function(response) {
                        var select = $('#pais_id');
                        select.empty();
                        select.append('<option value="">Seleccione un país...</option>');
                        
                        if (response && response.length > 0) {
                            $.each(response, function(index, pais) {
                                select.append('<option value="' + pais.pais_id + '">' + 
                                    escapeHtml(pais.pais) + '</option>');
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error al cargar países:', error);
                    }
                });
            }

            function cargarProvincias(pais_id) {
                $.ajax({
                    url: 'jurisdicciones_ajax.php',
                    type: 'GET',
                    data: {
                        accion: 'listar_provincias',
                        pais_id: pais_id
                    },
                    dataType: 'json',
                    success: function(response) {
                        var select = $('#provincia_id');
                        select.empty();
                        select.append('<option value="">Seleccione una provincia...</option>');
                        
                        if (response && response.length > 0) {
                            $.each(response, function(index, provincia) {
                                select.append('<option value="' + provincia.provincia_id + '">' + 
                                    escapeHtml(provincia.provincia) + '</option>');
                            });
                        }
                        
                        // Resetear localidades cuando cambia provincia
                        cargarLocalidades(null);
                    },
                    error: function(xhr, status, error) {
                        console.error('Error al cargar provincias:', error);
                    }
                });
            }

            function cargarLocalidades(provincia_id) {
                $.ajax({
                    url: 'jurisdicciones_ajax.php',
                    type: 'GET',
                    data: {
                        accion: 'listar_localidades',
                        provincia_id: provincia_id
                    },
                    dataType: 'json',
                    success: function(response) {
                        var select = $('#localidad_id');
                        select.empty();
                        select.append('<option value="">Seleccione una localidad...</option>');
                        
                        if (response && response.length > 0) {
                            $.each(response, function(index, localidad) {
                                select.append('<option value="' + localidad.localidad_id + '">' + 
                                    escapeHtml(localidad.localidad) + '</option>');
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error al cargar localidades:', error);
                    }
                });
            }

            // Evento para cargar provincias cuando cambia el país
            $('#pais_id').change(function() {
                var pais_id = $(this).val();
                if (pais_id) {
                    cargarProvincias(pais_id);
                } else {
                    $('#provincia_id').empty().append('<option value="">Seleccione una provincia...</option>');
                    $('#localidad_id').empty().append('<option value="">Seleccione una localidad...</option>');
                }
            });

            // Evento para cargar localidades cuando cambia la provincia
            $('#provincia_id').change(function() {
                var provincia_id = $(this).val();
                cargarLocalidades(provincia_id);
            });

            // Función para inicializar DataTable
            function inicializarDataTable() {
                if ($.fn.DataTable.isDataTable('#tablaJurisdicciones')) {
                    $('#tablaJurisdicciones').DataTable().destroy();
                    $('#tablaJurisdicciones tbody').empty();
                }

                tabla = $('#tablaJurisdicciones').DataTable({
                    ajax: {
                        url: 'jurisdicciones_ajax.php',
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
                            if (searchValue === '-1' || searchValue === '' || searchValue === '-1') {
                                currentSearch = '';
                            } else {
                                currentSearch = searchValue;
                            }
                        } else {
                            currentSearch = '';
                        }
                        
                        data.search = { search: currentSearch };
                    },
                    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                         '<"row"<"col-sm-12"tr>>' +
                         '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>' +
                         '<"clear">',
                    pageLength: 50,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                    columns: [
                        {
                            data: 'jurisdiccion_id',
                            className: 'text-center fw-bold'
                        },
                        {
                            data: 'jurisdiccion_codigo',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data;
                                }
                                return `<span class="badge bg-info">${escapeHtml(data)}</span>`;
                            }
                        },
                        {
                            data: 'jurisdiccion_nombre',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data;
                                }
                                return `<div class="fw-medium">${escapeHtml(data)}</div>`;
                            }
                        },
                        {
                            data: 'jurisdiccion_tipo',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data || '';
                                }
                                return data ? `<span class="badge bg-secondary">${escapeHtml(data)}</span>` : '<span class="text-muted">-</span>';
                            }
                        },
                        {
                            data: 'pais',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data || '';
                                }
                                return data ? escapeHtml(data) : '<span class="text-muted">-</span>';
                            }
                        },
                        {
                            data: 'provincia_nombre',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data || '';
                                }
                                return data ? escapeHtml(data) : '<span class="text-muted">-</span>';
                            }
                        },
                        {
                            data: 'localidad_nombre',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data || '';
                                }
                                return data ? escapeHtml(data) : '<span class="text-muted">-</span>';
                            }
                        },
                        {
                            data: 'organismo_recaudador',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data || '';
                                }
                                return data ? `<span class="text-info">${escapeHtml(data)}</span>` : '<span class="text-muted">-</span>';
                            }
                        },
                        {
                            data: 'requiere_padron',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data == 1 ? 'Sí' : 'No';
                                }
                                return data == 1 ? '<i class="fas fa-check-circle text-success fa-lg" title="Requiere padrón"></i>' : '<i class="fas fa-times-circle text-danger fa-lg" title="No requiere padrón"></i>';
                            }
                        },
                        {
                            data: 'estado_info',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (!data || !data.estado_registro) {
                                    if (type === 'export') {
                                        return 'Sin estado';
                                    }
                                    return '<span class="fw-medium">Sin estado</span>';
                                }

                                var estado = data.estado_registro;
                                var badgeClass = data.bg_clase ? data.bg_clase.replace('bg-', '') : 'secondary';

                                if (type === 'export') {
                                    return estado;
                                }

                                return `<span class="badge bg-${badgeClass}">${estado}</span>`;
                            }
                        },
                        {
                            data: 'botones',
                            orderable: false,
                            searchable: false,
                            className: "text-center",
                            width: '150px',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return '';
                                }

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

                                        var botonHtml = `<button type="button" class="btn ${claseBoton} btn-accion" 
                                       title="${titulo}" 
                                       data-id="${row.jurisdiccion_id}" 
                                       data-accion="${accionJs}"
                                       data-confirmable="${esConfirmable}"
                                       data-tipo="${escapeHtml(row.jurisdiccion_nombre)}">
                                    ${icono}
                                </button>`;

                                        if (accionJs === 'editar') {
                                            editarBoton = botonHtml;
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
                        url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
                    },
                    order: currentOrder,
                    responsive: true,
                    createdRow: function (row, data, dataIndex) {
                        if (data.estado_info && data.estado_info.codigo_estandar === 'INACTIVO') {
                            $(row).addClass('table-secondary');
                        } else if (data.estado_info && data.estado_info.codigo_estandar === 'BLOQUEADO') {
                            $(row).addClass('table-warning');
                        }
                    },
                    initComplete: function () {
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

                    tabla.ajax.reload(function () {
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
                $.get('jurisdicciones_ajax.php', {
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
                            '<i class="fas fa-plus me-1"></i>Agregar Jurisdicción</button>'
                        );
                    }
                }, 'json');
            }

            $(document).on('click', '#btnNuevo', function () {
                resetModal();
                $('#modalLabel').text('Nueva Jurisdicción');
                
                // Valores por defecto
                $('#requiere_padron').prop('checked', false);
                $('#orden').val(1);
                
                var modal = new bootstrap.Modal(document.getElementById('modalJurisdiccion'));
                modal.show();
                $('#jurisdiccion_codigo').focus();
            });

            $(document).on('click', '.btn-accion', function () {
                var id = $(this).data('id');
                var accionJs = $(this).data('accion');
                var confirmable = $(this).data('confirmable');
                var nombre = $(this).data('tipo');

                if (accionJs === 'editar') {
                    cargarJurisdiccionParaEditar(id);
                } else if (confirmable == 1) {
                    Swal.fire({
                        title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                        html: `¿Está seguro de <strong>${accionJs}</strong> la jurisdicción <strong>"${nombre}"</strong>?`,
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
                            ejecutarAccion(id, accionJs, nombre);
                        }
                    });
                } else {
                    ejecutarAccion(id, accionJs, nombre);
                }
            });

            function ejecutarAccion(id, accionJs, nombre) {
                var savedState = {
                    page: tabla.page(),
                    order: tabla.order(),
                    search: tabla.search()
                };

                $.post('jurisdicciones_ajax.php', {
                    accion: 'ejecutar_accion',
                    jurisdiccion_id: id,
                    accion_js: accionJs,
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                }, function (res) {
                    if (res.success) {
                        tabla.ajax.reload(function () {
                            if (savedState.page !== undefined) {
                                tabla.page(savedState.page).draw('page');
                            }
                            if (savedState.search && savedState.search !== '') {
                                tabla.search(savedState.search).draw();
                            }

                            tabla.rows().every(function (rowIdx, tableLoop, rowLoop) {
                                var data = this.data();
                                if (data.jurisdiccion_id == id) {
                                    $(this.node()).addClass('table-success');
                                    setTimeout(function () {
                                        $(this.node()).removeClass('table-success');
                                    }.bind(this), 2000);
                                }
                            });

                            Swal.fire({
                                icon: "success",
                                title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                                text: res.message || `Jurisdicción "${nombre}" actualizada correctamente`,
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
                            text: res.error || `Error al ${accionJs} la jurisdicción`,
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            function cargarJurisdiccionParaEditar(id) {
                $.get('jurisdicciones_ajax.php', {
                    accion: 'obtener',
                    jurisdiccion_id: id,
                    empresa_idx: empresa_idx
                }, function (res) {
                    if (res && res.jurisdiccion_id) {
                        resetModal();
                        
                        $('#jurisdiccion_id').val(res.jurisdiccion_id);
                        $('#jurisdiccion_codigo').val(res.jurisdiccion_codigo);
                        $('#jurisdiccion_nombre').val(res.jurisdiccion_nombre);
                        $('#jurisdiccion_tipo_id').val(res.jurisdiccion_tipo_id);
                        
                        if (res.pais_id) {
                            $('#pais_id').val(res.pais_id);
                            // Cargar provincias primero
                            cargarProvincias(res.pais_id);
                            // Esperar a que se carguen las provincias antes de seleccionar
                            setTimeout(function() {
                                if (res.provincia_id) {
                                    $('#provincia_id').val(res.provincia_id);
                                    // Cargar localidades después de seleccionar provincia
                                    cargarLocalidades(res.provincia_id);
                                    setTimeout(function() {
                                        if (res.localidad_id) {
                                            $('#localidad_id').val(res.localidad_id);
                                        }
                                    }, 300);
                                }
                            }, 300);
                        }
                        
                        $('#organismo_recaudador').val(res.organismo_recaudador || '');
                        $('#codigo_externo').val(res.codigo_externo || '');
                        $('#requiere_padron').prop('checked', res.requiere_padron == 1);
                        $('#orden').val(res.orden || 1);
                        
                        $('#modalLabel').text('Editar Jurisdicción');

                        var modal = new bootstrap.Modal(document.getElementById('modalJurisdiccion'));
                        modal.show();
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "Error al obtener datos de la jurisdicción",
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            function resetModal() {
                $('#formJurisdiccion')[0].reset();
                $('#jurisdiccion_id').val('');
                $('#formJurisdiccion').removeClass('was-validated');
                $('#pais_id').val('');
                $('#provincia_id').empty().append('<option value="">Seleccione una provincia...</option>');
                $('#localidad_id').empty().append('<option value="">Seleccione una localidad...</option>');
                $('#requiere_padron').prop('checked', false);
                $('#orden').val(1);
            }

            $('#btnGuardar').click(function () {
                var form = document.getElementById('formJurisdiccion');

                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var id = $('#jurisdiccion_id').val();
                var accionBackend = id ? 'editar' : 'agregar';
                var codigo = $('#jurisdiccion_codigo').val().trim();
                var nombre = $('#jurisdiccion_nombre').val().trim();
                var tipoId = $('#jurisdiccion_tipo_id').val();
                var paisId = $('#pais_id').val();
                var provinciaId = $('#provincia_id').val();
                var localidadId = $('#localidad_id').val();
                var organismo = $('#organismo_recaudador').val().trim();
                var codigoExterno = $('#codigo_externo').val().trim();
                var requierePadron = $('#requiere_padron').is(':checked') ? 1 : 0;
                var orden = $('#orden').val();

                if (!codigo || codigo.length > 10) {
                    $('#jurisdiccion_codigo').addClass('is-invalid');
                    Swal.fire({
                        icon: "warning",
                        title: "Validación",
                        text: "El código es obligatorio y no puede exceder los 10 caracteres",
                        confirmButtonText: "Entendido"
                    });
                    return false;
                } else {
                    $('#jurisdiccion_codigo').removeClass('is-invalid');
                }

                if (!nombre || nombre.length > 100) {
                    $('#jurisdiccion_nombre').addClass('is-invalid');
                    Swal.fire({
                        icon: "warning",
                        title: "Validación",
                        text: "El nombre es obligatorio y no puede exceder los 100 caracteres",
                        confirmButtonText: "Entendido"
                    });
                    return false;
                } else {
                    $('#jurisdiccion_nombre').removeClass('is-invalid');
                }

                if (!tipoId) {
                    $('#jurisdiccion_tipo_id').addClass('is-invalid');
                    Swal.fire({
                        icon: "warning",
                        title: "Validación",
                        text: "Debe seleccionar un tipo de jurisdicción",
                        confirmButtonText: "Entendido"
                    });
                    return false;
                } else {
                    $('#jurisdiccion_tipo_id').removeClass('is-invalid');
                }

                var btnGuardar = $(this);
                var originalText = btnGuardar.html();
                btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

                var savedState = {
                    page: tabla.page(),
                    order: tabla.order(),
                    search: tabla.search()
                };

                $.ajax({
                    url: 'jurisdicciones_ajax.php',
                    type: 'POST',
                    data: {
                        accion: accionBackend,
                        jurisdiccion_id: id,
                        jurisdiccion_codigo: codigo,
                        jurisdiccion_nombre: nombre,
                        jurisdiccion_tipo_id: tipoId,
                        pais_id: paisId,
                        provincia_id: provinciaId,
                        localidad_id: localidadId,
                        organismo_recaudador: organismo,
                        codigo_externo: codigoExterno,
                        requiere_padron: requierePadron,
                        orden: orden,
                        empresa_idx: empresa_idx,
                        pagina_idx: pagina_idx
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.resultado) {
                            tabla.ajax.reload(function () {
                                if (savedState.page !== undefined) {
                                    tabla.page(savedState.page).draw('page');
                                }
                                if (savedState.search && savedState.search !== '') {
                                    tabla.search(savedState.search).draw();
                                }

                                btnGuardar.prop('disabled', false).html(originalText);

                                Swal.fire({
                                    icon: "success",
                                    title: "¡Guardado!",
                                    text: "Jurisdicción guardada correctamente",
                                    showConfirmButton: false,
                                    timer: 1500,
                                    toast: true,
                                    position: 'top-end'
                                });

                                var modalEl = document.getElementById('modalJurisdiccion');
                                var modal = bootstrap.Modal.getInstance(modalEl);
                                modal.hide();
                            }, false);
                        } else {
                            btnGuardar.prop('disabled', false).html(originalText);
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
                        console.error('Error:', error);
                        Swal.fire({
                            icon: "error",
                            title: "Error de conexión",
                            text: "Error al comunicarse con el servidor. Por favor, intente nuevamente.",
                            confirmButtonText: "Entendido"
                        });
                    }
                });
            });

            $('#btnExportarExcel').click(function (e) {
                e.preventDefault();
                exportToExcel();
            });

            $('#btnExportarPDF').click(function (e) {
                e.preventDefault();
                exportToPDF();
            });

            $('#btnExportarCSV').click(function (e) {
                e.preventDefault();
                exportToCSV();
            });

            $('#btnExportarPrint').click(function (e) {
                e.preventDefault();
                exportToPrint();
            });

            function exportToExcel() {
                var data = tabla.rows().data().toArray();
                var exportData = data.map(row => ({
                    'ID': row.jurisdiccion_id,
                    'Código': row.jurisdiccion_codigo,
                    'Nombre': row.jurisdiccion_nombre,
                    'Tipo': row.jurisdiccion_tipo || '',
                    'País': row.pais || '',
                    'Provincia': row.provincia_nombre || '',
                    'Localidad': row.localidad_nombre || '',
                    'Organismo Recaudador': row.organismo_recaudador || '',
                    'Requiere Padrón': row.requiere_padron == 1 ? 'Sí' : 'No',
                    'Orden': row.orden || '',
                    'Estado': row.estado_info?.estado_registro || ''
                }));
                
                var ws = XLSX.utils.json_to_sheet(exportData);
                var wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Jurisdicciones');
                XLSX.writeFile(wb, `Jurisdicciones_${new Date().toISOString().slice(0,19)}.xlsx`);
            }

            function exportToPDF() {
                var printWindow = window.open('', '_blank');
                var content = '<html><head><title>Jurisdicciones</title>';
                content += '<style>table {border-collapse: collapse; width: 100%;} th, td {border: 1px solid #ddd; padding: 8px; text-align: left;} th {background-color: #f2f2f2;}</style>';
                content += '</head><body>';
                content += '<h2>Jurisdicciones</h2>';
                content += '<table>';
                content += '<thead><tr><th>ID</th><th>Código</th><th>Nombre</th><th>Tipo</th><th>País</th><th>Provincia</th><th>Localidad</th><th>Organismo</th><th>Padrón</th><th>Orden</th><th>Estado</th></tr></thead><tbody>';
                
                var data = tabla.rows().data().toArray();
                data.forEach(row => {
                    content += `<tr>
                        <td>${row.jurisdiccion_id}</td>
                        <td>${escapeHtml(row.jurisdiccion_codigo)}</td>
                        <td>${escapeHtml(row.jurisdiccion_nombre)}</td>
                        <td>${row.jurisdiccion_tipo || ''}</td>
                        <td>${row.pais || ''}</td>
                        <td>${row.provincia_nombre || ''}</td>
                        <td>${row.localidad_nombre || ''}</td>
                        <td>${row.organismo_recaudador || ''}</td>
                        <td>${row.requiere_padron == 1 ? 'Sí' : 'No'}</td>
                        <td>${row.orden || ''}</td>
                        <td>${row.estado_info?.estado_registro || ''}</td>
                    </tr>`;
                });
                
                content += '</tbody></table></body></html>';
                printWindow.document.write(content);
                printWindow.document.close();
                printWindow.print();
            }

            function exportToCSV() {
                var data = tabla.rows().data().toArray();
                var csv = "ID,Código,Nombre,Tipo,País,Provincia,Localidad,Organismo Recaudador,Requiere Padrón,Orden,Estado\n";
                
                data.forEach(row => {
                    csv += `"${row.jurisdiccion_id}","${escapeCsv(row.jurisdiccion_codigo)}","${escapeCsv(row.jurisdiccion_nombre)}","${escapeCsv(row.jurisdiccion_tipo || '')}","${escapeCsv(row.pais || '')}","${escapeCsv(row.provincia_nombre || '')}","${escapeCsv(row.localidad_nombre || '')}","${escapeCsv(row.organismo_recaudador || '')}","${row.requiere_padron == 1 ? 'Sí' : 'No'}","${row.orden || ''}","${escapeCsv(row.estado_info?.estado_registro || '')}"\n`;
                });
                
                var blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
                var link = document.createElement("a");
                var url = URL.createObjectURL(blob);
                link.setAttribute("href", url);
                link.setAttribute("download", `Jurisdicciones_${new Date().toISOString().slice(0,19)}.csv`);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }

            function exportToPrint() {
                window.print();
            }

            function escapeHtml(str) {
                if (!str) return '';
                return str.replace(/[&<>]/g, function(m) {
                    if (m === '&') return '&amp;';
                    if (m === '<') return '&lt;';
                    if (m === '>') return '&gt;';
                    return m;
                });
            }

            function escapeCsv(str) {
                if (!str) return '';
                return str.replace(/"/g, '""');
            }

            inicializarDataTable();
            cargarBotonAgregar();
            cargarTiposJurisdiccion();
            cargarPaises();
            
            $('[title]').tooltip({
                trigger: 'hover',
                placement: 'top'
            });
        });
    </script>

    <!-- Librerías necesarias -->
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>