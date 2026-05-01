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
require_once "facturas_proveedores_model.php";

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';

if (empty($accion)) {
    manejarError('Acción no especificada', 400);
}

$empresa_idx = intval($_GET['empresa_idx'] ?? $_POST['empresa_idx'] ?? 2);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 52);

if (!$conexion) {
    manejarError('Error de conexión a la base de datos', 500);
}

try {
    switch ($accion) {
        case 'listar':
            $facturas = obtenerFacturasProveedor($conexion, $empresa_idx, $pagina_idx);
            echo json_encode($facturas, JSON_UNESCAPED_UNICODE);
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
            $factura_proveedor_id = intval($_POST['factura_proveedor_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';
            $empresa_idx = intval($_POST['empresa_idx'] ?? 0);
            $pagina_id = intval($_POST['pagina_idx'] ?? 0);
            
            $resultado = ejecutarTransicionEstado($conexion, $factura_proveedor_id, $accion_js, $empresa_idx, $pagina_id);
            echo json_encode($resultado);
            break;

        case 'obtener_proveedores_con_sucursales':
            $proveedores = obtenerProveedores($conexion, $empresa_idx);
            $resultado = [];
            
            foreach ($proveedores as $proveedor) {
                $item = [
                    'tipo' => 'proveedor',
                    'entidad_id' => $proveedor['entidad_id'],
                    'entidad_nombre' => $proveedor['entidad_nombre'],
                    'sucursales' => []
                ];
                
                // Obtener sucursales del proveedor
                $sucursales = obtenerSucursales($conexion, $proveedor['entidad_id'], $empresa_idx);
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
                'comprobante_pv' => trim($_POST['comprobante_pv'] ?? ''),
                'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
                'deposito_id' => intval($_POST['deposito_id'] ?? 0),
                'comprobante_nro' => trim($_POST['comprobante_nro'] ?? ''),
                'entidad_id' => intval($_POST['entidad_id'] ?? 0),
                'entidad_sucursal_id' => intval($_POST['entidad_sucursal_id'] ?? 0),
                'f_emision' => $_POST['f_emision'] ?? '',
                'f_contabilidad' => $_POST['f_contabilidad'] ?? $_POST['f_emision'] ?? '',
                'f_vencimiento' => $_POST['f_vencimiento'] ?? null,
                'condicion_pago_id' => intval($_POST['condicion_pago_id'] ?? 0),
                'moneda_id' => intval($_POST['moneda_id'] ?? 0),
                'tipo_cambio' => floatval($_POST['tipo_cambio'] ?? 0),
                'direccion' => trim($_POST['direccion'] ?? ''),
                'subtotal' => floatval($_POST['subtotal'] ?? 0),
                'descuentos' => floatval($_POST['descuentos'] ?? 0),
                'no_gravado' => floatval($_POST['no_gravado'] ?? 0),
                'exento' => floatval($_POST['exento'] ?? 0),
                'impuestos' => floatval($_POST['impuestos'] ?? 0),
                'total' => floatval($_POST['total'] ?? 0),
                'descuento_general_pct' => floatval($_POST['descuento_general_pct'] ?? 0),
                'observaciones' => trim($_POST['observaciones'] ?? ''),
                'detalles' => $detalles,
                'empresa_idx' => $empresa_idx,
                'pagina_idx' => $pagina_idx
            ];

            $resultado = agregarFacturaProveedor($conexion, $data);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            $id = intval($_POST['factura_proveedor_id'] ?? 0);
            
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
                'deposito_id' => intval($_POST['deposito_id'] ?? 0),
                'comprobante_pv' => trim($_POST['comprobante_pv'] ?? ''),                
                'comprobante_nro' => trim($_POST['comprobante_nro'] ?? ''),
                'entidad_id' => intval($_POST['entidad_id'] ?? 0),
                'entidad_sucursal_id' => intval($_POST['entidad_sucursal_id'] ?? 0),
                'f_emision' => $_POST['f_emision'] ?? '',
                'f_contabilidad' => $_POST['f_contabilidad'] ?? $_POST['f_emision'] ?? '',
                'f_vencimiento' => $_POST['f_vencimiento'] ?? null,
                'condicion_pago_id' => intval($_POST['condicion_pago_id'] ?? 0),
                'moneda_id' => intval($_POST['moneda_id'] ?? 0),
                'tipo_cambio' => floatval($_POST['tipo_cambio'] ?? 0),
                'direccion' => trim($_POST['direccion'] ?? ''),
                'subtotal' => floatval($_POST['subtotal'] ?? 0),
                'descuentos' => floatval($_POST['descuentos'] ?? 0),
                'no_gravado' => floatval($_POST['no_gravado'] ?? 0),
                'exento' => floatval($_POST['exento'] ?? 0),
                'impuestos' => floatval($_POST['impuestos'] ?? 0),
                'total' => floatval($_POST['total'] ?? 0),
                'descuento_general_pct' => floatval($_POST['descuento_general_pct'] ?? 0),
                'observaciones' => trim($_POST['observaciones'] ?? ''),
                'detalles' => $detalles,
                'empresa_idx' => $empresa_idx
            ];
            
            error_log("data armada para editarFacturaProveedor: " . print_r($data, true));

            $resultado = editarFacturaProveedor($conexion, $id, $data);
            error_log("resultado de editarFacturaProveedor: " . print_r($resultado, true));
            
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener':
            $id = intval($_POST['factura_proveedor_id'] ?? $_GET['factura_proveedor_id'] ?? 0);
            if (empty($id)) {
                echo json_encode(['error' => 'ID no proporcionado'], JSON_UNESCAPED_UNICODE);
                break;
            }

            $factura = obtenerFacturaProveedorPorId($conexion, $id, $empresa_idx);
            if ($factura) {
                echo json_encode($factura, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Factura de proveedor no encontrada'], JSON_UNESCAPED_UNICODE);
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
        
        case 'obtener_condiciones_proveedor':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            if (empty($entidad_id)) {
                echo json_encode(['success' => false, 'error' => 'ID de proveedor no proporcionado']);
                break;
            }
            
            $condiciones = obtenerCondicionesProveedor($conexion, $entidad_id, $empresa_idx);
            if ($condiciones) {
                echo json_encode(['success' => true, 'data' => $condiciones]);
            } else {
                echo json_encode(['success' => false, 'data' => null]);
            }
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
        
        case 'obtener_depositos':
            $sucursal_id = intval($_GET['sucursal_id'] ?? 0);
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);
            
            if (empty($sucursal_id)) {
                echo json_encode([]);
                break;
            }
            
            $depositos = obtenerDepositosPorSucursal($conexion, $sucursal_id, $empresa_idx_local);
            echo json_encode($depositos, JSON_UNESCAPED_UNICODE);
            break;
        
        case 'obtener_impuestos_config':
            $comprobante_subgrupo_id = intval($_GET['comprobante_subgrupo_id'] ?? 5);
            $impuestos = obtenerImpuestosConfig($conexion, $empresa_idx, $comprobante_subgrupo_id);
            echo json_encode($impuestos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_impuestos_factura':
            $factura_proveedor_id = intval($_GET['factura_proveedor_id'] ?? 0);
            $impuestos = obtenerImpuestosFactura($conexion, $factura_proveedor_id);
            echo json_encode($impuestos, JSON_UNESCAPED_UNICODE);
            break;

        
       // En facturas_proveedores_ajax.php, reemplazar el case 'obtener_impuestos_config':

        case 'obtener_impuestos_config':
            $comprobante_subgrupo_id = intval($_GET['comprobante_subgrupo_id'] ?? 5);
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);
            
            $sql = "SELECT 
                        config.empresa_impuesto_config_id,
                        config.empresa_id,
                        -- Impuesto
                        config.impuesto_tipo_id,
                        it.impuesto_tipo,
                        it.codigo_afip,
                        -- Jurisdicción
                        config.jurisdiccion_id,
                        j.jurisdiccion_nombre,
                        j.jurisdiccion_codigo,
                        -- Condición fiscal
                        config.condicion_fiscal_id,
                        cf.condicion_fiscal,
                        cf.condicion_fiscal_codigo,
                        -- Configuración
                        config.base_calculo,
                        config.alicuota,
                        config.minimo_imponible,
                        config.monto_fijo,
                        config.aplica_siempre,
                        config.prioridad,
                        config.tipo_calculo,
                        config.f_desde,
                        config.f_hasta
                    FROM 
                        gestion__empresas_impuestos_config AS config
                    INNER JOIN 
                        gestion__empresas_impuestos_config_subgrupos AS sub
                        ON config.empresa_impuesto_config_id = sub.empresa_impuesto_config_id
                    INNER JOIN 
                        gestion__impuestos_tipos AS it
                        ON config.impuesto_tipo_id = it.impuesto_tipo_id
                    LEFT JOIN 
                        gestion__jurisdicciones AS j
                        ON config.jurisdiccion_id = j.jurisdiccion_id
                    LEFT JOIN 
                        gestion__condiciones_fiscales AS cf
                        ON config.condicion_fiscal_id = cf.condicion_fiscal_id
                    WHERE 
                        config.empresa_id = ?
                        AND sub.comprobante_subgrupo_id = ?
                        -- Estados activos (motor de estados)
                        AND config.tabla_estado_registro_id = 1
                        AND sub.tabla_estado_registro_id = 1
                        AND it.tabla_estado_registro_id = 1
                        AND (j.tabla_estado_registro_id = 1 OR j.jurisdiccion_id IS NULL)
                        AND (cf.tabla_estado_registro_id = 1 OR cf.condicion_fiscal_id IS NULL)
                    ORDER BY config.prioridad, it.impuesto_tipo";
            
            $stmt = mysqli_prepare($conexion, $sql);
            if (!$stmt) {
                echo json_encode(['error' => 'Error al consultar impuestos config: ' . mysqli_error($conexion)], JSON_UNESCAPED_UNICODE);
                break;
            }
            
            mysqli_stmt_bind_param($stmt, "ii", $empresa_idx_local, $comprobante_subgrupo_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            
            $impuestos = [];
            while ($fila = mysqli_fetch_assoc($result)) {
                $impuestos[] = $fila;
            }
            mysqli_stmt_close($stmt);
            
            echo json_encode($impuestos, JSON_UNESCAPED_UNICODE);
            break;

        // En facturas_proveedores_ajax.php, corregir el case 'obtener_tipos_impuesto':
        case 'obtener_jurisdicciones':
            $sql = "SELECT jurisdiccion_id, jurisdiccion_codigo, jurisdiccion_nombre 
                    FROM gestion__jurisdicciones 
                    WHERE tabla_estado_registro_id = 1
                    ORDER BY jurisdiccion_nombre";
            $result = mysqli_query($conexion, $sql);
            if (!$result) {
                echo json_encode(['error' => 'Error al consultar jurisdicciones'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $jurisdicciones = [];
            while ($fila = mysqli_fetch_assoc($result)) {
                $jurisdicciones[] = $fila;
            }
            echo json_encode($jurisdicciones, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_tipos_impuesto':
            $sql = "SELECT impuesto_tipo_id, impuesto_tipo, es_retencion, es_percepcion 
                    FROM gestion__impuestos_tipos 
                    WHERE tabla_estado_registro_id = 1 
                    ORDER BY impuesto_tipo";
            $result = mysqli_query($conexion, $sql);
            if (!$result) {
                echo json_encode(['error' => 'Error al consultar tipos de impuesto: ' . mysqli_error($conexion)], JSON_UNESCAPED_UNICODE);
                break;
            }
            $tipos = [];
            while ($fila = mysqli_fetch_assoc($result)) {
                $tipos[] = $fila;
            }
            echo json_encode($tipos, JSON_UNESCAPED_UNICODE);
            break;
        
      
        case 'guardar_impuestos_factura':
            $factura_proveedor_id = intval($_POST['factura_proveedor_id'] ?? 0);
            // Cambiar de 'impuestos' a 'impuestos_adicionales' para coincidir con el JS
            $impuestos_json = $_POST['impuestos_adicionales'] ?? $_POST['impuestos'] ?? '';
            
            if (empty($impuestos_json)) {
                echo json_encode(['success' => false, 'error' => 'No se recibieron impuestos']);
                break;
            }
            
            $impuestos = json_decode($impuestos_json, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                echo json_encode(['success' => false, 'error' => 'Error al decodificar impuestos: ' . json_last_error_msg()]);
                break;
            }
            
            $resultado = guardarImpuestosFactura($conexion, $factura_proveedor_id, $impuestos, $empresa_idx);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;
        
        
        
        default:
            echo json_encode(['error' => 'Acción no definida: ' . $accion], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    error_log("Excepción en facturas_proveedores_ajax.php: " . $e->getMessage());
    manejarError('Error del servidor: ' . $e->getMessage(), 500);
}

if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
?>