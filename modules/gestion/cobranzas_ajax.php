<?php
/**
 * cobranzas_ajax.php
 *
 * Dispatcher AJAX para Gestión de Cobranzas a Clientes (Comprobante RCX)
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
    error_log("Error PHP en cobranzas AJAX: $errstr en $errfile línea $errline");
    manejarError("Error interno: $errstr");
});

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/tesoreria_core_model.php';
require_once __DIR__ . '/cobranzas_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx  = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 105);

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
                'desde'          => $_GET['desde'] ?? $_POST['desde'] ?? null,
                'hasta'          => $_GET['hasta'] ?? $_POST['hasta'] ?? null,
                'entidad_id'     => !empty($_GET['entidad_id']) ? intval($_GET['entidad_id']) : (!empty($_POST['entidad_id']) ? intval($_POST['entidad_id']) : null),
                'estado_id'      => !empty($_GET['estado_id']) ? intval($_GET['estado_id']) : (!empty($_POST['estado_id']) ? intval($_POST['estado_id']) : null),
                'comprobante_pv' => !empty($_GET['comprobante_pv']) ? intval($_GET['comprobante_pv']) : (!empty($_POST['comprobante_pv']) ? intval($_POST['comprobante_pv']) : null)
            ];
            $resultado = obtenerCobranzasListado($conexion, $empresa_idx, $pagina_idx, $filtros);
            enviarRespuesta($resultado);
            break;

        case 'obtener':
            $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
            if ($id <= 0) manejarError('ID de cobranza inválido', 400);
            $datos = obtenerCobranzaDetalle($conexion, $id, $empresa_idx);
            if (!$datos) manejarError('Cobranza no encontrada', 404);
            enviarRespuesta(['success' => true, 'data' => $datos]);
            break;

        case 'maestros':
            $clientes = obtenerClientesCobranza($conexion, $empresa_idx);
            $medios_pago = obtenerMediosPagoDisponibles($conexion);
            $cuentas_tesoreria = obtenerCuentasTesoreriaEmpresa($conexion, $empresa_idx);
            $bancos = obtenerBancosDisponibles($conexion);
            $impuestos_retencion = obtenerImpuestosRetencion($conexion);
            $sucursales = obtenerSucursalesEmpresa($conexion, $empresa_idx);
            $puntos_venta = obtenerPuntosVentaCobranza($conexion, $empresa_idx);

            enviarRespuesta([
                'success' => true,
                'data' => [
                    'clientes'            => $clientes,
                    'medios_pago'         => $medios_pago,
                    'cuentas_tesoreria'   => $cuentas_tesoreria,
                    'bancos'              => $bancos,
                    'impuestos_retencion' => $impuestos_retencion,
                    'sucursales'          => $sucursales,
                    'puntos_venta'        => $puntos_venta
                ]
            ]);
            break;

        case 'comprobantes_pendientes':
            $entidad_id = intval($_GET['entidad_id'] ?? $_POST['entidad_id'] ?? 0);
            if ($entidad_id <= 0) {
                enviarRespuesta(['success' => true, 'data' => []]);
            }
            $pendientes = tesoreriaObtenerComprobantesCancelables($conexion, $empresa_idx, 'RCX', $entidad_id);
            enviarRespuesta(['success' => true, 'data' => $pendientes]);
            break;

        case 'guardar':
            // Puede llegar por POST normal o JSON
            $input_raw = file_get_contents('php://input');
            $data = json_decode($input_raw, true);
            if (!$data) {
                $data = $_POST;
            }

            $data['empresa_id'] = $empresa_idx;
            $data['usuario_id'] = $usuario_id;

            $cobranza_id = guardarCobranzaBorrador($conexion, $data);
            enviarRespuesta([
                'success'     => true,
                'cobranza_id' => $cobranza_id,
                'message'     => 'Borrador de cobranza guardado exitosamente'
            ]);
            break;

        case 'confirmar':
            $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                // Verificar si vino en JSON
                $input_raw = file_get_contents('php://input');
                $data_json = json_decode($input_raw, true);
                $id = intval($data_json['id'] ?? 0);
            }
            if ($id <= 0) manejarError('ID de cobranza inválido', 400);

            $resultado = confirmarCobranza($conexion, $id, $empresa_idx, $usuario_id);
            enviarRespuesta($resultado);
            break;

        case 'anular':
            $input_raw = file_get_contents('php://input');
            $data_json = json_decode($input_raw, true) ?: $_POST;

            $id = intval($data_json['id'] ?? 0);
            $motivo = trim($data_json['motivo'] ?? '');

            if ($id <= 0) manejarError('ID de cobranza inválido', 400);
            if (empty($motivo)) manejarError('Debe ingresar un motivo para anular', 400);

            $resultado = anularCobranza($conexion, $id, $empresa_idx, $usuario_id, $motivo);
            enviarRespuesta($resultado);
            break;

        case 'eliminar':
            $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                $input_raw = file_get_contents('php://input');
                $data_json = json_decode($input_raw, true);
                $id = intval($data_json['id'] ?? 0);
            }
            if ($id <= 0) manejarError('ID de cobranza inválido', 400);

            $resultado = eliminarCobranzaBorrador($conexion, $id, $empresa_idx, $usuario_id);
            enviarRespuesta($resultado);
            break;

        default:
            manejarError("Acción desconocida: $accion", 400);
    }
} catch (Exception $e) {
    manejarError($e->getMessage(), 500);
}
