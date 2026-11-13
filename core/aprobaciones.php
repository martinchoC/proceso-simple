<?php
/**
 * Motor de aprobaciones de obligaciones de pago y órdenes de pago (gestion y gestion_normal).
 * Diseño: _local/docs/gestion/13_obligaciones_de_pago.md §5.
 *
 * - Reglas (gestion__aprobaciones_reglas): por documento (OBLIGACION / OP) y, en obligaciones, por tipo
 *   (la del tipo gana sobre la general). Sin regla vigente = no requiere aprobación: el que carga confirma y paga.
 * - Topes (gestion__aprobaciones_topes): qué perfil aprueba y hasta qué importe. Sin topes cargados para el
 *   documento, alcanza con la función "Aprobar" de la página.
 * - Registro (gestion__aprobaciones_registro): cada aprobación / rechazo; vigente = 0 al rechazar.
 *
 * El motor no cambia estados: el llamador (obligaciones, pagos) pasa el documento a 4 o lo confirma según lo que
 * devuelven aprob_evaluar() / aprob_aprobar(). Errores para el usuario: RuntimeException.
 */

require_once __DIR__ . '/permisos.php';

const APROB_DOCUMENTOS = ['OBLIGACION', 'OP'];

function aprob_filas(mysqli $c, string $sql, string $tipos = '', ...$params): array
{
    $stmt = mysqli_prepare($c, $sql);
    if (!$stmt) throw new LogicException('Aprobaciones: ' . mysqli_error($c));
    if ($tipos !== '') mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function aprob_exec(mysqli $c, string $sql, string $tipos = '', ...$params): int
{
    $stmt = mysqli_prepare($c, $sql);
    if (!$stmt) throw new LogicException('Aprobaciones: ' . mysqli_error($c));
    if ($tipos !== '') mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $n = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    return $n;
}

/** Regla vigente para el documento (la del tipo de obligación, si hay; si no, la general). */
function aprob_regla(mysqli $c, int $empresa_id, string $documento, int $tipo_id, string $fecha): ?array
{
    $rows = aprob_filas($c,
        "SELECT * FROM gestion__aprobaciones_reglas
          WHERE empresa_id = ? AND documento = ? AND tabla_estado_registro_id = 1
            AND (obligacion_tipo_id IS NULL OR obligacion_tipo_id = ?)
            AND f_desde <= ? AND (f_hasta IS NULL OR f_hasta >= ?)
          ORDER BY (obligacion_tipo_id IS NULL), f_desde DESC, regla_id DESC LIMIT 1",
        'isiss', $empresa_id, $documento, $tipo_id, $fecha, $fecha);
    return $rows[0] ?? null;
}

/**
 * ¿Requiere aprobación? Devuelve ['requiere' => bool, 'aprobaciones' => 0|1|2, 'regla' => ?array].
 */
function aprob_evaluar(mysqli $c, int $empresa_id, string $documento, int $tipo_id, float $importe, ?string $fecha = null): array
{
    $fecha = $fecha ?: date('Y-m-d');
    $r = aprob_regla($c, $empresa_id, $documento, $tipo_id, $fecha);
    if (!$r || intval($r['requiere_aprobacion']) !== 1 || $importe + 0.0001 < floatval($r['importe_desde'])) {
        return ['requiere' => false, 'aprobaciones' => 0, 'regla' => $r];
    }
    $doble = $r['doble_aprobacion_desde'] !== null && $importe + 0.0001 >= floatval($r['doble_aprobacion_desde']);
    return ['requiere' => true, 'aprobaciones' => $doble ? 2 : 1, 'regla' => $r];
}

/**
 * Perfil con el que el usuario puede aprobar este importe (null si ninguno). Exige además la función "aprobar"
 * de la página. Sin topes para el documento: cualquier perfil del usuario con esa función.
 */
function aprob_perfil_habilitado(mysqli $c, int $usuario_id, int $empresa_id, int $pagina_id, string $documento, int $tipo_id, float $importe): ?int
{
    if (!usuario_puede_funcion($c, $usuario_id, $empresa_id, $pagina_id, 'aprobar')) return null;
    $perfiles = array_map('intval', array_column(usuario_obtener_perfiles_empresa($c, $usuario_id, $empresa_id), 'empresa_perfil_id'));
    $hoy = date('Y-m-d');
    $topes = aprob_filas($c,
        "SELECT empresa_perfil_id, importe_maximo FROM gestion__aprobaciones_topes
          WHERE empresa_id = ? AND documento = ? AND tabla_estado_registro_id = 1
            AND (obligacion_tipo_id IS NULL OR obligacion_tipo_id = ?)
            AND f_desde <= ? AND (f_hasta IS NULL OR f_hasta >= ?)",
        'isiss', $empresa_id, $documento, $tipo_id, $hoy, $hoy);
    if (!$topes) {
        // Sin topes: el superadmin no tiene perfil en conf__empresas_perfiles
        return $perfiles[0] ?? 0;
    }
    foreach ($topes as $t) {
        if (!in_array(intval($t['empresa_perfil_id']), $perfiles, true)) continue;
        if ($t['importe_maximo'] === null || $importe <= floatval($t['importe_maximo']) + 0.0001) return intval($t['empresa_perfil_id']);
    }
    return in_array($usuario_id, defined('USUARIOS_ACCESO_TOTAL') ? USUARIOS_ACCESO_TOTAL : [], true) ? 0 : null;
}

/** Aprobaciones vigentes del documento. */
function aprob_vigentes(mysqli $c, int $empresa_id, string $documento, int $registro_id): array
{
    return aprob_filas($c,
        "SELECT a.*, u.usuario FROM gestion__aprobaciones_registro a
         LEFT JOIN conf__usuarios u ON u.usuario_id = a.usuario_id
          WHERE a.empresa_id = ? AND a.documento = ? AND a.registro_id = ? AND a.accion = 'APROBAR' AND a.vigente = 1
          ORDER BY a.aprobacion_id", 'isi', $empresa_id, $documento, $registro_id);
}

/** Historial completo (aprobaciones y rechazos) para mostrar en el detalle. */
function aprob_historial(mysqli $c, int $empresa_id, string $documento, int $registro_id): array
{
    return aprob_filas($c,
        "SELECT a.accion, a.nivel, a.importe, a.motivo, a.vigente, a.fecha, u.usuario
           FROM gestion__aprobaciones_registro a
           LEFT JOIN conf__usuarios u ON u.usuario_id = a.usuario_id
          WHERE a.empresa_id = ? AND a.documento = ? AND a.registro_id = ?
          ORDER BY a.aprobacion_id", 'isi', $empresa_id, $documento, $registro_id);
}

/** ¿Puede este usuario aprobar el documento ahora? (para mostrar el botón). */
function aprob_puede(mysqli $c, int $usuario_id, int $empresa_id, int $pagina_id, string $documento, int $registro_id,
                     int $tipo_id, float $importe, int $usuario_carga): bool
{
    $regla = aprob_regla($c, $empresa_id, $documento, $tipo_id, date('Y-m-d'));
    if ($regla && intval($regla['permite_autoaprobar']) !== 1 && $usuario_carga === $usuario_id) return false;
    foreach (aprob_vigentes($c, $empresa_id, $documento, $registro_id) as $a) {
        if (intval($a['usuario_id']) === $usuario_id) return false;
    }
    return aprob_perfil_habilitado($c, $usuario_id, $empresa_id, $pagina_id, $documento, $tipo_id, $importe) !== null;
}

/**
 * Registra una aprobación. Devuelve ['completa' => bool, 'faltan' => int]. El llamador confirma el documento
 * cuando 'completa' es true (en la misma transacción).
 * $requeridas: las que se fijaron al pasar el documento a pendiente (aprobaciones_requeridas).
 */
function aprob_aprobar(mysqli $c, int $usuario_id, int $empresa_id, int $pagina_id, string $documento, int $registro_id,
                       int $tipo_id, float $importe, int $usuario_carga, int $requeridas): array
{
    if (!in_array($documento, APROB_DOCUMENTOS, true)) throw new LogicException('Documento de aprobación inválido.');
    $regla = aprob_regla($c, $empresa_id, $documento, $tipo_id, date('Y-m-d'));
    if ($regla && intval($regla['permite_autoaprobar']) !== 1 && $usuario_carga === $usuario_id) {
        throw new RuntimeException('Lo cargaste vos: lo tiene que aprobar otro usuario.');
    }
    $vigentes = aprob_vigentes($c, $empresa_id, $documento, $registro_id);
    foreach ($vigentes as $a) {
        if (intval($a['usuario_id']) === $usuario_id) throw new RuntimeException('Ya lo aprobaste: falta la aprobación de otro usuario.');
    }
    $perfil = aprob_perfil_habilitado($c, $usuario_id, $empresa_id, $pagina_id, $documento, $tipo_id, $importe);
    if ($perfil === null) throw new RuntimeException('Tu perfil no puede aprobar este importe.');

    $nivel = count($vigentes) + 1;
    aprob_exec($c,
        "INSERT INTO gestion__aprobaciones_registro (empresa_id, documento, registro_id, accion, nivel, importe, empresa_perfil_id, usuario_id)
         VALUES (?, ?, ?, 'APROBAR', ?, ?, ?, ?)",
        'isiidii', $empresa_id, $documento, $registro_id, $nivel, $importe, $perfil, $usuario_id);
    $requeridas = max(1, $requeridas);
    return ['completa' => $nivel >= $requeridas, 'faltan' => max(0, $requeridas - $nivel)];
}

/** Rechazo: deja sin efecto las aprobaciones previas. El llamador vuelve el documento a Borrador. */
function aprob_rechazar(mysqli $c, int $usuario_id, int $empresa_id, int $pagina_id, string $documento, int $registro_id,
                        float $importe, string $motivo): void
{
    if (!usuario_puede_funcion($c, $usuario_id, $empresa_id, $pagina_id, 'rechazar')) {
        throw new RuntimeException('No tenés permiso para rechazar.');
    }
    $motivo = trim($motivo);
    if ($motivo === '') throw new RuntimeException('Indicá el motivo del rechazo.');
    aprob_anular_vigentes($c, $empresa_id, $documento, $registro_id);
    aprob_exec($c,
        "INSERT INTO gestion__aprobaciones_registro (empresa_id, documento, registro_id, accion, nivel, importe, motivo, vigente, usuario_id)
         VALUES (?, ?, ?, 'RECHAZAR', 0, ?, ?, 0, ?)",
        'isidsi', $empresa_id, $documento, $registro_id, $importe, mb_substr($motivo, 0, 255), $usuario_id);
}

/** Las aprobaciones anteriores dejan de contar (rechazo, edición del importe, vuelta a borrador). */
function aprob_anular_vigentes(mysqli $c, int $empresa_id, string $documento, int $registro_id): void
{
    aprob_exec($c, "UPDATE gestion__aprobaciones_registro SET vigente = 0 WHERE empresa_id = ? AND documento = ? AND registro_id = ? AND vigente = 1",
               'isi', $empresa_id, $documento, $registro_id);
}

// ---------------------------------------------------------------------------------------------------------
// Configuración (página Reglas de Aprobación)
// ---------------------------------------------------------------------------------------------------------

function aprob_reglas_listar(mysqli $c, int $empresa_id): array
{
    return aprob_filas($c,
        "SELECT r.*, ot.nombre AS tipo_nombre, er.estado_registro, er.codigo_estandar
           FROM gestion__aprobaciones_reglas r
           LEFT JOIN gestion__obligaciones_tipos ot ON ot.obligacion_tipo_id = r.obligacion_tipo_id
           LEFT JOIN conf__estados_registros er ON er.estado_registro_id = r.tabla_estado_registro_id
          WHERE r.empresa_id = ?
          ORDER BY r.documento DESC, (r.obligacion_tipo_id IS NULL) DESC, ot.orden, r.f_desde DESC", 'i', $empresa_id);
}

function aprob_topes_listar(mysqli $c, int $empresa_id): array
{
    return aprob_filas($c,
        "SELECT t.*, ot.nombre AS tipo_nombre, ep.empresa_perfil_nombre, er.estado_registro, er.codigo_estandar
           FROM gestion__aprobaciones_topes t
           LEFT JOIN gestion__obligaciones_tipos ot ON ot.obligacion_tipo_id = t.obligacion_tipo_id
           LEFT JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = t.empresa_perfil_id
           LEFT JOIN conf__estados_registros er ON er.estado_registro_id = t.tabla_estado_registro_id
          WHERE t.empresa_id = ?
          ORDER BY t.documento DESC, ep.empresa_perfil_nombre, t.f_desde DESC", 'i', $empresa_id);
}

/** Valida y normaliza los datos comunes de regla / tope. */
function aprob_datos_comunes(mysqli $c, int $empresa_id, array $d): array
{
    $doc = strtoupper(trim((string)($d['documento'] ?? '')));
    if (!in_array($doc, APROB_DOCUMENTOS, true)) throw new RuntimeException('Elegí si es para obligaciones o para órdenes de pago.');
    $tipo = intval($d['obligacion_tipo_id'] ?? 0);
    if ($doc === 'OP') $tipo = 0;
    if ($tipo > 0 && !aprob_filas($c, "SELECT 1 FROM gestion__obligaciones_tipos WHERE obligacion_tipo_id = ? AND empresa_id = ?", 'ii', $tipo, $empresa_id)) {
        throw new RuntimeException('El tipo de obligación no es de la empresa.');
    }
    $f_desde = (string)($d['f_desde'] ?? '');
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_desde)) $f_desde = date('Y-m-d');
    $f_hasta = (string)($d['f_hasta'] ?? '');
    $f_hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_hasta) ? $f_hasta : null;
    if ($f_hasta !== null && $f_hasta < $f_desde) throw new RuntimeException('La fecha hasta es anterior a la fecha desde.');
    return [$doc, $tipo > 0 ? $tipo : null, $f_desde, $f_hasta];
}

function aprob_num_opcional($v): ?float
{
    $v = trim((string)$v);
    if ($v === '') return null;
    $n = floatval(str_replace(',', '.', $v));
    if ($n < 0) throw new RuntimeException('Los importes no pueden ser negativos.');
    return round($n, 2);
}

function aprob_regla_guardar(mysqli $c, int $empresa_id, int $usuario_id, array $d): int
{
    [$doc, $tipo, $f_desde, $f_hasta] = aprob_datos_comunes($c, $empresa_id, $d);
    $requiere = !empty($d['requiere_aprobacion']) ? 1 : 0;
    $desde = aprob_num_opcional($d['importe_desde'] ?? '') ?? 0.0;
    $doble = aprob_num_opcional($d['doble_aprobacion_desde'] ?? '');
    if ($doble !== null && $doble < $desde) throw new RuntimeException('La doble aprobación tiene que empezar en un importe mayor o igual al de la aprobación simple.');
    $auto = !empty($d['permite_autoaprobar']) ? 1 : 0;
    $id = intval($d['regla_id'] ?? 0);
    if ($id > 0) {
        if (!aprob_filas($c, "SELECT 1 FROM gestion__aprobaciones_reglas WHERE regla_id = ? AND empresa_id = ?", 'ii', $id, $empresa_id)) {
            throw new RuntimeException('Regla no encontrada.');
        }
        aprob_exec($c,
            "UPDATE gestion__aprobaciones_reglas SET documento = ?, obligacion_tipo_id = ?, requiere_aprobacion = ?, importe_desde = ?,
                    doble_aprobacion_desde = ?, permite_autoaprobar = ?, f_desde = ?, f_hasta = ?, usuario_modificacion_id = ?
              WHERE regla_id = ? AND empresa_id = ?",
            'siiddissiii', $doc, $tipo, $requiere, $desde, $doble, $auto, $f_desde, $f_hasta, $usuario_id, $id, $empresa_id);
        return $id;
    }
    aprob_exec($c,
        "INSERT INTO gestion__aprobaciones_reglas (empresa_id, documento, obligacion_tipo_id, requiere_aprobacion, importe_desde,
                doble_aprobacion_desde, permite_autoaprobar, f_desde, f_hasta, usuario_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        'isiiddissi', $empresa_id, $doc, $tipo, $requiere, $desde, $doble, $auto, $f_desde, $f_hasta, $usuario_id);
    return intval(mysqli_insert_id($c));
}

function aprob_tope_guardar(mysqli $c, int $empresa_id, int $usuario_id, array $d): int
{
    [$doc, $tipo, $f_desde, $f_hasta] = aprob_datos_comunes($c, $empresa_id, $d);
    $perfil = intval($d['empresa_perfil_id'] ?? 0);
    if (!aprob_filas($c, "SELECT 1 FROM conf__empresas_perfiles WHERE empresa_perfil_id = ? AND empresa_id = ?", 'ii', $perfil, $empresa_id)) {
        throw new RuntimeException('Elegí un perfil de la empresa.');
    }
    $max = aprob_num_opcional($d['importe_maximo'] ?? '');
    $id = intval($d['tope_id'] ?? 0);
    if ($id > 0) {
        if (!aprob_filas($c, "SELECT 1 FROM gestion__aprobaciones_topes WHERE tope_id = ? AND empresa_id = ?", 'ii', $id, $empresa_id)) {
            throw new RuntimeException('Tope no encontrado.');
        }
        aprob_exec($c,
            "UPDATE gestion__aprobaciones_topes SET documento = ?, obligacion_tipo_id = ?, empresa_perfil_id = ?, importe_maximo = ?,
                    f_desde = ?, f_hasta = ?, usuario_modificacion_id = ?
              WHERE tope_id = ? AND empresa_id = ?",
            'siidssiii', $doc, $tipo, $perfil, $max, $f_desde, $f_hasta, $usuario_id, $id, $empresa_id);
        return $id;
    }
    aprob_exec($c,
        "INSERT INTO gestion__aprobaciones_topes (empresa_id, documento, obligacion_tipo_id, empresa_perfil_id, importe_maximo, f_desde, f_hasta, usuario_id)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        'isiidssi', $empresa_id, $doc, $tipo, $perfil, $max, $f_desde, $f_hasta, $usuario_id);
    return intval(mysqli_insert_id($c));
}

/** Activa (1) o inactiva (2) una regla o un tope de la empresa. */
function aprob_cambiar_estado(mysqli $c, int $empresa_id, int $usuario_id, string $que, int $id, int $estado): void
{
    $tabla = $que === 'tope' ? 'gestion__aprobaciones_topes' : 'gestion__aprobaciones_reglas';
    $pk = $que === 'tope' ? 'tope_id' : 'regla_id';
    if (aprob_exec($c, "UPDATE $tabla SET tabla_estado_registro_id = ?, usuario_modificacion_id = ? WHERE $pk = ? AND empresa_id = ?",
                   'iiii', $estado, $usuario_id, $id, $empresa_id) < 1
        && !aprob_filas($c, "SELECT 1 FROM $tabla WHERE $pk = ? AND empresa_id = ?", 'ii', $id, $empresa_id)) {
        throw new RuntimeException('Registro no encontrado.');
    }
}
