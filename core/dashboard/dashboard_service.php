<?php
/**
 * core/dashboard/dashboard_service.php
 * Pantalla de inicio de gestion / gestion_normal: widgets, accesos directos y su personalización.
 * Diseño: _local/docs/gestion/11_dashboard_inicio.md
 *
 * Todo parte de dash_contexto(), que arma con datos de la sesión (nunca del request) la empresa,
 * el usuario, el módulo, sus perfiles base en ese módulo y las páginas que puede abrir. Un widget
 * solo se lista, se ofrece y se ejecuta si su pagina_id está entre esas páginas; se vuelve a
 * validar en cada llamada.
 *
 * Valores por defecto y accesos obligatorios cuelgan del perfil BASE (conf__perfiles), resuelto por
 * conf__empresas_perfiles.perfil_id_base. Con varios perfiles se usa la unión.
 *
 * Alta de un widget = fila en conf__dashboard_widgets + core/dashboard/widgets/<handler>.php, que
 * devuelve function (mysqli $c, array $ctx, array $params): array con los datos del tipo.
 */

require_once __DIR__ . '/../permisos.php';
require_once __DIR__ . '/../perfiles_predefinidos.php';
if (file_exists(__DIR__ . '/../accesos_sucursales.php')) {
    require_once __DIR__ . '/../accesos_sucursales.php';
}

const DASH_ANCHOS = [3, 4, 6, 12];
const DASH_FORMATOS = ['moneda', 'entero', 'decimal', 'porcentaje'];
const DASH_MAX_WIDGETS = 50;
const DASH_MAX_ACCESOS = 20;

/** Error de validación con mensaje para el usuario (el endpoint responde 400). */
class DashValidacionException extends RuntimeException {}
/** Widget inexistente o sin permiso (el endpoint responde 403). */
class DashPermisoException extends RuntimeException {}

/** SELECT preparado → filas. $tipos vacío = sin parámetros. */
function dash_filas(mysqli $conexion, string $sql, string $tipos = '', array $params = []): array
{
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        throw new RuntimeException('dash_filas prepare: ' . mysqli_error($conexion));
    }
    if ($tipos !== '') {
        mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $filas = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $filas[] = $r;
    }
    mysqli_stmt_close($stmt);
    return $filas;
}

function dash_ejecutar(mysqli $conexion, string $sql, string $tipos, array $params): void
{
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        throw new RuntimeException('dash_ejecutar prepare: ' . mysqli_error($conexion));
    }
    mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    if (!mysqli_stmt_execute($stmt)) {
        $err = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new RuntimeException('dash_ejecutar: ' . $err);
    }
    mysqli_stmt_close($stmt);
}

/** tabla_id de conf__tablas por nombre (los ids de tabla son globales, no por empresa). */
function dash_tabla_id(mysqli $conexion, string $nombre): int
{
    static $cache = [];
    if (!isset($cache[$nombre])) {
        $f = dash_filas($conexion, "SELECT tabla_id FROM conf__tablas WHERE tabla_nombre = ? LIMIT 1", 's', [$nombre]);
        if (!$f) {
            throw new RuntimeException("Tabla $nombre sin fila en conf__tablas");
        }
        $cache[$nombre] = (int) $f[0]['tabla_id'];
    }
    return $cache[$nombre];
}

/** Filtro SQL de las sucursales del usuario ('' si tiene todas). Solo ints: se puede concatenar. */
function dash_filtro_sucursal(mysqli $conexion, array $ctx, string $columna): string
{
    if (!class_exists('AccesosSucursalesHelper')) {
        return '';
    }
    return AccesosSucursalesHelper::filtroSqlSucursal($conexion, $ctx['usuario_id'], $ctx['empresa_id'], $columna);
}

/** "?, ?, ?" para un IN con n elementos. */
function dash_marcadores(int $n): string
{
    return implode(',', array_fill(0, $n, '?'));
}

/**
 * Contexto del usuario en la empresa y el módulo. Lanza DashPermisoException si no tiene perfil
 * vigente en ese módulo de la empresa (salvo superadmin).
 */
