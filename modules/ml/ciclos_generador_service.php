<?php
/**
 * Servicio Central: Generador y Gestor de Ciclos y Tareas Operativas (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/ciclos_generador_service.php
 * Casos Cubiertos: C02, C03, C04, C05, C07, C10, C12, C14, C16, C22, C23, C24, C25, C27, C28, C29, C30, C31.
 */

declare(strict_types=1);

require_once __DIR__ . '/posiciones_model.php';

/**
 * Calcular la fecha de vencimiento según la regla de la obligación y ajustar por feriados locales.
 */
function ciclos_calcular_fecha_vencimiento(
    mysqli $conexion, 
    string $fecha_base, 
    array $regla, 
    int $empresa_id, 
    int $entidad_id, 
    int $territorio_pais_id = 1
): string {
    $tipo = $regla['tipo'] ?? 'CALENDARIO';
    $codigo = $regla['codigo'] ?? '';
    $desplazamiento = intval($regla['desplazamiento_dias'] ?? 0);

    $dt = new DateTime($fecha_base);

    switch ($codigo) {
        case 'FIN_MES':
            // Último día del mes de fecha_base
            $dt->modify('last day of this month');
            break;

        case 'DIA_FIJO_MES':
            // Día 15 del mes (o desplazamiento indicado)
            $dia = $desplazamiento > 0 ? $desplazamiento : 15;
            $max_dias = (int)$dt->format('t');
            $dt->setDate((int)$dt->format('Y'), (int)$dt->format('m'), min($dia, $max_dias));
            break;

        case 'CUIT_TERMINACION':
            // Caso C22: Según último dígito del CUIT de la empresa
            $res_cuit = mysqli_query($conexion, "SELECT documento_numero FROM conf__empresas WHERE empresa_id = {$empresa_id} LIMIT 1");
            $r_cuit = mysqli_fetch_assoc($res_cuit);
            $cuit = preg_replace('/\D/', '', (string)($r_cuit['documento_numero'] ?? '0'));
            $ultimo_digito = (int)substr($cuit, -1);
            // Vence entre el día 10 y el 19 según terminación
            $dia = 10 + $ultimo_digito;
            $max_dias = (int)$dt->format('t');
            $dt->setDate((int)$dt->format('Y'), (int)$dt->format('m'), min($dia, $max_dias));
            break;

        case 'FECHA_PROPIA_PERMISO':
            // Caso C23: Vencimiento a partir de permiso o certificado en ml__entidades_datos
            $sql_per = "SELECT ed.valor_fecha 
                        FROM ml__entidades_datos ed 
                        INNER JOIN ml__datos_tipos dt ON dt.dato_tipo_id = ed.dato_tipo_id 
                        WHERE ed.entidad_id = ? AND dt.codigo LIKE '%VENCIMIENTO%'
                        LIMIT 1";
            $stmt_per = mysqli_prepare($conexion, $sql_per);
            mysqli_stmt_bind_param($stmt_per, "i", $entidad_id);
            mysqli_stmt_execute($stmt_per);
            $res_per = mysqli_stmt_get_result($stmt_per);
            $r_per = mysqli_fetch_assoc($res_per);
            mysqli_stmt_close($stmt_per);

            if (!empty($r_per['valor_fecha'])) {
                $dt = new DateTime($r_per['valor_fecha']);
                if ($desplazamiento != 0) {
                    $dt->modify("{$desplazamiento} days");
                }
            } else {
                $dt->modify('last day of this month');
            }
            break;

        case 'DIAS_CORRIDOS_PUBLICACION':
            $dias = $desplazamiento > 0 ? $desplazamiento : 30;
            $dt->modify("+{$dias} days");
            break;

        default:
            $dt->modify('last day of this month');
            break;
    }

    // Ajuste por feriados y fines de semana (si cae sábado/domingo o feriado nacional, corre al siguiente hábil)
    $fecha_candidata = $dt->format('Y-m-d');
    $w = (int)$dt->format('N'); // 1 (lunes) a 7 (domingo)

    // Consultar feriados del país
    $sql_fer = "SELECT fecha FROM ml__feriados WHERE territorio_id = ? AND YEAR(fecha) = ? AND tabla_estado_registro_id = 1";
    $stmt_fer = mysqli_prepare($conexion, $sql_fer);
    $anio = (int)$dt->format('Y');
    mysqli_stmt_bind_param($stmt_fer, "ii", $territorio_pais_id, $anio);
    mysqli_stmt_execute($stmt_fer);
    $res_fer = mysqli_stmt_get_result($stmt_fer);
    $feriados = [];
    while ($rf = mysqli_fetch_assoc($res_fer)) {
        $feriados[$rf['fecha']] = true;
    }
    mysqli_stmt_close($stmt_fer);

    // Correr al próximo día hábil si es feriado o fin de semana
    while ($w >= 6 || isset($feriados[$fecha_candidata])) {
        $dt->modify('+1 day');
        $fecha_candidata = $dt->format('Y-m-d');
        $w = (int)$dt->format('N');
    }

    return $fecha_candidata . ' 18:00:00';
}

/**
 * Generar períodos proyectados a 12 meses vista para una obligación asignada.
 */
