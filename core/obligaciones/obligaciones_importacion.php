<?php
/**
 * Importación de obligaciones de pago desde CSV / Excel (sueldos de un sistema externo, cargas sociales,
 * alquileres...). Diseño: _local/docs/gestion/13_obligaciones_de_pago.md §4.6.
 *
 * Dos pasos: previsualizar (guarda el archivo y valida cada fila, sin crear obligaciones) y confirmar (vuelve a
 * leer y validar el archivo guardado y crea las obligaciones de las filas válidas, en una sola transacción).
 * Lector común de planillas: core/bancos/planilla_lector.php (CSV, XLSX y XLS sin librerías externas).
 * No crea entidades: si a una entidad le falta el rol del tipo, la confirmación puede asignárselo.
 */

require_once __DIR__ . '/obligaciones_service.php';
require_once __DIR__ . '/../bancos/planilla_lector.php';

const OBL_IMP_DIR = 'uploads/obligaciones';
const OBL_IMP_COLUMNAS = [
    'tipo'        => ['tipo', 'tipo_obligacion', 'codigo_tipo'],
    'cuit'        => ['cuit', 'cuil', 'documento', 'cuit_cuil', 'cuil_cuit'],
    'entidad'     => ['entidad', 'nombre', 'beneficiario', 'empleado', 'apellido_y_nombre'],
    'importe'     => ['importe', 'monto', 'neto', 'neto_a_pagar', 'total'],
    'f_emision'   => ['f_emision', 'fecha', 'fecha_emision'],
    'f_vto'       => ['f_vto', 'vencimiento', 'fecha_vto', 'fecha_vencimiento', 'vto'],
    'periodo'     => ['periodo', 'periodo_fiscal', 'mes'],
    'descripcion' => ['descripcion', 'concepto', 'detalle'],
    'referencia'  => ['referencia', 'recibo', 'nro_recibo', 'comprobante'],
    'sucursal'    => ['sucursal'],
];

function obl_imp_normalizar_encabezado(string $t): string
{
    $t = mb_strtolower(trim($t));
    $t = strtr($t, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', '°' => '', 'º' => '', '.' => '', '/' => '_']);
    return trim(preg_replace('/[^a-z0-9]+/', '_', $t), '_');
}

/** Fecha de una celda: AAAA-MM-DD, DD/MM/AAAA o número de serie de Excel. */
function obl_imp_fecha($v): ?string
{
    if ($v === null || $v === '') return null;
    if (is_numeric($v) && floatval($v) > 20000 && floatval($v) < 80000) {
        return gmdate('Y-m-d', (int)round((floatval($v) - 25569) * 86400));
    }
    $v = trim((string)$v);
    if (preg_match('#^(\d{1,2})[/-](\d{1,2})[/-](\d{4})$#', $v, $m)) $v = sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    return obl_fecha($v);
}

function obl_imp_periodo($v): ?string
{
    if ($v === null || $v === '') return null;
    $v = trim((string)$v);
    if (preg_match('/^(\d{4})-?(\d{2})$/', $v, $m)) return "$m[1]-$m[2]";
    if (preg_match('#^(\d{1,2})[/-](\d{4})$#', $v, $m)) return sprintf('%04d-%02d', $m[2], $m[1]);
    if ($f = obl_imp_fecha($v)) return substr($f, 0, 7);
    return '?';
}

/** Valida un archivo subido. Devuelve [ruta temporal, nombre, tipo XLS|XLSX|CSV]. */
function obl_imp_archivo_subido(?array $f): array
{
    if (!$f || !isset($f['error']) || is_array($f['error'])) throw new RuntimeException('Elegí el archivo.');
    if ($f['error'] === UPLOAD_ERR_INI_SIZE || $f['error'] === UPLOAD_ERR_FORM_SIZE) throw new RuntimeException('El archivo supera los 5 MB.');
    if ($f['error'] !== UPLOAD_ERR_OK || (!is_uploaded_file($f['tmp_name']) && PHP_SAPI !== 'cli')) throw new RuntimeException('No se pudo subir el archivo.');
    if ($f['size'] <= 0 || $f['size'] > BCO_PLANILLA_MAX_BYTES) throw new RuntimeException('El archivo tiene que pesar entre 1 byte y 5 MB.');
    $nombre = mb_substr(basename(str_replace('\\', '/', (string)$f['name'])), 0, 200);
    $ext = strtoupper(pathinfo($nombre, PATHINFO_EXTENSION));
    if (!in_array($ext, ['XLS', 'XLSX', 'CSV', 'TXT'], true)) throw new RuntimeException('El archivo tiene que ser .xlsx, .xls o .csv.');
    return [$f['tmp_name'], $nombre, $ext === 'TXT' ? 'CSV' : $ext];
}

function obl_imp_guardar_archivo(string $tmp, int $empresa_id, string $nombre): string
{
    $base = dirname(__DIR__, 2) . '/' . OBL_IMP_DIR;
    $dir = $base . '/' . $empresa_id;
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) throw new RuntimeException('No se pudo crear la carpeta de importaciones.');
    if (!file_exists($base . '/.htaccess')) {
        $ht = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n";
        if (file_put_contents($base . '/.htaccess', $ht) === false) throw new RuntimeException('No se pudo proteger la carpeta de importaciones.');
    }
    $ext = preg_replace('/[^a-z0-9]/', '', strtolower(pathinfo($nombre, PATHINFO_EXTENSION))) ?: 'dat';
    $archivo = date('Ymd') . '_' . bin2hex(random_bytes(12)) . '.' . $ext;
    $ok = is_uploaded_file($tmp) ? move_uploaded_file($tmp, "$dir/$archivo") : copy($tmp, "$dir/$archivo");
    if (!$ok) throw new RuntimeException('No se pudo guardar el archivo.');
    return OBL_IMP_DIR . '/' . $empresa_id . '/' . $archivo;
}

