<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Gestión de Submodelos";
$currentPage = 'submodelos';
$modudo_idx = 2;
$pagina_idx = 42; // ID de página para submodelos (ajustar según tu BD)

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-layer-group me-2"></i>Gestión de Submodelos
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Gestión</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Submodelos</li>
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
                                <ul class="nav nav-tabs mb-3" id="tabsSubmodelos" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link tab-resumen active" id="tab-jerarquico-btn" data-bs-toggle="tab"
                                            data-bs-target="#tab-jerarquico" type="button" role="tab"
                                            aria-controls="tab-jerarquico" aria-selected="true">
                                            <i class="fas fa-sitemap me-1"></i>Jerárquico
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="tab-listado-btn" data-bs-toggle="tab"
                                            data-bs-target="#tab-listado" type="button" role="tab"
                                            aria-controls="tab-listado" aria-selected="false">
                                            <i class="fas fa-list me-1"></i>Listado
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link tab-resumen" id="tab-dashboard-btn" data-bs-toggle="tab"
                                            data-bs-target="#tab-dashboard" type="button" role="tab"
                                            aria-controls="tab-dashboard" aria-selected="false">
                                            <i class="fas fa-chart-bar me-1"></i>Dashboard
                                        </button>
                                    </li>
                                </ul>

                                <div class="tab-content">
                                <div class="tab-pane fade" id="tab-listado" role="tabpanel"
                                    aria-labelledby="tab-listado-btn">
                                <div class="card">
                                    <div class="card-header">
                                        <div id="contenedor-boton-agregar" class="d-inline"></div>
                                        <div class="float-end">
                                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                                id="btnRecargar">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <!-- DataTable -->
                                        <table id="tablaSubmodelos" class="table table-striped table-bordered"
                                            style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="80">ID</th>
                                                    <th>Nombre del Submodelo</th>
                                                    <th width="150">Modelo</th>
                                                    <th width="150">Marca</th>
                                                    <th width="120">Estado</th>
                                                    <th width="200" class="text-center">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                                </div>

                                <!-- Pestaña: dashboard de productos por marca / modelo / submodelo -->
                                <div class="tab-pane fade" id="tab-dashboard" role="tabpanel"
                                    aria-labelledby="tab-dashboard-btn">

                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <small class="text-muted">
                                            Productos activos distintos con compatibilidad activa. Un producto
                                            compatible con varios modelos cuenta en cada uno, por eso los totales
                                            de los hijos pueden superar al del padre.
                                        </small>
                                        <button type="button" class="btn btn-sm btn-outline-secondary ms-2 btn-recargar-resumen"
                                            title="Actualizar">
                                            <i class="fas fa-sync-alt"></i>
                                        </button>
                                    </div>

                                    <!-- KPIs -->
                                    <div class="row g-3 mb-3" id="resumenKpis"></div>

                                    <!-- Top marcas / modelos -->
                                    <div class="row g-3 mb-3">
                                        <div class="col-lg-6">
                                            <div class="card h-100">
                                                <div class="card-header"><strong>Top 10 marcas</strong></div>
                                                <div class="card-body" id="resumenTopMarcas"></div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6">
                                            <div class="card h-100">
                                                <div class="card-header"><strong>Top 10 modelos</strong></div>
                                                <div class="card-body" id="resumenTopModelos"></div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Registros sin productos (para depurar) -->
                                    <div class="card">
                                        <div class="card-header d-flex flex-wrap align-items-center gap-2">
                                            <strong class="me-auto">
                                                <i class="fas fa-exclamation-triangle text-warning me-1"></i>Sin productos asignados
                                            </strong>
                                            <ul class="nav nav-pills nav-sm" role="tablist">
                                                <li class="nav-item">
                                                    <button class="nav-link py-1 px-2 active sin-prod-tab" data-tipo="submodelos"
                                                        type="button">Submodelos <span class="badge text-bg-secondary" id="cntSinSubmodelos">0</span></button>
                                                </li>
                                                <li class="nav-item">
                                                    <button class="nav-link py-1 px-2 sin-prod-tab" data-tipo="modelos"
                                                        type="button">Modelos <span class="badge text-bg-secondary" id="cntSinModelos">0</span></button>
                                                </li>
                                                <li class="nav-item">
                                                    <button class="nav-link py-1 px-2 sin-prod-tab" data-tipo="marcas"
                                                        type="button">Marcas <span class="badge text-bg-secondary" id="cntSinMarcas">0</span></button>
                                                </li>
                                            </ul>
                                            <div class="btn-group btn-group-sm" role="group" aria-label="Inhabilitar sin productos">
                                                <button type="button" class="btn btn-outline-danger btn-inhabilitar-sin-prod" data-tipo="submodelos"
                                                    id="btnInhabilitarSinSubmodelos" title="Inhabilitar todos los submodelos sin productos asignados">
                                                    <i class="fas fa-ban me-1"></i>Inhabilitar Submodelos
                                                </button>
                                                <button type="button" class="btn btn-outline-danger btn-inhabilitar-sin-prod" data-tipo="modelos"
                                                    id="btnInhabilitarSinModelos" title="Inhabilitar todos los modelos sin productos asignados">
                                                    <i class="fas fa-ban me-1"></i>Inhabilitar Modelos
                                                </button>
                                                <button type="button" class="btn btn-outline-danger btn-inhabilitar-sin-prod" data-tipo="marcas"
                                                    id="btnInhabilitarSinMarcas" title="Inhabilitar todas las marcas sin productos asignados">
                                                    <i class="fas fa-ban me-1"></i>Inhabilitar Marcas
                                                </button>
                                            </div>
                                            <input type="search" class="form-control form-control-sm" style="max-width: 180px;"
                                                id="sinProdBuscar" placeholder="Filtrar...">
                                        </div>
                                        <div class="card-body p-0" style="max-height: 420px; overflow-y: auto;">
                                            <table class="table table-sm table-hover mb-0">
                                                <thead class="table-light" style="position: sticky; top: 0; z-index: 1;">
                                                    <tr id="sinProdCabecera"></tr>
                                                </thead>
                                                <tbody id="sinProdCuerpo"></tbody>
                                            </table>
                                        </div>
                                        <div class="card-footer small text-muted" id="sinProdPie"></div>
                                    </div>
                                </div>

                                <!-- Pestaña: árbol jerárquico marca → modelo → submodelo → productos -->
                                <div class="tab-pane fade show active" id="tab-jerarquico" role="tabpanel"
                                    aria-labelledby="tab-jerarquico-btn">
                                    <div class="card">
                                        <div class="card-header">
                                            <div class="row g-2 align-items-center">
                                                <div class="col-md-5">
                                                    <input type="search" class="form-control form-control-sm"
                                                        id="resumenBuscar" placeholder="Buscar marca, modelo o submodelo...">
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="form-check form-switch mb-0">
                                                        <input class="form-check-input" type="checkbox"
                                                            id="resumenSoloConProductos" checked>
                                                        <label class="form-check-label small" for="resumenSoloConProductos">
                                                            Solo con productos
                                                        </label>
                                                    </div>
                                                </div>
                                                <div class="col-md-4 text-md-end">
                                                    <button type="button" class="btn btn-sm btn-outline-secondary btn-recargar-resumen"
                                                        title="Actualizar">
                                                        <i class="fas fa-sync-alt"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                                        id="resumenExpandir">
                                                        <i class="fas fa-plus-square me-1"></i>Expandir
                                                    </button>
                                                    <button type="button" class="btn btn-sm btn-outline-secondary"
                                                        id="resumenColapsar">
                                                        <i class="fas fa-minus-square me-1"></i>Colapsar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="card-body p-0">
                                            <table class="table table-sm table-hover mb-0 tabla-arbol">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Marca / Modelo / Submodelo</th>
                                                        <th width="110" class="text-end">Productos</th>
                                                        <th width="25%">Distribución</th>
                                                        <th width="80" class="text-center">Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="resumenArbol">
                                                    <tr><td colspan="4" class="text-center text-muted py-4">
                                                        <i class="fas fa-spinner fa-spin me-1"></i>Cargando...
                                                    </td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Modal para crear/editar submodelo -->
            <div class="modal fade" id="modalSubmodelo" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Submodelo</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formSubmodelo" class="needs-validation" novalidate>
                                <input type="hidden" id="submodelo_id" name="submodelo_id" />
                                <div class="mb-3">
                                    <label for="modelo_id" class="form-label">Modelo *</label>
                                    <select class="form-select" id="modelo_id" name="modelo_id" required>
                                        <option value="">Seleccionar modelo...</option>
                                    </select>
                                    <div class="invalid-feedback">Debe seleccionar un modelo</div>
                                </div>
                                <div class="mb-3">
                                    <label for="submodelo_nombre" class="form-label">Nombre del Submodelo *</label>
                                    <input type="text" class="form-control" id="submodelo_nombre"
                                        name="submodelo_nombre" maxlength="100" required>
                                    <div class="invalid-feedback">El nombre del submodelo es obligatorio</div>
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

    <script>
        $(document).ready(function () {
            // Variables de contexto MULTIEMPRESA
            const empresa_idx = window.EMPRESA_ID; // empresa activa (header1.php)
            const pagina_idx = <?php echo $pagina_idx; ?>;

            // Cargar modelos para el select
            function cargarModelos(selectedId = null) {
                $.get('submodelos_ajax.php', {
                    accion: 'obtener_modelos_activos',
                    empresa_idx: empresa_idx
                }, function (modelos) {
                    var select = $('#modelo_id');
                    select.empty();
                    select.append('<option value="">Seleccionar modelo...</option>');

                    $.each(modelos, function (index, modelo) {
                        var selected = (selectedId && modelo.modelo_id == selectedId) ? 'selected' : '';
                        select.append(`<option value="${modelo.modelo_id}" ${selected}>${modelo.marca_nombre} - ${modelo.modelo_nombre}</option>`);
                    });
                }, 'json');
            }

            // Configuración de DataTable
            var tabla = $('#tablaSubmodelos').DataTable({
                ajax: {
                    url: 'submodelos_ajax.php',
                    type: 'GET',
                    data: {
                        accion: 'listar',
                        empresa_idx: empresa_idx,
                        pagina_idx: pagina_idx
                    },
                    dataSrc: ''
                },
                pageLength: 10, // REQUISITO: 50 registros iniciales
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]], // REQUISITO: paginación completa
                columns: [
                    {
                        data: 'submodelo_id',
                        className: 'text-center fw-bold',
                        render: function (data) {
                            return `<span class="fw-medium">${data}</span>`;
                        }
                    },
                    {
                        data: 'submodelo_nombre',
                        render: function (data, type, row) {
                            return `<div class="fw-medium">${data}</div>`;
                        }
                    },
                    {
                        data: 'modelo_nombre',
                        render: function (data, type, row) {
                            return `<div class="fw-medium">${data}</div>`;
                        }
                    },
                    {
                        data: 'marca_nombre',
                        render: function (data, type, row) {
                            return `<div class="fw-medium">${data}</div>`;
                        }
                    },
                    {
                        data: 'estado_info',
                        className: 'text-center',
                        render: function (data) {
                            if (!data || !data.estado_registro) {
                                return '<span class="fw-medium">Sin estado</span>';
                            }

                            // Aplicar colores desde BD o usar default
                            var estado = data.estado_registro;

                            return `<span class="fw-medium">
                                ${estado}
                                </span>`;
                        }
                    },
                    {
                        data: 'botones',
                        orderable: false,
                        searchable: false,
                        className: "text-center",
                        width: '200px',
                        render: function (data, type, row) {
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
                                       data-id="${row.submodelo_id}" 
                                       data-accion="${accionJs}"
                                       data-confirmable="${esConfirmable}"
                                       data-submodelo-nombre="${row.submodelo_nombre}">
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
                order: [[1, 'asc']],
                responsive: true,
                createdRow: function (row, data, dataIndex) {
                    if (data.estado_info && data.estado_info.codigo_estandar === 'INACTIVO') {
                        $(row).addClass('table-secondary');
                    } else if (data.estado_info && data.estado_info.codigo_estandar === 'BLOQUEADO') {
                        $(row).addClass('table-warning');
                    }
                }
            });

            // Cargar botón Agregar dinámicamente
            function cargarBotonAgregar() {
                $.get('submodelos_ajax.php', {
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
                            '<i class="fas fa-plus me-1"></i>Agregar Submodelo</button>'
                        );
                    }
                }, 'json');
            }

            // Manejador para botón "Agregar"
            $(document).on('click', '#btnNuevo', function () {
                resetModal();
                $('#modalLabel').text('Nuevo Submodelo');
                cargarModelos();

                var modal = new bootstrap.Modal(document.getElementById('modalSubmodelo'));
                modal.show();
                $('#submodelo_nombre').focus();
            });

            // Manejador para botones de acción dinámicos
            $(document).on('click', '.btn-accion', function () {
                var submodeloId = $(this).data('id');
                var accionJs = $(this).data('accion');
                var confirmable = $(this).data('confirmable');
                var submodeloNombre = $(this).data('submodelo-nombre');

                if (accionJs === 'editar') {
                    cargarSubmodeloParaEditar(submodeloId);
                } else if (confirmable == 1) {
                    Swal.fire({
                        title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                        html: `¿Está seguro de <strong>${accionJs}</strong> el submodelo <strong>"${submodeloNombre}"</strong>?`,
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
                            ejecutarAccion(submodeloId, accionJs, submodeloNombre);
                        }
                    });
                } else {
                    ejecutarAccion(submodeloId, accionJs, submodeloNombre);
                }
            });

            // Función para ejecutar cualquier acción del backend
            function ejecutarAccion(submodeloId, accionJs, submodeloNombre) {
                $.post('submodelos_ajax.php', {
                    accion: 'ejecutar_accion',
                    submodelo_id: submodeloId,
                    accion_js: accionJs,
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                }, function (res) {
                    if (res.success) {
                        tabla.ajax.reload();
                        refrescarResumenSiCargado();
                        Swal.fire({
                            icon: "success",
                            title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                            text: res.message || `Submodelo "${submodeloNombre}" actualizado correctamente`,
                            showConfirmButton: false,
                            timer: 1500,
                            toast: true,
                            position: 'top-end'
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || `Error al ${accionJs} el submodelo`,
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            // Función para cargar submodelo en modal de edición
            function cargarSubmodeloParaEditar(submodeloId) {
                $.get('submodelos_ajax.php', {
                    accion: 'obtener',
                    submodelo_id: submodeloId,
                    empresa_idx: empresa_idx
                }, function (res) {
                    if (res && res.submodelo_id) {
                        resetModal();
                        $('#submodelo_id').val(res.submodelo_id);
                        $('#submodelo_nombre').val(res.submodelo_nombre);
                        cargarModelos(res.modelo_id);
                        $('#modalLabel').text('Editar Submodelo');

                        var modal = new bootstrap.Modal(document.getElementById('modalSubmodelo'));
                        modal.show();

                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "Error al obtener datos del submodelo",
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            // Función para resetear el modal
            function resetModal() {
                $('#formSubmodelo')[0].reset();
                $('#submodelo_id').val('');
                $('#formSubmodelo').removeClass('was-validated');
            }

            // Validación del formulario
            $('#btnGuardar').click(function () {
                var form = document.getElementById('formSubmodelo');

                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var id = $('#submodelo_id').val();
                var accionBackend = id ? 'editar' : 'agregar';
                var submodeloNombre = $('#submodelo_nombre').val().trim();
                var modeloId = $('#modelo_id').val();

                if (!submodeloNombre) {
                    $('#submodelo_nombre').addClass('is-invalid');
                    return false;
                }

                if (!modeloId) {
                    $('#modelo_id').addClass('is-invalid');
                    return false;
                }

                var btnGuardar = $(this);
                var originalText = btnGuardar.html();
                btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

                $.ajax({
                    url: 'submodelos_ajax.php',
                    type: 'POST',
                    data: {
                        accion: accionBackend,
                        submodelo_id: id,
                        submodelo_nombre: submodeloNombre,
                        modelo_id: modeloId,
                        empresa_idx: empresa_idx,
                        pagina_idx: pagina_idx
                    },
                    success: function (res) {
                        if (res.resultado) {
                            tabla.ajax.reload();
                            refrescarResumenSiCargado();
                            var modalEl = document.getElementById('modalSubmodelo');
                            var modal = bootstrap.Modal.getInstance(modalEl);

                            btnGuardar.prop('disabled', false).html(originalText);

                            Swal.fire({
                                icon: "success",
                                title: "¡Guardado!",
                                text: "Submodelo guardado correctamente",
                                showConfirmButton: false,
                                timer: 1500,
                                toast: true,
                                position: 'top-end'
                            });
                            modal.hide();
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
                    error: function () {
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

            // Botón recargar
            $('#btnRecargar').click(function () {
                var btn = $(this);
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

                tabla.ajax.reload(function () {
                    btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
                });
            });

            // ================= Pestaña resumen de productos =================
            var resumenData = null;
            var resumenExpandidos = new Set();

            function esc(texto) {
                return $('<div>').text(texto == null ? '' : texto).html();
            }

            function fmt(n) {
                return Number(n || 0).toLocaleString('es-AR');
            }

            function cargarResumen() {
                $('#resumenArbol').html('<tr><td colspan="4" class="text-center text-muted py-4">' +
                    '<i class="fas fa-spinner fa-spin me-1"></i>Cargando...</td></tr>');
                $.get('submodelos_ajax.php', {
                    accion: 'resumen_productos',
                    empresa_idx: empresa_idx
                }, function (res) {
                    if (!res || !res.success) {
                        $('#resumenArbol').html('<tr><td colspan="4" class="text-center text-danger py-4">' +
                            esc((res && (res.message || res.error)) || 'Error al obtener el resumen') + '</td></tr>');
                        return;
                    }
                    resumenData = res.data;
                    renderKpis();
                    renderTops();
                    renderSinProductos();
                    renderArbol();
                }, 'json').fail(function () {
                    $('#resumenArbol').html('<tr><td colspan="4" class="text-center text-danger py-4">' +
                        'Error al comunicarse con el servidor</td></tr>');
                });
            }

            function renderKpis() {
                var k = resumenData.kpis;
                var pctCompat = k.productos_activos > 0
                    ? Math.round(k.productos_con_compatibilidad * 100 / k.productos_activos) : 0;
                var tarjetas = [
                    { titulo: 'Productos con compatibilidad', valor: fmt(k.productos_con_compatibilidad),
                      sub: pctCompat + '% de ' + fmt(k.productos_activos) + ' activos', icono: 'fas fa-box', color: 'primary' },
                    { titulo: 'Marcas con productos', valor: fmt(k.marcas_con_productos),
                      sub: 'de ' + fmt(k.marcas_total) + ' marcas', icono: 'fas fa-copyright', color: 'success' },
                    { titulo: 'Modelos con productos', valor: fmt(k.modelos_con_productos),
                      sub: 'de ' + fmt(k.modelos_total) + ' modelos', icono: 'fas fa-car', color: 'info' },
                    { titulo: 'Submodelos con productos', valor: fmt(k.submodelos_con_productos),
                      sub: 'de ' + fmt(k.submodelos_total) + ' submodelos', icono: 'fas fa-layer-group', color: 'warning' }
                ];
                $('#resumenKpis').html(tarjetas.map(function (t) {
                    return `<div class="col-sm-6 col-xl-3">
                        <div class="card h-100 border-start border-4 border-${t.color}">
                            <div class="card-body d-flex align-items-center">
                                <div class="flex-grow-1">
                                    <div class="text-muted small">${t.titulo}</div>
                                    <div class="fs-3 fw-bold">${t.valor}</div>
                                    <div class="small text-muted">${t.sub}</div>
                                </div>
                                <i class="${t.icono} fa-2x text-${t.color} opacity-50"></i>
                            </div>
                        </div>
                    </div>`;
                }).join(''));
            }

            function barra(valor, maximo, color) {
                var pct = maximo > 0 ? Math.max(valor > 0 ? 1 : 0, Math.round(valor * 100 / maximo)) : 0;
                return `<div class="progress" style="height: 8px;" title="${pct}%">
                    <div class="progress-bar bg-${color}" style="width: ${pct}%"></div>
                </div>`;
            }

            function renderTop(selector, items, color) {
                items = items.filter(function (i) { return i.productos > 0; })
                    .sort(function (a, b) { return b.productos - a.productos; })
                    .slice(0, 10);
                if (!items.length) {
                    $(selector).html('<div class="text-muted text-center">Sin datos</div>');
                    return;
                }
                var max = items[0].productos;
                $(selector).html(items.map(function (i) {
                    return `<div class="mb-2">
                        <div class="d-flex justify-content-between small">
                            <span class="text-truncate me-2">${i.etiqueta}</span>
                            <span class="fw-bold">${fmt(i.productos)}</span>
                        </div>
                        ${barra(i.productos, max, color)}
                    </div>`;
                }).join(''));
            }

            function renderTops() {
                var marcas = [], modelos = [];
                resumenData.marcas.forEach(function (ma) {
                    marcas.push({ etiqueta: esc(ma.nombre), productos: ma.productos });
                    ma.modelos.forEach(function (mo) {
                        modelos.push({
                            etiqueta: `<span class="text-muted">${esc(ma.nombre)}</span> ${esc(mo.nombre)}`,
                            productos: mo.productos
                        });
                    });
                });
                renderTop('#resumenTopMarcas', marcas, 'success');
                renderTop('#resumenTopModelos', modelos, 'info');
            }

            // Registros sin productos asignados (dashboard)
            var sinProdTipo = 'submodelos';

            function botonesSubmodelo(s) {
                return (s.botones || []).map(function (b) {
                    var clase = (b.bg_clase && b.text_clase) ? b.bg_clase + ' ' + b.text_clase
                        : (b.color_clase || 'btn-outline-primary');
                    var icono = b.icono_clase ? `<i class="${b.icono_clase}"></i>` : esc(b.nombre_funcion);
                    // Mismo formato que el listado: lo atiende el handler .btn-accion
                    return `<button type="button" class="btn btn-sm me-1 ${clase} btn-accion"
                        title="${esc(b.descripcion || b.nombre_funcion)}"
                        data-id="${s.id}" data-accion="${esc(b.accion_js)}"
                        data-confirmable="${b.es_confirmable || 0}"
                        data-submodelo-nombre="${esc(s.nombre)}">${icono}</button>`;
                }).join('');
            }

            function renderSinProductos() {
                if (!resumenData) return;
                var sp = resumenData.sin_productos;
                $('#cntSinSubmodelos').text(sp.submodelos.length);
                $('#cntSinModelos').text(sp.modelos.length);
                $('#cntSinMarcas').text(sp.marcas.length);

                var subsActivos = sp.submodelos.filter(function (s) { return s.estado === 'Activo'; }).length;
                var modsActivos = sp.modelos.filter(function (m) { return m.estado === 'Activo'; }).length;
                var marcasActivas = sp.marcas.filter(function (m) { return m.estado === 'Activo'; }).length;

                $('#btnInhabilitarSinSubmodelos').prop('disabled', subsActivos === 0)
                    .attr('title', subsActivos > 0 ? `Inhabilitar ${subsActivos} submodelos activos sin productos` : 'No hay submodelos activos sin productos');
                $('#btnInhabilitarSinModelos').prop('disabled', modsActivos === 0)
                    .attr('title', modsActivos > 0 ? `Inhabilitar ${modsActivos} modelos activos sin productos` : 'No hay modelos activos sin productos');
                $('#btnInhabilitarSinMarcas').prop('disabled', marcasActivas === 0)
                    .attr('title', marcasActivas > 0 ? `Inhabilitar ${marcasActivas} marcas activas sin productos` : 'No hay marcas activas sin productos');

                var q = $('#sinProdBuscar').val().trim().toLowerCase();
                var coincide = function () {
                    if (!q) return true;
                    return Array.prototype.some.call(arguments, function (t) {
                        return (t || '').toLowerCase().indexOf(q) !== -1;
                    });
                };
                var estado = function (r) {
                    return r.estado === 'Activo' ? `<span class="small">${esc(r.estado)}</span>`
                        : `<span class="badge text-bg-secondary">${esc(r.estado)}</span>`;
                };
                var sinDato = '<span class="text-muted">—</span>';
                var cab, filas, pie;

                if (sinProdTipo === 'submodelos') {
                    cab = '<th>Submodelo</th><th>Modelo</th><th>Marca</th><th>Estado</th><th class="text-center">Acciones</th>';
                    filas = sp.submodelos.filter(function (s) { return coincide(s.nombre, s.modelo, s.marca); })
                        .map(function (s) {
                            var modelo = s.huerfano
                                ? `<span class="badge text-bg-danger" title="El modelo asignado no existe">Sin modelo</span>`
                                  + (s.productos > 0 ? ` <span class="small text-muted">(${fmt(s.productos)} prod.)</span>` : '')
                                : esc(s.modelo);
                            return `<tr><td class="fw-medium">${esc(s.nombre)}</td><td>${modelo}</td>
                                <td>${s.marca ? esc(s.marca) : sinDato}</td><td>${estado(s)}</td>
                                <td class="text-center text-nowrap">${botonesSubmodelo(s)}</td></tr>`;
                        });
                    var huerfanos = sp.submodelos.filter(function (s) { return s.huerfano; }).length;
                    pie = 'Submodelos sin productos compatibles.' + (huerfanos
                        ? ` Incluye ${huerfanos} con un modelo inexistente, que no aparecen en el Listado ni en el Jerárquico: editarlos para reasignar el modelo o inhabilitarlos.`
                        : '');
                } else if (sinProdTipo === 'modelos') {
                    cab = '<th>Modelo</th><th>Marca</th><th class="text-end">Submodelos</th><th>Estado</th>';
                    filas = sp.modelos.filter(function (m) { return coincide(m.nombre, m.marca); })
                        .map(function (m) {
                            return `<tr><td class="fw-medium">${esc(m.nombre)}</td>
                                <td>${m.marca ? esc(m.marca) : sinDato}</td>
                                <td class="text-end">${fmt(m.hijos)}</td><td>${estado(m)}</td></tr>`;
                        });
                    pie = 'Modelos sin productos compatibles. Se pueden inhabilitar con el botón superior o gestionar desde la página de Modelos.';
                } else {
                    cab = '<th>Marca</th><th class="text-end">Modelos</th><th>Estado</th>';
                    filas = sp.marcas.filter(function (m) { return coincide(m.nombre); })
                        .map(function (m) {
                            return `<tr><td class="fw-medium">${esc(m.nombre)}</td>
                                <td class="text-end">${fmt(m.hijos)}</td><td>${estado(m)}</td></tr>`;
                        });
                    pie = 'Marcas sin productos compatibles. Se pueden inhabilitar con el botón superior o gestionar desde la página de Marcas.';
                }

                var columnas = (cab.match(/<th/g) || []).length;
                $('#sinProdCabecera').html(cab);
                $('#sinProdCuerpo').html(filas.length ? filas.join('')
                    : `<tr><td colspan="${columnas}" class="text-center text-muted py-3">
                        <i class="fas fa-check-circle text-success me-1"></i>Sin registros</td></tr>`);
                $('#sinProdPie').text(pie);
            }

            $(document).on('click', '.sin-prod-tab', function () {
                $('.sin-prod-tab').removeClass('active');
                $(this).addClass('active');
                sinProdTipo = $(this).data('tipo');
                renderSinProductos();
            });
            $('#sinProdBuscar').on('input', renderSinProductos);

            // Inhabilitar masivo sin productos (submodelos, modelos o marcas)
            $(document).on('click', '.btn-inhabilitar-sin-prod', function () {
                var tipo = $(this).data('tipo');
                var articulos = {
                    'submodelos': 'los submodelos',
                    'modelos': 'los modelos',
                    'marcas': 'las marcas'
                };
                var articulo = articulos[tipo] || tipo;

                Swal.fire({
                    title: `¿Inhabilitar ${articulo} sin productos?`,
                    html: `Esta acción cambiará a estado <strong>Inactivo</strong> todos ${articulo} activos que no tengan productos asignados.`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="fas fa-ban me-1"></i>Sí, inhabilitar',
                    cancelButtonText: 'Cancelar',
                    reverseButtons: true,
                    allowOutsideClick: false
                }).then((result) => {
                    if (result.isConfirmed) {
                        Swal.fire({
                            title: 'Procesando...',
                            text: `Inhabilitando ${articulo} sin productos asignados`,
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });

                        $.post('submodelos_ajax.php', {
                            accion: 'inhabilitar_sin_productos',
                            tipo: tipo,
                            empresa_idx: empresa_idx
                        }, function (res) {
                            if (res.success) {
                                tabla.ajax.reload();
                                cargarResumen();
                                Swal.fire({
                                    icon: res.afectados > 0 ? 'success' : 'info',
                                    title: res.afectados > 0 ? '¡Inhabilitación exitosa!' : 'Sin registros para inhabilitar',
                                    text: res.mensaje,
                                    confirmButtonText: 'Aceptar'
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: res.error || 'No se pudo completar la inhabilitación',
                                    confirmButtonText: 'Entendido'
                                });
                            }
                        }, 'json').fail(function () {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error de conexión',
                                text: 'Error al comunicarse con el servidor',
                                confirmButtonText: 'Entendido'
                            });
                        });
                    }
                });
            });

            function filaArbol(o) {
                var sangria = o.nivel * 1.5;
                var toggle = o.tieneHijos
                    ? `<a href="#" class="resumen-toggle text-reset me-1" data-key="${o.key}">
                           <i class="fas fa-${o.abierto ? 'minus' : 'plus'}-square"></i></a>`
                    : '<span class="d-inline-block me-1" style="width: 1em;"></span>';
                var inactivo = o.activo === false ? ' <span class="badge text-bg-secondary">Inactivo</span>' : '';
                var clases = ['fw-bold', '', 'text-muted'][o.nivel] || '';
                return `<tr class="${o.nivel === 0 ? 'table-light' : ''}">
                    <td style="padding-left: ${0.5 + sangria}rem;">
                        ${toggle}<i class="${o.icono} me-1 text-${o.color}"></i>
                        <span class="${clases}">${o.nombre}</span>${inactivo}
                    </td>
                    <td class="text-end fw-bold">${fmt(o.productos)}</td>
                    <td class="align-middle">${barra(o.productos, o.maximo, o.color)}</td>
                    <td class="text-center">${o.acciones || ''}</td>
                </tr>`;
            }

            // Botón editar del submodelo: solo si el motor de estados lo habilita
            function botonEditarArbol(s) {
                var b = s.boton_editar;
                if (!b) return '';
                var clase = (b.bg_clase && b.text_clase) ? b.bg_clase + ' ' + b.text_clase
                    : (b.color_clase || 'btn-outline-primary');
                var icono = b.icono_clase ? `<i class="${b.icono_clase}"></i>` : '<i class="fas fa-edit"></i>';
                return `<button type="button" class="btn btn-sm ${clase} btn-editar-arbol"
                    data-id="${s.id}" title="${esc(b.descripcion || b.nombre_funcion)}">${icono}</button>`;
            }

            function renderArbol() {
                if (!resumenData) return;
                var q = $('#resumenBuscar').val().trim().toLowerCase();
                var soloCon = $('#resumenSoloConProductos').is(':checked');
                var coincide = function (n) { return !q || n.toLowerCase().indexOf(q) !== -1; };
                var pasa = function (n) { return !soloCon || n > 0; };
                var totalCompat = resumenData.kpis.productos_con_compatibilidad;
                var filas = [];

                resumenData.marcas.forEach(function (ma) {
                    if (!pasa(ma.productos)) return;
                    var marcaCoincide = coincide(ma.nombre);

                    // Modelos visibles (y sus submodelos) según búsqueda
                    var modelosVis = [];
                    ma.modelos.forEach(function (mo) {
                        if (!pasa(mo.productos)) return;
                        var modeloCoincide = marcaCoincide || coincide(mo.nombre);
                        var subs = mo.submodelos.filter(function (s) {
                            return pasa(s.productos) && (modeloCoincide || coincide(s.nombre));
                        });
                        if (modeloCoincide || subs.length) {
                            modelosVis.push({ mo: mo, subs: subs, mostrarSinSub: modeloCoincide && mo.sin_submodelo > 0 });
                        }
                    });
                    if (!marcaCoincide && !modelosVis.length) return;

                    var keyMa = 'ma-' + ma.id;
                    var abiertoMa = !!q || resumenExpandidos.has(keyMa);
                    filas.push(filaArbol({
                        nivel: 0, key: keyMa, nombre: esc(ma.nombre), activo: ma.activo,
                        productos: ma.productos, maximo: totalCompat, color: 'success',
                        icono: 'fas fa-copyright', tieneHijos: modelosVis.length > 0, abierto: abiertoMa
                    }));
                    if (!abiertoMa) return;

                    modelosVis.forEach(function (v) {
                        var mo = v.mo;
                        var keyMo = 'mo-' + mo.id;
                        var tieneHijos = v.subs.length > 0 || v.mostrarSinSub;
                        var abiertoMo = !!q || resumenExpandidos.has(keyMo);
                        filas.push(filaArbol({
                            nivel: 1, key: keyMo, nombre: esc(mo.nombre), activo: mo.activo,
                            productos: mo.productos, maximo: ma.productos, color: 'info',
                            icono: 'fas fa-car', tieneHijos: tieneHijos, abierto: abiertoMo
                        }));
                        if (!abiertoMo) return;

                        if (v.mostrarSinSub) {
                            var keySs = 'ss-' + mo.id;
                            var abiertoSs = resumenExpandidos.has(keySs);
                            filas.push(filaArbol({
                                nivel: 2, key: keySs, nombre: '<em>Sin submodelo (aplica a todo el modelo)</em>',
                                productos: mo.sin_submodelo, maximo: mo.productos, color: 'secondary',
                                icono: 'fas fa-asterisk', tieneHijos: true, abierto: abiertoSs
                            }));
                            if (abiertoSs) filas.push(filasProductos(keySs));
                        }
                        v.subs.forEach(function (s) {
                            var keySm = 'sm-' + s.id;
                            var abiertoSm = s.productos > 0 && resumenExpandidos.has(keySm);
                            filas.push(filaArbol({
                                nivel: 2, key: keySm, nombre: esc(s.nombre), activo: s.activo,
                                productos: s.productos, maximo: mo.productos, color: 'warning',
                                icono: 'fas fa-layer-group', tieneHijos: s.productos > 0, abierto: abiertoSm,
                                acciones: botonEditarArbol(s)
                            }));
                            if (abiertoSm) filas.push(filasProductos(keySm));
                        });
                    });
                });

                $('#resumenArbol').html(filas.length ? filas.join('')
                    : '<tr><td colspan="4" class="text-center text-muted py-4">Sin resultados</td></tr>');
            }

            // Productos de las hojas (submodelo / sin submodelo), cacheados por key
            var resumenProductos = {};

            function filasProductos(key) {
                var sangria = 'padding-left: 5rem;';
                var lista = resumenProductos[key];
                if (lista === undefined) {
                    cargarProductosNodo(key);
                }
                if (lista === undefined || lista === 'cargando') {
                    return `<tr><td colspan="4" class="text-muted small" style="${sangria}">
                        <i class="fas fa-spinner fa-spin me-1"></i>Cargando productos...</td></tr>`;
                }
                if (lista === 'error') {
                    return `<tr><td colspan="4" class="text-danger small" style="${sangria}">
                        Error al cargar los productos</td></tr>`;
                }
                if (!lista.length) {
                    return `<tr><td colspan="4" class="text-muted small" style="${sangria}">Sin productos</td></tr>`;
                }
                // Productos como etiquetas: código + nombre
                var etiquetas = lista.map(function (p) {
                    var desc = (p.producto_descripcion || '').trim();
                    return `<span class="badge rounded-pill text-bg-light border fw-normal text-start text-wrap"
                        style="font-size: .8rem;" title="${esc(desc || p.producto_nombre)}">
                        <span class="font-monospace fw-bold">${esc(p.producto_codigo)}</span>
                        <span class="text-secondary">·</span> ${esc(p.producto_nombre)}
                    </span>`;
                }).join('');
                return `<tr><td colspan="4" style="${sangria}">
                    <div class="d-flex flex-wrap gap-1 py-1">${etiquetas}</div>
                </td></tr>`;
            }

            function cargarProductosNodo(key) {
                if (resumenProductos[key] !== undefined && resumenProductos[key] !== 'error') return;
                resumenProductos[key] = 'cargando';
                var partes = key.split('-');
                $.get('submodelos_ajax.php', {
                    accion: 'productos_nodo',
                    tipo: partes[0] === 'sm' ? 'submodelo' : 'sin_submodelo',
                    id: partes[1],
                    empresa_idx: empresa_idx
                }, function (res) {
                    resumenProductos[key] = (res && res.success) ? res.data : 'error';
                    renderArbol();
                }, 'json').fail(function () {
                    resumenProductos[key] = 'error';
                    renderArbol();
                });
            }

            $(document).on('click', '.resumen-toggle', function (e) {
                e.preventDefault();
                var key = $(this).data('key');
                if (resumenExpandidos.has(key)) {
                    resumenExpandidos.delete(key);
                } else {
                    resumenExpandidos.add(key);
                    if (key.indexOf('sm-') === 0 || key.indexOf('ss-') === 0) {
                        cargarProductosNodo(key);
                    }
                }
                renderArbol();
            });

            $('#resumenExpandir').click(function () {
                if (!resumenData) return;
                resumenData.marcas.forEach(function (ma) {
                    resumenExpandidos.add('ma-' + ma.id);
                    ma.modelos.forEach(function (mo) { resumenExpandidos.add('mo-' + mo.id); });
                });
                renderArbol();
            });

            $('#resumenColapsar').click(function () {
                resumenExpandidos.clear();
                renderArbol();
            });

            $('#resumenBuscar').on('input', renderArbol);
            $('#resumenSoloConProductos').on('change', renderArbol);
            $('.btn-recargar-resumen').click(function () {
                resumenProductos = {};
                cargarResumen();
            });

            // Editar submodelo desde el árbol: reutiliza el modal del listado
            $(document).on('click', '.btn-editar-arbol', function () {
                cargarSubmodeloParaEditar($(this).data('id'));
            });

            // Tras guardar/cambiar estado, refrescar el resumen si ya se cargó
            function refrescarResumenSiCargado() {
                if (resumenData) cargarResumen();
            }

            // Carga diferida: Dashboard y Jerárquico comparten los datos,
            // se piden la primera vez que se abre cualquiera de las dos
            $('.tab-resumen').on('shown.bs.tab', function () {
                if (!resumenData) cargarResumen();
            });
            $('#tab-listado-btn').on('shown.bs.tab', function () {
                tabla.columns.adjust();
            });

            // Inicializar (Jerárquico es la pestaña por defecto: cargar ya el resumen)
            cargarBotonAgregar();
            cargarResumen();
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>

</html>