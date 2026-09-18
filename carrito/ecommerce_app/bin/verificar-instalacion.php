<?php

declare(strict_types=1);

/**
 * Verificador de instalación.
 *
 * Por SSH (hPanel → Avanzado → Acceso SSH):
 *     cd ~/domains/TUDOMINIO/public_html/ps_ecommerce/ecommerce_app
 *     php bin/verificar-instalacion.php
 *
 * Si tu plan no tiene SSH, copiá `bin/verificar-web.php` como
 * ps_ecommerce/verificar.php y abrilo en el navegador (instrucciones en ese archivo).
 *
 * No modifica nada. Devuelve código de salida 1 si algo bloquea el arranque.
 */

$errores = 0;
$avisos = 0;
$cli = PHP_SAPI === 'cli';

if (!$cli) {
    header('Content-Type: text/plain; charset=utf-8');
}

function ok(string $m): void     { echo "  [OK]    $m\n"; }
function falla(string $m): void  { global $errores; $errores++; echo "  [ERROR] $m\n"; }
function aviso(string $m): void  { global $avisos;  $avisos++;  echo "  [AVISO] $m\n"; }
function titulo(string $m): void { echo "\n$m\n" . str_repeat('-', mb_strlen($m)) . "\n"; }

$base = dirname(__DIR__);

titulo('1. Entorno PHP');

PHP_VERSION_ID >= 80100
    ? ok('PHP ' . PHP_VERSION)
    : falla('Se requiere PHP 8.1+; hay ' . PHP_VERSION . ' — cambialo en hPanel → Avanzado → Configuración PHP');

foreach (['pdo_mysql', 'mbstring', 'json'] as $ext) {
    extension_loaded($ext) ? ok("Extensión $ext") : falla("Falta la extensión $ext (hPanel → Configuración PHP → Extensiones)");
}

titulo('2. Archivos y permisos');

is_readable($base . '/.env')
    ? ok('.env encontrado')
    : falla('Falta .env — copiar .env.example a .env y completar el bloque 1');

foreach (['/storage/logs', '/storage/cache'] as $dir) {
    is_writable($base . $dir)
        ? ok("$dir escribible")
        : falla("$dir no es escribible (permisos 755 en carpetas, 644 en archivos)");
}

// La carpeta pública es la que contiene a ecommerce_app (o su padre).
$publicos = [dirname($base), dirname($base, 2)];
$assetsOk = false;
foreach ($publicos as $raiz) {
    if (is_file($raiz . '/assets/vendor/adminlte/css/adminlte.min.css')) {
        $assetsOk = true;
        break;
    }
}
$assetsOk
    ? ok('AdminLTE 4 y Bootstrap presentes en assets/vendor')
    : aviso('No encontré assets/vendor/adminlte — verificá que subiste la carpeta assets completa');

is_file(dirname($base) . '/index.php')
    ? ok('index.php presente junto a ecommerce_app')
    : aviso('No encontré index.php junto a ecommerce_app');

if ($errores > 0) {
    echo "\nCorregí los errores anteriores antes de continuar.\n";
    exit(1);
}

require_once $base . '/bootstrap/autoload.php';

use App\Repositories\ParametrosRepository;
use App\Services\ParametrosService;
use App\Support\Config;
use App\Support\Env;

Env::load($base . '/.env');
Config::load(require $base . '/config/app.php');

titulo('3. Configuración');

Config::get('app.key') ? ok('APP_KEY definida') : falla('APP_KEY vacía — generar con: php -r "echo bin2hex(random_bytes(32));"');
Config::get('app.debug') === false ? ok('APP_DEBUG desactivado') : aviso('APP_DEBUG activo: NO dejarlo así en producción');
Config::get('session.secure') ? ok('Cookies de sesión Secure') : aviso('SESSION_SECURE=false — pasalo a true cuando el SSL esté activo');
Config::get('app.url')
    ? ok('APP_URL fija: ' . Config::get('app.url'))
    : ok('APP_URL vacía → URLs relativas al dominio (recomendado)');

titulo('4. Base de datos');

try {
    $pdo = App\Database\Connection::get();
    ok('Conexión establecida a ' . Config::get('db.name'));
} catch (Throwable $e) {
    falla('No se pudo conectar. Revisá DB_HOST / DB_NAME / DB_USER / DB_PASS en el .env.');
    echo "  Sugerencia: en Hostinger, DB_HOST suele ser 'localhost' y el usuario y la base llevan prefijo (uXXXXXX_).\n";
    echo "\nResumen: $errores error(es), $avisos aviso(s).\n";
    exit(1);
}

