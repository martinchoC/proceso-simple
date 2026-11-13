<?php
/**
 * Modelo de Datos para Procedimientos e Instrucciones de Soporte (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/procedimientos_model.php
 */

declare(strict_types=1);

/**
 * Listar tipos de procedimientos/documentos disponibles.
 */
function procedimientos_tipos_listar(mysqli $conexion): array
{
    $sql = "SELECT procedimiento_tipo_id, codigo, nombre, icono, color_hex, orden
            FROM ml__procedimientos_tipos
            WHERE tabla_estado_registro_id = 1
            ORDER BY orden ASC, nombre ASC";
    $res = mysqli_query($conexion, $sql);
    $lista = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $lista[] = $r;
        }
    }
    return $lista;
}

/**
 * Listar procedimientos de soporte con tipo, responsable y documento adjunto.
 */
function procedimientos_listar(mysqli $conexion, int $empresa_id = 0, int $estado = 0, int $tipo_id = 0): array
{
    $sql = "SELECT p.procedimiento_id, p.codigo, p.nombre, p.descripcion, p.confidencial,
                   p.empresa_id, emp.empresa AS empresa_nombre,
                   p.norma_id, n.codigo AS norma_codigo, n.titulo AS norma_titulo,
                   p.procedimiento_tipo_id,
                   COALESCE(pt.codigo, 'PROC') as tipo_codigo,
                   COALESCE(pt.nombre, 'Procedimiento Operativo') as tipo_nombre,
                   COALESCE(pt.icono, 'fas fa-file-alt') as tipo_icono,
                   COALESCE(pt.color_hex, '#0d6efd') as tipo_color,
                   p.area_responsable,
                   p.responsable_usuario_id,
                   u_resp.usuario_nombre as responsable_nombre,
                   p.frecuencia_revision_meses,
                   p.tabla_estado_registro_id,
                   er.estado_registro,
                   (SELECT pv.version FROM ml__procedimientos_versiones pv WHERE pv.procedimiento_id = p.procedimiento_id AND pv.tabla_estado_registro_id = 2059 ORDER BY pv.vigente_desde DESC LIMIT 1) AS version_vigente,
                   (SELECT pv.archivo_ref FROM ml__procedimientos_versiones pv WHERE pv.procedimiento_id = p.procedimiento_id AND pv.tabla_estado_registro_id = 2059 AND pv.archivo_ref IS NOT NULL ORDER BY pv.vigente_desde DESC LIMIT 1) AS archivo_ref_vigente,
                   (SELECT pv.archivo_ref FROM ml__procedimientos_versiones pv WHERE pv.procedimiento_id = p.procedimiento_id AND pv.archivo_ref IS NOT NULL ORDER BY pv.procedimiento_version_id DESC LIMIT 1) AS archivo_ref_reciente,
                   (SELECT pv.archivo_nombre_original FROM ml__procedimientos_versiones pv WHERE pv.procedimiento_id = p.procedimiento_id AND pv.archivo_nombre_original IS NOT NULL ORDER BY pv.procedimiento_version_id DESC LIMIT 1) AS archivo_nombre_original,
                   (SELECT COUNT(*) FROM ml__procedimientos_versiones pv WHERE pv.procedimiento_id = p.procedimiento_id) AS total_versiones,
                   (SELECT COUNT(*) FROM ml__procedimientos_documentos pd WHERE pd.procedimiento_padre_id = p.procedimiento_id AND pd.tabla_estado_registro_id = 1) AS total_documentos_asociados,
                   (SELECT COUNT(*) FROM ml__procedimientos_lecturas pl INNER JOIN ml__procedimientos_versiones pv ON pv.procedimiento_version_id = pl.procedimiento_version_id WHERE pv.procedimiento_id = p.procedimiento_id AND pl.conformidad = 1) AS total_lecturas_conformidad,
                   DATE_ADD(p.actualizado_en, INTERVAL COALESCE(p.frecuencia_revision_meses, 12) MONTH) AS fecha_proxima_revision,
                   DATEDIFF(DATE_ADD(p.actualizado_en, INTERVAL COALESCE(p.frecuencia_revision_meses, 12) MONTH), CURDATE()) AS dias_para_revision,
                   p.creado_en
            FROM ml__procedimientos p
            LEFT JOIN conf__empresas emp ON emp.empresa_id = p.empresa_id
            LEFT JOIN ml__normas n ON n.norma_id = p.norma_id
            LEFT JOIN ml__procedimientos_tipos pt ON pt.procedimiento_tipo_id = p.procedimiento_tipo_id
            LEFT JOIN conf__usuarios u_resp ON u_resp.usuario_id = p.responsable_usuario_id
            INNER JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = p.tabla_estado_registro_id
            INNER JOIN conf__estados_registros er ON er.estado_registro_id = ter.estado_registro_id
            WHERE 1=1";

    if ($empresa_id > 0) {
        $sql .= " AND (p.empresa_id = " . intval($empresa_id) . " OR p.empresa_id IS NULL)";
    }

    if ($estado > 0) {
        $sql .= " AND p.tabla_estado_registro_id = " . intval($estado);
    }

    if ($tipo_id > 0) {
        $sql .= " AND p.procedimiento_tipo_id = " . intval($tipo_id);
    }

    $sql .= " ORDER BY p.codigo ASC";

    $res = mysqli_query($conexion, $sql);
    $lista = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $dias = isset($row['dias_para_revision']) ? (int)$row['dias_para_revision'] : 365;
            if ($dias < 0) {
                $row['semaforo_revision'] = 'vencido';
                $row['semaforo_color'] = '#dc3545';
                $row['semaforo_texto'] = 'Revisión Vencida hace ' . abs($dias) . ' d';
            } elseif ($dias <= 45) {
                $row['semaforo_revision'] = 'por_vencer';
                $row['semaforo_color'] = '#ffc107';
                $row['semaforo_texto'] = 'Vence en ' . $dias . ' d';
            } else {
                $row['semaforo_revision'] = 'al_dia';
                $row['semaforo_color'] = '#198754';
                $row['semaforo_texto'] = 'Al día (' . $dias . ' d)';
            }
            $lista[] = $row;
        }
    }
    return $lista;
}

/**
 * Obtener un procedimiento específico con todos sus metadatos.
 */
