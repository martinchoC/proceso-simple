<?php
/**
 * Vista: Gestión del Cliente (ficha del cliente del estudio, todo lo que el estudio le hace en un solo lugar)
 * Módulo 4: Estudio Contable (cliente_gestion.php) - Página ID: 422
 * Las solapas dependen del servicio (nivel_servicio): ver cliente_gestion_model.php.
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Gestión del Cliente";
$currentPage = 'cliente_gestion';
$modudo_idx = 4;
$pagina_idx = 422;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

$selector = '<select id="cgCliente" class="form-select form-select-sm" style="min-width:280px" aria-label="Cliente"><option value="">Elegí un cliente…</option></select>';
?>

<main class="app-main">
    <?php ui_encabezado('Gestión del Cliente', 'bi bi-person-vcard-fill',
        'Todo lo que el estudio hace para un cliente en una sola pantalla. Las solapas dependen del servicio contratado: Padrón (vencimientos, tareas, honorarios e inspecciones), Fiscal (además IVA), Contable (además contabilidad) y Gestión completa (además la operación de su empresa). Los datos de la empresa del cliente se leen solo si ella le dio permiso al estudio en Vinculaciones entre empresas.',
        ['Gestión de Clientes'], $selector); ?>

    <div class="app-content">
        <div class="container-fluid">

            <div id="cgVacio" class="text-center text-muted py-5">
                <i class="bi bi-person-vcard fs-1 d-block mb-2"></i>Elegí un cliente para ver su gestión.
            </div>

            <div id="cgFicha" class="d-none">
                <!-- Encabezado del cliente -->
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-body py-3">
                        <div class="d-flex flex-wrap align-items-start gap-3">
                            <div class="flex-grow-1">
                                <h4 class="mb-1 fw-bold" id="cgRazon"></h4>
                                <div class="text-muted small" id="cgDatos"></div>
                                <div class="mt-2" id="cgBadges"></div>
                            </div>
                            <div class="d-flex flex-wrap gap-1" id="cgAccesos"></div>
                        </div>
                        <div class="mt-2 small d-none" id="cgVinculacion"></div>
                    </div>
                </div>

                <ul class="nav nav-tabs ui-solapas mb-3" role="tablist" id="cgSolapas">
                    <li class="nav-item" data-solapa="resumen"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#sResumen" type="button">Resumen</button></li>
                    <li class="nav-item" data-solapa="vencimientos"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sVencimientos" type="button">Vencimientos</button></li>
                    <li class="nav-item" data-solapa="tareas"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sTareas" type="button">Tareas</button></li>
                    <li class="nav-item" data-solapa="honorarios"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sHonorarios" type="button">Honorarios</button></li>
                    <li class="nav-item" data-solapa="inspecciones"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sInspecciones" type="button">Inspecciones</button></li>
                    <li class="nav-item" data-solapa="fiscal"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sFiscal" type="button">IVA</button></li>
                    <li class="nav-item" data-solapa="contable"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sContable" type="button">Contabilidad</button></li>
                    <li class="nav-item" data-solapa="gestion"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#sGestion" type="button">Su empresa</button></li>
                </ul>

                <div class="tab-content">
                    <!-- Resumen -->
                    <div class="tab-pane fade show active" id="sResumen">
                        <div class="row g-3 mb-3">
                            <div class="col-6 col-lg-3"><div class="ui-kpi">
                                <div class="ui-kpi-etiqueta">Vencimientos vencidos</div>
                                <div class="ui-kpi-valor" id="kVencidos">–</div>
                                <div class="ui-kpi-sub" id="kProximo"></div></div></div>
                            <div class="col-6 col-lg-3"><div class="ui-kpi">
                                <div class="ui-kpi-etiqueta">Tareas abiertas</div>
                                <div class="ui-kpi-valor" id="kTareas">–</div>
                                <div class="ui-kpi-sub" id="kTareasAtr"></div></div></div>
                            <div class="col-6 col-lg-3"><div class="ui-kpi">
                                <div class="ui-kpi-etiqueta">Inspecciones abiertas</div>
                                <div class="ui-kpi-valor" id="kInsp">–</div></div></div>
                            <div class="col-6 col-lg-3"><div class="ui-kpi">
                                <div class="ui-kpi-etiqueta">Abono mensual</div>
                                <div class="ui-kpi-valor" id="kAbono">–</div></div></div>
                        </div>
                        <div class="card shadow-sm border-0">
                            <div class="card-body">
                                <div class="ui-seccion-titulo mb-2">Datos del cliente</div>
                                <dl class="row small mb-0" id="cgFichaDatos"></dl>
                            </div>
                        </div>
                    </div>

                    <!-- Vencimientos -->
                    <div class="tab-pane fade" id="sVencimientos">
                        <div class="card shadow-sm border-0 mb-3"><div class="card-body p-0">
                            <div class="px-3 pt-3 ui-seccion-titulo">Pendientes (vencidos y próximos 90 días)</div>
                            <div class="table-responsive"><table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light"><tr><th>Vence</th><th>Obligación</th><th>Período</th><th>Estado</th><th class="text-end">A pagar</th></tr></thead>
                                <tbody id="tbVencAbiertos"></tbody></table></div>
                        </div></div>
                        <div class="card shadow-sm border-0"><div class="card-body p-0">
                            <div class="px-3 pt-3 ui-seccion-titulo">Últimos presentados o pagados</div>
                            <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                                <thead class="table-light"><tr><th>Vencía</th><th>Obligación</th><th>Período</th><th>Estado</th><th>Presentado</th><th>Pagado</th></tr></thead>
                                <tbody id="tbVencCerrados"></tbody></table></div>
                        </div></div>
                    </div>

                    <!-- Tareas -->
                    <div class="tab-pane fade" id="sTareas">
                        <div class="card shadow-sm border-0"><div class="card-body p-0"><div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light"><tr><th>Tarea</th><th>Límite</th><th>Estado</th><th>Responsable</th><th>Aprueba</th><th class="text-end">Horas</th></tr></thead>
                                <tbody id="tbTareas"></tbody></table>
                        </div></div></div>
                    </div>

                    <!-- Honorarios -->
                    <div class="tab-pane fade" id="sHonorarios">
                        <div class="row g-3 mb-3">
                            <div class="col-6 col-lg-3"><div class="ui-kpi">
                                <div class="ui-kpi-etiqueta">Saldo a cobrar</div>
                                <div class="ui-kpi-valor" id="kHonSaldo">–</div>
                                <div class="ui-kpi-sub">facturas del estudio al cliente</div></div></div>
                        </div>
                        <div class="row g-3">
                            <div class="col-lg-5">
                                <div class="card shadow-sm border-0 mb-3"><div class="card-body p-0">
                                    <div class="px-3 pt-3 ui-seccion-titulo">Abonos</div>
                                    <table class="table table-sm align-middle mb-0"><thead class="table-light"><tr><th>Concepto</th><th class="text-end">Importe</th><th>Último facturado</th></tr></thead>
                                        <tbody id="tbAbonos"></tbody></table>
                                </div></div>
                                <div class="card shadow-sm border-0"><div class="card-body p-0">
                                    <div class="px-3 pt-3 ui-seccion-titulo">Novedades sin facturar</div>
                                    <table class="table table-sm align-middle mb-0"><thead class="table-light"><tr><th>Período</th><th>Concepto</th><th class="text-end">Importe</th></tr></thead>
                                        <tbody id="tbNovedades"></tbody></table>
                                </div></div>
                            </div>
                            <div class="col-lg-7">
                                <div class="card shadow-sm border-0"><div class="card-body p-0">
                                    <div class="px-3 pt-3 ui-seccion-titulo">Últimas facturas emitidas</div>
                                    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                                        <thead class="table-light"><tr><th>Fecha</th><th>Comprobante</th><th>Estado</th><th class="text-end">Total</th><th class="text-end">Saldo</th></tr></thead>
                                        <tbody id="tbFactHon"></tbody></table></div>
                                </div></div>
                            </div>
                        </div>
                    </div>

                    <!-- Inspecciones -->
                    <div class="tab-pane fade" id="sInspecciones">
                        <div class="card shadow-sm border-0"><div class="card-body p-0"><div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light"><tr><th>Organismo / Acta</th><th>Inicio</th><th>Estado</th><th class="text-end">Reclamado</th><th class="text-end">Pagado</th><th>Próximo plazo</th></tr></thead>
                                <tbody id="tbInsp"></tbody></table>
                        </div></div></div>
                    </div>

                    <!-- IVA -->
                    <div class="tab-pane fade" id="sFiscal">
                        <div class="alert alert-warning py-2 d-none" id="fiscalAviso"></div>
                        <div class="card shadow-sm border-0"><div class="card-body p-0"><div class="table-responsive">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light"><tr><th>Período</th><th class="text-end">Débito</th><th class="text-end">Crédito</th><th class="text-end">Percepciones</th>
                                    <th class="text-end">Saldo a favor ant.</th><th class="text-end">A pagar</th><th class="text-end">A favor</th><th class="text-center">Comprob.</th><th>Estado</th></tr></thead>
                                <tbody id="tbFiscal"></tbody></table>
                        </div></div></div>
                        <div class="small text-muted mt-2">Mismo cálculo que Mini-Gestión: crédito fiscal solo de comprobantes A / M, percepciones de IVA y saldo a favor del período anterior; sin retenciones.</div>
                    </div>

                    <!-- Contabilidad -->
                    <div class="tab-pane fade" id="sContable">
                        <div class="alert alert-warning py-2 d-none" id="contableAviso"></div>
                        <div id="contableDatos" class="d-none">
                            <div class="row g-3 mb-3">
                                <div class="col-6 col-lg-3"><div class="ui-kpi">
                                    <div class="ui-kpi-etiqueta">Resultado del ejercicio</div>
                                    <div class="ui-kpi-valor" id="kResultado">–</div>
                                    <div class="ui-kpi-sub" id="kEjercicio"></div></div></div>
                                <div class="col-6 col-lg-3"><div class="ui-kpi">
                                    <div class="ui-kpi-etiqueta">Asientos</div>
                                    <div class="ui-kpi-valor" id="kAsientos">–</div>
                                    <div class="ui-kpi-sub" id="kAsientosSub"></div></div></div>
                                <div class="col-6 col-lg-3"><div class="ui-kpi">
                                    <div class="ui-kpi-etiqueta">Meses cerrados</div>
                                    <div class="ui-kpi-valor" id="kMesesCerrados">–</div>
                                    <div class="ui-kpi-sub" id="kMesesSub"></div></div></div>
                            </div>
                            <div class="card shadow-sm border-0"><div class="card-body p-0">
                                <div class="px-3 pt-3 ui-seccion-titulo">Saldos por rubro del ejercicio (incluye asientos en borrador, que Balances no cuenta)</div>
                                <table class="table table-sm align-middle mb-0"><thead class="table-light"><tr><th>Rubro</th><th class="text-end">Saldo deudor (+) / acreedor (−)</th></tr></thead>
                                    <tbody id="tbRubros"></tbody></table>
                            </div></div>
                        </div>
                    </div>

                    <!-- Gestión completa -->
                    <div class="tab-pane fade" id="sGestion">
                        <div class="alert alert-warning py-2 d-none" id="gestionAviso"></div>
                        <div id="gestionDatos" class="d-none">
                            <div class="row g-3 mb-3" id="gestionKpis"></div>
                            <div class="card shadow-sm border-0 d-none" id="gestionTesoreria"><div class="card-body p-0">
                                <div class="px-3 pt-3 ui-seccion-titulo">Cajas y bancos</div>
                                <table class="table table-sm align-middle mb-0"><thead class="table-light"><tr><th>Tipo</th><th>Cuenta</th><th>Moneda</th><th class="text-end">Saldo</th></tr></thead>
                                    <tbody id="tbTesoreria"></tbody></table>
                            </div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
    <?php require_once __DIR__ . '/estudio_cliente_activo.php'; estudio_cliente_activo_script((int) $current_empresa_id); ?>
    <script src="cliente_gestion.js?v=<?= filemtime(__DIR__ . '/cliente_gestion.js') ?>"></script>
</main>

<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
</body>
</html>
