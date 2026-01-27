<?php
// index.php

// 1. Incluimos config.php (inicia la sesión)
require_once 'config.php';

// 2. VERIFICACIÓN DE SEGURIDAD
if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    // Si no hay sesión válida, al login
    header("Location: " . url('login.php'));
    exit;
}

// Variables para el header
$ruta_assets = "";
$modudo_idx = 0;

// Incluimos el header
require_once 'templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Panel Principal</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="callout callout-info mb-4">
                <h5>Bienvenido, <?= htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Usuario') ?></h5>
                <p>Selecciona un módulo para comenzar.</p>
            </div>

            <div class="row">
                <div class="col-lg-3 col-6">
                    <div class="small-box text-bg-primary">
                        <div class="inner">
                            <h3>Ventas</h3>
                            <p>Gestión Comercial</p>
                        </div>
                        <div class="icon"><i class="fas fa-shopping-cart"></i></div>
                        <a href="<?= url('modules/ventas/index.php') ?>" class="small-box-footer">Ingresar <i
                                class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>

                <div class="col-lg-3 col-6">
                    <div class="small-box text-bg-warning">
                        <div class="inner">
                            <h3>Compras</h3>
                            <p>Proveedores</p>
                        </div>
                        <div class="icon"><i class="fas fa-file-invoice-dollar"></i></div>
                        <a href="<?= url('modules/compras/index.php') ?>" class="small-box-footer">Ingresar <i
                                class="fas fa-arrow-circle-right"></i></a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</main>

<?php require_once 'templates/adminlte/footer1.php'; ?>