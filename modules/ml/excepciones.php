<?php
/**
 * Vista de Excepciones de Cumplimiento (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/excepciones.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Excepciones de Cumplimiento";
$currentPage = 'excepciones';
$modudo_idx = 3;
$pagina_idx = 333; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-shield-alt me-2 text-warning"></i>Excepciones de Cumplimiento
                    </h3>
                    <small class="text-muted">Gestión temporal de exenciones normativas, dictamen legal y control de vigencia</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item"><a href="obligaciones_asignadas.php">Motor y Asignaciones</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Excepciones</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Barra de Filtros -->
            <div class="card card-outline card-warning mb-3">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-5 col-sm-12">
                            <label class="form-label form-label-sm mb-1 text-muted">Obligación / Entidad</label>
                            <select id="filtroAsignada" class="form-select form-select-sm">
                                <option value="">-- Todas las asignaciones --</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Estado de la Solicitud</label>
                            <select id="filtroEstado" class="form-select form-select-sm">
                                <option value="">-- Todos los estados --</option>
                                <option value="2087">Solicitada</option>
                                <option value="2088">En aprobación legal</option>
                                <option value="2089" selected>Aprobada (Vigente)</option>
                                <option value="2090">Rechazada</option>
                                <option value="2091">Vencida / Revocada</option>
                            </select>
                        </div>
                        <div class="col-md-4 col-sm-6 text-md-end mt-3 mt-md-auto">
                            <div id="btn-container-excepcion" class="d-inline-block me-1"></div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Refrescar">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Excepciones -->
            <div class="card card-outline card-secondary shadow-sm">
                <div class="card-header border-0 pb-0">
                    <h5 class="card-title text-secondary">
                        <i class="fas fa-tasks me-2"></i>Historial y Trámite de Excepciones
                    </h5>
                </div>
                <div class="card-body pt-2">
                    <div class="table-responsive">
                        <table id="tablaExcepciones" class="table table-sm table-striped table-hover align-middle w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;" class="text-center"># ID</th>
                                    <th>Entidad / Exigencia</th>
                                    <th>Motivo de Excepción</th>
                                    <th>Fundamento Jurídico</th>
                                    <th class="text-center">Vigencia Temporal</th>
                                    <th class="text-center">Estado</th>
                                    <th>Aprobación / Dictamen</th>
                                    <th class="text-center" style="width: 120px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Cargado vía AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal: Solicitar Excepción (Caso C09) -->
<div class="modal fade" id="modalExcepcion" tabindex="-1" aria-labelledby="modalExcepcionLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-warning text-dark py-2">
                <h5 class="modal-title" id="modalExcepcionLabel">
                    <i class="fas fa-file-signature me-2"></i>Solicitud de Excepción de Cumplimiento
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formExcepcion">
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label form-label-sm">Obligación Asignada a Eximir <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" name="obligacion_asignada_id" id="exc_asignada_id" required>
                            <option value="">-- Seleccionar obligación asignada --</option>
                        </select>
                        <div id="alertaNormaLegal" class="form-text text-danger d-none mt-1">
                            <i class="fas fa-gavel me-1"></i><strong>Atención:</strong> Esta exigencia deriva de una norma de carácter <u>legal</u>. El Caso C09 exige detallar el fundamento jurídico y dictamen del área legal.
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Vigente Desde <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" name="vigente_desde" id="exc_vigente_desde" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Vigente Hasta (Vencimiento inexorable) <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" name="vigente_hasta" id="exc_vigente_hasta" required>
                            <div class="form-text text-muted" style="font-size: 0.75rem;">Al vencer la fecha, la obligación vuelve a regir automáticamente.</div>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label form-label-sm">Motivo Operativo / Fáctico de la Excepción <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" name="motivo" id="exc_motivo" rows="2" placeholder="Explique las causas por las cuales se solicita no aplicar esta exigencia..." required></textarea>
                    </div>

                    <div class="mb-2">
                        <label class="form-label form-label-sm" id="lblFundamento">Fundamento Jurídico / Resolución de Autoridad</label>
                        <textarea class="form-control form-control-sm" name="fundamento" id="exc_fundamento" rows="2" placeholder="Citar resolución de la autoridad competente o causal de inaplicabilidad probada..."></textarea>
                    </div>

                    <div class="mb-2">
                        <label class="form-label form-label-sm">Referencia Documental / Nro. Expediente</label>
                        <input type="text" class="form-control form-control-sm" name="fundamento_ref" id="exc_fundamento_ref" placeholder="Ej: Expte. N° 2026-4412-SE / Res. Min. 88/26">
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-warning" id="btnGuardarExcepcion">
                        <i class="fas fa-paper-plane me-1"></i>Presentar Solicitud
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const PAGINA_ID = <?php echo $pagina_idx; ?>;
    const MODULO_ID = <?php echo $modudo_idx; ?>;
</script>
<script src="excepciones.js?v=<?php echo time(); ?>"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
