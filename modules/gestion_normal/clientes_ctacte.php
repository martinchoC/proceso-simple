<?php
/**
 * clientes_ctacte.php
 *
 * Cuenta corriente de clientes (solo lectura): saldos de todos los clientes y, al elegir uno,
 * su deuda, el detalle de la cuenta corriente con saldo acumulado y los comprobantes emitidos.
 * Página 103 (_local/sql/mantenimiento/20261007_comprobantes_ctacte_01_paginas.sql).
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Cuenta Corriente de Clientes";
$currentPage = 'clientes_ctacte';
$modudo_idx = 5; // Typo intencional y consistente con todo el ERP (ver CLAUDE.md)
$pagina_idx = 603;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <?php ui_encabezado('Cuenta corriente de clientes', 'bi bi-journal-text',
        'Saldo de cada cliente. Tocá un cliente para ver su deuda y sus movimientos.', ['Ventas']); ?>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Totales -->
            <div class="row g-3 mb-3" id="totalesSaldos">
                <div class="col-6 col-md-3">
                    <div class="ui-kpi">
                        <div class="ui-kpi-etiqueta">Saldo total</div>
                        <div class="ui-kpi-valor" id="kpiSaldoTotal">-</div>
                        <div class="ui-kpi-sub" id="kpiClientes"></div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="ui-kpi">
                        <div class="ui-kpi-etiqueta">Vencido</div>
                        <div class="ui-kpi-valor text-danger" id="kpiVencidoTotal">-</div>
                    </div>
                </div>
            </div>

            <!-- Saldos por cliente -->
            <div class="card">
                <div class="card-header ui-barra">
                    <form id="formFiltrosSaldos" class="ui-barra w-100" onsubmit="return false;">
                        <input type="search" class="form-control form-control-sm" id="filtroTexto" placeholder="Buscar cliente o CUIT…" style="max-width: 280px;">
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="filtroSoloSaldo" checked>
                            <label class="form-check-label small" for="filtroSoloSaldo">Con saldo</label>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" id="filtroSoloVencidos">
                            <label class="form-check-label small" for="filtroSoloVencidos">Con deuda vencida</label>
                        </div>
                    </form>
                </div>
                <div class="card-body">
                    <div id="avisoSaldos" class="alert alert-warning ui-aviso d-none"></div>
                    <table id="tablaSaldos" class="table table-hover align-middle w-100">
                        <thead>
                            <tr>
                                <th>Cliente</th>
                                <th>CUIT</th>
                                <th class="text-end">Deuda pendiente</th>
                                <th class="text-end">A favor</th>
                                <th class="text-end">Saldo</th>
                                <th class="text-end">Vencido</th>
                                <th>Vence desde</th>
                                <th class="text-end">Atraso</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>

            <!-- Detalle del cliente elegido (aparece al elegir un cliente) -->
            <div class="card d-none" id="cardDetalleCtacte">
                <div class="card-body" id="detalleCtacte"></div>
            </div>
        </div>
    </div>
</main>

<style>
#tablaSaldos tbody tr { cursor: pointer; }
#tablaSaldos tbody tr.table-active td { font-weight: 600; }
</style>

<?php require_once ROOT_PATH . '/core/csrf.php'; ?>
<script>
    const EMPRESA_ID = <?= intval($current_empresa_id) ?>;
    const CTACTE_CSRF = <?= json_encode(csrf_token()) ?>;   // envío por correo (enviar_email)
</script>
<script src="comprobantes_ui.js?v=<?= filemtime(__DIR__ . '/comprobantes_ui.js') ?>"></script>
<script src="ctacte_cliente.js?v=<?= filemtime(__DIR__ . '/ctacte_cliente.js') ?>"></script>
<script src="clientes_ctacte.js?v=<?= filemtime(__DIR__ . '/clientes_ctacte.js') ?>"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