function dash_contexto(mysqli $conexion, int $usuario_id, int $empresa_id, int $modulo_id): array
{
    $superadmin = in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true);

    $filas = dash_filas($conexion,
        "SELECT DISTINCT ep.empresa_perfil_id, ep.perfil_id_base
         FROM conf__usuarios_perfiles up
         JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = up.empresa_perfil_id
         WHERE up.usuario_id = ? AND ep.empresa_id = ? AND ep.modulo_id = ?
           AND up.tabla_estado_registro_id = 1 AND ep.tabla_estado_registro_id = 1
           AND ? BETWEEN up.fecha_inicio AND up.fecha_fin",
        'iiis', [$usuario_id, $empresa_id, $modulo_id, date('Y-m-d')]);

    if (!$filas && !$superadmin) {
        throw new DashPermisoException('No tenés acceso a este módulo en la empresa.');
    }

    $perfiles_base = [];
    foreach ($filas as $f) {
        if ((int) $f['perfil_id_base'] > 0) {
            $perfiles_base[(int) $f['perfil_id_base']] = true;
        }
    }

    $ctx = [
        'usuario_id'    => $usuario_id,
        'empresa_id'    => $empresa_id,
        'modulo_id'     => $modulo_id,
        'superadmin'    => $superadmin,
        'perfiles_base' => array_keys($perfiles_base),
        'hoy'           => date('Y-m-d'),
    ];
    $ctx['paginas'] = dash_paginas_disponibles($conexion, $ctx);
    return $ctx;
}

/**
 * Páginas activas del módulo que el usuario puede abrir en la empresa, sin las de bienes si la
 * empresa es de servicios. pagina_id => fila (pagina, url, icono_clase, es_acceso_directo, ...).
 */
function dash_paginas_disponibles(mysqli $conexion, array $ctx): array
{
    $permitidas = usuario_obtener_paginas_permitidas($conexion, $ctx['usuario_id'], $ctx['empresa_id']);
    if (!$permitidas) {
        return [];
    }
    $servicios = empresa_tipo_operacion($conexion, $ctx['empresa_id']) === 'SERVICIOS';

    $filas = dash_filas($conexion,
        "SELECT p.pagina_id, p.pagina, p.url, p.orden, p.requiere_bienes, p.es_acceso_directo,
                p.orden_acceso_directo, i.icono_clase
         FROM conf__paginas p
         LEFT JOIN conf__iconos i ON i.icono_id = p.icono_id
         WHERE p.modulo_id = ? AND p.tabla_estado_registro_id = 1 AND p.url <> ''",
        'i', [$ctx['modulo_id']]);

    $out = [];
    foreach ($filas as $p) {
        $id = (int) $p['pagina_id'];
        if (!isset($permitidas[$id]) || ($servicios && (int) $p['requiere_bienes'] === 1)) {
            continue;
        }
        $out[$id] = $p;
    }
    return $out;
}

/** Link a una página del módulo, con el mismo formato que arma el menú lateral. */
function dash_link(array $ctx, int $pagina_id, array $extra = []): ?string
{
    if (!isset($ctx['paginas'][$pagina_id])) {
        return null;
    }
    $qs = array_merge(['pagina_id' => $pagina_id, 'empresa_id' => $ctx['empresa_id'], 'modulo_id' => $ctx['modulo_id']], $extra);
    return $ctx['paginas'][$pagina_id]['url'] . '?' . http_build_query($qs);
}

