<?php
/**
 * Vista: Contabilidad del Cliente (balance, sumas y saldos, mayores, asientos y plan de cuentas). Los asientos se editan con el
 * período contable abierto (función "editar" de la página y vinculación de contabilidad con escritura).
 * Módulo 4: Estudio Contable (cliente_contabilidad.php) - Página ID: 423
 */

$pageTitle = "Contabilidad del Cliente";
$currentPage = 'cliente_contabilidad';
$modudo_idx = 4;
$pagina_idx = 423;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

$filtros = '<div class="ui-filtros d-flex flex-wrap gap-2 align-items-center">'
    . '<select id="cnCliente" class="form-select form-select-sm ui-f-ancha" aria-label="Cliente"><option value="">Elegí un cliente…</option></select>'
    . '<select id="cnEjercicio" class="form-select form-select-sm ui-f-media" aria-label="Ejercicio" disabled></select>'
    . '<input type="date" id="cnDesde" class="form-control form-control-sm ui-f-fecha" aria-label="Desde">'
    . '<input type="date" id="cnHasta" class="form-control form-control-sm ui-f-fecha" aria-label="Hasta">'
    . '<div class="form-check mb-0"><input class="form-check-input" type="checkbox" id="cnBorradores" checked>'
    . '<label class="form-check-label small" for="cnBorradores">Con borradores</label></div>'
    . '</div>';
?>

