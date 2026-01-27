<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../db.php';
require_once "ordenes_compra_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

// Parámetros del contexto (MULTIEMPRESA)
$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 50);

header('Content-Type: application/json; charset=utf-8');

// Verificar conexión
if (!$conexion) {
    echo json_encode(['error' => 'Error de conexión a la base de datos'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    switch ($accion) {
        case 'listar':
            $ordenes = obtenerOrdenesCompra($conexion, $empresa_idx, $pagina_idx);
            echo json_encode($ordenes, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_idx);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            $resultado = agregarOrdenCompra($conexion, $_POST, $empresa_idx, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $resultado = editarOrdenCompra($conexion, $_POST, $empresa_idx, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $orden_compra_id = intval($_POST['orden_compra_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';

            if (empty($orden_compra_id) || empty($accion_js)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $resultado = ejecutarTransicionEstado($conexion, $orden_compra_id, $accion_js, $empresa_idx, $pagina_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['orden_compra_id'] ?? $_GET['orden_compra_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $resultado = obtenerOrdenCompraPorId($conexion, $id, $empresa_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'obtener_detalle':
            $id = intval($_POST['orden_compra_id'] ?? $_GET['orden_compra_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $resultado = obtenerDetalleOrdenCompra($conexion, $id, $empresa_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'cargar_comprobantes':
            $comprobantes = cargarComprobantes($conexion);
            echo json_encode($comprobantes, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'cargar_proveedores_sucursales':
            $proveedores = cargarProveedoresConSucursales($conexion, $empresa_idx);
            echo json_encode($proveedores, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'buscar_productos':
            $search = $_GET['search'] ?? '';
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            $tipo_busqueda = $_GET['tipo_busqueda'] ?? 'proveedor';
            
            if ($tipo_busqueda === 'todos') {
                $productos = buscarTodosProductos($conexion, $search, $empresa_idx);
            } else {
                $productos = buscarProductosProveedor($conexion, $search, $entidad_id, $empresa_idx);
            }
            echo json_encode($productos, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'buscar_producto_por_id':
            $producto_id = intval($_GET['producto_id'] ?? 0);
            $producto = obtenerProductoPorId($conexion, $producto_id, $empresa_idx);
            echo json_encode($producto, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'cargar_condiciones_pago':
            $condiciones = cargarCondicionesPago($conexion);
            echo json_encode($condiciones, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'cargar_depositos':
            $depositos = cargarDepositos($conexion);
            echo json_encode($depositos, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'cargar_impuestos':
            $impuestos = cargarImpuestos($conexion);
            echo json_encode($impuestos, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    echo json_encode(['error' => 'Error del servidor: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
?>