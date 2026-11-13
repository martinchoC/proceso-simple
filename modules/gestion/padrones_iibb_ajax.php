<?php
declare(strict_types=1);
// Sesión, empresa y acceso a la página validados antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 115]);

/**
 * padrones_iibb_ajax.php — Padrones IIBB (página 115).
 * Respuesta: {success: bool, data: ..., message: string}
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/padrones_iibb_model.php';

header('Content-Type: application/json; charset=utf-8');
ob_start();

function padResponder(bool $ok, $data = null, string $mensaje = '', int $http = 200): void
{
    if (ob_get_length()) ob_clean();
    http_response_code($http);
    echo json_encode(['success' => $ok, 'data' => $data, 'message' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

$pagina_idx = 115;
$empresa_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];
$conexion = $conn;
$accion = (string)($_POST['accion'] ?? $_GET['accion'] ?? '');

try {
    switch ($accion) {
        case 'maestros':
            // Función activa de la página y asignada al perfil del usuario
            $importar = usuario_puede_funcion($conexion, $usuario_id, $empresa_id, $pagina_idx, 'importar');
            padResponder(true, ['jurisdicciones' => padJurisdicciones($conexion, $empresa_id), 'puede_importar' => $importar,
                                'max_upload' => ini_get('upload_max_filesize')]);
            break;

        case 'listar':
            padResponder(true, padListarImportaciones($conexion, $empresa_id));
            break;

        case 'importar':
            $ok = usuario_puede_funcion($conexion, $usuario_id, $empresa_id, $pagina_idx, 'importar');
            if (!$ok) padResponder(false, null, 'Acción no habilitada', 403);
            $r = padImportar($conexion, $empresa_id, $usuario_id, (int)($_POST['jurisdiccion_id'] ?? 0), $_FILES['archivo'] ?? []);
            $vig = $r['periodo'] ?: ($r['f_desde'] ? "{$r['f_desde']} a {$r['f_hasta']}" : '');
            padResponder(true, $r, "Padrón $vig: {$r['cargadas']} CUIT cargados, {$r['rechazadas']} líneas rechazadas.");
            break;

        case 'consultar_cuit':
            padResponder(true, padConsultarCuit($conexion, $empresa_id, (int)($_GET['jurisdiccion_id'] ?? 0),
                (string)($_GET['periodo'] ?? ''), (string)($_GET['cuit'] ?? '')));
            break;

        case 'clientes':
            padResponder(true, padClientes($conexion, $empresa_id, (int)($_GET['jurisdiccion_id'] ?? 0), (string)($_GET['periodo'] ?? '')));
            break;

        default:
            padResponder(false, null, 'Acción no reconocida', 400);
    }
} catch (mysqli_sql_exception $e) {
    error_log('[padrones_iibb_ajax] ' . $accion . ' SQL: ' . $e->getMessage());
    padResponder(false, null, 'Error de base de datos', 500);
} catch (Throwable $e) {
    error_log('[padrones_iibb_ajax] ' . $accion . ': ' . $e->getMessage());
    padResponder(false, null, $e->getMessage(), 400);
}
