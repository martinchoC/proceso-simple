<?php
/**
 * Servicio Central: Motor de Herencia y Consolidación de Solapamientos (Módulo 3 - Matriz Legal)
 * Archivo: modules/ml/motor_herencia_service.php
 * Casos Cubiertos: C01, C05, C06, C08, C09, C11, C13, C19, C20, C25, C35.
 */

declare(strict_types=1);

/**
 * Obtener todos los territorios ancestros de un territorio (árbol hacia arriba hasta la raíz),
 * incluyendo pertenencias a bloques regionales y supranacionales (Caso C01).
 */
function motor_territorios_ancestros(mysqli $conexion, int $territorio_id, ?string $fecha = null): array
{
    if ($territorio_id <= 0) return [];
    if (!$fecha) $fecha = date('Y-m-d');

    $ancestros = [$territorio_id => $territorio_id];
    $pendientes = [$territorio_id];

    while (!empty($pendientes)) {
        $actual = array_pop($pendientes);

        $sql = "SELECT territorio_superior_id 
                FROM ml__territorios_pertenencias 
                WHERE territorio_id = ?
                  AND vigente_desde <= ?
                  AND (vigente_hasta IS NULL OR vigente_hasta >= ?)";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "iss", $actual, $fecha, $fecha);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);

        while ($row = mysqli_fetch_assoc($res)) {
            $sup_id = intval($row['territorio_superior_id']);
            if ($sup_id > 0 && !isset($ancestros[$sup_id])) {
                $ancestros[$sup_id] = $sup_id;
                $pendientes[] = $sup_id;
            }
        }
        mysqli_stmt_close($stmt);
    }

    // Asegurar siempre territorio 1 (Mundo) si no está presente
    $ancestros[1] = 1;

    return array_values($ancestros);
}

/**
 * Obtener todos los territorios donde opera una entidad:
 * ubicación base (es_base = 1) más países de operación si es móvil (Caso C06).
 */
function motor_entidad_ubicaciones(mysqli $conexion, int $entidad_id, ?string $fecha = null): array
{
    if (!$fecha) $fecha = date('Y-m-d');

    $sql = "SELECT territorio_id, es_base 
            FROM ml__entidades_ubicaciones 
            WHERE entidad_id = ?
              AND vigente_desde <= ?
              AND (vigente_hasta IS NULL OR vigente_hasta >= ?)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iss", $entidad_id, $fecha, $fecha);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    return $rows;
}

/**
 * Obtener el país soberano correspondiente a un territorio (para validaciones locales C21).
 */
function motor_obtener_pais_territorio(mysqli $conexion, int $territorio_id): int
{
    $actual = $territorio_id;
    for ($i = 0; $i < 6; $i++) {
        $sql = "SELECT t.territorio_id, t.territorio_tipo_id, tp.territorio_superior_id
                FROM ml__territorios t
                LEFT JOIN ml__territorios_pertenencias tp ON tp.territorio_id = t.territorio_id AND (tp.vigente_hasta IS NULL)
                WHERE t.territorio_id = ? LIMIT 1";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "i", $actual);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if (!$row) break;
        // Tipo 4 suele ser PAIS en ml__territorios_tipos
        if (intval($row['territorio_tipo_id']) === 4) {
            return intval($row['territorio_id']);
        }
        if (empty($row['territorio_superior_id'])) break;
        $actual = intval($row['territorio_superior_id']);
    }
    return $territorio_id;
}

/**
 * Obtener la estructura corporativa de una empresa: grupo y región del grupo.
 */
function motor_empresa_estructura(mysqli $conexion, int $empresa_id, ?string $fecha = null): array
{
    if (!$fecha) $fecha = date('Y-m-d');

    $sql = "SELECT er.empresa_id, er.region_grupo_id, rg.grupo_id, rg.nombre AS region_nombre, g.nombre AS grupo_nombre
            FROM ml__empresas_regiones er
            INNER JOIN ml__regiones_grupo rg ON rg.region_grupo_id = er.region_grupo_id
            INNER JOIN ml__grupos g ON g.grupo_id = rg.grupo_id
            WHERE er.empresa_id = ?
              AND er.vigente_desde <= ?
              AND (er.vigente_hasta IS NULL OR er.vigente_hasta >= ?)
            LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iss", $empresa_id, $fecha, $fecha);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if ($row) {
        return [
            'empresa_id' => intval($row['empresa_id']),
            'region_grupo_id' => intval($row['region_grupo_id']),
            'grupo_id' => intval($row['grupo_id']),
            'region_nombre' => $row['region_nombre'],
            'grupo_nombre' => $row['grupo_nombre']
        ];
    }

    return [
        'empresa_id' => $empresa_id,
        'region_grupo_id' => 1,
        'grupo_id' => 1,
        'region_nombre' => 'Región Predeterminada',
        'grupo_nombre' => 'Grupo Corporativo'
    ];
}

/**
 * Cargar datos de atributos clave de una entidad y su empresa para evaluar reglas condicionales JSON (C20, C25).
 */
