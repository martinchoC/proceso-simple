<?php
/**
 * Modelo de Datos para Configuración del Grupo y RACI (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/configuracion_model.php
 */

declare(strict_types=1);

/**
 * Listar las 10 opciones de configuración con el valor activo para un grupo corporativo.
 */
function configuracion_grupo_listar(mysqli $conexion, int $grupo_id): array
{
    $sql = "SELECT o.opcion_id, o.codigo AS opcion_codigo, o.nombre AS opcion_nombre, o.nivel,
                   cg.configuracion_grupo_id, cg.vigente_desde,
                   ov.opcion_valor_id AS valor_actual_id, ov.codigo AS valor_actual_codigo, ov.nombre AS valor_actual_nombre
            FROM ml__opciones o
            LEFT JOIN ml__configuracion_grupo cg 
                ON cg.opcion_id = o.opcion_id 
                AND cg.grupo_id = ? 
                AND (cg.vigente_hasta IS NULL OR cg.vigente_hasta >= CURDATE())
            LEFT JOIN ml__opciones_valores ov ON ov.opcion_valor_id = cg.opcion_valor_id
            ORDER BY o.opcion_id ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $grupo_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $opciones = [];
    while ($row = mysqli_fetch_assoc($res)) {
        // Obtener los valores posibles para esta opción
        $opcion_id = (int)$row['opcion_id'];
        $sql_vals = "SELECT opcion_valor_id, codigo, nombre, es_defecto 
                     FROM ml__opciones_valores 
                     WHERE opcion_id = ? 
                     ORDER BY opcion_valor_id ASC";
        $stmt_v = mysqli_prepare($conexion, $sql_vals);
        mysqli_stmt_bind_param($stmt_v, "i", $opcion_id);
        mysqli_stmt_execute($stmt_v);
        $res_v = mysqli_stmt_get_result($stmt_v);
        $valores = [];
        while ($val = mysqli_fetch_assoc($res_v)) {
            $valores[] = $val;
        }
        mysqli_stmt_close($stmt_v);

        $row['valores_disponibles'] = $valores;
        $opciones[] = $row;
    }
    mysqli_stmt_close($stmt);

    return $opciones;
}

/**
 * Actualizar una opción de configuración del grupo cerrando la anterior y abriendo la nueva (C32).
 */
function configuracion_grupo_actualizar(mysqli $conexion, int $grupo_id, int $opcion_id, int $nuevo_valor_id, int $usuario_id): array
{
    if ($grupo_id <= 0 || $opcion_id <= 0 || $nuevo_valor_id <= 0) {
        return ['success' => false, 'message' => 'Parámetros inválidos para actualizar la opción.'];
    }

    $hoy = date('Y-m-d');

    mysqli_begin_transaction($conexion);
    try {
        // 1. Cerrar configuración abierta actual
        $sql_c = "UPDATE ml__configuracion_grupo 
                  SET vigente_hasta = ?, actualizado_en = NOW(), actualizado_por = ?
                  WHERE grupo_id = ? AND opcion_id = ? AND vigente_hasta IS NULL";
        $stmt_c = mysqli_prepare($conexion, $sql_c);
        mysqli_stmt_bind_param($stmt_c, "siii", $hoy, $usuario_id, $grupo_id, $opcion_id);
        mysqli_stmt_execute($stmt_c);
        mysqli_stmt_close($stmt_c);

        // 2. Insertar nuevo valor activo
        $sql_n = "INSERT INTO ml__configuracion_grupo (grupo_id, opcion_id, opcion_valor_id, vigente_desde, creado_en, creado_por)
                  VALUES (?, ?, ?, ?, NOW(), ?)";
        $stmt_n = mysqli_prepare($conexion, $sql_n);
        mysqli_stmt_bind_param($stmt_n, "iiisi", $grupo_id, $opcion_id, $nuevo_valor_id, $hoy, $usuario_id);
        mysqli_stmt_execute($stmt_n);
        mysqli_stmt_close($stmt_n);

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Opción corporativa actualizada correctamente con nueva vigencia.'];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error al guardar configuración: ' . $e->getMessage()];
    }
}

/**
 * Listar roles RACI de gobierno normativo.
 */
function raci_roles_listar(mysqli $conexion): array
{
    $sql = "SELECT raci_rol_id, codigo, nombre FROM ml__raci_roles ORDER BY raci_rol_id ASC";
    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}
