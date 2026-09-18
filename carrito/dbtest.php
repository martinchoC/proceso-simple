<?php

declare(strict_types=1);

/**
 * PRUEBA DE CONEXIÓN A LA BASE — archivo temporal.
 *
 * Subir a  public_html/ps_ecommerce/dbtest.php  y abrir:
 *     https://tudominio.com/ps_ecommerce/dbtest.php?clave=<primeros 16 de APP_KEY>
 *
 * Prueba el DB_HOST del .env y también las alternativas habituales, y muestra
 * el error EXACTO que devuelve MySQL en cada caso. Nunca imprime la contraseña.
 *
 * >>> BORRAR ESTE ARCHIVO CUANDO TERMINES <<<
 */

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$envPath = __DIR__ . '/ecommerce_app/.env';

if (!is_file($envPath)) {
    http_response_code(500);
    exit("No encontré ecommerce_app/.env junto a este archivo.\n");
}

// --- Leer el .env sin cargar la aplicación ----------------------------------
$env = [];
foreach (file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $linea) {
    $linea = trim($linea);
    if ($linea === '' || $linea[0] === '#' || $linea[0] === ';') {
        continue;
    }
    $pos = strpos($linea, '=');
    if ($pos === false) {
        continue;
    }
    $clave = trim(substr($linea, 0, $pos));
    $valor = trim(substr($linea, $pos + 1));
    $valor = trim(preg_replace('/\s+[;#].*$/', '', $valor) ?? $valor);
    $env[$clave] = trim($valor, "\"'");
}

// --- Autorización ------------------------------------------------------------
$appKey = (string) ($env['APP_KEY'] ?? '');
$esperado = substr($appKey, 0, 16);

if (strlen($esperado) >= 16) {
    if (!hash_equals($esperado, (string) ($_GET['clave'] ?? ''))) {
        http_response_code(404);
        exit("No encontrado.\n");
    }
} else {
    echo "*** APP_KEY vacía o muy corta: este archivo está SIN PROTEGER.\n";
    echo "*** Borralo apenas termines de diagnosticar.\n\n";
}

// --- Datos ------------------------------------------------------------------
$nombre = (string) ($env['DB_NAME'] ?? '');
$usuario = (string) ($env['DB_USER'] ?? '');
$clave = (string) ($env['DB_PASS'] ?? '');
$puerto = (int) ($env['DB_PORT'] ?? 3306);
$hostEnv = (string) ($env['DB_HOST'] ?? '');

echo "PRUEBA DE CONEXIÓN — " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('=', 62) . "\n\n";

printf("DB_NAME : %s\n", $nombre !== '' ? $nombre : '(VACÍO)');
printf("DB_USER : %s\n", $usuario !== '' ? $usuario : '(VACÍO)');
printf("DB_PASS : %s\n", $clave !== '' ? '(definida, ' . strlen($clave) . ' caracteres)' : '(VACÍA)');
printf("DB_HOST : %s\n", $hostEnv !== '' ? $hostEnv : '(VACÍO)');
printf("DB_PORT : %d\n\n", $puerto);

if (!extension_loaded('pdo_mysql')) {
    exit("La extensión pdo_mysql NO está activa (hPanel → Configuración PHP).\n");
}

if ($nombre === '' || $usuario === '') {
    echo "Faltan DB_NAME o DB_USER en el .env.\n";
    echo "En Hostinger ambos llevan el prefijo de la cuenta, por ejemplo u368960646_tienda.\n\n";
}

// --- Intentos ---------------------------------------------------------------
$hosts = array_values(array_unique(array_filter([$hostEnv, 'localhost', '127.0.0.1'])));

echo "-- Intentos de conexión --------------------------------------\n";

$exito = null;
foreach ($hosts as $host) {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $host, $puerto, $nombre);

    try {
        new PDO($dsn, $usuario, $clave, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        printf("  [OK ] host=%-12s CONECTA\n", $host);
        $exito ??= $host;
    } catch (PDOException $e) {
        printf("  [NO ] host=%-12s %s\n", $host, $e->getMessage());
    }
}

echo "\n" . str_repeat('=', 62) . "\n";

if ($exito !== null) {
    if ($exito !== $hostEnv) {
        echo "SOLUCIÓN: poné  DB_HOST=$exito  en el .env.\n";
    } else {
        echo "La conexión funciona con la configuración actual.\n";
        echo "Si el login sigue dando 500, el problema ya no es la conexión:\n";
        echo "revisá storage/logs/app.log de nuevo.\n";
    }
} else {
    echo "Ningún host conectó. Según el mensaje de arriba:\n\n";
    echo "  \"Access denied for user\"      -> usuario o contraseña incorrectos.\n";
    echo "     Recreá la contraseña en hPanel -> Bases de datos y copiala al .env.\n\n";
    echo "  \"Unknown database\"            -> DB_NAME mal escrito.\n";
    echo "     Copiá el nombre exacto (con prefijo) desde hPanel.\n\n";
    echo "  \"Connection refused\" / timeout -> DB_HOST incorrecto.\n";
    echo "     En Hostinger casi siempre es localhost.\n\n";
    echo "  \"No such file or directory\"   -> el socket no está donde PHP lo busca;\n";
    echo "     probá 127.0.0.1 en lugar de localhost.\n";
}

echo "\nBORRÁ ESTE ARCHIVO CUANDO TERMINES.\n";
