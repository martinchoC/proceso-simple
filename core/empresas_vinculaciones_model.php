<?php
declare(strict_types=1);

/**
 * core/empresas_vinculaciones_model.php
 * Modelo transversal para la gestión y verificación de autorizaciones
 * cross-tenant entre empresas clientes, estudios contables y otras entidades.
 */

require_once __DIR__ . '/../db.php';

/**
 * Retorna las estadísticas y KPIs de vinculaciones para la empresa activa.
 */
function vinculacionesObtenerKPIs(mysqli $conn, int $empresa_id): array
{
    $kpis = [
        'otorgadas_activas' => 0,
        'estudios_contables' => 0,
        'modo_escritura' => 0,
        'recibidas_activas' => 0,
    ];

    // Otorgadas
    $sql1 = "SELECT 
                COUNT(*) AS total_activas,
                SUM(IF(tipo_vinculo = 'ESTUDIO_CONTABLE', 1, 0)) AS total_estudios,
                SUM(IF(modo_acceso = 'LECTURA_ESCRITURA', 1, 0)) AS total_escritura
             FROM conf__empresas_vinculaciones
             WHERE empresa_origen_id = ? AND tabla_estado_registro_id = 1";
    $stmt1 = mysqli_prepare($conn, $sql1);
    if ($stmt1) {
        mysqli_stmt_bind_param($stmt1, "i", $empresa_id);
        mysqli_stmt_execute($stmt1);
        $res1 = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt1));
        if ($res1) {
            $kpis['otorgadas_activas'] = intval($res1['total_activas'] ?? 0);
            $kpis['estudios_contables'] = intval($res1['total_estudios'] ?? 0);
            $kpis['modo_escritura'] = intval($res1['total_escritura'] ?? 0);
        }
        mysqli_stmt_close($stmt1);
    }

    // Recibidas
    $sql2 = "SELECT COUNT(*) AS total_recibidas
             FROM conf__empresas_vinculaciones
             WHERE empresa_destino_id = ? AND tabla_estado_registro_id = 1";
    $stmt2 = mysqli_prepare($conn, $sql2);
    if ($stmt2) {
        mysqli_stmt_bind_param($stmt2, "i", $empresa_id);
        mysqli_stmt_execute($stmt2);
        $res2 = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt2));
        if ($res2) {
            $kpis['recibidas_activas'] = intval($res2['total_recibidas'] ?? 0);
        }
        mysqli_stmt_close($stmt2);
    }

    return $kpis;
}

/**
 * Lista las empresas registradas disponibles para vincular (excluyendo la propia).
 */
function vinculacionesObtenerEmpresasDisponibles(mysqli $conn, int $empresa_actual_id): array
{
    $sql = "SELECT empresa_id, empresa, documento_numero, email, telefono
            FROM conf__empresas
            WHERE empresa_id != ? AND tabla_estado_registro_id = 1
            ORDER BY empresa ASC";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $empresa_actual_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $lista;
}

/**
 * Lista las vinculaciones otorgadas por la empresa actual.
 */
function vinculacionesListarOtorgadas(mysqli $conn, int $empresa_origen_id): array
{
    $sql = "SELECT v.*, e.empresa AS empresa_destino_nombre, e.documento_numero AS empresa_destino_cuit,
                   e.email AS empresa_destino_email, e.telefono AS empresa_destino_telefono
            FROM conf__empresas_vinculaciones v
            INNER JOIN conf__empresas e ON e.empresa_id = v.empresa_destino_id
            WHERE v.empresa_origen_id = ?
            ORDER BY v.tabla_estado_registro_id ASC, e.empresa ASC";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $empresa_origen_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $lista;
}

/**
 * Lista las vinculaciones recibidas por la empresa actual (donde actúa como estudio u operador externo).
 */
