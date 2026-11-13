<?php
/**
 * Vista: Plan de Cuentas Jerárquico
 * Módulo 4: Estudio Contable (contabilidad_plan.php)
 * Página ID: 416
 */

$pageTitle = "Plan de Cuentas";
$currentPage = 'contabilidad_plan';
$modudo_idx = 4;
$pagina_idx = 416;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$empresa_id = (int) $current_empresa_id; // empresa validada por header1.php, sin default

require_once __DIR__ . '/../../db.php';
$empresas_res = mysqli_query($conn, "SELECT empresa_id, empresa, documento_numero FROM conf__empresas WHERE tabla_estado_registro_id = 1 ORDER BY empresa ASC");
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 fw-bold">
                        <i class="fas fa-sitemap me-2 text-primary"></i>Plan de Cuentas Contable
                    </h3>
                    <small class="text-muted">Estructura jerárquica de cuentas imputables y sintéticas por empresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="dashboard_estudio.php">Estudio Contable</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Plan de Cuentas</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Barra Superior -->
            <div class="card card-outline card-primary shadow-sm mb-3">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <label class="form-label form-label-sm mb-1 fw-bold">
                                <i class="fas fa-building me-1 text-primary"></i>Empresa
                            </label>
                            <select id="selEmpresaPlan" class="form-select form-select-sm">
                                <?php while ($e = mysqli_fetch_assoc($empresas_res)): ?>
                                    <option value="<?= $e['empresa_id'] ?>" <?= ($e['empresa_id'] == $empresa_id) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($e['empresa']) ?> (CUIT: <?= $e['documento_numero'] ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-7 text-md-end mt-auto pb-1">
                            <button class="btn btn-primary btn-sm shadow-sm" id="btnNuevaCuenta" data-funcion="agregar">
                                <i class="fas fa-plus me-1"></i>+ Nueva Cuenta Contable
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Cuentas -->
            <div class="card card-outline card-secondary shadow-sm mb-4">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-book me-1 text-primary"></i>Cuentas del Plan
                    </h6>
                    <div class="btn-group" role="group" aria-label="Exportar Plan">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnPlanRecargar" title="Recargar">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnPlanCopiar" title="Copiar">
                            <i class="fas fa-copy"></i>
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm" id="btnPlanExcel" title="Excel">
                            <i class="fas fa-file-excel"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" id="btnPlanPdf" title="PDF">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnPlanCsv" title="CSV">
                            <i class="fas fa-file-csv"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnPlanImprimir" title="Imprimir">
                            <i class="fas fa-print"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="tablaPlan" class="table table-bordered table-striped table-hover align-middle w-100">
                            <thead class="table-light">
                                <tr>
                                    <th>Código</th>
                                    <th>Nombre de la Cuenta</th>
                                    <th>Naturaleza</th>
                                    <th>Nivel</th>
                                    <th>Cuenta Padre</th>
                                    <th class="text-center">Imputable</th>
                                    <th class="text-center">Acciones</th>
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

<!-- Modal Cuenta -->
<div class="modal fade" id="modalCuenta" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title fw-bold" id="modalCuentaLabel">
                    <i class="fas fa-book me-1"></i>Nueva Cuenta Contable
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formCuenta">
                <input type="hidden" id="cta_id" name="cont_cuenta_id" value="0">
                <input type="hidden" id="cta_empresa_id" name="empresa_id" value="<?= $empresa_id ?>">

                <div class="modal-body p-3">
                    <div class="row g-2 mb-2">
                        <div class="col-md-5">
                            <label class="form-label form-label-sm mb-1 fw-bold">Código <span class="text-danger">*</span></label>
                            <input type="text" id="cta_codigo" name="codigo" class="form-control form-control-sm" required placeholder="Ej: 1.1.01.01">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label form-label-sm mb-1 fw-bold">Naturaleza</label>
                            <select id="cta_naturaleza" name="naturaleza" class="form-select form-select-sm">
                                <option value="ACTIVO">Activo</option>
                                <option value="PASIVO">Pasivo</option>
                                <option value="PATRIMONIO_NETO">Patrimonio Neto</option>
                                <option value="RESULTADO_POSITIVO">Resultado Positivo (Ingreso)</option>
                                <option value="RESULTADO_NEGATIVO">Resultado Negativo (Gasto)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label form-label-sm mb-1 fw-bold">Nombre de la Cuenta <span class="text-danger">*</span></label>
                        <input type="text" id="cta_nombre" name="nombre" class="form-control form-control-sm" required placeholder="Nombre descriptivo de la cuenta">
                    </div>

                    <div class="mb-2">
                        <label class="form-label form-label-sm mb-1 fw-bold">Cuenta Padre (Sintética)</label>
                        <select id="cta_padre" name="cuenta_padre_id" class="form-select form-select-sm">
                            <option value="0">-- Sin Cuenta Padre (Raíz) --</option>
                        </select>
                    </div>

                    <div class="mb-0">
                        <label class="form-label form-label-sm mb-1 fw-bold">Tipo de Imputación</label>
                        <select id="cta_imputable" name="es_imputable" class="form-select form-select-sm">
                            <option value="1">Imputable (Registra movimientos directos)</option>
                            <option value="0">Sintética / Totalizadora (Solo agrupa)</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm shadow-sm" id="btnGuardarCuenta">
                        <i class="fas fa-save me-1"></i>Guardar Cuenta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="contabilidad_plan.js"></script>
<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
