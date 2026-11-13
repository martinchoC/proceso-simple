<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Conciliación Bancaria";
$currentPage = 'bancos_conciliacion';
$modudo_idx = 5;
$pagina_idx = 628; // ID en conf__paginas para Conciliación Bancaria
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
require_once ROOT_PATH . '/core/csrf.php';

$filtros = '<div class="ui-filtros d-flex flex-wrap gap-2 align-items-end">
    <select class="form-select form-select-sm ui-f-ancha" id="cuenta_id" aria-label="Cuenta bancaria"></select>
    <input type="hidden" id="desde">
    <input type="hidden" id="hasta">
    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Recargar"><i class="fas fa-sync-alt"></i></button>
</div>';

/** Mes y año de las solapas Pendientes de Conciliación y Conciliaciones: es un solo período, cambiarlo en una lo cambia en la otra. */
function bco_conciliacion_periodo(): string
{
    return '<div class="bco-periodo d-flex gap-1 align-items-center">
        <select class="form-select form-select-sm sel-mes" aria-label="Mes" style="width: 130px;"></select>
        <select class="form-select form-select-sm sel-anio" aria-label="Año" style="width: 90px;"></select>
    </div>';
}
?>

<main class="app-main bco-compacto">
    <style>
        /* Todo lo que está arriba de las tablas, compacto: lo que importa es ver la mayor cantidad de líneas para conciliar */
        body.ui-simple .bco-compacto .ui-encabezado { padding: 0.375rem 0 0.25rem; }
        body.ui-simple .bco-compacto .ui-titulo { font-size: 1.125rem; }
        body.ui-simple .bco-compacto .ui-titulo > i { font-size: 1rem; }
        body.ui-simple .bco-compacto .ui-kpi { padding: 0.25rem 0.625rem; }
        body.ui-simple .bco-compacto .ui-kpi-etiqueta { font-size: 0.75rem; margin: 0; }
        body.ui-simple .bco-compacto .ui-kpi-valor { font-size: 1rem; }
        body.ui-simple .bco-compacto .ui-kpi-sub { font-size: 0.6875rem; margin: 0; }
        body.ui-simple .bco-compacto .ui-solapas .nav-link { padding: 0.25rem 0.75rem; }
        body.ui-simple .bco-compacto .card-header { padding-top: 0.25rem; padding-bottom: 0.25rem; }
        body.ui-simple .bco-compacto #sugerencias .card-body { max-height: 28vh; overflow-y: auto; }
    </style>
    <?php ui_encabezado('Conciliación Bancaria', 'bi bi-bank', 'Resumen: los meses que tienen algo sin conciliar. Pendientes de Conciliación: a la izquierda lo que dice el banco y a la derecha lo que registró tesorería; marcá de los dos lados lo que corresponde y conciliá cuando la diferencia sea cero. Conciliaciones: saldos del mes, lo conciliado y lo que queda pendiente. Los gastos del banco (comisiones, impuestos) se registran en Gastos Bancarios.', ['Tesorería'], $filtros); ?>

    <div class="app-content">
        <div class="container-fluid">
            <ul class="nav ui-solapas mb-2" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tabResumen" type="button" id="btnTabResumen">Resumen</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabPendientes" type="button" id="btnTabPendientes">Pendientes de Conciliación</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tabHistorial" type="button" id="btnTabHistorial">Conciliaciones</button></li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="tabResumen">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-white py-1 d-flex flex-wrap gap-2 align-items-center">
                            <span class="fw-semibold">Resumen de las conciliaciones</span>
                            <?= ui_ayuda('Por cuenta y mes, desde el primer extracto importado de la cuenta: saldo según el banco y según tesorería al cierre del mes, líneas del extracto conciliadas, lo que queda pendiente del banco (también las líneas sin concepto y los gastos sin registrar en Gastos Bancarios) y de tesorería, y el estado del mes. "Ver" abre el mes en la solapa Conciliaciones y "Conciliar", en Pendientes de Conciliación.') ?>
                            <div class="ms-auto d-flex flex-wrap gap-2 align-items-center">
                                <div class="input-group input-group-sm" style="width: 150px;"><span class="input-group-text">Desde</span>
                                    <select class="form-select" id="resumenDesde" aria-label="Año desde"></select></div>
                                <div class="input-group input-group-sm" style="width: 150px;"><span class="input-group-text">Hasta</span>
                                    <select class="form-select" id="resumenHasta" aria-label="Año hasta"></select></div>
                                <div class="form-check form-switch small mb-0"><input class="form-check-input" type="checkbox" id="periodosSoloCuenta"><label class="form-check-label" for="periodosSoloCuenta">Solo la cuenta elegida</label></div>
                            </div>
                        </div>
                        <div class="card-body p-3">
                            <table id="tablaPeriodos" class="table table-striped table-hover align-middle w-100">
                                <thead class="table-light text-nowrap"><tr>
                                    <th>Cuenta</th><th>Período</th><th class="text-end">Saldo según banco</th><th class="text-end">Saldo según tesorería</th>
                                    <th class="text-end">Diferencia</th><th class="text-center">Conciliadas</th><th class="text-end">Banco pendiente</th>
                                    <th class="text-end">Gastos sin registrar</th><th class="text-end">Tesorería pendiente</th><th class="text-center">Estado</th><th class="text-center">Acción</th>
                                </tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="tabPendientes">
                    <div class="card shadow-sm border-0 mb-2">
                        <div class="card-body py-1 d-flex flex-wrap gap-2 align-items-center" id="barraAcciones">
                            <?= bco_conciliacion_periodo() ?>
                            <div class="form-check form-switch small mb-0"><input class="form-check-input" type="checkbox" id="verIgnoradas"><label class="form-check-label" for="verIgnoradas">Ver ignoradas</label></div>
                            <span class="small">Banco: <b id="selExtracto" class="font-monospace">0,00</b></span>
                            <span class="small">Tesorería: <b id="selTesoreria" class="font-monospace">0,00</b></span>
                            <span class="small">Diferencia: <b id="selDiferencia" class="font-monospace">0,00</b></span>
                            <div class="ms-auto d-flex flex-wrap gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="btnNuevoConcepto"><i class="bi bi-plus-lg me-1"></i>Nuevo concepto</button>
                                <button type="button" class="btn btn-sm btn-outline-info" id="btnSugerir"><i class="bi bi-lightbulb me-1"></i>Sugerencias</button>
                                <button type="button" class="btn btn-sm btn-outline-primary d-none" data-funcion="conciliar_auto" id="btnAuto"><i class="bi bi-arrow-repeat me-1"></i>Conciliar automáticamente</button>
                                <div class="btn-group d-none" data-funcion="clasificar">
                                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">Líneas marcadas</button>
                                    <ul class="dropdown-menu dropdown-menu-end">
                                        <li><a class="dropdown-item" href="#" data-op="asignar">Asignar concepto…</a></li>
                                        <li><a class="dropdown-item" href="#" data-op="quitar">Quitar concepto (volver a las reglas)</a></li>
                                        <li><a class="dropdown-item" href="#" data-op="ignorar">Ignorar</a></li>
                                        <li><a class="dropdown-item" href="#" data-op="restaurar">Restaurar (las ignoradas)</a></li>
                                    </ul>
                                </div>
                                <button type="button" class="btn btn-sm btn-success d-none" data-funcion="agregar" id="btnConciliar" disabled><i class="bi bi-check2-all me-1"></i>Conciliar</button>
                            </div>
                        </div>
                    </div>
                    <div id="sugerencias" class="mb-2"></div>
                    <div class="row g-2">
                        <div class="col-xl-7">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-header bg-white py-1"><span class="fw-semibold">Banco</span> <span class="text-muted small" id="totExtracto"></span></div>
                                <div class="card-body p-2">
                                    <table id="tablaExtracto" class="table table-sm table-hover align-middle w-100" data-ui-acciones="no">
                                        <thead class="table-light"><tr><th></th><th>Fecha</th><th>Descripción</th><th>Concepto</th><th class="text-end">Importe</th><th class="ui-detalle">Referencia</th></tr></thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-5">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-header bg-white py-1"><span class="fw-semibold">Tesorería</span> <span class="text-muted small" id="totTesoreria"></span></div>
                                <div class="card-body p-2">
                                    <table id="tablaTesoreria" class="table table-sm table-hover align-middle w-100" data-ui-acciones="no">
                                        <thead class="table-light"><tr><th></th><th>Fecha</th><th>Concepto</th><th class="text-end">Importe</th><th class="ui-detalle">Medio</th></tr></thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="tabHistorial">
                    <div class="card shadow-sm border-0 mb-2">
                        <div class="card-body py-1 d-flex flex-wrap gap-2 align-items-center">
                            <?= bco_conciliacion_periodo() ?>
                        </div>
                    </div>
                    <div class="row g-2 mb-2" id="kpis"></div>

                    <div class="card shadow-sm border-0 mb-2">
                        <div class="card-header bg-white py-1"><span class="fw-semibold">Conciliado en el período</span>
                            <?= ui_ayuda('Las conciliaciones vigentes con fecha en el mes elegido: contra tesorería y de gastos bancarios. Desde las acciones de cada una se ven sus líneas y movimientos.') ?></div>
                        <div class="card-body p-3">
                            <table id="tablaHistorial" class="table table-striped table-hover align-middle w-100">
                                <thead class="table-light text-nowrap"><tr>
                                    <th class="text-center">N°</th><th>Fecha</th><th>Tipo</th><th class="text-center">Líneas</th><th class="text-center">Movimientos</th>
                                    <th class="text-end">Importe</th><th class="ui-detalle">Observaciones</th><th class="text-center">Estado</th><th class="text-center">Acciones</th>
                                </tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-xl-7">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-header bg-white py-1"><span class="fw-semibold">Pendiente de conciliar: banco</span> <span class="text-muted small" id="totPendBanco"></span></div>
                                <div class="card-body p-2">
                                    <table id="tablaPendBanco" class="table table-sm table-striped align-middle w-100">
                                        <thead class="table-light"><tr><th>Fecha</th><th>Descripción</th><th>Concepto</th><th class="text-end">Importe</th></tr></thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-xl-5">
                            <div class="card shadow-sm border-0 h-100">
                                <div class="card-header bg-white py-1"><span class="fw-semibold">Pendiente de conciliar: tesorería</span> <span class="text-muted small" id="totPendTesoreria"></span></div>
                                <div class="card-body p-2">
                                    <table id="tablaPendTesoreria" class="table table-sm table-striped align-middle w-100">
                                        <thead class="table-light"><tr><th>Fecha</th><th>Concepto</th><th class="text-end">Importe</th></tr></thead>
                                        <tbody></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-light"><h5 class="modal-title fw-bold" id="modalDetalleLabel">Conciliación</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                        <div class="modal-body p-3" id="detalle"></div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="modalRecibo" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-light"><h5 class="modal-title fw-bold">Generar recibo</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
                        <form id="formRecibo" novalidate>
                            <div class="modal-body p-3">
                                <div id="reciboAviso"></div>
                                <div class="ui-seccion">
                                    <div class="ui-seccion-titulo">Transferencia</div>
                                    <div class="row g-2 small">
                                        <div class="col-md-6"><div class="text-muted">Cliente</div><div class="fw-semibold" id="reciboCliente"></div></div>
                                        <div class="col-6 col-md-3"><div class="text-muted">Fecha</div><div id="reciboFecha"></div></div>
                                        <div class="col-6 col-md-3 text-md-end"><div class="text-muted">Importe</div><div class="fw-semibold" id="reciboImporte"></div></div>
                                        <div class="col-12 text-muted text-break" id="reciboDescripcion"></div>
                                    </div>
                                </div>
                                <div class="ui-seccion">
                                    <div class="ui-seccion-titulo">Recibo</div>
                                    <div class="row g-2">
                                        <div class="col-md-5"><label class="form-label ui-requerido" for="reciboPv">Punto de venta</label><select class="form-select" id="reciboPv" required></select></div>
                                        <div class="col-md-4"><label class="form-label ui-requerido" for="reciboTipo">Tipo</label><select class="form-select" id="reciboTipo" required></select></div>
                                        <div class="col-md-3"><label class="form-label ui-requerido" for="reciboMedio">Medio</label><select class="form-select" id="reciboMedio" required></select></div>
                                    </div>
                                </div>
                                <div class="ui-seccion">
                                    <div class="ui-seccion-titulo">Imputación <?= ui_ayuda('Facturas pendientes del cliente emitidas hasta la fecha de la transferencia. Si el importe coincide con el saldo de una factura (o con la suma de las más viejas) ya viene imputada; si no, el recibo queda a cuenta y se imputa después en Cobranzas.') ?></div>
                                    <div class="table-responsive">
                                        <table class="table table-sm small align-middle mb-1" id="tablaReciboImp">
                                            <thead class="table-light"><tr><th></th><th>Comprobante</th><th>Fecha</th><th class="text-end">Saldo</th><th class="text-end" style="width: 150px;">A imputar</th></tr></thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                    <div class="d-flex justify-content-end flex-wrap gap-3 small">
                                        <span>Imputado: <b class="font-monospace" id="reciboImputado"></b></span>
                                        <span>A cuenta: <b class="font-monospace" id="reciboACuenta"></b></span>
                                    </div>
                                </div>
                                <details class="ui-mas-opciones">
                                    <summary>Observaciones</summary>
                                    <textarea class="form-control mt-2" id="reciboObs" rows="2" maxlength="500" placeholder="Si se deja vacío, va la descripción del banco"></textarea>
                                </details>
                                <div class="form-check form-switch mt-3">
                                    <input class="form-check-input" type="checkbox" id="reciboConfirmar">
                                    <label class="form-check-label" for="reciboConfirmar">Confirmar el recibo y conciliar la línea <?= ui_ayuda('Confirmado: se numera, genera el asiento y el movimiento en la cuenta y queda conciliado con esta línea. Sin confirmar: queda en Borrador para revisarlo en Cobranzas; al confirmarlo allá, se concilia desde Sugerencias.') ?></label>
                                </div>
                            </div>
                            <div class="modal-footer bg-light py-2">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary" id="btnReciboGuardar"></button>
                            </div>
                        </form>
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
    <script src="bancos_conciliacion.js?v=<?= filemtime(__DIR__ . '/bancos_conciliacion.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
