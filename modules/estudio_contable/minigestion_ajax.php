<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['params' => ['empresa_id', 'empresa_idx'], 'pagina_idx' => 412]);
/**
 * AJAX Dispatcher: Mini-Gestión de Clientes
 * Módulo 4: Estudio Contable (minigestion_ajax.php)
 */

ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);

header('Content-Type: application/json; charset=utf-8');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/minigestion_model.php';

$empresa_id = $ctx['empresa_id'];
$accion = $_REQUEST['accion'] ?? '';

$model = new MiniGestionModel($conn);

function responderJson($success, $message, $data = null, $error = null) {
    echo json_encode([
        'success' => (bool)$success,
        'message' => (string)$message,
        'data'    => $data,
        'error'   => $error
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'obtener_clientes':
            $clientes = $model->listarClientes($empresa_id);
            responderJson(true, "Clientes obtenidos con éxito.", $clientes);
            break;

        case 'obtener_cliente_info':
            $cliente_id = intval($_REQUEST['cliente_id'] ?? 0);
            if ($cliente_id <= 0) {
                responderJson(false, "ID de cliente inválido.", null, "Cliente no seleccionado.");
            }
            $info = $model->getClienteInfo($empresa_id, $cliente_id);
            if (!$info) {
                responderJson(false, "Cliente no encontrado.", null, "No existe el cliente en el tenant activo.");
            }
            responderJson(true, "Información de cliente obtenida.", $info);
            break;

        case 'listar_ventas':
            $cliente_id = intval($_REQUEST['cliente_id'] ?? 0);
            $periodo = trim($_REQUEST['periodo_fiscal'] ?? '');
            if ($cliente_id <= 0) {
                responderJson(false, "Cliente no especificado.", []);
            }
            $ventas = $model->listarVentas($empresa_id, $cliente_id, $periodo);
            responderJson(true, "Ventas listadas correctamente.", $ventas);
            break;

        case 'listar_compras':
            $cliente_id = intval($_REQUEST['cliente_id'] ?? 0);
            $periodo = trim($_REQUEST['periodo_fiscal'] ?? '');
            if ($cliente_id <= 0) {
                responderJson(false, "Cliente no especificado.", []);
            }
            $compras = $model->listarCompras($empresa_id, $cliente_id, $periodo);
            responderJson(true, "Compras listadas correctamente.", $compras);
            break;

        case 'totales_fiscales':
            $cliente_id = intval($_REQUEST['cliente_id'] ?? 0);
            $periodo = trim($_REQUEST['periodo_fiscal'] ?? '');
            if ($cliente_id <= 0) {
                responderJson(false, "Cliente no especificado.", null);
            }
            $totales = $model->getTotalesFiscales($empresa_id, $cliente_id, $periodo);
            responderJson(true, "Totales fiscales calculados.", $totales);
            break;

        case 'obtener_tipos_comprobantes':
            $tipos = $model->listarTiposComprobantes();
            responderJson(true, "Tipos de comprobantes obtenidos.", $tipos);
            break;

        case 'obtener_comprobante':
            $comprobante_id = intval($_REQUEST['comprobante_id'] ?? 0);
            if ($comprobante_id <= 0) {
                responderJson(false, "ID de comprobante inválido.", null);
            }
            $comp = $model->obtenerComprobanteManual($empresa_id, $comprobante_id);
            if (!$comp) {
                responderJson(false, "Comprobante no encontrado.", null);
            }
            responderJson(true, "Comprobante obtenido.", $comp);
            break;

        case 'guardar_comprobante':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responderJson(false, "Método no permitido.", null, "Se requiere POST.");
            }
            $datos = $_POST;
            $id = $model->guardarComprobanteManual($empresa_id, $datos);
            responderJson(true, "Comprobante guardado con éxito.", ['comprobante_id' => $id]);
            break;

        case 'anular_comprobante':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                responderJson(false, "Método no permitido.", null, "Se requiere POST.");
            }
            $comprobante_id = intval($_POST['comprobante_id'] ?? 0);
            if ($comprobante_id <= 0) {
                responderJson(false, "ID de comprobante inválido.", null);
            }
            $ok = $model->anularComprobanteManual($empresa_id, $comprobante_id);
            if ($ok) {
                responderJson(true, "Comprobante anulado exitosamente.", ['comprobante_id' => $comprobante_id]);
            } else {
                responderJson(false, "No se pudo anular el comprobante.", null, "Error en base de datos.");
            }
            break;

        default:
            responderJson(false, "Acción no reconocida.", null, "Acción '{$accion}' no implementada.");
            break;
    }
} catch (Exception $e) {
    responderJson(false, "Error al procesar solicitud.", null, $e->getMessage());
}
