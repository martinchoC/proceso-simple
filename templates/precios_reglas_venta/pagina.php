<?php
/**
 * Escalas y promociones: cuerpo de la página, común a gestion (146) y gestion_normal (646). Lo incluye
 * precios_reglas_venta.php del módulo después de header1.php. Comportamiento: assets/js/precios-reglas-venta.js
 * (global PrecioReglas); backend: core/precios_venta_abm.php (prva_despachar).
 * Variables: $prva_activo (indicador de la empresa), $prva_estructura (script aplicado).
 */
?>
<main class="app-main">
    <div class="app-content">
        <div class="container-fluid">
            <?php ui_encabezado('Escalas y promociones', 'fas fa-percent',
                'Descuentos por cantidad y promociones con vigencia que se aplican al vender. El descuento automático es el mayor entre el del cliente, la escala y la promoción (no se suman), hasta el tope de la lista. Encima va el descuento manual del vendedor, hasta el tope de su perfil.',
                ['Ventas'],
                $prva_estructura ? '<button type="button" class="btn btn-sm btn-primary" id="prvaNueva" data-funcion="agregar"><i class="fas fa-plus me-1"></i>Nueva regla</button>' : ''); ?>

            <?php if (!$prva_estructura): ?>
                <div class="alert alert-warning">Falta aplicar el script <code>20261117_reglas_precio_venta_01.sql</code>.</div>
            <?php else: ?>
            <?php if (!$prva_activo): ?>
                <div class="alert alert-info small"><i class="fas fa-circle-info me-1"></i>La empresa no tiene prendido "Usa escalas y promociones" (Configuración &gt; Empresas): las reglas se pueden cargar, pero todavía no se aplican al vender.</div>
            <?php endif; ?>

            <ul class="nav ui-solapas mb-3" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#prvaTabReglas" type="button">Reglas</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#prvaTabTopes" type="button">Topes de descuento</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#prvaTabPrueba" type="button">Probar</button></li>
            </ul>

            <div class="tab-content">
                <div class="tab-pane fade show active" id="prvaTabReglas">
                    <div class="card">
                        <div class="card-header ui-barra">
                            <div class="ui-filtros d-flex flex-wrap gap-2">
                                <select class="form-select form-select-sm ui-f-media" id="prvaFiltroTipo">
                                    <option value="">Escalas y promociones</option>
                                    <option value="ESCALA">Escalas por cantidad</option>
                                    <option value="PROMOCION">Promociones</option>
                                </select>
                                <select class="form-select form-select-sm ui-f-media" id="prvaFiltroEstado">
                                    <option value="1">Activas</option>
                                    <option value="2">Inactivas</option>
                                    <option value="0">Todas</option>
                                </select>
                                <div class="form-check form-switch align-self-center">
                                    <input class="form-check-input" type="checkbox" id="prvaFiltroVigentes">
                                    <label class="form-check-label small" for="prvaFiltroVigentes">Solo vigentes hoy</label>
                                </div>
                                <input type="search" class="form-control form-control-sm ui-f-ancha" id="prvaFiltroQ" placeholder="Buscar por nombre o producto">
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0">
                                    <thead><tr><th>Nombre</th><th>Tipo</th><th>A qué se aplica</th><th>Descuento</th><th>Vigencia</th><th>Estado</th><th>Acciones</th></tr></thead>
                                    <tbody id="prvaFilas"><tr><td colspan="7" class="text-muted small p-3">Cargando…</td></tr></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="prvaTabTopes">
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <div class="card h-100">
                                <div class="card-header"><strong>Descuento manual por perfil</strong>
                                    <?= ui_ayuda('Lo máximo que puede agregar a mano, encima del automático, un usuario con ese perfil. Si se pasa, el pedido queda en Borrador y lo confirma alguien con un tope mayor. Sin tope = 0 %.') ?></div>
                                <div class="card-body p-0">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead><tr><th>Perfil</th><th style="width:9rem">Tope %</th><th style="width:6rem"></th></tr></thead>
                                        <tbody id="prvaTopes"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-6">
                            <div class="card h-100">
                                <div class="card-header"><strong>Tope del descuento automático por lista</strong>
                                    <?= ui_ayuda('El descuento automático (cliente, escala o promoción) de los clientes con esa lista no pasa de este %. Vacío = sin tope.') ?></div>
                                <div class="card-body p-0">
                                    <table class="table table-sm align-middle mb-0">
                                        <thead><tr><th>Lista</th><th style="width:9rem">Tope %</th><th style="width:6rem"></th></tr></thead>
                                        <tbody id="prvaTopesLista"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="prvaTabPrueba">
                    <div class="card">
                        <div class="card-body">
                            <div class="ui-seccion">
                                <div class="ui-seccion-titulo">¿Qué descuento le corresponde?</div>
                                <div class="row g-2 align-items-end">
                                    <div class="col-md-4 position-relative">
                                        <label class="form-label small mb-1">Producto</label>
                                        <input type="search" class="form-control form-control-sm" id="prvaPruebaProducto" placeholder="Código o nombre" autocomplete="off">
                                        <div class="list-group position-absolute w-100 shadow-sm" id="prvaPruebaProductos" style="z-index:20"></div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1">Presentación</label>
                                        <select class="form-select form-select-sm" id="prvaPruebaPres"><option value="0">Unidad</option></select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1">Cantidad (unidades)</label>
                                        <input type="number" class="form-control form-control-sm" id="prvaPruebaCant" min="0" step="any" value="1">
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1">Lista</label>
                                        <select class="form-select form-select-sm" id="prvaPruebaLista"></select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label small mb-1">Sucursal</label>
                                        <select class="form-select form-select-sm" id="prvaPruebaSucursal"></select>
                                    </div>
                                </div>
                                <div class="row g-2 align-items-end mt-1">
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Descuento del cliente %</label>
                                        <input type="number" class="form-control form-control-sm" id="prvaPruebaCliente" min="0" max="100" step="0.01" value="0">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label small mb-1">Fecha</label>
                                        <input type="date" class="form-control form-control-sm" id="prvaPruebaFecha">
                                    </div>
                                    <div class="col-md-2"><button type="button" class="btn btn-sm btn-primary" id="prvaPruebaCalcular">Calcular</button></div>
                                </div>
                            </div>
                            <div id="prvaPruebaResultado" class="mt-3"></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php if ($prva_estructura): ?>
