<?php
/**
 * cron/facturas_proveedores_lectura.php
 * Proceso CLI: lee las facturas de proveedores (PDF / JPG / PNG) que se dejan en una carpeta y las carga en Borrador.
 * Gestión (módulo 2) y Gestión Normal (5), mismo motor que "Cargar desde archivo" de facturas_proveedores.php
 * (core/facturas_lectura/ + modules/<carpeta>/facturas_proveedores_lectura_model.php).
 * Los modelos de facturas de los dos módulos tienen funciones con el mismo nombre: cada corrida trabaja con uno.
 * Si hay empresas configuradas de los dos, el script se vuelve a lanzar a sí mismo con --modulo=2 y --modulo=5.
 *
 * Por cada archivo de la carpeta de entrada de la empresa:
 *   - lo guarda como adjunto, lo lee (QR / texto / OCR con los binarios del servidor y, si la empresa la tiene
 *     habilitada y no llegó al tope del día, IA) y, si quedó listo (sin errores, proveedor cargado, sin
 *     percepciones pendientes, sin duplicado), crea la factura en BORRADOR para que alguien la revise y confirme;
 *   - mueve el archivo a <entrada>/procesados/ si se cargó, o a <entrada>/no_procesados/ con un
 *     <archivo>.motivo.txt que explica por qué (no da de alta proveedores: eso queda para la pantalla).
 * Sin poppler-utils / tesseract / zbar en el servidor y sin IA, solo se leen los PDF con texto que el parser
 * reconoce (sin QR): ver _local/docs/gestion/10_lectura_facturas_proveedores.md.
 *
 * Configuración (archivo de FACTURA_LECTURA_CONFIG, ver el ejemplo en _local/docs/gestion/):
 *   'cron' => ['empresas' => [6 => ['directorio' => '/ruta/entrada', 'usuario_id' => 12, 'sucursal_id' => 0, 'modulo' => 5]]]
 * El usuario tiene que tener perfil en la empresa con permiso de "Agregar" en Facturas de proveedores (57 en el
 * módulo 2, 557 en el 5); es el que queda como autor de las facturas. sucursal_id 0 = la primera sucursal a la que
 * el usuario tiene acceso. modulo: 2 o 5; si falta, el del módulo en el que el usuario tiene ese permiso (el 5 primero).
 *
 * Uso CLI:
 *   php cron/facturas_proveedores_lectura.php                     (todas las empresas configuradas)
 *   php cron/facturas_proveedores_lectura.php --empresa=6         (una sola)
 *   php cron/facturas_proveedores_lectura.php --empresa=6 --dir=/ruta --usuario=12 [--sucursal=3]   (sin configuración)
 *   --simular: lee y muestra el resultado sin guardar facturas ni mover archivos (sí registra adjunto y log)
 *   --modulo=2|5: solo las empresas de ese módulo (con --dir, el módulo de esa empresa)
 * Ejemplo de crontab (cada 15 minutos):
 *   (asterisco)/15 * * * * php /home/<usuario>/public_html/cron/facturas_proveedores_lectura.php >> /home/<usuario>/logs/fp_lectura.log 2>&1
 */

if (php_sapi_name() !== 'cli') {
    die("Este script solo puede ejecutarse desde la línea de comandos (CLI).\n");
}

require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../core/empresa_config.php';
require_once __DIR__ . '/../core/permisos.php';
require_once __DIR__ . '/../core/facturas_lectura/FacturaExtractor.php';

const FPL_CRON_EXTENSIONES = ['pdf', 'jpg', 'jpeg', 'png'];
// Módulo => [carpeta, página de Facturas de proveedores]
const FPL_CRON_MODULOS = [5 => ['gestion_normal', 557], 2 => ['gestion', 57]];

function fplc_log($mensaje)
{
    echo '[' . date('Y-m-d H:i:s') . '] ' . $mensaje . "\n";
}

/** Mueve el archivo a $destino sin pisar otro con el mismo nombre (agrega _2, _3…). Devuelve la ruta final. */
function fplc_mover($origen, $destino_dir)
{
    $info = pathinfo($origen);
    $base = $info['filename'];
    $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
    $destino = $destino_dir . DIRECTORY_SEPARATOR . $base . $ext;
    for ($n = 2; file_exists($destino); $n++) {
        $destino = $destino_dir . DIRECTORY_SEPARATOR . $base . '_' . $n . $ext;
    }
    if (!rename($origen, $destino)) {
        throw new RuntimeException('No se pudo mover ' . $origen . ' a ' . $destino_dir);
    }
    return $destino;
}

function fplc_no_procesado($ruta, $dir_no, array $motivos, $simular)
{
    fplc_log('     NO PROCESADO: ' . implode(' | ', $motivos));
    if ($simular) return;
    $final = fplc_mover($ruta, $dir_no);
    file_put_contents($final . '.motivo.txt', 'Archivo: ' . basename($final) . "\nFecha: " . date('Y-m-d H:i:s') . "\n\n- "
                      . implode("\n- ", $motivos) . "\n");
}

