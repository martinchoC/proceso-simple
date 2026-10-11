<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['params' => ['empresa_id', 'empresa_idx'], 'pagina_idx' => 414]);
/**
 * AJAX Dispatcher: Conceptos sin Stock
 * Módulo 4: Estudio Contable (minigestion_conceptos_ajax.php)
 */

ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/minigestion_conceptos_model.php';
require_once __DIR__ . '/contabilidad_parametros_model.php';

$empresa_id = $ctx['empresa_id'];
$accion = $_REQUEST['accion'] ?? '';

$model = new MiniGestionConceptosModel($conn);
$paramModel = new ContabilidadParametrosModel($conn);

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
            $conceptos = $model->listarConceptos($empresa_sel);
            responderJson(true, "Conceptos listados con éxito.", $conceptos);
            break;

        case 'listar_cuentas':
            $empresa_sel = intval($_REQUEST['empresa_id'] ?? $empresa_id);
            $cuentas = $paramModel->listarCuentasImputables($empresa_sel);
            responderJson(true, "Cuentas obtenidas.", $cuentas);
            break;

        case 'guardar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responderJson(false, "Método no permitido.", null, "Se requiere POST.");
            }
            $empresa_sel = intval($_POST['empresa_id'] ?? $empresa_id);
            $id = $model->guardarConcepto($empresa_sel, $_POST);
            responderJson(true, "Concepto guardado correctamente.", ['producto_id' => $id]);
            break;

        case 'anular':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responderJson(false, "Método no permitido.", null, "Se requiere POST.");
            }
            $id = intval($_POST['producto_id'] ?? 0);
            $empresa_sel = intval($_POST['empresa_id'] ?? $empresa_id);
            $ok = $model->anularConcepto($empresa_sel, $id);
            if ($ok) {
                responderJson(true, "Concepto anulado correctamente.");
            } else {
                responderJson(false, "No se pudo anular el concepto.");
            }
            break;

        default:
            responderJson(false, "Acción no reconocida.", null, "Acción '{$accion}' no implementada.");
            break;
    }
} catch (Exception $e) {
    responderJson(false, "Error al procesar concepto.", null, $e->getMessage());
}