function vinculacionesListarRecibidas(mysqli $conn, int $empresa_destino_id): array
{
    $sql = "SELECT v.*, e.empresa AS empresa_origen_nombre, e.documento_numero AS empresa_origen_cuit,
                   e.email AS empresa_origen_email, e.telefono AS empresa_origen_telefono
            FROM conf__empresas_vinculaciones v
            INNER JOIN conf__empresas e ON e.empresa_id = v.empresa_origen_id
            WHERE v.empresa_destino_id = ?
            ORDER BY v.tabla_estado_registro_id ASC, e.empresa ASC";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $empresa_destino_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $lista;
}

/**
 * Obtiene una vinculación por ID asegurando que pertenezca a la empresa actual (origen o destino).
 */
function vinculacionObtenerPorId(mysqli $conn, int $vinculacion_id, int $empresa_id): ?array
{
    $sql = "SELECT v.*, 
                   eo.empresa AS empresa_origen_nombre, eo.documento_numero AS empresa_origen_cuit,
                   ed.empresa AS empresa_destino_nombre, ed.documento_numero AS empresa_destino_cuit
            FROM conf__empresas_vinculaciones v
            INNER JOIN conf__empresas eo ON eo.empresa_id = v.empresa_origen_id
            INNER JOIN conf__empresas ed ON ed.empresa_id = v.empresa_destino_id
            WHERE v.vinculacion_id = ? AND (v.empresa_origen_id = ? OR v.empresa_destino_id = ?)
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "iii", $vinculacion_id, $empresa_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

/**
 * Guarda o actualiza una vinculación.
 */
function vinculacionesGuardar(mysqli $conn, array $data, int $empresa_origen_id, int $usuario_id): array
{
    $vinculacion_id = intval($data['vinculacion_id'] ?? 0);
    $empresa_destino_id = intval($data['empresa_destino_id'] ?? 0);
    $tipo_vinculo = trim((string)($data['tipo_vinculo'] ?? 'ESTUDIO_CONTABLE'));
    $modo_acceso = trim((string)($data['modo_acceso'] ?? 'LECTURA'));

    $permiso_ventas = !empty($data['permiso_ventas']) ? 1 : 0;
    $permiso_compras = !empty($data['permiso_compras']) ? 1 : 0;
    $permiso_tesoreria = !empty($data['permiso_tesoreria']) ? 1 : 0;
    $permiso_contabilidad = !empty($data['permiso_contabilidad']) ? 1 : 0;
    $permiso_stock = !empty($data['permiso_stock']) ? 1 : 0;
    $permiso_sindicatos = !empty($data['permiso_sindicatos']) ? 1 : 0;
    $permiso_impuestos = !empty($data['permiso_impuestos']) ? 1 : 0;

    $fecha_desde = !empty($data['fecha_desde']) ? trim((string)$data['fecha_desde']) : date('Y-m-d');
    $fecha_hasta = !empty($data['fecha_hasta']) ? trim((string)$data['fecha_hasta']) : null;
    $observaciones = trim((string)($data['observaciones'] ?? ''));
    $tabla_estado_registro_id = intval($data['tabla_estado_registro_id'] ?? 1);

    // Validaciones
    if ($empresa_destino_id <= 0) {
        return ['success' => false, 'error' => 'Debe seleccionar una empresa o estudio contable de destino'];
    }
    if ($empresa_destino_id === $empresa_origen_id) {
        return ['success' => false, 'error' => 'La empresa destino no puede ser la misma empresa de origen'];
    }
    if (!in_array($tipo_vinculo, ['ESTUDIO_CONTABLE', 'EMPRESA_VINCULADA', 'AUDITORIA', 'SINDICATO', 'OTRO'], true)) {
        $tipo_vinculo = 'ESTUDIO_CONTABLE';
    }
    if (!in_array($modo_acceso, ['LECTURA', 'LECTURA_ESCRITURA'], true)) {
        $modo_acceso = 'LECTURA';
    }

    if ($vinculacion_id > 0) {
        // Modificación
        $sql = "UPDATE conf__empresas_vinculaciones SET
                    empresa_destino_id = ?,
                    tipo_vinculo = ?,
                    modo_acceso = ?,
                    permiso_ventas = ?,
                    permiso_compras = ?,
                    permiso_tesoreria = ?,
                    permiso_contabilidad = ?,
                    permiso_stock = ?,
                    permiso_sindicatos = ?,
                    permiso_impuestos = ?,
                    fecha_desde = ?,
                    fecha_hasta = ?,
                    observaciones = ?,
                    tabla_estado_registro_id = ?,
                    usuario_modificacion_id = ?,
                    fecha_modificacion = NOW()
                WHERE vinculacion_id = ? AND empresa_origen_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return ['success' => false, 'error' => 'Error al preparar actualización: ' . mysqli_error($conn)];
        }
        mysqli_stmt_bind_param(
            $stmt,
            "issiiiiiiisssiiii",
            $empresa_destino_id,
            $tipo_vinculo,
            $modo_acceso,
            $permiso_ventas,
            $permiso_compras,
            $permiso_tesoreria,
            $permiso_contabilidad,
            $permiso_stock,
            $permiso_sindicatos,
            $permiso_impuestos,
            $fecha_desde,
            $fecha_hasta,
            $observaciones,
            $tabla_estado_registro_id,
            $usuario_id,
            $vinculacion_id,
            $empresa_origen_id
        );
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if ($ok) {
            return ['success' => true, 'mensaje' => 'Vinculación actualizada correctamente', 'vinculacion_id' => $vinculacion_id];
        } else {
            return ['success' => false, 'error' => 'Error al actualizar vinculación: ' . mysqli_error($conn)];
        }
    } else {
        // Alta directa (Vinculación simple)
        // Verificar si ya existe relación previa entre ambas empresas
        $chkSql = "SELECT vinculacion_id FROM conf__empresas_vinculaciones WHERE empresa_origen_id = ? AND empresa_destino_id = ? LIMIT 1";
        $chkStmt = mysqli_prepare($conn, $chkSql);
        if ($chkStmt) {
            mysqli_stmt_bind_param($chkStmt, "ii", $empresa_origen_id, $empresa_destino_id);
            mysqli_stmt_execute($chkStmt);
            $chkRow = mysqli_fetch_assoc(mysqli_stmt_get_result($chkStmt));
            mysqli_stmt_close($chkStmt);
            if ($chkRow) {
                return ['success' => false, 'error' => 'Ya existe una vinculación registrada con esta empresa. Puede editar sus permisos.'];
            }
        }

        $sql = "INSERT INTO conf__empresas_vinculaciones (
                    empresa_origen_id, empresa_destino_id, tipo_vinculo, modo_acceso,
                    permiso_ventas, permiso_compras, permiso_tesoreria, permiso_contabilidad,
                    permiso_stock, permiso_sindicatos, permiso_impuestos,
                    fecha_desde, fecha_hasta, observaciones,
                    tabla_estado_registro_id, usuario_creacion_id, fecha_creacion
                ) VALUES (
                    ?, ?, ?, ?,
                    ?, ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, ?,
                    ?, ?, NOW()
                )";
        $stmt = mysqli_prepare($conn, $sql);
        if (!$stmt) {
            return ['success' => false, 'error' => 'Error al preparar alta: ' . mysqli_error($conn)];
        }
        mysqli_stmt_bind_param(
            $stmt,
            "iissiiiiiiisssii",
            $empresa_origen_id,
            $empresa_destino_id,
            $tipo_vinculo,
            $modo_acceso,
            $permiso_ventas,
            $permiso_compras,
            $permiso_tesoreria,
            $permiso_contabilidad,
            $permiso_stock,
            $permiso_sindicatos,
            $permiso_impuestos,
            $fecha_desde,
            $fecha_hasta,
            $observaciones,
            $tabla_estado_registro_id,
            $usuario_id
        );
        $ok = mysqli_stmt_execute($stmt);
        $newId = mysqli_insert_id($conn);
        mysqli_stmt_close($stmt);

        if ($ok) {
            return ['success' => true, 'mensaje' => 'Vinculación otorgada exitosamente', 'vinculacion_id' => $newId];
        } else {
            return ['success' => false, 'error' => 'Error al registrar vinculación: ' . mysqli_error($conn)];
        }
    }
}