function motor_cargar_datos_condicionales(mysqli $conexion, int $entidad_id, int $empresa_id): array
{
    $datos = [];

    // Datos de la entidad actual (ml__entidades_datos)
    $sql_e = "SELECT dt.codigo, ed.valor_numero, ed.valor_texto, ed.valor_fecha
              FROM ml__entidades_datos ed
              INNER JOIN ml__datos_tipos dt ON dt.dato_tipo_id = ed.dato_tipo_id
              WHERE ed.entidad_id = ?
                AND ed.vigente_desde <= CURRENT_DATE
                AND (ed.vigente_hasta IS NULL OR ed.vigente_hasta >= CURRENT_DATE)";
    $stmt_e = mysqli_prepare($conexion, $sql_e);
    mysqli_stmt_bind_param($stmt_e, "i", $entidad_id);
    mysqli_stmt_execute($stmt_e);
    $res_e = mysqli_stmt_get_result($stmt_e);
    while ($r = mysqli_fetch_assoc($res_e)) {
        $val = $r['valor_numero'] !== null ? floatval($r['valor_numero']) : ($r['valor_fecha'] ?: $r['valor_texto']);
        $datos[$r['codigo']] = $val;
    }
    mysqli_stmt_close($stmt_e);

    // Si la entidad no es la entidad raíz de la empresa, cargar también datos de la entidad raíz (empresa)
    $sql_root = "SELECT dt.codigo, ed.valor_numero, ed.valor_texto, ed.valor_fecha
                 FROM ml__entidades e
                 INNER JOIN ml__entidades_empresas ee ON ee.entidad_id = e.entidad_id AND ee.empresa_id = ?
                 INNER JOIN ml__entidades_datos ed ON ed.entidad_id = e.entidad_id
                 INNER JOIN ml__datos_tipos dt ON dt.dato_tipo_id = ed.dato_tipo_id
                 WHERE e.es_raiz = 1
                   AND e.entidad_id != ?
                   AND ed.vigente_desde <= CURRENT_DATE
                   AND (ed.vigente_hasta IS NULL OR ed.vigente_hasta >= CURRENT_DATE)";
    $stmt_root = mysqli_prepare($conexion, $sql_root);
    mysqli_stmt_bind_param($stmt_root, "ii", $empresa_id, $entidad_id);
    mysqli_stmt_execute($stmt_root);
    $res_root = mysqli_stmt_get_result($stmt_root);
    while ($r = mysqli_fetch_assoc($res_root)) {
        if (!isset($datos[$r['codigo']])) {
            $val = $r['valor_numero'] !== null ? floatval($r['valor_numero']) : ($r['valor_fecha'] ?: $r['valor_texto']);
            $datos[$r['codigo']] = $val;
        }
    }
    mysqli_stmt_close($stmt_root);

    return $datos;
}

/**
 * Evaluar reglas condicionales en formato JSON (Caso C20, Caso C25).
 * Ejemplo regla_json: {"superficie_min_m2": 500, "empleados_min": 10}
 */
function motor_evaluar_regla_json(?string $regla_json, array $datos_disponibles): bool
{
    if (empty($regla_json) || trim($regla_json) === '' || trim($regla_json) === '{}') {
        return true;
    }

    $reglas = json_decode($regla_json, true);
    if (!is_array($reglas)) {
        return true;
    }

    foreach ($reglas as $campo => $condicion) {
        // Reglas numéricas mínimas
        if (str_ends_with($campo, '_min') || str_contains($campo, 'min_')) {
            $clave_base = str_replace(['_min', 'min_'], '', $campo);
            $valor_actual = $datos_disponibles[$campo] ?? ($datos_disponibles[$clave_base] ?? null);
            if ($valor_actual === null || floatval($valor_actual) < floatval($condicion)) {
                return false;
            }
        } elseif (str_ends_with($campo, '_max') || str_contains($campo, 'max_')) {
            $clave_base = str_replace(['_max', 'max_'], '', $campo);
            $valor_actual = $datos_disponibles[$campo] ?? ($datos_disponibles[$clave_base] ?? null);
            if ($valor_actual === null || floatval($valor_actual) > floatval($condicion)) {
                return false;
            }
        } else {
            // Comparación de igualdad directa
            if (!isset($datos_disponibles[$campo]) || $datos_disponibles[$campo] != $condicion) {
                return false;
            }
        }
    }

    return true;
}

/**
 * Obtener todas las normas vigentes que alcanzan a los territorios y a la estructura corporativa.
 * Aplica:
 * - Filtro de vigencia temporal
 * - Filtro de estado vigente (2047)
 * - Filtro Caso C21: norma regional que requiere ley local (ml__normas.requiere_norma_local)
 * - Filtro Caso C19: normas con conflictos incompatibles abiertos (ml__normas_conflictos)
 */
