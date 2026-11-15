<?php
/**
 * Presentaciones en las líneas de órdenes de compra y facturas de proveedor (doc 27, aprobado el 2026-11-15).
 * Mismo modelo que la venta (core/ventas_presentaciones.php): la línea sigue en unidad base (cantidad y precio
 * unitario de siempre: recepción, stock, IVA, asiento y cuenta corriente no cambian) y, si se compró en una
 * presentación, se guarda su "foto" en cinco columnas NULL por defecto (script 20261122_compra_presentaciones_01.sql):
 * presentacion_id, presentacion_nombre, presentacion_factor, presentacion_cantidad, presentacion_precio.
 * cantidad = presentacion_cantidad × factor.
 *
 * - Habilitación: cpre_activo() = usa_compra_presentaciones + usa_presentaciones + las columnas en la tabla de líneas.
 *   Si no, los modelos siguen como siempre y no llaman a nada más de acá.
 * - Antes de grabar: cpre_preparar_lineas() valida y convierte (cada línea sale con '_cpre' = foto o null); después
 *   de grabar cada línea, cpre_guardar_foto(). Las columnas nuevas se escriben siempre con un UPDATE aparte.
 * - En compras no hay mínimos ni múltiplos (son de venta) y se aceptan cantidades con decimales (lo que facturó el
 *   proveedor). La presentación tiene que ser del producto, estar activa y ser de compra (aplica_compra).
 * - Factura desde OC: la pantalla trae la presentación de la línea de la OC (cpre_agregar_fotos en fpOcLineas) y la
 *   línea se valida igual que una cargada a mano; la foto queda con lo facturado.
 * - Costo: en las líneas con presentación, cpre_costo_linea() lo calcula desde los importes (4 decimales) en vez de
 *   precio_unitario_neto (2 decimales).
 */

require_once __DIR__ . '/productos_presentaciones.php';

const CPRE_TABLAS = [
    'oc' => ['gestion__ordenes_compra_detalle', 'ordenes_compra_detalle_id'],
    'factura' => ['gestion__facturas_proveedores_detalle', 'factura_proveedor_detalle_id'],
];
const CPRE_COLUMNAS = ['presentacion_id', 'presentacion_nombre', 'presentacion_factor', 'presentacion_cantidad', 'presentacion_precio'];

if (!function_exists('cpre_columnas')) {
    /** ¿Tiene la tabla de líneas del tipo ('oc' | 'factura') las cinco columnas? (cacheado) */
    function cpre_columnas(mysqli $conexion, string $tipo): bool
    {
        static $cache = [];
        if (!isset(CPRE_TABLAS[$tipo])) {
            return false;
        }
        if (!isset($cache[$tipo])) {
            $tabla = CPRE_TABLAS[$tipo][0];
            $res = mysqli_query($conexion, "SHOW COLUMNS FROM `$tabla` WHERE Field LIKE 'presentacion\\_%'");
            $hay = [];
            while ($res && ($r = mysqli_fetch_row($res))) {
                $hay[] = $r[0];
            }
            $cache[$tipo] = !array_diff(CPRE_COLUMNAS, $hay);
        }
        return $cache[$tipo];
    }
}

if (!function_exists('cpre_empresa_usa')) {
    /** Indicador usa_compra_presentaciones (y usa_presentaciones). Sin la columna: false. */
    function cpre_empresa_usa(mysqli $conexion, int $empresa_id): bool
    {
        static $cache = [];
        if (isset($cache[$empresa_id])) {
            return $cache[$empresa_id];
        }
        $usa = false;
        if ($empresa_id > 0 && prpr_empresa_usa($conexion, $empresa_id)) {
            $col = mysqli_query($conexion, "SHOW COLUMNS FROM conf__empresas LIKE 'usa_compra_presentaciones'");
            if ($col && mysqli_num_rows($col) > 0) {
                $st = mysqli_prepare($conexion, "SELECT usa_compra_presentaciones FROM conf__empresas WHERE empresa_id = ?");
                mysqli_stmt_bind_param($st, "i", $empresa_id);
                mysqli_stmt_execute($st);
                $r = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
                mysqli_stmt_close($st);
                $usa = intval($r['usa_compra_presentaciones'] ?? 0) === 1;
            }
        }
        return $cache[$empresa_id] = $usa;
    }
}

