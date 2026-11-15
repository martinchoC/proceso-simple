<?php
/**
 * Venta por presentación (paso 1): precios por presentación en cada lista, unidad suelta por lista, mínimo y
 * múltiplo de venta y disponibilidad por sucursal. Diseño en _local/docs/gestion/19_precios_presentaciones.md.
 *
 * - Habilitación por empresa: conf__empresas.usa_venta_presentaciones + usa_presentaciones (lpre_empresa_usa).
 *   Sin la columna, la tabla o cualquiera de los dos indicadores, devuelve false y nada de esto corre.
 * - Precio: gestion__listas_precios_presentaciones (tabla 463), precio de la presentación entera por lista. Sin
 *   fila vigente la presentación no se ofrece en esa lista (no se calcula unitario × factor). Cambiar un precio
 *   deja la fila anterior en estado 2 (historial) e inserta otra.
 * - Canal: la lista vigente del cliente en gestion__entidades_condiciones_clientes. Con
 *   gestion__listas_precios.vende_unidad_base = 0 la lista no ofrece la unidad suelta.
 * - Mínimo / múltiplo: venta_minimo / venta_multiplo en gestion__productos (unidad base) y en
 *   gestion__productos_presentaciones (en cantidad de esa presentación). NULL = sin restricción.
 * - Disponibilidad: stock base de la sucursal = suma de sus bocas activas con es_deposito = 1;
 *   presentación = floor(stock base / factor). Solo stock físico (el pedido no reserva).
 *
 * Script: _local/sql/mantenimiento/20261114_venta_presentaciones_01.sql.
 */

require_once __DIR__ . '/productos_presentaciones.php';

if (!function_exists('lpre_columna_existe')) {
    /** ¿Existe la columna? (cacheado por request) */
    function lpre_columna_existe(mysqli $conexion, string $tabla, string $columna): bool
    {
        static $cache = [];
        $k = "$tabla.$columna";
        if (!isset($cache[$k])) {
            $tabla_sql = preg_replace('/[^a-z0-9_]/i', '', $tabla);
            $col = mysqli_real_escape_string($conexion, $columna);
            $res = mysqli_query($conexion, "SHOW COLUMNS FROM `$tabla_sql` LIKE '$col'");
            $cache[$k] = $res && mysqli_num_rows($res) > 0;
        }
        return $cache[$k];
    }
}

if (!function_exists('lpre_disponible')) {
    /** ¿Existe la tabla de precios por presentación? (cacheado por request) */
    function lpre_disponible(mysqli $conexion): bool
    {
        static $existe = null;
        if ($existe === null) {
            $res = mysqli_query($conexion, "SHOW TABLES LIKE 'gestion__listas_precios_presentaciones'");
            $existe = $res && mysqli_num_rows($res) > 0;
        }
        return $existe;
    }
}