/**
 * Módulo de la empresa: el de la configuración o, si falta, el módulo activo de la empresa (conf__empresas_modulos)
 * en el que el usuario puede agregar facturas de proveedores (el 5 primero).
 */
function fplc_modulo($conexion, $empresa_id, array $cfg)
{
    $modulo = intval($cfg['modulo'] ?? 0);
    if (isset(FPL_CRON_MODULOS[$modulo])) return $modulo;
    $usuario_id = intval($cfg['usuario_id'] ?? 0);
    $stmt = mysqli_prepare($conexion, "SELECT 1 FROM conf__empresas_modulos WHERE empresa_id = ? AND modulo_id = ? AND tabla_estado_registro_id = 1 LIMIT 1");
    foreach (FPL_CRON_MODULOS as $m => [, $pagina]) {
        mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $m);
        mysqli_stmt_execute($stmt);
        $activo = (bool)mysqli_fetch_row(mysqli_stmt_get_result($stmt));
        if ($activo && $usuario_id > 0 && usuario_puede_funcion($conexion, $usuario_id, $empresa_id, $pagina, 'agregar')) {
            mysqli_stmt_close($stmt);
            return $m;
        }
    }
    mysqli_stmt_close($stmt);
    return 0;
}

$opciones = getopt('', ['empresa::', 'dir::', 'usuario::', 'sucursal::', 'modulo::', 'simular']);
$simular = isset($opciones['simular']);
$solo_empresa = isset($opciones['empresa']) ? intval($opciones['empresa']) : 0;
$solo_modulo = isset($opciones['modulo']) ? intval($opciones['modulo']) : 0;

// Empresas: las de la configuración, o la de la línea de comandos con --dir y --usuario
$empresas = (array)(fl_config()['cron']['empresas'] ?? []);
if ($solo_empresa > 0 && !empty($opciones['dir'])) {
    $empresas = [$solo_empresa => ['directorio' => (string)$opciones['dir'], 'usuario_id' => intval($opciones['usuario'] ?? 0),
                                   'sucursal_id' => intval($opciones['sucursal'] ?? 0), 'modulo' => $solo_modulo]];
} elseif ($solo_empresa > 0) {
    $empresas = array_intersect_key($empresas, [$solo_empresa => true]);
}
if (!$empresas) {
    fplc_log("No hay empresas configuradas ('cron' => ['empresas' => ...] en FACTURA_LECTURA_CONFIG) ni --empresa con --dir.");
    exit(1);
}

// Módulo de cada empresa; una corrida por módulo
$por_modulo = [];
foreach ($empresas as $empresa_id => $cfg) {
    $m = fplc_modulo($conexion, intval($empresa_id), (array)$cfg);
    if (!$m) {
        fplc_log("Empresa $empresa_id: ERROR: no se pudo determinar el módulo (2 o 5): el usuario no tiene permiso para agregar facturas de proveedores.");
        continue;
    }
    $por_modulo[$m][$empresa_id] = $cfg;
}
if ($solo_modulo) {
    $por_modulo = array_intersect_key($por_modulo, [$solo_modulo => true]);
}
if (count($por_modulo) > 1) {
    // Un proceso por módulo (los dos modelos de facturas no se pueden cargar juntos)
    $codigo = 0;
    foreach (array_keys($por_modulo) as $m) {
        $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' --modulo=' . intval($m)
             . ($solo_empresa ? ' --empresa=' . $solo_empresa : '') . ($simular ? ' --simular' : '');
        passthru($cmd, $salida);
        $codigo = max($codigo, intval($salida));
    }
    exit($codigo);
}
if (!$por_modulo) exit(1);

$modulo = array_key_first($por_modulo);
$empresas = $por_modulo[$modulo];
[$carpeta_modulo, $pagina] = FPL_CRON_MODULOS[$modulo];
require_once __DIR__ . '/../modules/' . $carpeta_modulo . '/facturas_proveedores_model.php';
require_once __DIR__ . '/../modules/' . $carpeta_modulo . '/facturas_proveedores_lectura_model.php';
fplc_log("Módulo $modulo ($carpeta_modulo)");

