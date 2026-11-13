<?php
/**
 * Vista de Procedimientos y Soporte (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/procedimientos.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Procedimientos y Soporte";
$currentPage = 'procedimientos';
$modudo_idx = 3;
$pagina_idx = 315; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-file-alt me-2 text-primary"></i>Procedimientos y Soporte Operativo
                    </h3>
                    <small class="text-muted">Manuales, directivas técnicas e instrucciones paso a paso que guían la ejecución de obligaciones</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Procedimientos</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Barra de Filtros -->
            <div class="card card-outline card-primary mb-3">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Tipo de Documento</label>
                            <select id="filtroTipo" class="form-select form-select-sm">
                                <option value="0">-- Todos los tipos --</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Estado del Procedimiento</label>
                            <select id="filtroEstado" class="form-select form-select-sm">
                                <option value="0">-- Todos los estados --</option>
                                <option value="2053">Borrador</option>
                                <option value="2054">Pendiente de Aprobación</option>
                                <option value="2055" selected>Confirmado / Aprobado</option>
                                <option value="2056">Eliminado / Obsoleto</option>
                            </select>
                        </div>
                        <div class="col-md-6 col-sm-12 text-md-end mt-3 mt-md-auto">
                            <div id="btn-container-procedimiento" class="d-inline-block me-1"></div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Refrescar">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Procedimientos -->
            <div class="card shadow-sm">
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="tablaProcedimientos" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 45px;" class="text-center">ID</th>
                                    <th style="width: 140px;">Tipo</th>
                                    <th style="width: 125px;">Código</th>
                                    <th>Nombre del Procedimiento</th>
                                    <th style="width: 160px;">Área / Responsable</th>
                                    <th style="width: 150px;">Norma Jurídica</th>
                                    <th style="width: 110px;" class="text-center">Versión & Adjunto</th>
                                    <th style="width: 130px;" class="text-center">Revisión ISO</th>
                                    <th style="width: 100px;" class="text-center">Estado</th>
                                    <th style="width: 130px;" class="text-center">Acciones</th>
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

<!-- Modal: Alta y Edición de Procedimiento -->
<div class="modal fade" id="modalProcedimiento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="formProcedimiento" enctype="multipart/form-data" novalidate>
                <input type="hidden" id="proc_id" name="procedimiento_id" value="0">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6" id="modalProcTitle"><i class="fas fa-file-alt me-1"></i>Registrar Procedimiento de Soporte</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold">Tipo de Documento <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="proc_tipo_id" name="procedimiento_tipo_id" required>
                                <option value="">-- Seleccione tipo --</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm fw-semibold">Código <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm text-uppercase font-monospace" id="proc_codigo" name="codigo" required maxlength="80" placeholder="Ej: PRO-SEG-01">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label form-label-sm fw-semibold">Nombre del Procedimiento <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="proc_nombre" name="nombre" required maxlength="255" placeholder="Ej: Control de Descarga y Almacenaje">
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-4">
                            <label class="form-label form-label-sm fw-semibold">Área / Sector</label>
                            <input type="text" class="form-control form-control-sm" id="proc_area_responsable" name="area_responsable" list="dlAreas" placeholder="Ej: Operaciones y Tanques">
                            <datalist id="dlAreas"></datalist>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label form-label-sm fw-semibold">Responsable Asignado</label>
                            <select class="form-select form-select-sm" id="proc_responsable_id" name="responsable_usuario_id">
                                <option value="">-- Sin responsable asignado --</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm fw-semibold">Revisión Periódica</label>
                            <select class="form-select form-select-sm" id="proc_frecuencia_meses" name="frecuencia_revision_meses">
                                <option value="6">Cada 6 meses</option>
                                <option value="12" selected>Cada 12 meses (Anual)</option>
                                <option value="24">Cada 24 meses</option>
                                <option value="36">Cada 36 meses</option>
                            </select>
                        </div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label form-label-sm fw-semibold">Empresa Destino</label>
                            <select class="form-select form-select-sm" id="proc_empresa_id" name="empresa_id">
                                <option value="">-- Aplica a todo el Grupo --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label form-label-sm fw-semibold">Norma de Origen Vinculada</label>
                            <select class="form-select form-select-sm" id="proc_norma_id" name="norma_id">
                                <option value="">-- Sin norma asociada (Soporte interno) --</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Descripción / Alcance</label>
                        <textarea class="form-control form-control-sm" id="proc_descripcion" name="descripcion" rows="2" placeholder="Resumen del procedimiento, directivas clave o alcance operativo..."></textarea>
                    </div>
                    <div class="row g-2 align-items-center bg-light p-2 rounded border">
                        <div class="col-md-8">
                            <label class="form-label form-label-sm fw-semibold mb-1">
                                <i class="fas fa-paperclip me-1 text-primary"></i>Archivo del Procedimiento (PDF, Word, Excel, etc.)
                            </label>
                            <input type="file" class="form-control form-control-sm" id="proc_archivo" name="archivo_adjunto" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.txt,.rtf,.png,.jpg,.jpeg,.zip">
                            <small class="text-muted d-block" style="font-size: 0.75rem;">Se archivará en la versión inicial v1.0 con cálculo de hash SHA-256 (máx. 25 MB).</small>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch mt-3">
                                <input class="form-check-input" type="checkbox" id="proc_confidencial" name="confidencial" value="1">
                                <label class="form-check-label form-label-sm text-danger fw-semibold" for="proc_confidencial">¿Documento Confidencial?</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnGuardarProcedimiento"><i class="fas fa-save me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Centro de Control del Ciclo de Vida del Procedimiento (Puntos 2 al 7) -->
<div class="modal fade" id="modalVersionesProc" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white py-2">
                <div class="d-flex align-items-center">
                    <h5 class="modal-title fs-6 me-2 mb-0">
                        <i class="fas fa-layer-group me-1"></i>Ciclo de Vida: <span id="lblProcCodigo" class="fw-bold font-monospace text-warning"></span>
                    </h5>
                    <span id="lblProcNombreHeader" class="text-light small text-truncate" style="max-width: 450px;"></span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <!-- Pestañas de Navegación del Ciclo de Vida -->
            <ul class="nav nav-tabs px-3 pt-2 bg-light border-bottom" id="tabsProcedimiento" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active py-1 px-3 fw-semibold small" id="tab-ver-btn" data-bs-toggle="tab" data-bs-target="#tab-ver" type="button" role="tab">
                        <i class="fas fa-code-branch me-1 text-primary"></i>1. Versiones e Instrucciones
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-1 px-3 fw-semibold small" id="tab-docs-btn" data-bs-toggle="tab" data-bs-target="#tab-docs" type="button" role="tab">
                        <i class="fas fa-sitemap me-1 text-success"></i>2. Documentos Asociados <span id="badgeContadorDocs" class="badge bg-secondary ms-1">0</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-1 px-3 fw-semibold small" id="tab-aprob-btn" data-bs-toggle="tab" data-bs-target="#tab-aprob" type="button" role="tab">
                        <i class="fas fa-signature me-1 text-info"></i>3. Workflow y Firmas <span id="badgeContadorAprobs" class="badge bg-secondary ms-1">0</span>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-1 px-3 fw-semibold small" id="tab-lect-btn" data-bs-toggle="tab" data-bs-target="#tab-lect" type="button" role="tab">
                        <i class="fas fa-user-check me-1 text-warning"></i>4. Difusión y Toma de Conocimiento
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link py-1 px-3 fw-semibold small" id="tab-aud-btn" data-bs-toggle="tab" data-bs-target="#tab-aud" type="button" role="tab">
                        <i class="fas fa-history me-1 text-danger"></i>5. Pista de Auditoría
                    </button>
                </li>
            </ul>

            <div class="modal-body p-3">
                <div class="tab-content" id="tabsProcedimientoContent">
                    
                    <!-- TAB 1: Versiones e Instrucciones -->
                    <div class="tab-pane fade show active" id="tab-ver" role="tabpanel">
                        <div class="row">
                            <!-- Columna Izquierda: Versiones -->
                            <div class="col-md-6 border-end">
                                <h6 class="fw-bold text-primary mb-2"><i class="fas fa-code-branch me-1"></i>Versiones y Archivos Adjuntos</h6>
                                <form id="formNuevaVersionProc" class="bg-light p-2 rounded border mb-3" enctype="multipart/form-data" novalidate>
                                    <input type="hidden" id="verproc_id" name="procedimiento_id">
                                    <div class="row g-2 mb-2">
                                        <div class="col-4">
                                            <label class="form-label form-label-sm fw-semibold mb-0">Versión <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control form-control-sm font-monospace" id="verproc_version" name="version" required placeholder="Ej: v2.0">
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label form-label-sm fw-semibold mb-0">Vigente Desde <span class="text-danger">*</span></label>
                                            <input type="date" class="form-control form-control-sm" id="verproc_desde" name="vigente_desde" required>
                                        </div>
                                        <div class="col-4">
                                            <label class="form-label form-label-sm fw-semibold mb-0">Aprobar Directo</label>
                                            <div class="form-check form-switch mt-1">
                                                <input class="form-check-input" type="checkbox" id="verproc_aprobar_directo" name="aprobar_directo" value="1">
                                                <label class="form-check-label form-label-sm small text-success" for="verproc_aprobar_directo">Vigente</label>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <input type="text" class="form-control form-control-sm" id="verproc_resumen" name="resumen_cambios" placeholder="Resumen o motivo del cambio...">
                                        </div>
                                        <div class="col-8">
                                            <input type="file" class="form-control form-control-sm" id="verproc_archivo" name="archivo_version" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.odt,.ods,.txt,.rtf,.png,.jpg,.jpeg,.zip">
                                        </div>
                                        <div class="col-4 text-end">
                                            <button type="submit" class="btn btn-sm btn-primary w-100" id="btnCrearVersion">
                                                <i class="fas fa-plus me-1"></i>Crear Versión
                                            </button>
                                        </div>
                                    </div>
                                </form>
                                <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th>Versión</th>
                                                <th>Desde</th>
                                                <th>Archivo Adjunto</th>
                                                <th>Estado</th>
                                                <th class="text-center">Instrucciones</th>
                                            </tr>
                                        </thead>
                                        <tbody id="bodyVersionesProc"></tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Columna Derecha: Instrucciones de Trabajo -->
                            <div class="col-md-6">
                                <h6 class="fw-bold text-success mb-2">
                                    <i class="fas fa-list-ol me-1"></i>Instrucciones Paso a Paso: <span id="lblVersionSeleccionada" class="badge bg-secondary">-</span>
                                </h6>
                                <form id="formNuevaInstruccion" class="row g-2 mb-3 bg-light p-2 rounded border" novalidate style="display: none;">
                                    <input type="hidden" id="ins_version_id" name="procedimiento_version_id">
                                    <div class="col-2">
                                        <input type="number" class="form-control form-control-sm" id="ins_orden" name="orden" value="1" min="1" required placeholder="N°">
                                    </div>
                                    <div class="col-7">
                                        <input type="text" class="form-control form-control-sm" id="ins_titulo" name="titulo" required placeholder="Título del paso...">
                                    </div>
                                    <div class="col-3 text-end">
                                        <button type="submit" class="btn btn-sm btn-success w-100"><i class="fas fa-plus me-1"></i>Paso</button>
                                    </div>
                                    <div class="col-12 mt-1">
                                        <input type="text" class="form-control form-control-sm" id="ins_descripcion" name="descripcion" placeholder="Detalle técnico u operativo del paso...">
                                    </div>
                                </form>
                                <div class="table-responsive" style="max-height: 280px; overflow-y: auto;">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th style="width: 45px;" class="text-center">N°</th>
                                                <th>Instrucción / Paso Operativo</th>
                                                <th style="width: 50px;" class="text-center">Acción</th>
                                            </tr>
                                        </thead>
                                        <tbody id="bodyInstrucciones">
                                            <tr><td colspan="3" class="text-center text-muted py-2">Seleccione una versión a la izquierda</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- TAB 2: Documentos Asociados / Jerarquía Documental (Puntos 1 y 2) -->
                    <div class="tab-pane fade" id="tab-docs" role="tabpanel">
                        <div class="bg-light p-2 rounded border mb-3">
                            <form id="formVincularDocumento" class="row g-2 align-items-end" novalidate>
                                <input type="hidden" id="vinc_padre_id" name="procedimiento_padre_id">
                                <div class="col-md-5">
                                    <label class="form-label form-label-sm fw-semibold mb-1">Documento Asociado (Catálogo)</label>
                                    <select class="form-select form-select-sm" id="vinc_documento_id" name="documento_asociado_id" required>
                                        <option value="">-- Seleccionar documento del catálogo --</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label form-label-sm fw-semibold mb-1">Tipo de Relación</label>
                                    <select class="form-select form-select-sm" id="vinc_tipo_relacion" name="tipo_relacion">
                                        <option value="instructivo">Instructivo Operativo</option>
                                        <option value="formulario">Formulario / Plantilla</option>
                                        <option value="registro">Registro / Checklist</option>
                                        <option value="anexo" selected>Anexo Técnico</option>
                                        <option value="politica_marco">Política Marco</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <div class="form-check form-switch mt-3">
                                        <input class="form-check-input" type="checkbox" id="vinc_obligatorio" name="obligatorio" value="1" checked>
                                        <label class="form-check-label form-label-sm fw-semibold" for="vinc_obligatorio">Obligatorio</label>
                                    </div>
                                </div>
                                <div class="col-md-2 text-end">
                                    <button type="submit" class="btn btn-sm btn-success w-100" id="btnVincularDoc">
                                        <i class="fas fa-link me-1"></i>Vincular
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                            <table class="table table-sm table-striped table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 130px;">Tipo Relación</th>
                                        <th style="width: 120px;">Código</th>
                                        <th>Nombre del Documento Asociado</th>
                                        <th style="width: 90px;" class="text-center">Obligatorio</th>
                                        <th style="width: 140px;" class="text-center">Versión Vigente</th>
                                        <th style="width: 70px;" class="text-center">Acción</th>
                                    </tr>
                                </thead>
                                <tbody id="bodyDocumentosAsociados">
                                    <tr><td colspan="6" class="text-center text-muted py-2">Sin documentos vinculados</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB 3: Workflow de Aprobaciones y Firmas Digitales (Punto 3) -->
                    <div class="tab-pane fade" id="tab-aprob" role="tabpanel">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h6 class="fw-bold text-dark mb-0"><i class="fas fa-signature me-1 text-primary"></i>Historial de Firmas Electrónicas y Dictámenes</h6>
                            <button type="button" class="btn btn-sm btn-outline-primary" id="btnAbrirModalAprobacion">
                                <i class="fas fa-file-signature me-1"></i>Emitir Dictamen / Firma
                            </button>
                        </div>
                        <div class="table-responsive" style="max-height: 320px; overflow-y: auto;">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 130px;">Fecha y Hora</th>
                                        <th style="width: 140px;">Firmante / Usuario</th>
                                        <th style="width: 120px;">Rol</th>
                                        <th style="width: 100px;" class="text-center">Dictamen</th>
                                        <th>Observaciones</th>
                                        <th style="width: 150px;">Firma Digital (Hash)</th>
                                    </tr>
                                </thead>
                                <tbody id="bodyAprobacionesProc">
                                    <tr><td colspan="6" class="text-center text-muted py-2">Sin firmas registradas aún</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB 4: Difusión y Toma de Conocimiento (Punto 4) -->
                    <div class="tab-pane fade" id="tab-lect" role="tabpanel">
                        <div class="row align-items-center bg-light p-3 rounded border mb-3">
                            <div class="col-md-7">
                                <div class="d-flex align-items-center mb-1">
                                    <span class="fw-semibold text-dark me-2">Cobertura de Toma de Conocimiento:</span>
                                    <span id="lblPorcentajeLectura" class="badge bg-primary fs-7">0%</span>
                                    <small id="lblContadorLecturas" class="text-muted ms-2">(0 de 0 colaboradores)</small>
                                </div>
                                <div class="progress" style="height: 10px;">
                                    <div id="barLecturas" class="progress-bar progress-bar-striped bg-success" style="width: 0%"></div>
                                </div>
                            </div>
                            <div class="col-md-5 text-md-end mt-2 mt-md-0">
                                <button type="button" class="btn btn-sm btn-success" id="btnFirmarTomaConocimiento">
                                    <i class="fas fa-check-circle me-1"></i>Firmar Toma de Conocimiento
                                </button>
                            </div>
                        </div>
                        <h6 class="fw-bold text-secondary mb-2 small"><i class="fas fa-users me-1"></i>Colaboradores con Lectura y Compromiso Confirmado</h6>
                        <div class="table-responsive" style="max-height: 250px; overflow-y: auto;">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Fecha / Hora</th>
                                        <th>Colaborador</th>
                                        <th>Perfil / Área</th>
                                        <th>IP Origen</th>
                                        <th class="text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody id="bodyLecturasProc">
                                    <tr><td colspan="5" class="text-center text-muted py-2">Sin acuses de recibo registrados para esta versión</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- TAB 5: Pista de Auditoría (Punto 7) -->
                    <div class="tab-pane fade" id="tab-aud" role="tabpanel">
                        <div class="table-responsive" style="max-height: 340px; overflow-y: auto;">
                            <table class="table table-sm table-striped table-hover align-middle mb-0 font-monospace" style="font-size: 0.8rem;">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 140px;">Fecha</th>
                                        <th style="width: 130px;">Evento</th>
                                        <th>Descripción del Evento</th>
                                        <th style="width: 120px;">Usuario</th>
                                        <th style="width: 110px;">IP</th>
                                    </tr>
                                </thead>
                                <tbody id="bodyLogsProc">
                                    <tr><td colspan="5" class="text-center text-muted py-2">Sin eventos registrados</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Dictamen y Firma Formal de Aprobación (Punto 3) -->
<div class="modal fade" id="modalAprobacionFormal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <form id="formAprobacionFormal" novalidate>
                <input type="hidden" id="aprob_proc_id" name="procedimiento_id">
                <input type="hidden" id="aprob_ver_id" name="procedimiento_version_id">
                <div class="modal-header bg-dark text-white py-2">
                    <h5 class="modal-title fs-6"><i class="fas fa-file-signature me-1"></i>Dictamen Formal y Firma Electrónica</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info py-2 small mb-2">
                        <i class="fas fa-info-circle me-1"></i>La aprobación generará una firma digital con hash SHA-256 inmutable registrada con su usuario e IP.
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Rol del Firmante <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="aprob_rol_firmante" name="rol_firmante" required>
                            <option value="Aprobador de Compliance">Aprobador de Compliance y Normativa</option>
                            <option value="Responsable de Área Operativa">Responsable de Área Operativa</option>
                            <option value="Revisor Técnico Especialista">Revisor Técnico Especialista</option>
                            <option value="Jefe de Calidad y Procesos">Jefe de Calidad y Procesos</option>
                            <option value="Gerencia General">Gerencia General</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Observaciones / Dictamen</label>
                        <textarea class="form-control form-control-sm" id="aprob_observaciones" name="observaciones" rows="3" placeholder="Comentarios de aprobación o motivo de rechazo si aplica..."></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2 d-flex justify-content-between">
                    <button type="button" class="btn btn-sm btn-outline-danger" id="btnRechazarFormal">
                        <i class="fas fa-times me-1"></i>Rechazar a Borrador
                    </button>
                    <div>
                        <button type="button" class="btn btn-sm btn-secondary me-1" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-sm btn-success" id="btnAprobarFormal">
                            <i class="fas fa-check-circle me-1"></i>Firmar y Aprobar
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Marcar Procedimiento como Obsoleto (Punto 6) -->
<div class="modal fade" id="modalObsoletoProc" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md modal-dialog-centered">
        <div class="modal-content">
            <form id="formMarcarObsoleto" novalidate>
                <input type="hidden" id="obs_proc_id" name="procedimiento_id">
                <div class="modal-header bg-danger text-white py-2">
                    <h5 class="modal-title fs-6"><i class="fas fa-archive me-1"></i>Pase a Obsoleto / Baja Controlada</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning py-2 small mb-2">
                        <i class="fas fa-exclamation-triangle me-1"></i>El documento y sus versiones quedarán en estado <strong>Obsoleto</strong>. El archivo físico y el historial permanecerán intactos e inmutables para fines de auditoría.
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Motivo o Justificación de la Baja <span class="text-danger">*</span></label>
                        <textarea class="form-control form-control-sm" id="obs_motivo" name="motivo" rows="3" required placeholder="Indique la justificación técnica, cambio regulatorio o documento que lo reemplaza..."></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-danger" id="btnConfirmarObsoleto">
                        <i class="fas fa-archive me-1"></i>Confirmar Obsolescencia
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

</div>

<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="procedimientos.js"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
