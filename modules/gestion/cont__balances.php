<?php
/**
 * cont__balances.php
 *
 * Dashboard de Balance Contable y Menú de Listados de Sumas y Saldos
 * Sistema gestion_multipyme - Módulo 2 Gestión
 * Página ID: 86 | Módulo ID: 2
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Dashboard de Balance Contable y Sumas y Saldos";
$currentPage = 'cont__balances';
$modudo_idx = 2; // Typo intencional y consistente con la arquitectura del sistema (ver CLAUDE.md)
$pagina_idx = 86;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$empresa_id = intval($_GET['empresa_id'] ?? $_SESSION['empresa_id'] ?? 0);
?>

<style>
/* Estilos modernos y premium para el Dashboard de Balance */
.balance-card {
    border-radius: 0.75rem;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    border: 1px solid rgba(0, 0, 0, 0.08);
}
.balance-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 0.5rem 1.25rem rgba(0, 0, 0, 0.08) !important;
}
.kpi-icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: 0.75rem;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
}
.kpi-valor {
    font-size: 1.5rem;
    font-weight: 700;
    line-height: 1.2;
    letter-spacing: -0.5px;
}
.nav-pills-balance .nav-link {
    font-weight: 600;
    padding: 0.65rem 1.25rem;
    border-radius: 0.5rem;
    color: #495057;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}
.nav-pills-balance .nav-link:hover {
    background-color: rgba(13, 110, 253, 0.06);
    color: #0d6efd;
}
.nav-pills-balance .nav-link.active {
    background-color: #0d6efd;
    color: #fff;
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.25);
}
.table-sumas-saldos th {
    font-size: 0.78rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    background-color: #f8f9fa;
    vertical-align: middle;
    border-bottom: 2px solid #dee2e6;
}
.table-sumas-saldos td {
    vertical-align: middle;
    font-size: 0.88rem;
}
.font-monospace-nums {
    font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    font-size: 0.86rem;
}
.cuenta-nivel-1 {
    font-weight: 700;
    background-color: rgba(13, 110, 253, 0.05);
}
.cuenta-nivel-2 {
    font-weight: 600;
    background-color: rgba(108, 117, 125, 0.03);
}
.cuenta-nivel-3 {
    font-weight: 600;
}
.indent-nivel-1 { padding-left: 0.5rem !important; }
.indent-nivel-2 { padding-left: 1.5rem !important; }
.indent-nivel-3 { padding-left: 2.5rem !important; }
.indent-nivel-4 { padding-left: 3.5rem !important; }
.indent-nivel-5 { padding-left: 4.5rem !important; }

.badge-cuadre-ok {
    background-color: #d1e7dd;
    color: #0f5132;
    font-weight: 600;
    padding: 0.4rem 0.75rem;
    border-radius: 0.5rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}
.badge-cuadre-error {
    background-color: #f8d7da;
    color: #842029;
    font-weight: 600;
    padding: 0.4rem 0.75rem;
    border-radius: 0.5rem;
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
}

/* Modo impresión limpio */
@media print {
    .app-header, .app-sidebar, .card-header .btn, .nav-pills-balance, #filtrosCard, .breadcrumb, .no-print {
        display: none !important;
    }
    .app-main, .container-fluid {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    .table-sumas-saldos {
        font-size: 10px !important;
    }
}
</style>

