<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['params' => ['empresa_id', 'empresa_idx'], 'pagina_idx' => 416]);
/**
 * AJAX Dispatcher: Plan de Cuentas
 * Módulo 4: Estudio Contable (contabilidad_plan_ajax.php)
 */

ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/contabilidad_plan_model.php';

$empresa_id = $ctx['empresa_id'];
$accion = $_REQUEST['accion'] ?? '';
// Cada acción que escribe exige la función de la página asignada al perfil del usuario (core/permisos.php);
// los botones de esas acciones están marcados data-funcion y header1.php oculta los que el perfil no tiene.
permisos_exigir_accion($conn, 416, (string)$accion, [
    'guardar' => (intval($_POST['cont_cuenta_id'] ?? 0) > 0 ? 'editar' : 'agregar'),
    'anular' => 'inhabilitar',
]);

$model = new ContabilidadPlanModel($conn);

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
            $cuentas = $model->listarCuentas($empresa_sel);
            responderJson(true, "Plan de cuentas obtenido.", $cuentas);
            break;

        case 'guardar':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responderJson(false, "Método no permitido.", null, "Se requiere POST.");
            }
            $empresa_sel = intval($_POST['empresa_id'] ?? $empresa_id);
            $id = $model->guardarCuenta($empresa_sel, $_POST);
            responderJson(true, "Cuenta contable guardada exitosamente.", ['cont_cuenta_id' => $id]);
            break;

        case 'anular':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responderJson(false, "Método no permitido.", null, "Se requiere POST.");
            }
            $id = intval($_POST['cont_cuenta_id'] ?? 0);
            $empresa_sel = intval($_POST['empresa_id'] ?? $empresa_id);
            $ok = $model->anularCuenta($empresa_sel, $id);
            if ($ok) {
                responderJson(true, "Cuenta contable dada de baja.");
            } else {
                responderJson(false, "No se pudo anular la cuenta.");
            }
            break;

        default:
            responderJson(false, "Acción no reconocida.", null, "Acción '{$accion}' no implementada.");
            break;
    }
} catch (Exception $e) {
    responderJson(false, "Error al procesar la cuenta.", null, $e->getMessage());
}
