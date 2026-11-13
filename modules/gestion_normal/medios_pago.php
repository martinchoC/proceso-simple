<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Medios de Pago";
$currentPage = 'medios_pago';
$modudo_idx = 5;
$pagina_idx = 620; // ID en conf__paginas para Medios de Pago
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <?php ui_encabezado('Medios de pago','bi bi-credit-card-fill', 'Catálogo de instrumentos de cobro y pago, destinos de tesorería y cuentas contables', ['Tesorería']); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div class="content-wrapper">
                <section class="content">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-12">
                                <div class="card shadow-sm border-0">
                                    <div class="card-header bg-white py-3 border-bottom">
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
                                                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                                    <li>
                                                        <a class="dropdown-item" href="#" id="btnExportarExcel">
                                                            <i class="fas fa-file-excel text-success me-2"></i> Excel
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="#" id="btnExportarPDF">
                                                            <i class="fas fa-file-pdf text-danger me-2"></i> PDF
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="#" id="btnExportarCSV">
                                                            <i class="fas fa-file-csv text-primary me-2"></i> CSV
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="#" id="btnExportarPrint">
                                                            <i class="fas fa-print text-secondary me-2"></i> Imprimir
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-body p-3">
                                        <div class="table-responsive">
                                            <table id="tablaMediosPago" class="table table-striped table-hover align-middle w-100">
                                                <thead class="table-light text-nowrap">
                                                    <tr>
                                                        <th width="50" class="text-center">ID</th>
                                                        <th width="90" class="text-center">Código</th>
                                                        <th width="180">Medio de Pago</th>
                                                        <th width="120" class="text-center">Tipo</th>
                                                        <th width="140" class="text-center">Destino Tesorería</th>
                                                        <th width="200">Imputación Contable</th>
                                                        <th width="70" class="text-center">Cobro</th>
                                                        <th width="70" class="text-center">Pago</th>
                                                        <th width="80" class="text-center">Detalle</th>
                                                        <th width="130">Impuesto / Ret.</th>
                                                        <th width="50" class="text-center">Orden</th>
                                                        <th width="90" class="text-center">Estado</th>
                                                        <th width="120" class="text-center">Acciones</th>
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

            <!-- Modal Crear / Editar Medio de Pago -->
            <div class="modal fade" id="modalMedioPago" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-light">
                            <h5 class="modal-title fw-bold" id="modalLabel">
                                <i class="fas fa-credit-card me-2 text-primary"></i>Medio de Pago
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <form id="formMedioPago" class="needs-validation" novalidate>
                            <div class="modal-body p-4">
                                <input type="hidden" id="medio_pago_id" name="medio_pago_id" />

                                <div class="row g-3">
                                    <!-- Código -->
                                    <div class="col-md-4">
                                        <label for="codigo" class="form-label fw-semibold">Código <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control text-uppercase font-monospace" id="codigo" name="codigo" 
                                            maxlength="30" required pattern="[A-Za-z0-9\-_\.]+" placeholder="Ej: TRANSFER_BNA">
                                        <div class="invalid-feedback">Código obligatorio (máx 30 caracteres alfanuméricos).</div>
                                    </div>

                                    <!-- Nombre -->
                                    <div class="col-md-8">
                                        <label for="medio_pago" class="form-label fw-semibold">Nombre del Medio de Pago <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="medio_pago" name="medio_pago" 
                                            maxlength="100" required placeholder="Ej: Transferencia Banco Nación">
                                        <div class="invalid-feedback">El nombre es obligatorio (máx 100 caracteres).</div>
                                    </div>

                                    <!-- Tipo de medio -->
                                    <div class="col-md-6">
                                        <label for="medio_pago_tipo" class="form-label fw-semibold">Tipo de Instrumento <span class="text-danger">*</span></label>
                                        <select class="form-select" id="medio_pago_tipo" name="medio_pago_tipo" required>
                                            <option value="">Cargando tipos...</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione un tipo de instrumento.</div>
                                    </div>

                                    <!-- Tipo de Cuenta de Tesorería -->
                                    <div class="col-md-6">
                                        <label for="tesoreria_cuenta_tipo" class="form-label fw-semibold">Destino de Fondos (Tesorería) <?= ui_ayuda('Determina si impacta en Caja, Banco, Valores o Cupones.') ?></label>
                                        <select class="form-select" id="tesoreria_cuenta_tipo" name="tesoreria_cuenta_tipo">
                                            <option value="">Cargando tipos de cuenta...</option>
                                        </select>
                                    </div>

                                    <!-- Cuenta específica de Tesorería -->
                                    <div class="col-md-6">
                                        <label for="tesoreria_cuenta_id" class="form-label fw-semibold">Cuenta Específica Predeterminada <?= ui_ayuda('Opcional. Si se fija, asigna automáticamente la cuenta bancaria o caja.') ?></label>
                                        <select class="form-select" id="tesoreria_cuenta_id" name="tesoreria_cuenta_id">
                                            <option value="">-- Seleccionar en la operación --</option>
                                        </select>
                                    </div>

                                    <!-- Cuenta Contable -->
                                    <div class="col-md-6">
                                        <label for="cont_cuenta_id" class="form-label fw-semibold">Cuenta Contable Predeterminada <?= ui_ayuda('Cuenta del Plan Contable para asientos automáticos.') ?></label>
                                        <select class="form-select" id="cont_cuenta_id" name="cont_cuenta_id">
                                            <option value="">-- Ninguna / Asignar después --</option>
                                        </select>
                                    </div>

                                    <!-- Sección condicional: Tipo de Impuesto para Retenciones -->
                                    <div class="col-md-12" id="seccion_impuesto" style="display: none;">
                                        <div class="p-3 bg-light rounded border border-warning">
                                            <label for="impuesto_tipo_id" class="form-label fw-semibold text-danger">
                                                <i class="fas fa-file-invoice-dollar me-1"></i>Tipo de Retención / Impuesto Asociado
                                            </label>
                                            <select class="form-select" id="impuesto_tipo_id" name="impuesto_tipo_id">
                                                <option value="">-- Seleccionar Tipo de Impuesto --</option>
                                            </select>
                                            <?= ui_ayuda('Requerido para retenciones sufridas (IVA, Ganancias, IIBB, etc.).') ?>
                                        </div>
                                    </div>

                                    <!-- Opciones operativas -->
                                    <div class="col-md-8 pt-2">
                                        <label class="form-label fw-semibold d-block mb-2">Disponibilidad y Reglas</label>
                                        <div class="d-flex flex-wrap gap-4">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="aplica_cobro" name="aplica_cobro" value="1" checked>
                                                <label class="form-check-label" for="aplica_cobro">
                                                    <i class="fas fa-cash-register text-success me-1"></i>Cobranzas a Clientes
                                                </label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="aplica_pago" name="aplica_pago" value="1" checked>
                                                <label class="form-check-label" for="aplica_pago">
                                                    <i class="fas fa-file-invoice text-primary me-1"></i>Pagos a Proveedores
                                                </label>
                                            </div>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="requiere_detalle" name="requiere_detalle" value="1">
                                                <label class="form-check-label" for="requiere_detalle">
                                                    <i class="fas fa-barcode text-info me-1"></i>Exige Nro / Referencia
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Orden de visualización -->
                                    <div class="col-md-4 pt-2">
                                        <label for="orden" class="form-label fw-semibold">Orden de Visualización</label>
                                        <input type="number" class="form-control" id="orden" name="orden" min="1" max="999" value="1">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer bg-light py-2">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                    <i class="fas fa-times me-1"></i>Cancelar
                                </button>
                                <button type="submit" class="btn btn-primary" id="btnGuardarMedioPago">
                                    <i class="fas fa-save me-1"></i>Guardar
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Modal Visualizar Detalle -->
            <div class="modal fade" id="modalVerMedioPago" tabindex="-1" aria-labelledby="modalVerLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-md">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-light">
                            <h5 class="modal-title fw-bold" id="modalVerLabel">
                                <i class="fas fa-info-circle text-info me-2"></i>Detalle de Medio de Pago
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-4">
                            <table class="table table-sm table-bordered">
                                <tbody>
                                    <tr>
                                        <th class="bg-light" width="40%">ID</th>
                                        <td id="ver_id" class="fw-bold"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Código</th>
                                        <td id="ver_codigo" class="font-monospace fw-bold"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Nombre</th>
                                        <td id="ver_nombre" class="fw-semibold"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Tipo Instrumento</th>
                                        <td id="ver_tipo"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Destino Tesorería</th>
                                        <td id="ver_cta_tipo"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Cuenta Tesorería</th>
                                        <td id="ver_tesoreria_cta"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Cuenta Contable</th>
                                        <td id="ver_cont_cta"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Impuesto / Retención</th>
                                        <td id="ver_impuesto"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Aplica en Cobros</th>
                                        <td id="ver_cobro"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Aplica en Pagos</th>
                                        <td id="ver_pago"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Exige Detalle / Referencia</th>
                                        <td id="ver_detalle"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Orden</th>
                                        <td id="ver_orden"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Fecha de Creación</th>
                                        <td id="ver_fecha_creacion" class="small text-muted"></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light">Última Modificación</th>
                                        <td id="ver_fecha_modificacion" class="small text-muted"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="modal-footer bg-light py-2">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Estilos específicos y librerías -->
    <link rel="stylesheet" type="text/css"
        href="<?= asset_local('css/datatables-buttons-2.4.1/buttons.dataTables.min.css') ?>">
    <script src="<?= asset_local('js/datatables-buttons-2.4.1/dataTables.buttons.min.js') ?>"></script>
    <script src="<?= asset_local('js/jszip-3.10.1/jszip.min.js') ?>"></script>
    <script src="<?= asset_local('js/pdfmake-0.1.53/pdfmake.min.js') ?>"></script>
    <script src="<?= asset_local('js/pdfmake-0.1.53/vfs_fonts.js') ?>"></script>
    <script src="<?= asset_local('js/datatables-buttons-2.4.1/buttons.html5.min.js') ?>"></script>
    <script src="<?= asset_local('js/datatables-buttons-2.4.1/buttons.print.min.js') ?>"></script>
    <script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>

    <script>
        window.PAGINA_ID = <?= (int)$pagina_idx ?>;
    </script>
    <script src="medios_pago.js?v=<?= filemtime(__DIR__.'/medios_pago.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
