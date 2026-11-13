<?php
/**
 * Vista: Padrones IIBB — importación y consulta del padrón de alícuotas de percepción (SIRCIP).
 * Módulo 2: Gestión de Empresas — Página ID 115 (tabla 432). Diseño: _local/docs/gestion/09_percepciones_iibb.md §7
 */
$pageTitle = "Padrones IIBB";
$currentPage = 'padrones_iibb';
$modudo_idx = 2;
$pagina_idx = 115;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <?php ui_encabezado('Padrones de IIBB','fas fa-address-book', 'Padrón mensual de alícuotas de percepción por CUIT (SIRCIP: se descarga del Portal Federal Tributario días antes de cada mes).', ['Tesorería']); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div id="padSinConfig" class="alert alert-warning d-none">
                La empresa no tiene ninguna jurisdicción con padrón configurado. Se configura en <b>Impuestos Adic. - Conf</b> → solapa <b>Agente de Percepción</b> → Padrón.
            </div>
            <div class="row g-3">
                <div class="col-lg-5">
                    <div class="card card-outline card-primary shadow-sm" id="cardImportar">
                        <div class="card-header py-2"><b><i class="fas fa-file-import me-1"></i>Importar padrón</b></div>
                        <div class="card-body">
                            <form id="formPadImportar" enctype="multipart/form-data">
                                <div class="mb-2">
                                    <label class="form-label small fw-bold mb-0" for="padJurisdiccion">Jurisdicción</label>
                                    <select class="form-select form-select-sm" id="padJurisdiccion" name="jurisdiccion_id" required></select>
                                </div>
                                <div class="mb-2">
                                    <label class="form-label small fw-bold mb-0" for="padArchivo">Archivo (ZIP o TXT)</label>
                                    <input type="file" class="form-control form-control-sm" id="padArchivo" name="archivo" accept=".zip,.txt,.csv" required>
                                    <div class="form-text" id="padMaxUpload"></div>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-upload me-1"></i>Importar</button>
                            </form>
                        </div>
                    </div>
                    <div class="card card-outline card-secondary shadow-sm mt-3">
                        <div class="card-header py-2"><b><i class="fas fa-search me-1"></i>Consultar</b></div>
                        <div class="card-body">
                            <div class="row g-2 align-items-end">
                                <div class="col-5"><label class="form-label small fw-bold mb-0" for="padPeriodo">Período</label>
                                    <input type="month" class="form-control form-control-sm" id="padPeriodo"></div>
                                <div class="col-7"><label class="form-label small fw-bold mb-0" for="padCuit">CUIT</label>
                                    <div class="input-group input-group-sm"><input type="text" class="form-control" id="padCuit" maxlength="13">
                                        <button class="btn btn-outline-primary" type="button" id="btnPadConsultar"><i class="fas fa-search"></i></button></div></div>
                            </div>
                            <div id="padResultadoCuit" class="small mt-2"></div>
                            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="btnPadClientes"><i class="fas fa-users me-1"></i>Ver clientes en el padrón del período</button>
                        </div>
                    </div>
                </div>
                <div class="col-lg-7">
                    <div class="card card-outline card-info shadow-sm">
                        <div class="card-header py-2 d-flex"><b><i class="fas fa-history me-1"></i>Importaciones</b>
                            <button type="button" id="btnPadRecargar" class="btn btn-outline-secondary btn-sm ms-auto" title="Refrescar"><i class="fas fa-sync-alt"></i></button></div>
                        <div class="card-body p-2">
                            <div class="table-responsive">
                                <table id="tablaPadImportaciones" class="table table-sm table-striped align-middle mb-0" style="width:100%">
                                    <thead class="table-light"><tr>
                                        <th>Fecha</th><th>Jurisdicción</th><th>Período</th><th>Archivo</th>
                                        <th class="text-end">Leídas</th><th class="text-end">Cargadas</th><th class="text-end">Rechazadas</th><th>Estado</th>
                                    </tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="card card-outline card-success shadow-sm mt-3 d-none" id="cardPadClientes">
                        <div class="card-header py-2"><b><i class="fas fa-users me-1"></i>Clientes y padrón</b></div>
                        <div class="card-body p-2">
                            <div class="table-responsive">
                                <table id="tablaPadClientes" class="table table-sm table-striped align-middle mb-0" style="width:100%">
                                    <thead class="table-light"><tr><th>Cliente</th><th>CUIT</th><th>Situación</th><th class="text-end">Alícuota</th></tr></thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="padrones_iibb.js?v=<?= filemtime(__DIR__ . '/padrones_iibb.js') ?>"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