$total = ['procesados' => 0, 'no_procesados' => 0];
foreach ($empresas as $empresa_id => $cfg) {
    $empresa_id = intval($empresa_id);
    $dir = rtrim((string)($cfg['directorio'] ?? ''), "/\\");
    $usuario_id = intval($cfg['usuario_id'] ?? 0);
    $sucursal_id = intval($cfg['sucursal_id'] ?? 0);
    fplc_log("Empresa $empresa_id: carpeta $dir" . ($simular ? ' (simulación)' : ''));

    if ($dir === '' || !is_dir($dir) || !is_readable($dir)) {
        fplc_log('  ERROR: la carpeta no existe o no se puede leer.');
        continue;
    }
    // El usuario tiene que poder cargar facturas de proveedores en esa empresa
    if ($usuario_id <= 0 || !usuario_tiene_acceso_pagina($conexion, $usuario_id, $empresa_id, $pagina)
        || !usuario_puede_funcion($conexion, $usuario_id, $empresa_id, $pagina, 'agregar')) {
        fplc_log("  ERROR: el usuario $usuario_id no tiene permiso para agregar facturas de proveedores en la empresa.");
        continue;
    }
    $dir_ok = $dir . DIRECTORY_SEPARATOR . 'procesados';
    $dir_no = $dir . DIRECTORY_SEPARATOR . 'no_procesados';
    foreach ([$dir_ok, $dir_no] as $d) {
        if (!is_dir($d) && !mkdir($d, 0755, true)) {
            fplc_log("  ERROR: no se pudo crear $d.");
            continue 2;
        }
    }

    // Una sola corrida por empresa a la vez
    $lock = 'fp_lectura_cron_' . $empresa_id;
    $stmt = mysqli_prepare($conexion, "SELECT GET_LOCK(?, 1)");
    mysqli_stmt_bind_param($stmt, "s", $lock);
    mysqli_stmt_execute($stmt);
    $tiene_lock = intval(mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] ?? 0) === 1;
    mysqli_stmt_close($stmt);
    if (!$tiene_lock) {
        fplc_log('  Otra corrida está procesando esta empresa: se saltea.');
        continue;
    }

    // Lo que usan el alta y los accesos por sucursal (AccesosSucursalesHelper lee el usuario de la sesión)
    $_SESSION['usuario_id'] = $usuario_id;
    $_SESSION['empresa_id'] = $empresa_id;

    try {
        $archivos = [];
        foreach (scandir($dir) ?: [] as $nombre) {
            $ruta = $dir . DIRECTORY_SEPARATOR . $nombre;
            if ($nombre[0] === '.' || !is_file($ruta)) continue;
            $archivos[] = $ruta;
        }
        fplc_log('  ' . count($archivos) . ' archivo(s) en la carpeta.');

        foreach ($archivos as $ruta) {
            $nombre = basename($ruta);
            fplc_log("  - $nombre");
            if (!in_array(strtolower(pathinfo($nombre, PATHINFO_EXTENSION)), FPL_CRON_EXTENSIONES, true)) {
                fplc_no_procesado($ruta, $dir_no, ['No es un PDF, JPG o PNG.'], $simular);
                $total['no_procesados']++;
                continue;
            }
            try {
                $adjunto = fplGuardarArchivoDesdeRuta($conexion, $empresa_id, $usuario_id, $ruta, $nombre);
                $p = fplProcesar($conexion, $empresa_id, $usuario_id, $adjunto, []);
                $leido = ($p['form']['comprobante_tipo_nombre'] ?: '?') . ' ' . $p['form']['comprobante_pv'] . '-' . $p['form']['comprobante_nro']
                       . ' de ' . ($p['form']['proveedor_nombre'] ?: ($p['proveedor_nuevo']['razon_social'] ?? '?'))
                       . ' (' . (implode(' + ', $p['capas']) ?: 'sin datos leídos') . ')';
                fplc_log("     Leído: $leido");
                if (!$p['listo']) {
                    $motivos = fplMotivosNoListo($p);
                    if (!empty($p['proveedor_nuevo'])) $motivos[] = 'Dar de alta el proveedor desde "Cargar desde archivo" en la pantalla.';
                    if (!empty($p['ia']['error'])) $motivos[] = 'IA: ' . $p['ia']['error'];
                    fplc_no_procesado($ruta, $dir_no, $motivos ?: ['Datos incompletos.'], $simular);
                    $total['no_procesados']++;
                    continue;
                }
                if ($simular) {
                    fplc_log('     Listo para guardar en Borrador (simulación: no se guarda).');
                    $total['procesados']++;
                    continue;
                }
                $r = fplGuardarBorrador($conexion, $empresa_id, $usuario_id, $adjunto['adjunto_id'], $sucursal_id);
                if (!$r['success']) {
                    fplc_no_procesado($ruta, $dir_no, array_merge([$r['message']], $r['motivos']), $simular);
                    $total['no_procesados']++;
                    continue;
                }
                fplc_mover($ruta, $dir_ok);
                fplc_log('     PROCESADO: factura ' . $r['factura_proveedor_id'] . ' en Borrador.');
                $total['procesados']++;
            } catch (Throwable $ex) {
                error_log("cron facturas_proveedores_lectura ($nombre): " . $ex->getMessage());
                $total['no_procesados']++;
                try {
                    if (is_file($ruta)) fplc_no_procesado($ruta, $dir_no, [$ex->getMessage()], $simular);
                } catch (Throwable $ex2) {
                    fplc_log('     ERROR: ' . $ex2->getMessage());
                }
            }
        }
    } finally {
        $stmt = mysqli_prepare($conexion, "SELECT RELEASE_LOCK(?)");
        mysqli_stmt_bind_param($stmt, "s", $lock);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

fplc_log("Fin: {$total['procesados']} procesado(s), {$total['no_procesados']} no procesado(s).");