if (!function_exists('lpre_empresa_usa')) {
    /** ¿La empresa vende por presentación? Lectura tolerante: falta de columna o tabla = no. */
    function lpre_empresa_usa(mysqli $conexion, int $empresa_id): bool
    {
        static $cache = [];
        if (isset($cache[$empresa_id])) {
            return $cache[$empresa_id];
        }
        $usa = false;
        if ($empresa_id > 0 && lpre_disponible($conexion) && prpr_empresa_usa($conexion, $empresa_id)
            && lpre_columna_existe($conexion, 'conf__empresas', 'usa_venta_presentaciones')) {
            $stmt = mysqli_prepare($conexion, "SELECT usa_venta_presentaciones FROM conf__empresas WHERE empresa_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $empresa_id);
            mysqli_stmt_execute($stmt);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            $usa = intval($row['usa_venta_presentaciones'] ?? 0) === 1;
        }
        return $cache[$empresa_id] = $usa;
    }
}

if (!function_exists('lpre_validar_cantidad')) {
    /**
     * Valida una cantidad contra el mínimo y el múltiplo (NULL = sin restricción). Devuelve null si está bien o
     * el mensaje para el usuario. $unidad es el nombre que se muestra ("Caja x 12", "unidades").
     */
    function lpre_validar_cantidad(float $cantidad, ?float $minimo, ?float $multiplo, string $unidad = ''): ?string
    {
        $u = $unidad !== '' ? " $unidad" : '';
        if ($cantidad <= 0) {
            return 'La cantidad tiene que ser mayor que cero.';
        }
        if ($minimo !== null && $minimo > 0 && $cantidad + 1e-9 < $minimo) {
            return 'La cantidad mínima es ' . lpre_num($minimo) . $u . '.';
        }
        if ($multiplo !== null && $multiplo > 0) {
            $veces = $cantidad / $multiplo;
            if (abs($veces - round($veces)) > 1e-6) {
                return 'Se vende de a ' . lpre_num($multiplo) . $u . '.';
            }
        }
        return null;
    }
}

if (!function_exists('lpre_num')) {
    /** Número sin ceros de más, para mensajes (2, 2.5, 0.25). */
    function lpre_num(float $valor): string
    {
        return rtrim(rtrim(number_format($valor, 4, '.', ''), '0'), '.');
    }
}

if (!function_exists('lpre_disponibilidad')) {
    /** Presentaciones enteras que alcanzan con el stock base: floor(stock / factor), nunca negativo. */
    function lpre_disponibilidad(float $stock_base, float $factor): int
    {
        if ($factor <= 0 || $stock_base <= 0) {
            return 0;
        }
        return (int) floor($stock_base / $factor + 1e-9);
    }
}

if (!function_exists('lpre_stock_sucursal')) {
    /**
     * Stock base por producto en la sucursal: suma de las bocas activas con es_deposito = 1.
     * Devuelve [producto_id => cantidad]; los productos sin stock no aparecen.
     */
    function lpre_stock_sucursal(mysqli $conexion, int $empresa_id, int $sucursal_id, array $producto_ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $producto_ids))));
        if (!$ids || $sucursal_id <= 0) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $sql = "SELECT s.producto_id, SUM(s.cantidad) AS cantidad
                FROM gestion__stock s
                INNER JOIN gestion__bocas b ON b.boca_id = s.boca_id AND b.empresa_id = s.empresa_id
                WHERE s.empresa_id = ? AND s.sucursal_id = ? AND b.es_deposito = 1 AND b.tabla_estado_registro_id = 1
                  AND s.producto_id IN ($ph)
                GROUP BY s.producto_id";
        $stmt = mysqli_prepare($conexion, $sql);
        $params = array_merge([$empresa_id, $sucursal_id], $ids);
        mysqli_stmt_bind_param($stmt, str_repeat('i', count($params)), ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $stock = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $stock[(int) $r['producto_id']] = (float) $r['cantidad'];
        }
        mysqli_stmt_close($stmt);
        return $stock;
    }
}

if (!function_exists('lpre_lista_cliente')) {
    /**
     * Lista de precios vigente del cliente (el canal), con el mismo criterio que obtenerListaPrecioVigenteCliente
     * de ventas_pedidos_model.php. 0 si no tiene.
     */
    function lpre_lista_cliente(mysqli $conexion, int $empresa_id, int $entidad_id): int
    {
        $stmt = mysqli_prepare($conexion, "SELECT ecc.lista_precio_id
                                           FROM gestion__entidades_condiciones_clientes ecc
                                           INNER JOIN gestion__entidades e ON e.entidad_id = ecc.entidad_id AND e.empresa_id = ?
                                           WHERE ecc.entidad_id = ? AND ecc.tabla_estado_registro_id = 1
                                             AND ecc.f_desde <= CURDATE() AND (ecc.f_hasta IS NULL OR ecc.f_hasta >= CURDATE())
                                           ORDER BY ecc.f_desde DESC LIMIT 1");
        mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $entidad_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return intval($row['lista_precio_id'] ?? 0);
    }
}

if (!function_exists('lpre_lista_vende_unidad')) {
    /** ¿La lista ofrece la unidad suelta? Sin la columna, sí (como hoy). */
    function lpre_lista_vende_unidad(mysqli $conexion, int $empresa_id, int $lista_precio_id): bool
    {
        if (!lpre_columna_existe($conexion, 'gestion__listas_precios', 'vende_unidad_base')) {
            return true;
        }
        $stmt = mysqli_prepare($conexion, "SELECT vende_unidad_base FROM gestion__listas_precios WHERE lista_precio_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $lista_precio_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return $row ? intval($row['vende_unidad_base']) === 1 : true;
    }
}

if (!function_exists('lpre_precios_vigentes')) {
    /**
     * Precios vigentes por presentación de una lista: [presentacion_id => ['precio' => float,
     * 'lista_precio_presentacion_id' => int, 'f_desde' => 'Y-m-d']]. Si hubiera dos vigentes, gana la de f_desde más
     * reciente (y la última cargada).
     */
    function lpre_precios_vigentes(mysqli $conexion, int $empresa_id, int $lista_precio_id, array $producto_ids = []): array
    {
        if (!lpre_disponible($conexion) || $lista_precio_id <= 0) {
            return [];
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $producto_ids))));
        $filtro = '';
        $params = [$empresa_id, $lista_precio_id];
        if ($ids) {
            $filtro = ' AND producto_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')';
            $params = array_merge($params, $ids);
        }
        $sql = "SELECT lista_precio_presentacion_id, presentacion_id, precio_final, f_desde
                FROM gestion__listas_precios_presentaciones
                WHERE empresa_id = ? AND lista_precio_id = ? AND tabla_estado_registro_id = 1
                  AND f_desde <= CURDATE() AND (f_hasta IS NULL OR f_hasta >= CURDATE()) $filtro
                ORDER BY f_desde, lista_precio_presentacion_id";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, str_repeat('i', count($params)), ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $precios = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $precios[(int) $r['presentacion_id']] = [
                'precio' => (float) $r['precio_final'],
                'lista_precio_presentacion_id' => (int) $r['lista_precio_presentacion_id'],
                'f_desde' => $r['f_desde'],
            ];
        }
        mysqli_stmt_close($stmt);
        return $precios;
    }
}