function ciclos_proyectar_periodos(string $cadencia, string $fecha_inicio_vigencia, int $meses_proyeccion = 12): array
{
    $periodos = [];
    $hoy = new DateTime();
    $inicio = new DateTime($fecha_inicio_vigencia);

    // Si la vigencia arranca a futuro, proyectar a partir de su inicio (Caso C10)
    $cursor = $inicio > $hoy ? clone $inicio : clone $hoy;
    $cursor->modify('first day of this month');

    $fin_proyeccion = clone $cursor;
    $fin_proyeccion->modify("+{$meses_proyeccion} months");

    $cadencia = strtoupper($cadencia);

    while ($cursor < $fin_proyeccion) {
        $p_desde = clone $cursor;
        $p_hasta = clone $cursor;

        switch ($cadencia) {
            case 'MENSUAL':
                $p_hasta->modify('last day of this month');
                $cursor->modify('+1 month');
                break;

            case 'TRIMESTRAL':
                $p_hasta->modify('+2 months')->modify('last day of this month');
                $cursor->modify('+3 months');
                break;

            case 'SEMESTRAL':
                $p_hasta->modify('+5 months')->modify('last day of this month');
                $cursor->modify('+6 months');
                break;

            case 'ANUAL':
                $p_hasta->modify('+11 months')->modify('last day of this month');
                $cursor->modify('+1 year');
                break;

            case 'QUINQUENAL':
                $p_hasta->modify('+59 months')->modify('last day of this month');
                $cursor->modify('+5 years');
                break;

            default:
                $p_hasta->modify('last day of this month');
                $cursor->modify('+1 month');
                break;
        }

        $periodos[] = [
            'periodo_desde' => $p_desde->format('Y-m-d'),
            'periodo_hasta' => $p_hasta->format('Y-m-d'),
            'fecha_base' => $p_hasta->format('Y-m-d')
        ];
    }

    return $periodos;
}

/**
 * Generar ciclos periódicos a 12 meses móvil para una obligación asignada individual.
 */
function ciclos_generar_para_obligacion_asignada(
    mysqli $conexion, 
    int $obligacion_asignada_id, 
    ?int $usuario_id = 1
): array {
    if (!$usuario_id) $usuario_id = 1;

    // Cargar datos de la asignación y su obligación prevaleciente
    $sql = "SELECT oas.obligacion_asignada_id, oas.empresa_id, oas.entidad_id, oas.vigente_desde, oas.vigente_hasta,
                   o.obligacion_id, o.norma_id, o.codigo AS obligacion_codigo,
                   p.codigo AS periodicidad_codigo, p.es_evento,
                   vr.codigo AS vencimiento_regla_codigo, vr.tipo AS vencimiento_regla_tipo, vr.desplazamiento_dias,
                   (SELECT nv.norma_version_id 
                    FROM ml__normas_versiones nv 
                    WHERE nv.norma_id = o.norma_id AND nv.tabla_estado_registro_id = 1 
                    ORDER BY nv.vigente_desde DESC LIMIT 1) AS norma_version_id
            FROM ml__obligaciones_asignadas oas
            INNER JOIN ml__obligaciones o ON o.obligacion_id = oas.obligacion_prevaleciente_id
            INNER JOIN ml__periodicidades p ON p.periodicidad_id = o.periodicidad_id
            INNER JOIN ml__vencimientos_reglas vr ON vr.vencimiento_regla_id = o.vencimiento_regla_id
            WHERE oas.obligacion_asignada_id = ?
              AND oas.tabla_estado_registro_id = 1";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $obligacion_asignada_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $asig = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$asig) {
        return ['success' => false, 'message' => 'Obligación asignada no encontrada o inactiva.'];
    }

    // Las obligaciones por evento no se proyectan periódicamente (Caso C07)
    if (!empty($asig['es_evento']) && intval($asig['es_evento']) === 1) {
        return ['success' => true, 'ciclos_creados' => 0, 'mensaje' => 'Obligación configurada por evento. Requiere disparo manual.'];
    }

    // Caso C10: Si la vigencia arranca a futuro, respetar fecha
    $fecha_vigencia = $asig['vigente_desde'];
    $periodos = ciclos_proyectar_periodos($asig['periodicidad_codigo'], $fecha_vigencia, 12);

    $regla = [
        'codigo' => $asig['vencimiento_regla_codigo'],
        'tipo' => $asig['vencimiento_regla_tipo'],
        'desplazamiento_dias' => $asig['desplazamiento_dias']
    ];

    $ciclos_creados = 0;

    foreach ($periodos as $p) {
        $p_desde = $p['periodo_desde'];
        $p_hasta = $p['periodo_hasta'];

        // Verificar si ya existe un ciclo para este período
        $sql_chk = "SELECT ciclo_id FROM ml__ciclos WHERE obligacion_asignada_id = ? AND periodo_desde = ?";
        $stmt_chk = mysqli_prepare($conexion, $sql_chk);
        mysqli_stmt_bind_param($stmt_chk, "is", $obligacion_asignada_id, $p_desde);
        mysqli_stmt_execute($stmt_chk);
        $res_chk = mysqli_stmt_get_result($stmt_chk);
        $existe = (bool)mysqli_fetch_assoc($res_chk);
        mysqli_stmt_close($stmt_chk);

        if ($existe) {
            continue;
        }

        // Calcular vencimiento
        $fecha_venc = ciclos_calcular_fecha_vencimiento($conexion, $p['fecha_base'], $regla, (int)$asig['empresa_id'], (int)$asig['entidad_id']);
        $fecha_ini = $p_desde . ' 08:00:00';
        $norma_ver = !empty($asig['norma_version_id']) ? intval($asig['norma_version_id']) : null;

        // Insertar Ciclo (Estado inicial: 2069 Pendiente)
        $sql_ins = "INSERT INTO ml__ciclos 
                    (obligacion_asignada_id, empresa_id, entidad_id, norma_version_id, periodo_desde, periodo_hasta, 
                     fecha_inicio, fecha_vencimiento, tabla_estado_registro_id, creado_en, creado_por)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 2069, CURRENT_TIMESTAMP, ?)";
        $stmt_ins = mysqli_prepare($conexion, $sql_ins);
        mysqli_stmt_bind_param($stmt_ins, "iiiissssi", 
            $obligacion_asignada_id, $asig['empresa_id'], $asig['entidad_id'], $norma_ver,
            $p_desde, $p_hasta, $fecha_ini, $fecha_venc, $usuario_id
        );
        mysqli_stmt_execute($stmt_ins);
        $nuevo_ciclo_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt_ins);

        if ($nuevo_ciclo_id > 0) {
            // Instanciar tareas operativas a partir de las plantillas (ml__tareas_plantillas)
            ciclos_instanciar_tareas($conexion, $nuevo_ciclo_id, (int)$asig['obligacion_id'], (int)$asig['empresa_id'], $fecha_ini, $fecha_venc, $usuario_id);
            $ciclos_creados++;
        }
    }

    return ['success' => true, 'ciclos_creados' => $ciclos_creados];
}

