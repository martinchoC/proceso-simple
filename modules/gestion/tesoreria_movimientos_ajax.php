<?php
// SesiÃ³n y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 2, 'paginas_url' => ['tesoreria_movimientos.php']]);
$teso_pagina_id = pagina_id_por_url($conn, 2, 'tesoreria_movimientos.php');
/**
 * tesoreria_movimientos_ajax.php
 *
 * Dispatcher AJAX para Movimientos Internos de TesorerÃ­a (Transferencias entre Cuentas)
 * Sistema gestion_multipyme - MÃ³dulo TesorerÃ­a
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
    error_log("Error PHP en tesoreria_movimientos AJAX: $errstr en $errfile lÃ­nea $errline");
    manejarError("Error interno: $errstr");
});

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/tesoreria_core_model.php';
require_once __DIR__ . '/tesoreria_movimientos_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
if (empty($accion)) {
    manejarError('AcciÃ³n no especificada', 400);
}

$empresa_idx = intval($ctx['empresa_id']);

if (!$conexion) {
    manejarError('Error de conexiÃ³n a la base de datos', 500);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$usuario_id = intval($_SESSION['usuario_id'] ?? 1);

// Cada acciÃ³n exige la funciÃ³n de la pÃ¡gina asignada al perfil del usuario (los botones se arman con el mismo criterio)
function tesoExigirFuncion($conexion, int $pagina_id, string $accion_js): void
{
    if (!permisos_puede($conexion, $pagina_id, $accion_js)) {
        manejarError('Tu perfil no tiene permiso para esta acciÃ³n.', 403);
    }
}

try {
    switch ($accion) {
        case 'listar':
            $filtros = [
                'desde'     => $_GET['desde'] ?? $_POST['desde'] ?? null,
                'hasta'     => $_GET['hasta'] ?? $_POST['hasta'] ?? null,
                'cuenta_id' => !empty($_GET['cuenta_id']) ? intval($_GET['cuenta_id']) : null,
                'estado_id' => !empty($_GET['estado_id']) ? intval($_GET['estado_id']) : null
            ];
            $resultado = obtenerTransferenciasListado($conexion, $empresa_idx, $filtros, $teso_pagina_id);
            enviarRespuesta($resultado);
            break;

        case 'cuentas':
            $cuentas = obtenerCuentasEmpresa($conexion, $empresa_idx);
            enviarRespuesta(['success' => true, 'data' => $cuentas]);
            break;

        case 'maestros':
            $cuentas = obtenerCuentasEmpresa($conexion, $empresa_idx);
            $res_suc = $conexion->query("SELECT sucursal_id, sucursal_nombre AS sucursal FROM gestion__sucursales WHERE empresa_id = $empresa_idx AND tabla_estado_registro_id = 1");
            $sucursales = $res_suc ? $res_suc->fetch_all(MYSQLI_ASSOC) : [];
            $res_cc = $conexion->query("SELECT cont_cuenta_id, codigo, nombre FROM gestion__cont_cuentas WHERE empresa_id = $empresa_idx AND es_imputable = 1 AND tabla_estado_registro_id = 1 ORDER BY codigo ASC");
            $cuentas_contables = $res_cc ? $res_cc->fetch_all(MYSQLI_ASSOC) : [];
            $res_ent = $conexion->query("SELECT entidad_id, entidad_nombre, cuit FROM gestion__entidades WHERE empresa_id = $empresa_idx AND tabla_estado_registro_id = 1 ORDER BY entidad_nombre ASC LIMIT 500");
            $entidades = $res_ent ? $res_ent->fetch_all(MYSQLI_ASSOC) : [];
            enviarRespuesta([
                'success' => true,
                'data' => [
                    'cuentas'           => $cuentas,
                    'sucursales'        => $sucursales,
                    'cuentas_contables' => $cuentas_contables,
                    'entidades'         => $entidades
                ]
            ]);
            break;

        case 'listar_otros':
            $filtros = [
                'desde'     => $_GET['desde'] ?? $_POST['desde'] ?? null,
                'hasta'     => $_GET['hasta'] ?? $_POST['hasta'] ?? null,
                'cuenta_id' => !empty($_GET['cuenta_id']) ? intval($_GET['cuenta_id']) : null,
                'estado_id' => !empty($_GET['estado_id']) ? intval($_GET['estado_id']) : null
            ];
            enviarRespuesta(obtenerOtrosMovimientosListado($conexion, $empresa_idx, $filtros, $teso_pagina_id));
            break;

        case 'obtener_otro':
            $comprobante_id = intval($_GET['comprobante_id'] ?? $_POST['comprobante_id'] ?? 0);
            if ($comprobante_id <= 0) manejarError('ID de comprobante invÃ¡lido', 400);
            $row = obtenerOtroMovimientoPorId($conexion, $comprobante_id, $empresa_idx);
            if (!$row) manejarError('Comprobante no encontrado', 404);
            enviarRespuesta(['success' => true, 'data' => $row]);
            break;

        case 'guardar_otro':
            $input_raw = file_get_contents('php://input');
            $data_json = json_decode($input_raw, true) ?: $_POST;
            $es_edicion = intval($data_json['comprobante_id'] ?? 0) > 0;
            tesoExigirFuncion($conexion, $teso_pagina_id, $es_edicion ? 'editar' : 'agregar');
            enviarRespuesta(registrarOtroMovimientoTesoreria($conexion, $data_json, $empresa_idx, $usuario_id));
            break;

        case 'enviar_aprobacion_otro':
            $input_raw = file_get_contents('php://input');
            $data_json = json_decode($input_raw, true) ?: $_POST;
            $comprobante_id = intval($data_json['comprobante_id'] ?? 0);
            if ($comprobante_id <= 0) manejarError('ID de comprobante invÃ¡lido', 400);
            tesoExigirFuncion($conexion, $teso_pagina_id, 'enviar_aprobacion');
            enviarRespuesta(enviarAprobacionOtroMovimiento($conexion, $comprobante_id, $empresa_idx, $usuario_id));
            break;

        case 'confirmar_otro':
            $input_raw = file_get_contents('php://input');
            $data_json = json_decode($input_raw, true) ?: $_POST;
            $comprobante_id = intval($data_json['comprobante_id'] ?? 0);
            if ($comprobante_id <= 0) manejarError('ID de comprobante invÃ¡lido', 400);
            tesoExigirFuncion($conexion, $teso_pagina_id, 'confirmar');
            enviarRespuesta(confirmarOtroMovimientoTesoreria($conexion, $comprobante_id, $empresa_idx, $usuario_id, false));
            break;

        case 'aprobar_otro':
            $input_raw = file_get_contents('php://input');
            $data_json = json_decode($input_raw, true) ?: $_POST;
            $comprobante_id = intval($data_json['comprobante_id'] ?? 0);
            if ($comprobante_id <= 0) manejarError('ID de comprobante invÃ¡lido', 400);
            tesoExigirFuncion($conexion, $teso_pagina_id, 'aprobar');
            enviarRespuesta(confirmarOtroMovimientoTesoreria($conexion, $comprobante_id, $empresa_idx, $usuario_id, true));
            break;

        case 'rechazar_otro':
            $input_raw = file_get_contents('php://input');
            $data_json = json_decode($input_raw, true) ?: $_POST;
            $comprobante_id = intval($data_json['comprobante_id'] ?? 0);
            $motivo = trim((string)($data_json['motivo'] ?? ''));
            if ($comprobante_id <= 0) manejarError('ID de comprobante invÃ¡lido', 400);
            tesoExigirFuncion($conexion, $teso_pagina_id, 'rechazar');
            enviarRespuesta(rechazarOtroMovimiento($conexion, $comprobante_id, $empresa_idx, $usuario_id, $motivo));
            break;

        case 'eliminar_otro':
            $input_raw = file_get_contents('php://input');
            $data_json = json_decode($input_raw, true) ?: $_POST;
            $comprobante_id = intval($data_json['comprobante_id'] ?? 0);
            if ($comprobante_id <= 0) manejarError('ID de comprobante invÃ¡lido', 400);
            tesoExigirFuncion($conexion, $teso_pagina_id, 'eliminar');
            enviarRespuesta(eliminarOtroMovimientoBorrador($conexion, $comprobante_id, $empresa_idx, $usuario_id));
            break;

        case 'anular_otro':
            $input_raw = file_get_contents('php://input');
            $data_json = json_decode($input_raw, true) ?: $_POST;
            $comprobante_id = intval($data_json['comprobante_id'] ?? 0);
            if ($comprobante_id <= 0) manejarError('ID de comprobante invÃ¡lido', 400);
            tesoExigirFuncion($conexion, $teso_pagina_id, 'anular');
            enviarRespuesta(anularOtroMovimientoTesoreria($conexion, $comprobante_id, $empresa_idx, $usuario_id));
            break;

        case 'guardar':
            $input_raw = file_get_contents('php://input');
            $data_json = json_decode($input_raw, true) ?: $_POST;

            tesoExigirFuncion($conexion, $teso_pagina_id, 'agregar');
            $resultado = registrarTransferenciaInterna($conexion, $data_json, $empresa_idx, $usuario_id);
            enviarRespuesta($resultado);
            break;

        case 'anular':
            $input_raw = file_get_contents('php://input');
            $data_json = json_decode($input_raw, true) ?: $_POST;

            $comprobante_id = intval($data_json['comprobante_id'] ?? 0);
            if ($comprobante_id <= 0) manejarError('ID de comprobante invÃ¡lido', 400);

            tesoExigirFuncion($conexion, $teso_pagina_id, 'anular');
            $resultado = anularTransferenciaInterna($conexion, $comprobante_id, $empresa_idx, $usuario_id);
            enviarRespuesta($resultado);
            break;

        default:
            manejarError("AcciÃ³n desconocida: $accion", 400);
    }
} catch (Exception $e) {
    manejarError($e->getMessage(), 500);
}
