<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 2, 'paginas_url' => ['medios_pago.php']]);

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
    enviarRespuesta(['success' => false, 'error' => $mensaje]);
}

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error PHP: $errstr en $errfile línea $errline");
    manejarError("Error interno del servidor: $errstr");
});

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/medios_pago_model.php';

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
if (empty($accion)) manejarError('Acción no especificada', 400);

$empresa_idx = intval($ctx['empresa_id']);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 120);

if (!$conexion) manejarError('Error de conexión a la base de datos', 500);

try {
    switch ($accion) {
        case 'listar':
            $medios = obtenerMediosPago($conexion, $empresa_idx, $pagina_idx);
            enviarRespuesta($medios);
            break;

        case 'obtener_maestros':
            $maestros = obtenerMaestrosMediosPago($conexion, $empresa_idx);
            enviarRespuesta($maestros);
            break;

        case 'obtener_por_id':
            $id = intval($_GET['id'] ?? $_POST['id'] ?? 0);
            if ($id <= 0) {
                manejarError('ID no especificado', 400);
            }
            $medio = obtenerMedioPagoPorId($conexion, $id, $empresa_idx);
            if (!$medio) {
                manejarError('Medio de pago no encontrado', 404);
            }
            enviarRespuesta($medio);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            enviarRespuesta($boton_agregar);
            break;

        case 'ejecutar_accion':
            $medio_pago_id = intval($_POST['medio_pago_id'] ?? $_POST['id'] ?? 0);
            $accion_js = trim($_POST['accion_js'] ?? '');
            $empresa_id_req = intval($_POST['empresa_idx'] ?? $empresa_idx);
            $pagina_id_req = intval($_POST['pagina_idx'] ?? $pagina_idx);

            if ($medio_pago_id <= 0 || empty($accion_js)) {
                manejarError('Parámetros incompletos para ejecutar acción', 400);
            }

            $resultado = ejecutarTransicionEstadoMedioPago($conexion, $medio_pago_id, $accion_js, $empresa_id_req, $pagina_id_req);
            enviarRespuesta($resultado);
            break;

        case 'guardar':
            $datos = [
                'medio_pago_id' => intval($_POST['medio_pago_id'] ?? 0),
                'empresa_idx' => $empresa_idx,
                'codigo' => trim($_POST['codigo'] ?? ''),
                'medio_pago' => trim($_POST['medio_pago'] ?? ''),
                'medio_pago_tipo' => trim($_POST['medio_pago_tipo'] ?? ''),
                'tesoreria_cuenta_tipo' => trim($_POST['tesoreria_cuenta_tipo'] ?? ''),
                'tesoreria_cuenta_id' => intval($_POST['tesoreria_cuenta_id'] ?? 0),
                'cont_cuenta_id' => intval($_POST['cont_cuenta_id'] ?? 0),
                'impuesto_tipo_id' => intval($_POST['impuesto_tipo_id'] ?? 0),
                'requiere_detalle' => isset($_POST['requiere_detalle']) ? intval($_POST['requiere_detalle']) : 0,
                'aplica_cobro' => isset($_POST['aplica_cobro']) ? intval($_POST['aplica_cobro']) : 0,
                'aplica_pago' => isset($_POST['aplica_pago']) ? intval($_POST['aplica_pago']) : 0,
                'orden' => intval($_POST['orden'] ?? 1)
            ];

            $resultado = guardarMedioPago($conexion, $datos);
            enviarRespuesta($resultado);
            break;

        default:
            manejarError('Acción no reconocida: ' . htmlspecialchars($accion), 400);
            break;
    }
} catch (Throwable $e) {
    error_log("Error en medios_pago_ajax: " . $e->getMessage() . "\n" . $e->getTraceAsString());
    manejarError("Excepción del servidor: " . $e->getMessage(), 500);
}
