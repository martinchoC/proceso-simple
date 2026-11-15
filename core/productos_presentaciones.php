<?php
/**
 * Presentaciones de productos (gestion__productos_presentaciones, tabla 462).
 *
 * Una presentación es una forma de empaque del producto (Caja x 12, Pack x 6, Pallet) con su factor en
 * unidades de la unidad de medida del producto, código de barras propio (EAN / DUN-14), peso y medidas del
 * bulto, y si es la habitual de compra / de venta. La unidad base es el producto mismo (factor 1, su
 * codigo_barras): acá van solo las demás.
 *
 * - Habilitación por empresa: conf__empresas.usa_presentaciones (Configuración > Empresas). prpr_empresa_usa().
 * - Etapa 1 (2026-11-12): alta y edición en el ABM de productos de gestion y gestion_normal, que las graba
 *   junto con el producto (prpr_validar ANTES de guardar el producto, prpr_guardar después).
 * - Etapa 2 (pendiente): elegir la presentación en compras / ventas y convertir a la unidad base para el stock.
 *
 * Las presentaciones no se borran: la que el usuario quita pasa a estado 2 (los comprobantes de la etapa 2
 * van a apuntar a presentacion_id). Código de barras único por empresa entre productos activos y
 * presentaciones activas.
 *
 * Script: _local/sql/mantenimiento/20261112_productos_presentaciones_01.sql. Sin la tabla o la columna,
 * prpr_empresa_usa() devuelve false y nada cambia.
 */

const PRPR_MAX_POR_PRODUCTO = 30;

if (!function_exists('prpr_disponible')) {
    /** ¿Existe la tabla de presentaciones? (cacheado por request) */
    function prpr_disponible(mysqli $conexion): bool
    {
        static $existe = null;
        if ($existe === null) {
            $res = mysqli_query($conexion, "SHOW TABLES LIKE 'gestion__productos_presentaciones'");
            $existe = $res && mysqli_num_rows($res) > 0;
        }
        return $existe;
    }
}

if (!function_exists('prpr_empresa_usa')) {
    /** ¿La empresa trabaja con presentaciones? (columna conf__empresas.usa_presentaciones + tabla) */
    function prpr_empresa_usa(mysqli $conexion, int $empresa_id): bool
    {
        static $cache = [];
        if (isset($cache[$empresa_id])) {
            return $cache[$empresa_id];
        }
        $usa = false;
        if ($empresa_id > 0 && prpr_disponible($conexion)) {
            $col = mysqli_query($conexion, "SHOW COLUMNS FROM conf__empresas LIKE 'usa_presentaciones'");
            if ($col && mysqli_num_rows($col) > 0) {
                $stmt = mysqli_prepare($conexion, "SELECT usa_presentaciones FROM conf__empresas WHERE empresa_id = ?");
                mysqli_stmt_bind_param($stmt, "i", $empresa_id);
                mysqli_stmt_execute($stmt);
                $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
                mysqli_stmt_close($stmt);
                $usa = intval($row['usa_presentaciones'] ?? 0) === 1;
            }
        }
        return $cache[$empresa_id] = $usa;
    }
}

if (!function_exists('prpr_columnas_venta')) {
    /** ¿Tiene la tabla de presentaciones venta_minimo / venta_multiplo? (cacheado por request) */
    function prpr_columnas_venta(mysqli $conexion): bool
    {
        static $existe = null;
        if ($existe === null) {
            $existe = false;
            if (prpr_disponible($conexion)) {
                $res = mysqli_query($conexion, "SHOW COLUMNS FROM gestion__productos_presentaciones WHERE Field IN ('venta_minimo', 'venta_multiplo')");
                $existe = $res && mysqli_num_rows($res) === 2;
            }
        }
        return $existe;
    }
}