/**
 * Instanciar tareas operativas para un ciclo recién creado a partir de ml__tareas_plantillas.
 */
function ciclos_instanciar_tareas(
    mysqli $conexion, 
    int $ciclo_id, 
    int $obligacion_id, 
    int $empresa_id, 
    string $fecha_inicio_ciclo, 
    string $fecha_vencimiento_ciclo, 
    int $usuario_id
): int {
    $sql_tpl = "SELECT tp.tarea_plantilla_id, tp.orden, tp.titulo, tp.descripcion, 
                       tp.offset_dias_inicio, tp.offset_dias_vencimiento, tp.procedimiento_id,
                       o.requiere_revision, o.requiere_evidencia
                FROM ml__tareas_plantillas tp
                INNER JOIN ml__obligaciones o ON o.obligacion_id = tp.obligacion_id
                WHERE tp.obligacion_id = ? AND tp.tabla_estado_registro_id = 1
                ORDER BY tp.orden ASC";
    $stmt_tpl = mysqli_prepare($conexion, $sql_tpl);
    mysqli_stmt_bind_param($stmt_tpl, "i", $obligacion_id);
    mysqli_stmt_execute($stmt_tpl);
    $res_tpl = mysqli_stmt_get_result($stmt_tpl);
    $plantillas = mysqli_fetch_all($res_tpl, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_tpl);

    if (empty($plantillas)) {
        return 0;
    }

    $mapa_plantilla_tarea = [];
    $dt_ini_c = new DateTime($fecha_inicio_ciclo);
    $dt_venc_c = new DateTime($fecha_vencimiento_ciclo);

    // 1. Crear las tareas
    foreach ($plantillas as $tpl) {
        $tpl_id = intval($tpl['tarea_plantilla_id']);
        $off_ini = intval($tpl['offset_dias_inicio'] ?? 0);
        $off_venc = intval($tpl['offset_dias_vencimiento'] ?? 0);

        $t_ini = clone $dt_ini_c;
        if ($off_ini != 0) $t_ini->modify("{$off_ini} days");

        $t_venc = clone $dt_venc_c;
        if ($off_venc != 0) $t_venc->modify("{$off_venc} days");

        // Buscar versión vigente del procedimiento (estado 2059) si tiene procedimiento asociado
        $prc_ver_id = null;
        if (!empty($tpl['procedimiento_id'])) {
            $pid = intval($tpl['procedimiento_id']);
            $res_pv = mysqli_query($conexion, "SELECT procedimiento_version_id FROM ml__procedimientos_versiones WHERE procedimiento_id = {$pid} AND tabla_estado_registro_id = 2059 ORDER BY vigente_desde DESC LIMIT 1");
            if ($rpv = mysqli_fetch_assoc($res_pv)) {
                $prc_ver_id = intval($rpv['procedimiento_version_id']);
            } else {
                // Si no hay versión 2059 confirmada, asociar la última versión registrada
                $res_pv_fallback = mysqli_query($conexion, "SELECT procedimiento_version_id FROM ml__procedimientos_versiones WHERE procedimiento_id = {$pid} ORDER BY procedimiento_version_id DESC LIMIT 1");
                if ($rpv_fb = mysqli_fetch_assoc($res_pv_fallback)) {
                    $prc_ver_id = intval($rpv_fb['procedimiento_version_id']);
                }
            }
        }

        // Consultar si la plantilla tiene dependencias predecesoras
        $res_cdep = mysqli_query($conexion, "SELECT COUNT(*) AS total FROM ml__tareas_plantillas_dependencias WHERE tarea_plantilla_id = {$tpl_id}");
        $rcdep = mysqli_fetch_assoc($res_cdep);
        $tiene_deps = intval($rcdep['total'] ?? 0) > 0;

        // Estado inicial de tarea: Si tiene dependencias arranca Inactiva (2077); si no, Pendiente (2078)
        $estado_tarea = $tiene_deps ? 2077 : 2078;
        $f_act = $tiene_deps ? null : date('Y-m-d H:i:s');

        $sql_ct = "INSERT INTO ml__ciclos_tareas 
                   (ciclo_id, tarea_plantilla_id, titulo, descripcion, procedimiento_version_id, 
                    requiere_revision, requiere_evidencia, fecha_activacion, fecha_inicio, fecha_vencimiento, 
                    tabla_estado_registro_id, empresa_id, creado_en, creado_por)
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)";
        $stmt_ct = mysqli_prepare($conexion, $sql_ct);
        $f_ini_str = $t_ini->format('Y-m-d H:i:s');
        $f_venc_str = $t_venc->format('Y-m-d H:i:s');
        $rev = intval($tpl['requiere_revision'] ?? 0);
        $evi = intval($tpl['requiere_evidencia'] ?? 1);

        mysqli_stmt_bind_param($stmt_ct, "iissiiisssiii", 
            $ciclo_id, $tpl_id, $tpl['titulo'], $tpl['descripcion'], $prc_ver_id,
            $rev, $evi, $f_act, $f_ini_str, $f_venc_str,
            $estado_tarea, $empresa_id, $usuario_id
        );
        mysqli_stmt_execute($stmt_ct);
        $nuevo_cta_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt_ct);

        $mapa_plantilla_tarea[$tpl_id] = $nuevo_cta_id;
    }

    // 2. Mapear dependencias dinámicas entre las tareas instanciadas (ml__ciclos_tareas_dependencias)
    foreach ($plantillas as $tpl) {
        $tpl_id = intval($tpl['tarea_plantilla_id']);
        $cta_id = $mapa_plantilla_tarea[$tpl_id] ?? 0;
        if ($cta_id <= 0) continue;

        $sql_dep = "SELECT tarea_plantilla_predecesora_id, estado_activador_id, obligatoria 
                    FROM ml__tareas_plantillas_dependencias 
                    WHERE tarea_plantilla_id = ?";
        $stmt_dep = mysqli_prepare($conexion, $sql_dep);
        mysqli_stmt_bind_param($stmt_dep, "i", $tpl_id);
        mysqli_stmt_execute($stmt_dep);
        $res_dep = mysqli_stmt_get_result($stmt_dep);

        while ($rd = mysqli_fetch_assoc($res_dep)) {
            $tpl_pred_id = intval($rd['tarea_plantilla_predecesora_id']);
            $cta_pred_id = $mapa_plantilla_tarea[$tpl_pred_id] ?? 0;

            if ($cta_pred_id > 0) {
                $est_act = intval($rd['estado_activador_id'] ?? 2081);
                $obl = intval($rd['obligatoria'] ?? 1);

                $sql_ins_dep = "INSERT INTO ml__ciclos_tareas_dependencias 
                                (ciclo_tarea_id, ciclo_tarea_predecesora_id, estado_activador_id, obligatoria, empresa_id, creado_en, creado_por)
                                VALUES (?, ?, ?, ?, ?, CURRENT_TIMESTAMP, ?)";
                $stmt_idep = mysqli_prepare($conexion, $sql_ins_dep);
                mysqli_stmt_bind_param($stmt_idep, "iiiiii", $cta_id, $cta_pred_id, $est_act, $obl, $empresa_id, $usuario_id);
                mysqli_stmt_execute($stmt_idep);
                mysqli_stmt_close($stmt_idep);
            }
        }
        mysqli_stmt_close($stmt_dep);
    }

    // 3. Instanciar roles RACI dinámicos vinculados a la posición y supervisor (ml__ciclos_tareas_raci)
    foreach ($plantillas as $tpl) {
        $tpl_id = intval($tpl['tarea_plantilla_id']);
        $cta_id = $mapa_plantilla_tarea[$tpl_id] ?? 0;
        if ($cta_id <= 0) continue;

        // Consultar roles RACI definidos en la plantilla
        $sql_raci = "SELECT tpr.posicion_id, tpr.raci_rol_id, tpr.usar_supervisor_como_aprobador
                     FROM ml__tareas_plantillas_raci tpr
                     WHERE tpr.tarea_plantilla_id = ? AND tpr.tabla_estado_registro_id = 1
                     ORDER BY tpr.raci_rol_id ASC";
        $stmt_r = mysqli_prepare($conexion, $sql_raci);
        mysqli_stmt_bind_param($stmt_r, "i", $tpl_id);
        mysqli_stmt_execute($stmt_r);
        $res_r = mysqli_stmt_get_result($stmt_r);
        $roles_plantilla = mysqli_fetch_all($res_r, MYSQLI_ASSOC);
        mysqli_stmt_close($stmt_r);

        // Si la plantilla no tiene roles, buscar en ml__obligaciones_asignadas_raci por ciclo
        if (empty($roles_plantilla)) {
            $sql_oa_r = "SELECT oar.posicion_id, oar.raci_rol_id, oar.usuario_id
                         FROM ml__obligaciones_asignadas_raci oar
                         INNER JOIN ml__ciclos c ON c.obligacion_asignada_id = oar.obligacion_asignada_id
                         WHERE c.ciclo_id = ? AND oar.tabla_estado_registro_id = 1";
            $stmt_oar = mysqli_prepare($conexion, $sql_oa_r);
            mysqli_stmt_bind_param($stmt_oar, "i", $ciclo_id);
            mysqli_stmt_execute($stmt_oar);
            $res_oar = mysqli_stmt_get_result($stmt_oar);
            while ($ro = mysqli_fetch_assoc($res_oar)) {
                $pos_id = !empty($ro['posicion_id']) ? intval($ro['posicion_id']) : null;
                $u_id = intval($ro['usuario_id']);
                if ($u_id > 0) {
                    $sql_ins_r = "INSERT INTO ml__ciclos_tareas_raci 
                                  (ciclo_tarea_id, raci_rol_id, posicion_id, usuario_id, empresa_id, vigente_desde, tabla_estado_registro_id, creado_en, creado_por)
                                  VALUES (?, ?, ?, ?, ?, CURRENT_DATE, 1, CURRENT_TIMESTAMP, ?)";
                    $stmt_ir = mysqli_prepare($conexion, $sql_ins_r);
                    mysqli_stmt_bind_param($stmt_ir, "iiiiii", $cta_id, $ro['raci_rol_id'], $pos_id, $u_id, $empresa_id, $usuario_id);
                    mysqli_stmt_execute($stmt_ir);
                    mysqli_stmt_close($stmt_ir);
                }
            }
            mysqli_stmt_close($stmt_oar);
            continue;
        }

        // Si hay roles configurados en la plantilla:
        // Primero localizamos la posición del Responsable (R = 1) por si el rol Aprobador (A = 2) usa al supervisor
        $posicion_responsable_id = null;
        foreach ($roles_plantilla as $rp) {
            if (intval($rp['raci_rol_id']) === 1) {
                $posicion_responsable_id = intval($rp['posicion_id']);
                break;
            }
        }

        foreach ($roles_plantilla as $rp) {
            $raci_rol_id = intval($rp['raci_rol_id']);
            $pos_id = intval($rp['posicion_id']);
            $usar_sup = intval($rp['usar_supervisor_como_aprobador'] ?? 0);
            $dest_posicion_id = $pos_id;
            $dest_usuario_id = null;

            // Si es Aprobador (rol 2) y usa supervisor:
            if ($raci_rol_id === 2 && $usar_sup === 1) {
                $pos_base = $posicion_responsable_id ?: $pos_id;
                $sup_info = posicion_obtener_supervisor_de_posicion($conexion, $pos_base);
                if ($sup_info && !empty($sup_info['superior_posicion_id'])) {
                    $dest_posicion_id = intval($sup_info['superior_posicion_id']);
                    if (!empty($sup_info['supervisor_id'])) {
                        $dest_usuario_id = intval($sup_info['supervisor_id']);
                    }
                }
            }

            // Si aún no tenemos usuario resuelto, resolver el titular activo de la posición
            if (!$dest_usuario_id && $dest_posicion_id > 0) {
                $titular = posicion_obtener_titular($conexion, $dest_posicion_id);
                if ($titular && !empty($titular['usuario_id'])) {
                    $dest_usuario_id = intval($titular['usuario_id']);
                }
            }

            // Si encontramos titular u ocupante, insertamos en ml__ciclos_tareas_raci
            if ($dest_usuario_id && $dest_usuario_id > 0) {
                $sql_ins_r = "INSERT INTO ml__ciclos_tareas_raci 
                              (ciclo_tarea_id, raci_rol_id, posicion_id, usuario_id, empresa_id, vigente_desde, tabla_estado_registro_id, creado_en, creado_por)
                              VALUES (?, ?, ?, ?, ?, CURRENT_DATE, 1, CURRENT_TIMESTAMP, ?)";
                $stmt_ir = mysqli_prepare($conexion, $sql_ins_r);
                mysqli_stmt_bind_param($stmt_ir, "iiiiii", $cta_id, $raci_rol_id, $dest_posicion_id, $dest_usuario_id, $empresa_id, $usuario_id);
                mysqli_stmt_execute($stmt_ir);
                mysqli_stmt_close($stmt_ir);
            }
        }
    }

    return count($plantillas);
}

