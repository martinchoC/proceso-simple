<?php
// Inicio del módulo conf (Manejo de Módulos y Usuarios): indicadores, puntos a revisar y accesos por sección.
// Punto de entrada del selector de módulos de header1.php (modules/conf?empresa_id=..).
$pageTitle = "Inicio - Configuración";
$currentPage = 'inicio';
$modudo_idx = 1;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
require_once __DIR__ . '/inicio_model.php';

// Cada punto se muestra solo a quien puede abrir la página donde se corrige
$puede_ver = function (int $pagina_id) use ($es_superusuario, $paginas_permitidas): bool {
    return !empty($es_superusuario) || isset($paginas_permitidas[$pagina_id]);
};
$qs_conf = '';
$secciones = $GLOBALS['ui_menu_secciones'] ?? [];
$hay_acceso = !empty($es_superusuario) || !empty($secciones);
$indicadores = $hay_acceso ? confInicioIndicadores($conexion) : [];
$revisar = $hay_acceso ? array_values(array_filter(confInicioRevisar($conexion), fn($p) => $puede_ver((int)$p['pagina_id']))) : [];
$iconos_seccion = ['Empresas y accesos' => 'bi bi-buildings', 'Estructura del sistema' => 'bi bi-diagram-3', 'Otros' => 'bi bi-three-dots'];
?>
<style>
    .ci-revisar .list-group-item { padding: .7rem .9rem; }
    .ci-revisar .ci-cant { min-width: 2.2rem; text-align: center; }
    .ci-revisar details summary { cursor: pointer; color: #6c757d; font-size: .8rem; }
    .ci-revisar details ul { margin: .35rem 0 0; padding-left: 1.1rem; font-size: .82rem; columns: 2; column-gap: 2rem; }
    @media (max-width: 768px) { .ci-revisar details ul { columns: 1; } }
    .ci-seccion .list-group-item { display: flex; align-items: center; gap: .55rem; padding: .5rem .8rem; }
    .ci-seccion .list-group-item i { color: #6c757d; width: 1.1rem; text-align: center; }
    .ci-ok { color: #198754; }
</style>

<main class="app-main">
    <?php ui_encabezado('Configuración del sistema', 'bi bi-house', 'Empresas, módulos, perfiles, usuarios y la estructura de páginas y estados que usa todo el sistema.'); ?>

    <div class="app-content">
        <div class="container-fluid">
            <?php if (!$hay_acceso): ?>
                <div class="alert alert-light border">Tu perfil no tiene páginas habilitadas en este módulo.</div>
            <?php else: ?>

            <div class="row g-3 mb-3">
                <div class="col-6 col-lg-3"><div class="ui-kpi">
                    <div class="ui-kpi-etiqueta">Empresas activas</div>
                    <div class="ui-kpi-valor"><?= fmt_numero($indicadores['empresas'], 0) ?></div>
                    <div class="ui-kpi-sub"><?= fmt_numero($indicadores['modulos'], 0) ?> módulos en el sistema</div>
                </div></div>
                <div class="col-6 col-lg-3"><div class="ui-kpi">
                    <div class="ui-kpi-etiqueta">Usuarios activos</div>
                    <div class="ui-kpi-valor"><?= fmt_numero($indicadores['usuarios'], 0) ?></div>
                    <div class="ui-kpi-sub">con acceso al sistema</div>
                </div></div>
                <div class="col-6 col-lg-3"><div class="ui-kpi">
                    <div class="ui-kpi-etiqueta">Perfiles de empresa</div>
                    <div class="ui-kpi-valor"><?= fmt_numero($indicadores['perfiles'], 0) ?></div>
                    <div class="ui-kpi-sub">en todas las empresas</div>
                </div></div>
                <div class="col-6 col-lg-3"><div class="ui-kpi">
                    <div class="ui-kpi-etiqueta">Páginas del menú</div>
                    <div class="ui-kpi-valor"><?= fmt_numero($indicadores['paginas'], 0) ?></div>
                    <div class="ui-kpi-sub"><?= fmt_numero($indicadores['funciones'], 0) ?> funciones (botones)</div>
                </div></div>
            </div>

            <div class="row g-3">
                <div class="col-lg-7">
                    <div class="card ci-revisar h-100">
                        <div class="card-header"><h3 class="card-title mb-0"><i class="bi bi-clipboard-check me-1"></i> Para revisar</h3></div>
                        <?php if (!$revisar): ?>
                            <div class="card-body text-center py-5">
                                <i class="bi bi-check-circle fs-2 ci-ok"></i>
                                <div class="mt-2 text-muted">La configuración no tiene pendientes.</div>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($revisar as $p): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex align-items-start gap-2">
                                            <span class="badge bg-warning text-dark ci-cant"><?= count($p['items']) ?></span>
                                            <div class="flex-grow-1">
                                                <div class="fw-semibold"><?= htmlspecialchars($p['titulo']) ?></div>
                                                <div class="small text-muted"><?= htmlspecialchars($p['detalle']) ?></div>
                                                <details>
                                                    <summary>Ver cuáles</summary>
                                                    <ul>
                                                        <?php foreach (array_slice($p['items'], 0, 40) as $item): ?>
                                                            <li><?= htmlspecialchars($item) ?></li>
                                                        <?php endforeach; ?>
                                                        <?php if (count($p['items']) > 40): ?>
                                                            <li class="text-muted">y <?= count($p['items']) - 40 ?> más</li>
                                                        <?php endif; ?>
                                                    </ul>
                                                </details>
                                            </div>
                                            <a class="btn btn-sm btn-outline-primary text-nowrap"
                                               href="<?= htmlspecialchars($p['url']) ?>">Revisar</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="col-lg-5">
                    <?php foreach ($secciones as $sec): ?>
                        <div class="card ci-seccion mb-3">
                            <div class="card-header"><h3 class="card-title mb-0">
                                <i class="<?= htmlspecialchars($iconos_seccion[$sec['nombre']] ?? 'bi bi-folder') ?> me-1"></i>
                                <?= htmlspecialchars($sec['nombre']) ?>
                            </h3></div>
                            <div class="list-group list-group-flush">
                                <?php foreach ($sec['paginas'] as $pag): ?>
                                    <a class="list-group-item list-group-item-action" href="<?= htmlspecialchars($pag['url']) ?>">
                                        <i class="<?= htmlspecialchars($pag['icono'] !== '' ? $pag['icono'] : 'bi bi-file-earmark') ?>"></i>
                                        <?= htmlspecialchars($pag['nombre']) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
