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
            $recibos = $model->listarRecibos($empresa_sel);
            responderJson(true, "Recibos listados con éxito.", $recibos);
            break;

        case 'listar_clientes':
            $empresa_sel = intval($_REQUEST['empresa_id'] ?? $empresa_id);
            $sql = "SELECT entidad_id, entidad_nombre, cuit FROM gestion__entidades WHERE empresa_id = ? AND es_cliente = 1 AND tabla_estado_registro_id = 1 ORDER BY entidad_nombre ASC";
            $st = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($st, "i", $empresa_sel);
            mysqli_stmt_execute($st);
            $res = mysqli_stmt_get_result($st);
            $list = [];
            while ($r = mysqli_fetch_assoc($res)) $list[] = $r;
            mysqli_stmt_close($st);
            responderJson(true, "Clientes obtenidos.", $list);
            break;

        case 'emitir_recibo':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responderJson(false, "Método no permitido.", null, "Se requiere POST.");
            }
            $empresa_sel = intval($_POST['empresa_id'] ?? $empresa_id);
            $res = $model->emitirRecibo($empresa_sel, $_POST, $usuario_id);
            responderJson(true, "Recibo oficial emitido correctamente con imputación contable.", $res);
            break;

        default:
            responderJson(false, "Acción no reconocida.", null, "Acción '{$accion}' no existe.");
            break;
    }
} catch (Exception $e) {
    responderJson(false, "Error al procesar recibo.", null, $e->getMessage());
}
