<?php
/**
 * Servicio Central: Cargas y Cambios Masivos (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/cargas_masivas_service.php
 * Casos Cubiertos: C35, C36
 */

declare(strict_types=1);

require_once __DIR__ . '/motor_herencia_service.php';
require_once __DIR__ . '/ciclos_generador_service.php';

/**
 * -------------------------------------------------------------------------
 * SECCIÓN 1: PLANTILLAS Y CARGAS MASIVAS (CASO C36)
 * -------------------------------------------------------------------------
 */

/**
 * Obtener plantilla CSV modelo según el tipo de carga
 */
function cargas_generar_plantilla(string $tipo): array {
    $tipo = strtoupper(trim($tipo));
    switch ($tipo) {
        case 'NORMAS':
            $filename = 'plantilla_normas.csv';
            $encabezados = ['codigo', 'titulo', 'organismo', 'norma_tipo', 'jerarquia', 'fecha_sancion', 'fecha_vigencia', 'es_confidencial', 'descripcion'];
            $ejemplo = [
                ['LEY-24557', 'Ley de Riesgos del Trabajo', 'Congreso de la Nacion', 'LEGAL', '1', '1995-09-13', '1996-01-01', '0', 'Regimen fundamental de prevencion y cobertura de accidentes'],
                ['POL-AMB-01', 'Politica Corporativa Ambiental', 'Direccion Regional', 'POLITICA', '3', '2024-01-01', '2024-01-01', '0', 'Estandar de gestion y descarte seguro de residuos industriales']
            ];
            break;

        case 'OBLIGACIONES':
            $filename = 'plantilla_obligaciones.csv';
            $encabezados = ['norma_codigo', 'codigo', 'descripcion', 'periodicidad', 'criticidad', 'dias_anticipacion', 'retencion_meses'];
            $ejemplo = [
                ['LEY-24557', 'OBL-LRT-01', 'Presentacion de relevamiento anual de agentes de riesgo (RAR)', 'ANUAL', 'ALTA', '15', '60'],
                ['POL-AMB-01', 'OBL-AMB-02', 'Auditoria trimestral de manifiestos y remitos de residuos', 'TRIMESTRAL', 'MEDIA', '7', '36']
            ];
            break;

        case 'ENTIDADES':
            $filename = 'plantilla_entidades.csv';
            $encabezados = ['codigo', 'nombre', 'entidad_tipo', 'cuit', 'direccion', 'localidad', 'provincia', 'pais'];
            $ejemplo = [
                ['SUC-01', 'Planta Industrial Central', 'ESTABLECIMIENTO', '30712345678', 'Ruta 9 Km 45', 'Campana', 'Buenos Aires', 'Argentina'],
                ['DEP-LOG', 'Centro Logistico Norte', 'DEPOSITO', '30712345678', 'Av Circunvalacion 1200', 'Rosario', 'Santa Fe', 'Argentina']
            ];
            break;

        default:
            throw new InvalidArgumentException("Tipo de carga masiva desconocido: {$tipo}");
    }

    $csv = implode(';', $encabezados) . "\n";
    foreach ($ejemplo as $fila) {
        $csv .= implode(';', array_map(function ($val) {
            return '"' . str_replace('"', '""', (string)$val) . '"';
        }, $fila)) . "\n";
    }

    return [
        'filename' => $filename,
        'content' => $csv
    ];
}

/**
 * Subir y validar archivo de carga masiva
 */
