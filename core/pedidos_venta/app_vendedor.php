<?php
/**
 * Aplicación del vendedor (doc 28, pasos 9-7 / 9-8): lo que necesita app_vendedor/ del núcleo. El vendedor es un
 * usuario del ERP ligado a un vendedor (gestion__vendedores_usuarios) y solo ve su cartera (vcar_*). Los precios, las
 * presentaciones y el pedido salen de core/pedidos_venta/externo.php (pven_*), igual que el ERP y el carrito.
 * Habilitación: usa_app_vendedor (pven_empresa_usa 'app') + usa_vendedores + usuario vendedor activo.
 * Sin costos ni márgenes en ninguna respuesta.
 */

require_once __DIR__ . '/externo.php';

if (!function_exists('pven_app_vendedor')) {
    /** Vendedor del usuario en la empresa, si la app está habilitada. @throws InvalidArgumentException */
    function pven_app_vendedor(mysqli $conexion, int $empresa_id, int $usuario_id): int
    {
        if (!pven_empresa_usa($conexion, $empresa_id, 'app')) {
            throw new InvalidArgumentException('La aplicación del vendedor no está habilitada para la empresa.');
        }
        if (!vcar_empresa_usa($conexion, $empresa_id)) {
            throw new InvalidArgumentException('La empresa no usa vendedores y carteras.');
        }
        $v = vcar_vendedor_de_usuario($conexion, $empresa_id, $usuario_id);
        if ($v === null) {
            throw new InvalidArgumentException('Tu usuario no está ligado a un vendedor activo.');
        }
        return $v;
    }
}

