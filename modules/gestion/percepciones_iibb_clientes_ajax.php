<?php
declare(strict_types=1);
// Sesión, empresa y acceso a alguna de las pantallas de clientes validados antes de cualquier otra cosa.
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 2, 'paginas_url' => ['clientes.php', 'entidades.php']]);

/**
 * percepciones_iibb_clientes_ajax.php — datos IIBB del cliente para percepciones (jurisdicciones CM y certificados).
 * Respuesta: {success: bool, data: ..., message: string}
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/percepciones_iibb_clientes_model.php';

header('Content-Type: application/json; charset=utf-8');
ob_start();

function pcliResponder(bool $ok, $data = null, string $mensaje = '', int $http = 200): void
{
    if (ob_get_length()) ob_clean();
    http_response_code($http);
    echo json_encode(['success' => $ok, 'data' => $data, 'message' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

$empresa_id = (int)$ctx['empresa_id'];
$conexion = $conn;
$accion = (string)($_POST['accion'] ?? $_GET['accion'] ?? '');
$entidad_id = (int)($_POST['entidad_id'] ?? $_GET['entidad_id'] ?? 0);

try {
    switch ($accion) {
        case 'datos':
            pcliResponder(true, pcliDatos($conexion, $empresa_id, $entidad_id));
            break;
        case 'agregar_jurisdiccion':
            pcliAgregarJurisdiccion($conexion, $empresa_id, $entidad_id, $_POST);
            pcliResponder(true, null, 'Jurisdicción agregada');
            break;
        case 'quitar_jurisdiccion':
            pcliQuitarJurisdiccion($conexion, $empresa_id, (int)($_POST['id'] ?? 0));
            pcliResponder(true, null, 'Jurisdicción quitada');
            break;
        case 'agregar_exclusion':
            pcliAgregarExclusion($conexion, $empresa_id, $entidad_id, $_POST);
            pcliResponder(true, null, 'Certificado cargado');
            break;
        case 'inhabilitar_exclusion':
            pcliInhabilitarExclusion($conexion, $empresa_id, (int)($_POST['id'] ?? 0));
            pcliResponder(true, null, 'Certificado inhabilitado');
            break;
        default:
            pcliResponder(false, null, 'Acción no reconocida', 400);
    }
} catch (mysqli_sql_exception $e) {
    error_log('[percepciones_iibb_clientes_ajax] ' . $accion . ' SQL: ' . $e->getMessage());
    pcliResponder(false, null, 'Error de base de datos', 500);
} catch (Throwable $e) {
    pcliResponder(false, null, $e->getMessage(), 400);
}