/**
 * Transición de estado en Tarea Operativa con disparo de activación de tareas sucesoras en cascada.
 */
function ciclos_tarea_cambiar_estado(mysqli $conexion, int $ciclo_tarea_id, int $nuevo_estado, int $usuario_id): array
{
    $sql_t = "SELECT ct.ciclo_tarea_id, ct.ciclo_id, ct.tabla_estado_registro_id, ct.fecha_vencimiento, c.empresa_id
              FROM ml__ciclos_tareas ct
              INNER JOIN ml__ciclos c ON c.ciclo_id = ct.ciclo_id
              WHERE ct.ciclo_tarea_id = ?";
    $stmt_t = mysqli_prepare($conexion, $sql_t);
    mysqli_stmt_bind_param($stmt_t, "i", $ciclo_tarea_id);
    mysqli_stmt_execute($stmt_t);
    $res_t = mysqli_stmt_get_result($stmt_t);
    $tarea = mysqli_fetch_assoc($res_t);
    mysqli_stmt_close($stmt_t);

    if (!$tarea) {
        return ['success' => false, 'message' => 'Tarea no encontrada.'];
    }

    $ciclo_id = intval($tarea['ciclo_id']);
    $f_cierre = in_array($nuevo_estado, [2081, 2083], true) ? date('Y-m-d H:i:s') : null;

    // Actualizar tarea
    $sql_upd = "UPDATE ml__ciclos_tareas 
                SET tabla_estado_registro_id = ?, 
                    fecha_cierre = COALESCE(?, fecha_cierre), 
                    actualizado_en = CURRENT_TIMESTAMP, 
                    actualizado_por = ?
                WHERE ciclo_tarea_id = ?";
    $stmt_upd = mysqli_prepare($conexion, $sql_upd);
    mysqli_stmt_bind_param($stmt_upd, "isii", $nuevo_estado, $f_cierre, $usuario_id, $ciclo_tarea_id);
    mysqli_stmt_execute($stmt_upd);
    mysqli_stmt_close($stmt_upd);

    // Si la tarea pasó a Cumplida (2081), verificar si activa tareas sucesoras
    if ($nuevo_estado === 2081) {
        ciclos_activar_sucesoras_cascada($conexion, $ciclo_tarea_id, $usuario_id);
    }

    // Actualizar estado del Ciclo contenedor
    ciclos_actualizar_estado_ciclo($conexion, $ciclo_id, $usuario_id);

    return ['success' => true, 'message' => 'Estado de la tarea actualizado con éxito.'];
}

