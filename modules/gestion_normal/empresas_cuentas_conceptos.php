<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Conceptos Contables de Empresa";
$currentPage = 'empresas_cuentas_conceptos';
$modudo_idx = 5; // Typo intencional y consistente con todo el ERP (ver CLAUDE.md)
$pagina_idx = 601;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-tags text-primary me-2"></i>Conceptos Contables por Empresa
                    </h3>
                    <small class="text-muted">Resolución de Cuentas Contables por Defecto para Tesorería (D35 / D36)</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                        <li class="breadcrumb-item"><a href="#">Contabilidad</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Conceptos Contables</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Nota informativa de arquitectura D35/D36 -->
            <div class="alert alert-info border-start border-4 border-info shadow-sm mb-4">
                <div class="d-flex align-items-center">
                    <i class="fas fa-info-circle fa-2x me-3 text-info"></i>
                    <div>
                        <h6 class="fw-bold mb-1">Mapeo de Contrapartidas Automáticas de Tesorería</h6>
                        <p class="mb-0 small text-dark">
                            Los procesos de Tesorería (emisión de cheques propios diferidos, anticipos al personal, liquidación de gastos)
                            resuelven sus cuentas contables en cascada: primero buscan la cuenta específica en la ficha operativa;
                            si no existe, toman el concepto asignado aquí para la empresa activa. Si ningún concepto tiene cuenta asignada,
                            la confirmación se bloquea de forma preventiva.
                        </p>
                    </div>
                </div>
            </div>

            <div class="card card-outline card-primary shadow-sm">
                <div class="card-header bg-light py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0 fw-bold">
                            <i class="fas fa-list me-2 text-secondary"></i>Catálogo de Conceptos de la Empresa
                        </h5>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Recargar tabla">
                                <i class="fas fa-sync-alt me-1"></i> Actualizar
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tablaConceptos" class="table table-hover table-striped align-middle w-100">
                            <thead class="table-light text-uppercase fs-7">
                                <tr>
                                    <th style="width: 140px;">Código Concepto</th>
                                    <th style="width: 260px;">Concepto / Propósito</th>
                                    <th>Regla Funcional de Imputación</th>
                                    <th style="width: 320px;">Cuenta Contable Asignada</th>
                                    <th style="width: 80px;" class="text-center">Estado</th>
                                    <th style="width: 130px;" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- DataTables vía AJAX -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Modal Asignar / Modificar Cuenta Contable -->
<div class="modal fade" id="modalAsignarCuenta" tabindex="-1" aria-labelledby="modalAsignarCuentaLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title" id="modalAsignarCuentaLabel">
                    <i class="fas fa-link me-2"></i>Asignar Cuenta Contable al Concepto
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formAsignarCuenta">
                <div class="modal-body p-4">
                    <input type="hidden" id="concepto_id" name="id" value="">

                    <div class="mb-3">
                        <label class="form-label text-muted small text-uppercase fw-bold">Concepto de Sistema</label>
                        <div class="p-3 bg-light rounded border">
                            <div class="fw-bold text-dark fs-5" id="lblConceptoTitulo"></div>
                            <code class="text-primary fw-bold" id="lblConceptoCodigo"></code>
                            <div class="text-muted small mt-2" id="lblConceptoDescripcion"></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="selectCuentaContable" class="form-label fw-bold">
                            Cuenta Contable Imputable <span class="text-danger">*</span>
                        </label>
                        <select class="form-select select2" id="selectCuentaContable" name="cont_cuenta_id" required>
                            <option value="">-- Seleccionar Cuenta Contable --</option>
                        </select>
                        <div class="form-text small">
                            Solo se muestran cuentas imputables y activas del plan de cuentas de la empresa.
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i> Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarCuenta">
                        <i class="fas fa-save me-1"></i> Guardar Asignación
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Scripts específicos del módulo -->
<script src="empresas_cuentas_conceptos.js"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
