<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 5, 'paginas_url' => ['cobranzas.php']]);
const COB_MODULO_ID = 5;
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
    if (!(error_reporting() & $errno)) {
        return false;
    }
    // Solo abortar en errores fatales/de usuario; loguear advertencias
    if ($errno === E_USER_ERROR) {
        error_log("Error fatal PHP en cobranzas AJAX: $errstr en $errfile línea $errline");
        manejarError("Error interno: $errstr");
    } else {
        error_log("Aviso PHP en cobranzas AJAX [$errno]: $errstr en $errfile línea $errline");
        return true;
    }
});

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/tesoreria_core_model.php';
require_once __DIR__ . '/cobranzas_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

$empresa_idx = intval($ctx['empresa_id']);
// La página siempre es una de las configuradas con esta url en el módulo (el pedido solo elige entre ellas)
$pagina_idx  = pagina_id_por_url($conexion, COB_MODULO_ID, 'cobranzas.php', intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 0));

// Cada acción exige la función de la página asignada al perfil del usuario (los botones se arman con el mismo criterio)
function cobExigirFuncion($conexion, string $accion_js): void
{
    global $pagina_idx;
    if (!permisos_puede($conexion, intval($pagina_idx), $accion_js)) {
        manejarError('Tu perfil no tiene permiso para esta acción.', 403);
    }
}

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

        case 'obtener_asiento':
            $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
            if ($id <= 0) manejarError('ID de cobranza inválido', 400);
            $asiento = cobranzaObtenerAsiento($conexion, $id, $empresa_idx);
            if ($asiento) {
                enviarRespuesta(['success' => true, 'data' => $asiento]);
            } else {
                enviarRespuesta(['success' => false, 'error' => 'No se encontró asiento contable para esta cobranza.']);
            }
            break;

        case 'generar_asiento':
            $id = intval($_POST['id'] ?? $_GET['id'] ?? 0);
            if ($id <= 0) {
                $input_raw = file_get_contents('php://input');
                $data_json = json_decode($input_raw, true);
                $id = intval($data_json['id'] ?? 0);
            }
            if ($id <= 0) manejarError('ID de cobranza inválido', 400);
            $res_gen = cobranzaGenerarAsientoContable($conexion, $id, $empresa_idx, $usuario_id);
            enviarRespuesta($res_gen);
            break;

        case 'maestros':
            $clientes = obtenerClientesCobranza($conexion, $empresa_idx);
            $medios_pago = obtenerMediosPagoDisponibles($conexion, $empresa_idx);
            $cuentas_tesoreria = obtenerCuentasTesoreriaEmpresa($conexion, $empresa_idx);
            $bancos = obtenerBancosDisponibles($conexion);
            $impuestos_retencion = obtenerImpuestosRetencion($conexion, 'COBRO');
            $jurisdicciones = obtenerJurisdicciones($conexion);
            $sucursales = obtenerSucursalesEmpresa($conexion, $empresa_idx);
            $puntos_venta = obtenerPuntosVentaCobranza($conexion, $empresa_idx, $pagina_idx);

            enviarRespuesta([
                'success' => true,
                'data' => [
                    'clientes'            => $clientes,
                    'medios_pago'         => $medios_pago,
                    'cuentas_tesoreria'   => $cuentas_tesoreria,
                    'bancos'              => $bancos,
                    'impuestos_retencion' => $impuestos_retencion,
                    'jurisdicciones'      => $jurisdicciones,
                    'sucursales'          => $sucursales,
                    'puntos_venta'        => $puntos_venta
                ]
            ]);
            break;

        case 'buscar_clientes':
            $q = trim($_GET['q'] ?? $_POST['q'] ?? '');
            $clientes = obtenerClientesCobranza($conexion, $empresa_idx, $q);
            enviarRespuesta(['success' => true, 'data' => $clientes]);
            break;

        case 'tipos_por_punto_venta':
            $punto_venta_id = intval($_GET['punto_venta_id'] ?? $_POST['punto_venta_id'] ?? 0);
            $tipos = obtenerTiposComprobanteCobranza($conexion, $empresa_idx, $punto_venta_id, $pagina_idx);
            enviarRespuesta($tipos);
            break;

        case 'comprobantes_pendientes':
            $entidad_id = intval($_GET['entidad_id'] ?? $_POST['entidad_id'] ?? 0);
            if ($entidad_id <= 0) {
                enviarRespuesta(['success' => true, 'data' => []]);
            }
            $pendientes = tesoreriaObtenerComprobantesCancelables($conexion, $empresa_idx, 'RCX', $entidad_id);
            // Notas de crédito con saldo del cliente, para compensarlas con las facturas en el mismo recibo
            $notas = cobNotasCreditoCliente($conexion, $empresa_idx, $entidad_id);
            enviarRespuesta(['success' => true, 'data' => $pendientes, 'notas_credito' => $notas]);
            break;

        case 'guardar':
            // Puede llegar por POST normal o JSON
            $input_raw = file_get_contents('php://input');
            $data = json_decode($input_raw, true);
            if (!$data) {
                $data = $_POST;
            }

            if (is_string($data['imputaciones'] ?? null)) {
                $data['imputaciones'] = json_decode($data['imputaciones'], true) ?: [];
            }
            if (is_string($data['medios'] ?? null)) {
                $data['medios'] = json_decode($data['medios'], true) ?: [];
            }
            if (is_string($data['retenciones'] ?? null)) {
                $data['retenciones'] = json_decode($data['retenciones'], true) ?: [];
            }
            if (is_string($data['notas_credito'] ?? null)) {
                $data['notas_credito'] = json_decode($data['notas_credito'], true) ?: [];
            }

            $data['empresa_id'] = $empresa_idx;
            $data['usuario_id'] = $usuario_id;
            cobExigirFuncion($conexion, intval($data['cobranza_id'] ?? 0) > 0 ? 'editar' : 'agregar');

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
            cobExigirFuncion($conexion, 'confirmar');

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
            cobExigirFuncion($conexion, 'anular');

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
            cobExigirFuncion($conexion, 'eliminar');

            $resultado = eliminarCobranzaBorrador($conexion, $id, $empresa_idx, $usuario_id);
            enviarRespuesta($resultado);
            break;

        default:
            manejarError("Acción desconocida: $accion", 400);
    }
} catch (Throwable $e) {
    manejarError($e->getMessage(), 500);
}
