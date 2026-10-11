<?php
declare(strict_types=1);

/**
 * modules/sindicatos/tasas_interes_model.php
 * Modelo para tramos de tasas de interés mensual (Página 1005).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/sindi_base_model.php';

function sindi_listar_tasas(mysqli $conexion, int $empresa_id, int $pagina_id): array
{
    $sql = "SELECT t.*, er.estado_registro
            FROM sindi__tasas_interes t
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = t.tabla_estado_registro_id
            WHERE t.empresa_id = ?
            ORDER BY t.f_desde DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $estadoId = (int)$row['tabla_estado_registro_id'];
        $id = (int)$row['tasa_interes_id'];
        $row['acciones'] = sindi_obtener_botones_acciones($conexion, $pagina_id, $estadoId, $id);
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $lista;
}

function sindi_obtener_tasa(mysqli $conexion, int $empresa_id, int $tasa_interes_id): ?array
{
    $stmt = mysqli_prepare($conexion, "SELECT * FROM sindi__tasas_interes WHERE tasa_interes_id = ? AND empresa_id = ? LIMIT 1");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "ii", $tasa_interes_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function sindi_guardar_tasa(mysqli $conexion, int $empresa_id, array $data, int $usuario_id): array
{
    $id = isset($data['tasa_interes_id']) ? (int)$data['tasa_interes_id'] : 0;
    $clase = intval($data['clase'] ?? 1);
    $tasa_mensual = floatval($data['tasa_mensual'] ?? 0);
    $f_desde = trim($data['f_desde'] ?? '');
    $f_hasta = trim($data['f_hasta'] ?? '');

    if (empty($f_desde) || empty($f_hasta) || $tasa_mensual <= 0) {
        return ['success' => false, 'message' => 'Tasa mensual mayor a cero y fechas desde/hasta son obligatorias.'];
    }

    if ($f_desde > $f_hasta) {
        return ['success' => false, 'message' => 'La fecha desde no puede ser posterior a la fecha hasta.'];
    }

    // Validar superposiciones con otros tramos de la misma clase
    $sqlOverlap = "SELECT COUNT(*) AS total FROM sindi__tasas_interes 
                   WHERE empresa_id = ? AND clase = ? AND tasa_interes_id <> ?
                     AND tabla_estado_registro_id = 1
                     AND NOT (f_hasta < ? OR f_desde > ?)";
    $stmtO = mysqli_prepare($conexion, $sqlOverlap);
    if ($stmtO) {
        mysqli_stmt_bind_param($stmtO, "iiiss", $empresa_id, $clase, $id, $f_desde, $f_hasta);
        mysqli_stmt_execute($stmtO);
        $rowO = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtO));
        mysqli_stmt_close($stmtO);
        if ($rowO && (int)$rowO['total'] > 0) {
            return ['success' => false, 'message' => 'El rango de fechas se superpone con otro tramo de tasa activo.'];
        }
    }

    if ($id > 0) {
        $stmt = mysqli_prepare($conexion, "UPDATE sindi__tasas_interes SET clase = ?, tasa_mensual = ?, f_desde = ?, f_hasta = ?, usuario_actualizacion_id = ?, fecha_actualizacion = NOW() WHERE tasa_interes_id = ? AND empresa_id = ?");
        if (!$stmt) return ['success' => false, 'message' => mysqli_error($conexion)];
        mysqli_stmt_bind_param($stmt, "idssiii", $clase, $tasa_mensual, $f_desde, $f_hasta, $usuario_id, $id, $empresa_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return ['success' => $ok, 'message' => $ok ? 'Tasa actualizada.' : 'Error al actualizar tasa.'];
    } else {
        $stmt = mysqli_prepare($conexion, "INSERT INTO sindi__tasas_interes (empresa_id, clase, tasa_mensual, f_desde, f_hasta, tabla_estado_registro_id, usuario_creacion_id, fecha_creacion) VALUES (?, ?, ?, ?, ?, 1, ?, NOW())");
        if (!$stmt) return ['success' => false, 'message' => mysqli_error($conexion)];
        mysqli_stmt_bind_param($stmt, "iidssi", $empresa_id, $clase, $tasa_mensual, $f_desde, $f_hasta, $usuario_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return ['success' => $ok, 'message' => $ok ? 'Tasa de interés registrada.' : 'Error al registrar tasa.'];
    }
}