<main class="app-main">
    <?php ui_encabezado('Contabilidad del Cliente', 'bi bi-bar-chart-fill',
        'Balance (la misma vista que Gestión > Balance), sumas y saldos, mayores, asientos y plan de cuentas de la empresa del cliente en el ERP. Un asiento se puede editar (fecha, descripción y renglones) mientras su período contable esté abierto, desde la solapa Asientos o haciendo clic en un movimiento del mayor. El período sale del ejercicio elegido (mes de cierre de los Parámetros de Empresa del cliente) y se puede acotar con las fechas. "Con borradores" suma los asientos en borrador (los de venta nacen así); los mayores siempre los incluyen, como en Gestión. Para otra empresa hace falta que el cliente le haya dado al estudio permiso de contabilidad.',
        ['Gestión de Clientes'], $filtros); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div id="cnVacio" class="text-center text-muted py-5">
                <i class="bi bi-bar-chart fs-1 d-block mb-2"></i>Elegí un cliente para ver su contabilidad.
            </div>
            <div class="alert alert-warning py-2 d-none" id="cnAviso"></div>

            <div id="cnPanel" class="d-none">
                <ul class="nav nav-tabs ui-solapas mb-3" role="tablist" id="cnSolapas">
                    <li class="nav-item" data-solapa="balance"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#sBalance" type="button">Balance</button></li>
                    <li class="nav-item" data-solapa="sumas"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sSumas" type="button">Sumas y saldos</button></li>
                    <li class="nav-item" data-solapa="mayores"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sMayores" type="button">Mayores</button></li>
                    <li class="nav-item" data-solapa="asientos"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sAsientos" type="button">Asientos</button></li>
                    <li class="nav-item" data-solapa="plan"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sPlan" type="button">Plan de cuentas</button></li>
                </ul>

                <div class="tab-content">
                    <!-- Balance (la misma vista que Gestión > Balance: cliente_contabilidad_balance.php / .js) -->
                    <div class="tab-pane fade show active" id="sBalance">
                        <?php require __DIR__ . '/cliente_contabilidad_balance.php'; ?>
                    </div>

                    <!-- Sumas y saldos -->
                    <div class="tab-pane fade" id="sSumas">
                        <div class="alert alert-danger py-2 d-none" id="sumasDescuadre"></div>
                        <div class="card shadow-sm border-0"><div class="card-body p-0"><div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 w-100" id="tablaSumas">
                                <thead class="table-light"><tr><th>Código</th><th>Cuenta</th><th class="text-end">Saldo anterior</th><th class="text-end">Debe</th><th class="text-end">Haber</th><th class="text-end">Saldo deudor</th><th class="text-end">Saldo acreedor</th></tr></thead>
                                <tbody></tbody>
                                <tfoot class="table-light fw-bold"><tr><td></td><td>Totales</td><td></td><td class="text-end" id="tsDebe"></td><td class="text-end" id="tsHaber"></td><td class="text-end" id="tsDeudor"></td><td class="text-end" id="tsAcreedor"></td></tr></tfoot>
                            </table>
                        </div></div></div>
                    </div>

                    <!-- Mayores -->
                    <div class="tab-pane fade" id="sMayores">
                        <div class="row g-3">
                            <div class="col-lg-5">
                                <div class="card shadow-sm border-0"><div class="card-body p-0"><div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0 w-100" id="tablaMayorCuentas">
                                        <thead class="table-light"><tr><th>Código</th><th>Cuenta</th><th class="text-end">Saldo final</th></tr></thead>
                                        <tbody></tbody>
                                    </table>
                                </div></div></div>
                            </div>
                            <div class="col-lg-7">
                                <div class="card shadow-sm border-0">
                                    <div class="card-header bg-white py-2"><span class="ui-seccion-titulo mb-0" id="mayorTitulo">Elegí una cuenta</span></div>
                                    <div class="card-body p-0"><div class="table-responsive">
                                        <table class="table table-sm align-middle mb-0" data-ui-acciones="no">
                                            <thead class="table-light"><tr><th>Fecha</th><th>Asiento</th><th>Descripción</th><th class="text-end">Debe</th><th class="text-end">Haber</th><th class="text-end">Saldo</th></tr></thead>
                                            <tbody id="tbMayor"></tbody>
                                            <tfoot class="table-light fw-bold d-none" id="tfMayor"><tr><td colspan="3">Totales</td><td class="text-end" id="tmDebe"></td><td class="text-end" id="tmHaber"></td><td class="text-end" id="tmSaldo"></td></tr></tfoot>
                                        </table>
                                    </div></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Asientos -->
                    <div class="tab-pane fade" id="sAsientos">
                        <div class="card shadow-sm border-0"><div class="card-body p-0"><div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 w-100" id="tablaAsientos">
                                <thead class="table-light"><tr><th>Fecha</th><th>N°</th><th>Descripción</th><th>Tipo</th><th>Estado</th><th class="text-end">Debe</th><th class="text-end">Haber</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div></div></div>
                        <div class="small text-muted mt-2">Hacé clic en un asiento para ver sus renglones y, si su período está abierto, editarlo. Se muestran hasta 2000 asientos del período.</div>
                    </div>

                    <!-- Plan de cuentas -->
                    <div class="tab-pane fade" id="sPlan">
                        <div class="card shadow-sm border-0"><div class="card-body p-0"><div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0 w-100" id="tablaPlan">
                                <thead class="table-light"><tr><th>Código</th><th>Cuenta</th><th class="text-center">Nivel</th><th class="text-center">Nat.</th><th>Tipo</th><th>Cuenta madre</th><th class="text-center">Acciones</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div></div></div>
                        <div class="small text-muted mt-2">Plan de cuentas vigente de la empresa del cliente. Las altas y cambios de cuentas se hacen en el Plan de Cuentas de su empresa.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Detalle de asiento (y edición con el período abierto) -->
    <div class="modal fade" id="modalAsiento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header py-2"><h6 class="modal-title" id="maTitulo">Asiento</h6><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body p-0">
                <!-- Vista -->
                <div id="maVista">
                    <table class="table table-sm align-middle mb-0" data-ui-acciones="no">
                        <thead class="table-light"><tr><th>Código</th><th>Cuenta</th><th>Descripción</th><th class="text-end">Debe</th><th class="text-end">Haber</th></tr></thead>
                        <tbody id="tbAsiento"></tbody>
                        <tfoot class="table-light fw-bold"><tr><td colspan="3">Totales</td><td class="text-end" id="maDebe"></td><td class="text-end" id="maHaber"></td></tr></tfoot>
                    </table>
                </div>
                <!-- Edición -->
                <div id="maEdicion" class="d-none p-3">
                    <div class="alert alert-info py-2 small d-none" id="maAvisoComprobante">
                        Este asiento lo generó un comprobante. Si el comprobante se vuelve a contabilizar en Gestión, el asiento se rehace y se pierden estos cambios.
                    </div>
                    <div class="alert alert-warning py-2 small d-none" id="maAvisoAutomatico">
                        Asiento automático: solo se puede cambiar la cuenta de los renglones de productos. Las cuentas del cliente / proveedor,
                        IVA, IIBB y demás, los importes, la fecha y la descripción quedan como los generó el sistema.
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-sm-3">
                            <label class="form-label form-label-sm mb-1 ui-requerido" for="maFecha">Fecha</label>
                            <input type="date" id="maFecha" class="form-control form-control-sm">
                        </div>
                        <div class="col-sm-9">
                            <label class="form-label form-label-sm mb-1 ui-requerido" for="maDescripcion">Descripción</label>
                            <input type="text" id="maDescripcion" class="form-control form-control-sm" maxlength="500">
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-2" data-ui-acciones="no">
                            <thead class="table-light"><tr><th style="min-width:280px">Cuenta</th><th>Descripción</th><th class="text-end" style="width:140px">Debe</th><th class="text-end" style="width:140px">Haber</th><th style="width:40px"></th></tr></thead>
                            <tbody id="tbEdicion"></tbody>
                            <tfoot class="table-light fw-bold"><tr><td colspan="2">Totales <span class="ms-2 fw-normal" id="meDiferencia"></span></td><td class="text-end" id="meDebe"></td><td class="text-end" id="meHaber"></td><td></td></tr></tfoot>
                        </table>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRenglon"><i class="bi bi-plus-lg me-1"></i>Agregar renglón</button>
                </div>
            </div>
            <div class="modal-footer py-2 justify-content-between">
                <small class="text-muted" id="maMotivo"></small>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-sm btn-outline-primary d-none" id="btnEditarAsiento" data-funcion="editar"><i class="bi bi-pencil me-1"></i>Editar</button>
                    <button type="button" class="btn btn-sm btn-secondary d-none" id="btnCancelarEdicion">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-primary d-none" id="btnGuardarAsiento">Guardar</button>
                </div>
            </div>
        </div></div>
    </div>

    <script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
    <script src="<?= asset_local('js/chart.js-4.4.1/chart.umd.min.js') ?>"></script>
    <script src="cliente_contabilidad_balance.js?v=<?= filemtime(__DIR__ . '/cliente_contabilidad_balance.js') ?>"></script>
    <?php require_once __DIR__ . '/estudio_cliente_activo.php'; estudio_cliente_activo_script((int) $current_empresa_id); ?>
    <script src="cliente_contabilidad.js?v=<?= filemtime(__DIR__ . '/cliente_contabilidad.js') ?>"></script>
</main>

<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
</body>
</html>
