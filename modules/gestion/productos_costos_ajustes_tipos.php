<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Tipos de Ajuste de Costos";
$currentPage = 'productos_costos_ajustes_tipos';
$modudo_idx = 2;
$pagina_idx = 80; // ID de página para Tipos de Ajuste de Costos

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-percent me-2"></i>Tipos de Ajuste de Costos
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Gestión</a></li>
                        <li class="breadcrumb-item"><a href="#">Configuración</a></li>
                        <li class="breadcrumb-item"><a href="#">Productos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Tipos de Ajuste de Costos</li>
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
                                        <table id="tablaTiposAjuste" class="table table-striped table-bordered"
                                            style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="50">ID</th>
                                                    <th width="100">Código</th>
                                                    <th width="200">Nombre</th>
                                                    <th width="300">Descripción</th>
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

            <!-- Modal para crear/editar Tipo de Ajuste -->
            <div class="modal fade" id="modalTipoAjuste" tabindex="-1" aria-labelledby="modalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Tipo de Ajuste de Costos</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formTipoAjuste" class="needs-validation" novalidate>
                                <input type="hidden" id="producto_costo_ajuste_tipo_id" name="producto_costo_ajuste_tipo_id" />
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="producto_costo_ajuste_tipo_codigo" class="form-label">Código *</label>
                                        <input type="text" class="form-control" id="producto_costo_ajuste_tipo_codigo" 
                                            name="producto_costo_ajuste_tipo_codigo" maxlength="50" required>
                                        <div class="invalid-feedback">El código es obligatorio (máx. 50 caracteres)</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="producto_costo_ajuste_tipo_nombre" class="form-label">Nombre *</label>
                                        <input type="text" class="form-control" id="producto_costo_ajuste_tipo_nombre" 
                                            name="producto_costo_ajuste_tipo_nombre" maxlength="100" required>
                                        <div class="invalid-feedback">El nombre es obligatorio (máx. 100 caracteres)</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label for="descripcion" class="form-label">Descripción</label>
                                        <textarea class="form-control" id="descripcion" 
                                            name="descripcion" rows="3" maxlength="255"></textarea>
                                        <div class="form-text">Opcional, máximo 255 caracteres</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="orden" class="form-label">Orden</label>
                                        <input type="number" class="form-control" id="orden" 
                                            name="orden" value="1" min="1">
                                        <div class="form-text">Orden de visualización</div>
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
            
            // Función para inicializar DataTable
            function inicializarDataTable() {
                if ($.fn.DataTable.isDataTable('#tablaTiposAjuste')) {
                    $('#tablaTiposAjuste').DataTable().destroy();
                    $('#tablaTiposAjuste tbody').empty();
                }

                tabla = $('#tablaTiposAjuste').DataTable({
                    ajax: {
                        url: 'productos_costos_ajustes_tipos_ajax.php',
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
                            data: 'producto_costo_ajuste_tipo_id',
                            className: 'text-center fw-bold'
                        },
                        {
                            data: 'producto_costo_ajuste_tipo_codigo',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data;
                                }
                                return `<span class="badge bg-info">${escapeHtml(data)}</span>`;
                            }
                        },
                        {
                            data: 'producto_costo_ajuste_tipo_nombre',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data;
                                }
                                return `<div class="fw-medium">${escapeHtml(data)}</div>`;
                            }
                        },
                        {
                            data: 'descripcion',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data || '';
                                }
                                return data ? escapeHtml(data) : '<span class="text-muted">-</span>';
                            }
                        },
                        {
                            data: 'orden',
                            className: 'text-center',
                            render: function (data, type, row) {
                                if (type === 'export') {
                                    return data || '';
                                }
                                return `<span class="badge bg-secondary">${data || 1}</span>`;
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
                                       data-id="${row.producto_costo_ajuste_tipo_id}" 
                                       data-accion="${accionJs}"
                                       data-confirmable="${esConfirmable}"
                                       data-tipo="${escapeHtml(row.producto_costo_ajuste_tipo_nombre)}">
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
                $.get('productos_costos_ajustes_tipos_ajax.php', {
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
                            '<i class="fas fa-plus me-1"></i>Agregar Tipo de Ajuste</button>'
                        );
                    }
                }, 'json');
            }

            $(document).on('click', '#btnNuevo', function () {
                resetModal();
                $('#modalLabel').text('Nuevo Tipo de Ajuste de Costos');
                
                // Valores por defecto
                $('#orden').val(1);
                
                var modal = new bootstrap.Modal(document.getElementById('modalTipoAjuste'));
                modal.show();
                $('#producto_costo_ajuste_tipo_codigo').focus();
            });

            $(document).on('click', '.btn-accion', function () {
                var id = $(this).data('id');
                var accionJs = $(this).data('accion');
                var confirmable = $(this).data('confirmable');
                var nombre = $(this).data('tipo');

                if (accionJs === 'editar') {
                    cargarTipoAjusteParaEditar(id);
                } else if (confirmable == 1) {
                    Swal.fire({
                        title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                        html: `¿Está seguro de <strong>${accionJs}</strong> el tipo de ajuste <strong>"${nombre}"</strong>?`,
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

                $.post('productos_costos_ajustes_tipos_ajax.php', {
                    accion: 'ejecutar_accion',
                    producto_costo_ajuste_tipo_id: id,
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
                                if (data.producto_costo_ajuste_tipo_id == id) {
                                    $(this.node()).addClass('table-success');
                                    setTimeout(function () {
                                        $(this.node()).removeClass('table-success');
                                    }.bind(this), 2000);
                                }
                            });

                            Swal.fire({
                                icon: "success",
                                title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                                text: res.message || `Tipo de ajuste "${nombre}" actualizado correctamente`,
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
                            text: res.error || `Error al ${accionJs} el tipo de ajuste`,
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            function cargarTipoAjusteParaEditar(id) {
                $.get('productos_costos_ajustes_tipos_ajax.php', {
                    accion: 'obtener',
                    producto_costo_ajuste_tipo_id: id,
                    empresa_idx: empresa_idx
                }, function (res) {
                    if (res && res.producto_costo_ajuste_tipo_id) {
                        resetModal();
                        
                        $('#producto_costo_ajuste_tipo_id').val(res.producto_costo_ajuste_tipo_id);
                        $('#producto_costo_ajuste_tipo_codigo').val(res.producto_costo_ajuste_tipo_codigo);
                        $('#producto_costo_ajuste_tipo_nombre').val(res.producto_costo_ajuste_tipo_nombre);
                        $('#descripcion').val(res.descripcion || '');
                        $('#orden').val(res.orden || 1);
                        
                        $('#modalLabel').text('Editar Tipo de Ajuste de Costos');

                        var modal = new bootstrap.Modal(document.getElementById('modalTipoAjuste'));
                        modal.show();
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "Error al obtener datos del tipo de ajuste",
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            function resetModal() {
                $('#formTipoAjuste')[0].reset();
                $('#producto_costo_ajuste_tipo_id').val('');
                $('#formTipoAjuste').removeClass('was-validated');
                $('#descripcion').val('');
                $('#orden').val(1);
            }

            $('#btnGuardar').click(function () {
                var form = document.getElementById('formTipoAjuste');

                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var id = $('#producto_costo_ajuste_tipo_id').val();
                var accionBackend = id ? 'editar' : 'agregar';
                var codigo = $('#producto_costo_ajuste_tipo_codigo').val().trim();
                var nombre = $('#producto_costo_ajuste_tipo_nombre').val().trim();
                var descripcion = $('#descripcion').val().trim();
                var orden = $('#orden').val();

                if (!codigo || codigo.length > 50) {
                    $('#producto_costo_ajuste_tipo_codigo').addClass('is-invalid');
                    Swal.fire({
                        icon: "warning",
                        title: "Validación",
                        text: "El código es obligatorio y no puede exceder los 50 caracteres",
                        confirmButtonText: "Entendido"
                    });
                    return false;
                } else {
                    $('#producto_costo_ajuste_tipo_codigo').removeClass('is-invalid');
                }

                if (!nombre || nombre.length > 100) {
                    $('#producto_costo_ajuste_tipo_nombre').addClass('is-invalid');
                    Swal.fire({
                        icon: "warning",
                        title: "Validación",
                        text: "El nombre es obligatorio y no puede exceder los 100 caracteres",
                        confirmButtonText: "Entendido"
                    });
                    return false;
                } else {
                    $('#producto_costo_ajuste_tipo_nombre').removeClass('is-invalid');
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
                    url: 'productos_costos_ajustes_tipos_ajax.php',
                    type: 'POST',
                    data: {
                        accion: accionBackend,
                        producto_costo_ajuste_tipo_id: id,
                        producto_costo_ajuste_tipo_codigo: codigo,
                        producto_costo_ajuste_tipo_nombre: nombre,
                        descripcion: descripcion,
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
                                    text: "Tipo de ajuste guardado correctamente",
                                    showConfirmButton: false,
                                    timer: 1500,
                                    toast: true,
                                    position: 'top-end'
                                });

                                var modalEl = document.getElementById('modalTipoAjuste');
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
                    'ID': row.producto_costo_ajuste_tipo_id,
                    'Código': row.producto_costo_ajuste_tipo_codigo,
                    'Nombre': row.producto_costo_ajuste_tipo_nombre,
                    'Descripción': row.descripcion || '',
                    'Orden': row.orden || '',
                    'Estado': row.estado_info?.estado_registro || ''
                }));
                
                var ws = XLSX.utils.json_to_sheet(exportData);
                var wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'TiposAjusteCostos');
                XLSX.writeFile(wb, `TiposAjusteCostos_${new Date().toISOString().slice(0,19)}.xlsx`);
            }

            function exportToPDF() {
                var printWindow = window.open('', '_blank');
                var content = '<html><head><title>Tipos de Ajuste de Costos</title>';
                content += '<style>table {border-collapse: collapse; width: 100%;} th, td {border: 1px solid #ddd; padding: 8px; text-align: left;} th {background-color: #f2f2f2;}</style>';
                content += '</head><body>';
                content += '<h2>Tipos de Ajuste de Costos</h2>';
                content += '<table>';
                content += '<thead><tr><th>ID</th><th>Código</th><th>Nombre</th><th>Descripción</th><th>Orden</th><th>Estado</th></tr></thead><tbody>';
                
                var data = tabla.rows().data().toArray();
                data.forEach(row => {
                    content += `<tr>
                        <td>${row.producto_costo_ajuste_tipo_id}</td>
                        <td>${escapeHtml(row.producto_costo_ajuste_tipo_codigo)}</td>
                        <td>${escapeHtml(row.producto_costo_ajuste_tipo_nombre)}</td>
                        <td>${escapeHtml(row.descripcion || '')}</td>
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
                var csv = "ID,Código,Nombre,Descripción,Orden,Estado\n";
                
                data.forEach(row => {
                    csv += `"${row.producto_costo_ajuste_tipo_id}","${escapeCsv(row.producto_costo_ajuste_tipo_codigo)}","${escapeCsv(row.producto_costo_ajuste_tipo_nombre)}","${escapeCsv(row.descripcion || '')}","${row.orden || ''}","${escapeCsv(row.estado_info?.estado_registro || '')}"\n`;
                });
                
                var blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
                var link = document.createElement("a");
                var url = URL.createObjectURL(blob);
                link.setAttribute("href", url);
                link.setAttribute("download", `TiposAjusteCostos_${new Date().toISOString().slice(0,19)}.csv`);
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