function procedimiento_obtener(mysqli $conexion, int $procedimiento_id): ?array
{
    $id = intval($procedimiento_id);
    $sql = "SELECT p.*, 
                   t.nombre as tipo_nombre, t.codigo as tipo_codigo,
                   e.empresa as empresa_nombre,
                   n.codigo as norma_codigo, n.titulo as norma_titulo,
                   u.usuario as responsable_usuario, u.usuario_nombre as responsable_nombre
            FROM ml__procedimientos p
            LEFT JOIN ml__procedimientos_tipos t ON t.procedimiento_tipo_id = p.procedimiento_tipo_id
            LEFT JOIN conf__empresas e ON e.empresa_id = p.empresa_id
            LEFT JOIN ml__normas n ON n.norma_id = p.norma_id
            LEFT JOIN conf__usuarios u ON u.usuario_id = p.responsable_usuario_id
            WHERE p.procedimiento_id = {$id}
            LIMIT 1";
    $res = mysqli_query($conexion, $sql);
    if ($res && $row = mysqli_fetch_assoc($res)) {
        return $row;
    }
    return null;
}


/**
 * Procesar y almacenar archivo físico adjunto de procedimiento/versión.
 */
function procedimiento_procesar_archivo(int $procedimiento_id, string $version, array $file, ?int $empresa_id = null): array
{
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Error al subir el archivo (código ' . ($file['error'] ?? 'desconocido') . ').'];
    }

    $tamano_maximo = 25 * 1024 * 1024; // 25 MB
    if ($file['size'] > $tamano_maximo) {
        return ['success' => false, 'message' => 'El archivo supera el tamaño máximo permitido de 25 MB.'];
    }

    $nombre_original = $file['name'];
    $extension = strtolower(pathinfo($nombre_original, PATHINFO_EXTENSION));
    $permitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods', 'txt', 'rtf', 'png', 'jpg', 'jpeg', 'zip'];

    if (!in_array($extension, $permitidas, true)) {
        return ['success' => false, 'message' => "La extensión '.{$extension}' no está permitida. Formatos admitidos: PDF, Word, Excel, PowerPoint, OpenDocument, TXT, imágenes, ZIP."];
    }

    if (!file_exists($file['tmp_name'])) {
        return ['success' => false, 'message' => 'El archivo temporal subido no se encuentra disponible.'];
    }

    $hash = hash_file('sha256', $file['tmp_name']);
    $subfolder = ($empresa_id !== null && $empresa_id > 0) ? (string)$empresa_id : 'grupo';
    $base_dir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ml_procedimientos' . DIRECTORY_SEPARATOR . $subfolder;

    if (!is_dir($base_dir)) {
        @mkdir($base_dir, 0777, true);
    }

    $ver_slug = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $version);
    $nombre_guardado = 'proc_' . $procedimiento_id . '_' . $ver_slug . '_' . time() . '_' . substr($hash, 0, 8) . ($extension ? '.' . $extension : '');
    $destino_final = $base_dir . DIRECTORY_SEPARATOR . $nombre_guardado;
    $archivo_ref = 'uploads/ml_procedimientos/' . $subfolder . '/' . $nombre_guardado;

    if (!@move_uploaded_file($file['tmp_name'], $destino_final) && !@copy($file['tmp_name'], $destino_final)) {
        return ['success' => false, 'message' => 'No se pudo guardar el archivo físico en el servidor.'];
    }

    return [
        'success' => true,
        'archivo_ref' => $archivo_ref,
        'hash' => $hash,
        'archivo_nombre_original' => $nombre_original,
        'archivo_tamano' => (int)$file['size']
    ];
}

/**
 * Guardar procedimiento (crear o editar cabecera ampliada con soporte de archivo).
 */
