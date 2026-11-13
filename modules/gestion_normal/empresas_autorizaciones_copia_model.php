<?php
/**
 * Modelo: ABM de Autorizaciones de Acceso a Información Básica Interempresa
 * Módulo: Gestión (mod 2) / Gestión Normal (mod 5)
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
}

/**
 * Obtiene las empresas disponibles para autorizar (excluyendo la empresa actual)
 */
function autCopiaObtenerEmpresasDisponibles($conexion, $empresa_actual_id) {
    $empresa_actual_id = intval($empresa_actual_id);
    $sql = "SELECT empresa_id, empresa, documento_numero as cuit, domicilio, email
            FROM conf__empresas 
            WHERE empresa_id != ? AND tabla_estado_registro_id = 1
            ORDER BY empresa ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $empresa_actual_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $empresas = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $empresas[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $empresas;
}

/**
 * Obtiene las autorizaciones otorgadas por la empresa origen
 */
function autCopiaObtenerOtorgadas($conexion, $empresa_origen_id) {
    $empresa_origen_id = intval($empresa_origen_id);
    $sql = "SELECT a.*, 
                   e.empresa as empresa_destino_nombre, 
                   e.documento_numero as empresa_destino_cuit,
                   e.email as empresa_destino_email,
                   (CASE 
                       WHEN a.tabla_estado_registro_id != 1 THEN 'Revocada / Inactiva'
                       WHEN a.fecha_hasta IS NOT NULL AND a.fecha_hasta < CURDATE() THEN 'Vencida'
                       WHEN a.fecha_desde > CURDATE() THEN 'Pendiente'
                       ELSE 'Activa'
                    END) as vigencia_estado
            FROM gestion__empresas_autorizaciones_copia a
            JOIN conf__empresas e ON a.empresa_destino_id = e.empresa_id
            WHERE a.empresa_origen_id = ?
            ORDER BY a.autorizacion_id DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $empresa_origen_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $items = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $items[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $items;
}

/**
 * Obtiene las autorizaciones recibidas por la empresa destino
 */
function autCopiaObtenerRecibidas($conexion, $empresa_destino_id) {
    $empresa_destino_id = intval($empresa_destino_id);
    $sql = "SELECT a.*, 
                   e.empresa as empresa_origen_nombre, 
                   e.documento_numero as empresa_origen_cuit,
                   e.email as empresa_origen_email,
                   (CASE 
                       WHEN a.tabla_estado_registro_id != 1 THEN 'Revocada / Inactiva'
                       WHEN a.fecha_hasta IS NOT NULL AND a.fecha_hasta < CURDATE() THEN 'Vencida'
                       WHEN a.fecha_desde > CURDATE() THEN 'Pendiente'
                       ELSE 'Activa'
                    END) as vigencia_estado
            FROM gestion__empresas_autorizaciones_copia a
            JOIN conf__empresas e ON a.empresa_origen_id = e.empresa_id
            WHERE a.empresa_destino_id = ?
            ORDER BY a.autorizacion_id DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $empresa_destino_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $items = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $items[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $items;
}

/**
 * Obtiene una autorización por su ID
 */
function autCopiaObtenerPorId($conexion, $autorizacion_id, $empresa_id) {
    $autorizacion_id = intval($autorizacion_id);
    $empresa_id = intval($empresa_id);

    $sql = "SELECT a.*, 
                   eo.empresa as empresa_origen_nombre,
                   ed.empresa as empresa_destino_nombre
            FROM gestion__empresas_autorizaciones_copia a
            JOIN conf__empresas eo ON a.empresa_origen_id = eo.empresa_id
            JOIN conf__empresas ed ON a.empresa_destino_id = ed.empresa_id
            WHERE a.autorizacion_id = ? AND (a.empresa_origen_id = ? OR a.empresa_destino_id = ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $autorizacion_id, $empresa_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

/**
 * Guarda o actualiza una autorización
 */
function autCopiaGuardar($conexion, $data, $empresa_origen_id, $usuario_id) {
    $autorizacion_id = intval($data['autorizacion_id'] ?? 0);
    $empresa_origen_id = intval($empresa_origen_id);
    $empresa_destino_id = intval($data['empresa_destino_id'] ?? 0);

    if ($empresa_origen_id <= 0) {
        return ['success' => false, 'error' => 'Empresa origen no válida'];
    }
    if ($empresa_destino_id <= 0 || $empresa_destino_id === $empresa_origen_id) {
        return ['success' => false, 'error' => 'Debe seleccionar una empresa destino distinta a la empresa actual'];
    }

    $permite_plan_cuentas = !empty($data['permite_plan_cuentas']) ? 1 : 0;
    $permite_conceptos_contables = !empty($data['permite_conceptos_contables']) ? 1 : 0;
    $permite_roles_cuentas = !empty($data['permite_roles_cuentas']) ? 1 : 0;
    $permite_comprobantes_tipos = !empty($data['permite_comprobantes_tipos']) ? 1 : 0;
    $permite_condiciones_pago = !empty($data['permite_condiciones_pago']) ? 1 : 0;
    $permite_sucursales_bocas = !empty($data['permite_sucursales_bocas']) ? 1 : 0;
    $permite_listas_precios = !empty($data['permite_listas_precios']) ? 1 : 0;
    $permite_parametros = !empty($data['permite_parametros']) ? 1 : 0;
    $permite_datos_basicos = !empty($data['permite_datos_basicos']) ? 1 : 0;
    $permite_tesoreria = !empty($data['permite_tesoreria']) ? 1 : 0;
    $permite_impuestos_config = !empty($data['permite_impuestos_config']) ? 1 : 0;
    $permite_productos = !empty($data['permite_productos']) ? 1 : 0;

    $total_permisos = $permite_plan_cuentas + $permite_conceptos_contables + $permite_roles_cuentas +
                      $permite_comprobantes_tipos + $permite_condiciones_pago + $permite_sucursales_bocas +
                      $permite_listas_precios + $permite_parametros + $permite_datos_basicos + $permite_tesoreria +
                      $permite_impuestos_config + $permite_productos;

    if ($total_permisos === 0) {
        return ['success' => false, 'error' => 'Debe seleccionar al menos un componente de información para autorizar'];
    }

    $fecha_desde = !empty($data['fecha_desde']) ? trim($data['fecha_desde']) : date('Y-m-d');
    $fecha_hasta = !empty($data['fecha_hasta']) ? trim($data['fecha_hasta']) : null;

    if ($fecha_hasta && $fecha_hasta < $fecha_desde) {
        return ['success' => false, 'error' => 'La fecha límite no puede ser anterior a la fecha de inicio'];
    }

    $motivo = trim($data['motivo'] ?? '');
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id'] ?? 1);
    if (!in_array($tabla_estado_registro_id, [1, 2])) {
        $tabla_estado_registro_id = 1;
    }

    if ($autorizacion_id > 0) {
        // Verificar pertenencia a la empresa origen
        $chk = mysqli_prepare($conexion, "SELECT autorizacion_id FROM gestion__empresas_autorizaciones_copia WHERE autorizacion_id = ? AND empresa_origen_id = ?");
        mysqli_stmt_bind_param($chk, "ii", $autorizacion_id, $empresa_origen_id);
        mysqli_stmt_execute($chk);
        $res = mysqli_stmt_get_result($chk);
        if (!mysqli_fetch_assoc($res)) {
            mysqli_stmt_close($chk);
            return ['success' => false, 'error' => 'No tiene permiso para modificar esta autorización'];
        }
        mysqli_stmt_close($chk);

        $sql = "UPDATE gestion__empresas_autorizaciones_copia SET
                    empresa_destino_id = ?,
                    permite_plan_cuentas = ?,
                    permite_conceptos_contables = ?,
                    permite_roles_cuentas = ?,
                    permite_comprobantes_tipos = ?,
                    permite_condiciones_pago = ?,
                    permite_sucursales_bocas = ?,
                    permite_listas_precios = ?,
                    permite_parametros = ?,
                    permite_datos_basicos = ?,
                    permite_tesoreria = ?,
                    permite_impuestos_config = ?,
                    permite_productos = ?,
                    fecha_desde = ?,
                    fecha_hasta = ?,
                    motivo = ?,
                    tabla_estado_registro_id = ?,
                    usuario_modificacion_id = ?,
                    fecha_modificacion = NOW()
                WHERE autorizacion_id = ? AND empresa_origen_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "iiiiiiiiiiiiisssiiii",
            $empresa_destino_id,
            $permite_plan_cuentas,
            $permite_conceptos_contables,
            $permite_roles_cuentas,
            $permite_comprobantes_tipos,
            $permite_condiciones_pago,
            $permite_sucursales_bocas,
            $permite_listas_precios,
            $permite_parametros,
            $permite_datos_basicos,
            $permite_tesoreria,
            $permite_impuestos_config,
            $permite_productos,
            $fecha_desde,
            $fecha_hasta,
            $motivo,
            $tabla_estado_registro_id,
            $usuario_id,
            $autorizacion_id,
            $empresa_origen_id
        );
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($ok) {
            return ['success' => true, 'message' => 'Autorización actualizada correctamente', 'autorizacion_id' => $autorizacion_id];
        } else {
            return ['success' => false, 'error' => 'Error al actualizar autorización: ' . mysqli_error($conexion)];
        }
    } else {
        // Verificar si ya existe autorización para este par origen-destino
        $sql = "INSERT INTO gestion__empresas_autorizaciones_copia (
                    empresa_origen_id, empresa_destino_id, permite_plan_cuentas,
                    permite_conceptos_contables, permite_roles_cuentas, permite_comprobantes_tipos,
                    permite_condiciones_pago, permite_sucursales_bocas, permite_listas_precios,
                    permite_parametros, permite_datos_basicos, permite_tesoreria, permite_impuestos_config, permite_productos, fecha_desde, fecha_hasta, motivo,
                    tabla_estado_registro_id, usuario_creacion_id, fecha_creacion
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    permite_plan_cuentas = VALUES(permite_plan_cuentas),
                    permite_conceptos_contables = VALUES(permite_conceptos_contables),
                    permite_roles_cuentas = VALUES(permite_roles_cuentas),
                    permite_comprobantes_tipos = VALUES(permite_comprobantes_tipos),
                    permite_condiciones_pago = VALUES(permite_condiciones_pago),
                    permite_sucursales_bocas = VALUES(permite_sucursales_bocas),
                    permite_listas_precios = VALUES(permite_listas_precios),
                    permite_parametros = VALUES(permite_parametros),
                    permite_datos_basicos = VALUES(permite_datos_basicos),
                    permite_tesoreria = VALUES(permite_tesoreria),
                    permite_impuestos_config = VALUES(permite_impuestos_config),
                    permite_productos = VALUES(permite_productos),
                    fecha_desde = VALUES(fecha_desde),
                    fecha_hasta = VALUES(fecha_hasta),
                    motivo = VALUES(motivo),
                    tabla_estado_registro_id = 1,
                    usuario_modificacion_id = ?,
                    fecha_modificacion = NOW()";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "iiiiiiiiiiiiiisssiii",
            $empresa_origen_id,
            $empresa_destino_id,
            $permite_plan_cuentas,
            $permite_conceptos_contables,
            $permite_roles_cuentas,
            $permite_comprobantes_tipos,
            $permite_condiciones_pago,
            $permite_sucursales_bocas,
            $permite_listas_precios,
            $permite_parametros,
            $permite_datos_basicos,
            $permite_tesoreria,
            $permite_impuestos_config,
            $permite_productos,
            $fecha_desde,
            $fecha_hasta,
            $motivo,
            $tabla_estado_registro_id,
            $usuario_id,
            $usuario_id
        );
        $ok = mysqli_stmt_execute($stmt);
        $new_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        if ($ok) {
            return ['success' => true, 'message' => 'Autorización otorgada con éxito', 'autorizacion_id' => $new_id];
        } else {
            return ['success' => false, 'error' => 'Error al otorgar autorización: ' . mysqli_error($conexion)];
        }
    }
}

/**
 * Cambia el estado de una autorización (activar o revocar)
 */
function autCopiaCambiarEstado($conexion, $autorizacion_id, $empresa_origen_id, $nuevo_estado_id, $usuario_id) {
    $autorizacion_id = intval($autorizacion_id);
    $empresa_origen_id = intval($empresa_origen_id);
    $nuevo_estado_id = intval($nuevo_estado_id) === 1 ? 1 : 2;

    $sql = "UPDATE gestion__empresas_autorizaciones_copia 
            SET tabla_estado_registro_id = ?, usuario_modificacion_id = ?, fecha_modificacion = NOW()
            WHERE autorizacion_id = ? AND empresa_origen_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iiii", $nuevo_estado_id, $usuario_id, $autorizacion_id, $empresa_origen_id);
    $ok = mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($ok && $affected >= 0) {
        $estadoTxt = ($nuevo_estado_id === 1) ? 'activada' : 'revocada';
        return ['success' => true, 'message' => "La autorización fue $estadoTxt exitosamente"];
    } else {
        return ['success' => false, 'error' => 'No se pudo modificar el estado de la autorización'];
    }
}

/**
 * Elimina una autorización
 */
function autCopiaEliminar($conexion, $autorizacion_id, $empresa_origen_id) {
    $autorizacion_id = intval($autorizacion_id);
    $empresa_origen_id = intval($empresa_origen_id);

    $sql = "DELETE FROM gestion__empresas_autorizaciones_copia WHERE autorizacion_id = ? AND empresa_origen_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $autorizacion_id, $empresa_origen_id);
    $ok = mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($ok && $affected > 0) {
        return ['success' => true, 'message' => 'Autorización eliminada correctamente'];
    } else {
        return ['success' => false, 'error' => 'No se pudo eliminar la autorización o no pertenece a la empresa actual'];
    }
}

/**
 * Obtiene el log de copias realizadas
 */
function autCopiaObtenerLog($conexion, $empresa_id) {
    $empresa_id = intval($empresa_id);
    $sql = "SELECT l.*, 
                   eo.empresa as empresa_origen_nombre,
                   ed.empresa as empresa_destino_nombre
            FROM gestion__empresas_copia_log l
            JOIN conf__empresas eo ON l.empresa_origen_id = eo.empresa_id
            JOIN conf__empresas ed ON l.empresa_destino_id = ed.empresa_id
            WHERE l.empresa_origen_id = ? OR l.empresa_destino_id = ?
            ORDER BY l.copia_log_id DESC
            LIMIT 50";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $items = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $items[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $items;
}
