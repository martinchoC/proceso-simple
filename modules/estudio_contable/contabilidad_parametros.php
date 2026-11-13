<?php
/**
 * Vista: Parámetros Contables de Enlace
 * Módulo 4: Estudio Contable (contabilidad_parametros.php)
 * Página ID: 412
 */

$pageTitle = "Parámetros Contables de Enlace";
$currentPage = 'contabilidad_parametros';
$modudo_idx = 4;
$pagina_idx = 412;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$empresa_id = (int) $current_empresa_id; // empresa validada por header1.php, sin default

require_once __DIR__ . '/../../db.php';
// Selector de empresas disponibles para configurar
$empresas_res = mysqli_query($conn, "SELECT empresa_id, empresa, documento_numero FROM conf__empresas WHERE tabla_estado_registro_id = 1 ORDER BY empresa ASC");
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 fw-bold">
                        <i class="fas fa-sliders-h me-2 text-primary"></i>Parámetros Contables de Enlace
                    </h3>
                    <small class="text-muted">Cuentas por defecto para imputación automática de asientos en ventas, compras, tesorería y cierre</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="dashboard_estudio.php">Estudio Contable</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Parámetros Contables</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Selector de Empresa -->
            <div class="card card-outline card-primary shadow-sm mb-3">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-6">
                            <label class="form-label form-label-sm mb-1 fw-bold">
                                <i class="fas fa-building me-1 text-primary"></i>Empresa a Configurar
                            </label>
                            <select id="selEmpresaParam" class="form-select form-select-sm">
                                <?php while ($e = mysqli_fetch_assoc($empresas_res)): ?>
                                    <option value="<?= $e['empresa_id'] ?>" <?= ($e['empresa_id'] == $empresa_id) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($e['empresa']) ?> (CUIT: <?= $e['documento_numero'] ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 text-md-end mt-auto pb-1">
                            <button class="btn btn-outline-secondary btn-sm" id="btnRecargarParam">
                                <i class="fas fa-sync-alt me-1"></i>Recargar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Formulario de Parámetros -->
            <form id="formParametrosContables">
                <input type="hidden" id="param_empresa_id" name="empresa_id" value="<?= $empresa_id ?>">

                <div class="row g-3">
                    <!-- Grupo 1: Ventas y Clientes -->
                    <div class="col-md-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white py-2 border-bottom">
                                <h6 class="fw-bold mb-0 text-primary">
                                    <i class="fas fa-sign-out-alt me-1"></i>Ventas y Cuentas por Cobrar
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <div class="mb-3">
                                    <label class="form-label form-label-sm fw-bold">Cuenta Deudores por Ventas</label>
                                    <select class="form-select form-select-sm select-cuenta" id="cuenta_deudores_ventas_id" name="cuenta_deudores_ventas_id" required>
                                        <option value="">-- Seleccionar Cuenta --</option>
                                    </select>
                                    <small class="text-muted">Activo corriente / Créditos por ventas</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label form-label-sm fw-bold">Cuenta Ventas / Ingresos por Defecto</label>
                                    <select class="form-select form-select-sm select-cuenta" id="cuenta_ventas_defecto_id" name="cuenta_ventas_defecto_id" required>
                                        <option value="">-- Seleccionar Cuenta --</option>
                                    </select>
                                    <small class="text-muted">Resultado positivo / Ingresos operativos</small>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label form-label-sm fw-bold">Cuenta IVA Débito Fiscal</label>
                                    <select class="form-select form-select-sm select-cuenta" id="cuenta_iva_debito_id" name="cuenta_iva_debito_id" required>
                                        <option value="">-- Seleccionar Cuenta --</option>
                                    </select>
                                    <small class="text-muted">Pasivo corriente / Deudas impositivas</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Grupo 2: Compras y Proveedores -->
                    <div class="col-md-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white py-2 border-bottom">
                                <h6 class="fw-bold mb-0 text-success">
                                    <i class="fas fa-sign-in-alt me-1"></i>Compras y Cuentas por Pagar
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <div class="mb-3">
                                    <label class="form-label form-label-sm fw-bold">Cuenta Proveedores / Cuentas a Pagar</label>
                                    <select class="form-select form-select-sm select-cuenta" id="cuenta_proveedores_id" name="cuenta_proveedores_id" required>
                                        <option value="">-- Seleccionar Cuenta --</option>
                                    </select>
                                    <small class="text-muted">Pasivo corriente / Deudas comerciales</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label form-label-sm fw-bold">Cuenta Gastos / Compras por Defecto</label>
                                    <select class="form-select form-select-sm select-cuenta" id="cuenta_gastos_defecto_id" name="cuenta_gastos_defecto_id" required>
                                        <option value="">-- Seleccionar Cuenta --</option>
                                    </select>
                                    <small class="text-muted">Resultado negativo / Gastos de administración/comercialización</small>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label form-label-sm fw-bold">Cuenta IVA Crédito Fiscal</label>
                                    <select class="form-select form-select-sm select-cuenta" id="cuenta_iva_credito_id" name="cuenta_iva_credito_id" required>
                                        <option value="">-- Seleccionar Cuenta --</option>
                                    </select>
                                    <small class="text-muted">Activo corriente / Créditos impositivos</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Grupo 3: Tesorería y Fondos -->
                    <div class="col-md-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white py-2 border-bottom">
                                <h6 class="fw-bold mb-0 text-dark">
                                    <i class="fas fa-wallet me-1"></i>Tesorería y Fondos Disponibles
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <div class="mb-3">
                                    <label class="form-label form-label-sm fw-bold">Cuenta Caja General / Efectivo</label>
                                    <select class="form-select form-select-sm select-cuenta" id="cuenta_caja_defecto_id" name="cuenta_caja_defecto_id" required>
                                        <option value="">-- Seleccionar Cuenta --</option>
                                    </select>
                                    <small class="text-muted">Disponibilidades en moneda de curso legal</small>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label form-label-sm fw-bold">Cuenta Banco Cuenta Corriente</label>
                                    <select class="form-select form-select-sm select-cuenta" id="cuenta_banco_defecto_id" name="cuenta_banco_defecto_id" required>
                                        <option value="">-- Seleccionar Cuenta --</option>
                                    </select>
                                    <small class="text-muted">Saldos bancarios disponibles</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Grupo 4: Patrimonio Neto y Cierre -->
                    <div class="col-md-6">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-white py-2 border-bottom">
                                <h6 class="fw-bold mb-0 text-info">
                                    <i class="fas fa-door-closed me-1"></i>Patrimonio Neto y Cierre de Ejercicio
                                </h6>
                            </div>
                            <div class="card-body p-3">
                                <div class="mb-3">
                                    <label class="form-label form-label-sm fw-bold">Cuenta Resultado del Ejercicio</label>
                                    <select class="form-select form-select-sm select-cuenta" id="cuenta_resultado_ejercicio_id" name="cuenta_resultado_ejercicio_id" required>
                                        <option value="">-- Seleccionar Cuenta --</option>
                                    </select>
                                    <small class="text-muted">Contrapartida para el asiento de refundición anual de resultados</small>
                                </div>
                                <div class="mb-0">
                                    <label class="form-label form-label-sm fw-bold">Cuenta Resultados Acumulados</label>
                                    <select class="form-select form-select-sm select-cuenta" id="cuenta_resultados_acumulados_id" name="cuenta_resultados_acumulados_id" required>
                                        <option value="">-- Seleccionar Cuenta --</option>
                                    </select>
                                    <small class="text-muted">Resultados de ejercicios anteriores no asignados</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mt-3">
                    <div class="card-body py-2 text-end">
                        <button type="submit" class="btn btn-primary btn-sm px-4 shadow-sm" id="btnGuardarParam" data-funcion="editar">
                            <i class="fas fa-save me-1"></i>Guardar Parámetros Contables
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</main>

<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="contabilidad_parametros.js"></script>
<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
