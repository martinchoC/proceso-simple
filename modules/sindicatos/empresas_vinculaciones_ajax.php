<?php
/**
 * modules/sindicatos/empresas_vinculaciones_ajax.php
 * Controlador AJAX para el ABM de Vinculaciones y Permisos Inter-Empresas (Módulo 6: Sindicatos)
 */

declare(strict_types=1);

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 1013, 'params' => ['empresa_id', 'empresa_idx']]);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

require_once __DIR__ . '/../../core/empresas_vinculaciones_model.php';

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$empresa_id = (int) $ctx['empresa_id'];
$usuario_id = (int) $ctx['usuario_id'];

try {
    switch ($action) {
        case 'obtener_kpis':
            $kpis = vinculacionesObtenerKPIs($conexion, $empresa_id);
            echo json_encode(['success' => true, 'data' => $kpis]);
            break;

        case 'obtener_empresas_disponibles':
            $empresas = vinculacionesObtenerEmpresasDisponibles($conexion, $empresa_id);
            echo json_encode(['success' => true, 'data' => $empresas]);
            break;

        case 'obtener_otorgadas':
            $items = vinculacionesListarOtorgadas($conexion, $empresa_id);
            echo json_encode(['success' => true, 'data' => $items]);
            break;

        case 'obtener_recibidas':
            $items = vinculacionesListarRecibidas($conexion, $empresa_id);
            echo json_encode(['success' => true, 'data' => $items]);
            break;

        case 'obtener_por_id':
            $vinculacion_id = intval($_POST['vinculacion_id'] ?? $_GET['vinculacion_id'] ?? 0);
            $item = vinculacionObtenerPorId($conexion, $vinculacion_id, $empresa_id);
            if ($item) {
                echo json_encode(['success' => true, 'data' => $item]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Vinculación no encontrada']);
            }
            break;

        case 'guardar':
            $res = vinculacionesGuardar($conexion, $_POST, $empresa_id, $usuario_id);
            echo json_encode($res);
            break;

        case 'cambiar_estado':
            $vinculacion_id = intval($_POST['vinculacion_id'] ?? 0);
            $nuevo_estado = intval($_POST['nuevo_estado'] ?? 1);
            $res = vinculacionesCambiarEstado($conexion, $vinculacion_id, $nuevo_estado, $empresa_id, $usuario_id);
            echo json_encode($res);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Acción inválida: ' . htmlspecialchars($action)]);
            break;
    }
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => 'Excepción en el servidor: ' . $e->getMessage()]);
}
