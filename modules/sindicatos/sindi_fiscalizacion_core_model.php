<?php
/**
 * modules/sindicatos/sindi_fiscalizacion_core_model.php
 * Motor Core de Fiscalización: Detección de mora, matriz de cumplimiento,
 * control de nómina (empleados omitidos) y generación de DDJJ de oficio.
 * Módulo 6 — Sindicatos (§2.5, §5.5, §10).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/accesos_empleadores.php';
require_once __DIR__ . '/sindi_ddjj_core_model.php';

/**
 * Recalcula la categoría de mora de todos los empleadores de la empresa (o uno en particular).
 * Evalúa los períodos en sindi__empleadores_periodos y comprobantes impagos en gestion__comprobantes.
 * 
 * @param mysqli $c
 * @param int $empresa_id
 * @param int|null $empleador_id Si es null, recalcula para toda la empresa
 * @return array Estadísticas del recálculo
 */
function sindiFiscalizacionRecalcularMora(mysqli $c, int $empresa_id, ?int $empleador_id = null): array {
    // 1. Obtener rangos de categorías de mora de la empresa
    $catSql = "SELECT categoria_mora_id, codigo, dias_desde, dias_hasta 
               FROM sindi__categorias_mora 
               WHERE tabla_estado_registro_id = 1 
               ORDER BY dias_desde ASC";
    $catRes = $c->query($catSql);
    $categorias = [];
    while ($row = $catRes->fetch_assoc()) {
        $categorias[] = $row;
    }

    if (empty($categorias)) {
        throw new RuntimeException("No hay categorías de mora configuradas en sindi__categorias_mora");
    }

    // 2. Obtener empleadores activos
    $whereEmp = "WHERE e.empresa_id = ? AND e.tabla_estado_registro_id = 1";
    $params = [$empresa_id];
    $types = "i";
    if ($empleador_id !== null && $empleador_id > 0) {
        $whereEmp .= " AND e.empleador_id = ?";
        $params[] = $empleador_id;
        $types .= "i";
    }

    $empSql = "SELECT e.empleador_id, e.categoria_mora_id 
               FROM sindi__empleadores e 
               $whereEmp";
    $stmt = $c->prepare($empSql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $empleadores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    $stats = [
        'procesados' => 0,
        'actualizados' => 0,
        'por_categoria' => []
    ];
    foreach ($categorias as $cat) {
        $stats['por_categoria'][$cat['codigo']] = 0;
    }

    $hoy = date('Y-m-d');

    // 3. Evaluar cada empleador
    $updStmt = $c->prepare("UPDATE sindi__empleadores SET categoria_mora_id = ? WHERE empleador_id = ?");

    foreach ($empleadores as $emp) {
        $empId = (int)$emp['empleador_id'];

        // Buscar el máximo atraso en días considerando:
        // A) Períodos en sindi__empleadores_periodos que estén PENDIENTES o con fecha_vto superada y sin DDJJ
        // B) Comprobantes con saldo pendiente en gestion__comprobantes con fecha de vto anterior a hoy
        $diasAtraso = 0;

        // Consulta A: Períodos no presentados vencidos
        $pSql = "SELECT DATEDIFF(?, f_vto) AS dias 
                 FROM sindi__empleadores_periodos 
                 WHERE empresa_id = ? AND empleador_id = ? 
                   AND situacion IN ('PENDIENTE', 'FUERA_DE_TERMINO') 
                   AND f_vto < ? 
                 ORDER BY f_vto ASC LIMIT 1";
        $pStmt = $c->prepare($pSql);
        $pStmt->bind_param("siis", $hoy, $empresa_id, $empId, $hoy);
        $pStmt->execute();
        $pRes = $pStmt->get_result()->fetch_assoc();
        $pStmt->close();

        if ($pRes && (int)$pRes['dias'] > $diasAtraso) {
            $diasAtraso = (int)$pRes['dias'];
        }

        // Consulta B: Deudas impagas (comprobantes con importe_pendiente > 0 vencidos)
        $cSql = "SELECT DATEDIFF(?, c.f_vto) AS dias 
                 FROM gestion__comprobantes c
                 JOIN sindi__empleadores e ON e.entidad_id = c.entidad_id
                 WHERE c.empresa_id = ? AND e.empleador_id = ? 
                   AND c.importe_pendiente > 0 
                   AND c.f_vto < ? 
                   AND c.tabla_estado_registro_id = 5 
                 ORDER BY c.f_vto ASC LIMIT 1";
        $cStmt = $c->prepare($cSql);
        $cStmt->bind_param("siis", $hoy, $empresa_id, $empId, $hoy);
        $cStmt->execute();
        $cRes = $cStmt->get_result()->fetch_assoc();
        $cStmt->close();

        if ($cRes && (int)$cRes['dias'] > $diasAtraso) {
            $diasAtraso = (int)$cRes['dias'];
        }

        // 4. Mapear días de atraso a categoría de mora
        $catAsignadaId = $categorias[0]['categoria_mora_id'];
        $catCodigo = $categorias[0]['codigo'];

        foreach ($categorias as $cat) {
            $dDesde = (int)$cat['dias_desde'];
            $dHasta = (int)$cat['dias_hasta'];
            if ($diasAtraso >= $dDesde && $diasAtraso <= $dHasta) {
                $catAsignadaId = (int)$cat['categoria_mora_id'];
                $catCodigo = $cat['codigo'];
                break;
            }
        }

        // 5. Actualizar si cambió
        if ((int)$emp['categoria_mora_id'] !== $catAsignadaId) {
            $updStmt->bind_param("ii", $catAsignadaId, $empId);
            $updStmt->execute();
            $stats['actualizados']++;
        }

        $stats['procesados']++;
        $stats['por_categoria'][$catCodigo] = ($stats['por_categoria'][$catCodigo] ?? 0) + 1;
    }

    $updStmt->close();
    return $stats;
}

/**
 * Genera la matriz de cumplimiento y fiscalización (Empleador x Período).
 * 
 * @param mysqli $c
 * @param int $empresa_id
 * @param array $filtros ['periodo_desde' => 202601, 'periodo_hasta' => 202610, 'categoria_mora_id' => null, 'busqueda' => '']
 * @return array ['periodos' => [...], 'empleadores' => [...]]
 */
function sindiFiscalizacionMatrizCumplimiento(mysqli $c, int $empresa_id, array $filtros = []): array {
    $periodoHasta = !empty($filtros['periodo_hasta']) ? (int)$filtros['periodo_hasta'] : (int)date('Ym');
    $periodoDesde = !empty($filtros['periodo_desde']) ? (int)$filtros['periodo_desde'] : (int)date('Ym', strtotime('-5 months'));

    // Lista de períodos ordenados ascendente
    $periodos = [];
    $curr = $periodoDesde;
    while ($curr <= $periodoHasta) {
        $periodos[] = $curr;
        $y = (int)substr((string)$curr, 0, 4);
        $m = (int)substr((string)$curr, 4, 2);
        $m++;
        if ($m > 12) {
            $m = 1;
            $y++;
        }
        $curr = ($y * 100) + $m;
    }

    // Filtros de empleadores
    $where = "WHERE e.empresa_id = ? AND e.tabla_estado_registro_id = 1";
    $params = [$empresa_id];
    $types = "i";

    if (!empty($filtros['categoria_mora_id'])) {
        $where .= " AND e.categoria_mora_id = ?";
        $params[] = (int)$filtros['categoria_mora_id'];
        $types .= "i";
    }

    if (!empty($filtros['busqueda'])) {
        $like = '%' . trim($filtros['busqueda']) . '%';
        $where .= " AND (ent.entidad_nombre LIKE ? OR ent.cuit LIKE ?)";
        $params[] = $like;
        $params[] = $like;
        $types .= "ss";
    }

    $sql = "SELECT e.empleador_id, ent.entidad_nombre AS razon_social, ent.cuit, 
                   cm.categoria_mora_id, cm.codigo AS mora_codigo, cm.nombre AS mora_nombre, cm.color
            FROM sindi__empleadores e
            JOIN gestion__entidades ent ON ent.entidad_id = e.entidad_id
            LEFT JOIN sindi__categorias_mora cm ON cm.categoria_mora_id = e.categoria_mora_id
            $where
            ORDER BY ent.entidad_nombre ASC
            LIMIT 100";

    $stmt = $c->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $empleadoresRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if (empty($empleadoresRows)) {
        return ['periodos' => $periodos, 'empleadores' => []];
    }

    $empIds = array_column($empleadoresRows, 'empleador_id');
    $placeholders = implode(',', array_fill(0, count($empIds), '?'));

    // Obtener estado de cada período para los empleadores cargados
    $pSql = "SELECT ep.empleador_id, ep.periodo, ep.situacion, ep.f_vto, ep.ddjj_vigente_id,
                    d.total, d.tabla_estado_registro_id AS ddjj_estado
             FROM sindi__empleadores_periodos ep
             LEFT JOIN sindi__ddjj d ON d.ddjj_id = ep.ddjj_vigente_id
             WHERE ep.empresa_id = ? 
               AND ep.periodo BETWEEN ? AND ? 
               AND ep.empleador_id IN ($placeholders)";

    $pStmt = $c->prepare($pSql);
    $bindTypes = "iii" . str_repeat('i', count($empIds));
    $bindParams = array_merge([$empresa_id, $periodoDesde, $periodoHasta], $empIds);
    $pStmt->bind_param($bindTypes, ...$bindParams);
    $pStmt->execute();
    $matrizData = $pStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $pStmt->close();

    $mapData = [];
    foreach ($matrizData as $row) {
        $key = $row['empleador_id'] . '_' . $row['periodo'];
        $mapData[$key] = $row;
    }

    $hoy = date('Y-m-d');
    $empleadores = [];
    foreach ($empleadoresRows as $emp) {
        $rowEmp = [
            'empleador_id' => (int)$emp['empleador_id'],
            'razon_social' => $emp['razon_social'],
            'cuit' => $emp['cuit'],
            'mora_codigo' => $emp['mora_codigo'] ?? 'PUNTUAL',
            'mora_nombre' => $emp['mora_nombre'] ?? 'Puntual',
            'mora_color' => $emp['color'] ?? '#28a745',
            'periodos' => []
        ];

        foreach ($periodos as $per) {
            $key = $emp['empleador_id'] . '_' . $per;
            if (isset($mapData[$key])) {
                $d = $mapData[$key];
                $situacion = $d['situacion'];
                if ($situacion === 'PENDIENTE' && $d['f_vto'] < $hoy) {
                    $situacion = 'FUERA_DE_TERMINO';
                }
                $rowEmp['periodos'][$per] = [
                    'situacion' => $situacion,
                    'total' => (float)($d['total'] ?? 0),
                    'ddjj_id' => $d['ddjj_vigente_id'] ? (int)$d['ddjj_vigente_id'] : null,
                    'f_vto' => $d['f_vto']
                ];
            } else {
                $rowEmp['periodos'][$per] = [
                    'situacion' => 'SIN_REGISTRO',
                    'total' => 0.00,
                    'ddjj_id' => null,
                    'f_vto' => null
                ];
            }
        }
        $empleadores[] = $rowEmp;
    }

    return [
        'periodos' => $periodos,
        'empleadores' => $empleadores
    ];
}

/**
 * Detecta trabajadores omitidos (ddjj_empleados_out) comparando una DDJJ o período
 * contra el período anterior o las relaciones laborales vigentes sin baja informada.
 * 
 * @param mysqli $c
 * @param int $empresa_id
 * @param int $empleador_id
 * @param int $periodo
 * @return array Lista de trabajadores faltantes con su detalle
 */
function sindiFiscalizacionDetectarEmpleadosOmitidos(mysqli $c, int $empresa_id, int $empleador_id, int $periodo): array {
    // 1. Obtener la nómina de la DDJJ del período actual (si existe)
    $actualPersonaIds = [];
    $ddjjStmt = $c->prepare("SELECT d.ddjj_id 
                            FROM sindi__ddjj d 
                            WHERE d.empresa_id = ? AND d.empleador_id = ? AND d.periodo = ? 
                              AND d.tabla_estado_registro_id IN (3, 28) 
                            ORDER BY d.secuencia DESC LIMIT 1");
    $ddjjStmt->bind_param("iii", $empresa_id, $empleador_id, $periodo);
    $ddjjStmt->execute();
    $ddjjRes = $ddjjStmt->get_result()->fetch_assoc();
    $ddjjStmt->close();

    if ($ddjjRes) {
        $ddjjId = (int)$ddjjRes['ddjj_id'];
        $nStmt = $c->prepare("SELECT persona_id FROM sindi__ddjj_empleados WHERE ddjj_id = ?");
        $nStmt->bind_param("i", $ddjjId);
        $nStmt->execute();
        $actualPersonaIds = array_column($nStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'persona_id');
        $nStmt->close();
    }

    // 2. Determinar período anterior (AAAAMM)
    $y = (int)substr((string)$periodo, 0, 4);
    $m = (int)substr((string)$periodo, 4, 2);
    $m--;
    if ($m < 1) {
        $m = 12;
        $y--;
    }
    $periodoAnt = ($y * 100) + $m;

    // 3. Buscar empleados de la última DDJJ anterior confirmada
    $sqlAnt = "SELECT de.persona_id, p.cuil, p.apellido, p.nombre, de.rem_1, de.rem_2, de.categoria_id, cat.nombre AS categoria_nombre
               FROM sindi__ddjj d
               JOIN sindi__ddjj_empleados de ON de.ddjj_id = d.ddjj_id
               JOIN sindi__personas p ON p.persona_id = de.persona_id
               LEFT JOIN sindi__categorias cat ON cat.categoria_id = de.categoria_id
               WHERE d.empresa_id = ? AND d.empleador_id = ? AND d.periodo = ? AND d.tabla_estado_registro_id = 28
                 AND de.baja_motivo_id IS NULL";
    $stmtAnt = $c->prepare($sqlAnt);
    $stmtAnt->bind_param("iii", $empresa_id, $empleador_id, $periodoAnt);
    $stmtAnt->execute();
    $empleadosAnt = $stmtAnt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtAnt->close();

    $omitidos = [];
    foreach ($empleadosAnt as $ant) {
        $pId = (int)$ant['persona_id'];
        if (!in_array($pId, $actualPersonaIds, true)) {
            $omitidos[] = [
                'persona_id' => $pId,
                'cuil' => $ant['cuil'],
                'nombre_completo' => trim($ant['apellido'] . ', ' . $ant['nombre']),
                'categoria_nombre' => $ant['categoria_nombre'] ?? 'Sin Categoría',
                'ultima_remuneracion' => (float)$ant['rem_1'] + (float)$ant['rem_2'],
                'motivo_alerta' => "Declarado en período $periodoAnt y omitido en $periodo sin registro de baja."
            ];
        }
    }

    return $omitidos;
}

/**
 * Genera una Declaración Jurada de Oficio para un empleador moroso en un período dado.
 * Toma como base la última nómina declarada o las relaciones laborales activas.
 * 
 * @param mysqli $c
 * @param int $empresa_id
 * @param int $empleador_id
 * @param int $periodo
 * @param int $usuario_id
 * @param bool $confirmar Si es true, la confirma y genera comprobantes de deuda
 * @return array Datos de la DDJJ generada
 */
function sindiFiscalizacionGenerarDdjjOficio(
    mysqli $c, 
    int $empresa_id, 
    int $empleador_id, 
    int $periodo, 
    int $usuario_id, 
    bool $confirmar = false
): array {
    // 1. Validar que no exista ya una DDJJ confirmada para ese período
    $chk = $c->prepare("SELECT ddjj_id, tipo, tabla_estado_registro_id 
                        FROM sindi__ddjj 
                        WHERE empresa_id = ? AND empleador_id = ? AND periodo = ? 
                          AND tabla_estado_registro_id IN (3, 28) 
                        ORDER BY secuencia DESC LIMIT 1");
    $chk->bind_param("iii", $empresa_id, $empleador_id, $periodo);
    $chk->execute();
    $existente = $chk->get_result()->fetch_assoc();
    $chk->close();

    if ($existente && (int)$existente['tabla_estado_registro_id'] === 28 && $existente['tipo'] === 'EMPLEADOR') {
        throw new RuntimeException("El empleador ya tiene una DDJJ presentada formalmente para el período $periodo.");
    }

    // 2. Obtener la nómina de la última DDJJ presentada
    $lastDdjjSql = "SELECT ddjj_id FROM sindi__ddjj 
                    WHERE empresa_id = ? AND empleador_id = ? AND tabla_estado_registro_id = 28 
                    ORDER BY periodo DESC, secuencia DESC LIMIT 1";
    $lStmt = $c->prepare($lastDdjjSql);
    $lStmt->bind_param("ii", $empresa_id, $empleador_id);
    $lStmt->execute();
    $lastRow = $lStmt->get_result()->fetch_assoc();
    $lStmt->close();

    $nomina = [];
    if ($lastRow) {
        $lastId = (int)$lastRow['ddjj_id'];
        $nSql = "SELECT p.cuil, p.apellido, p.nombre, de.persona_id, de.categoria_id, de.rem_1, de.rem_2, de.rem_3,
                        de.media_jornada, de.jubilado, de.afiliado, de.obra_social_id, de.localidad_id
                 FROM sindi__ddjj_empleados de
                 JOIN sindi__personas p ON p.persona_id = de.persona_id
                 WHERE de.ddjj_id = ? AND de.baja_motivo_id IS NULL";
        $nStmt = $c->prepare($nSql);
        $nStmt->bind_param("i", $lastId);
        $nStmt->execute();
        $nomina = $nStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $nStmt->close();
    }

    // Si no había DDJJ previa, buscar relaciones laborales activas con escalas del CCT
    if (empty($nomina)) {
        $relSql = "SELECT p.cuil, p.apellido, p.nombre, r.persona_id, emp.convenio_id, c.categoria_id, ce.basico
                   FROM sindi__relaciones_laborales r
                   JOIN sindi__personas p ON p.persona_id = r.persona_id
                   JOIN sindi__empleadores emp ON emp.empleador_id = r.empleador_id
                   LEFT JOIN sindi__categorias c ON c.convenio_id = emp.convenio_id AND c.tabla_estado_registro_id = 1
                   LEFT JOIN sindi__categorias_escalas ce ON ce.categoria_id = c.categoria_id AND ce.periodo = ?
                   WHERE r.empresa_id = ? AND r.empleador_id = ? AND r.tabla_estado_registro_id = 1 AND r.f_baja IS NULL
                   GROUP BY r.persona_id";
        $rStmt = $c->prepare($relSql);
        $rStmt->bind_param("iii", $periodo, $empresa_id, $empleador_id);
        $rStmt->execute();
        $relRows = $rStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $rStmt->close();

        foreach ($relRows as $rel) {
            $nomina[] = [
                'cuil' => $rel['cuil'],
                'nombre' => trim($rel['apellido'] . ' ' . $rel['nombre']),
                'persona_id' => (int)$rel['persona_id'],
                'categoria_id' => $rel['categoria_id'] ? (int)$rel['categoria_id'] : null,
                'rem_1' => (float)($rel['basico'] ?? 100000.00),
                'rem_2' => 0.00,
                'rem_3' => 0.00,
                'media_jornada' => 0,
                'jubilado' => 0,
                'afiliado' => 0,
                'obra_social_id' => null,
                'localidad_id' => null
            ];
        }
    }

    if (empty($nomina)) {
        throw new RuntimeException("No se encontraron trabajadores ni nóminas anteriores para determinar de oficio.");
    }

    // 3. Guardar borrador de DDJJ de Oficio usando el motor core
    $resBorrador = sindi_guardar_borrador_ddjj($c, $empresa_id, $usuario_id, [
        'empleador_id' => $empleador_id,
        'periodo' => $periodo,
        'tipo' => 'OFICIO',
        'origen_ingreso' => 'MANUAL'
    ], $nomina);

    if (empty($resBorrador['success'])) {
        throw new RuntimeException($resBorrador['message'] ?? 'Error al generar la DDJJ de oficio.');
    }

    $ddjjId = (int)$resBorrador['ddjj_id'];
    $total = (float)$resBorrador['total'];
    $codVerif = $resBorrador['codigo_verificacion'];

    // 4. Actualizar situación en sindi__empleadores_periodos
    $fVto = $resBorrador['f_vto'] ?? date('Y-m-d', strtotime('+10 days'));
    $updEp = $c->prepare("INSERT INTO sindi__empleadores_periodos (
        empresa_id, empleador_id, periodo, f_vto, situacion, ddjj_vigente_id
    ) VALUES (?, ?, ?, ?, 'OFICIO', ?)
    ON DUPLICATE KEY UPDATE situacion = 'OFICIO', ddjj_vigente_id = VALUES(ddjj_vigente_id)");
    $updEp->bind_param("iiisi", $empresa_id, $empleador_id, $periodo, $fVto, $ddjjId);
    $updEp->execute();
    $updEp->close();

    // 5. Confirmar formalmente si se requirió
    if ($confirmar) {
        $resConf = sindi_confirmar_ddjj($c, $empresa_id, $usuario_id, $ddjjId);
        if (empty($resConf['success'])) {
            throw new RuntimeException($resConf['message'] ?? 'Error al confirmar la DDJJ de oficio.');
        }
    }

    return [
        'ddjj_id' => $ddjjId,
        'empleador_id' => $empleador_id,
        'periodo' => $periodo,
        'tipo' => 'OFICIO',
        'empleados_cargados' => count($nomina),
        'total' => $total,
        'codigo_verificacion' => $codVerif,
        'confirmada' => $confirmar
    ];
}