if (!function_exists('pven_app_empresas')) {
    /** Empresas del usuario (con perfil) donde la app está habilitada y él es vendedor: [empresa_id, empresa]. */
    function pven_app_empresas(mysqli $conexion, int $usuario_id): array
    {
        $out = [];
        foreach (pven_filas($conexion,
            "SELECT DISTINCT e.empresa_id, e.empresa FROM conf__empresas e
               JOIN conf__empresas_perfiles ep ON ep.empresa_id = e.empresa_id
               JOIN conf__usuarios_perfiles up ON up.empresa_perfil_id = ep.empresa_perfil_id AND up.usuario_id = ?
              WHERE e.tabla_estado_registro_id = 1 ORDER BY e.empresa", 'i', [$usuario_id]) as $e) {
            try {
                pven_app_vendedor($conexion, (int) $e['empresa_id'], $usuario_id);
                $out[] = ['empresa_id' => (int) $e['empresa_id'], 'empresa' => $e['empresa']];
            } catch (InvalidArgumentException $ex) {
                continue;
            }
        }
        return $out;
    }
}

if (!function_exists('pven_app_clientes')) {
    /** Clientes vigentes de la cartera (búsqueda por nombre, fantasía o CUIT), con su borrador APP si tiene. */
    function pven_app_clientes(mysqli $conexion, int $empresa_id, int $vendedor_id, string $q = ''): array
    {
        $q = mb_strtolower(trim($q));
        $borradores = [];
        foreach (pven_filas($conexion,
            "SELECT entidad_id, MAX(venta_pedido_id) id FROM gestion__ventas_pedidos
              WHERE empresa_id = ? AND vendedor_entidad_id = ? AND origen = 'APP' AND tabla_estado_registro_id = 3 GROUP BY entidad_id",
            'ii', [$empresa_id, $vendedor_id]) as $b) {
            $borradores[(int) $b['entidad_id']] = (int) $b['id'];
        }
        $out = [];
        foreach (vcar_cartera($conexion, $empresa_id, $vendedor_id) as $c) {
            $texto = mb_strtolower($c['entidad_nombre'] . ' ' . $c['entidad_fantasia'] . ' ' . $c['cuit']);
            if ($q !== '' && mb_strpos($texto, $q) === false) {
                continue;
            }
            $id = (int) $c['cliente_entidad_id'];
            $out[] = ['entidad_id' => $id, 'nombre' => $c['entidad_fantasia'] ?: $c['entidad_nombre'], 'razon_social' => $c['entidad_nombre'],
                      'cuit' => $c['cuit'], 'borrador_id' => $borradores[$id] ?? null];
        }
        return $out;
    }
}

if (!function_exists('pven_buscar_productos')) {
    /**
     * Productos activos por código, nombre, marca comercial o código de barras (del producto o de una presentación).
     * Un código de barras exacto de una presentación la devuelve en 'presentacion_id' (el escáner la agrega directo).
     * [['producto_id' => int, 'presentacion_id' => ?int]], hasta $limite.
     */
    function pven_buscar_productos(mysqli $conexion, int $empresa_id, string $q, int $limite = 30): array
    {
        $q = trim($q);
        if ($q === '') {
            return [];
        }
        // Código de barras exacto de una presentación activa
        if (preg_match('/^[0-9A-Za-z\-]{6,50}$/', $q) && prpr_disponible($conexion)) {
            $r = pven_filas($conexion,
                "SELECT pr.producto_id, pr.presentacion_id FROM gestion__productos_presentaciones pr
                   JOIN gestion__productos p ON p.producto_id = pr.producto_id AND p.tabla_estado_registro_id = 1
                  WHERE pr.empresa_id = ? AND pr.tabla_estado_registro_id = 1 AND pr.codigo_barras = ? LIMIT 1", 'is', [$empresa_id, $q]);
            if ($r) {
                return [['producto_id' => (int) $r[0]['producto_id'], 'presentacion_id' => (int) $r[0]['presentacion_id']]];
            }
        }
        $like = '%' . $q . '%';
        $cond = ['p.producto_codigo LIKE ?', 'p.producto_nombre LIKE ?', 'p.codigo_barras = ?'];
        $tipos = 'sss';
        $params = [$like, $like, $q];
        $barras = prpr_sql_busqueda_barras($conexion, 'p');
        if ($barras !== '') {
            $cond[] = $barras;
            $tipos .= 's';
            $params[] = $q;
        }
        if (pmar_empresa_usa($conexion, $empresa_id)) {
            $cond[] = 'EXISTS (SELECT 1 FROM gestion__productos_marcas m WHERE m.marca_comercial_id = p.marca_comercial_id AND m.empresa_id = p.empresa_id AND m.nombre LIKE ?)';
            $tipos .= 's';
            $params[] = $like;
        }
        $rows = pven_filas($conexion,
            "SELECT p.producto_id FROM gestion__productos p
              WHERE p.empresa_id = ? AND p.tabla_estado_registro_id = 1 AND (" . implode(' OR ', $cond) . ")
              ORDER BY (p.producto_codigo = ?) DESC, p.producto_nombre LIMIT " . max(1, min(100, $limite)),
            'i' . $tipos . 's', array_merge([$empresa_id], $params, [$q]));
        return array_map(fn($r) => ['producto_id' => (int) $r['producto_id'], 'presentacion_id' => null], $rows);
    }
}

if (!function_exists('pven_app_borrador')) {
    /** Borrador APP del vendedor para el cliente (el último): id, ítems (producto, presentación, cantidad), observaciones. */
    function pven_app_borrador(mysqli $conexion, int $empresa_id, int $vendedor_id, int $cliente_id): ?array
    {
        $p = pven_filas($conexion,
            "SELECT venta_pedido_id, observaciones, descuento_general_pct FROM gestion__ventas_pedidos
              WHERE empresa_id = ? AND vendedor_entidad_id = ? AND entidad_id = ? AND origen = 'APP' AND tabla_estado_registro_id = 3
              ORDER BY venta_pedido_id DESC LIMIT 1", 'iii', [$empresa_id, $vendedor_id, $cliente_id]);
        if (!$p) {
            return null;
        }
        $id = (int) $p[0]['venta_pedido_id'];
        $con_pres = vpre_columnas($conexion, 'pedido');
        $items = [];
        foreach (pven_filas($conexion,
            "SELECT producto_id, cantidad" . ($con_pres ? ', presentacion_id, presentacion_cantidad' : ', NULL AS presentacion_id, NULL AS presentacion_cantidad') . "
               FROM gestion__ventas_pedidos_detalles WHERE venta_pedido_id = ? AND tabla_estado_registro_id = 1 ORDER BY venta_pedido_detalle_id",
            'i', [$id]) as $d) {
            $pres = (int) ($d['presentacion_id'] ?? 0);
            $items[] = ['producto_id' => (int) $d['producto_id'], 'presentacion_id' => $pres ?: null,
                        'cantidad' => $pres ? (float) $d['presentacion_cantidad'] : (float) $d['cantidad']];
        }
        return ['venta_pedido_id' => $id, 'observaciones' => (string) $p[0]['observaciones'],
                'descuento_manual_pct' => (float) $p[0]['descuento_general_pct'], 'items' => $items];
    }
}

if (!function_exists('pven_app_historial')) {
    /** Últimos pedidos del cliente (cualquier origen, sin eliminados): número, fecha, total, estado. */
    function pven_app_historial(mysqli $conexion, int $empresa_id, int $cliente_id, int $limite = 15): array
    {
        // El nombre del estado según la versión de conf__estados_registros (estado_registro / nombre_estado / descripcion)
        $cols = array_column(pven_filas($conexion, "SHOW COLUMNS FROM conf__estados_registros"), 'Field');
        $col = in_array('estado_registro', $cols, true) ? 'er.estado_registro' : (in_array('nombre_estado', $cols, true) ? 'er.nombre_estado'
            : (in_array('descripcion', $cols, true) ? 'er.descripcion' : 'NULL'));
        return pven_filas($conexion,
            "SELECT vp.venta_pedido_id, vp.comprobante_nro, vp.f_emision, vp.total, $col AS estado
               FROM gestion__ventas_pedidos vp
               LEFT JOIN conf__estados_registros er ON er.estado_registro_id = vp.tabla_estado_registro_id
              WHERE vp.empresa_id = ? AND vp.entidad_id = ? AND vp.tabla_estado_registro_id <> 6
              ORDER BY vp.f_emision DESC, vp.venta_pedido_id DESC LIMIT " . max(1, min(50, $limite)), 'ii', [$empresa_id, $cliente_id]);
    }
}

if (!function_exists('pven_app_entrega_estimada')) {
    /**
     * Con distribución: el próximo día de reparto de la zona del cliente, después de $fecha (las zonas no tienen hora
     * de corte). null sin distribución, sin zona o sin días.
     */
    function pven_app_entrega_estimada(mysqli $conexion, int $empresa_id, int $cliente_id, ?string $fecha = null): ?array
    {
        if (!vdis_empresa_usa($conexion, $empresa_id)) {
            return null;
        }
        $zona = vdis_zona_de_cliente($conexion, $empresa_id, $cliente_id, null, $fecha);
        if (!$zona) {
            return null;
        }
        $dias = pven_filas($conexion, "SELECT dias_reparto FROM gestion__distribucion_zonas WHERE zona_id = ? AND empresa_id = ?", 'ii', [$zona['zona_id'], $empresa_id]);
        $lista = array_filter(array_map('intval', explode(',', (string) ($dias[0]['dias_reparto'] ?? ''))));
        if (!$lista) {
            return null;
        }
        $d = new DateTime($fecha ?: 'today');
        for ($i = 1; $i <= 7; $i++) {
            $d->modify('+1 day');
            if (in_array((int) $d->format('N'), $lista, true)) {
                return ['fecha' => $d->format('Y-m-d'), 'dia' => vdis_dias_texto((string) $d->format('N')), 'zona' => $zona['nombre']];
            }
        }
        return null;
    }
}
