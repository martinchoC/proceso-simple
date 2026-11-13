<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 2, 'paginas_url' => ['cheques_cartera.php']]);
$teso_pagina_id = pagina_id_por_url($conn, 2, 'cheques_cartera.php');
/**
 * cheques_cartera_ajax.php
 *
 * Dispatcher AJAX para Cartera de Cheques
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
    error_log("Error PHP en cheques AJAX: $errstr en $errfile línea $errline");
    manejarError("Error interno: $errstr");
});

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/tesoreria_core_model.php';
require_once __DIR__ . '/cobranzas_model.php';
require_once __DIR__ . '/cheques_cartera_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

$empresa_idx = intval($ctx['empresa_id']);

if (!$conexion) {
    manejarError('Error de conexión a la base de datos', 500);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$usuario_id = intval($_SESSION['usuario_id'] ?? 1);

// Cada acción exige la función de la página asignada al perfil del usuario (los botones se arman con el mismo criterio)
function tesoExigirFuncion($conexion, int $pagina_id, string $accion_js): void
{
    if (!permisos_puede($conexion, $pagina_id, $accion_js)) {
        manejarError('Tu perfil no tiene permiso para esta acción.', 403);
    }
}

try {
    switch ($accion) {
        case 'listar':
            $filtros = [
                'tipo'      => $_GET['tipo'] ?? $_POST['tipo'] ?? null,
                'estado_id' => !empty($_GET['estado_id']) ? intval($_GET['estado_id']) : (!empty($_POST['estado_id']) ? intval($_POST['estado_id']) : null),
                'desde'     => $_GET['desde'] ?? $_POST['desde'] ?? null,
                'hasta'     => $_GET['hasta'] ?? $_POST['hasta'] ?? null,
                'banco_id'  => !empty($_GET['banco_id']) ? intval($_GET['banco_id']) : (!empty($_POST['banco_id']) ? intval($_POST['banco_id']) : null)
            ];
            $resultado = obtenerChequesListado($conexion, $empresa_idx, $filtros);
            enviarRespuesta($resultado);
            break;

        case 'resumen':
            $resumen = obtenerResumenCartera($conexion, $empresa_idx);
            enviarRespuesta(['success' => true, 'data' => $resumen]);
            break;

        case 'maestros':
            $bancos = obtenerBancosDisponibles($conexion);
            $cuentas_bancarias = $conexion->query("SELECT tesoreria_cuenta_id, tesoreria_cuenta, banco_id FROM gestion__tesoreria_cuentas WHERE empresa_id = $empresa_idx AND cuenta_tipo = 'BANCO' AND tabla_estado_registro_id = 1")->fetch_all(MYSQLI_ASSOC);
            enviarRespuesta([
                'success' => true,
                'data' => [
                    'bancos'            => $bancos,
                    'cuentas_bancarias' => $cuentas_bancarias
                ]
            ]);
            break;

        case 'depositar':
            $input_raw = file_get_contents('php://input');
            $data_json = json_decode($input_raw, true) ?: $_POST;

            $cheque_id       = intval($data_json['cheque_id'] ?? 0);
            $banco_cuenta_id = intval($data_json['banco_cuenta_id'] ?? 0);
            $f_deposito      = !empty($data_json['f_deposito']) ? $data_json['f_deposito'] : date('Y-m-d');

            if ($cheque_id <= 0 || $banco_cuenta_id <= 0) {
                manejarError('Debe especificar el cheque y la cuenta bancaria de depósito', 400);
            }

            tesoExigirFuncion($conexion, $teso_pagina_id, 'depositar');
            $resultado = depositarChequeTercero($conexion, $cheque_id, $banco_cuenta_id, $f_deposito, $empresa_idx, $usuario_id);
            enviarRespuesta($resultado);
            break;

        case 'debitar':
            $input_raw = file_get_contents('php://input');
            $data_json = json_decode($input_raw, true) ?: $_POST;

            $cheque_id = intval($data_json['cheque_id'] ?? 0);
            $f_debito  = !empty($data_json['f_debito']) ? $data_json['f_debito'] : date('Y-m-d');

            if ($cheque_id <= 0) manejarError('ID de cheque inválido', 400);

            tesoExigirFuncion($conexion, $teso_pagina_id, 'debitar');
            $resultado = debitarChequePropio($conexion, $cheque_id, $f_debito, $empresa_idx, $usuario_id);
            enviarRespuesta($resultado);
            break;

        case 'rechazar':
            $input_raw = file_get_contents('php://input');
            $data_json = json_decode($input_raw, true) ?: $_POST;

            $cheque_id = intval($data_json['cheque_id'] ?? 0);
            $motivo    = trim($data_json['motivo'] ?? '');
            $f_rechazo = !empty($data_json['f_rechazo']) ? $data_json['f_rechazo'] : date('Y-m-d');

            if ($cheque_id <= 0) manejarError('ID de cheque inválido', 400);
            if (empty($motivo)) manejarError('Debe ingresar un motivo de rechazo', 400);

            tesoExigirFuncion($conexion, $teso_pagina_id, 'rechazar');
            $resultado = rechazarCheque($conexion, $cheque_id, $motivo, $f_rechazo, $empresa_idx, $usuario_id);
            enviarRespuesta($resultado);
            break;

        default:
            manejarError("Acción desconocida: $accion", 400);
    }
} catch (Exception $e) {
    manejarError($e->getMessage(), 500);
}