<div class="modal fade" id="prvaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="prvaModalTitulo">Nueva regla</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="prvaId" value="0">
                <div class="ui-seccion">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label small mb-1 ui-requerido">Tipo</label>
                            <select class="form-select form-select-sm" id="prvaTipo">
                                <option value="ESCALA">Escala por cantidad</option>
                                <option value="PROMOCION">Promoción</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small mb-1 ui-requerido">Nombre</label>
                            <input type="text" class="form-control form-control-sm" id="prvaNombre" maxlength="100">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1 ui-requerido">Desde</label>
                            <input type="date" class="form-control form-control-sm" id="prvaDesde">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Hasta <?= ui_ayuda('Vacío = sin fecha de fin.') ?></label>
                            <input type="date" class="form-control form-control-sm" id="prvaHasta">
                        </div>
                        <div class="col-md-4" id="prvaBloquePct">
                            <label class="form-label small mb-1 ui-requerido">Descuento %</label>
                            <input type="number" class="form-control form-control-sm" id="prvaPct" min="0" max="100" step="0.01">
                        </div>
                    </div>
                </div>
                <div class="ui-seccion" id="prvaBloqueTramos">
                    <div class="ui-seccion-titulo">Tramos <?= ui_ayuda('La cantidad es en unidades del producto y se cuenta sumando todas las líneas del producto en el comprobante (cajas y sueltas). Se aplica el tramo más alto alcanzado.') ?></div>
                    <table class="table table-sm align-middle mb-1">
                        <thead><tr><th>Desde (unidades)</th><th>Descuento %</th><th style="width:3rem"></th></tr></thead>
                        <tbody id="prvaTramos"></tbody>
                    </table>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="prvaTramoMas"><i class="fas fa-plus me-1"></i>Tramo</button>
                </div>
                <div class="ui-seccion">
                    <div class="ui-seccion-titulo">A qué se aplica <?= ui_ayuda('Vacío = todos. Sin producto ni categoría, vale para todos los productos.') ?></div>
                    <div class="row g-2">
                        <div class="col-md-6 position-relative">
                            <label class="form-label small mb-1">Producto</label>
                            <input type="hidden" id="prvaProductoId" value="">
                            <div class="input-group input-group-sm">
                                <input type="search" class="form-control" id="prvaProductoTexto" placeholder="Todos (buscá por código o nombre)" autocomplete="off">
                                <button class="btn btn-outline-secondary" type="button" id="prvaProductoQuitar" title="Quitar"><i class="fas fa-times"></i></button>
                            </div>
                            <div class="list-group position-absolute w-100 shadow-sm" id="prvaProductos" style="z-index:20"></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Presentación</label>
                            <select class="form-select form-select-sm" id="prvaPres"><option value="">Cualquiera</option></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Categoría</label>
                            <select class="form-select form-select-sm" id="prvaCategoria"></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Lista de precios</label>
                            <select class="form-select form-select-sm" id="prvaLista"></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Canal</label>
                            <select class="form-select form-select-sm" id="prvaCanal"></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small mb-1">Sucursal</label>
                            <select class="form-select form-select-sm" id="prvaSucursal"></select>
                        </div>
                    </div>
                </div>
                <details class="ui-mas-opciones">
                    <summary>Más opciones</summary>
                    <div class="row g-2 mt-1">
                        <div class="col-md-4">
                            <label class="form-label small mb-1">Prioridad <?= ui_ayuda('Desempata reglas del mismo tipo con el mismo %: gana la mayor.') ?></label>
                            <input type="number" class="form-control form-control-sm" id="prvaPrioridad" min="0" value="0">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label small mb-1">Observaciones</label>
                            <input type="text" class="form-control form-control-sm" id="prvaObs" maxlength="255">
                        </div>
                    </div>
                </details>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-primary" id="prvaGuardar">Guardar</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>