if (!function_exists('cpre_activo')) {
    /** ¿La empresa compra por presentación y la tabla de líneas del tipo tiene las columnas? */
    function cpre_activo(mysqli $conexion, int $empresa_id, string $tipo): bool
    {
        return cpre_empresa_usa($conexion, $empresa_id) && cpre_columnas($conexion, $tipo);
    }
}

if (!function_exists('cpre_num')) {
    function cpre_num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 4, ',', '.'), '0'), ',');
    }
}

if (!function_exists('cpre_presentaciones')) {
    /**
     * Presentaciones de compra activas de los productos: [producto_id => [[presentacion_id, presentacion_nombre,
     * factor, es_compra_defecto], ...]], la de compra por defecto primero.
     */
    function cpre_presentaciones(mysqli $conexion, int $empresa_id, array $producto_ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $producto_ids))));
        if (!$ids) {
            return [];
        }
        $st = mysqli_prepare($conexion, "SELECT presentacion_id, producto_id, presentacion_nombre, factor, es_compra_defecto
                                         FROM gestion__productos_presentaciones
                                         WHERE empresa_id = ? AND tabla_estado_registro_id = 1 AND aplica_compra = 1
                                           AND producto_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
                                         ORDER BY producto_id, es_compra_defecto DESC, orden, factor");
        mysqli_stmt_bind_param($st, 'i' . str_repeat('i', count($ids)), $empresa_id, ...$ids);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        $out = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $out[(int) $r['producto_id']][] = [
                'presentacion_id' => (int) $r['presentacion_id'],
                'presentacion_nombre' => $r['presentacion_nombre'],
                'factor' => (float) $r['factor'],
                'es_compra_defecto' => (int) $r['es_compra_defecto'],
            ];
        }
        mysqli_stmt_close($st);
        return $out;
    }
}

if (!function_exists('cpre_agregar_presentaciones')) {
    /** Para los buscadores de productos: agrega 'presentaciones_compra' a cada fila con producto_id (sin el indicador, tal cual). */
    function cpre_agregar_presentaciones(mysqli $conexion, int $empresa_id, array $productos, string $tipo = 'oc'): array
    {
        if (!$productos || !cpre_activo($conexion, $empresa_id, $tipo)) {
            return $productos;
        }
        $pres = cpre_presentaciones($conexion, $empresa_id, array_column($productos, 'producto_id'));
        foreach ($productos as &$p) {
            if (is_array($p) && isset($p['producto_id'])) {
                $p['presentaciones_compra'] = $pres[(int) $p['producto_id']] ?? [];
            }
        }
        unset($p);
        return $productos;
    }
}

if (!function_exists('cpre_presentacion')) {
    /** Presentación activa de la empresa, o null. */
    function cpre_presentacion(mysqli $conexion, int $empresa_id, int $presentacion_id): ?array
    {
        $st = mysqli_prepare($conexion, "SELECT presentacion_id, producto_id, presentacion_nombre, factor, aplica_compra
                                         FROM gestion__productos_presentaciones
                                         WHERE presentacion_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1");
        mysqli_stmt_bind_param($st, "ii", $presentacion_id, $empresa_id);
        mysqli_stmt_execute($st);
        $r = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);
        return $r ?: null;
    }
}

