<?php
declare(strict_types=1);

/**
 * modules/sindicatos/empleadores_model.php
 * Modelo para el padrón de empleadores (Página 1010).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/accesos_empleadores.php';
require_once __DIR__ . '/sindi_base_model.php';

function sindi_listar_empleadores(mysqli $conexion, int $empresa_id, int $usuario_id, int $pagina_id): array
{
    $filtroAcceso = AccesosEmpleadoresHelper::filtroSql($conexion, $usuario_id, $empresa_id, 'emp.empleador_id');

    $sql = "SELECT emp.*, ent.entidad_nombre AS entidad_razon_social, ent.cuit AS entidad_cuit,
                   suc.sucursal_telefono AS entidad_telefono, suc.sucursal_email AS entidad_email,
                   c.codigo AS convenio_codigo, cm.nombre AS mora_nombre, cm.color AS mora_color,
                   er.estado_registro
            FROM sindi__empleadores emp
            INNER JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
            LEFT JOIN gestion__entidades_sucursales suc ON suc.entidad_id = ent.entidad_id AND suc.tabla_estado_registro_id = 1
            LEFT JOIN sindi__convenios c ON c.convenio_id = emp.convenio_id
            LEFT JOIN sindi__categorias_mora cm ON cm.categoria_mora_id = emp.categoria_mora_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = emp.tabla_estado_registro_id
            WHERE emp.empresa_id = ? AND {$filtroAcceso}
            ORDER BY ent.entidad_nombre ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $estadoId = (int)$row['tabla_estado_registro_id'];
        $id = (int)$row['empleador_id'];
        $row['acciones'] = sindi_obtener_botones_acciones($conexion, $pagina_id, $estadoId, $id);
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $lista;
}

function sindi_obtener_empleador(mysqli $conexion, int $empresa_id, int $usuario_id, int $empleador_id): ?array
{
    AccesosEmpleadoresHelper::exigirAcceso($conexion, $usuario_id, $empresa_id, $empleador_id);

    $sql = "SELECT emp.*, ent.entidad_nombre AS entidad_razon_social, ent.cuit AS entidad_cuit,
                   suc.sucursal_telefono AS entidad_telefono, suc.sucursal_email AS entidad_email,
                   ent.domicilio_legal AS entidad_domicilio, ent.localidad_id
            FROM sindi__empleadores emp
            INNER JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
            LEFT JOIN gestion__entidades_sucursales suc ON suc.entidad_id = ent.entidad_id AND suc.tabla_estado_registro_id = 1
            WHERE emp.empleador_id = ? AND emp.empresa_id = ? LIMIT 1";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "ii", $empleador_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function sindi_guardar_empleador(mysqli $conexion, int $empresa_id, array $data, int $usuario_id): array
{
    $empleador_id = isset($data['empleador_id']) ? (int)$data['empleador_id'] : 0;
    $cuit = preg_replace('/\D/', '', (string)($data['cuit'] ?? ''));
    $razon_social = trim($data['razon_social'] ?? '');
    $email = trim($data['email'] ?? '');
    $telefono = trim($data['telefono'] ?? '');
    $domicilio = trim($data['domicilio'] ?? '');
    $rubro = trim($data['rubro'] ?? '');
    $convenio_id = !empty($data['convenio_id']) ? intval($data['convenio_id']) : null;
    $f_alta_padron = !empty($data['f_alta_padron']) ? $data['f_alta_padron'] : date('Y-m-d');

    if (strlen($cuit) !== 11 || empty($razon_social)) {
        return ['success' => false, 'message' => 'CUIT (11 dígitos) y Razón Social son obligatorios.'];
    }

    if ($empleador_id > 0) {
        AccesosEmpleadoresHelper::exigirAcceso($conexion, $usuario_id, $empresa_id, $empleador_id);
    }

    mysqli_begin_transaction($conexion);
    try {
        // 1. Buscar o crear entidad en gestion__entidades
        $sqlEnt = "SELECT entidad_id FROM gestion__entidades WHERE empresa_id = ? AND cuit = ? LIMIT 1";
        $stmtE = mysqli_prepare($conexion, $sqlEnt);
        mysqli_stmt_bind_param($stmtE, "is", $empresa_id, $cuit);
        mysqli_stmt_execute($stmtE);
        $resE = mysqli_stmt_get_result($stmtE);
        $entidadExistente = mysqli_fetch_assoc($resE);
        mysqli_stmt_close($stmtE);

        $entidad_id = 0;
        if ($entidadExistente) {
            $entidad_id = (int)$entidadExistente['entidad_id'];
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

        // Sucursal Casa Central
        $sqlSuc = "INSERT INTO gestion__entidades_sucursales (entidad_id, empresa_id, sucursal_nombre, sucursal_direccion, sucursal_telefono, sucursal_email, tabla_estado_registro_id)
                   VALUES (?, ?, 'Casa Central', ?, ?, ?, 1)
                   ON DUPLICATE KEY UPDATE sucursal_direccion = VALUES(sucursal_direccion), sucursal_telefono = VALUES(sucursal_telefono), sucursal_email = VALUES(sucursal_email)";
        $stmtSuc = mysqli_prepare($conexion, $sqlSuc);
        mysqli_stmt_bind_param($stmtSuc, "iisss", $entidad_id, $empresa_id, $domicilio, $telefono, $email);
        mysqli_stmt_execute($stmtSuc);
        mysqli_stmt_close($stmtSuc);

        // 2. Asegurar rol EMPLEADOR (rol_entidad_id = 5) en gestion__entidades_roles
        $stmtRol = mysqli_prepare($conexion, "INSERT IGNORE INTO gestion__entidades_roles (entidad_id, rol_entidad_id, f_alta, tabla_estado_registro_id) VALUES (?, 5, NOW(), 1)");
        mysqli_stmt_bind_param($stmtRol, "i", $entidad_id);
        mysqli_stmt_execute($stmtRol);
        mysqli_stmt_close($stmtRol);

        // 3. Crear o actualizar en sindi__empleadores
        if ($empleador_id > 0) {
            $sqlEmp = "UPDATE sindi__empleadores SET rubro = ?, convenio_id = ?, f_alta_padron = ?, usuario_actualizacion_id = ?, fecha_actualizacion = NOW()
                       WHERE empleador_id = ? AND empresa_id = ?";
            $stmtEmp = mysqli_prepare($conexion, $sqlEmp);
            mysqli_stmt_bind_param($stmtEmp, "sisiii", $rubro, $convenio_id, $f_alta_padron, $usuario_id, $empleador_id, $empresa_id);
            mysqli_stmt_execute($stmtEmp);
            mysqli_stmt_close($stmtEmp);
        } else {
            $sqlEmp = "INSERT INTO sindi__empleadores (empresa_id, entidad_id, rubro, convenio_id, f_alta_padron, origen, tabla_estado_registro_id, usuario_creacion_id, fecha_creacion)
                       VALUES (?, ?, ?, ?, ?, 'SINDICATO', 1, ?, NOW())
                       ON DUPLICATE KEY UPDATE rubro = VALUES(rubro), convenio_id = VALUES(convenio_id)";
            $stmtEmp = mysqli_prepare($conexion, $sqlEmp);
            mysqli_stmt_bind_param($stmtEmp, "iisisis", $empresa_id, $entidad_id, $rubro, $convenio_id, $f_alta_padron, $usuario_id, $usuario_id);
            // Corrige bind param types
            mysqli_stmt_close($stmtEmp);

            $stmtEmp = mysqli_prepare($conexion, "INSERT INTO sindi__empleadores (empresa_id, entidad_id, rubro, convenio_id, f_alta_padron, origen, tabla_estado_registro_id, usuario_creacion_id, fecha_creacion) VALUES (?, ?, ?, ?, ?, 'SINDICATO', 1, ?, NOW()) ON DUPLICATE KEY UPDATE rubro = VALUES(rubro), convenio_id = VALUES(convenio_id)");
            mysqli_stmt_bind_param($stmtEmp, "iisisi", $empresa_id, $entidad_id, $rubro, $convenio_id, $f_alta_padron, $usuario_id);
            mysqli_stmt_execute($stmtEmp);
            $empleador_id = mysqli_insert_id($conexion) ?: $empleador_id;
            mysqli_stmt_close($stmtEmp);
        }

        mysqli_commit($conexion);
        AccesosEmpleadoresHelper::limpiarCache();
        return ['success' => true, 'message' => 'Empleador guardado con éxito.'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
    }
}