/** Widgets activos del módulo con su página permitida (atajos no tienen página). widget_id => fila. */
function dash_widgets_permitidos(mysqli $conexion, array $ctx): array
{
    $filas = dash_filas($conexion,
        "SELECT widget_id, codigo, titulo, descripcion, tipo, handler, pagina_id, parametros_default,
                ancho_default, ttl_segundos
         FROM conf__dashboard_widgets
         WHERE modulo_id = ? AND tabla_estado_registro_id = 1
         ORDER BY widget_id",
        'i', [$ctx['modulo_id']]);

    $out = [];
    foreach ($filas as $w) {
        $pagina_id = $w['pagina_id'] === null ? 0 : (int) $w['pagina_id'];
        if ($pagina_id > 0 && !isset($ctx['paginas'][$pagina_id])) {
            continue;
        }
        if ($pagina_id === 0 && $w['tipo'] !== 'atajos') {
            continue;
        }
        $w['widget_id'] = (int) $w['widget_id'];
        $w['pagina_id'] = $pagina_id;
        $w['ancho_default'] = in_array((int) $w['ancho_default'], DASH_ANCHOS, true) ? (int) $w['ancho_default'] : 4;
        $out[$w['widget_id']] = $w;
    }
    return $out;
}

/**
 * Estado de cada widget dibujable (sin badges) para el usuario: personalizado o del perfil.
 * Devuelve ['personalizado' => bool, 'items' => [ [widget, orden, visible, ancho], ... ] ordenado].
 */
function dash_widgets_estado(mysqli $conexion, array $ctx, array $permitidos): array
{
    $dibujables = array_filter($permitidos, function ($w) {
        return $w['tipo'] !== 'badge';
    });

    $propias = dash_filas($conexion,
        "SELECT uw.widget_id, uw.orden, uw.visible, uw.ancho
         FROM conf__dashboard_usuarios_widgets uw
         JOIN conf__dashboard_widgets w ON w.widget_id = uw.widget_id
         WHERE uw.usuario_id = ? AND uw.empresa_id = ? AND w.modulo_id = ?",
        'iii', [$ctx['usuario_id'], $ctx['empresa_id'], $ctx['modulo_id']]);

    $estado = [];
    $personalizado = !empty($propias);
    if ($personalizado) {
        foreach ($propias as $r) {
            $estado[(int) $r['widget_id']] = ['orden' => (int) $r['orden'], 'visible' => (int) $r['visible'] === 1,
                                              'ancho' => $r['ancho'] === null ? null : (int) $r['ancho']];
        }
    } elseif ($ctx['perfiles_base']) {
        $ids = $ctx['perfiles_base'];
        $filas = dash_filas($conexion,
            "SELECT pw.widget_id, MIN(pw.orden) AS orden, MAX(pw.ancho) AS ancho
             FROM conf__dashboard_perfiles_widgets pw
             JOIN conf__dashboard_widgets w ON w.widget_id = pw.widget_id
             WHERE pw.perfil_id IN (" . dash_marcadores(count($ids)) . ") AND w.modulo_id = ?
             GROUP BY pw.widget_id",
            str_repeat('i', count($ids)) . 'i', array_merge($ids, [$ctx['modulo_id']]));
        foreach ($filas as $r) {
            $estado[(int) $r['widget_id']] = ['orden' => (int) $r['orden'], 'visible' => true,
                                              'ancho' => $r['ancho'] === null ? null : (int) $r['ancho']];
        }
    }
    // Sin layout propio ni del perfil (superadmin sin perfil, o perfiles de empresa que no salen de
    // un perfil base con layout cargado, como los de perfil_id_base 3 / 4): todos los permitidos.
    if (!$personalizado && !$estado) {
        foreach ($dibujables as $id => $w) {
            $estado[$id] = ['orden' => $id, 'visible' => true, 'ancho' => null];
        }
    }

    // Los permitidos que no figuran van al final, ocultos (en el modal se pueden activar).
    $items = [];
    foreach ($dibujables as $id => $w) {
        $e = $estado[$id] ?? ['orden' => 10000 + $id, 'visible' => false, 'ancho' => null];
        $ancho = in_array($e['ancho'], DASH_ANCHOS, true) ? $e['ancho'] : $w['ancho_default'];
        $items[] = ['widget' => $w, 'orden' => $e['orden'], 'visible' => $e['visible'], 'ancho' => $ancho];
    }
    usort($items, function ($a, $b) {
        return [$a['orden'], $a['widget']['widget_id']] <=> [$b['orden'], $b['widget']['widget_id']];
    });

    return ['personalizado' => $personalizado, 'items' => $items];
}

