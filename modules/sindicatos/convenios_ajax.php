<?php
declare(strict_types=1);

/**
 * modules/sindicatos/convenios_ajax.php
 * Controlador AJAX para convenios, categorías y escalas (Página 1002).
 */

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 1002]);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/convenios_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$empresa_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];
$pagina_idx = 1002;

try {
    switch ($accion) {
        case 'listar':
            $lista = sindi_listar_convenios($conexion, $empresa_id, $pagina_idx);
            echo json_encode(['data' => $lista], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $btn = sindi_obtener_boton_agregar($conexion, $pagina_idx);
            echo json_encode($btn, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
            $conv = sindi_obtener_convenio($conexion, $empresa_id, $id);
            echo json_encode(['success' => (bool)$conv, 'data' => $conv], JSON_UNESCAPED_UNICODE);
            break;

        case 'guardar':
            $res = sindi_guardar_convenio($conexion, $empresa_id, $_POST, $usuario_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $id = intval($_POST['id'] ?? 0);
            $accion_js = trim($_POST['accion_js'] ?? '');
            $res = sindi_ejecutar_transicion($conexion, 'sindi__convenios', 'convenio_id', $id, $accion_js, $pagina_idx, $empresa_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        // Categorías
        case 'listar_categorias':
            $convenio_id = intval($_GET['convenio_id'] ?? 0);
            $cats = sindi_listar_categorias($conexion, $empresa_id, $convenio_id);
            echo json_encode(['success' => true, 'data' => $cats], JSON_UNESCAPED_UNICODE);
            break;

        case 'guardar_categoria':
            $res = sindi_guardar_categoria($conexion, $empresa_id, $_POST, $usuario_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        // Escalas
        case 'listar_escalas':
            $categoria_id = intval($_GET['categoria_id'] ?? 0);
            $escalas = sindi_listar_escalas($conexion, $empresa_id, $categoria_id);
            echo json_encode(['success' => true, 'data' => $escalas], JSON_UNESCAPED_UNICODE);
            break;

        case 'guardar_escala':
            $res = sindi_guardar_escala($conexion, $empresa_id, $_POST, $usuario_id);
            echo json_encode($res, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no válida.'], JSON_UNESCAPED_UNICODE);
            break;
    }
} catch (Throwable $e) {
    error_log("[CONVENIOS_AJAX] Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
