<?php
/**
 * empresas_cuentas_conceptos_ajax.php
 *
 * Dispatcher AJAX para Conceptos de Cuentas Contables por Empresa
 * Sistema gestion_multipyme - Módulo Gestión
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
    error_log("Error PHP en conceptos AJAX: $errstr en $errfile línea $errline");
    manejarError("Error interno: $errstr");
});

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/empresas_cuentas_conceptos_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx  = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 101);

if (!$conexion) {
    manejarError('Error de conexión a la base de datos', 500);
}

session_start();
$usuario_id = intval($_SESSION['usuario_id'] ?? 1);

try {
    switch ($accion) {
        case 'listar':
            $resultado = obtenerConceptosEmpresa($conexion, $empresa_idx, $pagina_idx);
            enviarRespuesta($resultado);
            break;

        case 'obtener':
            $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
            if ($id <= 0) manejarError('ID de concepto inválido', 400);
            $datos = obtenerConceptoPorId($conexion, $id, $empresa_idx);
            if (!$datos) manejarError('Concepto no encontrado', 404);
            enviarRespuesta(['success' => true, 'data' => $datos]);
            break;

        case 'obtener_cuentas':
            $cuentas = obtenerCuentasContablesImputables($conexion, $empresa_idx);
            enviarRespuesta(['success' => true, 'data' => $cuentas]);
            break;

        case 'guardar':
            $id = intval($_POST['id'] ?? 0);
            $cont_cuenta_id = intval($_POST['cont_cuenta_id'] ?? 0);
            if ($id <= 0) manejarError('ID de concepto requerido', 400);
            if ($cont_cuenta_id <= 0) manejarError('Debe seleccionar una cuenta contable', 400);

            $res = guardarCuentaConcepto($conexion, $id, $empresa_idx, $cont_cuenta_id, $usuario_id);
            enviarRespuesta($res);
            break;

        default:
            manejarError("Acción '$accion' no reconocida", 400);
            break;
    }
} catch (Exception $e) {
    error_log("Excepción en empresas_cuentas_conceptos_ajax: " . $e->getMessage());
    manejarError($e->getMessage(), 500);
}
