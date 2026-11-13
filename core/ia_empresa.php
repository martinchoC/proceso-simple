<?php
/**
 * Uso de la IA por empresa: un solo lugar para todos los módulos (Configuración > Empresas).
 *
 * conf__empresas.ia_habilitada (por defecto 1): si es 0, ningún módulo manda nada a la IA (ni con la clave del
 *   servidor). conf__empresas.ia_api_key_cifrada: clave propia (Gemini AIza... / Claude sk-ant-...), cifrada.
 * Clave que se usa: la propia → la de la empresa principal (es_principal = 1) → '' (= la del servidor, CapaIa).
 * Migración: _local/sql/mantenimiento/20261111_ia_empresa_01.sql. Sin las columnas, la IA queda habilitada y la
 * clave sale de Parámetros de Empresa como antes.
 *
 * La clave descifrada nunca se devuelve al navegador ni se loguea: para la pantalla, ia_empresa_estado().
 */

require_once __DIR__ . '/secretos.php';

function ia_empresa_columnas(mysqli $c): bool
{
    static $cache = [];
    $k = spl_object_id($c);
    if (!isset($cache[$k])) {
        $res = mysqli_query($c, "SHOW COLUMNS FROM conf__empresas LIKE 'ia_api_key_cifrada'");
        $cache[$k] = $res && mysqli_num_rows($res) > 0;
    }
    return $cache[$k];
}

/** Fila de IA de la empresa: ['ia_habilitada' => int, 'ia_api_key_cifrada' => string] o null. */
function ia_empresa_fila(mysqli $c, int $empresa_id): ?array
{
    if ($empresa_id <= 0 || !ia_empresa_columnas($c)) return null;
    $st = mysqli_prepare($c, "SELECT ia_habilitada, ia_api_key_cifrada FROM conf__empresas WHERE empresa_id = ?");
    mysqli_stmt_bind_param($st, "i", $empresa_id);
    mysqli_stmt_execute($st);
    $f = mysqli_fetch_assoc(mysqli_stmt_get_result($st));
    mysqli_stmt_close($st);
    return $f ?: null;
}

