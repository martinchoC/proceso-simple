<?php
/**
 * modules/sindicatos/planes_ajax.php
 * Controlador AJAX para Planes de Pago y Refinanciación (Página 1042).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$modulo_idx = 6;
$pagina_idx = 1042;

require_once __DIR__ . '/../../core/ajax_guard.php';
require_once __DIR__ . '/planes_model.php';

header('Content-Type: application/json; charset=utf-8');

$ctx = ajax_guard(['pagina_idx' => 1042]);
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
$registrosPedidos = ['sindi__planes' => (int)($_GET['plan_id'] ?? $_POST['plan_id'] ?? 0)];
$registrosPedidos['sindi__actas'] = (int)($_POST['acta_id'] ?? 0);
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
$model = new PlanesModel($conn);

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
            $plan_id = (int)($_GET['plan_id'] ?? 0);
            if ($plan_id <= 0) throw new InvalidArgumentException("ID de plan inválido.");
            $plan = $model->obtener($plan_id);
            if (!$plan) throw new RuntimeException("Plan no encontrado.");
            echo json_encode(['status' => 'ok', 'data' => $plan]);
            break;

        case 'simular':
            $plan_tipo_id = (int)($_POST['plan_tipo_id'] ?? 0);
            $capital = (float)($_POST['capital'] ?? 0);
            $cuotas = (int)($_POST['cuotas'] ?? 1);
            $anticipo_pct = isset($_POST['anticipo_pct']) ? (float)$_POST['anticipo_pct'] : null;
            $f_primer_vto = !empty($_POST['f_primer_vto']) ? $_POST['f_primer_vto'] : null;

            if ($plan_tipo_id <= 0 || $capital <= 0 || $cuotas <= 0) {
                throw new InvalidArgumentException("Datos de simulación incompletos o inválidos.");
            }

            $sim = $model->simular($empresa_id, $plan_tipo_id, $capital, $cuotas, $anticipo_pct, $f_primer_vto);
            echo json_encode(['status' => 'ok', 'data' => $sim]);
            break;

        case 'crear_y_confirmar':
            $datos = [
                'empleador_id' => (int)($_POST['empleador_id'] ?? 0),
                'plan_tipo_id' => (int)($_POST['plan_tipo_id'] ?? 0),
                'origen_tipo' => $_POST['origen_tipo'] ?? 'DEUDA_DDJJ',
                'acta_id' => !empty($_POST['acta_id']) ? (int)$_POST['acta_id'] : null,
                'capital_original' => (float)($_POST['capital_original'] ?? 0),
                'cantidad_cuotas' => (int)($_POST['cantidad_cuotas'] ?? 1),
                'anticipo_pct' => isset($_POST['anticipo_pct']) ? (float)$_POST['anticipo_pct'] : null,
                'f_primer_vto' => !empty($_POST['f_primer_vto']) ? $_POST['f_primer_vto'] : null,
                'observaciones' => $_POST['observaciones'] ?? ''
            ];

            $comprobantesJson = $_POST['comprobantes_origen_ids'] ?? '[]';
            $comprobantes_origen_ids = json_decode($comprobantesJson, true) ?: [];

            if ($datos['empleador_id'] <= 0 || $datos['plan_tipo_id'] <= 0 || $datos['capital_original'] <= 0) {
                throw new InvalidArgumentException("Parámetros del plan incompletos.");
            }

            $res = $model->crearYConfirmar($empresa_id, $datos, $comprobantes_origen_ids, $usuario_id);
            echo json_encode([
                'status' => 'ok',
                'message' => "Plan {$res['numero_plan']} confirmado con éxito. Se generaron {$res['cuotas']} cuotas y se refinanció la deuda original.",
                'data' => $res
            ]);
            break;

        case 'anular':
            $plan_id = (int)($_POST['plan_id'] ?? 0);
            $motivo = $_POST['motivo'] ?? 'Anulación manual';
            if ($plan_id <= 0) throw new InvalidArgumentException("ID de plan inválido.");
            $model->anular($plan_id, $usuario_id, $motivo);
            echo json_encode(['status' => 'ok', 'message' => 'Plan de pagos anulado correctamente']);
            break;

        case 'auxiliares':
            $empleadores = $model->listarEmpleadores($empresa_id);
            $tiposPlanes = $model->listarTiposPlanes($empresa_id);
            echo json_encode([
                'status' => 'ok',
                'data' => [
                    'empleadores' => $empleadores,
                    'tipos_planes' => $tiposPlanes
                ]
            ]);
            break;

        case 'deudas_pendientes':
            $empleador_id = (int)($_GET['empleador_id'] ?? 0);
            if ($empleador_id <= 0) throw new InvalidArgumentException("Empleador no especificado.");
            $deudas = $model->listarDeudasPendientes($empresa_id, $empleador_id);
            echo json_encode(['status' => 'ok', 'data' => $deudas]);
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
