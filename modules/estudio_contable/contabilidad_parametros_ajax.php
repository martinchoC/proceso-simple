<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['params' => ['empresa_id', 'empresa_idx'], 'pagina_idx' => 412]);
/**
 * AJAX Dispatcher: Parámetros Contables
 * Módulo 4: Estudio Contable (contabilidad_parametros_ajax.php)
 */

ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/contabilidad_parametros_model.php';

$empresa_id = $ctx['empresa_id'];
$accion = $_REQUEST['accion'] ?? '';

$model = new ContabilidadParametrosModel($conn);

function responderJson($success, $message, $data = null, $error = null) {
    echo json_encode([
        'success' => (bool)$success,
        'message' => (string)$message,
        'data'    => $data,
        'error'   => $error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'obtener_parametros':
            $empresa_sel = intval($_REQUEST['empresa_id'] ?? $empresa_id);
            $params = $model->getParametros($empresa_sel);
            responderJson(true, "Parámetros contables obtenidos.", $params);
            break;

        case 'listar_cuentas':
            $empresa_sel = intval($_REQUEST['empresa_id'] ?? $empresa_id);
            $cuentas = $model->listarCuentasImputables($empresa_sel);
            responderJson(true, "Cuentas contables imputables obtenidas.", $cuentas);
            break;

        case 'guardar_parametros':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responderJson(false, "Método no permitido.", null, "Se requiere POST.");
            }
            $empresa_sel = intval($_POST['empresa_id'] ?? $empresa_id);
            $model->guardarParametros($empresa_sel, $_POST);
            responderJson(true, "Parámetros contables guardados exitosamente.");
            break;

        default:
            responderJson(false, "Acción no reconocida.", null, "Acción '{$accion}' no existe.");
            break;
    }
} catch (Exception $e) {
    responderJson(false, "Error al procesar solicitud.", null, $e->getMessage());
}