/** empresa_id de la principal (es_principal = 1, activa) o 0. */
function ia_empresa_principal_id(mysqli $c): int
{
    $res = mysqli_query($c, "SHOW COLUMNS FROM conf__empresas LIKE 'es_principal'");
    if (!$res || mysqli_num_rows($res) === 0) return 0;
    $res = mysqli_query($c, "SELECT empresa_id FROM conf__empresas WHERE es_principal = 1 AND tabla_estado_registro_id = 1
                              ORDER BY empresa_id LIMIT 1");
    $f = $res ? mysqli_fetch_row($res) : null;
    return intval($f[0] ?? 0);
}

/** Si la empresa usa la IA (por defecto sí). */
function ia_empresa_habilitada(mysqli $c, int $empresa_id): bool
{
    $f = ia_empresa_fila($c, $empresa_id);
    return $f === null ? true : intval($f['ia_habilitada']) === 1;
}

/** Clave cifrada de la vigencia de Parámetros de Empresa (antes de la migración). */
function ia_empresa_clave_parametros(mysqli $c, int $empresa_id): string
{
    $col = mysqli_query($c, "SHOW COLUMNS FROM gestion__empresas_parametros LIKE 'ia_api_key_cifrada'");
    if (!$col || mysqli_num_rows($col) === 0) return '';
    $st = mysqli_prepare($c, "SELECT ia_api_key_cifrada FROM gestion__empresas_parametros
            WHERE empresa_id = ? AND ia_api_key_cifrada IS NOT NULL AND ia_api_key_cifrada <> ''
            ORDER BY f_desde DESC, empresa_parametro_id DESC LIMIT 1");
    mysqli_stmt_bind_param($st, "i", $empresa_id);
    mysqli_stmt_execute($st);
    $cifrada = (string)(mysqli_fetch_row(mysqli_stmt_get_result($st))[0] ?? '');
    mysqli_stmt_close($st);
    return $cifrada;
}

/**
 * De dónde sale la clave: ['origen' => 'propia'|'principal'|'servidor', 'cifrada' => string, 'principal_id' => int].
 * No mira ia_habilitada.
 */
function ia_empresa_origen_clave(mysqli $c, int $empresa_id): array
{
    if (!ia_empresa_columnas($c)) {
        $cifrada = ia_empresa_clave_parametros($c, $empresa_id);
        return ['origen' => $cifrada !== '' ? 'propia' : 'servidor', 'cifrada' => $cifrada, 'principal_id' => 0];
    }
    $f = ia_empresa_fila($c, $empresa_id);
    $cifrada = (string)($f['ia_api_key_cifrada'] ?? '');
    if ($cifrada !== '') return ['origen' => 'propia', 'cifrada' => $cifrada, 'principal_id' => 0];

    $principal_id = ia_empresa_principal_id($c);
    if ($principal_id > 0 && $principal_id !== $empresa_id) {
        $p = ia_empresa_fila($c, $principal_id);
        $cifrada = (string)($p['ia_api_key_cifrada'] ?? '');
        if ($cifrada !== '') return ['origen' => 'principal', 'cifrada' => $cifrada, 'principal_id' => $principal_id];
    }
    return ['origen' => 'servidor', 'cifrada' => '', 'principal_id' => $principal_id];
}

/**
 * Clave descifrada para la empresa, o '' (sin clave propia ni de la principal: CapaIa usa la del servidor).
 * Si la empresa tiene la IA deshabilitada devuelve '' igual: los que llaman a la IA usan ia_empresa_proveedor()
 * o miran ia_empresa_habilitada() antes.
 */
function ia_empresa_clave(mysqli $c, int $empresa_id): string
{
    $o = ia_empresa_origen_clave($c, $empresa_id);
    if ($o['cifrada'] === '') return '';
    $clave = secreto_descifrar($o['cifrada']);
    if ($clave === null) {
        error_log("=== ia_empresa_clave: no se pudo descifrar la clave de la IA ({$o['origen']}) para la empresa $empresa_id ===");
        return '';
    }
    return trim($clave);
}

/**
 * Proveedor de IA listo para usar (Gemini o Claude según la clave), o null si la empresa la tiene deshabilitada o
 * no hay ninguna clave. $ajustes pisa la configuración del servidor (timeout, max_tokens, ...).
 */
function ia_empresa_proveedor(mysqli $c, int $empresa_id, array $ajustes = [])
{
    if (!ia_empresa_habilitada($c, $empresa_id)) return null;
    require_once __DIR__ . '/facturas_lectura/FacturaExtractor.php';
    return CapaIa::proveedorDesdeConfig(ia_empresa_clave($c, $empresa_id), $ajustes);
}

/** Mensaje para el usuario cuando ia_empresa_proveedor() da null. */
function ia_empresa_motivo(mysqli $c, int $empresa_id): string
{
    if (!ia_empresa_habilitada($c, $empresa_id)) {
        return 'La IA está deshabilitada para esta empresa (Configuración > Empresas).';
    }
    return 'No hay una clave de IA: cargala en Configuración > Empresas (en la empresa o en la principal).';
}

/**
 * Estado para la pantalla (sin la clave): ia_habilitada, clave_propia (0/1), mascara ('••••abcd'),
 * origen ('propia'|'principal'|'servidor'), principal (nombre de la empresa principal o '').
 */
function ia_empresa_estado(mysqli $c, int $empresa_id): array
{
    $f = ia_empresa_fila($c, $empresa_id);
    $propia = (string)($f['ia_api_key_cifrada'] ?? '');
    $o = ia_empresa_origen_clave($c, $empresa_id);
    $mascara = '';
    if ($propia !== '') {
        $clave = secreto_descifrar($propia);
        $mascara = $clave === null ? '(no se puede descifrar)' : '••••' . substr($clave, -4);
    }
    $principal = '';
    $pid = ia_empresa_principal_id($c);
    if ($pid > 0) {
        $st = mysqli_prepare($c, "SELECT empresa FROM conf__empresas WHERE empresa_id = ?");
        mysqli_stmt_bind_param($st, "i", $pid);
        mysqli_stmt_execute($st);
        $principal = trim((string)(mysqli_fetch_row(mysqli_stmt_get_result($st))[0] ?? ''));
        mysqli_stmt_close($st);
    }
    return [
        'ia_habilitada' => $f === null ? 1 : intval($f['ia_habilitada']),
        'clave_propia' => $propia !== '' ? 1 : 0,
        'mascara' => $mascara,
        'origen' => $o['origen'],
        'es_principal' => ($pid > 0 && $pid === $empresa_id) ? 1 : 0,
        'principal' => $principal,
    ];
}

/**
 * Guarda el uso de la IA de la empresa. $clave vacía = se conserva la cargada; $quitar = la borra.
 * Lanza Exception con un mensaje para el usuario.
 */
function ia_empresa_guardar(mysqli $c, int $empresa_id, bool $habilitada, string $clave = '', bool $quitar = false): void
{
    if ($empresa_id <= 0 || !ia_empresa_columnas($c)) return;
    $h = $habilitada ? 1 : 0;
    $st = mysqli_prepare($c, "UPDATE conf__empresas SET ia_habilitada = ? WHERE empresa_id = ?");
    mysqli_stmt_bind_param($st, "ii", $h, $empresa_id);
    mysqli_stmt_execute($st);
    mysqli_stmt_close($st);

    $clave = trim($clave);
    if ($clave !== '') {
        if (strlen($clave) > 300 || preg_match('/\s/', $clave)) {
            throw new Exception('La clave de la API de la IA no es válida.');
        }
        try {
            $cifrada = secreto_cifrar($clave);
        } catch (RuntimeException $e) {
            error_log('=== ia_empresa_guardar: ' . $e->getMessage() . ' ===');
            throw new Exception('No se puede guardar la clave de la IA: falta la clave de cifrado del servidor (SINDI_CLAVE_CIFRADO).');
        }
    } elseif ($quitar) {
        $cifrada = null;
    } else {
        return;
    }
    $st = mysqli_prepare($c, "UPDATE conf__empresas SET ia_api_key_cifrada = ? WHERE empresa_id = ?");
    mysqli_stmt_bind_param($st, "si", $cifrada, $empresa_id);
    if (!mysqli_stmt_execute($st)) {
        throw new Exception('Error al guardar la clave de la IA: ' . mysqli_stmt_error($st));
    }
    mysqli_stmt_close($st);
}
