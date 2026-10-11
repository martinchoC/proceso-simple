<?php
/**
 * Vista: Alertas de reposición de stock
 * Módulo 2: Gestión de Empresas — Página ID 113 (tabla 430 gestion__stock_alertas)
 */
$pageTitle = "Stock - Alertas de Reposición";
$currentPage = 'stock_alertas';
$modudo_idx = 2;
$pagina_idx = 113;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

$empresa_id = intval($_SESSION['empresa_id'] ?? 0);
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-7">
                    <h3 class="mb-0 fw-bold"><i class="bi bi-exclamation-triangle-fill me-2 text-warning"></i>Alertas de reposición</h3>
                    <small class="text-muted">Productos con stock por debajo del mínimo en cada boca. Se cierran solas cuando el stock vuelve al mínimo.</small>
                </div>
                <div class="col-sm-5">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Stock</a></li>
                        <li class="breadcrumb-item"><a href="stock_niveles.php">Mínimos y Máximos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Alertas</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card card-outline card-warning shadow-sm">
                <div class="card-header py-2 d-flex flex-wrap gap-2 align-items-center">
                    <div class="btn-group btn-group-sm" role="group" aria-label="Vista">
                        <input type="radio" class="btn-check" name="vista" id="vistaAbiertas" value="abiertas" checked>
                        <label class="btn btn-outline-warning" for="vistaAbiertas">Abiertas <span class="badge text-bg-warning ms-1" id="cantAbiertas">0</span></label>
                        <input type="radio" class="btn-check" name="vista" id="vistaHistorial" value="historial">
                        <label class="btn btn-outline-secondary" for="vistaHistorial">Cerradas (30 días)</label>
                    </div>
                    <label for="filtroBoca" class="form-label mb-0 small fw-bold ms-2">Boca:</label>
                    <select id="filtroBoca" class="form-select form-select-sm" style="max-width: 320px;">
                        <option value="0">Todas las bocas</option>
                    </select>
                    <button type="button" id="btnRecargar" class="btn btn-outline-secondary btn-sm ms-auto" title="Refrescar">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table id="tablaAlertas" class="table table-sm table-striped table-hover align-middle mb-0" style="width:100%">
                            <thead class="table-light">
                                <tr>
                                    <th>Desde</th>
                                    <th>Sucursal</th>
                                    <th>Boca</th>
                                    <th>Código</th>
                                    <th>Producto</th>
                                    <th class="text-end">Stock</th>
                                    <th class="text-end">Mínimo</th>
                                    <th class="text-end">Máximo</th>
                                    <th class="text-end">A reponer</th>
                                    <th class="text-center">Estado</th>
                                    <th>Gestión</th>
                                    <th class="text-center" style="width:100px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    const SAL_EMPRESA_ID = <?= $empresa_id ?>;
</script>
<script src="stock_alertas.js?v=<?= filemtime(__DIR__ . '/stock_alertas.js') ?>"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
