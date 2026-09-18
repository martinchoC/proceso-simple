<?php

declare(strict_types=1);

/**
 * DIAGNÓSTICO TEMPORAL — BORRAR DESPUÉS DE USAR.
 *
 * Página de solo lectura para depurar el login. NO modifica la base:
 * ejecuta únicamente SELECT (información de esquema y datos de lectura).
 *
 * Acceso: se protege con la clave del .env.
 *   https://tu-dominio/ps_ecommerce/diagnostico.php?clave=<APP_KEY>&usuario=30548970837
 *
 * Sin clave correcta devuelve 404 para no delatar que el archivo existe.
 */

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');

$base = null;
foreach ([__DIR__ . '/ecommerce_app', dirname(__DIR__) . '/ecommerce_app', dirname(__DIR__)] as $ruta) {
    if (is_file($ruta . '/bootstrap/autoload.php')) {
        $base = $ruta;
        break;
    }
}

if ($base === null) {
    http_response_code(500);
    exit("No se encontró ecommerce_app junto a este archivo.\n");
}

require $base . '/bootstrap/autoload.php';

use App\Support\Config;
use App\Support\Documento;
use App\Support\Env;

Env::load($base . '/.env');
Config::load(require $base . '/config/app.php');

$claveApp = (string) Config::get('app.key', '');
$claveDada = (string) ($_GET['clave'] ?? '');

if ($claveApp === '' || strlen($claveApp) < 16 || !hash_equals($claveApp, $claveDada)) {
    http_response_code(404);
    exit("404\n");
}

/** Imprime un título de sección. */
function seccion(string $titulo): void
{
    echo "\n" . str_repeat('=', 70) . "\n" . $titulo . "\n" . str_repeat('=', 70) . "\n";
}

/** Imprime filas como tabla simple. */
function tabla(array $filas): void
{
    if ($filas === []) {
        echo "  (sin resultados)\n";
        return;
    }
    foreach ($filas as $fila) {
        $partes = [];
        foreach ((array) $fila as $col => $val) {
            $partes[] = $col . '=' . ($val === null ? 'NULL' : (string) $val);
        }
        echo '  ' . implode('  |  ', $partes) . "\n";
    }
}

echo "DIAGNOSTICO PS ECOMMERCE — " . date('c') . "\n";
echo "SOLO LECTURA: esta pagina no modifica la base de datos.\n";

// ── 1. Entorno ───────────────────────────────────────────────────────────
seccion('1. Entorno');
echo '  PHP           : ' . PHP_VERSION . "\n";
echo '  Base          : ' . Config::get('db.name') . ' @ ' . Config::get('db.host') . "\n";
echo '  APP_ENV       : ' . Config::get('app.env') . "\n";
echo '  Extensiones   : pdo_mysql=' . (extension_loaded('pdo_mysql') ? 'si' : 'NO') . "\n";

// ── 2. Conexión ──────────────────────────────────────────────────────────
seccion('2. Conexion a la base');
try {
    $pdo = App\Database\Connection::get();
    echo "  Conexion OK\n";
    echo '  Servidor      : ' . $pdo->getAttribute(PDO::ATTR_SERVER_VERSION) . "\n";
    echo '  Base activa   : ' . $pdo->query('SELECT DATABASE()')->fetchColumn() . "\n";
} catch (Throwable $e) {
    echo '  FALLA: ' . $e->getMessage() . "\n";
    echo '  causa: ' . ($e->getPrevious()?->getMessage() ?? '-') . "\n";
    exit("\nSin conexion no se puede seguir.\n");
}

// ── 3. Configuración de negocio ──────────────────────────────────────────
seccion('3. Configuracion resuelta');
foreach ([
    'ECOM_EMPRESA_ID'           => 'ecom.empresa_id',
    'ECOM_MODULO_ID'            => 'ecom.modulo_id',
    'ECOM_ENTIDAD_DOC_COLUMNA'  => 'ecom.entidad_doc_columna',
    'ECOM_ENTIDAD_TIPO_COLUMNA' => 'ecom.entidad_tipo_columna',
    'ECOM_TIPOS_TABLA'          => 'ecom.tipos_tabla',
    'ECOM_TIPOS_PK'             => 'ecom.tipos_pk',
    'ECOM_TIPOS_ACCESO'         => 'ecom.tipos_acceso',
    'ECOM_PERMISOS'             => 'ecom.permisos',
] as $etiqueta => $clave) {
    printf("  %-26s = %s\n", $etiqueta, var_export(Config::get($clave), true));
}

