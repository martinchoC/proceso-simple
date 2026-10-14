<?php
/**
 * modules/sindicatos/sindi_planes_core_model.php
 * Motor Core de Planes de Pago, Facilidades y Refinanciación Sindical.
 * Módulo 6 — Sindicatos (§2.5, §5.5, §7.1).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/empresa_config.php';
require_once __DIR__ . '/../gestion_normal/tesoreria_core_model.php';
require_once __DIR__ . '/sindi_comprobantes_core.php';

/**
 * Simula el cronograma de cuotas de un plan de pagos.
 * 
 * @param mysqli $c
 * @param int $empresa_id
 * @param int $plan_tipo_id
 * @param float $capital
 * @param int $cuotas
 * @param float|null $anticipo_pct
 * @param string|null $f_primer_vto
 * @return array
 */
function sindiPlanSimular(
    mysqli $c, 
    int $empresa_id, 
    int $plan_tipo_id, 
    float $capital, 
    int $cuotas, 
    ?float $anticipo_pct = null, 
    ?string $f_primer_vto = null
): array {
    $tStmt = $c->prepare("SELECT * FROM sindi__planes_tipos WHERE plan_tipo_id = ? AND empresa_id = ?");
    $tStmt->bind_param("ii", $plan_tipo_id, $empresa_id);
    $tStmt->execute();
    $tipo = $tStmt->get_result()->fetch_assoc();
    $tStmt->close();

    if (!$tipo) {
        throw new RuntimeException("Tipo de plan $plan_tipo_id no encontrado.");
    }

    $capital = round($capital, 2);
    if ($capital <= 0) {
        throw new InvalidArgumentException("El capital a financiar debe ser mayor a cero.");
    }

    $cuotasMax = (int)$tipo['cuotas_max'];
    if ($cuotas < 1 || $cuotas > $cuotasMax) {
        throw new InvalidArgumentException("La cantidad de cuotas debe estar entre 1 y $cuotasMax.");
    }

    $anticipoMinPct = (float)$tipo['anticipo_min_porc'];
    $pctAnticipo = $anticipo_pct !== null ? max($anticipoMinPct, (float)$anticipo_pct) : $anticipoMinPct;
    $anticipoImporte = round($capital * ($pctAnticipo / 100), 2);
    $saldoFinanciar = round($capital - $anticipoImporte, 2);

    $sistema = $tipo['sistema'] ?? 'DIRECTO';
    $tasaMensual = (float)$tipo['tasa_mensual']; // Porcentaje, ej. 3.00

    $fPrimerVto = $f_primer_vto ?: date('Y-m-d', strtotime('+30 days'));

    $cronograma = [];
    $totalInteres = 0.00;

    // Si hay anticipo, cuota 0
    if ($anticipoImporte > 0) {
        $cronograma[] = [
            'numero_cuota' => 0,
            'f_vto' => date('Y-m-d', strtotime('+7 days')),
            'capital' => $anticipoImporte,
            'interes' => 0.00,
            'total_cuota' => $anticipoImporte
        ];
    }

    if ($sistema === 'DIRECTO') {
        // Interés simple directo sobre saldo a financiar
        $interesTotalFin = round($saldoFinanciar * ($tasaMensual / 100) * $cuotas, 2);
        $totalInteres = $interesTotalFin;
        $totalGeneralFinanciado = round($saldoFinanciar + $interesTotalFin, 2);

        $cuotaMontoBase = round($totalGeneralFinanciado / $cuotas, 2);
        $cuotaCapitalBase = round($saldoFinanciar / $cuotas, 2);
        $cuotaInteresBase = round($interesTotalFin / $cuotas, 2);

        $acumCap = 0.00;
        $acumInt = 0.00;

        for ($i = 1; $i <= $cuotas; $i++) {
            $fVtoCuota = date('Y-m-d', strtotime($fPrimerVto . " +" . (($i - 1) * 30) . " days"));

            if ($i === $cuotas) {
                // Ajuste de centavos en la última cuota
                $cap = round($saldoFinanciar - $acumCap, 2);
                $inte = round($interesTotalFin - $acumInt, 2);
            } else {
                $cap = $cuotaCapitalBase;
                $inte = $cuotaInteresBase;
            }

            $acumCap += $cap;
            $acumInt += $inte;
            $totCuota = round($cap + $inte, 2);

            $cronograma[] = [
                'numero_cuota' => $i,
                'f_vto' => $fVtoCuota,
                'capital' => $cap,
                'interes' => $inte,
                'total_cuota' => $totCuota
            ];
        }
    } elseif ($sistema === 'FRANCES') {
        // Sistema Francés (cuota constante)
        $iTasa = $tasaMensual / 100;
        if ($iTasa <= 0) {
            $cuotaFija = round($saldoFinanciar / $cuotas, 2);
        } else {
            $factor = pow(1 + $iTasa, $cuotas);
            $cuotaFija = round($saldoFinanciar * ($iTasa * $factor) / ($factor - 1), 2);
        }

        $saldoVivo = $saldoFinanciar;
        for ($i = 1; $i <= $cuotas; $i++) {
            $fVtoCuota = date('Y-m-d', strtotime($fPrimerVto . " +" . (($i - 1) * 30) . " days"));
            $inte = round($saldoVivo * $iTasa, 2);
            $cap = ($i === $cuotas) ? $saldoVivo : round($cuotaFija - $inte, 2);
            $saldoVivo = round(max(0.00, $saldoVivo - $cap), 2);
            $totalInteres += $inte;

            $cronograma[] = [
                'numero_cuota' => $i,
                'f_vto' => $fVtoCuota,
                'capital' => $cap,
                'interes' => $inte,
                'total_cuota' => round($cap + $inte, 2)
            ];
        }
    } else {
        // ALEMAN (amortización constante)
        $capFijo = round($saldoFinanciar / $cuotas, 2);
        $saldoVivo = $saldoFinanciar;
        $iTasa = $tasaMensual / 100;

        for ($i = 1; $i <= $cuotas; $i++) {
            $fVtoCuota = date('Y-m-d', strtotime($fPrimerVto . " +" . (($i - 1) * 30) . " days"));
            $inte = round($saldoVivo * $iTasa, 2);
            $cap = ($i === $cuotas) ? $saldoVivo : $capFijo;
            $saldoVivo = round(max(0.00, $saldoVivo - $cap), 2);
            $totalInteres += $inte;

            $cronograma[] = [
                'numero_cuota' => $i,
                'f_vto' => $fVtoCuota,
                'capital' => $cap,
                'interes' => $inte,
                'total_cuota' => round($cap + $inte, 2)
            ];
        }
    }

    $totalFinanciado = round($capital + $totalInteres, 2);

    return [
        'plan_tipo_id' => $plan_tipo_id,
        'tipo_nombre' => $tipo['nombre'],
        'sistema' => $sistema,
        'capital_original' => $capital,
        'anticipo_importe' => $anticipoImporte,
        'saldo_financiar' => $saldoFinanciar,
        'interes_financiero' => $totalInteres,
        'total_financiado' => $totalFinanciado,
        'cantidad_cuotas' => $cuotas,
        'cronograma' => $cronograma
    ];
}

