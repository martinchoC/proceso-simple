<?php
/**
 * Distribución - Configuración: cuerpo de la página, común a gestion (148) y gestion_normal (648). Lo incluye
 * distribucion_config.php del módulo después de header1.php. Comportamiento: assets/js/distribucion-config.js
 * (global DistConfig); backend: core/distribucion/distribucion_config_abm.php (vdcf_despachar).
 * Variables: $vdis_activo (indicador de la empresa), $vdis_estructura (script aplicado).
 */
?>
<main class="app-main">
    <div class="app-content">
        <div class="container-fluid">
            <?php ui_encabezado('Distribución - Configuración', 'fas fa-truck',
                'Zonas de reparto (con sus días), la zona de cada cliente o de una de sus sucursales de entrega, y los camiones con su capacidad en kilos y metros cúbicos para el armado del reparto.',
                ['Ventas']); ?>

            <?php if (!$vdis_estructura): ?>
                <div class="alert alert-warning">Falta aplicar el script <code>20261119_distribucion_01.sql</code>.</div>
            <?php else: ?>
            <?php if (!$vdis_activo): ?>
                <div class="alert alert-info small"><i class="fas fa-circle-info me-1"></i>La empresa no tiene prendido "Usa distribución" (Configuración &gt; Empresas): la configuración se puede cargar, pero los pedidos todavía no pasan por el armado de reparto.</div>
            <?php endif; ?>

            <ul class="nav ui-solapas mb-3" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#dcfTabZonas" type="button">Zonas</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#dcfTabClientes" type="button" id="dcfTabClientesBtn">Clientes por zona</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#dcfTabSin" type="button" id="dcfTabSinBtn">Clientes sin zona</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#dcfTabCamiones" type="button" id="dcfTabCamionesBtn">Camiones</button></li>
            </ul>

            <div class="tab-content">
                <!-- Zonas -->
                <div class="tab-pane fade show active" id="dcfTabZonas">
                    <div class="card">
                        <div class="card-header ui-barra">
                            <div class="ui-filtros d-flex flex-wrap gap-2 align-items-center">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="dcfZonasInactivas">
                                    <label class="form-check-label small" for="dcfZonasInactivas">Mostrar inactivas</label>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary ms-auto" id="dcfZonaNueva" data-funcion="agregar"><i class="fas fa-plus me-1"></i>Nueva zona</button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead><tr><th>Zona</th><th>Código</th><th>Días de reparto</th><th class="text-end">Clientes</th><th>Estado</th><th>Acciones</th></tr></thead>
                                    <tbody id="dcfZonasFilas"><tr><td colspan="6" class="text-muted small p-3">Cargando…</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Clientes de una zona -->
                <div class="tab-pane fade" id="dcfTabClientes">
                    <div class="card">
                        <div class="card-header ui-barra">
                            <div class="ui-filtros d-flex flex-wrap gap-2 align-items-center">
                                <select class="form-select form-select-sm ui-f-ancha" id="dcfZonaSel"></select>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="ui-seccion mb-3">
                                <div class="ui-seccion-titulo">Agregar clientes <?= ui_ayuda('Busque el cliente. Si tiene otra zona, pasa a esta desde la fecha elegida. Con "Solo una sucursal" la zona vale solo para esa dirección de entrega (manda sobre la del cliente).') ?></div>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <input type="search" class="form-control form-control-sm ui-f-ancha" id="dcfBuscarCliente" placeholder="Buscar cliente por nombre o CUIT" autocomplete="off">
                                    <label class="small text-muted mb-0" for="dcfAsignarFecha">Desde</label>
                                    <input type="date" class="form-control form-control-sm ui-f-fecha" id="dcfAsignarFecha">
                                </div>
                                <div class="list-group mt-1" id="dcfBuscarResultados" style="max-height: 240px; overflow-y: auto;"></div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <button type="button" class="btn btn-sm btn-outline-danger" id="dcfQuitar" data-funcion="editar" disabled><i class="fas fa-minus me-1"></i>Quitar de la zona los marcados</button>
                                <span class="small text-muted align-self-center" id="dcfZonaResumen"></span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead><tr><th style="width: 32px;"><input type="checkbox" class="form-check-input" id="dcfMarcarTodos"></th><th>Cliente</th><th>Sucursal de entrega</th><th>Desde</th></tr></thead>
                                    <tbody id="dcfZonaClientesFilas"><tr><td colspan="4" class="text-muted small p-3">Elija una zona.</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Clientes sin zona -->
                <div class="tab-pane fade" id="dcfTabSin">
                    <div class="card">
                        <div class="card-header ui-barra">
                            <div class="ui-filtros d-flex flex-wrap gap-2 align-items-center">
                                <input type="search" class="form-control form-control-sm ui-f-ancha" id="dcfSinQ" placeholder="Buscar por nombre o CUIT">
                                <select class="form-select form-select-sm ui-f-media" id="dcfSinZona"></select>
                                <button type="button" class="btn btn-sm btn-primary" id="dcfSinAsignar" data-funcion="editar" disabled><i class="fas fa-map-marker-alt me-1"></i>Poner los marcados en la zona</button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead><tr><th style="width: 32px;"><input type="checkbox" class="form-check-input" id="dcfSinTodos"></th><th>Cliente</th><th>CUIT</th></tr></thead>
                                    <tbody id="dcfSinFilas"><tr><td colspan="3" class="text-muted small p-3">Cargando…</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Camiones -->
                <div class="tab-pane fade" id="dcfTabCamiones">
                    <div class="card">
                        <div class="card-header ui-barra">
                            <div class="ui-filtros d-flex flex-wrap gap-2 align-items-center">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="dcfCamionesInactivos">
                                    <label class="form-check-label small" for="dcfCamionesInactivos">Mostrar inactivos</label>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary ms-auto" id="dcfCamionNuevo" data-funcion="agregar"><i class="fas fa-plus me-1"></i>Nuevo camión</button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead><tr><th>Camión</th><th>Patente</th><th class="text-end">Capacidad kg</th><th class="text-end">Capacidad m³</th><th>Depósito</th><th>Chofer</th><th>Estado</th><th>Acciones</th></tr></thead>
                                    <tbody id="dcfCamionesFilas"><tr><td colspan="8" class="text-muted small p-3">Cargando…</td></tr></tbody>
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