function procedimiento_guardar(mysqli $conexion, array $datos, int $usuario_id, ?array $archivo = null): array
{
    $procedimiento_id = intval($datos['procedimiento_id'] ?? 0);
    $procedimiento_tipo_id = !empty($datos['procedimiento_tipo_id']) ? intval($datos['procedimiento_tipo_id']) : 1;
    $codigo = strtoupper(trim($datos['codigo'] ?? ''));
    $nombre = trim($datos['nombre'] ?? '');
    $descripcion = trim($datos['descripcion'] ?? '');
    $area_responsable = trim($datos['area_responsable'] ?? '');
    $responsable_usuario_id = !empty($datos['responsable_usuario_id']) ? intval($datos['responsable_usuario_id']) : null;
    $frecuencia_revision_meses = !empty($datos['frecuencia_revision_meses']) ? intval($datos['frecuencia_revision_meses']) : 12;
    $empresa_id = !empty($datos['empresa_id']) ? intval($datos['empresa_id']) : null;
    $norma_id = !empty($datos['norma_id']) ? intval($datos['norma_id']) : null;
    $confidencial = !empty($datos['confidencial']) ? 1 : 0;

    if (empty($codigo) || empty($nombre)) {
        return ['success' => false, 'message' => 'Código y nombre de procedimiento son obligatorios.'];
    }

    if ($procedimiento_id > 0) {
        $sql = "UPDATE ml__procedimientos 
                SET codigo = ?, nombre = ?, descripcion = ?, procedimiento_tipo_id = ?, area_responsable = ?,
                    responsable_usuario_id = ?, frecuencia_revision_meses = ?, empresa_id = ?, norma_id = ?, confidencial = ?,
                    actualizado_en = NOW(), actualizado_por = ? 
                WHERE procedimiento_id = ?";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "sssisiisiiii", 
            $codigo, $nombre, $descripcion, $procedimiento_tipo_id, $area_responsable,
            $responsable_usuario_id, $frecuencia_revision_meses, $empresa_id, $norma_id, $confidencial, 
            $usuario_id, $procedimiento_id
        );
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        if (!$ok) {
            return ['success' => false, 'message' => 'Error al actualizar procedimiento: ' . mysqli_error($conexion)];
        }

        // Si se subió archivo en la edición, vincularlo a la última versión
        if ($archivo && isset($archivo['tmp_name']) && !empty($archivo['tmp_name']) && $archivo['error'] === UPLOAD_ERR_OK) {
            $res_ult = mysqli_query($conexion, "SELECT procedimiento_version_id, version FROM ml__procedimientos_versiones WHERE procedimiento_id = {$procedimiento_id} ORDER BY procedimiento_version_id DESC LIMIT 1");
            if ($r_ult = mysqli_fetch_assoc($res_ult)) {
                $ver_id = (int)$r_ult['procedimiento_version_id'];
                $ver_str = $r_ult['version'];
                $proc_arch = procedimiento_procesar_archivo($procedimiento_id, $ver_str, $archivo, $empresa_id);
                if ($proc_arch['success']) {
                    $sql_up_v = "UPDATE ml__procedimientos_versiones 
                                 SET archivo_ref = ?, hash = ?, archivo_nombre_original = ?, archivo_tamano = ?, actualizado_en = NOW(), actualizado_por = ?
                                 WHERE procedimiento_version_id = ?";
                    $stmt_v = mysqli_prepare($conexion, $sql_up_v);
                    mysqli_stmt_bind_param($stmt_v, "sssiii", 
                        $proc_arch['archivo_ref'], $proc_arch['hash'], $proc_arch['archivo_nombre_original'], $proc_arch['archivo_tamano'],
                        $usuario_id, $ver_id
                    );
                    mysqli_stmt_execute($stmt_v);
                    mysqli_stmt_close($stmt_v);
                }
            }
        }

        return ['success' => true, 'id' => $procedimiento_id, 'message' => 'Procedimiento actualizado con éxito.'];
    } else {
        // Estado inicial: 2053 (Borrador)
        $sql = "INSERT INTO ml__procedimientos 
                (codigo, nombre, descripcion, procedimiento_tipo_id, area_responsable, responsable_usuario_id, frecuencia_revision_meses, empresa_id, norma_id, confidencial, tabla_estado_registro_id, creado_en, creado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 2053, NOW(), ?)";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "sssisiiiiii", 
            $codigo, $nombre, $descripcion, $procedimiento_tipo_id, $area_responsable,
            $responsable_usuario_id, $frecuencia_revision_meses, $empresa_id, $norma_id, $confidencial, $usuario_id
        );
        $ok = mysqli_stmt_execute($stmt);
        $nuevo_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        if (!$ok) {
            return ['success' => false, 'message' => 'Error al crear procedimiento: ' . mysqli_error($conexion)];
        }

        // Procesar archivo adjunto si se subió en el alta
        $archivo_ref = null;
        $hash = null;
        $nombre_orig = null;
        $tamano = null;

        if ($archivo && isset($archivo['tmp_name']) && !empty($archivo['tmp_name']) && $archivo['error'] === UPLOAD_ERR_OK) {
            $proc_arch = procedimiento_procesar_archivo($nuevo_id, '1.0', $archivo, $empresa_id);
            if ($proc_arch['success']) {
                $archivo_ref = $proc_arch['archivo_ref'];
                $hash = $proc_arch['hash'];
                $nombre_orig = $proc_arch['archivo_nombre_original'];
                $tamano = $proc_arch['archivo_tamano'];
            }
        }

        if (!$hash) {
            $hash = hash('sha256', $nuevo_id . '|1.0|' . date('Y-m-d'));
        }

        // Versión inicial v1.0 (2057 Borrador)
        $sql_v = "INSERT INTO ml__procedimientos_versiones 
                  (procedimiento_id, version, archivo_ref, hash, archivo_nombre_original, archivo_tamano, resumen_cambios, vigente_desde, tabla_estado_registro_id, creado_en, creado_por)
                  VALUES (?, '1.0', ?, ?, ?, ?, 'Versión inicial', CURDATE(), 2057, NOW(), ?)";
        $stmt_v = mysqli_prepare($conexion, $sql_v);
        mysqli_stmt_bind_param($stmt_v, "isssii", $nuevo_id, $archivo_ref, $hash, $nombre_orig, $tamano, $usuario_id);
        mysqli_stmt_execute($stmt_v);
        mysqli_stmt_close($stmt_v);

        return ['success' => true, 'id' => $nuevo_id, 'message' => 'Procedimiento y versión inicial v1.0 registrados correctamente.'];
    }
}

/**
 * Cambiar estado de procedimiento (2053 Borrador -> 2054 Pendiente -> 2055 Confirmado -> 2056 Eliminado).
 * Si se confirma (2055), sincroniza y confirma la versión actual.
 */
function procedimiento_cambiar_estado(mysqli $conexion, int $procedimiento_id, int $nuevo_estado, int $usuario_id): array
{
    $sql = "UPDATE ml__procedimientos SET tabla_estado_registro_id = ?, actualizado_en = NOW(), actualizado_por = ? WHERE procedimiento_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iii", $nuevo_estado, $usuario_id, $procedimiento_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        // Si se confirma el procedimiento, sincronizar la versión vigente a 2059 y asentar firma
        if ($nuevo_estado === 2055) {
            $sql_pv = "UPDATE ml__procedimientos_versiones 
                       SET tabla_estado_registro_id = 2059, aprobado_por = ?, aprobado_en = NOW(), actualizado_en = NOW(), actualizado_por = ? 
                       WHERE procedimiento_id = ? AND tabla_estado_registro_id != 2060
                       ORDER BY procedimiento_version_id DESC LIMIT 1";
            $stmt_pv = mysqli_prepare($conexion, $sql_pv);
            mysqli_stmt_bind_param($stmt_pv, "iii", $usuario_id, $usuario_id, $procedimiento_id);
            mysqli_stmt_execute($stmt_pv);
            mysqli_stmt_close($stmt_pv);
        }

        return ['success' => true, 'message' => 'Estado del procedimiento actualizado.'];
    }
    return ['success' => false, 'message' => 'Error al cambiar estado: ' . mysqli_error($conexion)];
}

/**
 * Listar versiones de un procedimiento con datos de adjuntos y firmas.
 */
function procedimiento_versiones_listar(mysqli $conexion, int $procedimiento_id): array
{
    $sql = "SELECT pv.*, er.estado_registro, 
                   u_apr.usuario_nombre AS aprobado_por_nombre, u_apr.usuario AS aprobado_por_login,
                   u_cr.usuario_nombre AS creado_por_nombre,
                   (SELECT COUNT(*) FROM ml__procedimientos_instrucciones pi WHERE pi.procedimiento_version_id = pv.procedimiento_version_id) AS total_instrucciones
            FROM ml__procedimientos_versiones pv
            INNER JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = pv.tabla_estado_registro_id
            INNER JOIN conf__estados_registros er ON er.estado_registro_id = ter.estado_registro_id
            LEFT JOIN conf__usuarios u_apr ON u_apr.usuario_id = pv.aprobado_por
            LEFT JOIN conf__usuarios u_cr ON u_cr.usuario_id = pv.creado_por
            WHERE pv.procedimiento_id = ?
            ORDER BY pv.vigente_desde DESC, pv.procedimiento_version_id DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $procedimiento_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $lista = [];
    if ($res) {
        while ($row = mysqli_fetch_assoc($res)) {
            $lista[] = $row;
        }
    }
    mysqli_stmt_close($stmt);

    return $lista;
}

