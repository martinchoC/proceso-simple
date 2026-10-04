<?php
/**
 * Modelo de Datos para Accesos Confidenciales y Auditoría (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/accesos_confidenciales_model.php
 */

declare(strict_types=1);

/**
 * Listar autorizaciones de acceso a documentos confidenciales (Caso C33).
 */
function accesos_confidenciales_listar(mysqli $conexion): array
{
    $sql = "SELECT ac.acceso_confidencial_id, ac.objeto_tabla, ac.objeto_id, ac.empresa_perfil_id,
                   ep.empresa_perfil_nombre,
                   at.codigo AS ambito_tipo_codigo, at.nombre AS ambito_tipo_nombre,
                   ac.vigente_desde, ac.vigente_hasta,
                   CASE ac.objeto_tabla
                       WHEN 'ml__normas' THEN (SELECT CONCAT('[Norma] ', n.codigo, ' - ', n.titulo) FROM ml__normas n WHERE n.norma_id = ac.objeto_id)
                       WHEN 'ml__procedimientos' THEN (SELECT CONCAT('[Procedimiento] ', p.codigo, ' - ', p.nombre) FROM ml__procedimientos p WHERE p.procedimiento_id = ac.objeto_id)
                       ELSE CONCAT(ac.objeto_tabla, ' #', ac.objeto_id)
                   END AS objeto_descripcion,
                   CASE at.codigo
                       WHEN 'GRUPO' THEN g.nombre
                       WHEN 'REGION_GRUPO' THEN rg.nombre
                       WHEN 'EMPRESA' THEN emp.empresa
                       WHEN 'TERRITORIO' THEN t.nombre
                       ELSE 'Sin alcance'
                   END AS alcance_nombre,
                   ac.creado_en
            FROM ml__accesos_confidenciales ac
            INNER JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = ac.empresa_perfil_id
            INNER JOIN ml__ambitos_tipos at ON at.ambito_tipo_id = ac.ambito_tipo_id
            LEFT JOIN ml__grupos g ON g.grupo_id = ac.grupo_id
            LEFT JOIN ml__regiones_grupo rg ON rg.region_grupo_id = ac.region_grupo_id
            LEFT JOIN conf__empresas emp ON emp.empresa_id = ac.empresa_id
            LEFT JOIN ml__territorios t ON t.territorio_id = ac.territorio_id
            ORDER BY ac.vigente_desde DESC, ac.acceso_confidencial_id DESC";

    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}

/**
 * Crear autorización especial de acceso confidencial.
 */
function acceso_confidencial_crear(mysqli $conexion, array $datos, int $usuario_id): array
{
    $objeto_tabla = trim($datos['objeto_tabla'] ?? '');
    $objeto_id = intval($datos['objeto_id'] ?? 0);
    $empresa_perfil_id = intval($datos['empresa_perfil_id'] ?? 0);
    $ambito_tipo_id = intval($datos['ambito_tipo_id'] ?? 0);

    $grupo_id = !empty($datos['grupo_id']) ? intval($datos['grupo_id']) : null;
    $region_grupo_id = !empty($datos['region_grupo_id']) ? intval($datos['region_grupo_id']) : null;
    $empresa_id = !empty($datos['empresa_id']) ? intval($datos['empresa_id']) : null;
    $territorio_id = !empty($datos['territorio_id']) ? intval($datos['territorio_id']) : null;

    $vigente_desde = trim($datos['vigente_desde'] ?? date('Y-m-d'));
    $vigente_hasta = !empty($datos['vigente_hasta']) ? trim($datos['vigente_hasta']) : null;

    if (empty($objeto_tabla) || $objeto_id <= 0 || $empresa_perfil_id <= 0 || $ambito_tipo_id <= 0 || empty($vigente_desde)) {
        return ['success' => false, 'message' => 'Objeto confidencial, perfil autorizado, nivel de alcance y fecha son obligatorios.'];
    }

    $cont = ($grupo_id ? 1 : 0) + ($region_grupo_id ? 1 : 0) + ($empresa_id ? 1 : 0) + ($territorio_id ? 1 : 0);
    if ($cont !== 1) {
        return ['success' => false, 'message' => 'Debe indicar exactamente un destino para el ámbito.'];
    }

    $sql = "INSERT INTO ml__accesos_confidenciales 
            (objeto_tabla, objeto_id, empresa_perfil_id, ambito_tipo_id, grupo_id, region_grupo_id, empresa_id, territorio_id, vigente_desde, vigente_hasta, creado_en, creado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "siiiiiiissi", $objeto_tabla, $objeto_id, $empresa_perfil_id, $ambito_tipo_id, $grupo_id, $region_grupo_id, $empresa_id, $territorio_id, $vigente_desde, $vigente_hasta, $usuario_id);
    $ok = mysqli_stmt_execute($stmt);
    $nuevo_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'id' => $nuevo_id, 'message' => 'Acceso confidencial concedido con éxito.'];
    }
    return ['success' => false, 'message' => 'Error al registrar acceso confidencial: ' . mysqli_error($conexion)];
}

/**
 * Eliminar / Revocar autorización confidencial.
 */
function acceso_confidencial_revocar(mysqli $conexion, int $acceso_id): array
{
    $sql = "DELETE FROM ml__accesos_confidenciales WHERE acceso_confidencial_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $acceso_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Autorización confidencial revocada con éxito.'];
    }
    return ['success' => false, 'message' => 'Error al revocar autorización: ' . mysqli_error($conexion)];
}

/**
 * Listar registros de auditoría de accesos confidenciales.
 */
function auditoria_accesos_listar(mysqli $conexion, int $limite = 50): array
{
    $sql = "SELECT aa.auditoria_acceso_id, aa.usuario_id, u.usuario AS usuario_login,
                   aa.objeto_tabla, aa.objeto_id, aa.accedido_en, aa.motivo,
                   CASE aa.objeto_tabla
                       WHEN 'ml__normas' THEN (SELECT CONCAT('[Norma] ', n.codigo, ' - ', n.titulo) FROM ml__normas n WHERE n.norma_id = aa.objeto_id)
                       WHEN 'ml__procedimientos' THEN (SELECT CONCAT('[Procedimiento] ', p.codigo, ' - ', p.nombre) FROM ml__procedimientos p WHERE p.procedimiento_id = aa.objeto_id)
                       ELSE CONCAT(aa.objeto_tabla, ' #', aa.objeto_id)
                   END AS objeto_nombre
            FROM ml__auditoria_accesos aa
            INNER JOIN conf__usuarios u ON u.usuario_id = aa.usuario_id
            ORDER BY aa.accedido_en DESC, aa.auditoria_acceso_id DESC
            LIMIT " . intval($limite);

    $res = mysqli_query($conexion, $sql);
    $lista = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $lista[] = $row;
    }
    return $lista;
}
