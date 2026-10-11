<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['params' => ['empresa_id', 'empresa_idx'], 'pagina_idx' => 413]);
/**
 * AJAX Dispatcher: Cierre de Ejercicio Contable
 * Módulo 4: Estudio Contable (contabilidad_cierre_ajax.php)
 */

ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/contabilidad_cierre_model.php';

$empresa_id = $ctx['empresa_id'];
$usuario_id = $ctx['usuario_id'];
$accion = $_REQUEST['accion'] ?? '';

$model = new ContabilidadCierreModel($conn);

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
        case 'listar_cierres':
            $empresa_sel = intval($_REQUEST['empresa_id'] ?? $empresa_id);
            $cierres = $model->listarCierres($empresa_sel);
            responderJson(true, "Cierres de ejercicio listados.", $cierres);
            break;

        case 'calcular_balance':
            $empresa_sel = intval($_REQUEST['empresa_id'] ?? $empresa_id);
            $f_inicio = trim($_REQUEST['f_inicio'] ?? date('Y-01-01'));
            $f_cierre = trim($_REQUEST['f_cierre'] ?? date('Y-12-31'));
            $balance = $model->calcularBalanceComprobacion($empresa_sel, $f_inicio, $f_cierre);
            responderJson(true, "Balance de comprobación calculado.", $balance);
            break;

        case 'ejecutar_cierre':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responderJson(false, "Método no permitido.", null, "Se requiere POST.");
            }
            $empresa_sel = intval($_POST['empresa_id'] ?? $empresa_id);
            $anio = intval($_POST['anio_ejercicio'] ?? date('Y'));
            $f_inicio = trim($_POST['f_inicio'] ?? "{$anio}-01-01");
            $f_cierre = trim($_POST['f_cierre'] ?? "{$anio}-12-31");

            $resultado = $model->ejecutarCierre($empresa_sel, $anio, $f_inicio, $f_cierre, $usuario_id);
            responderJson(true, "Cierre de ejercicio {$anio} ejecutado con éxito.", $resultado);
            break;

        default:
            responderJson(false, "Acción no reconocida.", null, "Acción '{$accion}' no implementada.");
            break;
    }
} catch (Exception $e) {
    responderJson(false, "Error al procesar el cierre.", null, $e->getMessage());
}