function cargas_procesar_archivo(
    mysqli $conexion,
    int $carga_tipo_id,
    string $ruta_archivo,
    string $nombre_original,
    int $empresa_id,
    int $usuario_id
): array {
    // 1. Validar tipo
    $res_t = mysqli_query($conexion, "SELECT * FROM ml__cargas_tipos WHERE carga_tipo_id = {$carga_tipo_id} LIMIT 1");
    $tipo = mysqli_fetch_assoc($res_t);
    if (!$tipo) {
        return ['success' => false, 'message' => 'Tipo de carga masiva no válido.'];
    }

    if (!file_exists($ruta_archivo)) {
        return ['success' => false, 'message' => 'El archivo no fue encontrado para validación.'];
    }

    // 2. Guardar archivo físico en uploads/ml_cargas/
    $base_dir = dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'ml_cargas' . DIRECTORY_SEPARATOR . $empresa_id;
    if (!is_dir($base_dir)) {
        @mkdir($base_dir, 0755, true);
    }
    $extension = pathinfo($nombre_original, PATHINFO_EXTENSION);
    $nombre_guardado = 'carga_' . $tipo['codigo'] . '_' . time() . '_' . rand(100, 999) . ($extension ? '.' . $extension : '.csv');
    $destino = $base_dir . DIRECTORY_SEPARATOR . $nombre_guardado;
    $archivo_ref = 'uploads/ml_cargas/' . $empresa_id . '/' . $nombre_guardado;

    if (!@copy($ruta_archivo, $destino)) {
        return ['success' => false, 'message' => 'Error al almacenar el archivo de carga en el servidor.'];
    }

    // 3. Crear registro en ml__cargas (estado 1 = Iniciada/Cargada)
    $sql_c = "INSERT INTO ml__cargas (carga_tipo_id, empresa_id, archivo_ref, tabla_estado_registro_id, creado_en, creado_por)
              VALUES (?, ?, ?, 1, CURRENT_TIMESTAMP, ?)";
    $stmt_c = mysqli_prepare($conexion, $sql_c);
    mysqli_stmt_bind_param($stmt_c, "iisi", $carga_tipo_id, $empresa_id, $archivo_ref, $usuario_id);
    mysqli_stmt_execute($stmt_c);
    $carga_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt_c);

    // 4. Parsear CSV y validar fila por fila
    $handle = fopen($destino, 'r');
    if (!$handle) {
        return ['success' => false, 'message' => 'No se pudo leer el archivo cargado.'];
    }

    $nro_fila = 0;
    $filas_validas = 0;
    $filas_con_error = 0;
    $headers = [];

    while (($row = fgetcsv($handle, 4096, ';')) !== false) {
        $nro_fila++;
        // Limpiar BOM UTF-8 en primera celda si existe
        if ($nro_fila === 1) {
            $row[0] = preg_replace('/^[\xEF\xBB\xBF\xFE\xFF]/', '', $row[0]);
            $headers = array_map('trim', $row);
            continue;
        }

        // Si la fila está vacía, omitir
        if (count($row) === 1 && empty($row[0])) {
            continue;
        }

        // Mapear campos
        $datos = [];
        foreach ($headers as $idx => $header_name) {
            $datos[$header_name] = isset($row[$idx]) ? trim($row[$idx]) : '';
        }

        // Validaciones por tipo
        $errores_fila = [];
        switch ($tipo['codigo']) {
            case 'NORMAS':
                if (empty($datos['codigo'])) $errores_fila[] = "Falta código de norma.";
                if (empty($datos['titulo'])) $errores_fila[] = "Falta título o nombre de la norma.";
                if (!empty($datos['fecha_sancion']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $datos['fecha_sancion'])) {
                    $errores_fila[] = "Formato de fecha_sancion inválido (debe ser AAAA-MM-DD).";
                }
                if (!empty($datos['fecha_vigencia']) && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $datos['fecha_vigencia'])) {
                    $errores_fila[] = "Formato de fecha_vigencia inválido (debe ser AAAA-MM-DD).";
                }
                break;

            case 'OBLIGACIONES':
                if (empty($datos['codigo'])) $errores_fila[] = "Falta código de obligación.";
                if (empty($datos['descripcion'])) $errores_fila[] = "Falta descripción del requisito.";
                if (empty($datos['norma_codigo'])) {
                    $errores_fila[] = "Falta norma_codigo.";
                } else {
                    $nc = mysqli_real_escape_string($conexion, $datos['norma_codigo']);
                    $res_nv = mysqli_query($conexion, "SELECT norma_id FROM ml__normas WHERE codigo = '{$nc}' AND empresa_id = {$empresa_id} LIMIT 1");
                    if (!mysqli_fetch_assoc($res_nv)) {
                        $errores_fila[] = "La norma con código '{$datos['norma_codigo']}' no existe en el sistema.";
                    }
                }
                break;

            case 'ENTIDADES':
                if (empty($datos['nombre'])) $errores_fila[] = "Falta nombre del establecimiento/entidad.";
                if (empty($datos['codigo'])) $datos['codigo'] = 'ENT-' . time() . '-' . $nro_fila;
                break;
        }

        $tiene_error = !empty($errores_fila);
        if ($tiene_error) {
            $filas_con_error++;
        } else {
            $filas_validas++;
        }

        $json_contenido = json_encode($datos, JSON_UNESCAPED_UNICODE);
        $texto_errores = $tiene_error ? implode(' | ', $errores_fila) : null;

        $sql_f = "INSERT INTO ml__cargas_filas (carga_id, nro_fila, contenido, errores, creado_en, creado_por)
                  VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, ?)";
        $stmt_f = mysqli_prepare($conexion, $sql_f);
        mysqli_stmt_bind_param($stmt_f, "iissi", $carga_id, $nro_fila, $json_contenido, $texto_errores, $usuario_id);
        mysqli_stmt_execute($stmt_f);
        mysqli_stmt_close($stmt_f);
    }
    fclose($handle);

    // Actualizar estado de la carga:
    // Si tiene filas válidas y ninguna con error => Estado 2 (Validada / Lista para confirmar)
    // Si tiene errores => Estado 3 (Con errores)
    $estado_carga = ($filas_con_error === 0 && $filas_validas > 0) ? 2 : 3;
    $sql_up = "UPDATE ml__cargas 
               SET validada_en = CURRENT_TIMESTAMP, 
                   tabla_estado_registro_id = ?
               WHERE carga_id = ?";
    $stmt_up = mysqli_prepare($conexion, $sql_up);
    mysqli_stmt_bind_param($stmt_up, "ii", $estado_carga, $carga_id);
    mysqli_stmt_execute($stmt_up);
    mysqli_stmt_close($stmt_up);

    return [
        'success' => true,
        'carga_id' => $carga_id,
        'tipo' => $tipo['codigo'],
        'filas_totales' => ($filas_validas + $filas_con_error),
        'filas_validas' => $filas_validas,
        'filas_con_error' => $filas_con_error,
        'estado_id' => $estado_carga,
        'message' => "Archivo procesado: {$filas_validas} filas correctas, {$filas_con_error} con error."
    ];
}

