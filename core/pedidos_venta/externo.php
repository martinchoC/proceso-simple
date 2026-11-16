<?php
/**
 * Pedidos de venta desde afuera de las pantallas del ERP (doc 28, paso 9-3): el carrito del cliente y la aplicación
 * del vendedor. El que llama manda solo producto, presentación y cantidad; precio, descuentos, canal, mínimos,
 * múltiplos y disponibilidad los calcula y valida el servidor con el mismo código que el ERP:
 *   - lista del cliente: pven_condicion_cliente() (= obtenerListaPrecioVigenteCliente del ERP, con cvta_* si el
 *     indicador de canales está prendido);
 *   - ofertas por presentación: lpre_ofertas(); escalas / promociones / topes: precio_resolver();
 *   - líneas: las mismas fórmulas que la pantalla de pedidos (ventas_pedidos.js y ventas-presentaciones.js);
 *   - alta y confirmación: pven_agregar() / pven_ejecutar_transicion() (core/pedidos_venta/pedidos_service.php), que
 *     corren vpre_*, cvta_*, prv_*, vcar_* y vdis_* igual que una carga en el ERP.
 * Página del pedido: "Pedidos Realizados" (ventas_pedidos.php) del módulo de gestión de la empresa.
 * No incluye db.php ni modules/. Errores para el usuario: InvalidArgumentException.
 */

require_once __DIR__ . '/pedidos_service.php';
require_once __DIR__ . '/../listas_precios_presentaciones.php';
require_once __DIR__ . '/../productos_marcas.php';
require_once __DIR__ . '/../entidades_roles.php';

if (!function_exists('pven_filas')) {
    function pven_filas(mysqli $conexion, string $sql, string $tipos = '', array $params = []): array
    {
        $st = mysqli_prepare($conexion, $sql);
        if (!$st) {
            throw new RuntimeException('pven_filas: ' . mysqli_error($conexion));
        }
        if ($tipos !== '') {
            mysqli_stmt_bind_param($st, $tipos, ...$params);
        }
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        $out = [];
        while ($res && ($r = mysqli_fetch_assoc($res))) {
            $out[] = $r;
        }
        mysqli_stmt_close($st);
        return $out;
    }
}

if (!function_exists('pven_r')) {
    /** Redondeo como el Math.round(x * 10^d) / 10^d de las pantallas (importes positivos). */
    function pven_r(float $v, int $d): float
    {
        return round($v, $d);
    }
}

