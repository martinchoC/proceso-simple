<?php
/**
 * Vista de Bandeja de Evidencias (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/evidencias.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Bandeja de Evidencias";
$currentPage = 'evidencias';
$modudo_idx = 3;
$pagina_idx = 352; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-file-contract me-2 text-primary"></i>Bandeja de Evidencias
                    </h3>
                    <small class="text-muted">Custodia de archivos probatorios, hash SHA-256, comprobantes y validez legal</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item">Ejecución y Seguimiento</li>
                        <li class="breadcrumb-item active" aria-current="page">Bandeja de Evidencias</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Barra de Filtros y Acciones -->
            <div class="card card-outline card-primary mb-3">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Entidad Sujeta a Control</label>
                            <select id="filtroEntidad" class="form-select form-select-sm">
                                <option value="">-- Todas las entidades --</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">ID Ciclo</label>
                            <input type="number" id="filtroCiclo" class="form-control form-control-sm" placeholder="Ej: 10">
                        </div>
                        <div class="col-md-2 col-sm-4">
                            <label class="form-label form-label-sm mb-1 text-muted">Comprobante Oficial</label>
                            <select id="filtroComprobante" class="form-select form-select-sm">
                                <option value="">-- Todos --</option>
                                <option value="1">Solo Comprobantes</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-4">
                            <label class="form-label form-label-sm mb-1 text-muted">Estado</label>
                            <select id="filtroEstado" class="form-select form-select-sm">
                                <option value="">-- Todos --</option>
                                <option value="1" selected>Vigentes / Activas</option>
                                <option value="2">Anuladas</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-4 text-end pt-3">
                            <button id="btnSubirEvidencia" class="btn btn-sm btn-primary me-1" title="Subir archivo probatorio">
                                <i class="fas fa-upload me-1"></i>Subir Evidencia
                            </button>
                            <button id="btnRecargar" class="btn btn-sm btn-outline-secondary" title="Recargar lista">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Evidencias -->
            <div class="card card-outline card-secondary">
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table id="tablaEvidencias" class="table table-sm table-striped table-hover align-middle w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">ID</th>
                                    <th>Entidad</th>
                                    <th>Obligación / Ciclo</th>
                                    <th>Tarea Operativa</th>
                                    <th>Título / Hash SHA-256</th>
                                    <th style="width: 110px;">Vence</th>
                                    <th style="width: 110px;">Retención</th>
                                    <th style="width: 90px;" class="text-center">Tipo</th>
                                    <th style="width: 90px;">Estado</th>
                                    <th style="width: 80px;" class="text-center">Acciones</th>
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

<!-- Modal: Subir Evidencia -->
<div class="modal fade" id="modalSubirEvidencia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title"><i class="fas fa-file-upload me-2"></i>Subir Archivo Probatorio / Evidencia</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formSubirEvidencia" enctype="multipart/form-data">
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-12">
                            <label class="form-label form-label-sm">Tarea Operativa Abierta *</label>
                            <select id="eviTareaId" name="ciclo_tarea_id" class="form-select form-select-sm" required>
                                <option value="">-- Seleccionar tarea --</option>
                            </select>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label form-label-sm">Título de la Evidencia *</label>
                            <input type="text" id="eviTitulo" name="titulo" class="form-control form-control-sm" placeholder="Ej: Manifiesto de carga, Certificado de calibración..." required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label form-label-sm">Archivo Digital *</label>
                            <input type="file" id="eviArchivo" name="archivo" class="form-control form-control-sm" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label form-label-sm">Descripción / Observaciones</label>
                            <textarea id="eviDescripcion" name="descripcion" class="form-control form-control-sm" rows="2" placeholder="Detalles probatorios..."></textarea>
                        </div>
                    </div>

                    <div class="p-2 border rounded bg-light mb-2">
                        <h6 class="small fw-bold text-muted mb-2"><i class="fas fa-shield-alt me-1"></i>Vigencia y Metadatos Legales</h6>
                        <div class="row g-2">
                            <div class="col-md-4">
                                <label class="form-label form-label-sm">Validez Temporal (Caso C16)</label>
                                <input type="date" id="eviValidaHasta" name="valida_hasta" class="form-control form-control-sm">
                                <small class="text-muted" style="font-size: 0.75rem;">Dejar vacío si es permanente.</small>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label form-label-sm">Retener Legalmente Hasta</label>
                                <input type="date" id="eviRetenerHasta" name="retener_hasta" class="form-control form-control-sm">
                                <small class="text-muted" style="font-size: 0.75rem;">Guarda documental obligatoria.</small>
                            </div>
                            <div class="col-md-4 pt-3">
                                <div class="form-check form-switch mb-1">
                                    <input class="form-check-input" type="checkbox" id="eviEsComprobante" name="es_comprobante" value="1">
                                    <label class="form-check-label small fw-semibold" for="eviEsComprobante">Comprobante Oficial</label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="eviConfidencial" name="confidencial" value="1">
                                    <label class="form-check-label small fw-semibold text-danger" for="eviConfidencial">Confidencial</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-1">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnGuardarEvi"><i class="fas fa-save me-1"></i>Subir y Firmar SHA-256</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Detalle de Evidencia -->
<div class="modal fade" id="modalDetalleEvidencia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white py-2">
                <h5 class="modal-title"><i class="fas fa-info-circle me-2"></i>Ficha de Evidencia Probatoria</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="p-2 border rounded bg-light mb-3">
                    <strong>Título: </strong><span id="detEviTitulo" class="fw-bold"></span><br>
                    <strong>Entidad / Ciclo: </strong><span id="detEviEntidadCiclo"></span><br>
                    <strong>Tarea: </strong><span id="detEviTarea"></span><br>
                    <strong>Obligación: </strong><span id="detEviObligacion"></span>
                </div>
                <div class="mb-3">
                    <label class="form-label form-label-sm fw-bold">Integridad Digital (Firma Criptográfica SHA-256)</label>
                    <div class="p-2 bg-dark text-success font-monospace small rounded text-break" id="detEviHash">--</div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-md-4">
                        <small class="text-muted d-block">Vigente Hasta</small>
                        <span id="detEviValida" class="fw-semibold">--</span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Retención Legal</small>
                        <span id="detEviRetiene" class="fw-semibold">--</span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block">Cargado Por</small>
                        <span id="detEviUsuario" class="fw-semibold">--</span>
                    </div>
                </div>
                <div class="mb-2">
                    <small class="text-muted d-block">Descripción / Notas</small>
                    <div id="detEviDesc" class="p-2 border rounded bg-white small"></div>
                </div>
            </div>
            <div class="modal-footer py-1 justify-content-between">
                <a id="btnDescargaArchivo" href="#" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-download me-1"></i>Descargar Archivo</a>
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Anular Evidencia -->
<div class="modal fade" id="modalAnularEvidencia" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white py-2">
                <h5 class="modal-title"><i class="fas fa-trash-alt me-2"></i>Anular Evidencia</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formAnularEvidencia">
                <input type="hidden" id="anularEviId" name="evidencia_id">
                <div class="modal-body">
                    <div class="alert alert-warning py-2 small">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        La evidencia pasará a estado <strong>Anulada</strong> y quedará inhabilitada para sustentar la tarea operativa.
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm">Motivo de Anulación *</label>
                        <textarea id="anularMotivo" name="motivo" class="form-control form-control-sm" rows="3" placeholder="Detalle la justificación técnica..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer py-1">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-danger">Confirmar Anulación</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
<script src="modules/ml/evidencias.js"></script>
</body>
</html>
