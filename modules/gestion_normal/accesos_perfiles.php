<?php
declare(strict_types=1);

/**
 * Vista: Accesos por Perfil (Sucursales, Bocas, Puntos de Venta y Comprobantes)
 * Módulo 2: Gestión de Empresas (accesos_perfiles.php)
 * Página ID: 72
 */

$pageTitle = "Accesos por Perfil a Sucursales y Bocas";
$currentPage = 'accesos_perfiles';
$modudo_idx = 5;
$pagina_idx = 941;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
require_once __DIR__ . '/../../db.php';

$empresa_id = intval($_GET['empresa_id'] ?? $_SESSION['empresa_id'] ?? 0);
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-7">
                    <h3 class="mb-0 fw-bold">
                        <i class="fas fa-shield-alt me-2 text-primary"></i>Accesos por Perfil
                    </h3>
                    <small class="text-muted">Asignación transversal de Sucursales, Bocas y Puntos de Venta a cada perfil de usuario</small>
                </div>
                <div class="col-sm-5">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Gestión</a></li>
                        <li class="breadcrumb-item"><a href="sucursales.php?empresa_id=<?= $empresa_id ?>">Sucursales</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Accesos por Perfil</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="row g-3">
                <!-- Panel izquierdo: Selector de Perfil y Formulario de Asignación -->
                <div class="col-lg-4 col-md-5">
                    <div class="card card-outline card-primary shadow-sm mb-3">
                        <div class="card-header py-2">
                            <h5 class="card-title mb-0 fw-bold fs-6">
                                <i class="fas fa-user-tag me-1 text-primary"></i>1. Seleccionar Perfil
                            </h5>
                        </div>
                        <div class="card-body">
                            <label class="form-label form-label-sm fw-bold">Perfil de la Empresa:</label>
                            <select id="selPerfil" class="form-select form-select-sm">
                                <option value="">Cargando perfiles...</option>
                            </select>
                            <small class="text-muted d-block mt-2">
                                <i class="fas fa-info-circle me-1"></i>Los perfiles sin reglas específicas quedan <strong>restringidos a todo</strong> (denegado por defecto).
                            </small>
                        </div>
                    </div>

                    <div class="card card-outline card-success shadow-sm" id="cardAgregar" style="display: none;">
                        <div class="card-header py-2">
                            <h5 class="card-title mb-0 fw-bold fs-6">
                                <i class="fas fa-plus-circle me-1 text-success"></i>2. Asignar Acceso
                            </h5>
                        </div>
                        <div class="card-body">
                            <form id="formGrant" onsubmit="return false;">
                                <div class="mb-2">
                                    <label class="form-label form-label-sm fw-bold">Sucursal:</label>
                                    <select id="selSucursal" class="form-select form-select-sm">
                                        <option value="">Todas las Sucursales (Acceso Total)</option>
                                    </select>
                                    <small class="text-muted d-block" style="font-size:0.75rem;">Si selecciona "Todas", habilita acceso completo en esta empresa.</small>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label form-label-sm fw-bold">Boca / Depósito:</label>
                                    <select id="selBoca" class="form-select form-select-sm" disabled>
                                        <option value="">Todas las Bocas de la Sucursal</option>
                                    </select>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label form-label-sm fw-bold">Punto de Venta:</label>
                                    <select id="selPuntoVenta" class="form-select form-select-sm" disabled>
                                        <option value="">Todos los Puntos de Venta</option>
                                    </select>
                                </div>

                                <div class="mb-2">
                                    <label class="form-label form-label-sm fw-bold">Tipo de Comprobante:</label>
                                    <select id="selTipoComprobante" class="form-select form-select-sm" disabled>
                                        <option value="">Todos los Tipos Habilitados</option>
                                    </select>
                                    <small class="text-muted d-block" style="font-size:0.75rem;">Requiere elegir un Punto de Venta específico.</small>
                                </div>

                                <div class="form-check form-switch mb-3 mt-2">
                                    <input class="form-check-input" type="checkbox" id="chkPredeterminado">
                                    <label class="form-check-label form-label-sm" for="chkPredeterminado">Preseleccionar por defecto en combos</label>
                                </div>

                                <button type="button" id="btnAgregarGrant" class="btn btn-success btn-sm w-100 shadow-sm">
                                    <i class="fas fa-check me-1"></i>Otorgar Acceso
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Panel derecho: Grilla de Accesos Asignados -->
                <div class="col-lg-8 col-md-7">
                    <div class="card card-outline card-secondary shadow-sm">
                        <div class="card-header py-2 d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0 fw-bold fs-6" id="tituloGrants">
                                <i class="fas fa-list-check me-1"></i>Accesos por Perfil (Todos los perfiles)
                            </h5>
                            <button type="button" id="btnRecargarGrants" class="btn btn-outline-secondary btn-sm py-0" title="Refrescar">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <div class="card-body p-2">
                            <div class="table-responsive">
                                <table id="tblGrants" class="table table-sm table-striped table-hover align-middle mb-0" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Perfil</th>
                                            <th>Sucursal</th>
                                            <th>Boca / Depósito</th>
                                            <th>Punto de Venta</th>
                                            <th>Comprobante</th>
                                            <th class="text-center" style="width:60px;">Default</th>
                                            <th class="text-center" style="width:60px;">Acción</th>
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

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>window.ACCESOS_EMPRESA_ID = <?= (int)$empresa_id ?>;</script>
<script src="accesos_perfiles.js?v=<?= filemtime(__DIR__ . '/accesos_perfiles.js') ?>"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
