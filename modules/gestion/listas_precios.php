<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pagina_id = isset($_GET['pagina_id']) ? intval($_GET['pagina_id']) : 53;
$empresa_id = isset($_GET['empresa_id']) ? intval($_GET['empresa_id']) : 2;
$modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : 2;

$pageTitle = "Listas de Precios";
$currentPage = 'listas_precios';

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-tags me-2"></i>Listas de Precios
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Productos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Listas de Precios</li>
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

                                    <div class="card-body p-0">
                                        <table id="tablaListasPrecios" class="table table-striped table-bordered" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="50">ID</th>
                                                    <th width="100">Código</th>
                                                    <th width="250">Nombre</th>
                                                    <th width="100">Origen</th>
                                                    <th width="80">Moneda</th>
                                                    <th width="100">Lista Base</th>
                                                    <th width="100">Requiere Recalculo</th>
                                                    <th width="120">F. Recalculo</th>
                                                    <th width="100">Estado</th>
                                                    <th width="200" class="text-center">Acciones</th>
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

            <!-- Modal de Lista de Precio con TABS internas -->
            <div class="modal fade" id="modalListaPrecio" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content">
                        <div class="modal-header py-2" style="background-color: #f8f9fa; border-bottom: 2px solid #dee2e6;">
                            <h5 class="modal-title" id="modalLabel">
                                <i class="fas fa-tags me-2 text-primary"></i>Lista de Precio
                            </h5>
                            <div class="d-flex align-items-center">
                                <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="btnToggleFullscreen" title="Pantalla completa">
                                    <i class="fas fa-expand"></i>
                                </button>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                        </div>
                        <div class="modal-body p-0">
                            <div class="card mb-0 border-0">
                                <div class="card-header py-2 bg-primary bg-opacity-10">
                                    <ul class="nav nav-tabs card-header-tabs" id="listaPrecioTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="datos-tab" data-bs-toggle="tab" 
                                                data-bs-target="#datosContent" type="button" role="tab">
                                                <i class="fas fa-info-circle me-1"></i>Datos
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="reglas-tab" data-bs-toggle="tab" 
                                                data-bs-target="#reglasContent" type="button" role="tab">
                                                <i class="fas fa-cogs me-1"></i>Reglas
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="productos-tab" data-bs-toggle="tab" 
                                                data-bs-target="#productosContent" type="button" role="tab">
                                                <i class="fas fa-boxes me-1"></i>Productos
                                            </button>
                                        </li>
                                    </ul>
                                </div>
                                <div class="card-body p-3 tab-content">
                                    <!-- TAB DATOS -->
                                    <div class="tab-pane fade show active" id="datosContent" role="tabpanel">
                                        <form id="formListaPrecio" class="needs-validation" novalidate>
                                            <input type="hidden" id="lista_precio_id" name="lista_precio_id" />
                                            
                                            <div class="row mb-2">
                                                <div class="col-md-6">
                                                    <label class="form-label small mb-1">Código *</label>
                                                    <input type="text" class="form-control form-control-sm" 
                                                           id="lista_precio_codigo" name="lista_precio_codigo" required maxlength="50">
                                                    <div class="invalid-feedback small">Código obligatorio</div>
                                                </div>
                                                <div class="col-md-6">
                                                    <label class="form-label small mb-1">Nombre *</label>
                                                    <input type="text" class="form-control form-control-sm" 
                                                           id="lista_precio_nombre" name="lista_precio_nombre" required maxlength="150">
                                                    <div class="invalid-feedback small">Nombre obligatorio</div>
                                                </div>
                                            </div>

                                            <div class="row mb-2">
                                                <div class="col-md-12">
                                                    <label class="form-label small mb-1">Descripción</label>
                                                    <textarea class="form-control form-control-sm" id="descripcion" 
                                                              name="descripcion" rows="2" maxlength="65535"></textarea>
                                                </div>
                                            </div>

                                            <div class="row mb-2">
                                                <div class="col-md-4">
                                                    <label class="form-label small mb-1">Origen *</label>
                                                    <select class="form-select form-select-sm" id="lista_precio_origen_id" name="lista_precio_origen_id" required>
                                                        <option value="">Seleccionar origen</option>
                                                    </select>
                                                    <div class="invalid-feedback small">Seleccione un origen</div>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small mb-1">Moneda</label>
                                                    <select class="form-select form-select-sm" id="moneda_id" name="moneda_id">
                                                        <option value="">Seleccionar moneda</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small mb-1">Lista Base</label>
                                                    <select class="form-select form-select-sm" id="lista_base_id" name="lista_base_id">
                                                        <option value="">Sin lista base</option>
                                                    </select>
                                                </div>
                                            </div>

                                            <div class="row mb-2">
                                                <div class="col-md-4">
                                                    <label class="form-label small mb-1">Observaciones</label>
                                                    <textarea class="form-control form-control-sm" id="observaciones" 
                                                              name="observaciones" rows="2" maxlength="65535"></textarea>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="form-check mt-4">
                                                        <input type="checkbox" class="form-check-input" id="requiere_recalculo" name="requiere_recalculo" value="1">
                                                        <label class="form-check-label small">Requiere Recalculo</label>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label small mb-1">Fecha Último Recalculo</label>
                                                    <input type="datetime-local" class="form-control form-control-sm" id="f_ultimo_recalculo" name="f_ultimo_recalculo">
                                                </div>
                                            </div>

                                            <div class="row mt-3">
                                                <div class="col-12 text-end">
                                                    <button type="button" class="btn btn-secondary btn-sm px-4 me-2" data-bs-dismiss="modal">
                                                        <i class="fas fa-times me-1"></i>Cancelar
                                                    </button>
                                                    <button type="button" class="btn btn-primary btn-sm px-4" id="btnGuardar">
                                                        <i class="fas fa-save me-1"></i>Guardar
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                    </div>

                                    <!-- TAB REGLAS -->
                                    <div class="tab-pane fade" id="reglasContent" role="tabpanel">
                                        <div class="mb-3">
                                            <div class="row mb-2">
                                                <div class="col-md-8">
                                                    <small class="text-muted">Reglas aplicables a esta lista de precios</small>
                                                </div>
                                                <div class="col-md-4 text-end">
                                                    <button type="button" class="btn btn-sm btn-success" id="btnNuevaRegla">
                                                        <i class="fas fa-plus me-1"></i>Nueva Regla
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="table-responsive">
                                            <table id="tablaReglas" class="table table-striped table-bordered table-sm" style="width:100%">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th width="50">ID</th>
                                                        <th width="200">Nombre</th>
                                                        <th width="100">Tipo</th>
                                                        <th width="100">Valor Tipo</th>
                                                        <th width="100">Valor Ajuste</th>
                                                        <th width="60">Prioridad</th>
                                                        <th width="90">Vigencia Desde</th>
                                                        <th width="90">Vigencia Hasta</th>
                                                        <th width="70">Promoción</th>
                                                        <th width="100">Estado</th>
                                                        <th width="120" class="text-center">Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody></tbody>
                                            </table>
                                        </div>
                                    </div>

                                    <!-- TAB PRODUCTOS -->
                                    <div class="tab-pane fade" id="productosContent" role="tabpanel">
                                        <div class="mb-3">
                                            <div class="row">
                                                <div class="col-md-8">
                                                    <small class="text-muted">Productos con precios calculados para esta lista</small>
                                                </div>
                                                <div class="col-md-4 text-end">
                                                    <button type="button" class="btn btn-sm btn-warning" id="btnRecalcularPrecios">
                                                        <i class="fas fa-sync-alt me-1"></i>Recalcular Precios
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="table-responsive">
                                            <table id="tablaProductosLista" class="table table-striped table-bordered table-sm" style="width:100%">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th width="50">ID</th>
                                                        <th width="100">Código</th>
                                                        <th width="250">Producto</th>
                                                        <th width="120">Precio Origen</th>
                                                        <th width="100">% General</th>
                                                        <th width="120">Importe General</th>
                                                        <th width="120">Precio Final</th>
                                                        <th width="90">Vigencia Desde</th>
                                                        <th width="90">Vigencia Hasta</th>
                                                        <th width="100">Estado</th>
                                                        <th width="100" class="text-center">Acciones</th>
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
                </div>
            </div>

            <!-- Modal para editar precio manual -->
            <div class="modal fade" id="modalPrecioManual" tabindex="-1" aria-labelledby="precioManualLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header py-2 bg-warning bg-opacity-10">
                            <h5 class="modal-title" id="precioManualLabel">
                                <i class="fas fa-hand-holding-usd me-2 text-warning"></i>Editar Precio Manual
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formPrecioManual">
                                <input type="hidden" id="precio_manual_lista_precio_producto_id">
                                <div class="mb-3">
                                    <label class="form-label">Producto</label>
                                    <input type="text" class="form-control" id="precio_manual_producto_nombre" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Precio Actual</label>
                                    <input type="text" class="form-control" id="precio_actual_mostrar" readonly>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Nuevo Precio Manual *</label>
                                    <input type="number" class="form-control no-spinner" id="precio_manual" step="0.000001" required>
                                    <div class="invalid-feedback">Ingrese un precio válido</div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Observaciones</label>
                                    <textarea class="form-control" id="precio_manual_observaciones" rows="2"></textarea>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer py-2">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-warning btn-sm" id="btnGuardarPrecioManual">Guardar Precio Manual</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Regla (independiente pero vinculado) -->
            <div class="modal fade" id="modalRegla" tabindex="-1" aria-labelledby="reglaModalLabel">
                <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content">
                        <div class="modal-header py-2 bg-info bg-opacity-10">
                            <h5 class="modal-title" id="reglaModalLabel">
                                <i class="fas fa-cogs me-2 text-info"></i>Regla de Lista de Precio
                            </h5>
                            <div class="d-flex align-items-center">
                                <button type="button" class="btn btn-sm btn-outline-secondary me-2" id="btnToggleFullscreenRegla" title="Pantalla completa">
                                    <i class="fas fa-expand"></i>
                                </button>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                        </div>
                        <div class="modal-body p-3">
                            <form id="formRegla" class="needs-validation" novalidate>
                                <input type="hidden" id="lista_precio_regla_id" name="lista_precio_regla_id" />
                                <input type="hidden" id="regla_lista_precio_id" name="lista_precio_id" />
                                
                                <div class="card mb-3 border-info">
                                    <div class="card-header py-2 bg-info bg-opacity-10">
                                        <h6 class="mb-0 text-info"><i class="fas fa-info-circle me-2"></i>Datos de la Regla</h6>
                                    </div>
                                    <div class="card-body py-2">
                                        <div class="row mb-2">
                                            <div class="col-md-12">
                                                <label class="form-label small mb-1">Nombre *</label>
                                                <input type="text" class="form-control form-control-sm" 
                                                    id="regla_nombre" name="regla_nombre" required maxlength="150">
                                                <div class="invalid-feedback small">Nombre obligatorio</div>
                                            </div>
                                        </div>
                                        <div class="row mb-2">
                                            <div class="col-md-12">
                                                <label class="form-label small mb-1">Descripción</label>
                                                <textarea class="form-control form-control-sm" id="regla_descripcion" 
                                                        name="descripcion" rows="2"></textarea>
                                            </div>
                                        </div>

                                        <div class="row mb-2">
                                            <div class="col-md-4">
                                                <label class="form-label small mb-1">Tipo de Valor *</label>
                                                <select class="form-select form-select-sm" id="lista_precio_regla_valor_tipo_id" name="lista_precio_regla_valor_tipo_id" required>
                                                    <option value="">Seleccionar tipo valor</option>
                                                </select>
                                                <div class="invalid-feedback small">Seleccione un tipo de valor</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small mb-1">Valor Ajuste *</label>
                                                <input type="number" class="form-control form-control-sm" 
                                                    id="valor_ajuste" name="valor_ajuste" step="0.000001" required>
                                                <div class="invalid-feedback small">Valor obligatorio</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label class="form-label small mb-1">Prioridad</label>
                                                <input type="number" class="form-control form-control-sm" 
                                                    id="prioridad" name="prioridad" value="100" min="1" max="99999">
                                            </div>
                                        </div>

                                        <div class="row mb-2">
                                            <div class="col-md-6">
                                                <label class="form-label small mb-1">Vigencia Desde *</label>
                                                <input type="date" class="form-control form-control-sm" id="f_desde" required>
                                                <div class="invalid-feedback small">Fecha desde obligatoria</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label small mb-1">Vigencia Hasta</label>
                                                <input type="date" class="form-control form-control-sm" id="f_hasta">
                                            </div>
                                        </div>

                                        <div class="row mb-2">
                                            <div class="col-md-12">
                                                <div class="card border-secondary">
                                                    <div class="card-header py-1 bg-secondary bg-opacity-10">
                                                        <h6 class="mb-0 small">Alcance de la Regla (dejar vacío para aplicar a todos)</h6>
                                                    </div>
                                                    <div class="card-body py-2">
                                                        <div class="row mb-2">
                                                            <div class="col-md-4">
                                                                <label class="form-label small mb-1">Producto</label>
                                                                <select class="form-select form-select-sm" id="producto_id" name="producto_id">
                                                                    <option value="">Todos los productos</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label small mb-1">Marca</label>
                                                                <select class="form-select form-select-sm" id="marca_id" name="marca_id">
                                                                    <option value="">Todas las marcas</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label small mb-1">Modelo</label>
                                                                <select class="form-select form-select-sm" id="modelo_id" name="modelo_id">
                                                                    <option value="">Seleccione una marca primero</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="row mb-2">
                                                            <div class="col-md-4">
                                                                <label class="form-label small mb-1">Submodelo</label>
                                                                <select class="form-select form-select-sm" id="submodelo_id" name="submodelo_id">
                                                                    <option value="">Seleccione un modelo primero</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label small mb-1">Categoría</label>
                                                                <select class="form-select form-select-sm" id="producto_categoria_id" name="producto_categoria_id">
                                                                    <option value="">Todas las categorías</option>
                                                                </select>
                                                            </div>
                                                            <div class="col-md-4">
                                                                <label class="form-label small mb-1">Tipo de Producto</label>
                                                                <select class="form-select form-select-sm" id="producto_tipo_id" name="producto_tipo_id">
                                                                    <option value="">Todos los tipos</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                        <div class="row">
                                                            <div class="col-md-12">
                                                                <label class="form-label small mb-1">Entidad (Cliente/Proveedor)</label>
                                                                <select class="form-select form-select-sm" id="entidad_id" name="entidad_id">
                                                                    <option value="">Todas las entidades</option>
                                                                </select>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row mb-2">
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input" id="es_promocion" name="es_promocion" value="1">
                                                    <label class="form-check-label small">Es Promoción</label>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check">
                                                    <input type="checkbox" class="form-check-input" id="permite_acumulacion" name="permite_acumulacion" value="1">
                                                    <label class="form-check-label small">Permite Acumulación</label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="row mt-3">
                                    <div class="col-12 text-center">
                                        <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">
                                            <i class="fas fa-times me-1"></i>Cancelar
                                        </button>
                                        <button type="button" class="btn btn-primary btn-sm px-4" id="btnGuardarRegla">
                                            <i class="fas fa-save me-1"></i>Guardar Regla
                                        </button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .modal-dialog {
            max-width: 900px;
            width: 95%;
            margin: 1.75rem auto;
        }

        .modal-xl {
            max-width: 1200px;
        }

        .modal-fullscreen .modal-dialog {
            margin: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }

        .modal-fullscreen .modal-body {
            max-height: calc(100vh - 120px) !important;
        }

        .no-spinner::-webkit-inner-spin-button,
        .no-spinner::-webkit-outer-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .no-spinner {
            -moz-appearance: textfield;
            appearance: textfield;
        }

        .was-validated .form-control:valid,
        .was-validated .form-select:valid {
            border-color: #28a745 !important;
        }

        .was-validated .form-control:invalid,
        .was-validated .form-select:invalid {
            border-color: #dc3545 !important;
        }

        .invalid-feedback {
            display: none;
        }

        .was-validated .form-control:invalid ~ .invalid-feedback,
        .was-validated .form-select:invalid ~ .invalid-feedback {
            display: block;
        }

        .nav-tabs .nav-link {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
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
    
    <script>
        const empresa_id = <?php echo $empresa_id; ?>;
        const pagina_id = <?php echo $pagina_id; ?>;
        const modulo_id = <?php echo $modulo_id; ?>;
    </script>
    <script src="listas_precios.js?v=<?= filemtime(__DIR__.'/listas_precios.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>