/**
 * Cambia el estado de una vinculación (1 = Activa, 2 = Inactiva/Revocada).
 */
function vinculacionesCambiarEstado(mysqli $conn, int $vinculacion_id, int $nuevo_estado, int $empresa_origen_id, int $usuario_id): array
{
    if ($vinculacion_id <= 0) {
        return ['success' => false, 'error' => 'ID de vinculación inválido'];
    }

    $sql = "UPDATE conf__empresas_vinculaciones SET
                tabla_estado_registro_id = ?,
                usuario_modificacion_id = ?,
                fecha_modificacion = NOW()
            WHERE vinculacion_id = ? AND empresa_origen_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) {
        return ['success' => false, 'error' => 'Error al preparar cambio de estado: ' . mysqli_error($conn)];
    }
    mysqli_stmt_bind_param($stmt, "iiii", $nuevo_estado, $usuario_id, $vinculacion_id, $empresa_origen_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        $msg = ($nuevo_estado === 1) ? 'Vinculación activada exitosamente' : 'Vinculación revocada/pausada exitosamente';
        return ['success' => true, 'mensaje' => $msg];
    } else {
        return ['success' => false, 'error' => 'Error al actualizar estado: ' . mysqli_error($conn)];
    }
}

/**
 * Función central de autorización en tiempo de ejecución:
 * Verifica si una empresa externa ($empresa_destino_id) tiene permiso concedido por la empresa cliente ($empresa_origen_id)
 * para acceder a un área determinada y si cumple con el nivel de escritura solicitado.
 *
 * @param string $area: 'ventas', 'compras', 'tesoreria', 'contabilidad', 'stock', 'sindicatos', 'impuestos'
 * @param bool $requiere_escritura: Si true, verifica que modo_acceso sea 'LECTURA_ESCRITURA'
 */
