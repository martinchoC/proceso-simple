<?php
/**
 * Modelo de Datos: Motor de Normas Legales Dinámicas y Reglas de Impacto
 * Módulo 4: Estudio Contable (normas_gestion_model.php)
 * Tablas: estudio__normas (415), estudio__normas_condiciones (416), estudio__normas_clientes_impacto (417)
 */

declare(strict_types=1);

/**
 * Combos para normas y reglas
 */
function normas_combos(mysqli $conexion, int $empresa_id): array
{
    $combos = [
        'organismos' => ['ARCA', 'COMARB', 'ARBA', 'AGIP', 'DGR_CBA', 'MTSS', 'UIF', 'OTRO'],
        'tipos_norma' => ['RESOLUCION_GENERAL', 'DECRETO', 'LEY', 'DISPOSICION', 'CIRCULAR'],
        'obligaciones' => [],
        'condiciones_iva' => [],
        'jurisdicciones' => []
    ];

    $res_o = mysqli_query($conexion, "SELECT impuesto_obligacion_id, organismo, codigo_oficial, nombre FROM estudio__impuestos_obligaciones WHERE activo = 1 ORDER BY nombre");
    while ($r = mysqli_fetch_assoc($res_o)) {
        $combos['obligaciones'][] = $r;
    }

    $res_iva = mysqli_query($conexion, "SELECT condicion_fiscal_id, condicion_fiscal_codigo, condicion_fiscal FROM gestion__condiciones_fiscales WHERE tabla_estado_registro_id = 1");
    while ($r = mysqli_fetch_assoc($res_iva)) {
        $combos['condiciones_iva'][] = $r;
    }

    $res_j = mysqli_query($conexion, "SELECT jurisdiccion_id, jurisdiccion_codigo, jurisdiccion_nombre FROM gestion__jurisdicciones WHERE tabla_estado_registro_id = 1 ORDER BY orden");
    while ($r = mysqli_fetch_assoc($res_j)) {
        $combos['jurisdicciones'][] = $r;
    }

    return $combos;
}

/**
 * Listar normas legales registradas
 */
function normas_listar(mysqli $conexion, int $empresa_id, array $filtros = []): array
{
    $sql = "SELECT n.norma_id, n.empresa_id, n.organismo_emisor, n.tipo_norma, n.nro_norma,
                   n.f_boletin_oficial, n.vigencia_desde, n.vigencia_hasta, n.titulo,
                   n.genera_nueva_obligacion, n.impuesto_obligacion_id, n.tabla_estado_registro_id,
                   n.created_at,
                   o.codigo_oficial AS obligacion_codigo, o.nombre AS obligacion_nombre,
                   ter.tabla_estado_registro,
                   COALESCE(imp_stats.total_alcanzados, 0) AS total_alcanzados,
                   COALESCE(cond_stats.total_condiciones, 0) AS total_condiciones
            FROM estudio__normas n
            LEFT JOIN estudio__impuestos_obligaciones o ON o.impuesto_obligacion_id = n.impuesto_obligacion_id
            LEFT JOIN conf__tablas_estados_registros ter 
                ON ter.tabla_id = 415 AND ter.estado_registro_id = n.tabla_estado_registro_id
            LEFT JOIN (
                SELECT norma_id, COUNT(*) AS total_alcanzados 
                FROM estudio__normas_clientes_impacto 
                WHERE estado_impacto = 'ALCANZADO'
                GROUP BY norma_id
            ) imp_stats ON imp_stats.norma_id = n.norma_id
            LEFT JOIN (
                SELECT norma_id, COUNT(*) AS total_condiciones 
                FROM estudio__normas_condiciones 
                GROUP BY norma_id
            ) cond_stats ON cond_stats.norma_id = n.norma_id
            WHERE n.empresa_id = ?";

    $params = [$empresa_id];
    $types = "i";

    if (!empty($filtros['organismo'])) {
        $sql .= " AND n.organismo_emisor = ?";
        $params[] = $filtros['organismo'];
        $types .= "s";
    }

    if (!empty($filtros['estado_id'])) {
        $sql .= " AND n.tabla_estado_registro_id = ?";
        $params[] = intval($filtros['estado_id']);
        $types .= "i";
    }

    $sql .= " ORDER BY n.vigencia_desde DESC, n.norma_id DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $lista[] = $r;
    }
    mysqli_stmt_close($stmt);

    return $lista;
}

