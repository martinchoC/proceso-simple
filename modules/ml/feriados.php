<?php
/**
 * Vista de Calendario de Feriados (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/feriados.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Calendario de Feriados";
$currentPage = 'feriados';
$modudo_idx = 3;
$pagina_idx = 306; // ID registrado en conf__paginas

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-calendar-alt me-2 text-primary"></i>Calendario de Feriados
                    </h3>
                    <small class="text-muted">Días no laborables por país y territorio para el cálculo de plazos</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Feriados</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Filtros de búsqueda -->
            <div class="card card-outline card-primary mb-3">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-4 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Territorio / Jurisdicción</label>
                            <select id="filtroTerritorio" class="form-select form-select-sm">
                                <option value="">-- Todos los territorios --</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Año</label>
                            <select id="filtroAnio" class="form-select form-select-sm">
                                <option value="">-- Todos --</option>
                                <?php
                                $anio_actual = intval(date('Y'));
                                for ($y = $anio_actual - 2; $y <= $anio_actual + 2; $y++) {
                                    $selected = ($y === $anio_actual) ? 'selected' : '';
                                    echo "<option value=\"$y\" $selected>$y</option>";
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Estado</label>
                            <select id="filtroEstado" class="form-select form-select-sm">
                                <option value="1">Activos</option>
                                <option value="0">Todos</option>
                                <option value="2">Inactivos</option>
                            </select>
                        </div>
                        <div class="col-md-4 col-sm-6 text-md-end mt-3 mt-md-auto">
                            <div id="contenedor-boton-agregar" class="d-inline-block me-1"></div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Refrescar lista">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla Principal -->
            <div class="card shadow-sm">
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="tablaFeriados" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 70px;" class="text-center">ID</th>
                                    <th style="width: 130px;" class="text-center">Fecha</th>
                                    <th>Descripción / Motivo</th>
                                    <th style="width: 200px;">Territorio</th>
                                    <th style="width: 140px;">Nivel</th>
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

<!-- Modal: Agregar / Editar Feriado -->
<div class="modal fade" id="modalFeriado" tabindex="-1" aria-labelledby="modalFeriadoLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formFeriado" novalidate>
                <input type="hidden" id="feriado_id" name="feriado_id" value="0">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6" id="modalFeriadoLabel">
                        <i class="fas fa-calendar-plus me-1"></i> <span id="tituloModal">Nuevo Feriado</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="modal_territorio_id" class="form-label form-label-sm fw-semibold">Territorio / País <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="modal_territorio_id" name="territorio_id" required>
                            <option value="">-- Seleccionar Territorio --</option>
                        </select>
                        <div class="invalid-feedback">Seleccione un territorio.</div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_fecha" class="form-label form-label-sm fw-semibold">Fecha <span class="text-danger">*</span></label>
                        <input type="date" class="form-control form-control-sm" id="modal_fecha" name="fecha" required>
                        <div class="invalid-feedback">Indique la fecha del feriado.</div>
                    </div>
                    <div class="mb-3">
                        <label for="modal_nombre" class="form-label form-label-sm fw-semibold">Nombre o Motivo <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="modal_nombre" name="nombre" maxlength="150" placeholder="Ej: Año Nuevo, Día de la Independencia" required>
                        <div class="invalid-feedback">Ingrese la descripción o motivo del feriado.</div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnGuardarFeriado">
                        <i class="fas fa-save me-1"></i>Guardar Feriado
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="feriados.js"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