/**
 * Activar en cascada las tareas sucesoras que esperaban que esta tarea se cumpliera.
 */
function ciclos_activar_sucesoras_cascada(mysqli $conexion, int $tarea_predecesora_id, int $usuario_id): int
{
    // Buscar tareas sucesoras de esta predecesora
    $sql_suc = "SELECT DISTINCT ctd.ciclo_tarea_id 
                FROM ml__ciclos_tareas_dependencias ctd
                INNER JOIN ml__ciclos_tareas ct ON ct.ciclo_tarea_id = ctd.ciclo_tarea_id
                WHERE ctd.ciclo_tarea_predecesora_id = ?
                  AND ct.tabla_estado_registro_id = 2077"; // Inactiva
    $stmt_suc = mysqli_prepare($conexion, $sql_suc);
    mysqli_stmt_bind_param($stmt_suc, "i", $tarea_predecesora_id);
    mysqli_stmt_execute($stmt_suc);
    $res_suc = mysqli_stmt_get_result($stmt_suc);
    $sucesoras = mysqli_fetch_all($res_suc, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt_suc);

    $activadas = 0;

    foreach ($sucesoras as $s) {
        $sid = intval($s['ciclo_tarea_id']);

        // Verificar si todas sus dependencias obligatorias ya están cumplidas
        $sql_chk = "SELECT COUNT(*) AS pendientes
                    FROM ml__ciclos_tareas_dependencias ctd
                    INNER JOIN ml__ciclos_tareas ct_pred ON ct_pred.ciclo_tarea_id = ctd.ciclo_tarea_predecesora_id
                    WHERE ctd.ciclo_tarea_id = ?
                      AND ctd.obligatoria = 1
                      AND ct_pred.tabla_estado_registro_id != ctd.estado_activador_id";
        $stmt_chk = mysqli_prepare($conexion, $sql_chk);
        mysqli_stmt_bind_param($stmt_chk, "i", $sid);
        mysqli_stmt_execute($stmt_chk);
        $res_chk = mysqli_stmt_get_result($stmt_chk);
        $r_chk = mysqli_fetch_assoc($res_chk);
        mysqli_stmt_close($stmt_chk);

        if (intval($r_chk['pendientes'] ?? 0) === 0) {
            // Activar a Pendiente (2078)
            $sql_act = "UPDATE ml__ciclos_tareas 
                        SET tabla_estado_registro_id = 2078, 
                            fecha_activacion = CURRENT_TIMESTAMP, 
                            actualizado_en = CURRENT_TIMESTAMP, 
                            actualizado_por = ?
                        WHERE ciclo_tarea_id = ?";
            $stmt_act = mysqli_prepare($conexion, $sql_act);
            mysqli_stmt_bind_param($stmt_act, "ii", $usuario_id, $sid);
            mysqli_stmt_execute($stmt_act);
            mysqli_stmt_close($stmt_act);
            $activadas++;
        }
    }

    return $activadas;
}