// ── 4. Esquema real (solo lectura de information_schema) ────────────────
seccion('4. Esquema real de gestion__entidades');
$stmt = $pdo->prepare(
    'SELECT column_name AS columna, column_type AS tipo, is_nullable AS acepta_null
       FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = ?
      ORDER BY ordinal_position'
);
$stmt->execute(['gestion__entidades']);
tabla($stmt->fetchAll());

seccion('5. Tabla de tipos de cliente configurada');
$tablaTipos = (string) Config::get('ecom.tipos_tabla');
echo "  Buscando: {$tablaTipos}\n\n";
$stmt->execute([$tablaTipos]);
$colsTipos = $stmt->fetchAll();
tabla($colsTipos);

if ($colsTipos === []) {
    echo "\n  La tabla NO existe con ese nombre. Tablas parecidas:\n";
    $q = $pdo->query(
        "SELECT table_name AS tabla FROM information_schema.tables
          WHERE table_schema = DATABASE()
            AND (table_name LIKE '%tipo%' OR table_name LIKE '%client%')
          ORDER BY table_name"
    );
    tabla($q->fetchAll());
}

seccion('6. Verificacion de los nombres usados en el JOIN');
$repo = new App\Repositories\EntidadRepository($pdo);
$problemas = $repo->verificarNombresTipos();
if ($problemas === []) {
    echo "  OK: los cuatro nombres existen en el esquema.\n";
} else {
    foreach ($problemas as $p) {
        echo '  PROBLEMA: ' . $p . "\n";
    }
    echo "\n  Corregir en el .env las variables ECOM_* de la seccion 3.\n";
}

// ── 7. Simulación del login (solo SELECT) ───────────────────────────────
seccion('7. Simulacion del login');
$usuarioProbado = trim((string) ($_GET['usuario'] ?? ''));

if ($usuarioProbado === '') {
    echo "  Agregar &usuario=<el usuario del login> para simular el acceso.\n";
} else {
    $stmt = $pdo->prepare(
        'SELECT usuario_id, usuario, usuario_nombre, tabla_estado_registro_id
           FROM conf__usuarios WHERE usuario = ? LIMIT 1'
    );
    $stmt->execute([$usuarioProbado]);
    $u = $stmt->fetch();

    echo "  Usuario ingresado : {$usuarioProbado}\n";
    echo '  Existe en conf__usuarios : ' . ($u ? "si (id={$u['usuario_id']}, estado={$u['tabla_estado_registro_id']})" : 'NO') . "\n";

    $doc = Documento::normalizar($usuarioProbado);
    echo '  Normalizado a documento  : ' . ($doc === '' ? 'RECHAZADO (no tiene forma de CUIL/DNI)' : $doc) . "\n";

    if ($doc !== '' && $problemas === []) {
        try {
            $cliente = $repo->clientePorDocumento($doc, (int) Config::get('ecom.empresa_id'), (string) Config::get('ecom.entidad_doc_columna'));
            if ($cliente === null) {
                echo "  Entidad cliente          : NO se encontro (o hay mas de una con ese documento)\n";
                $dup = $repo->documentoDuplicado($doc, (int) Config::get('ecom.empresa_id'), (string) Config::get('ecom.entidad_doc_columna'));
                echo '  Documento duplicado      : ' . ($dup ? 'SI — corregir en el ERP' : 'no') . "\n";
            } else {
                echo "  Entidad cliente          : id={$cliente['entidad_id']} {$cliente['entidad_nombre']}\n";
                echo '  entidad_tipo_id          : ' . var_export($cliente['entidad_tipo_id'], true) . "\n";
                echo '  acceso_web               : ' . var_export($cliente['acceso_web'], true) . "\n";
                echo '  RESULTADO                : ' . ($repo->tipoHabilitaWeb($cliente)
                    ? 'ENTRA'
                    : 'NO ENTRA (el tipo de cliente no habilita el acceso web)') . "\n";
            }
        } catch (Throwable $e) {
            echo '  EXCEPCION: ' . $e::class . ' — ' . $e->getMessage() . "\n";
            if ($e instanceof PDOException && is_array($e->errorInfo ?? null)) {
                echo '  SQLSTATE : ' . ($e->errorInfo[0] ?? '') . ' / ' . ($e->errorInfo[1] ?? '') . "\n";
                echo '  Driver   : ' . ($e->errorInfo[2] ?? '') . "\n";
            }
        }
    }
}