/**
 * Accesos directos del usuario: obligatorios (perfil base), candidatas ofrecidas y elegidos.
 * Sin personalizar, elegidos = todas las candidatas (el comportamiento previo de la barra superior).
 */
function dash_accesos_estado(mysqli $conexion, array $ctx): array
{
    $obligatorios = [];
    if ($ctx['perfiles_base']) {
        $ids = $ctx['perfiles_base'];
        $filas = dash_filas($conexion,
            "SELECT pagina_id, MIN(orden) AS orden
             FROM conf__perfiles_accesos_directos
             WHERE perfil_id IN (" . dash_marcadores(count($ids)) . ")
             GROUP BY pagina_id",
            str_repeat('i', count($ids)), $ids);
        foreach ($filas as $r) {
            if (isset($ctx['paginas'][(int) $r['pagina_id']])) {
                $obligatorios[(int) $r['pagina_id']] = (int) $r['orden'];
            }
        }
        asort($obligatorios);
    }

    $candidatas = [];
    foreach ($ctx['paginas'] as $id => $p) {
        if ((int) $p['es_acceso_directo'] === 1 && !isset($obligatorios[$id])) {
            $candidatas[$id] = $p;
        }
    }
    uasort($candidatas, function ($a, $b) {
        $oa = $a['orden_acceso_directo'] === null ? PHP_INT_MAX : (int) $a['orden_acceso_directo'];
        $ob = $b['orden_acceso_directo'] === null ? PHP_INT_MAX : (int) $b['orden_acceso_directo'];
        return [$oa, (int) $a['orden']] <=> [$ob, (int) $b['orden']];
    });

    $marca = dash_filas($conexion,
        "SELECT accesos_personalizados FROM conf__usuarios_inicio
         WHERE usuario_id = ? AND empresa_id = ? AND modulo_id = ?",
        'iii', [$ctx['usuario_id'], $ctx['empresa_id'], $ctx['modulo_id']]);
    $personalizado = !empty($marca) && (int) $marca[0]['accesos_personalizados'] === 1;

    if ($personalizado) {
        $elegidos = [];
        $filas = dash_filas($conexion,
            "SELECT pagina_id FROM conf__usuarios_accesos_directos
             WHERE usuario_id = ? AND empresa_id = ?
             ORDER BY orden, pagina_id",
            'ii', [$ctx['usuario_id'], $ctx['empresa_id']]);
        foreach ($filas as $r) {
            if (isset($candidatas[(int) $r['pagina_id']])) {
                $elegidos[] = (int) $r['pagina_id'];
            }
        }
    } else {
        $elegidos = array_keys($candidatas);
    }

    return [
        'personalizado' => $personalizado,
        'obligatorios'  => array_keys($obligatorios),
        'candidatas'    => array_keys($candidatas),
        'elegidos'      => $elegidos,
    ];
}

/** Fila de acceso para el front. */
function dash_acceso_item(array $ctx, int $pagina_id, bool $obligatorio, array $badges): array
{
    $p = $ctx['paginas'][$pagina_id];
    return [
        'pagina_id'       => $pagina_id,
        'titulo'          => preg_replace('/^Gesti[oó]n\s*-\s*/iu', '', (string) $p['pagina']),
        'url'             => dash_link($ctx, $pagina_id),
        'icono'           => (string) ($p['icono_clase'] ?? ''),
        'obligatorio'     => $obligatorio,
        'badge_widget_id' => $badges[$pagina_id] ?? null,
    ];
}

/** pagina_id => widget_id de los badges permitidos. */
function dash_badges_mapa(array $permitidos): array
{
    $out = [];
    foreach ($permitidos as $w) {
        if ($w['tipo'] === 'badge') {
            $out[$w['pagina_id']] = $w['widget_id'];
        }
    }
    return $out;
}

