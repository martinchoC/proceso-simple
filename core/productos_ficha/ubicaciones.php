<?php
/**
 * Solapa Ubicaciones de la ficha de producto del módulo 5 (doc 26): en qué ubicaciones de depósito está el producto
 * (gestion__productos_ubicaciones → gestion__sucursales_ubicaciones: sucursal, boca, sección, estantería, estante,
 * posición). Mismas reglas que la solapa del módulo 2 (agregarUbicacionProducto / eliminarUbicacionProducto /
 * crearUbicacionSucursal de modules/gestion/productos_model.php), y además:
 *   - producto y ubicación tienen que ser de la empresa;
 *   - solo las sucursales del usuario (core/accesos_sucursales.php), para ver, asignar, quitar y crear.
 * La baja es lógica (estado 2); volver a asignar una ubicación dada de baja la reactiva.
 */

require_once __DIR__ . '/base.php';
require_once __DIR__ . '/../accesos_sucursales.php';

if (!function_exists('pfub_filtro')) {
    /** " AND col IN (...)" con las sucursales del usuario ('' = todas). */
    function pfub_filtro(mysqli $conexion, int $empresa_id, int $usuario_id, string $col): string
    {
        return $usuario_id > 0 ? AccesosSucursalesHelper::filtroSqlSucursal($conexion, $usuario_id, $empresa_id, $col) : '';
    }
}

if (!function_exists('pfub_campos')) {
    function pfub_campos(): string
    {
        return "su.sucursal_ubicacion_id, su.sucursal_id, su.boca_id, su.seccion, su.estanteria, su.estante, su.posicion, su.descripcion,
                s.sucursal_nombre, b.boca_nombre, b.codigo AS boca_codigo";
    }
}

