<?php
/**
 * core/ajax_guard.php
 * Control de acceso común para los endpoints *_ajax.php.
 *
 * Uso típico, como primera instrucción del _ajax.php (antes de cualquier echo):
 *
 *   require_once __DIR__ . '/../../core/ajax_guard.php';
 *   $ctx = ajax_guard();                          // sesión + empresa (empresa_idx)
 *   $ctx = ajax_guard(['empresa' => false]);      // solo sesión (módulo conf, catálogos globales)
 *   $ctx = ajax_guard(['params' => ['empresa_id', 'empresa_idx']]);  // tenant en otro parámetro
 *   $ctx = ajax_guard(['pagina_idx' => 402]);     // además, acceso a la página
 *   $ctx = ajax_guard(['modulo_id' => 1, 'paginas_url' => ['usuarios.php', 'perfiles_usuarios.php']]);
 *                                                 // acceso a alguna de las páginas que lo usan
 *   $ctx = ajax_guard(['empresa' => false, 'solo_superadmin' => true]);  // legacy sin pantalla
 *
 * Devuelve ['usuario_id' => int, 'empresa_id' => int|null]. Si algo falla responde JSON
 * con código HTTP 401/400/403 y corta la ejecución.
 *
 * Empresa: se toma del primer parámetro de request de la lista 'params' que venga > 0; si no
 * viene ninguno, de $_SESSION['empresa_id'] (la fija header1.php al abrir la página). Nunca hay
 * empresa por defecto. Si el parámetro no vino en el request, se completa en $_GET/$_POST/
 * $_REQUEST con la empresa validada, para que el código existente que hace
 * `$_GET['empresa_idx'] ?? 2` use la empresa de la sesión y no la 2.
 */

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/permisos.php';

function ajax_guard_error(int $http_code, string $codigo, string $mensaje): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        http_response_code($http_code);
        header('Content-Type: application/json; charset=utf-8');
    }
    error_log("=== ajax_guard RECHAZO [$codigo] " . ($_SERVER['SCRIPT_NAME'] ?? '') . " : $mensaje ===");
    // Incluye las dos convenciones de respuesta del proyecto para que cualquier JS lo muestre.
    echo json_encode([
        'success' => false,
        'resultado' => false,
        'data' => null,
        'message' => $mensaje,
        'error' => $mensaje,
        'codigo' => $codigo,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

/** Exige sesión iniciada por login.php. Devuelve el usuario_id. */
function ajax_guard_sesion(): int
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $usuario_id = (int) ($_SESSION['usuario_id'] ?? 0);
    if (empty($_SESSION['logueado']) || $_SESSION['logueado'] !== true || $usuario_id <= 0) {
        ajax_guard_error(401, 'SESSION_EXPIRED', 'Sesión expirada o no iniciada. Vuelva a ingresar al sistema.');
    }
    return $usuario_id;
}

/** True si el usuario tiene al menos un perfil vigente en la empresa (o es superadmin). */
function ajax_guard_usuario_tiene_empresa(mysqli $conexion, int $usuario_id, int $empresa_id): bool
{
    if ($empresa_id <= 0) {
        return false;
    }
    if (in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true)) {
        return true;
    }
    return !empty(usuario_obtener_perfiles_empresa($conexion, $usuario_id, $empresa_id));
}

/** Corta con 403 si el usuario no tiene acceso a la empresa indicada. */
function ajax_guard_exigir_empresa(mysqli $conexion, int $usuario_id, int $empresa_id): void
{
    if (!ajax_guard_usuario_tiene_empresa($conexion, $usuario_id, $empresa_id)) {
        ajax_guard_error(403, 'ACCESS_DENIED_EMPRESA', 'No tiene permisos sobre la empresa seleccionada.');
    }
}

/**
 * Resuelve y valida la empresa activa del request.
 * @param string[] $params nombres de parámetro que llevan la empresa, en orden de prioridad
 */