function motor_normas_aplicables(
    mysqli $conexion, 
    array $territorio_ids, 
    array $estructura_corp, 
    int $pais_territorio_id, 
    ?string $fecha = null
): array {
    if (!$fecha) $fecha = date('Y-m-d');
    if (empty($territorio_ids)) $territorio_ids = [1];

    $ter_placeholders = implode(',', array_fill(0, count($territorio_ids), '?'));
    $sql = "SELECT n.norma_id, n.codigo, n.titulo, n.norma_tipo_id, nt.es_legal,
                   n.ambito_tipo_id, n.territorio_id, n.empresa_id, n.region_grupo_id, n.grupo_id,
                   n.requiere_norma_local, n.confidencial, n.vigente_desde, n.vigente_hasta
            FROM ml__normas n
            INNER JOIN ml__normas_tipos nt ON nt.norma_tipo_id = n.norma_tipo_id
            WHERE n.tabla_estado_registro_id = 2047
              AND n.vigente_desde <= ?
              AND (n.vigente_hasta IS NULL OR n.vigente_hasta >= ?)
              AND (
                  n.territorio_id IN ($ter_placeholders)
                  OR n.empresa_id = ?
                  OR n.region_grupo_id = ?
                  OR n.grupo_id = ?
              )";

    $stmt = mysqli_prepare($conexion, $sql);
    $types = "ss" . str_repeat('i', count($territorio_ids)) . "iii";
    $params = array_merge([$fecha, $fecha], $territorio_ids, [
        $estructura_corp['empresa_id'],
        $estructura_corp['region_grupo_id'],
        $estructura_corp['grupo_id']
    ]);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $normas = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    $normas_filtradas = [];

    foreach ($normas as $n) {
        $norma_id = intval($n['norma_id']);

        // Caso C21: Si la norma regional requiere norma local, verificar si existe norma local vigente vinculada
        if (!empty($n['requiere_norma_local']) && intval($n['requiere_norma_local']) === 1) {
            $sql_loc = "SELECT nr.norma_destino_id
                        FROM ml__normas_relaciones nr
                        INNER JOIN ml__normas nl ON nl.norma_id = nr.norma_destino_id
                        WHERE nr.norma_origen_id = ?
                          AND nl.territorio_id = ?
                          AND nl.tabla_estado_registro_id = 2047
                          AND nl.vigente_desde <= ?
                          AND (nl.vigente_hasta IS NULL OR nl.vigente_hasta >= ?)
                        LIMIT 1";
            $stmt_loc = mysqli_prepare($conexion, $sql_loc);
            mysqli_stmt_bind_param($stmt_loc, "iiss", $norma_id, $pais_territorio_id, $fecha, $fecha);
            mysqli_stmt_execute($stmt_loc);
            $res_loc = mysqli_stmt_get_result($stmt_loc);
            $tiene_local = (bool)mysqli_fetch_assoc($res_loc);
            mysqli_stmt_close($stmt_loc);

            if (!$tiene_local) {
                // Bloqueada por falta de norma local (Caso C21)
                continue;
            }
        }

        // Caso C19: Verificar si la norma tiene un conflicto abierto o no resuelto (estados 2084 o 2085)
        $sql_conf = "SELECT norma_conflicto_id 
                     FROM ml__normas_conflictos 
                     WHERE (norma_a_id = ? OR norma_b_id = ?)
                       AND tabla_estado_registro_id IN (2084, 2085)
                     LIMIT 1";
        $stmt_conf = mysqli_prepare($conexion, $sql_conf);
        mysqli_stmt_bind_param($stmt_conf, "ii", $norma_id, $norma_id);
        mysqli_stmt_execute($stmt_conf);
        $res_conf = mysqli_stmt_get_result($stmt_conf);
        $en_conflicto = (bool)mysqli_fetch_assoc($res_conf);
        mysqli_stmt_close($stmt_conf);

        if ($en_conflicto) {
            // Norma con incompatibilidad legal abierta (Caso C19)
            continue;
        }

        $normas_filtradas[] = $n;
    }

    return $normas_filtradas;
}

/**
 * Obtener las obligaciones candidatas aplicables a una entidad específica y su tipo.
 * Aplica:
 * - Filtro de tipo de entidad (ml__obligaciones_tipos_entidades)
 * - Caso C20: Evaluación de reglas JSON
 * - Filtro territorial específico si la obligación restringe jurisdicción (ml__obligaciones_jurisdicciones)
 */
