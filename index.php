<?php
require_once __DIR__ . '/config.php';

if (!isset($_SESSION['logueado']) || $_SESSION['logueado'] !== true) {
    header("Location: " . url('login.php'));
    exit;
}

// Si el usuario solo tiene una combinación posible de empresa+módulo según sus perfiles
// asignados, nos salteamos el panel de selección y vamos directo a esa pantalla.
$empresas_modulos_usuario = usuario_obtener_empresas_modulos($conexion, (int) $_SESSION['usuario_id']);
$combos_disponibles = [];
foreach ($empresas_modulos_usuario as $empresa_id_disp => $empresa_disp) {
    foreach ($empresa_disp['modulos'] as $modulo_disp) {
        $combos_disponibles[] = ['empresa_id' => $empresa_id_disp, 'modulo' => $modulo_disp];
    }
}

if (count($combos_disponibles) === 1) {
    $unico_combo = $combos_disponibles[0];
    $ruta_modulo = $unico_combo['modulo']['url'];
    if (strpos($ruta_modulo, 'modules/') === false) {
        $ruta_modulo = 'modules/' . ltrim($ruta_modulo, '/');
    }
    header("Location: " . url($ruta_modulo) . "?empresa_id=" . $unico_combo['empresa_id'] . "&modulo_id=" . $unico_combo['modulo']['id']);
    exit;
}

require_once 'templates/adminlte/header1.php';
?>

<div class="app-content-header">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-6">
                <h3 class="mb-0 fw-bold">Panel de Acceso</h3>
            </div>
        </div>
    </div>
</div>

<div class="app-content">
    <div class="container-fluid">

        <?php
        if (!empty($empresas_modulos)):
            ?>
            <?php
            foreach ($empresas_modulos as $eid => $empresa):
                ?>
                <?php
                if (empty($empresa['modulos']))
                    continue;
                ?>

                <div class="empresa-separator">
                    <h4>
                        <i class="bi bi-building text-primary"></i>
                        <?= htmlspecialchars($empresa['nombre']) ?>
                    </h4>
                    <div class="line"></div>
                </div>

                <div class="row g-3"> <?php
                foreach ($empresa['modulos'] as $mod):
                    ?>
                        <?php
                        $ruta_modulo = $mod['url'];
                        if (strpos($ruta_modulo, 'modules/') === false) {
                            $ruta_modulo = 'modules/' . ltrim($ruta_modulo, '/');
                        }
                        $target_url = url($ruta_modulo) . "?empresa_id=" . $eid . "&modulo_id=" . $mod['id'];
                        ?>
                        <div class="col-6 col-md-4 col-lg-3 col-xl-2">
                            <a href="<?= $target_url ?>" class="modulo-card">
                                <i class="bi bi-grid-fill modulo-icon"></i>
                                <h5 class="modulo-title"><?= htmlspecialchars($mod['nombre']) ?></h5>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

            <?php endforeach; ?>
        <?php else: ?>
            <div class="alert alert-warning shadow-sm border-0 border-start border-warning border-5 p-4 mt-3">
                <h5 class="mb-0">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    No se encontraron módulos asignados a tu usuario. Por favor, contacta al administrador.
                </h5>
            </div>
        <?php endif; ?>

    </div>
</div>

<?php
require_once 'templates/adminlte/footer1.php';
?>