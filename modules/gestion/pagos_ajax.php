<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 2, 'paginas_url' => ['pagos.php']]);
/**
 * pagos_ajax.php
 *
 * Dispatcher AJAX para Gestión de Pagos a Proveedores (Órdenes de Pago OP)
 * Sistema gestion_multipyme - Módulo Tesorería
 */

error_reporting(E_ALL);
ini_set('display_errors', 0);

function enviarRespuesta($data) {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function manejarError($mensaje, $codigo = 500) {
    http_response_code($codigo);
    enviarRespuesta(['success' => false, 'error' => $mensaje]);
}

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error PHP en pagos AJAX: $errstr en $errfile línea $errline");
    manejarError("Error interno: $errstr");
});

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/tesoreria_core_model.php';
require_once __DIR__ . '/cobranzas_model.php'; // Reutiliza helpers de bancos, sucursales y cuentas
require_once __DIR__ . '/pagos_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

$empresa_idx = intval($ctx['empresa_id']);
$pagina_idx  = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 106);

if (!$conexion) {
    manejarError('Error de conexión a la base de datos', 500);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$usuario_id = intval($_SESSION['usuario_id'] ?? 1);

try {
    switch ($accion) {
        case 'listar':
            $filtros = [
                'desde'      => $_GET['desde'] ?? $_POST['desde'] ?? null,
                'hasta'      => $_GET['hasta'] ?? $_POST['hasta'] ?? null,
                'entidad_id' => !empty($_GET['entidad_id']) ? intval($_GET['entidad_id']) : (!empty($_POST['entidad_id']) ? intval($_POST['entidad_id']) : null),
                'estado_id'  => !empty($_GET['estado_id']) ? intval($_GET['estado_id']) : (!empty($_POST['estado_id']) ? intval($_POST['estado_id']) : null)
            ];
            $resultado = obtenerPagosListado($conexion, $empresa_idx, $pagina_idx, $filtros);
            enviarRespuesta($resultado);
            break;

        case 'obtener':
            $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
            if ($id <= 0) manejarError('ID de pago inválido', 400);
            $datos = obtenerPagoDetalle($conexion, $id, $empresa_idx);
            if (!$datos) manejarError('Pago no encontrado', 404);
            enviarRespuesta(['success' => true, 'data' => $datos]);
            break;

        case 'maestros':
            $proveedores        = obtenerProveedoresPago($conexion, $empresa_idx);
            $medios_pago        = $conexion->query("SELECT medio_pago_id, codigo, medio_pago, medio_pago_tipo, cont_cuenta_id, impuesto_tipo_id FROM gestion__medios_pago WHERE empresa_id = " . intval($empresa_idx) . " AND tabla_estado_registro_id = 1 AND aplica_pago = 1 ORDER BY orden ASC, medio_pago_id ASC")->fetch_all(MYSQLI_ASSOC);
            $cuentas_tesoreria  = obtenerCuentasTesoreriaEmpresa($conexion, $empresa_idx);
            $bancos             = obtenerBancosDisponibles($conexion);
            $cheques_en_cartera = obtenerChequesTercerosEnCartera($conexion, $empresa_idx);
            $chequeras          = obtenerCuentasBancariasChequeras($conexion, $empresa_idx);
            $impuestos_ret      = obtenerImpuestosRetencion($conexion, 'PAGO');
            $sucursales         = obtenerSucursalesEmpresa($conexion, $empresa_idx);

            enviarRespuesta([
                'success' => true,
                'data' => [
                    'proveedores'        => $proveedores,
                    'medios_pago'        => $medios_pago,
                    'cuentas_tesoreria'  => $cuentas_tesoreria,
                    'bancos'             => $bancos,
                    'cheques_en_cartera' => $cheques_en_cartera,
                    'chequeras'          => $chequeras,
                    'impuestos_ret'      => $impuestos_ret,
                    'sucursales'         => $sucursales
                ]
            ]);
            break;

        case 'comprobantes_pendientes':
            $entidad_id = intval($_GET['entidad_id'] ?? $_POST['entidad_id'] ?? 0);
            if ($entidad_id <= 0) {
                enviarRespuesta(['success' => true, 'data' => []]);
            }
            $pendientes = tesoreriaObtenerComprobantesCancelables($conexion, $empresa_idx, 'OP', $entidad_id);
            enviarRespuesta(['success' => true, 'data' => $pendientes]);
            break;

        case 'guardar':
            $input_raw = file_get_contents('php://input');
            $data = json_decode($input_raw, true) ?: $_POST;

            $data['empresa_id'] = $empresa_idx;
            $data['usuario_id'] = $usuario_id;

            $pago_id = guardarPagoBorrador($conexion, $data);
            enviarRespuesta([
                'success' => true,
                'pago_id' => $pago_id,
                'message' => 'Borrador de Orden de Pago guardado exitosamente'
            ]);
            break;

        case 'confirmar':
            $input_raw = file_get_contents('php://input');
            $data_json = json_decode($input_raw, true) ?: $_POST;
            $id = intval($data_json['id'] ?? 0);

            if ($id <= 0) manejarError('ID de pago inválido', 400);

            $resultado = confirmarPago($conexion, $id, $empresa_idx, $usuario_id);
            enviarRespuesta($resultado);
            break;

        case 'anular':
            $input_raw = file_get_contents('php://input');
            $data_json = json_decode($input_raw, true) ?: $_POST;

            $id = intval($data_json['id'] ?? 0);
            $motivo = trim($data_json['motivo'] ?? '');

            if ($id <= 0) manejarError('ID de pago inválido', 400);
            if (empty($motivo)) manejarError('Debe ingresar un motivo para anular', 400);

            $resultado = anularPago($conexion, $id, $empresa_idx, $usuario_id, $motivo);
            enviarRespuesta($resultado);
            break;

        case 'eliminar':
            $input_raw = file_get_contents('php://input');
            $data_json = json_decode($input_raw, true) ?: $_POST;
            $id = intval($data_json['id'] ?? 0);

            if ($id <= 0) manejarError('ID de pago inválido', 400);

            $resultado = eliminarPagoBorrador($conexion, $id, $empresa_idx, $usuario_id);
            enviarRespuesta($resultado);
            break;

        default:
            manejarError("Acción desconocida: $accion", 400);
    }
} catch (Exception $e) {
    manejarError($e->getMessage(), 500);
}