/** Lee la primera hoja con datos: [encabezados normalizados por columna, filas de datos con su número de fila]. */
function obl_imp_leer(string $ruta_abs, string $tipo): array
{
    $hojas = bco_planilla_leer($ruta_abs, $tipo)['hojas'] ?? [];
    foreach ($hojas as $filas) {
        $filas = array_filter($filas, static fn($f) => !empty($f));
        if (!$filas) continue;
        $n_enc = array_key_first($filas);
        $mapa = [];
        foreach ($filas[$n_enc] as $col => $titulo) {
            $t = obl_imp_normalizar_encabezado((string)$titulo);
            foreach (OBL_IMP_COLUMNAS as $campo => $alias) {
                if (in_array($t, $alias, true) && !isset($mapa[$campo])) $mapa[$campo] = $col;
            }
        }
        unset($filas[$n_enc]);
        $datos = [];
        foreach ($filas as $n => $f) {
            $r = ['fila' => $n + 1];
            foreach ($mapa as $campo => $col) $r[$campo] = $f[$col] ?? null;
            $datos[] = $r;
        }
        return [$mapa, $datos];
    }
    throw new RuntimeException('El archivo no tiene datos.');
}

/**
 * Valida las filas contra la empresa. Cada fila: datos normalizados para obl_registrar, errores (impiden
 * importarla) y avisos; 'falta_rol' si la entidad existe pero no tiene el rol del tipo.
 */
