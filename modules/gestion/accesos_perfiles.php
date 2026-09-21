<?php

declare(strict_types=1);

/**
 * accesos_perfiles.php
 * ABM: Accesos por perfil (sucursal / punto de venta / tipo de comprobante)
 *
 * ASUMIDO: reemplazar los includes de sesión/header/menu/footer por los reales
 * del sistema (misma estructura que usan bocas.php, puntos_venta.php, etc.)
 */
require_once __DIR__ . '/../sesion.php'; // ASUMIDO
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Accesos por perfil</title>
    <meta name="csrf-token" content="<?= htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES) ?>">
    <?php require __DIR__ . '/../templates/adminlte/header1.php'; // ASUMIDO ?>
</head>
<body class="hold-transition sidebar-mini">
<div class="wrapper">
    <?php require __DIR__ . '/../templates/adminlte/menu.php'; // ASUMIDO ?>

    <div class="content-wrapper">
        <section class="content-header">
            <h1>Accesos por perfil</h1>
            <p class="text-muted">
                Define qué sucursales, puntos de venta y tipos de comprobante puede ver cada perfil.
                Las excepciones puntuales por usuario se configuran aparte.
            </p>
        </section>

        <section class="content">
            <div class="row">
                <div class="col-md-4">
                    <div class="card card-primary">
                        <div class="card-header"><h3 class="card-title">Perfil</h3></div>
                        <div class="card-body">
                            <select id="selPerfil" class="form-control">
                                <option value="">Seleccione un perfil...</option>
                            </select>
                        </div>
                    </div>

                    <div class="card card-secondary" id="cardAgregar" style="display:none;">
                        <div class="card-header"><h3 class="card-title">Agregar acceso</h3></div>
                        <div class="card-body">
                            <div class="form-group">
                                <label>Sucursal <span class="text-danger">*</span></label>
                                <select id="selSucursal" class="form-control">
                                    <option value="">Seleccione...</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Punto de venta</label>
                                <select id="selPuntoVenta" class="form-control" disabled>
                                    <option value="">Todos los puntos de venta</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Tipo de comprobante</label>
                                <select id="selTipoComprobante" class="form-control" disabled>
                                    <option value="">Todos los tipos habilitados</option>
                                </select>
                                <small class="form-text text-muted">
                                    Para restringir por tipo hay que elegir antes un punto de venta puntual.
                                </small>
                            </div>
                            <button type="button" id="btnAgregarGrant" class="btn btn-primary btn-block" disabled>
                                <i class="fas fa-plus"></i> Agregar
                            </button>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Accesos configurados</h3></div>
                        <div class="card-body table-responsive">
                            <table id="tblGrants" class="table table-bordered table-striped" style="width:100%">
                                <thead>
                                    <tr>
                                        <th>Sucursal</th>
                                        <th>Punto de venta</th>
                                        <th>Tipo de comprobante</th>
                                        <th style="width:80px;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <?php require __DIR__ . '/../templates/adminlte/footer.php'; // ASUMIDO ?>
</div>
<script src="accesos_perfiles.js"></script>
</body>
</html>
