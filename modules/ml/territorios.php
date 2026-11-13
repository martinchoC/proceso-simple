<?php
/**
 * Vista de Territorios y Husos Horarios (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/territorios.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Territorios y Husos Horarios";
$currentPage = 'territorios';
$modudo_idx = 3;
$pagina_idx = 305; // ID registrado en conf__paginas

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(dirname(__DIR__)));
}
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-globe-americas me-2 text-primary"></i>Territorios y Husos Horarios
                    </h3>
                    <small class="text-muted">Árbol geográfico de profundidad libre y husos normativos</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Matriz Legal</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Territorios</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Filtros y Controles -->
            <div class="card card-outline card-primary mb-3">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-4 col-sm-6">
                            <label class="form-label form-label-sm mb-1 text-muted">Tipo de Territorio</label>
                            <select id="filtroTipo" class="form-select form-select-sm">
                                <option value="">-- Todos los tipos --</option>
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
                        <div class="col-md-5 col-sm-12 text-md-end mt-3 mt-md-auto">
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
                        <table id="tablaTerritorios" class="table table-sm table-striped table-hover align-middle mb-0 w-100">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;" class="text-center">ID</th>
                                <th style="width: 110px;">Código</th>
                                <th>Nombre del Territorio</th>
                                <th style="width: 140px;">Nivel / Tipo</th>
                                <th>Nodo Superior (Pertenencia Principal)</th>
                                <th style="width: 180px;">Huso Horario</th>
                                <th style="width: 90px;" class="text-center">Estado</th>
                                <th style="width: 140px;" class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                    </div>
                </div>
            </div>
        </div>
    </div>


<!-- Modal: Agregar / Editar Territorio -->
<div class="modal fade" id="modalTerritorio" tabindex="-1" aria-labelledby="modalTerritorioLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form id="formTerritorio" novalidate>
                <input type="hidden" id="territorio_id" name="territorio_id" value="0">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6" id="modalTerritorioLabel">
                        <i class="fas fa-map-marker-alt me-1"></i> <span id="tituloModal">Nuevo Territorio</span>
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label for="codigo" class="form-label form-label-sm">Código <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm text-uppercase" id="codigo" name="codigo" maxlength="30" required placeholder="Ej: ARG, BUE">
                        </div>
                        <div class="col-md-8">
                            <label for="nombre" class="form-label form-label-sm">Nombre <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="nombre" name="nombre" maxlength="150" required placeholder="Nombre del territorio">
                        </div>
                        <div class="col-md-6">
                            <label for="territorio_tipo_id" class="form-label form-label-sm">Tipo de Territorio <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="territorio_tipo_id" name="territorio_tipo_id" required>
                                <option value="">-- Seleccionar --</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="huso_horario" class="form-label form-label-sm">Huso Horario IANA <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-sm" id="huso_horario" name="huso_horario" list="listaHusos" required value="America/Argentina/Buenos_Aires">
                            <datalist id="listaHusos">
                                <option value="America/Argentina/Buenos_Aires">Argentina (UTC-3)</option>
                                <option value="America/Santiago">Chile (UTC-4/-3)</option>
                                <option value="America/Sao_Paulo">Brasil (UTC-3)</option>
                                <option value="America/Montevideo">Uruguay (UTC-3)</option>
                                <option value="America/Asuncion">Paraguay (UTC-4/-3)</option>
                                <option value="America/La_Paz">Bolivia (UTC-4)</option>
                                <option value="America/Lima">Perú (UTC-5)</option>
                                <option value="America/Bogota">Colombia (UTC-5)</option>
                                <option value="America/Mexico_City">México (UTC-6)</option>
                                <option value="America/New_York">EE.UU. Este (UTC-5/-4)</option>
                                <option value="Europe/Madrid">España (UTC+1/+2)</option>
                                <option value="UTC">UTC Universal</option>
                            </datalist>
                        </div>

                        <!-- Bloque de Pertenencia Inicial (Solo visible al crear) -->
                        <div id="bloquePertenenciaInicial" class="col-12 mt-2 pt-2 border-top">
                            <div class="alert alert-info py-1 px-2 mb-2 fs-7">
                                <i class="fas fa-info-circle me-1"></i> Pertenencia inicial en el árbol territorial (opcional si es nodo raíz)
                            </div>
                            <div class="row g-2">
                                <div class="col-md-7">
                                    <label for="territorio_superior_id" class="form-label form-label-sm">Territorio Superior</label>
                                    <select class="form-select form-select-sm" id="territorio_superior_id" name="territorio_superior_id">
                                        <option value="">-- Ninguno (Nodo Raíz / Mundo) --</option>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <label for="vigente_desde" class="form-label form-label-sm">Vigente Desde</label>
                                    <input type="date" class="form-control form-control-sm" id="vigente_desde" name="vigente_desde" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary" id="btnGuardarTerritorio">
                        <i class="fas fa-save me-1"></i> Guardar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: Cambiar Pertenencia (Caso C13) -->
<div class="modal fade" id="modalCambiarPertenencia" tabindex="-1" aria-labelledby="modalCPLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="formCambiarPertenencia" novalidate>
                <input type="hidden" id="cp_territorio_id" name="territorio_id" value="0">
                <div class="modal-header bg-warning text-dark py-2">
                    <h5 class="modal-title fs-6" id="modalCPLabel">
                        <i class="fas fa-sitemap me-1"></i> Cambiar Pertenencia Territorial (Caso C13)
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="card card-body bg-light py-2 mb-3">
                        <div class="row">
                            <div class="col-md-6">
                                <small class="text-muted d-block">Territorio:</small>
                                <strong id="cp_nombre_territorio" class="fs-6">-</strong>
                            </div>
                            <div class="col-md-6">
                                <small class="text-muted d-block">Pertenencia Principal Actual:</small>
                                <span id="cp_superior_actual" class="badge bg-secondary">-</span>
                            </div>
                        </div>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label for="cp_nuevo_superior_id" class="form-label form-label-sm">Nuevo Territorio Superior <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="cp_nuevo_superior_id" name="nuevo_superior_id" required>
                                <option value="">-- Seleccionar --</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label for="cp_fecha_cambio" class="form-label form-label-sm">Fecha Efectiva <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="cp_fecha_cambio" name="fecha_cambio" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                        <div class="col-md-3 d-flex align-items-center mt-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="cp_es_principal" name="es_principal" value="1" checked>
                                <label class="form-check-label fs-7" for="cp_es_principal">
                                    Pertenencia Principal
                                </label>
                            </div>
                        </div>
                    </div>

                    <h6 class="fs-7 text-muted border-bottom pb-1 mb-2">Historial de Pertenencias del Territorio</h6>
                    <div class="table-responsive">
                        <table class="table table-xs table-bordered mb-0" id="tablaHistorialPertenencias">
                            <thead class="table-light">
                                <tr>
                                    <th>Territorio Superior</th>
                                    <th class="text-center" style="width: 100px;">Principal</th>
                                    <th style="width: 120px;">Vigente Desde</th>
                                    <th style="width: 120px;">Vigente Hasta</th>
                                    <th class="text-center" style="width: 90px;">Estado</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="submit" class="btn btn-sm btn-warning" id="btnGuardarCambioPertenencia">
                        <i class="fas fa-check-circle me-1"></i> Aplicar Cambio Histórico
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    window.EMPRESA_ID = <?= (int)($current_empresa_id ?? $_SESSION['empresa_id'] ?? 2) ?>;
</script>
<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="territorios.js?v=<?= time() ?>"></script>

<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