function obl_imp_validar(mysqli $c, int $empresa_id, array $mapa, array $filas, int $tipo_fijo_id): array
{
    if (!isset($mapa['importe'])) throw new RuntimeException('Falta la columna "importe" en la primera fila (encabezados).');
    if (!isset($mapa['cuit']) && !isset($mapa['entidad'])) throw new RuntimeException('Falta la columna "cuit" (o "entidad" con el nombre exacto).');
    if (!isset($mapa['tipo']) && $tipo_fijo_id <= 0) throw new RuntimeException('Falta la columna "tipo": elegí un tipo para todo el archivo o agregá la columna.');

    $tipos = [];
    foreach (obl_tipos($c, $empresa_id, true, true) as $t) $tipos[$t['codigo']] = $t;
    $tipo_fijo = null;
    if ($tipo_fijo_id > 0) {
        foreach ($tipos as $t) if (intval($t['obligacion_tipo_id']) === $tipo_fijo_id) $tipo_fijo = $t;
        if (!$tipo_fijo) throw new RuntimeException('El tipo elegido no se puede cargar a mano.');
    }
    $sucursales = [];
    foreach (obl_filas($c, "SELECT sucursal_id, sucursal_nombre FROM gestion__sucursales WHERE empresa_id = ? AND tabla_estado_registro_id = 1", 'i', $empresa_id) as $s) {
        $sucursales[mb_strtolower(trim($s['sucursal_nombre']))] = intval($s['sucursal_id']);
        $sucursales[(string)$s['sucursal_id']] = intval($s['sucursal_id']);
    }

    $vistos = [];
    $out = [];
    foreach ($filas as $f) {
        $err = [];
        $avisos = [];
        $tipo = $tipo_fijo;
        if (!$tipo) {
            $cod = strtoupper(trim((string)($f['tipo'] ?? '')));
            $tipo = $tipos[$cod] ?? null;
            if (!$tipo) $err[] = $cod === '' ? 'Falta el tipo.' : "Tipo $cod inexistente o que no se carga a mano.";
        }
        $importe = obl_importe($f['importe'] ?? 0);
        if ($importe <= 0) $err[] = 'Importe inválido.';

        $entidad = null;
        $cuit = preg_replace('/\D/', '', (string)($f['cuit'] ?? ''));
        if ($cuit !== '') {
            $ents = obl_filas($c, "SELECT entidad_id, entidad_nombre FROM gestion__entidades WHERE empresa_id = ? AND cuit = ? AND tabla_estado_registro_id = 1", 'is', $empresa_id, $cuit);
            if (count($ents) > 1) $err[] = "Hay más de una entidad con CUIT $cuit.";
            $entidad = $ents[0] ?? null;
        } elseif (trim((string)($f['entidad'] ?? '')) !== '') {
            $ents = obl_filas($c, "SELECT entidad_id, entidad_nombre FROM gestion__entidades WHERE empresa_id = ? AND entidad_nombre = ? AND tabla_estado_registro_id = 1",
                              'is', $empresa_id, trim((string)$f['entidad']));
            if (count($ents) > 1) $err[] = 'Hay más de una entidad con ese nombre: usá el CUIT.';
            $entidad = $ents[0] ?? null;
        }
        if (!$entidad) $err[] = 'Entidad no encontrada' . ($cuit !== '' ? " (CUIT $cuit)" : '') . ': dala de alta en Entidades.';

        $falta_rol = null;
        if ($entidad && $tipo && !empty($tipo['rol_entidad_codigo']) && !ent_tiene_rol($c, intval($entidad['entidad_id']), $tipo['rol_entidad_codigo'])) {
            $falta_rol = $tipo['rol_entidad_codigo'];
            $avisos[] = 'No tiene el rol ' . (OBL_ROLES[$falta_rol] ?? $falta_rol) . ': se le puede asignar al importar.';
        }

        $f_emision = isset($f['f_emision']) && $f['f_emision'] !== null ? obl_imp_fecha($f['f_emision']) : date('Y-m-d');
        if ($f_emision === null) $err[] = 'Fecha inválida.';
        $f_vto = isset($f['f_vto']) && $f['f_vto'] !== null ? obl_imp_fecha($f['f_vto']) : $f_emision;
        if ($f_vto === null) $err[] = 'Vencimiento inválido.';
        $periodo = obl_imp_periodo($f['periodo'] ?? null);
        if ($periodo === '?') { $err[] = 'Período inválido (AAAA-MM).'; $periodo = null; }
        $sucursal = 0;
        if (trim((string)($f['sucursal'] ?? '')) !== '') {
            $sucursal = $sucursales[mb_strtolower(trim((string)$f['sucursal']))] ?? 0;
            if ($sucursal <= 0) $err[] = 'Sucursal inexistente.';
        }
        $referencia = trim((string)($f['referencia'] ?? ''));

        if ($entidad && $tipo && !$err) {
            $clave = $tipo['obligacion_tipo_id'] . '|' . $entidad['entidad_id'] . '|' . ($periodo ?? '') . '|' . $importe;
            if (isset($vistos[$clave])) $avisos[] = "Repetida en el archivo (fila {$vistos[$clave]}).";
            $vistos[$clave] = $f['fila'];
            $dup = obl_fila($c,
                "SELECT o.obligacion_id FROM gestion__obligaciones_pago o
                  WHERE o.empresa_id = ? AND o.tabla_estado_registro_id NOT IN (6, 38) AND o.entidad_id = ?
                    AND ((o.obligacion_tipo_id = ? AND COALESCE(o.periodo_fiscal, '') = ? AND o.importe_total = ?)
                         OR (? <> '' AND o.referencia = ? AND o.obligacion_tipo_id = ?)) LIMIT 1",
                'iiisdssi', $empresa_id, intval($entidad['entidad_id']), intval($tipo['obligacion_tipo_id']), $periodo ?? '', $importe,
                $referencia, $referencia, intval($tipo['obligacion_tipo_id']));
            if ($dup) $avisos[] = 'Posible duplicado de una obligación ya cargada.';
        }

        $out[] = [
            'fila' => $f['fila'],
            'tipo_nombre' => $tipo['nombre'] ?? ($f['tipo'] ?? ''),
            'entidad_nombre' => $entidad['entidad_nombre'] ?? ($f['entidad'] ?? ''),
            'cuit' => $cuit,
            'importe' => $importe,
            'f_emision' => $f_emision, 'f_vto' => $f_vto, 'periodo' => $periodo,
            'errores' => $err, 'avisos' => $avisos, 'falta_rol' => $falta_rol,
            'datos' => $err ? null : [
                'obligacion_tipo_id' => intval($tipo['obligacion_tipo_id']), 'entidad_id' => intval($entidad['entidad_id']),
                'importe_total' => $importe, 'f_emision' => $f_emision, 'f_vto' => $f_vto, 'periodo_fiscal' => $periodo,
                'descripcion' => trim((string)($f['descripcion'] ?? '')), 'referencia' => $referencia, 'sucursal_id' => $sucursal,
            ],
        ];
    }
    return $out;
}