if (!function_exists('cpre_normalizar_lineas')) {
    /**
     * Valida y convierte las líneas que vienen de la pantalla ANTES de grabarlas. Una línea con presentacion_id > 0
     * trae presentacion_cantidad y presentacion_precio (el de la presentación entera); se recalculan cantidad
     * (× factor) y precio unitario, y en la OC también los importes desde la presentación. Cada línea sale con
     * '_cpre' = foto para cpre_guardar_foto() (null = por unidad).
     * @throws InvalidArgumentException con el mensaje para el usuario
     */
    function cpre_normalizar_lineas(mysqli $conexion, int $empresa_id, array $detalles, string $tipo): array
    {
        foreach ($detalles as $i => &$d) {
            if (!is_array($d)) {
                continue;
            }
            $d['_cpre'] = null;
            $pres_id = intval($d['presentacion_id'] ?? 0);
            if ($pres_id <= 0) {
                continue;
            }
            $linea = 'Línea ' . ((int) $i + 1);
            $pres = cpre_presentacion($conexion, $empresa_id, $pres_id);
            if (!$pres || (int) $pres['producto_id'] !== intval($d['producto_id'] ?? 0)) {
                throw new InvalidArgumentException("$linea: la presentación no es de ese producto o está inactiva.");
            }
            if (!(int) $pres['aplica_compra']) {
                throw new InvalidArgumentException("$linea: la presentación \"{$pres['presentacion_nombre']}\" no se usa en compras.");
            }
            $factor = (float) $pres['factor'];
            if ($factor <= 0) {
                throw new InvalidArgumentException("$linea: la presentación \"{$pres['presentacion_nombre']}\" no tiene factor.");
            }
            $pc = round((float) ($d['presentacion_cantidad'] ?? 0), 4);
            if ($pc <= 0) {
                throw new InvalidArgumentException("$linea: la cantidad de \"{$pres['presentacion_nombre']}\" tiene que ser mayor que cero.");
            }
            $pp = round((float) ($d['presentacion_precio'] ?? 0), 6);
            if ($pp < 0) {
                throw new InvalidArgumentException("$linea: el precio no puede ser negativo.");
            }
            // Unidad base (lo que leen recepción, stock, costo, asientos)
            $d['cantidad'] = round($pc * $factor, 4);
            $d['precio_unitario'] = round($pp / $factor, 6);
            if ($tipo === 'oc') {
                // Importes desde la presentación (sin el redondeo del unitario)
                $iva_pct = (float) ($d['iva_porcentaje'] ?? 0);
                $d['neto_gravado'] = round($pc * $pp, 2);
                $d['iva_importe'] = round($d['neto_gravado'] * $iva_pct / 100, 2);
                $d['total_linea'] = round($d['neto_gravado'] + $d['iva_importe'] + (float) ($d['no_gravado'] ?? 0) + (float) ($d['exento'] ?? 0), 2);
            }
            $d['_cpre'] = [
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

if (!function_exists('cpre_preparar_lineas')) {
    /**
     * Punto de entrada de los modelos: con la compra por presentación activa para el tipo, normaliza y valida las
     * líneas; si no, las devuelve sin tocar.
     * @throws InvalidArgumentException
     */
    function cpre_preparar_lineas(mysqli $conexion, int $empresa_id, array $detalles, string $tipo): array
    {
        if (!cpre_activo($conexion, $empresa_id, $tipo)) {
            return $detalles;
        }
        return cpre_normalizar_lineas($conexion, $empresa_id, $detalles, $tipo);
    }
}

if (!function_exists('cpre_guardar_foto')) {
    /** Escribe (o borra, con null) la foto de la presentación de una línea ya grabada. */
    function cpre_guardar_foto(mysqli $conexion, string $tipo, int $detalle_id, ?array $foto): void
    {
        if ($detalle_id <= 0 || !cpre_columnas($conexion, $tipo)) {
            return;
        }
        [$tabla, $pk] = CPRE_TABLAS[$tipo];
        $f = $foto ?: array_fill_keys(CPRE_COLUMNAS, null);
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

if (!function_exists('cpre_fotos_lineas')) {
    /** Fotos de varias líneas: [detalle_id => foto] (solo las que tienen presentación). */
    function cpre_fotos_lineas(mysqli $conexion, string $tipo, array $detalle_ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $detalle_ids))));
        if (!$ids || !cpre_columnas($conexion, $tipo)) {
            return [];
        }
        [$tabla, $pk] = CPRE_TABLAS[$tipo];
        $st = mysqli_prepare($conexion, "SELECT `$pk` AS id, " . implode(', ', CPRE_COLUMNAS) . " FROM `$tabla`
                                         WHERE `$pk` IN (" . implode(',', array_fill(0, count($ids), '?')) . ") AND presentacion_id IS NOT NULL");
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

if (!function_exists('cpre_agregar_fotos')) {
    /**
     * Agrega las columnas de la foto a filas de líneas para devolverlas a la pantalla. $pk: la clave de la fila que
     * tiene el id de la línea del tipo (por defecto la PK de la tabla). Sin columnas, tal cual.
     */
    function cpre_agregar_fotos(mysqli $conexion, string $tipo, array $filas, ?string $pk = null): array
    {
        if (!$filas || !cpre_columnas($conexion, $tipo)) {
            return $filas;
        }
        $pk = $pk ?? CPRE_TABLAS[$tipo][1];
        $fotos = cpre_fotos_lineas($conexion, $tipo, array_column($filas, $pk));
        foreach ($filas as &$f) {
            $foto = $fotos[(int) ($f[$pk] ?? 0)] ?? null;
            foreach (CPRE_COLUMNAS as $c) {
                $f[$c] = $foto[$c] ?? null;
            }
        }
        unset($f);
        return $filas;
    }
}

if (!function_exists('cpre_costo_linea')) {
    /**
     * Costo unitario de una línea de factura de proveedor con presentación: (neto + no gravado + exento) ÷ cantidad,
     * con 4 decimales (precio_unitario_neto se guarda con 2: una caja de $ 1.000 x 12 daría $ 83,33). Devuelve null si
     * la línea no tiene presentación (el modelo sigue con su cálculo de siempre).
     */
    function cpre_costo_linea(array $fila): ?float
    {
        if (empty($fila['presentacion_id']) || (float) ($fila['cantidad'] ?? 0) <= 0) {
            return null;
        }
        $importe = (float) ($fila['neto_gravado'] ?? 0) + (float) ($fila['no_gravado'] ?? 0) + (float) ($fila['exento'] ?? 0);
        return round($importe / (float) $fila['cantidad'], 4);
    }
}

if (!function_exists('cpre_lineas_impresion')) {
    /**
     * Líneas para imprimir: las compradas en una presentación salen en presentaciones ("10 Caja x 12 a $ 9.600") en
     * vez de unidades. Cambia solo lo que se muestra; los totales se calculan antes con las líneas originales.
     */
    function cpre_lineas_impresion(mysqli $conexion, int $empresa_id, string $tipo, array $detalles): array
    {
        if (!$detalles || !cpre_activo($conexion, $empresa_id, $tipo)) {
            return $detalles;
        }
        $pk = CPRE_TABLAS[$tipo][1];
        if (!array_key_exists('presentacion_id', reset($detalles) ?: [])) {
            $detalles = cpre_agregar_fotos($conexion, $tipo, $detalles, $pk);
        }
        foreach ($detalles as &$d) {
            $factor = (float) ($d['presentacion_factor'] ?? 0);
            if ((int) ($d['presentacion_id'] ?? 0) <= 0 || $factor <= 0) {
                continue;
            }
            $unidades = (float) ($d['cantidad'] ?? 0);
            $pc = (float) $d['presentacion_cantidad'];
            $d['cantidad'] = $pc;
            if (isset($d['cantidad_recibida']) && $d['cantidad_recibida'] !== '') {
                $d['cantidad_recibida'] = round((float) $d['cantidad_recibida'] / $factor, 4);
            }
            // Precio de la presentación tal como se cargó (no el unitario redondeado × factor: 83,33 × 12 = 999,96)
            $pp = $d['presentacion_precio'] !== null ? (float) $d['presentacion_precio'] : null;
            foreach (['precio_unitario', 'precio_unitario_bruto'] as $c) {
                if (isset($d[$c]) && $d[$c] !== '') {
                    $d[$c] = $pp !== null ? $pp : round((float) $d[$c] * $factor, 6);
                }
            }
            if (isset($d['precio_unitario_neto']) && $d['precio_unitario_neto'] !== '') {
                $importe = (float) ($d['neto_gravado'] ?? 0) + (float) ($d['no_gravado'] ?? 0) + (float) ($d['exento'] ?? 0);
                $d['precio_unitario_neto'] = ($importe > 0 && $pc > 0) ? round($importe / $pc, 6) : round((float) $d['precio_unitario_neto'] * $factor, 6);
            }
            $d['producto_nombre'] = trim((string) ($d['producto_nombre'] ?? '')) . ' — ' . $d['presentacion_nombre'] . ' (' . cpre_num($unidades) . ' u)';
        }
        unset($d);
        return $detalles;
    }
}

// ---------------------------------------------------------------------------------------------------------------
// Paso 8-4: presentación en la que vende el proveedor (gestion__productos_proveedores.presentacion_id, script
// 20261122_compra_presentaciones_02.sql). Sin la columna o sin el indicador, nada de esto se usa.
// ---------------------------------------------------------------------------------------------------------------

if (!function_exists('cpre_proveedor_activo')) {
    /** ¿La empresa compra por presentación y gestion__productos_proveedores tiene presentacion_id? */
    function cpre_proveedor_activo(mysqli $conexion, int $empresa_id): bool
    {
        static $col = null;
        if (!cpre_empresa_usa($conexion, $empresa_id)) {
            return false;
        }
        if ($col === null) {
            $res = mysqli_query($conexion, "SHOW COLUMNS FROM gestion__productos_proveedores LIKE 'presentacion_id'");
            $col = $res && mysqli_num_rows($res) > 0;
        }
        return $col;
    }
}

if (!function_exists('cpre_presentaciones_proveedor')) {
    /**
     * Presentación del proveedor de cada producto (vínculo activo con presentacion_id, presentación activa y de
     * compra): [producto_id => ['presentacion_id', 'presentacion_nombre', 'factor']]. Vacío sin el indicador / columna.
     */
    function cpre_presentaciones_proveedor(mysqli $conexion, int $empresa_id, int $entidad_id, array $producto_ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $producto_ids))));
        if (!$ids || $entidad_id <= 0 || !cpre_proveedor_activo($conexion, $empresa_id)) {
            return [];
        }
        $st = mysqli_prepare($conexion, "SELECT pp.producto_id, pr.presentacion_id, pr.presentacion_nombre, pr.factor
                                           FROM gestion__productos_proveedores pp
                                           JOIN gestion__productos_presentaciones pr ON pr.presentacion_id = pp.presentacion_id
                                                AND pr.producto_id = pp.producto_id AND pr.empresa_id = pp.empresa_id
                                                AND pr.tabla_estado_registro_id = 1 AND pr.aplica_compra = 1 AND pr.factor > 0
                                          WHERE pp.empresa_id = ? AND pp.entidad_id = ? AND pp.tabla_estado_registro_id = 1
                                            AND pp.producto_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")");
        mysqli_stmt_bind_param($st, 'ii' . str_repeat('i', count($ids)), $empresa_id, $entidad_id, ...$ids);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        $out = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $out[(int) $r['producto_id']] = ['presentacion_id' => (int) $r['presentacion_id'],
                                             'presentacion_nombre' => $r['presentacion_nombre'], 'factor' => (float) $r['factor']];
        }
        mysqli_stmt_close($st);
        return $out;
    }
}

