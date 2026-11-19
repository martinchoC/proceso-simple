<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 5, 'paginas_url' => ['ventas_pedidos.php', 'ventas_pedidos_gestion.php']]);
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
require_once "ventas_pedidos_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

$empresa_idx = intval($ctx['empresa_id']);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 0);
// Solo una página de este módulo (si no, la de ventas_pedidos.php del módulo): contra ella se validan botones y acciones
$pagina_idx = permisos_pagina_modulo($conn, 5, (int)$pagina_idx, 'ventas_pedidos.php');

// Vendedores y carteras: un usuario vendedor solo abre y opera pedidos de su cartera (sin el indicador, nada)
if (in_array($accion, ['obtener', 'ejecutar_accion', 'copiar', 'editar', 'obtener_remitos_pedido'], true)) {
    try {
        vcar_exigir_pedido($conexion, intval($ctx['empresa_id']), intval($ctx['usuario_id']), intval($_POST['venta_pedido_id'] ?? $_GET['venta_pedido_id'] ?? 0));
    } catch (InvalidArgumentException $e) {
        enviarRespuesta(['success' => false, 'resultado' => false, 'error' => $e->getMessage(), 'message' => $e->getMessage()]);
    }
}


if (!$conexion) {
    manejarError('Error de conexión a la base de datos', 500);
}

