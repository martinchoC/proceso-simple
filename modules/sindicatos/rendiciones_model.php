<?php
declare(strict_types=1);

/**
 * modules/sindicatos/rendiciones_model.php
 * Capa de datos para la Página 1031 (Rendiciones y Cobranzas).
 */

require_once __DIR__ . '/sindi_cobros_core_model.php';
require_once __DIR__ . '/sindi_pagos_drivers.php';

function sindi_listar_rendiciones(mysqli $conexion, int $empresa_id, int $pagina_id): array
{
    $sql = "SELECT r.*, mp.nombre AS medio_nombre, mp.driver,
                   er.tabla_estado_registro AS estado_nombre, er.color_id AS estado_color_id
            FROM sindi__rendiciones r
            INNER JOIN sindi__medios_pago mp ON mp.medio_pago_id = r.medio_pago_id
            LEFT JOIN conf__tablas_estados_registros er ON er.tabla_id = 532 AND er.estado_registro_id = r.tabla_estado_registro_id
            WHERE r.empresa_id = ?
            ORDER BY r.rendicion_id DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $estadoId = (int)$row['tabla_estado_registro_id'];
        $id = (int)$row['rendicion_id'];
        $row['acciones'] = sindi_obtener_botones_acciones($conexion, $pagina_id, $estadoId, $id);
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $lista;
}

function sindi_obtener_detalle_rendicion(mysqli $conexion, int $empresa_id, int $rendicion_id): ?array
{
    $sqlR = "SELECT r.*, mp.nombre AS medio_nombre, mp.driver,
                    er.tabla_estado_registro AS estado_nombre, er.color_id AS estado_color_id
             FROM sindi__rendiciones r
             INNER JOIN sindi__medios_pago mp ON mp.medio_pago_id = r.medio_pago_id
             LEFT JOIN conf__tablas_estados_registros er ON er.tabla_id = 532 AND er.estado_registro_id = r.tabla_estado_registro_id
             WHERE r.rendicion_id = ? AND r.empresa_id = ? LIMIT 1";
    $stmtR = mysqli_prepare($conexion, $sqlR);
    if (!$stmtR) return null;
    mysqli_stmt_bind_param($stmtR, "ii", $rendicion_id, $empresa_id);
    mysqli_stmt_execute($stmtR);
    $cab = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtR));
    mysqli_stmt_close($stmtR);
    if (!$cab) return null;

    $sqlL = "SELECT rl.*, v.numero AS volante_numero, ent.entidad_nombre AS empleador_nombre
             FROM sindi__rendiciones_lineas rl
             LEFT JOIN sindi__volantes v ON v.volante_id = rl.volante_id
             LEFT JOIN sindi__empleadores emp ON emp.empleador_id = v.empleador_id
             LEFT JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
             WHERE rl.rendicion_id = ? AND rl.empresa_id = ?
             ORDER BY rl.numero_linea ASC";
    $stmtL = mysqli_prepare($conexion, $sqlL);
    mysqli_stmt_bind_param($stmtL, "ii", $rendicion_id, $empresa_id);
    mysqli_stmt_execute($stmtL);
    $resL = mysqli_stmt_get_result($stmtL);
    $lineas = [];
    while ($r = mysqli_fetch_assoc($resL)) {
        $lineas[] = $r;
    }
    mysqli_stmt_close($stmtL);
    $cab['lineas'] = $lineas;

    return $cab;
}
