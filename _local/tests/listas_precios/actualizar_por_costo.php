<?php
/**
 * Prueba de comparación del otro motor de precios (precios_core_model.php: actualizarListasPreciosPorRegla, el
 * que usan "Actualizar costo y lista de precios" y la confirmación de compras). Solo desarrollo, solo CLI.
 *
 * Restaura las tablas de listas desde zz_lp_* (las deja foto_precios.php), simula un aumento de costo del 10 %
 * en todos los productos activos con costo de la empresa y guarda la foto de precios vigentes en
 * salidas/<etiqueta>.csv (mismo formato que foto_precios.php, para comparar con --comparar / --contra).
 *
 * Uso:
 *   php actualizar_por_costo.php --db=<base_prueba> --etiqueta=costo_base [--motor=<ruta a precios_core_model.php>] [--empresa=2]
 * --motor permite correr una copia del motor anterior (con el require_once de core/ ajustado a ruta absoluta).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Solo línea de comandos.\n");
}

$opt = getopt('', ['db:', 'etiqueta:', 'motor:', 'empresa:']);
$db = $opt['db'] ?? '';
if (!preg_match('/^[A-Za-z0-9_]+$/', $db) || $db === 'gestion_multipyme' || stripos($db, 'prueba') === false) {
    exit("Me niego: --db tiene que ser una copia de pruebas (el nombre debe contener 'prueba' y no ser el maestro).\n");
}
$etiqueta = $opt['etiqueta'] ?? '';
if (!preg_match('/^[A-Za-z0-9_-]+$/', $etiqueta)) {
    exit("Falta --etiqueta=<nombre>.\n");
}
$empresa = intval($opt['empresa'] ?? 2);
$raiz = realpath(__DIR__ . '/../../..');
$motor = $opt['motor'] ?? "$raiz/modules/gestion_normal/precios_core_model.php";

require_once $raiz . '/.env';
$p = mysqli_connect(IP_HOST, DB_USER, DB_PASS, $db);
if (!$p) {
    exit("No se pudo conectar a $db\n");
}
mysqli_set_charset($p, 'utf8mb4');
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once $motor;

if (mysqli_num_rows(mysqli_query($p, "SHOW TABLES LIKE 'zz_lp_gestion__listas_precios_productos'")) === 0) {
    exit("No hay copia zz_lp_*: correr antes foto_precios.php una vez.\n");
}
$tablas = ['gestion__listas_precios', 'gestion__listas_precios_productos', 'gestion__listas_precios_productos_historial'];
mysqli_query($p, 'SET FOREIGN_KEY_CHECKS = 0');
foreach ($tablas as $t) {
    mysqli_query($p, "DELETE FROM `$t`");
    // Columnas en común: la tabla pudo ganar columnas después de la copia
    $res = mysqli_query($p, "SELECT a.COLUMN_NAME FROM information_schema.COLUMNS a
                             JOIN information_schema.COLUMNS b ON b.TABLE_SCHEMA = a.TABLE_SCHEMA AND b.TABLE_NAME = 'zz_lp_$t' AND b.COLUMN_NAME = a.COLUMN_NAME
                             WHERE a.TABLE_SCHEMA = DATABASE() AND a.TABLE_NAME = '$t' ORDER BY a.ORDINAL_POSITION");
    $cols = [];
    while ($r = mysqli_fetch_row($res)) {
        $cols[] = "`{$r[0]}`";
    }
    $cols = implode(', ', $cols);
    mysqli_query($p, "INSERT INTO `$t` ($cols) SELECT $cols FROM `zz_lp_$t`");
}
mysqli_query($p, 'SET FOREIGN_KEY_CHECKS = 1');

$contexto = obtenerContextoCalculoPrecios($p, $empresa, 0);
$res = mysqli_query($p, "SELECT p.producto_id, COALESCE(p.producto_categoria_id, 0) cat, pc.producto_costo_id, pc.costo_actual
                         FROM gestion__productos p
                         JOIN gestion__productos_costos pc ON pc.producto_id = p.producto_id AND pc.empresa_id = p.empresa_id
                         WHERE p.empresa_id = $empresa AND p.tabla_estado_registro_id = 1 AND pc.costo_actual > 0
                         ORDER BY p.producto_id");
$tot = ['productos' => 0, 'precios_actualizados' => 0, 'precios_sin_regla' => 0, 'precios_manuales' => 0];
while ($r = mysqli_fetch_assoc($res)) {
    $costo = (float) $r['costo_actual'];
    $nuevo = round($costo * 1.10, 4);
    $out = actualizarListasPreciosPorRegla($p, (int) $r['producto_id'], (int) $r['cat'], 0, $nuevo,
        (int) $r['producto_costo_id'], $empresa, 1, $contexto, $costo, $nuevo);
    $tot['productos']++;
    foreach (['precios_actualizados', 'precios_sin_regla', 'precios_manuales'] as $k) {
        $tot[$k] += (int) ($out[$k] ?? 0);
    }
}
echo json_encode($tot) . "\n";

$dir = __DIR__ . '/salidas';
if (!is_dir($dir)) {
    mkdir($dir, 0777, true);
}
$res = mysqli_query($p, "SELECT empresa_id, lista_precio_id, producto_id, ROUND(precio_final, 2), es_manual,
                                COALESCE(lista_precio_regla_id, ''), ROUND(COALESCE(porcentaje_regla_aplicado, 0), 4)
                         FROM gestion__listas_precios_productos
                         WHERE tabla_estado_registro_id = 1 AND empresa_id = $empresa
                         ORDER BY empresa_id, lista_precio_id, producto_id, lista_precio_producto_id");
$f = fopen("$dir/$etiqueta.csv", 'w');
fputcsv($f, ['empresa_id', 'lista_precio_id', 'producto_id', 'precio_final', 'es_manual', 'regla_id', 'porcentaje']);
while ($r = mysqli_fetch_row($res)) {
    fputcsv($f, $r);
}
fclose($f);
echo "Foto guardada: salidas/$etiqueta.csv\n";
