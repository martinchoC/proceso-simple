<?php
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
    enviarRespuesta(['error' => $mensaje]);
}

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    error_log("Error PHP: $errstr en $errfile línea $errline");
    manejarError("Error interno del servidor: $errstr");
});

require_once __DIR__ . '/../../db.php';
require_once "productos_costos_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_id = intval($_GET['pagina_id'] ?? $_POST['pagina_id'] ?? 81);

if (!$conexion) {
    manejarError('Error de conexión a la base de datos', 500);
}

try {
    switch ($accion) {
        case 'listar':
            $costos = obtenerProductosCostos($conexion, $empresa_idx, $pagina_id);
            echo json_encode($costos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_id);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $producto_costo_id = intval($_POST['producto_costo_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';
            $empresa_idx = intval($_POST['empresa_idx'] ?? 0);
            $pagina_id = intval($_POST['pagina_id'] ?? 0);
            
            $resultado = ejecutarTransicionEstado($conexion, $producto_costo_id, $accion_js, $empresa_idx, $pagina_id);
            echo json_encode($resultado);
            break;

        case 'agregar':
            $data = [
                'producto_id' => intval($_POST['producto_id'] ?? 0),
                'costo_actual' => floatval($_POST['costo_actual'] ?? 0),
                'moneda_id' => !empty($_POST['moneda_id']) ? intval($_POST['moneda_id']) : null,
                'producto_costo_origen_id' => !empty($_POST['producto_costo_origen_id']) ? intval($_POST['producto_costo_origen_id']) : null,
                'comprobante_id' => !empty($_POST['comprobante_id']) ? intval($_POST['comprobante_id']) : null,
                'f_actualizacion' => $_POST['f_actualizacion'] ?? date('Y-m-d'),
                'observaciones' => trim($_POST['observaciones'] ?? ''),
                'empresa_idx' => $empresa_idx,
                'pagina_id' => $pagina_id
            ];

            $resultado = agregarProductoCosto($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['producto_costo_id'] ?? 0);
            
            $data = [
                'producto_id' => intval($_POST['producto_id'] ?? 0),
                'costo_actual' => floatval($_POST['costo_actual'] ?? 0),
                'moneda_id' => !empty($_POST['moneda_id']) ? intval($_POST['moneda_id']) : null,
                'producto_costo_origen_id' => !empty($_POST['producto_costo_origen_id']) ? intval($_POST['producto_costo_origen_id']) : null,
                'comprobante_id' => !empty($_POST['comprobante_id']) ? intval($_POST['comprobante_id']) : null,
                'f_actualizacion' => $_POST['f_actualizacion'] ?? date('Y-m-d'),
                'observaciones' => trim($_POST['observaciones'] ?? ''),
                'empresa_idx' => $empresa_idx
            ];

            $resultado = editarProductoCosto($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['producto_costo_id'] ?? $_GET['producto_costo_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $costo = obtenerProductoCostoPorId($conexion, $id, $empresa_idx);
            if ($costo) {
                echo json_encode($costo, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Costo de producto no encontrado'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'obtener_productos':
            $productos = obtenerProductos($conexion, $empresa_idx);
            echo json_encode($productos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_monedas':
            $monedas = obtenerMonedas($conexion, $empresa_idx);
            echo json_encode($monedas, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_origenes':
            $origenes = obtenerOrigenesCosto($conexion);
            echo json_encode($origenes, JSON_UNESCAPED_UNICODE);
            break;
        case 'obtener_historial_paginado':
            $id = intval($_GET['producto_costo_id'] ?? 0);
            $page = intval($_GET['page'] ?? 1);
            $fecha_desde = $_GET['fecha_desde'] ?? '';
            $fecha_hasta = $_GET['fecha_hasta'] ?? '';
            
            if (empty($id)) {
                echo json_encode(['success' => false, 'error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $resultado = obtenerHistorialCostosPaginado($conexion, $id, $empresa_idx, $page, $fecha_desde, $fecha_hasta);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;
        case 'obtener_historial':
            $id = intval($_GET['producto_costo_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['success' => false, 'error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $resultado = obtenerHistorialCostos($conexion, $id, $empresa_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log("Excepción en productos_costos_ajax.php: " . $e->getMessage());
    manejarError('Error del servidor: ' . $e->getMessage(), 500);
}

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
?>