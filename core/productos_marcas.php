<?php
/**
 * Marca comercial del producto (Arcor, Molto…), doc 25. No es gestion__marcas (marca del VEHÍCULO de la compatibilidad
 * de autopartes del módulo 2).
 *
 * - gestion__productos_marcas: marcas por empresa (nombre único entre las activas).
 * - gestion__productos.marca_comercial_id (NULL): se graba APARTE, después del alta / edición del producto
 *   (pmar_procesar_formulario antes, pmar_guardar_formulario después), sin tocar los INSERT / UPDATE de productos.
 * - Acciones marcas_* de productos_ajax.php (2 y 5): pmar_ajax_acciones() responde y corta.
 * - Búsqueda del listado: pmar_sql_busqueda() (EXISTS con un parámetro).
 *
 * Indicador conf__empresas.usa_marcas_comerciales. Sin él (o sin la estructura) nada lee ni escribe lo nuevo.
 */

if (!function_exists('pmar_existe')) {
    function pmar_existe(mysqli $conexion, string $tabla, string $columna = ''): bool
    {
        static $cache = [];
        $k = "$tabla.$columna";
        if (!isset($cache[$k])) {
            $t = preg_replace('/[^a-z0-9_]/i', '', $tabla);
            $res = $columna === '' ? mysqli_query($conexion, "SHOW TABLES LIKE '$t'")
                : mysqli_query($conexion, "SHOW COLUMNS FROM `$t` LIKE '" . preg_replace('/[^a-z0-9_]/i', '', $columna) . "'");
            $cache[$k] = $res && mysqli_num_rows($res) > 0;
        }
        return $cache[$k];
    }
}

if (!function_exists('pmar_estructura')) {
    function pmar_estructura(mysqli $conexion): bool
    {
        return pmar_existe($conexion, 'gestion__productos_marcas') && pmar_existe($conexion, 'gestion__productos', 'marca_comercial_id')
            && pmar_existe($conexion, 'conf__empresas', 'usa_marcas_comerciales');
    }
}

if (!function_exists('pmar_empresa_usa')) {
    /** Indicador usa_marcas_comerciales. Sin la estructura: false. */
    function pmar_empresa_usa(mysqli $conexion, int $empresa_id): bool
    {
        static $cache = [];
        if (isset($cache[$empresa_id])) {
            return $cache[$empresa_id];
        }
        $usa = false;
        if ($empresa_id > 0 && pmar_estructura($conexion)) {
            $r = pmar_filas($conexion, "SELECT usa_marcas_comerciales FROM conf__empresas WHERE empresa_id = ?", 'i', [$empresa_id]);
            $usa = intval($r[0]['usa_marcas_comerciales'] ?? 0) === 1;
        }
        return $cache[$empresa_id] = $usa;
    }
}

if (!function_exists('pmar_filas')) {
    function pmar_filas(mysqli $conexion, string $sql, string $tipos = '', array $params = []): array
    {
        $st = mysqli_prepare($conexion, $sql);
        if (!$st) {
            throw new RuntimeException('Error de consulta: ' . mysqli_error($conexion));
        }
        if ($tipos !== '') {
            mysqli_stmt_bind_param($st, $tipos, ...$params);
        }
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        $out = $res ? mysqli_fetch_all($res, MYSQLI_ASSOC) : [];
        mysqli_stmt_close($st);
        return $out;
    }
}

if (!function_exists('pmar_ejecutar')) {
    function pmar_ejecutar(mysqli $conexion, string $sql, string $tipos, array $params, bool $insert = false): int
    {
        $st = mysqli_prepare($conexion, $sql);
        if (!$st) {
            throw new RuntimeException('Error de consulta: ' . mysqli_error($conexion));
        }
        mysqli_stmt_bind_param($st, $tipos, ...$params);
        if (!mysqli_stmt_execute($st)) {
            $e = mysqli_stmt_error($st);
            mysqli_stmt_close($st);
            throw new RuntimeException('Error al grabar: ' . $e);
        }
        $n = $insert ? (int) mysqli_insert_id($conexion) : (int) mysqli_stmt_affected_rows($st);
        mysqli_stmt_close($st);
        return $n;
    }
}

// ----------------------------------------------------------------------------------------------- marcas

