<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Productos y Servicios";
$currentPage = 'productos_generales';
$modudo_idx = 2;
$pagina_idx = 97; // ID de página en conf__paginas (ver _local/sql/mantenimiento/20260925_productos_generales_abm.sql)
$ui_simple = true;
$empresa_idx = intval($_GET['empresa_id'] ?? $_SESSION['empresa_id'] ?? 0);

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <?php ui_encabezado('Productos y servicios','fas fa-cubes', '', ['Seteo Empresas']); ?>

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
                                        <div class="d-inline-block ms-2" style="min-width: 150px;">
                                            <select class="form-select form-select-sm" id="filtroEstado" title="Filtrar por estado">
                                            </select>
                                        </div>
                                        <div class="d-inline-block ms-2" style="min-width: 150px;">
                                            <select class="form-select form-select-sm" id="filtroTipo" title="Filtrar por tipo">
                                                <option value="">Todos los tipos</option>
                                            </select>
                                        </div>
                                        <div class="d-inline-block ms-2" style="min-width: 170px;">
                                            <select class="form-select form-select-sm" id="filtroServicio" title="Productos o servicios">
                                                <option value="">Productos y servicios</option>
                                                <option value="0">Solo productos</option>
                                                <option value="1">Solo servicios</option>
                                            </select>
                                        </div>
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
                                                    <li><a class="dropdown-item btn-exportar" href="#" data-boton="0"><i
                                                                class="fas fa-file-excel text-success"></i> Excel</a></li>
                                                    <li><a class="dropdown-item btn-exportar" href="#" data-boton="1"><i
                                                                class="fas fa-file-pdf text-danger"></i> PDF</a></li>
                                                    <li><hr class="dropdown-divider"></li>
                                                    <li><a class="dropdown-item btn-exportar" href="#" data-boton="2"><i
                                                                class="fas fa-file-csv text-primary"></i> CSV</a></li>
                                                    <li><a class="dropdown-item btn-exportar" href="#" data-boton="3"><i
                                                                class="fas fa-print text-secondary"></i> Imprimir</a></li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <!-- Búsqueda de texto: código + etiquetas (mismo buscador que productos.php) -->
                                        <div class="row g-2 mb-3">
                                            <div class="col-md-3">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                                    <input type="text" class="form-control" id="filtroCodigo" placeholder="Código...">
                                                    <span class="input-group-text" id="codigoSearchIcon" style="display:none;"><i class="fas fa-search text-primary"></i></span>
                                                </div>
                                            </div>
                                            <div class="col-md-9">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                                    <div class="form-control" id="buscadorTagsContainer">
                                                        <input type="text" id="buscadorTagsInput" placeholder="Escribí una palabra y presioná espacio...">
                                                    </div>
                                                    <button type="button" class="btn btn-outline-secondary" id="btnLimpiarTags" title="Limpiar todos los filtros">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <table id="tablaProductosGenerales" class="table table-striped table-bordered"
                                            style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="110">Código</th>
                                                    <th>Nombre</th>
                                                    <th width="100">Tipo</th>
                                                    <th>Categoría</th>
                                                    <th width="60" class="text-center">U.M.</th>
                                                    <th width="80" class="text-center">IVA</th>
                                                    <th width="70" class="text-center">Stock</th>
                                                    <th width="70" class="text-center">Compra</th>
                                                    <th width="70" class="text-center">Venta</th>
                                                    <th width="80" class="text-center">Estado</th>
                                                    <th width="110" class="text-center">Acciones</th>
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

            <!-- ========== MODALES ========== -->
            <!-- Modal para crear/editar producto o servicio -->
            <div class="modal fade" id="modalProductoGeneral" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true"
                data-bs-backdrop="static">
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header bg-gradient-primary text-white border-0">
                            <h5 class="modal-title" id="modalLabel">
                                <i class="fas fa-cubes me-2"></i>Producto
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formProductoGeneral" class="needs-validation" novalidate>
                                <input type="hidden" id="producto_id" name="producto_id" />

                                <nav>
                                    <div class="nav nav-tabs mb-3" role="tablist">
                                        <button class="nav-link active" id="tab-general-btn" data-bs-toggle="tab"
                                            data-bs-target="#tab-general" type="button" role="tab">
                                            <i class="fas fa-info-circle me-1"></i>Datos generales
                                        </button>
                                        <button class="nav-link" id="tab-caracteristicas-btn" data-bs-toggle="tab"
                                            data-bs-target="#tab-caracteristicas" type="button" role="tab">
                                            <i class="fas fa-ruler-combined me-1"></i>Características
                                        </button>
                                        <button class="nav-link" id="tab-fiscal-btn" data-bs-toggle="tab"
                                            data-bs-target="#tab-fiscal" type="button" role="tab">
                                            <i class="fas fa-file-invoice-dollar me-1"></i>Impuestos y contabilidad
                                        </button>
                                    </div>
                                </nav>

                                <div class="tab-content">
                                    <!-- Datos generales -->
                                    <div class="tab-pane fade show active" id="tab-general" role="tabpanel">
                                        <div class="row g-2">
                                            <div class="col-md-3">
                                                <label for="producto_codigo" class="form-label small mb-1">Código *</label>
                                                <input type="text" class="form-control form-control-sm"
                                                    id="producto_codigo" name="producto_codigo" maxlength="50" required>
                                                <div class="invalid-feedback">El código es obligatorio</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="producto_nombre" class="form-label small mb-1">Nombre *</label>
                                                <input type="text" class="form-control form-control-sm"
                                                    id="producto_nombre" name="producto_nombre" maxlength="150" required>
                                                <div class="invalid-feedback">El nombre es obligatorio</div>
                                            </div>
                                            <div class="col-md-3">
                                                <label for="codigo_barras" class="form-label small mb-1">Código de barras</label>
                                                <input type="text" class="form-control form-control-sm"
                                                    id="codigo_barras" name="codigo_barras" maxlength="150">
                                            </div>
                                        </div>

                                        <div class="row g-2 mt-1">
                                            <div class="col-md-4">
                                                <label for="producto_tipo_id" class="form-label small mb-1">Tipo *</label>
                                                <select class="form-select form-select-sm" id="producto_tipo_id" name="producto_tipo_id" required></select>
                                                <div class="invalid-feedback">El tipo es obligatorio</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="producto_categoria_id" class="form-label small mb-1">Categoría *</label>
                                                <select class="form-select form-select-sm" id="producto_categoria_id" name="producto_categoria_id" required></select>
                                                <div class="invalid-feedback">La categoría es obligatoria</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="unidad_medida_id" class="form-label small mb-1">Unidad de medida</label>
                                                <select class="form-select form-select-sm" id="unidad_medida_id" name="unidad_medida_id"></select>
                                            </div>
                                        </div>

                                        <div class="row g-2 mt-1">
                                            <div class="col-12">
                                                <label for="producto_descripcion" class="form-label small mb-1">Descripción</label>
                                                <textarea class="form-control form-control-sm" id="producto_descripcion"
                                                    name="producto_descripcion" rows="3"></textarea>
                                            </div>
                                        </div>

                                        <div class="row g-2 mt-2">
                                            <div class="col-md-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="es_servicio" name="es_servicio">
                                                    <label class="form-check-label" for="es_servicio">Es servicio</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="controla_stock" name="controla_stock" checked>
                                                    <label class="form-check-label" for="controla_stock">Controla stock</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="aplica_compra" name="aplica_compra" checked>
                                                    <label class="form-check-label" for="aplica_compra">Se compra</label>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="aplica_venta" name="aplica_venta" checked>
                                                    <label class="form-check-label" for="aplica_venta">Se vende</label>
                                                </div>
                                            </div>
                                        </div>
                                        <small class="text-muted d-block mt-1">Un servicio no controla stock.</small>
                                    </div>

                                    <!-- Características -->
                                    <div class="tab-pane fade" id="tab-caracteristicas" role="tabpanel">
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <label for="lado" class="form-label small mb-1">Lado</label>
                                                <input type="text" class="form-control form-control-sm" id="lado" name="lado" maxlength="10">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="material" class="form-label small mb-1">Material</label>
                                                <input type="text" class="form-control form-control-sm" id="material" name="material" maxlength="50">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="color" class="form-label small mb-1">Color</label>
                                                <input type="text" class="form-control form-control-sm" id="color" name="color" maxlength="50">
                                            </div>
                                        </div>
                                        <div class="row g-2 mt-1">
                                            <div class="col-md-4">
                                                <label for="peso" class="form-label small mb-1">Peso</label>
                                                <input type="number" class="form-control form-control-sm" id="peso" name="peso"
                                                    step="0.01" min="0" max="9999.99">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="dimensiones" class="form-label small mb-1">Dimensiones</label>
                                                <input type="text" class="form-control form-control-sm" id="dimensiones" name="dimensiones" maxlength="50">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="garantia" class="form-label small mb-1">Garantía</label>
                                                <input type="text" class="form-control form-control-sm" id="garantia" name="garantia" maxlength="50">
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Impuestos y contabilidad -->
                                    <div class="tab-pane fade" id="tab-fiscal" role="tabpanel">
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <label for="iva_alicuota_id" class="form-label small mb-1">Alícuota de IVA *</label>
                                                <select class="form-select form-select-sm" id="iva_alicuota_id" name="iva_alicuota_id" required></select>
                                                <div class="invalid-feedback">La alícuota de IVA es obligatoria</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="impuesto_interno_porcentaje" class="form-label small mb-1">Impuesto interno (%)</label>
                                                <input type="number" class="form-control form-control-sm" id="impuesto_interno_porcentaje"
                                                    name="impuesto_interno_porcentaje" step="0.01" min="0" max="100">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="impuesto_interno_fijo" class="form-label small mb-1">Impuesto interno fijo ($)</label>
                                                <input type="number" class="form-control form-control-sm" id="impuesto_interno_fijo"
                                                    name="impuesto_interno_fijo" step="0.01" min="0">
                                            </div>
                                        </div>

                                        <hr class="my-3">
                                        <h6 class="text-muted mb-2"><i class="fas fa-book me-1"></i>Cuentas contables</h6>
                                        <small class="text-muted d-block mb-2" id="ayudaCuentas">
                                            Al dar de alta, se completan con las cuentas por defecto del tipo elegido. Se pueden cambiar.
                                        </small>
                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <label for="cont_cuenta_compra_id" class="form-label small mb-1">Cuenta de compra</label>
                                                <select class="form-select form-select-sm select-cuenta" id="cont_cuenta_compra_id" name="cont_cuenta_compra_id"></select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="cont_cuenta_venta_id" class="form-label small mb-1">Cuenta de venta</label>
                                                <select class="form-select form-select-sm select-cuenta" id="cont_cuenta_venta_id" name="cont_cuenta_venta_id"></select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="cont_cuenta_cmv_id" class="form-label small mb-1">Cuenta de CMV <?= ui_ayuda('Solo para productos que controlan stock.') ?></label>
                                                <select class="form-select form-select-sm select-cuenta" id="cont_cuenta_cmv_id" name="cont_cuenta_cmv_id"></select>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-sm btn-secondary px-3" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-sm btn-primary px-3" id="btnGuardar">Guardar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ========== ESTILOS ========== -->
            <style>
                /* Letra más chica en el listado, filtros y formulario */
                #tablaProductosGenerales,
                #tablaProductosGenerales .badge { font-size: 0.78rem; }
                #tablaProductosGenerales .btn-xs { font-size: 0.72rem; padding: 0.1rem 0.35rem; }
                .card-header .form-select-sm, .card-header .btn,
                .card-body .input-group-sm .form-control, .card-body .input-group-sm .input-group-text,
                .card-body .input-group-sm .btn,
                .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_info,
                .dataTables_wrapper .dataTables_paginate { font-size: 0.8rem; }
                /* Pie del listado: cantidad a la izquierda, info al medio, paginado a la derecha */
                .dataTables_wrapper .dataTables_info { text-align: center; }
                .dataTables_wrapper .dataTables_paginate .pagination { justify-content: flex-end; }
                #modalProductoGeneral .modal-body { font-size: 0.85rem; }
                #modalProductoGeneral .form-control-sm, #modalProductoGeneral .form-select-sm { font-size: 0.8rem; }

                /* Buscador por etiquetas (mismo diseño que productos.php, en tamaño chico) */
                #buscadorTagsContainer {
                    display: flex; flex-wrap: wrap; align-items: center; gap: 4px;
                    min-height: 31px; padding: 2px 6px; cursor: text; background-color: #fff;
                    transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
                }
                #buscadorTagsContainer:focus-within { border-color: #86b7fe; box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25); }
                #buscadorTagsInput { flex: 1; min-width: 80px; border: none; outline: none; background: transparent; padding: 2px 0; font-size: 0.8rem; }
                .tag-item {
                    display: inline-flex; align-items: center; max-width: 200px; white-space: nowrap;
                    background-color: #e9ecef; border: 1px solid #dee2e6; border-radius: 16px;
                    padding: 1px 6px 1px 10px; font-size: 0.75rem; font-weight: 500; color: #212529;
                }
                .tag-item:hover { background-color: #dee2e6; }
                .tag-item .tag-text { max-width: 150px; overflow: hidden; text-overflow: ellipsis; }
                .tag-item .tag-remove {
                    display: inline-flex; align-items: center; justify-content: center;
                    width: 16px; height: 16px; margin-left: 4px; border-radius: 50%;
                    cursor: pointer; color: #6c757d; font-size: 0.7rem;
                }
                .tag-item .tag-remove:hover { color: #dc3545; background-color: rgba(220, 53, 69, 0.1); }
            </style>

            <!-- Librerías necesarias -->
            <link rel="stylesheet" type="text/css" href="<?= asset_local('css/datatables-buttons-2.4.1/buttons.dataTables.min.css') ?>">
            <script src="<?= asset_local('js/datatables-buttons-2.4.1/dataTables.buttons.min.js') ?>"></script>
            <script src="<?= asset_local('js/jszip-3.10.1/jszip.min.js') ?>"></script>
            <script src="<?= asset_local('js/pdfmake-0.1.53/pdfmake.min.js') ?>"></script>
            <script src="<?= asset_local('js/pdfmake-0.1.53/vfs_fonts.js') ?>"></script>
            <script src="<?= asset_local('js/datatables-buttons-2.4.1/buttons.html5.min.js') ?>"></script>
            <script src="<?= asset_local('js/datatables-buttons-2.4.1/buttons.print.min.js') ?>"></script>


            <script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>

            <script>
                const PAGINA_ID = <?= intval($pagina_idx) ?>;
                const EMPRESA_ID = <?= intval($empresa_idx) ?>;
            </script>
            <script src="productos_generales.js?v=<?= filemtime(__DIR__ . '/productos_generales.js') ?>"></script>
        </div>
    </div>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
