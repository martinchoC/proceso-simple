<?php
declare(strict_types=1);

/**
 * modules/sindicatos/parametros_model.php
 * Modelo para configuración general del sindicato (Página 1001).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/secretos.php';
require_once __DIR__ . '/sindi_base_model.php';

function sindi_obtener_parametros(mysqli $conexion, int $empresa_id): ?array
{
    $sql = "SELECT p.*, c.nombre AS convenio_nombre
            FROM sindi__parametros p
            LEFT JOIN sindi__convenios c ON c.convenio_id = p.convenio_id_default
            WHERE p.empresa_id = ? AND p.tabla_estado_registro_id = 1
            ORDER BY p.f_desde DESC LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;

    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if ($row) {
        $row['tiene_smtp_clave'] = !empty($row['smtp_clave']);
        $row['tiene_correo_ingreso_clave'] = !empty($row['correo_ingreso_clave']);
        // Nunca enviar los secretos reales al cliente
        $row['smtp_clave'] = $row['tiene_smtp_clave'] ? '••••••••' : '';
        $row['correo_ingreso_clave'] = $row['tiene_correo_ingreso_clave'] ? '••••••••' : '';
    }

    return $row;
}

function sindi_guardar_parametros(mysqli $conexion, int $empresa_id, array $data, int $usuario_id): array
{
    $parametro_id = isset($data['parametro_id']) ? (int)$data['parametro_id'] : 0;
    $portal_slug = trim($data['portal_slug'] ?? 'sindicato');
    $dias_gracia = intval($data['dias_gracia'] ?? 0);
    $aprobacion_autoalta = !empty($data['aprobacion_autoalta']) ? 1 : 0;
    $email_avisos = trim($data['email_avisos'] ?? '');
    $media_jornada_piso = in_array($data['media_jornada_piso'] ?? '', ['COMPLETO', 'PROPORCIONAL', 'SIN_PISO']) 
        ? $data['media_jornada_piso'] 
        : 'COMPLETO';
    $media_jornada_factor = floatval($data['media_jornada_factor'] ?? 0.50);
    $convenio_id_default = !empty($data['convenio_id_default']) ? intval($data['convenio_id_default']) : null;

    // SMTP
    $smtp_host = trim($data['smtp_host'] ?? '');
    $smtp_puerto = intval($data['smtp_puerto'] ?? 587);
    $smtp_seguridad = trim($data['smtp_seguridad'] ?? 'tls');
    $smtp_usuario = trim($data['smtp_usuario'] ?? '');
    $nueva_smtp_clave = trim($data['smtp_clave'] ?? '');

    // Correo ingreso DDJJ
    $correo_ingreso_host = trim($data['correo_ingreso_host'] ?? '');
    $correo_ingreso_puerto = intval($data['correo_ingreso_puerto'] ?? 993);
    $correo_ingreso_protocolo = trim($data['correo_ingreso_protocolo'] ?? 'imap');
    $correo_ingreso_seguridad = trim($data['correo_ingreso_seguridad'] ?? 'ssl');
    $correo_ingreso_usuario = trim($data['correo_ingreso_usuario'] ?? '');
    $nueva_correo_ingreso_clave = trim($data['correo_ingreso_clave'] ?? '');

    // Google Drive
    $gdrive_service_account_path = trim($data['gdrive_service_account_path'] ?? '');
    $gdrive_folder_id = trim($data['gdrive_folder_id'] ?? '');

    // Obtener actual para preservar contraseñas si no fueron cambiadas
    $actual = null;
    if ($parametro_id > 0) {
        $stmtA = mysqli_prepare($conexion, "SELECT * FROM sindi__parametros WHERE parametro_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmtA, "ii", $parametro_id, $empresa_id);
        mysqli_stmt_execute($stmtA);
        $actual = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtA));
        mysqli_stmt_close($stmtA);
    }

    $smtp_clave_final = $actual['smtp_clave'] ?? null;
    if ($nueva_smtp_clave !== '' && $nueva_smtp_clave !== '••••••••') {
        $smtp_clave_final = secreto_cifrar($nueva_smtp_clave);
    }

    $correo_ingreso_clave_final = $actual['correo_ingreso_clave'] ?? null;
    if ($nueva_correo_ingreso_clave !== '' && $nueva_correo_ingreso_clave !== '••••••••') {
        $correo_ingreso_clave_final = secreto_cifrar($nueva_correo_ingreso_clave);
    }

    if ($parametro_id > 0) {
        $sql = "UPDATE sindi__parametros SET 
                portal_slug = ?, dias_gracia = ?, aprobacion_autoalta = ?, email_avisos = ?,
                media_jornada_piso = ?, media_jornada_factor = ?, convenio_id_default = ?,
                smtp_host = ?, smtp_puerto = ?, smtp_seguridad = ?, smtp_usuario = ?, smtp_clave = ?,
                correo_ingreso_host = ?, correo_ingreso_puerto = ?, correo_ingreso_protocolo = ?,
                correo_ingreso_seguridad = ?, correo_ingreso_usuario = ?, correo_ingreso_clave = ?,
                gdrive_service_account_path = ?, gdrive_folder_id = ?,
                usuario_actualizacion_id = ?, fecha_actualizacion = NOW()
                WHERE parametro_id = ? AND empresa_id = ?";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            return ['success' => false, 'message' => 'Error preparando actualización: ' . mysqli_error($conexion)];
        }

        mysqli_stmt_bind_param(
            $stmt,
            "siisdsisissssissssssiii",
            $portal_slug, $dias_gracia, $aprobacion_autoalta, $email_avisos,
            $media_jornada_piso, $media_jornada_factor, $convenio_id_default,
            $smtp_host, $smtp_puerto, $smtp_seguridad, $smtp_usuario, $smtp_clave_final,
            $correo_ingreso_host, $correo_ingreso_puerto, $correo_ingreso_protocolo,
            $correo_ingreso_seguridad, $correo_ingreso_usuario, $correo_ingreso_clave_final,
            $gdrive_service_account_path, $gdrive_folder_id,
            $usuario_id, $parametro_id, $empresa_id
        );

        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!$ok) {
            return ['success' => false, 'message' => 'Error al actualizar parámetros.'];
        }
        return ['success' => true, 'message' => 'Parámetros actualizados con éxito.'];
    } else {
        $f_desde = date('Y-m-d');
        $sql = "INSERT INTO sindi__parametros (
                empresa_id, f_desde, portal_slug, dias_gracia, aprobacion_autoalta, email_avisos,
                media_jornada_piso, media_jornada_factor, convenio_id_default,
                smtp_host, smtp_puerto, smtp_seguridad, smtp_usuario, smtp_clave,
                correo_ingreso_host, correo_ingreso_puerto, correo_ingreso_protocolo,
                correo_ingreso_seguridad, correo_ingreso_usuario, correo_ingreso_clave,
                gdrive_service_account_path, gdrive_folder_id,
                tabla_estado_registro_id, usuario_creacion_id, fecha_creacion
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW()
            )";

        $stmt = mysqli_prepare($conexion, $sql);
        if (!$stmt) {
            return ['success' => false, 'message' => 'Error preparando inserción: ' . mysqli_error($conexion)];
        }

        mysqli_stmt_bind_param(
            $stmt,
            "issiisdsisissssissssssi",
            $empresa_id, $f_desde, $portal_slug, $dias_gracia, $aprobacion_autoalta, $email_avisos,
            $media_jornada_piso, $media_jornada_factor, $convenio_id_default,
            $smtp_host, $smtp_puerto, $smtp_seguridad, $smtp_usuario, $smtp_clave_final,
            $correo_ingreso_host, $correo_ingreso_puerto, $correo_ingreso_protocolo,
            $correo_ingreso_seguridad, $correo_ingreso_usuario, $correo_ingreso_clave_final,
            $gdrive_service_account_path, $gdrive_folder_id, $usuario_id
        );

        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!$ok) {
            return ['success' => false, 'message' => 'Error al registrar parámetros.'];
        }
        return ['success' => true, 'message' => 'Parámetros configurados con éxito.'];
    }
}
