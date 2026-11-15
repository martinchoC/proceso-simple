<?php
/**
 * Página "Escalas y promociones" (146 gestion / 646 gestion_normal, paso 4b del doc 22): ABM de reglas de precio de
 * venta con sus tramos, topes de descuento manual por perfil y vista previa con precio_resolver().
 * Vista: templates/precios_reglas_venta/pagina.php; JS: assets/js/precios-reglas-venta.js (global PrecioReglas).
 * Los precios_reglas_venta_ajax.php de cada módulo solo fijan módulo y página y llaman a prva_despachar().
 * Sin motor de estados: funciones consultar / agregar / editar / inhabilitar de la página (permisos_exigir_accion).
 */

require_once __DIR__ . '/precios_venta.php';
require_once __DIR__ . '/productos_marcas.php';

const PRVA_TIPOS = ['ESCALA', 'PROMOCION'];

if (!function_exists('prva_texto')) {
    function prva_texto($v, int $max): string
    {
        $t = trim((string) $v);
        return mb_substr($t, 0, $max);
    }
}

if (!function_exists('prva_id_opcional')) {
    function prva_id_opcional($v): ?int
    {
        $n = intval($v ?? 0);
        return $n > 0 ? $n : null;
    }
}

if (!function_exists('prva_pertenece')) {
    /** ¿El id es de la empresa en esa tabla? (tablas fijas, no del request) */
    function prva_pertenece(mysqli $conexion, string $tabla, string $pk, int $id, int $empresa_id): bool
    {
        $r = prv_filas($conexion, "SELECT 1 FROM `$tabla` WHERE `$pk` = ? AND empresa_id = ? LIMIT 1", 'ii', [$id, $empresa_id]);
        return (bool) $r;
    }
}

if (!function_exists('prva_marcas_activo')) {
    /** ¿La regla puede llevar marca comercial? (empresa con marcas y la columna en las reglas, doc 25) */
    function prva_marcas_activo(mysqli $conexion, int $empresa_id): bool
    {
        return pmar_empresa_usa($conexion, $empresa_id) && prv_existe($conexion, 'gestion__precios_reglas_venta', 'marca_comercial_id');
    }
}

if (!function_exists('prva_marca_procesar')) {
    /**
     * Marca del alcance de la regla, antes de guardar: null = no tocar (sin marcas o sin el campo en el request);
     * ['id' => int|null] = grabar ese valor. Con producto elegido, sin marca (el producto manda). Una marca inactiva
     * solo se acepta si ya era la de la regla.
     * @throws InvalidArgumentException
     */
    function prva_marca_procesar(mysqli $conexion, int $empresa_id, int $regla_id, array $d, ?int $producto): ?array
    {
        if (!array_key_exists('marca_comercial_id', $d) || !prva_marcas_activo($conexion, $empresa_id)) {
            return null;
        }
        $id = intval($d['marca_comercial_id']);
        if ($producto || $id <= 0) {
            return ['id' => null];
        }
        if (!pmar_valida($conexion, $empresa_id, $id)) {
            $actual = $regla_id > 0 ? prv_filas($conexion, "SELECT marca_comercial_id FROM gestion__precios_reglas_venta WHERE regla_venta_id = ? AND empresa_id = ?", 'ii', [$regla_id, $empresa_id]) : [];
            if ((int) ($actual[0]['marca_comercial_id'] ?? 0) !== $id || !prva_pertenece($conexion, 'gestion__productos_marcas', 'marca_comercial_id', $id, $empresa_id)) {
                throw new InvalidArgumentException('La marca no existe o está inactiva.');
            }
        }
        return ['id' => $id];
    }
}

