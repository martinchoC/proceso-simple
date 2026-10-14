<?php
/**
 * modules/sindicatos/sindi_actas_core_model.php
 * Motor Core de Actas de Inspección y Determinación de Deuda Sindical.
 * Módulo 6 — Sindicatos (§2.5, §5.5, §7.1, §7.2).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/empresa_config.php';
require_once __DIR__ . '/sindi_intereses_core_model.php';
require_once __DIR__ . '/../gestion_normal/tesoreria_core_model.php';
require_once __DIR__ . '/sindi_comprobantes_core.php';

/**
 * Crea una nueva cabecera de Acta de Inspección.
 * 
 * @param mysqli $c
 * @param int $empresa_id
 * @param array $datos ['empleador_id', 'tipo_acta', 'inspector_usuario_id', 'f_inicio', 'f_notificacion', 'observaciones']
 * @param int $usuario_id
 * @return int ID del acta creada
 */
function sindiActaCrear(mysqli $c, int $empresa_id, array $datos, int $usuario_id): int {
    $empleador_id = (int)$datos['empleador_id'];
    $tipo_acta = in_array($datos['tipo_acta'] ?? '', ['NORMAL', 'LIBRE_DEUDA', 'COMPLEMENTARIA']) ? $datos['tipo_acta'] : 'NORMAL';
    $inspector_usuario_id = !empty($datos['inspector_usuario_id']) ? (int)$datos['inspector_usuario_id'] : null;
    $f_inicio = !empty($datos['f_inicio']) ? $datos['f_inicio'] : date('Y-m-d');
    $f_notificacion = !empty($datos['f_notificacion']) ? $datos['f_notificacion'] : null;
    $observaciones = $datos['observaciones'] ?? '';

    // Generar correlativo de número si no viene especificado
    $anio = date('Y', strtotime($f_inicio));
    $cntStmt = $c->prepare("SELECT COUNT(*) AS total FROM sindi__actas WHERE empresa_id = ? AND YEAR(f_inicio) = ?");
    $cntStmt->bind_param("is", $empresa_id, $anio);
    $cntStmt->execute();
    $cntRow = $cntStmt->get_result()->fetch_assoc();
    $cntStmt->close();
    $proxNro = ((int)$cntRow['total']) + 1;
    $numero_acta = !empty($datos['numero_acta']) ? trim($datos['numero_acta']) : sprintf("ACT-%s-%04d", $anio, $proxNro);

    $f_vencimiento_72h = null;
    if ($f_notificacion) {
        $f_vencimiento_72h = date('Y-m-d H:i:s', strtotime($f_notificacion . ' +72 hours'));
    }

    $sql = "INSERT INTO sindi__actas (
        empresa_id, empleador_id, numero_acta, tipo_acta, inspector_usuario_id,
        f_inicio, f_notificacion, f_vencimiento_72h, observaciones,
        usuario_creacion_id, tabla_estado_registro_id
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 3)";

    $stmt = $c->prepare($sql);
    $stmt->bind_param(
        "iississssi",
        $empresa_id, $empleador_id, $numero_acta, $tipo_acta, $inspector_usuario_id,
        $f_inicio, $f_notificacion, $f_vencimiento_72h, $observaciones,
        $usuario_id
    );
    $stmt->execute();
    $acta_id = (int)$c->insert_id;
    $stmt->close();

    return $acta_id;
}

/**
 * Agrega un período inspeccionado al acta, calculando diferencias e intereses por concepto.
 * 
 * @param mysqli $c
 * @param int $acta_id
 * @param int $periodo Período AAAAMM
 * @param array $items Array de ['concepto_id' => int, 'declarado' => float, 'determinado' => float, 'pagado' => float]
 * @param string|null $f_calculo_interes Fecha a la cual liquidar los intereses (default f_inicio del acta)
 * @return int ID de sindi__actas_periodos
 */