/**
 * Confirmar carga masiva en transacción atómica con rollback (Caso C36)
 */
function cargas_confirmar(mysqli_report | mysqli $conexion, int $carga_id, int $empresa_id, int $usuario_id): array {
    $res_c = mysqli_query($conexion, "SELECT c.*, ct.codigo as tipo_codigo 
                                      FROM ml__cargas c 
                                      INNER JOIN ml__cargas_tipos ct ON ct.carga_tipo_id = c.carga_tipo_id
                                      WHERE c.carga_id = {$carga_id} AND c.empresa_id = {$empresa_id} 
                                      LIMIT 1");
    $carga = mysqli_fetch_assoc($res_c);
    if (!$carga) {
        return ['success' => false, 'message' => 'Carga masiva no encontrada.'];
    }

    if ((int)$carga['tabla_estado_registro_id'] === 4) {
        return ['success' => false, 'message' => 'Esta carga ya fue confirmada previamente.'];
    }

    // Obtener filas sin errores
    $sql_filas = "SELECT * FROM ml__cargas_filas WHERE carga_id = {$carga_id} AND (errores IS NULL OR errores = '') ORDER BY nro_fila ASC";
    $res_filas = mysqli_query($conexion, $sql_filas);
    $filas = [];
    while ($r = mysqli_fetch_assoc($res_filas)) {
        $filas[] = json_decode($r['contenido'], true);
    }

    if (empty($filas)) {
        return ['success' => false, 'message' => 'No hay filas válidas para insertar en esta carga.'];
    }

    // Iniciar Transacción Atómica
    mysqli_begin_transaction($conexion);
    $insertados = 0;

    try {
        switch ($carga['tipo_codigo']) {
            case 'NORMAS':
                foreach ($filas as $d) {
                    $cod = mysqli_real_escape_string($conexion, $d['codigo']);
                    $tit = mysqli_real_escape_string($conexion, $d['titulo']);
                    $desc = mysqli_real_escape_string($conexion, $d['descripcion'] ?? '');
                    $conf = !empty($d['es_confidencial']) ? 1 : 0;
                    $jer = !empty($d['jerarquia']) ? (int)$d['jerarquia'] : 1;
                    $fs = !empty($d['fecha_sancion']) ? "'{$d['fecha_sancion']}'" : 'NULL';
                    $fv = !empty($d['fecha_vigencia']) ? "'{$d['fecha_vigencia']}'" : 'CURRENT_DATE';

                    $sql = "INSERT INTO ml__normas 
                            (norma_tipo_id, norma_jerarquia_id, codigo, titulo, descripcion, ambito_tipo_id, vigente_desde, confidencial, tabla_estado_registro_id, empresa_id, creado_en, creado_por)
                            VALUES (1, {$jer}, '{$cod}', '{$tit}', '{$desc}', 1, {$fv}, {$conf}, 1, {$empresa_id}, CURRENT_TIMESTAMP, {$usuario_id})
                            ON DUPLICATE KEY UPDATE titulo = VALUES(titulo), descripcion = VALUES(descripcion)";
                    if (!mysqli_query($conexion, $sql)) {
                        throw new RuntimeException("Error al insertar norma {$cod}: " . mysqli_error($conexion));
                    }
                    $norma_id = mysqli_insert_id($conexion);
                    if ($norma_id > 0) {
                        // Crear versión inicial
                        $sql_v = "INSERT INTO ml__normas_versiones 
                                  (norma_id, version, fecha_publicacion, es_vigente, vigente_desde, tabla_estado_registro_id, creado_en, creado_por)
                                  VALUES ({$norma_id}, '1.0', {$fs}, 1, {$fv}, 1, CURRENT_TIMESTAMP, {$usuario_id})";
                        mysqli_query($conexion, $sql_v);
                    }
                    $insertados++;
                }
                break;

            case 'OBLIGACIONES':
                foreach ($filas as $d) {
                    $nc = mysqli_real_escape_string($conexion, $d['norma_codigo']);
                    $res_n = mysqli_query($conexion, "SELECT norma_id FROM ml__normas WHERE codigo = '{$nc}' AND empresa_id = {$empresa_id} LIMIT 1");
                    $norma = mysqli_fetch_assoc($res_n);
                    if (!$norma) continue;

                    $norma_id = (int)$norma['norma_id'];
                    $cod = mysqli_real_escape_string($conexion, $d['codigo']);
                    $desc = mysqli_real_escape_string($conexion, $d['descripcion']);
                    $anticipo = !empty($d['dias_anticipacion']) ? (int)$d['dias_anticipacion'] : 5;
                    $retencion = !empty($d['retencion_meses']) ? (int)$d['retencion_meses'] : 24;

                    $sql = "INSERT INTO ml__obligaciones 
                            (norma_id, codigo, descripcion, periodicidad_id, vencimiento_regla_id, criticidad_id, retencion_evidencias_meses, vigente_desde, tabla_estado_registro_id, empresa_id, creado_en, creado_por)
                            VALUES ({$norma_id}, '{$cod}', '{$desc}', 1, 1, 1, {$retencion}, CURRENT_DATE, 1, {$empresa_id}, CURRENT_TIMESTAMP, {$usuario_id})
                            ON DUPLICATE KEY UPDATE descripcion = VALUES(descripcion)";
                    if (!mysqli_query($conexion, $sql)) {
                        throw new RuntimeException("Error al insertar obligacion {$cod}: " . mysqli_error($conexion));
                    }
                    $insertados++;
                }
                break;

            case 'ENTIDADES':
                foreach ($filas as $d) {
                    $nom = mysqli_real_escape_string($conexion, $d['nombre']);
                    $cuit = mysqli_real_escape_string($conexion, $d['cuit'] ?? '');
                    
                    // Insertar entidad
                    $sql = "INSERT INTO ml__entidades (nombre, cuit, entidad_tipo_id, tabla_estado_registro_id, creado_en, creado_por)
                            VALUES ('{$nom}', '{$cuit}', 1, 1, CURRENT_TIMESTAMP, {$usuario_id})";
                    if (!mysqli_query($conexion, $sql)) {
                        throw new RuntimeException("Error al insertar entidad {$nom}: " . mysqli_error($conexion));
                    }
                    $entidad_id = mysqli_insert_id($conexion);
                    // Asociar con empresa
                    $sql_ee = "INSERT IGNORE INTO ml__entidades_empresas (entidad_id, empresa_id, creado_en, creado_por)
                               VALUES ({$entidad_id}, {$empresa_id}, CURRENT_TIMESTAMP, {$usuario_id})";
                    mysqli_query($conexion, $sql_ee);
                    $insertados++;
                }
                break;
        }

        // Marcar carga como confirmada (estado 4 = Confirmada)
        $sql_c_ok = "UPDATE ml__cargas 
                     SET confirmada_por = {$usuario_id},
                         confirmada_en = CURRENT_TIMESTAMP,
                         tabla_estado_registro_id = 4
                     WHERE carga_id = {$carga_id}";
        mysqli_query($conexion, $sql_c_ok);

        mysqli_commit($conexion);

        return [
            'success' => true,
            'registros_insertados' => $insertados,
            'message' => "Carga masiva #{$carga_id} confirmada. Se importaron exitosamente {$insertados} registros."
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return [
            'success' => false,
            'message' => 'Error durante la confirmación. Transacción revertida completamente: ' . $e->getMessage()
        ];
    }
}

/**
 * -------------------------------------------------------------------------
 * SECCIÓN 2: CAMBIOS MASIVOS Y VISTA PREVIA DE IMPACTO (CASO C35)
 * -------------------------------------------------------------------------
 */

/**
 * Simular impacto de cambio masivo sobre una norma/versión
 */
function cambios_masivos_simular_impacto(
    mysqli $conexion,
    int $norma_version_id,
    string $descripcion,
    int $empresa_id,
    int $usuario_id
): array {
    // 1. Obtener norma y obligaciones
    $sql_nv = "SELECT nv.norma_version_id, nv.version, n.norma_id, n.codigo, n.titulo as norma_titulo
               FROM ml__normas_versiones nv
               INNER JOIN ml__normas n ON n.norma_id = nv.norma_id
               WHERE nv.norma_version_id = {$norma_version_id} AND n.empresa_id = {$empresa_id}
               LIMIT 1";
    $res_nv = mysqli_query($conexion, $sql_nv);
    $nv = mysqli_fetch_assoc($res_nv);
    if (!$nv) {
        return ['success' => false, 'message' => 'Versión de norma no encontrada.'];
    }

    $norma_id = (int)$nv['norma_id'];

    // 2. Crear solicitud de cambio masivo (estado 1 = Pendiente Aprobación)
    $sql_cm = "INSERT INTO ml__cambios_masivos 
               (norma_version_id, descripcion, solicitado_por, requiere_aprobacion, tabla_estado_registro_id, creado_en, creado_por)
               VALUES (?, ?, ?, 1, 1, CURRENT_TIMESTAMP, ?)";
    $stmt_cm = mysqli_prepare($conexion, $sql_cm);
    mysqli_stmt_bind_param($stmt_cm, "isii", $norma_version_id, $descripcion, $usuario_id, $usuario_id);
    mysqli_stmt_execute($stmt_cm);
    $cambio_masivo_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt_cm);

    // 3. Calcular entidades afectadas y ciclos/tareas futuros
    $sql_ent = "SELECT 
                    oa.entidad_id,
                    e.nombre as entidad_nombre,
                    COUNT(DISTINCT c.ciclo_id) as ciclos_futuros,
                    COUNT(DISTINCT ct.ciclo_tarea_id) as tareas_futuras
                FROM ml__obligaciones_asignadas oa
                INNER JOIN ml__entidades e ON e.entidad_id = oa.entidad_id
                INNER JOIN ml__obligaciones o ON o.obligacion_id = oa.obligacion_prevaleciente_id
                LEFT JOIN ml__ciclos c ON c.obligacion_asignada_id = oa.obligacion_asignada_id 
                                       AND c.tabla_estado_registro_id IN (2069, 2070, 2071) -- Abiertos / futuros
                LEFT JOIN ml__ciclos_tareas ct ON ct.ciclo_id = c.ciclo_id
                WHERE o.norma_id = {$norma_id} AND oa.empresa_id = {$empresa_id}
                GROUP BY oa.entidad_id, e.nombre";

    $res_ent = mysqli_query($conexion, $sql_ent);
    $detalles = [];
    $total_entidades = 0;
    $total_ciclos = 0;
    $total_tareas = 0;

    if ($res_ent) {
        while ($row = mysqli_fetch_assoc($res_ent)) {
            $ent_id = (int)$row['entidad_id'];
            $c_afect = (int)$row['ciclos_futuros'];
            $t_afect = (int)$row['tareas_futuras'];

            $total_entidades++;
            $total_ciclos += $c_afect;
            $total_tareas += $t_afect;

            $sql_ins_det = "INSERT INTO ml__cambios_masivos_detalles 
                            (cambio_masivo_id, entidad_id, ciclos_afectados, tareas_afectadas, creado_en, creado_por)
                            VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP, ?)";
            $stmt_d = mysqli_prepare($conexion, $sql_ins_det);
            mysqli_stmt_bind_param($stmt_d, "iiiii", $cambio_masivo_id, $ent_id, $c_afect, $t_afect, $usuario_id);
            mysqli_stmt_execute($stmt_d);
            mysqli_stmt_close($stmt_d);

            $detalles[] = [
                'entidad_id' => $ent_id,
                'entidad_nombre' => $row['entidad_nombre'],
                'ciclos_afectados' => $c_afect,
                'tareas_afectadas' => $t_afect,
            ];
        }
    }

    return [
        'success' => true,
        'cambio_masivo_id' => $cambio_masivo_id,
        'norma' => "[{$nv['codigo']}] {$nv['norma_titulo']} (v{$nv['version']})",
        'total_entidades' => $total_entidades,
        'total_ciclos' => $total_ciclos,
        'total_tareas' => $total_tareas,
        'detalles' => $detalles,
        'message' => "Simulación de impacto calculada: {$total_entidades} entidades, {$total_ciclos} ciclos y {$total_tareas} tareas alcanzadas."
    ];
}

