<?php
/**
 * Vista: Control Fiscal — registro de los cuatro circuitos de retenciones y percepciones, períodos fiscales cerrados
 * (bloquean confirmar y anular los comprobantes que los afectan), cuadre contable e inscripciones IIBB de la empresa.
 * Módulo 5: Gestión Normal — Página ID 625. Scripts: _local/sql/mantenimiento/20261016_cierres_fiscales_01_estructura.sql y _02
 */
$pageTitle = "Control Fiscal";
$currentPage = 'cierres_fiscales';
$modudo_idx = 5;
$pagina_idx = 625;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <?php ui_encabezado('Control fiscal','fas fa-landmark', 'Retenciones y percepciones practicadas y sufridas, liquidación mensual de Ingresos Brutos, períodos declarados, cuadre contable e inscripciones IIBB.', ['Tesorería']); ?>

    <div class="app-content">
        <div class="container-fluid">
            <ul class="nav nav-tabs" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#cfTabRegistro" type="button"><i class="fas fa-list me-1"></i>Retenciones y percepciones</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cfTabLiquidacion" type="button"><i class="fas fa-file-invoice-dollar me-1"></i>Liquidaciones</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cfTabIibb" type="button"><i class="fas fa-percent me-1"></i>Ingresos Brutos</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cfTabCierres" type="button"><i class="fas fa-lock me-1"></i>Períodos cerrados</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cfTabCuadre" type="button" id="cfTabCuadreBtn"><i class="fas fa-balance-scale me-1"></i>Cuadre contable</button></li>
                <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#cfTabInscripciones" type="button"><i class="fas fa-map-marked-alt me-1"></i>Inscripciones IIBB</button></li>
            </ul>
            <div class="tab-content">
                <!-- Registro de los cuatro circuitos -->
                <div class="tab-pane fade show active" id="cfTabRegistro">
                    <div class="card card-outline card-primary shadow-sm border-top-0 rounded-top-0">
                        <div class="card-header py-2 d-flex flex-wrap gap-2 align-items-center">
                            <span class="small fw-bold">Desde</span>
                            <input type="month" id="cfRegDesde" class="form-control form-control-sm" style="max-width: 160px;">
                            <span class="small fw-bold">Hasta</span>
                            <input type="month" id="cfRegHasta" class="form-control form-control-sm" style="max-width: 160px;">
                            <select id="cfRegCircuito" class="form-select form-select-sm" style="max-width: 250px;"><option value="">Los cuatro circuitos</option></select>
                            <select id="cfRegImpuesto" class="form-select form-select-sm" style="max-width: 230px;"><option value="">Todos los impuestos</option></select>
                            <select id="cfRegJurisdiccion" class="form-select form-select-sm" style="max-width: 200px;"><option value="">Todas las jurisdicciones</option></select>
                            <input type="text" id="cfRegTercero" class="form-control form-control-sm" style="max-width: 200px;" placeholder="Tercero o CUIT">
                            <select id="cfRegEstadoCert" class="form-select form-select-sm" style="max-width: 210px;" title="Solo retenciones sufridas">
                                <option value="">Certificados: todos</option>
                            </select>
                            <button type="button" id="btnCfRegistro" class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i>Consultar</button>
                            <button type="button" id="btnCfRegExportar" class="btn btn-outline-primary btn-sm ms-auto d-none"><i class="fas fa-file-csv me-1"></i>CSV</button>
                        </div>
                        <div class="card-body p-2">
                            <div id="cfRegResumen"></div>
                            <div class="table-responsive">
                                <table id="tablaRegistro" class="table table-sm table-hover align-middle mb-0 small" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Circuito</th><th>Fecha</th><th>Comprobante</th><th>Tercero</th><th>Impuesto</th><th>Jurisdicción</th>
                                            <th class="text-end">Base</th><th class="text-end">Alíc.</th><th class="text-end">Importe</th><th>Certificado</th><th>Declarada</th><th></th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <small class="text-muted">Importes firmados: las notas de crédito restan. "Declarada": percepción practicada incluida en una liquidación de Percepciones IIBB, o retención practicada liquidada.
                                Las retenciones sufridas van por su período de cómputo; su certificado (número, fecha, régimen, estado, período y archivo) se completa con el lápiz.</small>
                        </div>
                    </div>
                </div>

                <!-- Períodos cerrados -->
                <!-- Liquidación de retenciones practicadas -->
                <div class="tab-pane fade" id="cfTabLiquidacion">
                    <div class="card card-outline card-primary shadow-sm border-top-0 rounded-top-0">
                        <div class="card-header py-2 d-flex flex-wrap gap-2 align-items-center">
                            <span class="small fw-bold">Período</span>
                            <input type="month" id="cfLiqPeriodo" class="form-control form-control-sm" style="max-width: 170px;">
                            <button type="button" id="btnCfLiqRecargar" class="btn btn-outline-secondary btn-sm" title="Refrescar"><i class="fas fa-sync-alt"></i></button>
                        </div>
                        <div class="card-body p-2">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0" id="tablaLiquidacion">
                                    <thead class="table-light">
                                        <tr><th>Retención / percepción</th><th>Jurisdicción</th><th class="text-end">Pendientes</th><th class="text-end">Importe pendiente</th>
                                            <th>Liquidaciones</th><th class="text-end">Acciones</th></tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <small class="text-muted d-block mt-2">Retenciones practicadas en órdenes de pago confirmadas (por fecha de la OP) y percepción de IVA practicada en ventas (por fecha de la factura; las notas de crédito restan). Liquidar genera la I_LIQ
                                (se paga desde Órdenes de Pago) y marca las retenciones: esas OP ya no se pueden anular. Se anula mientras no tenga pagos.
                                La presentación ante el organismo se hace con el registro (solapa Retenciones y percepciones); el archivo de exportación queda para más adelante.</small>
                        </div>
                    </div>
                </div>

                <!-- Liquidación mensual de Ingresos Brutos (impuesto propio) -->
                <div class="tab-pane fade" id="cfTabIibb">
                    <div class="card card-outline card-primary shadow-sm border-top-0 rounded-top-0">
                        <div class="card-header py-2 d-flex flex-wrap gap-2 align-items-center">
                            <span class="small fw-bold">Período</span>
                            <input type="month" id="cfIibbPeriodo" class="form-control form-control-sm" style="max-width: 170px;">
                            <button type="button" id="btnCfIibbRecargar" class="btn btn-outline-secondary btn-sm" title="Refrescar"><i class="fas fa-sync-alt"></i></button>
                            <span class="small text-muted ms-auto" id="cfIibbBaseTotal"></span>
                            <?= ui_ayuda('Una liquidación por jurisdicción inscripta. Base: ventas fiscales del mes sin IVA (en Convenio Multilateral, por el coeficiente de la inscripción), editable por actividad. Impuesto: base por alícuota de cada actividad, o su mínimo mensual si es mayor. Se restan las retenciones y percepciones de IIBB sufridas en la jurisdicción y el saldo a favor anterior. Si da a pagar genera la I_LIQ, que se paga desde Órdenes de Pago. Liquidar cierra el período de IIBB de la jurisdicción; se reabre anulando la liquidación.') ?>
                        </div>
                        <div class="card-body p-2">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0" id="tablaIibb">
                                    <thead class="table-light">
                                        <tr><th>Jurisdicción</th><th class="text-end">Base imponible</th><th class="text-end">Impuesto</th>
                                            <th class="text-end">Retenciones</th><th class="text-end">Percepciones</th><th class="text-end">Saldo a favor ant.</th>
                                            <th class="text-end">Resultado</th><th>Liquidación</th><th class="text-end">Acciones</th></tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="cfTabCierres">
                    <div class="card card-outline card-primary shadow-sm border-top-0 rounded-top-0">
                        <div class="card-header py-2 d-flex flex-wrap gap-2 align-items-center">
                            <select id="cfFiltroImpuesto" class="form-select form-select-sm" style="max-width: 260px;"><option value="">Todas las obligaciones</option></select>
                            <input type="number" id="cfFiltroAnio" class="form-control form-control-sm" style="max-width: 110px;" min="2000" max="2100" placeholder="Año">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="cfFiltroReabiertos">
                                <label class="form-check-label small" for="cfFiltroReabiertos">Incluir reabiertos</label>
                            </div>
                            <div class="ms-auto d-flex gap-2">
                                <button type="button" id="btnCfRecargar" class="btn btn-outline-secondary btn-sm" title="Refrescar"><i class="fas fa-sync-alt"></i></button>
                                <button type="button" id="btnCfCerrar" class="btn btn-primary btn-sm d-none"><i class="fas fa-lock me-1"></i>Cerrar período</button>
                            </div>
                        </div>
                        <div class="card-body p-2">
                            <div class="table-responsive">
                                <table id="tablaCierres" class="table table-sm table-hover align-middle mb-0" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Período</th><th>Obligación</th><th>Jurisdicción</th><th>Origen</th><th>Cerrado por</th>
                                            <th>Estado</th><th>Observaciones</th><th class="text-end">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <small class="text-muted d-block mt-2">
                                Con el período cerrado no se confirman ni se anulan los comprobantes que lo afectan: ventas y compras fiscales (IVA; las ventas también IIBB),
                                percepciones de la jurisdicción, cobranzas con retenciones sufridas y órdenes de pago con retenciones practicadas.
                                Las liquidaciones de IVA y de Ingresos Brutos cierran su período solas y se reabren anulándolas; el resto (percepciones y retenciones practicadas, Ganancias) se cierra acá al presentar la DDJJ.
                            </small>
                        </div>
                    </div>
                </div>

                <!-- Cuadre -->
                <div class="tab-pane fade" id="cfTabCuadre">
                    <div class="card card-outline card-primary shadow-sm border-top-0 rounded-top-0">
                        <div class="card-header py-2 d-flex flex-wrap gap-2 align-items-center">
                            <span class="small fw-bold">Desde</span>
                            <input type="month" id="cfCuadreDesde" class="form-control form-control-sm" style="max-width: 170px;">
                            <span class="small fw-bold">Hasta</span>
                            <input type="month" id="cfCuadreHasta" class="form-control form-control-sm" style="max-width: 170px;">
                            <button type="button" id="btnCfCuadre" class="btn btn-primary btn-sm"><i class="fas fa-balance-scale me-1"></i>Calcular</button>
                            <button type="button" id="btnCfExportar" class="btn btn-outline-primary btn-sm ms-auto d-none"><i class="fas fa-file-csv me-1"></i>Diferencias (CSV)</button>
                        </div>
                        <div class="card-body p-2" id="cfCuadreCuerpo">
                            <div class="text-muted small p-2">Elija el rango de períodos y presione Calcular.</div>
                        </div>
                    </div>
                </div>

                <!-- Inscripciones IIBB de la empresa -->
                <div class="tab-pane fade" id="cfTabInscripciones">
                    <div class="card card-outline card-primary shadow-sm border-top-0 rounded-top-0">
                        <div class="card-header py-2 d-flex flex-wrap gap-2 align-items-center">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" id="cfInsInactivas">
                                <label class="form-check-label small" for="cfInsInactivas">Incluir inhabilitadas</label>
                            </div>
                            <button type="button" id="btnCfInscribir" class="btn btn-primary btn-sm ms-auto d-none"><i class="fas fa-plus me-1"></i>Agregar inscripción</button>
                        </div>
                        <div class="card-body p-2">
                            <div class="table-responsive">
                                <table class="table table-sm table-hover align-middle mb-0" id="tablaInscripciones">
                                    <thead class="table-light">
                                        <tr><th>Jurisdicción</th><th>Régimen</th><th>N° inscripción</th><th>Sede</th><th class="text-end">Coef. CM</th>
                                            <th>Vigencia</th><th>Actividades</th><th>Observaciones</th><th>Estado</th><th class="text-end">Acciones</th></tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                            <small class="text-muted d-block mt-2">Jurisdicciones en las que la empresa está inscripta en Ingresos Brutos. Con inscripciones cargadas,
                                el cierre de Ingresos Brutos solo se permite en jurisdicciones inscriptas en el período.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal cerrar período -->
    <div class="modal fade" id="modalCfCerrar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" id="formCfCerrar">
                <div class="modal-header py-2">
                    <h5 class="modal-title"><i class="fas fa-lock me-2"></i>Cerrar período fiscal</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small fw-bold mb-0" for="cfImpuesto">Obligación *</label>
                        <select class="form-select form-select-sm" id="cfImpuesto" required></select>
                    </div>
                    <div class="mb-2 d-none" id="cfJurisdiccionGrupo">
                        <label class="form-label small fw-bold mb-0" for="cfJurisdiccion">Jurisdicción *</label>
                        <select class="form-select form-select-sm" id="cfJurisdiccion"></select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold mb-0" for="cfPeriodo">Período *</label>
                        <input type="month" class="form-control form-control-sm" id="cfPeriodo" required>
                    </div>
                    <div>
                        <label class="form-label small fw-bold mb-0" for="cfObs">Observaciones</label>
                        <input type="text" class="form-control form-control-sm" id="cfObs" maxlength="255" placeholder="N° de DDJJ, presentación...">
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-lock me-1"></i>Cerrar período</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal certificado de retención sufrida -->
    <div class="modal fade" id="modalCfCertificado" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" id="formCfCertificado" enctype="multipart/form-data">
                <div class="modal-header py-2">
                    <h5 class="modal-title"><i class="fas fa-file-signature me-2"></i>Certificado de retención</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="small mb-2" id="cfCertTexto"></div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-0" for="cfCertEstado">Estado *</label>
                            <select class="form-select form-select-sm" id="cfCertEstado" required></select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-0" for="cfCertPeriodo">Período de cómputo *</label>
                            <input type="month" class="form-control form-control-sm" id="cfCertPeriodo" required>
                        </div>
                        <div class="col-7">
                            <label class="form-label small fw-bold mb-0" for="cfCertNro">N° de certificado</label>
                            <input type="text" class="form-control form-control-sm" id="cfCertNro" maxlength="50">
                        </div>
                        <div class="col-5">
                            <label class="form-label small fw-bold mb-0" for="cfCertFecha">Fecha</label>
                            <input type="date" class="form-control form-control-sm" id="cfCertFecha">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold mb-0" for="cfCertRegimen">Régimen / concepto</label>
                            <input type="text" class="form-control form-control-sm" id="cfCertRegimen" maxlength="100" placeholder="Como figura en el certificado">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold mb-0" for="cfCertArchivo">Archivo (PDF, JPG o PNG, hasta 5 MB)</label>
                            <input type="file" class="form-control form-control-sm" id="cfCertArchivo" accept=".pdf,.jpg,.jpeg,.png">
                            <div class="form-text" id="cfCertArchivoActual"></div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal liquidar retenciones -->
    <div class="modal fade" id="modalCfLiquidar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" id="formCfLiquidar">
                <div class="modal-header py-2">
                    <h5 class="modal-title"><i class="fas fa-file-invoice-dollar me-2"></i>Liquidar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="small mb-2" id="cfLiqTexto"></div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold mb-0" for="cfLiqEntidad">Organismo recaudador * <?= ui_ayuda('Entidad proveedora a la que se le ingresa (ARCA, ARBA, Rentas de Neuquén...).') ?></label>
                        <select class="form-select form-select-sm" id="cfLiqEntidad" required></select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small fw-bold mb-0" for="cfLiqSucursal">Sucursal *</label>
                        <select class="form-select form-select-sm" id="cfLiqSucursal" required></select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-0" for="cfLiqVto">Vencimiento</label>
                            <input type="date" class="form-control form-control-sm" id="cfLiqVto">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-0" for="cfLiqDdjj">N° DDJJ / VEP</label>
                            <input type="text" class="form-control form-control-sm" id="cfLiqDdjj" maxlength="50">
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check me-1"></i>Liquidar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal liquidar Ingresos Brutos -->
    <div class="modal fade" id="modalCfIibb" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <form class="modal-content" id="formCfIibb">
                <div class="modal-header py-2">
                    <h5 class="modal-title"><i class="fas fa-percent me-2"></i>Liquidar Ingresos Brutos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="small mb-2" id="cfIibbTexto"></div>
                    <div class="ui-seccion">
                        <div class="ui-seccion-titulo">Base imponible por actividad <?= ui_ayuda('Se propone la base calculada en la actividad principal. Corríjala o repártala entre las actividades según lo que declare (0 si una actividad no tuvo ingresos).') ?></div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-1" id="tablaIibbActividades">
                                <thead class="table-light"><tr><th>Actividad</th><th class="text-end" style="width: 170px;">Base imponible</th>
                                    <th class="text-end">Alícuota</th><th class="text-end">Mínimo</th><th class="text-end">Impuesto</th></tr></thead>
                                <tbody></tbody>
                                <tfoot></tfoot>
                            </table>
                        </div>
                        <div class="small" id="cfIibbAvisoBase"></div>
                    </div>
                    <div class="ui-seccion">
                        <div class="ui-seccion-titulo">Resultado</div>
                        <div id="cfIibbResultado" class="small"></div>
                    </div>
                    <div class="ui-seccion">
                        <div class="ui-seccion-titulo">Datos de la liquidación</div>
                        <div class="row g-2">
                            <div class="col-md-6" id="cfIibbEntidadGrupo">
                                <label class="form-label small fw-bold mb-0 ui-requerido" for="cfIibbEntidad">Organismo recaudador <?= ui_ayuda('Entidad proveedora a la que se le paga (ARBA, AGIP, Dirección de Rentas...). Solo si da a pagar.') ?></label>
                                <select class="form-select form-select-sm" id="cfIibbEntidad"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold mb-0 ui-requerido" for="cfIibbSucursal">Sucursal</label>
                                <select class="form-select form-select-sm" id="cfIibbSucursal" required></select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold mb-0" for="cfIibbVto">Vencimiento</label>
                                <input type="date" class="form-control form-control-sm" id="cfIibbVto">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold mb-0" for="cfIibbDdjj">N° DDJJ / VEP</label>
                                <input type="text" class="form-control form-control-sm" id="cfIibbDdjj" maxlength="50">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold mb-0" for="cfIibbObs">Observaciones</label>
                                <input type="text" class="form-control form-control-sm" id="cfIibbObs" maxlength="255">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check me-1"></i>Liquidar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal actividades de una inscripción -->
    <div class="modal fade" id="modalCfActividades" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h5 class="modal-title"><i class="fas fa-briefcase me-2"></i>Actividades <span id="cfActTitulo"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-2" id="tablaActividades">
                            <thead class="table-light"><tr><th>Código</th><th>Actividad</th><th class="text-end">Alícuota</th><th class="text-end">Mínimo mensual</th>
                                <th>Vigencia</th><th></th><th class="text-end">Acciones</th></tr></thead>
                            <tbody></tbody>
                        </table>
                    </div>
                    <form id="formCfActividad" class="ui-seccion d-none">
                        <div class="ui-seccion-titulo">Agregar actividad <?= ui_ayuda('Para cambiar la alícuota de una actividad, inhabilite la anterior y cárguela de nuevo con la fecha desde la que rige. Las liquidaciones ya hechas guardan la alícuota que usaron.') ?></div>
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-0" for="cfActCodigo">Código (NAES)</label>
                                <input type="text" class="form-control form-control-sm" id="cfActCodigo" maxlength="10">
                            </div>
                            <div class="col-md-9">
                                <label class="form-label small fw-bold mb-0 ui-requerido" for="cfActDescripcion">Descripción</label>
                                <input type="text" class="form-control form-control-sm" id="cfActDescripcion" maxlength="150" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-0 ui-requerido" for="cfActAlicuota">Alícuota %</label>
                                <input type="text" class="form-control form-control-sm" id="cfActAlicuota" inputmode="decimal" placeholder="3,5" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-0" for="cfActMinimo">Mínimo mensual <?= ui_ayuda('Impuesto mínimo mensual de la actividad, si la jurisdicción lo fija. Vacío = sin mínimo.') ?></label>
                                <input type="text" class="form-control form-control-sm" id="cfActMinimo" inputmode="decimal">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-0" for="cfActDesde">Desde</label>
                                <input type="date" class="form-control form-control-sm" id="cfActDesde">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold mb-0" for="cfActHasta">Hasta</label>
                                <input type="date" class="form-control form-control-sm" id="cfActHasta">
                            </div>
                            <div class="col-md-9">
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" id="cfActPrincipal">
                                    <label class="form-check-label small" for="cfActPrincipal">Actividad principal (recibe la base propuesta)</label>
                                </div>
                            </div>
                            <div class="col-md-3 text-end">
                                <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-plus me-1"></i>Agregar</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal inscripción IIBB -->
    <div class="modal fade" id="modalCfInscripcion" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <form class="modal-content" id="formCfInscripcion">
                <div class="modal-header py-2">
                    <h5 class="modal-title"><i class="fas fa-map-marked-alt me-2"></i>Inscripción en Ingresos Brutos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-7">
                            <label class="form-label small fw-bold mb-0" for="cfInsJurisdiccion">Provincia *</label>
                            <select class="form-select form-select-sm" id="cfInsJurisdiccion" required></select>
                        </div>
                        <div class="col-5">
                            <label class="form-label small fw-bold mb-0" for="cfInsRegimen">Régimen *</label>
                            <select class="form-select form-select-sm" id="cfInsRegimen" required></select>
                        </div>
                        <div class="col-7">
                            <label class="form-label small fw-bold mb-0" for="cfInsNro">N° de inscripción</label>
                            <input type="text" class="form-control form-control-sm" id="cfInsNro" maxlength="30">
                        </div>
                        <div class="col-5">
                            <label class="form-label small fw-bold mb-0" for="cfInsCoef">Coeficiente CM</label>
                            <input type="text" class="form-control form-control-sm" id="cfInsCoef" placeholder="0,0000" inputmode="decimal">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-0" for="cfInsDesde">Desde *</label>
                            <input type="date" class="form-control form-control-sm" id="cfInsDesde" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold mb-0" for="cfInsHasta">Hasta</label>
                            <input type="date" class="form-control form-control-sm" id="cfInsHasta">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="cfInsSede">
                                <label class="form-check-label small" for="cfInsSede">Jurisdicción sede</label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-bold mb-0" for="cfInsObs">Observaciones</label>
                            <input type="text" class="form-control form-control-sm" id="cfInsObs" maxlength="255">
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-check me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</main>

<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="cierres_fiscales.js?v=<?= filemtime(__DIR__ . '/cierres_fiscales.js') ?>"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
