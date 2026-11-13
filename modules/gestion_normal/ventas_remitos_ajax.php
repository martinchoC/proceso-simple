<?php
// Sesión y empresa validadas antes de cualquier otra cosa (ver core/ajax_guard.php).
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 5, 'paginas_url' => ['ventas_pedidos.php', 'ventas_pedidos_gestion.php', 'ventas_remitos.php', 'ventas_remitos_pendientes_facturacion.php']]);
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

$empresa_idx = intval($ctx['empresa_id']);
$pagina_idx = intval($_GET['pagina_idx'] ?? $_POST['pagina_idx'] ?? 88);
// Solo una página de este módulo (si no, la de ventas_remitos.php del módulo): contra ella se validan botones y acciones
$pagina_idx = permisos_pagina_modulo($conn, 5, (int)$pagina_idx, 'ventas_remitos.php');

if (!$conexion) {
    manejarError('Error de conexión a la base de datos', 500);
}

try {
    switch ($accion) {
        case 'listar':
            $solo_pendientes = isset($_GET['solo_pendientes_facturacion']) && ($_GET['solo_pendientes_facturacion'] === '1' || $_GET['solo_pendientes_facturacion'] === 'true');
            $remitos = obtenerRemitosVenta($conexion, $empresa_idx, $pagina_idx, $solo_pendientes);
            echo json_encode($remitos, JSON_UNESCAPED_UNICODE);
            break;

        case 'listar_pendientes_facturacion':
            $remitos = obtenerRemitosVenta($conexion, $empresa_idx, $pagina_idx, true);
            echo json_encode($remitos, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_boton_agregar':
            $pagina_id = intval($_GET['pagina_idx'] ?? 0);
            $boton_agregar = obtenerBotonAgregarRemito($conexion, $pagina_id);
            echo json_encode($boton_agregar, JSON_UNESCAPED_UNICODE);
            break;

        // Combo único: ya no depende de sucursal_id. Lista los PV de la empresa
        // cuya boca asociada es un depósito activo (ver plan acordado con Pablo:
        // se elimina el combo de sucursal y el de depósito, un solo select).
        case 'obtener_puntos_venta':
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);
            $puntos_venta = obtenerPuntosVentaDepositoRemitos($conexion, $empresa_idx_local);
            echo json_encode($puntos_venta, JSON_UNESCAPED_UNICODE);
            break;

        case 'ejecutar_accion':
            $venta_remito_id = intval($_POST['venta_remito_id'] ?? 0);
            $accion_js = $_POST['accion_js'] ?? '';
            $empresa_idx_local = intval($_POST['empresa_idx'] ?? 0);
            $pagina_id = intval($_POST['pagina_idx'] ?? 0);

            // La función tiene que estar asignada al perfil del usuario (core/permisos.php), no solo existir para el estado
            permisos_exigir_funcion($conexion, (int)($pagina_id), (string)($accion_js));
            $resultado = ejecutarTransicionEstadoRemito($conexion, $venta_remito_id, $accion_js, $empresa_idx_local, $pagina_id);
            echo json_encode($resultado);
            break;

        case 'obtener_clientes_con_sucursales':
            $clientes = obtenerClientesRemitos($conexion, $empresa_idx);
            $resultado = [];

            // Agrupamos todas las sucursales de la empresa en una sola consulta para evitar N+1
            $sql_suc = "SELECT sucursal_id, entidad_id, sucursal_nombre
                        FROM gestion__entidades_sucursales
                        WHERE empresa_id = ?
                        AND tabla_estado_registro_id = 1
                        ORDER BY sucursal_nombre";
            $stmt_suc = mysqli_prepare($conexion, $sql_suc);
            $suc_por_entidad = [];
            if ($stmt_suc) {
                mysqli_stmt_bind_param($stmt_suc, "i", $empresa_idx);
                mysqli_stmt_execute($stmt_suc);
                $res_suc = mysqli_stmt_get_result($stmt_suc);
                while ($fila_suc = mysqli_fetch_assoc($res_suc)) {
                    $suc_por_entidad[$fila_suc['entidad_id']][] = [
                        'sucursal_id' => $fila_suc['sucursal_id'],
                        'sucursal_nombre' => $fila_suc['sucursal_nombre']
                    ];
                }
                mysqli_stmt_close($stmt_suc);
            }

            foreach ($clientes as $cliente) {
                $eid = $cliente['entidad_id'];
                $resultado[] = [
                    'tipo' => 'cliente',
                    'entidad_id' => $eid,
                    'entidad_nombre' => $cliente['entidad_nombre'],
                    'condicion_fiscal' => $cliente['condicion_fiscal'] ?? 'Consumidor Final',
                    'condicion_fiscal_codigo' => $cliente['condicion_fiscal_codigo'] ?? 'CF',
                    'sucursales' => $suc_por_entidad[$eid] ?? []
                ];
            }

            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'obtener_pedidos_pendientes_cliente':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);
            $punto_venta_id_local = intval($_GET['punto_venta_id'] ?? 0);
            // Opcional: acota los pendientes a un pedido puntual (usado desde la
            // solapa "Remitos" del módulo de pedidos). Sin este parámetro, se
            // mantiene el comportamiento de siempre: todos los pendientes del cliente.
            $pedido_id_local = intval($_GET['pedido_id'] ?? 0);

            if (empty($entidad_id)) {
                echo json_encode([], JSON_UNESCAPED_UNICODE);
                break;
            }

            // Si ya hay punto de venta elegido en el remito, se ordena/filtra por la
            // ubicación del producto en la boca de ese PV (la relevante para armar el
            // remito). Sin PV todavía, se listan/ordenan las ubicaciones de todas las bocas.
            $boca_id_local = null;
            if (!empty($punto_venta_id_local)) {
                $ubicacion_pv = resolverBocaYSucursalPorPuntoVentaRemitos($conexion, $empresa_idx_local, $punto_venta_id_local);
                $boca_id_local = $ubicacion_pv['boca_id'] ?? null;
            }

            $pedidos = obtenerPedidosPendientesCliente($conexion, $empresa_idx_local, $entidad_id, $boca_id_local, $pedido_id_local ?: null);
            echo json_encode($pedidos, JSON_UNESCAPED_UNICODE);
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
                'punto_venta_id' => intval($_POST['punto_venta_id'] ?? 0),
                'comprobante_tipo_id' => intval($_POST['comprobante_tipo_id'] ?? 0),
                'entidad_id' => intval($_POST['entidad_id'] ?? 0),
                'entidad_sucursal_id' => intval($_POST['entidad_sucursal_id'] ?? 0),
                'consumidor_final_nombre' => trim($_POST['consumidor_final_nombre'] ?? ''),
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
            // Si la página tiene la función 'editar', el perfil tiene que tenerla asignada (core/permisos.php)
            permisos_exigir_funcion($conexion, (int)($pagina_idx), 'editar', true);
            $id = intval($_POST['venta_remito_id'] ?? 0);

            if (!isset($_POST['detalles'])) {
                enviarRespuesta(['resultado' => false, 'error' => 'No se recibieron los detalles']);
            }

            $detalles = json_decode($_POST['detalles'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                enviarRespuesta(['resultado' => false, 'error' => 'Error al decodificar los detalles: ' . json_last_error_msg()]);
            }

            $data = [
                'punto_venta_id' => intval($_POST['punto_venta_id'] ?? 0),
                'comprobante_tipo_id' => intval($_POST['comprobante_tipo_id'] ?? 0),
                'entidad_id' => intval($_POST['entidad_id'] ?? 0),
                'entidad_sucursal_id' => intval($_POST['entidad_sucursal_id'] ?? 0),
                'consumidor_final_nombre' => trim($_POST['consumidor_final_nombre'] ?? ''),
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
                // Solapa "Facturar" del modal: solo con la función Facturar (facturado_parcial) de esta página de remitos
                $remito['puede_facturar'] = permisos_puede($conexion, (int)$pagina_idx, 'facturado_parcial');
                // Botones del remito para su estado (filtrados por perfil): la gestión de pedidos los usa para
                // confirmar un remito recién guardado aunque sus ítems sean de otro pedido.
                $remito['botones'] = obtenerBotonesPorEstadoRemito($conexion, (int)$pagina_idx, $remito['tabla_estado_registro_id'] ?? 0);
                echo json_encode($remito, JSON_UNESCAPED_UNICODE);
            } else {
                echo json_encode(['error' => 'Remito de venta no encontrado'], JSON_UNESCAPED_UNICODE);
            }
            break;

        case 'obtener_comprobantes_tipos':
            $punto_venta_id = intval($_GET['punto_venta_id'] ?? 0);
            $tipos = obtenerComprobantesTiposRemitos($conexion, $empresa_idx, $pagina_idx, $punto_venta_id);
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

        // consumidor_final=1 (sin entidad_id): busca con la lista Mostrador, sin descuento.
        case 'buscar_productos_cliente':
            $entidad_id = intval($_GET['entidad_id'] ?? 0);
            $consumidor_final = intval($_GET['consumidor_final'] ?? 0) === 1;
            $q = $_GET['q'] ?? '';
            $empresa_idx_local = intval($_GET['empresa_idx'] ?? $empresa_idx);

            if (empty($entidad_id) && !$consumidor_final) {
                echo json_encode(['error' => null, 'productos' => []], JSON_UNESCAPED_UNICODE);
                break;
            }
            if ($consumidor_final) {
                $entidad_id = 0;
            }

            $condicion_comercial = obtenerCondicionPrecioRemito($conexion, $empresa_idx_local, $entidad_id);
            if (!$condicion_comercial) {
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