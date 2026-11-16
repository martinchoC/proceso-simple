<?php
/**
 * Canales del pedido de venta (doc 28): indicadores por empresa del carrito integrado y de la aplicación del vendedor
 * (script 20261123_canales_carrito_app_01.sql). Lectura tolerante: sin la columna, apagado (sin error), así el código
 * nuevo corre igual contra una base sin migrar; también la del carrito, que lee la misma base.
 */

const PVEN_INDICADORES = [
    'carrito' => 'usa_carrito_integrado',
    'app' => 'usa_app_vendedor',
];

if (!function_exists('pven_empresa_usa')) {
    /** ¿La empresa usa el canal ('carrito' | 'app')? Sin la columna o con un canal desconocido: false. */
    function pven_empresa_usa(mysqli $conexion, int $empresa_id, string $canal): bool
    {
        static $columnas = [];
        static $cache = [];
        $col = PVEN_INDICADORES[$canal] ?? null;
        if ($col === null || $empresa_id <= 0) {
            return false;
        }
        $clave = $canal . ':' . $empresa_id;
        if (isset($cache[$clave])) {
            return $cache[$clave];
        }
        if (!isset($columnas[$col])) {
            $res = mysqli_query($conexion, "SHOW COLUMNS FROM conf__empresas LIKE '$col'");
            $columnas[$col] = $res && mysqli_num_rows($res) > 0;
        }
        $usa = false;
        if ($columnas[$col]) {
            $st = mysqli_prepare($conexion, "SELECT `$col` FROM conf__empresas WHERE empresa_id = ?");
            mysqli_stmt_bind_param($st, 'i', $empresa_id);
            mysqli_stmt_execute($st);
            $r = mysqli_fetch_row(mysqli_stmt_get_result($st));
            mysqli_stmt_close($st);
            $usa = intval($r[0] ?? 0) === 1;
        }
        return $cache[$clave] = $usa;
    }
}
