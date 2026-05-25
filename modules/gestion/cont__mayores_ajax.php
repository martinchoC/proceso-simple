<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

function enviarRespuesta($data) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function manejarError($mensaje, $codigo = 500) {
    http_response_code($codigo);
    enviarRespuesta(['error' => $mensaje]);
}

require_once __DIR__ . '/../../db.php';
require_once "cont__mayores_model.php";

// Verificar conexión
if (!$conexion) {
    manejarError('Error de conexión a la base de datos', 500);
}

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);

try {
    switch ($accion) {
        case 'obtener_cuentas':
            $cuentas = obtenerCuentasContables($conexion, $empresa_idx);
            enviarRespuesta($cuentas);
            break;
            
        case 'obtener_saldos':
            $fecha_desde = $_GET['fecha_desde'] ?? null;
            $fecha_hasta = $_GET['fecha_hasta'] ?? null;
            $cuenta_id = !empty($_GET['cuenta_id']) ? intval($_GET['cuenta_id']) : null;
            
            error_log("Consulta saldos - empresa: $empresa_idx, desde: $fecha_desde, hasta: $fecha_hasta, cuenta: " . ($cuenta_id ?? 'todas'));
            
            $saldos = obtenerSaldosCuentas($conexion, $empresa_idx, $fecha_desde, $fecha_hasta, $cuenta_id);
            enviarRespuesta($saldos);
            break;
            
        case 'obtener_detalle':
            $cuenta_id = intval($_GET['cuenta_id'] ?? 0);
            $fecha_desde = $_GET['fecha_desde'] ?? null;
            $fecha_hasta = $_GET['fecha_hasta'] ?? null;
            
            if (empty($cuenta_id)) {
                manejarError('Cuenta no especificada', 400);
            }
            
            $detalle = obtenerDetalleCuenta($conexion, $empresa_idx, $cuenta_id, $fecha_desde, $fecha_hasta);
            enviarRespuesta($detalle);
            break;
            
        case 'exportar_resumen':
            $fecha_desde = $_GET['fecha_desde'] ?? null;
            $fecha_hasta = $_GET['fecha_hasta'] ?? null;
            
            $resumen = obtenerResumenExportacion($conexion, $empresa_idx, $fecha_desde, $fecha_hasta);
            enviarRespuesta($resumen);
            break;
            
        default:
            manejarError('Acción no definida: ' . $accion, 400);
    }
} catch (Exception $e) {
    error_log("Excepción en cont__mayores_ajax.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    manejarError('Error del servidor: ' . $e->getMessage(), 500);
}

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
?>