if (!function_exists('prpr_num_restriccion')) {
    /** Mínimo / múltiplo de venta: vacío o 0 = sin restricción (null); si no, número > 0. */
    function prpr_num_restriccion($valor, string $campo, string $fila): ?float
    {
        $n = prpr_num($valor, $campo, $fila);
        if ($n === null || $n == 0.0) {
            return null;
        }
        if ($n >= 100000000000000) {
            throw new InvalidArgumentException("$fila: $campo es demasiado grande.");
        }
        return round($n, 4);
    }
}

if (!function_exists('prpr_listar')) {
    /** Presentaciones activas del producto, en orden. */
    function prpr_listar(mysqli $conexion, int $empresa_id, int $producto_id): array
    {
        if ($producto_id <= 0 || !prpr_disponible($conexion)) {
            return [];
        }
        // Mínimo y múltiplo de venta solo si existen las columnas (script 20261114_venta_presentaciones_01.sql)
        $venta = prpr_columnas_venta($conexion) ? ', venta_minimo, venta_multiplo' : '';
        $stmt = mysqli_prepare($conexion, "SELECT presentacion_id, presentacion_nombre, factor, codigo_barras, peso_bruto_kg,
                                                  largo_cm, ancho_cm, alto_cm, aplica_compra, aplica_venta,
                                                  es_compra_defecto, es_venta_defecto, orden $venta
                                           FROM gestion__productos_presentaciones
                                           WHERE empresa_id = ? AND producto_id = ? AND tabla_estado_registro_id = 1
                                           ORDER BY orden ASC, factor ASC, presentacion_id ASC");
        mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $producto_id);
        mysqli_stmt_execute($stmt);
        $filas = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
        mysqli_stmt_close($stmt);
        return $filas;
    }
}

if (!function_exists('prpr_sql_busqueda_barras')) {
    /**
     * Condición SQL "alguna presentación activa del producto tiene un código de barras LIKE ?" para el alias
     * dado de gestion__productos. Lleva un parámetro (string). Devuelve '' si la tabla no existe.
     */
    function prpr_sql_busqueda_barras(mysqli $conexion, string $alias = 'p'): string
    {
        if (!prpr_disponible($conexion)) {
            return '';
        }
        $a = preg_replace('/[^A-Za-z0-9_]/', '', $alias);
        return "EXISTS (SELECT 1 FROM gestion__productos_presentaciones prb
                        WHERE prb.empresa_id = $a.empresa_id AND prb.producto_id = $a.producto_id
                          AND prb.tabla_estado_registro_id = 1 AND prb.codigo_barras LIKE ?)";
    }
}

if (!function_exists('prpr_num')) {
    /** Número opcional >= 0 (acepta coma decimal). null si viene vacío; lanza si no es válido. */
    function prpr_num($valor, string $campo, string $fila, bool $obligatorio = false): ?float
    {
        $txt = trim(str_replace(',', '.', (string) ($valor ?? '')));
        if ($txt === '') {
            if ($obligatorio) {
                throw new InvalidArgumentException("$fila: falta $campo.");
            }
            return null;
        }
        if (!is_numeric($txt) || (float) $txt < 0) {
            throw new InvalidArgumentException("$fila: $campo tiene que ser un número mayor o igual a cero.");
        }
        return (float) $txt;
    }
}

