<?php
// Ejecuta un _ajax.php real de modules/gestion (o de otro módulo, p. ej. gestion_normal) como lo haría el navegador, contra la COPIA de la base.
// Uso: php run_ajax.php <archivo_ajax> <params.json> <base_copia>
//   params.json: {"_method": "GET"|"POST", "_usuario_id": 1, "_modulo": "gestion", "_empresa_id": 2, ...parámetros del request}
// DB_NAME se define antes de que db.php cargue .env (que la vuelve a definir): queda la copia.
$base = $argv[3] ?? 'gestion_multipyme_sim';
if ($base === 'gestion_multipyme' && ($argv[4] ?? '') !== 'permitir_base_real') {
    fwrite(STDERR, "run_ajax.php: no se ejecuta contra la base real\n");
    exit(2);
}
define('DB_NAME', $base);
set_error_handler(function ($no, $str) {
    return strpos($str, 'already defined') !== false;   // solo se silencia el aviso de DB_NAME
});
// .env se carga acá (db.php lo hace con require_once): así el aviso de DB_NAME ocurre con este manejador
// y no con el que instalan algunos _ajax.php (p. ej. arca_integraciones_ajax.php), que lo vuelve error.
require_once __DIR__ . '/../../.env';

$params = json_decode(file_get_contents($argv[2]), true);
$method = $params['_method'] ?? 'POST';
$usuario_id = intval($params['_usuario_id'] ?? 1);
$modulo = preg_replace('/[^a-z_]/', '', $params['_modulo'] ?? 'gestion') ?: 'gestion';
$empresa_id = intval($params['_empresa_id'] ?? 2);
unset($params['_method'], $params['_usuario_id'], $params['_modulo'], $params['_empresa_id']);

session_start();
$_SESSION['logueado'] = true;
$_SESSION['usuario_id'] = $usuario_id;
$_SESSION['empresa_id'] = $empresa_id;

$_SERVER['REQUEST_METHOD'] = $method;
$_SERVER['SCRIPT_NAME'] = '/modules/' . $modulo . '/' . $argv[1];
if ($method === 'GET') {
    $_GET = $params;
} else {
    $_POST = $params;
}
$_REQUEST = $params;

$gestion = realpath(__DIR__ . '/../../modules/' . $modulo);
chdir($gestion);
include $gestion . '/' . $argv[1];
