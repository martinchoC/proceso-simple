<?php
/**
 * modules/sindicatos/actas_ajax.php
 * Controlador AJAX para Actas de Inspección (Página 1041).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$modulo_idx = 6;
$pagina_idx = 1041;

require_once __DIR__ . '/../../core/ajax_guard.php';
require_once __DIR__ . '/actas_model.php';

header('Content-Type: application/json; charset=utf-8');

$ctx = ajax_guard(['pagina_idx' => 1041]);
require_once __DIR__ . '/sindi_base_model.php';
sindi_contexto($ctx);
$empresa_id = (int)$ctx['empresa_id'];
$usuario_id = (int)$ctx['usuario_id'];

// Acceso por empleador: un usuario externo solo opera sobre los empleadores que tiene asignados
require_once __DIR__ . '/../../core/accesos_empleadores.php';
$empleadorPedido = (int)($_GET['empleador_id'] ?? $_POST['empleador_id'] ?? 0);
if ($empleadorPedido > 0) {
    try {
        AccesosEmpleadoresHelper::exigirAcceso($conn, $usuario_id, $empresa_id, $empleadorPedido);
    } catch (RuntimeException $e) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'success' => false, 'message' => $e->getMessage()]);
        exit;
    }
}

// Pertenencia del registro pedido a la empresa (las funciones del núcleo reciben solo el id)
require_once __DIR__ . '/sindi_base_model.php';
$registrosPedidos = ['sindi__actas' => (int)($_GET['acta_id'] ?? $_POST['acta_id'] ?? 0)];
foreach ($registrosPedidos as $tablaPedida => $idPedido) {
    if ($idPedido > 0) {
        try {
            sindi_exigir_registro_empresa($conn, $tablaPedida, $idPedido, $empresa_id, $usuario_id);
        } catch (RuntimeException $e) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'success' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$model = new ActasModel($conn);

try {
    switch ($action) {
        case 'listar':
            $filtros = [
                'empleador_id' => !empty($_GET['empleador_id']) ? (int)$_GET['empleador_id'] : null,
                'estado_id' => !empty($_GET['estado_id']) ? (int)$_GET['estado_id'] : null,
                'busqueda' => $_GET['busqueda'] ?? ''
            ];
            $lista = $model->listar($empresa_id, $filtros, $usuario_id);
            echo json_encode(['status' => 'ok', 'data' => $lista]);
            break;

        case 'obtener':
            $acta_id = (int)($_GET['acta_id'] ?? 0);
            if ($acta_id <= 0) throw new InvalidArgumentException("ID de acta no válido.");
            $acta = $model->obtener($acta_id);
            if (!$acta) throw new RuntimeException("Acta no encontrada.");
            echo json_encode(['status' => 'ok', 'data' => $acta]);
            break;

        case 'crear':
            $datos = [
                'empleador_id' => (int)($_POST['empleador_id'] ?? 0),
                'numero_acta' => $_POST['numero_acta'] ?? null,
                'tipo_acta' => $_POST['tipo_acta'] ?? 'NORMAL',
                'inspector_usuario_id' => !empty($_POST['inspector_usuario_id']) ? (int)$_POST['inspector_usuario_id'] : null,
                'f_inicio' => $_POST['f_inicio'] ?? date('Y-m-d'),
                'f_notificacion' => !empty($_POST['f_notificacion']) ? $_POST['f_notificacion'] : null,
                'observaciones' => $_POST['observaciones'] ?? ''
            ];
            if ($datos['empleador_id'] <= 0) {
                throw new InvalidArgumentException("Debe seleccionar un empleador.");
            }
            $acta_id = $model->crear($empresa_id, $datos, $usuario_id);
            echo json_encode(['status' => 'ok', 'message' => 'Acta creada con éxito', 'acta_id' => $acta_id]);
            break;

        case 'agregar_periodo':
            $acta_id = (int)($_POST['acta_id'] ?? 0);
            $periodo = (int)($_POST['periodo'] ?? 0);
            $itemsJson = $_POST['items'] ?? '[]';
            $items = json_decode($itemsJson, true) ?: [];

            if ($acta_id <= 0 || $periodo <= 0) {
                throw new InvalidArgumentException("Parámetros acta_id y periodo inválidos.");
            }

            $acta_periodo_id = $model->agregarPeriodo($acta_id, $periodo, $items);
            echo json_encode(['status' => 'ok', 'message' => 'Período agregado exitosamente', 'acta_periodo_id' => $acta_periodo_id]);
            break;

        case 'confirmar':
            $acta_id = (int)($_POST['acta_id'] ?? 0);
            if ($acta_id <= 0) throw new InvalidArgumentException("ID de acta inválido.");
            $res = $model->confirmar($acta_id, $usuario_id);
            echo json_encode(['status' => 'ok', 'message' => "Acta {$res['numero_acta']} confirmada con comprobante ACT #{$res['comprobante_id']}", 'data' => $res]);
            break;

        case 'anular':
            $acta_id = (int)($_POST['acta_id'] ?? 0);
            $motivo = $_POST['motivo'] ?? 'Anulación manual';
            if ($acta_id <= 0) throw new InvalidArgumentException("ID de acta inválido.");
            $model->anular($acta_id, $usuario_id, $motivo);
            echo json_encode(['status' => 'ok', 'message' => 'Acta anulada correctamente']);
            break;

        case 'auxiliares':
            $empleadores = $model->listarEmpleadores($empresa_id);
            $inspectores = $model->listarInspectores($empresa_id);
            echo json_encode([
                'status' => 'ok',
                'data' => [
                    'empleadores' => $empleadores,
                    'inspectores' => $inspectores
                ]
            ]);
            break;

        default:
            throw new InvalidArgumentException("Acción '$action' no reconocida.");
    }
} catch (Throwable $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