/** Accesos visibles en orden (obligatorios primero). La usan el dashboard y la barra superior. */
function dash_accesos_visibles(mysqli $conexion, array $ctx, ?array $permitidos = null): array
{
    $permitidos = $permitidos ?? dash_widgets_permitidos($conexion, $ctx);
    $badges = dash_badges_mapa($permitidos);
    $estado = dash_accesos_estado($conexion, $ctx);
    $out = [];
    foreach ($estado['obligatorios'] as $id) {
        $out[] = dash_acceso_item($ctx, $id, true, $badges);
    }
    foreach ($estado['elegidos'] as $id) {
        $out[] = dash_acceso_item($ctx, $id, false, $badges);
    }
    return $out;
}

/** Acción layout: lo que se dibuja al abrir la pantalla. */
function dash_layout(mysqli $conexion, array $ctx): array
{
    $permitidos = dash_widgets_permitidos($conexion, $ctx);
    $widgets = dash_widgets_estado($conexion, $ctx, $permitidos);
    $accesos_estado = dash_accesos_estado($conexion, $ctx);

    $lista = [];
    $orden = 0;
    foreach ($widgets['items'] as $it) {
        if (!$it['visible']) {
            continue;
        }
        $w = $it['widget'];
        $lista[] = [
            'widget_id' => $w['widget_id'],
            'codigo'    => $w['codigo'],
            'titulo'    => $w['titulo'],
            'ayuda'     => (string) ($w['descripcion'] ?? ''),
            'tipo'      => $w['tipo'],
            'ancho'     => $it['ancho'],
            'orden'     => ++$orden,
            'link'      => $w['pagina_id'] > 0 ? dash_link($ctx, $w['pagina_id']) : null,
        ];
    }

    return [
        'widgets'       => $lista,
        'accesos'       => dash_accesos_visibles($conexion, $ctx, $permitidos),
        'personalizado' => ['widgets' => $widgets['personalizado'], 'accesos' => $accesos_estado['personalizado']],
    ];
}

/** Acción config: todo lo que el modal puede mostrar y elegir. */
function dash_config(mysqli $conexion, array $ctx): array
{
    $permitidos = dash_widgets_permitidos($conexion, $ctx);
    $widgets = dash_widgets_estado($conexion, $ctx, $permitidos);
    $accesos = dash_accesos_estado($conexion, $ctx);
    $badges = dash_badges_mapa($permitidos);

    $lista_w = [];
    foreach ($widgets['items'] as $it) {
        $lista_w[] = [
            'widget_id' => $it['widget']['widget_id'],
            'titulo'    => $it['widget']['titulo'],
            'ayuda'     => (string) ($it['widget']['descripcion'] ?? ''),
            'tipo'      => $it['widget']['tipo'],
            'visible'   => $it['visible'],
            'ancho'     => $it['ancho'],
        ];
    }
    $item = function ($id, $oblig) use ($ctx, $badges) {
        return dash_acceso_item($ctx, $id, $oblig, $badges);
    };

    return [
        'widgets'       => $lista_w,
        'accesos'       => [
            'obligatorios' => array_map(function ($id) use ($item) { return $item($id, true); }, $accesos['obligatorios']),
            'disponibles'  => array_map(function ($id) use ($item) { return $item($id, false); }, $accesos['candidatas']),
            'elegidos'     => $accesos['elegidos'],
        ],
        'anchos_validos' => DASH_ANCHOS,
        // Los accesos elegidos solo se ven en el widget de atajos: sin él activo, no se ofrecen.
        'accesos_activos' => (bool) array_filter($permitidos, function ($w) { return $w['tipo'] === 'atajos'; }),
        'personalizado'  => ['widgets' => $widgets['personalizado'], 'accesos' => $accesos['personalizado']],
    ];
}

/**
 * Acción guardar. $widgets = [['widget_id', 'visible', 'ancho'], ...] en el orden elegido;
 * $accesos = [pagina_id, ...] en orden. Todo id tiene que pertenecer a lo que se le ofrece al
 * usuario; si no, no se guarda nada. Los widgets permitidos que no vienen quedan ocultos.
 */
