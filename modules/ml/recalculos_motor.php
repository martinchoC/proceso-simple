<?php
/**
 * Vista de la Consola del Motor de Herencia y Recálculos (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/recalculos_motor.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Motor de Herencia y Recálculos";
$currentPage = 'recalculos_motor';
$modudo_idx = 3;
$pagina_idx = 334; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-cogs me-2 text-primary"></i>Motor de Herencia y Recálculos
                    </h3>
                    <small class="text-muted">Cálculo en cascada por territorio y corporativo, consolidación de solapamientos y simulación de impacto</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item"><a href="obligaciones_asignadas.php">Motor y Asignaciones</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Consola del Motor</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Tarjetas de Métricas -->
            <div class="row g-2 mb-3">
                <div class="col-md-2 col-sm-6">
                    <div class="card bg-light border-primary shadow-sm h-100">
                        <div class="card-body p-2 text-center">
                            <span class="text-muted small">Entidades</span>
                            <h4 class="mb-0 text-primary fw-bold" id="statEntidades">-</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card bg-light border-success shadow-sm h-100">
                        <div class="card-body p-2 text-center">
                            <span class="text-muted small">Obligaciones Asignadas</span>
                            <h4 class="mb-0 text-success fw-bold" id="statAsignadas">-</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card bg-light border-info shadow-sm h-100">
                        <div class="card-body p-2 text-center">
                            <span class="text-muted small">Solapamientos Consolidados</span>
                            <h4 class="mb-0 text-info fw-bold" id="statSolapamientos">-</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6">
                    <div class="card bg-light border-warning shadow-sm h-100">
                        <div class="card-body p-2 text-center">
                            <span class="text-muted small">Excepciones Activas</span>
                            <h4 class="mb-0 text-warning fw-bold" id="statExcepciones">-</h4>
                        </div>
                    </div>
                </div>
                <div class="col-md-2 col-sm-6">
                    <div class="card bg-light border-danger shadow-sm h-100">
                        <div class="card-body p-2 text-center">
                            <span class="text-muted small">Pendientes en Cola</span>
                            <h4 class="mb-0 text-danger fw-bold" id="statPendientes">-</h4>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel de Control del Motor -->
            <div class="card card-outline card-primary mb-3 shadow-sm">
                <div class="card-header py-2">
                    <h5 class="card-title mb-0 small text-primary fw-bold">
                        <i class="fas fa-play-circle me-1"></i>Ejecución y Simulación del Motor
                    </h5>
                </div>
                <div class="card-body py-2">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-5 col-sm-12">
                            <label class="form-label form-label-sm mb-1 text-muted">Alcance de Ejecución</label>
                            <select id="selectAlcanceEntidad" class="form-select form-select-sm">
                                <option value="0">-- Toda la Empresa (Todas las Entidades) --</option>
                            </select>
                        </div>
                        <div class="col-md-7 col-sm-12 text-md-end">
                            <button type="button" class="btn btn-sm btn-outline-info me-1" id="btnSimularImpacto" title="Vista previa sin guardar cambios (Caso C35)">
                                <i class="fas fa-search-plus me-1"></i>Simular Impacto (C35)
                            </button>
                            <button type="button" class="btn btn-sm btn-primary me-1" id="btnEjecutarMotor" title="Calcular y sincronizar obligaciones asignadas">
                                <i class="fas fa-sync-alt me-1"></i>Ejecutar Motor Ahora
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-dark" id="btnProcesarCola" title="Procesar lote de eventos pendientes">
                                <i class="fas fa-tasks me-1"></i>Procesar Cola
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel de Resultados de Simulación (Colapsable / Dinámico) -->
            <div id="panelSimulacion" class="card card-outline card-info mb-3 shadow-sm d-none">
                <div class="card-header py-2 bg-light">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 text-info fw-bold">
                            <i class="fas fa-chart-pie me-1"></i>Resultado de la Simulación de Impacto (Vista Previa Caso C35)
                        </h6>
                        <button type="button" class="btn btn-xs btn-outline-secondary" id="btnCerrarSimulacion">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body p-2">
                    <div class="alert alert-info py-2 mb-2 small">
                        <i class="fas fa-info-circle me-1"></i>Los datos mostrados corresponden a una corrida en memoria. Ningún cambio ha sido guardado todavía.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0" id="tablaSimulacion">
                            <thead class="table-light">
                                <tr>
                                    <th>Entidad</th>
                                    <th>Tipo</th>
                                    <th class="text-center">Normas Alcanzadas</th>
                                    <th class="text-center">Obligaciones Asignadas Proyectadas</th>
                                    <th class="text-center">Solapamientos Consolidados</th>
                                </tr>
                            </thead>
                            <tbody id="tbodySimulacion">
                                <!-- Filas dinámicas -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tabla de Cola Asíncrona de Recálculos -->
            <div class="card card-outline card-secondary shadow-sm">
                <div class="card-header border-0 pb-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title text-secondary">
                            <i class="fas fa-history me-2"></i>Cola Asíncrona de Recálculos Pendientes (ml__recalculos_pendientes)
                        </h5>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargarCola" title="Refrescar">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body pt-2">
                    <div class="table-responsive">
                        <table id="tablaColaRecalculos" class="table table-sm table-striped table-hover align-middle w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 60px;" class="text-center"># ID</th>
                                    <th>Causa del Evento</th>
                                    <th>Objeto / Tabla Afectada</th>
                                    <th class="text-center">Fecha Efecto</th>
                                    <th>Fecha Creación</th>
                                    <th>Fecha Procesamiento</th>
                                    <th class="text-center" style="width: 100px;">Estado</th>
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

<script>
    const PAGINA_ID = <?php echo $pagina_idx; ?>;
    const MODULO_ID = <?php echo $modudo_idx; ?>;
</script>
<script src="recalculos_motor.js?v=<?php echo time(); ?>"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
