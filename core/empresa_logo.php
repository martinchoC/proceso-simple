<?php
/**
 * Logo de la empresa y colores del encabezado de los modales.
 *
 * Dos lugares guardan el logo y se mantienen iguales:
 *   - conf__empresas.logo_archivo: el logo ACTUAL (Empresas, conf, página 27). De él salen
 *     color_logo_oscuro / color_logo_claro, que header1.php usa en el encabezado de los modales.
 *   - gestion__empresas_parametros.logo_archivo de la vigencia abierta (Parámetros de Empresa,
 *     111 / 611). La impresión lee el de la vigencia de la fecha del comprobante.
 * Empresas escribe los dos con emp_logo_establecer(); Parámetros, al guardar la vigencia abierta,
 * actualiza conf__empresas con emp_logo_actualizar_empresa().
 * Colores (color_logo_origen): al cambiar el logo los propone la IA de la empresa (IA) y, si no hay o falla,
 * los calcula GD (AUTO); en Empresas se pueden ajustar a mano (MANUAL) o recalcular. Al abrir una página
 * solo se usa GD, para los que falten.
 * Scripts: _local/sql/mantenimiento/20261101_empresa_logo_colores_01.sql y _02_origen.sql
 */

if (!defined('EMPRESAS_LOGO_DIR_REL')) define('EMPRESAS_LOGO_DIR_REL', 'uploads/empresas');
if (!defined('EMPRESAS_LOGO_MAX_BYTES')) define('EMPRESAS_LOGO_MAX_BYTES', 1048576);

// Colores por defecto (logo de Developsam) si la empresa no tiene logo o no se pudieron calcular.
if (!defined('EMP_LOGO_COLOR_OSCURO')) define('EMP_LOGO_COLOR_OSCURO', '#03112f');
if (!defined('EMP_LOGO_COLOR_CLARO')) define('EMP_LOGO_COLOR_CLARO', '#054a96');

function emp_logo_columna_existe(mysqli $conexion, string $tabla, string $columna): bool
{
    static $cache = [];
    $clave = $tabla . '.' . $columna;
    if (!isset($cache[$clave])) {
        $stmt = mysqli_prepare($conexion, "SELECT COUNT(*) AS n FROM information_schema.COLUMNS
                                           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
        mysqli_stmt_bind_param($stmt, 'ss', $tabla, $columna);
        mysqli_stmt_execute($stmt);
        $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        $cache[$clave] = intval($fila['n'] ?? 0) > 0;
    }
    return $cache[$clave];
}

// Valida y guarda el logo subido. Devuelve ['ruta' => 'uploads/empresas/...' | null] o ['error' => '...'].
function emp_logo_guardar_archivo($archivo, $empresa_id): array
{
    if (!is_array($archivo) || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ruta' => null];
    }
    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'No se pudo recibir el archivo del logo (código ' . intval($archivo['error']) . ').'];
    }
    if ($archivo['size'] > EMPRESAS_LOGO_MAX_BYTES) {
        return ['error' => 'El logo no puede superar 1 MB.'];
    }
    // El tipo se toma del contenido, no de la extensión ni del navegador (sin SVG: puede llevar scripts)
    $info = @getimagesize($archivo['tmp_name']);
    $extensiones = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    if (!$info || !isset($extensiones[$info['mime']])) {
        return ['error' => 'El logo debe ser una imagen PNG, JPG, GIF o WEBP.'];
    }
    $dir = __DIR__ . '/../' . EMPRESAS_LOGO_DIR_REL;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        return ['error' => 'No se pudo crear la carpeta de logos.'];
    }
    $nombre = 'logo_' . intval($empresa_id) . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $extensiones[$info['mime']];
    if (!move_uploaded_file($archivo['tmp_name'], $dir . '/' . $nombre)) {
        error_log("=== emp_logo_guardar_archivo: no se pudo mover el archivo a $dir/$nombre ===");
        return ['error' => 'No se pudo guardar el archivo del logo.'];
    }
    return ['ruta' => EMPRESAS_LOGO_DIR_REL . '/' . $nombre];
}

