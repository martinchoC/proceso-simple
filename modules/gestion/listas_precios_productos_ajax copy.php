<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "listas_precios_productos_model.php";

$accion = $_REQUEST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $lista_id = intval($_GET['lista_id'] ?? 0);
        if ($lista_id <= 0) {
            echo json_encode([]);
            break;
        }
        $precios = obtenerPreciosProductos($conexion, $lista_id);
        echo json_encode($precios);
        break;
    
    case 'agregar':
        $data = [
            'lista_id' => $_POST['lista_id'] ?? 0,
            'producto_id' => $_POST['producto_id'] ?? 0,
            'precio_unitario' => $_POST['precio_unitario'] ?? 0
        ];
        
        if (empty($data['lista_id']) || empty($data['producto_id']) || $data['precio_unitario'] <= 0) {
            echo json_encode(['resultado' => false, 'error' => 'Todos los campos son obligatorios y el precio debe ser mayor a 0']);
            break;
        }
        
        $resultado = agregarPrecioProducto($conexion, $data);
        if (!$resultado) {
            echo json_encode(['resultado' => false, 'error' => 'Ya existe un precio para este producto en la lista seleccionada']);
            break;
        }
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_POST['lista_precio_producto_id']);
        // 🔥 DEBUG: Verificar si llegan los datos
        error_log("EDITAR - ID recibido: " . $id);
        error_log("EDITAR - Precio recibido: " . ($_POST['precio_unitario'] ?? 'NULL'));
        
        $data = [
            'precio_unitario' => $_POST['precio_unitario'] ?? 0
        ];
        
        if ($data['precio_unitario'] <= 0) {
            error_log("EDITAR - Precio inválido");
            echo json_encode(['resultado' => false, 'error' => 'El precio debe ser mayor a 0']);
            break;
        }
        
        $resultado = editarPrecioProducto($conexion, $id, $data);
        error_log("EDITAR - Resultado: " . ($resultado ? 'TRUE' : 'FALSE'));
        
        if (!$resultado) {
            echo json_encode(['resultado' => false, 'error' => 'Error al actualizar el precio']);
            break;
        }
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar':
        $id = intval($_GET['lista_precio_producto_id']);
        $resultado = eliminarPrecioProducto($conexion, $id);
        if (!$resultado) {
            echo json_encode(['resultado' => false, 'error' => 'No se puede eliminar el precio']);
            break;
        }
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['lista_precio_producto_id']);
        $precio = obtenerPrecioProductoPorId($conexion, $id);
        echo json_encode($precio);
        break;

    case 'obtener_listas':
        $listas = obtenerListasPreciosActivas($conexion);
        echo json_encode($listas);
        break;

    case 'obtener_productos':
        $productos = obtenerProductos($conexion);
        echo json_encode($productos);
        break;

    case 'obtener_historial':
        $lista_id = intval($_GET['lista_id'] ?? 0);
        $historial = obtenerHistorialPrecios($conexion, $lista_id);
        echo json_encode($historial);
        break;

    case 'aplicar_ajuste':
    $data = [
        'lista_id' => $_POST['lista_id'] ?? 0,
        'tipo_ajuste' => $_POST['tipo_ajuste'] ?? 'lote',
        'descripcion' => $_POST['descripcion'] ?? '',
        'porcentaje' => $_POST['porcentaje'] ?? 0,
        'monto_fijo' => $_POST['monto_fijo'] ?? 0,
        'criterio' => $_POST['criterio'] ?? 'aumento',
        'usuario_id_alta' => 1, // Obtener de sesión
        'ip_origen' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'
    ];
    
    // Validar que al menos porcentaje o monto_fijo tengan valor
    if ($data['porcentaje'] <= 0 && $data['monto_fijo'] <= 0) {
        echo json_encode(['resultado' => false, 'error' => 'Debe especificar un porcentaje o monto fijo']);
        break;
    }
    
    $resultado = aplicarAjustePrecios($conexion, $data);
    if (!$resultado) {
        echo json_encode(['resultado' => false, 'error' => 'Error al aplicar el ajuste']);
        break;
    }
    echo json_encode(['resultado' => $resultado]);
    break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}
?>