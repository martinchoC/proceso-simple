<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once "compras_pedidos_model.php";

header('Content-Type: application/json; charset=utf-8');

$accion = $_REQUEST['accion'] ?? '';

try {
    switch($accion) {
        case 'listar':
            listarPedidos();
            break;
        case 'obtener_datos_maestros':
            obtenerDatosMaestros();
            break;
        case 'obtener_siguiente_numero':
            obtenerSiguienteNumero();
            break;
        case 'agregar':
            agregarPedido();
            break;
        case 'editar':
            editarPedido();
            break;
        case 'obtener':
            obtenerPedido();
            break;
        case 'obtener_info_estado':
            obtenerInfoEstado();
            break;
        case 'confirmar':
            confirmarPedido();
            break;
        case 'pendiente':
            pendientePedido();
            break;
        case 'eliminar':
            eliminarPedido();
            break;
        default:
            throw new Exception("Acción no válida");
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'resultado' => false,
        'error' => $e->getMessage()
    ]);
}

function listarPedidos() {
    $model = new ComprasPedidosModel();
    $pedidos = $model->obtenerPedidos();
    
    echo json_encode($pedidos);
}

function obtenerDatosMaestros() {
    $model = new ComprasPedidosModel();
    
    $datos = [
        'sucursales' => $model->obtenerSucursales(),
        'tipos' => $model->obtenerTiposComprobante(),
        'proveedores' => $model->obtenerProveedores(),
        'productos' => $model->obtenerProductos()
    ];
    
    echo json_encode($datos);
}

function obtenerSiguienteNumero() {
    $sucursal_id = $_GET['sucursal_id'] ?? null;
    $comprobante_tipo_id = $_GET['comprobante_tipo_id'] ?? null;
    
    if (!$sucursal_id || !$comprobante_tipo_id) {
        throw new Exception("Sucursal y tipo de comprobante son requeridos");
    }
    
    $model = new ComprasPedidosModel();
    $siguiente = $model->obtenerSiguienteNumero($sucursal_id, $comprobante_tipo_id);
    
    echo json_encode(['siguiente_numero' => $siguiente]);
}

function agregarPedido() {
    $model = new ComprasPedidosModel();
    
    // Validaciones básicas
    if (empty($_POST['sucursal_id']) || empty($_POST['comprobante_tipo_id']) || empty($_POST['entidad_id'])) {
        throw new Exception("Campos requeridos faltantes");
    }
    
    // Datos del comprobante
    $comprobanteData = [
        'sucursal_id' => $_POST['sucursal_id'],
        'comprobante_tipo_id' => $_POST['comprobante_tipo_id'],
        'numero_comprobante' => $_POST['numero_comprobante'],
        'entidad_id' => $_POST['entidad_id'],
        'f_emision' => $_POST['f_emision'],
        'f_vto' => $_POST['f_vto'],
        'f_contabilizacion' => $_POST['f_contabilizacion'] ?? $_POST['f_emision'],
        'observaciones' => $_POST['observaciones'] ?? '',
        'importe_neto' => $_POST['importe_neto'] ?? 0,
        'importe_no_gravado' => $_POST['importe_no_gravado'] ?? 0,
        'total' => $_POST['total'] ?? 0,
        'punto_venta_id' => $_POST['punto_venta_id'] ?? 1,
        'estado_registro_id' => 3 // Borrador por defecto
    ];
    
    // Procesar detalles
    $detalles = [];
    if (isset($_POST['detalles']) && is_array($_POST['detalles'])) {
        foreach ($_POST['detalles'] as $detalle) {
            if (!empty($detalle['producto_id']) && !empty($detalle['cantidad'])) {
                $detalles[] = [
                    'producto_id' => $detalle['producto_id'],
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'] ?? 0,
                    'descuento' => $detalle['descuento'] ?? 0
                ];
            }
        }
    }
    
    if (empty($detalles)) {
        throw new Exception("Debe agregar al menos un producto al pedido");
    }
    
    $resultado = $model->crearPedido($comprobanteData, $detalles);
    
    echo json_encode([
        'resultado' => $resultado,
        'mensaje' => $resultado ? 'Pedido creado correctamente' : 'Error al crear el pedido'
    ]);
}

