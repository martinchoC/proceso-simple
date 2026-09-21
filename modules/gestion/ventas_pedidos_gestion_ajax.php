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
$conexion = $conn;
require_once "ventas_pedidos_gestion_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
// Pagina propia de esta página (dada de alta en conf__paginas), la pasa el
// front leyéndola de la URL con la que se abrió ventas_pedidos_gestion.php —
// no hay default hardcodeado porque es una página nueva sin convención previa.
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 0);

if (!$conexion) {
    manejarError('Error de conexión a la base de datos', 500);
}

try {
    switch ($accion) {
        // Grilla fija de pedidos en estados 5/9/10.
        case 'listar':
            $pedidos = obtenerPedidosGestion($conexion, $empresa_idx, $pagina_idx);
            echo json_encode($pedidos, JSON_UNESCAPED_UNICODE);
            break;

        // Al hacer click en un pedido: si está en estado 5, lo pasa a 9
        // automáticamente (transición real por el motor de estados), y
        // devuelve todo lo necesario para armar la pantalla de picking en
        // un solo viaje: datos del pedido, PV de depósito de su sucursal,
        // y los dos grupos de pendientes (este pedido / otros del mismo cliente).
        case 'abrir_pedido':
            $venta_pedido_id = intval($_POST['venta_pedido_id'] ?? $_GET['venta_pedido_id'] ?? 0);
            if (empty($venta_pedido_id)) {
                manejarError('ID de pedido no proporcionado', 400);
            }

            $pedido = obtenerPedidoVentaPorId($conexion, $venta_pedido_id, $empresa_idx, $pagina_idx);
            if (!$pedido) {
                manejarError('Pedido no encontrado', 404);
            }

            if (intval($pedido['tabla_estado_registro_id']) === 5) {
                $accion_js = resolverAccionTransicion($conexion, $pagina_idx, 5, 9);
                if (!$accion_js) {
                    manejarError('No hay una transición de estado 5 a 9 configurada para esta página en conf__paginas_funciones. Revisar el alta de la página.', 500);
                }
                $resultado_transicion = ejecutarTransicionEstado($conexion, $venta_pedido_id, $accion_js, $empresa_idx, $pagina_idx);
                if (!$resultado_transicion['success']) {
                    manejarError($resultado_transicion['error'] ?? 'Error al pasar el pedido a estado 9', 500);
                }
                // Releer con el estado ya actualizado.
                $pedido = obtenerPedidoVentaPorId($conexion, $venta_pedido_id, $empresa_idx, $pagina_idx);
            }

            $puntos_venta_sucursal = obtenerPuntosVentaDepositoPorSucursal($conexion, $empresa_idx, $pedido['sucursal_id']);

            $pendientes_pedido = obtenerPedidosPendientesCliente($conexion, $empresa_idx, $pedido['entidad_id'], null, $venta_pedido_id, true);

            // Todos los pendientes del cliente, sin filtro de pedido, menos los de
            // este mismo pedido (ya están en la sección de arriba).
            $pendientes_cliente_todos = obtenerPedidosPendientesCliente($conexion, $empresa_idx, $pedido['entidad_id'], null, null);
            $pendientes_otros = array_values(array_filter($pendientes_cliente_todos, function ($p) use ($venta_pedido_id) {
                return intval($p['venta_pedido_id']) !== intval($venta_pedido_id);
            }));

            echo json_encode([
                'pedido' => $pedido,
                'puntos_venta_sucursal' => $puntos_venta_sucursal,
                'pendientes_pedido' => $pendientes_pedido,
                'pendientes_otros' => $pendientes_otros
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'actualizar_cantidad_detalle':
            $resultado = actualizarCantidadDetallePedido(
                $conexion,
                intval($_POST['venta_pedido_id'] ?? 0),
                intval($_POST['venta_pedido_detalle_id'] ?? 0),
                floatval($_POST['cantidad'] ?? 0),
                $empresa_idx
            );
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log("Excepción en ventas_pedidos_gestion_ajax.php: " . $e->getMessage());
    manejarError('Error del servidor: ' . $e->getMessage(), 500);
}

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
?>
