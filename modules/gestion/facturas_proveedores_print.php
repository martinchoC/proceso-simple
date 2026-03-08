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
require_once "ordenes_compra_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 65);

if (!$conexion) {
    manejarError('Error de conexión a la base de datos', 500);
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

        case 'obtener_sucursales_empresa':
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);
            $sucursales = obtenerSucursalesEmpresa($conexion, $empresa_idx_local);
            echo json_encode($sucursales, JSON_UNESCAPED_UNICODE);
            break;
        case 'ejecutar_accion':
            $orden_compra_id = intval($_POST['orden_compra_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';
            $empresa_idx = intval($_POST['empresa_idx'] ?? 0);
            $pagina_id = intval($_POST['pagina_idx'] ?? 0);
            
            $resultado = ejecutarTransicionEstado($conexion, $orden_compra_id, $accion_js, $empresa_idx, $pagina_id);
            echo json_encode($resultado);
            break;

        case 'agregar':
            if (!isset($_POST['detalles'])) {
                enviarRespuesta(['resultado' => false, 'error' => 'No se recibieron los detalles']);
            }
            
            $detalles = json_decode($_POST['detalles'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                enviarRespuesta(['resultado' => false, 'error' => 'Error al decodificar los detalles: ' . json_last_error_msg()]);
            }
            
            // LOG TEMPORAL para depuración
            error_log("=== DATOS RECIBIDOS EN AGREGAR ===");
            error_log("POST: " . print_r($_POST, true));
            error_log("detalles decodificados: " . print_r($detalles, true));
            
            $data = [
                'comprobante_tipo_id' => intval($_POST['comprobante_tipo_id'] ?? 0),
                'comprobante_letra' => trim($_POST['comprobante_letra'] ?? ''),
                'comprobante_pv' => trim($_POST['comprobante_pv'] ?? ''),
                'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
                'comprobante_nro' => trim($_POST['comprobante_nro'] ?? ''),
                'entidad_id' => intval($_POST['entidad_id'] ?? 0),
                'entidad_sucursal_id' => intval($_POST['entidad_sucursal_id'] ?? 0),
                'f_emision' => $_POST['f_emision'] ?? '',
                'f_entrega_estimada' => $_POST['f_entrega_estimada'] ?? null,
                'condicion_pago_id' => intval($_POST['condicion_pago_id'] ?? 0),
                'moneda_id' => intval($_POST['moneda_id'] ?? 0),
                'tipo_cambio' => floatval($_POST['tipo_cambio'] ?? 0),
                'direccion_entrega' => trim($_POST['direccion_entrega'] ?? ''),
                'subtotal' => floatval($_POST['subtotal'] ?? 0),
                'descuentos' => floatval($_POST['descuentos'] ?? 0),
                'impuestos' => floatval($_POST['impuestos'] ?? 0),
                'total' => floatval($_POST['total'] ?? 0),
                'observaciones' => trim($_POST['observaciones'] ?? ''),
                'detalles' => $detalles,
                'empresa_idx' => $empresa_idx,
                'pagina_idx' => $pagina_idx
            ];

            $resultado = agregarOrdenCompra($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['orden_compra_id'] ?? 0);
            
            error_log("=== EDITAR - ID recibido: $id ===");
            error_log("POST completo: " . print_r($_POST, true));
            
            
            if (!isset($_POST['detalles'])) {
                enviarRespuesta(['resultado' => false, 'error' => 'No se recibieron los detalles']);
            }
            
            $detalles = json_decode($_POST['detalles'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                enviarRespuesta(['resultado' => false, 'error' => 'Error al decodificar los detalles: ' . json_last_error_msg()]);
            }
            
            error_log("detalles decodificados: " . print_r($detalles, true));
            
            $data = [
                'comprobante_tipo_id' => intval($_POST['comprobante_tipo_id'] ?? 0),
                'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
                'comprobante_pv' => trim($_POST['comprobante_pv'] ?? ''),                
                'comprobante_nro' => trim($_POST['comprobante_nro'] ?? ''),
                'entidad_id' => intval($_POST['entidad_id'] ?? 0),
                'entidad_sucursal_id' => intval($_POST['entidad_sucursal_id'] ?? 0),
                'f_emision' => $_POST['f_emision'] ?? '',
                'f_entrega_estimada' => $_POST['f_entrega_estimada'] ?? null,
                'condicion_pago_id' => intval($_POST['condicion_pago_id'] ?? 0),
                'moneda_id' => intval($_POST['moneda_id'] ?? 0),
                'tipo_cambio' => floatval($_POST['tipo_cambio'] ?? 0),
                'direccion_entrega' => trim($_POST['direccion_entrega'] ?? ''),
                'subtotal' => floatval($_POST['subtotal'] ?? 0),
                'descuentos' => floatval($_POST['descuentos'] ?? 0),
                'impuestos' => floatval($_POST['impuestos'] ?? 0),
                'total' => floatval($_POST['total'] ?? 0),
                'observaciones' => trim($_POST['observaciones'] ?? ''),
                'detalles' => $detalles,
                'empresa_idx' => $empresa_idx
            ];
            
            error_log("data armada para editarOrdenCompra: " . print_r($data, true));

            $resultado = editarOrdenCompra($conexion, $id, $data);
            error_log("resultado de editarOrdenCompra: " . print_r($resultado, true));
            
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

            $orden = obtenerOrdenCompraPorId($conexion, $id, $empresa_idx);
            if ($orden) {
                echo json_encode($orden, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Orden de compra no encontrada'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'obtener_comprobantes_tipos':
            $tipos = obtenerComprobantesTipos($conexion);
            echo json_encode($tipos, JSON_UNESCAPED_UNICODE);
            break;

       case 'obtener_proveedores':
            $proveedores = obtenerProveedores($conexion, $empresa_idx);
            echo json_encode($proveedores, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_sucursales':
                $entidad_id = intval($_GET['entidad_id'] ?? 0);
                $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx); // <-- USAR EL QUE VIENE
                $sucursales = obtenerSucursales($conexion, $entidad_id, $empresa_idx_local);
                echo json_encode($sucursales, JSON_UNESCAPED_UNICODE);
                break;

        case 'obtener_condiciones_pago':
            $empresa_idx_param = intval($_GET['empresa_idx'] ?? $empresa_idx);
            $condiciones = obtenerCondicionesPago($conexion, $empresa_idx_param);
            echo json_encode($condiciones, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_monedas':
            $monedas = obtenerMonedas($conexion, $empresa_idx);
            echo json_encode($monedas, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_productos_proveedor':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            if (empty($entidad_id)) {
                echo json_encode(['error' => 'ID de proveedor no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $productos = obtenerProductosPorProveedor($conexion, $empresa_idx, $entidad_id);
            echo json_encode($productos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_codigo_proveedor':
            $producto_id = intval($_GET['producto_id'] ?? 0);
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            
            if (empty($producto_id) || empty($entidad_id)) {
                echo json_encode(['success' => false, 'error' => 'Datos incompletos'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $codigo_proveedor = obtenerCodigoProveedor($conexion, $producto_id, $entidad_id, $empresa_idx);
            echo json_encode(['success' => true, 'codigo_proveedor' => $codigo_proveedor], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_categorias_productos':
            $empresa_idx_param = intval($_GET['empresa_idx'] ?? $empresa_idx);
            $categorias = obtenerCategoriasProductos($conexion, $empresa_idx_param);
            echo json_encode($categorias, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_unidades_medida':
            $unidades = obtenerUnidadesMedida($conexion);
            echo json_encode($unidades, JSON_UNESCAPED_UNICODE);
            break;
            
        case 'buscar_productos_proveedor':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            $q = $_GET['q'] ?? '';
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);
            
            if (empty($entidad_id)) {
                echo json_encode([]);
                break;
            }
            
            $productos = buscarProductosPorProveedor($conexion, $empresa_idx_local, $entidad_id, $q);
            echo json_encode($productos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_ultimo_precio':
            $producto_id = intval($_GET['producto_id'] ?? 0);
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);
            
            $resultado = obtenerUltimoPrecioProducto($conexion, $producto_id, $entidad_id, $empresa_idx_local);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;
        case 'obtener_alicuotas_iva':
            $sql = "SELECT iva_alicuota_id, codigo, iva_alicuota, porcentaje 
                    FROM gestion__impuestos__iva_alicuotas 
                    WHERE empresa_id = ? 
                    AND tabla_estado_registro_id = 1 
                    ORDER BY porcentaje";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            $alicuotas = [];
            while ($fila = mysqli_fetch_assoc($result)) {
                $alicuotas[] = $fila;
            }
            mysqli_stmt_close($stmt);
            echo json_encode($alicuotas, JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar_producto_rapido':
            $data = [
                'producto_codigo' => trim($_POST['producto_codigo'] ?? ''),
                'producto_nombre' => trim($_POST['producto_nombre'] ?? ''),
                'codigo_barras' => trim($_POST['codigo_barras'] ?? ''),
                'producto_descripcion' => trim($_POST['producto_descripcion'] ?? ''),
                'producto_categoria_id' => intval($_POST['producto_categoria_id'] ?? 0),
                'iva_alicuota_id' => intval($_POST['iva_alicuota_id'] ?? 1),
                'unidad_medida_id' => intval($_POST['unidad_medida_id'] ?? 0),
                'codigo_proveedor' => trim($_POST['codigo_proveedor'] ?? ''),
                'entidad_id' => intval($_POST['entidad_id'] ?? 0),
                'empresa_idx' => $empresa_idx
            ];
            
            $resultado = agregarProductoRapido($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log("Excepción en ordenes_compra_ajax.php: " . $e->getMessage());
    manejarError('Error del servidor: ' . $e->getMessage(), 500);
}

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
?>