function motor_obligaciones_candidatas(
    mysqli $conexion, 
    array $norma_ids, 
    int $entidad_tipo_id, 
    array $territorio_ids, 
    array $datos_disponibles, 
    ?string $fecha = null
): array {
    if (empty($norma_ids)) return [];
    if (!$fecha) $fecha = date('Y-m-d');

    $norma_placeholders = implode(',', array_fill(0, count($norma_ids), '?'));
    $sql = "SELECT o.obligacion_id, o.norma_id, o.codigo, o.descripcion, o.articulo_referencia,
                   o.periodicidad_id, p.codigo AS periodicidad_codigo, p.intervalo_cantidad, p.intervalo_unidad, p.es_evento,
                   o.vencimiento_regla_id, vr.codigo AS vencimiento_regla_codigo,
                   o.criticidad_id, COALESCE(c.peso, 1) AS criticidad_peso,
                   o.requiere_revision, o.requiere_evidencia, o.retencion_evidencias_meses,
                   o.vigente_desde, o.vigente_hasta,
                   n.codigo AS norma_codigo, n.titulo AS norma_titulo, nt.es_legal,
                   ote.regla_json
            FROM ml__obligaciones o
            INNER JOIN ml__normas n ON n.norma_id = o.norma_id
            INNER JOIN ml__normas_tipos nt ON nt.norma_tipo_id = n.norma_tipo_id
            INNER JOIN ml__periodicidades p ON p.periodicidad_id = o.periodicidad_id
            INNER JOIN ml__vencimientos_reglas vr ON vr.vencimiento_regla_id = o.vencimiento_regla_id
            LEFT JOIN ml__criticidades c ON c.criticidad_id = o.criticidad_id
            INNER JOIN ml__obligaciones_tipos_entidades ote ON ote.obligacion_id = o.obligacion_id AND ote.entidad_tipo_id = ?
            WHERE o.norma_id IN ($norma_placeholders)
              AND o.tabla_estado_registro_id = 2063
              AND o.vigente_desde <= ?
              AND (o.vigente_hasta IS NULL OR o.vigente_hasta >= ?)
              AND ote.vigente_desde <= ?
              AND (ote.vigente_hasta IS NULL OR ote.vigente_hasta >= ?)";

    $stmt = mysqli_prepare($conexion, $sql);
    $types = "i" . str_repeat('i', count($norma_ids)) . "ssss";
    $params = array_merge([$entidad_tipo_id], $norma_ids, [$fecha, $fecha, $fecha, $fecha]);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $obligaciones = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    $candidatas = [];

    foreach ($obligaciones as $obl) {
        // Caso C20: Evaluar regla condicional JSON
        if (!motor_evaluar_regla_json($obl['regla_json'], $datos_disponibles)) {
            continue;
        }

        // Filtro de jurisdicción específica de la obligación (ml__obligaciones_jurisdicciones)
        $obl_id = intval($obl['obligacion_id']);
        $sql_jur = "SELECT COUNT(*) AS total FROM ml__obligaciones_jurisdicciones WHERE obligacion_id = ?";
        $stmt_j = mysqli_prepare($conexion, $sql_jur);
        mysqli_stmt_bind_param($stmt_j, "i", $obl_id);
        mysqli_stmt_execute($stmt_j);
        $res_j = mysqli_stmt_get_result($stmt_j);
        $r_j = mysqli_fetch_assoc($res_j);
        mysqli_stmt_close($stmt_j);

        if (!empty($r_j['total']) && intval($r_j['total']) > 0) {
            $ter_in = implode(',', array_fill(0, count($territorio_ids), '?'));
            $sql_val_jur = "SELECT obligacion_jurisdiccion_id 
                            FROM ml__obligaciones_jurisdicciones 
                            WHERE obligacion_id = ? AND territorio_id IN ($ter_in) 
                            LIMIT 1";
            $stmt_vj = mysqli_prepare($conexion, $sql_val_jur);
            $types_vj = "i" . str_repeat('i', count($territorio_ids));
            $params_vj = array_merge([$obl_id], $territorio_ids);
            mysqli_stmt_bind_param($stmt_vj, $types_vj, ...$params_vj);
            mysqli_stmt_execute($stmt_vj);
            $res_vj = mysqli_stmt_get_result($stmt_vj);
            $cumple_jur = (bool)mysqli_fetch_assoc($res_vj);
            mysqli_stmt_close($stmt_vj);

            if (!$cumple_jur) {
                continue;
            }
        }

        $candidatas[] = $obl;
    }

    return $candidatas;
}

/**
 * Consolidar solapamientos entre obligaciones que exigen lo mismo (Caso C01, Caso C11).
 * Criterio de prevalencia (mayor estrictez):
 * 1. La política corporativa puede elevar el estándar legal, pero NUNCA relajarlo (Caso C11).
 * 2. Mayor peso de criticidad (criticidad_peso).
 * 3. Mayor frecuencia (menor días de intervalo).
 * 4. Mayor retención de evidencias.
 * 5. Requisito de evidencia y revisión técnica obligatoria.
 */