if (!function_exists('pfub_listar')) {
    /** Ubicaciones activas del producto (de las sucursales del usuario). */
    function pfub_listar(mysqli $conexion, int $empresa_id, int $usuario_id, int $producto_id): array
    {
        return pfic_filas($conexion,
            "SELECT pu.producto_ubicacion_id, " . pfub_campos() . "
               FROM gestion__productos_ubicaciones pu
               JOIN gestion__sucursales_ubicaciones su ON su.sucursal_ubicacion_id = pu.sucursal_ubicacion_id
               LEFT JOIN gestion__sucursales s ON s.sucursal_id = su.sucursal_id
               LEFT JOIN gestion__bocas b ON b.boca_id = su.boca_id
              WHERE pu.producto_id = ? AND su.empresa_id = ? AND pu.tabla_estado_registro_id = 1"
            . pfub_filtro($conexion, $empresa_id, $usuario_id, 'su.sucursal_id') . "
              ORDER BY s.sucursal_nombre, b.boca_nombre, su.seccion, su.estanteria, su.estante, su.posicion", 'ii', [$producto_id, $empresa_id]);
    }
}

if (!function_exists('pfub_disponibles')) {
    /** Ubicaciones activas de la empresa para elegir (de las sucursales del usuario). */
    function pfub_disponibles(mysqli $conexion, int $empresa_id, int $usuario_id): array
    {
        return pfic_filas($conexion,
            "SELECT " . pfub_campos() . "
               FROM gestion__sucursales_ubicaciones su
               LEFT JOIN gestion__sucursales s ON s.sucursal_id = su.sucursal_id
               LEFT JOIN gestion__bocas b ON b.boca_id = su.boca_id
              WHERE su.empresa_id = ? AND su.tabla_estado_registro_id = 1"
            . pfub_filtro($conexion, $empresa_id, $usuario_id, 'su.sucursal_id') . "
              ORDER BY s.sucursal_nombre, b.boca_nombre, su.seccion, su.estanteria, su.estante, su.posicion", 'i', [$empresa_id]);
    }
}

if (!function_exists('pfub_sucursales')) {
    /** Sucursales del usuario con sus bocas activas (para "Nueva ubicación"). */
    function pfub_sucursales(mysqli $conexion, int $empresa_id, int $usuario_id): array
    {
        $suc = pfic_filas($conexion,
            "SELECT s.sucursal_id, s.sucursal_nombre FROM gestion__sucursales s WHERE s.empresa_id = ? AND s.tabla_estado_registro_id = 1"
            . pfub_filtro($conexion, $empresa_id, $usuario_id, 's.sucursal_id') . " ORDER BY s.sucursal_nombre", 'i', [$empresa_id]);
        $bocas = pfic_filas($conexion,
            "SELECT boca_id, sucursal_id, boca_nombre, codigo FROM gestion__bocas WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY orden, boca_nombre",
            'i', [$empresa_id]);
        foreach ($suc as &$s) {
            $s['bocas'] = array_values(array_filter($bocas, fn($b) => (int) $b['sucursal_id'] === (int) $s['sucursal_id']));
        }
        return $suc;
    }
}

if (!function_exists('pfub_ubicacion_valida')) {
    /** La ubicación es activa, de la empresa y de una sucursal del usuario; si no, lanza. */
    function pfub_ubicacion_valida(mysqli $conexion, int $empresa_id, int $usuario_id, int $sucursal_ubicacion_id): void
    {
        if ($sucursal_ubicacion_id <= 0 || !pfic_filas($conexion,
            "SELECT 1 FROM gestion__sucursales_ubicaciones su WHERE su.sucursal_ubicacion_id = ? AND su.empresa_id = ? AND su.tabla_estado_registro_id = 1"
            . pfub_filtro($conexion, $empresa_id, $usuario_id, 'su.sucursal_id'), 'ii', [$sucursal_ubicacion_id, $empresa_id])) {
            throw new InvalidArgumentException('Elija una ubicación de sus sucursales.');
        }
    }
}

if (!function_exists('pfub_asignar')) {
    /** Asigna la ubicación al producto (reactiva si estaba dada de baja). Devuelve el producto_ubicacion_id. */
    function pfub_asignar(mysqli $conexion, int $empresa_id, int $usuario_id, int $producto_id, int $sucursal_ubicacion_id): int
    {
        pfic_producto_de_empresa($conexion, $empresa_id, $producto_id);
        pfub_ubicacion_valida($conexion, $empresa_id, $usuario_id, $sucursal_ubicacion_id);
        $ex = pfic_filas($conexion, "SELECT producto_ubicacion_id, tabla_estado_registro_id FROM gestion__productos_ubicaciones WHERE producto_id = ? AND sucursal_ubicacion_id = ? ORDER BY producto_ubicacion_id LIMIT 1",
            'ii', [$producto_id, $sucursal_ubicacion_id]);
        if ($ex && (int) $ex[0]['tabla_estado_registro_id'] === 1) {
            throw new InvalidArgumentException('Esa ubicación ya está asignada al producto.');
        }
        if ($ex) {
            pfic_ejecutar($conexion, "UPDATE gestion__productos_ubicaciones SET tabla_estado_registro_id = 1 WHERE producto_ubicacion_id = ?",
                'i', [(int) $ex[0]['producto_ubicacion_id']]);
            return (int) $ex[0]['producto_ubicacion_id'];
        }
        return pfic_ejecutar($conexion, "INSERT INTO gestion__productos_ubicaciones (producto_id, sucursal_ubicacion_id, tabla_estado_registro_id) VALUES (?, ?, 1)",
            'ii', [$producto_id, $sucursal_ubicacion_id], true);
    }
}

if (!function_exists('pfub_quitar')) {
    /** Baja lógica de la ubicación del producto. */
    function pfub_quitar(mysqli $conexion, int $empresa_id, int $usuario_id, int $producto_id, int $producto_ubicacion_id): void
    {
        $f = pfic_filas($conexion,
            "SELECT pu.sucursal_ubicacion_id FROM gestion__productos_ubicaciones pu
               JOIN gestion__sucursales_ubicaciones su ON su.sucursal_ubicacion_id = pu.sucursal_ubicacion_id
              WHERE pu.producto_ubicacion_id = ? AND pu.producto_id = ? AND su.empresa_id = ? AND pu.tabla_estado_registro_id = 1"
            . pfub_filtro($conexion, $empresa_id, $usuario_id, 'su.sucursal_id'), 'iii', [$producto_ubicacion_id, $producto_id, $empresa_id]);
        if (!$f) {
            throw new InvalidArgumentException('La ubicación ya no está asignada.');
        }
        pfic_ejecutar($conexion, "UPDATE gestion__productos_ubicaciones SET tabla_estado_registro_id = 2 WHERE producto_ubicacion_id = ?", 'i', [$producto_ubicacion_id]);
    }
}

if (!function_exists('pfub_crear')) {
    /**
     * Ubicación nueva de la sucursal (mismas validaciones que crearUbicacionSucursal del 2: sucursal, boca, sección,
     * estantería, estante y posición obligatorios; no repetida en la sucursal / boca). Devuelve el sucursal_ubicacion_id.
     */
    function pfub_crear(mysqli $conexion, int $empresa_id, int $usuario_id, array $d): int
    {
        $sucursal_id = intval($d['sucursal_id'] ?? 0);
        $boca_id = intval($d['boca_id'] ?? 0);
        $t = [];
        foreach (['seccion' => 'la sección', 'estanteria' => 'la estantería', 'estante' => 'el estante', 'posicion' => 'la posición'] as $k => $nombre) {
            $t[$k] = trim((string) ($d[$k] ?? ''));
            if ($t[$k] === '') {
                throw new InvalidArgumentException('Falta ' . $nombre . '.');
            }
            if (mb_strlen($t[$k]) > 50) {
                throw new InvalidArgumentException('En ' . $nombre . ' entran hasta 50 caracteres.');
            }
        }
        $desc = mb_substr(trim((string) ($d['descripcion'] ?? '')), 0, 255);
        if ($sucursal_id <= 0 || !pfic_filas($conexion, "SELECT 1 FROM gestion__sucursales s WHERE s.sucursal_id = ? AND s.empresa_id = ? AND s.tabla_estado_registro_id = 1"
            . pfub_filtro($conexion, $empresa_id, $usuario_id, 's.sucursal_id'), 'ii', [$sucursal_id, $empresa_id])) {
            throw new InvalidArgumentException('Elija una de sus sucursales.');
        }
        if ($boca_id <= 0 || !pfic_filas($conexion, "SELECT 1 FROM gestion__bocas WHERE boca_id = ? AND sucursal_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1",
            'iii', [$boca_id, $sucursal_id, $empresa_id])) {
            throw new InvalidArgumentException('Elija una boca de la sucursal.');
        }
        if (pfic_filas($conexion, "SELECT 1 FROM gestion__sucursales_ubicaciones WHERE sucursal_id = ? AND boca_id = ? AND seccion = ? AND estanteria = ? AND estante = ? AND posicion = ?",
            'iissss', [$sucursal_id, $boca_id, $t['seccion'], $t['estanteria'], $t['estante'], $t['posicion']])) {
            throw new InvalidArgumentException('Esa ubicación ya existe en la sucursal / boca: elíjala de la lista.');
        }
        return pfic_ejecutar($conexion,
            "INSERT INTO gestion__sucursales_ubicaciones (empresa_id, sucursal_id, boca_id, seccion, estanteria, estante, posicion, descripcion, tabla_estado_registro_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)", 'iiisssss',
            [$empresa_id, $sucursal_id, $boca_id, $t['seccion'], $t['estanteria'], $t['estante'], $t['posicion'], $desc], true);
    }
}
