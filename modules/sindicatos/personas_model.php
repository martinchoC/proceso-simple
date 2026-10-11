<?php
declare(strict_types=1);

/**
 * modules/sindicatos/personas_model.php
 * Modelo para padrón de trabajadores y personas (Página 1012).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/sindi_base_model.php';

function sindi_listar_personas(mysqli $conexion, int $empresa_id, int $pagina_id): array
{
    $sql = "SELECT p.*, er.estado_registro,
                   (SELECT COUNT(*) FROM sindi__relaciones_laborales rl WHERE rl.persona_id = p.persona_id AND rl.empresa_id = p.empresa_id AND rl.tabla_estado_registro_id = 1 AND rl.f_baja IS NULL) AS empleos_activos
            FROM sindi__personas p
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = p.tabla_estado_registro_id
            WHERE p.empresa_id = ?
            ORDER BY p.apellido ASC, p.nombre ASC
            LIMIT 1000";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $estadoId = (int)$row['tabla_estado_registro_id'];
        $id = (int)$row['persona_id'];
        $row['acciones'] = sindi_obtener_botones_acciones($conexion, $pagina_id, $estadoId, $id);
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $lista;
}

function sindi_obtener_persona(mysqli $conexion, int $empresa_id, int $persona_id): ?array
{
    $sql = "SELECT p.* FROM sindi__personas p WHERE p.persona_id = ? AND p.empresa_id = ? LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "ii", $persona_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function sindi_guardar_persona(mysqli $conexion, int $empresa_id, array $data, int $usuario_id): array
{
    $id = isset($data['persona_id']) ? (int)$data['persona_id'] : 0;
    $cuil = preg_replace('/\D/', '', (string)($data['cuil'] ?? ''));
    $apellido = trim($data['apellido'] ?? '');
    $nombre = trim($data['nombre'] ?? '');
    $documento_nro = trim($data['documento_nro'] ?? '');
    $documento_tipo_id = !empty($data['documento_tipo_id']) ? intval($data['documento_tipo_id']) : 1;
    $sexo = in_array($data['sexo'] ?? '', ['M', 'F', 'X']) ? $data['sexo'] : 'M';
    $f_nacimiento = !empty($data['f_nacimiento']) ? $data['f_nacimiento'] : null;
    $email = trim($data['email'] ?? '');
    $telefono = trim($data['telefono'] ?? '');
    $direccion = trim($data['direccion'] ?? '');

    if (strlen($cuil) !== 11 || empty($apellido) || empty($nombre)) {
        return ['success' => false, 'message' => 'CUIL (11 dígitos), Apellido y Nombre son obligatorios.'];
    }

    if ($id > 0) {
        $sql = "UPDATE sindi__personas SET
                cuil = ?, apellido = ?, nombre = ?, documento_tipo_id = ?, documento_nro = ?,
                sexo = ?, f_nacimiento = ?, email = ?, telefono = ?, direccion = ?,
                usuario_actualizacion_id = ?, fecha_actualizacion = NOW()
                WHERE persona_id = ? AND empresa_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) return ['success' => false, 'message' => mysqli_error($conexion)];

        mysqli_stmt_bind_param(
            $stmt,
            "sssissssssiii",
            $cuil, $apellido, $nombre, $documento_tipo_id, $documento_nro,
            $sexo, $f_nacimiento, $email, $telefono, $direccion,
            $usuario_id, $id, $empresa_id
        );
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return ['success' => $ok, 'message' => $ok ? 'Persona actualizada con éxito.' : 'Error al actualizar persona.'];
    } else {
        $sql = "INSERT INTO sindi__personas (
                empresa_id, cuil, apellido, nombre, documento_tipo_id, documento_nro,
                sexo, f_nacimiento, email, telefono, direccion,
                tabla_estado_registro_id, usuario_creacion_id, fecha_creacion
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW()
            )";
        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) return ['success' => false, 'message' => mysqli_error($conexion)];

        mysqli_stmt_bind_param(
            $stmt,
            "isssissssssi",
            $empresa_id, $cuil, $apellido, $nombre, $documento_tipo_id, $documento_nro,
            $sexo, $f_nacimiento, $email, $telefono, $direccion, $usuario_id
        );
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        return ['success' => $ok, 'message' => $ok ? 'Persona registrada en padrón.' : 'Error al registrar persona.'];
    }
}
