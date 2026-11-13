<?php
/**
 * Prueba de comparación del recálculo de listas de precios (doc 18, Fase B). Solo desarrollo, solo CLI.
 *
 * Sobre una base de PRUEBAS (nunca el maestro):
 *   1. La primera vez copia las tablas de listas a zz_lp_* (en la misma base de pruebas); en las corridas
 *      siguientes las restaura desde ahí, así cada corrida parte del mismo estado.
 *   2. Saca la foto "antes" (precios vigentes) y recalcula todas las listas activas de cada empresa con la
 *      función real del modelo (recalcularPreciosLista), primero las que no dependen de otra lista.
 *   3. Saca la foto "después" y la guarda en salidas/<etiqueta>.csv.
 *
 * Uso:
 *   php foto_precios.php --db=<base_prueba> --etiqueta=base
 *   php foto_precios.php --db=<base_prueba> --etiqueta=c1 --comparar=base
 *   php foto_precios.php --comparar=base --contra=c1          (solo compara dos fotos guardadas)
 * Opciones: --modulo=gestion_normal|gestion (por defecto gestion_normal), --empresa=<id> (solo esa empresa),
 *           --reiniciar-copia (vuelve a copiar las tablas actuales a zz_lp_*; usar solo con la base recién restaurada).
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Solo línea de comandos.\n");
}

$opt = getopt('', ['db:', 'etiqueta:', 'comparar:', 'contra:', 'modulo:', 'empresa:', 'reiniciar-copia']);
$dir_salidas = __DIR__ . '/salidas';
if (!is_dir($dir_salidas)) {
    mkdir($dir_salidas, 0777, true);
}

// ---------------------------------------------------------------- solo comparar dos fotos guardadas
if (empty($opt['db'])) {
    if (empty($opt['comparar']) || empty($opt['contra'])) {
        exit("Falta --db=<base_prueba> (o --comparar=<a> --contra=<b> para solo comparar).\n");
    }
    comparar_fotos($dir_salidas, $opt['comparar'], $opt['contra']);
    exit(0);
}

// ---------------------------------------------------------------- guardas de seguridad
$db = $opt['db'];
if (!preg_match('/^[A-Za-z0-9_]+$/', $db)) {
    exit("Nombre de base inválido.\n");
}
if ($db === 'gestion_multipyme' || stripos($db, 'prueba') === false) {
    exit("Me niego: la base tiene que ser una copia de pruebas (el nombre debe contener 'prueba' y no ser el maestro).\n");
}
$etiqueta = $opt['etiqueta'] ?? '';
if (!preg_match('/^[A-Za-z0-9_-]+$/', $etiqueta)) {
    exit("Falta --etiqueta=<nombre> (letras, números, _ o -).\n");
}
$modulo = $opt['modulo'] ?? 'gestion_normal';
if (!in_array($modulo, ['gestion_normal', 'gestion'], true)) {
    exit("--modulo debe ser gestion_normal o gestion.\n");
}
$solo_empresa = isset($opt['empresa']) ? intval($opt['empresa']) : 0;

$raiz = realpath(__DIR__ . '/../../..');
require_once $raiz . '/.env';
$prueba = mysqli_connect(IP_HOST, DB_USER, DB_PASS, $db);
if (!$prueba) {
    exit("No se pudo conectar a $db: " . mysqli_connect_error() . "\n");
}
mysqli_set_charset($prueba, 'utf8mb4');
if (mysqli_fetch_row(mysqli_query($prueba, 'SELECT DATABASE()'))[0] !== $db) {
    exit("La conexión no quedó en $db.\n");
}

// El modelo incluye db.php (conecta al maestro). Se carga y se reemplazan las conexiones globales por la de
// pruebas antes de llamar a nada; la del maestro se cierra sin usarse.
$_SESSION = ['usuario_id' => 1];
require_once $raiz . "/modules/$modulo/listas_precios_model.php";
if (isset($conn) && $conn instanceof mysqli && $conn !== $prueba) {
    mysqli_close($conn);
}
$conn = $prueba;
$conexion = $prueba;

// ---------------------------------------------------------------- copia / restauración de las tablas
$tablas = ['gestion__listas_precios', 'gestion__listas_precios_productos', 'gestion__listas_precios_productos_historial'];
$hay_copia = mysqli_num_rows(mysqli_query($prueba, "SHOW TABLES LIKE 'zz_lp_gestion__listas_precios_productos'")) > 0;
mysqli_query($prueba, 'SET FOREIGN_KEY_CHECKS = 0');
if (!$hay_copia || isset($opt['reiniciar-copia'])) {
    foreach ($tablas as $t) {
        mysqli_query($prueba, "DROP TABLE IF EXISTS `zz_lp_$t`");
        mysqli_query($prueba, "CREATE TABLE `zz_lp_$t` LIKE `$t`");
        mysqli_query($prueba, "INSERT INTO `zz_lp_$t` SELECT * FROM `$t`");
    }
    echo "Copia guardada en zz_lp_* (estado de partida).\n";
} else {
    foreach ($tablas as $t) {
        mysqli_query($prueba, "DELETE FROM `$t`");
        mysqli_query($prueba, "INSERT INTO `$t` SELECT * FROM `zz_lp_$t`");
    }
    echo "Tablas restauradas desde zz_lp_*.\n";
}
mysqli_query($prueba, 'SET FOREIGN_KEY_CHECKS = 1');

// ---------------------------------------------------------------- foto antes, recálculo, foto después
guardar_foto($prueba, "$dir_salidas/{$etiqueta}_antes.csv", $solo_empresa);

$where_emp = $solo_empresa > 0 ? "AND empresa_id = $solo_empresa" : '';
$res = mysqli_query($prueba, "SELECT lista_precio_id, empresa_id, lista_precio_nombre, lista_precio_origen_id, lista_base_id
                              FROM gestion__listas_precios WHERE tabla_estado_registro_id = 1 $where_emp
                              ORDER BY empresa_id, lista_precio_id");
$listas = [];
while ($r = mysqli_fetch_assoc($res)) {
    $listas[(int) $r['lista_precio_id']] = $r;
}

// Orden de dependencia: una lista va después de su lista base (si la base está entre las que se recalculan).
$orden = [];
$pendientes = $listas;
for ($vuelta = 0; $pendientes && $vuelta <= count($listas); $vuelta++) {
    foreach ($pendientes as $id => $l) {
        $base = (int) $l['lista_base_id'];
        if ($base === 0 || !isset($listas[$base]) || in_array($base, $orden, true)) {
            $orden[] = $id;
            unset($pendientes[$id]);
        }
    }
}
$orden = array_merge($orden, array_keys($pendientes)); // dependencias circulares: al final

$log = fopen("$dir_salidas/{$etiqueta}_log.txt", 'w');
foreach ($orden as $id) {
    $l = $listas[$id];
    $r = recalcularPreciosLista($prueba, $id, (int) $l['empresa_id']);
    $linea = sprintf("empresa %d lista %d (%s, origen %s, base %s): %s %s",
        $l['empresa_id'], $id, $l['lista_precio_nombre'], $l['lista_precio_origen_id'], $l['lista_base_id'] ?? '-',
        !empty($r['success']) ? 'OK' : 'RECHAZADO/ERROR', $r['message'] ?? $r['error'] ?? '');
    echo $linea . "\n";
    fwrite($log, $linea . "\n");
}
fclose($log);

guardar_foto($prueba, "$dir_salidas/$etiqueta.csv", $solo_empresa);
echo "Foto guardada: salidas/$etiqueta.csv\n";

if (!empty($opt['comparar'])) {
    comparar_fotos($dir_salidas, $opt['comparar'], $etiqueta);
}

// ================================================================= funciones

/** Precios vigentes (estado 1) por lista y producto; sin ids de fila ni fechas, que cambian en cada corrida. */
function guardar_foto(mysqli $c, string $archivo, int $empresa): void
{
    $where = $empresa > 0 ? "AND lpp.empresa_id = $empresa" : '';
    $res = mysqli_query($c, "SELECT lpp.empresa_id, lpp.lista_precio_id, lpp.producto_id,
                                    ROUND(lpp.precio_final, 2) AS precio_final, lpp.es_manual,
                                    COALESCE(lpp.lista_precio_regla_id, '') AS regla_id,
                                    ROUND(COALESCE(lpp.porcentaje_regla_aplicado, 0), 4) AS porcentaje
                             FROM gestion__listas_precios_productos lpp
                             WHERE lpp.tabla_estado_registro_id = 1 $where
                             ORDER BY lpp.empresa_id, lpp.lista_precio_id, lpp.producto_id, lpp.lista_precio_producto_id");
    $f = fopen($archivo, 'w');
    fputcsv($f, ['empresa_id', 'lista_precio_id', 'producto_id', 'precio_final', 'es_manual', 'regla_id', 'porcentaje']);
    while ($r = mysqli_fetch_row($res)) {
        fputcsv($f, $r);
    }
    fclose($f);
}

function leer_foto(string $archivo): array
{
    if (!is_file($archivo)) {
        exit("No existe la foto $archivo\n");
    }
    $f = fopen($archivo, 'r');
    fgetcsv($f);
    $filas = [];
    while (($r = fgetcsv($f)) !== false) {
        // Clave lista|producto; si hubiera dos vigentes del mismo producto, se numeran para no perder ninguna.
        $k = $r[1] . '|' . $r[2];
        $n = 0;
        while (isset($filas["$k#$n"])) {
            $n++;
        }
        $filas["$k#$n"] = $r;
    }
    fclose($f);
    return $filas;
}

function comparar_fotos(string $dir, string $a, string $b): void
{
    $fa = leer_foto("$dir/$a.csv");
    $fb = leer_foto("$dir/$b.csv");
    $porLista = [];
    $ejemplos = [];
    $tocar = function ($lista, $tipo, $texto) use (&$porLista, &$ejemplos) {
        $porLista[$lista][$tipo] = ($porLista[$lista][$tipo] ?? 0) + 1;
        if (count($ejemplos[$lista] ?? []) < 10) {
            $ejemplos[$lista][] = $texto;
        }
    };
    foreach ($fa as $k => $ra) {
        $lista = $ra[0] . '/' . $ra[1];
        if (!isset($fb[$k])) {
            $tocar($lista, 'bajas', "  - baja    prod {$ra[2]}: {$ra[3]} (manual {$ra[4]}, regla {$ra[5]})");
            continue;
        }
        $rb = $fb[$k];
        if ($ra[3] !== $rb[3] || $ra[4] !== $rb[4] || $ra[5] !== $rb[5] || $ra[6] !== $rb[6]) {
            $tocar($lista, 'cambios', "  ~ cambio  prod {$ra[2]}: {$ra[3]} -> {$rb[3]} | manual {$ra[4]} -> {$rb[4]} | regla {$ra[5]} -> {$rb[5]} | % {$ra[6]} -> {$rb[6]}");
        } else {
            $porLista[$lista]['iguales'] = ($porLista[$lista]['iguales'] ?? 0) + 1;
        }
    }
    foreach ($fb as $k => $rb) {
        if (!isset($fa[$k])) {
            $tocar($rb[0] . '/' . $rb[1], 'altas', "  + alta    prod {$rb[2]}: {$rb[3]} (manual {$rb[4]}, regla {$rb[5]})");
        }
    }
    ksort($porLista);
    echo "\n=== Diff $a -> $b (empresa/lista) ===\n";
    $hay = false;
    foreach ($porLista as $lista => $c) {
        $dif = ($c['altas'] ?? 0) + ($c['bajas'] ?? 0) + ($c['cambios'] ?? 0);
        $hay = $hay || $dif > 0;
        printf("%-10s iguales %6d | altas %6d | bajas %6d | cambios %6d%s\n", $lista,
            $c['iguales'] ?? 0, $c['altas'] ?? 0, $c['bajas'] ?? 0, $c['cambios'] ?? 0, $dif ? '' : '  (idéntica)');
        foreach ($ejemplos[$lista] ?? [] as $e) {
            echo $e . "\n";
        }
    }
    echo $hay ? "Hay diferencias.\n" : "Sin diferencias.\n";
}