if (!function_exists('lpre_ofertas')) {
    /**
     * Qué se puede vender de cada producto en una lista: la unidad suelta (si la lista la vende y tiene precio
     * unitario vigente) y cada presentación activa con aplica_venta y precio vigente en la lista. Con $sucursal_id
     * agrega la disponibilidad. Devuelve [producto_id => [oferta, ...]], cada oferta:
     *   presentacion_id (null = unidad suelta), nombre, factor, precio, minimo, multiplo, disponible (o null).
     * Solo con lpre_empresa_usa(); si no, [] (el llamador sigue con su comportamiento de siempre).
     */
    function lpre_ofertas(mysqli $conexion, int $empresa_id, int $lista_precio_id, array $producto_ids, int $sucursal_id = 0): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $producto_ids))));
        if (!$ids || $lista_precio_id <= 0 || !lpre_empresa_usa($conexion, $empresa_id)) {
            return [];
        }
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $tipos = str_repeat('i', count($ids));
        $stock = $sucursal_id > 0 ? lpre_stock_sucursal($conexion, $empresa_id, $sucursal_id, $ids) : null;
        $ofertas = [];

        // Unidad suelta: precio vigente de gestion__listas_precios_productos (mismo filtro que el buscador de pedidos)
        if (lpre_lista_vende_unidad($conexion, $empresa_id, $lista_precio_id)) {
            $min = lpre_columna_existe($conexion, 'gestion__productos', 'venta_minimo') ? 'p.venta_minimo' : 'NULL';
            $mul = lpre_columna_existe($conexion, 'gestion__productos', 'venta_multiplo') ? 'p.venta_multiplo' : 'NULL';
            $sql = "SELECT p.producto_id, $min AS venta_minimo, $mul AS venta_multiplo,
                           (SELECT lp.precio_final FROM gestion__listas_precios_productos lp
                             WHERE lp.producto_id = p.producto_id AND lp.lista_precio_id = ? AND lp.empresa_id = p.empresa_id
                               AND lp.tabla_estado_registro_id = 1 AND lp.f_desde <= CURDATE()
                               AND (lp.f_hasta IS NULL OR lp.f_hasta >= CURDATE())
                             ORDER BY lp.f_desde DESC, lp.lista_precio_producto_id DESC LIMIT 1) AS precio
                    FROM gestion__productos p
                    WHERE p.empresa_id = ? AND p.tabla_estado_registro_id = 1 AND p.producto_id IN ($ph)";
            $stmt = mysqli_prepare($conexion, $sql);
            $params = array_merge([$lista_precio_id, $empresa_id], $ids);
            mysqli_stmt_bind_param($stmt, 'ii' . $tipos, ...$params);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($r = mysqli_fetch_assoc($res)) {
                if ($r['precio'] === null) {
                    continue;
                }
                $pid = (int) $r['producto_id'];
                $ofertas[$pid][] = [
                    'presentacion_id' => null,
                    'nombre' => 'Unidad',
                    'factor' => 1.0,
                    'precio' => (float) $r['precio'],
                    'minimo' => $r['venta_minimo'] !== null ? (float) $r['venta_minimo'] : null,
                    'multiplo' => $r['venta_multiplo'] !== null ? (float) $r['venta_multiplo'] : null,
                    'disponible' => $stock === null ? null : lpre_disponibilidad($stock[$pid] ?? 0, 1.0),
                ];
            }
            mysqli_stmt_close($stmt);
        }

        // Presentaciones con precio vigente en la lista
        $precios = lpre_precios_vigentes($conexion, $empresa_id, $lista_precio_id, $ids);
        if ($precios) {
            $min = lpre_columna_existe($conexion, 'gestion__productos_presentaciones', 'venta_minimo') ? 'pp.venta_minimo' : 'NULL';
            $mul = lpre_columna_existe($conexion, 'gestion__productos_presentaciones', 'venta_multiplo') ? 'pp.venta_multiplo' : 'NULL';
            $sql = "SELECT pp.presentacion_id, pp.producto_id, pp.presentacion_nombre, pp.factor,
                           $min AS venta_minimo, $mul AS venta_multiplo
                    FROM gestion__productos_presentaciones pp
                    WHERE pp.empresa_id = ? AND pp.tabla_estado_registro_id = 1 AND pp.aplica_venta = 1
                      AND pp.producto_id IN ($ph)
                    ORDER BY pp.producto_id, pp.orden, pp.factor, pp.presentacion_id";
            $stmt = mysqli_prepare($conexion, $sql);
            $params = array_merge([$empresa_id], $ids);
            mysqli_stmt_bind_param($stmt, 'i' . $tipos, ...$params);
            mysqli_stmt_execute($stmt);
            $res = mysqli_stmt_get_result($stmt);
            while ($r = mysqli_fetch_assoc($res)) {
                $prid = (int) $r['presentacion_id'];
                if (!isset($precios[$prid])) {
                    continue; // sin precio en esta lista: no se ofrece
                }
                $pid = (int) $r['producto_id'];
                $factor = (float) $r['factor'];
                $ofertas[$pid][] = [
                    'presentacion_id' => $prid,
                    'nombre' => $r['presentacion_nombre'],
                    'factor' => $factor,
                    'precio' => $precios[$prid]['precio'],
                    'minimo' => $r['venta_minimo'] !== null ? (float) $r['venta_minimo'] : null,
                    'multiplo' => $r['venta_multiplo'] !== null ? (float) $r['venta_multiplo'] : null,
                    'disponible' => $stock === null ? null : lpre_disponibilidad($stock[$pid] ?? 0, $factor),
                ];
            }
            mysqli_stmt_close($stmt);
        }
        return $ofertas;
    }
}