if (!function_exists('prva_reglas_listar')) {
    /** Reglas de la empresa con sus textos de alcance y tramos. $filtros: tipo, estado (1/2/0 = todas), q, vigentes (0/1). */
    function prva_reglas_listar(mysqli $conexion, int $empresa_id, array $filtros = []): array
    {
        $where = ['r.empresa_id = ?'];
        $tipos = 'i';
        $params = [$empresa_id];
        if (in_array($filtros['tipo'] ?? '', PRVA_TIPOS, true)) {
            $where[] = 'r.tipo = ?'; $tipos .= 's'; $params[] = $filtros['tipo'];
        }
        $estado = intval($filtros['estado'] ?? 1);
        if ($estado > 0) {
            $where[] = 'r.tabla_estado_registro_id = ?'; $tipos .= 'i'; $params[] = $estado;
        }
        if (!empty($filtros['vigentes'])) {
            $where[] = 'r.f_desde <= CURDATE() AND (r.f_hasta IS NULL OR r.f_hasta >= CURDATE())';
        }
        $q = trim((string) ($filtros['q'] ?? ''));
        if ($q !== '') {
            $where[] = '(r.nombre LIKE ? OR p.producto_nombre LIKE ? OR p.producto_codigo LIKE ?)';
            $tipos .= 'sss'; $like = "%$q%"; array_push($params, $like, $like, $like);
        }
        $pres = prv_existe($conexion, 'gestion__productos_presentaciones');
        $sql = "SELECT r.*, p.producto_codigo, p.producto_nombre, pc.producto_categoria_nombre, lp.lista_precio_nombre,
                       cv.nombre AS canal_nombre, s.sucursal_nombre" . ($pres ? ", pp.presentacion_nombre" : ", NULL AS presentacion_nombre") . "
                  FROM gestion__precios_reglas_venta r
                  LEFT JOIN gestion__productos p ON p.producto_id = r.producto_id AND p.empresa_id = r.empresa_id
                  LEFT JOIN gestion__productos_categorias pc ON pc.producto_categoria_id = r.producto_categoria_id AND pc.empresa_id = r.empresa_id
                  LEFT JOIN gestion__listas_precios lp ON lp.lista_precio_id = r.lista_precio_id AND lp.empresa_id = r.empresa_id
                  LEFT JOIN gestion__canales_venta cv ON cv.canal_venta_id = r.canal_venta_id AND cv.empresa_id = r.empresa_id
                  LEFT JOIN gestion__sucursales s ON s.sucursal_id = r.sucursal_id AND s.empresa_id = r.empresa_id"
                . ($pres ? " LEFT JOIN gestion__productos_presentaciones pp ON pp.presentacion_id = r.presentacion_id AND pp.empresa_id = r.empresa_id" : "") . "
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY r.tabla_estado_registro_id, r.tipo, r.nombre";
        if (!prv_existe($conexion, 'gestion__canales_venta')) {
            $sql = str_replace(["cv.nombre AS canal_nombre", "LEFT JOIN gestion__canales_venta cv ON cv.canal_venta_id = r.canal_venta_id AND cv.empresa_id = r.empresa_id"],
                               ["NULL AS canal_nombre", ""], $sql);
        }
        $reglas = prv_filas($conexion, $sql, $tipos, $params);
        if (!$reglas) {
            return [];
        }
        $ids = array_map('intval', array_column($reglas, 'regla_venta_id'));
        $tramos = [];
        foreach (prv_filas($conexion, "SELECT regla_venta_id, cantidad_desde, descuento_pct FROM gestion__precios_reglas_venta_tramos WHERE regla_venta_id IN ("
                . implode(',', array_fill(0, count($ids), '?')) . ") ORDER BY cantidad_desde", str_repeat('i', count($ids)), $ids) as $t) {
            $tramos[(int) $t['regla_venta_id']][] = ['cantidad_desde' => (float) $t['cantidad_desde'], 'descuento_pct' => (float) $t['descuento_pct']];
        }
        // Nombre de la marca del alcance (doc 25), si la columna existe y alguna regla la tiene
        $marca_nombre = [];
        $marca_ids = array_values(array_unique(array_filter(array_map('intval', array_column($reglas, 'marca_comercial_id')))));
        if ($marca_ids && pmar_estructura($conexion)) {
            foreach (prv_filas($conexion, "SELECT marca_comercial_id, nombre FROM gestion__productos_marcas WHERE empresa_id = ? AND marca_comercial_id IN ("
                    . implode(',', array_fill(0, count($marca_ids), '?')) . ")", 'i' . str_repeat('i', count($marca_ids)), array_merge([$empresa_id], $marca_ids)) as $m) {
                $marca_nombre[(int) $m['marca_comercial_id']] = $m['nombre'];
            }
        }
        foreach ($reglas as &$r) {
            $r['tramos'] = $tramos[(int) $r['regla_venta_id']] ?? [];
            $alc = [];
            $marca = !$r['producto_id'] && !empty($r['marca_comercial_id']) ? 'Marca ' . ($marca_nombre[(int) $r['marca_comercial_id']] ?? $r['marca_comercial_id']) : '';
            $alc[] = $r['producto_id'] ? trim($r['producto_codigo'] . ' ' . $r['producto_nombre']) : ($r['producto_categoria_id'] ? 'Categoría ' . $r['producto_categoria_nombre'] : ($marca !== '' ? $marca : 'Todos los productos'));
            if ($marca !== '' && $r['producto_categoria_id']) $alc[] = $marca;
            if ($r['presentacion_id']) $alc[] = (string) ($r['presentacion_nombre'] ?: 'presentación ' . $r['presentacion_id']);
            if ($r['lista_precio_id']) $alc[] = 'Lista ' . $r['lista_precio_nombre'];
            if ($r['canal_venta_id']) $alc[] = 'Canal ' . ($r['canal_nombre'] ?: $r['canal_venta_id']);
            if ($r['sucursal_id']) $alc[] = 'Sucursal ' . $r['sucursal_nombre'];
            $r['alcance_texto'] = implode(' · ', $alc);
        }
        unset($r);
        return $reglas;
    }
}

