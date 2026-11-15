<?php
/**
 * Armado y hojas de ruta: cuerpo de la página, común a gestion (149) y gestion_normal (649). Lo incluye
 * distribucion_hojas.php del módulo después de header1.php. Comportamiento: assets/js/distribucion-hojas.js
 * (global DistHojas); backend: core/distribucion/distribucion_hojas.php (vdhr_despachar).
 * Variables: $vdis_activo (indicador de la empresa), $vdis_estructura (script aplicado).
 */
?>
<main class="app-main">
    <div class="app-content">
        <div class="container-fluid">
            <?php ui_encabezado('Armado y hojas de ruta', 'fas fa-route',
                'Armado del reparto: los pedidos liberados para distribución se reparten en hojas de ruta por camión, controlando peso y volumen. Cada hoja tiene su orden de visita, la lista de carga y la rendición de lo entregado y cobrado (la rendición registra, no hace remitos ni recibos).',
                ['Ventas']); ?>

            <?php if (!$vdis_estructura): ?>
                <div class="alert alert-warning">Falta aplicar el script <code>20261119_distribucion_01.sql</code>.</div>
            <?php elseif (!$vdis_activo): ?>
                <div class="alert alert-info small"><i class="fas fa-circle-info me-1"></i>La empresa no tiene prendido "Usa distribución" (Configuración &gt; Empresas).</div>
            <?php else: ?>

            <ul class="nav ui-solapas mb-3" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#dhrTabArmado" type="button" id="dhrTabArmadoBtn">Armado</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#dhrTabHoja" type="button" id="dhrTabHojaBtn">Hoja de ruta</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#dhrTabHojas" type="button" id="dhrTabHojasBtn">Hojas</button></li>
            </ul>

            <div class="tab-content">
                <!-- Armado -->
                <div class="tab-pane fade show active" id="dhrTabArmado">
                    <div class="card mb-3">
                        <div class="card-header ui-barra">
                            <div class="ui-filtros d-flex flex-wrap gap-2 align-items-center">
                                <label class="small text-muted mb-0" for="dhrFecha">Reparto del</label>
                                <input type="date" class="form-control form-control-sm ui-f-fecha" id="dhrFecha">
                                <select class="form-select form-select-sm ui-f-media" id="dhrDeposito"></select>
                                <select class="form-select form-select-sm ui-f-media" id="dhrZona"></select>
                                <button type="button" class="btn btn-sm btn-primary ms-auto" id="dhrNuevaHoja" data-funcion="agregar"><i class="fas fa-plus me-1"></i>Nueva hoja</button>
                            </div>
                        </div>
                    </div>
                    <div class="row g-3">
                        <div class="col-lg-7">
                            <div class="card h-100">
                                <div class="card-header d-flex flex-wrap gap-2 align-items-center">
                                    <strong class="small">Pedidos para distribución</strong>
                                    <span class="small text-muted" id="dhrDispResumen"></span>
                                    <div class="ms-auto d-flex gap-2">
                                        <select class="form-select form-select-sm" id="dhrDestino" style="min-width: 200px;"></select>
                                        <button type="button" class="btn btn-sm btn-primary text-nowrap" id="dhrAgregar" data-funcion="editar" disabled><i class="fas fa-truck-loading me-1"></i>Agregar los marcados</button>
                                    </div>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive" style="max-height: 60vh; overflow-y: auto;">
                                        <table class="table table-sm table-hover align-middle mb-0">
                                            <thead><tr><th style="width: 32px;"><input type="checkbox" class="form-check-input" id="dhrDispTodos"></th><th>Pedido</th><th>Cliente / entrega</th><th>Zona</th><th class="text-end">kg</th><th class="text-end">m³</th><th class="text-end">Total</th></tr></thead>
                                            <tbody id="dhrDispFilas"><tr><td colspan="7" class="text-muted small p-3">Cargando…</td></tr></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="card h-100">
                                <div class="card-header"><strong class="small">Hojas del día</strong></div>
                                <div class="card-body" id="dhrHojasDia"><div class="text-muted small">Cargando…</div></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Una hoja -->
                <div class="tab-pane fade" id="dhrTabHoja">
                    <div class="card">
                        <div class="card-header ui-barra">
                            <div class="d-flex flex-wrap gap-2 align-items-center w-100">
                                <strong id="dhrHojaTitulo">Elija una hoja en Armado o en Hojas.</strong>
                                <span id="dhrHojaEstado"></span>
                                <div class="ms-auto d-flex flex-wrap gap-2" id="dhrHojaAcciones"></div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row g-2 mb-3 d-none" id="dhrHojaCabecera">
                                <div class="col-md-2"><label class="form-label small" for="dhrHFecha">Fecha</label><input type="date" class="form-control form-control-sm" id="dhrHFecha"></div>
                                <div class="col-md-3"><label class="form-label small" for="dhrHCamion">Camión</label><select class="form-select form-select-sm" id="dhrHCamion"></select></div>
                                <div class="col-md-2"><label class="form-label small" for="dhrHChofer">Chofer</label><input type="text" class="form-control form-control-sm" id="dhrHChofer" maxlength="100"></div>
                                <div class="col-md-2"><label class="form-label small" for="dhrHDeposito">Depósito</label><select class="form-select form-select-sm" id="dhrHDeposito"></select></div>
                                <div class="col-md-3"><label class="form-label small" for="dhrHObs">Observaciones</label><input type="text" class="form-control form-control-sm" id="dhrHObs" maxlength="255"></div>
                            </div>
                            <div id="dhrHojaCapacidad" class="mb-3"></div>
                            <div class="small text-muted mb-1 d-none" id="dhrOrdenAyuda"><i class="fas fa-grip-vertical me-1"></i>Arrastre las filas para cambiar el orden de visita.</div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead><tr><th style="width: 28px;"></th><th>#</th><th>Pedido</th><th>Cliente / entrega</th><th class="text-end">kg</th><th class="text-end">m³</th><th class="text-end">Total</th><th>Rendición</th><th></th></tr></thead>
                                    <tbody id="dhrHojaFilas"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Listado de hojas -->
                <div class="tab-pane fade" id="dhrTabHojas">
                    <div class="card">
                        <div class="card-header ui-barra">
                            <div class="ui-filtros d-flex flex-wrap gap-2 align-items-center">
                                <label class="small text-muted mb-0" for="dhrDesde">Desde</label>
                                <input type="date" class="form-control form-control-sm ui-f-fecha" id="dhrDesde">
                                <label class="small text-muted mb-0" for="dhrHasta">Hasta</label>
                                <input type="date" class="form-control form-control-sm ui-f-fecha" id="dhrHasta">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="dhrAnuladas">
                                    <label class="form-check-label small" for="dhrAnuladas">Con anuladas</label>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead><tr><th>N°</th><th>Fecha</th><th>Camión</th><th>Chofer</th><th class="text-end">Pedidos</th><th class="text-end">kg</th><th class="text-end">m³</th><th>Estado</th><th></th></tr></thead>
                                    <tbody id="dhrHojasFilas"></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Nueva hoja -->
