<?php
/**
 * Presentaciones en las líneas de pedidos, remitos y facturas de venta (paso 2 de la venta por presentación).
 * Diseño: _local/docs/gestion/20_presentaciones_en_ventas.md (aprobado el 2026-11-14).
 *
 * La línea sigue en unidad base (cantidad y precio unitario de siempre: stock, entregas, facturación, asientos y
 * ARCA no cambian). Si se vendió en una presentación, se guarda además su "foto" en cinco columnas NULL por
 * defecto (script 20261115_venta_presentaciones_02_lineas.sql): presentacion_id, presentacion_nombre,
 * presentacion_factor, presentacion_cantidad, presentacion_precio. cantidad = presentacion_cantidad × factor.
 *
 * - Habilitación: vpre_activo() = lpre_empresa_usa() (indicador + tabla de precios) + las columnas en la tabla de
 *   líneas. Si no, los modelos siguen como siempre y no llaman a nada más de acá.
 * - Pedido / remito o factura sin comprobante anterior: vpre_normalizar_lineas() valida y convierte ANTES de
 *   grabar; después de grabar cada línea, vpre_guardar_foto().
 * - Remito desde pedido y factura desde remito: vpre_heredar() copia la presentación de la línea de origen
 *   (solo presentaciones enteras: el depósito no abre cajas).
 * - Gestión de pedidos (cambio de cantidad): vpre_ajustar_cantidad().
 * Las columnas nuevas se escriben siempre con un UPDATE aparte (no se agregan a los INSERT / UPDATE existentes).
 */

require_once __DIR__ . '/productos_presentaciones.php'; // y listas_precios_presentaciones.php

const VPRE_TABLAS = [
    'pedido' => ['gestion__ventas_pedidos_detalles', 'venta_pedido_detalle_id'],
    'remito' => ['gestion__ventas_remitos_detalles', 'venta_remito_detalle_id'],
    'factura' => ['gestion__ventas_facturas_detalles', 'venta_factura_detalle_id'],
];
const VPRE_COLUMNAS = ['presentacion_id', 'presentacion_nombre', 'presentacion_factor', 'presentacion_cantidad', 'presentacion_precio'];

if (!function_exists('vpre_columnas')) {
    /** ¿Tiene la tabla de líneas del tipo ('pedido' | 'remito' | 'factura') las cinco columnas? (cacheado) */
    function vpre_columnas(mysqli $conexion, string $tipo): bool
    {
        static $cache = [];
        if (!isset(VPRE_TABLAS[$tipo])) {
            return false;
        }
        if (!isset($cache[$tipo])) {
            $tabla = VPRE_TABLAS[$tipo][0];
            $res = mysqli_query($conexion, "SHOW COLUMNS FROM `$tabla` WHERE Field LIKE 'presentacion\\_%'");
            $hay = [];
            while ($res && ($r = mysqli_fetch_row($res))) {
                $hay[] = $r[0];
            }
            $cache[$tipo] = !array_diff(VPRE_COLUMNAS, $hay);
        }
        return $cache[$tipo];
    }
}

if (!function_exists('vpre_activo')) {
    /** ¿La empresa vende por presentación y la tabla de líneas del tipo tiene las columnas? */
    function vpre_activo(mysqli $conexion, int $empresa_id, string $tipo): bool
    {
        return lpre_empresa_usa($conexion, $empresa_id) && vpre_columnas($conexion, $tipo);
    }
}

if (!function_exists('vpre_agregar_ofertas')) {
    /**
     * Para los buscadores de productos: agrega 'ofertas' (lpre_ofertas) a cada fila con producto_id. Sin venta por
     * presentación, devuelve las filas tal cual. $sucursal_id > 0 suma la disponibilidad (solo informativa).
     */
    function vpre_agregar_ofertas(mysqli $conexion, int $empresa_id, int $lista_precio_id, array $productos, int $sucursal_id = 0, string $tipo = 'pedido'): array
    {
        if (!$productos || $lista_precio_id <= 0 || !vpre_activo($conexion, $empresa_id, $tipo)) {
            return $productos;
        }
        $ofertas = lpre_ofertas($conexion, $empresa_id, $lista_precio_id, array_column($productos, 'producto_id'), $sucursal_id);
        foreach ($productos as &$p) {
            $p['ofertas'] = $ofertas[(int) $p['producto_id']] ?? [];
        }
        unset($p);
        return $productos;
    }
}