/**
 * Obtener norma con sus condiciones configuradas
 */
function norma_obtener(mysqli $conexion, int $empresa_id, int $norma_id): ?array
{
    $sql = "SELECT n.*, o.nombre AS obligacion_nombre, o.codigo_oficial
            FROM estudio__normas n
            LEFT JOIN estudio__impuestos_obligaciones o ON o.impuesto_obligacion_id = n.impuesto_obligacion_id
            WHERE n.empresa_id = ? AND n.norma_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $norma_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $norma = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$norma) return null;

    // Condiciones dinámicas
    $sql_c = "SELECT * FROM estudio__normas_condiciones WHERE norma_id = ? ORDER BY condicion_id ASC";
    $stmt_c = mysqli_prepare($conexion, $sql_c);
    mysqli_stmt_bind_param($stmt_c, "i", $norma_id);
    mysqli_stmt_execute($stmt_c);
    $res_c = mysqli_stmt_get_result($stmt_c);
    $norma['condiciones'] = [];
    while ($rc = mysqli_fetch_assoc($res_c)) {
        $norma['condiciones'][] = $rc;
    }
    mysqli_stmt_close($stmt_c);

    return $norma;
}

/**
 * Guardar o actualizar norma
 */
function norma_guardar(mysqli $conexion, int $empresa_id, array $datos): array
{
    $norma_id = intval($datos['norma_id'] ?? 0);
    $organismo_emisor = trim($datos['organismo_emisor'] ?? 'ARCA');
    $tipo_norma = trim($datos['tipo_norma'] ?? 'RESOLUCION_GENERAL');
    $nro_norma = trim($datos['nro_norma'] ?? '');
    $f_boletin_oficial = !empty($datos['f_boletin_oficial']) ? $datos['f_boletin_oficial'] : date('Y-m-d');
    $vigencia_desde = !empty($datos['vigencia_desde']) ? $datos['vigencia_desde'] : date('Y-m-d');
    $vigencia_hasta = !empty($datos['vigencia_hasta']) ? $datos['vigencia_hasta'] : null;
    $titulo = trim($datos['titulo'] ?? '');
    $resumen_tecnico = trim($datos['resumen_tecnico'] ?? '');
    $genera_nueva_obligacion = !empty($datos['genera_nueva_obligacion']) ? 1 : 0;
    $impuesto_obligacion_id = !empty($datos['impuesto_obligacion_id']) ? intval($datos['impuesto_obligacion_id']) : null;

    if (empty($nro_norma) || empty($titulo)) {
        return ['success' => false, 'message' => 'Número de norma y título son obligatorios.', 'data' => null, 'error' => 'VALIDATION'];
    }

    mysqli_begin_transaction($conexion);
    try {
        if ($norma_id > 0) {
            $sql = "UPDATE estudio__normas SET
                        organismo_emisor = ?, tipo_norma = ?, nro_norma = ?,
                        f_boletin_oficial = ?, vigencia_desde = ?, vigencia_hasta = ?,
                        titulo = ?, resumen_tecnico = ?, genera_nueva_obligacion = ?,
                        impuesto_obligacion_id = ?
                    WHERE empresa_id = ? AND norma_id = ?";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                "ssssssssiiii",
                $organismo_emisor,
                $tipo_norma,
                $nro_norma,
                $f_boletin_oficial,
                $vigencia_desde,
                $vigencia_hasta,
                $titulo,
                $resumen_tecnico,
                $genera_nueva_obligacion,
                $impuesto_obligacion_id,
                $empresa_id,
                $norma_id
            );
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        } else {
            $sql = "INSERT INTO estudio__normas (
                        empresa_id, organismo_emisor, tipo_norma, nro_norma,
                        f_boletin_oficial, vigencia_desde, vigencia_hasta,
                        titulo, resumen_tecnico, genera_nueva_obligacion,
                        impuesto_obligacion_id, tabla_estado_registro_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 3)";
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param(
                $stmt,
                "issssssssii",
                $empresa_id,
                $organismo_emisor,
                $tipo_norma,
                $nro_norma,
                $f_boletin_oficial,
                $vigencia_desde,
                $vigencia_hasta,
                $titulo,
                $resumen_tecnico,
                $genera_nueva_obligacion,
                $impuesto_obligacion_id
            );
            mysqli_stmt_execute($stmt);
            $norma_id = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmt);
        }

        // Si se enviaron condiciones dinámicas
        if (isset($datos['condiciones']) && is_array($datos['condiciones'])) {
            mysqli_query($conexion, "DELETE FROM estudio__normas_condiciones WHERE norma_id = {$norma_id}");
            $sql_ic = "INSERT INTO estudio__normas_condiciones (norma_id, parametro, operador, valor_esperado) VALUES (?, ?, ?, ?)";
            $stmt_ic = mysqli_prepare($conexion, $sql_ic);
            foreach ($datos['condiciones'] as $c) {
                if (empty($c['parametro']) || empty($c['valor_esperado'])) continue;
                $p = $c['parametro'];
                $op = $c['operador'] ?? '=';
                $val = trim($c['valor_esperado']);
                mysqli_stmt_bind_param($stmt_ic, "isss", $norma_id, $p, $op, $val);
                mysqli_stmt_execute($stmt_ic);
            }
            mysqli_stmt_close($stmt_ic);
        }

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Norma legal guardada exitosamente.', 'data' => ['norma_id' => $norma_id], 'error' => null];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => null, 'error' => $e->getMessage()];
    }
}