function ajax_guard_empresa(mysqli $conexion, int $usuario_id, array $params = ['empresa_idx']): int
{
    $empresa_id = 0;
    foreach ($params as $p) {
        $valor = (int) ($_GET[$p] ?? $_POST[$p] ?? 0);
        if ($valor > 0) {
            $empresa_id = $valor;
            break;
        }
    }
    if ($empresa_id <= 0) {
        $empresa_id = (int) ($_SESSION['empresa_id'] ?? 0);
    }
    if ($empresa_id <= 0) {
        ajax_guard_error(400, 'INVALID_TENANT', 'Empresa no especificada. Vuelva a abrir la página desde el menú.');
    }

    ajax_guard_exigir_empresa($conexion, $usuario_id, $empresa_id);

    foreach ($params as $p) {
        if ((int) ($_GET[$p] ?? $_POST[$p] ?? 0) <= 0) {
            $_GET[$p] = $_POST[$p] = $_REQUEST[$p] = (string) $empresa_id;
        }
    }
    return $empresa_id;
}

/** Corta con 403 si el usuario no tiene acceso a la página en la empresa. */
function ajax_guard_pagina(mysqli $conexion, int $usuario_id, int $empresa_id, int $pagina_id): void
{
    if (!usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_id, $pagina_id)) {
        ajax_guard_error(403, 'ACCESS_DENIED', 'No tiene permisos para esta página en la empresa seleccionada.');
    }
}

/**
 * Corta con 403 salvo que el usuario tenga acceso a alguna de las páginas configuradas en
 * conf__paginas con esas urls dentro del módulo. Para endpoints que se llaman desde más de una
 * pantalla o cuyo pagina_id difiere entre entornos. Si ninguna url está configurada como página,
 * solo pasa el superadmin (endpoint sin pantalla en el menú = herramienta interna).
 * @param string[] $urls
 */
function ajax_guard_paginas_url(mysqli $conexion, int $usuario_id, int $empresa_id, int $modulo_id, array $urls): void
{
    if (in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true)) {
        return;
    }
    $permitidas = usuario_obtener_paginas_permitidas($conexion, $usuario_id, $empresa_id);
    foreach ($urls as $url) {
        // Puede haber más de una página activa con la misma url: alcanza con una permitida.
        $sql ="SELECT pagina_id FROM conf__paginas WHERE modulo_id = ? AND url = ? AND tabla_estado_registro_id = 1";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "is", $modulo_id, $url);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($row = mysqli_fetch_assoc($res)) {
            if (isset($permitidas[(int) $row['pagina_id']])) {
                mysqli_stmt_close($stmt);
                return;
            }
        }
        mysqli_stmt_close($stmt);
    }
    ajax_guard_error(403, 'ACCESS_DENIED', 'No tiene permisos para esta funcionalidad.');
}

/**
 * Punto de entrada. Opciones:
 *  - 'empresa'         (bool, default true): resolver y validar la empresa.
 *  - 'params'          (string[], default ['empresa_idx']): parámetros que llevan la empresa.
 *  - 'pagina_idx'      (int|null): exige acceso a esa página (requiere empresa).
 *  - 'paginas_url'     (string[]) + 'modulo_id' (int): exige acceso a alguna de esas páginas
 *                      (ver ajax_guard_paginas_url; requiere empresa).
 *  - 'solo_superadmin' (bool): solo USUARIOS_ACCESO_TOTAL (endpoints legacy sin pantalla).
 */
function ajax_guard(array $opciones = []): array
{
    global $conn;

    $usuario_id = ajax_guard_sesion();
    $empresa_id = null;

    if (!empty($opciones['solo_superadmin']) && !in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true)) {
        ajax_guard_error(403, 'ACCESS_DENIED', 'Funcionalidad restringida al administrador del sistema.');
    }

    if ($opciones['empresa'] ?? true) {
        $empresa_id = ajax_guard_empresa($conn, $usuario_id, $opciones['params'] ?? ['empresa_idx']);
        if (!empty($opciones['pagina_idx'])) {
            ajax_guard_pagina($conn, $usuario_id, $empresa_id, (int) $opciones['pagina_idx']);
        }
        if (!empty($opciones['paginas_url'])) {
            ajax_guard_paginas_url($conn, $usuario_id, $empresa_id, (int) ($opciones['modulo_id'] ?? 0), $opciones['paginas_url']);
        }
    }

    return ['usuario_id' => $usuario_id, 'empresa_id' => $empresa_id];
}
