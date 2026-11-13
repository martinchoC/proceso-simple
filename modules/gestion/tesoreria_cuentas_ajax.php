<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$pagina_idx = 127;
$ctx = ajax_guard(['pagina_idx' => $pagina_idx]);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/tesoreria_cuentas_model.php';

$empresa_id = intval($ctx['empresa_id']);
$usuario_id = intval($ctx['usuario_id']);
$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

function tcResponder($ok, $data = null, $mensaje = '', $http = 200)
{
    if ($http !== 200) http_response_code($http);
    echo json_encode(['success' => $ok, 'data' => $data, 'message' => $mensaje, 'error' => $ok ? null : $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            tcResponder(true, tcListar($conexion, $empresa_id, $pagina_idx));
            break;

        case 'maestros':
            tcResponder(true, tcMaestros($conexion, $empresa_id));
            break;

        case 'obtener_boton_agregar':
            tcResponder(true, monBotonAgregar($conexion, $pagina_idx));
            break;

        case 'obtener':
            $t = tcObtener($conexion, $empresa_id, intval($_GET['tesoreria_cuenta_id'] ?? $_POST['tesoreria_cuenta_id'] ?? 0));
            $t ? tcResponder(true, $t) : tcResponder(false, null, 'Cuenta de tesorería no encontrada.', 404);
            break;

        case 'agregar':
        case 'editar':
            if (!usuario_puede_funcion($conexion, $usuario_id, $empresa_id, $pagina_idx, $accion)) {
                tcResponder(false, null, 'No tiene permiso para ' . ($accion === 'agregar' ? 'agregar' : 'editar') . ' cuentas de tesorería.', 403);
            }
            $datos = $_POST;
            $datos['tesoreria_cuenta_id'] = $accion === 'agregar' ? 0 : intval($_POST['tesoreria_cuenta_id'] ?? 0);
            if ($accion === 'editar' && $datos['tesoreria_cuenta_id'] <= 0) tcResponder(false, null, 'Falta la cuenta a editar.', 400);
            $id = tcGuardar($conexion, $empresa_id, $usuario_id, $datos);
            tcResponder(true, ['tesoreria_cuenta_id' => $id], $accion === 'agregar' ? 'Cuenta creada.' : 'Cuenta actualizada.');
            break;

        case 'ejecutar_accion':
            $accion_js = trim((string)($_POST['accion_js'] ?? ''));
            if (!usuario_puede_funcion($conexion, $usuario_id, $empresa_id, $pagina_idx, $accion_js)) {
                tcResponder(false, null, 'No tiene permiso para esta acción.', 403);
            }
            $estado = tcEjecutarAccion($conexion, $empresa_id, $pagina_idx, intval($_POST['tesoreria_cuenta_id'] ?? 0), $accion_js, $usuario_id);
            tcResponder(true, ['estado' => $estado], 'Estado actualizado.');
            break;

        default:
            tcResponder(false, null, 'Acción no válida.', 400);
    }
} catch (Throwable $e) {
    error_log('ERROR tesoreria_cuentas_ajax (' . $accion . '): ' . $e->getMessage());
    tcResponder(false, null, $e->getMessage());
}
