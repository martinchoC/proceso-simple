<?php
// Corre una prueba del carrito (casalucho-carrito/ecommerce_app/tests/*.php) contra una base de pruebas, con un .env
// temporal armado con las credenciales del ERP (sin imprimirlas ni copiarlas a ninguna carpeta del proyecto).
// Uso: php _local/tests/carrito/run_carrito.php <TestArchivo.php> [base=gestion_multipyme_sim] [erp_core_path=raíz del ERP|-]
//   erp_core_path "-" = sin integración (ERP_CORE_PATH vacía).
$test = $argv[1] ?? '';
$base = $argv[2] ?? 'gestion_multipyme_sim';
$raiz = realpath(__DIR__ . '/../../..');
$core = ($argv[3] ?? '') === '-' ? '' : ($argv[3] ?? $raiz);
if ($base === 'gestion_multipyme') {
    exit("No se corre contra el maestro.\n");
}
$carrito = realpath($raiz . '/../casalucho-carrito/ecommerce_app');
if (!$carrito || !is_file("$carrito/tests/$test")) {
    exit("No existe la prueba $test en el carrito.\n");
}
define('DB_NAME', $base);
set_error_handler(function ($no, $str) { return strpos($str, 'already defined') !== false; });
require_once "$raiz/.env";
restore_error_handler();
$env = tempnam(sys_get_temp_dir(), 'ecomenv');
file_put_contents($env, implode("\n", [
    'DB_HOST=' . IP_HOST, 'DB_PORT=3306', 'DB_NAME=' . $base, 'DB_USER=' . DB_USER, 'DB_PASS="' . DB_PASS . '"',
    'APP_KEY=' . bin2hex(random_bytes(32)), 'APP_ENV=testing', 'APP_DEBUG=true', 'SESSION_SECURE=false',
    'ECOM_EMPRESA_ID=2', 'ECOM_ENTIDAD_DOC_COLUMNA=cuit', 'ERP_CORE_PATH=' . $core,
]) . "\n");
try {
    $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg("$carrito/tests/$test");
    $proc = proc_open($cmd, [1 => STDOUT, 2 => STDERR], $pipes, $carrito, ['ECOM_TEST_ENV' => $env, 'ECOM_TEST_BASE' => $base] + getenv());
    $codigo = proc_close($proc);
} finally {
    @unlink($env);
}
exit($codigo);
