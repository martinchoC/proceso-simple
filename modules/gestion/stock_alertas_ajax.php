<?php
declare(strict_types=1);
// Sesión, empresa y acceso a la página validados antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 113]);

/**
 * stock_alertas_ajax.php — Alertas de reposición (página 113).
 * Respuesta: {success: bool, data: ..., message: string}
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/stock_alertas_model.php';

header('Content-Type: application/json; charset=utf-8');
ob_start();

function salResponder(bool $ok, $data = null, string $mensaje = '', int $http = 200): void
{
    if (ob_get_length()) ob_clean();
    http_response_code($http);
    echo json_encode(['success' => $ok, 'data' => $data, 'message' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

$pagina_idx = 113;
$empresa_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];
$conexion = $conn;
$accion = (string)($_POST['accion'] ?? $_GET['accion'] ?? '');

try {
    switch ($accion) {
        case 'listar':
            $vista = ($_GET['vista'] ?? 'abiertas') === 'historial' ? 'historial' : 'abiertas';
            salResponder(true, salListar($conexion, $empresa_id, $pagina_idx, $usuario_id, $vista,
                (int)($_GET['boca_id'] ?? 0), (int)($_GET['dias'] ?? 30)));
            break;

        case 'obtener_bocas':
            salResponder(true, salBocas($conexion, $empresa_id, $usuario_id));
            break;

        case 'contar_abiertas':
            salResponder(true, salContarAbiertas($conexion, $empresa_id, $usuario_id));
            break;

        case 'ejecutar_accion':
            $nombre = salEjecutarAccion($conexion, $empresa_id, $usuario_id, $pagina_idx,
                (int)($_POST['stock_alerta_id'] ?? 0), (string)($_POST['accion_js'] ?? ''));
            salResponder(true, null, "$nombre: listo");
            break;

        default:
            salResponder(false, null, 'Acción no reconocida', 400);
    }
} catch (mysqli_sql_exception $e) {
    error_log('[stock_alertas_ajax] ' . $accion . ' SQL: ' . $e->getMessage());
    salResponder(false, null, 'Error de base de datos', 500);
} catch (InvalidArgumentException | RuntimeException $e) {
    salResponder(false, null, $e->getMessage(), 400);
} catch (Throwable $e) {
    error_log('[stock_alertas_ajax] ' . $accion . ': ' . $e->getMessage());
    salResponder(false, null, 'Error interno del servidor', 500);
}
