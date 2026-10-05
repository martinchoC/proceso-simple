<?php
/**
 * Portada Principal del Módulo 3: Matriz Legal y Corporativa
 * Archivo: modules/ml/index.php
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Matriz Legal y Corporativa";
$currentPage = 'ml_index';
$modudo_idx = 3;
$pagina_idx = 64;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

// Consultas rápidas para métricas del dashboard
$q_terr = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM ml__territorios WHERE tabla_estado_registro_id = 1");
$tot_terr = ($r = mysqli_fetch_assoc($q_terr)) ? (int)$r['total'] : 0;

$q_ent = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM ml__entidades WHERE tabla_estado_registro_id = 1");
$tot_ent = ($r = mysqli_fetch_assoc($q_ent)) ? (int)$r['total'] : 0;

$q_grup = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM ml__grupos WHERE tabla_estado_registro_id = 1");
$tot_grup = ($r = mysqli_fetch_assoc($q_grup)) ? (int)$r['total'] : 0;

$q_fer = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM ml__feriados WHERE tabla_estado_registro_id = 1 AND YEAR(fecha) = YEAR(CURDATE())");
$tot_fer = ($r = mysqli_fetch_assoc($q_fer)) ? (int)$r['total'] : 0;

$qs_modulo = "?empresa_id=" . $current_empresa_id . "&modulo_id=3";
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-balance-scale me-2 text-primary"></i>Matriz Legal y Corporativa
                    </h3>
                    <small class="text-muted">Control normativo, entidades operativas y estructura corporativa multi-jurisdicción</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Inicio</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Matriz Legal</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">

            <!-- Banner Acceso Directo al Tablero Ejecutivo y Drilldown -->
            <div class="alert alert-primary shadow-sm border-0 d-flex flex-column flex-md-row align-items-md-center justify-content-between p-3 mb-4 rounded-3 bg-white border-start border-primary border-4">
                <div class="d-flex align-items-center mb-2 mb-md-0">
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle me-3">
                        <i class="fas fa-sitemap fa-2x"></i>
                    </div>
                    <div>
                        <h5 class="fw-bold mb-1 text-primary">Tablero de Gobierno Legal y Drilldown Corporativo</h5>
                        <span class="text-muted small">Supervisión en tiempo real de tareas vencidas, alertas de ciclos y desglose multinivel (Global ➔ Regiones ➔ Países ➔ Empresas)</span>
                    </div>
                </div>
                <a href="tablero_cumplimiento.php" class="btn btn-primary px-3 py-2 fw-semibold shadow-sm text-nowrap">
                    <i class="fas fa-chart-line me-1"></i>Abrir Tablero Ejecutivo
                </a>
            </div>

            <!-- Fila de Tarjetas Informativas / Métricas -->
            <div class="row g-3 mb-4">
                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card shadow-sm border-0 border-start border-primary border-4 h-100">
                        <div class="card-body d-flex align-items-center justify-content-between p-3">
                            <div>
                                <span class="text-muted text-uppercase fs-7 fw-semibold">Territorios Activos</span>
                                <h3 class="fw-bold text-primary mb-0 mt-1"><?= $tot_terr ?></h3>
                            </div>
                            <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                                <i class="fas fa-globe-americas fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card shadow-sm border-0 border-start border-success border-4 h-100">
                        <div class="card-body d-flex align-items-center justify-content-between p-3">
                            <div>
                                <span class="text-muted text-uppercase fs-7 fw-semibold">Entidades Sujetas a Control</span>
                                <h3 class="fw-bold text-success mb-0 mt-1"><?= $tot_ent ?></h3>
                            </div>
                            <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle">
                                <i class="fas fa-boxes fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card shadow-sm border-0 border-start border-info border-4 h-100">
                        <div class="card-body d-flex align-items-center justify-content-between p-3">
                            <div>
                                <span class="text-muted text-uppercase fs-7 fw-semibold">Grupos Corporativos</span>
                                <h3 class="fw-bold text-info mb-0 mt-1"><?= $tot_grup ?></h3>
                            </div>
                            <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle">
                                <i class="fas fa-sitemap fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="card shadow-sm border-0 border-start border-warning border-4 h-100">
                        <div class="card-body d-flex align-items-center justify-content-between p-3">
                            <div>
                                <span class="text-muted text-uppercase fs-7 fw-semibold">Feriados del Año</span>
                                <h3 class="fw-bold text-warning mb-0 mt-1"><?= $tot_fer ?></h3>
                            </div>
                            <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle">
                                <i class="fas fa-calendar-alt fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Módulos de M1: Accesos Rápidos -->
            <h5 class="fw-bold text-dark mb-3">
                <i class="fas fa-th-large me-2 text-secondary"></i>Módulos de Estructura y Geografía Normativa (M1)
            </h5>

            <div class="row g-3">
                <!-- 1. Territorios -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-primary text-white p-2 rounded me-3">
                                    <i class="fas fa-globe-americas fa-lg"></i>
                                </div>
                                <h5 class="card-title fw-bold mb-0">Territorios y Husos</h5>
                            </div>
                            <p class="card-text text-muted small flex-grow-1">
                                Árbol territorial de profundidad libre (Mundo, Regiones, Países, Provincias) y husos horarios IANA aplicables.
                            </p>
                            <a href="territorios.php<?= $qs_modulo ?>" class="btn btn-outline-primary btn-sm mt-2">
                                Gestionar Territorios <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 2. Grupos y Regiones -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-info text-white p-2 rounded me-3">
                                    <i class="fas fa-sitemap fa-lg"></i>
                                </div>
                                <h5 class="card-title fw-bold mb-0">Grupos y Regiones</h5>
                            </div>
                            <p class="card-text text-muted small flex-grow-1">
                                Grupos económicos, regiones corporativas de gestión y vinculación temporal de empresas del grupo.
                            </p>
                            <a href="grupos_regiones.php<?= $qs_modulo ?>" class="btn btn-outline-info btn-sm mt-2">
                                Gestionar Estructura <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 3. Entidades -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-success text-white p-2 rounded me-3">
                                    <i class="fas fa-boxes fa-lg"></i>
                                </div>
                                <h5 class="card-title fw-bold mb-0">Entidades y Activos</h5>
                            </div>
                            <p class="card-text text-muted small flex-grow-1">
                                Plantas, terminales, buques y unidades operativas sujetas a control legal, transferencias de titularidad e historial de radicación.
                            </p>
                            <a href="entidades.php<?= $qs_modulo ?>" class="btn btn-outline-success btn-sm mt-2">
                                Gestionar Entidades <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 4. Eventos Societarios -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-warning text-dark p-2 rounded me-3">
                                    <i class="fas fa-file-signature fa-lg"></i>
                                </div>
                                <h5 class="card-title fw-bold mb-0">Eventos Societarios</h5>
                            </div>
                            <p class="card-text text-muted small flex-grow-1">
                                Proceso corto de registro y aprobación legal de fusiones, escisiones, cambios de denominación y altas/bajas de empresas.
                            </p>
                            <a href="empresas_eventos.php<?= $qs_modulo ?>" class="btn btn-outline-warning text-dark btn-sm mt-2">
                                Gestionar Eventos <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 5. Calendario de Feriados -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-danger text-white p-2 rounded me-3">
                                    <i class="fas fa-calendar-alt fa-lg"></i>
                                </div>
                                <h5 class="card-title fw-bold mb-0">Calendario de Feriados</h5>
                            </div>
                            <p class="card-text text-muted small flex-grow-1">
                                Días no laborables por país y territorio para el cómputo automático de plazos en días hábiles (M4 y M6).
                            </p>
                            <a href="feriados.php<?= $qs_modulo ?>" class="btn btn-outline-danger btn-sm mt-2">
                                Ver Feriados <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- 6. Tipos de Entidad y Datos -->
                <div class="col-12 col-md-6 col-lg-4">
                    <div class="card shadow-sm h-100">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex align-items-center mb-3">
                                <div class="bg-secondary text-white p-2 rounded me-3">
                                    <i class="fas fa-tags fa-lg"></i>
                                </div>
                                <h5 class="card-title fw-bold mb-0">Tipos de Entidad y Datos</h5>
                            </div>
                            <p class="card-text text-muted small flex-grow-1">
                                Catálogos de tipos de entidades (físicas, móviles, lógicas) y definición de campos dinámicos complementarios.
                            </p>
                            <a href="entidades_tipos.php<?= $qs_modulo ?>" class="btn btn-outline-secondary btn-sm mt-2">
                                Configurar Catálogos <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>