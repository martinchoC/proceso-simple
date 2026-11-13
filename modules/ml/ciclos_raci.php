<?php
/**
 * Vista de Matriz RACI y Asignaciones (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/ciclos_raci.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Matriz RACI y Asignaciones";
$currentPage = 'ciclos_raci';
$modudo_idx = 3;
$pagina_idx = 344; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-users-cog me-2 text-primary"></i>Matriz RACI y Asignaciones
                    </h3>
                    <small class="text-muted">Gestión de roles RACI por tarea y reasignación en bloque ante bajas o cambios de puesto</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item">Ciclos y Tareas</li>
                        <li class="breadcrumb-item active" aria-current="page">Matriz RACI</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Barra de Acciones y Filtros -->
            <div class="card card-outline card-primary mb-3">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Cargo / Posición</label>
                            <select id="filtroPosicion" class="form-select form-select-sm">
                                <option value="">-- Todos los cargos --</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Usuario Asignado</label>
                            <select id="filtroUsuario" class="form-select form-select-sm">
                                <option value="">-- Todos los usuarios --</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Rol RACI</label>
                            <select id="filtroRol" class="form-select form-select-sm">
                                <option value="">-- Todos los roles --</option>
                                <option value="1">R - Responsable</option>
                                <option value="2">A - Aprobador</option>
                                <option value="3">C - Consultado</option>
                                <option value="4">I - Informado</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Entidad</label>
                            <select id="filtroEntidad" class="form-select form-select-sm">
                                <option value="">-- Todas las entidades --</option>
                            </select>
                        </div>
                        <div class="col-md-3 col-sm-12 text-end pt-3">
                            <button id="btnNuevaAsignacion" class="btn btn-sm btn-primary me-1" title="Asignar rol RACI">
                                <i class="fas fa-user-plus me-1"></i>Asignar Rol
                            </button>
                            <button id="btnReasignarBloque" class="btn btn-sm btn-danger me-1" title="Reasignación Masiva (Caso C27)">
                                <i class="fas fa-exchange-alt me-1"></i>Reasignación
                            </button>
                            <button id="btnHistorialReasig" class="btn btn-sm btn-outline-secondary" title="Historial">
                                <i class="fas fa-history"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Matriz RACI -->
            <div class="card card-outline card-secondary">
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table id="tablaRaci" class="table table-sm table-striped table-hover align-middle w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">ID</th>
                                    <th>Entidad</th>
                                    <th>Obligación / Ciclo</th>
                                    <th>Tarea Operativa</th>
                                    <th style="width: 140px;">Rol RACI</th>
                                    <th>Cargo / Posición</th>
                                    <th>Usuario Titular</th>
                                    <th style="width: 100px;">Vigente Desde</th>
                                    <th style="width: 70px;" class="text-center">Acciones</th>
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

<!-- Modal: Asignar Rol RACI -->
<div class="modal fade" id="modalAsignarRaci" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title"><i class="fas fa-user-tag me-2"></i>Asignar Rol RACI por Cargo y Usuario</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formAsignarRaci">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label form-label-sm">Tarea Operativa Abierta *</label>
                        <select id="raciTareaId" name="ciclo_tarea_id" class="form-select form-select-sm" required>
                            <option value="">-- Seleccionar tarea --</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm">Rol en Matriz RACI *</label>
                        <select id="raciRolId" name="raci_rol_id" class="form-select form-select-sm" required>
                            <option value="1">R - Responsable (Ejecutor directo)</option>
                            <option value="2">A - Aprobador / Accountable (Rinde cuentas y valida)</option>
                            <option value="3">C - Consultado (Aporta criterio técnico o legal)</option>
                            <option value="4">I - Informado (Recibe notificación de avance/cierre)</option>
                        </select>
                    </div>

                    <!-- Opción inteligente: Aprobador automático por supervisor jerárquico -->
                    <div class="mb-3 p-2 bg-light border rounded" id="boxSupervisorAuto" style="display: none;">
                        <div class="form-check form-switch mb-1">
                            <input class="form-check-input" type="checkbox" id="chkUsarSupervisor" name="usar_supervisor" value="1">
                            <label class="form-check-label form-label-sm fw-bold" for="chkUsarSupervisor">
                                <i class="fas fa-sitemap text-primary me-1"></i>Asignar automáticamente al supervisor del Responsable (R)
                            </label>
                        </div>
                        <small class="text-muted d-block" id="txtSupervisorInfo">
                            Determina el jefe directo de la posición ejecutora según el organigrama empresarial.
                        </small>
                    </div>

                    <div class="mb-3" id="boxPosicion">
                        <label class="form-label form-label-sm">Cargo / Posición Estructural</label>
                        <select id="raciPosicionId" name="posicion_id" class="form-select form-select-sm">
                            <option value="">-- Seleccionar cargo o definir por usuario --</option>
                        </select>
                        <div class="form-text text-muted" style="font-size: 0.75rem;">
                            Al seleccionar el cargo, se autocompletará el usuario titular activo asignado a esa posición.
                        </div>
                    </div>

                    <div class="mb-3" id="boxUsuario">
                        <label class="form-label form-label-sm">Usuario Titular Asignado *</label>
                        <select id="raciUsuarioId" name="usuario_id" class="form-select form-select-sm" required>
                            <option value="">-- Seleccionar usuario --</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer py-1">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-save me-1"></i>Guardar Asignación</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Reasignación en Bloque (Caso C27) -->
<div class="modal fade" id="modalReasignarBloque" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white py-2">
                <h5 class="modal-title"><i class="fas fa-exchange-alt me-2"></i>Reasignación en Bloque (Caso C27)</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formReasignarBloque">
                <div class="modal-body">
                    <div class="alert alert-warning py-2 small mb-3">
                        <i class="fas fa-exclamation-triangle me-1"></i>
                        Esta acción sustituye al <strong>Responsable (R)</strong> en todas las tareas pendientes, en curso o activas del usuario saliente hacia el nuevo responsable, dejando trazabilidad en el registro corporativo de reasignaciones.
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm">Usuario Origen (Saliente / Sustituido) *</label>
                        <select id="reasigOrigenId" name="usuario_origen_id" class="form-select form-select-sm" required>
                            <option value="">-- Seleccionar usuario actual --</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm">Usuario Destino (Nuevo Responsable) *</label>
                        <select id="reasigDestinoId" name="usuario_destino_id" class="form-select form-select-sm" required>
                            <option value="">-- Seleccionar usuario sustituto --</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm">Fecha de Efecto *</label>
                        <input type="date" id="reasigFechaEfecto" name="fecha_efecto" class="form-control form-control-sm" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label form-label-sm">Motivo Corporativo *</label>
                        <textarea id="reasigMotivo" name="motivo" class="form-control form-control-sm" rows="3" placeholder="Ej: Baja por desvinculación, rotación de puesto, licencia médica..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer py-1">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-danger"><i class="fas fa-check me-1"></i>Ejecutar Reasignación Masiva</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Historial de Reasignaciones -->
<div class="modal fade" id="modalHistorialReasig" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-secondary text-white py-2">
                <h5 class="modal-title"><i class="fas fa-history me-2"></i>Historial de Reasignaciones Masivas</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-2">
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Usuario Saliente</th>
                                <th>Nuevo Responsable</th>
                                <th style="width: 100px;">Fecha Efecto</th>
                                <th>Motivo</th>
                                <th style="width: 140px;">Ejecutado Por</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyHistorialReasig">
                            <tr><td colspan="6" class="text-center text-muted">Sin registros</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer py-1">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
<script src="ciclos_raci.js?v=<?= time() ?>"></script>
</body>
</html>