/**
 * Motor de Evaluación de Reglas Dinámicas sobre el Padrón de Clientes
 */
function norma_evaluar_impacto(mysqli $conexion, int $empresa_id, int $norma_id, int $usuario_id): array
{
    $norma = norma_obtener($conexion, $empresa_id, $norma_id);
    if (!$norma) {
        return ['success' => false, 'message' => 'Norma no encontrada.', 'data' => null, 'error' => 'NOT_FOUND'];
    }

    $condiciones = $norma['condiciones'];
    if (empty($condiciones)) {
        return ['success' => false, 'message' => 'La norma debe poseer al menos una regla condicional para evaluar impacto.', 'data' => null, 'error' => 'NO_RULES'];
    }

    // Traer todos los clientes activos del estudio
    $sql_cli = "SELECT c.cliente_id, c.razon_social, c.cuit, c.condicion_iva_id,
                       c.ingresos_brutos_tipo, c.jurisdiccion_sede_id, c.es_empleador,
                       cf.condicion_fiscal_codigo
                FROM estudio__clientes c
                INNER JOIN gestion__condiciones_fiscales cf ON cf.condicion_fiscal_id = c.condicion_iva_id
                WHERE c.empresa_id = ? AND c.tabla_estado_registro_id = 1";
    $stmt_c = mysqli_prepare($conexion, $sql_cli);
    mysqli_stmt_bind_param($stmt_c, "i", $empresa_id);
    mysqli_stmt_execute($stmt_c);
    $res_cli = mysqli_stmt_get_result($stmt_c);

    mysqli_begin_transaction($conexion);

    $alcanzados = 0;
    $exentos = 0;
    $obligaciones_creadas = 0;

    try {
        // Limpiar evaluaciones previas
        mysqli_query($conexion, "DELETE FROM estudio__normas_clientes_impacto WHERE norma_id = {$norma_id}");

        $sql_imp = "INSERT INTO estudio__normas_clientes_impacto (norma_id, cliente_id, f_evaluacion, estado_impacto)
                    VALUES (?, ?, NOW(), ?)";
        $stmt_imp = mysqli_prepare($conexion, $sql_imp);

        while ($cli = mysqli_fetch_assoc($res_cli)) {
            $cumple_todas = true;

            foreach ($condiciones as $cond) {
                $param = $cond['parametro'];
                $operador = $cond['operador'];
                $esperado = trim($cond['valor_esperado']);

                $valor_cliente = '';

                if ($param === 'CONDICION_IVA') {
                    $valor_cliente = $cli['condicion_fiscal_codigo'];
                } elseif ($param === 'EMPLEADOR') {
                    $valor_cliente = (string) $cli['es_empleador'];
                } elseif ($param === 'JURISDICCION') {
                    $valor_cliente = (string) $cli['jurisdiccion_sede_id'];
                }

                // Evaluación del operador
                if ($operador === '=') {
                    if (strcasecmp((string)$valor_cliente, (string)$esperado) !== 0) $cumple_todas = false;
                } elseif ($operador === '!=') {
                    if (strcasecmp((string)$valor_cliente, (string)$esperado) === 0) $cumple_todas = false;
                } elseif ($operador === 'IN') {
                    $lista = array_map('trim', explode(',', $esperado));
                    if (!in_array($valor_cliente, $lista, true)) $cumple_todas = false;
                }

                if (!$cumple_todas) break;
            }

            $estado = $cumple_todas ? 'ALCANZADO' : 'EXENTO';
            if ($cumple_todas) {
                $alcanzados++;
                // Si la norma genera nueva obligación impositiva, asignarla a estudio__clientes_obligaciones
                if ($norma['genera_nueva_obligacion'] == 1 && !empty($norma['impuesto_obligacion_id'])) {
                    $sql_asig = "INSERT INTO estudio__clientes_obligaciones (
                                    empresa_id, cliente_id, impuesto_obligacion_id, jurisdiccion_id, tabla_estado_registro_id
                                 ) VALUES (?, ?, ?, ?, 1)
                                 ON DUPLICATE KEY UPDATE tabla_estado_registro_id = 1";
                    $stmt_asig = mysqli_prepare($conexion, $sql_asig);
                    $juris = $cli['jurisdiccion_sede_id'];
                    mysqli_stmt_bind_param($stmt_asig, "iiii", $empresa_id, $cli['cliente_id'], $norma['impuesto_obligacion_id'], $juris);
                    mysqli_stmt_execute($stmt_asig);
                    $obligaciones_creadas++;
                    mysqli_stmt_close($stmt_asig);
                }
            } else {
                $exentos++;
            }

            mysqli_stmt_bind_param($stmt_imp, "iis", $norma_id, $cli['cliente_id'], $estado);
            mysqli_stmt_execute($stmt_imp);
        }
        mysqli_stmt_close($stmt_c);
        mysqli_stmt_close($stmt_imp);

        // Actualizar estado de la norma a Vigente / Impactada (5)
        mysqli_query($conexion, "UPDATE estudio__normas SET tabla_estado_registro_id = 5 WHERE norma_id = {$norma_id}");

        mysqli_commit($conexion);

        return [
            'success' => true,
            'message' => "Motor evaluó exitosamente: {$alcanzados} clientes alcanzados por la norma ({$exentos} exentos). Se actualizaron {$obligaciones_creadas} obligaciones.",
            'data' => [
                'alcanzados' => $alcanzados,
                'exentos' => $exentos,
                'obligaciones_generadas' => $obligaciones_creadas
            ],
            'error' => null
        ];
    } catch (Throwable $e) {
        mysqli_rollback($conexion);
        return ['success' => false, 'message' => 'Error: ' . $e->getMessage(), 'data' => null, 'error' => $e->getMessage()];
    }
}

/**
 * Listar clientes alcanzados por una norma
 */
function norma_impacto_listar(mysqli $conexion, int $norma_id): array
{
    $sql = "SELECT i.impacto_id, i.norma_id, i.cliente_id, i.f_evaluacion, i.estado_impacto,
                   c.razon_social, c.cuit, cf.condicion_fiscal_codigo
            FROM estudio__normas_clientes_impacto i
            INNER JOIN estudio__clientes c ON c.cliente_id = i.cliente_id
            INNER JOIN gestion__condiciones_fiscales cf ON cf.condicion_fiscal_id = c.condicion_iva_id
            WHERE i.norma_id = ?
            ORDER BY i.estado_impacto ASC, c.razon_social ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $norma_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $lista[] = $r;
    }
    mysqli_stmt_close($stmt);
    return $lista;
}
