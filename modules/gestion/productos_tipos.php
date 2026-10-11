<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Tipos de Producto";
$currentPage = 'productos_tipos';
$modudo_idx = 2;
$pagina_idx = 96; // ID de página en conf__paginas (ver sql/mantenimiento/20260925_productos_tipos_abm.sql)
$empresa_idx = intval($_GET['empresa_id'] ?? $_SESSION['empresa_id'] ?? 0);

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-shapes me-2"></i>Tipos de Producto
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Gestión</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Tipos de Producto</li>
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
                                        <div class="d-inline-block ms-2" style="min-width: 160px;">
                                            <select class="form-select form-select-sm" id="filtroEstado" title="Filtrar por estado">
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
                                        <table id="tablaProductosTipos" class="table table-striped table-bordered"
                                            style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="90">Código</th>
                                                    <th>Tipo</th>
                                                    <th width="90" class="text-center">Stock</th>
                                                    <th width="90" class="text-center">Compuesto</th>
                                                    <th width="90" class="text-center">Factura</th>
                                                    <th>Cta. Compra</th>
                                                    <th>Cta. Venta</th>
                                                    <th>Cta. CMV</th>
                                                    <th width="80" class="text-center">Productos</th>
                                                    <th width="90" class="text-center">Estado</th>
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
            <!-- Modal para crear/editar tipo de producto -->
            <div class="modal fade" id="modalProductoTipo" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true"
                data-bs-backdrop="static">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header bg-gradient-primary text-white border-0">
                            <h5 class="modal-title" id="modalLabel">
                                <i class="fas fa-shapes me-2"></i>Tipo de Producto
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formProductoTipo" class="needs-validation" novalidate>
                                <input type="hidden" id="producto_tipo_id" name="producto_tipo_id" />

                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label for="producto_tipo_codigo" class="form-label small mb-1">Código *</label>
                                        <input type="text" class="form-control form-control-sm text-uppercase"
                                            id="producto_tipo_codigo" name="producto_tipo_codigo" maxlength="20" required>
                                        <div class="invalid-feedback">El código es obligatorio</div>
                                    </div>
                                    <div class="col-md-8">
                                        <label for="producto_tipo" class="form-label small mb-1">Nombre *</label>
                                        <input type="text" class="form-control form-control-sm"
                                            id="producto_tipo" name="producto_tipo" maxlength="100" required>
                                        <div class="invalid-feedback">El nombre es obligatorio</div>
                                    </div>
                                </div>

                                <div class="row g-2 mt-2">
                                    <div class="col-md-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="maneja_stock" name="maneja_stock">
                                            <label class="form-check-label" for="maneja_stock">Maneja stock</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="es_compuesto" name="es_compuesto">
                                            <label class="form-check-label" for="es_compuesto">Es compuesto</label>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="afecta_facturacion" name="afecta_facturacion" checked>
                                            <label class="form-check-label" for="afecta_facturacion">Afecta facturación</label>
                                        </div>
                                    </div>
                                </div>

                                <hr class="my-3">
                                <h6 class="text-muted mb-2"><i class="fas fa-book me-1"></i>Cuentas contables por defecto</h6>
                                <small class="text-muted d-block mb-2">
                                    Se copian al producto en el momento del alta (según su tipo). Cambiarlas acá no modifica productos ya existentes.
                                </small>
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label for="cont_cuenta_compra_default_id" class="form-label small mb-1">Cuenta de compra</label>
                                        <select class="form-select form-select-sm select-cuenta" id="cont_cuenta_compra_default_id" name="cont_cuenta_compra_default_id"></select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="cont_cuenta_venta_default_id" class="form-label small mb-1">Cuenta de venta</label>
                                        <select class="form-select form-select-sm select-cuenta" id="cont_cuenta_venta_default_id" name="cont_cuenta_venta_default_id"></select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="cont_cuenta_cmv_default_id" class="form-label small mb-1">Cuenta de CMV</label>
                                        <select class="form-select form-select-sm select-cuenta" id="cont_cuenta_cmv_default_id" name="cont_cuenta_cmv_default_id"></select>
                                        <small class="text-muted">Solo se aplica a productos que controlan stock.</small>
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

            <!-- Librerías necesarias -->
            <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
            <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
            <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
            <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
            <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

            <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
            <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
            <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

            <script>
                const PAGINA_ID = <?= intval($pagina_idx) ?>;
                const EMPRESA_ID = <?= intval($empresa_idx) ?>;
            </script>
            <script src="productos_tipos.js?v=<?= filemtime(__DIR__ . '/productos_tipos.js') ?>"></script>
        </div>
    </div>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
