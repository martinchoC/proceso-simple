<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "listas_precios_productos_model.php";

$accion = $_REQUEST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $filtro_lista = $_GET['filtro_lista'] ?? '';
        $filtro_producto = $_GET['filtro_producto'] ?? '';
        $precios = obtenerListasPreciosProductos($conexion, $filtro_lista, $filtro_producto);
        echo json_encode($precios);
        break;
    
    case 'agregar':
        $data = [
            'lista_id' => $_POST['lista_id'] ?? 0,
            'producto_id' => $_POST['producto_id'] ?? 0,
            'precio_unitario' => $_POST['precio_unitario'] ?? 0,
            'ajuste_id' => $_POST['ajuste_id'] ?? null
        ];
        
        if (empty($data['lista_id']) || empty($data['producto_id']) || $data['precio_unitario'] <= 0) {
            echo json_encode(['resultado' => false, 'error' => 'Lista, producto y precio son obligatorios']);
            break;
        }
        
        $resultado = agregarListaPrecioProducto($conexion, $data);
        if (!$resultado) {
            echo json_encode(['resultado' => false, 'error' => 'Ya existe un precio para este producto en la lista seleccionada']);
            break;
        }
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_POST['lista_precio_producto_id']);
        $data = [
            'lista_id' => $_POST['lista_id'] ?? 0,
            'producto_id' => $_POST['producto_id'] ?? 0,
            'precio_unitario' => $_POST['precio_unitario'] ?? 0,
            'ajuste_id' => $_POST['ajuste_id'] ?? null
        ];
        
        if (empty($data['lista_id']) || empty($data['producto_id']) || $data['precio_unitario'] <= 0) {
            echo json_encode(['resultado' => false, 'error' => 'Lista, producto y precio son obligatorios']);
            break;
        }
        
        $resultado = editarListaPrecioProducto($conexion, $id, $data);
        if (!$resultado) {
            echo json_encode(['resultado' => false, 'error' => 'Ya existe un precio para este producto en la lista seleccionada']);
            break;
        }
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar':
        $id = intval($_GET['lista_precio_producto_id']);
        $resultado = eliminarListaPrecioProducto($conexion, $id);
        if (!$resultado) {
            echo json_encode(['resultado' => false, 'error' => 'No se puede eliminar el precio del producto']);
            break;
        }
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['lista_precio_producto_id']);
        $precio_producto = obtenerListaPrecioProductoPorId($conexion, $id);
        echo json_encode($precio_producto);
        break;

    case 'obtener_listas':
        $listas = obtenerListasPrecios($conexion);
        echo json_encode($listas);
        break;

    case 'obtener_productos':
        $productos = obtenerProductos($conexion);
        echo json_encode($productos);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}
?>