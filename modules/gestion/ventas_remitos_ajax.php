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
require_once "ventas_remitos_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 88);

if (!$conexion) {
    manejarError('Error de conexión a la base de datos', 500);
}

try {
    switch ($accion) {
        case 'listar':
            $remitos = obtenerRemitosVenta($conexion, $empresa_idx, $pagina_idx);
            echo json_encode($remitos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $pagina_id = intval($_GET['pagina_idx'] ?? 0);
            $boton_agregar = obtenerBotonAgregarRemito($conexion, $pagina_id);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_sucursales_empresa':
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);
            $sucursales = obtenerSucursalesEmpresaRemitos($conexion, $empresa_idx_local);
            echo json_encode($sucursales, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_depositos':
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);
            $depositos = obtenerDepositosEmpresa($conexion, $empresa_idx_local);
            echo json_encode($depositos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_puntos_venta':
            $sucursal_id = intval($_GET['sucursal_id'] ?? 0);
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);

            if (empty($sucursal_id)) {
                echo json_encode([]);
                break;
            }

            $sql = "SELECT punto_venta_id, nombre as punto_venta_nombre, codigo_fiscal as punto_venta_codigo
                    FROM gestion__puntos_venta
                    WHERE sucursal_id = ?
                    AND empresa_id = ?
                    AND tabla_estado_registro_id = 1
                    ORDER BY nombre";

            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param($stmt, "ii", $sucursal_id, $empresa_idx_local);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);

            $puntos_venta = [];
            while ($fila = mysqli_fetch_assoc($result)) {
                $puntos_venta[] = $fila;
            }
            mysqli_stmt_close($stmt);

            echo json_encode($puntos_venta, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $venta_remito_id = intval($_POST['venta_remito_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';
            $empresa_idx_local = intval($_POST['empresa_idx'] ?? 0);
            $pagina_id = intval($_POST['pagina_idx'] ?? 0);

            $resultado = ejecutarTransicionEstadoRemito($conexion, $venta_remito_id, $accion_js, $empresa_idx_local, $pagina_id);
            echo json_encode($resultado);
            break;

        case 'obtener_clientes_con_sucursales':
            $clientes = obtenerClientesRemitos($conexion, $empresa_idx);
            $resultado = [];

            foreach ($clientes as $cliente) {
                $item = [
                    'tipo' => 'cliente',
                    'entidad_id' => $cliente['entidad_id'],
                    'entidad_nombre' => $cliente['entidad_nombre'],
                    'sucursales' => []
                ];

                $sucursales = obtenerSucursalesClienteRemitos($conexion, $cliente['entidad_id'], $empresa_idx);
                foreach ($sucursales as $sucursal) {
                    $item['sucursales'][] = [
                        'sucursal_id' => $sucursal['sucursal_id'],
                        'sucursal_nombre' => $sucursal['sucursal_nombre']
                    ];
                }

                $resultado[] = $item;
            }

            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_pedidos_pendientes_cliente':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);

            if (empty($entidad_id)) {
                echo json_encode([], JSON_UNESCAPED_UNICODE);
                break;
            }

            $pedidos = obtenerPedidosPendientesCliente($conexion, $empresa_idx_local, $entidad_id);
            echo json_encode($pedidos, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar':
            if (!isset($_POST['detalles'])) {
                enviarRespuesta(['resultado' => false, 'error' => 'No se recibieron los detalles']);
            }

            $detalles = json_decode($_POST['detalles'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                enviarRespuesta(['resultado' => false, 'error' => 'Error al decodificar los detalles: ' . json_last_error_msg()]);
            }

            $data = [
                'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
                'deposito_id' => intval($_POST['deposito_id'] ?? 0),
                'punto_venta_id' => intval($_POST['punto_venta_id'] ?? 0),
                'comprobante_tipo_id' => intval($_POST['comprobante_tipo_id'] ?? 0),
                'entidad_id' => intval($_POST['entidad_id'] ?? 0),
                'entidad_sucursal_id' => intval($_POST['entidad_sucursal_id'] ?? 0),
                'f_emision' => $_POST['f_emision'] ?? '',
                'observaciones' => trim($_POST['observaciones'] ?? ''),
                'detalles' => $detalles,
                'empresa_idx' => $empresa_idx,
                'pagina_idx' => $pagina_idx
            ];

            $resultado = agregarRemitoVenta($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['venta_remito_id'] ?? 0);

            if (!isset($_POST['detalles'])) {
                enviarRespuesta(['resultado' => false, 'error' => 'No se recibieron los detalles']);
            }

            $detalles = json_decode($_POST['detalles'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                enviarRespuesta(['resultado' => false, 'error' => 'Error al decodificar los detalles: ' . json_last_error_msg()]);
            }

            $data = [
                'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
                'deposito_id' => intval($_POST['deposito_id'] ?? 0),
                'punto_venta_id' => intval($_POST['punto_venta_id'] ?? 0),
                'comprobante_tipo_id' => intval($_POST['comprobante_tipo_id'] ?? 0),
                'entidad_id' => intval($_POST['entidad_id'] ?? 0),
                'entidad_sucursal_id' => intval($_POST['entidad_sucursal_id'] ?? 0),
                'f_emision' => $_POST['f_emision'] ?? '',
                'observaciones' => trim($_POST['observaciones'] ?? ''),
                'detalles' => $detalles,
                'empresa_idx' => $empresa_idx,
                'pagina_idx' => $pagina_idx
            ];

            $resultado = editarRemitoVenta($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['venta_remito_id'] ?? $_GET['venta_remito_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $remito = obtenerRemitoVentaPorId($conexion, $id, $empresa_idx);
            if ($remito) {
                echo json_encode($remito, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Remito de venta no encontrado'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'obtener_comprobantes_tipos':
            $tipos = obtenerComprobantesTiposRemitos($conexion);
            echo json_encode($tipos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_clientes':
            $clientes = obtenerClientesRemitos($conexion, $empresa_idx);
            echo json_encode($clientes, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_sucursales':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);
            $sucursales = obtenerSucursalesClienteRemitos($conexion, $entidad_id, $empresa_idx_local);
            echo json_encode($sucursales, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_condiciones_cliente':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);

            if (empty($entidad_id)) {
                echo json_encode(['success' => false, 'error' => 'ID de cliente no proporcionado']);
                break;
            }

            $condiciones = obtenerListaPrecioVigenteClienteRemitos($conexion, $entidad_id);
            if ($condiciones) {
                echo json_encode(['success' => true, 'data' => $condiciones]);
            } else {
                echo json_encode(['success' => false, 'data' => null]);
            }
            break;

        case 'buscar_productos_cliente':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            $q = $_GET['q'] ?? '';
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);

            if (empty($entidad_id)) {
                echo json_encode(['error' => null, 'productos' => []], JSON_UNESCAPED_UNICODE);
                break;
            }

            $condicion_comercial = obtenerListaPrecioVigenteClienteRemitos($conexion, $entidad_id);
            if (!$condicion_comercial || empty($condicion_comercial['lista_precio_id'])) {
                echo json_encode(['error' => 'sin_lista_precios', 'productos' => []], JSON_UNESCAPED_UNICODE);
                break;
            }

            $productos = buscarProductosClienteRemitos($conexion, $empresa_idx_local, $entidad_id, $q);
            echo json_encode(['error' => null, 'productos' => $productos], JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log("Excepción en ventas_remitos_ajax.php: " . $e->getMessage());
    manejarError('Error del servidor: ' . $e->getMessage(), 500);
}

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
?>