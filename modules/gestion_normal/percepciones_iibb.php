<?php
/**
 * Vista: Percepciones IIBB practicadas — DDJJ por jurisdicción y período, exportación y liquidación.
 * Módulo 5: Gestión Normal — Página ID 116. Diseño: _local/docs/gestion/09_percepciones_iibb.md
 */
$pageTitle = "Percepciones IIBB";
$currentPage = 'percepciones_iibb';
$modudo_idx = 5;
$pagina_idx = 616;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <?php ui_encabezado('Percepciones de IIBB','fas fa-landmark', 'Percepciones de los comprobantes de venta confirmados, por jurisdicción y período: archivo de la DDJJ y liquidación.', ['Tesorería']); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card card-outline card-primary shadow-sm">
                <div class="card-header py-2 d-flex flex-wrap gap-2 align-items-center">
                    <label for="perJurisdiccion" class="form-label mb-0 small fw-bold">Jurisdicción:</label>
                    <select id="perJurisdiccion" class="form-select form-select-sm" style="max-width: 260px;"></select>
                    <label for="perPeriodo" class="form-label mb-0 small fw-bold ms-2">Período:</label>
                    <input type="month" id="perPeriodo" class="form-control form-control-sm" style="max-width: 170px;">
                    <div class="ms-auto d-flex gap-2">
                        <button type="button" id="btnPerExportar" class="btn btn-outline-primary btn-sm d-none"><i class="fas fa-file-export me-1"></i>Exportar DDJJ</button>
                        <button type="button" id="btnPerLiquidar" class="btn btn-success btn-sm d-none"><i class="fas fa-file-invoice-dollar me-1"></i>Liquidar</button>
                        <button type="button" id="btnPerRecargar" class="btn btn-outline-secondary btn-sm" title="Refrescar"><i class="fas fa-sync-alt"></i></button>
                    </div>
                </div>
                <div class="card-body p-2">
                    <div id="perResumen" class="d-flex flex-wrap gap-3 small mb-2"></div>
                    <div class="table-responsive">
                        <table id="tablaPercepciones" class="table table-sm table-striped table-hover align-middle mb-0" style="width:100%">
                            <thead class="table-light">
                                <tr>
                                    <th>Fecha</th>
                                    <th>Comprobante</th>
                                    <th>Cliente</th>
                                    <th>CUIT</th>
                                    <th>Situación</th>
                                    <th class="text-end">Base</th>
                                    <th class="text-end">Alícuota</th>
                                    <th class="text-end">Percibido</th>
                                    <th>Liquidación</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal liquidación -->
    <div class="modal fade" id="modalPerLiquidar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" id="formPerLiquidar">
                <div class="modal-header py-2">
                    <h5 class="modal-title"><i class="fas fa-file-invoice-dollar me-2"></i>Liquidar percepciones</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="small mb-2" id="perLiqTexto"></p>
                    <div class="mb-2">
                        <label class="form-label small fw-bold mb-0" for="perLiqEntidad">Organismo recaudador * <?= ui_ayuda('Entidad proveedora a la que se le debe (p. ej. la Agencia de Recaudación de la provincia).') ?></label>
                        <select class="form-select form-select-sm" id="perLiqEntidad" required></select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold mb-0" for="perLiqSucursal">Sucursal *</label>
                        <select class="form-select form-select-sm" id="perLiqSucursal" required></select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-0" for="perLiqVto">Vencimiento</label>
                            <input type="date" class="form-control form-control-sm" id="perLiqVto">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-0" for="perLiqDdjj">N° DDJJ / constancia</label>
                            <input type="text" class="form-control form-control-sm" id="perLiqDdjj" maxlength="50">
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check me-1"></i>Generar liquidación</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="percepciones_iibb.js?v=<?= filemtime(__DIR__ . '/percepciones_iibb.js') ?>"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
