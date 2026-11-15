<?php
/**
 * Vendedores y carteras: cuerpo de la página, común a gestion (147) y gestion_normal (647). Lo incluye vendedores.php
 * del módulo después de header1.php. Comportamiento: assets/js/vendedores.js (global Vendedores); backend:
 * core/vendedores_abm.php (vabm_despachar).
 * Variables: $vabm_activo (indicador de la empresa), $vabm_estructura (script aplicado).
 */
?>
<main class="app-main">
    <div class="app-content">
        <div class="container-fluid">
            <?php ui_encabezado('Vendedores y carteras', 'fas fa-user-tie',
                'Cada vendedor es una entidad con el rol Vendedor. Se le liga el usuario con el que entra al sistema y se le asignan clientes con fecha desde: un cliente tiene un solo vendedor a una fecha. El usuario vendedor ve y carga solo pedidos de su cartera, salvo que su perfil tenga "Ver todas las carteras".',
                ['Ventas'],
                $vabm_estructura ? '<button type="button" class="btn btn-sm btn-primary" id="vabmNuevo" data-funcion="agregar"><i class="fas fa-plus me-1"></i>Nuevo vendedor</button>' : ''); ?>

            <?php if (!$vabm_estructura): ?>
                <div class="alert alert-warning">Falta aplicar el script <code>20261118_vendedores_carteras_01.sql</code>.</div>
            <?php else: ?>
            <?php if (!$vabm_activo): ?>
                <div class="alert alert-info small"><i class="fas fa-circle-info me-1"></i>La empresa no tiene prendido "Usa vendedores y carteras" (Configuración &gt; Empresas): los vendedores y las carteras se pueden cargar, pero todavía no se aplican a los pedidos.</div>
            <?php endif; ?>

            <ul class="nav ui-solapas mb-3" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#vabmTabVendedores" type="button">Vendedores</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#vabmTabCartera" type="button" id="vabmTabCarteraBtn">Cartera</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#vabmTabSin" type="button" id="vabmTabSinBtn">Clientes sin vendedor</button></li>
            </ul>

            <div class="tab-content">
                <!-- Vendedores -->
                <div class="tab-pane fade show active" id="vabmTabVendedores">
                    <div class="card">
                        <div class="card-header ui-barra">
                            <div class="ui-filtros d-flex flex-wrap gap-2">
                                <div class="form-check form-switch align-self-center">
                                    <input class="form-check-input" type="checkbox" id="vabmBajas">
                                    <label class="form-check-label small" for="vabmBajas">Mostrar dados de baja</label>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead><tr><th>Vendedor</th><th>CUIT / documento</th><th>Usuario</th><th class="text-end">Clientes</th><th>Estado</th><th>Acciones</th></tr></thead>
                                    <tbody id="vabmFilas"><tr><td colspan="6" class="text-muted small p-3">Cargando…</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cartera de un vendedor -->
                <div class="tab-pane fade" id="vabmTabCartera">
                    <div class="card">
                        <div class="card-header ui-barra">
                            <div class="ui-filtros d-flex flex-wrap gap-2 align-items-center">
                                <select class="form-select form-select-sm ui-f-ancha" id="vabmCarteraVendedor"></select>
                                <label class="small text-muted mb-0" for="vabmCarteraFecha">A la fecha</label>
                                <input type="date" class="form-control form-control-sm ui-f-fecha" id="vabmCarteraFecha">
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="ui-seccion mb-3">
                                <div class="ui-seccion-titulo">Agregar clientes <?= ui_ayuda('Busque un cliente por nombre o CUIT. Si ya tiene otro vendedor, pasa a este desde la fecha elegida (la asignación anterior queda en el historial).') ?></div>
                                <div class="d-flex flex-wrap gap-2 align-items-center">
                                    <input type="search" class="form-control form-control-sm ui-f-ancha" id="vabmBuscarCliente" placeholder="Buscar cliente por nombre o CUIT" autocomplete="off">
                                    <label class="small text-muted mb-0" for="vabmAsignarFecha">Desde</label>
                                    <input type="date" class="form-control form-control-sm ui-f-fecha" id="vabmAsignarFecha">
                                </div>
                                <div class="list-group mt-1" id="vabmBuscarResultados" style="max-height: 240px; overflow-y: auto;"></div>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                <button type="button" class="btn btn-sm btn-outline-danger" id="vabmQuitar" data-funcion="editar" disabled><i class="fas fa-user-minus me-1"></i>Quitar los marcados</button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="vabmPasar" data-funcion="editar"><i class="fas fa-right-left me-1"></i>Pasar toda la cartera a otro vendedor</button>
                                <span class="small text-muted align-self-center" id="vabmCarteraResumen"></span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead><tr><th style="width: 32px;"><input type="checkbox" class="form-check-input" id="vabmMarcarTodos"></th><th>Cliente</th><th>CUIT</th><th>Desde</th><th>Acciones</th></tr></thead>
                                    <tbody id="vabmCarteraFilas"><tr><td colspan="5" class="text-muted small p-3">Elija un vendedor.</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Clientes sin vendedor -->
                <div class="tab-pane fade" id="vabmTabSin">
                    <div class="card">
                        <div class="card-header ui-barra">
                            <div class="ui-filtros d-flex flex-wrap gap-2 align-items-center">
                                <input type="search" class="form-control form-control-sm ui-f-ancha" id="vabmSinQ" placeholder="Buscar por nombre o CUIT">
                                <select class="form-select form-select-sm ui-f-media" id="vabmSinVendedor"></select>
                                <input type="date" class="form-control form-control-sm ui-f-fecha" id="vabmSinFecha">
                                <button type="button" class="btn btn-sm btn-primary" id="vabmSinAsignar" data-funcion="editar" disabled><i class="fas fa-user-plus me-1"></i>Asignar los marcados</button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead><tr><th style="width: 32px;"><input type="checkbox" class="form-check-input" id="vabmSinTodos"></th><th>Cliente</th><th>CUIT</th></tr></thead>
                                    <tbody id="vabmSinFilas"><tr><td colspan="3" class="text-muted small p-3">Cargando…</td></tr></tbody>
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