if (!function_exists('vpre_presentacion')) {
    /** Presentación activa de la empresa (con venta_minimo / venta_multiplo si existen las columnas), o null. */
    function vpre_presentacion(mysqli $conexion, int $empresa_id, int $presentacion_id): ?array
    {
        $extra = prpr_columnas_venta($conexion) ? ', venta_minimo, venta_multiplo' : ', NULL AS venta_minimo, NULL AS venta_multiplo';
        $st = mysqli_prepare($conexion, "SELECT presentacion_id, producto_id, presentacion_nombre, factor, aplica_venta $extra
                                         FROM gestion__productos_presentaciones
                                         WHERE presentacion_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1");
        mysqli_stmt_bind_param($st, "ii", $presentacion_id, $empresa_id);
        mysqli_stmt_execute($st);
        $r = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);
        return $r ?: null;
    }
}

if (!function_exists('vpre_restriccion_unidad')) {
    /** [venta_minimo, venta_multiplo] del producto (unidad suelta); [null, null] sin columnas o sin datos. */
    function vpre_restriccion_unidad(mysqli $conexion, int $empresa_id, int $producto_id): array
    {
        if (!lpre_columna_existe($conexion, 'gestion__productos', 'venta_minimo')
            || !lpre_columna_existe($conexion, 'gestion__productos', 'venta_multiplo')) {
            return [null, null];
        }
        $st = mysqli_prepare($conexion, "SELECT venta_minimo, venta_multiplo, producto_nombre FROM gestion__productos WHERE producto_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($st, "ii", $producto_id, $empresa_id);
        mysqli_stmt_execute($st);
        $r = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);
        return [
            isset($r['venta_minimo']) ? (float) $r['venta_minimo'] : null,
            isset($r['venta_multiplo']) ? (float) $r['venta_multiplo'] : null,
            $r['producto_nombre'] ?? '',
        ];
    }
}

