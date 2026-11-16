<?php
// Llama a una función de core/ contra la COPIA de la base, sin pasar por un _ajax.php (canales externos: carrito,
// app del vendedor; doc 28). La conexión mysqli va siempre como primer argumento.
// Uso: php run_core.php <params.json> <base_copia>
//   params.json: {"requires": ["core/pedidos_venta/externo.php"], "funcion": "pven_crear_externo", "args": [2, {...}]}
// Imprime {"ok": true, "resultado": ...} o {"ok": false, "error": "...", "tipo": "Clase"}.
$base = $argv[2] ?? 'gestion_multipyme_sim';
if ($base === 'gestion_multipyme') {
    fwrite(STDERR, "run_core.php: no se ejecuta contra la base real\n");
    exit(2);
}
define('DB_NAME', $base);
set_error_handler(function ($no, $str) {
    return strpos($str, 'already defined') !== false;
});
$raiz = realpath(__DIR__ . '/../../..');
require_once $raiz . '/.env';
$p = json_decode(file_get_contents($argv[1]), true);
$conexion = mysqli_connect(IP_HOST, DB_USER, DB_PASS, $base);
mysqli_set_charset($conexion, 'utf8mb4');
foreach ((array) ($p['requires'] ?? []) as $r) {
    if (!preg_match('#^core/[A-Za-z0-9_/]+\.php$#', $r)) {
        exit(json_encode(['ok' => false, 'error' => "require no permitido: $r"]));
    }
    require_once $raiz . '/' . $r;
}
$f = (string) ($p['funcion'] ?? '');
if (!preg_match('/^[a-z][a-z0-9_]*$/', $f) || !function_exists($f)) {
    exit(json_encode(['ok' => false, 'error' => "función desconocida: $f"]));
}
try {
    $res = $f($conexion, ...array_values((array) ($p['args'] ?? [])));
    echo json_encode(['ok' => true, 'resultado' => $res], JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage(), 'tipo' => get_class($e)], JSON_UNESCAPED_UNICODE);
}
