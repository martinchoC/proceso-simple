<?php
/**
 * Vista: Cierre de Ejercicio y Refundición Contable
 * Módulo 4: Estudio Contable (contabilidad_cierre.php)
 * Página ID: 413
 */

$pageTitle = "Cierre de Ejercicio Contable";
$currentPage = 'contabilidad_cierre';
$modudo_idx = 4;
$pagina_idx = 413;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$empresa_id = (int) $current_empresa_id; // empresa validada por header1.php, sin default

require_once __DIR__ . '/../../db.php';
$empresas_res = mysqli_query($conn, "SELECT empresa_id, empresa, documento_numero FROM conf__empresas WHERE tabla_estado_registro_id = 1 ORDER BY empresa ASC");

// Cliente activo del estudio (?cliente_id= o el último elegido): preselecciona su empresa en el ERP. Cada empresa lleva
// el cliente que la tiene vinculada (data-cliente) para que al cambiar de empresa quede como cliente activo.
require_once __DIR__ . '/estudio_cliente_activo.php';
$cliente_activo = intval($_GET['cliente_id'] ?? 0) ?: estudio_cliente_activo_get($empresa_id);
$cliente_por_empresa = [];
$empresa_sel = $empresa_id;
$st = mysqli_prepare($conn, "SELECT cliente_id, empresa_vinculada_id FROM estudio__clientes
                             WHERE empresa_id = ? AND tabla_estado_registro_id = 1 AND empresa_vinculada_id > 0
                             ORDER BY cliente_id");
mysqli_stmt_bind_param($st, "i", $empresa_id);
mysqli_stmt_execute($st);
foreach (mysqli_fetch_all(mysqli_stmt_get_result($st), MYSQLI_ASSOC) as $cl) {
    $erp = (int) $cl['empresa_vinculada_id'];
    $cliente_por_empresa[$erp] = $cliente_por_empresa[$erp] ?? (int) $cl['cliente_id'];
    if ((int) $cl['cliente_id'] === $cliente_activo) {
        $empresa_sel = $erp;
        $cliente_por_empresa[$erp] = $cliente_activo;
    }
}
mysqli_stmt_close($st);
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 fw-bold">
                        <i class="fas fa-door-closed me-2 text-primary"></i>Cierre de Ejercicio Anual
                    </h3>
                    <small class="text-muted">Proceso de refundición automática de cuentas de resultado y asiento de cierre patrimonial</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="dashboard_estudio.php">Estudio Contable</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Cierre de Ejercicio</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Barra de Selección y Controles -->
            <div class="card card-outline card-primary shadow-sm mb-3">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-4">
                            <label class="form-label form-label-sm mb-1 fw-bold">
                                <i class="fas fa-building me-1 text-primary"></i>Empresa
                            </label>
                            <select id="selEmpresaCierre" class="form-select form-select-sm">
                                <?php while ($e = mysqli_fetch_assoc($empresas_res)): ?>
                                    <option value="<?= $e['empresa_id'] ?>" data-cliente="<?= (int) ($cliente_por_empresa[(int) $e['empresa_id']] ?? 0) ?>" <?= ($e['empresa_id'] == $empresa_sel) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($e['empresa']) ?> (CUIT: <?= $e['documento_numero'] ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm mb-1 fw-bold" title="El ejercicio se nombra por el año en que termina; el mes de cierre sale de Parámetros de Empresa">Ejercicio a Cerrar</label>
                            <input type="number" id="txtAnioCierre" class="form-control form-control-sm text-center fw-bold" value="<?= date('Y') - 1 ?>" min="2000" max="2099">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm mb-1 fw-bold">Fecha Inicio</label>
                            <input type="date" id="txtFechaInicio" class="form-control form-control-sm" readonly title="Sale del mes de cierre del ejercicio (Parámetros de Empresa)">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label form-label-sm mb-1 fw-bold">Fecha Cierre</label>
                            <input type="date" id="txtFechaCierre" class="form-control form-control-sm" readonly title="Sale del mes de cierre del ejercicio (Parámetros de Empresa)">
                        </div>
                        <div class="col-md-2 text-md-end mt-auto pb-1">
                            <button class="btn btn-outline-primary btn-sm w-100 shadow-sm" id="btnDiagnosticar">
                                <i class="fas fa-search me-1"></i>Pre-Balance
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel de Diagnóstico Pre-Cierre -->
            <div class="card border-0 shadow-sm mb-3" id="panelPreCierre">
                <div class="card-header bg-white py-2 border-bottom d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-balance-scale me-1 text-primary"></i>Diagnóstico de Sumas y Saldos (Pre-Cierre)
                    </h6>
                    <span id="badgeEstadoCuadre" class="badge bg-secondary">Sin evaluar</span>
                </div>
                <div class="card-body p-3">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="p-2 border rounded bg-light text-center">
                                <small class="text-muted d-block fw-semibold text-uppercase">Total Sumas Debe</small>
                                <h5 class="mb-0 fw-bold text-dark" id="valTotalDebe">$ 0,00</h5>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-2 border rounded bg-light text-center">
                                <small class="text-muted d-block fw-semibold text-uppercase">Total Sumas Haber</small>
                                <h5 class="mb-0 fw-bold text-dark" id="valTotalHaber">$ 0,00</h5>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-2 border rounded bg-light text-center">
                                <small class="text-muted d-block fw-semibold text-uppercase">Diferencia Cuadre</small>
                                <h5 class="mb-0 fw-bold" id="valDiferencia">$ 0,00</h5>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="p-2 border rounded bg-light text-center">
                                <small class="text-muted d-block fw-semibold text-uppercase">Resultado Neto Proyectado</small>
                                <h5 class="mb-0 fw-bold text-primary" id="valResultadoNeto">$ 0,00</h5>
                            </div>
                        </div>
                    </div>

                    <div class="text-end mt-3">
                        <button class="btn btn-danger btn-sm px-4 shadow-sm" id="btnEjecutarCierre" data-funcion="agregar" disabled>
                            <i class="fas fa-lock me-1"></i>Ejecutar Cierre y Refundición Contable
                        </button>
                    </div>
                </div>
            </div>

            <!-- Historial de Cierres Anuales -->
            <div class="card card-outline card-secondary shadow-sm mb-4">
                <div class="card-header bg-white py-2 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-history me-1 text-primary"></i>Historial de Cierres Anuales Registrados
                    </h6>
                    <div class="btn-group" role="group" aria-label="Exportar Cierres">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCierresRecargar" title="Recargar">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCierresCopiar" title="Copiar">
                            <i class="fas fa-copy"></i>
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm" id="btnCierresExcel" title="Excel">
                            <i class="fas fa-file-excel"></i>
                        </button>
                        <button type="button" class="btn btn-outline-danger btn-sm" id="btnCierresPdf" title="PDF">
                            <i class="fas fa-file-pdf"></i>
                        </button>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnCierresCsv" title="CSV">
                            <i class="fas fa-file-csv"></i>
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnCierresImprimir" title="Imprimir">
                            <i class="fas fa-print"></i>
                        </button>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="tablaCierres" class="table table-bordered table-striped table-hover align-middle w-100">
                            <thead class="table-light">
                                <tr>
                                    <th>Año Ejercicio</th>
                                    <th>Fecha Inicio</th>
                                    <th>Fecha Cierre</th>
                                    <th class="text-end">Resultado Neto</th>
                                    <th>Asiento Refundición</th>
                                    <th>Asiento Patrimonial</th>
                                    <th>Usuario Cierre</th>
                                    <th>Fecha Registro</th>
                                    <th class="text-center">Estado</th>
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

<?php estudio_cliente_activo_script($empresa_id); ?>
<script src="contabilidad_cierre.js?v=<?= (int) @filemtime(__DIR__ . '/contabilidad_cierre.js') ?>"></script>
<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