/**
 * Aprobar cambio masivo (Caso C35)
 */
function cambios_masivos_aprobar(mysqli $conexion, int $cambio_masivo_id, int $empresa_id, int $usuario_id): array {
    $sql = "UPDATE ml__cambios_masivos 
            SET aprobado_por = ?,
                aprobado_en = CURRENT_TIMESTAMP,
                tabla_estado_registro_id = 2 -- Aprobado
            WHERE cambio_masivo_id = ? AND tabla_estado_registro_id = 1";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $usuario_id, $cambio_masivo_id);
    $ok = mysqli_stmt_execute($stmt);
    $filas = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if ($ok && $filas > 0) {
        return ['success' => true, 'message' => "Cambio masivo #{$cambio_masivo_id} aprobado formalmente."];
    }
    return ['success' => false, 'message' => 'No se pudo aprobar el cambio (no existe o ya fue procesado).'];
}

/**
 * Aplicar cambio masivo y recalcular en cascada (Caso C35)
 */
function cambios_masivos_aplicar(mysqli $conexion, int $cambio_masivo_id, int $empresa_id, int $usuario_id): array {
    $res = mysqli_query($conexion, "SELECT * FROM ml__cambios_masivos WHERE cambio_masivo_id = {$cambio_masivo_id} LIMIT 1");
    $cm = mysqli_fetch_assoc($res);
    if (!$cm) {
        return ['success' => false, 'message' => 'Cambio masivo no encontrado.'];
    }

    if ((int)$cm['tabla_estado_registro_id'] === 3) {
        return ['success' => false, 'message' => 'Este cambio masivo ya fue aplicado previamente.'];
    }

    if ((int)$cm['requiere_aprobacion'] === 1 && (int)$cm['tabla_estado_registro_id'] !== 2) {
        return ['success' => false, 'message' => 'El cambio masivo requiere aprobación previa antes de aplicarse (Caso C35).'];
    }

    // Iniciar recálculo del motor de herencia para la empresa
    $res_her = motor_recalcular_empresa($conexion, $empresa_id, $usuario_id);

    // Marcar como aplicado
    $sql_up = "UPDATE ml__cambios_masivos 
               SET aplicado_en = CURRENT_TIMESTAMP,
                   tabla_estado_registro_id = 3 -- Aplicado
               WHERE cambio_masivo_id = {$cambio_masivo_id}";
    mysqli_query($conexion, $sql_up);

    return [
        'success' => true,
        'message' => "Cambio masivo #{$cambio_masivo_id} aplicado exitosamente con recálculo normativo en cascada."
    ];
}
