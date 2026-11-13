<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Reglas Bancarias";
$currentPage = 'bancos_reglas';
$modudo_idx = 2;
$pagina_idx = 133; // ID en conf__paginas para Reglas Bancarias
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
require_once ROOT_PATH . '/core/csrf.php';
?>

<main class="app-main">
    <?php ui_encabezado('Reglas Bancarias', 'bi bi-funnel', 'Textos de la descripción del extracto que asignan cada concepto, por banco. Se aplican en orden de prioridad (el número más bajo primero) y gana la primera que coincide. Una línea sin regla queda sin concepto hasta que se le asigne a mano.', ['Tesorería']); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom ui-barra">
                    <div class="ui-filtros d-inline-flex gap-2 align-items-center">
                        <div id="contenedor-boton-agregar" class="d-inline"></div>
                        <select class="form-select form-select-sm ui-f-media" id="filtroBanco"></select>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Recargar"><i class="fas fa-sync-alt"></i></button>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div class="input-group input-group-sm mb-3">
                        <span class="input-group-text">Probar una descripción <?= ui_ayuda('Escribí el texto como viene en el extracto para ver qué regla del banco elegido lo clasifica.') ?></span>
                        <input type="text" class="form-control" id="textoPrueba" placeholder="COMISION DATANET">
                        <select class="form-select" id="signoPrueba" style="max-width: 9rem"><option value="-1">Débito</option><option value="1">Crédito</option></select>
                        <button class="btn btn-outline-primary" type="button" id="btnProbarTexto">Probar</button>
                    </div>
                    <div id="resultadoTexto" class="small mb-3"></div>
                    <table id="tablaReglas" class="table table-striped table-hover align-middle w-100">
                        <thead class="table-light text-nowrap">
                            <tr>
                                <th>Banco</th>
                                <th class="text-center">Prioridad</th>
                                <th>Si la descripción...</th>
                                <th class="text-center">Aplica a</th>
                                <th>Concepto</th>
                                <th class="text-center ui-detalle">Líneas</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <div class="modal fade" id="modalRegla" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-light">
                            <h5 class="modal-title fw-bold" id="modalReglaLabel">Regla</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <form id="formRegla" novalidate>
                            <div class="modal-body p-4">
                                <input type="hidden" id="regla_id" name="regla_id">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label ui-requerido" for="banco_id">Banco</label>
                                        <select class="form-select" id="banco_id" name="banco_id" required></select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label" for="prioridad">Prioridad <?= ui_ayuda('Menor número = se evalúa antes.') ?></label>
                                        <input type="number" class="form-control" id="prioridad" name="prioridad" min="0" max="9999" value="100">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label" for="aplica_a">Aplica a</label>
                                        <select class="form-select" id="aplica_a" name="aplica_a">
                                            <option value="AMBOS">Débitos y créditos</option><option value="DEBITO">Solo débitos</option><option value="CREDITO">Solo créditos</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label" for="tipo_coincidencia">Si la descripción</label>
                                        <select class="form-select" id="tipo_coincidencia" name="tipo_coincidencia">
                                            <option value="CONTIENE">contiene</option><option value="EMPIEZA">empieza con</option><option value="IGUAL">es igual a</option><option value="REGEX">cumple la expresión regular</option>
                                        </select>
                                    </div>
                                    <div class="col-md-8">
                                        <label class="form-label ui-requerido" for="patron">Texto <?= ui_ayuda('No distingue mayúsculas ni espacios repetidos. Una expresión regular se escribe sin barras, p. ej. ^IMPUESTO (DEBITO|CREDITO)') ?></label>
                                        <input type="text" class="form-control font-monospace" id="patron" name="patron" maxlength="200" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label ui-requerido" for="concepto_id">Concepto que asigna</label>
                                        <select class="form-select" id="concepto_id" name="concepto_id" required></select>
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
    <script src="bancos_reglas.js?v=<?= filemtime(__DIR__ . '/bancos_reglas.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
