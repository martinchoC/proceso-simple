<?php
/**
 * comprobantes_consulta.php
 *
 * Consulta universal de comprobantes (gestion__comprobantes) con filtros. Solo lectura: los
 * comprobantes se crean, editan y cambian de estado en su pantalla de origen (el detalle
 * tiene el link). Página 102 (_local/sql/mantenimiento/20261007_comprobantes_ctacte_01_paginas.sql).
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Consulta de Comprobantes";
$currentPage = 'comprobantes_consulta';
$modudo_idx = 2; // Typo intencional y consistente con todo el ERP (ver CLAUDE.md)
$pagina_idx = 102;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <?php ui_encabezado('Comprobantes','fas fa-folder-open', 'Todos los comprobantes de la empresa: ventas, compras, stock y tesorería', ['Tesorería']); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card card-outline card-primary shadow-sm mb-4">
                <div class="card-header bg-light py-2">
                    <form id="formFiltros" class="row g-2 align-items-end" onsubmit="return false;">
                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-bold mb-1">Emisión desde</label>
                            <input type="date" class="form-control form-control-sm" id="fDesde">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-bold mb-1">Emisión hasta</label>
                            <input type="date" class="form-control form-control-sm" id="fHasta">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-bold mb-1">Grupo</label>
                            <select class="form-select form-select-sm" id="fGrupo"><option value="">Todos</option></select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-bold mb-1">Tipo</label>
                            <select class="form-select form-select-sm" id="fTipo"><option value="">Todos</option></select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-bold mb-1">Estado</label>
                            <select class="form-select form-select-sm" id="fEstado"><option value="">Todos</option></select>
                        </div>
                        <div class="col-6 col-md-3">
                            <label class="form-label small fw-bold mb-1">Entidad</label>
                            <input type="text" class="form-control form-control-sm" id="fEntidadTexto" list="fEntidadLista" placeholder="Nombre o CUIT..." autocomplete="off">
                            <datalist id="fEntidadLista"></datalist>
                            <input type="hidden" id="fEntidad">
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-bold mb-1">Sucursal</label>
                            <select class="form-select form-select-sm" id="fSucursal"><option value="">Todas</option></select>
                        </div>
                        <div class="col-3 col-md-1">
                            <label class="form-label small fw-bold mb-1">PV</label>
                            <input type="number" min="0" class="form-control form-control-sm" id="fPv">
                        </div>
                        <div class="col-3 col-md-1">
                            <label class="form-label small fw-bold mb-1">Número</label>
                            <input type="number" min="0" class="form-control form-control-sm" id="fNumero">
                        </div>
                        <div class="col-6 col-md-1">
                            <label class="form-label small fw-bold mb-1">Moneda</label>
                            <select class="form-select form-select-sm" id="fMoneda"><option value="">Todas</option></select>
                        </div>
                        <div class="col-6 col-md-2">
                            <label class="form-label small fw-bold mb-1">Texto libre</label>
                            <input type="text" class="form-control form-control-sm" id="fTexto" placeholder="Código, observaciones...">
                        </div>
                        <div class="col-auto">
                            <div class="form-check mb-1">
                                <input class="form-check-input" type="checkbox" id="fSoloSaldo">
                                <label class="form-check-label small" for="fSoloSaldo">Solo con saldo</label>
                            </div>
                        </div>
                        <div class="col-auto ms-auto">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimpiar"><i class="fas fa-eraser me-1"></i>Limpiar</button>
                            <button type="button" class="btn btn-sm btn-primary" id="btnBuscar"><i class="fas fa-search me-1"></i>Buscar</button>
                        </div>
                    </form>
                </div>
                <div class="card-body p-2">
                    <div id="avisoListado" class="alert alert-warning small py-2 d-none"></div>
                    <table id="tablaComprobantes" class="table table-sm table-striped table-bordered align-middle w-100 small">
                        <thead class="table-dark">
                            <tr>
                                <th>Emisión</th>
                                <th>Vto.</th>
                                <th>Comprobante</th>
                                <th>Grupo</th>
                                <th>Entidad</th>
                                <th>Sucursal</th>
                                <th class="text-end">Total</th>
                                <th class="text-end">Saldo</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center no-export">Ver</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                        <tfoot>
                            <tr class="fw-bold">
                                <td colspan="6" class="text-end">Totales (filas filtradas, moneda local)</td>
                                <td class="text-end" id="totTotal"></td>
                                <td class="text-end" id="totSaldo"></td>
                                <td colspan="2"></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</main>

<script>
    const EMPRESA_ID = <?= intval($current_empresa_id) ?>;
</script>
<script src="comprobantes_ui.js?v=<?= filemtime(__DIR__ . '/comprobantes_ui.js') ?>"></script>
<script src="comprobantes_consulta.js?v=<?= filemtime(__DIR__ . '/comprobantes_consulta.js') ?>"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