/**
 * Registrar nueva versión de procedimiento con soporte de archivo adjunto.
 */
function procedimiento_version_crear(mysqli $conexion, int $procedimiento_id, array $datos, int $usuario_id, ?array $archivo = null): array
{
    $version = trim($datos['version'] ?? '');
    $resumen_cambios = trim($datos['resumen_cambios'] ?? '');
    $vigente_desde = trim($datos['vigente_desde'] ?? date('Y-m-d'));
    $vigente_hasta = !empty($datos['vigente_hasta']) ? trim($datos['vigente_hasta']) : null;
    $aprobar_directo = !empty($datos['aprobar_directo']);

    if (empty($version) || empty($vigente_desde)) {
        return ['success' => false, 'message' => 'Versión y fecha de vigencia son obligatorias.'];
    }

    // Obtener empresa_id del procedimiento para la carpeta destino
    $q_proc = mysqli_query($conexion, "SELECT empresa_id FROM ml__procedimientos WHERE procedimiento_id = {$procedimiento_id} LIMIT 1");
    $r_proc = mysqli_fetch_assoc($q_proc);
    $empresa_id = $r_proc ? (int)$r_proc['empresa_id'] : null;

    $archivo_ref = null;
    $hash = null;
    $nombre_orig = null;
    $tamano = null;

    if ($archivo && isset($archivo['tmp_name']) && !empty($archivo['tmp_name']) && $archivo['error'] === UPLOAD_ERR_OK) {
        $proc_arch = procedimiento_procesar_archivo($procedimiento_id, $version, $archivo, $empresa_id);
        if (!$proc_arch['success']) {
            return $proc_arch;
        }
        $archivo_ref = $proc_arch['archivo_ref'];
        $hash = $proc_arch['hash'];
        $nombre_orig = $proc_arch['archivo_nombre_original'];
        $tamano = $proc_arch['archivo_tamano'];
    }

    if (!$hash) {
        $hash = hash('sha256', $procedimiento_id . '|' . $version . '|' . $vigente_desde);
    }

    $estado_v = $aprobar_directo ? 2059 : 2057; // 2059 Confirmado / 2057 Borrador
    $aprobado_por = $aprobar_directo ? $usuario_id : null;
    $aprobado_en = $aprobar_directo ? date('Y-m-d H:i:s') : null;

    $sql = "INSERT INTO ml__procedimientos_versiones 
            (procedimiento_id, version, archivo_ref, hash, archivo_nombre_original, archivo_tamano, resumen_cambios, aprobado_por, aprobado_en, vigente_desde, vigente_hasta, tabla_estado_registro_id, creado_en, creado_por)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "issssisisssii", 
        $procedimiento_id, $version, $archivo_ref, $hash, $nombre_orig, $tamano, 
        $resumen_cambios, $aprobado_por, $aprobado_en, $vigente_desde, $vigente_hasta, $estado_v, $usuario_id
    );
    $ok = mysqli_stmt_execute($stmt);
    $v_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ($ok) {
        if ($aprobar_directo) {
            // Pasar versiones anteriores vigentes a obsoletas (2060)
            mysqli_query($conexion, "UPDATE ml__procedimientos_versiones 
                                     SET tabla_estado_registro_id = 2060, 
                                         vigente_hasta = '{$vigente_desde}',
                                         reemplazado_por_version_id = {$v_id},
                                         actualizado_en = NOW(),
                                         actualizado_por = {$usuario_id}
                                     WHERE procedimiento_id = {$procedimiento_id} 
                                       AND tabla_estado_registro_id = 2059 
                                       AND procedimiento_version_id != {$v_id}");

            // Actualizar estado de procedimiento a Confirmado (2055)
            mysqli_query($conexion, "UPDATE ml__procedimientos 
                                     SET tabla_estado_registro_id = 2055, 
                                         actualizado_en = NOW(), 
                                         actualizado_por = {$usuario_id} 
                                     WHERE procedimiento_id = {$procedimiento_id}");

            // Registrar firma de aprobación
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $firma_hash = hash('sha256', "{$usuario_id}|{$procedimiento_id}|{$v_id}|" . microtime() . "|{$hash}");
            $obs_apr = 'Aprobación directa al crear versión ' . $version;
            $stmt_apr = mysqli_prepare($conexion, "INSERT INTO ml__procedimientos_aprobaciones 
                (procedimiento_id, procedimiento_version_id, usuario_id, rol_firmante, accion, observaciones, firma_hash, ip_origen) 
                VALUES (?, ?, ?, 'Aprobador', 'aprobado', ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_apr, "iiisss", $procedimiento_id, $v_id, $usuario_id, $obs_apr, $firma_hash, $ip);
            mysqli_stmt_execute($stmt_apr);
            mysqli_stmt_close($stmt_apr);

            procedimiento_log_registrar($conexion, $procedimiento_id, $v_id, 'APROBADO', "Versión {$version} aprobada y vigente directamente", $usuario_id, ['version' => $version]);
        } else {
            procedimiento_log_registrar($conexion, $procedimiento_id, $v_id, 'NUEVA_VERSION', "Nueva versión {$version} registrada en borrador", $usuario_id, ['version' => $version]);
        }
        return ['success' => true, 'id' => $v_id, 'message' => "Versión {$version} registrada exitosamente."];
    }
    return ['success' => false, 'message' => 'Error al crear versión: ' . mysqli_error($conexion)];
}

/**
 * Listar instrucciones secuenciales de una versión de procedimiento.
 */
function procedimiento_instrucciones_listar(mysqli $conexion, int $procedimiento_version_id): array
{
    $sql = "SELECT pi.* 
            FROM ml__procedimientos_instrucciones pi
            WHERE pi.procedimiento_version_id = ?
            ORDER BY pi.orden ASC, pi.instruccion_id ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $procedimiento_version_id);
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
 * Guardar instrucción de trabajo.
 */
function procedimiento_instruccion_guardar(mysqli $conexion, array $datos, int $usuario_id): array
{
    $procedimiento_version_id = intval($datos['procedimiento_version_id'] ?? 0);
    $orden = intval($datos['orden'] ?? 1);
    $titulo = trim($datos['titulo'] ?? '');
    $descripcion = trim($datos['descripcion'] ?? '');
    $es_obligatoria = !empty($datos['es_obligatoria']) ? 1 : 0;

    if ($procedimiento_version_id <= 0 || empty($titulo)) {
        return ['success' => false, 'message' => 'Título y versión de procedimiento son obligatorios.'];
    }

    $sql = "INSERT INTO ml__procedimientos_instrucciones 
            (procedimiento_version_id, orden, titulo, descripcion, es_obligatoria, creado_en, creado_por)
            VALUES (?, ?, ?, ?, ?, NOW(), ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iissii", $procedimiento_version_id, $orden, $titulo, $descripcion, $es_obligatoria, $usuario_id);
    $ok = mysqli_stmt_execute($stmt);
    $ins_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'id' => $ins_id, 'message' => 'Instrucción agregada al procedimiento.'];
    }
    return ['success' => false, 'message' => 'Error al agregar instrucción: ' . mysqli_error($conexion)];
}

/**
 * Eliminar instrucción de procedimiento.
 */
function procedimiento_instruccion_eliminar(mysqli $conexion, int $instruccion_id): array
{
    $sql = "DELETE FROM ml__procedimientos_instrucciones WHERE instruccion_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $instruccion_id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        return ['success' => true, 'message' => 'Instrucción eliminada.'];
    }
    return ['success' => false, 'message' => 'Error al eliminar instrucción: ' . mysqli_error($conexion)];
}

