<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Puntos de Venta";
$currentPage = 'puntos_venta';
$modudo_idx = 2;
$pagina_idx = 70;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-cash-register me-2"></i>Puntos de Venta
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Configuración</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Puntos de Venta</li>
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
                                        <div class="table-responsive">
                                            <table id="tablaPuntosVenta" class="table table-striped table-bordered" style="width:100%">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th width="80">ID</th>

                                                        <th width="150">Sucursal</th>
                                                        <th width="150">Boca</th>
                                                        <th width="200">Nombre</th>
                                                        <th width="250">Descripción</th>
                                                        <th width="120">Código Fiscal</th>
                                                        <th width="80">Web</th>
                                                        <th width="120">Estado</th>
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
                    </div>
                </section>
            </div>

            <!-- Modal principal -->
            <div class="modal fade" id="modalPuntoVenta" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered modal-ancho-maximo">
                    <div class="modal-content">
                        <div class="modal-header py-2 position-relative">
                            <h5 class="modal-title" id="modalLabel">Punto de Venta</h5>
                            <div class="position-absolute top-0 end-0 mt-2 me-2 d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnToggleFullscreen" title="Pantalla completa">
                                    <i class="fas fa-expand"></i>
                                </button>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                        </div>
                        <div class="modal-body p-3">
                            <form id="formPuntoVenta" class="needs-validation" novalidate>
                                <input type="hidden" id="punto_venta_id" name="punto_venta_id" />

                                <ul class="nav nav-tabs" id="tabsPuntoVenta" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="tab-datos-btn" data-bs-toggle="tab" data-bs-target="#tab-datos" type="button" role="tab" aria-controls="tab-datos" aria-selected="true">
                                            Datos
                                        </button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="tab-comprobantes-btn" data-bs-toggle="tab" data-bs-target="#tab-comprobantes" type="button" role="tab" aria-controls="tab-comprobantes" aria-selected="false">
                                            Comprobantes <span class="badge bg-secondary ms-1" id="badgeComprobantesCount">0</span>
                                        </button>
                                    </li>
                                </ul>

                                <div class="tab-content pt-3">
                                    <div class="tab-pane fade show active" id="tab-datos" role="tabpanel" aria-labelledby="tab-datos-btn">
                                        <div class="row mb-2">
                                            <div class="col-md-6 mb-2">
                                                <label for="sucursal_id" class="form-label small mb-1">Sucursal *</label>
                                                <select class="form-select form-select-sm" id="sucursal_id" name="sucursal_id" required>
                                                    <option value="">Seleccionar sucursal</option>
                                                </select>
                                                <div class="invalid-feedback small">Seleccione una sucursal</div>
                                            </div>
                                            <div class="col-md-6 mb-2">
                                                <label for="boca_id" class="form-label small mb-1">Boca *</label>
                                                <select class="form-select form-select-sm" id="boca_id" name="boca_id" required disabled>
                                                    <option value="">Seleccione una sucursal primero</option>
                                                </select>
                                                <div class="invalid-feedback small">Seleccione una boca</div>
                                            </div>
                                        </div>

                                        <div class="row mb-2">
                                            <div class="col-12 mb-2">
                                                <label for="nombre" class="form-label small mb-1">Nombre *</label>
                                                <input type="text" class="form-control form-control-sm" id="nombre" name="nombre" maxlength="100" required>
                                                <div class="invalid-feedback small">El nombre es obligatorio</div>
                                            </div>
                                        </div>

                                        <div class="row mb-2">
                                            <div class="col-md-6 mb-2">
                                                <label for="codigo_fiscal" class="form-label small mb-1">Código Fiscal</label>
                                                <input type="number" class="form-control form-control-sm no-spinner" id="codigo_fiscal" name="codigo_fiscal" min="0" step="1">
                                            </div>
                                            <div class="col-md-6 mb-2 d-flex align-items-center">
                                                <div class="form-check form-switch mt-4">
                                                    <input class="form-check-input" type="checkbox" id="es_web" name="es_web">
                                                    <label class="form-check-label small" for="es_web">Es el punto de venta web</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row mb-2">
                                            <div class="col-12 mb-2">
                                                <label for="descripcion" class="form-label small mb-1">Descripción</label>
                                                <textarea class="form-control form-control-sm" id="descripcion" name="descripcion" rows="2" maxlength="255"></textarea>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="tab-pane fade" id="tab-comprobantes" role="tabpanel" aria-labelledby="tab-comprobantes-btn">
                                        <div class="card card-info card-outline mb-2">
                                            <div class="card-header py-1 bg-info bg-opacity-10">
                                                <h6 class="mb-0 small"><i class="fas fa-plus-circle me-2"></i>Agregar tipo de comprobante</h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                                    <input type="text" class="form-control" id="busqueda_comprobante_tipo" autocomplete="off" placeholder="Buscar por nombre, código o letra...">
                                                </div>
                                                <div id="resultados_busqueda_comprobantes" class="mt-2"></div>
                                            </div>
                                        </div>

                                        <label class="form-label small mb-1">Tipos de comprobante habilitados</label>
                                        <div style="max-width: 600px;">
                                            <div class="table-responsive" style="min-height: 440px; border: 1px solid #dee2e6; border-radius: 0.25rem;">
                                                <table class="table table-sm table-hover mb-0" id="tablaComprobantesSeleccionados" style="width: auto; max-width: 600px;">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th>Subgrupo / Tipo</th>
                                                            <th width="70" class="text-center">Código</th>
                                                            <th width="60" class="text-center">Letra</th>
                                                            <th width="110" class="text-center">Requiere AFIP</th>
                                                            <th width="50" class="text-center">Quitar</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <tr>
                                                            <td colspan="5" class="text-center text-muted small py-2">Cargando...</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center mt-1">
                                                <div class="form-text small mb-0">Solo los tipos de esta lista quedan habilitados para el punto de venta</div>
                                                <nav id="paginacionComprobantesSeleccionados" aria-label="Paginación de tipos de comprobante habilitados"></nav>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary btn-sm" id="btnGuardar">
                                <i class="fas fa-save me-1"></i>Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Quitar flechitas de los inputs number */
        .no-spinner::-webkit-inner-spin-button,
        .no-spinner::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .no-spinner {
            -moz-appearance: textfield;
            appearance: textfield;
        }

        /* Estilo para pantalla completa */
        .modal-fullscreen {
            max-width: 100%;
            margin: 0;
            height: 100vh;
        }

        .modal-fullscreen .modal-content {
            height: 100vh;
            border-radius: 0;
        }

        .modal-fullscreen .modal-body {
            overflow-y: auto;
        }

        /* Modal lo más ancho posible en pantallas grandes: modal-xl (1140px)
           se queda corto en monitores anchos. Por debajo de 576px, Bootstrap
           ya hace que .modal-dialog ocupe el ancho completo con margen fijo
           (sin usar max-width), así que esta regla no interfiere con mobile. */
        @media (min-width: 576px) {
            .modal-ancho-maximo:not(.modal-fullscreen) {
                max-width: 96vw;
            }
        }

        .table-sm td, .table-sm th {
            padding: 0.5rem;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }

        /* Estilos de validación */
        .was-validated .form-control:valid,
        .was-validated .form-select:valid,
        .form-control.is-valid,
        .form-select.is-valid {
            border-color: #28a745 !important;
            background-image: none !important;
            padding-right: 0.75rem !important;
        }

        .was-validated .form-control:invalid,
        .was-validated .form-select:invalid,
        .form-control.is-invalid,
        .form-select.is-invalid {
            border-color: #dc3545 !important;
            background-image: none !important;
            padding-right: 0.75rem !important;
        }

        .invalid-feedback {
            display: none;
            color: #dc3545;
            font-size: 80%;
        }

        .was-validated .form-control:invalid ~ .invalid-feedback,
        .was-validated .form-select:invalid ~ .invalid-feedback,
        .form-control.is-invalid ~ .invalid-feedback,
        .form-select.is-invalid ~ .invalid-feedback {
            display: block;
        }
    </style>

    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script src="puntos_venta.js?v=<?= filemtime(__DIR__.'/puntos_venta.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>