function motor_consolidar_solapamientos(array $candidatas): array
{
    if (empty($candidatas)) return [];

    // Agrupación por clave de materia / solapamiento
    // Si comparten la misma referencia temática (ej: código unificado, artículo o categoría base)
    $grupos = [];

    foreach ($candidatas as $obl) {
        // Clave de solapamiento:
        // Si el código contiene un prefijo común (ej: 'AUDITORIA_AMBIENTAL' o 'SEG_INCENDIO')
        // o si es único, se agrupa por su tema. Extraer tema base (antes del primer guión o código limpio).
        $partes = explode('-', $obl['codigo']);
        $tema_base = trim($partes[0]);
        if (strlen($tema_base) < 3) {
            $tema_base = $obl['codigo'];
        }

        // Si la descripción contiene una materia común explícita entre corchetes ej [MATERIA]
        if (preg_match('/\[(.*?)\]/', $obl['descripcion'], $matches)) {
            $clave = strtoupper(trim($matches[1]));
        } else {
            $clave = $tema_base;
        }

        $grupos[$clave][] = $obl;
    }

    $consolidados = [];

    foreach ($grupos as $clave => $lista_obls) {
        if (count($lista_obls) === 1) {
            $consolidados[] = [
                'prevaleciente' => $lista_obls[0],
                'origenes' => [$lista_obls[0]]
            ];
            continue;
        }

        // Caso C01 / C11: Múltiples orígenes solapados. Seleccionar la prevaleciente más estricta.
        $mejor_obl = null;
        $max_score = -1;
        $tiene_legal = false;
        $obl_legal = null;

        // Primero verificar si alguna es legal
        foreach ($lista_obls as $o) {
            if (!empty($o['es_legal']) && intval($o['es_legal']) === 1) {
                $tiene_legal = true;
                $obl_legal = $o;
                break;
            }
        }

        foreach ($lista_obls as $o) {
            // Calcular días aproximados de frecuencia
            $cant = intval($o['intervalo_cantidad'] ?? 1);
            $unidad = strtoupper($o['intervalo_unidad'] ?? 'MONTH');
            $dias = 365;
            if ($unidad === 'DAY') $dias = $cant;
            elseif ($unidad === 'MONTH') $dias = $cant * 30;
            elseif ($unidad === 'YEAR') $dias = $cant * 365;
            elseif ($unidad === 'HOUR') $dias = 1;

            $peso_crit = intval($o['criticidad_peso'] ?? 1);
            $retencion = intval($o['retencion_evidencias_meses'] ?? 12);
            $rev = !empty($o['requiere_revision']) ? 10 : 0;
            $evi = !empty($o['requiere_evidencia']) ? 10 : 0;

            // Score de estrictez: mayor peso de criticidad, menor plazo entre ciclos, mayor retención
            $score = ($peso_crit * 100) + intval(10000 / max($dias, 1)) + $retencion + $rev + $evi;

            // Caso C11: Si hay ley y esta es corporativa pero con menor score que la ley, se descarta relajar la ley
            if ($tiene_legal && empty($o['es_legal'])) {
                // Es corporativa: solo puede ganar si es MÁS estricta que la legal
                $score_legal_base = (intval($obl_legal['criticidad_peso'] ?? 1) * 100);
                if ($peso_crit < intval($obl_legal['criticidad_peso'] ?? 1)) {
                    $score = -100; // Penalizada por intentar relajar la ley
                }
            }

            if ($score > $max_score) {
                $max_score = $score;
                $mejor_obl = $o;
            }
        }

        if (!$mejor_obl) {
            $mejor_obl = $lista_obls[0];
        }

        $consolidados[] = [
            'prevaleciente' => $mejor_obl,
            'origenes' => $lista_obls
        ];
    }

    return $consolidados;
}

/**
 * Ejecutar el motor de herencia para una entidad individual.
 * Realiza la sincronización completa en ml__obligaciones_asignadas y ml__obligaciones_asignadas_origenes.
 */