// Ruta absoluta del logo, solo si está dentro de uploads/empresas y existe.
function emp_logo_ruta_absoluta($ruta_relativa): ?string
{
    if (empty($ruta_relativa)) return null;
    $base = realpath(__DIR__ . '/../' . EMPRESAS_LOGO_DIR_REL);
    $archivo = realpath(__DIR__ . '/../' . $ruta_relativa);
    if (!$base || !$archivo || strpos($archivo, $base . DIRECTORY_SEPARATOR) !== 0 || !is_file($archivo)) return null;
    return $archivo;
}

// Logo como data URI para la vista previa (vacío si no hay o el archivo no está).
function emp_logo_data_uri($ruta_relativa): string
{
    $archivo = emp_logo_ruta_absoluta($ruta_relativa);
    if (!$archivo) return '';
    $info = @getimagesize($archivo);
    if (!$info) return '';
    return 'data:' . $info['mime'] . ';base64,' . base64_encode(file_get_contents($archivo));
}

// ---------- Colores ----------

function emp_logo_luminancia(array $rgb): float
{
    $c = array_map(function ($v) {
        $v /= 255;
        return $v <= 0.03928 ? $v / 12.92 : pow(($v + 0.055) / 1.055, 2.4);
    }, $rgb);
    return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
}

// Oscurece hasta que el texto blanco tenga buen contraste (luminancia <= $maxima).
function emp_logo_oscurecer_hasta(array $rgb, float $maxima): array
{
    for ($i = 0; $i < 40 && emp_logo_luminancia($rgb) > $maxima; $i++) {
        $rgb = array_map(function ($v) { return $v * 0.92; }, $rgb);
    }
    return $rgb;
}

function emp_logo_mezclar(array $a, array $b, float $t): array
{
    return [$a[0] + ($b[0] - $a[0]) * $t, $a[1] + ($b[1] - $a[1]) * $t, $a[2] + ($b[2] - $a[2]) * $t];
}

function emp_logo_hex(array $rgb): string
{
    return sprintf('#%02x%02x%02x', max(0, min(255, (int) round($rgb[0]))), max(0, min(255, (int) round($rgb[1]))), max(0, min(255, (int) round($rgb[2]))));
}