/**
 * Recalcular y sincronizar automáticamente el estado del ciclo según sus tareas y fechas.
 */
function ciclos_actualizar_estado_ciclo(mysqli $conexion, int $ciclo_id, int $usuario_id): void
{
    $sql = "SELECT c.tabla_estado_registro_id, c.fecha_vencimiento,
                   (SELECT COUNT(*) FROM ml__ciclos_tareas ct WHERE ct.ciclo_id = c.ciclo_id) AS total_tareas,
                   (SELECT COUNT(*) FROM ml__ciclos_tareas ct WHERE ct.ciclo_id = c.ciclo_id AND ct.tabla_estado_registro_id = 2081) AS cumplidas,
                   (SELECT COUNT(*) FROM ml__ciclos_tareas ct WHERE ct.ciclo_id = c.ciclo_id AND ct.tabla_estado_registro_id IN (2079, 2080)) AS en_curso
            FROM ml__ciclos c
            WHERE c.ciclo_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $ciclo_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $c = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$c) return;

    $total = intval($c['total_tareas'] ?? 0);
    $cumplidas = intval($c['cumplidas'] ?? 0);
    $en_curso = intval($c['en_curso'] ?? 0);
    $estado_actual = intval($c['tabla_estado_registro_id']);

    // Si ya está cerrado o eximido o cancelado manualmente, no modificar
    if (in_array($estado_actual, [2072, 2073, 2075, 2076], true)) {
        return;
    }

    $hoy = date('Y-m-d H:i:s');
    $vencimiento = $c['fecha_vencimiento'];

    if ($total > 0 && $cumplidas === $total) {
        // Todas las tareas cumplidas: si a término -> 2072 Cumplido, si vencido -> 2073 Cumplido fuera de plazo
        $nuevo_estado = ($hoy <= $vencimiento) ? 2072 : 2073;
        $sql_c = "UPDATE ml__ciclos SET tabla_estado_registro_id = ?, fecha_cierre = NOW(), actualizado_en = NOW(), actualizado_por = ? WHERE ciclo_id = ?";
        $stmt_c = mysqli_prepare($conexion, $sql_c);
        mysqli_stmt_bind_param($stmt_c, "iii", $nuevo_estado, $usuario_id, $ciclo_id);
        mysqli_stmt_execute($stmt_c);
        mysqli_stmt_close($stmt_c);
    } elseif ($en_curso > 0 && $estado_actual === 2069) {
        // Al menos una tarea en curso -> ciclo en curso (2070)
        $sql_c = "UPDATE ml__ciclos SET tabla_estado_registro_id = 2070, actualizado_en = NOW(), actualizado_por = ? WHERE ciclo_id = ?";
        $stmt_c = mysqli_prepare($conexion, $sql_c);
        mysqli_stmt_bind_param($stmt_c, "ii", $usuario_id, $ciclo_id);
        mysqli_stmt_execute($stmt_c);
        mysqli_stmt_close($stmt_c);
    }
}

/**
 * Disparar ciclo por evento (Caso C07: registrar incidente y medir plazo en horas según huso horario local C31).
 */
