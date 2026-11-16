<?php
/**
 * API de la aplicación del vendedor (doc 28, pasos 9-7 / 9-8). JSON {success, data, message}.
 * Sesión del ERP (ajax_guard) + usa_app_vendedor + usuario ligado a un vendedor activo; cada cliente se valida contra
 * su cartera vigente. Las escrituras son POST con CSRF y exigen las funciones de la página de pedidos del módulo
 * (agregar / confirmar_pedido), como la pantalla del ERP. Precios y pedidos: core/pedidos_venta (pven_*); el alta,
 * la edición y la confirmación usan las funciones del modelo de pedidos del módulo de la empresa (las mismas del ERP).
 *
 * GET  inicio                       empresa, vendedor, sucursales con pedidos, tope de descuento manual
 * GET  clientes        q            cartera (con su borrador APP)
 * GET  cliente         entidad_id   condición, entrega estimada, borrador, historial y cuenta (solo lectura)
 * GET  buscar          entidad_id, q, sucursal_id   productos con sus ofertas (código, nombre, marca, código de barras)
 * POST valorizar       entidad_id, sucursal_id, items (JSON), descuento_manual_pct
 * POST guardar         entidad_id, sucursal_id, items, observaciones, descuento_manual_pct, pedido_id, confirmar (0/1)
 */
declare(strict_types=1);

require_once __DIR__ . '/../core/ajax_guard.php';
$ctx = ajax_guard(['params' => ['empresa_idx']]);
require_once __DIR__ . '/../core/csrf.php';
require_once __DIR__ . '/../core/permisos.php';
require_once __DIR__ . '/../core/pedidos_venta/app_vendedor.php';

header('Content-Type: application/json; charset=utf-8');
$conexion = $conn;
$empresa_id = (int) $ctx['empresa_id'];
$usuario_id = (int) $ctx['usuario_id'];

function app_responder(bool $ok, $data = null, string $mensaje = '', int $status = 200): void
{
    http_response_code($status);
    echo json_encode(['success' => $ok, 'data' => $data, 'message' => $mensaje], JSON_UNESCAPED_UNICODE);
    exit;
}

function app_items(): array
{
    $items = json_decode((string) ($_POST['items'] ?? '[]'), true);
    if (!is_array($items)) {
        throw new InvalidArgumentException('Ítems inválidos.');
    }
    // Solo producto, presentación y cantidad: cualquier otro dato del navegador se descarta
    return array_map(fn($i) => ['producto_id' => intval($i['producto_id'] ?? 0), 'presentacion_id' => intval($i['presentacion_id'] ?? 0),
                                'cantidad' => (float) ($i['cantidad'] ?? 0)], array_values($items));
}

try {
    $vendedor_id = pven_app_vendedor($conexion, $empresa_id, $usuario_id);
} catch (InvalidArgumentException $e) {
    app_responder(false, null, $e->getMessage(), 403);
}

// Modelo de pedidos (y cuenta corriente) del módulo de gestión de la empresa: las mismas funciones del ERP
$pagina = pven_pagina_pedidos($conexion, $empresa_id);
$modulo = (int) (pven_filas($conexion, "SELECT modulo_id FROM conf__paginas WHERE pagina_id = ?", 'i', [$pagina])[0]['modulo_id'] ?? 0);
$carpeta = $modulo === 5 ? 'gestion_normal' : 'gestion';
require_once __DIR__ . '/../modules/' . $carpeta . '/ventas_pedidos_model.php';
$conexion = $conn;

/** Cliente de la cartera vigente del vendedor. @throws InvalidArgumentException */
function app_cliente(mysqli $conexion, int $empresa_id, int $vendedor_id, int $cliente_id): void
{
    if (!in_array($cliente_id, array_map(fn($c) => (int) $c['cliente_entidad_id'], vcar_cartera($conexion, $empresa_id, $vendedor_id)), true)) {
        throw new InvalidArgumentException('El cliente no está en tu cartera.');
    }
}