function emp_logo_rgb(string $hex): array
{
    $hex = ltrim($hex, '#');
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

/**
 * Los dos colores principales del logo, listos para un fondo con texto blanco.
 * Se ignoran el fondo transparente, los blancos y los grises claros. Devuelve
 * ['oscuro' => '#rrggbb', 'claro' => '#rrggbb'] o null si no hay imagen o no tiene color.
 */
function emp_logo_colores($ruta_relativa): ?array
{
    $archivo = emp_logo_ruta_absoluta($ruta_relativa);
    if (!$archivo || !function_exists('imagecreatefromstring')) return null;
    $img = @imagecreatefromstring(file_get_contents($archivo));
    if (!$img) return null;

    $lado = 64;
    $chica = imagecreatetruecolor($lado, $lado);
    imagealphablending($chica, false);
    imagesavealpha($chica, true);
    imagefill($chica, 0, 0, imagecolorallocatealpha($chica, 255, 255, 255, 127));
    imagecopyresampled($chica, $img, 0, 0, 0, 0, $lado, $lado, imagesx($img), imagesy($img));
    imagedestroy($img);

    // Agrupa los píxeles en cubos de 32 niveles por canal y promedia cada cubo
    $grupos = [];
    for ($x = 0; $x < $lado; $x++) {
        for ($y = 0; $y < $lado; $y++) {
            $rgba = imagecolorat($chica, $x, $y);
            if ((($rgba >> 24) & 0x7F) > 64) continue; // transparente
            $r = ($rgba >> 16) & 0xFF; $g = ($rgba >> 8) & 0xFF; $b = $rgba & 0xFF;
            $max = max($r, $g, $b); $min = min($r, $g, $b);
            $luz = ($max + $min) / 510;
            $sat = $max === $min ? 0 : ($max - $min) / (255 - abs($max + $min - 255));
            if ($luz > 0.9 || ($sat < 0.15 && $luz > 0.7)) continue; // blancos y grises claros = fondo
            $clave = ($r >> 5) . '-' . ($g >> 5) . '-' . ($b >> 5);
            if (!isset($grupos[$clave])) $grupos[$clave] = [0, 0, 0, 0];
            $grupos[$clave][0]++;
            $grupos[$clave][1] += $r; $grupos[$clave][2] += $g; $grupos[$clave][3] += $b;
        }
    }
    imagedestroy($chica);
    if (empty($grupos)) return null;

    // Cada grupo: color promedio, cantidad, saturación y luminancia
    $colores = [];
    $maximo = 0;
    foreach ($grupos as $g) {
        $c = [$g[1] / $g[0], $g[2] / $g[0], $g[3] / $g[0]];
        $max = max($c); $min = min($c);
        $sat = $max == $min ? 0 : ($max - $min) / (255 - abs($max + $min - 255));
        $colores[] = ['rgb' => $c, 'n' => $g[0], 'sat' => $sat, 'lum' => emp_logo_luminancia($c)];
        $maximo = max($maximo, $g[0]);
    }
    // Se ignoran los grupos chicos (bordes suavizados, detalles)
    $colores = array_values(array_filter($colores, function ($c) use ($maximo) { return $c['n'] >= $maximo * 0.08; }));

    // Principal: el más vistoso que admite texto blanco (los colores muy claros, como un amarillo,
    // habría que oscurecerlos tanto que dejan de parecerse al logo)
    usort($colores, function ($a, $b) {
        $pa = $a['n'] * (0.3 + $a['sat']) * ($a['lum'] > 0.35 ? 0.3 : 1);
        $pb = $b['n'] * (0.3 + $b['sat']) * ($b['lum'] > 0.35 ? 0.3 : 1);
        return $pb <=> $pa;
    });
    $principal = $colores[0];

    // Segundo: el más oscuro de los otros colores del logo que también admite texto blanco
    $segundo = null;
    foreach (array_slice($colores, 1) as $c) {
        $distancia = sqrt(pow($c['rgb'][0] - $principal['rgb'][0], 2) + pow($c['rgb'][1] - $principal['rgb'][1], 2) + pow($c['rgb'][2] - $principal['rgb'][2], 2));
        if ($distancia <= 70 || $c['lum'] > 0.35) continue;
        if ($segundo === null || $c['lum'] < $segundo['lum']) $segundo = $c;
    }
    $principal = $principal['rgb'];
    if ($segundo !== null) {
        $segundo = $segundo['rgb'];
    } else {
        // Un solo color: el degradé va de ese color a una versión más clara (o más oscura)
        $segundo = emp_logo_luminancia($principal) < 0.02
            ? emp_logo_mezclar($principal, [255, 255, 255], 0.3)
            : emp_logo_mezclar($principal, [0, 0, 0], 0.55);
    }

    [$oscuro, $claro] = emp_logo_luminancia($principal) <= emp_logo_luminancia($segundo)
        ? [$principal, $segundo] : [$segundo, $principal];
    // Texto blanco encima: contraste >= 5,5 en el oscuro y >= 3,5 en el claro
    return [
        'oscuro' => emp_logo_hex(emp_logo_oscurecer_hasta($oscuro, 0.14)),
        'claro'  => emp_logo_hex(emp_logo_oscurecer_hasta($claro, 0.25)),
    ];
}

// Valida un par de colores y los ajusta para texto blanco (oscuro: contraste >= 5,5, claro: >= 3,5).
// Devuelve ['oscuro', 'claro'] o null si alguno no es #rrggbb.
function emp_logo_normalizar_colores($oscuro, $claro): ?array
{
    if (!preg_match('/^#[0-9a-f]{6}$/i', (string) $oscuro) || !preg_match('/^#[0-9a-f]{6}$/i', (string) $claro)) return null;
    $o = emp_logo_rgb($oscuro);
    $c = emp_logo_rgb($claro);
    if (emp_logo_luminancia($o) > emp_logo_luminancia($c)) [$o, $c] = [$c, $o];
    return [
        'oscuro' => emp_logo_hex(emp_logo_oscurecer_hasta($o, 0.14)),
        'claro'  => emp_logo_hex(emp_logo_oscurecer_hasta($c, 0.25)),
    ];
}

/**
 * Colores propuestos por la IA de la empresa (o la del servidor) para el logo: mismo proveedor y clave que
 * la lectura de facturas (core/facturas_lectura). Corto (20 s, sin reintentos): si no hay clave, falla o
 * responde algo inválido devuelve null y se usa el cálculo con GD. Nunca se llama al abrir una página.
 */
function emp_logo_colores_ia(mysqli $conexion, int $empresa_id, $ruta_relativa): ?array
{
    $archivo = emp_logo_ruta_absoluta($ruta_relativa);
    if (!$archivo || !function_exists('curl_init')) return null;
    $info = @getimagesize($archivo);
    if (!$info) return null;

    require_once __DIR__ . '/ia_empresa.php';
    try {
        // null si la empresa tiene la IA deshabilitada o no hay clave (Configuración > Empresas)
        $proveedor = ia_empresa_proveedor($conexion, $empresa_id, [
            'timeout' => 20, 'reintentos' => 0, 'max_tokens' => 2048, 'esfuerzo' => 'low', 'fallbacks' => false,
        ]);
        if ($proveedor === null) return null;

        $instrucciones = "Sos diseñador de interfaces. Recibís el logo de una empresa y elegís dos colores para el fondo "
            . "de la barra superior, el menú lateral y los encabezados de las ventanas de su sistema de gestión, en un "
            . "degradé de 'oscuro' a 'claro'. Encima va texto blanco. Reglas:\n"
            . "- Los colores tienen que reconocerse como los del logo: no inventes tonos que el logo no tenga.\n"
            . "- Ignorá el fondo blanco, transparente o gris claro del logo.\n"
            . "- 'claro' es el color más representativo y vistoso del logo, con contraste de al menos 4.5:1 con el blanco; "
            . "si ese color es muy claro (amarillo, lima, celeste), usá una versión más oscura del mismo tono.\n"
            . "- 'oscuro' es el color más oscuro del logo (por ejemplo negro o azul marino), con contraste de al menos 7:1 "
            . "con el blanco; si el logo tiene un solo color, usá una versión bien oscura de ese tono.\n"
            . "- Formato #rrggbb en minúsculas.";
        $esquema = [
            'type' => 'object', 'additionalProperties' => false, 'required' => ['oscuro', 'claro'],
            'properties' => [
                'oscuro' => ['type' => 'string', 'description' => 'Color oscuro del degradé, #rrggbb'],
                'claro'  => ['type' => 'string', 'description' => 'Color claro del degradé, #rrggbb'],
            ],
        ];
        $r = $proveedor->extraer((string) file_get_contents($archivo), $info['mime'], $instrucciones, $esquema,
            'Elegí los dos colores del encabezado para este logo y devolvé solo el JSON {"oscuro": "#rrggbb", "claro": "#rrggbb"}.');
        $colores = emp_logo_normalizar_colores(strtolower(trim((string) ($r['datos']['oscuro'] ?? ''))), strtolower(trim((string) ($r['datos']['claro'] ?? ''))));
        error_log("=== emp_logo_colores_ia empresa $empresa_id: " . $proveedor->nombre() . ' ' . ($r['modelo'] ?? '')
            . ' tokens ' . intval($r['tokens_entrada'] ?? 0) . '/' . intval($r['tokens_salida'] ?? 0)
            . ' -> ' . ($colores ? $colores['oscuro'] . ' ' . $colores['claro'] : 'respuesta inválida') . ' ===');
        return $colores;
    } catch (Throwable $e) {
        error_log("=== emp_logo_colores_ia empresa $empresa_id: " . $e->getMessage() . ' ===');
        return null;
    }
}

/**
 * Colores de un logo: primero la IA (si $usar_ia y hay clave), si no el cálculo con GD.
 * Devuelve ['oscuro', 'claro', 'origen' => 'IA' | 'AUTO'] o null si no hay logo o no tiene color.
 */
function emp_logo_calcular(mysqli $conexion, int $empresa_id, $ruta_relativa, bool $usar_ia = true): ?array
{
    if (empty($ruta_relativa)) return null;
    $colores = $usar_ia ? emp_logo_colores_ia($conexion, $empresa_id, $ruta_relativa) : null;
    if ($colores) return $colores + ['origen' => 'IA'];
    $colores = emp_logo_colores($ruta_relativa);
    return $colores ? $colores + ['origen' => 'AUTO'] : null;
}

// ---------- Sincronización ----------

// Escribe los colores (y el origen, si la columna existe: 20261101_empresa_logo_colores_02_origen.sql).
function emp_logo_guardar_colores(mysqli $conexion, int $empresa_id, ?string $ruta, ?string $oscuro, ?string $claro, ?string $origen): void
{
    if (emp_logo_columna_existe($conexion, 'conf__empresas', 'color_logo_origen')) {
        $stmt = mysqli_prepare($conexion, "UPDATE conf__empresas SET logo_archivo = ?, color_logo_oscuro = ?, color_logo_claro = ?, color_logo_origen = ? WHERE empresa_id = ?");
        mysqli_stmt_bind_param($stmt, 'ssssi', $ruta, $oscuro, $claro, $origen, $empresa_id);
    } else {
        $stmt = mysqli_prepare($conexion, "UPDATE conf__empresas SET logo_archivo = ?, color_logo_oscuro = ?, color_logo_claro = ? WHERE empresa_id = ?");
        mysqli_stmt_bind_param($stmt, 'sssi', $ruta, $oscuro, $claro, $empresa_id);
    }
    if (!mysqli_stmt_execute($stmt)) {
        error_log("=== emp_logo_guardar_colores empresa $empresa_id: " . mysqli_stmt_error($stmt) . " ===");
    }
    mysqli_stmt_close($stmt);
}

// Logo actual de conf__empresas (null si no tiene o la columna no existe).
function emp_logo_actual(mysqli $conexion, int $empresa_id): ?string
{
    if (!emp_logo_columna_existe($conexion, 'conf__empresas', 'logo_archivo')) return null;
    $stmt = mysqli_prepare($conexion, "SELECT logo_archivo FROM conf__empresas WHERE empresa_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $empresa_id);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return !empty($fila['logo_archivo']) ? $fila['logo_archivo'] : null;
}

/**
 * Logo del menú lateral (header1.php): el de la vigencia abierta de Parámetros de Empresa y, si no tiene,
 * el actual de conf__empresas. Devuelve la ruta relativa solo si el archivo existe, o null.
 */
function emp_logo_barra(mysqli $conexion, int $empresa_id): ?string
{
    if ($empresa_id <= 0) return null;
    $ruta = null;
    if (emp_logo_columna_existe($conexion, 'gestion__empresas_parametros', 'logo_archivo')) {
        $stmt = mysqli_prepare($conexion, "SELECT logo_archivo FROM gestion__empresas_parametros
                                           WHERE empresa_id = ? AND f_hasta IS NULL AND tabla_estado_registro_id = 1
                                             AND logo_archivo IS NOT NULL AND logo_archivo <> ''
                                           ORDER BY f_desde DESC LIMIT 1");
        mysqli_stmt_bind_param($stmt, 'i', $empresa_id);
        mysqli_stmt_execute($stmt);
        $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        $ruta = $fila['logo_archivo'] ?? null;
    }
    if (!$ruta || !emp_logo_ruta_absoluta($ruta)) $ruta = emp_logo_actual($conexion, $empresa_id);
    return ($ruta && emp_logo_ruta_absoluta($ruta)) ? $ruta : null;
}

/**
 * Guarda el logo actual en conf__empresas y calcula sus colores (IA → GD) solo si el logo cambió o faltan:
 * con el mismo logo se conservan, también los ajustados a mano. $usar_ia = false en header1.php (abrir una
 * página nunca llama a la IA).
 */
function emp_logo_actualizar_empresa(mysqli $conexion, int $empresa_id, ?string $ruta, bool $usar_ia = true): void
{
    if ($empresa_id <= 0 || !emp_logo_columna_existe($conexion, 'conf__empresas', 'color_logo_claro')) return;
    $ruta = !empty($ruta) ? $ruta : null;

    $stmt = mysqli_prepare($conexion, "SELECT logo_archivo, color_logo_oscuro FROM conf__empresas WHERE empresa_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $empresa_id);
    mysqli_stmt_execute($stmt);
    $actual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$actual) return;
    if (($actual['logo_archivo'] ?: null) === $ruta && $actual['color_logo_oscuro'] !== null) return;

    $colores = emp_logo_calcular($conexion, $empresa_id, $ruta, $usar_ia);
    // '' = calculado sin resultado (logo sin color o archivo ausente): no se vuelve a intentar en cada página
    emp_logo_guardar_colores($conexion, $empresa_id, $ruta,
        $colores['oscuro'] ?? ($ruta ? '' : null), $colores['claro'] ?? ($ruta ? '' : null), $colores['origen'] ?? null);
}

/**
 * Vuelve a calcular los colores del logo actual (IA → GD), aunque estuvieran ajustados a mano.
 * Devuelve ['oscuro', 'claro', 'origen'] o null si la empresa no tiene logo o no se pudieron calcular.
 */
function emp_logo_recalcular(mysqli $conexion, int $empresa_id): ?array
{
    $ruta = emp_logo_actual($conexion, $empresa_id);
    if ($ruta === null) return null;
    $colores = emp_logo_calcular($conexion, $empresa_id, $ruta, true);
    emp_logo_guardar_colores($conexion, $empresa_id, $ruta, $colores['oscuro'] ?? '', $colores['claro'] ?? '', $colores['origen'] ?? null);
    return $colores;
}

/**
 * Colores elegidos a mano (Empresas, conf). Se ajustan para que el texto blanco se lea. Devuelve los
 * colores guardados o null si no son #rrggbb o la empresa no tiene logo (sin logo van los colores por defecto).
 */
function emp_logo_fijar_manual(mysqli $conexion, int $empresa_id, $oscuro, $claro): ?array
{
    $ruta = emp_logo_actual($conexion, $empresa_id);
    $colores = emp_logo_normalizar_colores($oscuro, $claro);
    if ($ruta === null || $colores === null) return null;
    emp_logo_guardar_colores($conexion, $empresa_id, $ruta, $colores['oscuro'], $colores['claro'], 'MANUAL');
    return $colores + ['origen' => 'MANUAL'];
}

// Logo cargado desde Empresas: conf__empresas + vigencia abierta de Parámetros de Empresa.
function emp_logo_establecer(mysqli $conexion, int $empresa_id, ?string $ruta): void
{
    emp_logo_actualizar_empresa($conexion, $empresa_id, $ruta);
    if (!emp_logo_columna_existe($conexion, 'gestion__empresas_parametros', 'logo_archivo')) return;
    $ruta = !empty($ruta) ? $ruta : null;
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__empresas_parametros SET logo_archivo = ?
                                       WHERE empresa_id = ? AND f_hasta IS NULL AND tabla_estado_registro_id = 1");
    mysqli_stmt_bind_param($stmt, 'si', $ruta, $empresa_id);
    if (!mysqli_stmt_execute($stmt)) {
        error_log("=== emp_logo_establecer empresa $empresa_id: " . mysqli_stmt_error($stmt) . " ===");
    }
    mysqli_stmt_close($stmt);
}

/**
 * Colores del encabezado para la empresa activa (header1.php). Si la empresa tiene logo y
 * todavía no se calcularon (empresas migradas por el script), los calcula y los guarda.
 */
function emp_logo_tema(mysqli $conexion, int $empresa_id): array
{
    $tema = ['oscuro' => EMP_LOGO_COLOR_OSCURO, 'claro' => EMP_LOGO_COLOR_CLARO];
    if ($empresa_id <= 0 || !emp_logo_columna_existe($conexion, 'conf__empresas', 'color_logo_claro')) return $tema;

    $leer = function () use ($conexion, $empresa_id) {
        $stmt = mysqli_prepare($conexion, "SELECT logo_archivo, color_logo_oscuro, color_logo_claro FROM conf__empresas WHERE empresa_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $empresa_id);
        mysqli_stmt_execute($stmt);
        $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        return $fila;
    };
    $fila = $leer();
    if (!$fila || empty($fila['logo_archivo'])) return $tema;

    if ($fila['color_logo_oscuro'] === null) {
        emp_logo_actualizar_empresa($conexion, $empresa_id, $fila['logo_archivo'], false);
        $fila = $leer() ?: $fila;
    }
    if (preg_match('/^#[0-9a-f]{6}$/i', (string) $fila['color_logo_oscuro']) && preg_match('/^#[0-9a-f]{6}$/i', (string) $fila['color_logo_claro'])) {
        $tema = ['oscuro' => $fila['color_logo_oscuro'], 'claro' => $fila['color_logo_claro']];
    }
    return $tema;
}

// Variables CSS del encabezado (las usa ui-simple.css en .modal .modal-header).
function emp_logo_css(array $tema): string
{
    $medio = emp_logo_hex(emp_logo_mezclar(emp_logo_rgb($tema['oscuro']), emp_logo_rgb($tema['claro']), 0.45));
    return ':root{--ui-logo-marino:' . $tema['oscuro'] . ';--ui-logo-medio:' . $medio . ';--ui-logo-azul:' . $tema['claro'] . ';}';
}

/**
 * Obtiene los datos de la empresa principal del sistema (es_principal = 1).
 * Si ninguna está marcada, busca la primera empresa activa con logo o la primera activa.
 */
function emp_empresa_principal(mysqli $conexion): ?array
{
    static $empresa_principal = null;
    if ($empresa_principal !== null) {
        return $empresa_principal;
    }

    $tiene_logo = emp_logo_columna_existe($conexion, 'conf__empresas', 'logo_archivo');
    $col_logo = $tiene_logo ? 'logo_archivo' : 'NULL AS logo_archivo';

    if (emp_logo_columna_existe($conexion, 'conf__empresas', 'es_principal')) {
        $stmt = mysqli_prepare($conexion, "SELECT empresa_id, empresa, $col_logo, es_principal
                                           FROM conf__empresas
                                           WHERE es_principal = 1 AND tabla_estado_registro_id = 1
                                           LIMIT 1");
        if ($stmt) {
            mysqli_stmt_execute($stmt);
            $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            mysqli_stmt_close($stmt);
            if ($fila) {
                $empresa_principal = $fila;
                return $empresa_principal;
            }
        }
    }

    // Fallback: empresa activa con logo
    if ($tiene_logo) {
        $res = mysqli_query($conexion, "SELECT empresa_id, empresa, logo_archivo, 0 AS es_principal
                                       FROM conf__empresas
                                       WHERE tabla_estado_registro_id = 1 AND logo_archivo IS NOT NULL AND logo_archivo <> ''
                                       ORDER BY empresa_id ASC LIMIT 1");
        if ($res && ($fila = mysqli_fetch_assoc($res))) {
            $empresa_principal = $fila;
            return $empresa_principal;
        }
    }

    // Fallback: primera empresa activa
    $res = mysqli_query($conexion, "SELECT empresa_id, empresa, $col_logo, 0 AS es_principal
                                   FROM conf__empresas
                                   WHERE tabla_estado_registro_id = 1
                                   ORDER BY empresa_id ASC LIMIT 1");
    if ($res && ($fila = mysqli_fetch_assoc($res))) {
        $empresa_principal = $fila;
        return $empresa_principal;
    }

    return null;
}

/**
 * ID de la empresa principal del sistema.
 */
function emp_empresa_principal_id(mysqli $conexion): ?int
{
    $p = emp_empresa_principal($conexion);
    return $p ? (int)$p['empresa_id'] : null;
}

/**
 * Logo relativo de la empresa principal del sistema (header / login).
 * Devuelve la ruta relativa si el archivo existe en disco, o null.
 */
function emp_logo_principal(mysqli $conexion): ?string
{
    $id = emp_empresa_principal_id($conexion);
    if (!$id) return null;
    return emp_logo_barra($conexion, $id);
}

/**
 * URL del logo inicial del sistema (login.php y cabecera cuando no hay empresa activa).
 * Si la empresa principal tiene logo configurado y existe, devuelve url($ruta).
 * De lo contrario, devuelve asset('img/logo.png').
 */
function emp_logo_inicial(mysqli $conexion): string
{
    $ruta = emp_logo_principal($conexion);
    if ($ruta && emp_logo_ruta_absoluta($ruta)) {
        return url($ruta);
    }
    return asset('img/logo.png');
}

