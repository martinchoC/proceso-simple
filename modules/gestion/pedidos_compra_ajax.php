<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../../conexion.php';
require_once "pedidos_compra_model.php";

// Simular usuario logueado (debes reemplazar con tu sistema de autenticación)
session_start();
$usuario_id = $_SESSION['usuario_id'] ?? 1;

$accion = $_REQUEST['accion'] ?? $_POST['accion'] ?? '';
$empresa_id = intval($_REQUEST['empresa_id'] ?? $_POST['empresa_id'] ?? 0);

header('Content-Type: application/json; charset=utf-8');

switch ($accion) {
    case 'listar':
        if ($empresa_id <= 0) {
            echo json_encode([]);
            break;
        }
        $pedidos = obtenerPedidosCompra($conexion, $empresa_id);
        echo json_encode($pedidos);
        break;
    
    case 'listar_tipos':
        $tipos = obtenerTiposComprobantePedido($conexion);
        echo json_encode($tipos);
        break;
    
    case 'listar_proveedores':
        if ($empresa_id <= 0) {
            echo json_encode([]);
            break;
        }
        $proveedores = obtenerProveedores($conexion, $empresa_id);
        echo json_encode($proveedores);
        break;
    
    case 'listar_sucursales':
        if ($empresa_id <= 0) {
            echo json_encode([]);
            break;
        }
        $sucursales = obtenerSucursales($conexion, $empresa_id);
        echo json_encode($sucursales);
        break;
    
    case 'listar_productos':
        if ($empresa_id <= 0) {
            echo json_encode([]);
            break;
        }
        $productos = obtenerProductos($conexion, $empresa_id);
        echo json_encode($productos);
        break;
    
    case 'obtener_proximo_numero':
        $comprobante_tipo_id = intval($_GET['comprobante_tipo_id']);
        $punto_venta_id = intval($_GET['punto_venta_id']);
        $numero = obtenerProximoNumeroComprobante($conexion, $comprobante_tipo_id, $punto_venta_id);
        echo json_encode(['proximo_numero' => $numero]);
        break;
    
    case 'agregar':
        $data = [
            'empresa_id' => $empresa_id,
            'sucursal_id' => $_POST['sucursal_id'] ?? 0,
            'punto_venta_id' => $_POST['punto_venta_id'] ?? 1,
            'comprobante_tipo_id' => $_POST['comprobante_tipo_id'] ?? 0,
            'numero_comprobante' => $_POST['numero_comprobante'] ?? 0,
            'entidad_id' => $_POST['entidad_id'] ?? 0,
            'f_emision' => $_POST['f_emision'] ?? date('Y-m-d'),
            'f_contabilizacion' => $_POST['f_contabilizacion'] ?? date('Y-m-d'),
            'f_vto' => $_POST['f_vto'] ?? date('Y-m-d'),
            'observaciones' => $_POST['observaciones'] ?? '',
            'importe_neto' => $_POST['importe_neto'] ?? 0,
            'importe_no_gravado' => $_POST['importe_no_gravado'] ?? 0,
            'total' => $_POST['total'] ?? 0,
            'estado_registro_id' => $_POST['estado_registro_id'] ?? 1
        ];
        
        // Validaciones básicas
        if ($data['comprobante_tipo_id'] <= 0 || $data['entidad_id'] <= 0) {
            echo json_encode(['resultado' => false, 'error' => 'Tipo de comprobante y proveedor son obligatorios']);
            break;
        }
        
        // Procesar detalles
        $detalles = [];
        if (isset($_POST['detalles']) && is_array($_POST['detalles'])) {
            foreach ($_POST['detalles'] as $detalle) {
                if (!empty($detalle['producto_id']) && $detalle['cantidad'] > 0) {
                    $detalles[] = [
                        'producto_id' => intval($detalle['producto_id']),
                        'cantidad' => floatval($detalle['cantidad']),
                        'precio_unitario' => floatval($detalle['precio_unitario']),
                        'descuento' => floatval($detalle['descuento'] ?? 0)
                    ];
                }
            }
        }
        
        if (empty($detalles)) {
            echo json_encode(['resultado' => false, 'error' => 'Debe agregar al menos un producto al pedido']);
            break;
        }
        
        $resultado = agregarPedidoCompra($conexion, $data, $detalles, $usuario_id);
        if ($resultado) {
            echo json_encode(['resultado' => true, 'comprobante_id' => $resultado]);
        } else {
            echo json_encode(['resultado' => false, 'error' => 'Error al crear el pedido de compra']);
        }
        break;

    case 'editar':
        $comprobante_id = intval($_POST['comprobante_id']);
        $data = [
            'sucursal_id' => $_POST['sucursal_id'] ?? 0,
            'punto_venta_id' => $_POST['punto_venta_id'] ?? 1,
            'comprobante_tipo_id' => $_POST['comprobante_tipo_id'] ?? 0,
            'numero_comprobante' => $_POST['numero_comprobante'] ?? 0,
            'entidad_id' => $_POST['entidad_id'] ?? 0,
            'f_emision' => $_POST['f_emision'] ?? date('Y-m-d'),
            'f_contabilizacion' => $_POST['f_contabilizacion'] ?? date('Y-m-d'),
            'f_vto' => $_POST['f_vto'] ?? date('Y-m-d'),
            'observaciones' => $_POST['observaciones'] ?? '',
            'importe_neto' => $_POST['importe_neto'] ?? 0,
            'importe_no_gravado' => $_POST['importe_no_gravado'] ?? 0,
            'total' => $_POST['total'] ?? 0,
            'estado_registro_id' => $_POST['estado_registro_id'] ?? 1
        ];
        
        // Validaciones básicas
        if ($data['comprobante_tipo_id'] <= 0 || $data['entidad_id'] <= 0) {
            echo json_encode(['resultado' => false, 'error' => 'Tipo de comprobante y proveedor son obligatorios']);
            break;
        }
        
        // Procesar detalles
        $detalles = [];
        if (isset($_POST['detalles']) && is_array($_POST['detalles'])) {
            foreach ($_POST['detalles'] as $detalle) {
                if (!empty($detalle['producto_id']) && $detalle['cantidad'] > 0) {
                    $detalles[] = [
                        'producto_id' => intval($detalle['producto_id']),
                        'cantidad' => floatval($detalle['cantidad']),
                        'precio_unitario' => floatval($detalle['precio_unitario']),
                        'descuento' => floatval($detalle['descuento'] ?? 0)
                    ];
                }
            }
        }
        
        if (empty($detalles)) {
            echo json_encode(['resultado' => false, 'error' => 'Debe agregar al menos un producto al pedido']);
            break;
        }
        
        $resultado = editarPedidoCompra($conexion, $comprobante_id, $data, $detalles, $usuario_id);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'cambiar_estado':
        $comprobante_id = intval($_GET['comprobante_id']);
        $nuevo_estado = intval($_GET['nuevo_estado']);
        $resultado = cambiarEstadoPedidoCompra($conexion, $comprobante_id, $nuevo_estado, $usuario_id);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'eliminar':
        $comprobante_id = intval($_GET['comprobante_id']);
        $resultado = eliminarPedidoCompra($conexion, $comprobante_id);
        echo json_encode(['resultado' => $resultado]);
        break;

    case 'obtener':
        $comprobante_id = intval($_GET['comprobante_id']);
        $pedido = obtenerPedidoCompraPorId($conexion, $comprobante_id);
        $detalles = obtenerDetallesPedidoCompra($conexion, $comprobante_id);
        echo json_encode(['pedido' => $pedido, 'detalles' => $detalles]);
        break;

    default:
        echo json_encode(['error' => 'Acción no definida: ' . $accion]);
}
?>