// ── 8. Últimas líneas del log ───────────────────────────────────────────
seccion('8. Ultimas 40 lineas de storage/logs/app.log');
$log = $base . '/storage/logs/app.log';
if (!is_file($log)) {
    echo "  El archivo no existe todavia (no hubo errores registrados).\n";
} elseif (!is_readable($log)) {
    echo "  El archivo existe pero no se puede leer (permisos).\n";
} else {
    $lineas = file($log, FILE_IGNORE_NEW_LINES) ?: [];
    foreach (array_slice($lineas, -40) as $linea) {
        echo '  ' . $linea . "\n";
    }
    if ($lineas === []) {
        echo "  (vacio)\n";
    }
}

// ── 9. Requisitos de la aplicación ──────────────────────────────────────
seccion('9. Requisitos de la aplicacion');

echo "  -- Tablas propias del storefront (las crea database/crear_tablas_ecommerce.sql) --\n";
$stmtT = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.tables
      WHERE table_schema = DATABASE() AND table_name = ?'
);
$faltanEcom = [];
foreach (['ecom__login_intentos', 'ecom__carritos', 'ecom__carritos_items', 'ecom__pedidos'] as $t) {
    $stmtT->execute([$t]);
    $existe = (int) $stmtT->fetchColumn() === 1;
    if (!$existe) {
        $faltanEcom[] = $t;
    }
    printf("    %-24s %s\n", $t, $existe ? 'OK' : '*** FALTA ***');
}

echo "\n  -- Tablas del ERP que la aplicacion LEE (no se modifican) --\n";
foreach ([
    'conf__usuarios', 'conf__usuarios_perfiles', 'conf__empresas_perfiles',
    'conf__empresas_perfiles_funciones', 'conf__paginas', 'conf__paginas_funciones',
    'conf__empresas_modulos', 'conf__modulos', 'conf__tablas',
    'gestion__entidades', 'gestion__productos', 'gestion__entidades_listas_precios',
    'gestion__entidades_sucursales_compra', 'gestion__compras_pedidos',
    'gestion__compras_pedidos_detalles', 'gestion__comprobantes',
    'gestion__comprobantes_numeradores',
] as $t) {
    $stmtT->execute([$t]);
    printf("    %-38s %s\n", $t, (int) $stmtT->fetchColumn() === 1 ? 'OK' : '*** FALTA ***');
}

$stmtC = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.columns
      WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?'
);
$stmtC->execute(['conf__paginas_funciones', 'codigo_funcion']);
$hayCodigo = (int) $stmtC->fetchColumn() === 1;
echo "\n  -- Modelo de permisos --\n";
printf("    %-38s %s\n", 'ECOM_PERMISOS', (string) Config::get('ecom.permisos', 'tienda'));
printf("    %-38s %s\n", 'conf__paginas_funciones.codigo_funcion', $hayCodigo ? 'presente' : 'ausente');
if (strtolower((string) Config::get('ecom.permisos', 'tienda')) === 'tienda') {
    echo "    Modo 'tienda': esa columna NO se usa. Nada que configurar en el ERP.\n";
} elseif (!$hayCodigo) {
    echo "    *** Modo 'erp' sin esa columna: nadie tendra permisos. ***\n";
}

// ── 10. Configuración del módulo en el ERP ──────────────────────────────
seccion('10. Configuracion del modulo en el ERP');
$empresaId = (int) Config::get('ecom.empresa_id');
$moduloId  = (int) Config::get('ecom.modulo_id');

$q = $pdo->prepare('SELECT modulo_id, modulo, tabla_estado_registro_id AS estado FROM conf__modulos WHERE modulo_id = ?');
$q->execute([$moduloId]);
echo "  Modulo configurado ({$moduloId}):\n";
tabla($q->fetchAll());

