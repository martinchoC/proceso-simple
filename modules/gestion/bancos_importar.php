<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Extractos Bancarios";
$currentPage = 'bancos_importar';
$modudo_idx = 2;
$pagina_idx = 129; // ID en conf__paginas para Extractos Bancarios
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
require_once ROOT_PATH . '/core/csrf.php';
?>

<main class="app-main">
    <?php ui_encabezado('Extractos Bancarios', 'bi bi-upload', 'Subí el extracto que descargás del banco (PDF, Excel, CSV, TXT o imagen) y se lee automáticamente con IA. Primero se muestra cómo se leyó: cantidad de movimientos, saldos y si la cadena de saldos cierra; después confirmás. Los movimientos que ya estaban importados se omiten. En la solapa Movimientos se consultan todos los movimientos importados, se les asigna el concepto (o uno nuevo) y se ignoran o restauran; en Resumen se ven mes a mes el saldo inicial, los débitos, los créditos y el saldo final del banco.', ['Tesorería']); ?>

    <div class="app-content">
        <div class="container-fluid">
            <ul class="nav ui-solapas mb-3" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabImportar" type="button">Importar</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabMovimientos" type="button" id="btnTabMovimientos">Movimientos</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabResumen" type="button" id="btnTabResumen">Resumen</button></li>
            </ul>

            <div class="tab-content">
            <div class="tab-pane fade show active" id="tabImportar">
            <div class="card shadow-sm border-0 mb-3" id="cardImportar">
                <div class="card-header bg-white py-3 border-bottom"><span class="fw-semibold"><i class="bi bi-stars text-primary me-1"></i>Importar extracto con IA</span></div>
                <div class="card-body p-3">
                    <form id="formImportar" class="row g-3 align-items-end" novalidate>
                        <div class="col-md-5">
                            <label class="form-label ui-requerido" for="cuenta_id">Cuenta bancaria</label>
                            <select class="form-select" id="cuenta_id" name="cuenta_id" required></select>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label ui-requerido" for="archivo">Archivo (PDF, Excel, CSV, TXT o imagen)</label>
                            <input type="file" class="form-control" id="archivo" name="archivo" accept=".pdf,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png" required>
                        </div>
                        <div class="col-md-2 d-grid">
                            <button type="submit" class="btn btn-outline-primary" id="btnPrevisualizar"><i class="bi bi-stars me-1"></i>Leer con IA</button>
                        </div>
                    </form>
                    <div id="previa" class="mt-3"></div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom ui-barra">
                    <span class="fw-semibold me-2">Lotes importados</span>
                    <select class="form-select form-select-sm d-inline-block w-auto" id="filtroCuenta"></select>
                    <button type="button" class="btn btn-sm btn-outline-secondary float-end" id="btnRecargar" title="Recargar"><i class="fas fa-sync-alt"></i></button>
                </div>
                <div class="card-body p-3">
                    <table id="tablaLotes" class="table table-striped table-hover align-middle w-100">
                        <thead class="table-light text-nowrap">
                            <tr>
                                <th class="text-center">Lote</th>
                                <th>Cuenta</th>
                                <th>Período</th>
                                <th class="text-center">Movimientos</th>
                                <th class="text-center">Sin concepto</th>
                                <th class="text-center">Conciliados</th>
                                <th class="text-end ui-detalle">Saldo final</th>
                                <th class="ui-detalle">Archivo</th>
                                <th class="ui-detalle">Formato</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
            </div>

            <?php
            // Filtros de las solapas Movimientos y Resumen: los mismos controles, con prefijo propio (mov / res)
            $filtros_extracto = function (string $px, string $extra = '') { ?>
                        <div class="ui-filtros d-flex flex-wrap gap-2 align-items-end flex-grow-1">
                            <select class="form-select form-select-sm ui-f-ancha" id="<?= $px ?>Cuenta" aria-label="Cuenta bancaria"></select>
                            <input type="date" class="form-control form-control-sm ui-f-fecha" id="<?= $px ?>Desde" aria-label="Desde" title="Desde">
                            <input type="date" class="form-control form-control-sm ui-f-fecha" id="<?= $px ?>Hasta" aria-label="Hasta" title="Hasta">
                            <select class="form-select form-select-sm ui-f-ancha" id="<?= $px ?>Concepto" aria-label="Concepto"></select>
                            <select class="form-select form-select-sm ui-f-media" id="<?= $px ?>Estado" aria-label="Estado">
                                <option value="">Todos los estados</option>
                                <option value="PENDIENTE">Sin concepto</option>
                                <option value="CLASIFICADA">Clasificada</option>
                                <option value="CONCILIADA">Conciliada</option>
                                <option value="IGNORADA">Ignorada</option>
                            </select>
                            <select class="form-select form-select-sm ui-f-media" id="<?= $px ?>Sentido" aria-label="Débitos o créditos">
                                <option value="">Débitos y créditos</option>
                                <option value="DEBITO">Solo débitos</option>
                                <option value="CREDITO">Solo créditos</option>
                            </select>
                            <input type="search" class="form-control form-control-sm ui-f-ancha" id="<?= $px ?>Texto" placeholder="Descripción o referencia" aria-label="Descripción o referencia">
                            <button type="button" class="btn btn-sm btn-primary" id="<?= $px ?>Buscar"><i class="bi bi-search me-1"></i>Buscar</button>
                            <?= $extra ?>
                        </div>
            <?php };
            // Saldos y totales del filtro, en una línea
            $totales_extracto = function (string $px) { ?>
                        <div class="bco-totales d-flex flex-wrap gap-2 small mb-2">
                            <span class="bco-total bg-light rounded px-2 py-1">Saldo inicial <b class="font-monospace" id="<?= $px ?>SaldoInicial">—</b></span>
                            <span class="bco-total bg-light rounded px-2 py-1">Débitos <b class="font-monospace text-danger" id="<?= $px ?>Debitos">0,00</b></span>
                            <span class="bco-total bg-light rounded px-2 py-1">Créditos <b class="font-monospace" id="<?= $px ?>Creditos">0,00</b></span>
                            <span class="bco-total bg-light rounded px-2 py-1">Saldo final <b class="font-monospace" id="<?= $px ?>SaldoFinal">—</b></span>
                            <span class="bco-total bg-light rounded px-2 py-1 text-muted"><span id="<?= $px ?>Cantidad">0</span> movimientos<span id="<?= $px ?>SinConcepto"></span></span>
                            <span class="text-muted align-self-center d-none" id="<?= $px ?>Parcial"><i class="bi bi-info-circle me-1"></i>Saldos de la cuenta; débitos y créditos solo de lo filtrado.</span>
                        </div>
            <?php };
            ?>
            <div class="tab-pane fade" id="tabMovimientos">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-2 border-bottom">
                        <?php $filtros_extracto('mov', '<button type="button" class="btn btn-sm btn-outline-secondary ms-auto d-none" id="btnNuevoConcepto"><i class="bi bi-plus-lg me-1"></i>Nuevo concepto</button>'); ?>
                    </div>
                    <div class="card-body px-3 pt-2 pb-3">
                        <?php $totales_extracto('mov'); ?>
                        <div class="small text-warning mb-1" id="movLimitado"></div>
                        <table id="tablaMovimientos" class="table table-striped table-hover align-middle w-100">
                            <thead class="table-light text-nowrap">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Cuenta</th>
                                    <th>Descripción</th>
                                    <th>Referencia</th>
                                    <th>Concepto</th>
                                    <th class="text-end">Importe</th>
                                    <th class="text-end">Saldo</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center no-export">Acciones</th>
                                    <th class="text-center ui-detalle">Lote</th>
                                    <th class="text-center ui-detalle">Fila</th>
                                    <th class="ui-detalle">Fecha valor</th>
                                    <th class="ui-detalle">Origen del concepto</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tabResumen">
                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white py-2 border-bottom">
                        <?php $filtros_extracto('res'); ?>
                    </div>
                    <div class="card-body px-3 pt-2 pb-3">
                        <?php $totales_extracto('res'); ?>
                        <table id="tablaResumen" class="table table-striped table-hover align-middle w-100">
                            <thead class="table-light text-nowrap">
                                <tr>
                                    <th>Mes</th>
                                    <th>Cuenta</th>
                                    <th class="text-end">Saldo inicial</th>
                                    <th class="text-end">Débitos</th>
                                    <th class="text-end">Créditos</th>
                                    <th class="text-end">Saldo final</th>
                                    <th class="text-center">Movimientos</th>
                                    <th class="text-center ui-detalle">Sin concepto</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
            </div>

            <div class="modal fade" id="modalLote" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-light">
                            <h5 class="modal-title fw-bold" id="modalLoteLabel">Lote</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-3" id="detalleLote"></div>
                    </div>
                </div>
            </div>

            <?php require ROOT_PATH . '/templates/bancos/concepto_rapido_modal.php'; ?>
        </div>
    </div>

    <script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
    <script>
        window.PAGINA_ID = <?= (int)$pagina_idx ?>;
        window.BCO_CSRF = <?= json_encode(csrf_token()) ?>;
    </script>
    <script src="<?= asset_local('js/bancos-comun.js') ?>?v=<?= filemtime(ROOT_PATH . '/assets/js/bancos-comun.js') ?>"></script>
    <script src="<?= asset_local('js/bancos-concepto-rapido.js') ?>?v=<?= filemtime(ROOT_PATH . '/assets/js/bancos-concepto-rapido.js') ?>"></script>
    <script src="bancos_importar.js?v=<?= filemtime(__DIR__ . '/bancos_importar.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
