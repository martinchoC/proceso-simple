<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Gastos Bancarios";
$currentPage = 'bancos_gastos';
$modudo_idx = 5;
$pagina_idx = 630; // ID en conf__paginas para Gastos Bancarios
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
require_once ROOT_PATH . '/core/csrf.php';

/** Filtros de una solapa: cuenta (o todas) y período desde / hasta en meses. */
function bco_gastos_filtros(string $p): string
{
    return '<div class="ui-filtros d-flex flex-wrap gap-2 align-items-end">
        <select class="form-select form-select-sm ui-f-ancha" id="' . $p . 'Cuenta" aria-label="Cuenta bancaria"></select>
        <div class="input-group input-group-sm ui-f-media"><span class="input-group-text">Desde</span>
            <input type="month" class="form-control" id="' . $p . 'Desde" aria-label="Mes desde"></div>
        <div class="input-group input-group-sm ui-f-media"><span class="input-group-text">Hasta</span>
            <input type="month" class="form-control" id="' . $p . 'Hasta" aria-label="Mes hasta"></div>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="' . $p . 'Recargar" title="Recargar"><i class="fas fa-sync-alt"></i></button>
    </div>';
}
?>

<main class="app-main">
    <?php ui_encabezado('Gastos Bancarios', 'bi bi-receipt', 'Las comisiones e impuestos que el banco debita se registran con un Resumen bancario al proveedor del banco: entra al Libro IVA Compras, a la contabilidad y se cancela con un débito en la cuenta. El comprobante sale con la fecha del último movimiento incluido; se puede generar en cualquier momento y en un mismo mes puede haber varios, pero nunca mezcla meses.', ['Tesorería'], ''); ?>

    <div class="app-content">
        <div class="container-fluid">
            <ul class="nav ui-solapas mb-3" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabGenerar" type="button" id="btnTabGenerar">
                    A generar <span class="badge bg-warning text-dark ms-1 d-none" id="cantPendientes"></span></button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabGenerados" type="button" id="btnTabGenerados">Generados</button></li>
            </ul>

            <div class="tab-content">
            <div class="tab-pane fade show active" id="tabGenerar">
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-header ui-barra bg-white py-2 d-flex flex-wrap gap-2 align-items-center">
                        <span class="fw-semibold">Pendientes de generar</span>
                        <?= ui_ayuda('Meses con gastos del banco clasificados que todavía no tienen resumen bancario. "Ver" abre el mes abajo para revisar las líneas; "Generar" emite el comprobante con todos los gastos del mes. Sin período elegido se muestran todos los meses pendientes.') ?>
                        <div class="ms-auto d-flex flex-wrap gap-2 align-items-end">
                            <?= bco_gastos_filtros('gen') ?>
                            <div class="input-group input-group-sm ui-f-ancha d-none" id="grupoOpPv"><span class="input-group-text">PV de la OP</span>
                                <select class="form-select" id="opPuntoVenta" aria-label="Punto de venta de la orden de pago"></select></div>
                            <?= ui_ayuda('Punto de venta con el que se numera la orden de pago automática (débito en cuenta). Se ofrecen los que tienen habilitada la Orden de Pago en Puntos de Venta; si no hay ninguno, se asigna solo.') ?>
                            <button type="button" class="btn btn-sm btn-success d-none" id="btnGenerarTodos"><i class="bi bi-lightning-charge me-1"></i>Generar todos</button>
                        </div>
                    </div>
                    <div class="card-body p-2" id="pendientes"><div class="text-muted small p-2">Cargando…</div></div>
                </div>

                <div class="d-none" id="detalleMes">
                    <div class="card shadow-sm border-0 mb-3">
                        <div class="card-body py-2 d-flex flex-wrap gap-2 align-items-center">
                            <span class="fw-semibold me-2" id="tituloMes"></span>
                            <span class="small text-muted">Proveedor del banco:</span>
                            <span class="fw-semibold" id="proveedorNombre">—</span>
                            <button type="button" class="btn btn-sm btn-outline-secondary d-none" data-funcion="proveedor" id="btnProveedor"><i class="bi bi-person-badge me-1"></i>Cambiar</button>
                            <button type="button" class="btn btn-success ms-auto d-none" data-funcion="agregar" id="btnGenerar" disabled><i class="bi bi-file-earmark-ruled me-1"></i>Generar comprobante</button>
                        </div>
                    </div>
                    <div id="avisos"></div>
                    <div class="row g-2 mb-3" id="kpis"></div>

                    <div class="row g-3 mb-3">
                        <div class="col-lg-5">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-header bg-white py-2 fw-semibold">Comprobante a generar</div>
                                <div class="card-body p-2" id="comprobante"></div>
                            </div>
                        </div>
                        <div class="col-lg-7">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-header bg-white py-2"><span class="fw-semibold">Líneas del extracto</span>
                                    <span class="small text-muted ms-1">Desmarcá las que no correspondan a este comprobante.</span></div>
                                <div class="card-body p-2"><div class="table-responsive" style="max-height: 28rem" id="lineas"></div></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="tab-pane fade" id="tabGenerados">
                <div class="card shadow-sm border-0">
                    <div class="card-header ui-barra bg-white py-2 d-flex flex-wrap gap-2 align-items-center">
                        <span class="fw-semibold">Comprobantes generados</span>
                        <?= ui_ayuda('Resúmenes bancarios emitidos desde esta pantalla, filtrados por la fecha del comprobante (la del último movimiento incluido). "Deshacer" anula el comprobante y su débito, y las líneas vuelven a A generar.') ?>
                        <div class="ms-auto"><?= bco_gastos_filtros('hist') ?></div>
                    </div>
                    <div class="card-body p-3">
                        <table id="tablaHistorial" class="table table-striped table-hover align-middle w-100">
                            <thead class="table-light text-nowrap"><tr>
                                <th class="text-center">N°</th><th>Fecha</th><th>Cuenta</th><th>Comprobante</th><th class="text-center">Líneas</th>
                                <th class="text-end">Total</th><th class="ui-detalle">Observaciones</th><th class="text-center">Estado</th><th class="text-center">Acciones</th>
                            </tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
            </div>

            <div class="modal fade" id="modalProveedor" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-light"><h5 class="modal-title fw-bold">Proveedor del banco</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                        <div class="modal-body p-3">
                            <input type="search" class="form-control mb-2" id="buscarProveedor" placeholder="Nombre o CUIT del proveedor">
                            <div class="list-group" id="listaProveedores"></div>
                            <div class="small text-muted mt-2">Tiene que ser una entidad con rol Proveedor. Si el banco no está cargado, dalo de alta en Proveedores.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-light"><h5 class="modal-title fw-bold" id="modalDetalleLabel">Gastos</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                        <div class="modal-body p-3" id="detalle"></div>
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
    <script src="bancos_gastos.js?v=<?= filemtime(__DIR__ . '/bancos_gastos.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