<main class="app-main">
    <!-- Header Principal -->
    <div class="app-content-header py-3 border-bottom bg-white">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-7">
                    <h3 class="mb-0 fw-bold text-dark">
                        <i class="fas fa-balance-scale text-primary me-2"></i>Dashboard de Balance Contable
                    </h3>
                    <small class="text-muted">Estado Patrimonial consolidado, Resultado del Ejercicio y Listados de Sumas y Saldos</small>
                </div>
                <div class="col-sm-5 text-end">
                    <ol class="breadcrumb float-sm-end mb-0">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Contabilidad</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Balance</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content py-3">
        <div class="container-fluid">

            <!-- Barra de Filtros y Control de Período -->
            <div class="card shadow-sm mb-3 balance-card" id="filtrosCard">
                <div class="card-body py-2">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label small fw-bold text-muted mb-1">
                                <i class="fas fa-calendar-alt me-1"></i>Ejercicio Fiscal
                            </label>
                            <select id="filtroEjercicio" class="form-select form-select-sm">
                                <option value="2026">2026 (Actual)</option>
                            </select>
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label small fw-bold text-muted mb-1">Fecha Desde</label>
                            <input type="date" id="filtroFechaDesde" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-2 col-sm-6">
                            <label class="form-label small fw-bold text-muted mb-1">Fecha Hasta</label>
                            <input type="date" id="filtroFechaHasta" class="form-control form-control-sm">
                        </div>
                        <div class="col-md-3 col-sm-6">
                            <label class="form-label small fw-bold text-muted mb-1">Período Rápido</label>
                            <div class="btn-group btn-group-sm w-100" role="group">
                                <button type="button" class="btn btn-outline-secondary btn-periodo" data-tipo="mes">Mes Actual</button>
                                <button type="button" class="btn btn-outline-secondary btn-periodo" data-tipo="trimestre">Trimestre</button>
                                <button type="button" class="btn btn-outline-secondary btn-periodo active" data-tipo="ejercicio">Ejercicio</button>
                                <button type="button" class="btn btn-outline-secondary btn-periodo" data-tipo="historico">Histórico</button>
                            </div>
                        </div>
                        <div class="col-md-3 col-sm-12 text-md-end text-start mt-2 mt-md-0 d-flex align-items-center justify-content-md-end gap-2">
                            <div class="form-check form-switch mb-0" title="Permite simular balances con borradores antes de registrarlos definitivamente">
                                <input class="form-check-input" type="checkbox" id="checkBorradores" checked>
                                <label class="form-check-label small fw-bold text-muted" for="checkBorradores">Incluir Borradores</label>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm px-3" id="btnActualizar">
                                <i class="fas fa-sync-alt me-1"></i> Actualizar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- KPIs Principales del Balance -->
            <div class="row g-3 mb-3">
                <!-- Activo Total -->
                <div class="col-12 col-sm-6 col-xl">
                    <div class="card shadow-sm balance-card h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="small fw-bold text-muted text-uppercase">Total Activo</span>
                                <div class="kpi-icon-wrapper bg-primary-subtle text-primary">
                                    <i class="fas fa-wallet"></i>
                                </div>
                            </div>
                            <div class="kpi-valor text-dark" id="kpiActivoTotal">$0,00</div>
                            <div class="small text-muted mt-2 d-flex justify-content-between">
                                <span>Cte: <strong id="kpiActivoCte" class="text-primary">$0</strong></span>
                                <span>No Cte: <strong id="kpiActivoNoCte">$0</strong></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pasivo Total -->
                <div class="col-12 col-sm-6 col-xl">
                    <div class="card shadow-sm balance-card h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="small fw-bold text-muted text-uppercase">Total Pasivo</span>
                                <div class="kpi-icon-wrapper bg-danger-subtle text-danger">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                </div>
                            </div>
                            <div class="kpi-valor text-dark" id="kpiPasivoTotal">$0,00</div>
                            <div class="small text-muted mt-2 d-flex justify-content-between">
                                <span>Cte: <strong id="kpiPasivoCte" class="text-danger">$0</strong></span>
                                <span>No Cte: <strong id="kpiPasivoNoCte">$0</strong></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Patrimonio Neto -->
                <div class="col-12 col-sm-6 col-xl">
                    <div class="card shadow-sm balance-card h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="small fw-bold text-muted text-uppercase">Patrimonio Neto</span>
                                <div class="kpi-icon-wrapper bg-info-subtle text-info">
                                    <i class="fas fa-landmark"></i>
                                </div>
                            </div>
                            <div class="kpi-valor text-dark" id="kpiPatrimonioNeto">$0,00</div>
                            <div class="small text-muted mt-2">
                                <span>Capital + Res.: <strong id="kpiPatrimonioBase">$0</strong></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resultado del Ejercicio -->
                <div class="col-12 col-sm-6 col-xl">
                    <div class="card shadow-sm balance-card h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="small fw-bold text-muted text-uppercase">Resultado Ejercicio</span>
                                <div class="kpi-icon-wrapper bg-success-subtle text-success" id="kpiIconResultado">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                            <div class="kpi-valor" id="kpiResultadoEjercicio">$0,00</div>
                            <div class="small text-muted mt-2 d-flex justify-content-between">
                                <span>Margen: <strong id="kpiMargenNeto">0%</strong></span>
                                <span id="badgeEstadoResultado" class="badge bg-success-subtle text-success fw-bold">Ganancia</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ecuación Patrimonial Fundamental -->
                <div class="col-12 col-sm-6 col-xl">
                    <div class="card shadow-sm balance-card h-100">
                        <div class="card-body p-3">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <span class="small fw-bold text-muted text-uppercase">Ecuación Patrimonial</span>
                                <div class="kpi-icon-wrapper bg-secondary-subtle text-secondary" id="kpiIconEcuacion">
                                    <i class="fas fa-balance-scale"></i>
                                </div>
                            </div>
                            <div class="d-flex align-items-center mt-2">
                                <span id="badgeEcuacionCuadre" class="badge-cuadre-ok w-100 justify-content-center">
                                    <i class="fas fa-check-circle"></i> Activo = Pasivo + PN
                                </span>
                            </div>
                            <div class="small text-muted mt-2 text-center" id="textoEcuacionDetalle">
                                Diferencia: $0,00
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Menú de Navegación de Vistas del Balance -->
            <ul class="nav nav-pills nav-pills-balance mb-3 gap-2" id="balanceTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-dashboard" data-bs-toggle="pill" data-bs-target="#vista-dashboard" type="button" role="tab">
                        <i class="fas fa-chart-pie me-2"></i>Dashboard Contable
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-sumas-saldos" data-bs-toggle="pill" data-bs-target="#vista-sumas-saldos" type="button" role="tab">
                        <i class="fas fa-list-ol me-2"></i>Listado de Sumas y Saldos
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-situacion" data-bs-toggle="pill" data-bs-target="#vista-situacion" type="button" role="tab">
                        <i class="fas fa-building-columns me-2"></i>Estado de Situación (Patrimonial)
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-resultados" data-bs-toggle="pill" data-bs-target="#vista-resultados" type="button" role="tab">
                        <i class="fas fa-hand-holding-dollar me-2"></i>Estado de Resultados (P y G)
                    </button>
                </li>
            </ul>

            <!-- Contenido de las Vistas -->
            <div class="tab-content" id="balanceTabsContent">

                <!-- ========================================== -->
                <!-- VISTA 1: DASHBOARD CONTABLE               -->
                <!-- ========================================== -->
                <div class="tab-pane fade show active" id="vista-dashboard" role="tabpanel">
                    <div class="row g-3 mb-3">
                        <!-- Gráfico Estructura Patrimonial -->
                        <div class="col-lg-5">
                            <div class="card shadow-sm balance-card h-100">
                                <div class="card-header bg-white py-3 border-bottom">
                                    <h6 class="card-title mb-0 fw-bold">
                                        <i class="fas fa-chart-pie text-primary me-2"></i>Estructura Patrimonial
                                    </h6>
                                </div>
                                <div class="card-body d-flex flex-column align-items-center justify-content-center p-3">
                                    <div style="position: relative; width: 100%; max-height: 280px;">
                                        <canvas id="chartEstructuraPatrimonial"></canvas>
                                    </div>
                                    <div class="small text-muted text-center mt-3" id="leyendaEstructura">
                                        Distribución relativa de activos, pasivos y patrimonio neto
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Gráfico Evolución Mensual de Ingresos y Egresos -->
                        <div class="col-lg-7">
                            <div class="card shadow-sm balance-card h-100">
                                <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                                    <h6 class="card-title mb-0 fw-bold">
                                        <i class="fas fa-chart-column text-success me-2"></i>Evolución de Ingresos vs Egresos
                                    </h6>
                                    <span class="badge bg-light text-muted border">Mes a Mes</span>
                                </div>
                                <div class="card-body p-3">
                                    <div style="position: relative; width: 100%; height: 280px;">
                                        <canvas id="chartEvolucionMensual"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Mini-Tablas y Diagnóstico de Auditoría -->
                    <div class="row g-3">
                        <!-- Principales Rubros de Activo y Pasivo -->
                        <div class="col-lg-7">
                            <div class="card shadow-sm balance-card">
                                <div class="card-header bg-white py-3 border-bottom">
                                    <h6 class="card-title mb-0 fw-bold">
                                        <i class="fas fa-layer-group text-info me-2"></i>Composición por Rubros Principales
                                    </h6>
                                </div>
                                <div class="card-body p-3">
                                    <div class="row g-3">
                                        <div class="col-md-6 border-end">
                                            <h6 class="small fw-bold text-primary text-uppercase mb-2">Rubros de Activo</h6>
                                            <div id="listaRubrosActivo" class="list-group list-group-flush small">
                                                <div class="text-muted text-center py-2">Sin movimientos</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <h6 class="small fw-bold text-danger text-uppercase mb-2">Rubros de Pasivo</h6>
                                            <div id="listaRubrosPasivo" class="list-group list-group-flush small">
                                                <div class="text-muted text-center py-2">Sin movimientos</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Diagnóstico de Auditoría Contable -->
                        <div class="col-lg-5">
                            <div class="card shadow-sm balance-card">
                                <div class="card-header bg-white py-3 border-bottom">
                                    <h6 class="card-title mb-0 fw-bold">
                                        <i class="fas fa-clipboard-check text-warning me-2"></i>Diagnóstico de Auditoría Contable
                                    </h6>
                                </div>
                                <div class="card-body p-3">
                                    <ul class="list-group list-group-flush small">
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            <span><i class="fas fa-check-double text-success me-2"></i>Asientos Registrados (Confirmados)</span>
                                            <span class="badge bg-success-subtle text-success fw-bold" id="auditoriaRegistrados">0</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            <span><i class="fas fa-edit text-warning me-2"></i>Asientos en Borrador (Pendientes)</span>
                                            <span class="badge bg-warning-subtle text-warning fw-bold" id="auditoriaBorradores">0</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            <span><i class="fas fa-exclamation-triangle text-danger me-2"></i>Asientos Descuadrados</span>
                                            <span class="badge bg-secondary-subtle text-secondary fw-bold" id="auditoriaDescuadrados">0</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                                            <span><i class="fas fa-exchange-alt text-info me-2"></i>Cuentas con Saldo Contrario</span>
                                            <span class="badge bg-info-subtle text-info fw-bold" id="auditoriaSaldoContrario">0</span>
                                        </li>
                                    </ul>
                                    <div class="alert alert-light border mt-3 mb-0 p-2 small text-muted text-center" id="auditoriaMensaje">
                                        <i class="fas fa-info-circle me-1"></i> Auditoría conforme a principios de la partida doble.
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- VISTA 2: LISTADO DE SUMAS Y SALDOS        -->
                <!-- ========================================== -->
                <div class="tab-pane fade" id="vista-sumas-saldos" role="tabpanel">
                    <div class="card shadow-sm balance-card">
                        <div class="card-header bg-white py-3 border-bottom">
                            <div class="row align-items-center g-2">
                                <div class="col-md-6">
                                    <h5 class="card-title mb-0 fw-bold">
                                        <i class="fas fa-list-ol text-primary me-2"></i>Balance de Comprobación de Sumas y Saldos
                                    </h5>
                                </div>
                                <div class="col-md-6 text-md-end text-start">
                                    <div class="d-inline-flex flex-wrap align-items-center gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-success" id="btnExportarExcel" title="Exportar a archivo Excel / CSV">
                                            <i class="fas fa-file-excel me-1"></i> Excel
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnImprimirBalance" title="Imprimir o exportar PDF">
                                            <i class="fas fa-print me-1"></i> Imprimir
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Menú y Filtros Específicos del Listado -->
                        <div class="card-body border-bottom bg-light py-2">
                            <div class="row g-2 align-items-center">
                                <!-- Selector de Niveles / Modos del Listado -->
                                <div class="col-md-5 col-sm-12">
                                    <label class="small fw-bold text-muted mb-1 d-block">Tipo de Listado de Sumas y Saldos:</label>
                                    <div class="btn-group btn-group-sm w-100" role="group" id="grupoModosListado">
                                        <button type="button" class="btn btn-outline-primary btn-modo-listado active" data-modo="analitico">
                                            Analítico (Imputables)
                                        </button>
                                        <button type="button" class="btn btn-outline-primary btn-modo-listado" data-modo="arbol">
                                            Árbol Completo
                                        </button>
                                        <button type="button" class="btn btn-outline-primary btn-modo-listado" data-modo="sintetico_nivel1">
                                            Sintético (Nivel 1)
                                        </button>
                                        <button type="button" class="btn btn-outline-primary btn-modo-listado" data-modo="sintetico_nivel2">
                                            Capítulos (Nivel 2)
                                        </button>
                                    </div>
                                </div>

                                <!-- Filtro de Solo con Movimientos -->
                                <div class="col-md-3 col-sm-6">
                                    <div class="form-check form-switch mt-md-4">
                                        <input class="form-check-input" type="checkbox" id="checkSoloMovimientos" checked>
                                        <label class="form-check-label small fw-bold text-muted" for="checkSoloMovimientos">
                                            Solo cuentas con movimientos o saldo
                                        </label>
                                    </div>
                                </div>

                                <!-- Búsqueda rápida -->
                                <div class="col-md-4 col-sm-6">
                                    <label class="small fw-bold text-muted mb-1 d-block">Filtrar Cuentas:</label>
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                                        <input type="text" id="buscadorSumasSaldos" class="form-control" placeholder="Buscar por código o nombre...">
                                        <button class="btn btn-outline-secondary" type="button" id="btnLimpiarBusqueda" title="Limpiar"><i class="fas fa-times"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Tabla de Sumas y Saldos -->
                        <div class="card-body p-0">
                            <div class="table-responsive" style="max-height: 650px; overflow-y: auto;">
                                <table class="table table-hover table-striped align-middle table-sumas-saldos mb-0" id="tablaSumasSaldos">
                                    <thead class="sticky-top bg-light">
                                        <tr>
                                            <th style="width: 120px;">Código</th>
                                            <th>Cuenta Contable</th>
                                            <th class="text-center" style="width: 70px;">Nivel</th>
                                            <th class="text-center" style="width: 60px;">Nat.</th>
                                            <th class="text-end" style="width: 130px;">Saldo Anterior</th>
                                            <th class="text-end" style="width: 130px;">Suma Debe</th>
                                            <th class="text-end" style="width: 130px;">Suma Haber</th>
                                            <th class="text-end" style="width: 130px;">Saldo Deudor</th>
                                            <th class="text-end" style="width: 130px;">Saldo Acreedor</th>
                                            <th class="text-center no-print" style="width: 80px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodySumasSaldos">
                                        <tr>
                                            <td colspan="10" class="text-center py-4 text-muted">
                                                <i class="fas fa-spinner fa-spin me-2"></i>Cargando Balance de Sumas y Saldos...
                                            </td>
                                        </tr>
                                    </tbody>
                                    <tfoot class="table-light border-top border-2 fw-bold" id="tfootSumasSaldos">
                                        <tr class="table-secondary">
                                            <td colspan="4" class="text-end text-uppercase">Totales de Control:</td>
                                            <td class="text-end font-monospace-nums" id="totFooterSaldoAnt">$0,00</td>
                                            <td class="text-end font-monospace-nums text-primary" id="totFooterDebe">$0,00</td>
                                            <td class="text-end font-monospace-nums text-primary" id="totFooterHaber">$0,00</td>
                                            <td class="text-end font-monospace-nums text-success" id="totFooterDeudor">$0,00</td>
                                            <td class="text-end font-monospace-nums text-danger" id="totFooterAcreedor">$0,00</td>
                                            <td class="no-print"></td>
                                        </tr>
                                        <tr id="filaControlCuadre">
                                            <td colspan="4" class="text-end small">Diferencia de Cuadre:</td>
                                            <td class="text-end small text-muted">—</td>
                                            <td colspan="2" class="text-center small font-monospace-nums" id="diffSumasDebeHaber">
                                                Debe - Haber: $0,00
                                            </td>
                                            <td colspan="2" class="text-center small font-monospace-nums" id="diffSaldosDeudorAcreedor">
                                                Deudor - Acreedor: $0,00
                                            </td>
                                            <td class="no-print"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <!-- Banner inferior de estado de balance -->
                        <div class="card-footer bg-white py-3 border-top">
                            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                                <div id="bannerCuadreSumasSaldos" class="d-flex align-items-center gap-2">
                                    <span class="badge-cuadre-ok">
                                        <i class="fas fa-check-circle"></i> Balance de Sumas y Saldos Verificado y Cuadrado
                                    </span>
                                </div>
                                <div class="small text-muted">
                                    Total de cuentas listadas: <strong id="lblCantCuentas">0</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- VISTA 3: BALANCE PATRIMONIAL (SITUACIÓN)  -->
                <!-- ========================================== -->
                <div class="tab-pane fade" id="vista-situacion" role="tabpanel">
                    <div class="row g-3">
                        <!-- Columna ACTIVO -->
                        <div class="col-lg-6">
                            <div class="card shadow-sm balance-card h-100">
                                <div class="card-header bg-primary text-white py-3">
                                    <h5 class="card-title mb-0 fw-bold">
                                        <i class="fas fa-wallet me-2"></i>ACTIVO
                                    </h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <tbody id="tbodyBalanceActivo">
                                                <tr>
                                                    <td class="p-3 text-muted text-center">Cargando Activo...</td>
                                                </tr>
                                            </tbody>
                                            <tfoot class="table-light border-top border-2">
                                                <tr class="fw-bold fs-6">
                                                    <td class="p-3 text-uppercase">TOTAL ACTIVO</td>
                                                    <td class="p-3 text-end font-monospace-nums text-primary" id="totalSituacionActivo">$0,00</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Columna PASIVO Y PATRIMONIO NETO -->
                        <div class="col-lg-6">
                            <!-- Pasivo -->
                            <div class="card shadow-sm balance-card mb-3">
                                <div class="card-header bg-danger text-white py-3">
                                    <h5 class="card-title mb-0 fw-bold">
                                        <i class="fas fa-file-invoice-dollar me-2"></i>PASIVO
                                    </h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <tbody id="tbodyBalancePasivo">
                                                <tr>
                                                    <td class="p-3 text-muted text-center">Cargando Pasivo...</td>
                                                </tr>
                                            </tbody>
                                            <tfoot class="table-light border-top border-2">
                                                <tr class="fw-bold">
                                                    <td class="p-3 text-uppercase">TOTAL PASIVO</td>
                                                    <td class="p-3 text-end font-monospace-nums text-danger" id="totalSituacionPasivo">$0,00</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>

                            <!-- Patrimonio Neto -->
                            <div class="card shadow-sm balance-card">
                                <div class="card-header bg-info text-white py-3">
                                    <h5 class="card-title mb-0 fw-bold">
                                        <i class="fas fa-landmark me-2"></i>PATRIMONIO NETO
                                    </h5>
                                </div>
                                <div class="card-body p-0">
                                    <div class="table-responsive">
                                        <table class="table table-hover align-middle mb-0">
                                            <tbody id="tbodyBalancePN">
                                                <tr>
                                                    <td class="p-3 text-muted text-center">Cargando Patrimonio Neto...</td>
                                                </tr>
                                            </tbody>
                                            <tfoot class="table-light border-top border-2">
                                                <tr class="fw-bold">
                                                    <td class="p-3 text-uppercase">TOTAL PATRIMONIO NETO</td>
                                                    <td class="p-3 text-end font-monospace-nums text-info" id="totalSituacionPN">$0,00</td>
                                                </tr>
                                                <tr class="table-secondary fw-bold fs-6 border-top">
                                                    <td class="p-3 text-uppercase">TOTAL PASIVO + PATRIMONIO NETO</td>
                                                    <td class="p-3 text-end font-monospace-nums text-dark" id="totalSituacionPasivoPN">$0,00</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- VISTA 4: ESTADO DE RESULTADOS (P y G)      -->
                <!-- ========================================== -->
                <div class="tab-pane fade" id="vista-resultados" role="tabpanel">
                    <div class="card shadow-sm balance-card col-lg-10 mx-auto">
                        <div class="card-header bg-white py-3 border-bottom">
                            <h5 class="card-title mb-0 fw-bold">
                                <i class="fas fa-hand-holding-dollar text-success me-2"></i>Estado de Resultados del Ejercicio
                            </h5>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" id="tablaResultadosPyG">
                                    <tbody>
                                        <!-- Ingresos -->
                                        <tr class="table-light fw-bold">
                                            <td colspan="2" class="p-3 text-uppercase text-success">
                                                <i class="fas fa-plus-circle me-1"></i> INGRESOS OPERATIVOS
                                            </td>
                                            <td class="p-3 text-end font-monospace-nums text-success fs-6" id="pygTotalIngresos">$0,00</td>
                                        </tr>
                                        <tbody id="tbodyDetalleIngresos"></tbody>

                                        <!-- Costos -->
                                        <tr class="table-light fw-bold border-top">
                                            <td colspan="2" class="p-3 text-uppercase text-danger">
                                                <i class="fas fa-minus-circle me-1"></i> COSTOS DIRECTOS / MERCADERÍAS
                                            </td>
                                            <td class="p-3 text-end font-monospace-nums text-danger fs-6" id="pygTotalCostos">$0,00</td>
                                        </tr>
                                        <tbody id="tbodyDetalleCostos"></tbody>

                                        <!-- Margen Bruto -->
                                        <tr class="table-secondary fw-bold border-top border-bottom fs-6">
                                            <td colspan="2" class="p-3 text-uppercase">
                                                (=) RESULTADO BRUTO / MARGEN BRUTO
                                            </td>
                                            <td class="p-3 text-end font-monospace-nums" id="pygResultadoBruto">$0,00</td>
                                        </tr>

                                        <!-- Gastos -->
                                        <tr class="table-light fw-bold border-top">
                                            <td colspan="2" class="p-3 text-uppercase text-danger">
                                                <i class="fas fa-minus-circle me-1"></i> GASTOS OPERATIVOS Y DE ADMINISTRACIÓN
                                            </td>
                                            <td class="p-3 text-end font-monospace-nums text-danger fs-6" id="pygTotalGastos">$0,00</td>
                                        </tr>
                                        <tbody id="tbodyDetalleGastos"></tbody>

                                        <!-- Resultado Neto -->
                                        <tr class="table-primary fw-bold fs-5 border-top border-2">
                                            <td colspan="2" class="p-3 text-uppercase" id="lblTextoResultadoNeto">
                                                (=) RESULTADO NETO DEL EJERCICIO
                                            </td>
                                            <td class="p-3 text-end font-monospace-nums text-primary" id="pygResultadoNeto">$0,00</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

            </div> <!-- fin tab-content -->

        </div> <!-- fin container-fluid -->
    </div> <!-- fin app-content -->
</main>

<!-- Modal Detalle de Cuenta / Acceso a Mayor -->
<div class="modal fade" id="modalMayorCuenta" tabindex="-1" aria-labelledby="modalMayorTitulo" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold" id="modalMayorTitulo">
                    <i class="fas fa-book me-2 text-primary"></i>Mayor Contable
                </h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p id="modalMayorDescripcion" class="text-muted small mb-3"></p>
                <div class="d-grid gap-2">
                    <a id="btnIrAlMayor" href="#" target="_blank" class="btn btn-primary">
                        <i class="fas fa-external-link-alt me-1"></i> Abrir Mayor de la Cuenta
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Chart.js 4.4 CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<!-- SweetAlert2 (por consistencia con el ERP) -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<!-- Script de la página -->
<script src="cont__balances.js"></script>

<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
