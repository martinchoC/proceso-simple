<?php
/**
 * Vista de Consola del Generador y Eventos (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/ciclos_generador.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Generador y Eventos";
$currentPage = 'ciclos_generador';
$modudo_idx = 3;
$pagina_idx = 345; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-cogs me-2 text-primary"></i>Generador y Eventos
                    </h3>
                    <small class="text-muted">Proyección de calendarios móviles a 12 meses y gatillo de ciclos extraordinarios por evento</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item">Ciclos y Tareas</li>
                        <li class="breadcrumb-item active" aria-current="page">Generador y Eventos</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Métricas Principales -->
            <div class="row g-3 mb-4">
                <div class="col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm bg-primary text-white">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50 mb-1">Obligaciones Asignadas</h6>
                                    <h3 class="mb-0 fw-bold" id="kpiObligaciones">0</h3>
                                </div>
                                <i class="fas fa-layer-group fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm bg-info text-white">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50 mb-1">Ciclos Abiertos</h6>
                                    <h3 class="mb-0 fw-bold" id="kpiCiclosAbiertos">0</h3>
                                </div>
                                <i class="fas fa-clock fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm bg-success text-white">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50 mb-1">Ciclos Cumplidos</h6>
                                    <h3 class="mb-0 fw-bold" id="kpiCiclosCumplidos">0</h3>
                                </div>
                                <i class="fas fa-check-circle fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card border-0 shadow-sm bg-secondary text-white">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="text-white-50 mb-1">Tareas Instanciadas</h6>
                                    <h3 class="mb-0 fw-bold" id="kpiTareas">0</h3>
                                </div>
                                <i class="fas fa-tasks fa-2x text-white-50"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dos Módulos: Proyección y Disparo por Evento -->
            <div class="row g-3 mb-4">
                <!-- Proyección de Calendarios Móviles (Casos C02, C10, C22, C26) -->
                <div class="col-lg-6">
                    <div class="card card-outline card-primary h-100 shadow-sm">
                        <div class="card-header py-2">
                            <h5 class="card-title mb-0 text-primary">
                                <i class="fas fa-calendar-plus me-2"></i>Proyección de Calendario Móvil (Caso C02)
                            </h5>
                        </div>
                        <form id="formProyeccion">
                            <div class="card-body">
                                <p class="text-muted small">
                                    Genera anticipadamente los ciclos y tareas operativas para los próximos 12 meses móviles respetando cadencias ordinarias (mensual, bimestral, trimestral, semestral, anual, quinquenal), inicio futuro de vigencia (Caso C10) y vencimientos por CUIT/feriados (Caso C22).
                                </p>
                                <div class="mb-3">
                                    <label class="form-label form-label-sm">Entidad de Control</label>
                                    <select id="proyEntidadId" name="entidad_id" class="form-select form-select-sm">
                                        <option value="">-- Todas las entidades asignadas --</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label form-label-sm">Horizonte de Proyección (Meses Adelante)</label>
                                    <select id="proyMeses" name="meses_adelante" class="form-select form-select-sm">
                                        <option value="6">6 Meses</option>
                                        <option value="12" selected>12 Meses (Estándar Corporativo C02)</option>
                                        <option value="24">24 Meses</option>
                                    </select>
                                </div>
                                <div id="alertaProy" class="alert alert-info py-2 small mb-0" style="display:none;"></div>
                            </div>
                            <div class="card-footer bg-transparent py-2 text-end">
                                <button type="submit" class="btn btn-sm btn-primary" id="btnEjecutarProy">
                                    <i class="fas fa-magic me-1"></i>Proyectar e Instanciar Ciclos
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Disparador de Ciclos por Evento Extraordinario (Casos C07 y C31) -->
                <div class="col-lg-6">
                    <div class="card card-outline card-danger h-100 shadow-sm">
                        <div class="card-header py-2">
                            <h5 class="card-title mb-0 text-danger">
                                <i class="fas fa-bolt me-2"></i>Gatillo por Evento Extraordinario (Casos C07 y C31)
                            </h5>
                        </div>
                        <form id="formDispararEvento">
                            <div class="card-body">
                                <p class="text-muted small">
                                    Dispara un ciclo no programado ante un suceso crítico (ej. derrame ambiental, accidente de trabajo grave). El plazo de vencimiento se calcula en horas según la regla y el huso horario local de la planta (Caso C31).
                                </p>
                                <div class="mb-3">
                                    <label class="form-label form-label-sm">Obligación Asignada por Evento *</label>
                                    <select id="eventoObligacionId" name="obligacion_asignada_id" class="form-select form-select-sm" required>
                                        <option value="">-- Seleccionar obligación gatillada --</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label form-label-sm">Fecha y Hora del Suceso *</label>
                                    <input type="datetime-local" id="eventoFechaHora" name="fecha_hora_evento" class="form-control form-control-sm" value="<?= date('Y-m-d\TH:i') ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label form-label-sm">Descripción del Incidente / Detalle *</label>
                                    <textarea id="eventoDescripcion" name="descripcion" class="form-control form-control-sm" rows="2" placeholder="Detalle circunstancias del evento..." required></textarea>
                                </div>
                                <div id="alertaEvento" class="alert alert-danger py-2 small mb-0" style="display:none;"></div>
                            </div>
                            <div class="card-footer bg-transparent py-2 text-end">
                                <button type="submit" class="btn btn-sm btn-danger" id="btnDispararEvento">
                                    <i class="fas fa-exclamation-circle me-1"></i>Gatillar Ciclo Inmediato
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Últimos Ciclos Instanciados -->
            <div class="card card-outline card-secondary shadow-sm">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0"><i class="fas fa-list-alt me-2"></i>Últimos Ciclos Generados por el Motor</h6>
                    <a href="modules/ml/ciclos.php?empresa_id=<?= (int)($_SESSION['empresa_id'] ?? 2) ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-external-link-alt me-1"></i>Ver en Calendario y Ciclos
                    </a>
                </div>
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">ID</th>
                                    <th>Entidad</th>
                                    <th>Obligación</th>
                                    <th style="width: 140px;">Período</th>
                                    <th style="width: 140px;">Vencimiento</th>
                                    <th style="width: 80px;" class="text-center">Tareas</th>
                                    <th style="width: 100px;">Estado</th>
                                    <th style="width: 130px;">Generado En</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyUltimosCiclos">
                                <tr><td colspan="8" class="text-center text-muted">Cargando registros...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
<script src="modules/ml/ciclos_generador.js"></script>
</body>
</html>
