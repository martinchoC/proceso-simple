<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Impuestos por Jurisdicción";
$currentPage = 'impuestos_jurisdicciones';
$modudo_idx = 2;
$pagina_idx = 76; // ID de página para Impuestos por Jurisdicción

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-dollar-sign me-2"></i>Impuestos por Jurisdicción
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Gestión</a></li>
                        <li class="breadcrumb-item"><a href="#">Configuración</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Impuestos por Jurisdicción</li>
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
                                        <table id="tablaImpuestosJurisdicciones" class="table table-striped table-bordered"
                                            style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="50">ID</th>
                                                    <th width="150">Impuesto</th>
                                                    <th width="150">Jurisdicción</th>
                                                    <th width="100">Tipo Cálculo</th>
                                                    <th width="100">Código Local</th>
                                                    <th width="80" class="text-center">Requiere Padrón</th>
                                                    <th width="120">Cuenta Contable</th>
                                                    <th width="80" class="text-center">Orden</th>
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

            <!-- Modal para crear/editar Impuesto por Jurisdicción -->
            <div class="modal fade" id="modalImpuestoJurisdiccion" tabindex="-1" aria-labelledby="modalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Impuesto por Jurisdicción</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formImpuestoJurisdiccion" class="needs-validation" novalidate>
                                <input type="hidden" id="impuesto_jurisdiccion_id" name="impuesto_jurisdiccion_id" />
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="impuesto_tipo_id" class="form-label">Tipo de Impuesto *</label>
                                        <select class="form-select" id="impuesto_tipo_id" name="impuesto_tipo_id" required>
                                            <option value="">Seleccione un impuesto...</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione un tipo de impuesto</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="jurisdiccion_id" class="form-label">Jurisdicción *</label>
                                        <select class="form-select" id="jurisdiccion_id" name="jurisdiccion_id" required>
                                            <option value="">Seleccione una jurisdicción...</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione una jurisdicción</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="tipo_calculo" class="form-label">Tipo de Cálculo *</label>
                                        <select class="form-select" id="tipo_calculo" name="tipo_calculo" required>
                                            <option value="">Seleccione un tipo...</option>
                                            <option value="manual">Manual</option>
                                            <option value="padron">Padrón</option>
                                            <option value="regla">Regla</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione un tipo de cálculo</div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="codigo_local" class="form-label">Código Local</label>
                                        <input type="text" class="form-control" id="codigo_local" 
                                            name="codigo_local" maxlength="20">
                                        <div class="form-text">Opcional</div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="orden" class="form-label">Orden</label>
                                        <input type="number" class="form-control" id="orden" 
                                            name="orden" value="1" min="1">
                                        <div class="form-text">Orden de visualización</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="cuenta_contable_id" class="form-label">Cuenta Contable</label>
                                        <select class="form-select" id="cuenta_contable_id" name="cuenta_contable_id">
                                            <option value="">Seleccione una cuenta contable...</option>
                                        </select>
                                        <div class="form-text">Opcional</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Configuración</label>
                                        <div class="border rounded p-3 bg-light">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" 
                                                    id="requiere_padron" name="requiere_padron" value="1">
                                                <label class="form-check-label" for="requiere_padron">
                                                    <i class="fas fa-id-card text-primary me-1"></i>Requiere Padrón
                                                </label>
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
            
            function cargarTiposImpuesto() {
                $.ajax({
                    url: 'impuestos_jurisdicciones_ajax.php',
                    type: 'GET',
                    data: {
                        accion: 'listar_tipos_impuesto'
                    },
                    dataType: 'json',
                    success: function(response) {
                        var select = $('#impuesto_tipo_id');
                        select.empty();
                        select.append('<option value="">Seleccione un impuesto...</option>');
                        
                        if (response && response.length > 0) {
                            $.each(response, function(index, tipo) {
                                select.append('<option value="' + tipo.impuesto_tipo_id + '">' + 
                                    escapeHtml(tipo.impuesto_tipo) + '</option>');
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error al cargar tipos de impuesto:', error);
                    }
                });
            }

            function cargarJurisdicciones() {
                $.ajax({
                    url: 'impuestos_jurisdicciones_ajax.php',
                    type: 'GET',
                    data: {
                        accion: 'listar_jurisdicciones'
                    },
                    dataType: 'json',
                    success: function(response) {
                        var select = $('#jurisdiccion_id');
                        select.empty();
                        select.append('<option value="">Seleccione una jurisdicción...</option>');
                        
                        if (response && response.length > 0) {
                            $.each(response, function(index, jurisdiccion) {
                                select.append('<option value="' + jurisdiccion.jurisdiccion_id + '">' + 
                                    escapeHtml(jurisdiccion.jurisdiccion_nombre) + ' (' + escapeHtml(jurisdiccion.jurisdiccion_codigo) + ')</option>');
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error al cargar jurisdicciones:', error);
                    }
                });
            }

            function cargarCuentasContables() {
                $.ajax({
                    url: 'impuestos_jurisdicciones_ajax.php',
                    type: 'GET',
                    data: {
                        accion: 'listar_cuentas_contables'
                    },
                    dataType: 'json',
                    success: function(response) {
                        var select = $('#cuenta_contable_id');
                        select.empty();
                        select.append('<option value="">Seleccione una cuenta contable...</option>');
                        
                        if (response && response.length > 0) {
                            $.each(response, function(index, cuenta) {
                                select.append('<option value="' + cuenta.cuenta_contable_id + '">' + 
                                    escapeHtml(cuenta.codigo) + ' - ' + escapeHtml(cuenta.nombre) + '</option>');
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error al cargar cuentas contables:', error);
                    }
                });
            }

            // Función para inicializar DataTable
            function inicializarDataTable() {
                if ($.fn.DataTable.isDataTable('#tablaImpuestosJurisdicciones')) {
                    $('#tablaImpuestosJurisdicciones').DataTable().destroy();
                    $('#tablaImpuestosJurisdicciones tbody').empty();
                }

                tabla = $('#tablaImpuestosJurisdicciones').DataTable({
                    ajax: {
                        url: 'impuestos_jurisdicciones_ajax.php',
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
                            data: 'impuesto_jurisdiccion_id',
                            className: 'text-center fw-bold'
                        },
                        {
                            data: 'impuesto_tipo',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data || '';
                                }
                                return data ? `<span class="badge bg-info">${escapeHtml(data)}</span>` : '<span class="text-muted">-</span>';
                            }
                        },
                        {
                            data: 'jurisdiccion_nombre',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data || '';
                                }
                                return data ? `<div class="fw-medium">${escapeHtml(data)}</div><small class="text-muted">${escapeHtml(row.jurisdiccion_codigo || '')}</small>` : '<span class="text-muted">-</span>';
                            }
                        },
                        {
                            data: 'tipo_calculo',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data || '';
                                }
                                var badgeClass = 'secondary';
                                if (data === 'manual') badgeClass = 'primary';
                                if (data === 'padron') badgeClass = 'success';
                                if (data === 'regla') badgeClass = 'warning';
                                return data ? `<span class="badge bg-${badgeClass}">${escapeHtml(data)}</span>` : '<span class="text-muted">-</span>';
                            }
                        },
                        {
                            data: 'codigo_local',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data || '';
                                }
                                return data ? `<code>${escapeHtml(data)}</code>` : '<span class="text-muted">-</span>';
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
                            data: 'cuenta_contable',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data || '';
                                }
                                return data ? `<span class="text-info">${escapeHtml(data)}</span>` : '<span class="text-muted">-</span>';
                            }
                        },
                        {
                            data: 'orden',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data || '';
                                }
                                return data ? `<span class="badge bg-secondary">${data}</span>` : '<span class="text-muted">-</span>';
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
                                       data-id="${row.impuesto_jurisdiccion_id}" 
                                       data-accion="${accionJs}"
                                       data-confirmable="${esConfirmable}"
                                       data-tipo="${escapeHtml(row.impuesto_tipo)} - ${escapeHtml(row.jurisdiccion_nombre)}">
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
                $.get('impuestos_jurisdicciones_ajax.php', {
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
                            '<i class="fas fa-plus me-1"></i>Agregar Impuesto por Jurisdicción</button>'
                        );
                    }
                }, 'json');
            }

            $(document).on('click', '#btnNuevo', function () {
                resetModal();
                $('#modalLabel').text('Nuevo Impuesto por Jurisdicción');
                
                // Valores por defecto
                $('#requiere_padron').prop('checked', false);
                $('#orden').val(1);
                $('#tipo_calculo').val('');
                
                var modal = new bootstrap.Modal(document.getElementById('modalImpuestoJurisdiccion'));
                modal.show();
                $('#impuesto_tipo_id').focus();
            });

            $(document).on('click', '.btn-accion', function () {
                var id = $(this).data('id');
                var accionJs = $(this).data('accion');
                var confirmable = $(this).data('confirmable');
                var nombre = $(this).data('tipo');

                if (accionJs === 'editar') {
                    cargarImpuestoJurisdiccionParaEditar(id);
                } else if (confirmable == 1) {
                    Swal.fire({
                        title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                        html: `¿Está seguro de <strong>${accionJs}</strong> el registro <strong>"${nombre}"</strong>?`,
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

                $.post('impuestos_jurisdicciones_ajax.php', {
                    accion: 'ejecutar_accion',
                    impuesto_jurisdiccion_id: id,
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
                                if (data.impuesto_jurisdiccion_id == id) {
                                    $(this.node()).addClass('table-success');
                                    setTimeout(function () {
                                        $(this.node()).removeClass('table-success');
                                    }.bind(this), 2000);
                                }
                            });

                            Swal.fire({
                                icon: "success",
                                title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                                text: res.message || `Registro "${nombre}" actualizado correctamente`,
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
                            text: res.error || `Error al ${accionJs} el registro`,
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            function cargarImpuestoJurisdiccionParaEditar(id) {
                $.get('impuestos_jurisdicciones_ajax.php', {
                    accion: 'obtener',
                    impuesto_jurisdiccion_id: id,
                    empresa_idx: empresa_idx
                }, function (res) {
                    if (res && res.impuesto_jurisdiccion_id) {
                        resetModal();
                        
                        $('#impuesto_jurisdiccion_id').val(res.impuesto_jurisdiccion_id);
                        $('#impuesto_tipo_id').val(res.impuesto_tipo_id);
                        $('#jurisdiccion_id').val(res.jurisdiccion_id);
                        $('#tipo_calculo').val(res.tipo_calculo);
                        $('#codigo_local').val(res.codigo_local || '');
                        $('#cuenta_contable_id').val(res.cuenta_contable_id || '');
                        $('#requiere_padron').prop('checked', res.requiere_padron == 1);
                        $('#orden').val(res.orden || 1);
                        
                        $('#modalLabel').text('Editar Impuesto por Jurisdicción');

                        var modal = new bootstrap.Modal(document.getElementById('modalImpuestoJurisdiccion'));
                        modal.show();
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "Error al obtener datos del registro",
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            function resetModal() {
                $('#formImpuestoJurisdiccion')[0].reset();
                $('#impuesto_jurisdiccion_id').val('');
                $('#formImpuestoJurisdiccion').removeClass('was-validated');
                $('#requiere_padron').prop('checked', false);
                $('#orden').val(1);
                $('#tipo_calculo').val('');
            }

            $('#btnGuardar').click(function () {
                var form = document.getElementById('formImpuestoJurisdiccion');

                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var id = $('#impuesto_jurisdiccion_id').val();
                var accionBackend = id ? 'editar' : 'agregar';
                var impuesto_tipo_id = $('#impuesto_tipo_id').val();
                var jurisdiccion_id = $('#jurisdiccion_id').val();
                var tipo_calculo = $('#tipo_calculo').val();
                var codigo_local = $('#codigo_local').val().trim();
                var cuenta_contable_id = $('#cuenta_contable_id').val();
                var requiere_padron = $('#requiere_padron').is(':checked') ? 1 : 0;
                var orden = $('#orden').val();

                if (!impuesto_tipo_id) {
                    $('#impuesto_tipo_id').addClass('is-invalid');
                    Swal.fire({
                        icon: "warning",
                        title: "Validación",
                        text: "Debe seleccionar un tipo de impuesto",
                        confirmButtonText: "Entendido"
                    });
                    return false;
                } else {
                    $('#impuesto_tipo_id').removeClass('is-invalid');
                }

                if (!jurisdiccion_id) {
                    $('#jurisdiccion_id').addClass('is-invalid');
                    Swal.fire({
                        icon: "warning",
                        title: "Validación",
                        text: "Debe seleccionar una jurisdicción",
                        confirmButtonText: "Entendido"
                    });
                    return false;
                } else {
                    $('#jurisdiccion_id').removeClass('is-invalid');
                }

                if (!tipo_calculo) {
                    $('#tipo_calculo').addClass('is-invalid');
                    Swal.fire({
                        icon: "warning",
                        title: "Validación",
                        text: "Debe seleccionar un tipo de cálculo",
                        confirmButtonText: "Entendido"
                    });
                    return false;
                } else {
                    $('#tipo_calculo').removeClass('is-invalid');
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
                    url: 'impuestos_jurisdicciones_ajax.php',
                    type: 'POST',
                    data: {
                        accion: accionBackend,
                        impuesto_jurisdiccion_id: id,
                        impuesto_tipo_id: impuesto_tipo_id,
                        jurisdiccion_id: jurisdiccion_id,
                        tipo_calculo: tipo_calculo,
                        codigo_local: codigo_local,
                        cuenta_contable_id: cuenta_contable_id,
                        requiere_padron: requiere_padron,
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
                                    text: "Registro guardado correctamente",
                                    showConfirmButton: false,
                                    timer: 1500,
                                    toast: true,
                                    position: 'top-end'
                                });

                                var modalEl = document.getElementById('modalImpuestoJurisdiccion');
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
                    'ID': row.impuesto_jurisdiccion_id,
                    'Impuesto': row.impuesto_tipo || '',
                    'Jurisdicción': row.jurisdiccion_nombre || '',
                    'Código Jurisdicción': row.jurisdiccion_codigo || '',
                    'Tipo Cálculo': row.tipo_calculo || '',
                    'Código Local': row.codigo_local || '',
                    'Requiere Padrón': row.requiere_padron == 1 ? 'Sí' : 'No',
                    'Cuenta Contable': row.cuenta_contable || '',
                    'Orden': row.orden || '',
                    'Estado': row.estado_info?.estado_registro || ''
                }));
                
                var ws = XLSX.utils.json_to_sheet(exportData);
                var wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'ImpuestosJurisdicciones');
                XLSX.writeFile(wb, `Impuestos_Jurisdicciones_${new Date().toISOString().slice(0,19)}.xlsx`);
            }

            function exportToPDF() {
                var printWindow = window.open('', '_blank');
                var content = '<html><head><title>Impuestos por Jurisdicción</title>';
                content += '<style>table {border-collapse: collapse; width: 100%;} th, td {border: 1px solid #ddd; padding: 8px; text-align: left;} th {background-color: #f2f2f2;}</style>';
                content += '</head><body>';
                content += '<h2>Impuestos por Jurisdicción</h2>';
                content += '<table>';
                content += '<thead><tr><th>ID</th><th>Impuesto</th><th>Jurisdicción</th><th>Tipo Cálculo</th><th>Código Local</th><th>Requiere Padrón</th><th>Cuenta Contable</th><th>Orden</th><th>Estado</th></tr></thead><tbody>';
                
                var data = tabla.rows().data().toArray();
                data.forEach(row => {
                    content += `<tr>
                        <td>${row.impuesto_jurisdiccion_id}</td>
                        <td>${escapeHtml(row.impuesto_tipo || '')}</td>
                        <td>${escapeHtml(row.jurisdiccion_nombre || '')}</td>
                        <td>${row.tipo_calculo || ''}</td>
                        <td>${escapeHtml(row.codigo_local || '')}</td>
                        <td>${row.requiere_padron == 1 ? 'Sí' : 'No'}</td>
                        <td>${escapeHtml(row.cuenta_contable || '')}</td>
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
                var csv = "ID,Impuesto,Jurisdicción,Código Jurisdicción,Tipo Cálculo,Código Local,Requiere Padrón,Cuenta Contable,Orden,Estado\n";
                
                data.forEach(row => {
                    csv += `"${row.impuesto_jurisdiccion_id}","${escapeCsv(row.impuesto_tipo || '')}","${escapeCsv(row.jurisdiccion_nombre || '')}","${escapeCsv(row.jurisdiccion_codigo || '')}","${escapeCsv(row.tipo_calculo || '')}","${escapeCsv(row.codigo_local || '')}","${row.requiere_padron == 1 ? 'Sí' : 'No'}","${escapeCsv(row.cuenta_contable || '')}","${row.orden || ''}","${escapeCsv(row.estado_info?.estado_registro || '')}"\n`;
                });
                
                var blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
                var link = document.createElement("a");
                var url = URL.createObjectURL(blob);
                link.setAttribute("href", url);
                link.setAttribute("download", `Impuestos_Jurisdicciones_${new Date().toISOString().slice(0,19)}.csv`);
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
            cargarTiposImpuesto();
            cargarJurisdicciones();
            cargarCuentasContables();
            
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