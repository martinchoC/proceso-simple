<?php
declare(strict_types=1);

/**
 * modules/sindicatos/tasas_interes_ajax.php
 * Controlador AJAX para tasas de interés (Página 1005).
 */

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 1005]);
require_once __DIR__ . '/sindi_base_model.php';
sindi_contexto($ctx);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/tasas_interes_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$empresa_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];
$pagina_idx = 1005;

try {
    switch ($accion) {
        case 'listar':
            $lista = sindi_listar_tasas($conexion, $empresa_id, $pagina_idx);
            echo json_encode(['data' => $lista], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $btn = sindi_obtener_boton_agregar($conexion, $pagina_idx);
            echo json_encode($btn, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
            $tasa = sindi_obtener_tasa($conexion, $empresa_id, $id);
            echo json_encode(['success' => (bool)$tasa, 'data' => $tasa], JSON_UNESCAPED_UNICODE);
            break;

        case 'guardar':
            $res = sindi_guardar_tasa($conexion, $empresa_id, $_POST, $usuario_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $id = intval($_POST['id'] ?? 0);
            $accion_js = trim($_POST['accion_js'] ?? '');
            $res = sindi_ejecutar_transicion($conexion, 'sindi__tasas_interes', 'tasa_interes_id', $id, $accion_js, $pagina_idx, $empresa_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida.'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Throwable $e) {
    error_log("[TASAS_AJAX] Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