<!-- Zona -->
<div class="modal fade" id="dcfModalZona" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="dcfZonaTitulo">Zona</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <input type="hidden" id="dcfZonaId">
                <div class="row g-2">
                    <div class="col-8"><label class="form-label small ui-requerido" for="dcfZonaNombre">Nombre</label><input type="text" class="form-control form-control-sm" id="dcfZonaNombre" maxlength="100"></div>
                    <div class="col-4"><label class="form-label small" for="dcfZonaCodigo">Código</label><input type="text" class="form-control form-control-sm" id="dcfZonaCodigo" maxlength="20"></div>
                    <div class="col-12">
                        <label class="form-label small d-block">Días de reparto</label>
                        <div id="dcfZonaDias" class="d-flex flex-wrap gap-2"></div>
                    </div>
                    <div class="col-4"><label class="form-label small" for="dcfZonaOrden">Orden</label><input type="number" class="form-control form-control-sm" id="dcfZonaOrden" min="0" step="1" value="0"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-sm btn-primary" id="dcfZonaGuardar">Guardar</button></div>
        </div>
    </div>
</div>

<!-- Camión -->
<div class="modal fade" id="dcfModalCamion" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="dcfCamionTitulo">Camión</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <input type="hidden" id="dcfCamionId">
                <div class="row g-2">
                    <div class="col-8"><label class="form-label small ui-requerido" for="dcfCamionNombre">Nombre</label><input type="text" class="form-control form-control-sm" id="dcfCamionNombre" maxlength="100"></div>
                    <div class="col-4"><label class="form-label small" for="dcfCamionPatente">Patente</label><input type="text" class="form-control form-control-sm" id="dcfCamionPatente" maxlength="15"></div>
                    <div class="col-6"><label class="form-label small" for="dcfCamionKg">Capacidad (kg) <?= ui_ayuda('Vacío = sin control de peso en el armado.') ?></label><input type="number" class="form-control form-control-sm text-end" id="dcfCamionKg" min="0" step="0.01"></div>
                    <div class="col-6"><label class="form-label small" for="dcfCamionM3">Capacidad (m³) <?= ui_ayuda('Vacío = sin control de volumen en el armado.') ?></label><input type="number" class="form-control form-control-sm text-end" id="dcfCamionM3" min="0" step="0.001"></div>
                    <div class="col-6"><label class="form-label small" for="dcfCamionBoca">Depósito habitual</label><select class="form-select form-select-sm" id="dcfCamionBoca"></select></div>
                    <div class="col-6"><label class="form-label small" for="dcfCamionChofer">Chofer habitual</label><input type="text" class="form-control form-control-sm" id="dcfCamionChofer" maxlength="100"></div>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-sm btn-primary" id="dcfCamionGuardar">Guardar</button></div>
        </div>
    </div>
</div>

<!-- Sucursal de entrega (al agregar un cliente a la zona) -->
<div class="modal fade" id="dcfModalSucursal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Zona de <span id="dcfSucCliente"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <label class="form-label small" for="dcfSucSel">Para</label>
                <select class="form-select form-select-sm" id="dcfSucSel"></select>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-sm btn-primary" id="dcfSucGuardar">Agregar a la zona</button></div>
        </div>
    </div>
</div>
