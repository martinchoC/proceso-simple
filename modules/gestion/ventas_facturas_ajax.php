<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 2, 'paginas_url' => ['ventas_facturas.php', 'ventas_remitos.php', 'ventas_remitos_pendientes_facturacion.php']]);
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/ventas_facturas_model.php';
// Cobro desde la solapa "Medios de Pago": el recibo se arma con el modelo de cobranzas.
require_once __DIR__ . '/tesoreria_core_model.php';
require_once __DIR__ . '/cobranzas_model.php';
header('Content-Type: application/json; charset=utf-8');

$accion = $_GET['accion'] ?? $_POST['accion'] ?? '';
$empresa_id = intval($ctx['empresa_id']);
$pagina_id = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 56);
// Solo una página de este módulo (si no, la de ventas_facturas.php del módulo): contra ella se validan botones y acciones
$pagina_id = permisos_pagina_modulo($conexion, 2, (int)$pagina_id, 'ventas_facturas.php');

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
            $entidad_param = isset($_GET['entidad_id']) ? $_GET['entidad_id'] : (isset($_POST['entidad_id']) ? $_POST['entidad_id'] : null);
            // solo_raiz=1: sin NC/ND (tipos dependientes), para facturar desde un remito
            $solo_raiz = !empty($_GET['solo_raiz'] ?? $_POST['solo_raiz'] ?? null);
            echo json_encode(vfTiposPorPuntoVenta($conexion, $empresa_id, intval($_GET['punto_venta_id'] ?? $_POST['punto_venta_id'] ?? 0), $pagina_id, $entidad_param, $solo_raiz), JSON_UNESCAPED_UNICODE);
            break;
        // entidad_id = 0: consumidor final (lista Mostrador, contado). Ídem remitos_pendientes y buscar_productos.
        case 'condicion_cliente':
            echo json_encode(vfObtenerCondicionDestinatario($conexion, intval($_GET['entidad_id'] ?? 0), $empresa_id), JSON_UNESCAPED_UNICODE);
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
            // Si la página tiene la función 'agregar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_id), 'agregar', true);
            $detalles = json_decode($_POST['detalles'] ?? '[]', true);
            if (!is_array($detalles)) throw new Exception('Detalle inválido.');
            $data = $_POST;
            $data['detalles'] = $detalles;
            echo json_encode(guardarVentaFactura($conexion, $empresa_id, $pagina_id, $data), JSON_UNESCAPED_UNICODE);
            break;
        case 'calcular_percepciones':
            $detalles = json_decode($_POST['detalles'] ?? '[]', true);
            if (!is_array($detalles)) throw new Exception('Detalle inválido.');
            $data = $_POST;
            $data['detalles'] = $detalles;
            echo json_encode(vfPercepcionesPreview($conexion, $empresa_id, $data), JSON_UNESCAPED_UNICODE);
            break;
        case 'editar':
            // Si la página tiene la función 'editar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_id), 'editar', true);
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
            if ($accion_js === 'aprobada') @set_time_limit(180);   // puede pedir el CAE a ARCA
            // Cobro de contado a consumidor final: medios elegidos en la pantalla al aprobar (sin ellos, efectivo)
            $medios_contado = isset($_POST['medios_contado']) ? json_decode($_POST['medios_contado'], true) : null;
            if (isset($_POST['medios_contado']) && !is_array($medios_contado)) throw new Exception('Medios de pago inválidos.');
            // La función tiene que estar asignada al perfil del usuario (core/permisos.php), no solo existir para el estado
            permisos_exigir_funcion($conexion, (int)($pagina_id), (string)($accion_js));
            echo json_encode(vfEjecutarTransicionEstado($conexion, $id, $accion_js, $empresa_id, $pagina_id, $medios_contado), JSON_UNESCAPED_UNICODE);
            break;
        case 'cobro_contado_info':
            $info = vfCobroContadoInfo($conexion, intval($_GET['venta_factura_id'] ?? 0), $ctx['empresa_id']);
            echo json_encode($info ? ['success' => true, 'data' => $info] : ['success' => false, 'message' => 'Factura no encontrada.'], JSON_UNESCAPED_UNICODE);
            break;
        case 'generar_asiento_contable':
            $id = intval($_POST['venta_factura_id'] ?? $_GET['venta_factura_id'] ?? 0);
            echo json_encode(vfGenerarAsientoContableFactura($conexion, $id, $empresa_id), JSON_UNESCAPED_UNICODE);
            break;
        case 'obtener_asiento':
            $id = intval($_GET['venta_factura_id'] ?? $_POST['venta_factura_id'] ?? 0);
            $asiento = vfObtenerAsientoFactura($conexion, $id, $empresa_id);
            if ($asiento) {
                echo json_encode(['success' => true, 'data' => $asiento], JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['success' => false, 'error' => 'No se encontró asiento contable para esta factura.'], JSON_UNESCAPED_UNICODE);
            }
            break;
        // Solapa "Medios de Pago": recibo (RCX) con punto de venta imputado a la factura. La función
        // 'cobrar' se valida contra la página de facturas, no contra el pagina_idx del request.
        case 'cobro_factura':
            $cobro = vfCobroFactura($conexion, intval($_GET['venta_factura_id'] ?? 0), $ctx['empresa_id'], pagina_id_por_url($conexion, 2, 'ventas_facturas.php'));
            echo json_encode($cobro ? ['success' => true, 'data' => $cobro] : ['success' => false, 'message' => 'Factura no encontrada.'], JSON_UNESCAPED_UNICODE);
            break;
        case 'cobro_maestros':
            echo json_encode(['success' => true, 'data' => vfMaestrosCobro($conexion, $ctx['empresa_id'])], JSON_UNESCAPED_UNICODE);
            break;
        case 'cobro_tipos_por_punto_venta':
            echo json_encode(['success' => true, 'data' => vfTiposReciboPorPuntoVenta($conexion, $ctx['empresa_id'], intval($_GET['punto_venta_id'] ?? 0))], JSON_UNESCAPED_UNICODE);
            break;
        // Notas de crédito / débito: comprobante asociado y aplicación de NC con saldo (recibo en $0)
        case 'comprobantes_asociables':
            $tipo_nota = intval($_GET['comprobante_tipo_id'] ?? 0);
            echo json_encode(['success' => true, 'es_nota' => vfTipoEsNota($conexion, $ctx['empresa_id'], $tipo_nota),
                'es_credito' => vfTipoEsCredito($conexion, $ctx['empresa_id'], $tipo_nota),
                'data' => vfComprobantesAsociables($conexion, $ctx['empresa_id'], intval($_GET['entidad_id'] ?? 0), $tipo_nota)], JSON_UNESCAPED_UNICODE);
            break;
        // Ítems del comprobante asociado a una NC, para elegir cuáles acreditar
        case 'items_asociado':
            echo json_encode(['success' => true, 'data' => vfItemsComprobanteAsociado($conexion, $ctx['empresa_id'], intval($_GET['entidad_id'] ?? 0),
                intval($_GET['comprobante_id'] ?? 0), intval($_GET['excluir_factura_id'] ?? 0))], JSON_UNESCAPED_UNICODE);
            break;
        case 'notas_credito_disponibles':
            $disp = vfNotasCreditoDisponiblesFactura($conexion, intval($_GET['venta_factura_id'] ?? 0), $ctx['empresa_id']);
            echo json_encode(['success' => true, 'data' => $disp], JSON_UNESCAPED_UNICODE);
            break;
        case 'aplicar_notas_credito':
            $disp = vfNotasCreditoDisponiblesFactura($conexion, intval($_POST['venta_factura_id'] ?? 0), $ctx['empresa_id']);
            if (!$disp) {
                echo json_encode(['success' => false, 'error' => 'La factura no tiene saldo o no hay notas de crédito para aplicar.'], JSON_UNESCAPED_UNICODE);
                break;
            }
            $aplicaciones = json_decode($_POST['aplicaciones'] ?? '[]', true);
            if (!is_array($aplicaciones)) $aplicaciones = [];
            echo json_encode(vfAplicarNotasCredito($conexion, $ctx['empresa_id'], $disp['comprobante_id'], $aplicaciones, $ctx['usuario_id']), JSON_UNESCAPED_UNICODE);
            break;
        case 'emitir_recibo':
            $data = $_POST;
            $data['medios'] = json_decode($_POST['medios'] ?? '[]', true);
            echo json_encode(vfEmitirReciboFactura($conexion, intval($_POST['venta_factura_id'] ?? 0), $ctx['empresa_id'], pagina_id_por_url($conexion, 2, 'ventas_facturas.php'), $ctx['usuario_id'], $data), JSON_UNESCAPED_UNICODE);
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