if (!function_exists('pven_pagina_pedidos')) {
    /** Página "Pedidos Realizados" (ventas_pedidos.php) del módulo de gestión de la empresa (2 o 5), o 0. */
    function pven_pagina_pedidos(mysqli $conexion, int $empresa_id): int
    {
        $r = pven_filas($conexion,
            "SELECT p.pagina_id FROM conf__paginas p
               JOIN conf__empresas_modulos em ON em.modulo_id = p.modulo_id AND em.empresa_id = ? AND em.tabla_estado_registro_id = 1
              WHERE p.url = 'ventas_pedidos.php' AND p.modulo_id IN (2, 5) AND p.tabla_estado_registro_id = 1
              ORDER BY p.modulo_id LIMIT 1", 'i', [$empresa_id]);
        return (int) ($r[0]['pagina_id'] ?? 0);
    }
}

if (!function_exists('pven_pv_tipo')) {
    /**
     * Punto de venta de la sucursal y tipo de comprobante de pedido habilitado en él (tabla de la página): el PV web
     * primero, como el carrito. [punto_venta_id, comprobante_tipo_id] o [0, 0].
     */
    function pven_pv_tipo(mysqli $conexion, int $empresa_id, int $pagina_id, int $sucursal_id): array
    {
        $tabla_id = (int) pven_tabla_origen_pagina($conexion, $pagina_id);
        if ($tabla_id <= 0 || $sucursal_id <= 0) {
            return [0, 0];
        }
        $r = pven_filas($conexion,
            "SELECT pv.punto_venta_id, ct.comprobante_tipo_id
               FROM gestion__puntos_venta pv
               JOIN gestion__puntos_venta_comprobantes pvc ON pvc.punto_venta_id = pv.punto_venta_id AND pvc.empresa_id = pv.empresa_id
                    AND pvc.tabla_estado_registro_id = 1
               JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = pvc.comprobante_tipo_id AND ct.tabla_estado_registro_id = 1
                    AND ct.empresa_id IN (0, pv.empresa_id)
               JOIN gestion__comprobantes_subgrupos cs ON cs.comprobante_subgrupo_id = ct.comprobante_subgrupo_id
                    AND cs.tabla_estado_registro_id = 1 AND cs.tabla_id = ? AND cs.empresa_id IN (0, pv.empresa_id)
              WHERE pv.empresa_id = ? AND pv.sucursal_id = ? AND pv.tabla_estado_registro_id = 1
              ORDER BY pv.es_web DESC, pv.punto_venta_id, ct.orden, ct.comprobante_tipo_id
              LIMIT 1", 'iii', [$tabla_id, $empresa_id, $sucursal_id]);
        return $r ? [(int) $r[0]['punto_venta_id'], (int) $r[0]['comprobante_tipo_id']] : [0, 0];
    }
}

if (!function_exists('pven_cliente')) {
    /**
     * Cliente de la empresa (entidad activa con rol CLI) y su condición comercial vigente: lista_precio_id,
     * cliente_descuento_general, condicion_pago_id. @throws InvalidArgumentException
     */
    function pven_cliente(mysqli $conexion, int $empresa_id, int $entidad_id): array
    {
        $e = pven_filas($conexion, "SELECT entidad_id FROM gestion__entidades WHERE entidad_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1",
            'ii', [$entidad_id, $empresa_id]);
        if (!$e || !ent_tiene_rol($conexion, $entidad_id, 'CLI')) {
            throw new InvalidArgumentException('El cliente no existe o no está activo.');
        }
        $c = pven_condicion_cliente($conexion, $entidad_id) ?: [];
        return [
            'entidad_id' => $entidad_id,
            'lista_precio_id' => intval($c['lista_precio_id'] ?? 0),
            'cliente_descuento_general' => (float) ($c['cliente_descuento_general'] ?? 0),
            'condicion_pago_id' => intval($c['condicion_pago_id'] ?? 0),
        ];
    }
}

if (!function_exists('pven_cliente_o_cf')) {
    /**
     * pven_cliente(), o consumidor final con $entidad_id 0 (mostrador, doc 29): la lista Mostrador (cvta_lista_cliente
     * sin cliente, como la factura del ERP), sin descuento de cliente ni condición de pago. @throws InvalidArgumentException
     */
    function pven_cliente_o_cf(mysqli $conexion, int $empresa_id, int $entidad_id): array
    {
        if ($entidad_id > 0) {
            return pven_cliente($conexion, $empresa_id, $entidad_id);
        }
        return ['entidad_id' => 0, 'lista_precio_id' => (int) (cvta_lista_cliente($conexion, $empresa_id, null)['lista_precio_id'] ?? 0),
                'cliente_descuento_general' => 0.0, 'condicion_pago_id' => 0];
    }
}

if (!function_exists('pven_productos')) {
    /**
     * Datos de venta de los productos activos de la empresa: código, nombre, IVA y precio unitario vigente en la lista
     * (la misma subconsulta que el buscador de pedidos; null sin precio) y, con marcas comerciales, la marca.
     */
    function pven_productos(mysqli $conexion, int $empresa_id, int $lista_precio_id, array $producto_ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $producto_ids))));
        if (!$ids) {
            return [];
        }
        $marca = pmar_empresa_usa($conexion, $empresa_id);
        $sql = "SELECT p.producto_id, p.producto_codigo, p.producto_nombre, p.iva_alicuota_id, iva.porcentaje AS iva_porcentaje,
                       " . ($marca ? 'p.marca_comercial_id, mc.nombre AS marca_comercial_nombre' : 'NULL AS marca_comercial_id, NULL AS marca_comercial_nombre') . ",
                       (SELECT lp.precio_final FROM gestion__listas_precios_productos lp
                         WHERE lp.producto_id = p.producto_id AND lp.lista_precio_id = ? AND lp.empresa_id = ? AND lp.tabla_estado_registro_id = 1
                           AND lp.f_desde <= CURDATE() AND (lp.f_hasta IS NULL OR lp.f_hasta >= CURDATE())
                         ORDER BY lp.f_desde DESC LIMIT 1) AS precio_final
                  FROM gestion__productos p
                  LEFT JOIN gestion__impuestos__iva_alicuotas iva ON p.iva_alicuota_id = iva.iva_alicuota_id
                  " . ($marca ? 'LEFT JOIN gestion__productos_marcas mc ON mc.marca_comercial_id = p.marca_comercial_id AND mc.empresa_id = p.empresa_id' : '') . "
                 WHERE p.empresa_id = ? AND p.tabla_estado_registro_id = 1
                   AND p.producto_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")";
        $out = [];
        foreach (pven_filas($conexion, $sql, 'iii' . str_repeat('i', count($ids)), array_merge([$lista_precio_id, $empresa_id, $empresa_id], $ids)) as $r) {
            $out[(int) $r['producto_id']] = $r;
        }
        return $out;
    }
}

