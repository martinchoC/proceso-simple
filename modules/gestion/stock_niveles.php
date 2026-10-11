<?php
/**
 * Vista: Stock mínimo y máximo por boca
 * Módulo 2: Gestión de Empresas — Página ID 112 (tabla 429 gestion__stock_niveles)
 */
$pageTitle = "Stock - Mínimos y Máximos";
$currentPage = 'stock_niveles';
$modudo_idx = 2;
$pagina_idx = 112;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

$empresa_id = intval($_SESSION['empresa_id'] ?? 0);
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-7">
                    <h3 class="mb-0 fw-bold"><i class="bi bi-layers-fill me-2 text-primary"></i>Stock mínimo y máximo</h3>
                    <small class="text-muted">Por producto y boca de expendio. Si el stock de la boca cae por debajo del mínimo se genera una alerta de reposición.</small>
                </div>
                <div class="col-sm-5">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Stock</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Mínimos y Máximos</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card card-outline card-primary shadow-sm">
                <div class="card-header py-2 d-flex flex-wrap gap-2 align-items-center">
                    <label for="filtroBoca" class="form-label mb-0 small fw-bold">Boca:</label>
                    <select id="filtroBoca" class="form-select form-select-sm" style="max-width: 320px;">
                        <option value="0">Todas las bocas</option>
                    </select>
                    <div class="form-check form-switch ms-2 mb-0">
                        <input class="form-check-input" type="checkbox" id="filtroBajoMinimo">
                        <label class="form-check-label small" for="filtroBajoMinimo">Solo bajo el mínimo</label>
                    </div>
                    <div class="ms-auto d-flex gap-2" id="contenedorBotonesCreacion"></div>
                    <button type="button" id="btnRecargar" class="btn btn-outline-secondary btn-sm" title="Refrescar">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table id="tablaNiveles" class="table table-sm table-striped table-hover align-middle mb-0" style="width:100%">
                            <thead class="table-light">
                                <tr>
                                    <th>Sucursal</th>
                                    <th>Boca</th>
                                    <th>Código</th>
                                    <th>Producto</th>
                                    <th class="text-end">Stock actual</th>
                                    <th class="text-end">Mínimo</th>
                                    <th class="text-end">Máximo</th>
                                    <th class="text-center">Alerta</th>
                                    <th class="text-center">Estado</th>
                                    <th class="text-center" style="width:120px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Alta / edición -->
    <div class="modal fade" id="modalNivel" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" id="formNivel" novalidate>
                <div class="modal-header py-2">
                    <h5 class="modal-title" id="modalNivelTitulo">Nuevo nivel</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="stock_nivel_id" name="stock_nivel_id">
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-bold" for="boca_id">Boca</label>
                        <select id="boca_id" name="boca_id" class="form-select form-select-sm" required></select>
                    </div>
                    <div class="mb-2" id="grupoBuscarProducto">
                        <label class="form-label form-label-sm fw-bold" for="buscarProducto">Producto</label>
                        <input type="text" id="buscarProducto" class="form-control form-control-sm" placeholder="Código, nombre o código de barras (mín. 2 letras)" autocomplete="off">
                        <select id="producto_id" name="producto_id" class="form-select form-select-sm mt-1" size="6" required></select>
                    </div>
                    <div class="mb-2 d-none" id="grupoProductoFijo">
                        <label class="form-label form-label-sm fw-bold">Producto</label>
                        <div class="form-control form-control-sm bg-light" id="productoFijo"></div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label form-label-sm fw-bold" for="stock_minimo">Stock mínimo</label>
                            <input type="number" step="any" min="0" id="stock_minimo" name="stock_minimo" class="form-control form-control-sm text-end" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label form-label-sm fw-bold" for="stock_maximo">Stock máximo</label>
                            <input type="number" step="any" min="0" id="stock_maximo" name="stock_maximo" class="form-control form-control-sm text-end" placeholder="Sin tope">
                        </div>
                    </div>
                    <small class="text-muted d-block mt-1">La alerta se dispara cuando el stock queda <strong>por debajo</strong> del mínimo. El máximo solo se usa para calcular cuánto reponer.</small>
                    <div class="mt-2">
                        <label class="form-label form-label-sm fw-bold" for="observaciones">Observaciones</label>
                        <input type="text" maxlength="255" id="observaciones" name="observaciones" class="form-control form-control-sm">
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm" id="btnGuardarNivel"><i class="fas fa-check me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Copiar de otra boca -->
    <div class="modal fade" id="modalCopiar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" id="formCopiar" novalidate>
                <div class="modal-header py-2">
                    <h5 class="modal-title">Copiar niveles de otra boca</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-bold" for="boca_origen_id">Desde</label>
                        <select id="boca_origen_id" name="boca_origen_id" class="form-select form-select-sm" required></select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-bold" for="boca_destino_id">Hacia</label>
                        <select id="boca_destino_id" name="boca_destino_id" class="form-select form-select-sm" required></select>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="sobrescribir" name="sobrescribir" value="1">
                        <label class="form-check-label small" for="sobrescribir">Sobrescribir los productos que ya tienen nivel en el destino</label>
                    </div>
                    <small class="text-muted d-block mt-2">Se copian solo los niveles activos. Los productos que queden bajo el mínimo en el destino generan su alerta en el momento.</small>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-info btn-sm"><i class="bi bi-layers-fill me-1"></i>Copiar</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const SNV_EMPRESA_ID = <?= $empresa_id ?>;
</script>
<script src="stock_niveles.js?v=<?= filemtime(__DIR__ . '/stock_niveles.js') ?>"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
