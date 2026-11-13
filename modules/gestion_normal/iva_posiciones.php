<?php
/**
 * Vista: IVA - Posición Mensual — IVA Ventas, IVA Compras, percepciones y retenciones por mes, y liquidación
 * de la posición del período (I_LIQ si da a pagar). Módulo 5: Gestión Normal — Página ID 117.
 * Script: _local/sql/mantenimiento/20261011_iva_posiciones_01.sql
 */
$pageTitle = "IVA - Posición Mensual";
$currentPage = 'iva_posiciones';
$modudo_idx = 5;
$pagina_idx = 617;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <?php ui_encabezado('Posición mensual de IVA','fas fa-percent', 'Débito y crédito fiscal, percepciones y retenciones por mes; liquidación de la posición del período.', ['Tesorería']); ?>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Resumen por mes -->
            <div class="card card-outline card-primary shadow-sm mb-3">
                <div class="card-header py-2 d-flex flex-wrap gap-2 align-items-center">
                    <span class="fw-bold small">Últimos 12 meses hasta</span>
                    <input type="month" id="ivaHasta" class="form-control form-control-sm" style="max-width: 170px;">
                    <button type="button" id="btnIvaRecargar" class="btn btn-outline-secondary btn-sm ms-auto" title="Refrescar"><i class="fas fa-sync-alt"></i></button>
                </div>
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table id="tablaIvaMeses" class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Período</th>
                                    <th class="text-end">Débito fiscal</th>
                                    <th class="text-end">Crédito fiscal</th>
                                    <th class="text-end">Percep. IVA</th>
                                    <th class="text-end">Retenc. IVA</th>
                                    <th class="text-end">Saldo técnico del mes</th>
                                    <th class="text-end" title="Percepciones IIBB / Ganancias sufridas">Otras percep.</th>
                                    <th class="text-end" title="Retenciones IIBB / Ganancias sufridas">Otras retenc.</th>
                                    <th class="text-end" title="Percepciones IIBB practicadas en ventas">IIBB practicadas</th>
                                    <th>Liquidación</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <small class="text-muted">Clic en un mes para ver el detalle. El saldo técnico del mes no incluye el saldo a favor de períodos anteriores.</small>
                </div>
            </div>

            <!-- Detalle del período -->
            <div class="card card-outline card-secondary shadow-sm d-none" id="ivaDetalleCard">
                <div class="card-header py-2 d-flex flex-wrap gap-2 align-items-center">
                    <span class="fw-bold">Período <span id="ivaPeriodoTitulo"></span></span>
                    <div class="ms-auto d-flex gap-2">
                        <div class="btn-group">
                            <button type="button" class="btn btn-outline-primary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><i class="fas fa-file-csv me-1"></i>Libro IVA (CSV)</button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item btn-iva-csv" href="#" data-libro="VENTAS">Libro IVA Ventas</a></li>
                                <li><a class="dropdown-item btn-iva-csv" href="#" data-libro="COMPRAS">Libro IVA Compras</a></li>
                                <li><a class="dropdown-item btn-iva-csv" href="#" data-libro="">Ventas y compras (resumido)</a></li>
                            </ul>
                        </div>
                        <button type="button" id="btnIvaLiquidar" class="btn btn-success btn-sm d-none"><i class="fas fa-file-invoice-dollar me-1"></i>Liquidar período</button>
                        <button type="button" id="btnIvaAnular" class="btn btn-outline-danger btn-sm d-none"><i class="fas fa-ban me-1"></i>Anular liquidación</button>
                    </div>
                </div>
                <div class="card-body p-2">
                    <div id="ivaAvisos"></div>
                    <div class="row g-2 mb-2" id="ivaKpis"></div>
                    <ul class="nav nav-tabs nav-sm" role="tablist">
                        <li class="nav-item"><button class="nav-link active py-1" data-bs-toggle="tab" data-bs-target="#ivaTabPosicion" type="button">Posición</button></li>
                        <li class="nav-item"><button class="nav-link py-1" data-bs-toggle="tab" data-bs-target="#ivaTabVentas" type="button">IVA Ventas</button></li>
                        <li class="nav-item"><button class="nav-link py-1" data-bs-toggle="tab" data-bs-target="#ivaTabCompras" type="button">IVA Compras</button></li>
                        <li class="nav-item"><button class="nav-link py-1" data-bs-toggle="tab" data-bs-target="#ivaTabPercRet" type="button">Percepciones y retenciones</button></li>
                        <li class="nav-item"><button class="nav-link py-1" data-bs-toggle="tab" data-bs-target="#ivaTabLibroVentas" type="button">Libro IVA Ventas</button></li>
                        <li class="nav-item"><button class="nav-link py-1" data-bs-toggle="tab" data-bs-target="#ivaTabLibroCompras" type="button">Libro IVA Compras</button></li>
                    </ul>
                    <div class="tab-content border border-top-0 p-2">
                        <div class="tab-pane fade show active" id="ivaTabPosicion"></div>
                        <div class="tab-pane fade" id="ivaTabVentas"></div>
                        <div class="tab-pane fade" id="ivaTabCompras"></div>
                        <div class="tab-pane fade" id="ivaTabPercRet"></div>
                        <div class="tab-pane fade" id="ivaTabLibroVentas" data-libro="VENTAS"></div>
                        <div class="tab-pane fade" id="ivaTabLibroCompras" data-libro="COMPRAS"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal liquidación -->
    <div class="modal fade" id="modalIvaLiquidar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" id="formIvaLiquidar">
                <div class="modal-header py-2">
                    <h5 class="modal-title"><i class="fas fa-file-invoice-dollar me-2"></i>Liquidar posición de IVA</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="small mb-2" id="ivaLiqTexto"></div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold mb-0" for="ivaLiqSucursal">Sucursal *</label>
                        <select class="form-select form-select-sm" id="ivaLiqSucursal" required></select>
                    </div>
                    <div id="ivaLiqPagar">
                        <div class="mb-2">
                            <label class="form-label small fw-bold mb-0" for="ivaLiqEntidad">Organismo recaudador * <?= ui_ayuda('Entidad proveedora a la que se le debe (ARCA).') ?></label>
                            <select class="form-select form-select-sm" id="ivaLiqEntidad"></select>
                        </div>
                        <div class="row g-2">
                            <div class="col-6">
                                <label class="form-label small fw-bold mb-0" for="ivaLiqVto">Vencimiento</label>
                                <input type="date" class="form-control form-control-sm" id="ivaLiqVto">
                            </div>
                            <div class="col-6">
                                <label class="form-label small fw-bold mb-0" for="ivaLiqDdjj">N° DDJJ / VEP</label>
                                <input type="text" class="form-control form-control-sm" id="ivaLiqDdjj" maxlength="50">
                            </div>
                        </div>
                    </div>
                    <div class="mt-2">
                        <label class="form-label small fw-bold mb-0" for="ivaLiqObs">Observaciones</label>
                        <input type="text" class="form-control form-control-sm" id="ivaLiqObs" maxlength="255">
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check me-1"></i>Liquidar</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="iva_posiciones.js?v=<?= filemtime(__DIR__ . '/iva_posiciones.js') ?>"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
