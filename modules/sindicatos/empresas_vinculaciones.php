<?php
/**
 * Vista: ABM de Vinculaciones y Permisos Inter-Empresas
 * Módulo: Sindicatos - Configuración Sindical (padre_id = 1009, orden = 403)
 * Página ID: 1013
 */

$pageTitle = "Vinculaciones y Permisos Inter-Empresas";
$currentPage = 'empresas_vinculaciones';
$modudo_idx = 6;
$pagina_idx = 1013;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

// Empresa activa: la resuelve y valida header1.php (GET empresa_id -> sesión).
$empresa_id_activa = (int) ($current_empresa_id ?? 0);

// El selector de empresa activa solo ofrece las empresas a las que el usuario tiene acceso
// ($empresas_modulos y $es_superusuario vienen de header1.php).
$empresasAccesibles = [];
$resEmp = $conn->query("SELECT empresa_id, empresa, documento_numero FROM conf__empresas WHERE tabla_estado_registro_id = 1 ORDER BY empresa ASC");
if ($resEmp) {
    while ($rEmp = $resEmp->fetch_assoc()) {
        if ($es_superusuario || isset($empresas_modulos[(int) $rEmp['empresa_id']])) {
            $empresasAccesibles[] = $rEmp;
        }
    }
}
?>

<main class="app-main">
    <!-- Header -->
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-7">
                    <h3 class="mb-0 fw-bold">
                        <i class="fas fa-handshake me-2 text-primary"></i>Vinculaciones y Permisos Inter-Empresas
                    </h3>
                    <small class="text-muted">Concede o gestiona accesos autorizados a Estudios Contables y otras entidades para auditar o registrar información gremial y contable</small>
                </div>
                <div class="col-sm-5">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Sindicatos</a></li>
                        <li class="breadcrumb-item"><a href="#">Configuración Sindical</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Vinculaciones</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenido -->
    <div class="app-content">
        <div class="container-fluid">
            
            <!-- Barra superior: Selector de Empresa y Acciones -->
            <div class="card card-outline card-primary mb-3 shadow-sm">
                <div class="card-body py-2">
                    <div class="row align-items-center g-2">
                        <div class="col-auto">
                            <label for="selectEmpresaFiltro" class="col-form-label fw-bold">
                                <i class="fas fa-building me-1 text-primary"></i>Entidad Activa:
                            </label>
                        </div>
                        <div class="col-md-4 col-sm-6">
                            <select class="form-select form-select-sm" id="selectEmpresaFiltro">
                                <?php foreach ($empresasAccesibles as $emp): ?>
                                    <option value="<?= (int) $emp['empresa_id'] ?>" <?= ((int) $emp['empresa_id'] === $empresa_id_activa) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($emp['empresa']) ?><?= $emp['documento_numero'] ? ' (CUIT: ' . htmlspecialchars($emp['documento_numero']) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-auto ms-auto d-flex gap-2">
                            <a href="estudios.php" class="btn btn-sm btn-outline-info" title="Ver Estudios Contables autorizados en el sindicato">
                                <i class="fas fa-briefcase me-1"></i>Padrón de Estudios
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Actualizar datos">
                                <i class="fas fa-sync-alt me-1"></i>Actualizar
                            </button>
                            <button type="button" class="btn btn-sm btn-primary" id="btnNuevaVinculacion">
                                <i class="fas fa-plus-circle me-1"></i>Otorgar Nueva Vinculación
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tarjetas de Resumen / KPIs -->
            <div class="row g-3 mb-3">
                <div class="col-md-3 col-sm-6">
                    <div class="card bg-primary-subtle border-0 shadow-sm">
                        <div class="card-body p-3 d-flex align-items-center">
                            <div class="rounded-circle bg-primary text-white p-3 me-3">
                                <i class="fas fa-shield-alt fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="card-subtitle text-muted mb-1 small">Vinculaciones Otorgadas</h6>
                                <h3 class="card-title fw-bold mb-0 text-primary" id="kpiOtorgadasActivas">0</h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card bg-info-subtle border-0 shadow-sm">
                        <div class="card-body p-3 d-flex align-items-center">
                            <div class="rounded-circle bg-info text-white p-3 me-3">
                                <i class="fas fa-calculator fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="card-subtitle text-muted mb-1 small">Estudios Contables</h6>
                                <h3 class="card-title fw-bold mb-0 text-info" id="kpiEstudiosContables">0</h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card bg-success-subtle border-0 shadow-sm">
                        <div class="card-body p-3 d-flex align-items-center">
                            <div class="rounded-circle bg-success text-white p-3 me-3">
                                <i class="fas fa-edit fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="card-subtitle text-muted mb-1 small">Con Permiso de Escritura</h6>
                                <h3 class="card-title fw-bold mb-0 text-success" id="kpiModoEscritura">0</h3>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-sm-6">
                    <div class="card bg-warning-subtle border-0 shadow-sm">
                        <div class="card-body p-3 d-flex align-items-center">
                            <div class="rounded-circle bg-warning text-white p-3 me-3">
                                <i class="fas fa-users-cog fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="card-subtitle text-muted mb-1 small">Vinculaciones Recibidas</h6>
                                <h3 class="card-title fw-bold mb-0 text-warning-emphasis" id="kpiRecibidasActivas">0</h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestañas de Vinculaciones -->
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white p-2">
                    <ul class="nav nav-tabs card-header-tabs" id="tabsVinculaciones" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-bold" id="tab-otorgadas" data-bs-toggle="tab" data-bs-target="#pane-otorgadas" type="button" role="tab">
                                <i class="fas fa-key me-1 text-primary"></i>Vinculaciones Otorgadas
                                <span class="badge bg-primary ms-1" id="badgeTotalOtorgadas">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold" id="tab-recibidas" data-bs-toggle="tab" data-bs-target="#pane-recibidas" type="button" role="tab">
                                <i class="fas fa-inbox me-1 text-secondary"></i>Vinculaciones Recibidas (Entidades/Empleadores)
                                <span class="badge bg-secondary ms-1" id="badgeTotalRecibidas">0</span>
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-3">
                    <div class="tab-content" id="tabContentVinculaciones">
                        
                        <!-- TAB 1: OTORGADAS -->
                        <div class="tab-pane fade show active" id="pane-otorgadas" role="tabpanel">
                            <div class="table-responsive">
                                <table id="tablaOtorgadas" class="table table-hover table-bordered table-striped align-middle" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Empresa / Estudio Destino</th>
                                            <th>Tipo Vínculo</th>
                                            <th>Modo de Acceso</th>
                                            <th>Áreas Autorizadas</th>
                                            <th>Vigencia</th>
                                            <th>Estado</th>
                                            <th class="text-end">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB 2: RECIBIDAS -->
                        <div class="tab-pane fade" id="pane-recibidas" role="tabpanel">
                            <div class="table-responsive">
                                <table id="tablaRecibidas" class="table table-hover table-bordered table-striped align-middle" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Entidad Origen</th>
                                            <th>Tipo Vínculo</th>
                                            <th>Modo de Acceso</th>
                                            <th>Áreas Habilitadas</th>
                                            <th>Vigencia</th>
                                            <th>Estado</th>
                                            <th>Contacto</th>
                                        </tr>
                                    </thead>
                                    <tbody></tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
