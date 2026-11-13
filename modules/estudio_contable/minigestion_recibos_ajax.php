<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['params' => ['empresa_id', 'empresa_idx'], 'pagina_idx' => 415]);
/**
 * AJAX Dispatcher: Recibos y Cobranzas con Retenciones
 * Módulo 4: Estudio Contable (minigestion_recibos_ajax.php)
 */

ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/minigestion_recibos_model.php';

$empresa_id = $ctx['empresa_id'];
$usuario_id = $ctx['usuario_id'];
$accion = $_REQUEST['accion'] ?? '';
// Cada acción que escribe exige la función de la página asignada al perfil del usuario (core/permisos.php);
// los botones de esas acciones están marcados data-funcion y header1.php oculta los que el perfil no tiene.
permisos_exigir_accion($conn, 415, (string)$accion, [
    'emitir_recibo' => 'agregar',
]);

$model = new MiniGestionRecibosModel($conn);

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
        case 'listar':
            $empresa_sel = intval($_REQUEST['empresa_id'] ?? $empresa_id);
            responderJson(true, "Recibos listados con éxito.", $model->listarRecibos($empresa_sel));
            break;

        case 'maestros':
            $empresa_sel = intval($_REQUEST['empresa_id'] ?? $empresa_id);
            responderJson(true, "Datos del recibo obtenidos.", $model->maestros($empresa_sel));
            break;

        case 'tipos_por_punto_venta':
            $empresa_sel = intval($_REQUEST['empresa_id'] ?? $empresa_id);
            responderJson(true, "Tipos de recibo obtenidos.", $model->tiposRecibo($empresa_sel, intval($_REQUEST['punto_venta_id'] ?? 0)));
            break;

        case 'emitir_recibo':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responderJson(false, "Método no permitido.", null, "Se requiere POST.");
            }
            $empresa_sel = intval($_POST['empresa_id'] ?? $empresa_id);
            $res = $model->emitirRecibo($empresa_sel, $_POST, $usuario_id);
            responderJson(true, "Recibo {$res['numero']} emitido y confirmado.", $res);
            break;

        default:
            responderJson(false, "Acción no reconocida.", null, "Acción '{$accion}' no existe.");
            break;
    }
} catch (Throwable $e) {
    error_log("=== minigestion_recibos_ajax {$accion}: " . $e->getMessage());
    responderJson(false, $e->getMessage(), null, $e->getMessage());
}
