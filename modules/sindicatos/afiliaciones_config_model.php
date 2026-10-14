<?php
declare(strict_types=1);

/**
 * modules/sindicatos/afiliaciones_config_model.php
 * Tipos de afiliación, parentescos y vigencias por edad (Página 1054).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/sindi_base_model.php';

function sindi_afcfg_listar_tipos(mysqli $conexion, int $empresa_id, int $pagina_id): array
{
    $stmt = mysqli_prepare($conexion, "SELECT * FROM sindi__afiliaciones_tipos WHERE empresa_id = ? ORDER BY afiliacion_tipo_id");
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $lista = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    foreach ($lista as &$t) {
        $t['acciones'] = sindi_obtener_botones_acciones($conexion, $pagina_id, (int)$t['tabla_estado_registro_id'], (int)$t['afiliacion_tipo_id']);
    }
    unset($t);
    return $lista;
}

/** Edita nombre y cuota de un tipo (el código, el grupo y la forma de alta son de la estructura del módulo). */
function sindi_afcfg_guardar_tipo(mysqli $conexion, int $empresa_id, array $d): array
{
    $id = (int)($d['afiliacion_tipo_id'] ?? 0);
    $nombre = trim((string)($d['nombre'] ?? ''));
    $paga = !empty($d['paga_cuota_directa']) ? 1 : 0;
    $importe = round(max(0, (float)($d['importe_cuota'] ?? 0)), 2);
    if ($id <= 0 || $nombre === '') {
        return ['success' => false, 'message' => 'Nombre obligatorio.'];
    }
    $stmt = mysqli_prepare($conexion, "SELECT grupo FROM sindi__afiliaciones_tipos WHERE afiliacion_tipo_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $grupo = mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0] ?? null;
    mysqli_stmt_close($stmt);
    if ($grupo === null) {
        return ['success' => false, 'message' => 'Tipo no encontrado.'];
    }
    if ($grupo === 'FAMILIAR') {
        $paga = 0;
        $importe = 0.0;
    }
    $stmt = mysqli_prepare($conexion, "UPDATE sindi__afiliaciones_tipos SET nombre = ?, paga_cuota_directa = ?, importe_cuota = ?
                                       WHERE afiliacion_tipo_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, "sidii", $nombre, $paga, $importe, $id, $empresa_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return ['success' => true, 'message' => 'Tipo de afiliación actualizado.'];
}

function sindi_afcfg_catalogos(mysqli $conexion, int $empresa_id): array
{
    $stmt = mysqli_prepare($conexion, "SELECT p.*, inv.nombre AS inverso_nombre FROM sindi__parentescos p
                                       LEFT JOIN sindi__parentescos inv ON inv.parentesco_id = p.parentesco_inverso_id
                                       WHERE p.empresa_id = ? ORDER BY p.parentesco_id");
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $parentescos = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    $stmt = mysqli_prepare($conexion, "SELECT v.*, p.nombre AS parentesco FROM sindi__afiliaciones_vigencias v
                                       INNER JOIN sindi__parentescos p ON p.parentesco_id = v.parentesco_id
                                       WHERE v.empresa_id = ? ORDER BY p.parentesco_id");
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $vigencias = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return ['parentescos' => $parentescos, 'vigencias' => $vigencias];
}

function sindi_afcfg_guardar_parentesco(mysqli $conexion, int $empresa_id, array $d): array
{
    $id = (int)($d['parentesco_id'] ?? 0);
    $codigo = strtoupper(preg_replace('/[^A-Za-z_]/', '', (string)($d['codigo'] ?? '')));
    $nombre = trim((string)($d['nombre'] ?? ''));
    $inverso = !empty($d['parentesco_inverso_id']) ? (int)$d['parentesco_inverso_id'] : null;
    $estado = ((int)($d['tabla_estado_registro_id'] ?? 1)) === 2 ? 2 : 1;
    if ($codigo === '' || $nombre === '') {
        return ['success' => false, 'message' => 'Código y nombre obligatorios.'];
    }
    try {
        if ($id > 0) {
            $stmt = mysqli_prepare($conexion, "UPDATE sindi__parentescos SET codigo = ?, nombre = ?, parentesco_inverso_id = ?, tabla_estado_registro_id = ?
                                               WHERE parentesco_id = ? AND empresa_id = ?");
            mysqli_stmt_bind_param($stmt, "ssiiii", $codigo, $nombre, $inverso, $estado, $id, $empresa_id);
        } else {
            $stmt = mysqli_prepare($conexion, "INSERT INTO sindi__parentescos (empresa_id, codigo, nombre, parentesco_inverso_id, tabla_estado_registro_id)
                                               VALUES (?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, "issii", $empresa_id, $codigo, $nombre, $inverso, $estado);
        }
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return ['success' => true, 'message' => 'Parentesco guardado.'];
    } catch (mysqli_sql_exception $e) {
        return ['success' => false, 'message' => 'Ya existe un parentesco con ese código.'];
    }
}

/** Una regla por parentesco: alta o edición (la clave es el parentesco). */
function sindi_afcfg_guardar_vigencia(mysqli $conexion, int $empresa_id, array $d): array
{
    $parentesco_id = (int)($d['parentesco_id'] ?? 0);
    $edad = ($d['edad_maxima'] ?? '') !== '' ? (int)$d['edad_maxima'] : null;
    $edadExc = ($d['edad_maxima_excepcion'] ?? '') !== '' ? (int)$d['edad_maxima_excepcion'] : null;
    $doc = trim((string)($d['documento_excepcion'] ?? '')) ?: null;
    $meses = max(1, (int)($d['meses_validez_documento'] ?? 12));
    $estado = ((int)($d['tabla_estado_registro_id'] ?? 1)) === 2 ? 2 : 1;

    $stmt = mysqli_prepare($conexion, "SELECT 1 FROM sindi__parentescos WHERE parentesco_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $parentesco_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $ok = (bool)mysqli_fetch_row(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$ok) {
        return ['success' => false, 'message' => 'Parentesco no válido.'];
    }
    if ($edadExc !== null && ($edad === null || $edadExc <= $edad || $doc === null)) {
        return ['success' => false, 'message' => 'La edad con excepción debe ser mayor que la edad máxima y requiere indicar el documento.'];
    }
    $stmt = mysqli_prepare($conexion, "INSERT INTO sindi__afiliaciones_vigencias
            (empresa_id, parentesco_id, edad_maxima, edad_maxima_excepcion, documento_excepcion, meses_validez_documento, tabla_estado_registro_id)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE edad_maxima = VALUES(edad_maxima), edad_maxima_excepcion = VALUES(edad_maxima_excepcion),
            documento_excepcion = VALUES(documento_excepcion), meses_validez_documento = VALUES(meses_validez_documento),
            tabla_estado_registro_id = VALUES(tabla_estado_registro_id)");
    mysqli_stmt_bind_param($stmt, "iiiisii", $empresa_id, $parentesco_id, $edad, $edadExc, $doc, $meses, $estado);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return ['success' => true, 'message' => 'Vigencia guardada. Rige para las altas y documentos nuevos.'];
}