function ciclos_disparar_evento(mysqli $conexion, array $datos, int $usuario_id): array
{
    $obligacion_asignada_id = intval($datos['obligacion_asignada_id'] ?? 0);
    $descripcion_evento = trim($datos['descripcion'] ?? '');
    $plazo_horas = isset($datos['plazo_horas']) && intval($datos['plazo_horas']) > 0 ? intval($datos['plazo_horas']) : 24;

    if ($obligacion_asignada_id <= 0 || empty($descripcion_evento)) {
        return ['success' => false, 'message' => 'Debe indicar la obligación asignada y la descripción del evento ocurrido.'];
    }

    $sql = "SELECT oas.empresa_id, oas.entidad_id, oas.obligacion_prevaleciente_id,
                   o.codigo AS obligacion_codigo, o.descripcion AS obligacion_descripcion,
                   t.huso_horario
            FROM ml__obligaciones_asignadas oas
            INNER JOIN ml__obligaciones o ON o.obligacion_id = oas.obligacion_prevaleciente_id
            INNER JOIN ml__entidades e ON e.entidad_id = oas.entidad_id
            LEFT JOIN ml__entidades_ubicaciones eu ON eu.entidad_id = e.entidad_id AND eu.es_base = 1
            LEFT JOIN ml__territorios t ON t.territorio_id = eu.territorio_id
            WHERE oas.obligacion_asignada_id = ? AND oas.tabla_estado_registro_id = 1";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $obligacion_asignada_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $asig = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$asig) {
        return ['success' => false, 'message' => 'Obligación asignada no encontrada o inactiva.'];
    }

    // Caso C31: Huso horario local de la entidad
    $tz_str = !empty($asig['huso_horario']) ? $asig['huso_horario'] : 'America/Argentina/Buenos_Aires';
    try {
        $tz = new DateTimeZone($tz_str);
    } catch (\Throwable $e) {
        $tz = new DateTimeZone('America/Argentina/Buenos_Aires');
    }

    $ahora = new DateTime('now', $tz);
    $f_ini = $ahora->format('Y-m-d H:i:s');
    $venc = clone $ahora;
    $venc->modify("+{$plazo_horas} hours");
    $f_venc = $venc->format('Y-m-d H:i:s');

    $p_desde = $ahora->format('Y-m-d');
    $p_hasta = $venc->format('Y-m-d');

    $sql_ins = "INSERT INTO ml__ciclos 
                (obligacion_asignada_id, empresa_id, entidad_id, periodo_desde, periodo_hasta, 
                 fecha_inicio, fecha_vencimiento, observaciones, tabla_estado_registro_id, creado_en, creado_por)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 2070, CURRENT_TIMESTAMP, ?)";
    $stmt_ins = mysqli_prepare($conexion, $sql_ins);
    $obs = "[EVENTO REGISTRADO] {$descripcion_evento} (Plazo {$plazo_horas}h en huso {$tz_str})";
    mysqli_stmt_bind_param($stmt_ins, "iiisssssi", 
        $obligacion_asignada_id, $asig['empresa_id'], $asig['entidad_id'],
        $p_desde, $p_hasta, $f_ini, $f_venc, $obs, $usuario_id
    );
    $ok = mysqli_stmt_execute($stmt_ins);
    $nuevo_ciclo_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt_ins);

    if (!$ok || $nuevo_ciclo_id <= 0) {
        return ['success' => false, 'message' => 'Error al registrar ciclo por evento.'];
    }

    // Instanciar tareas operativas para este ciclo
    ciclos_instanciar_tareas($conexion, $nuevo_ciclo_id, (int)$asig['obligacion_prevaleciente_id'], (int)$asig['empresa_id'], $f_ini, $f_venc, $usuario_id);

    return [
        'success' => true,
        'ciclo_id' => $nuevo_ciclo_id,
        'fecha_vencimiento' => $f_venc,
        'huso_horario' => $tz_str,
        'message' => "Ciclo por evento registrado con éxito. Plazo de {$plazo_horas}h fijado al {$f_venc}."
    ];
}

/**
 * Crear Ciclo Complementario para subsanar un error en un ciclo cerrado (Caso C28).
 * Mantiene el ciclo original intacto e inmutable.
 */
function ciclos_crear_complementario(mysqli $conexion, int $ciclo_origen_id, string $motivo, int $usuario_id): array
{
    $sql_orig = "SELECT c.*, oas.obligacion_prevaleciente_id 
                 FROM ml__ciclos c
                 INNER JOIN ml__obligaciones_asignadas oas ON oas.obligacion_asignada_id = c.obligacion_asignada_id
                 WHERE c.ciclo_id = ?";
    $stmt_o = mysqli_prepare($conexion, $sql_orig);
    mysqli_stmt_bind_param($stmt_o, "i", $ciclo_origen_id);
    mysqli_stmt_execute($stmt_o);
    $res_o = mysqli_stmt_get_result($stmt_o);
    $orig = mysqli_fetch_assoc($res_o);
    mysqli_stmt_close($stmt_o);

    if (!$orig) {
        return ['success' => false, 'message' => 'Ciclo original no encontrado.'];
    }

    $obs = "[COMPLEMENTARIO de Ciclo #{$ciclo_origen_id}] Motivo de subsanación: {$motivo}";
    $f_ini = date('Y-m-d H:i:s');
    $dt_venc = new DateTime();
    $dt_venc->modify('+15 days');
    $f_venc = $dt_venc->format('Y-m-d 18:00:00');

    // Ciclo vinculado tipo 1: COMPLEMENTARIO
    $sql_ins = "INSERT INTO ml__ciclos 
                (obligacion_asignada_id, empresa_id, entidad_id, norma_version_id, ciclo_origen_id, ciclo_vinculo_tipo_id, 
                 periodo_desde, periodo_hasta, fecha_inicio, fecha_vencimiento, observaciones, tabla_estado_registro_id, creado_en, creado_por)
                VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?, 2069, CURRENT_TIMESTAMP, ?)";
    $stmt_ins = mysqli_prepare($conexion, $sql_ins);
    mysqli_stmt_bind_param($stmt_ins, "iiiiisssssi", 
        $orig['obligacion_asignada_id'], $orig['empresa_id'], $orig['entidad_id'], $orig['norma_version_id'],
        $ciclo_origen_id, $orig['periodo_desde'], $orig['periodo_hasta'],
        $f_ini, $f_venc, $obs, $usuario_id
    );
    mysqli_stmt_execute($stmt_ins);
    $nuevo_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt_ins);

    if ($nuevo_id > 0) {
        ciclos_instanciar_tareas($conexion, $nuevo_id, (int)$orig['obligacion_prevaleciente_id'], (int)$orig['empresa_id'], $f_ini, $f_venc, $usuario_id);
        return ['success' => true, 'ciclo_id' => $nuevo_id, 'message' => 'Ciclo complementario creado exitosamente sin alterar el ciclo de origen.'];
    }

    return ['success' => false, 'message' => 'Error al crear ciclo complementario.'];
}

/**
 * Crear Ciclo de Seguimiento por hallazgo de auditoría (Caso C29).
 */