if (!function_exists('pven_ofertas_productos')) {
    /**
     * [producto_id => [oferta, ...]]: con venta por presentación, lpre_ofertas() (unidad suelta si la lista la vende y
     * presentaciones con precio); si no, la unidad con el precio de la lista. Sin precio, el producto no se ofrece.
     */
    function pven_ofertas_productos(mysqli $conexion, int $empresa_id, int $lista_precio_id, array $productos, int $sucursal_id): array
    {
        if (vpre_activo($conexion, $empresa_id, 'pedido')) {
            return lpre_ofertas($conexion, $empresa_id, $lista_precio_id, array_keys($productos), $sucursal_id);
        }
        $out = [];
        foreach ($productos as $pid => $p) {
            if ($p['precio_final'] !== null && (float) $p['precio_final'] > 0) {
                $out[$pid][] = ['presentacion_id' => null, 'nombre' => 'Unidad', 'factor' => 1.0, 'precio' => (float) $p['precio_final'],
                                'minimo' => null, 'multiplo' => null, 'disponible' => null];
            }
        }
        return $out;
    }
}

if (!function_exists('pven_ofertas')) {
    /**
     * Qué se le puede vender a un cliente: ['lista_precio_id', 'canal', 'productos' => [producto_id => [producto_id,
     * codigo, nombre, iva_porcentaje, marca, ofertas => [presentacion_id, nombre, factor, precio, minimo, multiplo,
     * disponible, descuento_pct, etiqueta, precio_final]]]]. descuento_pct / etiqueta: lo que corresponde al pedir el
     * mínimo (o 1) de esa oferta sola; con escalas, el descuento real depende del total del pedido (pven_valorizar).
     * Nunca devuelve costos ni reglas internas. @throws InvalidArgumentException
     */
    function pven_ofertas(mysqli $conexion, int $empresa_id, int $entidad_id, array $producto_ids, int $sucursal_id = 0, ?string $fecha = null): array
    {
        $cli = pven_cliente_o_cf($conexion, $empresa_id, $entidad_id);
        $lista = $cli['lista_precio_id'];
        $canal = $lista > 0 ? cvta_canal_lista($conexion, $empresa_id, $lista) : null;
        $res = ['lista_precio_id' => $lista, 'canal' => $canal['nombre'] ?? null, 'productos' => []];
        if ($lista <= 0) {
            return $res;
        }
        $prods = pven_productos($conexion, $empresa_id, $lista, $producto_ids);
        $ofertas = pven_ofertas_productos($conexion, $empresa_id, $lista, $prods, $sucursal_id);
        $usa_prv = prv_activo($conexion, $empresa_id, 'pedido');
        $lineas = [];
        foreach ($ofertas as $pid => $ofs) {
            foreach ($ofs as $i => $of) {
                $minimo = max(1.0, (float) ($of['minimo'] ?? 1));
                $lineas[$pid . ':' . $i] = ['producto_id' => $pid, 'presentacion_id' => intval($of['presentacion_id'] ?? 0),
                                            'cantidad' => $minimo * (float) $of['factor']];
            }
        }
        $resuelto = [];
        if ($usa_prv && $lineas) {
            // de a una (la escala depende de la cantidad del producto en el pedido: acá, la oferta sola)
            foreach ($lineas as $k => $l) {
                $resuelto[$k] = precio_resolver($conexion, $empresa_id, ['entidad_id' => $entidad_id, 'lista_precio_id' => 0, // 0: la resuelve precio_resolver, como la pantalla
                    'sucursal_id' => $sucursal_id, 'fecha' => $fecha], [$k => $l])[$k];
            }
        }
        foreach ($ofertas as $pid => $ofs) {
            if (!isset($prods[$pid])) {
                continue;
            }
            $p = $prods[$pid];
            $items = [];
            foreach ($ofs as $i => $of) {
                $r = $resuelto[$pid . ':' . $i] ?? null;
                $pct = $r !== null ? (float) $r['total_pct'] : $cli['cliente_descuento_general'];
                $etiqueta = ($r !== null && in_array($r['regla_tipo'], ['ESCALA', 'PROMOCION'], true) && $r['regla_nombre'])
                    ? $r['regla_nombre'] . ' ' . lpre_num((float) $r['auto_pct']) . ' %' : null;
                $items[] = $of + ['descuento_pct' => $pct, 'etiqueta' => $etiqueta,
                                  'precio_final' => pven_r((float) $of['precio'] * (1 - $pct / 100), 2)];
            }
            $res['productos'][$pid] = [
                'producto_id' => $pid, 'codigo' => $p['producto_codigo'], 'nombre' => $p['producto_nombre'],
                'iva_porcentaje' => (float) ($p['iva_porcentaje'] ?? 0), 'marca' => $p['marca_comercial_nombre'],
                'ofertas' => $items,
            ];
        }
        return $res;
    }
}

