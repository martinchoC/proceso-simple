<?php
/**
 * core/formato.php — formato de fechas y números de cada empresa (conf__empresas.formato_fecha,
 * separador_miles, separador_decimal; script 20261031_formato_empresa_01_columnas.sql; decimales_stock,
 * script 20261107_formato_empresa_02_decimales_stock.sql).
 *
 * header1.php llama a fmt_usar_empresa() con la empresa activa y publica la configuración para el
 * navegador (window.APP_FORMATO, la usa assets/js/formato.js: global Fmt). En PHP, fmt_fecha() /
 * fmt_fecha_hora() / fmt_numero() / fmt_moneda() formatean con la empresa activa.
 *
 * Solo pantallas: los datos se siguen guardando y viajando en ISO (AAAA-MM-DD) y con punto decimal.
 */

const FORMATOS_FECHA = [
    'd/m/Y' => 'dd/mm/aaaa',
    'm/d/Y' => 'mm/dd/aaaa',
    'Y-m-d' => 'aaaa-mm-dd',
    'd-m-Y' => 'dd-mm-aaaa',
    'd.m.Y' => 'dd.mm.aaaa',
];
const SEPARADORES_MILES = ['PUNTO' => '.', 'COMA' => ',', 'ESPACIO' => ' ', 'NINGUNO' => ''];
const SEPARADORES_DECIMAL = ['COMA' => ',', 'PUNTO' => '.'];
const FORMATO_DEFAULT = ['fecha' => 'd/m/Y', 'miles' => 'PUNTO', 'decimal' => 'COMA', 'stock' => 2];
/** Decimales posibles de las cantidades de stock (el libro, stock_core_model.php, redondea a 4). */
const DECIMALES_STOCK_MAX = 4;

/** Valida y completa una configuración (códigos); miles y decimal no pueden ser el mismo carácter. */
function fmt_normalizar(array $cfg): array
{
    $fecha = (string) ($cfg['fecha'] ?? '');
    $miles = strtoupper(trim((string) ($cfg['miles'] ?? '')));
    $decimal = strtoupper(trim((string) ($cfg['decimal'] ?? '')));
    if (!isset(FORMATOS_FECHA[$fecha])) $fecha = FORMATO_DEFAULT['fecha'];
    if (!isset(SEPARADORES_DECIMAL[$decimal])) $decimal = FORMATO_DEFAULT['decimal'];
    if (!isset(SEPARADORES_MILES[$miles])) $miles = FORMATO_DEFAULT['miles'];
    if (SEPARADORES_MILES[$miles] === SEPARADORES_DECIMAL[$decimal]) {
        $miles = $decimal === 'COMA' ? 'PUNTO' : 'COMA';
    }
    $stock = $cfg['stock'] ?? null;
    $stock = (is_numeric($stock) && (int) $stock >= 0 && (int) $stock <= DECIMALES_STOCK_MAX) ? (int) $stock : FORMATO_DEFAULT['stock'];
    return ['fecha' => $fecha, 'miles' => $miles, 'decimal' => $decimal, 'stock' => $stock];
}

/** Configuración de una empresa (códigos). Sin columnas todavía (script sin correr) o sin empresa: el default. */
function fmt_config_empresa($conexion, int $empresa_id): array
{
    static $cache = [];
    if ($empresa_id <= 0) return FORMATO_DEFAULT;
    if (isset($cache[$empresa_id])) return $cache[$empresa_id];
    $cfg = FORMATO_DEFAULT;
    $fila = null;
    // Con decimales_stock (script 20261107_formato_empresa_02); si la columna todavía no está, sin ella
    foreach (["SELECT formato_fecha, separador_miles, separador_decimal, decimales_stock FROM conf__empresas WHERE empresa_id = ?",
              "SELECT formato_fecha, separador_miles, separador_decimal FROM conf__empresas WHERE empresa_id = ?"] as $sql) {
        try {
            $stmt = mysqli_prepare($conexion, $sql);
            if (!$stmt) continue;
            mysqli_stmt_bind_param($stmt, 'i', $empresa_id);
            mysqli_stmt_execute($stmt);
            $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
            mysqli_stmt_close($stmt);
            break;
        } catch (Throwable $e) {
            error_log('fmt_config_empresa: ' . $e->getMessage());
        }
    }
    if ($fila) {
        $cfg = fmt_normalizar(['fecha' => $fila['formato_fecha'], 'miles' => $fila['separador_miles'],
                               'decimal' => $fila['separador_decimal'], 'stock' => $fila['decimales_stock'] ?? null]);
    }
    return $cache[$empresa_id] = $cfg;
}

/** Configuración activa del request (la fija header1.php; sin fijar, el default). */
function fmt_actual(?array $nueva = null): array
{
    static $actual = FORMATO_DEFAULT;
    if ($nueva !== null) $actual = fmt_normalizar($nueva);
    return $actual;
}

function fmt_usar_empresa($conexion, int $empresa_id): array
{
    return fmt_actual(fmt_config_empresa($conexion, $empresa_id));
}

/** Lo que recibe assets/js/formato.js (caracteres, no códigos). */
function fmt_config_js(?array $cfg = null): array
{
    $cfg = $cfg ?? fmt_actual();
    return [
        'fecha' => $cfg['fecha'],
        'miles' => SEPARADORES_MILES[$cfg['miles']],
        'decimal' => SEPARADORES_DECIMAL[$cfg['decimal']],
        'stock' => (int) ($cfg['stock'] ?? FORMATO_DEFAULT['stock']),
    ];
}

/** Fecha (ISO, 'AAAA-MM-DD HH:MM:SS', timestamp o DateTimeInterface) en el formato de la empresa; vacía o inválida = ''. */
function fmt_fecha($valor, bool $con_hora = false): string
{
    if ($valor === null || $valor === '' || $valor === '0000-00-00' || $valor === '0000-00-00 00:00:00') return '';
    if ($valor instanceof DateTimeInterface) {
        $ts = $valor->getTimestamp();
    } elseif (is_int($valor)) {
        $ts = $valor;
    } else {
        $ts = strtotime((string) $valor);
        if ($ts === false) return (string) $valor;
    }
    return date(fmt_actual()['fecha'] . ($con_hora ? ' H:i' : ''), $ts);
}

function fmt_fecha_hora($valor): string
{
    return fmt_fecha($valor, true);
}

function fmt_numero($valor, int $decimales = 2): string
{
    $cfg = fmt_actual();
    return number_format((float) $valor, $decimales, SEPARADORES_DECIMAL[$cfg['decimal']], SEPARADORES_MILES[$cfg['miles']]);
}

/** Decimales de las cantidades de stock de la empresa activa (conf__empresas.decimales_stock). */
function fmt_decimales_stock(): int
{
    return (int) (fmt_actual()['stock'] ?? FORMATO_DEFAULT['stock']);
}

/** Cantidad de stock con los decimales de la empresa (para mostrar). */
function fmt_cantidad($valor): string
{
    return fmt_numero($valor, fmt_decimales_stock());
}

/** Redondea una cantidad de stock a los decimales de la empresa (para guardar lo que se carga). */
function fmt_redondear_cantidad($valor, ?int $decimales = null): float
{
    return round((float) $valor, $decimales ?? fmt_decimales_stock());
}

function fmt_moneda($valor, int $decimales = 2, string $simbolo = '$'): string
{
    return ($simbolo !== '' ? $simbolo . ' ' : '') . fmt_numero($valor, $decimales);
}
