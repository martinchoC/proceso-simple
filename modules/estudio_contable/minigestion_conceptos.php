<?php
/**
 * Vista: Catálogo de Conceptos sin Stock (Bienes vs Servicios)
 * Módulo 4: Estudio Contable (minigestion_conceptos.php)
 * Página ID: 414
 */

$pageTitle = "Conceptos de Mini-Gestión";
$currentPage = 'minigestion_conceptos';
$modudo_idx = 4;
$pagina_idx = 414;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$empresa_id = intval($_SESSION['empresa_id'] ?? 2);

require_once __DIR__ . '/../../db.php';
$empresas_res = mysqli_query($conn, "SELECT empresa_id, empresa, documento_numero FROM conf__empresas WHERE tabla_estado_registro_id = 1 ORDER BY empresa ASC");
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 fw-bold">
                        <i class="fas fa-boxes me-2 text-primary"></i>Catálogo de Conceptos (Sin Stock)
                    </h3>
                    <small class="text-muted">Bienes y servicios para imputación contable automática en compras y ventas de mini-gestión</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="dashboard_estudio.php">Estudio Contable</a></li>
                        <li class="breadcrumb-item"><a href="minigestion.php">Mini-Gestión</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Conceptos</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Barra de Selección de Empresa y Alta -->
            <div class="card card-outline card-primary shadow-sm mb-3">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-5">
                            <label class="form-label form-label-sm mb-1 fw-bold">
                                <i class="fas fa-building me-1 text-primary"></i>Empresa
                            </label>
                            <select id="selEmpresaConcepto" class="form-select form-select-sm">
                                <?php while ($e = mysqli_fetch_assoc($empresas_res)): ?>
                                    <option value="<?= $e['empresa_id'] ?>" <?= ($e['empresa_id'] == $empresa_id) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($e['empresa']) ?> (CUIT: <?= $e['documento_numero'] ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-7 text-md-end mt-auto pb-1">
                            <button class="btn btn-primary btn-sm shadow-sm" id="btnNuevoConcepto">
                                <i class="fas fa-plus me-1"></i>+ Nuevo Concepto
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Conceptos -->
            <div class="card card-outline card-secondary shadow-sm mb-4">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-list me-1 text-primary"></i>Conceptos Registrados
                    </h6>
                    <div class="btn-group" role="group" aria-label="Exportar Conceptos">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnConceptosRecargar" title="Recargar">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnConceptosCopiar" title="Copiar">
                            <i class="fas fa-copy"></i>
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm" id="btnConceptosExcel" title="Excel">
                            <i class="fas fa-file-excel"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" id="btnConceptosPdf" title="PDF">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnConceptosCsv" title="CSV">
                            <i class="fas fa-file-csv"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnConceptosImprimir" title="Imprimir">
                            <i class="fas fa-print"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="tablaConceptos" class="table table-bordered table-striped table-hover align-middle w-100">
                            <thead class="table-light">
                                <tr>
                                    <th>Código</th>
                                    <th>Descripción del Concepto</th>
                                    <th>Tipo</th>
                                    <th>Cuenta Ingreso (Venta)</th>
                                    <th>Cuenta Gasto (Compra)</th>
                                    <th class="text-center">Controla Stock</th>
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

<!-- Modal Concepto -->
<div class="modal fade" id="modalConcepto" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white py-2">
                <h5 class="modal-title fw-bold" id="modalConceptoLabel">
                    <i class="fas fa-box me-1"></i>Nuevo Concepto
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="formConcepto">
                <input type="hidden" id="con_id" name="producto_id" value="0">
                <input type="hidden" id="con_empresa_id" name="empresa_id" value="<?= $empresa_id ?>">

                <div class="modal-body p-3">
                    <div class="row g-2 mb-2">
                        <div class="col-md-5">
                            <label class="form-label form-label-sm mb-1 fw-bold">Código <span class="text-danger">*</span></label>
                            <input type="text" id="con_codigo" name="producto_codigo" class="form-control form-control-sm" required placeholder="Ej: SERV-01">
                        </div>
                        <div class="col-md-7">
                            <label class="form-label form-label-sm mb-1 fw-bold">Tipo de Concepto</label>
                            <select id="con_es_servicio" name="es_servicio" class="form-select form-select-sm">
                                <option value="1">Servicio (Sin stock)</option>
                                <option value="0">Bien / Artículo (Sin control de almacén)</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label form-label-sm mb-1 fw-bold">Nombre / Descripción <span class="text-danger">*</span></label>
                        <input type="text" id="con_nombre" name="producto_nombre" class="form-control form-control-sm" required placeholder="Descripción del concepto">
                    </div>

                    <div class="mb-2">
                        <label class="form-label form-label-sm mb-1 fw-bold">Alícuota IVA por Defecto</label>
                        <select id="con_alicuota" name="iva_alicuota_id" class="form-select form-select-sm">
                            <option value="1">21,00 %</option>
                            <option value="2">10,50 %</option>
                            <option value="3">27,00 %</option>
                            <option value="4">0,00 % (Exento)</option>
                        </select>
                    </div>

                    <div class="p-2 border rounded bg-light mb-2">
                        <div class="mb-2">
                            <label class="form-label form-label-sm mb-1 fw-bold text-primary">Cuenta Contable de Ventas / Ingresos</label>
                            <select id="con_cuenta_venta" name="cont_cuenta_venta_id" class="form-select form-select-sm select-cuenta-imputable">
                                <option value="0">-- Seleccionar Cuenta de Ingreso --</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label form-label-sm mb-1 fw-bold text-success">Cuenta Contable de Compras / Gastos</label>
                            <select id="con_cuenta_compra" name="cont_cuenta_compra_id" class="form-select form-select-sm select-cuenta-imputable">
                                <option value="0">-- Seleccionar Cuenta de Gasto --</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm shadow-sm" id="btnGuardarConcepto">
                        <i class="fas fa-save me-1"></i>Guardar Concepto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="minigestion_conceptos.js"></script>
<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