/** Sucursales del usuario (accesos por sucursal) con punto de venta para pedidos. */
function app_sucursales(mysqli $conexion, int $empresa_id, int $pagina): array
{
    $out = [];
    foreach (obtenerSucursalesEmpresa($conexion, $empresa_id) as $s) {
        if (pven_pv_tipo($conexion, $empresa_id, $pagina, (int) $s['sucursal_id'])[0] > 0) {
            $out[] = ['sucursal_id' => (int) $s['sucursal_id'], 'nombre' => $s['sucursal_nombre']];
        }
    }
    return $out;
}

function app_sucursal(mysqli $conexion, int $empresa_id, int $pagina, int $pedida): int
{
    $ids = array_column(app_sucursales($conexion, $empresa_id, $pagina), 'sucursal_id');
    if (!$ids) {
        throw new InvalidArgumentException('No tenés acceso a ninguna sucursal con punto de venta para pedidos.');
    }
    return in_array($pedida, $ids, true) ? $pedida : (int) $ids[0];
}

$accion = (string) ($_GET['accion'] ?? $_POST['accion'] ?? '');
try {
    switch ($accion) {
        case 'inicio':
            $usa_prv = prv_activo($conexion, $empresa_id, 'pedido');
            $v = pven_filas($conexion, "SELECT entidad_nombre FROM gestion__entidades WHERE entidad_id = ?", 'i', [$vendedor_id]);
            app_responder(true, [
                'empresa_id' => $empresa_id, 'vendedor' => $v[0]['entidad_nombre'] ?? '', 'sucursales' => app_sucursales($conexion, $empresa_id, $pagina),
                'descuento_manual' => $usa_prv, 'tope_descuento' => $usa_prv ? prv_tope_usuario($conexion, $empresa_id, $usuario_id) : 0,
            ]);

        case 'clientes':
            app_responder(true, pven_app_clientes($conexion, $empresa_id, $vendedor_id, (string) ($_GET['q'] ?? '')));

        case 'cliente':
            $cli = intval($_GET['entidad_id'] ?? 0);
            app_cliente($conexion, $empresa_id, $vendedor_id, $cli);
            $cond = pven_cliente($conexion, $empresa_id, $cli);
            $canal = $cond['lista_precio_id'] > 0 ? cvta_canal_lista($conexion, $empresa_id, $cond['lista_precio_id']) : null;
            require_once __DIR__ . '/../modules/' . $carpeta . '/clientes_ctacte_model.php';
            $cuenta = ctacteResumen($conexion, $empresa_id, $cli);
            app_responder(true, [
                'entidad_id' => $cli, 'canal' => $canal['nombre'] ?? null, 'descuento_cliente' => $cond['cliente_descuento_general'],
                'entrega' => pven_app_entrega_estimada($conexion, $empresa_id, $cli),
                'borrador' => pven_app_borrador($conexion, $empresa_id, $vendedor_id, $cli),
                'historial' => pven_app_historial($conexion, $empresa_id, $cli),
                'cuenta' => ['saldo' => (float) ($cuenta['saldo'] ?? 0), 'vencido' => (float) ($cuenta['vencido'] ?? 0),
                             'comprobantes_pendientes' => (int) ($cuenta['comprobantes_pendientes'] ?? 0)],
            ]);

        case 'buscar':
            $cli = intval($_GET['entidad_id'] ?? 0);
            app_cliente($conexion, $empresa_id, $vendedor_id, $cli);
            $encontrados = pven_buscar_productos($conexion, $empresa_id, (string) ($_GET['q'] ?? ''));
            $suc = app_sucursal($conexion, $empresa_id, $pagina, intval($_GET['sucursal_id'] ?? 0));
            $of = pven_ofertas($conexion, $empresa_id, $cli, array_column($encontrados, 'producto_id'), $suc);
            $out = [];
            foreach ($encontrados as $e) {
                if (isset($of['productos'][$e['producto_id']])) {
                    $out[] = $of['productos'][$e['producto_id']] + ['presentacion_escaneada' => $e['presentacion_id']];
                }
            }
            app_responder(true, ['productos' => $out, 'canal' => $of['canal']]);

        case 'valorizar':
            csrf_exigir();
            $cli = intval($_POST['entidad_id'] ?? 0);
            app_cliente($conexion, $empresa_id, $vendedor_id, $cli);
            $items = app_items();
            if (!$items) {
                app_responder(true, ['lineas' => [], 'cabecera' => ['subtotal' => 0, 'impuestos' => 0, 'total' => 0]]);
            }
            $v = pven_valorizar($conexion, $empresa_id, $cli, $items, ['sucursal_id' => app_sucursal($conexion, $empresa_id, $pagina, intval($_POST['sucursal_id'] ?? 0)),
                'usuario_id' => $usuario_id, 'descuento_manual_pct' => (float) ($_POST['descuento_manual_pct'] ?? 0)]);
            app_responder(true, ['lineas' => array_map(fn($d, $l) => $l + ['neto_gravado' => $d['neto_gravado'], 'total_linea' => $d['total_linea']],
                $v['detalles'], $v['lineas']), 'cabecera' => $v['cabecera']]);

        case 'guardar':
            csrf_exigir();
            permisos_exigir_funcion($conexion, $pagina, 'agregar', true);
            $cli = intval($_POST['entidad_id'] ?? 0);
            app_cliente($conexion, $empresa_id, $vendedor_id, $cli);
            $suc = app_sucursal($conexion, $empresa_id, $pagina, intval($_POST['sucursal_id'] ?? 0));
            [$data] = pven_datos_pedido($conexion, $empresa_id, [
                'entidad_id' => $cli, 'sucursal_id' => $suc, 'items' => app_items(), 'origen' => 'APP', 'usuario_id' => $usuario_id,
                'descuento_manual_pct' => (float) ($_POST['descuento_manual_pct'] ?? 0), 'observaciones' => (string) ($_POST['observaciones'] ?? ''),
            ]);
            $pedido_id = intval($_POST['pedido_id'] ?? 0);
            if ($pedido_id > 0) {
                $b = pven_app_borrador($conexion, $empresa_id, $vendedor_id, $cli);
                if (!$b || $b['venta_pedido_id'] !== $pedido_id) {
                    throw new InvalidArgumentException('El pedido ya no está en preparación.');
                }
                $r = editarPedidoVenta($conexion, $pedido_id, $data);
            } else {
                $r = agregarPedidoVenta($conexion, $data);
                $pedido_id = (int) ($r['venta_pedido_id'] ?? 0);
            }
            if (empty($r['resultado'])) {
                app_responder(false, null, (string) ($r['error'] ?? 'No se pudo guardar el pedido.'));
            }
            $res = ['venta_pedido_id' => $pedido_id, 'confirmado' => false, 'comprobante_nro' => 0, 'entrega' => null];
            $msg = 'Pedido guardado. Lo podés retomar cuando quieras.';
            if (!empty($_POST['confirmar'])) {
                permisos_exigir_funcion($conexion, $pagina, 'confirmar_pedido');
                $t = ejecutarTransicionEstado($conexion, $pedido_id, 'confirmar_pedido', $empresa_id, $pagina);
                if (!empty($t['success'])) {
                    $res['confirmado'] = true;
                    $res['comprobante_nro'] = (int) ($t['comprobante_nro'] ?? 0);
                    $res['entrega'] = pven_app_entrega_estimada($conexion, $empresa_id, $cli);
                    $msg = 'Pedido confirmado.';
                } else {
                    $msg = 'Quedó guardado sin confirmar: ' . ($t['error'] ?? 'no se pudo confirmar.');
                }
            }
            app_responder(true, $res, $msg);

        default:
            app_responder(false, null, 'Acción desconocida.', 400);
    }
} catch (InvalidArgumentException $e) {
    app_responder(false, null, $e->getMessage(), 422);
}
