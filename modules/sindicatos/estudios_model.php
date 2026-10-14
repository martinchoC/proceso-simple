<?php
declare(strict_types=1);

/**
 * modules/sindicatos/estudios_model.php
 * Modelo para estudios contables y vinculaciones con empleadores (Página 1011).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/accesos_empleadores.php';
require_once __DIR__ . '/sindi_base_model.php';

function sindi_listar_estudios(mysqli $conexion, int $empresa_id, int $pagina_id): array
{
    $sql = "SELECT est.*, ent.entidad_nombre AS entidad_razon_social, ent.cuit AS entidad_cuit,
                   suc.sucursal_telefono AS entidad_telefono, suc.sucursal_email AS entidad_email,
                   (SELECT COUNT(*) FROM sindi__estudios_empleadores ee WHERE ee.estudio_id = est.estudio_id AND ee.tabla_estado_registro_id = 1) AS vinculaciones_activas,
                   er.estado_registro
            FROM sindi__estudios est
            INNER JOIN gestion__entidades ent ON ent.entidad_id = est.entidad_id
            LEFT JOIN gestion__entidades_sucursales suc ON suc.entidad_id = ent.entidad_id AND suc.sucursal_numero = 1
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = est.tabla_estado_registro_id
            WHERE est.empresa_id = ?
            ORDER BY ent.entidad_nombre ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $estadoId = (int)$row['tabla_estado_registro_id'];
        $id = (int)$row['estudio_id'];
        $row['acciones'] = sindi_obtener_botones_acciones($conexion, $pagina_id, $estadoId, $id);
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $lista;
}

function sindi_obtener_estudio(mysqli $conexion, int $empresa_id, int $estudio_id): ?array
{
    $sql = "SELECT est.*, ent.entidad_nombre AS entidad_razon_social, ent.cuit AS entidad_cuit,
                   suc.sucursal_telefono AS entidad_telefono, suc.sucursal_email AS entidad_email,
                   ent.domicilio_legal AS entidad_domicilio
            FROM sindi__estudios est
            INNER JOIN gestion__entidades ent ON ent.entidad_id = est.entidad_id
            LEFT JOIN gestion__entidades_sucursales suc ON suc.entidad_id = ent.entidad_id AND suc.sucursal_numero = 1
            WHERE est.estudio_id = ? AND est.empresa_id = ? LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "ii", $estudio_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function sindi_guardar_estudio(mysqli $conexion, int $empresa_id, array $data, int $usuario_id): array
{
    $estudio_id = isset($data['estudio_id']) ? (int)$data['estudio_id'] : 0;
    $cuit = preg_replace('/\D/', '', (string)($data['cuit'] ?? ''));
    $razon_social = trim($data['razon_social'] ?? '');
    $email = trim($data['email'] ?? '');
    $telefono = trim($data['telefono'] ?? '');
    $domicilio = trim($data['domicilio'] ?? '');
    $matricula = trim($data['matricula'] ?? '');
    $contacto = trim($data['contacto'] ?? '');

    if (strlen($cuit) !== 11 || empty($razon_social)) {
        return ['success' => false, 'message' => 'CUIT (11 dígitos) y Razón Social son obligatorios.'];
    }

    mysqli_begin_transaction($conexion);
    try {
        // 1. Entidad en gestion__entidades
        $sqlEnt = "SELECT entidad_id FROM gestion__entidades WHERE empresa_id = ? AND cuit = ? LIMIT 1";
        $stmtE = mysqli_prepare($conexion, $sqlEnt);
        mysqli_stmt_bind_param($stmtE, "is", $empresa_id, $cuit);
        mysqli_stmt_execute($stmtE);
        $resE = mysqli_stmt_get_result($stmtE);
        $entExistente = mysqli_fetch_assoc($resE);
        mysqli_stmt_close($stmtE);

        $entidad_id = 0;
        if ($entExistente) {
            $entidad_id = (int)$entExistente['entidad_id'];
            $sqlUpdE = "UPDATE gestion__entidades SET entidad_nombre = ?, domicilio_legal = ? WHERE entidad_id = ?";
            $stmtUpdE = mysqli_prepare($conexion, $sqlUpdE);
            mysqli_stmt_bind_param($stmtUpdE, "ssi", $razon_social, $domicilio, $entidad_id);
            mysqli_stmt_execute($stmtUpdE);
            mysqli_stmt_close($stmtUpdE);
        } else {
            $sqlInsE = "INSERT INTO gestion__entidades (empresa_id, cuit, entidad_nombre, domicilio_legal, tabla_estado_registro_id, fecha_alta)
                        VALUES (?, ?, ?, ?, 1, NOW())";
            $stmtInsE = mysqli_prepare($conexion, $sqlInsE);
            mysqli_stmt_bind_param($stmtInsE, "isss", $empresa_id, $cuit, $razon_social, $domicilio);
            mysqli_stmt_execute($stmtInsE);
            $entidad_id = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmtInsE);
        }

        // Sucursal N° 1 = Casa Central y rol ESTUDIO (por código)
        sindi_guardar_sucursal_central($conexion, $empresa_id, $entidad_id, $domicilio, $telefono, $email);
        sindi_asegurar_rol_entidad($conexion, $entidad_id, 'EST');

        if ($estudio_id > 0) {
            $sqlEst = "UPDATE sindi__estudios SET matricula = ?, contacto = ?, usuario_actualizacion_id = ?, fecha_actualizacion = NOW() WHERE estudio_id = ? AND empresa_id = ?";
            $stmtEst = mysqli_prepare($conexion, $sqlEst);
            mysqli_stmt_bind_param($stmtEst, "ssiii", $matricula, $contacto, $usuario_id, $estudio_id, $empresa_id);
            mysqli_stmt_execute($stmtEst);
            mysqli_stmt_close($stmtEst);
        } else {
            $sqlEst = "INSERT INTO sindi__estudios (empresa_id, entidad_id, matricula, contacto, tabla_estado_registro_id, usuario_creacion_id, fecha_creacion)
                       VALUES (?, ?, ?, ?, 1, ?, NOW()) ON DUPLICATE KEY UPDATE matricula = VALUES(matricula), contacto = VALUES(contacto)";
            $stmtEst = mysqli_prepare($conexion, $sqlEst);
            mysqli_stmt_bind_param($stmtEst, "iisss", $empresa_id, $entidad_id, $matricula, $contacto, $usuario_id);
            mysqli_stmt_execute($stmtEst);
            mysqli_stmt_close($stmtEst);
        }

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Estudio contable guardado exitosamente.'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}

// Vinculaciones
function sindi_listar_vinculaciones(mysqli $conexion, int $empresa_id, int $estudio_id): array
{
    $sql = "SELECT ee.*, ent.entidad_nombre AS empleador_nombre, ent.cuit AS empleador_cuit,
                   er.estado_registro
            FROM sindi__estudios_empleadores ee
            INNER JOIN sindi__empleadores emp ON emp.empleador_id = ee.empleador_id
            INNER JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = ee.tabla_estado_registro_id
            WHERE ee.empresa_id = ? AND ee.estudio_id = ?
            ORDER BY ee.f_solicitud DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $estudio_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $lista = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $lista[] = $r;
    }
    mysqli_stmt_close($stmt);
    return $lista;
}

function sindi_vincular_estudio_empleador(mysqli $conexion, int $empresa_id, int $estudio_id, int $empleador_id, string $iniciado_por, int $usuario_id): array
{
    mysqli_begin_transaction($conexion);
    try {
        // Verificar si ya existe vínculo
        $sqlChk = "SELECT estudio_empleador_id, tabla_estado_registro_id FROM sindi__estudios_empleadores WHERE empresa_id = ? AND estudio_id = ? AND empleador_id = ? LIMIT 1";
        $stmtC = mysqli_prepare($conexion, $sqlChk);
        mysqli_stmt_bind_param($stmtC, "iii", $empresa_id, $estudio_id, $empleador_id);
        mysqli_stmt_execute($stmtC);
        $existente = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtC));
        mysqli_stmt_close($stmtC);

        // Si es iniciado por el SINDICATO o EMPLEADOR directo, queda Activo (1); si es iniciado por ESTUDIO queda Pendiente (4)
        $estadoInicial = ($iniciado_por === 'SINDICATO' || $iniciado_por === 'EMPLEADOR') ? 1 : 4;
        $f_res = ($estadoInicial === 1) ? date('Y-m-d H:i:s') : null;

        $ee_id = 0;
        if ($existente) {
            $ee_id = (int)$existente['estudio_empleador_id'];
            $sqlUpd = "UPDATE sindi__estudios_empleadores SET tabla_estado_registro_id = ?, f_resolucion = ?, usuario_actualizacion_id = ?, fecha_actualizacion = NOW() WHERE estudio_empleador_id = ?";
            $stmtU = mysqli_prepare($conexion, $sqlUpd);
            mysqli_stmt_bind_param($stmtU, "isii", $estadoInicial, $f_res, $usuario_id, $ee_id);
            mysqli_stmt_execute($stmtU);
            mysqli_stmt_close($stmtU);
        } else {
            $sqlIns = "INSERT INTO sindi__estudios_empleadores (empresa_id, estudio_id, empleador_id, iniciado_por, f_solicitud, f_resolucion, tabla_estado_registro_id, usuario_creacion_id, fecha_creacion)
                       VALUES (?, ?, ?, ?, NOW(), ?, ?, ?, NOW())";
            $stmtI = mysqli_prepare($conexion, $sqlIns);
            mysqli_stmt_bind_param($stmtI, "iiissii", $empresa_id, $estudio_id, $empleador_id, $iniciado_por, $f_res, $estadoInicial, $usuario_id);
            mysqli_stmt_execute($stmtI);
            $ee_id = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmtI);
        }

        // Si quedó activo, otorgar acceso en sindi__usuarios_empleadores
        if ($estadoInicial === 1) {
            // Buscar usuario correspondiente al CUIT del estudio
            $sqlUid = "SELECT u.usuario_id FROM sindi__estudios est
                       INNER JOIN gestion__entidades ent ON ent.entidad_id = est.entidad_id
                       INNER JOIN conf__usuarios u ON u.usuario = ent.cuit
                       WHERE est.estudio_id = ? LIMIT 1";
            $stmtUid = mysqli_prepare($conexion, $sqlUid);
            mysqli_stmt_bind_param($stmtUid, "i", $estudio_id);
            mysqli_stmt_execute($stmtUid);
            $uRow = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtUid));
            mysqli_stmt_close($stmtUid);

            if ($uRow) {
                $uidEstudio = (int)$uRow['usuario_id'];
                $hoy = date('Y-m-d');
                $sqlAcc = "INSERT INTO sindi__usuarios_empleadores (usuario_id, empresa_id, empleador_id, rol, estudio_empleador_id, f_desde, tabla_estado_registro_id, usuario_creacion_id, fecha_creacion)
                           VALUES (?, ?, ?, 'ESTUDIO', ?, ?, 1, ?, NOW())
                           ON DUPLICATE KEY UPDATE tabla_estado_registro_id = 1, f_desde = VALUES(f_desde), f_hasta = NULL";
                $stmtAcc = mysqli_prepare($conexion, $sqlAcc);
                mysqli_stmt_bind_param($stmtAcc, "iiiisii", $uidEstudio, $empresa_id, $empleador_id, $ee_id, $hoy, $usuario_id);
                mysqli_stmt_execute($stmtAcc);
                mysqli_stmt_close($stmtAcc);
            }
        }

        mysqli_commit($conexion);
        AccesosEmpleadoresHelper::limpiarCache();
        return ['success' => true, 'message' => 'Vinculación registrada correctamente.'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}
