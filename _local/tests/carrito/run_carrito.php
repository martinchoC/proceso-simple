<?php
// Corre una prueba del carrito contra una base de pruebas, con un .env temporal armado con las credenciales del ERP
// (sin imprimirlas ni copiarlas a ninguna carpeta del proyecto). Los parámetros ECOM_* van fijos (empresa 2, sucursal 1,
// tipo de pedido 1, tabla de pedidos 82) para que el carrito no lea ni escriba su caché de parámetros (storage/cache).
// Uso: php _local/tests/carrito/run_carrito.php <prueba.php> [base=gestion_multipyme_sim] [erp_core_path|-] [carpeta del carrito] [args...]
//   prueba: tests/<prueba> del carrito, o _local/tests/carrito/<prueba> del ERP (recibe la carpeta del carrito en
//   ECOM_CARRITO_DIR). erp_core_path "-" = sin integración. Carpeta del carrito: por defecto ../casalucho-carrito/ecommerce_app
//   (otra, p. ej. un worktree de la línea base, para comparar antes / después).
$test = $argv[1] ?? '';
$base = $argv[2] ?? 'gestion_multipyme_sim';
$raiz = realpath(__DIR__ . '/../../..');
$core = ($argv[3] ?? '') === '-' ? '' : (($argv[3] ?? '') !== '' ? $argv[3] : $raiz);
$carrito = realpath(($argv[4] ?? '') !== '' ? $argv[4] : $raiz . '/../casalucho-carrito/ecommerce_app');
$extra = array_slice($argv, 5);
if ($base === 'gestion_multipyme') {
    exit("No se corre contra el maestro.\n");
}
$script = is_file("$carrito/tests/$test") ? "$carrito/tests/$test" : (is_file(__DIR__ . "/$test") ? __DIR__ . "/$test" : '');
if (!$carrito || $script === '') {
    exit("No existe la prueba $test.\n");
}
define('DB_NAME', $base);
set_error_handler(function ($no, $str) { return strpos($str, 'already defined') !== false; });
require_once "$raiz/.env";
restore_error_handler();
$env = tempnam(sys_get_temp_dir(), 'ecomenv');
file_put_contents($env, implode("\n", [
    'DB_HOST=' . IP_HOST, 'DB_PORT=3306', 'DB_NAME=' . $base, 'DB_USER=' . DB_USER, 'DB_PASS="' . DB_PASS . '"',
    'APP_KEY=' . bin2hex(random_bytes(32)), 'APP_ENV=testing', 'APP_DEBUG=true', 'SESSION_SECURE=false',
    'ECOM_EMPRESA_ID=2', 'ECOM_MODULO_ID=2', 'ECOM_SUCURSAL_ID=1', 'ECOM_MONEDA_ID=1', 'ECOM_COMPROBANTE_TIPO_PEDIDO_ID=1',
    'ECOM_TABLA_ORIGEN_PEDIDOS_ID=82', 'ECOM_ENTIDAD_DOC_COLUMNA=cuit', 'ERP_CORE_PATH=' . $core,
]) . "\n");
try {
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script) . ' ' . implode(' ', array_map('escapeshellarg', $extra));
    $proc = proc_open($cmd, [1 => STDOUT, 2 => STDERR], $pipes, $carrito,
        ['ECOM_TEST_ENV' => $env, 'ECOM_TEST_BASE' => $base, 'ECOM_CARRITO_DIR' => $carrito] + getenv());
    $codigo = proc_close($proc);
} finally {
    @unlink($env);
}
exit($codigo);
