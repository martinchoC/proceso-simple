<?php
/**
 * Vendedores y carteras de clientes (paso 5a de la distribuidora). Diseño: _local/docs/gestion/23_vendedores_carteras.md
 *
 * - Vendedor = entidad con rol VEN (gestion__entidades_roles). El usuario que lo opera, en gestion__vendedores_usuarios.
 * - Cartera = gestion__vendedores_carteras: un vendedor por cliente a una fecha, con vigencia (nada se borra: se
 *   cierra con f_hasta; una fila cargada por error pasa a estado 2).
 * - Pedido: vendedor_entidad_id y origen (ERP / VENDEDOR / CARRITO / APP) al grabar.
 * - Permisos: un usuario ligado a un vendedor y sin la función "Ver todas las carteras" (accion_js
 *   ver_todas_carteras de la página vendedores.php) ve y carga solo pedidos de clientes de su cartera. Un usuario que
 *   no es vendedor no tiene restricción.
 *
 * Indicador conf__empresas.usa_vendedores. Sin él (o sin la estructura) todo devuelve "inactivo" / null / '' y
 * nada se lee ni se escribe en las tablas o columnas nuevas.
 */

require_once __DIR__ . '/entidades_roles.php';

const VCAR_ORIGENES = ['ERP', 'VENDEDOR', 'CARRITO', 'APP'];
const VCAR_PAGINA_URL = 'vendedores.php';
const VCAR_FUNCION_VER_TODAS = 'ver_todas_carteras';
const VCAR_FUNCION_ASIGNAR = 'asignar_vendedor';

if (!function_exists('vcar_existe')) {
    /** ¿Existe la tabla o, con $columna, la columna? (cacheado por request) */
    function vcar_existe(mysqli $conexion, string $tabla, string $columna = ''): bool
    {
        static $cache = [];
        $k = "$tabla.$columna";
        if (!isset($cache[$k])) {
            $t = preg_replace('/[^a-z0-9_]/i', '', $tabla);
            if ($columna === '') {
                $res = mysqli_query($conexion, "SHOW TABLES LIKE '$t'");
            } else {
                $c = preg_replace('/[^a-z0-9_]/i', '', $columna);
                $res = mysqli_query($conexion, "SHOW COLUMNS FROM `$t` LIKE '$c'");
            }
            $cache[$k] = $res && mysqli_num_rows($res) > 0;
        }
        return $cache[$k];
    }
}

if (!function_exists('vcar_estructura')) {
    /** Indicador, tablas y columnas del pedido. */
    function vcar_estructura(mysqli $conexion): bool
    {
        return vcar_existe($conexion, 'conf__empresas', 'usa_vendedores') && vcar_existe($conexion, 'gestion__vendedores_usuarios')
            && vcar_existe($conexion, 'gestion__vendedores_carteras') && vcar_existe($conexion, 'gestion__ventas_pedidos', 'vendedor_entidad_id')
            && vcar_existe($conexion, 'gestion__ventas_pedidos', 'origen');
    }
}

if (!function_exists('vcar_empresa_usa')) {
    /** Indicador usa_vendedores. Sin la estructura: false (lectura tolerante). */
    function vcar_empresa_usa(mysqli $conexion, int $empresa_id): bool
    {
        static $cache = [];
        if (isset($cache[$empresa_id])) {
            return $cache[$empresa_id];
        }
        $usa = false;
        if ($empresa_id > 0 && vcar_estructura($conexion)) {
            $st = mysqli_prepare($conexion, "SELECT usa_vendedores FROM conf__empresas WHERE empresa_id = ?");
            mysqli_stmt_bind_param($st, "i", $empresa_id);
            mysqli_stmt_execute($st);
            $row = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
            mysqli_stmt_close($st);
            $usa = intval($row['usa_vendedores'] ?? 0) === 1;
        }
        return $cache[$empresa_id] = $usa;
    }
}