/**
 * Crea y confirma un Plan de Pagos, emite las cuotas como comprobantes PLC (309)
 * y refinancia/cancela las deudas de origen en cuenta corriente.
 * 
 * @param mysqli $c
 * @param int $empresa_id
 * @param array $datos ['empleador_id', 'plan_tipo_id', 'origen_tipo', 'acta_id', 'capital_original', 'cantidad_cuotas', 'anticipo_pct', 'f_primer_vto', 'observaciones']
 * @param array $comprobantes_origen_ids Comprobantes a refinanciar
 * @param int $usuario_id
 * @return array
 */
function sindiPlanCrearYConfirmar(
    mysqli $c, 
    int $empresa_id, 
    array $datos, 
    array $comprobantes_origen_ids, 
    int $usuario_id
): array {
    $empleador_id = (int)$datos['empleador_id'];
    $plan_tipo_id = (int)$datos['plan_tipo_id'];
    $origen_tipo = in_array($datos['origen_tipo'] ?? '', ['DEUDA_DDJJ', 'ACTA', 'MIXTO']) ? $datos['origen_tipo'] : 'DEUDA_DDJJ';
    $acta_id = !empty($datos['acta_id']) ? (int)$datos['acta_id'] : null;
    $capital = (float)$datos['capital_original'];
    $cuotas = (int)$datos['cantidad_cuotas'];
    $anticipo_pct = isset($datos['anticipo_pct']) ? (float)$datos['anticipo_pct'] : null;
    $f_primer_vto = $datos['f_primer_vto'] ?? null;
    $observaciones = $datos['observaciones'] ?? '';

    // Simular para obtener números exactos
    $sim = sindiPlanSimular($c, $empresa_id, $plan_tipo_id, $capital, $cuotas, $anticipo_pct, $f_primer_vto);

    $c->begin_transaction();
    try {
        // 1. Obtener entidad_id del empleador
        $eStmt = $c->prepare("SELECT entidad_id FROM sindi__empleadores WHERE empleador_id = ? AND empresa_id = ?");
        $eStmt->bind_param("ii", $empleador_id, $empresa_id);
        $eStmt->execute();
        $empRow = $eStmt->get_result()->fetch_assoc();
        $eStmt->close();

        if (!$empRow) {
            throw new RuntimeException("Empleador $empleador_id no encontrado.");
        }
        $entidad_id = (int)$empRow['entidad_id'];

        // 2. Generar número de plan (PLAN-YYYY-NNNN)
        $anio = date('Y');
        $cntStmt = $c->prepare("SELECT COUNT(*) AS total FROM sindi__planes WHERE empresa_id = ? AND YEAR(f_acuerdo) = ?");
        $cntStmt->bind_param("is", $empresa_id, $anio);
        $cntStmt->execute();
        $cntRow = $cntStmt->get_result()->fetch_assoc();
        $cntStmt->close();
        $nroPlan = sprintf("PLAN-%s-%04d", $anio, ((int)$cntRow['total']) + 1);

        $fAcuerdo = date('Y-m-d');
        $fPrimerVtoReal = $sim['cronograma'][1]['f_vto'] ?? ($sim['cronograma'][0]['f_vto'] ?? $fAcuerdo);

        // 3. Insertar cabecera sindi__planes
        $inPlan = $c->prepare("INSERT INTO sindi__planes (
            empresa_id, numero_plan, empleador_id, plan_tipo_id, origen_tipo,
            acta_id, capital_original, interes_financiero, total_financiado,
            anticipo_importe, cantidad_cuotas, f_acuerdo, f_primer_vto,
            saldo_pendiente, tabla_estado_registro_id, observaciones,
            usuario_creacion_id
        ) VALUES (
            ?, ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, ?, ?, ?,
            ?, 5, ?,
            ?
        )");

        $totalFinanciado = $sim['total_financiado'];
        $interesFin = $sim['interes_financiero'];
        $anticipoImp = $sim['anticipo_importe'];
        $saldoPend = $totalFinanciado;

        $inPlan->bind_param(
            "isiisiddddissdsi",
            $empresa_id, $nroPlan, $empleador_id, $plan_tipo_id, $origen_tipo,
            $acta_id, $capital, $interesFin, $totalFinanciado,
            $anticipoImp, $cuotas, $fAcuerdo, $fPrimerVtoReal,
            $saldoPend, $observaciones,
            $usuario_id
        );
        $inPlan->execute();
        $plan_id = (int)$c->insert_id;
        $inPlan->close();

        // 4. Deudas que se refinancian: de la empresa, del empleador, con saldo; el capital es su saldo
        $origenes = [];
        $capitalOrigen = 0.0;
        foreach (array_unique(array_map('intval', $comprobantes_origen_ids)) as $compOrigId) {
            if ($compOrigId <= 0) continue;
            $chk = $c->prepare("SELECT comprobante_id, importe_pendiente FROM gestion__comprobantes
                                WHERE comprobante_id = ? AND empresa_id = ? AND entidad_id = ? AND signo = 1
                                  AND modulo = 'sindicatos' AND tabla_estado_registro_id IN (5, 39)
                                FOR UPDATE");
            $chk->bind_param("iii", $compOrigId, $empresa_id, $entidad_id);
            $chk->execute();
            $fila = $chk->get_result()->fetch_assoc();
            $chk->close();
            if (!$fila || (float)$fila['importe_pendiente'] <= 0) {
                throw new RuntimeException("La deuda {$compOrigId} no es del empleador o ya no tiene saldo.");
            }
            $origenes[] = ['id' => $compOrigId, 'saldo' => round((float)$fila['importe_pendiente'], 2)];
            $capitalOrigen = round($capitalOrigen + (float)$fila['importe_pendiente'], 2);
        }
        if (empty($origenes)) {
            throw new RuntimeException('Seleccione las deudas a refinanciar.');
        }
        if (abs($capitalOrigen - round($capital, 2)) > 0.009) {
            throw new RuntimeException("El capital del plan ({$capital}) no coincide con el saldo de las deudas seleccionadas ({$capitalOrigen}).");
        }

        $compTipoPlc = emp_comprobante_tipo_id($c, $empresa_id, 'PLC');
        $compTipoRpl = emp_comprobante_tipo_id($c, $empresa_id, 'RPL');
        if ($compTipoPlc <= 0 || $compTipoRpl <= 0) {
            throw new RuntimeException('Faltan los tipos de comprobante PLC (cuota de plan) y/o RPL (refinanciación por plan) de la empresa.');
        }
        $cuentaDeudores = sindi_cuenta_obligatoria($c, $empresa_id, 'SINDI_DEUDORES_APORTES');

        // 5. Crédito de refinanciación por el capital, imputado contra cada deuda de origen
        $rpl = sindi_emitir_comprobante($c, [
            'empresa_id' => $empresa_id,
            'tabla_origen_id' => SINDI_TABLA_PLANES,
            'registro_origen_id' => $plan_id,
            'comprobante_tipo_id' => $compTipoRpl,
            'entidad_id' => $entidad_id,
            'importe' => $capitalOrigen,
            'signo' => -1,
            'f_emision' => $fAcuerdo,
            'usuario_id' => $usuario_id,
            'observaciones' => "Refinanciación por plan {$nroPlan}",
        ]);
        foreach ($origenes as $o) {
            tesoreriaRegistrarImputacion($c, [
                'empresa_id' => $empresa_id,
                'comp_origen_id' => $rpl['comprobante_id'],
                'comp_destino_id' => $o['id'],
                'importe_imputado' => $o['saldo'],
                'f_imputacion' => $fAcuerdo,
                'usuario_id' => $usuario_id,
            ]);
        }

        // 6. Cuotas (incluida la 0 = anticipo) como deudas nuevas
        $insCuota = $c->prepare("INSERT INTO sindi__planes_cuotas (
            plan_id, empresa_id, numero_cuota, f_vto, capital,
            interes, total_cuota, saldo_pendiente, comprobante_id,
            tabla_estado_registro_id
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL, 5)");
        $updCuotaComp = $c->prepare("UPDATE sindi__planes_cuotas SET comprobante_id = ? WHERE plan_cuota_id = ?");

        $cuotasGeneradas = [];
        foreach ($sim['cronograma'] as $cr) {
            $numCuota = (int)$cr['numero_cuota'];
            $fVtoCuota = $cr['f_vto'];
            $capCuota = (float)$cr['capital'];
            $intCuota = (float)$cr['interes'];
            $totCuota = (float)$cr['total_cuota'];

            $insCuota->bind_param("iiisdddd", $plan_id, $empresa_id, $numCuota, $fVtoCuota, $capCuota, $intCuota, $totCuota, $totCuota);
            $insCuota->execute();
            $plan_cuota_id = (int)$c->insert_id;

            $cuo = sindi_emitir_comprobante($c, [
                'empresa_id' => $empresa_id,
                'tabla_origen_id' => SINDI_TABLA_PLANES_CUOTAS,
                'registro_origen_id' => $plan_cuota_id,
                'comprobante_tipo_id' => $compTipoPlc,
                'entidad_id' => $entidad_id,
                'importe' => $totCuota,
                'signo' => 1,
                'f_emision' => $fAcuerdo,
                'f_vto' => $fVtoCuota,
                'usuario_id' => $usuario_id,
                'observaciones' => ($numCuota === 0 ? 'Anticipo' : "Cuota {$numCuota}") . " de plan {$nroPlan}",
            ]);
            $comprobante_id = $cuo['comprobante_id'];
            $updCuotaComp->bind_param("ii", $comprobante_id, $plan_cuota_id);
            $updCuotaComp->execute();

            $cuotasGeneradas[] = [
                'plan_cuota_id' => $plan_cuota_id,
                'numero_cuota' => $numCuota,
                'f_vto' => $fVtoCuota,
                'total_cuota' => $totCuota,
                'comprobante_id' => $comprobante_id
            ];
        }
        $insCuota->close();
        $updCuotaComp->close();

        // 7. Asiento del interés de financiación (el capital pasa de las deudas viejas a las cuotas
        //    dentro de la misma cuenta de deudores, sin efecto contable)
        $interesFin = round((float)$sim['interes_financiero'], 2);
        if ($interesFin > 0) {
            tesoreriaGenerarAsiento($c, [
                'empresa_id' => $empresa_id,
                'comprobante_id' => $rpl['comprobante_id'],
                'entidad_id' => $entidad_id,
                'f_asiento' => $fAcuerdo,
                'descripcion' => "Interés de financiación plan {$nroPlan}",
                'usuario_id' => $usuario_id,
                'cont_tipo_asiento_codigo' => 'AUT'
            ], [
                ['cuenta_id' => $cuentaDeudores, 'debe' => $interesFin, 'haber' => 0.00, 'descripcion' => 'Interés de financiación a cobrar'],
                ['cuenta_id' => sindi_cuenta_obligatoria($c, $empresa_id, 'SINDI_INTERESES_GANADOS'), 'debe' => 0.00, 'haber' => $interesFin, 'descripcion' => 'Interés de financiación'],
            ]);
        }

        $updRpl = $c->prepare("UPDATE sindi__planes SET observaciones = CONCAT_WS('\n', NULLIF(observaciones, ''), ?) WHERE plan_id = ?");
        $obsRpl = "Refinanciación: {$rpl['numero']}";
        $updRpl->bind_param("si", $obsRpl, $plan_id);
        $updRpl->execute();
        $updRpl->close();

        // 8. Si vino asociado a un acta, el acta queda resuelta por plan
        if ($acta_id !== null && $acta_id > 0) {
            $updActa = $c->prepare("UPDATE sindi__actas SET
                resolucion_tipo = 'PLAN', plan_id = ?, tabla_estado_registro_id = 40,
                usuario_actualizacion_id = ?
                WHERE acta_id = ? AND empresa_id = ?");
            $updActa->bind_param("iiii", $plan_id, $usuario_id, $acta_id, $empresa_id);
            $updActa->execute();
            $updActa->close();
        }

        $c->commit();

        return [
            'plan_id' => $plan_id,
            'numero_plan' => $nroPlan,
            'total_financiado' => $totalFinanciado,
            'cuotas' => count($cuotasGeneradas),
            'cuotas_detalle' => $cuotasGeneradas,
            'estado' => 5
        ];
    } catch (Throwable $e) {
        $c->rollback();
        throw $e;
    }
}

/**
 * Anula un plan de pago: solo si ninguna cuota tiene cobros. Anula las imputaciones de la
 * refinanciación (las deudas de origen recuperan su saldo), anula cuotas y crédito, revierte el
 * asiento del interés y devuelve el acta, si la había, a Confirmada.
 */
function sindiPlanAnular(mysqli $c, int $plan_id, int $usuario_id, string $motivo = ''): bool {
    $c->begin_transaction();
    try {
        $pStmt = $c->prepare("SELECT p.plan_id, p.empresa_id, p.empleador_id, p.acta_id, p.numero_plan, p.interes_financiero,
                                     p.tabla_estado_registro_id, e.entidad_id
                              FROM sindi__planes p
                              INNER JOIN sindi__empleadores e ON e.empleador_id = p.empleador_id AND e.empresa_id = p.empresa_id
                              WHERE p.plan_id = ? FOR UPDATE");
        $pStmt->bind_param("i", $plan_id);
        $pStmt->execute();
        $plan = $pStmt->get_result()->fetch_assoc();
        $pStmt->close();

        if (!$plan) {
            throw new RuntimeException("Plan $plan_id no encontrado.");
        }
        if ((int)$plan['tabla_estado_registro_id'] === 38) {
            throw new RuntimeException("El plan ya se encuentra anulado.");
        }
        $empresa_id = (int)$plan['empresa_id'];

        // Cuotas: ninguna puede tener cobros imputados
        $qComp = $c->prepare("SELECT pc.comprobante_id,
                                     (SELECT COUNT(*) FROM gestion__comprobantes_imputaciones ci
                                       JOIN conf__estados_registros er ON er.estado_registro_id = ci.tabla_estado_registro_id
                                      WHERE ci.comp_destino_id = pc.comprobante_id AND er.codigo_estandar = 'CONFIRMADO') AS cobros
                              FROM sindi__planes_cuotas pc
                              WHERE pc.plan_id = ? AND pc.comprobante_id IS NOT NULL");
        $qComp->bind_param("i", $plan_id);
        $qComp->execute();
        $cuotas = $qComp->get_result()->fetch_all(MYSQLI_ASSOC);
        $qComp->close();
        foreach ($cuotas as $cu) {
            if ((int)$cu['cobros'] > 0) {
                throw new RuntimeException('El plan tiene cuotas con cobros: anule primero esos cobros.');
            }
        }

        // Crédito de refinanciación: anular sus imputaciones (las deudas de origen recuperan saldo)
        $tablaPlanes = SINDI_TABLA_PLANES;
        $qRpl = $c->prepare("SELECT comprobante_id FROM gestion__comprobantes WHERE tabla_origen_id = ? AND registro_origen_id = ? AND empresa_id = ?");
        $qRpl->bind_param("iii", $tablaPlanes, $plan_id, $empresa_id);
        $qRpl->execute();
        $rplId = (int)($qRpl->get_result()->fetch_row()[0] ?? 0);
        $qRpl->close();

        if ($rplId > 0) {
            $qImp = $c->prepare("SELECT ci.imputacion_id FROM gestion__comprobantes_imputaciones ci
                                 JOIN conf__estados_registros er ON er.estado_registro_id = ci.tabla_estado_registro_id
                                 WHERE ci.comp_origen_id = ? AND er.codigo_estandar = 'CONFIRMADO'");
            $qImp->bind_param("i", $rplId);
            $qImp->execute();
            $imps = $qImp->get_result()->fetch_all(MYSQLI_ASSOC);
            $qImp->close();
            foreach ($imps as $imp) {
                tesoreriaAnularImputacion($c, (int)$imp['imputacion_id'], $usuario_id, "Anulación del plan {$plan['numero_plan']}");
            }

            // Revertir el asiento del interés de financiación
            $interesFin = round((float)$plan['interes_financiero'], 2);
            if ($interesFin > 0) {
                tesoreriaGenerarAsiento($c, [
                    'empresa_id' => $empresa_id,
                    'comprobante_id' => $rplId,
                    'entidad_id' => (int)$plan['entidad_id'],
                    'f_asiento' => date('Y-m-d'),
                    'descripcion' => "Anulación interés de financiación plan {$plan['numero_plan']}",
                    'usuario_id' => $usuario_id,
                    'cont_tipo_asiento_codigo' => 'AUT'
                ], [
                    ['cuenta_id' => sindi_cuenta_obligatoria($c, $empresa_id, 'SINDI_INTERESES_GANADOS'), 'debe' => $interesFin, 'haber' => 0.00, 'descripcion' => 'Anulación interés de financiación'],
                    ['cuenta_id' => sindi_cuenta_obligatoria($c, $empresa_id, 'SINDI_DEUDORES_APORTES'), 'debe' => 0.00, 'haber' => $interesFin, 'descripcion' => 'Anulación interés a cobrar'],
                ]);
            }
        }

        // Cuotas y crédito anulados, sin saldo
        $updC = $c->prepare("UPDATE gestion__comprobantes SET tabla_estado_registro_id = 38, importe_pendiente = 0, usuario_modificacion_id = ?
                             WHERE comprobante_id = ? AND empresa_id = ?");
        $idsAnular = array_map(fn($cu) => (int)$cu['comprobante_id'], $cuotas);
        if ($rplId > 0) {
            $idsAnular[] = $rplId;
        }
        foreach ($idsAnular as $cId) {
            $updC->bind_param("iii", $usuario_id, $cId, $empresa_id);
            $updC->execute();
        }
        $updC->close();

        $updCuotas = $c->prepare("UPDATE sindi__planes_cuotas SET tabla_estado_registro_id = 38 WHERE plan_id = ?");
        $updCuotas->bind_param("i", $plan_id);
        $updCuotas->execute();
        $updCuotas->close();

        if (!empty($plan['acta_id'])) {
            $actaId = (int)$plan['acta_id'];
            $updActa = $c->prepare("UPDATE sindi__actas SET resolucion_tipo = 'PENDIENTE', plan_id = NULL, tabla_estado_registro_id = 5 WHERE acta_id = ? AND empresa_id = ?");
            $updActa->bind_param("ii", $actaId, $empresa_id);
            $updActa->execute();
            $updActa->close();
        }

        $updP = $c->prepare("UPDATE sindi__planes SET tabla_estado_registro_id = 38, observaciones = CONCAT(COALESCE(observaciones,''), '\nAnulado: ', ?), usuario_actualizacion_id = ? WHERE plan_id = ?");
        $updP->bind_param("sii", $motivo, $usuario_id, $plan_id);
        $updP->execute();
        $updP->close();

        $c->commit();
        return true;
    } catch (Throwable $e) {
        $c->rollback();
        throw $e;
    }
}

/**
 * Obtiene el detalle completo de un plan y sus cuotas.
 */
function sindiPlanObtener(mysqli $c, int $plan_id): ?array {
    $sql = "SELECT p.*, ent.entidad_nombre AS razon_social, ent.cuit, pt.nombre AS tipo_plan_nombre, pt.sistema,
                   er.tabla_estado_registro AS estado_nombre
            FROM sindi__planes p
            JOIN sindi__empleadores e ON e.empleador_id = p.empleador_id
            JOIN gestion__entidades ent ON ent.entidad_id = e.entidad_id
            JOIN sindi__planes_tipos pt ON pt.plan_tipo_id = p.plan_tipo_id
            LEFT JOIN conf__tablas_estados_registros er ON er.tabla_id = 538 AND er.estado_registro_id = p.tabla_estado_registro_id
            WHERE p.plan_id = ?";
    $stmt = $c->prepare($sql);
    $stmt->bind_param("i", $plan_id);
    $stmt->execute();
    $plan = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$plan) {
        return null;
    }

    $cSql = "SELECT pc.*, comp.numero AS comprobante_numero, er.tabla_estado_registro AS estado_nombre
             FROM sindi__planes_cuotas pc
             LEFT JOIN gestion__comprobantes comp ON comp.comprobante_id = pc.comprobante_id
             LEFT JOIN conf__tablas_estados_registros er ON er.tabla_id = 539 AND er.estado_registro_id = pc.tabla_estado_registro_id
             WHERE pc.plan_id = ?
             ORDER BY pc.numero_cuota ASC";
    $cStmt = $c->prepare($cSql);
    $cStmt->bind_param("i", $plan_id);
    $cStmt->execute();
    $plan['cuotas'] = $cStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $cStmt->close();

    return $plan;
}
