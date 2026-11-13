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
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/tesoreria_core_model.php';
require_once __DIR__ . '/cobranzas_model.php'; // Reutiliza helpers de bancos, sucursales y cuentas
require_once __DIR__ . '/pagos_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

$empresa_idx = intval($ctx['empresa_id']);
$pagina_idx  = 106; // Órdenes de Pago de este módulo (fijo: nunca del request)

if (!$conexion) {
    manejarError('Error de conexión a la base de datos', 500);
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$usuario_id = intval($ctx['usuario_id']);

// Cada acción exige la función de la página asignada al perfil del usuario (los botones se arman con el mismo criterio)
function pagoExigirFuncion($conexion, int $pagina_id, string $accion_js): void
{
    if (!permisos_puede($conexion, $pagina_id, $accion_js)) {
        manejarError('Tu perfil no tiene permiso para esta acción.', 403);
    }
}

try {
    switch ($accion) {
        case 'listar':
            $filtros = [
                'desde'      => $_GET['desde'] ?? $_POST['desde'] ?? null,
                'hasta'      => $_GET['hasta'] ?? $_POST['hasta'] ?? null,
                'entidad_id' => !empty($_GET['entidad_id']) ? intval($_GET['entidad_id']) : (!empty($_POST['entidad_id']) ? intval($_POST['entidad_id']) : null),
                'estado_id'  => !empty($_GET['estado_id']) ? intval($_GET['estado_id']) : (!empty($_POST['estado_id']) ? intval($_POST['estado_id']) : null)
            ];
            $resultado = obtenerPagosListado($conexion, $empresa_idx, $pagina_idx, $filtros, $usuario_id);
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
            $sucursales         = obtenerSucursalesEmpresa($conexion, $empresa_idx);

            // Retenciones: solo los impuestos (y jurisdicciones de IIBB) de los que la empresa es agente a la fecha
            $fecha_ret = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($_GET['fecha'] ?? '')) ? $_GET['fecha'] : date('Y-m-d');
            $retenciones_config = retPracConfigsVigentes($conexion, $empresa_idx, $fecha_ret);
            $impuestos_agente = array_map('intval', array_column($retenciones_config, 'impuesto_tipo_id'));
            $medios_pago = array_values(array_filter($medios_pago, function ($m) use ($impuestos_agente) {
                return $m['medio_pago_tipo'] !== 'RETENCION'
                    || (empty($m['impuesto_tipo_id']) ? !empty($impuestos_agente) : in_array(intval($m['impuesto_tipo_id']), $impuestos_agente, true));
            }));

            enviarRespuesta([
                'success' => true,
                'data' => [
                    'proveedores'        => $proveedores,
                    'medios_pago'        => $medios_pago,
                    'cuentas_tesoreria'  => $cuentas_tesoreria,
                    'bancos'             => $bancos,
                    'cheques_en_cartera' => $cheques_en_cartera,
                    'chequeras'          => $chequeras,
                    'retenciones_config' => $retenciones_config,
                    'sucursales'         => $sucursales,
                    // Solo los que tienen habilitada la OP; vacío = la empresa no habilitó ninguno (se asigna solo)
                    'puntos_venta'       => pagoPuntosVentaOp($conexion, $empresa_idx)
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

        case 'calcular_retenciones':
            $input_raw = file_get_contents('php://input');
            $data = json_decode($input_raw, true) ?: $_POST;
            $entidad_id = intval($data['entidad_id'] ?? 0);
            if ($entidad_id <= 0) manejarError('Seleccione el proveedor.', 400);
            $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)($data['f_emision'] ?? '')) ? $data['f_emision'] : date('Y-m-d');
            $imputaciones = is_array($data['imputaciones'] ?? null) ? $data['imputaciones'] : [];
            $calculo = retPracCalcular($conexion, $empresa_idx, $entidad_id, $fecha, $imputaciones);
            enviarRespuesta(['success' => true, 'data' => $calculo, 'message' => '']);
            break;

        case 'guardar':
            $input_raw = file_get_contents('php://input');
            $data = json_decode($input_raw, true) ?: $_POST;

            $data['empresa_id'] = $empresa_idx;
            $data['usuario_id'] = $usuario_id;
            pagoExigirFuncion($conexion, $pagina_idx, intval($data['pago_id'] ?? 0) > 0 ? 'editar' : 'agregar');

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
            pagoExigirFuncion($conexion, $pagina_idx, 'confirmar');

            // Si la empresa exige aprobación de OP, queda pendiente; si no, se confirma (ver pagoSolicitarConfirmacion)
            $resultado = pagoSolicitarConfirmacion($conexion, $id, $empresa_idx, $usuario_id);
            enviarRespuesta($resultado);
            break;

        case 'aprobar':
        case 'rechazar':
            $data_json = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            $id = intval($data_json['id'] ?? 0);
            if ($id <= 0) manejarError('ID de pago inválido', 400);
            $resultado = $accion === 'aprobar'
                ? pagoAprobar($conexion, $id, $empresa_idx, $usuario_id, $pagina_idx)
                : pagoRechazar($conexion, $id, $empresa_idx, $usuario_id, $pagina_idx, $data_json['motivo'] ?? '');
            enviarRespuesta($resultado);
            break;

        case 'pendientes_todos':
            // Todo lo que se paga con OP (facturas y obligaciones), de cualquier beneficiario
            enviarRespuesta(['success' => true, 'data' => tesoreriaObtenerComprobantesCancelables($conexion, $empresa_idx, 'OP')]);
            break;

        case 'pagar_lote':
            $data_json = json_decode(file_get_contents('php://input'), true) ?: $_POST;
            // El lote crea y emite una OP por beneficiario
            pagoExigirFuncion($conexion, $pagina_idx, 'agregar');
            pagoExigirFuncion($conexion, $pagina_idx, 'confirmar');
            enviarRespuesta(['success' => true, 'data' => pagosPagarLote($conexion, $empresa_idx, $usuario_id, $data_json)]);
            break;

        case 'anular':
            $input_raw = file_get_contents('php://input');
            $data_json = json_decode($input_raw, true) ?: $_POST;

            $id = intval($data_json['id'] ?? 0);
            $motivo = trim($data_json['motivo'] ?? '');

            if ($id <= 0) manejarError('ID de pago inválido', 400);
            if (empty($motivo)) manejarError('Debe ingresar un motivo para anular', 400);
            pagoExigirFuncion($conexion, $pagina_idx, 'anular');

            $resultado = anularPago($conexion, $id, $empresa_idx, $usuario_id, $motivo);
            enviarRespuesta($resultado);
            break;

        case 'eliminar':
            $input_raw = file_get_contents('php://input');
            $data_json = json_decode($input_raw, true) ?: $_POST;
            $id = intval($data_json['id'] ?? 0);

            if ($id <= 0) manejarError('ID de pago inválido', 400);
            pagoExigirFuncion($conexion, $pagina_idx, 'eliminar');

            $resultado = eliminarPagoBorrador($conexion, $id, $empresa_idx, $usuario_id);
            enviarRespuesta($resultado);
            break;

        default:
            manejarError("Acción desconocida: $accion", 400);
    }
} catch (Exception $e) {
    manejarError($e->getMessage(), 500);
}