function obl_imp_resumen(array $filas): array
{
    $ok = array_filter($filas, static fn($f) => !$f['errores']);
    return [
        'leidas' => count($filas), 'validas' => count($ok), 'con_error' => count($filas) - count($ok),
        'importe' => round(array_sum(array_column($ok, 'importe')), 2),
        'faltan_roles' => count(array_filter($ok, static fn($f) => $f['falta_rol'] !== null)),
    ];
}

/** Paso 1: guarda el archivo, valida y devuelve la previsualización. */
function obl_imp_previsualizar(mysqli $c, int $empresa_id, int $usuario_id, ?array $archivo, int $tipo_fijo_id): array
{
    [$tmp, $nombre, $tipo_arch] = obl_imp_archivo_subido($archivo);
    [$mapa, $filas] = obl_imp_leer($tmp, $tipo_arch);
    if (!$filas) throw new RuntimeException('El archivo no tiene filas debajo de los encabezados.');
    if (count($filas) > 2000) throw new RuntimeException('El archivo tiene más de 2.000 filas: dividilo.');
    $validadas = obl_imp_validar($c, $empresa_id, $mapa, $filas, $tipo_fijo_id);
    $ruta = obl_imp_guardar_archivo($tmp, $empresa_id, $nombre);
    $res = obl_imp_resumen($validadas);
    $tipo = $tipo_fijo_id > 0 ? $tipo_fijo_id : null;
    obl_exec($c, "INSERT INTO gestion__obligaciones_importaciones (empresa_id, archivo_nombre, archivo_ruta, obligacion_tipo_id, filas_leidas, filas_error,
                         importe_total, tabla_estado_registro_id, usuario_id) VALUES (?, ?, ?, ?, ?, ?, ?, 3, ?)",
             'issiiidi', $empresa_id, $nombre, $ruta, $tipo, $res['leidas'], $res['con_error'], $res['importe'], $usuario_id);
    return ['importacion_id' => intval(mysqli_insert_id($c)), 'resumen' => $res, 'filas' => $validadas];
}

/**
 * Paso 2: crea las obligaciones de las filas válidas. $confirmar: pasan por el motor de aprobaciones (si no,
 * quedan en Borrador). $asignar_roles: asigna el rol del tipo a las entidades que no lo tienen.
 */
function obl_imp_confirmar(mysqli $c, int $empresa_id, int $usuario_id, int $importacion_id, bool $confirmar, bool $asignar_roles): array
{
    $imp = obl_fila($c, "SELECT * FROM gestion__obligaciones_importaciones WHERE importacion_id = ? AND empresa_id = ?", 'ii', $importacion_id, $empresa_id);
    if (!$imp) throw new RuntimeException('Importación no encontrada.');
    if (intval($imp['tabla_estado_registro_id']) !== 3) throw new RuntimeException('Esa importación ya se procesó.');
    $ruta = dirname(__DIR__, 2) . '/' . $imp['archivo_ruta'];
    $ext = strtoupper(pathinfo($imp['archivo_ruta'], PATHINFO_EXTENSION));
    [$mapa, $filas] = obl_imp_leer($ruta, $ext === 'TXT' ? 'CSV' : $ext);
    $validadas = obl_imp_validar($c, $empresa_id, $mapa, $filas, intval($imp['obligacion_tipo_id'] ?? 0));

    mysqli_begin_transaction($c);
    try {
        $creadas = 0;
        $pendientes_aprob = 0;
        $total = 0.0;
        $sin_rol = 0;
        foreach ($validadas as $f) {
            if ($f['errores'] || !$f['datos']) continue;
            if ($f['falta_rol'] !== null) {
                if (!$asignar_roles) { $sin_rol++; continue; }
                actualizarRolIndividualEntidad($c, $f['datos']['entidad_id'], $f['falta_rol'], true, null, $empresa_id);
            }
            $r = obl_registrar($c, $f['datos'] + ['origen' => 'IMPORTACION', 'importacion_id' => $importacion_id, 'confirmar' => $confirmar ? 'SI' : 'NO'],
                               $empresa_id, $usuario_id, true);
            $creadas++;
            $total += $f['importe'];
            if (intval($r['estado']) === OBL_PEND_APROBACION) $pendientes_aprob++;
        }
        if ($creadas === 0) throw new RuntimeException('No hay filas válidas para importar.');
        $errores = count($validadas) - $creadas;
        $conf = $confirmar ? 1 : 0;
        obl_exec($c, "UPDATE gestion__obligaciones_importaciones SET filas_leidas = ?, filas_importadas = ?, filas_error = ?, importe_total = ?,
                             confirmar_al_importar = ?, tabla_estado_registro_id = 5, usuario_modificacion_id = ? WHERE importacion_id = ?",
                 'iiidiii', count($validadas), $creadas, $errores, $total, $conf, $usuario_id, $importacion_id);
        mysqli_commit($c);
    } catch (Throwable $e) {
        mysqli_rollback($c);
        throw $e;
    }
    $msg = "Se importaron $creadas obligaciones por $ " . number_format($total, 2, ',', '.');
    if ($pendientes_aprob) $msg .= " ($pendientes_aprob quedaron pendientes de aprobación)";
    if ($sin_rol) $msg .= ". $sin_rol filas no se importaron porque la entidad no tiene el rol";
    return ['creadas' => $creadas, 'mensaje' => $msg . '.'];
}

