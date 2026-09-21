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
        case 'clientes':
            echo json_encode(vfClientes($conexion, $empresa_id), JSON_UNESCAPED_UNICODE);
            break;
        case 'condicion_cliente':
            echo json_encode(vfObtenerCondicionCliente($conexion, intval($_GET['entidad_id'] ?? 0)), JSON_UNESCAPED_UNICODE);
            break;
        case 'remitos_pendientes':
            echo json_encode(vfRemitosPendientes($conexion, $empresa_id, intval($_GET['entidad_id'] ?? 0)), JSON_UNESCAPED_UNICODE);
            break;
        case 'buscar_productos':
            echo json_encode(vfBuscarProductos($conexion, $empresa_id, intval($_GET['entidad_id'] ?? 0), trim($_GET['q'] ?? '')), JSON_UNESCAPED_UNICODE);
            break;
        case 'guardar':
            $detalles = json_decode($_POST['detalles'] ?? '[]', true);
            if (!is_array($detalles)) throw new Exception('Detalle inválido.');
            $data = $_POST;
            $data['detalles'] = $detalles;
            echo json_encode(guardarVentaFactura($conexion, $empresa_id, $pagina_id, $data), JSON_UNESCAPED_UNICODE);
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
