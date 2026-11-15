<?php
/**
 * Distribución (paso 5b de la distribuidora): zonas, zona de cada cliente, camiones, depósito de despacho, peso y
 * volumen de los pedidos y ganchos del pedido para el estado 42 "Para distribución".
 * Diseño: _local/docs/gestion/24_distribucion.md. Hojas de ruta y rendición: distribucion_hojas.php.
 *
 * Indicador conf__empresas.usa_distribucion. Sin él (o sin la estructura) todo devuelve "inactivo" / [] y nada lee ni
 * escribe las tablas o columnas nuevas. Usa los ayudantes genéricos de core/vendedores.php (vcar_filas,
 * vcar_ejecutar, vcar_fecha, vcar_dia_anterior).
 */

require_once __DIR__ . '/../vendedores.php';

const VDIS_ESTADO_PARA_DISTRIBUCION = 42;
const VDIS_DIAS = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];

if (!function_exists('vdis_estructura')) {
    /** Indicador, tablas, columna del pedido y estado 42. */
    function vdis_estructura(mysqli $conexion): bool
    {
        foreach (['gestion__distribucion_zonas', 'gestion__distribucion_zonas_clientes', 'gestion__distribucion_camiones',
                  'gestion__distribucion_hojas', 'gestion__distribucion_hojas_pedidos'] as $t) {
            if (!vcar_existe($conexion, $t)) {
                return false;
            }
        }
        return vcar_existe($conexion, 'conf__empresas', 'usa_distribucion') && vcar_existe($conexion, 'gestion__ventas_pedidos', 'boca_despacho_id');
    }
}

if (!function_exists('vdis_empresa_usa')) {
    /** Indicador usa_distribucion. Sin la estructura: false (lectura tolerante). */
    function vdis_empresa_usa(mysqli $conexion, int $empresa_id): bool
    {
        static $cache = [];
        if (isset($cache[$empresa_id])) {
            return $cache[$empresa_id];
        }
        $usa = false;
        if ($empresa_id > 0 && vdis_estructura($conexion)) {
            $r = vcar_filas($conexion, "SELECT usa_distribucion FROM conf__empresas WHERE empresa_id = ?", 'i', [$empresa_id]);
            $usa = intval($r[0]['usa_distribucion'] ?? 0) === 1;
        }
        return $cache[$empresa_id] = $usa;
    }
}

if (!function_exists('vdis_num')) {
    /** Número >= 0 o null (vacío). @throws InvalidArgumentException */
    function vdis_num($v, string $campo): ?float
    {
        if ($v === null || trim((string) $v) === '') {
            return null;
        }
        if (!is_numeric($v) || (float) $v < 0) {
            throw new InvalidArgumentException("$campo: tiene que ser un número mayor o igual a 0.");
        }
        return (float) $v;
    }
}

// ----------------------------------------------------------------------------------------------- depósitos