if (!function_exists('pven_valorizar')) {
    /**
     * Arma las líneas y los totales del pedido a partir de [producto_id, presentacion_id (0 / null = unidad), cantidad
     * (en presentaciones o en unidades)], con los mismos cálculos que la pantalla de pedidos. Ignora cualquier precio o
     * descuento que venga en los ítems. $opc: sucursal_id, fecha, usuario_id, descuento_manual_pct (solo la app del
     * vendedor; con reglas de precio se suma encima del automático y lo controla el tope al confirmar).
     * Devuelve ['detalles' => [...], 'cabecera' => [subtotal, descuentos, impuestos, total, descuento_general_pct],
     * 'cliente' => pven_cliente(), 'lineas' => [por línea: descuento_pct, etiqueta, requiere_aprobacion]].
     * @throws InvalidArgumentException
     */
    function pven_valorizar(mysqli $conexion, int $empresa_id, int $entidad_id, array $items, array $opc = []): array
    {
        $cli = pven_cliente_o_cf($conexion, $empresa_id, $entidad_id);
        $lista = $cli['lista_precio_id'];
        if ($lista <= 0) {
            throw new InvalidArgumentException('No hay una lista de precios vigente para el cliente.');
        }
        $sucursal_id = intval($opc['sucursal_id'] ?? 0);
        $manual = max(0.0, min(100.0, (float) ($opc['descuento_manual_pct'] ?? 0)));
        // Ítems: unificar producto + presentación (una línea por presentación; cajas y unidades van separadas)
        $pedidos = [];
        foreach ($items as $it) {
            $pid = intval($it['producto_id'] ?? 0);
            $pres = intval($it['presentacion_id'] ?? 0);
            $cant = round((float) ($it['cantidad'] ?? 0), 4);
            if ($pid <= 0 || $cant <= 0) {
                throw new InvalidArgumentException('Cada producto tiene que tener una cantidad mayor que cero.');
            }
            $k = $pid . ':' . $pres;
            $pedidos[$k] = ['producto_id' => $pid, 'presentacion_id' => $pres, 'cantidad' => round(($pedidos[$k]['cantidad'] ?? 0) + $cant, 4)];
        }
        if (!$pedidos) {
            throw new InvalidArgumentException('El pedido no tiene productos.');
        }
        $prods = pven_productos($conexion, $empresa_id, $lista, array_column($pedidos, 'producto_id'));
        $ofertas = pven_ofertas_productos($conexion, $empresa_id, $lista, $prods, $sucursal_id);
        $lineas = [];
        foreach ($pedidos as $k => $pd) {
            $p = $prods[$pd['producto_id']] ?? null;
            $of = null;
            foreach ($ofertas[$pd['producto_id']] ?? [] as $o) {
                if (intval($o['presentacion_id'] ?? 0) === $pd['presentacion_id']) {
                    $of = $o;
                }
            }
            if (!$p || !$of) {
                $nombre = $p ? '"' . $p['producto_nombre'] . '"' : 'Un producto';
                throw new InvalidArgumentException($nombre . ($pd['presentacion_id'] ? ' no se vende en esa presentación' : ' no se vende por unidad')
                    . ' en la lista del cliente.');
            }
            $lineas[$k] = ['p' => $p, 'of' => $of, 'cantidad_base' => round($pd['cantidad'] * (float) $of['factor'], 4)] + $pd;
        }
        // % de cada línea: con reglas de precio, precio_resolver (mayor entre cliente, escala y promoción, tope de la
        // lista, más el manual); sin reglas, el descuento del cliente (como la pantalla)
        $usa_prv = prv_activo($conexion, $empresa_id, 'pedido');
        $res = [];
        if ($usa_prv) {
            $res = precio_resolver($conexion, $empresa_id, ['entidad_id' => $entidad_id, 'lista_precio_id' => 0, 'sucursal_id' => $sucursal_id, // lista: como la pantalla
                'fecha' => $opc['fecha'] ?? null, 'usuario_id' => intval($opc['usuario_id'] ?? 0)],
                array_map(fn($l) => ['producto_id' => $l['producto_id'], 'presentacion_id' => $l['presentacion_id'],
                                     'cantidad' => $l['cantidad_base'], 'descuento_manual_pct' => $manual], $lineas));
        }
        $detalles = [];
        $info = [];
        $sn = $si = $sd = 0.0;
        foreach ($lineas as $k => $l) {
            $pct = $usa_prv ? (float) $res[$k]['total_pct'] : $cli['cliente_descuento_general'];
            $iva_pct = (float) ($l['p']['iva_porcentaje'] ?? 0);
            $d = ['venta_pedido_detalle_id' => 0, 'producto_id' => $l['producto_id'], 'cantidad_entregada' => 0, 'no_gravado' => 0, 'exento' => 0,
                  'iva_alicuota_id' => $l['p']['iva_alicuota_id'] ? intval($l['p']['iva_alicuota_id']) : null, 'iva_porcentaje' => $iva_pct,
                  'descuento_general_pct' => $pct];
            if ($l['presentacion_id'] > 0) {
                // ventas-presentaciones.js: nuevaLinea + recalcular
                $factor = (float) $l['of']['factor'];
                $pc = pven_r($l['cantidad'], 4);
                $pp = (float) $l['of']['precio'];
                $unit = $factor > 0 ? pven_r($pp / $factor, 6) : 0.0;
                $d += ['presentacion_id' => $l['presentacion_id'], 'presentacion_nombre' => $l['of']['nombre'], 'presentacion_factor' => $factor,
                       'presentacion_cantidad' => $pc, 'presentacion_precio' => $pp];
                $d['cantidad'] = pven_r($pc * $factor, 4);
                $d['precio_unitario'] = $unit;
                $d['descuento_general'] = pven_r($unit * $pct / 100, 6);
                $d['precio_unitario_neto'] = pven_r($unit - $d['descuento_general'], 6);
                $d['neto_gravado'] = pven_r($pc * $pp * (1 - $pct / 100), 2);
                $d['iva_importe'] = pven_r($d['neto_gravado'] * $iva_pct / 100, 2);
                $d['total_linea'] = pven_r($d['neto_gravado'] + $d['iva_importe'], 2);
            } else {
                // ventas_pedidos.js: línea por unidad (sin redondear, como la pantalla)
                $precio = (float) $l['of']['precio'];
                $desc = $precio * ($pct / 100);
                $neto = $precio - $desc;
                $ng = $l['cantidad'] * $neto;
                $iva = $ng * ($iva_pct / 100);
                $d += ['cantidad' => $l['cantidad'], 'precio_unitario' => $precio, 'descuento_general' => $desc, 'precio_unitario_neto' => $neto,
                       'neto_gravado' => $ng, 'iva_importe' => $iva, 'total_linea' => $ng + $iva];
            }
            $sn += $d['neto_gravado'];
            $si += $d['iva_importe'];
            $sd += $d['descuento_general'] * $d['cantidad'];
            $detalles[] = $d;
            $info[] = ['producto_id' => $l['producto_id'], 'presentacion_id' => $l['presentacion_id'] ?: null, 'descuento_pct' => $pct,
                       'nombre' => $l['p']['producto_nombre'] . ($l['presentacion_id'] > 0 ? ' — ' . $l['of']['nombre'] : ''),
                       'etiqueta' => ($usa_prv && in_array($res[$k]['regla_tipo'], ['ESCALA', 'PROMOCION'], true)) ? $res[$k]['regla_nombre'] : null,
                       'requiere_aprobacion' => $usa_prv && !empty($res[$k]['requiere_aprobacion'])];
        }
        return [
            'detalles' => $detalles,
            'cabecera' => [
                'subtotal' => pven_r($sn, 2), 'descuentos' => pven_r($sd, 2), 'impuestos' => pven_r($si, 2), 'total' => pven_r($sn + $si, 2),
                // con reglas de precio el campo de la pantalla es el manual del vendedor; sin ellas, el del cliente
                'descuento_general_pct' => $usa_prv ? $manual : $cli['cliente_descuento_general'],
            ],
            'cliente' => $cli,
            'lineas' => $info,
        ];
    }
}