<div class="modal fade" id="dhrModalNueva" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Nueva hoja de ruta</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-6"><label class="form-label small ui-requerido" for="dhrNFecha">Fecha de reparto</label><input type="date" class="form-control form-control-sm" id="dhrNFecha"></div>
                    <div class="col-6"><label class="form-label small" for="dhrNCamion">Camión</label><select class="form-select form-select-sm" id="dhrNCamion"></select></div>
                    <div class="col-6"><label class="form-label small" for="dhrNChofer">Chofer <?= ui_ayuda('Vacío = el chofer habitual del camión.') ?></label><input type="text" class="form-control form-control-sm" id="dhrNChofer" maxlength="100"></div>
                    <div class="col-6"><label class="form-label small" for="dhrNDeposito">Depósito <?= ui_ayuda('Vacío = el depósito habitual del camión.') ?></label><select class="form-select form-select-sm" id="dhrNDeposito"></select></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-sm btn-primary" id="dhrNGuardar">Crear</button></div>
        </div>
    </div>
</div>

<!-- Rendición de un pedido -->
<div class="modal fade" id="dhrModalRendir" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Rendición: <span id="dhrRPedido"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <div class="row g-2">
                    <div class="col-6"><label class="form-label small ui-requerido" for="dhrRResultado">Resultado</label>
                        <select class="form-select form-select-sm" id="dhrRResultado">
                            <option value="ENTREGADO">Entregado</option>
                            <option value="PARCIAL">Entregado parcial</option>
                            <option value="RECHAZADO">Rechazado</option>
                            <option value="NO_VISITADO">No visitado</option>
                        </select></div>
                    <div class="col-6"><label class="form-label small" for="dhrRMotivo">Motivo <?= ui_ayuda('Obligatorio si no se entregó todo.') ?></label><input type="text" class="form-control form-control-sm" id="dhrRMotivo" maxlength="255"></div>
                    <div class="col-6"><label class="form-label small" for="dhrRImporte">Cobrado $</label><input type="number" class="form-control form-control-sm text-end" id="dhrRImporte" min="0" step="0.01"></div>
                    <div class="col-6"><label class="form-label small" for="dhrRMedio">Medio</label><input type="text" class="form-control form-control-sm" id="dhrRMedio" maxlength="50" placeholder="Efectivo, transferencia…"></div>
                </div>
                <div class="small text-muted mt-2">La rendición registra: los remitos y los recibos se hacen en sus pantallas.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-sm btn-primary" id="dhrRGuardar">Guardar</button></div>
        </div>
    </div>
</div>
