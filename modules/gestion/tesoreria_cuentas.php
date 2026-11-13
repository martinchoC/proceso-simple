<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Cuentas de Tesorería";
$currentPage = 'tesoreria_cuentas';
$modudo_idx = 2;
$pagina_idx = 127; // ID en conf__paginas para Cuentas de Tesorería
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <?php ui_encabezado('Cuentas de tesorería','bi bi-bank', 'Cajas, cuentas bancarias, valores en cartera y cupones, con su cuenta contable. El saldo lo mueven los cobros, pagos y transferencias: no se edita. Con movimientos no se cambian el tipo ni la moneda; para inactivar una cuenta, su saldo tiene que estar en cero.', ['Tesorería']); ?>

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
                        <table id="tablaCuentas" class="table table-striped table-hover align-middle w-100">
                            <thead class="table-light text-nowrap">
                                <tr>
                                    <th class="text-center">Tipo</th>
                                    <th class="text-center">Código</th>
                                    <th>Cuenta</th>
                                    <th>Sucursal / Banco</th>
                                    <th class="text-center">Moneda</th>
                                    <th>Cuenta contable</th>
                                    <th class="text-end">Saldo</th>
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
            <div class="modal fade" id="modalCuenta" tabindex="-1" aria-labelledby="modalCuentaLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-light">
                            <h5 class="modal-title fw-bold" id="modalCuentaLabel"><i class="bi bi-bank me-2 text-primary"></i>Cuenta de tesorería</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <form id="formCuenta" class="needs-validation" novalidate>
                            <div class="modal-body p-4">
                                <input type="hidden" id="tesoreria_cuenta_id" name="tesoreria_cuenta_id">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label for="cuenta_tipo" class="form-label fw-semibold">Tipo <span class="text-danger">*</span></label>
                                        <select class="form-select" id="cuenta_tipo" name="cuenta_tipo" required></select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="codigo" class="form-label fw-semibold">Código</label>
                                        <input type="text" class="form-control text-uppercase font-monospace" id="codigo" name="codigo" maxlength="20"
                                               pattern="[A-Za-z0-9_\-]{1,20}" placeholder="CAJA_CC">
                                    </div>
                                    <div class="col-md-5">
                                        <label for="tesoreria_cuenta" class="form-label fw-semibold">Nombre <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="tesoreria_cuenta" name="tesoreria_cuenta" maxlength="100" required
                                               placeholder="Caja Casa Central, Banco Nación CC $">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="moneda_id" class="form-label fw-semibold">Moneda <span class="text-danger">*</span></label>
                                        <select class="form-select" id="moneda_id" name="moneda_id" required></select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="sucursal_id" class="form-label fw-semibold">Sucursal <span class="text-danger campo-caja">*</span></label>
                                        <select class="form-select" id="sucursal_id" name="sucursal_id"></select>
                                        <?= ui_ayuda('Obligatoria en las cajas; vacía = toda la empresa.') ?>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="orden" class="form-label fw-semibold">Orden</label>
                                        <input type="number" class="form-control" id="orden" name="orden" min="0" max="999" value="1">
                                    </div>
                                    <div class="col-md-8">
                                        <label for="cont_cuenta_id" class="form-label fw-semibold">Cuenta contable <span class="text-danger">*</span></label>
                                        <select class="form-select" id="cont_cuenta_id" name="cont_cuenta_id" required></select>
                                    </div>
                                    <div class="col-md-4 d-flex align-items-end">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="permite_saldo_negativo" name="permite_saldo_negativo" value="1">
                                            <label class="form-check-label" for="permite_saldo_negativo">Admite saldo negativo (descubierto)</label>
                                        </div>
                                    </div>
                                    <div class="col-12 seccion-banco">
                                        <div class="row g-3 p-3 bg-light rounded border">
                                            <div class="col-md-6">
                                                <label for="banco_id" class="form-label fw-semibold">Banco <span class="text-danger">*</span></label>
                                                <select class="form-select" id="banco_id" name="banco_id"></select>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="banco_cuenta_tipo" class="form-label fw-semibold">Tipo de cuenta bancaria <span class="text-danger">*</span></label>
                                                <select class="form-select" id="banco_cuenta_tipo" name="banco_cuenta_tipo"></select>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="numero" class="form-label fw-semibold">Número</label>
                                                <input type="text" class="form-control" id="numero" name="numero" maxlength="50">
                                            </div>
                                            <div class="col-md-5">
                                                <label for="cbu" class="form-label fw-semibold">CBU / CVU</label>
                                                <input type="text" class="form-control font-monospace" id="cbu" name="cbu" maxlength="22" pattern="\d{22}"
                                                       inputmode="numeric" placeholder="22 dígitos">
                                                <div class="invalid-feedback">22 dígitos.</div>
                                            </div>
                                            <div class="col-md-3">
                                                <label for="alias" class="form-label fw-semibold">Alias</label>
                                                <input type="text" class="form-control" id="alias" name="alias" maxlength="50">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-12"><small class="text-muted" id="avisoMovimientos"></small></div>
                                </div>
                            </div>
                            <div class="modal-footer bg-light py-2">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-1"></i>Cancelar</button>
                                <button type="submit" class="btn btn-primary" id="btnGuardarCuenta"><i class="fas fa-save me-1"></i>Guardar</button>
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
    <script src="tesoreria_cuentas.js?v=<?= filemtime(__DIR__ . '/tesoreria_cuentas.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