if (!function_exists('pven_datos_pedido')) {
    /**
     * Datos del alta / edición del pedido ($data de agregarPedidoVenta / editarPedidoVenta) a partir de los ítems:
     * página y punto de venta de la sucursal, líneas y totales de pven_valorizar, condición de pago del cliente y
     * moneda base. $p: los de pven_crear_externo. Devuelve [$data, $valorizado]. @throws InvalidArgumentException
     */
    function pven_datos_pedido(mysqli $conexion, int $empresa_id, array $p): array
    {
        $origen = strtoupper((string) ($p['origen'] ?? ''));
        if (!in_array($origen, ['CARRITO', 'APP'], true)) {
            throw new InvalidArgumentException('Origen del pedido no válido.');
        }
        $pagina = pven_pagina_pedidos($conexion, $empresa_id);
        if ($pagina <= 0) {
            throw new InvalidArgumentException('La empresa no tiene la página de pedidos configurada.');
        }
        $sucursal_id = intval($p['sucursal_id'] ?? 0);
        [$pv, $tipo] = pven_pv_tipo($conexion, $empresa_id, $pagina, $sucursal_id);
        if ($pv <= 0) {
            throw new InvalidArgumentException('La sucursal no tiene un punto de venta habilitado para pedidos.');
        }
        $fecha = (!empty($p['f_emision']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $p['f_emision'])) ? (string) $p['f_emision'] : date('Y-m-d');
        $entidad_id = intval($p['entidad_id'] ?? 0);
        $val = pven_valorizar($conexion, $empresa_id, $entidad_id, (array) ($p['items'] ?? []), [
            'sucursal_id' => $sucursal_id, 'fecha' => $fecha, 'usuario_id' => intval($p['usuario_id'] ?? 0),
            'descuento_manual_pct' => $origen === 'APP' ? (float) ($p['descuento_manual_pct'] ?? 0) : 0,
        ]);
        $data = $val['cabecera'] + [
            'comprobante_tipo_id' => $tipo, 'sucursal_id' => $sucursal_id, 'punto_venta_id' => $pv, 'comprobante_nro' => '',
            'entidad_id' => $entidad_id, 'entidad_sucursal_id' => intval($p['entidad_sucursal_id'] ?? 0), 'f_emision' => $fecha,
            'f_entrega_estimada' => null, 'condicion_pago_id' => $val['cliente']['condicion_pago_id'],
            'moneda_id' => emp_moneda_base_id($conexion, $empresa_id), 'tipo_cambio' => 1.0,
            'direccion_entrega' => trim((string) ($p['direccion_entrega'] ?? '')),
            'observaciones' => mb_substr(trim((string) ($p['observaciones'] ?? '')), 0, 500),
            'vendedor_entidad_id' => 0, 'origen' => $origen, 'detalles' => $val['detalles'],
            'empresa_idx' => $empresa_id, 'pagina_idx' => $pagina,
        ];
        return [$data, $val];
    }
}

