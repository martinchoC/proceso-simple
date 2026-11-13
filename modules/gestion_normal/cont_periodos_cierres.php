<?php
/**
 * Vista: Cierre de Períodos Contables — meses cerrados de la contabilidad. Con el mes cerrado no se crea,
 * modifica, confirma, anula ni elimina ningún asiento con fecha en ese mes (manual o automático).
 * Módulo 5: Gestión Normal — Página ID 634. Script: _local/sql/mantenimiento/20261028_cont_periodos_cierres_01_estructura.sql
 */
$pageTitle = "Cierre de Períodos Contables";
$currentPage = 'cont_periodos_cierres';
$modudo_idx = 5;
$pagina_idx = 634;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

$acciones = '<div class="d-flex gap-2 align-items-center">'
          . '<label for="cpcEjercicio" class="small fw-semibold mb-0">Ejercicio</label>'
          . '<select id="cpcEjercicio" class="form-select form-select-sm" style="max-width: 280px;"></select>'
          . '<div id="contenedor-boton-agregar" class="d-inline"></div>'
          . '</div>';
?>

<main class="app-main">
    <?php ui_encabezado('Cierre de períodos contables', 'fas fa-lock',
        'Los meses se agrupan por ejercicio contable, que sale del mes de cierre de Parámetros de Empresa. Con un mes cerrado no se cargan, modifican, confirman, anulan ni eliminan asientos con fecha en ese mes: ni los manuales ni los que generan ventas, compras, cobranzas, pagos, tesorería y liquidaciones. Se reabre con un motivo y queda el historial.',
        ['Contabilidad'], $acciones); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white py-2 d-flex align-items-center gap-2 flex-wrap">
                    <span class="fw-semibold" id="cpcTituloEjercicio">Meses del ejercicio</span>
                    <span class="ui-sub" id="cpcInfoEjercicio"></span>
                    <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="btnCpcRecargar" title="Recargar">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <div class="card-body p-3">
                    <div class="row g-2" id="cpcMeses"></div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-2 d-flex align-items-center ui-barra">
                    <span class="fw-semibold">Historial de cierres</span>
                    <div class="form-check form-switch mb-0 ms-3">
                        <input class="form-check-input" type="checkbox" id="cpcReabiertos">
                        <label class="form-check-label small" for="cpcReabiertos">Incluir reabiertos</label>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="tablaCierres" class="table table-sm table-hover align-middle w-100">
                            <thead class="table-light text-nowrap">
                                <tr>
                                    <th>Período</th>
                                    <th>Estado</th>
                                    <th>Cerrado por</th>
                                    <th>Fecha de cierre</th>
                                    <th class="ui-detalle">Observaciones</th>
                                    <th class="ui-detalle">Reapertura</th>
                                    <th class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Modal Cerrar -->
            <div class="modal fade" id="modalCerrar" tabindex="-1" aria-labelledby="modalCerrarLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-light">
                            <h5 class="modal-title fw-bold" id="modalCerrarLabel"><i class="fas fa-lock me-2 text-primary"></i>Cerrar período</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <form id="formCerrar" novalidate>
                            <div class="modal-body p-4">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label for="cerrarEjercicio" class="form-label fw-semibold ui-requerido">Ejercicio</label>
                                        <select class="form-select" id="cerrarEjercicio" name="ejercicio" required></select>
                                    </div>
                                    <div class="col-12">
                                        <label for="cerrarMes" class="form-label fw-semibold ui-requerido">Mes
                                            <?= ui_ayuda('"Todo el ejercicio" cierra los meses del ejercicio que todavía están abiertos, salvo los futuros.') ?></label>
                                        <select class="form-select" id="cerrarMes" name="mes" required></select>
                                    </div>
                                    <div class="col-12">
                                        <label for="cerrarObs" class="form-label fw-semibold">Observaciones</label>
                                        <input type="text" class="form-control" id="cerrarObs" name="observaciones" maxlength="255">
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer bg-light py-2">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary" id="btnConfirmarCierre"><i class="fas fa-lock me-1"></i>Cerrar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
    <script>
        window.PAGINA_ID = <?= (int)$pagina_idx ?>;
    </script>
    <script src="cont_periodos_cierres.js?v=<?= filemtime(__DIR__ . '/cont_periodos_cierres.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