// =========================================================================
// PISTA DE AUDITORÍA (PUNTO 7)
// =========================================================================

/**
 * Registrar evento en el log de auditoría del procedimiento.
 */
function procedimiento_log_registrar(mysqli $conexion, int $procedimiento_id, ?int $version_id, string $evento, string $descripcion, int $usuario_id, ?array $datos = null): void
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $json = $datos !== null ? json_encode($datos, JSON_UNESCAPED_UNICODE) : null;
    $sql = "INSERT INTO ml__procedimientos_log 
            (procedimiento_id, procedimiento_version_id, evento, descripcion, usuario_id, datos_json, ip_origen) 
            VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "iississ", $procedimiento_id, $version_id, $evento, $descripcion, $usuario_id, $json, $ip);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Listar pista de auditoría de un procedimiento.
 */
function procedimiento_logs_listar(mysqli $conexion, int $procedimiento_id): array
{
    $id = intval($procedimiento_id);
    $sql = "SELECT l.*, u.usuario, u.usuario_nombre, pv.version
            FROM ml__procedimientos_log l
            LEFT JOIN conf__usuarios u ON u.usuario_id = l.usuario_id
            LEFT JOIN ml__procedimientos_versiones pv ON pv.procedimiento_version_id = l.procedimiento_version_id
            WHERE l.procedimiento_id = {$id}
            ORDER BY l.procedimiento_log_id DESC
            LIMIT 50";
    $res = mysqli_query($conexion, $sql);
    $logs = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $logs[] = $r;
        }
    }
    return $logs;
}

// =========================================================================
// DOCUMENTOS ASOCIADOS / JERARQUÍA DOCUMENTAL (PUNTOS 1 Y 2)
// =========================================================================

/**
 * Listar documentos vinculados / hijos de un procedimiento padre.
 */
function procedimiento_documentos_listar(mysqli $conexion, int $procedimiento_padre_id): array
{
    $id = intval($procedimiento_padre_id);
    $sql = "SELECT pd.procedimiento_documento_id, pd.procedimiento_padre_id, pd.documento_asociado_id,
                   pd.tipo_relacion, pd.orden, pd.obligatorio, pd.notas, pd.creado_en,
                   hijo.codigo, hijo.nombre, hijo.descripcion,
                   t.nombre AS tipo_nombre, t.codigo AS tipo_codigo, t.icono AS tipo_icono, t.color_hex AS tipo_color,
                   (SELECT pv.version FROM ml__procedimientos_versiones pv WHERE pv.procedimiento_id = hijo.procedimiento_id AND pv.tabla_estado_registro_id = 2059 ORDER BY pv.vigente_desde DESC LIMIT 1) AS version_vigente,
                   (SELECT pv.archivo_ref FROM ml__procedimientos_versiones pv WHERE pv.procedimiento_id = hijo.procedimiento_id AND pv.tabla_estado_registro_id = 2059 AND pv.archivo_ref IS NOT NULL ORDER BY pv.vigente_desde DESC LIMIT 1) AS archivo_ref_vigente,
                   (SELECT pv.archivo_nombre_original FROM ml__procedimientos_versiones pv WHERE pv.procedimiento_id = hijo.procedimiento_id AND pv.archivo_nombre_original IS NOT NULL ORDER BY pv.procedimiento_version_id DESC LIMIT 1) AS archivo_nombre_original
            FROM ml__procedimientos_documentos pd
            INNER JOIN ml__procedimientos hijo ON hijo.procedimiento_id = pd.documento_asociado_id
            LEFT JOIN ml__procedimientos_tipos t ON t.procedimiento_tipo_id = hijo.procedimiento_tipo_id
            WHERE pd.procedimiento_padre_id = {$id} AND pd.tabla_estado_registro_id = 1
            ORDER BY pd.orden ASC, hijo.codigo ASC";
    $res = mysqli_query($conexion, $sql);
    $lista = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $lista[] = $r;
        }
    }
    return $lista;
}

/**
 * Listar candidatos a asociar como documentos hijos (excluye al propio procedimiento y a los ya vinculados).
 */
function procedimiento_documentos_candidatos(mysqli $conexion, int $padre_id, int $empresa_id = 0): array
{
    $id = intval($padre_id);
    $sql = "SELECT p.procedimiento_id, p.codigo, p.nombre, t.codigo as tipo_codigo, t.nombre as tipo_nombre, t.color_hex
            FROM ml__procedimientos p
            LEFT JOIN ml__procedimientos_tipos t ON t.procedimiento_tipo_id = p.procedimiento_tipo_id
            WHERE p.procedimiento_id != {$id}
              AND p.tabla_estado_registro_id != 2056
              AND p.procedimiento_id NOT IN (
                  SELECT documento_asociado_id 
                  FROM ml__procedimientos_documentos 
                  WHERE procedimiento_padre_id = {$id} AND tabla_estado_registro_id = 1
              )";
    if ($empresa_id > 0) {
        $sql .= " AND (p.empresa_id = {$empresa_id} OR p.empresa_id IS NULL)";
    }
    $sql .= " ORDER BY t.orden ASC, p.codigo ASC";
    $res = mysqli_query($conexion, $sql);
    $cands = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $cands[] = $r;
        }
    }
    return $cands;
}

