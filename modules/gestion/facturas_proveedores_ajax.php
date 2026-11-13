<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 2, 'paginas_url' => ['facturas_proveedores.php']]);
require_once __DIR__ . '/../../core/empresa_config.php';
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

$empresa_idx = intval($ctx['empresa_id']);
// Página fija (conf__paginas 57 = facturas_proveedores.php), nunca del request: define qué funciones/transiciones valen.
$pagina_idx = 57;

if (!$conexion) {
    manejarError('Error de conexión a la base de datos', 500);
}

try {
    switch ($accion) {
        case 'listar':
            $filtro_estado = trim((string)($_GET['filtro_estado'] ?? ''));
            $facturas = obtenerFacturasProveedor($conexion, $empresa_idx, $pagina_idx, $filtro_estado);
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
            // La función tiene que estar asignada al perfil del usuario (core/permisos.php), no solo existir para el estado
            permisos_exigir_funcion($conexion, (int)($pagina_idx), (string)($accion_js));
            $resultado = ejecutarTransicionEstado($conexion, $factura_proveedor_id, $accion_js, $ctx['empresa_id'], $pagina_idx,
                                                  trim((string)($_POST['motivo'] ?? '')));
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
            // Si la página tiene la función 'agregar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'agregar', true);
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
                'otros_impuestos' => floatval($_POST['otros_impuestos'] ?? 0),
                'total' => floatval($_POST['total'] ?? 0),
                'descuento_general_pct' => floatval($_POST['descuento_general_pct'] ?? 0),
                'modalidad_descuento_factura' => trim($_POST['modalidad_descuento_factura'] ?? 'EN_FACTURA_GLOBAL'),
                'precio_compra_contempla_descuento' => isset($_POST['precio_compra_contempla_descuento']) ? intval($_POST['precio_compra_contempla_descuento']) : 1,
                'descuento_nc_pct' => floatval($_POST['descuento_nc_pct'] ?? 0),
                'observaciones' => trim($_POST['observaciones'] ?? ''),
                'orden_compra_id' => intval($_POST['orden_compra_id'] ?? 0),
                'certificacion_id' => intval($_POST['certificacion_id'] ?? 0),
                'comprobante_asociado_id' => intval($_POST['comprobante_asociado_id'] ?? 0),
                'tesoreria_cuenta_debito_id' => intval($_POST['tesoreria_cuenta_debito_id'] ?? 0),
                'detalles' => $detalles,
                'empresa_idx' => $empresa_idx,
                'pagina_idx' => $pagina_idx
            ];
            if (isset($_POST['impuestos_adicionales'])) {   // percepciones recibidas (JSON); vacío = sin impuestos
                $data['impuestos_adicionales'] = (string)$_POST['impuestos_adicionales'];
            }

            $resultado = agregarFacturaProveedor($conexion, $data);
            // Cargada desde archivo: liga el archivo original y copia el CAE leído
            $lectura_adjunto_id = intval($_POST['lectura_adjunto_id'] ?? 0);
            if (!empty($resultado['resultado']) && $lectura_adjunto_id > 0) {
                require_once __DIR__ . '/facturas_proveedores_lectura_model.php';
                fplVincularAdjunto($conexion, $empresa_idx, intval($resultado['factura_proveedor_id']), $lectura_adjunto_id);
            }
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'editar':
            // Si la página tiene la función 'editar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'editar', true);
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
                'otros_impuestos' => floatval($_POST['otros_impuestos'] ?? 0),
                'total' => floatval($_POST['total'] ?? 0),
                'descuento_general_pct' => floatval($_POST['descuento_general_pct'] ?? 0),
                'modalidad_descuento_factura' => trim($_POST['modalidad_descuento_factura'] ?? 'EN_FACTURA_GLOBAL'),
                'precio_compra_contempla_descuento' => isset($_POST['precio_compra_contempla_descuento']) ? intval($_POST['precio_compra_contempla_descuento']) : 1,
                'descuento_nc_pct' => floatval($_POST['descuento_nc_pct'] ?? 0),
                'observaciones' => trim($_POST['observaciones'] ?? ''),
                'orden_compra_id' => intval($_POST['orden_compra_id'] ?? 0),
                'certificacion_id' => intval($_POST['certificacion_id'] ?? 0),
                'comprobante_asociado_id' => intval($_POST['comprobante_asociado_id'] ?? 0),
                'tesoreria_cuenta_debito_id' => intval($_POST['tesoreria_cuenta_debito_id'] ?? 0),
                'detalles' => $detalles,
                'empresa_idx' => $empresa_idx
            ];
            if (isset($_POST['impuestos_adicionales'])) {   // percepciones recibidas (JSON); vacío = sin impuestos
                $data['impuestos_adicionales'] = (string)$_POST['impuestos_adicionales'];
            }
            
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

        case 'obtener_alicuotas_iva_marcas':   // alícuotas de la empresa con sus marcas (no gravado / exento)
            $stmt = mysqli_prepare($conexion, "SELECT iva_alicuota_id, iva_alicuota, porcentaje, es_gravado, es_exento, es_no_gravado
                                               FROM gestion__impuestos__iva_alicuotas WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                                               ORDER BY porcentaje DESC, iva_alicuota_id");
            $empresa_alic = intval($ctx['empresa_id']);
            mysqli_stmt_bind_param($stmt, "i", $empresa_alic);
            mysqli_stmt_execute($stmt);
            $alicuotas = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
            mysqli_stmt_close($stmt);
            echo json_encode(['success' => true, 'data' => $alicuotas], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_cuentas_debito':   // cuentas BANCO de la empresa para el débito directo en cuenta
            echo json_encode(['success' => true, 'data' => fpCuentasDebito($conexion, $ctx['empresa_id'])], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_comprobantes_tipos':
            $tipos = obtenerComprobantesTipos($conexion, $empresa_idx);
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

        case 'obtener_catalogo_productos':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);
            $catalogo = obtenerCatalogoProductosCompra($conexion, $empresa_idx_local, $entidad_id);
            enviarRespuesta(['success' => true, 'data' => $catalogo]);
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

        // Notas de crédito / débito: comprobante asociado y aplicación de NC con saldo (OP en $0)
        case 'obtener_asiento':
            $asiento = fpObtenerAsientoFactura($conexion, intval($_GET['factura_proveedor_id'] ?? 0), $ctx['empresa_id']);
            echo json_encode(['success' => (bool) $asiento, 'data' => $asiento], JSON_UNESCAPED_UNICODE);
            break;

        case 'comprobantes_asociables':
            $tipo_nota = intval($_GET['comprobante_tipo_id'] ?? 0);
            echo json_encode(['success' => true, 'es_nota' => fpTipoEsNota($conexion, $ctx['empresa_id'], $tipo_nota),
                'data' => fpComprobantesAsociables($conexion, $ctx['empresa_id'], intval($_GET['entidad_id'] ?? 0), $tipo_nota)], JSON_UNESCAPED_UNICODE);
            break;

        case 'notas_credito_disponibles':
            $disp = fpNotasCreditoDisponiblesFactura($conexion, intval($_GET['factura_proveedor_id'] ?? 0), $ctx['empresa_id']);
            echo json_encode(['success' => true, 'data' => $disp], JSON_UNESCAPED_UNICODE);
            break;

        case 'aplicar_notas_credito':
            $disp = fpNotasCreditoDisponiblesFactura($conexion, intval($_POST['factura_proveedor_id'] ?? 0), $ctx['empresa_id']);
            if (!$disp) {
                echo json_encode(['success' => false, 'error' => 'La factura no tiene saldo o no hay notas de crédito para aplicar.'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $aplicaciones = json_decode($_POST['aplicaciones'] ?? '[]', true);
            if (!is_array($aplicaciones)) $aplicaciones = [];
            echo json_encode(fpAplicarNotasCredito($conexion, $ctx['empresa_id'], $disp['comprobante_id'], $aplicaciones, $ctx['usuario_id']), JSON_UNESCAPED_UNICODE);
            break;

        case 'listar_oc_pendientes':
            // OC del proveedor (Confirmado / Pend. Recepción / Recibido parcial) con algo pendiente de recibir
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            $factura_excluir_id = intval($_GET['factura_proveedor_id'] ?? 0);
            if ($entidad_id <= 0) {
                echo json_encode(['success' => false, 'data' => [], 'message' => 'Seleccione un proveedor'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $ordenes = fpOcPendientesProveedor($conexion, $ctx['empresa_id'], $entidad_id, $factura_excluir_id);
            echo json_encode(['success' => true, 'data' => $ordenes, 'message' => ''], JSON_UNESCAPED_UNICODE);
            break;

        case 'listar_certificaciones_pendientes':
            require_once __DIR__ . '/../../core/contratos/contratos_service.php';
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            $orden_compra_id = intval($_GET['orden_compra_id'] ?? 0);
            if ($entidad_id <= 0) {
                echo json_encode(['success' => false, 'data' => [], 'message' => 'Seleccione un proveedor'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $certificaciones = contratoCertificacionesDisponiblesFacturar($conexion, $entidad_id, $ctx['empresa_id'], $orden_compra_id);
            echo json_encode(['success' => true, 'data' => $certificaciones, 'message' => ''], JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_oc_lineas':
            $orden_compra_id = intval($_GET['orden_compra_id'] ?? 0);
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            $factura_excluir_id = intval($_GET['factura_proveedor_id'] ?? 0);
            $ordenes = fpOcPendientesProveedor($conexion, $ctx['empresa_id'], $entidad_id, $factura_excluir_id);
            $orden = null;
            foreach ($ordenes as $o) {
                if ($o['orden_compra_id'] === $orden_compra_id) {
                    $orden = $o;
                    break;
                }
            }
            if (!$orden) {
                echo json_encode(['success' => false, 'data' => null, 'message' => 'La orden de compra no tiene pendientes para este proveedor'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $orden['lineas'] = fpOcLineas($conexion, $ctx['empresa_id'], $orden_compra_id, $factura_excluir_id);
            echo json_encode(['success' => true, 'data' => $orden, 'message' => ''], JSON_UNESCAPED_UNICODE);
            break;

        case 'vincular_producto_proveedor':
            $resultado = vincularProductoProveedor($conexion, [
                'producto_id' => intval($_POST['producto_id'] ?? 0),
                'entidad_id' => intval($_POST['entidad_id'] ?? 0),
                'codigo_proveedor' => trim($_POST['codigo_proveedor'] ?? ''),
                'empresa_id' => $ctx['empresa_id']
            ]);
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
            $comprobante_subgrupo_id = intval($_GET['comprobante_subgrupo_id'] ?? 0);   // 0: Facturas de Compra (FC) de la empresa
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
            $empresa_idx_local = intval($ctx['empresa_id']);
            $comprobante_subgrupo_id = emp_subgrupo_valido_id($conexion, $empresa_idx_local, $_GET['comprobante_subgrupo_id'] ?? 0, 'FC');
            
            $sql = "SELECT 
                        config.empresa_impuesto_config_id,
                        config.empresa_id,
                        -- Impuesto
                        config.impuesto_tipo_id,
                        it.impuesto_tipo,
                        it.codigo_afip,
                        it.es_percepcion,
                        it.es_retencion,
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

        case 'generar_asiento_contable':
            error_log("=== CASE generar_asiento_contable ===");
            $factura_proveedor_id = intval($_POST['factura_proveedor_id'] ?? 0);
            $empresa_idx_local = intval($_POST['empresa_idx'] ?? $empresa_idx);
            
            error_log("factura_proveedor_id: $factura_proveedor_id");
            error_log("empresa_idx_local: $empresa_idx_local");
            
            $resultado = generarAsientoContableFactura($conexion, $factura_proveedor_id, $empresa_idx_local);
            error_log("Resultado: " . print_r($resultado, true));
            
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
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
        
        
        
        case 'obtener_opciones_importacion_arca':
            $sucursales = obtenerSucursalesEmpresa($conexion, $empresa_idx);
            $condiciones = obtenerCondicionesPago($conexion, $empresa_idx);
            $primer_sucursal = !empty($sucursales[0]['sucursal_id']) ? intval($sucursales[0]['sucursal_id']) : 0;
            $depositos = $primer_sucursal ? obtenerDepositosPorSucursal($conexion, $primer_sucursal, $empresa_idx) : [];
            echo json_encode([
                'success' => true,
                'sucursales' => $sucursales,
                'condiciones' => $condiciones,
                'depositos' => $depositos
            ], JSON_UNESCAPED_UNICODE);
            break;

        case 'previsualizar_importacion_arca':
            // Filas del archivo tal como las leyó el navegador; el modelo las interpreta
            $filas = json_decode($_POST['filas'] ?? '[]', true);
            if (!is_array($filas)) {
                echo json_encode(['success' => false, 'error' => 'Datos inválidos para previsualización'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $resultado = fpPrevisualizarImportacionArca($conexion, $empresa_idx, $filas, !empty($_POST['crear_proveedores_auto']));
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_importacion_arca':
            $filas = json_decode($_POST['filas'] ?? '[]', true);
            if (!is_array($filas) || empty($filas)) {
                echo json_encode(['success' => false, 'error' => 'No hay comprobantes para importar.'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $opciones = [
                'sucursal_id' => intval($_POST['sucursal_id'] ?? 0),
                'deposito_id' => intval($_POST['deposito_id'] ?? 0),
                'condicion_pago_id' => intval($_POST['condicion_pago_id'] ?? 0),
                'crear_proveedores_auto' => !empty($_POST['crear_proveedores_auto']),
                'pagina_idx' => $pagina_idx
            ];
            $resultado = fpImportarComprobantesArca($conexion, $empresa_idx, $filas, $opciones, $ctx['usuario_id']);
            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        // ===== Carga desde archivo (PDF / JPG / PNG): mismo permiso que "Agregar"; las que escriben piden CSRF =====
        case 'lectura_procesar':        // sube el archivo y lo lee (QR / texto / OCR y, si hace falta, IA)
        case 'lectura_reintentar_ia':   // vuelve a leer el mismo archivo con IA
        case 'lectura_propuesta':       // rearma la propuesta con la última lectura
        case 'lectura_crear_proveedor': // alta del proveedor con el CUIT y la razón social leídos
        case 'lectura_guardar_lote':    // carga de varios archivos: guarda en Borrador los que quedaron listos
        case 'lectura_items_proveedor': // el proveedor de la lectura carga (o no) los ítems producto por producto
            require_once __DIR__ . '/../../core/csrf.php';
            require_once __DIR__ . '/facturas_proveedores_lectura_model.php';
            csrf_exigir();
            // Como el manejador de arriba, pero respeta los @ (getimagesize / imagecreatefromstring de un archivo dañado)
            set_error_handler(function ($errno, $errstr, $errfile, $errline) {
                if (!(error_reporting() & $errno)) return true;
                error_log("Error PHP: $errstr en $errfile línea $errline");
                manejarError("Error interno del servidor: $errstr");
            });
            $usuario_id = intval($ctx['usuario_id']);
            if (!usuario_puede_funcion($conexion, $usuario_id, $empresa_idx, $pagina_idx, 'agregar')) {
                http_response_code(403);
                enviarRespuesta(['success' => false, 'message' => 'No tiene permiso para cargar facturas de proveedores.']);
            }
            try {
                if ($accion === 'lectura_guardar_lote') {
                    $ids = array_slice(array_values(array_filter(array_map('intval', (array)($_POST['adjunto_ids'] ?? [])))), 0, 200);
                    if (!$ids) throw new RuntimeException('No hay archivos para guardar.');
                    set_time_limit(240);
                    $sucursal_id = intval($_POST['sucursal_id'] ?? 0);
                    $items = [];
                    foreach ($ids as $id) {   // uno que falla no corta a los demás
                        try {
                            $items[] = ['adjunto_id' => $id] + fplGuardarBorrador($conexion, $empresa_idx, $usuario_id, $id, $sucursal_id);
                        } catch (Throwable $ex) {
                            error_log("lectura_guardar_lote adjunto $id: " . $ex->getMessage());
                            $items[] = ['adjunto_id' => $id, 'success' => false, 'message' => $ex->getMessage(), 'motivos' => []];
                        }
                    }
                    enviarRespuesta(['success' => true, 'data' => $items,
                                     'message' => count(array_filter(array_column($items, 'success'))) . ' de ' . count($items) . ' guardadas en Borrador.']);
                } elseif ($accion === 'lectura_procesar') {
                    set_time_limit(240);
                    $adjunto = fplGuardarArchivo($conexion, $empresa_idx, $usuario_id, $_FILES['archivo'] ?? null);
                    $cliente = json_decode((string)($_POST['cliente'] ?? '{}'), true);
                    $resultado = fplProcesar($conexion, $empresa_idx, $usuario_id, $adjunto, is_array($cliente) ? $cliente : [],
                                             !empty($_POST['forzar_ia']));
                    if ($adjunto['previo']) {
                        $p = $adjunto['previo'];
                        $resultado['avisos'][] = 'Este mismo archivo ya está adjunto a la factura '
                            . str_pad((string)$p['comprobante_pv'], 5, '0', STR_PAD_LEFT) . '-' . str_pad((string)$p['comprobante_nro'], 8, '0', STR_PAD_LEFT)
                            . ' de ' . $p['entidad_nombre'] . '.';
                    }
                } else {
                    $adjunto = fplAdjunto($conexion, $empresa_idx, intval($_POST['adjunto_id'] ?? 0));
                    if (!$adjunto) throw new RuntimeException('Archivo no encontrado.');
                    if ($accion === 'lectura_reintentar_ia') {
                        set_time_limit(240);
                        $resultado = fplProcesar($conexion, $empresa_idx, $usuario_id, $adjunto, [], true);
                    } else {
                        if ($accion === 'lectura_crear_proveedor') {
                            fplCrearProveedor($conexion, $empresa_idx, $usuario_id, $adjunto['adjunto_id']);
                        } elseif ($accion === 'lectura_items_proveedor') {
                            // Solo el proveedor que salió de la lectura (por CUIT), no uno que mande el navegador
                            $previa = fplPropuestaGuardada($conexion, $empresa_idx, $adjunto['adjunto_id']);
                            $entidad_id = intval($previa['form']['entidad_id'] ?? 0);
                            if ($entidad_id <= 0) throw new RuntimeException('Primero tiene que estar cargado el proveedor.');
                            fplGuardarLecturaItemsProveedor($conexion, $empresa_idx, $entidad_id, !empty($_POST['leer_items']));
                        }
                        $resultado = fplPropuestaGuardada($conexion, $empresa_idx, $adjunto['adjunto_id']);
                    }
                }
                enviarRespuesta($resultado);
            } catch (RuntimeException $ex) {
                error_log("facturas_proveedores lectura ($accion): " . $ex->getMessage());
                enviarRespuesta(['success' => false, 'message' => $ex->getMessage()]);
            }
            break;

        case 'lectura_adjuntos_factura':   // archivos originales de una factura de la empresa
            require_once __DIR__ . '/facturas_proveedores_lectura_model.php';
            enviarRespuesta(['success' => true, 'data' => fplAdjuntosFactura($conexion, $empresa_idx, intval($_GET['factura_proveedor_id'] ?? 0))]);
            break;

        case 'lectura_archivo':   // ver / descargar el archivo original (solo de la empresa de la sesión)
            require_once __DIR__ . '/facturas_proveedores_lectura_model.php';
            $adjunto = fplAdjunto($conexion, $empresa_idx, intval($_GET['adjunto_id'] ?? 0));
            if (!$adjunto) {
                http_response_code(404);
                enviarRespuesta(['success' => false, 'message' => 'Archivo no encontrado.']);
            }
            if (ob_get_length()) ob_clean();
            $nombre = preg_replace('/[^\w.\- ]/u', '_', $adjunto['nombre_original']);
            header('Content-Type: ' . $adjunto['mime']);
            header('Content-Length: ' . filesize($adjunto['ruta_abs']));
            header('Content-Disposition: ' . (!empty($_GET['descargar']) ? 'attachment' : 'inline') . '; filename="' . $nombre . '"');
            header('X-Content-Type-Options: nosniff');   // el tipo es PDF / JPG / PNG (finfo al subirlo)
            readfile($adjunto['ruta_abs']);
            exit;

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