if (!function_exists('cpre_presentaciones_compra')) {
    /**
     * Para la pantalla (acción cpre_presentaciones): presentaciones de compra del producto; con proveedor, la suya
     * va primero y con es_proveedor = 1 (la pantalla la propone antes que la de compra por defecto).
     */
    function cpre_presentaciones_compra(mysqli $conexion, int $empresa_id, int $producto_id, int $entidad_id = 0): array
    {
        $lista = cpre_presentaciones($conexion, $empresa_id, [$producto_id])[$producto_id] ?? [];
        $prov = cpre_presentaciones_proveedor($conexion, $empresa_id, $entidad_id, [$producto_id])[$producto_id] ?? null;
        foreach ($lista as &$o) {
            $o['es_proveedor'] = ($prov && $o['presentacion_id'] === $prov['presentacion_id']) ? 1 : 0;
        }
        unset($o);
        usort($lista, fn($a, $b) => $b['es_proveedor'] <=> $a['es_proveedor']);
        return $lista;
    }
}

if (!function_exists('cpre_validar_presentacion_proveedor')) {
    /** 0 = por unidad; si no, una presentación activa y de compra del producto. @throws InvalidArgumentException */
    function cpre_validar_presentacion_proveedor(mysqli $conexion, int $empresa_id, int $producto_id, int $presentacion_id): void
    {
        if ($presentacion_id <= 0) {
            return;
        }
        $pres = cpre_presentacion($conexion, $empresa_id, $presentacion_id);
        if (!$pres || (int) $pres['producto_id'] !== $producto_id) {
            throw new InvalidArgumentException('La presentación no es de este producto o está inactiva.');
        }
        if (!(int) $pres['aplica_compra']) {
            throw new InvalidArgumentException("La presentación \"{$pres['presentacion_nombre']}\" no se usa en compras.");
        }
    }
}

