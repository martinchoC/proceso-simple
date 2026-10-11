<?php
/**
 * tesoreria_dashboard_ajax.php
 *
 * Dispatcher AJAX para el Dashboard Financiero de Caja y Bancos
 * Sistema gestion_multipyme - Módulo Tesorería
 */

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 2, 'paginas_url' => ['tesoreria_dashboard.php']]);

error_reporting(E_ALL);
ini_set('display_errors', 0);

function enviarRespuestaJson($data) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function responderError($mensaje, $codigo = 500) {
    http_response_code($codigo);
    enviarRespuestaJson(['success' => false, 'error' => $mensaje]);
}

require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/tesoreria_dashboard_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
if (empty($accion)) {
    responderError('Acción no especificada', 400);
}

$empresa_idx = intval($ctx['empresa_id']);
$usuario_id  = intval($ctx['usuario_id'] ?? $_SESSION['usuario_id'] ?? 1);

try {
    switch ($accion) {
        case 'obtener_todo':
            // Carga unificada de todos los bloques del dashboard
            $filtros_mov = [
                'desde'       => $_GET['desde'] ?? date('Y-m-01'),
                'hasta'       => $_GET['hasta'] ?? date('Y-m-t'),
                'cuenta_id'   => !empty($_GET['cuenta_id']) ? intval($_GET['cuenta_id']) : null,
                'sucursal_id' => !empty($_GET['sucursal_id']) ? intval($_GET['sucursal_id']) : null
            ];
            $dias_flujo = !empty($_GET['dias_flujo']) ? intval($_GET['dias_flujo']) : 30;

            $saldos      = obtenerSaldosCuentasConsolidados($conexion, $empresa_idx, ['sucursal_id' => $filtros_mov['sucursal_id']]);
            $movimientos = obtenerMovimientosPeriodo($conexion, $empresa_idx, $filtros_mov);
            $flujo       = obtenerFlujoFondosProyectado($conexion, $empresa_idx, $dias_flujo);
            $valores     = obtenerValoresADepositar($conexion, $empresa_idx);

            enviarRespuestaJson([
                'success'     => true,
                'saldos'      => $saldos,
                'movimientos' => $movimientos,
                'flujo'       => $flujo,
                'valores'     => $valores
            ]);
            break;

        case 'obtener_saldos':
            $filtros = [
                'sucursal_id' => !empty($_GET['sucursal_id']) ? intval($_GET['sucursal_id']) : null,
                'cuenta_tipo' => !empty($_GET['cuenta_tipo']) ? trim($_GET['cuenta_tipo']) : null
            ];
            $saldos = obtenerSaldosCuentasConsolidados($conexion, $empresa_idx, $filtros);
            enviarRespuestaJson([
                'success' => true,
                'data'    => $saldos
            ]);
            break;

        case 'obtener_movimientos':
            $filtros = [
                'desde'     => $_GET['desde'] ?? date('Y-m-01'),
                'hasta'     => $_GET['hasta'] ?? date('Y-m-t'),
                'cuenta_id' => !empty($_GET['cuenta_id']) ? intval($_GET['cuenta_id']) : null
            ];
            $mov = obtenerMovimientosPeriodo($conexion, $empresa_idx, $filtros);
            enviarRespuestaJson([
                'success' => true,
                'data'    => $mov
            ]);
            break;

        case 'obtener_flujo':
            $dias = !empty($_GET['dias']) ? intval($_GET['dias']) : 30;
            $flujo = obtenerFlujoFondosProyectado($conexion, $empresa_idx, $dias);
            enviarRespuestaJson([
                'success' => true,
                'data'    => $flujo
            ]);
            break;

        case 'obtener_valores':
            $filtros = [
                'categoria' => $_GET['categoria'] ?? null
            ];
            $val = obtenerValoresADepositar($conexion, $empresa_idx, $filtros);
            enviarRespuestaJson([
                'success' => true,
                'data'    => $val
            ]);
            break;

        case 'reconciliar_saldo':
            $cuenta_id = intval($_POST['cuenta_id'] ?? 0);
            if ($cuenta_id <= 0) {
                responderError('ID de cuenta de tesorería inválido', 400);
            }
            $res = reconciliarSaldoCuenta($conexion, $empresa_idx, $cuenta_id, $usuario_id);
            if (!$res['success']) {
                responderError($res['error'] ?? 'Error al reconciliar saldo', 500);
            }
            enviarRespuestaJson($res);
            break;

        default:
            responderError("Acción '$accion' no reconocida", 400);
            break;
    }
} catch (Throwable $e) {
    error_log("Excepción en tesoreria_dashboard_ajax: " . $e->getMessage());
    responderError("Error procesando solicitud: " . $e->getMessage(), 500);
}
