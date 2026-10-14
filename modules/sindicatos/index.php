<?php
/**
 * modules/sindicatos/index.php
 * Portada Principal y Tablero General del Módulo 6: Sindicatos.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Gestión Sindical - Tablero General";
$currentPage = 'sindi_index';
$modudo_idx = 6;
$pagina_idx = 1000;

define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

$empresa_id = (int)$current_empresa_id;
$qs_mod = "?empresa_id={$empresa_id}&modulo_id=6";

// Métricas del gremio
$qEmp = $conn->query("SELECT COUNT(*) AS total FROM sindi__empleadores WHERE empresa_id = $empresa_id AND tabla_estado_registro_id = 1");
$totEmpleadores = (int)($qEmp->fetch_assoc()['total'] ?? 0);

$qPers = $conn->query("SELECT COUNT(*) AS total FROM sindi__personas WHERE empresa_id = $empresa_id AND tabla_estado_registro_id = 1");
$totPersonas = (int)($qPers->fetch_assoc()['total'] ?? 0);

$qDdjj = $conn->query("SELECT COUNT(*) AS total, IFNULL(SUM(total), 0) AS monto FROM sindi__ddjj WHERE empresa_id = $empresa_id AND tabla_estado_registro_id = 5");
$rDdjj = $qDdjj->fetch_assoc();
$totDdjj = (int)($rDdjj['total'] ?? 0);
$montoDdjj = (float)($rDdjj['monto'] ?? 0.0);

$qVol = $conn->query("SELECT COUNT(*) AS total, IFNULL(SUM(importe_1), 0) AS monto FROM sindi__volantes WHERE empresa_id = $empresa_id");
$rVol = $qVol->fetch_assoc();
$totVolantes = (int)($rVol['total'] ?? 0);
$montoVolantes = (float)($rVol['monto'] ?? 0.0);

$qActas = $conn->query("SELECT COUNT(*) AS total FROM sindi__actas WHERE empresa_id = $empresa_id AND tabla_estado_registro_id IN (1, 2, 5)");
$totActas = (int)($qActas->fetch_assoc()['total'] ?? 0);

$qPlanes = $conn->query("SELECT COUNT(*) AS total FROM sindi__planes WHERE empresa_id = $empresa_id AND tabla_estado_registro_id = 5");
$totPlanes = (int)($qPlanes->fetch_assoc()['total'] ?? 0);
?>

<div class="app-content-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-sm-6">
                <h3 class="mb-0">
                    <i class="fas fa-users-cog me-2 text-primary"></i>Gestión Sindical y Aportes
                </h3>
                <small class="text-muted">Módulo 6 — Control de DDJJ, Cobranzas, Fiscalización y Padrón Gremial</small>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-end">
                    <li class="breadcrumb-item"><a href="#">Home</a></li>
                    <li class="breadcrumb-item active">Sindicatos</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="app-content">
    <div class="container-fluid">
        <!-- Indicadores KPI Superiores -->
        <div class="row g-3 mb-4">
            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card shadow-sm border-0 border-start border-primary border-4 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted text-uppercase fw-semibold mb-1" style="font-size: 0.75rem;">Empleadores Activos</h6>
                                <h3 class="mb-0 fw-bold text-dark"><?= number_format($totEmpleadores, 0, ',', '.') ?></h3>
                            </div>
                            <div class="rounded-circle p-3 bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-building fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card shadow-sm border-0 border-start border-success border-4 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted text-uppercase fw-semibold mb-1" style="font-size: 0.75rem;">Trabajadores en Padrón</h6>
                                <h3 class="mb-0 fw-bold text-dark"><?= number_format($totPersonas, 0, ',', '.') ?></h3>
                            </div>
                            <div class="rounded-circle p-3 bg-success bg-opacity-10 text-success">
                                <i class="bi bi-people fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card shadow-sm border-0 border-start border-info border-4 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted text-uppercase fw-semibold mb-1" style="font-size: 0.75rem;">DDJJ Confirmadas</h6>
                                <h3 class="mb-0 fw-bold text-dark"><?= number_format($totDdjj, 0, ',', '.') ?></h3>
                                <small class="text-muted">$ <?= number_format($montoDdjj, 2, ',', '.') ?></small>
                            </div>
                            <div class="rounded-circle p-3 bg-info bg-opacity-10 text-info">
                                <i class="bi bi-file-earmark-spreadsheet-fill fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-sm-6 col-xl-3">
                <div class="card shadow-sm border-0 border-start border-warning border-4 h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="text-muted text-uppercase fw-semibold mb-1" style="font-size: 0.75rem;">Volantes Emitidos</h6>
                                <h3 class="mb-0 fw-bold text-dark"><?= number_format($totVolantes, 0, ',', '.') ?></h3>
                                <small class="text-muted">$ <?= number_format($montoVolantes, 2, ',', '.') ?></small>
                            </div>
                            <div class="rounded-circle p-3 bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-cash-coin fs-3"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Módulos y Accesos Principales -->
        <div class="row g-4 mb-4">
            <!-- 1. Gestión de Aportes y Cobranzas -->
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-primary text-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i>Gestión de Aportes</h6>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-between p-3">
                        <p class="text-muted small mb-3">
                            Presentación y rectificación de declaraciones juradas mensuales, liquidación de nóminas CCT 130/75 y generación de volantes con código de barras e Interbanking.
                        </p>
                        <div class="d-grid gap-2">
                            <a href="ddjj.php<?= $qs_mod ?>" class="btn btn-outline-primary btn-sm text-start">
                                <i class="bi bi-file-earmark-spreadsheet me-2"></i>Declaraciones Juradas (DDJJ)
                            </a>
                            <a href="volantes.php<?= $qs_mod ?>" class="btn btn-outline-primary btn-sm text-start">
                                <i class="bi bi-cash-coin me-2"></i>Volantes de Pago
                            </a>
                            <a href="ctacte_empleadores.php<?= $qs_mod ?>" class="btn btn-outline-primary btn-sm text-start">
                                <i class="bi bi-person-lines-fill me-2"></i>Cuenta Corriente de Empleadores
                            </a>
                            <a href="rendiciones.php<?= $qs_mod ?>" class="btn btn-outline-primary btn-sm text-start">
                                <i class="bi bi-cash-stack me-2"></i>Rendiciones y Cobranzas Bancarias
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Fiscalización y Convenios -->
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-danger text-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-shield-alt me-2"></i>Fiscalización y Cobranzas</h6>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-between p-3">
                        <p class="text-muted small mb-3">
                            Control del estado de morosidad (Puntual a Crónica), detección de personal no declarado, actas de inspección con cálculo de intereses resarcitorios y planes de facilidades.
                        </p>
                        <div class="d-grid gap-2">
                            <a href="fiscalizacion_tablero.php<?= $qs_mod ?>" class="btn btn-outline-danger btn-sm text-start">
                                <i class="bi bi-speedometer2 me-2"></i>Tablero de Fiscalización
                            </a>
                            <a href="actas.php<?= $qs_mod ?>" class="btn btn-outline-danger btn-sm text-start">
                                <i class="bi bi-shield-check me-2"></i>Actas de Inspección (ACT)
                            </a>
                            <a href="planes.php<?= $qs_mod ?>" class="btn btn-outline-danger btn-sm text-start">
                                <i class="bi bi-calculator me-2"></i>Planes de Pago y Convenios
                            </a>
                            <a href="ddjj_oficio.php<?= $qs_mod ?>" class="btn btn-outline-danger btn-sm text-start">
                                <i class="bi bi-file-earmark-text me-2"></i>DDJJ de Oficio
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Padrón y Configuración -->
            <div class="col-md-6 col-lg-4">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-dark text-white py-3">
                        <h6 class="mb-0 fw-bold"><i class="fas fa-cog me-2"></i>Padrón y Parámetros</h6>
                    </div>
                    <div class="card-body d-flex flex-column justify-content-between p-3">
                        <p class="text-muted small mb-3">
                            Administración del padrón de empresas y trabajadores afiliados, escalas salariales por categoría, cronograma de vencimientos por terminación de CUIT y tasas de mora.
                        </p>
                        <div class="d-grid gap-2">
                            <a href="empleadores.php<?= $qs_mod ?>" class="btn btn-outline-dark btn-sm text-start">
                                <i class="bi bi-building me-2"></i>Padrón de Empleadores
                            </a>
                            <a href="personas.php<?= $qs_mod ?>" class="btn btn-outline-dark btn-sm text-start">
                                <i class="bi bi-people me-2"></i>Trabajadores y Afiliados
                            </a>
                            <a href="convenios.php<?= $qs_mod ?>" class="btn btn-outline-dark btn-sm text-start">
                                <i class="bi bi-journal-text me-2"></i>Convenios y Escalas Salariales
                            </a>
                            <a href="parametros.php<?= $qs_mod ?>" class="btn btn-outline-dark btn-sm text-start">
                                <i class="bi bi-gear-fill me-2"></i>Parámetros Sindicales
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
