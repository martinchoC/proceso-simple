<?php

declare(strict_types=1);

/**
 * DIAGNÓSTICO DE PERMISOS — archivo temporal.
 *
 * Subir a  public_html/ps_ecommerce/permisos.php  y abrir:
 *     https://tudominio.com/ps_ecommerce/permisos.php?usuario=martin&clave=<16 de APP_KEY>
 *
 * Usa exactamente la misma configuración y la misma consulta que la aplicación,
 * y va mostrando dónde se corta la cadena empresa → módulo → perfil → función.
 *
 * >>> BORRAR ESTE ARCHIVO CUANDO TERMINES <<<
 */

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$base = __DIR__ . '/ecommerce_app';
if (!is_file($base . '/.env')) {
    http_response_code(500);
    exit("No encontré ecommerce_app/.env junto a este archivo.\n");
}

require_once $base . '/bootstrap/autoload.php';

use App\Database\Connection;
use App\Repositories\ParametrosRepository;
use App\Services\ParametrosService;
use App\Support\Config;
use App\Support\Env;

Env::load($base . '/.env');
Config::load(require $base . '/config/app.php');

$appKey = (string) Config::get('app.key', '');
$esperado = substr($appKey, 0, 16);
if (strlen($esperado) >= 16 && !hash_equals($esperado, (string) ($_GET['clave'] ?? ''))) {
    http_response_code(404);
    exit("No encontrado.\n");
}

$usuario = (string) ($_GET['usuario'] ?? '');
if ($usuario === '') {
    exit("Agregá ?usuario=<nombre de usuario> a la URL.\n");
}

$pdo = Connection::get();

$avisoParametros = '';
try {
    (new ParametrosService(new ParametrosRepository($pdo), $base . '/storage/cache/parametros.json'))->resolver();
} catch (Throwable $e) {
    // No cortamos: sin parámetros resueltos igual queremos ver el resto.
    $avisoParametros = $e->getMessage();
}

$empresaId = Config::int('ecom.empresa_id');
$moduloId = Config::int('ecom.modulo_id');

$tabla = static function (array $filas): void {
    if ($filas === []) {
        echo "  (sin resultados)\n";
        return;
    }
    foreach ($filas as $fila) {
        $partes = [];
        foreach ($fila as $col => $val) {
            $partes[] = "$col=" . ($val === null ? 'NULL' : $val);
        }
        echo '  ' . implode('  ', $partes) . "\n";
    }
};

$consulta = static function (PDO $pdo, string $sql, array $params = []): array {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
};

echo "DIAGNÓSTICO DE PERMISOS — " . date('Y-m-d H:i:s') . "\n";
echo str_repeat('=', 66) . "\n\n";

echo "Parámetros que usa la aplicación\n";
echo "  ecom.empresa_id = $empresaId\n";
echo "  ecom.modulo_id  = $moduloId\n";
if ($avisoParametros !== '') {
    echo "  AVISO: $avisoParametros\n";
    echo "  (definí ECOM_EMPRESA_ID y ECOM_MODULO_ID en el .env)\n";
}
echo "\n";

$fila = $consulta($pdo, 'SELECT usuario_id, usuario, tabla_estado_registro_id FROM conf__usuarios WHERE usuario = ?', [$usuario]);
echo "1. Usuario\n";
$tabla($fila);
$usuarioId = (int) ($fila[0]['usuario_id'] ?? 0);
if ($usuarioId === 0) {
    exit("\nEse usuario no existe.\n");
}

