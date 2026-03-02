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
require_once "puntos_venta_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 70);

if (!$conexion) {
    manejarError('Error de conexión a la base de datos', 500);
}

try {
    switch ($accion) {
        case 'listar':
            $puntos = obtenerPuntosVenta($conexion, $empresa_idx, $pagina_idx);
            echo json_encode($puntos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_sucursales_empresa':
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);
            $sucursales = obtenerSucursalesEmpresa($conexion, $empresa_idx_local);
            echo json_encode($sucursales, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_estados':
            $estados = obtenerEstadosRegistro($conexion);
            echo json_encode($estados, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $punto_venta_id = intval($_POST['punto_venta_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';
            $empresa_idx = intval($_POST['empresa_idx'] ?? 0);
            $pagina_id = intval($_POST['pagina_idx'] ?? 0);
            
            $resultado = ejecutarTransicionEstado($conexion, $punto_venta_id, $accion_js, $empresa_idx, $pagina_id);
            echo json_encode($resultado);
            break;

        case 'agregar':
            $data = [
                'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
                'nombre' => trim($_POST['nombre'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'codigo_fiscal' => trim($_POST['codigo_fiscal'] ?? ''),
                'empresa_idx' => $empresa_idx,
                'pagina_idx' => $pagina_idx
            ];

            $resultado = agregarPuntoVenta($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['punto_venta_id'] ?? 0);
            
            error_log("=== EDITAR - ID recibido: $id ===");
            error_log("POST completo: " . print_r($_POST, true));
            
            $data = [
                'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
                'nombre' => trim($_POST['nombre'] ?? ''),
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'codigo_fiscal' => trim($_POST['codigo_fiscal'] ?? ''),
                'empresa_idx' => $empresa_idx
            ];
            
            error_log("data armada para editarPuntoVenta: " . print_r($data, true));

            $resultado = editarPuntoVenta($conexion, $id, $data);
            error_log("resultado de editarPuntoVenta: " . print_r($resultado, true));
            
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['punto_venta_id'] ?? $_GET['punto_venta_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $punto = obtenerPuntoVentaPorId($conexion, $id, $empresa_idx);
            if ($punto) {
                echo json_encode($punto, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Punto de venta no encontrado'], JSON_UNESCAPED_UNICODE);
            }
            break;

        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log("Excepción en puntos_venta_ajax.php: " . $e->getMessage());
    manejarError('Error del servidor: ' . $e->getMessage(), 500);
}

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
?>