function sindiActaAgregarPeriodo(mysqli $c, int $acta_id, int $periodo, array $items = [], ?string $f_calculo_interes = null): int {
    // 1. Obtener datos del acta
    $aStmt = $c->prepare("SELECT empresa_id, f_inicio, tabla_estado_registro_id FROM sindi__actas WHERE acta_id = ?");
    $aStmt->bind_param("i", $acta_id);
    $aStmt->execute();
    $acta = $aStmt->get_result()->fetch_assoc();
    $aStmt->close();

    if (!$acta) {
        throw new RuntimeException("Acta $acta_id no encontrada.");
    }
    if ((int)$acta['tabla_estado_registro_id'] === 5) {
        throw new RuntimeException("No se pueden agregar períodos a un acta ya confirmada.");
    }

    $empresa_id = (int)$acta['empresa_id'];
    $fCalculo = $f_calculo_interes ?: ($acta['f_inicio'] ?: date('Y-m-d'));

    // 2. Obtener fecha de vencimiento original del período
    $vStmt = $c->prepare("SELECT f_vto FROM sindi__vencimientos WHERE empresa_id = ? AND periodo = ? LIMIT 1");
    $vStmt->bind_param("ii", $empresa_id, $periodo);
    $vStmt->execute();
    $vRow = $vStmt->get_result()->fetch_assoc();
    $vStmt->close();
    
    // Si no tiene vencimiento exacto, estimar el día 15 del mes siguiente
    $fVtoOriginal = $vRow['f_vto'] ?? null;
    if (!$fVtoOriginal) {
        $y = (int)substr((string)$periodo, 0, 4);
        $m = (int)substr((string)$periodo, 4, 2);
        $m++;
        if ($m > 12) { $m = 1; $y++; }
        $fVtoOriginal = sprintf('%04d-%02d-15', $y, $m);
    }

    $c->begin_transaction();
    try {
        // 3. Crear cabecera de período en acta
        $insP = $c->prepare("INSERT INTO sindi__actas_periodos (
            acta_id, empresa_id, periodo, f_vto_original
        ) VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE f_vto_original = VALUES(f_vto_original)");
        $insP->bind_param("iiis", $acta_id, $empresa_id, $periodo, $fVtoOriginal);
        $insP->execute();
        $acta_periodo_id = (int)($c->insert_id ?: 0);
        $insP->close();

        if ($acta_periodo_id === 0) {
            $q = $c->prepare("SELECT acta_periodo_id FROM sindi__actas_periodos WHERE acta_id = ? AND periodo = ?");
            $q->bind_param("ii", $acta_id, $periodo);
            $q->execute();
            $acta_periodo_id = (int)$q->get_result()->fetch_assoc()['acta_periodo_id'];
            $q->close();
        }

        // 4. Si no se especificaron items, cargar los conceptos activos del gremio
        if (empty($items)) {
            $cSql = "SELECT concepto_id FROM sindi__conceptos WHERE empresa_id = ? AND tabla_estado_registro_id = 1";
            $cStmt = $c->prepare($cSql);
            $cStmt->bind_param("i", $empresa_id);
            $cStmt->execute();
            $conceptosRows = $cStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $cStmt->close();
            foreach ($conceptosRows as $cr) {
                $items[] = [
                    'concepto_id' => (int)$cr['concepto_id'],
                    'declarado' => 0.00,
                    'determinado' => 0.00,
                    'pagado' => 0.00
                ];
            }
        }

        $totDeclarado = 0.00;
        $totDeterminado = 0.00;
        $totPagado = 0.00;
        $totDiferencia = 0.00;
        $totIntereses = 0.00;

        $insItem = $c->prepare("INSERT INTO sindi__actas_items (
            acta_periodo_id, empresa_id, concepto_id, importe_declarado,
            importe_determinado, importe_pagado, diferencia_capital,
            intereses, total_item
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            importe_declarado = VALUES(importe_declarado),
            importe_determinado = VALUES(importe_determinado),
            importe_pagado = VALUES(importe_pagado),
            diferencia_capital = VALUES(diferencia_capital),
            intereses = VALUES(intereses),
            total_item = VALUES(total_item)");

        foreach ($items as $it) {
            $concepto_id = (int)$it['concepto_id'];
            $declarado = round((float)($it['declarado'] ?? 0), 2);
            $determinado = round((float)($it['determinado'] ?? 0), 2);
            $pagado = round((float)($it['pagado'] ?? 0), 2);

            $diferencia = round(max(0.00, $determinado - $pagado), 2);

            // Calcular interés sobre la diferencia de capital desde f_vto_original hasta fCalculo
            $interes = 0.00;
            if ($diferencia > 0 && $fCalculo > $fVtoOriginal) {
                $calcInteres = sindi_calcular_interes_deuda($diferencia, $fVtoOriginal, $fCalculo, [], $c, $empresa_id);
                $interes = (float)$calcInteres['interes_total'];
            }

            $totalItem = round($diferencia + $interes, 2);

            $insItem->bind_param(
                "iiidddddd",
                $acta_periodo_id, $empresa_id, $concepto_id, $declarado,
                $determinado, $pagado, $diferencia, $interes, $totalItem
            );
            $insItem->execute();

            $totDeclarado += $declarado;
            $totDeterminado += $determinado;
            $totPagado += $pagado;
            $totDiferencia += $diferencia;
            $totIntereses += $interes;
        }
        $insItem->close();

        // 5. Actualizar totales del período
        $totPeriodo = round($totDiferencia + $totIntereses, 2);
        $updP = $c->prepare("UPDATE sindi__actas_periodos SET
            total_declarado = ?, total_determinado = ?, total_pagado = ?,
            saldo_capital = ?, intereses = ?, total_periodo = ?
            WHERE acta_periodo_id = ?");
        $updP->bind_param("ddddddi", $totDeclarado, $totDeterminado, $totPagado, $totDiferencia, $totIntereses, $totPeriodo, $acta_periodo_id);
        $updP->execute();
        $updP->close();

        // 6. Recalcular y actualizar totales acumulados en la cabecera del acta
        sindiActaRecalcularTotales($c, $acta_id);

        $c->commit();
        return $acta_periodo_id;
    } catch (Throwable $e) {
        $c->rollback();
        throw $e;
    }
}

/**
 * Recalcula y actualiza los totales consolidados de un acta a partir de sus períodos.
 */
function sindiActaRecalcularTotales(mysqli $c, int $acta_id): void {
    $sumSql = "SELECT 
        COALESCE(SUM(total_declarado), 0.00) AS tot_decl,
        COALESCE(SUM(total_determinado), 0.00) AS tot_det,
        COALESCE(SUM(total_pagado), 0.00) AS tot_pag,
        COALESCE(SUM(saldo_capital), 0.00) AS tot_dif,
        COALESCE(SUM(intereses), 0.00) AS tot_int,
        COALESCE(SUM(total_periodo), 0.00) AS tot_acta
    FROM sindi__actas_periodos
    WHERE acta_id = ?";

    $stmt = $c->prepare($sumSql);
    $stmt->bind_param("i", $acta_id);
    $stmt->execute();
    $totals = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $updActa = $c->prepare("UPDATE sindi__actas SET
        total_declarado = ?, total_determinado = ?, total_pagado = ?,
        total_diferencia = ?, total_intereses = ?, total_acta = ?
        WHERE acta_id = ?");
    $updActa->bind_param(
        "ddddddi",
        $totals['tot_decl'], $totals['tot_det'], $totals['tot_pag'],
        $totals['tot_dif'], $totals['tot_int'], $totals['tot_acta'],
        $acta_id
    );
    $updActa->execute();
    $updActa->close();
}

/**
 * Confirma el Acta de Inspección, genera su comprobante contable formal tipo ACT (308)
 * e impacta el asiento contable en el ERP.
 * 
 * @param mysqli $c
 * @param int $acta_id
 * @param int $usuario_id
 * @return array Datos del comprobante y asiento generados
 */
function sindiActaConfirmar(mysqli $c, int $acta_id, int $usuario_id): array {
    $c->begin_transaction();
    try {
        // 1. Obtener datos del acta con bloqueo FOR UPDATE
        $aSql = "SELECT a.*, e.entidad_id 
                 FROM sindi__actas a
                 JOIN sindi__empleadores e ON e.empleador_id = a.empleador_id
                 WHERE a.acta_id = ? FOR UPDATE";
        $aStmt = $c->prepare($aSql);
        $aStmt->bind_param("i", $acta_id);
        $aStmt->execute();
        $acta = $aStmt->get_result()->fetch_assoc();
        $aStmt->close();

        if (!$acta) {
            throw new RuntimeException("Acta $acta_id no encontrada.");
        }
        if ((int)$acta['tabla_estado_registro_id'] === 5) {
            throw new RuntimeException("El acta $acta_id ya se encuentra confirmada.");
        }
        if ((float)$acta['total_acta'] <= 0) {
            throw new RuntimeException("El acta no tiene deuda determinada exigible.");
        }

        $empresa_id = (int)$acta['empresa_id'];
        $entidad_id = (int)$acta['entidad_id'];
        $totalActa = (float)$acta['total_acta'];
        $totalDiferencia = (float)$acta['total_diferencia'];
        $totalIntereses = (float)$acta['total_intereses'];
        $fEmision = $acta['f_cierre'] ?: date('Y-m-d');
        $fVto = $acta['f_vencimiento_72h'] ? date('Y-m-d', strtotime($acta['f_vencimiento_72h'])) : date('Y-m-d', strtotime('+3 days'));

        // 2. Comprobante del acta (tipo ACT de la empresa), con sucursal, PV y numeración de la empresa
        $comprobante_tipo_id = emp_comprobante_tipo_id($c, $empresa_id, 'ACT');
        if ($comprobante_tipo_id <= 0) {
            throw new RuntimeException('Falta el tipo de comprobante ACT (acta determinada) de la empresa.');
        }
        $emi = sindi_emitir_comprobante($c, [
            'empresa_id' => $empresa_id,
            'tabla_origen_id' => SINDI_TABLA_ACTAS,
            'registro_origen_id' => $acta_id,
            'comprobante_tipo_id' => $comprobante_tipo_id,
            'entidad_id' => $entidad_id,
            'importe' => $totalActa,
            'signo' => 1,
            'f_emision' => $fEmision,
            'f_vto' => $fVto,
            'usuario_id' => $usuario_id,
            'observaciones' => "Acta de inspección {$acta['numero_acta']}",
        ]);
        $comprobante_id = $emi['comprobante_id'];

        // 4. Asiento Contable Formal (§7.2):
        // DEBE:  SINDI_DEUDORES_APORTES por totalActa
        // HABER: SINDI_AJUSTE_ACTAS por totalDiferencia
        // HABER: SINDI_INTERESES_GANADOS por totalIntereses
        $cuentaDeudores = sindi_cuenta_obligatoria($c, $empresa_id, 'SINDI_DEUDORES_APORTES');
        $cuentaAjuste = $totalDiferencia > 0 ? sindi_cuenta_obligatoria($c, $empresa_id, 'SINDI_AJUSTE_ACTAS') : 0;
        $cuentaIntereses = $totalIntereses > 0 ? sindi_cuenta_obligatoria($c, $empresa_id, 'SINDI_INTERESES_GANADOS') : 0;

        $detallesAsiento = [
            [
                'cuenta_id' => $cuentaDeudores,
                'debe' => $totalActa,
                'haber' => 0.00,
                'descripcion' => "Acta {$acta['numero_acta']} - Deuda determinada"
            ]
        ];
        if ($totalDiferencia > 0) {
            $detallesAsiento[] = [
                'cuenta_id' => $cuentaAjuste,
                'debe' => 0.00,
                'haber' => $totalDiferencia,
                'descripcion' => "Acta {$acta['numero_acta']} - Capital determinado"
            ];
        }
        if ($totalIntereses > 0) {
            $detallesAsiento[] = [
                'cuenta_id' => $cuentaIntereses,
                'debe' => 0.00,
                'haber' => $totalIntereses,
                'descripcion' => "Acta {$acta['numero_acta']} - Intereses resarcitorios"
            ];
        }

        $asiento_id = tesoreriaGenerarAsiento($c, [
            'empresa_id' => $empresa_id,
            'comprobante_id' => $comprobante_id,
            'entidad_id' => $entidad_id,
            'f_asiento' => $fEmision,
            'descripcion' => "Determinación de deuda por Acta {$acta['numero_acta']}",
            'usuario_id' => $usuario_id,
            'cont_tipo_asiento_codigo' => 'AUT'
        ], $detallesAsiento);

        // 5. Actualizar acta a estado 5 (Confirmado) con su comprobante
        $updActa = $c->prepare("UPDATE sindi__actas SET
            comprobante_id = ?,
            f_cierre = ?,
            tabla_estado_registro_id = 5,
            usuario_actualizacion_id = ?
            WHERE acta_id = ?");
        $updActa->bind_param("isii", $comprobante_id, $fEmision, $usuario_id, $acta_id);
        $updActa->execute();
        $updActa->close();

        $c->commit();

        return [
            'acta_id' => $acta_id,
            'numero_acta' => $acta['numero_acta'],
            'comprobante_id' => $comprobante_id,
            'asiento_id' => $asiento_id,
            'total_acta' => $totalActa,
            'estado' => 5
        ];
    } catch (Throwable $e) {
        $c->rollback();
        throw $e;
    }
}

/**
 * Anula un acta. Si ya estaba confirmada, su comprobante no puede tener imputaciones vigentes
 * (cobros o refinanciación por plan): se anula el comprobante y se revierte el asiento.
 */
function sindiActaAnular(mysqli $c, int $acta_id, int $usuario_id, string $motivo = ''): bool {
    $c->begin_transaction();
    try {
        $aStmt = $c->prepare("SELECT a.acta_id, a.empresa_id, a.numero_acta, a.comprobante_id, a.tabla_estado_registro_id,
                                     a.total_acta, a.total_diferencia, a.total_intereses, e.entidad_id
                              FROM sindi__actas a
                              INNER JOIN sindi__empleadores e ON e.empleador_id = a.empleador_id AND e.empresa_id = a.empresa_id
                              WHERE a.acta_id = ? FOR UPDATE");
        $aStmt->bind_param("i", $acta_id);
        $aStmt->execute();
        $acta = $aStmt->get_result()->fetch_assoc();
        $aStmt->close();

        if (!$acta) {
            throw new RuntimeException("Acta $acta_id no encontrada.");
        }
        if ((int)$acta['tabla_estado_registro_id'] === 38) {
            throw new RuntimeException("El acta ya se encuentra anulada.");
        }
        $empresa_id = (int)$acta['empresa_id'];

        if (!empty($acta['comprobante_id'])) {
            $comp_id = (int)$acta['comprobante_id'];
            $qImp = $c->prepare("SELECT COUNT(*) FROM gestion__comprobantes_imputaciones ci
                                 JOIN conf__estados_registros er ON er.estado_registro_id = ci.tabla_estado_registro_id
                                 WHERE ci.comp_destino_id = ? AND er.codigo_estandar = 'CONFIRMADO'");
            $qImp->bind_param("i", $comp_id);
            $qImp->execute();
            $imputaciones = (int)$qImp->get_result()->fetch_row()[0];
            $qImp->close();
            if ($imputaciones > 0) {
                throw new RuntimeException('El acta tiene cobros o un plan de pagos aplicados: anúlelos primero.');
            }

            $cUpd = $c->prepare("UPDATE gestion__comprobantes SET tabla_estado_registro_id = 38, importe_pendiente = 0, usuario_modificacion_id = ?
                                 WHERE comprobante_id = ? AND empresa_id = ?");
            $cUpd->bind_param("iii", $usuario_id, $comp_id, $empresa_id);
            $cUpd->execute();
            $cUpd->close();

            // Asiento inverso al de la determinación
            $totalActa = round((float)$acta['total_acta'], 2);
            $totalDif = round((float)$acta['total_diferencia'], 2);
            $totalInt = round((float)$acta['total_intereses'], 2);
            $lineas = [];
            if ($totalDif > 0) {
                $lineas[] = ['cuenta_id' => sindi_cuenta_obligatoria($c, $empresa_id, 'SINDI_AJUSTE_ACTAS'), 'debe' => $totalDif, 'haber' => 0.00, 'descripcion' => "Anulación acta {$acta['numero_acta']} - capital"];
            }
            if ($totalInt > 0) {
                $lineas[] = ['cuenta_id' => sindi_cuenta_obligatoria($c, $empresa_id, 'SINDI_INTERESES_GANADOS'), 'debe' => $totalInt, 'haber' => 0.00, 'descripcion' => "Anulación acta {$acta['numero_acta']} - intereses"];
            }
            $lineas[] = ['cuenta_id' => sindi_cuenta_obligatoria($c, $empresa_id, 'SINDI_DEUDORES_APORTES'), 'debe' => 0.00, 'haber' => $totalActa, 'descripcion' => "Anulación acta {$acta['numero_acta']} - deuda"];
            tesoreriaGenerarAsiento($c, [
                'empresa_id' => $empresa_id,
                'comprobante_id' => $comp_id,
                'entidad_id' => (int)$acta['entidad_id'],
                'f_asiento' => date('Y-m-d'),
                'descripcion' => "Anulación del acta {$acta['numero_acta']}",
                'usuario_id' => $usuario_id,
                'cont_tipo_asiento_codigo' => 'AUT'
            ], $lineas);
        }

        $upd = $c->prepare("UPDATE sindi__actas SET tabla_estado_registro_id = 38, observaciones = CONCAT(COALESCE(observaciones,''), '\nAnulado: ', ?), usuario_actualizacion_id = ? WHERE acta_id = ?");
        $upd->bind_param("sii", $motivo, $usuario_id, $acta_id);
        $upd->execute();
        $upd->close();

        $c->commit();
        return true;
    } catch (Throwable $e) {
        $c->rollback();
        throw $e;
    }
}

/**
 * Obtiene los datos completos de un acta con sus períodos y conceptos.
 */
function sindiActaObtener(mysqli $c, int $acta_id): ?array {
    $sql = "SELECT a.*, ent.entidad_nombre AS razon_social, ent.cuit, u.usuario_nombre AS inspector_nombre,
                   er.tabla_estado_registro AS estado_nombre
            FROM sindi__actas a
            JOIN sindi__empleadores e ON e.empleador_id = a.empleador_id
            JOIN gestion__entidades ent ON ent.entidad_id = e.entidad_id
            LEFT JOIN conf__usuarios u ON u.usuario_id = a.inspector_usuario_id
            LEFT JOIN conf__tablas_estados_registros er ON er.tabla_id = 534 AND er.estado_registro_id = a.tabla_estado_registro_id
            WHERE a.acta_id = ?";
    $stmt = $c->prepare($sql);
    $stmt->bind_param("i", $acta_id);
    $stmt->execute();
    $acta = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$acta) {
        return null;
    }

    // Períodos
    $pSql = "SELECT ap.* 
             FROM sindi__actas_periodos ap 
             WHERE ap.acta_id = ? 
             ORDER BY ap.periodo ASC";
    $pStmt = $c->prepare($pSql);
    $pStmt->bind_param("i", $acta_id);
    $pStmt->execute();
    $periodos = $pStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $pStmt->close();

    if (!empty($periodos)) {
        $pIds = array_column($periodos, 'acta_periodo_id');
        $placeholders = implode(',', array_fill(0, count($pIds), '?'));
        $iSql = "SELECT ai.*, co.codigo AS concepto_codigo, co.descripcion AS concepto_nombre
                 FROM sindi__actas_items ai
                 LEFT JOIN sindi__conceptos co ON co.concepto_id = ai.concepto_id
                 WHERE ai.acta_periodo_id IN ($placeholders)
                 ORDER BY ai.concepto_id ASC";
        $iStmt = $c->prepare($iSql);
        $types = str_repeat('i', count($pIds));
        $iStmt->bind_param($types, ...$pIds);
        $iStmt->execute();
        $allItems = $iStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $iStmt->close();

        $itemsByPeriodo = [];
        foreach ($allItems as $it) {
            $itemsByPeriodo[$it['acta_periodo_id']][] = $it;
        }

        foreach ($periodos as &$p) {
            $p['items'] = $itemsByPeriodo[$p['acta_periodo_id']] ?? [];
        }
    }

    $acta['periodos'] = $periodos;
    return $acta;
}
