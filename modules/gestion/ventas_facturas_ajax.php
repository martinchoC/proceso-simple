<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/ventas_facturas_model.php';
header('Content-Type: application/json; charset=utf-8');

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$empresa_id = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_id = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 56);

try {
    switch ($accion) {
        case 'listar':
            echo json_encode(listarVentasFacturas($conexion, $empresa_id, $pagina_id), JSON_UNESCAPED_UNICODE);
            break;
        case 'obtener_boton_agregar':
            echo json_encode(vfObtenerBotonAgregar($conexion, $pagina_id), JSON_UNESCAPED_UNICODE);
            break;
        case 'clientes':
            echo json_encode(vfClientes($conexion, $empresa_id), JSON_UNESCAPED_UNICODE);
            break;
        case 'puntos_venta':
            echo json_encode(vfPuntosVenta($conexion, $empresa_id), JSON_UNESCAPED_UNICODE);
            break;
        case 'tipos_por_punto_venta':
            echo json_encode(vfTiposPorPuntoVenta($conexion, $empresa_id, intval($_GET['punto_venta_id'] ?? 0)), JSON_UNESCAPED_UNICODE);
            break;
        case 'condicion_cliente':
            echo json_encode(vfObtenerCondicionCliente($conexion, intval($_GET['entidad_id'] ?? 0), $empresa_id), JSON_UNESCAPED_UNICODE);
            break;
        case 'remitos_pendientes':
            echo json_encode(vfRemitosPendientes($conexion, $empresa_id, intval($_GET['entidad_id'] ?? 0), intval($_GET['entidad_sucursal_id'] ?? 0), intval($_GET['excluir_factura_id'] ?? 0), intval($_GET['venta_remito_id'] ?? 0)), JSON_UNESCAPED_UNICODE);
            break;
        case 'buscar_productos':
            echo json_encode(vfBuscarProductos($conexion, $empresa_id, intval($_GET['entidad_id'] ?? 0), trim($_GET['q'] ?? '')), JSON_UNESCAPED_UNICODE);
            break;
        case 'facturas_de_remito':
            echo json_encode(vfObtenerFacturasDeRemito($conexion, $empresa_id, intval($_GET['venta_remito_id'] ?? 0), $pagina_id), JSON_UNESCAPED_UNICODE);
            break;
        case 'remito_info':
            $info = vfObtenerRemitoInfo($conexion, $empresa_id, intval($_GET['venta_remito_id'] ?? 0));
            if ($info) {
                echo json_encode(['success' => true, 'data' => $info], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'error' => 'Remito no encontrado.'], JSON_UNESCAPED_UNICODE);
            }
            break;
        case 'obtener':
            $id = intval($_GET['venta_factura_id'] ?? $_POST['venta_factura_id'] ?? 0);
            $factura = vfObtenerVentaFacturaPorId($conexion, $id, $empresa_id, $pagina_id);
            if ($factura) {
                echo json_encode(['success' => true, 'data' => $factura], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'error' => 'Factura no encontrada.'], JSON_UNESCAPED_UNICODE);
            }
            break;
        case 'agregar':
            $detalles = json_decode($_POST['detalles'] ?? '[]', true);
            if (!is_array($detalles)) throw new Exception('Detalle inválido.');
            $data = $_POST;
            $data['detalles'] = $detalles;
            echo json_encode(guardarVentaFactura($conexion, $empresa_id, $pagina_id, $data), JSON_UNESCAPED_UNICODE);
            break;
        case 'editar':
            $id = intval($_POST['venta_factura_id'] ?? 0);
            $detalles = json_decode($_POST['detalles'] ?? '[]', true);
            if (!is_array($detalles)) throw new Exception('Detalle inválido.');
            $data = $_POST;
            $data['detalles'] = $detalles;
            echo json_encode(vfEditarVentaFactura($conexion, $id, $empresa_id, $pagina_id, $data), JSON_UNESCAPED_UNICODE);
            break;
        case 'ejecutar_accion':
            $id = intval($_POST['venta_factura_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';
            echo json_encode(vfEjecutarTransicionEstado($conexion, $id, $accion_js, $empresa_id, $pagina_id), JSON_UNESCAPED_UNICODE);
            break;
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Acción no definida.'], JSON_UNESCAPED_UNICODE);
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
?>
