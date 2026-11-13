<?php
/**
 * core/contexto.php
 * Gestor de contexto de sesión seguro para Empresa y Módulo activo.
 * 
 * Principios de Ciberseguridad aplicados:
 *  - Ocultamiento de identificadores internos de la URL (Anti Information Disclosure).
 *  - Aislamiento estricto de Tenant: nunca se acepta una empresa o módulo si el usuario
 *    no posee un perfil asignado, activo y vigente.
 *  - Mitigación de IDOR / Tampering: la sesión es la única fuente de verdad autoritativa.
 *  - Protección CSRF en los cambios de contexto para evitar Session / Context Hijacking.
 *  - Auditoría de seguridad: se registran intentos de acceso no autorizados con IP y usuario.
 */

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/permisos.php';
require_once __DIR__ . '/auditoria.php';

/**
 * Valida si un usuario tiene acceso activo y vigente a una combinación empresa_id + modulo_id.
 * 
 * @return array{id: int, nombre: string, url: string}|null Devuelve los datos del módulo si es válido, o null si está denegado.
 */
function contexto_validar_acceso(mysqli $conexion, int $usuario_id, int $empresa_id, int $modulo_id): ?array
{
    if ($usuario_id <= 0 || $empresa_id <= 0 || $modulo_id <= 0) {
        return null;
    }

    $empresas_modulos = usuario_obtener_empresas_modulos($conexion, $usuario_id);
    if (!isset($empresas_modulos[$empresa_id])) {
        return null;
    }

    foreach ($empresas_modulos[$empresa_id]['modulos'] as $mod) {
        if ((int) $mod['id'] === $modulo_id) {
            return [
                'id' => (int) $mod['id'],
                'nombre' => (string) $mod['nombre'],
                'url' => (string) $mod['url'],
            ];
        }
    }

    return null;
}

/**
 * Fija en sesión el contexto de trabajo de forma segura.
 */
function contexto_fijar(int $empresa_id, int $modulo_id): void
{
    $_SESSION['empresa_id'] = $empresa_id;
    $_SESSION['modulo_id'] = $modulo_id;
}

/**
 * Normaliza la ruta del módulo asegurando que contenga el prefijo modules/ si corresponde.
 */
function contexto_normalizar_url_modulo(string $ruta): string
{
    $ruta = trim($ruta);
    if (strpos($ruta, 'modules/') === false) {
        $ruta = 'modules/' . ltrim($ruta, '/');
    }
    if (substr($ruta, -9) === 'index.php') {
        $ruta = substr($ruta, 0, -9);
    }
    return rtrim($ruta, '/') . '/';
}

// ---------------------------------------------------------------------------------------------------
// Endpoint de ejecución directa: Procesamiento de cambio de Empresa / Módulo
// ---------------------------------------------------------------------------------------------------

// Solo actuar como controlador si se invoca directamente este script vía HTTP
$script_ejecutado = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
if (substr($script_ejecutado, -17) === 'core/contexto.php') {
    
    // 1. Validar autenticación
    if (empty($_SESSION['logueado']) || $_SESSION['logueado'] !== true || empty($_SESSION['usuario_id'])) {
        header("Location: " . url('login.php'));
        exit;
    }

    $usuario_id = (int) $_SESSION['usuario_id'];
    $empresa_id = filter_input(INPUT_GET, 'empresa_id', FILTER_VALIDATE_INT) 
                  ?: filter_input(INPUT_POST, 'empresa_id', FILTER_VALIDATE_INT) ?: 0;
    $modulo_id  = filter_input(INPUT_GET, 'modulo_id', FILTER_VALIDATE_INT) 
                  ?: filter_input(INPUT_POST, 'modulo_id', FILTER_VALIDATE_INT) ?: 0;
    $token      = $_POST['csrf_token'] ?? $_GET['token'] ?? null;

    // 2. Validar token CSRF para evitar cambios forzados de contexto por enlaces externos maliciosos
    if (!csrf_valido(is_string($token) ? $token : null)) {
        auditoria_seguridad($conexion, 'CSRF_INVALIDO', "Intento de cambio de contexto sin token CSRF válido (Empresa solicitada: {$empresa_id}, Módulo: {$modulo_id})", $empresa_id);
        error_log(sprintf(
            "ALERTA_SEGURIDAD [CSRF_INVALIDO] Usuario ID %d intentó cambiar contexto sin token válido desde IP %s.",
            $usuario_id,
            $_SERVER['REMOTE_ADDR'] ?? 'desconocida'
        ));
        http_response_code(403);
        echo "<!DOCTYPE html><html lang='es'><head><meta charset='utf-8'><title>Error de Seguridad</title></head>";
        echo "<body style='font-family:sans-serif;text-align:center;padding:50px;'>";
        echo "<h3>Solicitud no válida o expirada</h3><p>La sesión de seguridad expiró. <a href='" . htmlspecialchars(url('index.php')) . "'>Volver al inicio</a></p>";
        echo "</body></html>";
        exit;
    }

    // 3. Validar autorización estricta sobre la empresa y el módulo solicitados
    $modulo_info = contexto_validar_acceso($conexion, $usuario_id, $empresa_id, $modulo_id);

    if ($modulo_info === null) {
        auditoria_seguridad($conexion, 'ACCESO_DENEGADO', "Usuario ID {$usuario_id} intentó forzar acceso a Empresa ID {$empresa_id} y Módulo ID {$modulo_id} sin autorización", $empresa_id);
        error_log(sprintf(
            "ALERTA_SEGURIDAD [ACCESO_NO_AUTORIZADO] Usuario ID %d intentó forzar acceso a Empresa ID %d y Módulo ID %d desde IP %s.",
            $usuario_id,
            $empresa_id,
            $modulo_id,
            $_SERVER['REMOTE_ADDR'] ?? 'desconocida'
        ));
        header("Location: " . url('index.php?error=acceso_denegado'));
        exit;
    }

    // 4. Fijar contexto en sesión de forma autoritativa
    contexto_fijar($empresa_id, $modulo_id);
    auditoria_cambio_contexto($conexion, $usuario_id, $empresa_id, $modulo_id);

    // 5. Redireccionar con HTTP 303 (See Other) a la URL limpia del módulo (sin ningún query param)
    $url_limpia = url(contexto_normalizar_url_modulo($modulo_info['url']));
    header("Location: " . $url_limpia, true, 303);
    exit;
}

