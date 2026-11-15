<?php
/**
 * Canales de venta (paso 3 de la distribuidora). Diseño: _local/docs/gestion/21_canal_cliente_sucursal.md
 * (aprobado el 2026-11-15).
 *
 * - El canal es un dato de la LISTA (gestion__listas_precios.canal_venta_id); el cliente lo hereda de su lista
 *   vigente. Qué presentaciones compra cada canal ya lo decide la lista (precios por presentación y
 *   vende_unidad_base, docs 19 y 20).
 * - Cada sucursal puede restringir los canales que vende (gestion__sucursales_canales_venta). Sin filas activas
 *   vende todos; una lista sin canal se vende en todas.
 * - cvta_lista_cliente(): único criterio para elegir la lista del cliente (condición comercial → lista de la
 *   entidad → lista por defecto de la empresa; sin cliente, la lista Mostrador).
 *
 * Indicador: conf__empresas.usa_canales_venta (script 20261116_canales_venta_01.sql). Sin el script o con el
 * indicador apagado, cvta_empresa_usa() da false y los modelos no llaman a nada más de acá.
 */

const CVTA_LISTA_CODIGO_MOSTRADOR = 'MOST'; // la misma que VF_LISTA_PRECIO_CODIGO_MOSTRADOR (ventas_facturas_model.php)

if (!function_exists('cvta_estructura')) {
    /** ¿Están las dos tablas, la columna de la lista y el indicador? (cacheado por request) */
    function cvta_estructura(mysqli $conexion): bool
    {
        static $ok = null;
        if ($ok !== null) {
            return $ok;
        }
        $ok = true;
        foreach (['gestion__canales_venta', 'gestion__sucursales_canales_venta'] as $t) {
            $res = mysqli_query($conexion, "SHOW TABLES LIKE '$t'");
            if (!$res || mysqli_num_rows($res) === 0) {
                return $ok = false;
            }
        }
        foreach ([['gestion__listas_precios', 'canal_venta_id'], ['conf__empresas', 'usa_canales_venta']] as [$t, $c]) {
            $res = mysqli_query($conexion, "SHOW COLUMNS FROM `$t` LIKE '$c'");
            if (!$res || mysqli_num_rows($res) === 0) {
                return $ok = false;
            }
        }
        return $ok;
    }
}

if (!function_exists('cvta_empresa_usa')) {
    /** Indicador usa_canales_venta de la empresa. Sin la estructura: false (lectura tolerante). */
    function cvta_empresa_usa(mysqli $conexion, int $empresa_id): bool
    {
        static $cache = [];
        if (isset($cache[$empresa_id])) {
            return $cache[$empresa_id];
        }
        $usa = false;
        if ($empresa_id > 0 && cvta_estructura($conexion)) {
            $st = mysqli_prepare($conexion, "SELECT usa_canales_venta FROM conf__empresas WHERE empresa_id = ?");
            mysqli_stmt_bind_param($st, "i", $empresa_id);
            mysqli_stmt_execute($st);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
            mysqli_stmt_close($st);
            $usa = intval($row['usa_canales_venta'] ?? 0) === 1;
        }
        return $cache[$empresa_id] = $usa;
    }
}

if (!function_exists('cvta_canales')) {
    /** Canales de la empresa (activos, o todos con $todos), ordenados. [] sin la estructura. */
    function cvta_canales(mysqli $conexion, int $empresa_id, bool $todos = false): array
    {
        if (!cvta_estructura($conexion)) {
            return [];
        }
        $sql = "SELECT canal_venta_id, codigo, nombre, orden, tabla_estado_registro_id
                FROM gestion__canales_venta WHERE empresa_id = ?" . ($todos ? '' : " AND tabla_estado_registro_id = 1") . "
                ORDER BY orden, nombre";
        $st = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($st, "i", $empresa_id);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        $out = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $r['canal_venta_id'] = (int) $r['canal_venta_id'];
            $r['orden'] = (int) $r['orden'];
            $r['tabla_estado_registro_id'] = (int) $r['tabla_estado_registro_id'];
            $out[] = $r;
        }
        mysqli_stmt_close($st);
        return $out;
    }
}

