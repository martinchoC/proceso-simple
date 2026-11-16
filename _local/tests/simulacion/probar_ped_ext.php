<?php
// Pedidos externos (doc 28, paso 9-3) contra una base SIN la estructura de la distribuidora (por defecto la copia
// local ps_gestion_casalucho): solo lectura (ofertas y valorización); el alta y la comparación con el ERP, apagado y
// prendido, están en casos/41_pedidos_externos.py.
// Uso: php _local/tests/simulacion/probar_ped_ext.php [base]
$base = $argv[1] ?? 'ps_gestion_casalucho';
if ($base === 'gestion_multipyme') {
    exit("No se corre contra el maestro.\n");
}
define('DB_NAME', $base);
set_error_handler(function ($no, $str) { return strpos($str, 'already defined') !== false; });
$raiz = realpath(__DIR__ . '/../../..');
require_once "$raiz/.env";
require_once "$raiz/core/pedidos_venta/externo.php";
$c = mysqli_connect(IP_HOST, DB_USER, DB_PASS, $base);
mysqli_set_charset($c, 'utf8mb4');
$ok = 0; $mal = 0;
$check = function ($n, $cond, $x = '') use (&$ok, &$mal) { echo ($cond ? '  OK    ' : '  FALLA ') . $n . ($cond ? '' : "  $x") . "\n"; $cond ? $ok++ : $mal++; };
$uno = fn($sql) => mysqli_fetch_row(mysqli_query($c, $sql))[0] ?? null;
$E = 2;
$estructura = (int) $uno("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conf__empresas' AND COLUMN_NAME IN ('usa_carrito_integrado','usa_venta_presentaciones','usa_reglas_precio_venta')");
$check("la base $base no tiene los indicadores", $estructura === 0, "$estructura columnas");
$cli = (int) $uno("SELECT ecc.entidad_id FROM gestion__entidades_condiciones_clientes ecc JOIN gestion__entidades e ON e.entidad_id = ecc.entidad_id AND e.empresa_id = $E
                   JOIN gestion__entidades_roles er ON er.entidad_id = e.entidad_id AND er.f_baja IS NULL
                   JOIN gestion__roles_entidades re ON re.rol_entidad_id = er.rol_entidad_id AND re.rol_entidad_codigo = 'CLI'
                   WHERE ecc.tabla_estado_registro_id = 1 AND ecc.lista_precio_id IS NOT NULL AND e.tabla_estado_registro_id = 1
                     AND ecc.f_desde <= CURDATE() AND (ecc.f_hasta IS NULL OR ecc.f_hasta >= CURDATE()) LIMIT 1");
$lista = (int) $uno("SELECT lista_precio_id FROM gestion__entidades_condiciones_clientes WHERE entidad_id = $cli AND tabla_estado_registro_id = 1 ORDER BY f_desde DESC LIMIT 1");
$pid = (int) $uno("SELECT lp.producto_id FROM gestion__listas_precios_productos lp JOIN gestion__productos p ON p.producto_id = lp.producto_id AND p.tabla_estado_registro_id = 1
                   WHERE lp.lista_precio_id = $lista AND lp.empresa_id = $E AND lp.tabla_estado_registro_id = 1 AND lp.precio_final > 0
                     AND lp.f_desde <= CURDATE() AND (lp.f_hasta IS NULL OR lp.f_hasta >= CURDATE()) LIMIT 1");
$check("datos de prueba (cliente $cli, lista $lista, producto $pid)", $cli > 0 && $pid > 0);
$check('indicadores apagados sin error', !pven_empresa_usa($c, $E, 'carrito') && !pven_empresa_usa($c, $E, 'app'));
try {
    $o = pven_ofertas($c, $E, $cli, [$pid], 1);
    $of = $o['productos'][$pid]['ofertas'][0] ?? null;
    $check('ofertas: la unidad con el precio de la lista', $of && $of['presentacion_id'] === null && $of['precio'] > 0 && $of['etiqueta'] === null, json_encode($o));
    $v = pven_valorizar($c, $E, $cli, [['producto_id' => $pid, 'cantidad' => 2, 'precio_unitario' => 1]], ['sucursal_id' => 1]);
    $d = $v['detalles'][0];
    $check('valorizar: precio de la lista (ignora el del navegador) y sin columnas nuevas',
        abs($d['precio_unitario'] - $of['precio']) < 0.000001 && !isset($d['presentacion_id']) && $v['cabecera']['total'] > 0, json_encode($v));
    try {
        pven_valorizar($c, $E, $cli, [['producto_id' => $pid, 'presentacion_id' => 999999, 'cantidad' => 1]]);
        $check('presentación sin la estructura se rechaza', false, 'no lanzó');
    } catch (InvalidArgumentException $e) {
        $check('presentación sin la estructura se rechaza', strpos($e->getMessage(), 'no se vende') !== false, $e->getMessage());
    }
} catch (Throwable $e) {
    $check('sin errores', false, get_class($e) . ': ' . $e->getMessage());
}
echo "Resultado: $ok OK, $mal fallas\n";
exit($mal ? 1 : 0);