function vinculacionesVerificarPermiso(mysqli $conn, int $empresa_origen_id, int $empresa_destino_id, string $area, bool $requiere_escritura = false): bool
{
    // Si es la misma empresa, acceso total
    if ($empresa_origen_id === $empresa_destino_id) {
        return true;
    }

    $columnaPermiso = match (strtolower($area)) {
        'ventas' => 'permiso_ventas',
        'compras' => 'permiso_compras',
        'tesoreria' => 'permiso_tesoreria',
        'contabilidad' => 'permiso_contabilidad',
        'stock' => 'permiso_stock',
        'sindicatos' => 'permiso_sindicatos',
        'impuestos' => 'permiso_impuestos',
        default => ''
    };

    if ($columnaPermiso === '') {
        return false;
    }

    $sql = "SELECT {$columnaPermiso} AS tiene_permiso, modo_acceso
            FROM conf__empresas_vinculaciones
            WHERE empresa_origen_id = ? 
              AND empresa_destino_id = ?
              AND tabla_estado_registro_id = 1
              AND fecha_desde <= CURDATE()
              AND (fecha_hasta IS NULL OR fecha_hasta >= CURDATE())
            LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    if (!$stmt) return false;
    mysqli_stmt_bind_param($stmt, "ii", $empresa_origen_id, $empresa_destino_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$row) return false;
    if (intval($row['tiene_permiso']) !== 1) return false;

    if ($requiere_escritura && $row['modo_acceso'] !== 'LECTURA_ESCRITURA') {
        return false;
    }

    return true;
}
