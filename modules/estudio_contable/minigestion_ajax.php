<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['params' => ['empresa_id', 'empresa_idx'], 'pagina_idx' => 417]);
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
    // Cargar, editar y anular comprobantes manuales exige "Cargar comprobantes" (3449, agregar);
    // liquidar y anular la liquidación de IVA, "Liquidar IVA" (3555, liquidar_iva).
    if (in_array($accion, ['guardar_comprobante', 'anular_comprobante'], true)
        && !usuario_puede_funcion($conn, intval($ctx['usuario_id']), intval($empresa_id), 417, 'agregar')) {
        http_response_code(403);
        responderJson(false, "No tenés permiso para cargar comprobantes.", null, "Sin permiso.");
    }
    if (in_array($accion, ['liquidar_iva', 'anular_liquidacion_iva'], true)
        && !usuario_puede_funcion($conn, intval($ctx['usuario_id']), intval($empresa_id), 417, 'liquidar_iva')) {
        http_response_code(403);
        responderJson(false, "No tenés permiso para liquidar el IVA.", null, "Sin permiso.");
    }

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

        case 'obtener_jurisdicciones_cliente':
            $cliente_id = intval($_REQUEST['cliente_id'] ?? 0);
            if ($cliente_id <= 0 || !$model->getClienteInfo($empresa_id, $cliente_id)) {
                responderJson(false, "Cliente inválido.", []);
            }
            responderJson(true, "Jurisdicciones del cliente.", $model->jurisdiccionesCliente($empresa_id, $cliente_id));
            break;

        case 'calcular_liquidacion_iva':
            $cliente_id = intval($_REQUEST['cliente_id'] ?? 0);
            $periodo = intval(str_replace('-', '', $_REQUEST['periodo_fiscal'] ?? ''));
            $ret = floatval($_REQUEST['retenciones_iva'] ?? 0);
            responderJson(true, "Liquidación calculada.", $model->calcularLiquidacion($empresa_id, $cliente_id, $periodo, $ret));
            break;

        case 'liquidar_iva':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') responderJson(false, "Método no permitido.", null, "Se requiere POST.");
            $cliente_id = intval($_POST['cliente_id'] ?? 0);
            $periodo = intval(str_replace('-', '', $_POST['periodo_fiscal'] ?? ''));
            $id = $model->liquidarIva($empresa_id, intval($ctx['usuario_id']), $cliente_id, $periodo,
                                      floatval($_POST['retenciones_iva'] ?? 0), $_POST['observaciones'] ?? '');
            responderJson(true, "Período liquidado.", ['liquidacion_id' => $id]);
            break;

        case 'anular_liquidacion_iva':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') responderJson(false, "Método no permitido.", null, "Se requiere POST.");
            $model->anularLiquidacionIva($empresa_id, intval($_POST['cliente_id'] ?? 0), intval(str_replace('-', '', $_POST['periodo_fiscal'] ?? '')));
            responderJson(true, "Liquidación anulada.", null);
            break;

        case 'obtener_tipos_comprobantes':
            $tipos = $model->listarTiposComprobantes($empresa_id);
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
} catch (Throwable $e) {
    error_log("minigestion_ajax [$accion]: " . $e->getMessage());
    responderJson(false, "Error al procesar solicitud.", null, $e->getMessage());
}
