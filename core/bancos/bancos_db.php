<?php
/**
 * Conciliación bancaria: utilidades comunes de los servicios de core/bancos/ (consultas preparadas, botones del
 * motor, cuentas BANCO, archivos subidos). Las páginas de gestion y gestion_normal son delgadas: validan sesión,
 * empresa y función en su _ajax.php y llaman a estos servicios. Diseño: _local/docs/gestion/12_conciliacion_bancaria.md
 *
 * Todo error para el usuario es RuntimeException con un mensaje corto; los servicios nunca toman la empresa del
 * request: la reciben del _ajax.php ($ctx['empresa_id']).
 */

require_once __DIR__ . '/extracto_reglas.php';

const BCO_DIR_ARCHIVOS = 'uploads/extractos_bancarios';   // + /<empresa>/; .htaccess niega el acceso web
const BCO_TOLERANCIA = 0.005;

function bco_filas(mysqli $c, string $sql, string $tipos = '', ...$params): array
{
    $stmt = mysqli_prepare($c, $sql);
    if (!$stmt) throw new LogicException('Bancos: ' . mysqli_error($c));
    if ($tipos !== '') mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function bco_fila(mysqli $c, string $sql, string $tipos = '', ...$params): ?array
{
    return bco_filas($c, $sql, $tipos, ...$params)[0] ?? null;
}

/** Ejecuta una escritura; devuelve filas afectadas. */
function bco_exec(mysqli $c, string $sql, string $tipos = '', ...$params): int
{
    $stmt = mysqli_prepare($c, $sql);
    if (!$stmt) throw new LogicException('Bancos: ' . mysqli_error($c));
    if ($tipos !== '') mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    if (!mysqli_stmt_execute($stmt)) {
        $e = mysqli_stmt_error($stmt);
        mysqli_stmt_close($stmt);
        throw new LogicException('Bancos: ' . $e);
    }
    $n = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $n;
}

/** Ids enteros positivos únicos de un parámetro (array o "1,2,3"). */
function bco_ids($valor): array
{
    if (!is_array($valor)) $valor = explode(',', (string)$valor);
    return array_values(array_unique(array_filter(array_map('intval', $valor), static fn($v) => $v > 0)));
}

function bco_placeholders(array $ids): string
{
    return implode(',', array_fill(0, count($ids), '?'));
}

/** Columna con el nombre del estado en conf__estados_registros (varía según la versión de la tabla). */
function bco_columna_estado(mysqli $c): string
{
    static $col = null;
    if ($col !== null) return $col;
    $cols = [];
    $res = mysqli_query($c, "SHOW COLUMNS FROM conf__estados_registros");
    while ($res && ($r = mysqli_fetch_assoc($res))) $cols[] = $r['Field'];
    foreach (['estado_registro', 'nombre_estado', 'descripcion'] as $x) {
        if (in_array($x, $cols, true)) return $col = $x;
    }
    return $col = 'estado_registro_id';
}

/** Botones del motor para un estado (conf__paginas_funciones de la página). */
function bco_botones(mysqli $c, int $pagina_id, int $estado_id): array
{
    static $cache = [];
    $k = "$pagina_id|$estado_id";
    if (isset($cache[$k])) return $cache[$k];
    // Por estado (origen) y, además, solo las funciones asignadas al perfil del usuario (core/permisos.php)
    return $cache[$k] = permisos_filtrar_funciones($c, bco_filas($c,
        "SELECT pf.pagina_funcion_id, pf.pagina_id, pf.nombre_funcion, pf.accion_js, pf.descripcion, pf.tabla_estado_registro_destino_id AS estado_destino_id,
                IF(pf.tabla_estado_registro_destino_id <> pf.tabla_estado_registro_origen_id, 1, 0) AS es_confirmable,
                i.icono_clase, co.color_clase, co.bg_clase, co.text_clase
         FROM conf__paginas_funciones pf
         LEFT JOIN conf__iconos i ON i.icono_id = pf.icono_id
         LEFT JOIN conf__colores co ON co.color_id = pf.color_id
         WHERE pf.pagina_id = ? AND pf.tabla_estado_registro_origen_id = ? AND pf.tabla_estado_registro_id = 1
         ORDER BY pf.orden", 'ii', $pagina_id, $estado_id));
}

/** Botón "Agregar" (función de creación, orden 0) y las demás funciones de página (origen 0) que el usuario puede usar. */
function bco_funciones_pagina(mysqli $c, int $usuario_id, int $empresa_id, int $pagina_id): array
{
    $out = [];
    foreach (bco_botones($c, $pagina_id, 0) as $b) {
        if (usuario_puede_funcion($c, $usuario_id, $empresa_id, $pagina_id, $b['accion_js'])) $out[$b['accion_js']] = $b;
    }
    return $out;
}

/** Agrega los botones de fila según el estado de cada registro. */
function bco_con_botones(mysqli $c, int $pagina_id, array $rows, string $col_estado = 'tabla_estado_registro_id'): array
{
    foreach ($rows as &$r) $r['botones'] = bco_botones($c, $pagina_id, intval($r[$col_estado]));
    unset($r);
    return $rows;
}

/** Destino de una función de fila para el estado actual, o excepción. */
function bco_destino_accion(mysqli $c, int $pagina_id, string $accion_js, int $estado_actual): int
{
    $f = bco_fila($c, "SELECT tabla_estado_registro_destino_id d FROM conf__paginas_funciones
                       WHERE pagina_id = ? AND accion_js = ? AND tabla_estado_registro_origen_id = ? AND tabla_estado_registro_id = 1 LIMIT 1",
                  'isi', $pagina_id, $accion_js, $estado_actual);
    if (!$f) throw new RuntimeException('La acción no corresponde al estado del registro.');
    return intval($f['d']);
}

/** Id de la página con esa url en el mismo módulo que $pagina_id (gestion 57 / gestion_normal 557 para facturas de proveedor). */
function bco_pagina_hermana(mysqli $c, int $pagina_id, string $url): int
{
    $p = bco_fila($c, "SELECT h.pagina_id FROM conf__paginas p JOIN conf__paginas h ON h.modulo_id = p.modulo_id AND h.url = ?
                       WHERE p.pagina_id = ? AND h.tabla_estado_registro_id = 1 ORDER BY h.pagina_id LIMIT 1", 'si', $url, $pagina_id);
    if (!$p) throw new RuntimeException("El módulo no tiene la página $url.");
    return intval($p['pagina_id']);
}

/** Cuentas BANCO de la empresa (activas, salvo $todas) con su banco y proveedor. */
function bco_cuentas_banco(mysqli $c, int $empresa_id, bool $todas = false): array
{
    return bco_filas($c,
        "SELECT t.tesoreria_cuenta_id, t.tesoreria_cuenta, t.numero, t.cbu, t.banco_id, b.banco, t.moneda_id, t.sucursal_id,
                t.proveedor_entidad_id, e.entidad_nombre AS proveedor_nombre, t.saldo_actual, t.tabla_estado_registro_id
         FROM gestion__tesoreria_cuentas t
         LEFT JOIN conf__bancos b ON b.banco_id = t.banco_id
         LEFT JOIN gestion__entidades e ON e.entidad_id = t.proveedor_entidad_id AND e.empresa_id = t.empresa_id
         WHERE t.empresa_id = ? AND t.cuenta_tipo = 'BANCO'" . ($todas ? '' : ' AND t.tabla_estado_registro_id = 1') . "
         ORDER BY t.orden, t.tesoreria_cuenta", 'i', $empresa_id);
}

/** Cuenta BANCO activa de la empresa, o excepción. */
function bco_cuenta(mysqli $c, int $empresa_id, int $cuenta_id): array
{
    foreach (bco_cuentas_banco($c, $empresa_id) as $cta) {
        if (intval($cta['tesoreria_cuenta_id']) === $cuenta_id) return $cta;
    }
    throw new RuntimeException('Elegí una cuenta bancaria activa de la empresa.');
}

function bco_bancos(mysqli $c): array
{
    return bco_filas($c, "SELECT banco_id, banco, codigo_bcra FROM conf__bancos WHERE tabla_estado_registro_id = 1 ORDER BY orden, banco");
}

/**
 * Valida un archivo subido ($_FILES['archivo']) y devuelve [ruta temporal, nombre original, tipo PDF|XLS|XLSX|CSV|JPG|PNG].
 * El tipo real lo confirma el lector por el contenido (cabecera PDF / OLE2 / ZIP / imagen / texto).
 */
function bco_archivo_subido(?array $f): array
{
    $maxBytes = 10 * 1024 * 1024;
    if (!$f || !isset($f['error']) || is_array($f['error'])) throw new RuntimeException('Elegí el archivo del extracto.');
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('El archivo supera los 10 MB.');
    // En CLI (simulador de circuitos) el archivo no viene de una subida HTTP
    if ($f['error'] !== UPLOAD_ERR_OK || (!is_uploaded_file($f['tmp_name']) && PHP_SAPI !== 'cli')) throw new RuntimeException('No se pudo subir el archivo.');
    if ($f['size'] <= 0 || $f['size'] > $maxBytes) throw new RuntimeException('El archivo tiene que pesar entre 1 byte y 10 MB.');
    $nombre = mb_substr(basename(str_replace('\\', '/', (string)$f['name'])), 0, 200);
    $ext = strtoupper(pathinfo($nombre, PATHINFO_EXTENSION));
    if (!in_array($ext, ['PDF', 'XLS', 'XLSX', 'CSV', 'TXT', 'JPG', 'JPEG', 'PNG'], true)) {
        throw new RuntimeException('El archivo tiene que ser PDF, Excel (.xls, .xlsx), CSV, TXT o imagen (.jpg, .png).');
    }
    $tipo = $ext === 'TXT' ? 'CSV' : ($ext === 'JPEG' ? 'JPG' : $ext);
    return [$f['tmp_name'], $nombre, $tipo];
}

/** Guarda el archivo del extracto en uploads/extractos_bancarios/<empresa>/ (sin acceso web). Devuelve la ruta relativa. */
function bco_archivo_guardar(string $tmp, int $empresa_id, string $nombre): string
{
    $raiz = dirname(__DIR__, 2);
    $base = $raiz . '/' . BCO_DIR_ARCHIVOS;
    $dir = $base . '/' . $empresa_id;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) throw new RuntimeException('No se pudo crear la carpeta de extractos.');
    if (!file_exists($base . '/.htaccess')) {
        $ht = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n";
        if (file_put_contents($base . '/.htaccess', $ht) === false) {
            error_log("bco_archivo_guardar: no se pudo crear $base/.htaccess");
            throw new RuntimeException('No se pudo proteger la carpeta de extractos.');
        }
    }
    $ext = strtolower(pathinfo($nombre, PATHINFO_EXTENSION)) ?: 'dat';
    $archivo = date('Ymd') . '_' . bin2hex(random_bytes(12)) . '.' . preg_replace('/[^a-z0-9]/', '', $ext);
    $ok = is_uploaded_file($tmp) ? move_uploaded_file($tmp, "$dir/$archivo") : copy($tmp, "$dir/$archivo");
    if (!$ok) throw new RuntimeException('No se pudo guardar el archivo del extracto.');
    return BCO_DIR_ARCHIVOS . '/' . $empresa_id . '/' . $archivo;
}

function bco_mes_valido(string $mes): string
{
    if (!preg_match('/^(\d{4})-(\d{2})$/', $mes, $m) || (int)$m[2] < 1 || (int)$m[2] > 12) throw new RuntimeException('Elegí el mes (AAAA-MM).');
    return $mes;
}

/** Fechas de un período de meses AAAA-MM (vacío = sin límite de ese lado): [desde, hasta], null donde no hay límite. */
function bco_rango_meses(string $desde, string $hasta): array
{
    $d = $desde !== '' ? bco_mes_valido($desde) . '-01' : null;
    $h = $hasta !== '' ? date('Y-m-t', strtotime(bco_mes_valido($hasta) . '-01')) : null;
    if ($d && $h && $d > $h) throw new RuntimeException('El mes "desde" es posterior al mes "hasta".');
    return [$d, $h];
}

function bco_fecha_valida($f): ?string
{
    if (!is_string($f) || $f === '') return null;
    $d = DateTime::createFromFormat('!Y-m-d', $f);
    return $d && $d->format('Y-m-d') === $f ? $f : null;
}