if (!function_exists('vpre_normalizar_lineas')) {
    /**
     * Valida y convierte las líneas que vienen de la pantalla ANTES de grabarlas (pedido, o remito / factura sin
     * comprobante anterior). Una línea con presentacion_id > 0 trae presentacion_cantidad y presentacion_precio
     * (el de la presentación entera); se recalculan cantidad (× factor), precio unitario y, para el pedido, los
     * importes desde la presentación. Una línea por unidad se valida con el mínimo / múltiplo del producto y con
     * que la lista venda la unidad suelta ($lista_precio_id; 0 = no se controla).
     * Cada línea sale con '_vpre' = foto para vpre_guardar_foto() (null = por unidad).
     * Las líneas que heredan de un comprobante anterior (venta_pedido_detalle_id / venta_remito_detalle_id según
     * $tipo) no se tocan acá: van por vpre_heredar().
     * @throws InvalidArgumentException con el mensaje para el usuario
     */
    function vpre_normalizar_lineas(mysqli $conexion, int $empresa_id, int $lista_precio_id, array $detalles, string $tipo): array
    {
        $vende_unidad = $lista_precio_id > 0 ? lpre_lista_vende_unidad($conexion, $empresa_id, $lista_precio_id) : true;
        $campo_origen = ['pedido' => null, 'remito' => 'venta_pedido_detalle_id', 'factura' => 'venta_remito_detalle_id'][$tipo] ?? null;
        foreach ($detalles as $i => &$d) {
            $d['_vpre'] = null;
            if ($campo_origen !== null && !empty($d[$campo_origen])) {
                continue; // hereda
            }
            $producto_id = intval($d['producto_id'] ?? 0);
            $pres_id = intval($d['presentacion_id'] ?? 0);
            $linea = 'Línea ' . ($i + 1);
            if ($pres_id <= 0) {
                if (!$vende_unidad) {
                    throw new InvalidArgumentException("$linea: la lista del cliente no vende la unidad suelta; elegí una presentación.");
                }
                [$min, $mul, $nombre] = vpre_restriccion_unidad($conexion, $empresa_id, $producto_id) + [null, null, ''];
                $msg = lpre_validar_cantidad((float) ($d['cantidad'] ?? 0), $min, $mul, 'unidades');
                if ($msg !== null) {
                    throw new InvalidArgumentException(($nombre !== '' ? "\"$nombre\"" : $linea) . ": $msg");
                }
                continue;
            }
            $pres = vpre_presentacion($conexion, $empresa_id, $pres_id);
            if (!$pres || (int) $pres['producto_id'] !== $producto_id) {
                throw new InvalidArgumentException("$linea: la presentación no es de ese producto o está inactiva.");
            }
            if (!(int) $pres['aplica_venta']) {
                throw new InvalidArgumentException("$linea: la presentación \"{$pres['presentacion_nombre']}\" no se usa en ventas.");
            }
            $factor = (float) $pres['factor'];
            $pc = round((float) ($d['presentacion_cantidad'] ?? 0), 4);
            $msg = lpre_validar_cantidad($pc,
                $pres['venta_minimo'] !== null ? (float) $pres['venta_minimo'] : null,
                $pres['venta_multiplo'] !== null ? (float) $pres['venta_multiplo'] : null,
                $pres['presentacion_nombre']);
            if ($msg !== null) {
                throw new InvalidArgumentException("\"{$pres['presentacion_nombre']}\": $msg");
            }
            $pp = round((float) ($d['presentacion_precio'] ?? 0), 6);
            if ($pp < 0) {
                throw new InvalidArgumentException("$linea: el precio no puede ser negativo.");
            }
            // Unidad base (lo que leen entregas, stock, facturación y asientos)
            $d['cantidad'] = round($pc * $factor, 4);
            $unit = $factor > 0 ? round($pp / $factor, 6) : 0.0;
            $d['precio_unitario'] = $unit;
            $d['precio_unitario_bruto'] = $unit;
            if ($tipo === 'pedido') {
                // Importes desde la presentación (sin el redondeo del unitario de 6 decimales)
                $desc_pct = (float) ($d['descuento_general_pct'] ?? 0);
                $iva_pct = (float) ($d['iva_porcentaje'] ?? 0);
                $d['descuento_general'] = round($unit * $desc_pct / 100, 6);
                $d['precio_unitario_neto'] = round($unit - $d['descuento_general'], 6);
                $d['neto_gravado'] = round($pc * $pp * (1 - $desc_pct / 100), 2);
                $d['iva_importe'] = round($d['neto_gravado'] * $iva_pct / 100, 2);
                $d['total_linea'] = round($d['neto_gravado'] + $d['iva_importe'] + (float) ($d['no_gravado'] ?? 0) + (float) ($d['exento'] ?? 0), 2);
            }
            $d['_vpre'] = [
                'presentacion_id' => $pres_id,
                'presentacion_nombre' => mb_substr((string) $pres['presentacion_nombre'], 0, 60),
                'presentacion_factor' => $factor,
                'presentacion_cantidad' => $pc,
                'presentacion_precio' => $pp,
            ];
        }
        unset($d);
        return $detalles;
    }
}