$q = $pdo->prepare(
    'SELECT empresa_id, modulo_id, tabla_estado_registro_id AS estado
       FROM conf__empresas_modulos WHERE empresa_id = ? AND modulo_id = ?'
);
$q->execute([$empresaId, $moduloId]);
echo "\n  Modulo habilitado para la empresa {$empresaId}:\n";
tabla($q->fetchAll());

$q = $pdo->prepare(
    'SELECT pagina_id, pagina, url, tabla_estado_registro_id AS estado
       FROM conf__paginas WHERE modulo_id = ? ORDER BY orden'
);
$q->execute([$moduloId]);
echo "\n  Paginas del modulo:\n";
tabla($q->fetchAll());

if ($hayCodigo) {
    $q = $pdo->query(
        "SELECT pagina_funcion_id, pagina_id, nombre_funcion, codigo_funcion,
                tabla_estado_registro_id AS estado
           FROM conf__paginas_funciones WHERE codigo_funcion LIKE 'ecom.%'"
    );
    echo "\n  Funciones del ecommerce:\n";
    tabla($q->fetchAll());
}

// ── 11. Permisos efectivos del usuario ──────────────────────────────────
seccion('11. Permisos efectivos del usuario probado');
if ($usuarioProbado === '' || !$u) {
    echo "  Agregar &usuario=<usuario existente> para ver sus permisos.\n";
} elseif (strtolower((string) Config::get('ecom.permisos', 'tienda')) === 'tienda') {
    echo "  Modo 'tienda': el permiso ya se decidio en el login.\n";
    echo "  Un usuario que entra tiene habilitadas estas funciones:\n";
    foreach (App\Services\AutorizacionService::FUNCIONES as $f) {
        echo '    ' . $f . "\n";
    }
    echo "  No hace falta configurar nada en el ERP.\n";
} elseif (!$hayCodigo) {
    echo "  Modo 'erp' pero falta conf__paginas_funciones.codigo_funcion: nadie tendra permisos.\n";
    echo "  Poner ECOM_PERMISOS=tienda en el .env, o agregar la columna al ERP.\n";
} else {
    $permisos = (new App\Repositories\PermisoRepository($pdo))
        ->codigosPorUsuario((int) $u['usuario_id'], $empresaId, $moduloId);
    if ($permisos === []) {
        echo "  SIN PERMISOS. La aplicacion respondera 403 en las pantallas internas.\n";
        echo "  Se necesita un perfil del modulo {$moduloId} vigente para este usuario.\n";
    } else {
        foreach ($permisos as $p) {
            echo '    ' . $p . "\n";
        }
    }
}

// ── 12. Resumen accionable ──────────────────────────────────────────────
seccion('12. Resumen');
$pendientes = [];
if ($faltanEcom !== []) {
    $pendientes[] = 'Ejecutar database/crear_tablas_ecommerce.sql (faltan: ' . implode(', ', $faltanEcom) . ').';
}
$modoPermisos = strtolower((string) Config::get('ecom.permisos', 'tienda'));
if (!$hayCodigo && $modoPermisos === 'erp') {
    $pendientes[] = 'ECOM_PERMISOS=erp pero falta conf__paginas_funciones.codigo_funcion. '
        . 'Poner ECOM_PERMISOS=tienda (no requiere cambios en el ERP).';
}
if ($problemas !== []) {
    $pendientes[] = 'Corregir los nombres ECOM_* del .env (ver seccion 6).';
}
if ($usuarioProbado !== '' && Documento::normalizar($usuarioProbado) === '') {
    $pendientes[] = "El usuario '{$usuarioProbado}' no es un CUIL: con la regla actual no puede entrar. "
        . 'Probar con un usuario cuyo nombre sea el CUIL del cliente.';
}
if ($pendientes === []) {
    echo "  Sin pendientes detectados.\n";
} else {
    foreach ($pendientes as $i => $p) {
        echo '  ' . ($i + 1) . '. ' . $p . "\n";
    }
}

echo "\n" . str_repeat('=', 70) . "\n";
echo "FIN. Borrar este archivo del servidor cuando termine el diagnostico.\n";