function editarPedido() {
    $comprobante_id = $_POST['comprobante_id'] ?? null;
    
    if (!$comprobante_id) {
        throw new Exception("ID de pedido no especificado");
    }
    
    $model = new ComprasPedidosModel();
    
    // Verificar que el pedido sea editable (estado borrador)
    $pedido = $model->obtenerPedido($comprobante_id);
    if (!$pedido || $pedido['estado_registro_id'] != 3) {
        throw new Exception("El pedido no se puede editar porque no está en estado borrador");
    }
    
    // Datos del comprobante
    $comprobanteData = [
        'sucursal_id' => $_POST['sucursal_id'],
        'comprobante_tipo_id' => $_POST['comprobante_tipo_id'],
        'numero_comprobante' => $_POST['numero_comprobante'],
        'entidad_id' => $_POST['entidad_id'],
        'f_emision' => $_POST['f_emision'],
        'f_vto' => $_POST['f_vto'],
        'f_contabilizacion' => $_POST['f_contabilizacion'] ?? $_POST['f_emision'],
        'observaciones' => $_POST['observaciones'] ?? '',
        'importe_neto' => $_POST['importe_neto'] ?? 0,
        'importe_no_gravado' => $_POST['importe_no_gravado'] ?? 0,
        'total' => $_POST['total'] ?? 0
    ];
    
    // Procesar detalles
    $detalles = [];
    if (isset($_POST['detalles']) && is_array($_POST['detalles'])) {
        foreach ($_POST['detalles'] as $detalle) {
            if (!empty($detalle['producto_id']) && !empty($detalle['cantidad'])) {
                $detalles[] = [
                    'producto_id' => $detalle['producto_id'],
                    'cantidad' => $detalle['cantidad'],
                    'precio_unitario' => $detalle['precio_unitario'] ?? 0,
                    'descuento' => $detalle['descuento'] ?? 0
                ];
            }
        }
    }
    
    if (empty($detalles)) {
        throw new Exception("Debe agregar al menos un producto al pedido");
    }
    
    $resultado = $model->actualizarPedido($comprobante_id, $comprobanteData, $detalles);
    
    echo json_encode([
        'resultado' => $resultado,
        'mensaje' => $resultado ? 'Pedido actualizado correctamente' : 'Error al actualizar el pedido'
    ]);
}

function obtenerPedido() {
    $comprobante_id = $_GET['comprobante_id'] ?? null;
    
    if (!$comprobante_id) {
        throw new Exception("ID de pedido no especificado");
    }
    
    $model = new ComprasPedidosModel();
    $pedido = $model->obtenerPedido($comprobante_id);
    $detalles = $model->obtenerDetallesPedido($comprobante_id);
    
    if ($pedido) {
        echo json_encode([
            'pedido' => $pedido,
            'detalles' => $detalles
        ]);
    } else {
        throw new Exception("Pedido no encontrado");
    }
}

function obtenerInfoEstado() {
    $comprobante_id = $_GET['comprobante_id'] ?? null;
    
    if (!$comprobante_id) {
        throw new Exception("ID de pedido no especificado");
    }
    
    $model = new ComprasPedidosModel();
    $pedido = $model->obtenerPedido($comprobante_id);
    
    if (!$pedido) {
        throw new Exception("Pedido no encontrado");
    }
    
    // Solo se puede editar si está en estado borrador (3)
    $editable = ($pedido['estado_registro_id'] == 3);
    
    echo json_encode([
        'estado_registro_id' => $pedido['estado_registro_id'],
        'estado_nombre' => $pedido['estado_nombre'] ?? 'Desconocido',
        'editable' => $editable
    ]);
}

function confirmarPedido() {
    cambiarEstadoPedido(5, 'confirmado'); // 5 = Confirmado
}

function pendientePedido() {
    cambiarEstadoPedido(4, 'pendiente'); // 4 = Pendiente
}

function eliminarPedido() {
    cambiarEstadoPedido(6, 'eliminado'); // 6 = Eliminado
}

function cambiarEstadoPedido($nuevo_estado, $accion) {
    $comprobante_id = $_POST['comprobante_id'] ?? null;
    
    if (!$comprobante_id) {
        throw new Exception("ID de pedido no especificado");
    }
    
    $model = new ComprasPedidosModel();
    
    // Verificar que existe el pedido
    $pedido = $model->obtenerPedido($comprobante_id);
    if (!$pedido) {
        throw new Exception("Pedido no encontrado");
    }
    
    // Validar transición de estado
    $estado_actual = $pedido['estado_registro_id'];
    $transiciones_validas = [
        3 => [4, 5, 6], // De borrador a pendiente, confirmado o eliminado
        4 => [5, 6],    // De pendiente a confirmado o eliminado
        5 => [6],       // De confirmado a eliminado
        6 => []         // Eliminado no puede cambiar
    ];
    
    if (!in_array($nuevo_estado, $transiciones_validas[$estado_actual] ?? [])) {
        throw new Exception("No se puede cambiar del estado actual al estado solicitado");
    }
    
    $resultado = $model->cambiarEstadoPedido($comprobante_id, $nuevo_estado);
    
    $mensajes = [
        4 => 'Pedido marcado como pendiente',
        5 => 'Pedido confirmado correctamente',
        6 => 'Pedido eliminado correctamente'
    ];
    
    echo json_encode([
        'resultado' => $resultado,
        'mensaje' => $resultado ? ($mensajes[$nuevo_estado] ?? 'Estado cambiado correctamente') : 'Error al cambiar el estado del pedido'
    ]);
}
?>