if (!function_exists('vpre_guardar_foto')) {
    /** Escribe (o borra, con null) la foto de la presentación de una línea ya grabada. */
    function vpre_guardar_foto(mysqli $conexion, string $tipo, int $detalle_id, ?array $foto): void
    {
        if ($detalle_id <= 0 || !vpre_columnas($conexion, $tipo)) {
            return;
        }
        [$tabla, $pk] = VPRE_TABLAS[$tipo];
        $f = $foto ?: array_fill_keys(VPRE_COLUMNAS, null);
        $st = mysqli_prepare($conexion, "UPDATE `$tabla` SET presentacion_id = ?, presentacion_nombre = ?, presentacion_factor = ?,
                                                presentacion_cantidad = ?, presentacion_precio = ? WHERE `$pk` = ?");
        $id = $f['presentacion_id'] !== null ? (int) $f['presentacion_id'] : null;
        mysqli_stmt_bind_param($st, "isdddi", $id, $f['presentacion_nombre'], $f['presentacion_factor'],
            $f['presentacion_cantidad'], $f['presentacion_precio'], $detalle_id);
        if (!mysqli_stmt_execute($st)) {
            $err = mysqli_stmt_error($st);
            mysqli_stmt_close($st);
            throw new RuntimeException('No se pudo guardar la presentación de la línea: ' . $err);
        }
        mysqli_stmt_close($st);
    }
}

if (!function_exists('vpre_foto')) {
    /** Foto de la presentación de una línea (null si es por unidad o no hay columnas). */
    function vpre_foto(mysqli $conexion, string $tipo, int $detalle_id): ?array
    {
        if ($detalle_id <= 0 || !vpre_columnas($conexion, $tipo)) {
            return null;
        }
        [$tabla, $pk] = VPRE_TABLAS[$tipo];
        $st = mysqli_prepare($conexion, "SELECT " . implode(', ', VPRE_COLUMNAS) . " FROM `$tabla` WHERE `$pk` = ?");
        mysqli_stmt_bind_param($st, "i", $detalle_id);
        mysqli_stmt_execute($st);
        $r = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);
        if (!$r || $r['presentacion_id'] === null) {
            return null;
        }
        return [
            'presentacion_id' => (int) $r['presentacion_id'],
            'presentacion_nombre' => $r['presentacion_nombre'],
            'presentacion_factor' => (float) $r['presentacion_factor'],
            'presentacion_cantidad' => (float) $r['presentacion_cantidad'],
            'presentacion_precio' => $r['presentacion_precio'] !== null ? (float) $r['presentacion_precio'] : null,
        ];
    }
}

if (!function_exists('vpre_heredar')) {
    /**
     * Remito desde pedido ('remito' ← 'pedido') o factura desde remito ('factura' ← 'remito'): copia la foto de la
     * línea de origen a la línea nueva con la cantidad en presentaciones de lo que se entrega / factura. Solo
     * presentaciones enteras (el depósito no abre cajas). Sin presentación en el origen, no hace nada.
     * @throws InvalidArgumentException si la cantidad no es un número entero de presentaciones
     */
    function vpre_heredar(mysqli $conexion, string $tipo_destino, int $detalle_id, string $tipo_origen, int $origen_detalle_id, float $cantidad_base): void
    {
        if (!vpre_columnas($conexion, $tipo_destino)) {
            return;
        }
        $foto = vpre_foto($conexion, $tipo_origen, $origen_detalle_id);
        if (!$foto || $foto['presentacion_factor'] <= 0) {
            return;
        }
        $pc = $cantidad_base / $foto['presentacion_factor'];
        if (abs($pc - round($pc)) > 1e-6) {
            throw new InvalidArgumentException(sprintf('La línea se vendió en "%s" (%s unidades): se entrega y se factura de a presentaciones enteras, no %s unidades.',
                $foto['presentacion_nombre'], lpre_num($foto['presentacion_factor']), lpre_num($cantidad_base)));
        }
        $foto['presentacion_cantidad'] = round($pc, 4);
        vpre_guardar_foto($conexion, $tipo_destino, $detalle_id, $foto);
    }
}

if (!function_exists('vpre_validar_heredada')) {
    /**
     * Antes de grabar una línea que hereda: devuelve el mensaje de error si la cantidad no es un número entero de
     * presentaciones de la línea de origen (null si está bien o si el origen es por unidad).
     */
    function vpre_validar_heredada(mysqli $conexion, string $tipo_origen, int $origen_detalle_id, float $cantidad_base): ?string
    {
        $foto = vpre_foto($conexion, $tipo_origen, $origen_detalle_id);
        if (!$foto || $foto['presentacion_factor'] <= 0) {
            return null;
        }
        $pc = $cantidad_base / $foto['presentacion_factor'];
        if (abs($pc - round($pc)) > 1e-6) {
            return sprintf('La línea se vendió en "%s" (%s unidades): se entrega y se factura de a presentaciones enteras, no %s unidades.',
                $foto['presentacion_nombre'], lpre_num($foto['presentacion_factor']), lpre_num($cantidad_base));
        }
        return null;
    }
}