function ciclos_crear_seguimiento(mysqli $conexion, int $ciclo_origen_id, string $plan_accion, int $usuario_id): array
{
    $sql_orig = "SELECT c.*, oas.obligacion_prevaleciente_id 
                 FROM ml__ciclos c
                 INNER JOIN ml__obligaciones_asignadas oas ON oas.obligacion_asignada_id = c.obligacion_asignada_id
                 WHERE c.ciclo_id = ?";
    $stmt_o = mysqli_prepare($conexion, $sql_orig);
    mysqli_stmt_bind_param($stmt_o, "i", $ciclo_origen_id);
    mysqli_stmt_execute($stmt_o);
    $res_o = mysqli_stmt_get_result($stmt_o);
    $orig = mysqli_fetch_assoc($res_o);
    mysqli_stmt_close($stmt_o);

    if (!$orig) {
        return ['success' => false, 'message' => 'Ciclo original no encontrado.'];
    }

    $obs = "[SEGUIMIENTO de Auditoría de Ciclo #{$ciclo_origen_id}] Plan de acción: {$plan_accion}";
    $f_ini = date('Y-m-d H:i:s');
    $dt_venc = new DateTime();
    $dt_venc->modify('+30 days');
    $f_venc = $dt_venc->format('Y-m-d 18:00:00');

    // Ciclo vinculado tipo 2: SEGUIMIENTO
    $sql_ins = "INSERT INTO ml__ciclos 
                (obligacion_asignada_id, empresa_id, entidad_id, norma_version_id, ciclo_origen_id, ciclo_vinculo_tipo_id, 
                 periodo_desde, periodo_hasta, fecha_inicio, fecha_vencimiento, observaciones, tabla_estado_registro_id, creado_en, creado_por)
                VALUES (?, ?, ?, ?, ?, 2, ?, ?, ?, ?, ?, 2069, CURRENT_TIMESTAMP, ?)";
    $stmt_ins = mysqli_prepare($conexion, $sql_ins);
    mysqli_stmt_bind_param($stmt_ins, "iiiiisssssi", 
        $orig['obligacion_asignada_id'], $orig['empresa_id'], $orig['entidad_id'], $orig['norma_version_id'],
        $ciclo_origen_id, $orig['periodo_desde'], $orig['periodo_hasta'],
        $f_ini, $f_venc, $obs, $usuario_id
    );
    mysqli_stmt_execute($stmt_ins);
    $nuevo_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt_ins);

    if ($nuevo_id > 0) {
        // Crear tarea específica del plan de acción
        $sql_ct = "INSERT INTO ml__ciclos_tareas 
                   (ciclo_id, titulo, descripcion, requiere_revision, requiere_evidencia, 
                    fecha_activacion, fecha_inicio, fecha_vencimiento, tabla_estado_registro_id, empresa_id, creado_en, creado_por)
                   VALUES (?, 'Ejecución de Plan de Acción de Auditoría', ?, 1, 1, NOW(), NOW(), ?, 2078, ?, CURRENT_TIMESTAMP, ?)";
        $stmt_ct = mysqli_prepare($conexion, $sql_ct);
        mysqli_stmt_bind_param($stmt_ct, "issii", $nuevo_id, $plan_accion, $f_venc, $orig['empresa_id'], $usuario_id);
        mysqli_stmt_execute($stmt_ct);
        mysqli_stmt_close($stmt_ct);

        return ['success' => true, 'ciclo_id' => $nuevo_id, 'message' => 'Ciclo de seguimiento de auditoría creado con éxito.'];
    }

    return ['success' => false, 'message' => 'Error al crear ciclo de seguimiento.'];
}

/**
 * Reasignar en bloque todas las tareas activas de un responsable a otro usuario (Caso C27).
 * Se dispara cuando un responsable deja la empresa o cambia de puesto.
 */
function ciclos_reasignar_responsable_bloque(
    mysqli $conexion, 
    int $empresa_id, 
    int $usuario_origen_id, 
    int $usuario_destino_id, 
    string $motivo, 
    int $usuario_aplicador_id
): array {
    if ($usuario_origen_id <= 0 || $usuario_destino_id <= 0 || $usuario_origen_id === $usuario_destino_id) {
        return ['success' => false, 'message' => 'Debe indicar un usuario de origen y un usuario de destino diferentes.'];
    }

    // Reasignar tareas activas (no cumplidas ni canceladas) en ml__ciclos_tareas_raci
    $sql_reas = "UPDATE ml__ciclos_tareas_raci ctar
                 INNER JOIN ml__ciclos_tareas ct ON ct.ciclo_tarea_id = ctar.ciclo_tarea_id
                 SET ctar.usuario_id = ?, ctar.actualizado_en = CURRENT_TIMESTAMP, ctar.actualizado_por = ?
                 WHERE ctar.empresa_id = ?
                   AND ctar.usuario_id = ?
                   AND ct.tabla_estado_registro_id NOT IN (2081, 2083)";
    $stmt = mysqli_prepare($conexion, $sql_reas);
    mysqli_stmt_bind_param($stmt, "iiii", $usuario_destino_id, $usuario_aplicador_id, $empresa_id, $usuario_origen_id);
    $ok = mysqli_stmt_execute($stmt);
    $total_afectadas = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    // Registrar en log histórico ml__reasignaciones
    $sql_log = "INSERT INTO ml__reasignaciones 
                (usuario_origen_id, usuario_destino_id, fecha_efecto, motivo, aplicada_por, aplicada_en, tabla_estado_registro_id, creado_en, creado_por)
                VALUES (?, ?, CURRENT_DATE, ?, ?, NOW(), 1, CURRENT_TIMESTAMP, ?)";
    $stmt_log = mysqli_prepare($conexion, $sql_log);
    mysqli_stmt_bind_param($stmt_log, "iisii", $usuario_origen_id, $usuario_destino_id, $motivo, $usuario_aplicador_id, $usuario_aplicador_id);
    mysqli_stmt_execute($stmt_log);
    $reasignacion_id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt_log);

    return [
        'success' => true,
        'reasignacion_id' => $reasignacion_id,
        'tareas_reasignadas' => max(0, $total_afectadas),
        'message' => "Reasignación en bloque ejecutada con éxito. Total de asignaciones transferidas: {$total_afectadas}."
    ];
}