function motor_recalcular_entidad(mysqli $conexion, int $entidad_id, ?int $usuario_id = 1, ?string $fecha = null): array
{
    if (!$fecha) $fecha = date('Y-m-d');
    if (!$usuario_id) $usuario_id = 1;

    // 1. Cargar Entidad
    $sql_ent = "SELECT e.entidad_id, e.entidad_tipo_id, e.codigo, e.nombre, e.es_raiz, e.vigente_desde, e.vigente_hasta,
                       ee.empresa_id
                FROM ml__entidades e
                INNER JOIN ml__entidades_empresas ee ON ee.entidad_id = e.entidad_id AND ee.es_titular = 1 AND (ee.vigente_hasta IS NULL OR ee.vigente_hasta >= ?)
                WHERE e.entidad_id = ?
                  AND e.tabla_estado_registro_id = 1
                LIMIT 1";
    $stmt_ent = mysqli_prepare($conexion, $sql_ent);
    mysqli_stmt_bind_param($stmt_ent, "si", $fecha, $entidad_id);
    mysqli_stmt_execute($stmt_ent);
    $res_ent = mysqli_stmt_get_result($stmt_ent);
    $entidad = mysqli_fetch_assoc($res_ent);
    mysqli_stmt_close($stmt_ent);

    if (!$entidad) {
        return ['success' => false, 'message' => "Entidad #{$entidad_id} no encontrada o sin empresa titular vigente."];
    }

    $empresa_id = intval($entidad['empresa_id']);
    $entidad_tipo_id = intval($entidad['entidad_tipo_id']);

    // 2. Ubicaciones territoriales (Base + Operación si es móvil - Caso C06)
    $ubicaciones = motor_entidad_ubicaciones($conexion, $entidad_id, $fecha);
    if (empty($ubicaciones)) {
        return ['success' => false, 'message' => "La entidad #{$entidad_id} no tiene ubicación territorial asignada."];
    }

    $todos_territorio_ids = [];
    $pais_principal_id = 1;

    foreach ($ubicaciones as $ub) {
        $tid = intval($ub['territorio_id']);
        $ancestros = motor_territorios_ancestros($conexion, $tid, $fecha);
        foreach ($ancestros as $a) {
            $todos_territorio_ids[$a] = $a;
        }
        if (!empty($ub['es_base'])) {
            $pais_principal_id = motor_obtener_pais_territorio($conexion, $tid);
        }
    }
    $territorios_finales = array_values($todos_territorio_ids);

    // 3. Estructura Corporativa
    $estructura_corp = motor_empresa_estructura($conexion, $empresa_id, $fecha);

    // 4. Datos condicionales de entidad y empresa
    $datos_disponibles = motor_cargar_datos_condicionales($conexion, $entidad_id, $empresa_id);

    // 5. Normas aplicables
    $normas = motor_normas_aplicables($conexion, $territorios_finales, $estructura_corp, $pais_principal_id, $fecha);
    $norma_ids = array_map(fn($n) => intval($n['norma_id']), $normas);

    // 6. Obligaciones candidatas
    $candidatas = motor_obligaciones_candidatas($conexion, $norma_ids, $entidad_tipo_id, $territorios_finales, $datos_disponibles, $fecha);

    // 7. Consolidación de solapamientos (Casos C01, C11)
    $consolidados = motor_consolidar_solapamientos($candidatas);

    // 8. Sincronización en Base de Datos (Transacción)
    mysqli_begin_transaction($conexion);
    try {
        $asignadas_actuales_ids = [];

        foreach ($consolidados as $item) {
            $prevaleciente = $item['prevaleciente'];
            $origenes = $item['origenes'];
            $prevaleciente_id = intval($prevaleciente['obligacion_id']);

            // Verificar si ya existe asignación vigente para esta entidad y obligación prevaleciente
            $sql_chk = "SELECT obligacion_asignada_id 
                        FROM ml__obligaciones_asignadas 
                        WHERE entidad_id = ? AND obligacion_prevaleciente_id = ?";
            $stmt_chk = mysqli_prepare($conexion, $sql_chk);
            mysqli_stmt_bind_param($stmt_chk, "ii", $entidad_id, $prevaleciente_id);
            mysqli_stmt_execute($stmt_chk);
            $res_chk = mysqli_stmt_get_result($stmt_chk);
            $asig_existente = mysqli_fetch_assoc($res_chk);
            mysqli_stmt_close($stmt_chk);

            if ($asig_existente) {
                $asig_id = intval($asig_existente['obligacion_asignada_id']);
                // Reactivar si estaba inactiva
                $sql_upd = "UPDATE ml__obligaciones_asignadas 
                            SET tabla_estado_registro_id = 1, vigente_hasta = NULL, actualizado_en = CURRENT_TIMESTAMP, actualizado_por = ?
                            WHERE obligacion_asignada_id = ?";
                $stmt_upd = mysqli_prepare($conexion, $sql_upd);
                mysqli_stmt_bind_param($stmt_upd, "ii", $usuario_id, $asig_id);
                mysqli_stmt_execute($stmt_upd);
                mysqli_stmt_close($stmt_upd);
            } else {
                // Insertar nueva asignación
                $sql_ins = "INSERT INTO ml__obligaciones_asignadas 
                            (obligacion_prevaleciente_id, entidad_id, empresa_id, vigente_desde, tabla_estado_registro_id, creado_en, creado_por)
                            VALUES (?, ?, ?, ?, 1, CURRENT_TIMESTAMP, ?)";
                $stmt_ins = mysqli_prepare($conexion, $sql_ins);
                mysqli_stmt_bind_param($stmt_ins, "iiisi", $prevaleciente_id, $entidad_id, $empresa_id, $fecha, $usuario_id);
                mysqli_stmt_execute($stmt_ins);
                $asig_id = mysqli_insert_id($conexion);
                mysqli_stmt_close($stmt_ins);
            }

            $asignadas_actuales_ids[] = $asig_id;

            // Sincronizar orígenes (ml__obligaciones_asignadas_origenes)
            foreach ($origenes as $orig) {
                $orig_id = intval($orig['obligacion_id']);
                $sql_orig = "INSERT INTO ml__obligaciones_asignadas_origenes 
                             (obligacion_asignada_id, obligacion_id, creado_en, creado_por)
                             VALUES (?, ?, CURRENT_TIMESTAMP, ?)
                             ON DUPLICATE KEY UPDATE actualizado_en = CURRENT_TIMESTAMP";
                $stmt_orig = mysqli_prepare($conexion, $sql_orig);
                mysqli_stmt_bind_param($stmt_orig, "iii", $asig_id, $orig_id, $usuario_id);
                mysqli_stmt_execute($stmt_orig);
                mysqli_stmt_close($stmt_orig);
            }
        }

        // Desactivar asignaciones previas que ya no aplican (ej: cambio de país C05 o cambio de umbral C25)
        if (!empty($asignadas_actuales_ids)) {
            $in_placeholders = implode(',', array_fill(0, count($asignadas_actuales_ids), '?'));
            $sql_deact = "UPDATE ml__obligaciones_asignadas 
                          SET tabla_estado_registro_id = 2, vigente_hasta = ?, actualizado_en = CURRENT_TIMESTAMP, actualizado_por = ?
                          WHERE entidad_id = ? 
                            AND obligacion_asignada_id NOT IN ($in_placeholders)
                            AND tabla_estado_registro_id = 1";
            $stmt_deact = mysqli_prepare($conexion, $sql_deact);
            $types_deact = "sii" . str_repeat('i', count($asignadas_actuales_ids));
            $params_deact = array_merge([$fecha, $usuario_id, $entidad_id], $asignadas_actuales_ids);
            mysqli_stmt_bind_param($stmt_deact, $types_deact, ...$params_deact);
            mysqli_stmt_execute($stmt_deact);
            mysqli_stmt_close($stmt_deact);
        }

        mysqli_commit($conexion);

        return [
            'success' => true,
            'entidad_id' => $entidad_id,
            'entidad_nombre' => $entidad['nombre'],
            'total_normas' => count($normas),
            'total_candidatas' => count($candidatas),
            'total_asignadas' => count($consolidados),
            'total_solapamientos' => count($candidatas) - count($consolidados)
        ];
    } catch (\Throwable $e) {
        mysqli_rollback($conexion);
        return [
            'success' => false,
            'message' => 'Error durante el recálculo de la entidad: ' . $e->getMessage()
        ];
    }
}

