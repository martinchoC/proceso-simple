<?php
/**
 * Vista de Plantillas de Tareas (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/tareas_plantillas.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Plantillas de Tareas";
$currentPage = 'tareas_plantillas';
$modudo_idx = 3;
$pagina_idx = 324; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-tasks me-2 text-primary"></i>Plantillas de Tareas
                    </h3>
                    <small class="text-muted">Definición de actividades operativas tipo, ventana de días y secuencia de dependencias</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item"><a href="obligaciones.php">Obligaciones</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Plantillas de Tareas</li>
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
                        <div class="col-md-5 col-sm-12">
                            <label class="form-label form-label-sm mb-1 text-muted">Obligación de Cumplimiento</label>
                            <select id="filtroObligacion" class="form-select form-select-sm">
                                <option value="">-- Todas las obligaciones --</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Estado</label>
                            <select id="filtroEstado" class="form-select form-select-sm">
                                <option value="">-- Todos los estados --</option>
                                <option value="1" selected>Activo</option>
                                <option value="2">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-md-4 col-sm-6 text-md-end mt-3 mt-md-auto">
                            <div id="btn-container-plantilla" class="d-inline-block me-1"></div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Refrescar">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Plantillas de Tareas -->
            <div class="card card-outline card-secondary shadow-sm">
                <div class="card-header border-0 pb-0">
                    <h5 class="card-title text-secondary">
                        <i class="fas fa-list-ol me-2"></i>Secuencia de Tareas Tipo por Obligación
                    </h5>
                </div>
                <div class="card-body pt-2">
                    <div class="table-responsive">
                        <table id="tablaTareasPlantillas" class="table table-sm table-striped table-hover align-middle w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 60px;" class="text-center"># Orden</th>
                                    <th>Obligación / Norma</th>
                                    <th>Título de la Tarea</th>
                                    <th>Procedimiento de Soporte</th>
                                    <th class="text-center">Ventana (Inicio / Vencimiento)</th>
                                    <th>Dependencias Predecesoras</th>
                                    <th class="text-center" style="width: 80px;">Estado</th>
                                    <th class="text-center" style="width: 110px;">Acciones</th>
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

<!-- Modal: Crear/Editar Plantilla de Tarea -->
<div class="modal fade" id="modalPlantilla" tabindex="-1" aria-labelledby="modalPlantillaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title" id="modalPlantillaLabel">
                    <i class="fas fa-tasks me-2"></i>Plantilla de Tarea
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formPlantilla">
                <input type="hidden" name="tarea_plantilla_id" id="plantilla_id" value="0">
                <div class="modal-body">
                    <div class="row g-2 mb-2">
                        <div class="col-md-9">
                            <label class="form-label form-label-sm">Obligación <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" name="obligacion_id" id="plantilla_obligacion_id" required>
                                <option value="">-- Seleccionar obligación --</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm">Orden de Secuencia <span class="text-danger">*</span></label>
                            <input type="number" class="form-control form-control-sm" name="orden" id="plantilla_orden" min="1" placeholder="Ej: 1, 2, 3..." required>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label form-label-sm">Título de la Tarea <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" name="titulo" id="plantilla_titulo" maxlength="255" placeholder="Ej: Elaborar informe técnico previo" required>
                    </div>

                    <div class="mb-2">
                        <label class="form-label form-label-sm">Descripción / Instrucciones Operativas</label>
                        <textarea class="form-control form-control-sm" name="descripcion" id="plantilla_descripcion" rows="3" placeholder="Detalle los pasos requeridos para ejecutar esta tarea..."></textarea>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-md-6">
                            <label class="form-label form-label-sm">Procedimiento Operativo Vinculado</label>
                            <select class="form-select form-select-sm" name="procedimiento_id" id="plantilla_procedimiento_id">
                                <option value="">-- Sin procedimiento asignado --</option>
                            </select>
                            <div class="form-text text-muted" style="font-size: 0.75rem;">Guía o manual técnico estándar del catálogo M3.</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm">Offset Inicio (días)</label>
                            <input type="number" class="form-control form-control-sm" name="offset_dias_inicio" id="plantilla_offset_inicio" placeholder="Ej: -15 (antes)">
                            <div class="form-text text-muted" style="font-size: 0.75rem;">Relativo a la fecha base</div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label form-label-sm">Offset Vencimiento (días)</label>
                            <input type="number" class="form-control form-control-sm" name="offset_dias_vencimiento" id="plantilla_offset_vencimiento" placeholder="Ej: 0 o -2">
                            <div class="form-text text-muted" style="font-size: 0.75rem;">Relativo al vencimiento</div>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="form-label form-label-sm">Estado de la Plantilla</label>
                            <select class="form-select form-select-sm" name="tabla_estado_registro_id" id="plantilla_estado">
                                <option value="1">Activo</option>
                                <option value="2">Inactivo</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnGuardarPlantilla">
                        <i class="fas fa-save me-1"></i>Guardar Plantilla
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Dependencias y Secuencia de la Tarea -->
<div class="modal fade" id="modalDependencias" tabindex="-1" aria-labelledby="modalDependenciasLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-info text-white py-2">
                <h5 class="modal-title" id="modalDependenciasLabel">
                    <i class="fas fa-project-diagram me-2"></i>Dependencias y Predecesoras
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border py-2 mb-3">
                    <div class="fw-bold" id="dep_tarea_info">Tarea seleccionada</div>
                    <small class="text-muted" id="dep_obligacion_info">Obligación</small>
                </div>

                <!-- Formulario Agregar Predecesora -->
                <div class="card card-outline card-info mb-3">
                    <div class="card-header py-1 bg-light">
                        <h6 class="card-title mb-0 small text-info fw-bold">
                            <i class="fas fa-plus-circle me-1"></i>Vincular Tarea Predecesora
                        </h6>
                    </div>
                    <div class="card-body py-2">
                        <form id="formNuevaDependencia" class="row g-2 align-items-end">
                            <input type="hidden" name="tarea_plantilla_id" id="dep_tarea_id" value="0">
                            <div class="col-md-5">
                                <label class="form-label form-label-sm mb-1">Tarea Previa (Predecesora)</label>
                                <select class="form-select form-select-sm" name="tarea_plantilla_predecesora_id" id="dep_predecesora_id" required>
                                    <option value="">-- Seleccionar tarea previa --</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label form-label-sm mb-1">Estado Requerido para Activar</label>
                                <select class="form-select form-select-sm" name="estado_activador_id" id="dep_estado_activador_id" required>
                                    <!-- Cargado vía AJAX -->
                                </select>
                            </div>
                            <div class="col-md-2">
                                <div class="form-check form-switch mt-3">
                                    <input class="form-check-input" type="checkbox" name="obligatoria" id="dep_obligatoria" value="1" checked>
                                    <label class="form-check-label form-label-sm" for="dep_obligatoria">Obligatoria</label>
                                </div>
                            </div>
                            <div class="col-md-1 text-end">
                                <button type="submit" class="btn btn-sm btn-info text-white" id="btnAgregarDep" title="Vincular">
                                    <i class="fas fa-link"></i>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Lista de Predecesoras Actuales -->
                <div class="table-responsive">
                    <table class="table table-sm table-bordered table-hover align-middle mb-0" id="tablaDependencias">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;" class="text-center"># Orden</th>
                                <th>Tarea Predecesora</th>
                                <th class="text-center">Condición de Activación</th>
                                <th class="text-center" style="width: 90px;">Tipo</th>
                                <th class="text-center" style="width: 60px;">Acción</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyDependencias">
                            <!-- Filas dinámicas -->
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script>
    const PAGINA_ID = <?php echo $pagina_idx; ?>;
    const MODULO_ID = <?php echo $modudo_idx; ?>;
</script>
<script src="tareas_plantillas.js?v=<?php echo time(); ?>"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
