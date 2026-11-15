<?php
/**
 * Ficha de producto completa (doc 26): solapas Proveedores, Precios, Ubicaciones e Imágenes en la ficha de productos
 * del módulo 5 (gestion_normal). Las tablas son las mismas que usa la ficha del módulo 2; este código es del 5 y el 2
 * sigue con su lógica propia (modules/gestion/productos_model.php).
 *
 * - base.php        indicador y helpers de consulta (pfic_*)
 * - proveedores.php vínculos producto ↔ proveedor con el código del proveedor (pfpv_*)
 * - ubicaciones.php ubicaciones de depósito del producto y "Nueva ubicación" (pfub_*)
 * - imagenes.php    fotos del producto (pfim_*)
 * - (Precios no tiene backend acá: la solapa usa listas_precios_productos_ajax.php del módulo)
 * - acciones.php    acciones ficha_* de productos_ajax.php (pfic_ajax_acciones)
 *
 * Indicador conf__empresas.usa_ficha_producto_completa (script 20261121_ficha_producto_01.sql). Sin él (o sin la
 * columna) nada de esto responde.
 */

if (!function_exists('pfic_existe')) {
    function pfic_existe(mysqli $conexion, string $tabla, string $columna = ''): bool
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

if (!function_exists('pfic_empresa_usa')) {
    /** Indicador usa_ficha_producto_completa. Sin la columna: false. */
    function pfic_empresa_usa(mysqli $conexion, int $empresa_id): bool
    {
        static $cache = [];
        if (isset($cache[$empresa_id])) {
            return $cache[$empresa_id];
        }
        $usa = false;
        if ($empresa_id > 0 && pfic_existe($conexion, 'conf__empresas', 'usa_ficha_producto_completa')) {
            $r = pfic_filas($conexion, "SELECT usa_ficha_producto_completa FROM conf__empresas WHERE empresa_id = ?", 'i', [$empresa_id]);
            $usa = intval($r[0]['usa_ficha_producto_completa'] ?? 0) === 1;
        }
        return $cache[$empresa_id] = $usa;
    }
}

if (!function_exists('pfic_filas')) {
    function pfic_filas(mysqli $conexion, string $sql, string $tipos = '', array $params = []): array
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
        $filas = [];
        while ($res && ($f = mysqli_fetch_assoc($res))) {
            $filas[] = $f;
        }
        mysqli_stmt_close($st);
        return $filas;
    }
}

if (!function_exists('pfic_ejecutar')) {
    /** INSERT / UPDATE con parámetros. Devuelve el id insertado ($insert) o las filas afectadas. */
    function pfic_ejecutar(mysqli $conexion, string $sql, string $tipos, array $params, bool $insert = false): int
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

if (!function_exists('pfic_producto_de_empresa')) {
    /** El producto existe y es de la empresa; si no, lanza. */
    function pfic_producto_de_empresa(mysqli $conexion, int $empresa_id, int $producto_id): void
    {
        if ($producto_id <= 0 || !pfic_filas($conexion, "SELECT 1 FROM gestion__productos WHERE producto_id = ? AND empresa_id = ?", 'ii', [$producto_id, $empresa_id])) {
            throw new InvalidArgumentException('Guarde primero el producto.');
        }
    }
}
