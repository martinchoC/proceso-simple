<?php
/**
 * modules/sindicatos/ddjj_oficio_ajax.php
 * Controlador AJAX para DDJJ de Oficio (Página 1022).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$modulo_idx = 6;
$pagina_idx = 1022;

require_once __DIR__ . '/../../core/ajax_guard.php';
require_once __DIR__ . '/ddjj_oficio_model.php';

header('Content-Type: application/json; charset=utf-8');

$ctx = ajax_guard(['pagina_idx' => 1022]);
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

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$model = new DdjjOficioModel($conn);

try {
    switch ($action) {
        case 'listar_omisos':
            $periodo = (int)($_GET['periodo'] ?? date('Ym'));
            if ($periodo < 200001 || $periodo > 209912) {
                throw new InvalidArgumentException("Período inválido.");
            }
            $omisos = $model->listarOmisos($empresa_id, $periodo);
            echo json_encode(['status' => 'ok', 'data' => $omisos]);
            break;

        case 'listar_emitidas':
            $periodo = !empty($_GET['periodo']) ? (int)$_GET['periodo'] : null;
            $emitidas = $model->listarEmitidas($empresa_id, $periodo);
            echo json_encode(['status' => 'ok', 'data' => $emitidas]);
            break;

        case 'generar':
            $empleador_id = (int)($_POST['empleador_id'] ?? 0);
            $periodo = (int)($_POST['periodo'] ?? 0);
            $confirmar = !empty($_POST['confirmar']) && $_POST['confirmar'] === '1';

            if ($empleador_id <= 0 || $periodo <= 0) {
                throw new InvalidArgumentException("Parámetros empleador_id y periodo requeridos.");
            }

            $res = $model->generarOficio($empresa_id, $empleador_id, $periodo, $usuario_id, $confirmar);
            echo json_encode([
                'status' => 'ok',
                'message' => "DDJJ de Oficio para el período $periodo generada exitosamente. Total determinado: $" . number_format($res['total'], 2, ',', '.'),
                'data' => $res
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