if (!function_exists('cpre_vinculos_presentacion')) {
    /** Presentación del proveedor de los vínculos de un producto: [producto_proveedor_id => [presentacion_id, presentacion_nombre]]. */
    function cpre_vinculos_presentacion(mysqli $conexion, int $empresa_id, int $producto_id): array
    {
        if (!cpre_proveedor_activo($conexion, $empresa_id)) {
            return [];
        }
        $st = mysqli_prepare($conexion, "SELECT pp.producto_proveedor_id, pp.presentacion_id, pr.presentacion_nombre
                                           FROM gestion__productos_proveedores pp
                                           LEFT JOIN gestion__productos_presentaciones pr ON pr.presentacion_id = pp.presentacion_id AND pr.empresa_id = pp.empresa_id
                                          WHERE pp.empresa_id = ? AND pp.producto_id = ? AND pp.presentacion_id IS NOT NULL");
        mysqli_stmt_bind_param($st, 'ii', $empresa_id, $producto_id);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        $out = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $out[(int) $r['producto_proveedor_id']] = ['presentacion_id' => (int) $r['presentacion_id'], 'presentacion_nombre' => $r['presentacion_nombre']];
        }
        mysqli_stmt_close($st);
        return $out;
    }
}

if (!function_exists('cpre_guardar_presentacion_proveedor')) {
    /**
     * Fija (o quita, con 0) la presentación en la que vende el proveedor, en un UPDATE aparte. Tiene que ser una
     * presentación activa y de compra del producto. Sin el indicador / columna no hace nada. @throws InvalidArgumentException
     */
    function cpre_guardar_presentacion_proveedor(mysqli $conexion, int $empresa_id, int $producto_id, int $producto_proveedor_id, int $presentacion_id): void
    {
        if (!cpre_proveedor_activo($conexion, $empresa_id)) {
            return;
        }
        cpre_validar_presentacion_proveedor($conexion, $empresa_id, $producto_id, $presentacion_id);
        $valor = $presentacion_id > 0 ? $presentacion_id : null;
        $st = mysqli_prepare($conexion, "UPDATE gestion__productos_proveedores SET presentacion_id = ?
                                          WHERE producto_proveedor_id = ? AND empresa_id = ? AND producto_id = ?");
        mysqli_stmt_bind_param($st, 'iiii', $valor, $producto_proveedor_id, $empresa_id, $producto_id);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
    }
}

if (!function_exists('cpre_lectura_lineas')) {
    /**
     * Carga de facturas desde archivo: un ítem reconocido por el código del proveedor, de un producto que ese
     * proveedor vende en una presentación, viene en presentaciones (cantidad de cajas, precio de la caja). La línea
     * (importes ya calculados) pasa a unidad base y lleva la foto; cpre_preparar_lineas() la vuelve a validar al
     * guardar. Solo toca las líneas con 'codigo_proveedor' (las de imputación quedan igual). Devuelve [líneas, avisos].
     */
    function cpre_lectura_lineas(mysqli $conexion, int $empresa_id, int $entidad_id, array $detalles): array
    {
        if (!$detalles || !cpre_activo($conexion, $empresa_id, 'factura')) {
            return [$detalles, []];
        }
        $con_codigo = array_filter($detalles, fn($d) => trim((string) ($d['codigo_proveedor'] ?? '')) !== '');
        $pres = cpre_presentaciones_proveedor($conexion, $empresa_id, $entidad_id, array_column($con_codigo, 'producto_id'));
        if (!$pres) {
            return [$detalles, []];
        }
        $n = 0;
        foreach ($detalles as &$d) {
            $p = $pres[(int) ($d['producto_id'] ?? 0)] ?? null;
            if (!$p || trim((string) ($d['codigo_proveedor'] ?? '')) === '' || (float) ($d['cantidad'] ?? 0) <= 0) {
                continue;
            }
            $f = $p['factor'];
            $pc = (float) $d['cantidad'];
            $pp = (float) $d['precio_unitario'];
            $d['presentacion_id'] = $p['presentacion_id'];
            $d['presentacion_nombre'] = $p['presentacion_nombre'];
            $d['presentacion_factor'] = $f;
            $d['presentacion_cantidad'] = $pc;
            $d['presentacion_precio'] = $pp;
            $d['cantidad'] = round($pc * $f, 4);
            $d['precio_unitario'] = round($pp / $f, 6);
            $d['precio_unitario_bruto'] = round((float) ($d['precio_unitario_bruto'] ?? $pp) / $f, 6);
            $n++;
        }
        unset($d);
        return [$detalles, $n ? [$n . ' ítem(s) se cargaron en la presentación del proveedor (cantidad y precio por caja / pack).'] : []];
    }
}