if (!function_exists('cvta_guardar_canal')) {
    /**
     * Alta (canal_venta_id 0) o edición de un canal: codigo, nombre, orden, activo (0/1).
     * @return int canal_venta_id
     * @throws InvalidArgumentException con el mensaje para el usuario
     */
    function cvta_guardar_canal(mysqli $conexion, int $empresa_id, array $datos, int $usuario_id): int
    {
        if (!cvta_estructura($conexion)) {
            throw new InvalidArgumentException('Falta aplicar el script de canales de venta.');
        }
        $id = intval($datos['canal_venta_id'] ?? 0);
        $codigo = strtoupper(trim((string) ($datos['codigo'] ?? '')));
        $nombre = trim((string) ($datos['nombre'] ?? ''));
        $orden = max(0, intval($datos['orden'] ?? 0));
        $estado = intval($datos['activo'] ?? 1) === 0 ? 2 : 1;
        if ($nombre === '' || mb_strlen($nombre) > 60) {
            throw new InvalidArgumentException('Poné un nombre de hasta 60 letras.');
        }
        if ($codigo === '') {
            $codigo = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $nombre) ?: $nombre), 0, 20));
        }
        if (!preg_match('/^[A-Z0-9_]{1,20}$/', $codigo)) {
            throw new InvalidArgumentException('El código lleva hasta 20 letras, números o guion bajo.');
        }
        $st = mysqli_prepare($conexion, "SELECT canal_venta_id FROM gestion__canales_venta WHERE empresa_id = ? AND codigo = ? AND canal_venta_id <> ?");
        mysqli_stmt_bind_param($st, "isi", $empresa_id, $codigo, $id);
        mysqli_stmt_execute($st);
        $dup = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);
        if ($dup) {
            throw new InvalidArgumentException("Ya hay un canal con el código $codigo.");
        }
        if ($id > 0) {
            if (!cvta_canal($conexion, $empresa_id, $id, true)) {
                throw new InvalidArgumentException('El canal no existe.');
            }
            $st = mysqli_prepare($conexion, "UPDATE gestion__canales_venta SET codigo = ?, nombre = ?, orden = ?, tabla_estado_registro_id = ?, actualizado_en = NOW()
                                             WHERE canal_venta_id = ? AND empresa_id = ?");
            mysqli_stmt_bind_param($st, "ssiiii", $codigo, $nombre, $orden, $estado, $id, $empresa_id);
            mysqli_stmt_execute($st);
            mysqli_stmt_close($st);
            return $id;
        }
        $st = mysqli_prepare($conexion, "INSERT INTO gestion__canales_venta (empresa_id, codigo, nombre, orden, tabla_estado_registro_id, creado_por) VALUES (?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($st, "issiii", $empresa_id, $codigo, $nombre, $orden, $estado, $usuario_id);
        mysqli_stmt_execute($st);
        $id = (int) mysqli_insert_id($conexion);
        mysqli_stmt_close($st);
        return $id;
    }
}

if (!function_exists('cvta_canal')) {
    /** Un canal de la empresa (activo, o en cualquier estado con $todos), o null. */
    function cvta_canal(mysqli $conexion, int $empresa_id, int $canal_venta_id, bool $todos = false): ?array
    {
        if ($canal_venta_id <= 0) {
            return null;
        }
        foreach (cvta_canales($conexion, $empresa_id, true) as $c) {
            if ($c['canal_venta_id'] === $canal_venta_id && ($todos || $c['tabla_estado_registro_id'] === 1)) {
                return $c;
            }
        }
        return null;
    }
}