if (!function_exists('prpr_validar')) {
    /**
     * Normaliza y valida las presentaciones que vienen del formulario. Se llama ANTES de guardar el producto,
     * así un error no deja el producto grabado sin sus presentaciones.
     *
     * @param array  $filas           [{presentacion_id?, presentacion_nombre, factor, codigo_barras, peso_bruto_kg,
     *                                  largo_cm, ancho_cm, alto_cm, aplica_compra, aplica_venta,
     *                                  es_compra_defecto, es_venta_defecto}]
     * @param int    $producto_id     0 en un alta
     * @param string $barras_producto código de barras de la unidad base (el del producto, como viene en el formulario)
     * @return array filas normalizadas
     * @throws InvalidArgumentException con un mensaje para el usuario
     */
    function prpr_validar(mysqli $conexion, int $empresa_id, int $producto_id, array $filas, string $barras_producto = ''): array
    {
        if (count($filas) > PRPR_MAX_POR_PRODUCTO) {
            throw new InvalidArgumentException('Un producto puede tener hasta ' . PRPR_MAX_POR_PRODUCTO . ' presentaciones.');
        }
        $barras_producto = trim($barras_producto);
        $nombres = [];
        $barras = [];
        $def_compra = 0;
        $def_venta = 0;
        $salida = [];

        foreach (array_values($filas) as $i => $f) {
            if (!is_array($f)) {
                throw new InvalidArgumentException('Las presentaciones llegaron con un formato inválido.');
            }
            $nombre = trim((string) ($f['presentacion_nombre'] ?? ''));
            $fila = 'Presentación ' . ($nombre !== '' ? "\"$nombre\"" : '#' . ($i + 1));
            if ($nombre === '') {
                throw new InvalidArgumentException("$fila: falta el nombre.");
            }
            if (mb_strlen($nombre) > 60) {
                throw new InvalidArgumentException("$fila: el nombre admite hasta 60 caracteres.");
            }
            $clave = mb_strtolower($nombre);
            if (isset($nombres[$clave])) {
                throw new InvalidArgumentException("$fila: hay dos presentaciones con el mismo nombre.");
            }
            $nombres[$clave] = true;

            $factor = prpr_num($f['factor'] ?? null, 'cuántas unidades contiene', $fila, true);
            if ($factor <= 0) {
                throw new InvalidArgumentException("$fila: tiene que contener más de cero unidades.");
            }
            if ($factor >= 100000000000000) {
                throw new InvalidArgumentException("$fila: la cantidad de unidades es demasiado grande.");
            }

            $cb = trim((string) ($f['codigo_barras'] ?? ''));
            if (mb_strlen($cb) > 50) {
                throw new InvalidArgumentException("$fila: el código de barras admite hasta 50 caracteres.");
            }
            if ($cb !== '') {
                if ($barras_producto !== '' && $cb === $barras_producto) {
                    throw new InvalidArgumentException("$fila: el código de barras es el mismo de la unidad del producto.");
                }
                if (isset($barras[$cb])) {
                    throw new InvalidArgumentException("$fila: el código de barras $cb está repetido en otra presentación.");
                }
                $barras[$cb] = true;
            }

            $es_compra_def = !empty($f['es_compra_defecto']) ? 1 : 0;
            $es_venta_def = !empty($f['es_venta_defecto']) ? 1 : 0;
            $def_compra += $es_compra_def;
            $def_venta += $es_venta_def;

            // Venta por presentación (solo si el formulario los trae: prpr_procesar_formulario los quita si la
            // empresa no vende por presentación)
            $venta = [];
            if (array_key_exists('venta_minimo', $f) || array_key_exists('venta_multiplo', $f)) {
                $venta['venta_minimo'] = prpr_num_restriccion($f['venta_minimo'] ?? null, 'la cantidad mínima', $fila);
                $venta['venta_multiplo'] = prpr_num_restriccion($f['venta_multiplo'] ?? null, 'el múltiplo', $fila);
            }
            if (isset($f['precios'])) {
                if (!is_array($f['precios'])) {
                    throw new InvalidArgumentException("$fila: los precios llegaron con un formato inválido.");
                }
                $venta['precios'] = [];
                foreach ($f['precios'] as $lista_id => $precio) {
                    $lista_id = intval($lista_id);
                    if ($lista_id > 0) {
                        $venta['precios'][$lista_id] = prpr_num($precio, 'el precio', $fila);
                    }
                }
            }

            $salida[] = $venta + [
                'presentacion_id' => max(0, intval($f['presentacion_id'] ?? 0)),
                'presentacion_nombre' => $nombre,
                'factor' => round($factor, 4),
                'codigo_barras' => $cb !== '' ? $cb : null,
                'peso_bruto_kg' => prpr_num($f['peso_bruto_kg'] ?? null, 'el peso', $fila),
                'largo_cm' => prpr_num($f['largo_cm'] ?? null, 'el largo', $fila),
                'ancho_cm' => prpr_num($f['ancho_cm'] ?? null, 'el ancho', $fila),
                'alto_cm' => prpr_num($f['alto_cm'] ?? null, 'el alto', $fila),
                'aplica_compra' => isset($f['aplica_compra']) ? (!empty($f['aplica_compra']) ? 1 : 0) : 1,
                'aplica_venta' => isset($f['aplica_venta']) ? (!empty($f['aplica_venta']) ? 1 : 0) : 1,
                'es_compra_defecto' => $es_compra_def,
                'es_venta_defecto' => $es_venta_def,
                'orden' => $i + 1,
            ];
        }
        if ($def_compra > 1) {
            throw new InvalidArgumentException('Solo una presentación puede ser la habitual de compra.');
        }
        if ($def_venta > 1) {
            throw new InvalidArgumentException('Solo una presentación puede ser la habitual de venta.');
        }
        foreach ($salida as $s) {
            if ($s['es_compra_defecto'] && !$s['aplica_compra']) {
                throw new InvalidArgumentException("La presentación \"{$s['presentacion_nombre']}\" es la habitual de compra pero no se usa en compras.");
            }
            if ($s['es_venta_defecto'] && !$s['aplica_venta']) {
                throw new InvalidArgumentException("La presentación \"{$s['presentacion_nombre']}\" es la habitual de venta pero no se usa en ventas.");
            }
        }

        // Códigos de barras de otros productos o de presentaciones de otros productos (activos, de la empresa)
        if ($barras) {
            $lista = array_keys($barras);
            $marcas = implode(',', array_fill(0, count($lista), '?'));
            $sql = "SELECT 'P' AS origen, producto_codigo AS codigo, codigo_barras AS barras
                    FROM gestion__productos
                    WHERE empresa_id = ? AND producto_id <> ? AND tabla_estado_registro_id = 1 AND codigo_barras IN ($marcas)
                    UNION ALL
                    SELECT 'R', p.producto_codigo, pr.codigo_barras
                    FROM gestion__productos_presentaciones pr
                    JOIN gestion__productos p ON p.producto_id = pr.producto_id AND p.empresa_id = pr.empresa_id
                    WHERE pr.empresa_id = ? AND pr.producto_id <> ? AND pr.tabla_estado_registro_id = 1 AND pr.codigo_barras IN ($marcas)
                    LIMIT 1";
            $tipos = 'ii' . str_repeat('s', count($lista)) . 'ii' . str_repeat('s', count($lista));
            $params = array_merge([$empresa_id, $producto_id], $lista, [$empresa_id, $producto_id], $lista);
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param($stmt, $tipos, ...$params);
            mysqli_stmt_execute($stmt);
            $dup = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if ($dup) {
                $donde = $dup['origen'] === 'P' ? 'del producto' : 'de una presentación del producto';
                throw new InvalidArgumentException("El código de barras {$dup['barras']} ya es $donde {$dup['codigo']}.");
            }
        }
        return $salida;
    }
}

