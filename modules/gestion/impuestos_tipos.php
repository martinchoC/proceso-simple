<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Tipos de Impuestos";
$currentPage = 'impuestos_tipos';
$modudo_idx = 2;
$pagina_idx = 72; // ID de página para Tipos de Impuestos (ajustar según corresponda)

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-tags me-2"></i>Tipos de Impuestos
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Gestión</a></li>
                        <li class="breadcrumb-item"><a href="#">Impuestos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Tipos de Impuestos</li>
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
                                        <table id="tablaImpuestosTipos" class="table table-striped table-bordered"
                                            style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="50">ID</th>
                                                    <th width="200">Tipo de Impuesto</th>
                                                    <th width="100">Código AFIP</th>
                                                    <th width="80" class="text-center">Compra</th>
                                                    <th width="80" class="text-center">Venta</th>
                                                    <th width="80" class="text-center">Retención</th>
                                                    <th width="80" class="text-center">Percepción</th>
                                                    <th width="200">Cuenta Contable</th>
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

            <!-- Modal para crear/editar Tipo de Impuesto -->
            <div class="modal fade" id="modalTipoImpuesto" tabindex="-1" aria-labelledby="modalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Tipo de Impuesto</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formTipoImpuesto" class="needs-validation" novalidate>
                                <input type="hidden" id="impuesto_tipo_id" name="impuesto_tipo_id" />
                                
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="impuesto_tipo" class="form-label">Tipo de Impuesto *</label>
                                        <input type="text" class="form-control" id="impuesto_tipo" 
                                            name="impuesto_tipo" maxlength="100" required>
                                        <div class="invalid-feedback">El tipo de impuesto es obligatorio (máx. 100 caracteres)</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="codigo_afip" class="form-label">Código AFIP</label>
                                        <input type="text" class="form-control" id="codigo_afip" 
                                            name="codigo_afip" maxlength="20">
                                        <div class="form-text">Código según nomenclador AFIP (opcional)</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="cuenta_contable_id" class="form-label">Cuenta Contable</label>
                                        <select class="form-select select2-cuenta" id="cuenta_contable_id" name="cuenta_contable_id">
                                            <option value="">Seleccione una cuenta contable...</option>
                                        </select>
                                        <div class="form-text">Cuenta contable asociada (opcional)</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label">Aplicaciones</label>
                                        <div class="border rounded p-3 bg-light">
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" 
                                                            id="aplica_compra" name="aplica_compra" value="1" checked>
                                                        <label class="form-check-label" for="aplica_compra">
                                                            <i class="fas fa-shopping-cart text-primary me-1"></i>Aplica a Compras
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" 
                                                            id="aplica_venta" name="aplica_venta" value="1">
                                                        <label class="form-check-label" for="aplica_venta">
                                                            <i class="fas fa-chart-line text-success me-1"></i>Aplica a Ventas
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" 
                                                            id="es_retencion" name="es_retencion" value="1">
                                                        <label class="form-check-label" for="es_retencion">
                                                            <i class="fas fa-hand-holding-usd text-warning me-1"></i>Retención
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-check form-switch">
                                                        <input class="form-check-input" type="checkbox" 
                                                            id="es_percepcion" name="es_percepcion" value="1">
                                                        <label class="form-check-label" for="es_percepcion">
                                                            <i class="fas fa-eye text-info me-1"></i>Percepción
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                            <small class="text-muted d-block mt-2">
                                                Seleccione las aplicaciones del impuesto
                                            </small>
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

        .switch-label {
            cursor: pointer;
        }
    </style>

    <script>
        $(document).ready(function () {
            // Variables de contexto MULTIEMPRESA
            const empresa_idx = <?php echo isset($_GET['empresa_id']) ? $_GET['empresa_id'] : 2; ?>;
            const pagina_idx = <?php echo $pagina_idx; ?>;

            // Variables para mantener el estado del DataTable
            var tabla;
            var currentPage = 0;
            var currentOrder = [[0, 'asc']];
            var currentSearch = '';
            
            function cargarCuentasContables() {
                console.log("Cargando cuentas contables...");
                $.ajax({
                    url: 'impuestos_tipos_ajax.php',
                    type: 'GET',
                    data: {
                        accion: 'listar_cuentas_contables',
                        empresa_idx: empresa_idx
                    },
                    dataType: 'json',
                    success: function(response) {
                        console.log("Respuesta recibida:", response);
                        var select = $('#cuenta_contable_id');
                        select.empty();
                        select.append('<option value="">Seleccione una cuenta contable...</option>');
                        
                        if (response && response.length > 0) {
                            $.each(response, function(index, cuenta) {
                                var optionText = cuenta.codigo + ' - ' + cuenta.nombre;
                                select.append('<option value="' + cuenta.cont_cuenta_id + '">' + optionText + '</option>');
                            });
                        } else {
                            select.append('<option value="" disabled>No hay cuentas contables disponibles</option>');
                        }
                        
                        if ($('#cuenta_contable_id').data('select2')) {
                            $('#cuenta_contable_id').select2('destroy');
                        }
                        inicializarSelect2();
                    },
                    error: function(xhr, status, error) {
                        console.error('Error al cargar cuentas contables:', error);
                        $('#cuenta_contable_id').append('<option value="" disabled>Error al cargar cuentas</option>');
                    }
                });
            }

            // Inicializar Select2 en el modal
            function inicializarSelect2() {
                $('#cuenta_contable_id').select2({
                    theme: 'bootstrap-5',
                    placeholder: 'Buscar cuenta contable...',
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('#modalTipoImpuesto'),
                    language: {
                        noResults: function() {
                            return "No se encontraron cuentas contables";
                        }
                    }
                });
            }

            // Función para inicializar DataTable
            function inicializarDataTable() {
                if ($.fn.DataTable.isDataTable('#tablaImpuestosTipos')) {
                    $('#tablaImpuestosTipos').DataTable().destroy();
                    $('#tablaImpuestosTipos tbody').empty();
                }

                tabla = $('#tablaImpuestosTipos').DataTable({
                    ajax: {
                        url: 'impuestos_tipos_ajax.php',
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
                            data: 'impuesto_tipo_id',
                            className: 'text-center fw-bold'
                        },
                        {
                            data: 'impuesto_tipo',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data;
                                }
                                return `<div class="fw-medium">${escapeHtml(data)}</div>`;
                            }
                        },
                        {
                            data: 'codigo_afip',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data || '';
                                }
                                return data ? `<span class="badge bg-secondary">${escapeHtml(data)}</span>` : '<span class="text-muted">-</span>';
                            }
                        },
                        {
                            data: 'aplica_compra',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data == 1 ? 'Sí' : 'No';
                                }
                                return data == 1 ? '<i class="fas fa-check-circle text-success fa-lg" title="Aplica a compras"></i>' : '<i class="fas fa-times-circle text-danger fa-lg" title="No aplica a compras"></i>';
                            }
                        },
                        {
                            data: 'aplica_venta',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data == 1 ? 'Sí' : 'No';
                                }
                                return data == 1 ? '<i class="fas fa-check-circle text-success fa-lg" title="Aplica a ventas"></i>' : '<i class="fas fa-times-circle text-danger fa-lg" title="No aplica a ventas"></i>';
                            }
                        },
                        {
                            data: 'es_retencion',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data == 1 ? 'Sí' : 'No';
                                }
                                return data == 1 ? '<i class="fas fa-check-circle text-success fa-lg" title="Es retención"></i>' : '<i class="fas fa-times-circle text-danger fa-lg" title="No es retención"></i>';
                            }
                        },
                        {
                            data: 'es_percepcion',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data == 1 ? 'Sí' : 'No';
                                }
                                return data == 1 ? '<i class="fas fa-check-circle text-success fa-lg" title="Es percepción"></i>' : '<i class="fas fa-times-circle text-danger fa-lg" title="No es percepción"></i>';
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
                                       data-id="${row.impuesto_tipo_id}" 
                                       data-accion="${accionJs}"
                                       data-confirmable="${esConfirmable}"
                                       data-tipo="${escapeHtml(row.impuesto_tipo)}">
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
                $.get('impuestos_tipos_ajax.php', {
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
                            '<i class="fas fa-plus me-1"></i>Agregar Tipo de Impuesto</button>'
                        );
                    }
                }, 'json');
            }

            $(document).on('click', '#btnNuevo', function () {
                resetModal();
                $('#modalLabel').text('Nuevo Tipo de Impuesto');
                
                // Valores por defecto
                $('#aplica_compra').prop('checked', true);
                $('#aplica_venta').prop('checked', false);
                $('#es_retencion').prop('checked', false);
                $('#es_percepcion').prop('checked', false);
                
                var modal = new bootstrap.Modal(document.getElementById('modalTipoImpuesto'));
                modal.show();
                $('#impuesto_tipo').focus();
            });

            $(document).on('click', '.btn-accion', function () {
                var tipoId = $(this).data('id');
                var accionJs = $(this).data('accion');
                var confirmable = $(this).data('confirmable');
                var tipo = $(this).data('tipo');

                if (accionJs === 'editar') {
                    cargarTipoParaEditar(tipoId);
                } else if (confirmable == 1) {
                    Swal.fire({
                        title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                        html: `¿Está seguro de <strong>${accionJs}</strong> el tipo de impuesto <strong>"${tipo}"</strong>?`,
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
                            ejecutarAccion(tipoId, accionJs, tipo);
                        }
                    });
                } else {
                    ejecutarAccion(tipoId, accionJs, tipo);
                }
            });

            function ejecutarAccion(tipoId, accionJs, tipo) {
                var savedState = {
                    page: tabla.page(),
                    order: tabla.order(),
                    search: tabla.search()
                };

                $.post('impuestos_tipos_ajax.php', {
                    accion: 'ejecutar_accion',
                    impuesto_tipo_id: tipoId,
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
                                if (data.impuesto_tipo_id == tipoId) {
                                    $(this.node()).addClass('table-success');
                                    setTimeout(function () {
                                        $(this.node()).removeClass('table-success');
                                    }.bind(this), 2000);
                                }
                            });

                            Swal.fire({
                                icon: "success",
                                title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                                text: res.message || `Tipo de impuesto "${tipo}" actualizado correctamente`,
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
                            text: res.error || `Error al ${accionJs} el tipo de impuesto`,
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            function cargarTipoParaEditar(tipoId) {
                $.get('impuestos_tipos_ajax.php', {
                    accion: 'obtener',
                    impuesto_tipo_id: tipoId,
                    empresa_idx: empresa_idx
                }, function (res) {
                    if (res && res.impuesto_tipo_id) {
                        resetModal();
                        
                        $('#impuesto_tipo_id').val(res.impuesto_tipo_id);
                        $('#impuesto_tipo').val(res.impuesto_tipo);
                        $('#codigo_afip').val(res.codigo_afip || '');
                        
                        if (res.cuenta_contable_id) {
                            $('#cuenta_contable_id').val(res.cuenta_contable_id).trigger('change');
                        }
                        
                        $('#aplica_compra').prop('checked', res.aplica_compra == 1);
                        $('#aplica_venta').prop('checked', res.aplica_venta == 1);
                        $('#es_retencion').prop('checked', res.es_retencion == 1);
                        $('#es_percepcion').prop('checked', res.es_percepcion == 1);
                        
                        $('#modalLabel').text('Editar Tipo de Impuesto');

                        var modal = new bootstrap.Modal(document.getElementById('modalTipoImpuesto'));
                        modal.show();
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "Error al obtener datos del tipo de impuesto",
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            function resetModal() {
                $('#formTipoImpuesto')[0].reset();
                $('#impuesto_tipo_id').val('');
                $('#formTipoImpuesto').removeClass('was-validated');
                $('#cuenta_contable_id').val('').trigger('change');
            }

            $('#btnGuardar').click(function () {
                var form = document.getElementById('formTipoImpuesto');

                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var id = $('#impuesto_tipo_id').val();
                var accionBackend = id ? 'editar' : 'agregar';
                var impuestoTipo = $('#impuesto_tipo').val().trim();
                var codigoAfip = $('#codigo_afip').val().trim();
                var cuentaContableId = $('#cuenta_contable_id').val();
                var aplicaCompra = $('#aplica_compra').is(':checked') ? 1 : 0;
                var aplicaVenta = $('#aplica_venta').is(':checked') ? 1 : 0;
                var esRetencion = $('#es_retencion').is(':checked') ? 1 : 0;
                var esPercepcion = $('#es_percepcion').is(':checked') ? 1 : 0;

                if (!impuestoTipo || impuestoTipo.length > 100) {
                    $('#impuesto_tipo').addClass('is-invalid');
                    Swal.fire({
                        icon: "warning",
                        title: "Validación",
                        text: "El tipo de impuesto es obligatorio y no puede exceder los 100 caracteres",
                        confirmButtonText: "Entendido"
                    });
                    return false;
                } else {
                    $('#impuesto_tipo').removeClass('is-invalid');
                }

                if (codigoAfip && codigoAfip.length > 20) {
                    $('#codigo_afip').addClass('is-invalid');
                    Swal.fire({
                        icon: "warning",
                        title: "Validación",
                        text: "El código AFIP no puede exceder los 20 caracteres",
                        confirmButtonText: "Entendido"
                    });
                    return false;
                } else {
                    $('#codigo_afip').removeClass('is-invalid');
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
                    url: 'impuestos_tipos_ajax.php',
                    type: 'POST',
                    data: {
                        accion: accionBackend,
                        impuesto_tipo_id: id,
                        impuesto_tipo: impuestoTipo,
                        codigo_afip: codigoAfip,
                        cuenta_contable_id: cuentaContableId,
                        aplica_compra: aplicaCompra,
                        aplica_venta: aplicaVenta,
                        es_retencion: esRetencion,
                        es_percepcion: esPercepcion,
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
                                    text: "Tipo de impuesto guardado correctamente",
                                    showConfirmButton: false,
                                    timer: 1500,
                                    toast: true,
                                    position: 'top-end'
                                });

                                var modalEl = document.getElementById('modalTipoImpuesto');
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
                    'ID': row.impuesto_tipo_id,
                    'Tipo de Impuesto': row.impuesto_tipo,
                    'Código AFIP': row.codigo_afip || '',
                    'Aplica Compra': row.aplica_compra == 1 ? 'Sí' : 'No',
                    'Aplica Venta': row.aplica_venta == 1 ? 'Sí' : 'No',
                    'Retención': row.es_retencion == 1 ? 'Sí' : 'No',
                    'Percepción': row.es_percepcion == 1 ? 'Sí' : 'No',
                    'Cuenta Contable': row.cuenta_contable || '',
                    'Estado': row.estado_info?.estado_registro || ''
                }));
                
                var ws = XLSX.utils.json_to_sheet(exportData);
                var wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'Tipos_Impuestos');
                XLSX.writeFile(wb, `Tipos_Impuestos_${new Date().toISOString().slice(0,19)}.xlsx`);
            }

            function exportToPDF() {
                var printWindow = window.open('', '_blank');
                var content = '<html><head><title>Tipos de Impuestos</title>';
                content += '<style>table {border-collapse: collapse; width: 100%;} th, td {border: 1px solid #ddd; padding: 8px; text-align: left;} th {background-color: #f2f2f2;}</style>';
                content += '</head><body>';
                content += '<h2>Tipos de Impuestos</h2>';
                content += '<table>';
                content += '<thead><tr><th>ID</th><th>Tipo de Impuesto</th><th>Código AFIP</th><th>Compra</th><th>Venta</th><th>Retención</th><th>Percepción</th><th>Cuenta Contable</th><th>Estado</th></tr></thead><tbody>';
                
                var data = tabla.rows().data().toArray();
                data.forEach(row => {
                    content += `<tr>
                        <td>${row.impuesto_tipo_id}</td>
                        <td>${escapeHtml(row.impuesto_tipo)}</td>
                        <td>${row.codigo_afip || ''}</td>
                        <td>${row.aplica_compra == 1 ? 'Sí' : 'No'}</td>
                        <td>${row.aplica_venta == 1 ? 'Sí' : 'No'}</td>
                        <td>${row.es_retencion == 1 ? 'Sí' : 'No'}</td>
                        <td>${row.es_percepcion == 1 ? 'Sí' : 'No'}</td>
                        <td>${row.cuenta_contable || ''}</td>
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
                var csv = "ID,Tipo de Impuesto,Código AFIP,Aplica Compra,Aplica Venta,Retención,Percepción,Cuenta Contable,Estado\n";
                
                data.forEach(row => {
                    csv += `"${row.impuesto_tipo_id}","${escapeCsv(row.impuesto_tipo)}","${escapeCsv(row.codigo_afip || '')}","${row.aplica_compra == 1 ? 'Sí' : 'No'}","${row.aplica_venta == 1 ? 'Sí' : 'No'}","${row.es_retencion == 1 ? 'Sí' : 'No'}","${row.es_percepcion == 1 ? 'Sí' : 'No'}","${escapeCsv(row.cuenta_contable || '')}","${escapeCsv(row.estado_info?.estado_registro || '')}"\n`;
                });
                
                var blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
                var link = document.createElement("a");
                var url = URL.createObjectURL(blob);
                link.setAttribute("href", url);
                link.setAttribute("download", `Tipos_Impuestos_${new Date().toISOString().slice(0,19)}.csv`);
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