try {
    switch ($accion) {
        case 'listar':
            $pedidos = obtenerPedidosVenta($conexion, $empresa_idx, $pagina_idx);
            echo json_encode($pedidos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $pagina_id = (int)$pagina_idx; // validada por permisos_pagina_modulo()
            $boton_agregar = obtenerBotonAgregar($conexion, $pagina_id);
            error_log("Botón agregar obtenido: " . print_r($boton_agregar, true));
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_sucursales_empresa':
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);
            $sucursales = obtenerSucursalesEmpresa($conexion, $empresa_idx_local);
            echo json_encode($sucursales, JSON_UNESCAPED_UNICODE);
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
            $venta_pedido_id = intval($_POST['venta_pedido_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';
            $empresa_idx = intval($_POST['empresa_idx'] ?? 0);
            $pagina_id = (int)$pagina_idx; // validada por permisos_pagina_modulo()
            
            // La función tiene que estar asignada al perfil del usuario (core/permisos.php), no solo existir para el estado
            permisos_exigir_funcion($conexion, (int)($pagina_id), (string)($accion_js));
            $resultado = ejecutarTransicionEstado($conexion, $venta_pedido_id, $accion_js, $empresa_idx, $pagina_id);
            echo json_encode($resultado);
            break;

        case 'copiar':
            // Página fija de Pedidos de Clientes (no la del request) y permiso de la función "Copiar"
            $pagina_copiar = pagina_id_por_url($conexion, 5, 'ventas_pedidos.php', $pagina_idx);
            if (!usuario_puede_funcion($conexion, intval($ctx['usuario_id']), intval($ctx['empresa_id']), $pagina_copiar, 'copiar')) {
                enviarRespuesta(['success' => false, 'message' => 'No tiene permiso para copiar pedidos.']);
            }
            $res = copiarPedidoVenta($conexion, intval($_POST['venta_pedido_id'] ?? 0), intval($ctx['empresa_id']), $pagina_copiar);
            enviarRespuesta([
                'success' => !empty($res['resultado']),
                'data' => !empty($res['resultado']) ? ['venta_pedido_id' => $res['venta_pedido_id']] : null,
                'message' => !empty($res['resultado']) ? 'Pedido copiado en Borrador.' : ($res['error'] ?? 'No se pudo copiar el pedido.')
            ]);
            break;

        case 'obtener_clientes_con_sucursales':
            $clientes = obtenerClientes($conexion, $empresa_idx);
            $resultado = [];
            
            foreach ($clientes as $cliente) {
                $item = [
                    'tipo' => 'cliente',
                    'entidad_id' => $cliente['entidad_id'],
                    'entidad_nombre' => $cliente['entidad_nombre'],
                    'sucursales' => []
                ];
                
                $sucursales = obtenerSucursales($conexion, $cliente['entidad_id'], $empresa_idx);
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
            
        case 'agregar':
            // Si la página tiene la función 'agregar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'agregar', true);
            if (!isset($_POST['detalles'])) {
                enviarRespuesta(['resultado' => false, 'error' => 'No se recibieron los detalles']);
            }
            
            $detalles = json_decode($_POST['detalles'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                enviarRespuesta(['resultado' => false, 'error' => 'Error al decodificar los detalles: ' . json_last_error_msg()]);
            }
            
            $data = [
                'comprobante_tipo_id' => intval($_POST['comprobante_tipo_id'] ?? 0),
                'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
                'punto_venta_id' => intval($_POST['punto_venta_id'] ?? 0),
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
                'descuento_general_pct' => floatval($_POST['descuento_general_pct'] ?? 0),
                'descuentos' => floatval($_POST['descuentos'] ?? 0),
                'impuestos' => floatval($_POST['impuestos'] ?? 0),
                'total' => floatval($_POST['total'] ?? 0),
                'observaciones' => trim($_POST['observaciones'] ?? ''),
                'vendedor_entidad_id' => intval($_POST['vendedor_entidad_id'] ?? 0),
                'detalles' => $detalles,
                'empresa_idx' => $empresa_idx,
                'pagina_idx' => $pagina_idx
            ];

            $resultado = agregarPedidoVenta($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            // Si la página tiene la función 'editar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'editar', true);
            $id = intval($_POST['venta_pedido_id'] ?? 0);
            
            if (!isset($_POST['detalles'])) {
                enviarRespuesta(['resultado' => false, 'error' => 'No se recibieron los detalles']);
            }
            
            $detalles = json_decode($_POST['detalles'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                enviarRespuesta(['resultado' => false, 'error' => 'Error al decodificar los detalles: ' . json_last_error_msg()]);
            }
            
            $data = [
                'comprobante_tipo_id' => intval($_POST['comprobante_tipo_id'] ?? 0),
                'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
                'punto_venta_id' => intval($_POST['punto_venta_id'] ?? 0),
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
                'descuento_general_pct' => floatval($_POST['descuento_general_pct'] ?? 0),
                'descuentos' => floatval($_POST['descuentos'] ?? 0),
                'impuestos' => floatval($_POST['impuestos'] ?? 0),
                'total' => floatval($_POST['total'] ?? 0),
                'observaciones' => trim($_POST['observaciones'] ?? ''),
                'vendedor_entidad_id' => intval($_POST['vendedor_entidad_id'] ?? 0),
                'detalles' => $detalles,
                'empresa_idx' => $empresa_idx,
                'pagina_idx' => $pagina_idx
            ];

            $resultado = editarPedidoVenta($conexion, $id, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['venta_pedido_id'] ?? $_GET['venta_pedido_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $pedido = obtenerPedidoVentaPorId($conexion, $id, $empresa_idx, $pagina_idx);
            if ($pedido) {
                echo json_encode($pedido, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Pedido de venta no encontrado'], JSON_UNESCAPED_UNICODE);
            }
            break;

        // Solapa "Remitos" del modal de pedido: lista los remitos ya generados
        // que tocan a este pedido. pagina_idx_remitos es la página de
        // ventas_remitos (88 por defecto ahí), NO la de este módulo — son
        // motores de estado independientes con sus propias transiciones.
        case 'obtener_remitos_pedido':
            $pedido_id = intval($_GET['venta_pedido_id'] ?? 0);
            $pagina_idx_remitos = permisos_pagina_modulo($conexion, 5, intval($_GET['pagina_idx_remitos'] ?? 0), 'ventas_remitos.php');
            if (empty($pedido_id)) {
                echo json_encode([], JSON_UNESCAPED_UNICODE);
                break;
            }

            $remitos = obtenerRemitosDePedido($conexion, $empresa_idx, $pedido_id, $pagina_idx_remitos);
            echo json_encode($remitos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_comprobantes_tipos':
            $punto_venta_id = intval($_GET['punto_venta_id'] ?? 0);
            $tipos = obtenerComprobantesTipos($conexion, $empresa_idx, $pagina_idx, $punto_venta_id);
            echo json_encode($tipos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_clientes':
            $clientes = obtenerClientes($conexion, $empresa_idx);
            echo json_encode($clientes, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_sucursales':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);
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

        case 'obtener_productos_cliente':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);
            
            if (empty($entidad_id)) {
                echo json_encode(['error' => 'ID de cliente no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            $productos = obtenerProductosPorCliente($conexion, $empresa_idx_local, $entidad_id);
            echo json_encode($productos, JSON_UNESCAPED_UNICODE);
            break;
        
        case 'obtener_condiciones_cliente':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);
            
            if (empty($entidad_id)) {
                echo json_encode(['success' => false, 'error' => 'ID de cliente no proporcionado']);
                break;
            }
            
            $condiciones = obtenerCondicionesCliente($conexion, $entidad_id, $empresa_idx_local);
            if ($condiciones) {
                echo json_encode(['success' => true, 'data' => $condiciones]);
            } else {
                echo json_encode(['success' => false, 'data' => null]);
            }
            break;
        
        case 'obtener_lista_precios_cliente':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);
            
            if (empty($entidad_id)) {
                echo json_encode(['success' => false, 'error' => 'ID de cliente no proporcionado']);
                break;
            }
            
            $lista_precio = obtenerListaPreciosCliente($conexion, $entidad_id, $empresa_idx_local);
            echo json_encode($lista_precio, JSON_UNESCAPED_UNICODE);
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
            
        // Vendedores y carteras (core/vendedores.php): si el usuario puede elegir el vendedor y la lista de vendedores
        case 'vendedores_opciones':
            echo json_encode(['success' => true, 'data' => vcar_opciones_pedido($conexion, intval($ctx['empresa_id']), intval($ctx['usuario_id']))], JSON_UNESCAPED_UNICODE);
            break;

        // Reglas de precio de venta (core/precios_venta.php): descuento de cada línea según escalas, promociones y topes
        case 'resolver_descuentos':
            echo json_encode(prv_resolver_pantalla($conexion, intval($ctx['empresa_id']), intval($ctx['usuario_id']), 'pedido', $_POST), JSON_UNESCAPED_UNICODE);
            break;

        case 'buscar_productos_cliente':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            $q = $_GET['q'] ?? '';
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);
            
            if (empty($entidad_id)) {
                echo json_encode(['error' => null, 'productos' => []], JSON_UNESCAPED_UNICODE);
                break;
            }

            // Cliente sin lista de precios vigente: igual se buscan los productos, con importe 0.
            $productos = buscarProductosPorCliente($conexion, $empresa_idx_local, $entidad_id, $q, intval($_GET['sucursal_id'] ?? 0));
            echo json_encode(['error' => null, 'productos' => $productos], JSON_UNESCAPED_UNICODE);
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
                'empresa_idx' => $empresa_idx
            ];
            
            $resultado = agregarProductoRapido($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log("Excepción en ventas_pedidos_ajax.php: " . $e->getMessage());
    manejarError('Error del servidor: ' . $e->getMessage(), 500);
}

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
?>