if (!function_exists('vdis_depositos')) {
    /** Depósitos activos de la empresa (bocas con es_deposito = 1), con su sucursal. */
    function vdis_depositos(mysqli $conexion, int $empresa_id): array
    {
        return vcar_filas($conexion,
            "SELECT b.boca_id, b.boca_nombre, b.sucursal_id, b.es_principal, s.sucursal_nombre
               FROM gestion__bocas b LEFT JOIN gestion__sucursales s ON s.sucursal_id = b.sucursal_id AND s.empresa_id = b.empresa_id
              WHERE b.empresa_id = ? AND b.es_deposito = 1 AND b.tabla_estado_registro_id = 1
              ORDER BY s.sucursal_nombre, b.orden, b.boca_nombre", 'i', [$empresa_id]);
    }
}

if (!function_exists('vdis_es_deposito')) {
    function vdis_es_deposito(mysqli $conexion, int $empresa_id, int $boca_id): bool
    {
        return in_array($boca_id, array_map('intval', array_column(vdis_depositos($conexion, $empresa_id), 'boca_id')), true);
    }
}

if (!function_exists('vdis_deposito_de_sucursal')) {
    /** Depósito principal de la sucursal (o el primero); null si no tiene. */
    function vdis_deposito_de_sucursal(mysqli $conexion, int $empresa_id, int $sucursal_id): ?int
    {
        foreach (vdis_depositos($conexion, $empresa_id) as $d) {
            if ((int) $d['sucursal_id'] === $sucursal_id && (int) $d['es_principal'] === 1) {
                return (int) $d['boca_id'];
            }
        }
        foreach (vdis_depositos($conexion, $empresa_id) as $d) {
            if ((int) $d['sucursal_id'] === $sucursal_id) {
                return (int) $d['boca_id'];
            }
        }
        return null;
    }
}

// ----------------------------------------------------------------------------------------------- zonas

if (!function_exists('vdis_dias_texto')) {
    /** "1,3,5" → "Lun, Mié, Vie" */
    function vdis_dias_texto(?string $dias): string
    {
        $out = [];
        foreach (array_filter(array_map('intval', explode(',', (string) $dias))) as $d) {
            if (isset(VDIS_DIAS[$d])) {
                $out[] = VDIS_DIAS[$d];
            }
        }
        return implode(', ', $out);
    }
}

if (!function_exists('vdis_zonas')) {
    /** Zonas de la empresa con la cantidad de clientes vigentes. */
    function vdis_zonas(mysqli $conexion, int $empresa_id, bool $incluir_inactivas = false): array
    {
        $hoy = date('Y-m-d');
        $filas = vcar_filas($conexion,
            "SELECT z.*, (SELECT COUNT(*) FROM gestion__distribucion_zonas_clientes zc
                           WHERE zc.empresa_id = z.empresa_id AND zc.zona_id = z.zona_id AND zc.tabla_estado_registro_id = 1
                             AND zc.f_desde <= ? AND (zc.f_hasta IS NULL OR zc.f_hasta >= ?)) AS clientes
               FROM gestion__distribucion_zonas z
              WHERE z.empresa_id = ?" . ($incluir_inactivas ? '' : ' AND z.tabla_estado_registro_id = 1') . "
              ORDER BY z.orden, z.nombre", 'ssi', [$hoy, $hoy, $empresa_id]);
        foreach ($filas as &$f) {
            $f['dias_texto'] = vdis_dias_texto($f['dias_reparto']);
        }
        unset($f);
        return $filas;
    }
}

if (!function_exists('vdis_zona_guardar')) {
    /** Alta (zona_id 0) o edición de una zona. Devuelve el id. @throws InvalidArgumentException */
    function vdis_zona_guardar(mysqli $conexion, int $empresa_id, array $d, int $usuario_id): int
    {
        $id = intval($d['zona_id'] ?? 0);
        $nombre = mb_substr(trim((string) ($d['nombre'] ?? '')), 0, 100);
        if ($nombre === '') {
            throw new InvalidArgumentException('Falta el nombre de la zona.');
        }
        $codigo = mb_substr(trim((string) ($d['codigo'] ?? '')), 0, 20);
        $codigo = $codigo === '' ? null : $codigo;
        $dias = is_array($d['dias_reparto'] ?? null) ? $d['dias_reparto'] : explode(',', (string) ($d['dias_reparto'] ?? ''));
        $dias = array_values(array_unique(array_filter(array_map('intval', $dias), fn($x) => $x >= 1 && $x <= 7)));
        sort($dias);
        $dias = $dias ? implode(',', $dias) : null;
        $orden = max(0, min(65535, intval($d['orden'] ?? 0)));
        $dup = vcar_filas($conexion, "SELECT zona_id FROM gestion__distribucion_zonas WHERE empresa_id = ? AND nombre = ? AND tabla_estado_registro_id = 1 AND zona_id <> ?",
            'isi', [$empresa_id, $nombre, $id]);
        if ($dup) {
            throw new InvalidArgumentException('Ya hay una zona activa con ese nombre.');
        }
        if ($id > 0) {
            if (!vcar_filas($conexion, "SELECT 1 FROM gestion__distribucion_zonas WHERE zona_id = ? AND empresa_id = ?", 'ii', [$id, $empresa_id])) {
                throw new InvalidArgumentException('La zona no existe.');
            }
            vcar_ejecutar($conexion, "UPDATE gestion__distribucion_zonas SET nombre = ?, codigo = ?, dias_reparto = ?, orden = ?, actualizado_en = NOW()
                                      WHERE zona_id = ? AND empresa_id = ?", 'sssiii', [$nombre, $codigo, $dias, $orden, $id, $empresa_id]);
            return $id;
        }
        return vcar_ejecutar($conexion, "INSERT INTO gestion__distribucion_zonas (empresa_id, nombre, codigo, dias_reparto, orden, creado_por)
                                         VALUES (?, ?, ?, ?, ?, ?)", 'isssii', [$empresa_id, $nombre, $codigo, $dias, $orden, $usuario_id], true);
    }
}

if (!function_exists('vdis_zona_estado')) {
    /** Activa (1) o inactiva (2) una zona. Inactivarla no cambia la zona de sus clientes. */
    function vdis_zona_estado(mysqli $conexion, int $empresa_id, int $zona_id, int $estado): void
    {
        if (!in_array($estado, [1, 2], true)) {
            throw new InvalidArgumentException('Estado inválido.');
        }
        if (vcar_ejecutar($conexion, "UPDATE gestion__distribucion_zonas SET tabla_estado_registro_id = ?, actualizado_en = NOW() WHERE zona_id = ? AND empresa_id = ?",
                'iii', [$estado, $zona_id, $empresa_id]) === 0
            && !vcar_filas($conexion, "SELECT 1 FROM gestion__distribucion_zonas WHERE zona_id = ? AND empresa_id = ?", 'ii', [$zona_id, $empresa_id])) {
            throw new InvalidArgumentException('La zona no existe.');
        }
    }
}

if (!function_exists('vdis_zona_de_cliente')) {
    /**
     * Zona del cliente a $fecha: la de su sucursal de entrega si tiene una propia, si no la del cliente (todas las
     * sucursales). Devuelve ['zona_id', 'nombre'] o null.
     */
    function vdis_zona_de_cliente(mysqli $conexion, int $empresa_id, int $cliente_id, ?int $entidad_sucursal_id = null, ?string $fecha = null): ?array
    {
        $fecha = vcar_fecha($fecha);
        $r = vcar_filas($conexion,
            "SELECT zc.zona_id, z.nombre FROM gestion__distribucion_zonas_clientes zc JOIN gestion__distribucion_zonas z ON z.zona_id = zc.zona_id
              WHERE zc.empresa_id = ? AND zc.cliente_entidad_id = ? AND zc.tabla_estado_registro_id = 1 AND zc.f_desde <= ? AND (zc.f_hasta IS NULL OR zc.f_hasta >= ?)
                AND (zc.entidad_sucursal_id IS NULL OR zc.entidad_sucursal_id = ?)
              ORDER BY zc.entidad_sucursal_id IS NULL, zc.f_desde DESC, zc.zona_cliente_id DESC LIMIT 1",
            'iissi', [$empresa_id, $cliente_id, $fecha, $fecha, (int) $entidad_sucursal_id]);
        return $r ? ['zona_id' => (int) $r[0]['zona_id'], 'nombre' => $r[0]['nombre']] : null;
    }
}

if (!function_exists('vdis_zona_cerrar_cliente')) {
    /** Cierra la zona vigente del cliente (para esa sucursal o para todas: NULL) desde $fecha. */
    function vdis_zona_cerrar_cliente(mysqli $conexion, int $empresa_id, int $cliente_id, ?int $entidad_sucursal_id, string $fecha): void
    {
        $cond = $entidad_sucursal_id ? 'entidad_sucursal_id = ' . (int) $entidad_sucursal_id : 'entidad_sucursal_id IS NULL';
        vcar_ejecutar($conexion, "UPDATE gestion__distribucion_zonas_clientes SET tabla_estado_registro_id = 2, actualizado_en = NOW()
                                   WHERE empresa_id = ? AND cliente_entidad_id = ? AND $cond AND tabla_estado_registro_id = 1 AND f_desde >= ?",
            'iis', [$empresa_id, $cliente_id, $fecha]);
        vcar_ejecutar($conexion, "UPDATE gestion__distribucion_zonas_clientes SET f_hasta = ?, actualizado_en = NOW()
                                   WHERE empresa_id = ? AND cliente_entidad_id = ? AND $cond AND tabla_estado_registro_id = 1 AND (f_hasta IS NULL OR f_hasta >= ?)",
            'siis', [vcar_dia_anterior($fecha), $empresa_id, $cliente_id, $fecha]);
    }
}

if (!function_exists('vdis_zona_asignar')) {
    /**
     * Pone a los clientes en la zona desde $fecha (para todas sus sucursales, o solo para $entidad_sucursal_id si se
     * asigna un único cliente). Devuelve ['asignados', 'sin_cambios']. @throws InvalidArgumentException
     */
    function vdis_zona_asignar(mysqli $conexion, int $empresa_id, array $cliente_ids, int $zona_id, ?int $entidad_sucursal_id, ?string $fecha, int $usuario_id): array
    {
        $fecha = vcar_fecha($fecha);
        if (!vcar_filas($conexion, "SELECT 1 FROM gestion__distribucion_zonas WHERE zona_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', [$zona_id, $empresa_id])) {
            throw new InvalidArgumentException('La zona no existe o está inactiva.');
        }
        $ids = array_values(array_unique(array_filter(array_map('intval', $cliente_ids))));
        if (!$ids) {
            throw new InvalidArgumentException('Elegí al menos un cliente.');
        }
        $validos = array_map('intval', array_column(vcar_filas($conexion,
            "SELECT entidad_id FROM gestion__entidades WHERE empresa_id = ? AND entidad_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")",
            'i' . str_repeat('i', count($ids)), array_merge([$empresa_id], $ids)), 'entidad_id'));
        if (count($validos) !== count($ids)) {
            throw new InvalidArgumentException('Hay clientes que no son de esta empresa.');
        }
        $suc = ($entidad_sucursal_id && count($ids) === 1) ? $entidad_sucursal_id : null;
        if ($suc !== null && !vcar_filas($conexion, "SELECT 1 FROM gestion__entidades_sucursales WHERE sucursal_id = ? AND entidad_id = ?", 'ii', [$suc, $ids[0]])) {
            throw new InvalidArgumentException('La sucursal no es de ese cliente.');
        }
        $asignados = 0;
        $sin_cambios = 0;
        foreach ($ids as $cli) {
            $actual = vcar_filas($conexion,
                "SELECT zona_id FROM gestion__distribucion_zonas_clientes WHERE empresa_id = ? AND cliente_entidad_id = ? AND "
                . ($suc ? 'entidad_sucursal_id = ' . (int) $suc : 'entidad_sucursal_id IS NULL')
                . " AND tabla_estado_registro_id = 1 AND f_desde <= ? AND (f_hasta IS NULL OR f_hasta >= ?) ORDER BY f_desde DESC LIMIT 1",
                'iiss', [$empresa_id, $cli, $fecha, $fecha]);
            if ($actual && (int) $actual[0]['zona_id'] === $zona_id) {
                $sin_cambios++;
                continue;
            }
            vdis_zona_cerrar_cliente($conexion, $empresa_id, $cli, $suc, $fecha);
            vcar_ejecutar($conexion, "INSERT INTO gestion__distribucion_zonas_clientes (empresa_id, zona_id, cliente_entidad_id, entidad_sucursal_id, f_desde, creado_por)
                                      VALUES (?, ?, ?, ?, ?, ?)", 'iiiisi', [$empresa_id, $zona_id, $cli, $suc, $fecha, $usuario_id], true);
            $asignados++;
        }
        return ['asignados' => $asignados, 'sin_cambios' => $sin_cambios];
    }
}

if (!function_exists('vdis_zona_quitar')) {
    /** Deja a los clientes sin zona propia (para todas sus sucursales) desde $fecha. */
    function vdis_zona_quitar(mysqli $conexion, int $empresa_id, array $cliente_ids, ?string $fecha = null): void
    {
        $fecha = vcar_fecha($fecha);
        foreach (array_unique(array_filter(array_map('intval', $cliente_ids))) as $cli) {
            vdis_zona_cerrar_cliente($conexion, $empresa_id, $cli, null, $fecha);
        }
    }
}

if (!function_exists('vdis_zona_clientes')) {
    /** Clientes de una zona a $fecha (con la sucursal si la asignación es de una sucursal). */
    function vdis_zona_clientes(mysqli $conexion, int $empresa_id, int $zona_id, ?string $fecha = null): array
    {
        $fecha = vcar_fecha($fecha);
        return vcar_filas($conexion,
            "SELECT zc.zona_cliente_id, zc.cliente_entidad_id, e.entidad_nombre, e.entidad_fantasia, e.cuit, zc.entidad_sucursal_id, es.sucursal_nombre, zc.f_desde
               FROM gestion__distribucion_zonas_clientes zc
               JOIN gestion__entidades e ON e.entidad_id = zc.cliente_entidad_id
               LEFT JOIN gestion__entidades_sucursales es ON es.sucursal_id = zc.entidad_sucursal_id
              WHERE zc.empresa_id = ? AND zc.zona_id = ? AND zc.tabla_estado_registro_id = 1 AND zc.f_desde <= ? AND (zc.f_hasta IS NULL OR zc.f_hasta >= ?)
              ORDER BY e.entidad_nombre, es.sucursal_nombre", 'iiss', [$empresa_id, $zona_id, $fecha, $fecha]);
    }
}

if (!function_exists('vdis_clientes_sin_zona')) {
    /** Clientes (rol CLI vigente) sin zona propia a $fecha, con búsqueda. Máx. $limite. */
    function vdis_clientes_sin_zona(mysqli $conexion, int $empresa_id, ?string $fecha = null, string $q = '', int $limite = 300): array
    {
        $fecha = vcar_fecha($fecha);
        $like = '%' . trim($q) . '%';
        return vcar_filas($conexion,
            "SELECT e.entidad_id AS cliente_entidad_id, e.entidad_nombre, e.entidad_fantasia, e.cuit
               FROM gestion__entidades e
              WHERE e.empresa_id = ? AND e.tabla_estado_registro_id = 1 AND " . ent_sql_tiene_rol('e.entidad_id', 'CLI') . "
                AND (e.entidad_nombre LIKE ? OR IFNULL(e.entidad_fantasia, '') LIKE ? OR IFNULL(e.cuit, '') LIKE ?)
                AND NOT EXISTS (SELECT 1 FROM gestion__distribucion_zonas_clientes zc
                                 WHERE zc.empresa_id = e.empresa_id AND zc.cliente_entidad_id = e.entidad_id AND zc.tabla_estado_registro_id = 1
                                   AND zc.f_desde <= ? AND (zc.f_hasta IS NULL OR zc.f_hasta >= ?))
              ORDER BY e.entidad_nombre LIMIT " . max(1, min(1000, $limite)),
            'isssss', [$empresa_id, $like, $like, $like, $fecha, $fecha]);
    }
}

// ----------------------------------------------------------------------------------------------- camiones

if (!function_exists('vdis_camiones')) {
    function vdis_camiones(mysqli $conexion, int $empresa_id, bool $incluir_inactivos = false): array
    {
        return vcar_filas($conexion,
            "SELECT c.*, b.boca_nombre FROM gestion__distribucion_camiones c LEFT JOIN gestion__bocas b ON b.boca_id = c.boca_id
              WHERE c.empresa_id = ?" . ($incluir_inactivos ? '' : ' AND c.tabla_estado_registro_id = 1') . " ORDER BY c.nombre", 'i', [$empresa_id]);
    }
}

if (!function_exists('vdis_camion_guardar')) {
    /** Alta (camion_id 0) o edición. Devuelve el id. @throws InvalidArgumentException */
    function vdis_camion_guardar(mysqli $conexion, int $empresa_id, array $d, int $usuario_id): int
    {
        $id = intval($d['camion_id'] ?? 0);
        $nombre = mb_substr(trim((string) ($d['nombre'] ?? '')), 0, 100);
        if ($nombre === '') {
            throw new InvalidArgumentException('Falta el nombre del camión.');
        }
        $patente = strtoupper(mb_substr(preg_replace('/\s+/', '', (string) ($d['patente'] ?? '')), 0, 15));
        $patente = $patente === '' ? null : $patente;
        $kg = vdis_num($d['capacidad_kg'] ?? null, 'Capacidad en kg');
        $m3 = vdis_num($d['capacidad_m3'] ?? null, 'Capacidad en m³');
        $boca = intval($d['boca_id'] ?? 0) ?: null;
        if ($boca !== null && !vdis_es_deposito($conexion, $empresa_id, $boca)) {
            throw new InvalidArgumentException('El depósito no es de esta empresa.');
        }
        $chofer = mb_substr(trim((string) ($d['chofer'] ?? '')), 0, 100);
        $chofer = $chofer === '' ? null : $chofer;
        if ($patente !== null && vcar_filas($conexion, "SELECT 1 FROM gestion__distribucion_camiones WHERE empresa_id = ? AND patente = ? AND tabla_estado_registro_id = 1 AND camion_id <> ?",
                'isi', [$empresa_id, $patente, $id])) {
            throw new InvalidArgumentException('Ya hay un camión activo con esa patente.');
        }
        if ($id > 0) {
            if (!vcar_filas($conexion, "SELECT 1 FROM gestion__distribucion_camiones WHERE camion_id = ? AND empresa_id = ?", 'ii', [$id, $empresa_id])) {
                throw new InvalidArgumentException('El camión no existe.');
            }
            vcar_ejecutar($conexion, "UPDATE gestion__distribucion_camiones SET nombre = ?, patente = ?, capacidad_kg = ?, capacidad_m3 = ?, boca_id = ?, chofer = ?, actualizado_en = NOW()
                                      WHERE camion_id = ? AND empresa_id = ?", 'ssddisii', [$nombre, $patente, $kg, $m3, $boca, $chofer, $id, $empresa_id]);
            return $id;
        }
        return vcar_ejecutar($conexion, "INSERT INTO gestion__distribucion_camiones (empresa_id, nombre, patente, capacidad_kg, capacidad_m3, boca_id, chofer, creado_por)
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)", 'issddisi', [$empresa_id, $nombre, $patente, $kg, $m3, $boca, $chofer, $usuario_id], true);
    }
}

if (!function_exists('vdis_camion_estado')) {
    function vdis_camion_estado(mysqli $conexion, int $empresa_id, int $camion_id, int $estado): void
    {
        if (!in_array($estado, [1, 2], true)) {
            throw new InvalidArgumentException('Estado inválido.');
        }
        if (!vcar_filas($conexion, "SELECT 1 FROM gestion__distribucion_camiones WHERE camion_id = ? AND empresa_id = ?", 'ii', [$camion_id, $empresa_id])) {
            throw new InvalidArgumentException('El camión no existe.');
        }
        vcar_ejecutar($conexion, "UPDATE gestion__distribucion_camiones SET tabla_estado_registro_id = ?, actualizado_en = NOW() WHERE camion_id = ? AND empresa_id = ?",
            'iii', [$estado, $camion_id, $empresa_id]);
    }
}

// ----------------------------------------------------------------------------------------------- peso y volumen

if (!function_exists('vdis_pesos_pedidos')) {
    /**
     * Peso (kg) y volumen (m³) de lo que falta entregar de cada pedido: [venta_pedido_id => ['peso_kg', 'volumen_m3',
     * 'sin_peso' (líneas sin dato), 'sin_volumen', 'lineas']].
     * Línea con presentación: cajas pendientes × peso_bruto_kg y × largo × ancho × alto / 1.000.000. Sin
     * presentación: la presentación de factor 1 del producto si tiene datos, si no gestion__productos.peso (kg por
     * unidad) y volumen sin dato.
     */
    function vdis_pesos_pedidos(mysqli $conexion, int $empresa_id, array $pedido_ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $pedido_ids))));
        if (!$ids) {
            return [];
        }
        $con_pres = vcar_existe($conexion, 'gestion__ventas_pedidos_detalles', 'presentacion_id') && vcar_existe($conexion, 'gestion__productos_presentaciones');
        $pres_cols = $con_pres ? 'd.presentacion_id, d.presentacion_factor,' : 'NULL AS presentacion_id, NULL AS presentacion_factor,';
        $join_pres = $con_pres
            ? "LEFT JOIN gestion__productos_presentaciones pp ON pp.presentacion_id = d.presentacion_id
               LEFT JOIN gestion__productos_presentaciones pu ON pu.producto_id = d.producto_id AND pu.empresa_id = vp.empresa_id AND pu.factor = 1
                    AND pu.tabla_estado_registro_id = 1 AND pu.presentacion_id = (SELECT MIN(x.presentacion_id) FROM gestion__productos_presentaciones x
                         WHERE x.producto_id = d.producto_id AND x.empresa_id = vp.empresa_id AND x.factor = 1 AND x.tabla_estado_registro_id = 1)"
            : '';
        $pp_cols = $con_pres
            ? 'pp.factor AS p_factor, pp.peso_bruto_kg AS p_peso, pp.largo_cm AS p_l, pp.ancho_cm AS p_a, pp.alto_cm AS p_h,
               pu.peso_bruto_kg AS u_peso, pu.largo_cm AS u_l, pu.ancho_cm AS u_a, pu.alto_cm AS u_h,'
            : 'NULL AS p_factor, NULL AS p_peso, NULL AS p_l, NULL AS p_a, NULL AS p_h, NULL AS u_peso, NULL AS u_l, NULL AS u_a, NULL AS u_h,';
        $out = [];
        foreach (array_chunk($ids, 300) as $lote) {
            $filas = vcar_filas($conexion,
                "SELECT d.venta_pedido_id, d.producto_id, d.cantidad, IFNULL(d.cantidad_entregada, 0) AS entregada, $pres_cols $pp_cols p.peso AS prod_peso
                   FROM gestion__ventas_pedidos_detalles d
                   JOIN gestion__ventas_pedidos vp ON vp.venta_pedido_id = d.venta_pedido_id AND vp.empresa_id = ?
                   LEFT JOIN gestion__productos p ON p.producto_id = d.producto_id
                   $join_pres
                  WHERE d.venta_pedido_id IN (" . implode(',', array_fill(0, count($lote), '?')) . ") AND d.tabla_estado_registro_id NOT IN (2, 6)",
                'i' . str_repeat('i', count($lote)), array_merge([$empresa_id], $lote));
            foreach ($lote as $id) {
                $out[$id] = ['peso_kg' => 0.0, 'volumen_m3' => 0.0, 'sin_peso' => 0, 'sin_volumen' => 0, 'lineas' => 0];
            }
            foreach ($filas as $f) {
                $id = (int) $f['venta_pedido_id'];
                $pend = max(0.0, (float) $f['cantidad'] - (float) $f['entregada']);
                if ($pend <= 0) {
                    continue;
                }
                $out[$id]['lineas']++;
                $peso = null;
                $vol = null;
                if (!empty($f['presentacion_id']) && (float) ($f['p_factor'] ?? $f['presentacion_factor'] ?? 0) > 0) {
                    $cajas = $pend / (float) ($f['p_factor'] ?: $f['presentacion_factor']);
                    $peso = (float) $f['p_peso'] > 0 ? $cajas * (float) $f['p_peso'] : null;
                    $vol = ((float) $f['p_l'] > 0 && (float) $f['p_a'] > 0 && (float) $f['p_h'] > 0) ? $cajas * $f['p_l'] * $f['p_a'] * $f['p_h'] / 1000000 : null;
                } else {
                    if ((float) $f['u_peso'] > 0) {
                        $peso = $pend * (float) $f['u_peso'];
                    } elseif ((float) $f['prod_peso'] > 0) {
                        $peso = $pend * (float) $f['prod_peso'];
                    }
                    $vol = ((float) $f['u_l'] > 0 && (float) $f['u_a'] > 0 && (float) $f['u_h'] > 0) ? $pend * $f['u_l'] * $f['u_a'] * $f['u_h'] / 1000000 : null;
                }
                if ($peso === null) {
                    $out[$id]['sin_peso']++;
                } else {
                    $out[$id]['peso_kg'] += $peso;
                }
                if ($vol === null) {
                    $out[$id]['sin_volumen']++;
                } else {
                    $out[$id]['volumen_m3'] += $vol;
                }
            }
        }
        foreach ($out as &$o) {
            $o['peso_kg'] = round($o['peso_kg'], 3);
            $o['volumen_m3'] = round($o['volumen_m3'], 4);
        }
        unset($o);
        return $out;
    }
}

// ----------------------------------------------------------------------------------------------- ganchos del pedido

if (!function_exists('vdis_filtrar_botones')) {
    /** Sin el indicador, saca los botones que llevan el pedido a 42 (las funciones existen en la configuración). */
    function vdis_filtrar_botones(mysqli $conexion, int $empresa_id, array $botones): array
    {
        if (vdis_empresa_usa($conexion, $empresa_id)) {
            return $botones;
        }
        return array_values(array_filter($botones, fn($b) => (int) ($b['estado_destino_id'] ?? 0) !== VDIS_ESTADO_PARA_DISTRIBUCION));
    }
}

if (!function_exists('vdis_en_hoja')) {
    /** Hoja activa (Borrador o Confirmada) en la que está el pedido (['hoja_id', 'numero', 'estado']) o null. */
    function vdis_en_hoja(mysqli $conexion, int $venta_pedido_id): ?array
    {
        $r = vcar_filas($conexion,
            "SELECT h.hoja_id, h.numero, h.tabla_estado_registro_id AS estado FROM gestion__distribucion_hojas_pedidos hp
               JOIN gestion__distribucion_hojas h ON h.hoja_id = hp.hoja_id
              WHERE hp.venta_pedido_id = ? AND hp.tabla_estado_registro_id = 1 AND h.tabla_estado_registro_id IN (3, 5)
              ORDER BY h.hoja_id DESC LIMIT 1", 'i', [$venta_pedido_id]);
        return $r ? $r[0] : null;
    }
}

if (!function_exists('vdis_antes_de_transicion')) {
    /**
     * Gancho de ejecutarTransicionEstado (dentro de la transacción), según el estado destino:
     * - a 42: exige el indicador y completa el depósito de despacho con el de la sucursal del pedido si falta;
     * - de 42 a 5 (volver a revisión): no si está en una hoja confirmada; si está en una en borrador, lo saca.
     * @throws Exception con el mensaje para el usuario
     */
    function vdis_antes_de_transicion(mysqli $conexion, int $empresa_id, array $pedido, int $destino): void
    {
        $origen = (int) $pedido['tabla_estado_registro_id'];
        if ($destino !== VDIS_ESTADO_PARA_DISTRIBUCION && $origen !== VDIS_ESTADO_PARA_DISTRIBUCION) {
            return;
        }
        $id = (int) $pedido['venta_pedido_id'];
        if ($destino === VDIS_ESTADO_PARA_DISTRIBUCION) {
            // Solo para entrar a 42: si la empresa apagó el indicador, los pedidos que quedaron en 42 pueden salir
            if (!vdis_empresa_usa($conexion, $empresa_id)) {
                throw new Exception('La empresa no usa distribución (Configuración > Empresas).');
            }
            vcar_ejecutar($conexion, "UPDATE gestion__ventas_pedidos SET boca_despacho_id = ? WHERE venta_pedido_id = ? AND boca_despacho_id IS NULL",
                'ii', [vdis_deposito_de_sucursal($conexion, $empresa_id, (int) $pedido['sucursal_id']), $id]);
            return;
        }
        if ($destino === 5 && vdis_estructura($conexion)) {
            $hoja = vdis_en_hoja($conexion, $id);
            if ($hoja && (int) $hoja['estado'] !== 3) {
                throw new Exception('El pedido está en la hoja de ruta N° ' . $hoja['numero'] . ', ya confirmada: sacalo de la hoja antes.');
            }
            if ($hoja) {
                vcar_ejecutar($conexion, "UPDATE gestion__distribucion_hojas_pedidos SET tabla_estado_registro_id = 2, actualizado_en = NOW()
                                          WHERE hoja_id = ? AND venta_pedido_id = ? AND tabla_estado_registro_id = 1", 'ii', [(int) $hoja['hoja_id'], $id]);
            }
        }
    }
}
