<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once "pedidos_clientes_model.php";

$accion = $_REQUEST['accion'] ?? '';

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        $pedidos = obtenerPedidosClientes($conexion);
        echo json_encode($pedidos);
        break;
    
    case 'agregar':
        $data = [
            'numero_comprobante' => $_POST['numero_comprobante'] ?? '',
            'entidad_id' => $_POST['entidad_id'] ?? 0,
            'f_emision' => $_POST['f_emision'] ?? '',
            'f_vto' => $_POST['f_vto'] ?? '',
            'observaciones' => $_POST['observaciones'] ?? '',
            'detalles' => $_POST['detalles'] ?? []
        ];
        
        $resultado = agregarPedidoCliente($conexion, $data);
        if (!$resultado) {
            echo json_encode(['resultado' => false, 'error' => 'Error al crear el pedido']);
            break;
        }
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'editar':
        $id = intval($_POST['comprobante_id']);
        $data = [
            'numero_comprobante' => $_POST['numero_comprobante'] ?? '',
            'entidad_id' => $_POST['entidad_id'] ?? 0,
            'f_emision' => $_POST['f_emision'] ?? '',
            'f_vto' => $_POST['f_vto'] ?? '',
            'observaciones' => $_POST['observaciones'] ?? '',
            'detalles' => $_POST['detalles'] ?? []
        ];
        
        $resultado = editarPedidoCliente($conexion, $id, $data);
        if (!$resultado) {
            echo json_encode(['resultado' => false, 'error' => 'Error al actualizar el pedido']);
            break;
        }
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'cambiar_estado':
        $id = intval($_GET['comprobante_id']);
        $nuevo_estado = intval($_GET['nuevo_estado']);
        $resultado = cambiarEstadoPedidoCliente($conexion, $id, $nuevo_estado);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar':
        $id = intval($_GET['comprobante_id']);
        $resultado = eliminarPedidoCliente($conexion, $id);
        if (!$resultado) {
            echo json_encode(['resultado' => false, 'error' => 'No se puede eliminar el pedido porque está siendo usado']);
            break;
        }
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $id = intval($_GET['comprobante_id']);
        $pedido = obtenerPedidoClientePorId($conexion, $id);
        echo json_encode($pedido);
        break;

    case 'obtener_clientes':
        $clientes = obtenerClientes($conexion);
        echo json_encode(['resultado' => true, 'data' => $clientes]);
        break;

    case 'obtener_productos':
        $productos = obtenerProductos($conexion);
        echo json_encode(['resultado' => true, 'data' => $productos]);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida']);
}
?>