/**
 * Vincular un documento asociado a un procedimiento padre.
 */
function procedimiento_documento_vincular(mysqli $conexion, int $padre_id, int $doc_id, string $tipo_relacion, int $obligatorio, ?string $notas, int $usuario_id): array
{
    if ($padre_id <= 0 || $doc_id <= 0 || $padre_id === $doc_id) {
        return ['success' => false, 'message' => 'Documento de origen y destino inválidos.'];
    }

    $q_ex = mysqli_query($conexion, "SELECT procedimiento_documento_id FROM ml__procedimientos_documentos WHERE procedimiento_padre_id = {$padre_id} AND documento_asociado_id = {$doc_id} AND tabla_estado_registro_id = 1 LIMIT 1");
    if (mysqli_num_rows($q_ex) > 0) {
        return ['success' => false, 'message' => 'El documento ya se encuentra vinculado a este procedimiento.'];
    }

    $sql = "INSERT INTO ml__procedimientos_documentos 
            (procedimiento_padre_id, documento_asociado_id, tipo_relacion, orden, obligatorio, notas, tabla_estado_registro_id, creado_en, creado_por) 
            VALUES (?, ?, ?, 1, ?, ?, 1, NOW(), ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iisisi", $padre_id, $doc_id, $tipo_relacion, $obligatorio, $notas, $usuario_id);
    $ok = mysqli_stmt_execute($stmt);
    $new_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    if ($ok) {
        procedimiento_log_registrar($conexion, $padre_id, null, 'VINCULO_DOC', "Se vinculó el documento ID {$doc_id} ({$tipo_relacion})", $usuario_id, ['documento_asociado_id' => $doc_id, 'tipo_relacion' => $tipo_relacion]);
        return ['success' => true, 'id' => $new_id, 'message' => 'Documento asociado vinculado correctamente.'];
    }
    return ['success' => false, 'message' => 'Error al vincular documento: ' . mysqli_error($conexion)];
}

/**
 * Desvincular un documento asociado.
 */
function procedimiento_documento_desvincular(mysqli $conexion, int $procedimiento_documento_id, int $usuario_id): array
{
    $id = intval($procedimiento_documento_id);
    $q_info = mysqli_query($conexion, "SELECT procedimiento_padre_id, documento_asociado_id FROM ml__procedimientos_documentos WHERE procedimiento_documento_id = {$id} LIMIT 1");
    $info = mysqli_fetch_assoc($q_info);

    $sql = "UPDATE ml__procedimientos_documentos SET tabla_estado_registro_id = 2 WHERE procedimiento_documento_id = {$id}";
    $ok = mysqli_query($conexion, $sql);

    if ($ok) {
        if ($info) {
            procedimiento_log_registrar($conexion, (int)$info['procedimiento_padre_id'], null, 'DESVINCULO_DOC', "Se desvinculó el documento ID {$info['documento_asociado_id']}", $usuario_id);
        }
        return ['success' => true, 'message' => 'Documento desvinculado con éxito.'];
    }
    return ['success' => false, 'message' => 'Error al desvincular documento: ' . mysqli_error($conexion)];
}

// =========================================================================
// WORKFLOW DE REVISIÓN Y APROBACIÓN CON FIRMAS (PUNTO 3)
// =========================================================================

/**
 * Enviar procedimiento a circuito formal de aprobación.
 */
function procedimiento_solicitar_aprobacion(mysqli $conexion, int $procedimiento_id, int $usuario_id, ?string $nota = null): array
{
    $id = intval($procedimiento_id);
    // Cambiar estado a 2054 (En Aprobación)
    $ok1 = mysqli_query($conexion, "UPDATE ml__procedimientos SET tabla_estado_registro_id = 2054, actualizado_en = NOW(), actualizado_por = {$usuario_id} WHERE procedimiento_id = {$id}");
    // Cambiar estado de versión más reciente en borrador a 2058 (En Revisión)
    $q_ver = mysqli_query($conexion, "SELECT procedimiento_version_id FROM ml__procedimientos_versiones WHERE procedimiento_id = {$id} AND tabla_estado_registro_id = 2057 ORDER BY procedimiento_version_id DESC LIMIT 1");
    $r_ver = mysqli_fetch_assoc($q_ver);
    $v_id = $r_ver ? (int)$r_ver['procedimiento_version_id'] : 0;

    if ($v_id > 0) {
        mysqli_query($conexion, "UPDATE ml__procedimientos_versiones SET tabla_estado_registro_id = 2058, actualizado_en = NOW(), actualizado_por = {$usuario_id} WHERE procedimiento_version_id = {$v_id}");
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $firma = hash('sha256', "solicitud|{$id}|{$usuario_id}|" . microtime());
    $obs = $nota ?: 'Solicitud de revisión y aprobación formal iniciada';
    $stmt_sol = mysqli_prepare($conexion, "INSERT INTO ml__procedimientos_aprobaciones 
        (procedimiento_id, procedimiento_version_id, usuario_id, rol_firmante, accion, observaciones, firma_hash, ip_origen) 
        VALUES (?, ?, ?, 'Iniciador', 'solicitado', ?, ?, ?)");
    mysqli_stmt_bind_param($stmt_sol, "iiisss", $id, $v_id, $usuario_id, $obs, $firma, $ip);
    mysqli_stmt_execute($stmt_sol);
    mysqli_stmt_close($stmt_sol);

    procedimiento_log_registrar($conexion, $id, $v_id ?: null, 'ENVIO_APROBACION', $obs, $usuario_id);

    return ['success' => true, 'message' => 'Procedimiento enviado a aprobación exitosamente.'];
}

/**
 * Aprobar formalmente una versión de procedimiento con firma digital.
 */
function procedimiento_aprobar_formal(mysqli $conexion, int $procedimiento_id, int $version_id, int $usuario_id, string $rol_firmante, ?string $observaciones = null): array
{
    $proc_id = intval($procedimiento_id);
    $ver_id = intval($version_id);

    // Si no se pasó versión específica, tomar la más reciente en revisión/borrador
    if ($ver_id <= 0) {
        $q_v = mysqli_query($conexion, "SELECT procedimiento_version_id, version, vigente_desde, hash FROM ml__procedimientos_versiones WHERE procedimiento_id = {$proc_id} AND tabla_estado_registro_id IN (2057, 2058) ORDER BY procedimiento_version_id DESC LIMIT 1");
        $r_v = mysqli_fetch_assoc($q_v);
        if (!$r_v) {
            return ['success' => false, 'message' => 'No se encontró versión pendiente de aprobación.'];
        }
        $ver_id = (int)$r_v['procedimiento_version_id'];
        $ver_str = $r_v['version'];
        $ver_desde = $r_v['vigente_desde'];
        $ver_hash = $r_v['hash'];
    } else {
        $q_v = mysqli_query($conexion, "SELECT version, vigente_desde, hash FROM ml__procedimientos_versiones WHERE procedimiento_version_id = {$ver_id} LIMIT 1");
        $r_v = mysqli_fetch_assoc($q_v);
        $ver_str = $r_v['version'] ?? 'vX';
        $ver_desde = $r_v['vigente_desde'] ?? date('Y-m-d');
        $ver_hash = $r_v['hash'] ?? '';
    }

    // 1. Pasar versión a 2059 (Vigente / Confirmada)
    mysqli_query($conexion, "UPDATE ml__procedimientos_versiones 
                             SET tabla_estado_registro_id = 2059, 
                                 aprobado_por = {$usuario_id}, 
                                 aprobado_en = NOW(),
                                 actualizado_en = NOW(),
                                 actualizado_por = {$usuario_id}
                             WHERE procedimiento_version_id = {$ver_id}");

    // 2. Pasar versiones anteriores vigentes a 2060 (Obsoletas / Reemplazadas)
    mysqli_query($conexion, "UPDATE ml__procedimientos_versiones 
                             SET tabla_estado_registro_id = 2060,
                                 vigente_hasta = '{$ver_desde}',
                                 reemplazado_por_version_id = {$ver_id},
                                 actualizado_en = NOW(),
                                 actualizado_por = {$usuario_id}
                             WHERE procedimiento_id = {$proc_id} 
                               AND tabla_estado_registro_id = 2059 
                               AND procedimiento_version_id != {$ver_id}");

    // 3. Confirmar procedimiento cabecera a 2055
    mysqli_query($conexion, "UPDATE ml__procedimientos 
                             SET tabla_estado_registro_id = 2055, 
                                 actualizado_en = NOW(), 
                                 actualizado_por = {$usuario_id} 
                             WHERE procedimiento_id = {$proc_id}");

    // 4. Registrar firma electrónica digital (hash)
    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $firma_hash = hash('sha256', "{$usuario_id}|{$proc_id}|{$ver_id}|" . microtime() . "|{$ver_hash}|{$rol_firmante}");
    $obs = $observaciones ?: 'Procedimiento aprobado y declarado vigente según estándares de gestión.';

    $stmt = mysqli_prepare($conexion, "INSERT INTO ml__procedimientos_aprobaciones 
        (procedimiento_id, procedimiento_version_id, usuario_id, rol_firmante, accion, observaciones, firma_hash, ip_origen) 
        VALUES (?, ?, ?, ?, 'aprobado', ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iiissss", $proc_id, $ver_id, $usuario_id, $rol_firmante, $obs, $firma_hash, $ip);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    procedimiento_log_registrar($conexion, $proc_id, $ver_id, 'APROBADO', "Versión {$ver_str} aprobada formalmente por {$rol_firmante}", $usuario_id, [
        'rol_firmante' => $rol_firmante,
        'firma_hash' => $firma_hash,
        'observaciones' => $obs
    ]);

    return ['success' => true, 'firma_hash' => $firma_hash, 'message' => "Versión {$ver_str} aprobada y declarada vigente con firma registrada."];
}

/**
 * Rechazar versión en revisión y devolver a borrador con observaciones.
 */
function procedimiento_rechazar_formal(mysqli $conexion, int $procedimiento_id, int $version_id, int $usuario_id, string $motivo_rechazo): array
{
    $proc_id = intval($procedimiento_id);
    $ver_id = intval($version_id);

    if (empty($motivo_rechazo)) {
        return ['success' => false, 'message' => 'Debe indicar el motivo del rechazo / observaciones de corrección.'];
    }

    if ($ver_id <= 0) {
        $q_v = mysqli_query($conexion, "SELECT procedimiento_version_id FROM ml__procedimientos_versiones WHERE procedimiento_id = {$proc_id} AND tabla_estado_registro_id = 2058 ORDER BY procedimiento_version_id DESC LIMIT 1");
        $r_v = mysqli_fetch_assoc($q_v);
        $ver_id = $r_v ? (int)$r_v['procedimiento_version_id'] : 0;
    }

    // Volver procedimiento a Borrador (2053)
    mysqli_query($conexion, "UPDATE ml__procedimientos SET tabla_estado_registro_id = 2053, actualizado_en = NOW(), actualizado_por = {$usuario_id} WHERE procedimiento_id = {$proc_id}");

    // Volver versión a Borrador (2057)
    if ($ver_id > 0) {
        mysqli_query($conexion, "UPDATE ml__procedimientos_versiones SET tabla_estado_registro_id = 2057, actualizado_en = NOW(), actualizado_por = {$usuario_id} WHERE procedimiento_version_id = {$ver_id}");
    }

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $firma = hash('sha256', "rechazo|{$proc_id}|{$ver_id}|{$usuario_id}|" . microtime());
    $stmt = mysqli_prepare($conexion, "INSERT INTO ml__procedimientos_aprobaciones 
        (procedimiento_id, procedimiento_version_id, usuario_id, rol_firmante, accion, observaciones, firma_hash, ip_origen) 
        VALUES (?, ?, ?, 'Revisor', 'rechazado', ?, ?, ?)");
    mysqli_stmt_bind_param($stmt, "iiisss", $proc_id, $ver_id, $usuario_id, $motivo_rechazo, $firma, $ip);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    procedimiento_log_registrar($conexion, $proc_id, $ver_id ?: null, 'RECHAZADO', "Rechazado con observaciones: {$motivo_rechazo}", $usuario_id);

    return ['success' => true, 'message' => 'Procedimiento devuelto a borrador para correcciones.'];
}

/**
 * Listar aprobaciones y firmas registradas de un procedimiento.
 */
function procedimiento_aprobaciones_listar(mysqli $conexion, int $procedimiento_id): array
{
    $id = intval($procedimiento_id);
    $sql = "SELECT a.*, u.usuario, u.usuario_nombre, pv.version
            FROM ml__procedimientos_aprobaciones a
            LEFT JOIN conf__usuarios u ON u.usuario_id = a.usuario_id
            LEFT JOIN ml__procedimientos_versiones pv ON pv.procedimiento_version_id = a.procedimiento_version_id
            WHERE a.procedimiento_id = {$id}
            ORDER BY a.aprobacion_id DESC";
    $res = mysqli_query($conexion, $sql);
    $aprs = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $aprs[] = $r;
        }
    }
    return $aprs;
}

// =========================================================================
// PUBLICACIÓN, DIFUSIÓN Y TOMA DE CONOCIMIENTO (PUNTO 4)
// =========================================================================

/**
 * Registrar acuse de recibo / toma de conocimiento de una versión por un usuario.
 */
function procedimiento_lectura_registrar(mysqli $conexion, int $version_id, int $usuario_id, ?string $comentario = null): array
{
    $v_id = intval($version_id);
    if ($v_id <= 0) {
        return ['success' => false, 'message' => 'Versión inválida.'];
    }

    $q_proc = mysqli_query($conexion, "SELECT procedimiento_id, version FROM ml__procedimientos_versiones WHERE procedimiento_version_id = {$v_id} LIMIT 1");
    $r_proc = mysqli_fetch_assoc($q_proc);
    $proc_id = $r_proc ? (int)$r_proc['procedimiento_id'] : 0;
    $ver_str = $r_proc['version'] ?? 'vX';

    $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    $sql = "INSERT INTO ml__procedimientos_lecturas (procedimiento_version_id, usuario_id, conformidad, comentario, ip_origen) 
            VALUES (?, ?, 1, ?, ?) 
            ON DUPLICATE KEY UPDATE conformidad = 1, comentario = VALUES(comentario), ip_origen = VALUES(ip_origen), creado_en = NOW()";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iiss", $v_id, $usuario_id, $comentario, $ip);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    if ($ok) {
        procedimiento_log_registrar($conexion, $proc_id, $v_id, 'LECTURA_CONFORMIDAD', "Usuario confirmó toma de conocimiento de versión {$ver_str}", $usuario_id);
        return ['success' => true, 'message' => 'Toma de conocimiento registrada con éxito.'];
    }
    return ['success' => false, 'message' => 'Error al registrar toma de conocimiento: ' . mysqli_error($conexion)];
}

/**
 * Listar control de lecturas de una versión de procedimiento.
 */
function procedimiento_lecturas_listar(mysqli $conexion, int $version_id, int $empresa_id = 0): array
{
    $v_id = intval($version_id);
    $sql = "SELECT pl.lectura_id, pl.creado_en as fecha_lectura, pl.conformidad, pl.comentario, pl.ip_origen,
                   u.usuario_id, u.usuario, u.usuario_nombre,
                   ep.empresa_perfil_nombre as perfil_nombre
            FROM ml__procedimientos_lecturas pl
            INNER JOIN conf__usuarios u ON u.usuario_id = pl.usuario_id
            LEFT JOIN conf__usuarios_perfiles up ON up.usuario_id = u.usuario_id
            LEFT JOIN conf__empresas_perfiles ep ON ep.empresa_perfil_id = up.empresa_perfil_id
            WHERE pl.procedimiento_version_id = {$v_id}
            GROUP BY u.usuario_id
            ORDER BY pl.creado_en DESC";
    $res = mysqli_query($conexion, $sql);
    $lecturas = [];
    if ($res) {
        while ($r = mysqli_fetch_assoc($res)) {
            $lecturas[] = $r;
        }
    }

    // Total de usuarios activos del sistema para calcular porcentaje de cobertura
    $q_tot = mysqli_query($conexion, "SELECT COUNT(DISTINCT u.usuario_id) as total FROM conf__usuarios u WHERE u.tabla_estado_registro_id = 1");
    $r_tot = mysqli_fetch_assoc($q_tot);
    $total_personal = (int)($r_tot['total'] ?? 0);
    $total_lecturas = count($lecturas);
    $porcentaje = $total_personal > 0 ? round(($total_lecturas / $total_personal) * 100, 1) : 0;

    return [
        'lecturas' => $lecturas,
        'total_lecturas' => $total_lecturas,
        'total_personal' => $total_personal,
        'porcentaje_cobertura' => $porcentaje
    ];
}

// =========================================================================
// OBSOLESCENCIA Y BAJA CONTROLADA (PUNTO 6)
// =========================================================================

/**
 * Marcar un procedimiento como obsoleto / de baja justificada.
 */
function procedimiento_marcar_obsoleto(mysqli $conexion, int $procedimiento_id, int $usuario_id, string $motivo): array
{
    $id = intval($procedimiento_id);
    if (empty($motivo)) {
        return ['success' => false, 'message' => 'Debe indicar el motivo u orden de pase a obsoleto.'];
    }

    // 1. Procedimiento a 2056 (Obsoleto)
    mysqli_query($conexion, "UPDATE ml__procedimientos SET tabla_estado_registro_id = 2056, actualizado_en = NOW(), actualizado_por = {$usuario_id} WHERE procedimiento_id = {$id}");

    // 2. Versiones vigentes a 2060 (Obsoletas)
    mysqli_query($conexion, "UPDATE ml__procedimientos_versiones SET tabla_estado_registro_id = 2060, vigente_hasta = CURDATE(), actualizado_en = NOW(), actualizado_por = {$usuario_id} WHERE procedimiento_id = {$id} AND tabla_estado_registro_id = 2059");

    procedimiento_log_registrar($conexion, $id, null, 'OBSOLETO', "Procedimiento dado de baja/obsoleto: {$motivo}", $usuario_id, ['motivo' => $motivo]);

    return ['success' => true, 'message' => 'El procedimiento y sus versiones han sido marcados como obsoletos. El registro histórico permanecerá disponible para auditoría.'];
}