if (!function_exists('cvta_canal_lista')) {
    /** Canal de una lista de la empresa (activo), o null si la lista no tiene. */
    function cvta_canal_lista(mysqli $conexion, int $empresa_id, int $lista_precio_id): ?array
    {
        if ($lista_precio_id <= 0 || !cvta_estructura($conexion)) {
            return null;
        }
        $st = mysqli_prepare($conexion, "SELECT c.canal_venta_id, c.codigo, c.nombre
                                         FROM gestion__listas_precios lp
                                         INNER JOIN gestion__canales_venta c ON c.canal_venta_id = lp.canal_venta_id
                                               AND c.empresa_id = lp.empresa_id AND c.tabla_estado_registro_id = 1
                                         WHERE lp.lista_precio_id = ? AND lp.empresa_id = ?");
        mysqli_stmt_bind_param($st, "ii", $lista_precio_id, $empresa_id);
        mysqli_stmt_execute($st);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);
        if (!$row) {
            return null;
        }
        $row['canal_venta_id'] = (int) $row['canal_venta_id'];
        return $row;
    }
}

if (!function_exists('cvta_guardar_canal_lista')) {
    /**
     * Asigna (o quita, con 0) el canal de una lista. UPDATE aparte de los de listas_precios (regla 8).
     * @throws InvalidArgumentException si el canal no es de la empresa o no está activo
     */
    function cvta_guardar_canal_lista(mysqli $conexion, int $empresa_id, int $lista_precio_id, int $canal_venta_id): void
    {
        if (!cvta_estructura($conexion) || $lista_precio_id <= 0) {
            return;
        }
        if ($canal_venta_id > 0 && !cvta_canal($conexion, $empresa_id, $canal_venta_id)) {
            // Conservar el canal que ya tiene aunque se haya desactivado (volver a guardar la lista no lo quita)
            $st = mysqli_prepare($conexion, "SELECT canal_venta_id FROM gestion__listas_precios WHERE lista_precio_id = ? AND empresa_id = ?");
            mysqli_stmt_bind_param($st, "ii", $lista_precio_id, $empresa_id);
            mysqli_stmt_execute($st);
            $actual = intval(mysqli_fetch_row(mysqli_stmt_get_result($st))[0] ?? 0);
            mysqli_stmt_close($st);
            if ($actual === $canal_venta_id && cvta_canal($conexion, $empresa_id, $canal_venta_id, true)) {
                return;
            }
            throw new InvalidArgumentException('El canal elegido no existe o está inactivo.');
        }
        $canal = $canal_venta_id > 0 ? $canal_venta_id : null;
        $st = mysqli_prepare($conexion, "UPDATE gestion__listas_precios SET canal_venta_id = ? WHERE lista_precio_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($st, "iii", $canal, $lista_precio_id, $empresa_id);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
    }
}

if (!function_exists('cvta_canales_sucursal')) {
    /** Ids de los canales que vende la sucursal ([] = todos: sin restricción). */
    function cvta_canales_sucursal(mysqli $conexion, int $empresa_id, int $sucursal_id): array
    {
        if ($sucursal_id <= 0 || !cvta_estructura($conexion)) {
            return [];
        }
        $st = mysqli_prepare($conexion, "SELECT sc.canal_venta_id
                                         FROM gestion__sucursales_canales_venta sc
                                         INNER JOIN gestion__canales_venta c ON c.canal_venta_id = sc.canal_venta_id AND c.empresa_id = sc.empresa_id
                                         WHERE sc.empresa_id = ? AND sc.sucursal_id = ? AND sc.tabla_estado_registro_id = 1
                                           AND c.tabla_estado_registro_id = 1
                                         ORDER BY c.orden, c.nombre");
        mysqli_stmt_bind_param($st, "ii", $empresa_id, $sucursal_id);
        mysqli_stmt_execute($st);
        $res = mysqli_stmt_get_result($st);
        $ids = [];
        while ($r = mysqli_fetch_assoc($res)) {
            $ids[] = (int) $r['canal_venta_id'];
        }
        mysqli_stmt_close($st);
        return $ids;
    }
}

if (!function_exists('cvta_guardar_canales_sucursal')) {
    /**
     * Deja exactamente esos canales en la sucursal ([] = vende todos). Los que salen pasan a estado 2 (historial);
     * los que vuelven se reactivan. Sin transacción propia: el que llama decide.
     * @throws InvalidArgumentException si un canal no es de la empresa o la sucursal no es de la empresa
     */
    function cvta_guardar_canales_sucursal(mysqli $conexion, int $empresa_id, int $sucursal_id, array $canales, int $usuario_id): void
    {
        if (!cvta_estructura($conexion)) {
            return;
        }
        $st = mysqli_prepare($conexion, "SELECT 1 FROM gestion__sucursales WHERE sucursal_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($st, "ii", $sucursal_id, $empresa_id);
        mysqli_stmt_execute($st);
        $es = mysqli_fetch_row(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);
        if (!$es) {
            throw new InvalidArgumentException('La sucursal no existe.');
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $canales))));
        foreach ($ids as $c) {
            if (!cvta_canal($conexion, $empresa_id, $c)) {
                throw new InvalidArgumentException('Uno de los canales no existe o está inactivo.');
            }
        }
        $st = mysqli_prepare($conexion, "UPDATE gestion__sucursales_canales_venta SET tabla_estado_registro_id = 2, actualizado_en = NOW()
                                         WHERE empresa_id = ? AND sucursal_id = ? AND tabla_estado_registro_id = 1");
        mysqli_stmt_bind_param($st, "ii", $empresa_id, $sucursal_id);
        mysqli_stmt_execute($st);
        mysqli_stmt_close($st);
        $st = mysqli_prepare($conexion, "INSERT INTO gestion__sucursales_canales_venta (empresa_id, sucursal_id, canal_venta_id, creado_por)
                                         VALUES (?, ?, ?, ?)
                                         ON DUPLICATE KEY UPDATE tabla_estado_registro_id = 1, actualizado_en = NOW()");
        foreach ($ids as $c) {
            mysqli_stmt_bind_param($st, "iiii", $empresa_id, $sucursal_id, $c, $usuario_id);
            mysqli_stmt_execute($st);
        }
        mysqli_stmt_close($st);
    }
}

if (!function_exists('cvta_lista_cliente')) {
    /**
     * Lista de precios del cliente con un único criterio (doc 21, decisión 4):
     *   1. condición comercial vigente (gestion__entidades_condiciones_clientes; f_desde DESC, id DESC)
     *   2. lista vigente de la entidad (gestion__entidades_listas_precios, la del carrito)
     *   3. lista por defecto de la empresa (gestion__empresas_parametros vigente)
     * Sin cliente (consumidor final): la lista Mostrador (código MOST).
     * Vigencias a $fecha (Y-m-d; null = hoy). No mira el indicador: el que llama decide si la usa.
     * @return array ['lista_precio_id' => int (0 = ninguna), 'origen' => condicion|entidad_lista|defecto|mostrador|ninguna,
     *                'canal_venta_id' => ?int, 'canal' => ?string]
     */
    function cvta_lista_cliente(mysqli $conexion, int $empresa_id, ?int $entidad_id, ?string $fecha = null): array
    {
        $fecha = ($fecha && preg_match('/^\d{4}-\d{2}-\d{2}/', $fecha)) ? substr($fecha, 0, 10) : date('Y-m-d');
        $entidad_id = intval($entidad_id ?? 0);
        $lista = 0;
        $origen = 'ninguna';
        $uno = function (string $sql, string $tipos, array $params) use ($conexion): int {
            $st = mysqli_prepare($conexion, $sql);
            if (!$st) {
                return 0;
            }
            mysqli_stmt_bind_param($st, $tipos, ...$params);
            mysqli_stmt_execute($st);
            $row = mysqli_fetch_row(mysqli_stmt_get_result($st));
            mysqli_stmt_close($st);
            return intval($row[0] ?? 0);
        };
        if ($entidad_id > 0) {
            $lista = $uno("SELECT ecc.lista_precio_id
                           FROM gestion__entidades_condiciones_clientes ecc
                           INNER JOIN gestion__entidades e ON e.entidad_id = ecc.entidad_id AND e.empresa_id = ?
                           WHERE ecc.entidad_id = ? AND ecc.tabla_estado_registro_id = 1 AND ecc.lista_precio_id IS NOT NULL
                             AND ecc.f_desde <= ? AND (ecc.f_hasta IS NULL OR ecc.f_hasta >= ?)
                           ORDER BY ecc.f_desde DESC, ecc.entidad_condicion_cliente_id DESC LIMIT 1",
                          "iiss", [$empresa_id, $entidad_id, $fecha, $fecha]);
            if ($lista > 0) {
                $origen = 'condicion';
            } else {
                $lista = $uno("SELECT lista_precio_id FROM gestion__entidades_listas_precios
                               WHERE empresa_id = ? AND entidad_id = ? AND tabla_estado_registro_id = 1
                                 AND f_desde <= ? AND (f_hasta IS NULL OR f_hasta >= ?)
                               ORDER BY f_desde DESC, entidad_lista_precio_id DESC LIMIT 1",
                              "iiss", [$empresa_id, $entidad_id, $fecha, $fecha]);
                if ($lista > 0) {
                    $origen = 'entidad_lista';
                } else {
                    $lista = $uno("SELECT lista_precio_defecto_id FROM gestion__empresas_parametros
                                   WHERE empresa_id = ? AND tabla_estado_registro_id = 1 AND lista_precio_defecto_id IS NOT NULL
                                     AND f_desde <= ? AND (f_hasta IS NULL OR f_hasta >= ?)
                                   ORDER BY f_desde DESC LIMIT 1",
                                  "iss", [$empresa_id, $fecha, $fecha]);
                    $origen = $lista > 0 ? 'defecto' : 'ninguna';
                }
            }
        } else {
            $codigo = CVTA_LISTA_CODIGO_MOSTRADOR;
            $lista = $uno("SELECT lista_precio_id FROM gestion__listas_precios
                           WHERE empresa_id = ? AND lista_precio_codigo = ? AND tabla_estado_registro_id = 1 LIMIT 1",
                          "is", [$empresa_id, $codigo]);
            $origen = $lista > 0 ? 'mostrador' : 'ninguna';
        }
        // La lista tiene que ser de la empresa y estar activa
        if ($lista > 0 && $uno("SELECT COUNT(*) FROM gestion__listas_precios WHERE lista_precio_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1",
                               "ii", [$lista, $empresa_id]) === 0) {
            $lista = 0;
            $origen = 'ninguna';
        }
        $canal = $lista > 0 ? cvta_canal_lista($conexion, $empresa_id, $lista) : null;
        return [
            'lista_precio_id' => $lista,
            'origen' => $origen,
            'canal_venta_id' => $canal ? $canal['canal_venta_id'] : null,
            'canal' => $canal ? $canal['nombre'] : null,
        ];
    }
}

if (!function_exists('cvta_validar_sucursal')) {
    /**
     * ¿La sucursal vende al canal de la lista? null = sí (sucursal sin restricción, lista sin canal o indicador
     * apagado); si no, el mensaje para el usuario.
     */
    function cvta_validar_sucursal(mysqli $conexion, int $empresa_id, int $sucursal_id, int $lista_precio_id): ?string
    {
        if (!cvta_empresa_usa($conexion, $empresa_id) || $sucursal_id <= 0) {
            return null;
        }
        $permitidos = cvta_canales_sucursal($conexion, $empresa_id, $sucursal_id);
        if (!$permitidos) {
            return null;
        }
        $canal = cvta_canal_lista($conexion, $empresa_id, $lista_precio_id);
        if (!$canal || in_array($canal['canal_venta_id'], $permitidos, true)) {
            return null;
        }
        $st = mysqli_prepare($conexion, "SELECT sucursal_nombre FROM gestion__sucursales WHERE sucursal_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($st, "ii", $sucursal_id, $empresa_id);
        mysqli_stmt_execute($st);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
        mysqli_stmt_close($st);
        $suc = trim((string) ($row['sucursal_nombre'] ?? '')) ?: 'elegida';
        return "La sucursal $suc no vende al canal {$canal['nombre']}.";
    }
}

if (!function_exists('cvta_despachar')) {
    /**
     * Acciones de la ventana "Canales de venta" (Listas de precios, gestion / gestion_normal). El _ajax.php del módulo
     * exige antes la función editar de la página. Respuesta {success, data, message}.
     *   canales_listar   todos los canales de la empresa (activos e inactivos)
     *   canal_guardar    alta / edición (POST canal_venta_id, codigo, nombre, orden, activo)
     */
    function cvta_despachar(mysqli $conexion, int $empresa_id, int $usuario_id, string $accion): array
    {
        if (!cvta_empresa_usa($conexion, $empresa_id)) {
            return ['success' => false, 'data' => null, 'message' => 'La empresa no usa canales de venta.'];
        }
        try {
            if ($accion === 'canal_guardar') {
                $id = cvta_guardar_canal($conexion, $empresa_id, [
                    'canal_venta_id' => $_POST['canal_venta_id'] ?? 0,
                    'codigo' => $_POST['codigo'] ?? '',
                    'nombre' => $_POST['nombre'] ?? '',
                    'orden' => $_POST['orden'] ?? 0,
                    'activo' => $_POST['activo'] ?? 1,
                ], $usuario_id);
                return ['success' => true, 'data' => ['canal_venta_id' => $id, 'canales' => cvta_canales($conexion, $empresa_id, true)], 'message' => 'Guardado'];
            }
            if ($accion === 'canales_listar') {
                return ['success' => true, 'data' => ['canales' => cvta_canales($conexion, $empresa_id, true)], 'message' => ''];
            }
        } catch (InvalidArgumentException $e) {
            return ['success' => false, 'data' => null, 'message' => $e->getMessage()];
        }
        return ['success' => false, 'data' => null, 'message' => 'Acción desconocida.'];
    }
}

if (!function_exists('cvta_completar_condicion')) {
    /**
     * Gancho de las funciones que devuelven la condición comercial del cliente (obtenerListaPrecioVigenteCliente de
     * pedidos, obtenerListaPrecioVigenteClienteRemitos, vfObtenerCondicionCliente): con usa_canales_venta, la lista
     * sale de cvta_lista_cliente() (condición → lista de la entidad → lista por defecto) y se agregan
     * lista_origen, canal_venta_id y canal. Sin el indicador devuelve $row tal cual.
     * Un cliente sin condición vigente devuelve una fila mínima con la lista (antes: null); el resto de los datos de la
     * condición (pago, descuento, límite) quedan en null, como en una condición sin esos datos.
     * $empresa_id 0: se toma la de la entidad.
     */
    function cvta_completar_condicion(mysqli $conexion, int $entidad_id, ?array $row, int $empresa_id = 0): ?array
    {
        if ($entidad_id <= 0 || !cvta_estructura($conexion)) {
            return $row;
        }
        if ($empresa_id <= 0) {
            $st = mysqli_prepare($conexion, "SELECT empresa_id FROM gestion__entidades WHERE entidad_id = ?");
            mysqli_stmt_bind_param($st, "i", $entidad_id);
            mysqli_stmt_execute($st);
            $empresa_id = intval(mysqli_fetch_row(mysqli_stmt_get_result($st))[0] ?? 0);
            mysqli_stmt_close($st);
        }
        if (!cvta_empresa_usa($conexion, $empresa_id)) {
            return $row;
        }
        $r = cvta_lista_cliente($conexion, $empresa_id, $entidad_id);
        if ($r['lista_precio_id'] <= 0) {
            return $row; // ni condición, ni lista de la entidad, ni lista por defecto: como hoy
        }
        if ($row === null) {
            $row = ['lista_precio_id' => null, 'condicion_pago_id' => null, 'cliente_descuento_general' => null, 'limite_credito' => null];
        }
        $row['lista_precio_id'] = $r['lista_precio_id'];
        $row['lista_origen'] = $r['origen'];
        $row['canal_venta_id'] = $r['canal_venta_id'];
        $row['canal'] = $r['canal'];
        if (array_key_exists('lista_precio_nombre', $row)) {
            $st = mysqli_prepare($conexion, "SELECT lista_precio_nombre FROM gestion__listas_precios WHERE lista_precio_id = ? AND empresa_id = ?");
            mysqli_stmt_bind_param($st, "ii", $r['lista_precio_id'], $empresa_id);
            mysqli_stmt_execute($st);
            $row['lista_precio_nombre'] = mysqli_fetch_row(mysqli_stmt_get_result($st))[0] ?? $row['lista_precio_nombre'];
            mysqli_stmt_close($st);
        }
        return $row;
    }
}

if (!function_exists('cvta_marcar_en_lista')) {
    /**
     * Buscadores de pedidos, remitos y facturas (doc 18, hallazgo 8): con usa_canales_venta agrega en_lista (bool) a
     * cada producto: false = no tiene precio vigente en la lista del cliente (la pantalla avisa en vez de cargar 0 en
     * silencio). Sin el indicador devuelve $productos tal cual.
     */
    function cvta_marcar_en_lista(mysqli $conexion, int $empresa_id, int $lista_precio_id, array $productos): array
    {
        if (!$productos || !cvta_empresa_usa($conexion, $empresa_id)) {
            return $productos;
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', array_column($productos, 'producto_id')))));
        $con_precio = [];
        if ($ids && $lista_precio_id > 0) {
            $ph = implode(',', array_fill(0, count($ids), '?'));
            $st = mysqli_prepare($conexion, "SELECT DISTINCT producto_id FROM gestion__listas_precios_productos
                                             WHERE lista_precio_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1
                                               AND f_desde <= CURDATE() AND (f_hasta IS NULL OR f_hasta >= CURDATE())
                                               AND producto_id IN ($ph)");
            $params = array_merge([$lista_precio_id, $empresa_id], $ids);
            mysqli_stmt_bind_param($st, str_repeat('i', count($params)), ...$params);
            mysqli_stmt_execute($st);
            $res = mysqli_stmt_get_result($st);
            while ($r = mysqli_fetch_row($res)) {
                $con_precio[(int) $r[0]] = true;
            }
            mysqli_stmt_close($st);
        }
        foreach ($productos as &$p) {
            $p['en_lista'] = isset($con_precio[(int) ($p['producto_id'] ?? 0)]);
        }
        unset($p);
        return $productos;
    }
}

if (!function_exists('cvta_exigir_venta_sucursal')) {
    /**
     * Al grabar un pedido, remito o factura de venta: la sucursal tiene que vender al canal de la lista del cliente
     * (o la de Mostrador, sin cliente). Sin usa_canales_venta no hace nada.
     * @throws InvalidArgumentException con el mensaje para el usuario
     */
    function cvta_exigir_venta_sucursal(mysqli $conexion, int $empresa_id, int $sucursal_id, ?int $entidad_id): void
    {
        if (!cvta_empresa_usa($conexion, $empresa_id) || $sucursal_id <= 0) {
            return;
        }
        $lista = cvta_lista_cliente($conexion, $empresa_id, $entidad_id ?: null)['lista_precio_id'];
        $msg = cvta_validar_sucursal($conexion, $empresa_id, $sucursal_id, $lista);
        if ($msg !== null) {
            throw new InvalidArgumentException($msg);
        }
    }
}