/**
 * Ejecutar el motor de herencia para todas las entidades activas de una empresa.
 */
function motor_recalcular_empresa(mysqli $conexion, int $empresa_id, ?int $usuario_id = 1): array
{
    $fecha = date('Y-m-d');
    $sql = "SELECT DISTINCT e.entidad_id, e.nombre
            FROM ml__entidades e
            INNER JOIN ml__entidades_empresas ee ON ee.entidad_id = e.entidad_id AND ee.es_titular = 1 AND (ee.vigente_hasta IS NULL OR ee.vigente_hasta >= ?)
            WHERE ee.empresa_id = ?
              AND e.tabla_estado_registro_id = 1
            ORDER BY e.es_raiz DESC, e.entidad_id ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "si", $fecha, $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $entidades = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    $resultados = [];
    $total_asignadas = 0;

    foreach ($entidades as $ent) {
        $eid = intval($ent['entidad_id']);
        $r = motor_recalcular_entidad($conexion, $eid, $usuario_id, $fecha);
        if ($r['success']) {
            $total_asignadas += $r['total_asignadas'];
        }
        $resultados[] = $r;
    }

    return [
        'success' => true,
        'empresa_id' => $empresa_id,
        'total_entidades' => count($entidades),
        'total_asignadas' => $total_asignadas,
        'detalles' => $resultados
    ];
}

/**
 * Simular impacto sin escribir en base de datos (Caso C35: vista previa antes de aplicar).
 */
function motor_simular_impacto(mysqli $conexion, int $empresa_id): array
{
    $fecha = date('Y-m-d');
    $sql = "SELECT DISTINCT e.entidad_id, e.nombre, et.nombre AS tipo_entidad
            FROM ml__entidades e
            INNER JOIN ml__entidades_tipos et ON et.entidad_tipo_id = e.entidad_tipo_id
            INNER JOIN ml__entidades_empresas ee ON ee.entidad_id = e.entidad_id AND ee.es_titular = 1 AND (ee.vigente_hasta IS NULL OR ee.vigente_hasta >= ?)
            WHERE ee.empresa_id = ?
              AND e.tabla_estado_registro_id = 1";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "si", $fecha, $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $entidades = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    $total_entidades = count($entidades);
    $total_obligaciones_proyectadas = 0;
    $total_solapamientos_proyectados = 0;
    $detalle_entidades = [];

    foreach ($entidades as $ent) {
        $eid = intval($ent['entidad_id']);
        $ubicaciones = motor_entidad_ubicaciones($conexion, $eid, $fecha);
        if (empty($ubicaciones)) continue;

        $ter_ids = [];
        $pais_id = 1;
        foreach ($ubicaciones as $ub) {
            $tid = intval($ub['territorio_id']);
            foreach (motor_territorios_ancestros($conexion, $tid, $fecha) as $a) {
                $ter_ids[$a] = $a;
            }
            if (!empty($ub['es_base'])) $pais_id = motor_obtener_pais_territorio($conexion, $tid);
        }

        $est_corp = motor_empresa_estructura($conexion, $empresa_id, $fecha);
        $datos = motor_cargar_datos_condicionales($conexion, $eid, $empresa_id);
        $normas = motor_normas_aplicables($conexion, array_values($ter_ids), $est_corp, $pais_id, $fecha);
        $norma_ids = array_map(fn($n) => intval($n['norma_id']), $normas);
        $candidatas = motor_obligaciones_candidatas($conexion, $norma_ids, intval($ent['entidad_tipo_id'] ?? 1), array_values($ter_ids), $datos, $fecha);
        $consolidados = motor_consolidar_solapamientos($candidatas);

        $cant_asig = count($consolidados);
        $cant_solap = count($candidatas) - $cant_asig;

        $total_obligaciones_proyectadas += $cant_asig;
        $total_solapamientos_proyectados += $cant_solap;

        $detalle_entidades[] = [
            'entidad_id' => $eid,
            'nombre' => $ent['nombre'],
            'tipo' => $ent['tipo_entidad'],
            'normas_alcanzadas' => count($normas),
            'obligaciones_asignadas' => $cant_asig,
            'solapamientos_resueltos' => $cant_solap
        ];
    }

    return [
        'success' => true,
        'empresa_id' => $empresa_id,
        'total_entidades' => $total_entidades,
        'total_obligaciones_proyectadas' => $total_obligaciones_proyectadas,
        'total_solapamientos_proyectados' => $total_solapamientos_proyectados,
        'detalles' => $detalle_entidades
    ];
}

