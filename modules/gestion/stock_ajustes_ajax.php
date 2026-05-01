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
require_once "stock_ajustes_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 77);

if (!$conexion) {
    manejarError('Error de conexión a la base de datos', 500);
}

try {
    switch ($accion) {
        case 'listar':
            $ajustes = obtenerStockAjustes($conexion, $empresa_idx, $pagina_idx);
            echo json_encode($ajustes, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            error_log("Botón agregar obtenido: " . json_encode($boton_agregar));
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_sucursales_empresa':
            $sucursales = obtenerSucursalesEmpresa($conexion, $empresa_idx);
            echo json_encode($sucursales, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_depositos':
            $sucursal_id = intval($_GET['sucursal_id'] ?? 0);
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);
            if (empty($sucursal_id)) {
                echo json_encode([]);
                break;
            }
            $depositos = obtenerDepositos($conexion, $sucursal_id, $empresa_idx_local);
            echo json_encode($depositos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_comprobantes_tipos':
            $tipos = obtenerComprobantesTipos($conexion);
            echo json_encode($tipos, JSON_UNESCAPED_UNICODE);
            break;

        case 'buscar_productos':
            $deposito_id = intval($_GET['deposito_id'] ?? 0);
            $q = $_GET['q'] ?? '';
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);
            if (empty($deposito_id)) {
                echo json_encode([]);
                break;
            }
            $productos = buscarProductosConStock($conexion, $empresa_idx_local, $deposito_id, $q);
            echo json_encode($productos, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $stock_ajuste_id = intval($_POST['stock_ajuste_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';
            $empresa_idx = intval($_POST['empresa_idx'] ?? 0);
            $pagina_id = intval($_POST['pagina_idx'] ?? 0);
            $resultado = ejecutarTransicionEstado($conexion, $stock_ajuste_id, $accion_js, $empresa_idx, $pagina_id);
            echo json_encode($resultado);
            break;

        case 'agregar':
            if (!isset($_POST['detalles'])) {
                enviarRespuesta(['resultado' => false, 'error' => 'No se recibieron los detalles']);
            }
            $detalles = json_decode($_POST['detalles'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                enviarRespuesta(['resultado' => false, 'error' => 'Error al decodificar los detalles: ' . json_last_error_msg()]);
            }
            $data = [
                'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
                'deposito_id' => intval($_POST['deposito_id'] ?? 0),
                'comprobante_tipo_id' => intval($_POST['comprobante_tipo_id'] ?? 0),
                'fecha' => $_POST['fecha'] ?? '',
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'detalles' => $detalles,
                'empresa_idx' => $empresa_idx,
                'pagina_idx' => $pagina_idx
            ];
            $resultado = agregarStockAjuste($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['stock_ajuste_id'] ?? 0);
            if (!isset($_POST['detalles'])) {
                enviarRespuesta(['resultado' => false, 'error' => 'No se recibieron los detalles']);
            }
            $detalles = json_decode($_POST['detalles'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                enviarRespuesta(['resultado' => false, 'error' => 'Error al decodificar los detalles: ' . json_last_error_msg()]);
            }
            $data = [
                'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
                'deposito_id' => intval($_POST['deposito_id'] ?? 0),
                'comprobante_tipo_id' => intval($_POST['comprobante_tipo_id'] ?? 0),
                'fecha' => $_POST['fecha'] ?? '',
                'descripcion' => trim($_POST['descripcion'] ?? ''),
                'detalles' => $detalles,
                'empresa_idx' => $empresa_idx
            ];
            $resultado = editarStockAjuste($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['stock_ajuste_id'] ?? $_GET['stock_ajuste_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $ajuste = obtenerStockAjustePorId($conexion, $id, $empresa_idx);
            if ($ajuste) {
                echo json_encode($ajuste, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Ajuste de stock no encontrado'], JSON_UNESCAPED_UNICODE);
            }
            break;

        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log("Excepción en stock_ajustes_ajax.php: " . $e->getMessage());
    manejarError('Error del servidor: ' . $e->getMessage(), 500);
}

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
?>