/** Deshace una importación mientras ninguna de sus obligaciones esté confirmada. */
function obl_imp_deshacer(mysqli $c, int $empresa_id, int $usuario_id, int $importacion_id): void
{
    mysqli_begin_transaction($c);
    try {
        $imp = obl_fila($c, "SELECT * FROM gestion__obligaciones_importaciones WHERE importacion_id = ? AND empresa_id = ? FOR UPDATE", 'ii', $importacion_id, $empresa_id);
        if (!$imp || intval($imp['tabla_estado_registro_id']) === 38) throw new RuntimeException('Importación no encontrada o ya deshecha.');
        $obls = obl_filas($c, "SELECT obligacion_id, comprobante_id, tabla_estado_registro_id FROM gestion__obligaciones_pago
                               WHERE importacion_id = ? AND empresa_id = ? FOR UPDATE", 'ii', $importacion_id, $empresa_id);
        foreach ($obls as $o) {
            if (!in_array(intval($o['tabla_estado_registro_id']), [OBL_BORRADOR, OBL_PEND_APROBACION, OBL_ELIMINADA], true)) {
                throw new RuntimeException('Hay obligaciones de esta importación ya confirmadas: anulalas una por una.');
            }
        }
        foreach ($obls as $o) {
            if (intval($o['tabla_estado_registro_id']) !== OBL_ELIMINADA) obl_set_estado($c, $o, OBL_ELIMINADA, $usuario_id);
        }
        obl_exec($c, "UPDATE gestion__obligaciones_importaciones SET tabla_estado_registro_id = 38, usuario_modificacion_id = ? WHERE importacion_id = ?",
                 'ii', $usuario_id, $importacion_id);
        mysqli_commit($c);
    } catch (Throwable $e) {
        mysqli_rollback($c);
        throw $e;
    }
}

function obl_imp_listar(mysqli $c, int $empresa_id): array
{
    return obl_filas($c,
        "SELECT i.importacion_id, i.archivo_nombre, i.filas_leidas, i.filas_importadas, i.filas_error, i.importe_total, i.tabla_estado_registro_id,
                i.fecha_creacion, ot.nombre AS tipo_nombre, u.usuario, ter.tabla_estado_registro AS estado
           FROM gestion__obligaciones_importaciones i
           LEFT JOIN gestion__obligaciones_tipos ot ON ot.obligacion_tipo_id = i.obligacion_tipo_id
           LEFT JOIN conf__usuarios u ON u.usuario_id = i.usuario_id
           LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_id = 450 AND ter.estado_registro_id = i.tabla_estado_registro_id
          WHERE i.empresa_id = ? AND i.tabla_estado_registro_id IN (5, 38)
          ORDER BY i.importacion_id DESC LIMIT 100", 'i', $empresa_id);
}

/** Plantilla CSV (separador ; para Excel en castellano). */
function obl_imp_plantilla(): string
{
    return "\xEF\xBB\xBF" . "tipo;cuit;importe;f_emision;f_vto;periodo;descripcion;referencia\r\n"
         . "SUELDO;20123456789;450000,00;" . date('d/m/Y') . ';' . date('d/m/Y', strtotime('+4 days')) . ';' . date('Y-m') . ";Sueldo " . date('m/Y') . ";Recibo 1\r\n";
}