$existe = static function (PDO $pdo, string $tabla): bool {
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables
                            WHERE table_schema = DATABASE() AND table_name = ?');
    $stmt->execute([$tabla]);
    return (int) $stmt->fetchColumn() > 0;
};

$erp = ['conf__usuarios', 'conf__usuarios_perfiles', 'conf__empresas_perfiles',
        'conf__empresas_perfiles_funciones', 'conf__paginas_funciones', 'conf__paginas',
        'conf__modulos', 'conf__empresas', 'gestion__productos', 'gestion__listas_precios_productos',
        'gestion__entidades', 'gestion__ventas_pedidos', 'gestion__comprobantes',
        'gestion__comprobantes_numeradores', 'gestion__puntos_venta'];

$faltanErp = array_values(array_filter($erp, static fn (string $t): bool => !$existe($pdo, $t)));
$faltanErp === []
    ? ok('Tablas del sistema compartido presentes (' . count($erp) . ')')
    : falla('¿Base equivocada? Faltan tablas del sistema: ' . implode(', ', $faltanErp));

// La aplicación no crea tablas: si quedaron de una versión anterior, sólo
// se avisa para que se puedan borrar. Ya no se usan.
$sobrantes = array_values(array_filter(
    ['ecom__usuarios_entidades', 'ecom__login_intentos', 'ecom__carritos',
     'ecom__carritos_items', 'ecom__pedidos'],
    static fn (string $t): bool => $existe($pdo, $t)
));
$sobrantes === []
    ? ok('Sin tablas propias: la aplicación sólo lee el ERP y escribe el pedido')
    : aviso('Tablas de versiones anteriores que ya no se usan y se pueden borrar: '
        . implode(', ', $sobrantes));

if ($errores > 0) {
    echo "\nResumen: $errores error(es), $avisos aviso(s).\n";
    exit(1);
}

titulo('5. Detección automática de parámetros');

$parametros = new ParametrosService(new ParametrosRepository($pdo), $base . '/storage/cache/parametros.json');

try {
    foreach ($parametros->diagnostico() as $clave => $valor) {
        $obligatorio = in_array($clave, ['modulo_id', 'empresa_id', 'sucursal_id', 'comprobante_tipo_pedido', 'tabla_origen_pedidos_id'], true);
        if ((int) $valor > 0) {
            ok(str_pad($clave, 26) . "= $valor");
        } elseif ($obligatorio) {
            falla(str_pad($clave, 26) . '= sin resolver — definilo en el .env');
        } else {
            aviso(str_pad($clave, 26) . '= sin resolver (opcional)');
        }
    }
} catch (Throwable $e) {
    falla($e->getMessage());
}

titulo('6. Datos mínimos de negocio');

$empresaId = Config::int('ecom.empresa_id');
$moduloId  = Config::int('ecom.modulo_id');

$contar = static function (PDO $pdo, string $sql, array $params = []): int {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return (int) $stmt->fetchColumn();
};

// El modelo de permisos por función sólo se controla si está activo.
$modoPermisos = strtolower((string) Config::get('ecom.permisos', 'tienda'));

if ($modoPermisos === 'erp') {
    $hayColumna = $contar($pdo,
        'SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
        ['conf__paginas_funciones', 'codigo_funcion']) === 1;

    if (!$hayColumna) {
        falla('ECOM_PERMISOS=erp pero falta conf__paginas_funciones.codigo_funcion. '
            . 'Usar ECOM_PERMISOS=tienda, que no requiere nada cargado en el ERP.');
    } else {
        $funciones = $contar($pdo,
            "SELECT COUNT(*) FROM conf__paginas_funciones WHERE codigo_funcion LIKE 'ecom.%'");
        $funciones === 4
            ? ok('Las 4 funciones del ecommerce están cargadas')
            : falla("Se encontraron $funciones de 4 funciones para ECOM_PERMISOS=erp");
    }
} else {
    ok("Permisos en modo 'tienda': no requiere configuración en el ERP");
}

