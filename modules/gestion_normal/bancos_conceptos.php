<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Conceptos Bancarios";
$currentPage = 'bancos_conceptos';
$modudo_idx = 5;
$pagina_idx = 632; // ID en conf__paginas para Conceptos Bancarios
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
require_once ROOT_PATH . '/core/csrf.php';
?>

<main class="app-main">
    <?php ui_encabezado('Conceptos Bancarios', 'bi bi-list-check', 'Qué es cada línea del extracto. Los gastos del banco (comisiones, impuestos, percepciones) se registran con un resumen bancario desde Gastos Bancarios; los movimientos propios (transferencias, cheques, depósitos) se concilian contra tesorería.', ['Tesorería']); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <div id="contenedor-boton-agregar" class="d-inline"></div>
                    <button type="button" class="btn btn-sm btn-outline-secondary float-end" id="btnRecargar" title="Recargar"><i class="fas fa-sync-alt"></i></button>
                </div>
                <div class="card-body p-3">
                    <table id="tablaConceptos" class="table table-striped table-hover align-middle w-100">
                        <thead class="table-light text-nowrap">
                            <tr>
                                <th class="text-center">Código</th>
                                <th>Concepto</th>
                                <th>Tipo</th>
                                <th>En el comprobante</th>
                                <th class="ui-detalle text-center">Reglas</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <div class="modal fade" id="modalConcepto" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-light">
                            <h5 class="modal-title fw-bold" id="modalConceptoLabel">Concepto</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <form id="formConcepto" novalidate>
                            <div class="modal-body p-4">
                                <input type="hidden" id="concepto_id" name="concepto_id">
                                <div class="row g-3">
                                    <div class="col-md-4">
                                        <label class="form-label ui-requerido" for="codigo">Código</label>
                                        <input type="text" class="form-control text-uppercase font-monospace" id="codigo" name="codigo" maxlength="30" required pattern="[A-Za-z0-9_]{1,30}">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label ui-requerido" for="concepto">Nombre</label>
                                        <input type="text" class="form-control" id="concepto" name="concepto" maxlength="120" required>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label" for="orden">Orden</label>
                                        <input type="number" class="form-control" id="orden" name="orden" min="0" max="9999" value="1">
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label ui-requerido">Tipo</label>
                                        <div class="d-flex flex-wrap gap-3">
                                            <div class="form-check"><input class="form-check-input" type="radio" name="naturaleza" id="nat_gasto" value="GASTO_BANCARIO">
                                                <label class="form-check-label" for="nat_gasto">Gasto del banco <?= ui_ayuda('Lo cobra el banco: comisiones, impuestos, percepciones. Se registra con el botón Generar comprobante de Gastos Bancarios.') ?></label></div>
                                            <div class="form-check"><input class="form-check-input" type="radio" name="naturaleza" id="nat_propio" value="MOVIMIENTO_PROPIO">
                                                <label class="form-check-label" for="nat_propio">Movimiento propio <?= ui_ayuda('Ya está registrado en tesorería (cobranzas, pagos, transferencias, cheques): solo se concilia.') ?></label></div>
                                        </div>
                                    </div>
                                    <div class="col-12 solo-propio">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="es_cobranza" name="es_cobranza" value="1">
                                            <label class="form-check-label" for="es_cobranza">Concepto de cobranzas <?= ui_ayuda('Se asigna a las líneas del banco de las que se genera un recibo de cobranza desde Conciliación Bancaria (transferencias de clientes identificados por su CUIT). Uno solo por empresa: al marcar este se desmarca el anterior.') ?></label>
                                        </div>
                                    </div>
                                    <div class="col-md-6 solo-gasto">
                                        <label class="form-label ui-requerido" for="rol">Cómo va en el comprobante</label>
                                        <select class="form-select" id="rol" name="rol">
                                            <option value="ITEM">Línea con producto (comisión, impuesto no gravado...)</option>
                                            <option value="IVA">IVA del gasto</option>
                                            <option value="PERCEPCION_IVA">Percepción de IVA</option>
                                            <option value="PERCEPCION_IIBB">Percepción de IIBB</option>
                                            <option value="PERCEPCION_GANANCIAS">Percepción de Ganancias</option>
                                            <option value="RETENCION_IIBB">IIBB sobre créditos (SIRCREB)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 solo-item">
                                        <label class="form-label ui-requerido" for="producto_id">Producto <?= ui_ayuda('Servicio sin stock. De él salen la cuenta contable de compra y la alícuota de IVA.') ?></label>
                                        <select class="form-select" id="producto_id" name="producto_id"></select>
                                    </div>
                                    <div class="col-md-6 solo-jurisdiccion">
                                        <label class="form-label ui-requerido" for="jurisdiccion_id">Provincia <?= ui_ayuda('Va al comprobante como percepción de IIBB recibida de esta provincia (SIRCREB también). Salen las provincias configuradas en Impuestos de la empresa.') ?></label>
                                        <select class="form-select" id="jurisdiccion_id" name="jurisdiccion_id"></select>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer bg-light py-2">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary" id="btnGuardar"><i class="fas fa-save me-1"></i>Guardar</button>
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
        window.BCO_CSRF = <?= json_encode(csrf_token()) ?>;
    </script>
    <script src="<?= asset_local('js/bancos-comun.js') ?>?v=<?= filemtime(ROOT_PATH . '/assets/js/bancos-comun.js') ?>"></script>
    <script src="bancos_conceptos.js?v=<?= filemtime(__DIR__ . '/bancos_conceptos.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