/**
 * Encolar un evento para recálculo asíncrono en ml__recalculos_pendientes.
 */
function motor_encolar_recalculo(
    mysqli $conexion, 
    string $causa, 
    string $objeto_tabla, 
    int $objeto_id, 
    ?string $fecha_efecto = null
): int {
    if (!$fecha_efecto) $fecha_efecto = date('Y-m-d');

    $sql = "INSERT INTO ml__recalculos_pendientes 
            (causa, objeto_tabla, objeto_id, fecha_efecto, creado_en, tabla_estado_registro_id)
            VALUES (?, ?, ?, ?, NOW(), 2092)";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ssis", $causa, $objeto_tabla, $objeto_id, $fecha_efecto);
    mysqli_stmt_execute($stmt);
    $id = mysqli_insert_id($conexion);
    mysqli_stmt_close($stmt);

    return $id;
}

/**
 * Procesar lote de recálculos pendientes de la cola ml__recalculos_pendientes (Estados 2092 -> 2093 / 2094).
 */
function motor_procesar_cola(mysqli $conexion, int $limite = 20): array
{
    $sql = "SELECT recalculo_pendiente_id, causa, objeto_tabla, objeto_id, fecha_efecto
            FROM ml__recalculos_pendientes
            WHERE tabla_estado_registro_id = 2092
            ORDER BY fecha_efecto ASC, recalculo_pendiente_id ASC
            LIMIT ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $limite);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $pendientes = mysqli_fetch_all($res, MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);

    $procesados = 0;
    $errores = 0;

    foreach ($pendientes as $p) {
        $pid = intval($p['recalculo_pendiente_id']);
        $tabla = $p['objeto_tabla'];
        $obj_id = intval($p['objeto_id']);
        $fecha = $p['fecha_efecto'];

        try {
            if ($tabla === 'ml__entidades') {
                motor_recalcular_entidad($conexion, $obj_id, 1, $fecha);
            } elseif ($tabla === 'conf__empresas' || $tabla === 'ml__empresas_regiones') {
                motor_recalcular_empresa($conexion, $obj_id, 1);
            } else {
                // Si la causa es NORMA u OBLIGACION, recalcula para todas las empresas activas
                $res_emp = mysqli_query($conexion, "SELECT empresa_id FROM conf__empresas WHERE tabla_estado_registro_id = 1");
                while ($remp = mysqli_fetch_assoc($res_emp)) {
                    motor_recalcular_empresa($conexion, intval($remp['empresa_id']), 1);
                }
            }

            // Marcar 2093 (Procesado)
            $stmt_ok = mysqli_prepare($conexion, "UPDATE ml__recalculos_pendientes SET tabla_estado_registro_id = 2093, procesado_en = NOW() WHERE recalculo_pendiente_id = ?");
            mysqli_stmt_bind_param($stmt_ok, "i", $pid);
            mysqli_stmt_execute($stmt_ok);
            mysqli_stmt_close($stmt_ok);
            $procesados++;
        } catch (\Throwable $e) {
            // Marcar 2094 (Con error)
            $stmt_err = mysqli_prepare($conexion, "UPDATE ml__recalculos_pendientes SET tabla_estado_registro_id = 2094, procesado_en = NOW() WHERE recalculo_pendiente_id = ?");
            mysqli_stmt_bind_param($stmt_err, "i", $pid);
            mysqli_stmt_execute($stmt_err);
            mysqli_stmt_close($stmt_err);
            $errores++;
        }
    }

    return [
        'success' => true,
        'total_evaluados' => count($pendientes),
        'procesados' => $procesados,
        'errores' => $errores
    ];
}