if ($empresaId > 0) {
    // El acceso se resuelve por documento: conf__usuarios.usuario tiene que
    // ser igual al CUIL/CUIT de una entidad cliente activa de la empresa.
    $columnaDoc = (string) App\Support\Config::get('ecom.entidad_doc_columna', 'cuil');
    $columnaDoc = in_array($columnaDoc, ['cuil', 'cuit'], true) ? $columnaDoc : 'cuil';

    $existeColumna = $contar($pdo,
        'SELECT COUNT(*) FROM information_schema.columns
          WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
        ['gestion__entidades', $columnaDoc]);

    if ($existeColumna === 0) {
        falla("gestion__entidades no tiene la columna '$columnaDoc' — corregir ECOM_ENTIDAD_DOC_COLUMNA en el .env");
    } else {
        $usuariosTienda = $contar($pdo,
            "SELECT COUNT(*) FROM conf__usuarios u
         INNER JOIN gestion__entidades e
                 ON e.empresa_id = ?
                AND e.es_cliente = 1
                AND e.tabla_estado_registro_id = 1
                AND e.$columnaDoc = u.usuario
              WHERE u.tabla_estado_registro_id = 1",
            [$empresaId]);
        $usuariosTienda > 0
            ? ok("$usuariosTienda usuario(s) coinciden con el $columnaDoc de un cliente")
            : aviso("Ningún usuario coincide con el $columnaDoc de un cliente — revisar database/login_por_cuil.sql");

        $duplicados = $contar($pdo,
            "SELECT COUNT(*) FROM (
                 SELECT $columnaDoc FROM gestion__entidades
                  WHERE empresa_id = ? AND es_cliente = 1 AND tabla_estado_registro_id = 1
                    AND $columnaDoc IS NOT NULL
                  GROUP BY $columnaDoc HAVING COUNT(*) > 1) d",
            [$empresaId]);
        $duplicados === 0
            ? ok("Sin $columnaDoc duplicados entre los clientes")
            : falla("$duplicados $columnaDoc duplicado(s): esos clientes no van a poder entrar");

        // Segunda condición de acceso: el tipo de cliente con acceso_web = 1.
        $existeTipos = $contar($pdo,
            'SELECT COUNT(*) FROM information_schema.columns
              WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?',
            ['gestion__entidades_clientes_tipos', 'acceso_web']);

        if ($existeTipos === 0) {
            falla('Falta gestion__entidades_clientes_tipos.acceso_web — con esto NADIE puede entrar '
                . '(ejecutar database/acceso_web_tipos_cliente.sql)');
        } else {
            $conAcceso = $contar($pdo,
                'SELECT COUNT(*) FROM gestion__entidades_clientes_tipos WHERE acceso_web = 1');
            $conAcceso > 0
                ? ok("$conAcceso tipo(s) de cliente con acceso_web = 1")
                : falla('Ningún tipo de cliente tiene acceso_web = 1 — nadie va a poder entrar');

            $listos = $contar($pdo,
                "SELECT COUNT(*) FROM conf__usuarios u
             INNER JOIN gestion__entidades e
                     ON e.empresa_id = ?
                    AND e.es_cliente = 1
                    AND e.tabla_estado_registro_id = 1
                    AND e.$columnaDoc = u.usuario
             INNER JOIN gestion__entidades_clientes_tipos t
                     ON t.entidad_cliente_tipo_id = e.entidad_tipo_id
                    AND t.acceso_web = 1
                  WHERE u.tabla_estado_registro_id = 1",
                [$empresaId]);
            $listos > 0
                ? ok("$listos usuario(s) cumplen las dos condiciones y pueden entrar")
                : falla('Ningún usuario cumple las dos condiciones — revisar '
                    . 'database/acceso_web_tipos_cliente.sql (bloque B2)');
        }
    }

    $perfiles = $contar($pdo,
        'SELECT COUNT(*) FROM conf__usuarios_perfiles up
     INNER JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = up.empresa_perfil_id
          WHERE ep.empresa_id = ? AND ep.modulo_id = ?
            AND up.tabla_estado_registro_id = 1
            AND up.fecha_inicio <= CURDATE() AND up.fecha_fin >= CURDATE()',
        [$empresaId, $moduloId]);
    $perfiles > 0
        ? ok("$perfiles asignación(es) de perfil vigentes en el módulo")
        : ($modoPermisos === 'erp'
        ? aviso('Sin perfiles vigentes del módulo: los usuarios entrarán pero recibirán 403')
        : ok("Sin perfiles del módulo (no hacen falta en modo 'tienda')"));

    $lista = Config::int('ecom.lista_precio_default_id');
    $conPrecio = $contar($pdo,
        'SELECT COUNT(*) FROM gestion__listas_precios_productos
          WHERE empresa_id = ? AND tabla_estado_registro_id = 1
            AND precio_final > 0 AND f_desde <= CURDATE()
            AND (f_hasta IS NULL OR f_hasta >= CURDATE())',
        [$empresaId]);
    $conPrecio > 0
        ? ok("$conPrecio precio(s) vigentes en la empresa" . ($lista > 0 ? " (lista por defecto: $lista)" : ''))
        : aviso('Ningún producto con precio vigente: el catálogo se verá vacío');
}

echo "\n" . str_repeat('=', 62) . "\n";
echo $errores === 0
    ? "Instalación correcta. $avisos aviso(s) para revisar.\n"
    : "$errores error(es) y $avisos aviso(s). Corregir antes de publicar.\n";

exit($errores === 0 ? 0 : 1);