if (!function_exists('pmar_marcas')) {
    /** Marcas de la empresa con la cantidad de productos activos de cada una. */
    function pmar_marcas(mysqli $conexion, int $empresa_id, bool $incluir_inactivas = false): array
    {
        return pmar_filas($conexion,
            "SELECT m.marca_comercial_id, m.nombre, m.tabla_estado_registro_id,
                    (SELECT COUNT(*) FROM gestion__productos p WHERE p.empresa_id = m.empresa_id AND p.marca_comercial_id = m.marca_comercial_id) AS productos
               FROM gestion__productos_marcas m
              WHERE m.empresa_id = ?" . ($incluir_inactivas ? '' : ' AND m.tabla_estado_registro_id = 1') . "
              ORDER BY m.nombre", 'i', [$empresa_id]);
    }
}

if (!function_exists('pmar_guardar')) {
    /** Alta (id 0) o edición. Devuelve el id. @throws InvalidArgumentException */
    function pmar_guardar(mysqli $conexion, int $empresa_id, int $marca_id, string $nombre, int $usuario_id): int
    {
        $nombre = mb_substr(trim(preg_replace('/\s+/', ' ', $nombre)), 0, 100);
        if ($nombre === '') {
            throw new InvalidArgumentException('Falta el nombre de la marca.');
        }
        if (pmar_filas($conexion, "SELECT 1 FROM gestion__productos_marcas WHERE empresa_id = ? AND nombre = ? AND tabla_estado_registro_id = 1 AND marca_comercial_id <> ?",
                'isi', [$empresa_id, $nombre, $marca_id])) {
            throw new InvalidArgumentException('Ya hay una marca activa con ese nombre.');
        }
        if ($marca_id > 0) {
            if (!pmar_filas($conexion, "SELECT 1 FROM gestion__productos_marcas WHERE marca_comercial_id = ? AND empresa_id = ?", 'ii', [$marca_id, $empresa_id])) {
                throw new InvalidArgumentException('La marca no existe.');
            }
            pmar_ejecutar($conexion, "UPDATE gestion__productos_marcas SET nombre = ?, actualizado_en = NOW() WHERE marca_comercial_id = ? AND empresa_id = ?",
                'sii', [$nombre, $marca_id, $empresa_id]);
            return $marca_id;
        }
        return pmar_ejecutar($conexion, "INSERT INTO gestion__productos_marcas (empresa_id, nombre, creado_por) VALUES (?, ?, ?)",
            'isi', [$empresa_id, $nombre, $usuario_id], true);
    }
}

if (!function_exists('pmar_estado')) {
    /** Activa (1) o inactiva (2). Inactivarla no cambia la marca de sus productos (solo deja de ofrecerse). */
    function pmar_estado(mysqli $conexion, int $empresa_id, int $marca_id, int $estado): void
    {
        if (!in_array($estado, [1, 2], true)) {
            throw new InvalidArgumentException('Estado inválido.');
        }
        $m = pmar_filas($conexion, "SELECT nombre FROM gestion__productos_marcas WHERE marca_comercial_id = ? AND empresa_id = ?", 'ii', [$marca_id, $empresa_id]);
        if (!$m) {
            throw new InvalidArgumentException('La marca no existe.');
        }
        if ($estado === 1 && pmar_filas($conexion, "SELECT 1 FROM gestion__productos_marcas WHERE empresa_id = ? AND nombre = ? AND tabla_estado_registro_id = 1 AND marca_comercial_id <> ?",
                'isi', [$empresa_id, $m[0]['nombre'], $marca_id])) {
            throw new InvalidArgumentException('Ya hay otra marca activa con ese nombre.');
        }
        pmar_ejecutar($conexion, "UPDATE gestion__productos_marcas SET tabla_estado_registro_id = ?, actualizado_en = NOW() WHERE marca_comercial_id = ? AND empresa_id = ?",
            'iii', [$estado, $marca_id, $empresa_id]);
    }
}

if (!function_exists('pmar_valida')) {
    /** ¿La marca es de la empresa y está activa? */
    function pmar_valida(mysqli $conexion, int $empresa_id, int $marca_id): bool
    {
        return (bool) pmar_filas($conexion, "SELECT 1 FROM gestion__productos_marcas WHERE marca_comercial_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1",
            'ii', [$marca_id, $empresa_id]);
    }
}

// ----------------------------------------------------------------------------------------------- producto

if (!function_exists('pmar_procesar_formulario')) {
    /**
     * Antes de guardar el producto: null = no tocar (sin el indicador o sin el campo marca_comercial_id en el
     * request); ['marca_id' => int|null] = grabar ese valor (vacío = sin marca). Una marca inválida lanza.
     * Una marca inactiva solo se acepta si ya era la del producto.
     * @throws InvalidArgumentException
     */
    function pmar_procesar_formulario(mysqli $conexion, int $empresa_id, int $producto_id, array $req): ?array
    {
        if (!pmar_empresa_usa($conexion, $empresa_id) || !array_key_exists('marca_comercial_id', $req)) {
            return null;
        }
        $id = intval($req['marca_comercial_id']);
        if ($id <= 0) {
            return ['marca_id' => null];
        }
        if (!pmar_valida($conexion, $empresa_id, $id)) {
            $actual = $producto_id > 0 ? pmar_filas($conexion, "SELECT marca_comercial_id FROM gestion__productos WHERE producto_id = ? AND empresa_id = ?", 'ii', [$producto_id, $empresa_id]) : [];
            if ((int) ($actual[0]['marca_comercial_id'] ?? 0) !== $id || !pmar_filas($conexion, "SELECT 1 FROM gestion__productos_marcas WHERE marca_comercial_id = ? AND empresa_id = ?", 'ii', [$id, $empresa_id])) {
                throw new InvalidArgumentException('La marca elegida no existe o está inactiva.');
            }
        }
        return ['marca_id' => $id];
    }
}

if (!function_exists('pmar_procesar_o_responder')) {
    /** Para los _ajax.php: pmar_procesar_formulario, y ante un error responde JSON (los dos formatos) y corta. */
    function pmar_procesar_o_responder(mysqli $conexion, int $empresa_id, int $producto_id, array $req): ?array
    {
        try {
            return pmar_procesar_formulario($conexion, $empresa_id, $producto_id, $req);
        } catch (InvalidArgumentException $e) {
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'resultado' => false, 'error' => $e->getMessage(), 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
}

if (!function_exists('pmar_guardar_formulario')) {
    /** Después de guardar el producto: graba la marca procesada (null = no tocar). Devuelve un aviso o null. */
    function pmar_guardar_formulario(mysqli $conexion, int $empresa_id, int $producto_id, ?array $proc): ?string
    {
        if ($proc === null || $producto_id <= 0) {
            return null;
        }
        try {
            pmar_ejecutar($conexion, "UPDATE gestion__productos SET marca_comercial_id = ? WHERE producto_id = ? AND empresa_id = ?",
                'iii', [$proc['marca_id'], $producto_id, $empresa_id]);
            return null;
        } catch (Throwable $e) {
            error_log('pmar_guardar_formulario: ' . $e->getMessage());
            return 'El producto se guardó, pero no su marca.';
        }
    }
}

if (!function_exists('pmar_sql_busqueda')) {
    /** Condición para la búsqueda del listado: la marca del producto contiene el término (un parámetro). '' sin el indicador. */
    function pmar_sql_busqueda(mysqli $conexion, int $empresa_id, string $alias = 'p'): string
    {
        if (!pmar_empresa_usa($conexion, $empresa_id)) {
            return '';
        }
        $a = preg_replace('/[^A-Za-z0-9_]/', '', $alias);
        return "EXISTS (SELECT 1 FROM gestion__productos_marcas pmb WHERE pmb.marca_comercial_id = $a.marca_comercial_id AND pmb.nombre LIKE ?)";
    }
}

// ----------------------------------------------------------------------------------------------- asignación en masa

if (!function_exists('pmar_buscar_productos')) {
    /** Productos activos cuyo nombre o código contiene $q (máx. 500), con su marca actual. */
    function pmar_buscar_productos(mysqli $conexion, int $empresa_id, string $q): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            throw new InvalidArgumentException('Escribí al menos 2 letras para buscar.');
        }
        $like = '%' . $q . '%';
        return pmar_filas($conexion,
            "SELECT p.producto_id, p.producto_codigo, p.producto_nombre, m.nombre AS marca
               FROM gestion__productos p LEFT JOIN gestion__productos_marcas m ON m.marca_comercial_id = p.marca_comercial_id
              WHERE p.empresa_id = ? AND p.tabla_estado_registro_id = 1 AND (p.producto_nombre LIKE ? OR p.producto_codigo LIKE ?)
              ORDER BY p.producto_nombre LIMIT 500", 'iss', [$empresa_id, $like, $like]);
    }
}

if (!function_exists('pmar_asignar')) {
    /** Pone la marca a los productos dados (de la empresa). Devuelve cuántos cambiaron. */
    function pmar_asignar(mysqli $conexion, int $empresa_id, int $marca_id, array $producto_ids): int
    {
        if (!pmar_valida($conexion, $empresa_id, $marca_id)) {
            throw new InvalidArgumentException('La marca no existe o está inactiva.');
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $producto_ids))));
        if (!$ids) {
            throw new InvalidArgumentException('Elegí al menos un producto.');
        }
        return pmar_ejecutar($conexion,
            "UPDATE gestion__productos SET marca_comercial_id = ? WHERE empresa_id = ? AND producto_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")",
            'ii' . str_repeat('i', count($ids)), array_merge([$marca_id, $empresa_id], $ids));
    }
}

