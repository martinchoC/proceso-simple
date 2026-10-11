<?php
declare(strict_types=1);

/**
 * modules/sindicatos/vencimientos_model.php
 * Modelo para cronograma de vencimientos por terminación de CUIT (Página 1004).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/sindi_base_model.php';

function sindi_listar_vencimientos(mysqli $conexion, int $empresa_id, int $pagina_id, ?int $periodo = null): array
{
    $sql = "SELECT v.*, er.estado_registro
            FROM sindi__vencimientos v
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = v.tabla_estado_registro_id
            WHERE v.empresa_id = ?";

    $params = [$empresa_id];
    $types = "i";

    if ($periodo !== null && $periodo > 0) {
        $sql .= " AND v.periodo = ?";
        $params[] = $periodo;
        $types .= "i";
    }

    $sql .= " ORDER BY v.periodo DESC, v.terminacion_cuit ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $estadoId = (int)$row['tabla_estado_registro_id'];
        $id = (int)$row['vencimiento_id'];
        $row['acciones'] = sindi_obtener_botones_acciones($conexion, $pagina_id, $estadoId, $id);
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $lista;
}

function sindi_obtener_vencimiento(mysqli $conexion, int $empresa_id, int $vencimiento_id): ?array
{
    $stmt = mysqli_prepare($conexion, "SELECT * FROM sindi__vencimientos WHERE vencimiento_id = ? AND empresa_id = ? LIMIT 1");
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "ii", $vencimiento_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function sindi_guardar_vencimiento(mysqli $conexion, int $empresa_id, array $data, int $usuario_id): array
{
    $id = isset($data['vencimiento_id']) ? (int)$data['vencimiento_id'] : 0;
    $periodo = intval($data['periodo'] ?? 0);
    $terminacion_cuit = intval($data['terminacion_cuit'] ?? 0);
    $tipo_vencimiento = trim($data['tipo_vencimiento'] ?? 'DDJJ');
    $f_vto = trim($data['f_vto'] ?? '');

    if ($periodo < 200001 || $terminacion_cuit < 0 || $terminacion_cuit > 9 || empty($f_vto)) {
        return ['success' => false, 'message' => 'Período, dígito (0-9) y fecha de vencimiento son obligatorios.'];
    }

    if ($id > 0) {
        $stmt = mysqli_prepare($conexion, "UPDATE sindi__vencimientos SET periodo = ?, terminacion_cuit = ?, tipo_vencimiento = ?, f_vto = ?, usuario_actualizacion_id = ?, fecha_actualizacion = NOW() WHERE vencimiento_id = ? AND empresa_id = ?");
        if (!$stmt) return ['success' => false, 'message' => mysqli_error($conexion)];
        mysqli_stmt_bind_param($stmt, "iissiii", $periodo, $terminacion_cuit, $tipo_vencimiento, $f_vto, $usuario_id, $id, $empresa_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return ['success' => $ok, 'message' => $ok ? 'Vencimiento actualizado.' : 'Error al actualizar vencimiento.'];
    } else {
        $stmt = mysqli_prepare($conexion, "INSERT INTO sindi__vencimientos (empresa_id, periodo, terminacion_cuit, tipo_vencimiento, f_vto, tabla_estado_registro_id, usuario_creacion_id, fecha_creacion) VALUES (?, ?, ?, ?, ?, 1, ?, NOW()) ON DUPLICATE KEY UPDATE f_vto = VALUES(f_vto)");
        if (!$stmt) return ['success' => false, 'message' => mysqli_error($conexion)];
        mysqli_stmt_bind_param($stmt, "iiissi", $empresa_id, $periodo, $terminacion_cuit, $tipo_vencimiento, $f_vto, $usuario_id);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return ['success' => $ok, 'message' => $ok ? 'Vencimiento registrado.' : 'Error al registrar vencimiento.'];
    }
}

function sindi_generar_vencimientos_periodo(mysqli $conexion, int $empresa_id, int $periodo, string $f_base, int $usuario_id): array
{
    if ($periodo < 200001 || empty($f_base)) {
        return ['success' => false, 'message' => 'Período y fecha base son obligatorios.'];
    }

    $baseTime = strtotime($f_base);
    if (!$baseTime) {
        return ['success' => false, 'message' => 'Fecha base no válida.'];
    }

    // Generar vencimientos para los dígitos 0 al 9
    // Se asigna 1 día hábil adicional cada 2 dígitos o fechas progresivas
    mysqli_begin_transaction($conexion);
    try {
        for ($digito = 0; $digito <= 9; $digito++) {
            // Ejemplo de escalonamiento habitual: 0-1 día base, 2-3 +1 día, 4-5 +2 días, etc.
            $desplazamiento = intdiv($digito, 2);
            $vtoTime = strtotime("+{$desplazamiento} weekdays", $baseTime);
            $f_vto = date('Y-m-d', $vtoTime);

            $stmt = mysqli_prepare($conexion, "INSERT INTO sindi__vencimientos (empresa_id, periodo, terminacion_cuit, tipo_vencimiento, f_vto, tabla_estado_registro_id, usuario_creacion_id, fecha_creacion)
                    VALUES (?, ?, ?, 'DDJJ', ?, 1, ?, NOW())
                    ON DUPLICATE KEY UPDATE f_vto = VALUES(f_vto), usuario_actualizacion_id = ?, fecha_actualizacion = NOW()");
            mysqli_stmt_bind_param($stmt, "iiisii", $empresa_id, $periodo, $digito, $f_vto, $usuario_id, $usuario_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        mysqli_commit($conexion);
        return ['success' => true, 'message' => "Cronograma de vencimientos para el período {$periodo} generado exitosamente."];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}