</main>

<!-- Modal: Alta / Edición de Vinculación -->
<div class="modal fade" id="modalVinculacion" tabindex="-1" aria-labelledby="modalVinculacionLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="formVinculacion" novalidate>
                <div class="modal-header bg-primary text-white py-3">
                    <h5 class="modal-title fw-bold" id="modalVinculacionLabel">
                        <i class="fas fa-handshake me-2"></i>Nueva Vinculación
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <input type="hidden" id="vinculacion_id" name="vinculacion_id" value="0">
                    <input type="hidden" id="form_empresa_origen_id" name="empresa_origen_id" value="<?= $empresa_id_activa ?>">

                    <!-- Empresa Destino y Tipo de Vínculo -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-7">
                            <label for="empresa_destino_id" class="form-label fw-bold">
                                <i class="fas fa-building text-primary me-1"></i>Empresa o Estudio Receptora <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="empresa_destino_id" name="empresa_destino_id" required>
                                <option value="">-- Seleccionar Entidad Externa --</option>
                            </select>
                            <div class="invalid-feedback">Por favor seleccione la entidad receptora.</div>
                        </div>
                        <div class="col-md-5">
                            <label for="tipo_vinculo" class="form-label fw-bold">
                                <i class="fas fa-tag text-primary me-1"></i>Tipo de Relación <span class="text-danger">*</span>
                            </label>
                            <select class="form-select" id="tipo_vinculo" name="tipo_vinculo" required>
                                <option value="ESTUDIO_CONTABLE">Estudio Contable</option>
                                <option value="AUDITORIA">Auditoría Externa</option>
                                <option value="EMPRESA_VINCULADA">Empresa Vinculada / Holding</option>
                                <option value="SINDICATO">Sindicato / Gremio</option>
                                <option value="OTRO">Otro</option>
                            </select>
                        </div>
                    </div>

                    <!-- Modo de Acceso (Lectura vs Escritura) -->
                    <div class="card bg-light border-0 mb-3">
                        <div class="card-body p-3">
                            <label class="form-label fw-bold mb-2">
                                <i class="fas fa-lock-open text-primary me-1"></i>Modo de Acceso Concedido <span class="text-danger">*</span>
                            </label>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="form-check p-3 bg-white border rounded">
                                        <input class="form-check-input" type="radio" name="modo_acceso" id="modoLectura" value="LECTURA" checked>
                                        <label class="form-check-label fw-bold" for="modoLectura">
                                            <i class="fas fa-eye text-primary me-1"></i>Solo Lectura (Auditoría)
                                        </label>
                                        <p class="text-muted small mb-0 mt-1">La entidad externa solo puede consultar declaraciones juradas, comprobantes y reportes.</p>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check p-3 bg-white border rounded">
                                        <input class="form-check-input" type="radio" name="modo_acceso" id="modoEscritura" value="LECTURA_ESCRITURA">
                                        <label class="form-check-label fw-bold text-success" for="modoEscritura">
                                            <i class="fas fa-edit me-1"></i>Lectura y Escritura
                                        </label>
                                        <p class="text-muted small mb-0 mt-1">Permite al estudio o entidad externa cargar DDJJ, generar asientos y liquidaciones impositivas/sindicales.</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Alcance / Módulos Autorizados -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-bold mb-0">
                                <i class="fas fa-th-list text-primary me-1"></i>Áreas y Módulos Autorizados
                            </label>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-primary btn-sm" id="btnMarcarTodos">Marcar Todos</button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" id="btnDesmarcarTodos">Desmarcar</button>
                            </div>
                        </div>

                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="form-check form-switch p-2 bg-light rounded border">
                                    <input class="form-check-input ms-0 me-2 chk-permiso" type="checkbox" id="permiso_sindicatos" name="permiso_sindicatos" value="1" checked>
                                    <label class="form-check-label fw-semibold" for="permiso_sindicatos">
                                        <i class="fas fa-users text-primary me-1"></i>Nóminas y Aportes Sindicales
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch p-2 bg-light rounded border">
                                    <input class="form-check-input ms-0 me-2 chk-permiso" type="checkbox" id="permiso_impuestos" name="permiso_impuestos" value="1" checked>
                                    <label class="form-check-label fw-semibold" for="permiso_impuestos">
                                        <i class="fas fa-percentage text-danger me-1"></i>Impuestos y DDJJ Fiscales
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-check form-switch p-2 bg-light rounded border">
                                    <input class="form-check-input ms-0 me-2 chk-permiso" type="checkbox" id="permiso_ventas" name="permiso_ventas" value="1" checked>
                                    <label class="form-check-label fw-semibold" for="permiso_ventas">
                                        <i class="fas fa-file-invoice text-info me-1"></i>Ventas y Facturas
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-check form-switch p-2 bg-light rounded border">
                                    <input class="form-check-input ms-0 me-2 chk-permiso" type="checkbox" id="permiso_compras" name="permiso_compras" value="1" checked>
                                    <label class="form-check-label fw-semibold" for="permiso_compras">
                                        <i class="fas fa-shopping-cart text-warning me-1"></i>Compras y Gastos
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-4 col-sm-6">
                                <div class="form-check form-switch p-2 bg-light rounded border">
                                    <input class="form-check-input ms-0 me-2 chk-permiso" type="checkbox" id="permiso_tesoreria" name="permiso_tesoreria" value="1" checked>
                                    <label class="form-check-label fw-semibold" for="permiso_tesoreria">
                                        <i class="fas fa-cash-register text-success me-1"></i>Tesorería y Bancos
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch p-2 bg-light rounded border">
                                    <input class="form-check-input ms-0 me-2 chk-permiso" type="checkbox" id="permiso_contabilidad" name="permiso_contabilidad" value="1" checked>
                                    <label class="form-check-label fw-semibold" for="permiso_contabilidad">
                                        <i class="fas fa-book text-warning-emphasis me-1"></i>Contabilidad y Balances
                                    </label>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-check form-switch p-2 bg-light rounded border">
                                    <input class="form-check-input ms-0 me-2 chk-permiso" type="checkbox" id="permiso_stock" name="permiso_stock" value="1">
                                    <label class="form-check-label fw-semibold" for="permiso_stock">
                                        <i class="fas fa-boxes text-secondary me-1"></i>Stock e Inventario
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vigencia y Estado -->
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label for="fecha_desde" class="form-label fw-bold">Fecha Desde <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="fecha_desde" name="fecha_desde" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="fecha_hasta" class="form-label fw-bold">Fecha Hasta</label>
                            <input type="date" class="form-control" id="fecha_hasta" name="fecha_hasta" placeholder="Sin límite">
                            <small class="text-muted">Dejar vacío para vigencia permanente.</small>
                        </div>
                        <div class="col-md-4">
                            <label for="tabla_estado_registro_id" class="form-label fw-bold">Estado</label>
                            <select class="form-select" id="tabla_estado_registro_id" name="tabla_estado_registro_id">
                                <option value="1">Activa / Autorizada</option>
                                <option value="2">Inactiva / Revocada</option>
                            </select>
                        </div>
                    </div>

                    <!-- Observaciones -->
                    <div class="mb-2">
                        <label for="observaciones" class="form-label fw-bold">Observaciones / Motivo</label>
                        <input type="text" class="form-control" id="observaciones" name="observaciones" placeholder="Ej: Autorización para presentación de DDJJ y liquidaciones gremiales/contables">
                    </div>

                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarVinculacion">
                        <i class="fas fa-save me-1"></i>Guardar Vinculación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
<script src="empresas_vinculaciones.js"></script>
