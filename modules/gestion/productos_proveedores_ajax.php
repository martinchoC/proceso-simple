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
    enviarRespuesta(['success' => false, 'message' => $mensaje]);
}

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    error_log("Error PHP: $errstr en $errfile línea $errline");
    manejarError("Error interno del servidor: $errstr");
});

require_once __DIR__ . '/../../db.php';
require_once "productos_proveedores_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 95);
$usuario_id = intval($_SESSION['usuario_id'] ?? 0);

if (!$conexion) {
    manejarError('Error de conexión a la base de datos', 500);
}

header('Content-Type: application/json; charset=utf-8');

try {
    switch ($accion) {

        case 'listar':
            $start = intval($_GET['start'] ?? 0);
            $length = intval($_GET['length'] ?? 10);
            $draw = intval($_GET['draw'] ?? 1);
            $orderColumn = intval($_GET['order'][0]['column'] ?? 1);
            $orderDir = $_GET['order'][0]['dir'] ?? 'asc';

            $data = obtenerProductosProveedoresPaginados($conexion, $empresa_idx, [
                'start' => $start,
                'length' => $length,
                'order_column' => $orderColumn,
                'order_dir' => $orderDir,
                'filtro_texto' => $_GET['filtro_texto'] ?? '',
                'filtro_proveedor_id' => $_GET['filtro_proveedor_id'] ?? 0,
                'filtro_vinculo' => $_GET['filtro_vinculo'] ?? 'todos',
                'filtro_marca' => $_GET['filtro_marca'] ?? '',
                'filtro_modelo' => $_GET['filtro_modelo'] ?? '',
                'filtro_submodelo' => $_GET['filtro_submodelo'] ?? ''
            ]);

            echo json_encode([
                'draw' => $draw,
                'recordsTotal' => $data['total'],
                'recordsFiltered' => $data['filtered'],
                'data' => $data['productos']
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_resumen':
            echo json_encode(obtenerResumenCorrelacion($conexion, $empresa_idx), JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_dashboard':
            echo json_encode(obtenerDatosDashboard($conexion, $empresa_idx), JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_proveedores':
            echo json_encode(obtenerProveedoresCombo($conexion, $empresa_idx), JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            echo json_encode(obtenerBotonAgregar($conexion, $pagina_idx), JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_marcas':
            echo json_encode(obtenerMarcas($conexion), JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_modelos':
            echo json_encode(obtenerModelosPorMarca($conexion, $_GET['marca_id'] ?? 0), JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_submodelos':
            echo json_encode(obtenerSubmodelosPorModelo($conexion, $_GET['modelo_id'] ?? 0), JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            $data = [
                'producto_id' => intval($_POST['producto_id'] ?? 0),
                'entidad_id' => intval($_POST['entidad_id'] ?? 0),
                'empresa_id' => $empresa_idx,
                'codigo_proveedor' => $_POST['codigo_proveedor'] ?? ''
            ];
            $resultado = agregarVinculoProveedor($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $producto_proveedor_id = intval($_POST['producto_proveedor_id'] ?? 0);
            $codigo_proveedor = $_POST['codigo_proveedor'] ?? '';
            $resultado = editarVinculoProveedor($conexion, $producto_proveedor_id, $codigo_proveedor, $empresa_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $producto_proveedor_id = intval($_POST['producto_proveedor_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';
            $resultado = ejecutarTransicionEstadoVinculo($conexion, $producto_proveedor_id, $accion_js, $pagina_idx, $empresa_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log("Excepción en productos_proveedores_ajax.php: " . $e->getMessage());
    manejarError('Error del servidor: ' . $e->getMessage(), 500);
}

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
