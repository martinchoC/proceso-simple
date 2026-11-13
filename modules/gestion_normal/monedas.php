<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Monedas";
$currentPage = 'monedas';
$modudo_idx = 5;
$pagina_idx = 626; // ID en conf__paginas para Monedas
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <?php ui_encabezado('Monedas','bi bi-cash-coin', 'Moneda base de la empresa y monedas extranjeras con su cotización. La moneda base cotiza 1 y no se cambia cuando la empresa ya tiene comprobantes. Una moneda que usan cuentas de tesorería activas no se inactiva.', ['Tesorería']); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <div id="contenedor-boton-agregar" class="d-inline"></div>
                    <button type="button" class="btn btn-sm btn-outline-secondary float-end" id="btnRecargar" title="Recargar">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="tablaMonedas" class="table table-striped table-hover align-middle w-100">
                            <thead class="table-light text-nowrap">
                                <tr>
                                    <th class="text-center">Código</th>
                                    <th>Moneda</th>
                                    <th class="text-center">Símbolo</th>
                                    <th class="text-center">Base</th>
                                    <th class="text-end">Cotización</th>
                                    <th class="text-center">Decimales</th>
                                    <th class="text-center">Cuentas de tesorería</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Modal Crear / Editar -->
            <div class="modal fade" id="modalMoneda" tabindex="-1" aria-labelledby="modalMonedaLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-light">
                            <h5 class="modal-title fw-bold" id="modalMonedaLabel"><i class="bi bi-cash-coin me-2 text-primary"></i>Moneda</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <form id="formMoneda" class="needs-validation" novalidate>
                            <div class="modal-body p-4">
                                <input type="hidden" id="moneda_id" name="moneda_id">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="codigo" class="form-label fw-semibold">Código <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control text-uppercase font-monospace" id="codigo" name="codigo" maxlength="10"
                                               required pattern="[A-Za-z0-9]{1,10}" placeholder="ARS, USD">
                                        <div class="invalid-feedback">Letras y números, hasta 10.</div>
                                    </div>
                                    <div class="col-md-8">
                                        <label for="moneda" class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="moneda" name="moneda" maxlength="100" required placeholder="Peso argentino">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="simbolo" class="form-label fw-semibold">Símbolo <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="simbolo" name="simbolo" maxlength="10" required placeholder="$">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="cantidad_decimales" class="form-label fw-semibold">Decimales</label>
                                        <input type="number" class="form-control" id="cantidad_decimales" name="cantidad_decimales" min="0" max="6" value="2">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="orden" class="form-label fw-semibold">Orden</label>
                                        <input type="number" class="form-control" id="orden" name="orden" min="0" max="999" value="1">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="cotizacion_actual" class="form-label fw-semibold">Cotización (en moneda base)</label>
                                        <input type="number" class="form-control text-end" id="cotizacion_actual" name="cotizacion_actual" min="0.000001" step="0.000001" value="1">
                                    </div>
                                    <div class="col-md-6 d-flex flex-column justify-content-end gap-1">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="es_moneda_base" name="es_moneda_base" value="1">
                                            <label class="form-check-label" for="es_moneda_base">Moneda base de la empresa</label>
                                        </div>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="permite_editar_cotizacion" name="permite_editar_cotizacion" value="1">
                                            <label class="form-check-label" for="permite_editar_cotizacion">Cotización editable en los comprobantes</label>
                                        </div>
                                    </div>
                                    <div class="col-12"><small class="text-muted" id="avisoUsoMoneda"></small></div>
                                </div>
                            </div>
                            <div class="modal-footer bg-light py-2">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Cancelar</button>
                                <button type="submit" class="btn btn-primary" id="btnGuardarMoneda"><i class="fas fa-save me-1"></i>Guardar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
    <script>
        window.PAGINA_ID = <?= (int)$pagina_idx ?>;
    </script>
    <script src="monedas.js?v=<?= filemtime(__DIR__ . '/monedas.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
