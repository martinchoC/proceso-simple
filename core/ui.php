<?php
// Componentes visuales comunes de las páginas (estilo "ui-simple": assets/css/ui-simple.css + assets/js/ui-simple.js).
// Una página adopta el estilo con $ui_simple = true antes de incluir header1.php.

if (!function_exists('ui_ayuda')) {
    /** Ícono "?" con la ayuda en un tooltip (reemplaza los textos explicativos fijos). */
    function ui_ayuda(string $texto): string
    {
        if (trim($texto) === '') {
            return '';
        }
        return '<span class="ui-ayuda" tabindex="0" role="button" data-bs-toggle="tooltip" data-bs-placement="bottom"'
            . ' title="' . htmlspecialchars($texto, ENT_QUOTES) . '" aria-label="Ayuda"><i class="bi bi-question-circle"></i></span>';
    }
}

if (!function_exists('ui_miga_seccion')) {
    /**
     * Sección del menú lateral que corresponde a una miga (la arma header1.php en $GLOBALS['ui_menu_secciones'],
     * solo con las páginas que el usuario puede ver). La última miga es la sección que contiene a la página
     * actual, aunque su texto no coincida con el del menú; las demás se buscan por nombre. null = sin desplegable.
     */
    function ui_miga_seccion(string $miga, bool $es_ultima): ?array
    {
        $secciones = $GLOBALS['ui_menu_secciones'] ?? [];
        if (!$secciones) {
            return null;
        }
        if ($es_ultima) {
            $pid = (int) ($GLOBALS['pagina_idx'] ?? ($_GET['pagina_id'] ?? 0));
            $idx = $GLOBALS['ui_menu_seccion_de_pagina'][$pid] ?? null;
            if ($idx !== null && isset($secciones[$idx])) {
                return $secciones[$idx];
            }
        }
        $clave = fn(string $s): string => mb_strtolower(trim($s), 'UTF-8');
        foreach ($secciones as $sec) {
            if ($clave($sec['nombre']) === $clave($miga)) {
                return $sec;
            }
        }
        return null;
    }
}

if (!function_exists('ui_encabezado')) {
    /**
     * Encabezado de página: migas de pan (Inicio › sección › página), título con ícono,
     * ayuda opcional en un "?" y, a la derecha, las acciones de la página (HTML).
     * $migas: secciones intermedias, sin "Inicio" ni la página actual.
     */
    function ui_encabezado(string $titulo, string $icono = '', string $ayuda = '', array $migas = [], string $acciones = ''): void
    {
        // "Inicio" = pantalla de inicio del módulo de la página (modules/<carpeta>/index.php), con la
        // empresa y el módulo activos que fijó header1.php; fuera de un módulo, el index.php raíz.
        $inicio = function_exists('url') ? url('index.php') : '#';
        $es_inicio = false;
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
        if (preg_match('#/modules/([a-z0-9_]+)/([^/]+)$#i', $script, $m)
            && is_file(__DIR__ . '/../modules/' . $m[1] . '/index.php') && function_exists('url')) {
            $qs = [];
            if (!empty($GLOBALS['current_empresa_id'])) $qs['empresa_id'] = (int) $GLOBALS['current_empresa_id'];
            if (!empty($GLOBALS['current_modulo_id'])) $qs['modulo_id'] = (int) $GLOBALS['current_modulo_id'];
            $inicio = url('modules/' . $m[1] . '/index.php') . ($qs ? '?' . http_build_query($qs) : '');
            $es_inicio = strcasecmp($m[2], 'index.php') === 0;
        }
        ?>
        <div class="app-content-header ui-encabezado">
            <div class="container-fluid">
                <nav aria-label="Ubicación">
                    <ol class="breadcrumb ui-migas">
                        <?php if ($es_inicio && !$migas): ?>
                            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($titulo) ?></li>
                        <?php else: ?>
                            <li class="breadcrumb-item"><a href="<?= htmlspecialchars($inicio) ?>">Inicio</a></li>
                            <?php foreach ($migas as $i => $miga): ?>
                                <?php $sec = ui_miga_seccion($miga, $i === count($migas) - 1); ?>
                                <?php if ($sec): ?>
                                    <li class="breadcrumb-item dropdown">
                                        <a href="#" class="dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false"><?= htmlspecialchars($miga) ?></a>
                                        <ul class="dropdown-menu ui-migas-menu">
                                            <?php foreach ($sec['paginas'] as $p): ?>
                                                <li><a class="dropdown-item<?= $p['activa'] ? ' active' : '' ?>" href="<?= htmlspecialchars($p['url']) ?>"<?= $p['activa'] ? ' aria-current="page"' : '' ?>>
                                                    <?php if ($p['icono'] !== ''): ?><i class="<?= htmlspecialchars($p['icono']) ?>" aria-hidden="true"></i><?php endif; ?>
                                                    <?= htmlspecialchars($p['nombre']) ?>
                                                </a></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </li>
                                <?php else: ?>
                                    <li class="breadcrumb-item"><?= htmlspecialchars($miga) ?></li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($titulo) ?></li>
                        <?php endif; ?>
                    </ol>
                </nav>
                <div class="ui-encabezado-fila">
                    <h1 class="ui-titulo">
                        <?php if ($icono !== ''): ?><i class="<?= htmlspecialchars($icono) ?>" aria-hidden="true"></i><?php endif; ?>
                        <span><?= htmlspecialchars($titulo) ?></span>
                        <?= ui_ayuda($ayuda) ?>
                    </h1>
                    <?php if ($acciones !== ''): ?>
                        <div class="ui-encabezado-acciones"><?= $acciones ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
    }
}