function dash_guardar(mysqli $conexion, array $ctx, array $widgets, array $accesos): void
{
    if (count($widgets) > DASH_MAX_WIDGETS || count($accesos) > DASH_MAX_ACCESOS) {
        throw new DashValidacionException('Demasiados elementos.');
    }
    $permitidos = dash_widgets_permitidos($conexion, $ctx);
    $dibujables = array_filter($permitidos, function ($w) {
        return $w['tipo'] !== 'badge';
    });
    $estado_accesos = dash_accesos_estado($conexion, $ctx);
    $candidatas = array_flip($estado_accesos['candidatas']);

    $filas_w = [];
    foreach ($widgets as $w) {
        $id = is_array($w) ? (int) ($w['widget_id'] ?? 0) : 0;
        if (!isset($dibujables[$id]) || isset($filas_w[$id])) {
            throw new DashValidacionException("Widget no válido: $id.");
        }
        $ancho = (int) ($w['ancho'] ?? 0);
        if (!in_array($ancho, DASH_ANCHOS, true)) {
            throw new DashValidacionException("Ancho no válido para el widget $id.");
        }
        $filas_w[$id] = ['visible' => !empty($w['visible']) ? 1 : 0, 'ancho' => $ancho];
    }
    foreach ($dibujables as $id => $w) {
        if (!isset($filas_w[$id])) {
            $filas_w[$id] = ['visible' => 0, 'ancho' => $w['ancho_default']];
        }
    }

    $filas_a = [];
    foreach ($accesos as $pid) {
        $pid = (int) $pid;
        if (!isset($candidatas[$pid]) || in_array($pid, $filas_a, true)) {
            throw new DashValidacionException("Acceso directo no válido: $pid.");
        }
        $filas_a[] = $pid;
    }

    $uid = $ctx['usuario_id'];
    $eid = $ctx['empresa_id'];
    mysqli_begin_transaction($conexion);
    try {
        // Solo se reemplazan los permitidos: lo guardado de widgets/páginas sin permiso queda para
        // cuando lo recupere.
        $ids_w = array_keys($dibujables);
        if ($ids_w) {
            dash_ejecutar($conexion,
                "DELETE FROM conf__dashboard_usuarios_widgets
                 WHERE usuario_id = ? AND empresa_id = ? AND widget_id IN (" . dash_marcadores(count($ids_w)) . ")",
                'ii' . str_repeat('i', count($ids_w)), array_merge([$uid, $eid], $ids_w));
        }

        $stmt = mysqli_prepare($conexion,
            "INSERT INTO conf__dashboard_usuarios_widgets (usuario_id, empresa_id, widget_id, orden, visible, ancho)
             VALUES (?, ?, ?, ?, ?, ?)");
        $orden = 0;
        foreach ($filas_w as $wid => $f) {
            $orden++;
            mysqli_stmt_bind_param($stmt, 'iiiiii', $uid, $eid, $wid, $orden, $f['visible'], $f['ancho']);
            mysqli_stmt_execute($stmt);
        }
        mysqli_stmt_close($stmt);

        $ids_a = array_merge($estado_accesos['candidatas'], $estado_accesos['obligatorios']);
        if ($ids_a) {
            dash_ejecutar($conexion,
                "DELETE FROM conf__usuarios_accesos_directos
                 WHERE usuario_id = ? AND empresa_id = ? AND pagina_id IN (" . dash_marcadores(count($ids_a)) . ")",
                'ii' . str_repeat('i', count($ids_a)), array_merge([$uid, $eid], $ids_a));
        }
        if ($filas_a) {
            $stmt = mysqli_prepare($conexion,
                "INSERT INTO conf__usuarios_accesos_directos (usuario_id, empresa_id, pagina_id, orden) VALUES (?, ?, ?, ?)");
            foreach ($filas_a as $i => $pid) {
                $orden = $i + 1;
                mysqli_stmt_bind_param($stmt, 'iiii', $uid, $eid, $pid, $orden);
                mysqli_stmt_execute($stmt);
            }
            mysqli_stmt_close($stmt);
        }

        dash_ejecutar($conexion,
            "INSERT INTO conf__usuarios_inicio (usuario_id, empresa_id, modulo_id, accesos_personalizados)
             VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE accesos_personalizados = 1",
            'iii', [$uid, $eid, $ctx['modulo_id']]);

        mysqli_commit($conexion);
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        throw $e;
    }
}

/** Acción restablecer: vuelve a los valores del perfil en este módulo y empresa. */
function dash_restablecer(mysqli $conexion, array $ctx): void
{
    $uid = $ctx['usuario_id'];
    $eid = $ctx['empresa_id'];
    $mid = $ctx['modulo_id'];
    mysqli_begin_transaction($conexion);
    try {
        dash_ejecutar($conexion,
            "DELETE uw FROM conf__dashboard_usuarios_widgets uw
             JOIN conf__dashboard_widgets w ON w.widget_id = uw.widget_id
             WHERE uw.usuario_id = ? AND uw.empresa_id = ? AND w.modulo_id = ?",
            'iii', [$uid, $eid, $mid]);
        dash_ejecutar($conexion,
            "DELETE ua FROM conf__usuarios_accesos_directos ua
             JOIN conf__paginas p ON p.pagina_id = ua.pagina_id
             WHERE ua.usuario_id = ? AND ua.empresa_id = ? AND p.modulo_id = ?",
            'iii', [$uid, $eid, $mid]);
        dash_ejecutar($conexion,
            "DELETE FROM conf__usuarios_inicio WHERE usuario_id = ? AND empresa_id = ? AND modulo_id = ?",
            'iii', [$uid, $eid, $mid]);
        mysqli_commit($conexion);
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        throw $e;
    }
}

/**
 * Carga el handler de un widget. El nombre sale del catálogo (no del request) y además se valida
 * contra el patrón, así no puede apuntar fuera de core/dashboard/widgets/.
 */
function dash_cargar_handler(string $handler): callable
{
    static $cache = [];
    if (isset($cache[$handler])) {
        return $cache[$handler];
    }
    if (!preg_match('/^[a-z0-9_]{1,60}$/', $handler)) {
        throw new RuntimeException("Handler inválido: $handler");
    }
    $ruta = __DIR__ . '/widgets/' . $handler . '.php';
    if (!is_file($ruta)) {
        throw new RuntimeException("Handler inexistente: $handler");
    }
    // Los modelos que incluyen los handlers hacen `$conexion = $conn;` a nivel de archivo: como se
    // incluyen desde esta función, tienen que ver las globales o quedarían indefinidas.
    global $conn, $conexion;
    $fn = require $ruta;
    if (!is_callable($fn)) {
        throw new RuntimeException("El handler $handler no devuelve una función");
    }
    return $cache[$handler] = $fn;
}

/** Clave de caché: parámetros + sucursales a las que el usuario tiene acceso. */
function dash_cache_clave(mysqli $conexion, array $ctx, array $params): string
{
    $suc = 'todas';
    if (class_exists('AccesosSucursalesHelper')) {
        $acc = AccesosSucursalesHelper::obtenerAccesos($conexion, $ctx['usuario_id'], $ctx['empresa_id']);
        if (empty($acc['tiene_acceso_total'])) {
            $ids = array_map('intval', array_keys($acc['sucursales'] ?? []));
            sort($ids);
            $suc = implode(',', $ids);
        }
    }
    ksort($params);
    return sha1(json_encode([$params, $suc, $ctx['hoy']]));
}

function dash_cache_leer(mysqli $conexion, int $empresa_id, int $widget_id, string $clave): ?array
{
    $filas = dash_filas($conexion,
        "SELECT payload, calculado_en FROM conf__dashboard_cache
         WHERE empresa_id = ? AND widget_id = ? AND clave = ? AND expira_en > ?",
        'iiss', [$empresa_id, $widget_id, $clave, date('Y-m-d H:i:s')]);
    if (!$filas) {
        return null;
    }
    $data = json_decode($filas[0]['payload'], true);
    return is_array($data) ? ['data' => $data, 'calculado_en' => $filas[0]['calculado_en']] : null;
}

function dash_cache_guardar(mysqli $conexion, int $empresa_id, int $widget_id, string $clave, array $data, int $ttl): void
{
    $ahora = date('Y-m-d H:i:s');
    $expira = date('Y-m-d H:i:s', time() + $ttl);
    $payload = json_encode($data, JSON_UNESCAPED_UNICODE);
    dash_ejecutar($conexion,
        "INSERT INTO conf__dashboard_cache (empresa_id, widget_id, clave, payload, calculado_en, expira_en)
         VALUES (?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE payload = VALUES(payload), calculado_en = VALUES(calculado_en), expira_en = VALUES(expira_en)",
        'iissss', [$empresa_id, $widget_id, $clave, $payload, $ahora, $expira]);
    // Purga en el camino de escritura; con LIMIT para no frenar el request.
    dash_ejecutar($conexion, "DELETE FROM conf__dashboard_cache WHERE expira_en < ? LIMIT 200", 's', [$ahora]);
}

/** Parámetros por defecto del widget (JSON del catálogo). */
function dash_params(array $w): array
{
    $p = $w['parametros_default'] ? json_decode($w['parametros_default'], true) : [];
    return is_array($p) ? $p : [];
}

/**
 * Acción widget: valida permiso, usa la caché si el widget la tiene y ejecuta el handler.
 * Devuelve ['data' => ..., 'meta' => ['calculado_en', 'desde_cache']].
 */
function dash_ejecutar_widget(mysqli $conexion, array $ctx, int $widget_id, bool $forzar = false, ?array $permitidos = null): array
{
    $permitidos = $permitidos ?? dash_widgets_permitidos($conexion, $ctx);
    if (!isset($permitidos[$widget_id]) || $permitidos[$widget_id]['tipo'] === 'atajos') {
        throw new DashPermisoException('Widget no disponible.');
    }
    $w = $permitidos[$widget_id];
    $params = dash_params($w);
    $ttl = (int) $w['ttl_segundos'];

    $clave = null;
    if ($ttl > 0) {
        $clave = dash_cache_clave($conexion, $ctx, $params);
        if (!$forzar && ($c = dash_cache_leer($conexion, $ctx['empresa_id'], $widget_id, $clave))) {
            return ['data' => $c['data'], 'meta' => ['calculado_en' => $c['calculado_en'], 'desde_cache' => true]];
        }
    }

    $fn = dash_cargar_handler($w['handler']);
    $ctx_w = $ctx + ['widget' => $w];
    $data = $fn($conexion, $ctx_w, $params);
    if (!is_array($data)) {
        throw new RuntimeException("El handler {$w['handler']} no devolvió un arreglo");
    }
    $data['tipo'] = $data['tipo'] ?? $w['tipo'];
    if (!isset($data['link']) && $w['pagina_id'] > 0) {
        $data['link'] = dash_link($ctx, $w['pagina_id']);
    }

    if ($ttl > 0) {
        dash_cache_guardar($conexion, $ctx['empresa_id'], $widget_id, $clave, $data, $ttl);
    }
    return ['data' => $data, 'meta' => ['calculado_en' => date('Y-m-d H:i:s'), 'desde_cache' => false]];
}

/**
 * Acción badges: número de cada acceso visible que tiene badge. Un badge que falla se omite (se
 * loguea) para no tirar los demás.
 */
function dash_badges(mysqli $conexion, array $ctx): array
{
    $permitidos = dash_widgets_permitidos($conexion, $ctx);
    $mapa = dash_badges_mapa($permitidos);
    $visibles = array_column(dash_accesos_visibles($conexion, $ctx, $permitidos), 'pagina_id');
    $out = [];
    foreach ($visibles as $pid) {
        if (!isset($mapa[$pid])) {
            continue;
        }
        try {
            $r = dash_ejecutar_widget($conexion, $ctx, $mapa[$pid], false, $permitidos);
            $out[(string) $pid] = (int) ($r['data']['valor'] ?? 0);
        } catch (Throwable $e) {
            error_log("=== dash_badges pagina $pid: " . $e->getMessage() . ' ===');
        }
    }
    return $out;
}