echo "\n2. Perfiles asignados al usuario (todos los módulos)\n";
$tabla($consulta($pdo,
    'SELECT up.usuario_perfil_id, ep.empresa_perfil_id, ep.empresa_id, ep.modulo_id,
            ep.empresa_perfil_nombre, ep.tabla_estado_registro_id AS estado_perfil,
            up.fecha_inicio, up.fecha_fin, up.tabla_estado_registro_id AS estado_asignacion
       FROM conf__usuarios_perfiles up
  LEFT JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = up.empresa_perfil_id
      WHERE up.usuario_id = ?', [$usuarioId]));

echo "\n3. Funciones ecom.* cargadas y a qué módulo pertenecen\n";
$tabla($consulta($pdo,
    "SELECT pf.pagina_funcion_id, pf.codigo_funcion, pg.modulo_id, pg.url,
            pf.tabla_estado_registro_id AS estado_funcion,
            pg.tabla_estado_registro_id AS estado_pagina
       FROM conf__paginas_funciones pf
  LEFT JOIN conf__paginas pg ON pg.pagina_id = pf.pagina_id
      WHERE pf.codigo_funcion LIKE 'ecom.%'"));

echo "\n4. Asignación función → perfil\n";
$tabla($consulta($pdo,
    "SELECT epf.empresa_perfil_funcion_id, epf.empresa_id, epf.empresa_perfil_id,
            epf.pagina_funcion_id, epf.asignado, pf.codigo_funcion
       FROM conf__empresas_perfiles_funciones epf
 INNER JOIN conf__paginas_funciones pf ON pf.pagina_funcion_id = epf.pagina_funcion_id
      WHERE pf.codigo_funcion LIKE 'ecom.%'"));

echo "\n5. CONSULTA EXACTA DE LA APLICACIÓN\n";
$codigos = $consulta($pdo,
    'SELECT DISTINCT pf.codigo_funcion
       FROM conf__usuarios_perfiles up
 INNER JOIN conf__empresas_perfiles ep
         ON ep.empresa_perfil_id = up.empresa_perfil_id
        AND ep.empresa_id = :empresa_perfil
        AND ep.modulo_id = :modulo_perfil
        AND ep.tabla_estado_registro_id = 1
 INNER JOIN conf__empresas_perfiles_funciones epf
         ON epf.empresa_perfil_id = ep.empresa_perfil_id
        AND epf.empresa_id = :empresa_funcion
        AND epf.asignado = 1
 INNER JOIN conf__paginas_funciones pf
         ON pf.pagina_funcion_id = epf.pagina_funcion_id
        AND pf.tabla_estado_registro_id = 1
 INNER JOIN conf__paginas pg
         ON pg.pagina_id = pf.pagina_id
        AND pg.modulo_id = :modulo_pagina
        AND pg.tabla_estado_registro_id = 1
      WHERE up.usuario_id = :usuario
        AND up.tabla_estado_registro_id = 1
        AND up.fecha_inicio <= CURDATE()
        AND up.fecha_fin >= CURDATE()
        AND pf.codigo_funcion IS NOT NULL',
    [
        'usuario' => $usuarioId,
        'empresa_perfil' => $empresaId,
        'empresa_funcion' => $empresaId,
        'modulo_perfil' => $moduloId,
        'modulo_pagina' => $moduloId,
    ]);
$tabla($codigos);

echo "\n" . str_repeat('=', 66) . "\n";
if ($codigos !== []) {
    echo "Permisos OK. Si seguís viendo 403, borrá storage/cache/parametros.json.\n";
} else {
    echo "Sin permisos. Comparar los bloques de arriba con los parámetros:\n";
    echo "  - En el bloque 2, el perfil del usuario debe tener empresa_id=$empresaId y modulo_id=$moduloId\n";
    echo "  - En el bloque 3, las funciones deben pertenecer al modulo_id=$moduloId\n";
    echo "  - En el bloque 4, epf.empresa_id debe ser $empresaId y asignado=1\n";
    echo "  Si los números no coinciden, ajustá ECOM_EMPRESA_ID / ECOM_MODULO_ID en el .env\n";
    echo "  o volvé a correr instalar_en_modulo_existente.sql con esos valores.\n";
}

echo "\nBORRÁ ESTE ARCHIVO CUANDO TERMINES.\n";
