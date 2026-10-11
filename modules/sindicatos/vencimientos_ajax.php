<?php
declare(strict_types=1);

/**
 * modules/sindicatos/vencimientos_ajax.php
 * Controlador AJAX para cronograma de vencimientos (Página 1004).
 */

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 1004]);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/vencimientos_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$empresa_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];
$pagina_idx = 1004;

try {
    switch ($accion) {
        case 'listar':
            $periodo = !empty($_GET['periodo']) ? intval($_GET['periodo']) : null;
            $lista = sindi_listar_vencimientos($conexion, $empresa_id, $pagina_idx, $periodo);
            echo json_encode(['data' => $lista], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $btn = sindi_obtener_boton_agregar($conexion, $pagina_idx);
            echo json_encode($btn, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
            $vto = sindi_obtener_vencimiento($conexion, $empresa_id, $id);
            echo json_encode(['success' => (bool)$vto, 'data' => $vto], JSON_UNESCAPED_UNICODE);
            break;

        case 'guardar':
            $res = sindi_guardar_vencimiento($conexion, $empresa_id, $_POST, $usuario_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'generar_periodo':
            $periodo = intval($_POST['periodo'] ?? 0);
            $f_base = trim($_POST['f_base'] ?? '');
            $res = sindi_generar_vencimientos_periodo($conexion, $empresa_id, $periodo, $f_base, $usuario_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $id = intval($_POST['id'] ?? 0);
            $accion_js = trim($_POST['accion_js'] ?? '');
            $res = sindi_ejecutar_transicion($conexion, 'sindi__vencimientos', 'vencimiento_id', $id, $accion_js, $pagina_idx, $empresa_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida.'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Throwable $e) {
    error_log("[VENCIMIENTOS_AJAX] Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
