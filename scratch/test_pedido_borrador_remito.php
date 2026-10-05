<?php
/**
 * Test automatizado: validación de que un pedido no confirmado (Borrador) no puede remitirse.
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../modules/gestion/ventas_remitos_model.php';
require_once __DIR__ . '/../modules/gestion/ventas_pedidos_model.php';

$empresa_id = 2;
$conexion = $conn;

echo "=== TEST: RESTRICCIÓN DE REMITOS EN PEDIDOS NO CONFIRMADOS (BORRADOR) ===\n";

// 1. Buscar un cliente y un producto válido
$res_c = $conexion->query("SELECT entidad_id FROM gestion__entidades WHERE es_cliente = 1 AND empresa_id = $empresa_id LIMIT 1");
$entidad = $res_c->fetch_assoc();
$entidad_id = intval($entidad['entidad_id'] ?? 3);

$res_p = $conexion->query("SELECT producto_id FROM gestion__productos WHERE empresa_id = $empresa_id AND tabla_estado_registro_id = 1 LIMIT 1");
$prod = $res_p->fetch_assoc();
$producto_id = intval($prod['producto_id'] ?? 1);

// 2. Crear pedido de prueba en estado BORRADOR (137)
$f_hoy = date('Y-m-d');
$conexion->query("INSERT INTO gestion__ventas_pedidos (
                    empresa_id, sucursal_id, punto_venta_id, comprobante_tipo_id, comprobante_nro,
                    comprobante_id, f_emision, entidad_id, moneda_id, tipo_cambio, total,
                    tabla_estado_registro_id
                 ) VALUES (
                    $empresa_id, 2, 4, 1, 99999, 0, '$f_hoy', $entidad_id, 1, 1.0, 500.0, 137
                 )");
$pedido_id = $conexion->insert_id;

$conexion->query("INSERT INTO gestion__ventas_pedidos_detalles (
                    venta_pedido_id, producto_id, cantidad, cantidad_entregada, precio_unitario_bruto,
                    precio_unitario_neto, neto_gravado, total_linea, tabla_estado_registro_id
                 ) VALUES (
                    $pedido_id, $producto_id, 5.0, 0.0, 100.0, 100.0, 500.0, 500.0, 1
                 )");
$vpd_id = $conexion->insert_id;

echo "1. Pedido de prueba creado en estado BORRADOR (ID: $pedido_id, Detalle ID: $vpd_id)\n";

// 3. Consultar pendientes para remitir: debe ser 0 porque el pedido es BORRADOR
$pendientes = obtenerPedidosPendientesCliente($conexion, $empresa_id, $entidad_id, null, $pedido_id);
echo "2. Pendientes para remitir del pedido en Borrador: " . count($pendientes) . " líneas encontradas (Esperado: 0)\n";
if (count($pendientes) !== 0) {
    die("ERROR: Se encontraron líneas pendientes para un pedido en estado Borrador!\n");
}
echo "   [OK] obtenerPedidosPendientesCliente no retorna líneas de pedidos en Borrador.\n";

// 4. Intentar agregar remito contra esa línea de pedido en borrador
$res_remito = agregarRemitoVenta($conexion, [
    'empresa_idx'         => $empresa_id,
    'pagina_idx'          => 88,
    'punto_venta_id'      => 4,
    'comprobante_tipo_id' => 9, // Remito
    'f_emision'           => $f_hoy,
    'entidad_id'          => $entidad_id,
    'detalles'            => [
        [
            'producto_id'             => $producto_id,
            'venta_pedido_detalle_id' => $vpd_id,
            'cantidad'                => 2.0
        ]
    ]
]);

echo "3. Intentando forzar remito sobre pedido en Borrador...\n";
if ($res_remito['resultado'] === true) {
    die("ERROR: Se permitió crear un remito para un pedido en Borrador!\n");
}
echo "   [OK] Remito bloqueado con éxito. Mensaje del motor: " . $res_remito['error'] . "\n";

// 5. Confirmar el pedido (pasar a 139)
$conexion->query("UPDATE gestion__ventas_pedidos SET tabla_estado_registro_id = 139 WHERE venta_pedido_id = $pedido_id");
echo "4. Pedido pasado a estado CONFIRMADO (139)...\n";

$pendientes_post = obtenerPedidosPendientesCliente($conexion, $empresa_id, $entidad_id, null, $pedido_id);
echo "5. Pendientes para remitir del pedido Confirmado: " . count($pendientes_post) . " líneas encontradas (Esperado: 1)\n";
if (count($pendientes_post) !== 1) {
    die("ERROR: El pedido confirmado debió mostrar 1 línea pendiente para remitir!\n");
}
echo "   [OK] El pedido confirmado ahora sí está disponible para remitir.\n";

// 6. Limpiar datos de prueba
$conexion->query("DELETE FROM gestion__ventas_pedidos_detalles WHERE venta_pedido_id = $pedido_id");
$conexion->query("DELETE FROM gestion__ventas_pedidos WHERE venta_pedido_id = $pedido_id");
echo "6. Datos de prueba eliminados.\n";

echo "\n>>> TEST COMPLETADO CON 100% DE ÉXITO <<<\n";