if (!function_exists('pven_crear_externo')) {
    /**
     * Crea (y, con 'confirmar', confirma) un pedido desde el carrito o la app. $p:
     *   entidad_id, sucursal_id, items (pven_valorizar), origen ('CARRITO' | 'APP'), usuario_id, accesos (bool, por
     *   defecto false: el canal valida lo suyo), confirmar (bool), entidad_sucursal_id, direccion_entrega,
     *   observaciones, descuento_manual_pct (solo APP), f_emision (Y-m-d; hoy).
     * Devuelve ['success', 'venta_pedido_id', 'comprobante_nro', 'confirmado', 'message', 'cabecera'].
     * El alta y la confirmación son dos transacciones (como en el ERP): si la confirmación no pasa (p. ej. un
     * descuento manual por encima del tope), el pedido queda en Borrador y se informa.
     */
    function pven_crear_externo(mysqli $conexion, int $empresa_id, array $p): array
    {
        try {
            [$data, $val] = pven_datos_pedido($conexion, $empresa_id, $p);
            $pagina = (int) $data['pagina_idx'];
            $usuario_id = intval($p['usuario_id'] ?? 0);
            $ctx = ['usuario_id' => $usuario_id, 'accesos' => !empty($p['accesos'])];
            $r = pven_agregar($conexion, $data, $ctx);
            if (empty($r['resultado'])) {
                return ['success' => false, 'message' => $r['error'] ?? 'No se pudo grabar el pedido.'];
            }
            $id = (int) $r['venta_pedido_id'];
            $out = ['success' => true, 'venta_pedido_id' => $id, 'comprobante_nro' => 0, 'confirmado' => false,
                    'message' => 'Pedido guardado en Borrador.', 'cabecera' => $val['cabecera'], 'lineas' => $val['lineas']];
            if (!empty($p['confirmar'])) {
                $t = pven_ejecutar_transicion($conexion, $id, 'confirmar_pedido', $empresa_id, $pagina, $ctx);
                if (!empty($t['success'])) {
                    $out['confirmado'] = true;
                    $out['comprobante_nro'] = (int) ($t['comprobante_nro'] ?? 0);
                    $out['message'] = 'Pedido confirmado.';
                } else {
                    $out['message'] = 'El pedido quedó en Borrador: ' . ($t['error'] ?? 'no se pudo confirmar.');
                }
            }
            return $out;
        } catch (InvalidArgumentException $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