if (!function_exists('prva_regla_obtener')) {
    function prva_regla_obtener(mysqli $conexion, int $empresa_id, int $id): ?array
    {
        foreach (prva_reglas_listar($conexion, $empresa_id, ['estado' => 0]) as $r) {
            if ((int) $r['regla_venta_id'] === $id) {
                return $r;
            }
        }
        return null;
    }
}

if (!function_exists('prva_regla_guardar')) {
    /**
     * Alta (regla_venta_id 0) o edición. $d: tipo, nombre, prioridad, producto_id, presentacion_id,
     * producto_categoria_id, lista_precio_id, canal_venta_id, sucursal_id, descuento_pct, f_desde, f_hasta,
     * observaciones, tramos [[cantidad_desde, descuento_pct]...], marca_comercial_id (opcional, doc 25). Los tramos se
     * reemplazan enteros.
     * @throws InvalidArgumentException con el mensaje para el usuario
     */
    function prva_regla_guardar(mysqli $conexion, int $empresa_id, int $usuario_id, array $d): int
    {
        $id = intval($d['regla_venta_id'] ?? 0);
        $tipo = strtoupper(trim((string) ($d['tipo'] ?? '')));
        if (!in_array($tipo, PRVA_TIPOS, true)) throw new InvalidArgumentException('Elegí si es una escala por cantidad o una promoción.');
        $nombre = prva_texto($d['nombre'] ?? '', 100);
        if ($nombre === '') throw new InvalidArgumentException('Poné un nombre.');
        $f_desde = (string) ($d['f_desde'] ?? '');
        $f_hasta = trim((string) ($d['f_hasta'] ?? ''));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_desde)) throw new InvalidArgumentException('Poné la fecha desde.');
        if ($f_hasta !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_hasta) || $f_hasta < $f_desde)) throw new InvalidArgumentException('La fecha hasta tiene que ser posterior a la fecha desde.');
        $f_hasta = $f_hasta === '' ? null : $f_hasta;

        $producto = prva_id_opcional($d['producto_id'] ?? null);
        $pres = prva_id_opcional($d['presentacion_id'] ?? null);
        $categoria = prva_id_opcional($d['producto_categoria_id'] ?? null);
        $lista = prva_id_opcional($d['lista_precio_id'] ?? null);
        $canal = prva_id_opcional($d['canal_venta_id'] ?? null);
        $sucursal = prva_id_opcional($d['sucursal_id'] ?? null);
        if ($producto && !prva_pertenece($conexion, 'gestion__productos', 'producto_id', $producto, $empresa_id)) throw new InvalidArgumentException('El producto no existe.');
        if ($pres) {
            if (!$producto) throw new InvalidArgumentException('Para elegir una presentación, elegí antes el producto.');
            $ok = prv_existe($conexion, 'gestion__productos_presentaciones') && prv_filas($conexion,
                "SELECT 1 FROM gestion__productos_presentaciones WHERE presentacion_id = ? AND producto_id = ? AND empresa_id = ?", 'iii', [$pres, $producto, $empresa_id]);
            if (!$ok) throw new InvalidArgumentException('La presentación no es de ese producto.');
        }
        if ($producto) $categoria = null; // el producto manda
        if ($categoria && !prva_pertenece($conexion, 'gestion__productos_categorias', 'producto_categoria_id', $categoria, $empresa_id)) throw new InvalidArgumentException('La categoría no existe.');
        if ($lista && !prva_pertenece($conexion, 'gestion__listas_precios', 'lista_precio_id', $lista, $empresa_id)) throw new InvalidArgumentException('La lista no existe.');
        if ($canal && !(prv_existe($conexion, 'gestion__canales_venta') && prva_pertenece($conexion, 'gestion__canales_venta', 'canal_venta_id', $canal, $empresa_id))) throw new InvalidArgumentException('El canal no existe.');
        if ($sucursal && !prva_pertenece($conexion, 'gestion__sucursales', 'sucursal_id', $sucursal, $empresa_id)) throw new InvalidArgumentException('La sucursal no existe.');

        $pct = null;
        $tramos = [];
        if ($tipo === 'PROMOCION') {
            $pct = round((float) ($d['descuento_pct'] ?? 0), 2);
            if ($pct <= 0 || $pct > 100) throw new InvalidArgumentException('El descuento de la promoción va entre 0 y 100 %.');
        } else {
            $vistos = [];
            foreach ((array) ($d['tramos'] ?? []) as $t) {
                $desde = round((float) ($t['cantidad_desde'] ?? 0), 4);
                $tp = round((float) ($t['descuento_pct'] ?? 0), 2);
                if ($desde <= 0 && $tp <= 0) continue; // fila vacía
                if ($desde <= 0) throw new InvalidArgumentException('Cada tramo necesita una cantidad desde mayor que 0.');
                if ($tp <= 0 || $tp > 100) throw new InvalidArgumentException('El descuento de cada tramo va entre 0 y 100 %.');
                if (isset($vistos[(string) $desde])) throw new InvalidArgumentException('Hay dos tramos con la misma cantidad.');
                $vistos[(string) $desde] = true;
                $tramos[] = [$desde, $tp];
            }
            if (!$tramos) throw new InvalidArgumentException('La escala necesita al menos un tramo.');
            usort($tramos, fn($a, $b) => $a[0] <=> $b[0]);
        }
        $prioridad = max(0, min(65535, intval($d['prioridad'] ?? 0)));
        $obs = prva_texto($d['observaciones'] ?? '', 255);
        $obs = $obs === '' ? null : $obs;

        if ($id > 0 && !prv_filas($conexion, "SELECT 1 FROM gestion__precios_reglas_venta WHERE regla_venta_id = ? AND empresa_id = ?", 'ii', [$id, $empresa_id])) {
            throw new InvalidArgumentException('La regla no existe.');
        }
        // Marca comercial del alcance (doc 25): se valida antes y se graba aparte, sin tocar el INSERT / UPDATE
        $marca = prva_marca_procesar($conexion, $empresa_id, $id, $d, $producto);
        mysqli_begin_transaction($conexion);
        try {
            if ($id > 0) {
                $st = mysqli_prepare($conexion, "UPDATE gestion__precios_reglas_venta SET tipo = ?, nombre = ?, prioridad = ?, producto_id = ?, presentacion_id = ?,
                    producto_categoria_id = ?, lista_precio_id = ?, canal_venta_id = ?, sucursal_id = ?, descuento_pct = ?, f_desde = ?, f_hasta = ?,
                    observaciones = ?, actualizado_en = NOW() WHERE regla_venta_id = ? AND empresa_id = ?");
                mysqli_stmt_bind_param($st, "ssiiiiiiidsssii", $tipo, $nombre, $prioridad, $producto, $pres, $categoria, $lista, $canal, $sucursal,
                    $pct, $f_desde, $f_hasta, $obs, $id, $empresa_id);
            } else {
                $st = mysqli_prepare($conexion, "INSERT INTO gestion__precios_reglas_venta (empresa_id, tipo, nombre, prioridad, producto_id, presentacion_id,
                    producto_categoria_id, lista_precio_id, canal_venta_id, sucursal_id, descuento_pct, f_desde, f_hasta, observaciones, creado_por)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($st, "issiiiiiiidsssi", $empresa_id, $tipo, $nombre, $prioridad, $producto, $pres, $categoria, $lista, $canal,
                    $sucursal, $pct, $f_desde, $f_hasta, $obs, $usuario_id);
            }
            mysqli_stmt_execute($st);
            if ($id <= 0) $id = (int) mysqli_insert_id($conexion);
            mysqli_stmt_close($st);
            if ($marca !== null) {
                $st = mysqli_prepare($conexion, "UPDATE gestion__precios_reglas_venta SET marca_comercial_id = ? WHERE regla_venta_id = ? AND empresa_id = ?");
                mysqli_stmt_bind_param($st, "iii", $marca['id'], $id, $empresa_id);
                mysqli_stmt_execute($st);
                mysqli_stmt_close($st);
            }
            $st = mysqli_prepare($conexion, "DELETE FROM gestion__precios_reglas_venta_tramos WHERE regla_venta_id = ?");
            mysqli_stmt_bind_param($st, "i", $id);
            mysqli_stmt_execute($st);
            mysqli_stmt_close($st);
            if ($tramos) {
                $st = mysqli_prepare($conexion, "INSERT INTO gestion__precios_reglas_venta_tramos (regla_venta_id, cantidad_desde, descuento_pct) VALUES (?, ?, ?)");
                foreach ($tramos as [$desde, $tp]) {
                    mysqli_stmt_bind_param($st, "idd", $id, $desde, $tp);
                    mysqli_stmt_execute($st);
                }
                mysqli_stmt_close($st);
            }
            mysqli_commit($conexion);
        } catch (Throwable $e) {
            mysqli_rollback($conexion);
            throw $e;
        }
        return $id;
    }
}

