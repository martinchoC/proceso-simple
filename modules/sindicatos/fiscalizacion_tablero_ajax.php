<?php
/**
 * modules/sindicatos/fiscalizacion_tablero_ajax.php
 * Controlador AJAX para el Tablero de Fiscalización (Página 1040).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$modulo_idx = 6;
$pagina_idx = 1040;

require_once __DIR__ . '/../../core/ajax_guard.php';
require_once __DIR__ . '/fiscalizacion_tablero_model.php';

header('Content-Type: application/json; charset=utf-8');

$ctx = ajax_guard(['pagina_idx' => 1040]);
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
$model = new FiscalizacionTableroModel($conn);

try {
    switch ($action) {
        case 'kpis':
            $kpis = $model->obtenerKpis($empresa_id);
            echo json_encode(['status' => 'ok', 'data' => $kpis]);
            break;

        case 'categorias_mora':
            $cats = $model->listarCategoriasMora();
            echo json_encode(['status' => 'ok', 'data' => $cats]);
            break;

        case 'matriz':
            $filtros = [
                'periodo_desde' => !empty($_GET['periodo_desde']) ? (int)$_GET['periodo_desde'] : null,
                'periodo_hasta' => !empty($_GET['periodo_hasta']) ? (int)$_GET['periodo_hasta'] : null,
                'categoria_mora_id' => !empty($_GET['categoria_mora_id']) ? (int)$_GET['categoria_mora_id'] : null,
                'busqueda' => $_GET['busqueda'] ?? ''
            ];
            $matriz = $model->obtenerMatriz($empresa_id, $filtros);
            echo json_encode(['status' => 'ok', 'data' => $matriz]);
            break;

        case 'recalcular_mora':
            $res = $model->recalcularMora($empresa_id);
            echo json_encode([
                'status' => 'ok',
                'message' => "Morosidad recalculada para {$res['procesados']} empleadores ({$res['actualizados']} cambios de categoría).",
                'data' => $res
            ]);
            break;

        case 'detectar_omitidos':
            $empleador_id = (int)($_GET['empleador_id'] ?? 0);
            $periodo = (int)($_GET['periodo'] ?? 0);
            if ($empleador_id <= 0 || $periodo <= 0) {
                throw new InvalidArgumentException("Parámetros empleador_id y periodo requeridos.");
            }
            $omitidos = $model->detectarOmitidos($empresa_id, $empleador_id, $periodo);
            echo json_encode(['status' => 'ok', 'data' => $omitidos]);
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