if (!function_exists('prpr_guardar')) {
    /**
     * Graba las presentaciones del producto (ya validadas con prpr_validar) en su propia transacción:
     * actualiza las que vienen con id del producto, da de alta las nuevas y pasa a estado 2 las activas que no
     * vinieron. No llamarla dentro de otra transacción.
     * @return array ['altas' => n, 'cambios' => n, 'bajas' => n]
     */
    function prpr_guardar(mysqli $conexion, int $empresa_id, int $usuario_id, int $producto_id, array $filas): array
    {
        if ($producto_id <= 0) {
            throw new InvalidArgumentException('Producto inválido para guardar presentaciones.');
        }
        $resumen = ['altas' => 0, 'cambios' => 0, 'bajas' => 0];
        mysqli_begin_transaction($conexion);
        try {
            // Las del producto, bloqueadas (dos ediciones a la vez no duplican altas)
            $stmt = mysqli_prepare($conexion, "SELECT presentacion_id FROM gestion__productos_presentaciones
                                               WHERE empresa_id = ? AND producto_id = ? AND tabla_estado_registro_id = 1
                                               FOR UPDATE");
            mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $producto_id);
            mysqli_stmt_execute($stmt);
            $actuales = array_map('intval', array_column(mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC), 'presentacion_id'));
            mysqli_stmt_close($stmt);
            $actuales = array_flip($actuales);

            $upd = mysqli_prepare($conexion, "UPDATE gestion__productos_presentaciones SET
                                                  presentacion_nombre = ?, factor = ?, codigo_barras = ?, peso_bruto_kg = ?,
                                                  largo_cm = ?, ancho_cm = ?, alto_cm = ?, aplica_compra = ?, aplica_venta = ?,
                                                  es_compra_defecto = ?, es_venta_defecto = ?, orden = ?, f_modificacion = NOW()
                                              WHERE presentacion_id = ? AND empresa_id = ? AND producto_id = ?");
            $ins = mysqli_prepare($conexion, "INSERT INTO gestion__productos_presentaciones
                                                  (empresa_id, producto_id, presentacion_nombre, factor, codigo_barras, peso_bruto_kg,
                                                   largo_cm, ancho_cm, alto_cm, aplica_compra, aplica_venta,
                                                   es_compra_defecto, es_venta_defecto, orden, tabla_estado_registro_id, usuario_id, f_alta)
                                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())");
            // Mínimo / múltiplo: aparte, solo con las columnas y si la fila los trae (sin eso no se tocan)
            $upd_venta = prpr_columnas_venta($conexion)
                ? mysqli_prepare($conexion, "UPDATE gestion__productos_presentaciones SET venta_minimo = ?, venta_multiplo = ?
                                             WHERE presentacion_id = ? AND empresa_id = ? AND producto_id = ?")
                : null;
            $resumen['ids'] = []; // orden de la fila => presentacion_id (para grabar después sus precios)
            $vistos = [];
            foreach ($filas as $f) {
                $id = intval($f['presentacion_id'] ?? 0);
                if ($id > 0 && isset($actuales[$id])) {
                    mysqli_stmt_bind_param($upd, "sdsddddiiiiiiii",
                        $f['presentacion_nombre'], $f['factor'], $f['codigo_barras'], $f['peso_bruto_kg'],
                        $f['largo_cm'], $f['ancho_cm'], $f['alto_cm'], $f['aplica_compra'], $f['aplica_venta'],
                        $f['es_compra_defecto'], $f['es_venta_defecto'], $f['orden'],
                        $id, $empresa_id, $producto_id);
                    mysqli_stmt_execute($upd);
                    $vistos[$id] = true;
                    $resumen['cambios']++;
                } else {
                    // Id ajeno, inactivo o nuevo: siempre alta (nunca se toca una fila de otro producto)
                    $uid = $usuario_id > 0 ? $usuario_id : null;
                    mysqli_stmt_bind_param($ins, "iisdsddddiiiiii",
                        $empresa_id, $producto_id, $f['presentacion_nombre'], $f['factor'], $f['codigo_barras'],
                        $f['peso_bruto_kg'], $f['largo_cm'], $f['ancho_cm'], $f['alto_cm'],
                        $f['aplica_compra'], $f['aplica_venta'], $f['es_compra_defecto'], $f['es_venta_defecto'],
                        $f['orden'], $uid);
                    mysqli_stmt_execute($ins);
                    $id = (int) mysqli_insert_id($conexion);
                    $resumen['altas']++;
                }
                $resumen['ids'][(int) $f['orden']] = $id;
                if ($upd_venta && array_key_exists('venta_minimo', $f)) {
                    $vmin = $f['venta_minimo'];
                    $vmul = $f['venta_multiplo'] ?? null;
                    mysqli_stmt_bind_param($upd_venta, "ddiii", $vmin, $vmul, $id, $empresa_id, $producto_id);
                    mysqli_stmt_execute($upd_venta);
                }
            }
            mysqli_stmt_close($upd);
            mysqli_stmt_close($ins);
            if ($upd_venta) {
                mysqli_stmt_close($upd_venta);
            }

            $bajas = array_diff(array_keys($actuales), array_keys($vistos));
            if ($bajas) {
                $baja = mysqli_prepare($conexion, "UPDATE gestion__productos_presentaciones
                                                   SET tabla_estado_registro_id = 2, es_compra_defecto = 0, es_venta_defecto = 0,
                                                       f_modificacion = NOW()
                                                   WHERE presentacion_id = ? AND empresa_id = ? AND producto_id = ?");
                foreach ($bajas as $id) {
                    mysqli_stmt_bind_param($baja, "iii", $id, $empresa_id, $producto_id);
                    mysqli_stmt_execute($baja);
                    $resumen['bajas']++;
                }
                mysqli_stmt_close($baja);
            }
            mysqli_commit($conexion);
            return $resumen;
        } catch (Throwable $e) {
            mysqli_rollback($conexion);
            throw $e;
        }
    }
}

if (!function_exists('prpr_procesar_formulario')) {
    /**
     * Paso 1 del guardado desde el ABM de productos: si la empresa usa presentaciones y el formulario las trae
     * (POST presentaciones = JSON), las valida. Devuelve null si no hay nada que guardar, o las filas listas.
     *
     * El JSON es la lista de filas o, con venta por presentación, {filas: [...], unidad: {venta_minimo,
     * venta_multiplo}} con mínimo / múltiplo y precios por lista ({lista_precio_id: precio}) en cada fila. Si la
     * empresa no vende por presentación, eso se ignora y se devuelve la lista de filas, como antes.
     * @throws InvalidArgumentException con el mensaje para el usuario (el producto NO se guarda)
     */
    function prpr_procesar_formulario(mysqli $conexion, int $empresa_id, int $producto_id, array $post): ?array
    {
        // Sin el campo (o vacío: la página se abrió sin la sección) no se tocan las presentaciones
        if (!isset($post['presentaciones']) || $post['presentaciones'] === '' || !prpr_empresa_usa($conexion, $empresa_id)) {
            return null;
        }
        $datos = json_decode((string) $post['presentaciones'], true);
        if (!is_array($datos)) {
            throw new InvalidArgumentException('Las presentaciones llegaron con un formato inválido.');
        }
        $con_venta = array_key_exists('filas', $datos);
        $filas = $con_venta ? $datos['filas'] : $datos;
        if (!is_array($filas)) {
            throw new InvalidArgumentException('Las presentaciones llegaron con un formato inválido.');
        }
        $venta = $con_venta && lpre_empresa_usa($conexion, $empresa_id);
        if (!$venta) {
            foreach ($filas as $i => $f) {
                if (is_array($f)) {
                    unset($filas[$i]['venta_minimo'], $filas[$i]['venta_multiplo'], $filas[$i]['precios']);
                }
            }
        }
        $validadas = prpr_validar($conexion, $empresa_id, $producto_id, $filas, (string) ($post['codigo_barras'] ?? ''));
        if (!$venta) {
            return $validadas;
        }

        // Unidad suelta (gestion__productos.venta_minimo / venta_multiplo)
        $unidad = null;
        if (isset($datos['unidad']) && is_array($datos['unidad'])) {
            $unidad = [
                'venta_minimo' => prpr_num_restriccion($datos['unidad']['venta_minimo'] ?? null, 'la cantidad mínima', 'Unidad'),
                'venta_multiplo' => prpr_num_restriccion($datos['unidad']['venta_multiplo'] ?? null, 'el múltiplo', 'Unidad'),
            ];
        }
        // Las listas de los precios tienen que ser de la empresa (se valida antes de guardar el producto)
        $listas = [];
        foreach ($validadas as $f) {
            foreach (array_keys($f['precios'] ?? []) as $lista_id) {
                $listas[$lista_id] = true;
            }
        }
        if ($listas) {
            $propias = array_column(lpre_listas_empresa($conexion, $empresa_id), 'lista_precio_id');
            foreach (array_keys($listas) as $lista_id) {
                if (!in_array($lista_id, $propias, true)) {
                    throw new InvalidArgumentException('Un precio es de una lista que no es de la empresa o está inactiva.');
                }
            }
        }
        return ['filas' => $validadas, 'unidad' => $unidad];
    }
}

if (!function_exists('prpr_guardar_formulario')) {
    /**
     * Paso 2: después de guardar el producto. Devuelve null si todo bien, o un aviso para el usuario
     * (el producto quedó guardado, las presentaciones no). Con venta por presentación graba además el mínimo /
     * múltiplo de la unidad y los precios por lista (lpre_guardar_precios, transacción propia).
     */
    function prpr_guardar_formulario(mysqli $conexion, int $empresa_id, int $usuario_id, int $producto_id, ?array $filas): ?string
    {
        if ($filas === null || $producto_id <= 0) {
            return null;
        }
        $venta = array_key_exists('filas', $filas);
        $lista_filas = $venta ? $filas['filas'] : $filas;
        try {
            $resumen = prpr_guardar($conexion, $empresa_id, $usuario_id, $producto_id, $lista_filas);
        } catch (Throwable $e) {
            error_log('prpr_guardar_formulario producto ' . $producto_id . ': ' . $e->getMessage());
            return 'El producto se guardó, pero no sus presentaciones. Volvé a guardar para reintentar.';
        }
        if (!$venta) {
            return null;
        }
        try {
            if ($filas['unidad'] !== null) {
                lpre_guardar_restriccion_unidad($conexion, $empresa_id, $producto_id,
                    $filas['unidad']['venta_minimo'], $filas['unidad']['venta_multiplo']);
            }
            $precios = [];
            foreach ($lista_filas as $f) {
                $id = $resumen['ids'][(int) $f['orden']] ?? 0;
                foreach (($f['precios'] ?? []) as $lista_id => $precio) {
                    if ($id > 0) {
                        $precios[] = ['lista_precio_id' => $lista_id, 'presentacion_id' => $id, 'precio' => $precio];
                    }
                }
            }
            if ($precios) {
                $r = lpre_guardar_precios($conexion, $empresa_id, $usuario_id, $precios);
                if (!$r['success']) {
                    error_log('prpr_guardar_formulario precios producto ' . $producto_id . ': ' . implode(' | ', $r['errores']));
                    return 'El producto y sus presentaciones se guardaron, pero no los precios por presentación. Volvé a guardar para reintentar.';
                }
            }
            return null;
        } catch (Throwable $e) {
            error_log('prpr_guardar_formulario venta producto ' . $producto_id . ': ' . $e->getMessage());
            return 'El producto y sus presentaciones se guardaron, pero no los precios ni las cantidades de venta. Volvé a guardar para reintentar.';
        }
    }
}

if (!function_exists('prpr_respuesta_presentaciones')) {
    /**
     * Respuesta de la acción obtener_presentaciones del ABM de productos: las presentaciones activas y, si la
     * empresa vende por presentación, 'venta' con las listas, los precios y el mínimo / múltiplo de la unidad.
     */
    function prpr_respuesta_presentaciones(mysqli $conexion, int $empresa_id, int $producto_id): array
    {
        $out = ['success' => true, 'data' => prpr_empresa_usa($conexion, $empresa_id) ? prpr_listar($conexion, $empresa_id, $producto_id) : []];
        if (lpre_empresa_usa($conexion, $empresa_id)) {
            $out['venta'] = lpre_datos_producto($conexion, $empresa_id, $producto_id);
        }
        return $out;
    }
}

// Venta por presentación (precios por lista, mínimo / múltiplo): sus funciones usan las de este archivo
require_once __DIR__ . '/listas_precios_presentaciones.php';