if (!function_exists('vcar_filas')) {
    function vcar_filas(mysqli $conexion, string $sql, string $tipos = '', array $params = []): array
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

if (!function_exists('vcar_ejecutar')) {
    /** Escritura con bind; devuelve filas afectadas o el id insertado ($insert). */
    function vcar_ejecutar(mysqli $conexion, string $sql, string $tipos, array $params, bool $insert = false): int
    {
        $st = mysqli_prepare($conexion, $sql);
        if (!$st) {
            throw new RuntimeException('Error de consulta: ' . mysqli_error($conexion));
        }
        if ($tipos !== '') {
            mysqli_stmt_bind_param($st, $tipos, ...$params);
        }
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

if (!function_exists('vcar_fecha')) {
    /** Fecha Y-m-d válida o hoy. */
    function vcar_fecha($fecha = null): string
    {
        $f = is_string($fecha) ? substr(trim($fecha), 0, 10) : '';
        $d = $f !== '' ? DateTime::createFromFormat('Y-m-d', $f) : false;
        return ($d && $d->format('Y-m-d') === $f) ? $f : date('Y-m-d');
    }
}

if (!function_exists('vcar_dia_anterior')) {
    function vcar_dia_anterior(string $fecha): string
    {
        return date('Y-m-d', strtotime($fecha . ' -1 day'));
    }
}

// ----------------------------------------------------------------------------------------------- vendedores

if (!function_exists('vcar_es_vendedor')) {
    /** ¿La entidad es de la empresa y tiene el rol VEN vigente? */
    function vcar_es_vendedor(mysqli $conexion, int $empresa_id, int $entidad_id): bool
    {
        if ($entidad_id <= 0) {
            return false;
        }
        $r = vcar_filas($conexion, "SELECT 1 FROM gestion__entidades e WHERE e.entidad_id = ? AND e.empresa_id = ? AND "
            . ent_sql_tiene_rol('e.entidad_id', 'VEN') . " LIMIT 1", 'ii', [$entidad_id, $empresa_id]);
        return (bool) $r;
    }
}

if (!function_exists('vcar_vendedores')) {
    /**
     * Vendedores de la empresa (rol VEN vigente), con el usuario ligado a $fecha y la cantidad de clientes de su
     * cartera a $fecha. $incluir_bajas: también las entidades que tuvieron el rol y lo dieron de baja.
     */
    function vcar_vendedores(mysqli $conexion, int $empresa_id, ?string $fecha = null, bool $incluir_bajas = false): array
    {
        $fecha = vcar_fecha($fecha);
        $cond_rol = $incluir_bajas ? '' : ' AND er.f_baja IS NULL';
        return vcar_filas($conexion,
            "SELECT e.entidad_id AS vendedor_entidad_id, e.entidad_nombre, e.entidad_fantasia, e.cuit,
                    IF(er.f_baja IS NULL, 1, 0) AS activo, er.f_alta, er.f_baja,
                    vu.usuario_id, u.usuario, u.usuario_nombre,
                    (SELECT COUNT(*) FROM gestion__vendedores_carteras vc
                      WHERE vc.empresa_id = e.empresa_id AND vc.vendedor_entidad_id = e.entidad_id AND vc.tabla_estado_registro_id = 1
                        AND vc.f_desde <= ? AND (vc.f_hasta IS NULL OR vc.f_hasta >= ?)) AS clientes
               FROM gestion__entidades e
               JOIN gestion__entidades_roles er ON er.entidad_id = e.entidad_id $cond_rol
               JOIN gestion__roles_entidades r ON r.rol_entidad_id = er.rol_entidad_id AND r.rol_entidad_codigo = 'VEN'
               LEFT JOIN gestion__vendedores_usuarios vu ON vu.empresa_id = e.empresa_id AND vu.vendedor_entidad_id = e.entidad_id
                     AND vu.tabla_estado_registro_id = 1 AND vu.f_desde <= ? AND (vu.f_hasta IS NULL OR vu.f_hasta >= ?)
               LEFT JOIN conf__usuarios u ON u.usuario_id = vu.usuario_id
              WHERE e.empresa_id = ?
              ORDER BY activo DESC, e.entidad_nombre",
            'ssssi', [$fecha, $fecha, $fecha, $fecha, $empresa_id]);
    }
}

if (!function_exists('vcar_alta_vendedor')) {
    /**
     * Da el rol VEN a una entidad existente de la empresa ($datos['entidad_id']) o crea una entidad mínima
     * ($datos['entidad_nombre'], opcional 'cuit') con ese rol. Devuelve el entidad_id.
     * @throws InvalidArgumentException
     */
    function vcar_alta_vendedor(mysqli $conexion, int $empresa_id, array $datos, int $usuario_id): int
    {
        $entidad_id = intval($datos['entidad_id'] ?? 0);
        if ($entidad_id > 0) {
            $r = vcar_filas($conexion, "SELECT entidad_id FROM gestion__entidades WHERE entidad_id = ? AND empresa_id = ?", 'ii', [$entidad_id, $empresa_id]);
            if (!$r) {
                throw new InvalidArgumentException('La entidad no es de esta empresa.');
            }
        } else {
            $nombre = trim((string) ($datos['entidad_nombre'] ?? ''));
            if ($nombre === '') {
                throw new InvalidArgumentException('Elegí una entidad o escribí el nombre del vendedor.');
            }
            $nombre = mb_substr($nombre, 0, 255);
            $cuit = preg_replace('/\D/', '', (string) ($datos['cuit'] ?? ''));
            $cuit = $cuit === '' ? null : substr($cuit, 0, 11);
            if ($cuit !== null) {
                $dup = vcar_filas($conexion, "SELECT entidad_id, entidad_nombre FROM gestion__entidades WHERE empresa_id = ? AND cuit = ? AND tabla_estado_registro_id = 1 LIMIT 1",
                    'is', [$empresa_id, $cuit]);
                if ($dup) {
                    throw new InvalidArgumentException('Ya hay una entidad con ese CUIT / documento (' . $dup[0]['entidad_nombre'] . '): elegila de la lista.');
                }
            }
            $entidad_id = vcar_ejecutar($conexion,
                "INSERT INTO gestion__entidades (empresa_id, entidad_nombre, cuit, fecha_alta, usuario_alta, tabla_estado_registro_id)
                 VALUES (?, ?, ?, NOW(), ?, 1)", 'issi', [$empresa_id, $nombre, $cuit, $usuario_id], true);
        }
        if (!actualizarRolIndividualEntidad($conexion, $entidad_id, 'VEN', true, null, $empresa_id)) {
            throw new RuntimeException('No se pudo asignar el rol Vendedor (¿falta el rol VEN en el catálogo?).');
        }
        return $entidad_id;
    }
}

if (!function_exists('vcar_baja_vendedor')) {
    /**
     * Baja del rol VEN: solo sin clientes vigentes en la cartera (primero se reasignan). Cierra el usuario ligado.
     * @throws InvalidArgumentException
     */
    function vcar_baja_vendedor(mysqli $conexion, int $empresa_id, int $vendedor_id, ?string $fecha = null): void
    {
        $fecha = vcar_fecha($fecha);
        if (!vcar_es_vendedor($conexion, $empresa_id, $vendedor_id)) {
            throw new InvalidArgumentException('No es un vendedor activo de esta empresa.');
        }
        $n = count(vcar_cartera($conexion, $empresa_id, $vendedor_id, $fecha));
        if ($n > 0) {
            throw new InvalidArgumentException("El vendedor tiene $n cliente(s) en su cartera: reasignalos antes de darlo de baja.");
        }
        vcar_cerrar_usuario($conexion, $empresa_id, ['vendedor_entidad_id' => $vendedor_id], $fecha);
        actualizarRolIndividualEntidad($conexion, $vendedor_id, 'VEN', false, null, $empresa_id);
    }
}

// ----------------------------------------------------------------------------------------------- usuarios

if (!function_exists('vcar_cerrar_usuario')) {
    /**
     * Cierra los vínculos activos que coinciden con $donde (vendedor_entidad_id o usuario_id) desde $fecha: los que
     * empezaron antes quedan con f_hasta = día anterior; los que empezaban ese día o después pasan a estado 2.
     */
    function vcar_cerrar_usuario(mysqli $conexion, int $empresa_id, array $donde, string $fecha): void
    {
        $col = isset($donde['usuario_id']) ? 'usuario_id' : 'vendedor_entidad_id';
        $id = intval($donde[$col] ?? 0);
        $ayer = vcar_dia_anterior($fecha);
        vcar_ejecutar($conexion, "UPDATE gestion__vendedores_usuarios SET tabla_estado_registro_id = 2, actualizado_en = NOW()
                                   WHERE empresa_id = ? AND $col = ? AND tabla_estado_registro_id = 1 AND f_desde >= ?", 'iis', [$empresa_id, $id, $fecha]);
        vcar_ejecutar($conexion, "UPDATE gestion__vendedores_usuarios SET f_hasta = ?, actualizado_en = NOW()
                                   WHERE empresa_id = ? AND $col = ? AND tabla_estado_registro_id = 1 AND (f_hasta IS NULL OR f_hasta >= ?)",
            'siis', [$ayer, $empresa_id, $id, $fecha]);
    }
}

if (!function_exists('vcar_ligar_usuario')) {
    /**
     * Liga un usuario a un vendedor desde $fecha (un usuario, un vendedor; un vendedor, un usuario). $usuario_id = 0:
     * solo desliga. El usuario tiene que tener perfil en la empresa.
     * @throws InvalidArgumentException
     */
    function vcar_ligar_usuario(mysqli $conexion, int $empresa_id, int $vendedor_id, int $usuario_id, ?string $fecha, int $creado_por): void
    {
        $fecha = vcar_fecha($fecha);
        if (!vcar_es_vendedor($conexion, $empresa_id, $vendedor_id)) {
            throw new InvalidArgumentException('No es un vendedor activo de esta empresa.');
        }
        if ($usuario_id > 0) {
            $tiene = vcar_filas($conexion,
                "SELECT 1 FROM conf__usuarios_perfiles up JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = up.empresa_perfil_id
                  WHERE up.usuario_id = ? AND ep.empresa_id = ? AND up.tabla_estado_registro_id = 1 AND ep.tabla_estado_registro_id = 1
                    AND CURDATE() BETWEEN up.fecha_inicio AND up.fecha_fin LIMIT 1", 'ii', [$usuario_id, $empresa_id]);
            if (!$tiene) {
                throw new InvalidArgumentException('El usuario no tiene perfil en esta empresa.');
            }
            $actual = vcar_vendedor_de_usuario($conexion, $empresa_id, $usuario_id, $fecha);
            if ($actual === $vendedor_id) {
                return;
            }
        }
        vcar_cerrar_usuario($conexion, $empresa_id, ['vendedor_entidad_id' => $vendedor_id], $fecha);
        if ($usuario_id > 0) {
            vcar_cerrar_usuario($conexion, $empresa_id, ['usuario_id' => $usuario_id], $fecha);
            vcar_ejecutar($conexion, "INSERT INTO gestion__vendedores_usuarios (empresa_id, vendedor_entidad_id, usuario_id, f_desde, creado_por)
                                      VALUES (?, ?, ?, ?, ?)", 'iiisi', [$empresa_id, $vendedor_id, $usuario_id, $fecha, $creado_por], true);
        }
    }
}

if (!function_exists('vcar_vendedor_de_usuario')) {
    /** Vendedor que es el usuario en la empresa a $fecha (null si no es vendedor o sin el indicador). */
    function vcar_vendedor_de_usuario(mysqli $conexion, int $empresa_id, int $usuario_id, ?string $fecha = null): ?int
    {
        if ($usuario_id <= 0 || !vcar_empresa_usa($conexion, $empresa_id)) {
            return null;
        }
        $fecha = vcar_fecha($fecha);
        $r = vcar_filas($conexion,
            "SELECT vu.vendedor_entidad_id FROM gestion__vendedores_usuarios vu
               JOIN gestion__entidades_roles er ON er.entidad_id = vu.vendedor_entidad_id AND er.f_baja IS NULL
               JOIN gestion__roles_entidades r ON r.rol_entidad_id = er.rol_entidad_id AND r.rol_entidad_codigo = 'VEN'
              WHERE vu.empresa_id = ? AND vu.usuario_id = ? AND vu.tabla_estado_registro_id = 1
                AND vu.f_desde <= ? AND (vu.f_hasta IS NULL OR vu.f_hasta >= ?)
              ORDER BY vu.f_desde DESC LIMIT 1", 'iiss', [$empresa_id, $usuario_id, $fecha, $fecha]);
        return $r ? (int) $r[0]['vendedor_entidad_id'] : null;
    }
}

// ----------------------------------------------------------------------------------------------- cartera

if (!function_exists('vcar_vendedor_de_cliente')) {
    /** Vendedor del cliente a $fecha (null si no tiene o sin el indicador). */
    function vcar_vendedor_de_cliente(mysqli $conexion, int $empresa_id, int $cliente_id, ?string $fecha = null): ?int
    {
        if ($cliente_id <= 0 || !vcar_empresa_usa($conexion, $empresa_id)) {
            return null;
        }
        $fecha = vcar_fecha($fecha);
        $r = vcar_filas($conexion,
            "SELECT vendedor_entidad_id FROM gestion__vendedores_carteras
              WHERE empresa_id = ? AND cliente_entidad_id = ? AND tabla_estado_registro_id = 1 AND f_desde <= ? AND (f_hasta IS NULL OR f_hasta >= ?)
              ORDER BY f_desde DESC, cartera_id DESC LIMIT 1", 'iiss', [$empresa_id, $cliente_id, $fecha, $fecha]);
        return $r ? (int) $r[0]['vendedor_entidad_id'] : null;
    }
}

if (!function_exists('vcar_cartera')) {
    /** Clientes de la cartera del vendedor a $fecha (con nombre y desde cuándo). */
    function vcar_cartera(mysqli $conexion, int $empresa_id, int $vendedor_id, ?string $fecha = null): array
    {
        $fecha = vcar_fecha($fecha);
        return vcar_filas($conexion,
            "SELECT vc.cartera_id, vc.cliente_entidad_id, e.entidad_nombre, e.entidad_fantasia, e.cuit, vc.f_desde, vc.f_hasta
               FROM gestion__vendedores_carteras vc
               JOIN gestion__entidades e ON e.entidad_id = vc.cliente_entidad_id
              WHERE vc.empresa_id = ? AND vc.vendedor_entidad_id = ? AND vc.tabla_estado_registro_id = 1
                AND vc.f_desde <= ? AND (vc.f_hasta IS NULL OR vc.f_hasta >= ?)
              ORDER BY e.entidad_nombre", 'iiss', [$empresa_id, $vendedor_id, $fecha, $fecha]);
    }
}

if (!function_exists('vcar_historial_cliente')) {
    /** Todas las asignaciones del cliente (vigentes, cerradas y anuladas), de la más nueva a la más vieja. */
    function vcar_historial_cliente(mysqli $conexion, int $empresa_id, int $cliente_id): array
    {
        return vcar_filas($conexion,
            "SELECT vc.cartera_id, vc.vendedor_entidad_id, e.entidad_nombre AS vendedor_nombre, vc.f_desde, vc.f_hasta, vc.tabla_estado_registro_id
               FROM gestion__vendedores_carteras vc JOIN gestion__entidades e ON e.entidad_id = vc.vendedor_entidad_id
              WHERE vc.empresa_id = ? AND vc.cliente_entidad_id = ?
              ORDER BY vc.f_desde DESC, vc.cartera_id DESC", 'ii', [$empresa_id, $cliente_id]);
    }
}

if (!function_exists('vcar_cerrar_cliente')) {
    /**
     * Cierra la asignación del cliente desde $fecha: las que empezaron antes quedan con f_hasta = día anterior; las
     * que empezaban ese día o después (futuras) pasan a estado 2.
     */
    function vcar_cerrar_cliente(mysqli $conexion, int $empresa_id, int $cliente_id, string $fecha): void
    {
        vcar_ejecutar($conexion, "UPDATE gestion__vendedores_carteras SET tabla_estado_registro_id = 2, actualizado_en = NOW()
                                   WHERE empresa_id = ? AND cliente_entidad_id = ? AND tabla_estado_registro_id = 1 AND f_desde >= ?",
            'iis', [$empresa_id, $cliente_id, $fecha]);
        vcar_ejecutar($conexion, "UPDATE gestion__vendedores_carteras SET f_hasta = ?, actualizado_en = NOW()
                                   WHERE empresa_id = ? AND cliente_entidad_id = ? AND tabla_estado_registro_id = 1 AND (f_hasta IS NULL OR f_hasta >= ?)",
            'siis', [vcar_dia_anterior($fecha), $empresa_id, $cliente_id, $fecha]);
    }
}

if (!function_exists('vcar_asignar')) {
    /**
     * Asigna los clientes al vendedor desde $fecha (cierra la asignación anterior de cada uno). Los que ya son de
     * ese vendedor a esa fecha no se tocan. Devuelve ['asignados' => n, 'sin_cambios' => n].
     * @throws InvalidArgumentException
     */
    function vcar_asignar(mysqli $conexion, int $empresa_id, array $cliente_ids, int $vendedor_id, ?string $fecha, int $creado_por): array
    {
        $fecha = vcar_fecha($fecha);
        if (!vcar_es_vendedor($conexion, $empresa_id, $vendedor_id)) {
            throw new InvalidArgumentException('No es un vendedor activo de esta empresa.');
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $cliente_ids))));
        if (!$ids) {
            throw new InvalidArgumentException('Elegí al menos un cliente.');
        }
        $validos = array_map('intval', array_column(vcar_filas($conexion,
            "SELECT e.entidad_id FROM gestion__entidades e WHERE e.empresa_id = ? AND e.entidad_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")",
            'i' . str_repeat('i', count($ids)), array_merge([$empresa_id], $ids)), 'entidad_id'));
        if (count($validos) !== count($ids)) {
            throw new InvalidArgumentException('Hay clientes que no son de esta empresa.');
        }
        if (in_array($vendedor_id, $ids, true)) {
            throw new InvalidArgumentException('Un vendedor no puede estar en su propia cartera.');
        }
        $asignados = 0;
        $sin_cambios = 0;
        foreach ($ids as $cli) {
            if (vcar_vendedor_de_cliente($conexion, $empresa_id, $cli, $fecha) === $vendedor_id) {
                $sin_cambios++;
                continue;
            }
            vcar_cerrar_cliente($conexion, $empresa_id, $cli, $fecha);
            vcar_ejecutar($conexion, "INSERT INTO gestion__vendedores_carteras (empresa_id, vendedor_entidad_id, cliente_entidad_id, f_desde, creado_por)
                                      VALUES (?, ?, ?, ?, ?)", 'iiisi', [$empresa_id, $vendedor_id, $cli, $fecha, $creado_por], true);
            $asignados++;
        }
        return ['asignados' => $asignados, 'sin_cambios' => $sin_cambios];
    }
}

if (!function_exists('vcar_quitar')) {
    /** Deja a los clientes sin vendedor desde $fecha. Devuelve cuántos tenían uno. */
    function vcar_quitar(mysqli $conexion, int $empresa_id, array $cliente_ids, ?string $fecha = null): int
    {
        $fecha = vcar_fecha($fecha);
        $n = 0;
        foreach (array_unique(array_filter(array_map('intval', $cliente_ids))) as $cli) {
            if (vcar_vendedor_de_cliente($conexion, $empresa_id, $cli, $fecha) !== null) {
                $n++;
            }
            vcar_cerrar_cliente($conexion, $empresa_id, $cli, $fecha);
        }
        return $n;
    }
}

if (!function_exists('vcar_reasignar_cartera')) {
    /** Pasa toda la cartera de $de a $a desde $fecha (vacaciones, baja). Devuelve el resultado de vcar_asignar. */
    function vcar_reasignar_cartera(mysqli $conexion, int $empresa_id, int $de, int $a, ?string $fecha, int $creado_por): array
    {
        $fecha = vcar_fecha($fecha);
        if ($de === $a) {
            throw new InvalidArgumentException('Elegí otro vendedor de destino.');
        }
        $clientes = array_column(vcar_cartera($conexion, $empresa_id, $de, $fecha), 'cliente_entidad_id');
        if (!$clientes) {
            return ['asignados' => 0, 'sin_cambios' => 0];
        }
        return vcar_asignar($conexion, $empresa_id, $clientes, $a, $fecha, $creado_por);
    }
}

if (!function_exists('vcar_clientes_sin_vendedor')) {
    /** Clientes (rol CLI vigente) sin vendedor a $fecha, con búsqueda opcional por nombre / CUIT. Máx. $limite. */
    function vcar_clientes_sin_vendedor(mysqli $conexion, int $empresa_id, ?string $fecha = null, string $q = '', int $limite = 200): array
    {
        $fecha = vcar_fecha($fecha);
        $like = '%' . trim($q) . '%';
        return vcar_filas($conexion,
            "SELECT e.entidad_id AS cliente_entidad_id, e.entidad_nombre, e.entidad_fantasia, e.cuit
               FROM gestion__entidades e
              WHERE e.empresa_id = ? AND e.tabla_estado_registro_id = 1 AND " . ent_sql_tiene_rol('e.entidad_id', 'CLI') . "
                AND (e.entidad_nombre LIKE ? OR IFNULL(e.entidad_fantasia, '') LIKE ? OR IFNULL(e.cuit, '') LIKE ?)
                AND NOT EXISTS (SELECT 1 FROM gestion__vendedores_carteras vc
                                 WHERE vc.empresa_id = e.empresa_id AND vc.cliente_entidad_id = e.entidad_id AND vc.tabla_estado_registro_id = 1
                                   AND vc.f_desde <= ? AND (vc.f_hasta IS NULL OR vc.f_hasta >= ?))
              ORDER BY e.entidad_nombre LIMIT " . max(1, min(1000, $limite)),
            'isssss', [$empresa_id, $like, $like, $like, $fecha, $fecha]);
    }
}

// ----------------------------------------------------------------------------------------------- permisos

if (!function_exists('vcar_usuario_puede')) {
    /** ¿Tiene el usuario la función $accion_js en la página Vendedores y carteras (de cualquiera de los módulos)? */
    function vcar_usuario_puede(mysqli $conexion, int $empresa_id, int $usuario_id, string $accion_js): bool
    {
        if (defined('USUARIOS_ACCESO_TOTAL') && in_array($usuario_id, USUARIOS_ACCESO_TOTAL, true)) {
            return true;
        }
        $r = vcar_filas($conexion,
            "SELECT 1 FROM conf__usuarios_perfiles up
               JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = up.empresa_perfil_id AND ep.empresa_id = ? AND ep.tabla_estado_registro_id = 1
               JOIN conf__empresas_perfiles_funciones epf ON epf.empresa_perfil_id = ep.empresa_perfil_id AND epf.asignado = 1
               JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = epf.pagina_funcion_id AND pf.tabla_estado_registro_id = 1
               JOIN conf__paginas p ON p.pagina_id = pf.pagina_id
              WHERE up.usuario_id = ? AND up.tabla_estado_registro_id = 1 AND CURDATE() BETWEEN up.fecha_inicio AND up.fecha_fin
                AND p.url = ? AND pf.accion_js = ?
              LIMIT 1", 'iiss', [$empresa_id, $usuario_id, VCAR_PAGINA_URL, $accion_js]);
        return (bool) $r;
    }
}

if (!function_exists('vcar_restringido')) {
    /**
     * Vendedor al que queda limitado el usuario: el que es, si no tiene "Ver todas las carteras". null = sin
     * restricción (no es vendedor, tiene la función o la empresa no usa vendedores).
     */
    function vcar_restringido(mysqli $conexion, int $empresa_id, int $usuario_id): ?int
    {
        static $cache = [];
        $k = "$empresa_id.$usuario_id";
        if (array_key_exists($k, $cache)) {
            return $cache[$k];
        }
        $v = vcar_vendedor_de_usuario($conexion, $empresa_id, $usuario_id);
        if ($v !== null && vcar_usuario_puede($conexion, $empresa_id, $usuario_id, VCAR_FUNCION_VER_TODAS)) {
            $v = null;
        }
        return $cache[$k] = $v;
    }
}

if (!function_exists('vcar_sql_filtro_cliente')) {
    /**
     * Condición SQL (" AND ...") para listados: con restricción, solo filas cuyo cliente ($col_cliente) está hoy en
     * la cartera del vendedor del usuario o cuyo vendedor ($col_vendedor, opcional) es él. Sin restricción: ''.
     * Los ids van casteados a int (no hay datos del request en el SQL).
     */
    function vcar_sql_filtro_cliente(mysqli $conexion, int $empresa_id, int $usuario_id, string $col_cliente, string $col_vendedor = ''): string
    {
        $v = vcar_restringido($conexion, $empresa_id, $usuario_id);
        if ($v === null) {
            return '';
        }
        $col_cliente = preg_replace('/[^a-z0-9_.]/i', '', $col_cliente);
        $col_vendedor = preg_replace('/[^a-z0-9_.]/i', '', $col_vendedor);
        $hoy = date('Y-m-d');
        $cond = "$col_cliente IN (SELECT vcf.cliente_entidad_id FROM gestion__vendedores_carteras vcf
                                    WHERE vcf.empresa_id = " . (int) $empresa_id . " AND vcf.vendedor_entidad_id = " . (int) $v . "
                                      AND vcf.tabla_estado_registro_id = 1 AND vcf.f_desde <= '$hoy' AND (vcf.f_hasta IS NULL OR vcf.f_hasta >= '$hoy'))";
        if ($col_vendedor !== '') {
            $cond = "($cond OR $col_vendedor = " . (int) $v . ")";
        }
        return " AND $cond";
    }
}

if (!function_exists('vcar_exigir_cliente')) {
    /**
     * Al grabar: un usuario restringido solo carga para clientes de su cartera (a $fecha o hoy).
     * @throws InvalidArgumentException
     */
    function vcar_exigir_cliente(mysqli $conexion, int $empresa_id, int $usuario_id, int $cliente_id, ?string $fecha = null): void
    {
        $v = vcar_restringido($conexion, $empresa_id, $usuario_id);
        if ($v === null) {
            return;
        }
        $de_cliente = vcar_vendedor_de_cliente($conexion, $empresa_id, $cliente_id, $fecha);
        if ($de_cliente !== $v && vcar_vendedor_de_cliente($conexion, $empresa_id, $cliente_id) !== $v) {
            throw new InvalidArgumentException('El cliente no está en tu cartera.');
        }
    }
}

// ----------------------------------------------------------------------------------------------- pedido

if (!function_exists('vcar_resolver_pedido')) {
    /**
     * Vendedor y origen de un pedido que se graba (null sin el indicador):
     * - usuario vendedor → él, origen VENDEDOR (y el cliente tiene que estar en su cartera si está restringido);
     * - si no → $datos['vendedor_entidad_id'] si el usuario tiene "Asignar vendedor" y es un vendedor activo; si no,
     *   el de la cartera del cliente a la fecha (o el que ya tenía el pedido, $actual); origen ERP.
     * $datos['origen'] solo se acepta si es CARRITO / APP (integraciones).
     * @throws InvalidArgumentException
     */
    function vcar_resolver_pedido(mysqli $conexion, int $empresa_id, int $usuario_id, int $cliente_id, ?string $fecha, array $datos = [], ?array $actual = null): ?array
    {
        if (!vcar_empresa_usa($conexion, $empresa_id)) {
            return null;
        }
        vcar_exigir_cliente($conexion, $empresa_id, $usuario_id, $cliente_id, $fecha);
        $origen_pedido = strtoupper(trim((string) ($datos['origen'] ?? '')));
        $propio = vcar_vendedor_de_usuario($conexion, $empresa_id, $usuario_id, $fecha);
        if ($propio !== null) {
            return ['vendedor_entidad_id' => $propio, 'origen' => $actual['origen'] ?? 'VENDEDOR'];
        }
        $origen = $actual['origen'] ?? (in_array($origen_pedido, ['CARRITO', 'APP'], true) ? $origen_pedido : 'ERP');
        $pedido_vendedor = intval($datos['vendedor_entidad_id'] ?? 0);
        if ($pedido_vendedor > 0 && vcar_usuario_puede($conexion, $empresa_id, $usuario_id, VCAR_FUNCION_ASIGNAR)) {
            if (!vcar_es_vendedor($conexion, $empresa_id, $pedido_vendedor)) {
                throw new InvalidArgumentException('El vendedor elegido no es un vendedor activo.');
            }
            return ['vendedor_entidad_id' => $pedido_vendedor, 'origen' => $origen];
        }
        $de_cartera = vcar_vendedor_de_cliente($conexion, $empresa_id, $cliente_id, $fecha);
        if ($actual !== null && !empty($actual['vendedor_entidad_id']) && intval($actual['cliente_entidad_id'] ?? 0) === $cliente_id) {
            return ['vendedor_entidad_id' => (int) $actual['vendedor_entidad_id'], 'origen' => $origen];
        }
        return ['vendedor_entidad_id' => $de_cartera, 'origen' => $origen];
    }
}

if (!function_exists('vcar_pedido_actual')) {
    /** Vendedor, origen y cliente que ya tiene un pedido (para editar). null sin el indicador o sin el pedido. */
    function vcar_pedido_actual(mysqli $conexion, int $empresa_id, int $venta_pedido_id): ?array
    {
        if (!vcar_empresa_usa($conexion, $empresa_id)) {
            return null;
        }
        $r = vcar_filas($conexion, "SELECT vendedor_entidad_id, origen, entidad_id AS cliente_entidad_id FROM gestion__ventas_pedidos WHERE venta_pedido_id = ? AND empresa_id = ?",
            'ii', [$venta_pedido_id, $empresa_id]);
        return $r ? $r[0] : null;
    }
}

if (!function_exists('vcar_guardar_pedido')) {
    /** Graba vendedor y origen resueltos (sin resultado, nada). */
    function vcar_guardar_pedido(mysqli $conexion, int $venta_pedido_id, ?array $res): void
    {
        if ($res === null || $venta_pedido_id <= 0) {
            return;
        }
        $vend = $res['vendedor_entidad_id'] !== null ? (int) $res['vendedor_entidad_id'] : null;
        $origen = in_array($res['origen'] ?? '', VCAR_ORIGENES, true) ? $res['origen'] : 'ERP';
        vcar_ejecutar($conexion, "UPDATE gestion__ventas_pedidos SET vendedor_entidad_id = ?, origen = ? WHERE venta_pedido_id = ?",
            'isi', [$vend, $origen, $venta_pedido_id]);
    }
}

if (!function_exists('vcar_pedidos_vendedores')) {
    /** Vendedor de cada pedido [venta_pedido_id => ['vendedor_entidad_id', 'vendedor_nombre', 'origen']] (para los listados). */
    function vcar_pedidos_vendedores(mysqli $conexion, int $empresa_id, array $pedido_ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $pedido_ids))));
        if (!$ids || !vcar_empresa_usa($conexion, $empresa_id)) {
            return [];
        }
        $out = [];
        foreach (array_chunk($ids, 500) as $lote) {
            foreach (vcar_filas($conexion,
                "SELECT vp.venta_pedido_id, vp.vendedor_entidad_id, vp.origen, e.entidad_nombre AS vendedor_nombre
                   FROM gestion__ventas_pedidos vp LEFT JOIN gestion__entidades e ON e.entidad_id = vp.vendedor_entidad_id
                  WHERE vp.empresa_id = ? AND vp.venta_pedido_id IN (" . implode(',', array_fill(0, count($lote), '?')) . ")",
                'i' . str_repeat('i', count($lote)), array_merge([$empresa_id], $lote)) as $r) {
                $out[(int) $r['venta_pedido_id']] = $r;
            }
        }
        return $out;
    }
}

if (!function_exists('vcar_exigir_pedido')) {
    /**
     * Un usuario restringido solo abre / opera pedidos de su cartera (cliente en su cartera hoy) o propios (vendedor =
     * él). Sin restricción o pedido inexistente: no hace nada (el resto lo valida cada acción).
     * @throws InvalidArgumentException
     */
    function vcar_exigir_pedido(mysqli $conexion, int $empresa_id, int $usuario_id, int $venta_pedido_id): void
    {
        $v = vcar_restringido($conexion, $empresa_id, $usuario_id);
        if ($v === null || $venta_pedido_id <= 0) {
            return;
        }
        $r = vcar_filas($conexion, "SELECT entidad_id, vendedor_entidad_id FROM gestion__ventas_pedidos WHERE venta_pedido_id = ? AND empresa_id = ?",
            'ii', [$venta_pedido_id, $empresa_id]);
        if (!$r) {
            return;
        }
        if ((int) ($r[0]['vendedor_entidad_id'] ?? 0) === $v || vcar_vendedor_de_cliente($conexion, $empresa_id, (int) $r[0]['entidad_id']) === $v) {
            return;
        }
        throw new InvalidArgumentException('El pedido es de un cliente que no está en tu cartera.');
    }
}

if (!function_exists('vcar_agregar_vendedor_filas')) {
    /** Agrega vendedor_nombre y origen a filas de pedidos (con venta_pedido_id). Sin el indicador no las toca. */
    function vcar_agregar_vendedor_filas(mysqli $conexion, int $empresa_id, array $filas): array
    {
        if (!$filas || !vcar_empresa_usa($conexion, $empresa_id)) {
            return $filas;
        }
        $v = vcar_pedidos_vendedores($conexion, $empresa_id, array_column($filas, 'venta_pedido_id'));
        foreach ($filas as &$f) {
            $x = $v[(int) ($f['venta_pedido_id'] ?? 0)] ?? null;
            $f['vendedor_entidad_id'] = $x['vendedor_entidad_id'] ?? null;
            $f['vendedor_nombre'] = $x['vendedor_nombre'] ?? null;
            $f['origen'] = $x['origen'] ?? null;
        }
        unset($f);
        return $filas;
    }
}

if (!function_exists('vcar_opciones_pedido')) {
    /** Para la pantalla del pedido: indicador, si el usuario puede elegir vendedor y la lista de vendedores activos. */
    function vcar_opciones_pedido(mysqli $conexion, int $empresa_id, int $usuario_id): array
    {
        if (!vcar_empresa_usa($conexion, $empresa_id)) {
            return ['activo' => false];
        }
        $puede = vcar_vendedor_de_usuario($conexion, $empresa_id, $usuario_id) === null
            && vcar_usuario_puede($conexion, $empresa_id, $usuario_id, VCAR_FUNCION_ASIGNAR);
        return ['activo' => true, 'puede_asignar' => $puede, 'restringido' => vcar_restringido($conexion, $empresa_id, $usuario_id) !== null,
                'vendedores' => array_map(fn($v) => ['id' => (int) $v['vendedor_entidad_id'], 'nombre' => $v['entidad_nombre']], vcar_vendedores($conexion, $empresa_id))];
    }
}