if (!function_exists('vpre_ajustar_cantidad')) {
    /**
     * Gestión de pedidos cambia la cantidad (en unidad base) de una línea: si la línea es por presentación, tiene
     * que ser un número entero de presentaciones y se actualiza presentacion_cantidad. Devuelve el error o null.
     * Llamar antes de grabar con $grabar = false (valida) y después con $grabar = true.
     */
    function vpre_ajustar_cantidad(mysqli $conexion, int $detalle_id, float $cantidad_base, bool $grabar): ?string
    {
        $foto = vpre_foto($conexion, 'pedido', $detalle_id);
        if (!$foto || $foto['presentacion_factor'] <= 0) {
            return null;
        }
        $pc = $cantidad_base / $foto['presentacion_factor'];
        if (abs($pc - round($pc)) > 1e-6) {
            return sprintf('La línea es en "%s" (%s unidades): la cantidad tiene que ser de presentaciones enteras.',
                $foto['presentacion_nombre'], lpre_num($foto['presentacion_factor']));
        }
        if ($grabar) {
            $foto['presentacion_cantidad'] = round($pc, 4);
            vpre_guardar_foto($conexion, 'pedido', $detalle_id, $foto);
        }
        return null;
    }
}

if (!function_exists('vpre_fotos_lineas')) {
    /** Fotos de varias líneas: [detalle_id => foto] (solo las que tienen presentación). Para obtener / imprimir. */
    function vpre_fotos_lineas(mysqli $conexion, string $tipo, array $detalle_ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $detalle_ids))));
        if (!$ids || !vpre_columnas($conexion, $tipo)) {
            return [];
        }
        [$tabla, $pk] = VPRE_TABLAS[$tipo];
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $st = mysqli_prepare($conexion, "SELECT `$pk` AS id, " . implode(', ', VPRE_COLUMNAS) . " FROM `$tabla`
                                         WHERE `$pk` IN ($ph) AND presentacion_id IS NOT NULL");
        mysqli_stmt_bind_param($st, str_repeat('i', count($ids)), ...$ids);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        $out = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $out[(int) $r['id']] = [
                'presentacion_id' => (int) $r['presentacion_id'],
                'presentacion_nombre' => $r['presentacion_nombre'],
                'presentacion_factor' => (float) $r['presentacion_factor'],
                'presentacion_cantidad' => (float) $r['presentacion_cantidad'],
                'presentacion_precio' => $r['presentacion_precio'] !== null ? (float) $r['presentacion_precio'] : null,
            ];
        }
        mysqli_stmt_close($st);
        return $out;
    }
}

if (!function_exists('vpre_agregar_fotos')) {
    /** Agrega las columnas de la foto a filas de líneas (con la PK del tipo) para devolverlas a la pantalla. */
    function vpre_agregar_fotos(mysqli $conexion, string $tipo, array $filas): array
    {
        if (!$filas || !vpre_columnas($conexion, $tipo)) {
            return $filas;
        }
        $pk = VPRE_TABLAS[$tipo][1];
        $fotos = vpre_fotos_lineas($conexion, $tipo, array_column($filas, $pk));
        foreach ($filas as &$f) {
            $foto = $fotos[(int) ($f[$pk] ?? 0)] ?? null;
            foreach (VPRE_COLUMNAS as $c) {
                $f[$c] = $foto[$c] ?? null;
            }
        }
        unset($f);
        return $filas;
    }
}

if (!function_exists('vpre_preparar_lineas')) {
    /**
     * Punto de entrada de los modelos: si la venta por presentación está activa para el tipo, normaliza y valida las
     * líneas (vpre_normalizar_lineas); si no, las devuelve sin tocar. $lista_precio_id: la del cliente (0 = no se
     * controla la unidad suelta).
     * @throws InvalidArgumentException con el mensaje para el usuario
     */
    function vpre_preparar_lineas(mysqli $conexion, int $empresa_id, int $lista_precio_id, array $detalles, string $tipo): array
    {
        if (!vpre_activo($conexion, $empresa_id, $tipo)) {
            return $detalles;
        }
        return vpre_normalizar_lineas($conexion, $empresa_id, $lista_precio_id, $detalles, $tipo);
    }
}
