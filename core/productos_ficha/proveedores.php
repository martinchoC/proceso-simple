<?php
/**
 * Solapa Proveedores de la ficha de producto del módulo 5 (doc 26): vínculos producto ↔ proveedor con el código del
 * proveedor (gestion__productos_proveedores). Mismas reglas que la solapa del módulo 2
 * (modules/gestion/productos_model.php: agregar / editar / eliminarProveedorProducto):
 *   - solo entidades con rol PRO activo de la empresa; un vínculo por proveedor y producto (si estaba dado de baja, se
 *     reactiva);
 *   - el principal con core/productos_proveedores.php (pp_marcar_principal / pp_asegurar_principal);
 *   - después de todo cambio se recalcula gestion__productos.proveedores_busqueda (búsqueda por código de proveedor);
 *   - la baja es lógica (estado 2) y da de baja también su precio de lista en gestion__productos_proveedores_precios.
 * Cada escritura va en su transacción.
 */

require_once __DIR__ . '/base.php';
require_once __DIR__ . '/../productos_proveedores.php';
require_once __DIR__ . '/../entidades_roles.php';

if (!function_exists('pfpv_listar')) {
    /** Vínculos activos del producto, el principal primero. */
    function pfpv_listar(mysqli $conexion, int $empresa_id, int $producto_id): array
    {
        $filas = pfic_filas($conexion,
            "SELECT pp.producto_proveedor_id, pp.entidad_id, pp.codigo_proveedor, " . pp_sql_es_principal('pp') . " AS es_principal,
                    COALESCE(NULLIF(TRIM(e.entidad_fantasia), ''), e.entidad_nombre) AS entidad_nombre, e.cuit
               FROM gestion__productos_proveedores pp
               LEFT JOIN gestion__entidades e ON e.entidad_id = pp.entidad_id
              WHERE pp.producto_id = ? AND pp.empresa_id = ? AND pp.tabla_estado_registro_id = 1
              ORDER BY es_principal DESC, entidad_nombre", 'ii', [$producto_id, $empresa_id]);
        foreach ($filas as &$f) {
            $f['es_principal'] = (int) $f['es_principal'];
        }
        return $filas;
    }
}

if (!function_exists('pfpv_proveedores')) {
    /** Proveedores activos de la empresa (rol PRO) para el selector. */
    function pfpv_proveedores(mysqli $conexion, int $empresa_id): array
    {
        return pfic_filas($conexion,
            "SELECT e.entidad_id, COALESCE(NULLIF(TRIM(e.entidad_fantasia), ''), e.entidad_nombre) AS entidad_nombre, e.cuit
               FROM gestion__entidades e
              WHERE e.empresa_id = ? AND e.tabla_estado_registro_id = 1 AND " . ent_sql_tiene_rol('e.entidad_id', 'PRO') . "
              ORDER BY entidad_nombre", 'i', [$empresa_id]);
    }
}

if (!function_exists('pfpv_recalcular_busqueda')) {
    /** gestion__productos.proveedores_busqueda (misma regla que recalcularProveedoresBusqueda del módulo 2). */
    function pfpv_recalcular_busqueda(mysqli $conexion, int $producto_id): void
    {
        pfic_ejecutar($conexion,
            "UPDATE gestion__productos p
                SET p.proveedores_busqueda = (SELECT GROUP_CONCAT(DISTINCT pp.codigo_proveedor SEPARATOR ' ')
                                                FROM gestion__productos_proveedores pp
                                               WHERE pp.producto_id = p.producto_id AND pp.tabla_estado_registro_id = 1
                                                 AND pp.codigo_proveedor IS NOT NULL AND pp.codigo_proveedor <> '')
              WHERE p.producto_id = ?", 'i', [$producto_id]);
    }
}

if (!function_exists('pfpv_es_proveedor')) {
    function pfpv_es_proveedor(mysqli $conexion, int $empresa_id, int $entidad_id): bool
    {
        return (bool) pfic_filas($conexion,
            "SELECT 1 FROM gestion__entidades e WHERE e.entidad_id = ? AND e.empresa_id = ? AND e.tabla_estado_registro_id = 1 AND "
            . ent_sql_tiene_rol('e.entidad_id', 'PRO'), 'ii', [$entidad_id, $empresa_id]);
    }
}

if (!function_exists('pfpv_guardar')) {
    /**
     * Alta ($producto_proveedor_id = 0) o edición de un vínculo. Devuelve el producto_proveedor_id.
     * @throws InvalidArgumentException
     */
    function pfpv_guardar(mysqli $conexion, int $empresa_id, int $producto_id, int $producto_proveedor_id, int $entidad_id, string $codigo, bool $principal): int
    {
        $codigo = trim($codigo);
        if (mb_strlen($codigo) > 50) {
            throw new InvalidArgumentException('El código del proveedor admite hasta 50 caracteres.');
        }
        if ($entidad_id <= 0) {
            throw new InvalidArgumentException('Elija el proveedor.');
        }
        pfic_producto_de_empresa($conexion, $empresa_id, $producto_id);
        mysqli_begin_transaction($conexion);
        try {
            if ($producto_proveedor_id > 0) {
                $actual = pfic_filas($conexion,
                    "SELECT entidad_id FROM gestion__productos_proveedores WHERE producto_proveedor_id = ? AND empresa_id = ? AND producto_id = ? AND tabla_estado_registro_id = 1",
                    'iii', [$producto_proveedor_id, $empresa_id, $producto_id]);
                if (!$actual) {
                    throw new InvalidArgumentException('El vínculo ya no existe.');
                }
                if ((int) $actual[0]['entidad_id'] !== $entidad_id) {
                    if (!pfpv_es_proveedor($conexion, $empresa_id, $entidad_id)) {
                        throw new InvalidArgumentException('La entidad elegida no es un proveedor de la empresa.');
                    }
                    if (pfic_filas($conexion, "SELECT 1 FROM gestion__productos_proveedores WHERE producto_id = ? AND entidad_id = ? AND empresa_id = ? AND producto_proveedor_id <> ?",
                        'iiii', [$producto_id, $entidad_id, $empresa_id, $producto_proveedor_id])) {
                        throw new InvalidArgumentException('Ese proveedor ya está vinculado al producto.');
                    }
                }
                pfic_ejecutar($conexion, "UPDATE gestion__productos_proveedores SET entidad_id = ?, codigo_proveedor = ? WHERE producto_proveedor_id = ? AND empresa_id = ?",
                    'isii', [$entidad_id, $codigo, $producto_proveedor_id, $empresa_id]);
                $id = $producto_proveedor_id;
            } else {
                if (!pfpv_es_proveedor($conexion, $empresa_id, $entidad_id)) {
                    throw new InvalidArgumentException('La entidad elegida no es un proveedor de la empresa.');
                }
                $ex = pfic_filas($conexion, "SELECT producto_proveedor_id, tabla_estado_registro_id FROM gestion__productos_proveedores WHERE producto_id = ? AND entidad_id = ? AND empresa_id = ? LIMIT 1",
                    'iii', [$producto_id, $entidad_id, $empresa_id]);
                if ($ex && (int) $ex[0]['tabla_estado_registro_id'] === 1) {
                    throw new InvalidArgumentException('Ese proveedor ya está vinculado al producto.');
                }
                if ($ex) {
                    $id = (int) $ex[0]['producto_proveedor_id'];
                    pfic_ejecutar($conexion, "UPDATE gestion__productos_proveedores SET tabla_estado_registro_id = 1, codigo_proveedor = ? WHERE producto_proveedor_id = ?",
                        'si', [$codigo, $id]);
                } else {
                    $id = pfic_ejecutar($conexion, "INSERT INTO gestion__productos_proveedores (producto_id, entidad_id, empresa_id, codigo_proveedor, tabla_estado_registro_id) VALUES (?, ?, ?, ?, 1)",
                        'iiis', [$producto_id, $entidad_id, $empresa_id, $codigo], true);
                }
            }
            pfpv_recalcular_busqueda($conexion, $producto_id);
            if ($principal) {
                pp_marcar_principal($conexion, $empresa_id, $id);
            } else {
                pp_asegurar_principal($conexion, $empresa_id, $producto_id);
            }
            mysqli_commit($conexion);
            return $id;
        } catch (Throwable $e) {
            mysqli_rollback($conexion);
            throw $e;
        }
    }
}

if (!function_exists('pfpv_quitar')) {
    /** Baja lógica del vínculo y de su precio de lista del proveedor. @throws InvalidArgumentException */
    function pfpv_quitar(mysqli $conexion, int $empresa_id, int $producto_id, int $producto_proveedor_id): void
    {
        $f = pfic_filas($conexion, "SELECT 1 FROM gestion__productos_proveedores WHERE producto_proveedor_id = ? AND empresa_id = ? AND producto_id = ? AND tabla_estado_registro_id = 1",
            'iii', [$producto_proveedor_id, $empresa_id, $producto_id]);
        if (!$f) {
            throw new InvalidArgumentException('El vínculo ya no existe.');
        }
        mysqli_begin_transaction($conexion);
        try {
            pfic_ejecutar($conexion, "UPDATE gestion__productos_proveedores SET tabla_estado_registro_id = 2 WHERE producto_proveedor_id = ? AND empresa_id = ?",
                'ii', [$producto_proveedor_id, $empresa_id]);
            if (pfic_existe($conexion, 'gestion__productos_proveedores_precios')) {
                pfic_ejecutar($conexion, "UPDATE gestion__productos_proveedores_precios SET tabla_estado_registro_id = 2 WHERE producto_proveedor_id = ? AND empresa_id = ?",
                    'ii', [$producto_proveedor_id, $empresa_id]);
            }
            pfpv_recalcular_busqueda($conexion, $producto_id);
            pp_asegurar_principal($conexion, $empresa_id, $producto_id);
            mysqli_commit($conexion);
        } catch (Throwable $e) {
            mysqli_rollback($conexion);
            throw $e;
        }
    }
}

if (!function_exists('pfpv_principal')) {
    /** Marca el vínculo como principal del producto. @throws InvalidArgumentException */
    function pfpv_principal(mysqli $conexion, int $empresa_id, int $producto_id, int $producto_proveedor_id): void
    {
        if (!pfic_filas($conexion, "SELECT 1 FROM gestion__productos_proveedores WHERE producto_proveedor_id = ? AND empresa_id = ? AND producto_id = ? AND tabla_estado_registro_id = 1",
            'iii', [$producto_proveedor_id, $empresa_id, $producto_id])) {
            throw new InvalidArgumentException('El vínculo ya no existe.');
        }
        $r = pp_marcar_principal($conexion, $empresa_id, $producto_proveedor_id);
        if (empty($r['success'])) {
            throw new InvalidArgumentException($r['message'] ?? 'No se pudo marcar como principal.');
        }
    }
}