if (!function_exists('prva_regla_estado')) {
    /** Activa (1) o inactiva (2) una regla. Nada se borra. */
    function prva_regla_estado(mysqli $conexion, int $empresa_id, int $id, int $estado): void
    {
        $estado = $estado === 1 ? 1 : 2;
        $st = mysqli_prepare($conexion, "UPDATE gestion__precios_reglas_venta SET tabla_estado_registro_id = ?, actualizado_en = NOW() WHERE regla_venta_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($st, "iii", $estado, $id, $empresa_id);
        mysqli_stmt_execute($st);
        $n = mysqli_stmt_affected_rows($st);
        mysqli_stmt_close($st);
        if ($n < 0) throw new InvalidArgumentException('La regla no existe.');
    }
}

if (!function_exists('prva_topes_listar')) {
    /** Perfiles del módulo en la empresa con su tope vigente (null = sin tope cargado = 0 %). */
    function prva_topes_listar(mysqli $conexion, int $empresa_id, int $modulo_id): array
    {
        return array_map(function ($r) {
            $r['empresa_perfil_id'] = (int) $r['empresa_perfil_id'];
            $r['tope_pct'] = $r['tope_pct'] === null ? null : (float) $r['tope_pct'];
            return $r;
        }, prv_filas($conexion,
            "SELECT ep.empresa_perfil_id, ep.empresa_perfil_nombre,
                    (SELECT t.tope_pct FROM gestion__ventas_descuentos_topes t
                      WHERE t.empresa_id = ep.empresa_id AND t.empresa_perfil_id = ep.empresa_perfil_id AND t.tabla_estado_registro_id = 1
                        AND t.f_desde <= CURDATE() AND (t.f_hasta IS NULL OR t.f_hasta >= CURDATE())
                      ORDER BY t.f_desde DESC, t.descuento_tope_id DESC LIMIT 1) AS tope_pct
               FROM conf__empresas_perfiles ep
              WHERE ep.empresa_id = ? AND ep.modulo_id = ? AND ep.tabla_estado_registro_id = 1
              ORDER BY ep.empresa_perfil_nombre", 'ii', [$empresa_id, $modulo_id]));
    }
}

if (!function_exists('prva_tope_guardar')) {
    /** Nuevo tope del perfil desde hoy (el anterior queda en estado 2, como historial). Vacío = quitar el tope (0 %). */
    function prva_tope_guardar(mysqli $conexion, int $empresa_id, int $usuario_id, int $empresa_perfil_id, $tope): void
    {
        if (!prva_pertenece($conexion, 'conf__empresas_perfiles', 'empresa_perfil_id', $empresa_perfil_id, $empresa_id)) {
            throw new InvalidArgumentException('El perfil no es de la empresa.');
        }
        $tope = ($tope === '' || $tope === null) ? null : round((float) $tope, 2);
        if ($tope !== null && ($tope < 0 || $tope > 100)) throw new InvalidArgumentException('El tope va entre 0 y 100 %.');
        mysqli_begin_transaction($conexion);
        try {
            $st = mysqli_prepare($conexion, "UPDATE gestion__ventas_descuentos_topes SET tabla_estado_registro_id = 2, actualizado_en = NOW()
                                             WHERE empresa_id = ? AND empresa_perfil_id = ? AND tabla_estado_registro_id = 1");
            mysqli_stmt_bind_param($st, "ii", $empresa_id, $empresa_perfil_id);
            mysqli_stmt_execute($st);
            mysqli_stmt_close($st);
            if ($tope !== null) {
                $st = mysqli_prepare($conexion, "INSERT INTO gestion__ventas_descuentos_topes (empresa_id, empresa_perfil_id, tope_pct, f_desde, creado_por) VALUES (?, ?, ?, CURDATE(), ?)");
                mysqli_stmt_bind_param($st, "iidi", $empresa_id, $empresa_perfil_id, $tope, $usuario_id);
                mysqli_stmt_execute($st);
                mysqli_stmt_close($st);
            }
            mysqli_commit($conexion);
        } catch (Throwable $e) {
            mysqli_rollback($conexion);
            throw $e;
        }
    }
}

if (!function_exists('prva_catalogos')) {
    /** Combos de la pantalla: categorías, listas (con su tope), canales y sucursales de la empresa. */
    function prva_catalogos(mysqli $conexion, int $empresa_id): array
    {
        $out = [
            'categorias' => prv_filas($conexion, "SELECT producto_categoria_id AS id, producto_categoria_nombre AS nombre FROM gestion__productos_categorias
                                                   WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY producto_categoria_nombre", 'i', [$empresa_id]),
            'listas' => prv_filas($conexion, "SELECT lista_precio_id AS id, lista_precio_nombre AS nombre, descuento_tope_pct FROM gestion__listas_precios
                                               WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY lista_precio_nombre", 'i', [$empresa_id]),
            'canales' => function_exists('cvta_canales') ? array_map(fn($c) => ['id' => $c['canal_venta_id'], 'nombre' => $c['nombre']], cvta_canales($conexion, $empresa_id)) : [],
            'sucursales' => prv_filas($conexion, "SELECT sucursal_id AS id, sucursal_nombre AS nombre FROM gestion__sucursales
                                                   WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY sucursal_nombre", 'i', [$empresa_id]),
        ];
        // Marcas comerciales (doc 25): solo con el indicador de marcas; la pantalla agrega el combo si viene la clave
        if (prva_marcas_activo($conexion, $empresa_id)) {
            $out['marcas'] = array_map(fn($m) => ['id' => $m['marca_comercial_id'], 'nombre' => $m['nombre']], pmar_marcas($conexion, $empresa_id));
        }
        return $out;
    }
}

if (!function_exists('prva_buscar_productos')) {
    /** Productos activos por código o nombre (máx. 20), con sus presentaciones de venta si hay. */
    function prva_buscar_productos(mysqli $conexion, int $empresa_id, string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) return [];
        $like = "%$q%";
        $prods = prv_filas($conexion, "SELECT producto_id, producto_codigo, producto_nombre FROM gestion__productos
                                        WHERE empresa_id = ? AND tabla_estado_registro_id = 1 AND (producto_codigo LIKE ? OR producto_nombre LIKE ?)
                                        ORDER BY producto_nombre LIMIT 20", 'iss', [$empresa_id, $like, $like]);
        if ($prods && prv_existe($conexion, 'gestion__productos_presentaciones')) {
            $ids = array_map('intval', array_column($prods, 'producto_id'));
            $pres = [];
            foreach (prv_filas($conexion, "SELECT presentacion_id, producto_id, presentacion_nombre, factor FROM gestion__productos_presentaciones
                                           WHERE empresa_id = ? AND tabla_estado_registro_id = 1 AND aplica_venta = 1 AND producto_id IN ("
                    . implode(',', array_fill(0, count($ids), '?')) . ") ORDER BY orden, presentacion_nombre", 'i' . str_repeat('i', count($ids)), array_merge([$empresa_id], $ids)) as $r) {
                $pres[(int) $r['producto_id']][] = $r;
            }
            foreach ($prods as &$p) $p['presentaciones'] = $pres[(int) $p['producto_id']] ?? [];
            unset($p);
        }
        return $prods;
    }
}

if (!function_exists('prva_despachar')) {
    /**
     * Despachador de precios_reglas_venta_ajax.php. $ctx = ajax_guard (usuario_id, empresa_id); $modulo_id para los
     * perfiles de la solapa de topes. El _ajax.php ya exigió la función de la página con permisos_exigir_accion.
     * Respuesta {success, data, message}.
     */
    function prva_despachar(mysqli $conexion, array $ctx, int $modulo_id, string $accion): array
    {
        $empresa_id = (int) $ctx['empresa_id'];
        $usuario_id = (int) $ctx['usuario_id'];
        if (!prv_estructura($conexion)) {
            return ['success' => false, 'data' => null, 'message' => 'Falta aplicar el script de reglas de precio de venta.'];
        }
        $ok = fn($data, $msg = '') => ['success' => true, 'data' => $data, 'message' => $msg];
        try {
            switch ($accion) {
                case 'listar':
                    return $ok(['reglas' => prva_reglas_listar($conexion, $empresa_id, $_REQUEST), 'activo' => prv_empresa_usa($conexion, $empresa_id)]);
                case 'obtener':
                    $r = prva_regla_obtener($conexion, $empresa_id, intval($_REQUEST['regla_venta_id'] ?? 0));
                    return $r ? $ok($r) : ['success' => false, 'data' => null, 'message' => 'La regla no existe.'];
                case 'catalogos':
                    return $ok(prva_catalogos($conexion, $empresa_id));
                case 'buscar_productos':
                    return $ok(prva_buscar_productos($conexion, $empresa_id, (string) ($_REQUEST['q'] ?? '')));
                case 'agregar':
                case 'editar':
                    $d = $_POST;
                    $d['tramos'] = json_decode((string) ($_POST['tramos'] ?? '[]'), true) ?: [];
                    if ($accion === 'agregar') $d['regla_venta_id'] = 0;
                    elseif (intval($d['regla_venta_id'] ?? 0) <= 0) return ['success' => false, 'data' => null, 'message' => 'Falta la regla.'];
                    return $ok(['regla_venta_id' => prva_regla_guardar($conexion, $empresa_id, $usuario_id, $d)], 'Guardado');
                case 'estado':
                    prva_regla_estado($conexion, $empresa_id, intval($_POST['regla_venta_id'] ?? 0), intval($_POST['estado'] ?? 2));
                    return $ok(null, 'Guardado');
                case 'topes_listar':
                    return $ok(prva_topes_listar($conexion, $empresa_id, $modulo_id));
                case 'tope_guardar':
                    prva_tope_guardar($conexion, $empresa_id, $usuario_id, intval($_POST['empresa_perfil_id'] ?? 0), $_POST['tope_pct'] ?? null);
                    return $ok(prva_topes_listar($conexion, $empresa_id, $modulo_id), 'Guardado');
                case 'lista_tope_guardar':
                    $lista = intval($_POST['lista_precio_id'] ?? 0);
                    if (!prva_pertenece($conexion, 'gestion__listas_precios', 'lista_precio_id', $lista, $empresa_id)) throw new InvalidArgumentException('La lista no existe.');
                    $tope = ($_POST['descuento_tope_pct'] ?? '') === '' ? null : round((float) $_POST['descuento_tope_pct'], 2);
                    if ($tope !== null && ($tope < 0 || $tope > 100)) throw new InvalidArgumentException('El tope va entre 0 y 100 %.');
                    $st = mysqli_prepare($conexion, "UPDATE gestion__listas_precios SET descuento_tope_pct = ? WHERE lista_precio_id = ? AND empresa_id = ?");
                    mysqli_stmt_bind_param($st, "dii", $tope, $lista, $empresa_id);
                    mysqli_stmt_execute($st);
                    mysqli_stmt_close($st);
                    return $ok(prva_catalogos($conexion, $empresa_id)['listas'], 'Guardado');
                case 'vista_previa':
                    $linea = ['producto_id' => intval($_REQUEST['producto_id'] ?? 0), 'presentacion_id' => intval($_REQUEST['presentacion_id'] ?? 0),
                              'cantidad' => (float) ($_REQUEST['cantidad'] ?? 0)];
                    if ($linea['producto_id'] <= 0) return ['success' => false, 'data' => null, 'message' => 'Elegí un producto.'];
                    $res = precio_resolver($conexion, $empresa_id, [
                        'entidad_id' => intval($_REQUEST['entidad_id'] ?? 0), 'lista_precio_id' => intval($_REQUEST['lista_precio_id'] ?? 0),
                        'sucursal_id' => intval($_REQUEST['sucursal_id'] ?? 0), 'fecha' => $_REQUEST['fecha'] ?? null, 'usuario_id' => $usuario_id,
                        'cliente_descuento_pct' => (($_REQUEST['cliente_descuento_pct'] ?? '') === '') ? null : (float) $_REQUEST['cliente_descuento_pct'],
                    ], ['x' => $linea]);
                    return $ok($res['x']);
            }
        } catch (InvalidArgumentException $e) {
            return ['success' => false, 'data' => null, 'message' => $e->getMessage()];
        }
        return ['success' => false, 'data' => null, 'message' => 'Acción desconocida.'];
    }
}
