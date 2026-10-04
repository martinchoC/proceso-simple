<?php
/**
 * Vista de Obligaciones Asignadas y Orígenes Consolidados (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/obligaciones_asignadas.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Obligaciones Asignadas";
$currentPage = 'obligaciones_asignadas';
$modudo_idx = 3;
$pagina_idx = 332; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-layer-group me-2 text-primary"></i>Obligaciones Asignadas
                    </h3>
                    <small class="text-muted">Matriz viva resultante del Motor de Herencia y consolidación de solapamientos</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item">Motor y Asignaciones</li>
                        <li class="breadcrumb-item active" aria-current="page">Obligaciones Asignadas</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Barra de Filtros y Enlaces -->
            <div class="card card-outline card-primary mb-3">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-4 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Entidad Sujeta a Control</label>
                            <select id="filtroEntidad" class="form-select form-select-sm">
                                <option value="">-- Todas las entidades --</option>
                            </select>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Norma de Origen</label>
                            <select id="filtroNorma" class="form-select form-select-sm">
                                <option value="">-- Todas las normas --</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Estado</label>
                            <select id="filtroEstado" class="form-select form-select-sm">
                                <option value="">-- Todos --</option>
                                <option value="1" selected>Activas</option>
                                <option value="2">Inactivas / Bajas</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6 text-md-end mt-3 mt-md-auto">
                            <a href="recalculos_motor.php" class="btn btn-sm btn-outline-primary me-1" title="Ir a Consola del Motor">
                                <i class="fas fa-cogs me-1"></i>Motor
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Refrescar">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Obligaciones Asignadas -->
            <div class="card card-outline card-secondary shadow-sm">
                <div class="card-header border-0 pb-0">
                    <h5 class="card-title text-secondary">
                        <i class="fas fa-clipboard-list me-2"></i>Matriz de Exigencias Vigentes por Entidad
                    </h5>
                </div>
                <div class="card-body pt-2">
                    <div class="table-responsive">
                        <table id="tablaObligacionesAsignadas" class="table table-sm table-striped table-hover align-middle w-100">
                            <thead class="table-light">
                                <tr>
                                    <th>Entidad</th>
                                    <th>Exigencia Prevaleciente</th>
                                    <th>Norma / Ámbito</th>
                                    <th class="text-center">Periodicidad & Criticidad</th>
                                    <th class="text-center">Orígenes Consolidados</th>
                                    <th class="text-center">Excepción</th>
                                    <th class="text-center">Vigencia</th>
                                    <th class="text-center" style="width: 70px;">Estado</th>
                                    <th class="text-center" style="width: 90px;">Acciones</th>
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

<!-- Modal: Trazabilidad de Orígenes Consolidados (Casos C01, C11) -->
<div class="modal fade" id="modalOrigenes" tabindex="-1" aria-labelledby="modalOrigenesLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title" id="modalOrigenesLabel">
                    <i class="fas fa-code-branch me-2"></i>Trazabilidad de Orígenes y Solapamiento
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-light border py-2 mb-3">
                    <div class="fw-bold" id="orig_prevaleciente_info">Exigencia Prevaleciente</div>
                    <small class="text-muted" id="orig_entidad_info">Entidad asignada</small>
                </div>

                <h6 class="text-secondary fw-bold mb-2">
                    <i class="fas fa-history me-1"></i>Fuentes Normativas que Convergen en esta Exigencia
                </h6>
                <div class="table-responsive">
                    <table class="table table-sm table-bordered align-middle mb-0" id="tablaOrigenesDetalle">
                        <thead class="table-light">
                            <tr>
                                <th>Norma</th>
                                <th>Tipo</th>
                                <th>Código Obligación</th>
                                <th>Descripción</th>
                                <th class="text-center">Criticidad</th>
                                <th class="text-center">Periodicidad</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyOrigenesDetalle">
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
<script src="obligaciones_asignadas.js?v=<?php echo time(); ?>"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