<!-- Alta de vendedor -->
<div class="modal fade" id="vabmModalAlta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Nuevo vendedor</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <div class="ui-seccion">
                    <div class="ui-seccion-titulo">Entidad <?= ui_ayuda('Elija una entidad que ya existe (por ejemplo un empleado) o escriba el nombre para crear una nueva solo con el rol Vendedor.') ?></div>
                    <input type="search" class="form-control form-control-sm" id="vabmAltaBuscar" placeholder="Buscar entidad existente por nombre o CUIT" autocomplete="off">
                    <div class="list-group mt-1" id="vabmAltaResultados" style="max-height: 200px; overflow-y: auto;"></div>
                    <div class="small mt-1" id="vabmAltaElegida"></div>
                    <details class="ui-mas-opciones mt-2" id="vabmAltaNuevaDet">
                        <summary>Crear una entidad nueva</summary>
                        <div class="row g-2 mt-1">
                            <div class="col-8"><label class="form-label small ui-requerido" for="vabmAltaNombre">Nombre</label><input type="text" class="form-control form-control-sm" id="vabmAltaNombre" maxlength="255"></div>
                            <div class="col-4"><label class="form-label small" for="vabmAltaCuit">CUIT / DNI</label><input type="text" class="form-control form-control-sm" id="vabmAltaCuit" maxlength="13"></div>
                        </div>
                    </details>
                </div>
                <div class="ui-seccion">
                    <div class="ui-seccion-titulo">Usuario <?= ui_ayuda('Usuario con el que el vendedor entra al sistema (opcional). Con usuario, sus pedidos quedan a su nombre y ve solo los de su cartera.') ?></div>
                    <select class="form-select form-select-sm" id="vabmAltaUsuario"></select>
                    <details class="ui-mas-opciones mt-2" id="vabmAltaNuevoUsuDet">
                        <summary>Crear un usuario nuevo en el sistema</summary>
                        <div class="row g-2 mt-1">
                            <div class="col-5">
                                <label class="form-label small ui-requerido" for="vabmAltaUsuLogin">Usuario (ingreso)</label>
                                <input type="text" class="form-control form-control-sm" id="vabmAltaUsuLogin" maxlength="20" autocomplete="off" placeholder="ej. jperez">
                            </div>
                            <div class="col-7">
                                <label class="form-label small ui-requerido" for="vabmAltaUsuNombre">Nombre completo</label>
                                <input type="text" class="form-control form-control-sm" id="vabmAltaUsuNombre" maxlength="100" autocomplete="off">
                            </div>
                            <div class="col-7">
                                <label class="form-label small ui-requerido" for="vabmAltaUsuEmail">Email</label>
                                <input type="email" class="form-control form-control-sm" id="vabmAltaUsuEmail" maxlength="100" autocomplete="off">
                            </div>
                            <div class="col-5">
                                <label class="form-label small ui-requerido" for="vabmAltaUsuPass">Contraseña</label>
                                <input type="password" class="form-control form-control-sm" id="vabmAltaUsuPass" minlength="6" autocomplete="new-password" placeholder="mín. 6 caracteres">
                            </div>
                            <div class="col-12">
                                <label class="form-label small ui-requerido" for="vabmAltaUsuPerfil">Perfil en la empresa</label>
                                <select class="form-select form-select-sm" id="vabmAltaUsuPerfil"></select>
                            </div>
                        </div>
                    </details>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-sm btn-primary" id="vabmAltaGuardar">Guardar</button></div>
        </div>
    </div>
</div>

<!-- Usuario del vendedor -->
<div class="modal fade" id="vabmModalUsuario" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Usuario de <span id="vabmUsuarioNombre"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <label class="form-label small" for="vabmUsuarioSel">Usuario</label>
                <select class="form-select form-select-sm" id="vabmUsuarioSel"></select>
                <label class="form-label small mt-2" for="vabmUsuarioFecha">Desde</label>
                <input type="date" class="form-control form-control-sm" id="vabmUsuarioFecha">
                <div class="small text-muted mt-2">Si el usuario ya era otro vendedor, deja de serlo desde esa fecha.</div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-sm btn-primary" id="vabmUsuarioGuardar">Guardar</button></div>
        </div>
    </div>
</div>

<!-- Pasar la cartera -->
<div class="modal fade" id="vabmModalPasar" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Pasar la cartera</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body">
                <label class="form-label small" for="vabmPasarA">Vendedor que la recibe</label>
                <select class="form-select form-select-sm" id="vabmPasarA"></select>
                <label class="form-label small mt-2" for="vabmPasarFecha">Desde</label>
                <input type="date" class="form-control form-control-sm" id="vabmPasarFecha">
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-sm btn-primary" id="vabmPasarGuardar">Pasar</button></div>
        </div>
    </div>
</div>

<!-- Historial de un cliente -->
<div class="modal fade" id="vabmModalHistorial" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">Vendedores de <span id="vabmHistorialNombre"></span></h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button></div>
            <div class="modal-body p-0">
                <table class="table table-sm mb-0"><thead><tr><th>Vendedor</th><th>Desde</th><th>Hasta</th><th>Estado</th></tr></thead><tbody id="vabmHistorialFilas"></tbody></table>
            </div>
        </div>
    </div>
</div>
