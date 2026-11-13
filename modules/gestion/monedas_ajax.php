<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$pagina_idx = 126;
$ctx = ajax_guard(['pagina_idx' => $pagina_idx]);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/monedas_model.php';

$empresa_id = intval($ctx['empresa_id']);
$usuario_id = intval($ctx['usuario_id']);
$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

function monResponder($ok, $data = null, $mensaje = '', $http = 200)
{
    if ($http !== 200) http_response_code($http);
    echo json_encode(['success' => $ok, 'data' => $data, 'message' => $mensaje, 'error' => $ok ? null : $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            monResponder(true, monListar($conexion, $empresa_id, $pagina_idx));
            break;

        case 'obtener_boton_agregar':
            monResponder(true, monBotonAgregar($conexion, $pagina_idx));
            break;

        case 'obtener':
            $m = monObtener($conexion, $empresa_id, intval($_GET['moneda_id'] ?? $_POST['moneda_id'] ?? 0));
            $m ? monResponder(true, $m) : monResponder(false, null, 'Moneda no encontrada.', 404);
            break;

        case 'agregar':
        case 'editar':
            if (!usuario_puede_funcion($conexion, $usuario_id, $empresa_id, $pagina_idx, $accion)) {
                monResponder(false, null, 'No tiene permiso para ' . ($accion === 'agregar' ? 'agregar' : 'editar') . ' monedas.', 403);
            }
            $datos = $_POST;
            $datos['moneda_id'] = $accion === 'agregar' ? 0 : intval($_POST['moneda_id'] ?? 0);
            if ($accion === 'editar' && $datos['moneda_id'] <= 0) monResponder(false, null, 'Falta la moneda a editar.', 400);
            $id = monGuardar($conexion, $empresa_id, $datos);
            monResponder(true, ['moneda_id' => $id], $accion === 'agregar' ? 'Moneda creada.' : 'Moneda actualizada.');
            break;

        case 'ejecutar_accion':
            $accion_js = trim((string)($_POST['accion_js'] ?? ''));
            if (!usuario_puede_funcion($conexion, $usuario_id, $empresa_id, $pagina_idx, $accion_js)) {
                monResponder(false, null, 'No tiene permiso para esta acción.', 403);
            }
            $estado = monEjecutarAccion($conexion, $empresa_id, $pagina_idx, intval($_POST['moneda_id'] ?? 0), $accion_js);
            monResponder(true, ['estado' => $estado], 'Estado actualizado.');
            break;

        default:
            monResponder(false, null, 'Acción no válida.', 400);
    }
} catch (Throwable $e) {
    error_log('ERROR monedas_ajax (' . $accion . '): ' . $e->getMessage());
    monResponder(false, null, $e->getMessage());
}