if (!function_exists('lpre_guardar_precios')) {
    /**
     * Graba precios por presentación en una transacción propia (no llamar dentro de otra transacción abierta).
     * $filas: [['lista_precio_id' => int, 'presentacion_id' => int, 'precio' => float|null, 'f_desde' => 'Y-m-d'|null,
     *           'observaciones' => string|null], ...]. precio null o 0 = quitar el precio vigente (pasa a estado 2).
     * Si el precio vigente ya es el mismo, no se toca. Valida que la lista y la presentación sean de la empresa.
     * Devuelve ['success' => bool, 'insertados' => n, 'quitados' => n, 'sin_cambio' => n, 'errores' => [...]].
     */
    function lpre_guardar_precios(mysqli $conexion, int $empresa_id, int $usuario_id, array $filas): array
    {
        $out = ['success' => false, 'insertados' => 0, 'quitados' => 0, 'sin_cambio' => 0, 'errores' => []];
        if (!lpre_disponible($conexion)) {
            $out['errores'][] = 'Falta la tabla de precios por presentación (script 20261114_venta_presentaciones_01.sql).';
            return $out;
        }
        $hoy = date('Y-m-d');
        mysqli_begin_transaction($conexion);
        try {
            $st_lista = mysqli_prepare($conexion, "SELECT 1 FROM gestion__listas_precios WHERE lista_precio_id = ? AND empresa_id = ?");
            $st_pres = mysqli_prepare($conexion, "SELECT producto_id FROM gestion__productos_presentaciones
                                                  WHERE presentacion_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1");
            $st_act = mysqli_prepare($conexion, "SELECT lista_precio_presentacion_id, precio_final FROM gestion__listas_precios_presentaciones
                                                 WHERE empresa_id = ? AND lista_precio_id = ? AND presentacion_id = ? AND tabla_estado_registro_id = 1
                                                 FOR UPDATE");
            $st_baja = mysqli_prepare($conexion, "UPDATE gestion__listas_precios_presentaciones
                                                  SET tabla_estado_registro_id = 2, actualizado_en = NOW()
                                                  WHERE empresa_id = ? AND lista_precio_id = ? AND presentacion_id = ? AND tabla_estado_registro_id = 1");
            $st_ins = mysqli_prepare($conexion, "INSERT INTO gestion__listas_precios_presentaciones
                                                 (empresa_id, lista_precio_id, presentacion_id, producto_id, precio_final, f_desde, observaciones,
                                                  tabla_estado_registro_id, creado_por)
                                                 VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?)");
            foreach ($filas as $i => $f) {
                $n = $i + 1;
                $lista = intval($f['lista_precio_id'] ?? 0);
                $pres = intval($f['presentacion_id'] ?? 0);
                $precio = isset($f['precio']) && $f['precio'] !== '' ? round((float) $f['precio'], 4) : 0.0;
                $f_desde = !empty($f['f_desde']) ? (string) $f['f_desde'] : $hoy;
                $obs = isset($f['observaciones']) && $f['observaciones'] !== '' ? (string) $f['observaciones'] : null;

                mysqli_stmt_bind_param($st_lista, "ii", $lista, $empresa_id);
                mysqli_stmt_execute($st_lista);
                if (!mysqli_fetch_row(mysqli_stmt_get_result($st_lista))) {
                    $out['errores'][] = "Fila $n: la lista no es de la empresa.";
                    continue;
                }
                mysqli_stmt_bind_param($st_pres, "ii", $pres, $empresa_id);
                mysqli_stmt_execute($st_pres);
                $p = mysqli_fetch_assoc(mysqli_stmt_get_result($st_pres));
                if (!$p) {
                    $out['errores'][] = "Fila $n: la presentación no existe o está inactiva.";
                    continue;
                }
                if ($precio < 0) {
                    $out['errores'][] = "Fila $n: el precio no puede ser negativo.";
                    continue;
                }
                if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_desde)) {
                    $out['errores'][] = "Fila $n: fecha desde inválida.";
                    continue;
                }

                mysqli_stmt_bind_param($st_act, "iii", $empresa_id, $lista, $pres);
                mysqli_stmt_execute($st_act);
                $actual = mysqli_fetch_assoc(mysqli_stmt_get_result($st_act));

                if ($precio == 0.0) {
                    if ($actual) {
                        mysqli_stmt_bind_param($st_baja, "iii", $empresa_id, $lista, $pres);
                        mysqli_stmt_execute($st_baja);
                        $out['quitados']++;
                    } else {
                        $out['sin_cambio']++;
                    }
                    continue;
                }
                if ($actual && abs((float) $actual['precio_final'] - $precio) < 0.00005) {
                    $out['sin_cambio']++;
                    continue;
                }
                if ($actual) {
                    mysqli_stmt_bind_param($st_baja, "iii", $empresa_id, $lista, $pres);
                    mysqli_stmt_execute($st_baja);
                }
                $producto_id = (int) $p['producto_id'];
                mysqli_stmt_bind_param($st_ins, "iiiidssi", $empresa_id, $lista, $pres, $producto_id, $precio, $f_desde, $obs, $usuario_id);
                mysqli_stmt_execute($st_ins);
                $out['insertados']++;
            }
            foreach ([$st_lista, $st_pres, $st_act, $st_baja, $st_ins] as $st) {
                mysqli_stmt_close($st);
            }
            if ($out['errores']) {
                mysqli_rollback($conexion);
                $out['insertados'] = $out['quitados'] = $out['sin_cambio'] = 0;
                return $out;
            }
            mysqli_commit($conexion);
            $out['success'] = true;
            return $out;
        } catch (Throwable $e) {
            mysqli_rollback($conexion);
            $out['insertados'] = $out['quitados'] = $out['sin_cambio'] = 0;
            $out['errores'][] = $e->getMessage();
            return $out;
        }
    }
}

if (!function_exists('lpre_listas_empresa')) {
    /** Listas de precios activas de la empresa: [{lista_precio_id, nombre, vende_unidad_base}], en orden de id. */
    function lpre_listas_empresa(mysqli $conexion, int $empresa_id): array
    {
        $vub = lpre_columna_existe($conexion, 'gestion__listas_precios', 'vende_unidad_base') ? 'vende_unidad_base' : '1';
        $stmt = mysqli_prepare($conexion, "SELECT lista_precio_id, lista_precio_nombre, $vub AS vende_unidad_base
                                           FROM gestion__listas_precios
                                           WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                                           ORDER BY lista_precio_id");
        mysqli_stmt_bind_param($stmt, "i", $empresa_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $listas = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $listas[] = [
                'lista_precio_id' => (int) $r['lista_precio_id'],
                'nombre' => $r['lista_precio_nombre'],
                'vende_unidad_base' => (int) $r['vende_unidad_base'],
            ];
        }
        mysqli_stmt_close($stmt);
        return $listas;
    }
}

if (!function_exists('lpre_datos_producto')) {
    /**
     * Lo que necesita la ficha del producto para cargar la venta por presentación:
     *   listas  [{lista_precio_id, nombre, vende_unidad_base, precio_unidad (vigente o null)}]
     *   precios {presentacion_id: {lista_precio_id: precio}} (vigentes)
     *   unidad  {venta_minimo, venta_multiplo} del producto (null = sin restricción)
     */
    function lpre_datos_producto(mysqli $conexion, int $empresa_id, int $producto_id): array
    {
        $listas = lpre_listas_empresa($conexion, $empresa_id);
        $precios = [];
        $unidad = ['venta_minimo' => null, 'venta_multiplo' => null];
        if ($producto_id > 0) {
            $st = mysqli_prepare($conexion, "SELECT precio_final FROM gestion__listas_precios_productos
                                             WHERE producto_id = ? AND lista_precio_id = ? AND empresa_id = ?
                                               AND tabla_estado_registro_id = 1 AND f_desde <= CURDATE()
                                               AND (f_hasta IS NULL OR f_hasta >= CURDATE())
                                             ORDER BY f_desde DESC, lista_precio_producto_id DESC LIMIT 1");
            foreach ($listas as &$l) {
                $lista_id = $l['lista_precio_id'];
                mysqli_stmt_bind_param($st, "iii", $producto_id, $lista_id, $empresa_id);
                mysqli_stmt_execute($st);
                $row = mysqli_fetch_row(mysqli_stmt_get_result($st));
                $l['precio_unidad'] = $row ? (float) $row[0] : null;
                foreach (lpre_precios_vigentes($conexion, $empresa_id, $lista_id, [$producto_id]) as $pres_id => $p) {
                    $precios[$pres_id][$lista_id] = $p['precio'];
                }
            }
            unset($l);
            mysqli_stmt_close($st);

            if (lpre_columna_existe($conexion, 'gestion__productos', 'venta_minimo')
                && lpre_columna_existe($conexion, 'gestion__productos', 'venta_multiplo')) {
                $st = mysqli_prepare($conexion, "SELECT venta_minimo, venta_multiplo FROM gestion__productos WHERE producto_id = ? AND empresa_id = ?");
                mysqli_stmt_bind_param($st, "ii", $producto_id, $empresa_id);
                mysqli_stmt_execute($st);
                $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
                mysqli_stmt_close($st);
                if ($row) {
                    $unidad = [
                        'venta_minimo' => $row['venta_minimo'] !== null ? (float) $row['venta_minimo'] : null,
                        'venta_multiplo' => $row['venta_multiplo'] !== null ? (float) $row['venta_multiplo'] : null,
                    ];
                }
            }
        } else {
            foreach ($listas as &$l) {
                $l['precio_unidad'] = null;
            }
            unset($l);
        }
        return ['listas' => $listas, 'precios' => (object) $precios, 'unidad' => $unidad];
    }
}

if (!function_exists('lpre_guardar_restriccion_unidad')) {
    /** Mínimo / múltiplo de venta de la unidad suelta del producto (null = sin restricción). Sin las columnas, nada. */
    function lpre_guardar_restriccion_unidad(mysqli $conexion, int $empresa_id, int $producto_id, ?float $minimo, ?float $multiplo): bool
    {
        if (!lpre_columna_existe($conexion, 'gestion__productos', 'venta_minimo')
            || !lpre_columna_existe($conexion, 'gestion__productos', 'venta_multiplo')) {
            return false;
        }
        $st = mysqli_prepare($conexion, "UPDATE gestion__productos SET venta_minimo = ?, venta_multiplo = ? WHERE producto_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($st, "ddii", $minimo, $multiplo, $producto_id, $empresa_id);
        $ok = mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
        return $ok;
    }
}
