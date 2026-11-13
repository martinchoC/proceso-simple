<?php
/**
 * Proveedor principal de un producto (gestion__productos_proveedores.es_principal).
 *
 * Un producto puede estar vinculado a varios proveedores; el vínculo principal es el único cuya lista
 * de precios (Lista Precios Proveedores, proveedor_precios.php) actualiza el costo y los precios de
 * venta del producto. Regla: una sola fila activa con es_principal = 1 por empresa + producto; si no
 * hay ninguna marcada, vale la primera relación activa (menor producto_proveedor_id).
 *
 * - Escribir: pp_marcar_principal() (elección del usuario) y pp_asegurar_principal() después de todo
 *   alta, reactivación, baja o cambio de proveedor de un vínculo.
 * - Leer en SQL: pp_sql_es_principal('pp'), que aplica la regla aunque la columna no esté al día.
 *
 * Columna creada por _local/sql/mantenimiento/20261022_productos_proveedores_principal_01.sql. Lo
 * usan gestion y gestion_normal (productos, productos_proveedores, proveedor_precios, precios_core,
 * ordenes_compra, facturas_proveedores). Las funciones no abren transacción.
 */

if (!function_exists('pp_sql_es_principal')) {
    /**
     * Expresión SQL (0/1) "este vínculo es el principal de su producto" para el alias dado de
     * gestion__productos_proveedores: activo y marcado, o activo, sin ningún otro marcado y el primero.
     */
    function pp_sql_es_principal($alias = 'pp')
    {
        $a = preg_replace('/[^A-Za-z0-9_.]/', '', $alias);
        return "($a.tabla_estado_registro_id = 1 AND ($a.es_principal = 1 OR (
                    NOT EXISTS (SELECT 1 FROM gestion__productos_proveedores ppx
                                WHERE ppx.producto_id = $a.producto_id AND ppx.empresa_id = $a.empresa_id
                                  AND ppx.tabla_estado_registro_id = 1 AND ppx.es_principal = 1)
                    AND $a.producto_proveedor_id = (SELECT MIN(ppm.producto_proveedor_id) FROM gestion__productos_proveedores ppm
                                                    WHERE ppm.producto_id = $a.producto_id AND ppm.empresa_id = $a.empresa_id
                                                      AND ppm.tabla_estado_registro_id = 1))))";
    }
}

if (!function_exists('pp_es_principal')) {
    /** ¿El proveedor es el principal del producto en la empresa? (vínculo activo, regla de pp_sql_es_principal) */
    function pp_es_principal($conexion, $empresa_id, $entidad_id, $producto_id)
    {
        $empresa_id = intval($empresa_id);
        $entidad_id = intval($entidad_id);
        $producto_id = intval($producto_id);
        $stmt = mysqli_prepare($conexion, "SELECT MAX(" . pp_sql_es_principal('pp') . ") AS es_principal
                                           FROM gestion__productos_proveedores pp
                                           WHERE pp.empresa_id = ? AND pp.entidad_id = ? AND pp.producto_id = ?
                                             AND pp.tabla_estado_registro_id = 1");
        if (!$stmt) {
            error_log("pp_es_principal: " . mysqli_error($conexion));
            return false;
        }
        mysqli_stmt_bind_param($stmt, "iii", $empresa_id, $entidad_id, $producto_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return intval($row['es_principal'] ?? 0) === 1;
    }
}

if (!function_exists('pp_asegurar_principal')) {
    /**
     * Deja exactamente un principal entre los vínculos activos del producto: saca la marca a los
     * inactivos, conserva el marcado de menor id y, si no hay ninguno, marca la primera relación.
     * Devuelve el producto_proveedor_id principal (0 si el producto no tiene vínculos activos).
     */
    function pp_asegurar_principal($conexion, $empresa_id, $producto_id)
    {
        $empresa_id = intval($empresa_id);
        $producto_id = intval($producto_id);
        if ($empresa_id <= 0 || $producto_id <= 0) return 0;

        $stmt = mysqli_prepare($conexion, "SELECT producto_proveedor_id, es_principal, tabla_estado_registro_id
                                           FROM gestion__productos_proveedores
                                           WHERE empresa_id = ? AND producto_id = ?
                                           ORDER BY producto_proveedor_id");
        if (!$stmt) {
            error_log("pp_asegurar_principal: " . mysqli_error($conexion));
            return 0;
        }
        mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $producto_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $principal = 0;
        $primero = 0;
        $desmarcar = [];
        while ($row = mysqli_fetch_assoc($res)) {
            $id = intval($row['producto_proveedor_id']);
            $activo = intval($row['tabla_estado_registro_id']) === 1;
            $marcado = intval($row['es_principal']) === 1;
            if ($activo && $primero === 0) $primero = $id;
            if ($marcado && (!$activo || $principal > 0)) {
                $desmarcar[] = $id;
            } elseif ($marcado) {
                $principal = $id;
            }
        }
        mysqli_stmt_close($stmt);

        if ($desmarcar) {
            $ph = implode(',', array_fill(0, count($desmarcar), '?'));
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__productos_proveedores SET es_principal = 0 WHERE producto_proveedor_id IN ($ph)");
            mysqli_stmt_bind_param($stmt, str_repeat('i', count($desmarcar)), ...$desmarcar);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        if ($principal === 0 && $primero > 0) {
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__productos_proveedores SET es_principal = 1 WHERE producto_proveedor_id = ?");
            mysqli_stmt_bind_param($stmt, "i", $primero);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $principal = $primero;
        }
        return $principal;
    }
}

if (!function_exists('pp_marcar_principal')) {
    /**
     * Marca un vínculo activo de la empresa como principal de su producto y desmarca los demás.
     * Devuelve ['success' => bool, 'message' => string, 'producto_id' => int].
     */
    function pp_marcar_principal($conexion, $empresa_id, $producto_proveedor_id)
    {
        $empresa_id = intval($empresa_id);
        $producto_proveedor_id = intval($producto_proveedor_id);

        $stmt = mysqli_prepare($conexion, "SELECT producto_id, tabla_estado_registro_id FROM gestion__productos_proveedores
                                           WHERE producto_proveedor_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmt, "ii", $producto_proveedor_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $vinculo = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$vinculo) {
            return ['success' => false, 'message' => 'Vínculo no encontrado', 'producto_id' => 0];
        }
        if (intval($vinculo['tabla_estado_registro_id']) !== 1) {
            return ['success' => false, 'message' => 'Solo un proveedor activo puede ser el principal', 'producto_id' => 0];
        }
        $producto_id = intval($vinculo['producto_id']);

        $stmt = mysqli_prepare($conexion, "UPDATE gestion__productos_proveedores
                                           SET es_principal = IF(producto_proveedor_id = ?, 1, 0)
                                           WHERE producto_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmt, "iii", $producto_proveedor_id, $producto_id, $empresa_id);
        $ok = mysqli_stmt_execute($stmt);
        $error = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);

        if (!$ok) {
            error_log("pp_marcar_principal($producto_proveedor_id): $error");
            return ['success' => false, 'message' => 'Error al marcar el proveedor principal', 'producto_id' => $producto_id];
        }
        return ['success' => true, 'message' => 'Proveedor principal actualizado', 'producto_id' => $producto_id];
    }
}
