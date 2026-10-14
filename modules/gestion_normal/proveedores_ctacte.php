<?php
/**
 * proveedores_ctacte.php
 *
 * Cuenta corriente de proveedores (solo lectura): saldos de todos los proveedores y, al elegir uno,
 * su deuda, el detalle de la cuenta corriente con saldo acumulado y los comprobantes recibidos.
 * Página 118 (módulo 5) / 618 (módulo 5).
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Cuenta Corriente de Proveedores";
$currentPage = 'proveedores_ctacte';
$modudo_idx = 5; // Typo intencional y consistente con todo el ERP (ver CLAUDE.md)
$pagina_idx = 618;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0"><i class="fas fa-balance-scale text-primary me-2"></i>Cuenta Corriente de Proveedores</h3>
                    <small class="text-muted">Saldos, deuda vencida y movimientos por proveedor</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Compras</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Cuenta Corriente</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Saldos por proveedor -->
            <div class="card card-outline card-primary shadow-sm mb-3">
                <div class="card-header bg-light py-2">
                    <form id="formFiltrosSaldos" class="row g-2 align-items-center" onsubmit="return false;">
                        <div class="col-md-4">
                            <input type="text" class="form-control form-control-sm" id="filtroTexto" placeholder="Buscar por nombre o CUIT...">
                        </div>
                        <div class="col-auto">
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" id="filtroSoloSaldo" checked>
                                <label class="form-check-label small" for="filtroSoloSaldo">Solo con saldo</label>
                            </div>
                        </div>
                        <div class="col-auto">
                            <div class="form-check mb-0">
                                <input class="form-check-input" type="checkbox" id="filtroSoloVencidos">
                                <label class="form-check-label small" for="filtroSoloVencidos">Solo con deuda vencida</label>
                            </div>
                        </div>
                        <div class="col text-end small" id="totalesSaldos"></div>
                    </form>
                </div>
                <div class="card-body p-2">
                    <div id="avisoSaldos" class="alert alert-warning small py-2 d-none"></div>
                    <table id="tablaSaldos" class="table table-sm table-striped table-bordered table-hover align-middle w-100 small">
                        <thead class="table-dark">
                            <tr>
                                <th>Proveedor</th>
                                <th>CUIT</th>
                                <th class="text-end">Saldo</th>
                                <th class="text-end">Deuda pendiente</th>
                                <th class="text-end">Vencido</th>
                                <th class="text-end">A favor</th>
                                <th>Vence desde</th>
                                <th>Último mov.</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Detalle del proveedor elegido -->
            <div class="card shadow-sm mb-4">
                <div class="card-body" id="detalleCtacte"></div>
            </div>
        </div>
    </div>
</main>

<style>
#tablaSaldos tbody tr { cursor: pointer; }
#tablaSaldos tbody tr.table-active td { font-weight: 600; }
</style>

<script>
    const EMPRESA_ID = <?= intval($current_empresa_id) ?>;
</script>
<script src="comprobantes_ui.js?v=<?= filemtime(__DIR__ . '/comprobantes_ui.js') ?>"></script>
<script src="ctacte_proveedor.js?v=<?= filemtime(__DIR__ . '/ctacte_proveedor.js') ?>"></script>
<script src="proveedores_ctacte.js?v=<?= filemtime(__DIR__ . '/proveedores_ctacte.js') ?>"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