// ----------------------------------------------------------------------------------------------- acciones AJAX

if (!function_exists('pmar_ajax_acciones')) {
    /**
     * Acciones marcas_* de productos_ajax.php: si $accion es una de ellas, responde JSON y corta; si no, no hace nada.
     * Lectura con el acceso a la página que ya validó ajax_guard; escrituras con la función "editar" de la página.
     */
    function pmar_ajax_acciones(mysqli $conexion, array $ctx, string $accion, int $pagina_id): void
    {
        $acciones = ['marcas_listar', 'marcas_guardar', 'marcas_estado', 'marcas_buscar_productos', 'marcas_asignar'];
        if (!in_array($accion, $acciones, true)) {
            return;
        }
        $empresa_id = (int) $ctx['empresa_id'];
        $responder = function (array $r) {
            if (ob_get_length()) ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($r, JSON_UNESCAPED_UNICODE);
            exit;
        };
        if (!pmar_empresa_usa($conexion, $empresa_id)) {
            $responder(['success' => false, 'data' => null, 'message' => 'La empresa no usa marcas comerciales (Configuración > Empresas).']);
        }
        if ($accion !== 'marcas_listar' && $accion !== 'marcas_buscar_productos' && function_exists('permisos_exigir_funcion')) {
            permisos_exigir_funcion($conexion, $pagina_id, 'editar', true);
        }
        try {
            switch ($accion) {
                case 'marcas_listar':
                    $responder(['success' => true, 'data' => pmar_marcas($conexion, $empresa_id, !empty($_REQUEST['incluir_inactivas'])), 'message' => '']);
                case 'marcas_guardar':
                    $id = pmar_guardar($conexion, $empresa_id, intval($_POST['marca_comercial_id'] ?? 0), (string) ($_POST['nombre'] ?? ''), (int) $ctx['usuario_id']);
                    $responder(['success' => true, 'data' => ['marca_comercial_id' => $id], 'message' => 'Guardado']);
                case 'marcas_estado':
                    pmar_estado($conexion, $empresa_id, intval($_POST['marca_comercial_id'] ?? 0), intval($_POST['estado'] ?? 2));
                    $responder(['success' => true, 'data' => null, 'message' => 'Guardado']);
                case 'marcas_buscar_productos':
                    $responder(['success' => true, 'data' => pmar_buscar_productos($conexion, $empresa_id, (string) ($_REQUEST['q'] ?? '')), 'message' => '']);
                case 'marcas_asignar':
                    $ids = json_decode((string) ($_POST['productos'] ?? '[]'), true);
                    $n = pmar_asignar($conexion, $empresa_id, intval($_POST['marca_comercial_id'] ?? 0), is_array($ids) ? $ids : []);
                    $responder(['success' => true, 'data' => ['cambiados' => $n], 'message' => $n . ' producto(s) con la marca']);
            }
        } catch (InvalidArgumentException $e) {
            $responder(['success' => false, 'data' => null, 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            error_log('pmar_ajax_acciones ' . $accion . ': ' . $e->getMessage());
            $responder(['success' => false, 'data' => null, 'message' => 'No se pudo completar la operación.']);
        }
    }
}
