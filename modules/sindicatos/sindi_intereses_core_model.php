<?php
declare(strict_types=1);

/**
 * modules/sindicatos/sindi_intereses_core_model.php
 * Motor de cálculo de intereses resarcitorios por tramos según CCT y tasas del gremio.
 * Implementa estrictamente la especificación técnica (§2.3, §7.3.2) y el algoritmo validado del legacy.
 */

require_once __DIR__ . '/../../db.php';

/**
 * Obtiene los tramos de tasa resarcitoria activos para la empresa ordenados cronológicamente.
 */
function sindi_obtener_tasas_interes(mysqli $conexion, int $empresa_id, int $clase = 1): array
{
    $sql = "SELECT tasa_interes_id, empresa_id, clase, tasa_mensual, f_desde, f_hasta
            FROM sindi__tasas_interes
            WHERE empresa_id = ? AND clase = ? AND tabla_estado_registro_id = 1
            ORDER BY f_desde ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "ii", $empresa_id, $clase);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $tasas = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $tasas[] = [
            'tasa_interes_id' => (int)$r['tasa_interes_id'],
            'clase' => (int)$r['clase'],
            'tasa_mensual' => (float)$r['tasa_mensual'],
            'f_desde' => $r['f_desde'],
            'f_hasta' => $r['f_hasta']
        ];
    }
    mysqli_stmt_close($stmt);
    return $tasas;
}

/**
 * Calcula los intereses resarcitorios para un importe de deuda entre f_vto y f_pago.
 * Retorna el interés total redondeado y el array de tramos aplicados para auditoría.
 *
 * @param float $saldoDeuda Importe pendiente de la deuda
 * @param string $f_vto Fecha de vencimiento original (YYYY-MM-DD)
 * @param string $f_pago Fecha de pago proyectada (YYYY-MM-DD)
 * @param array $tasas Array de tramos de tasas precargados (opcional, si viene vacío los consulta)
 * @param mysqli|null $conexion Conexión activa si $tasas viene vacío
 * @param int $empresa_id ID de empresa si $tasas viene vacío
 * @return array ['interes_total' => float, 'dias_mora_total' => int, 'tramos' => array]
 */
function sindi_calcular_interes_deuda(
    float $saldoDeuda,
    string $f_vto,
    string $f_pago,
    array $tasas = [],
    ?mysqli $conexion = null,
    int $empresa_id = 0
): array {
    $saldoDeuda = round($saldoDeuda, 2);
    if ($saldoDeuda <= 0 || strtotime($f_pago) <= strtotime($f_vto)) {
        return [
            'interes_total' => 0.00,
            'dias_mora_total' => 0,
            'tramos' => []
        ];
    }

    if (empty($tasas) && $conexion !== null && $empresa_id > 0) {
        $tasas = sindi_obtener_tasas_interes($conexion, $empresa_id, 1);
    }

    $timeVto = strtotime($f_vto);
    $timePago = strtotime($f_pago);
    $diasMoraTotal = (int)round(($timePago - $timeVto) / 86400);

    $interesTotal = 0.00;
    $tramosAplicados = [];

    foreach ($tasas as $tramo) {
        $timeDesde = strtotime($tramo['f_desde']);
        $timeHasta = strtotime($tramo['f_hasta']);

        // El tramo debe tener vigencia posterior al vencimiento
        if ($timeHasta <= $timeVto) {
            continue;
        }

        // Si la fecha de pago es anterior al inicio de este tramo, terminamos
        if ($timePago < $timeDesde) {
            break;
        }

        $diasAtrasoTramo = 0;
        if ($timeVto > $timeDesde) {
            // El vencimiento cayó dentro de este tramo
            if ($timePago <= $timeHasta) {
                $diasAtrasoTramo = (int)round(($timePago - $timeVto) / 86400);
            } else {
                $diasAtrasoTramo = (int)round(($timeHasta - $timeVto) / 86400);
            }
        } else {
            // El vencimiento ocurrió antes de que empiece este tramo
            if ($timePago <= $timeHasta) {
                $diasAtrasoTramo = (int)round(($timePago - $timeDesde) / 86400) + 1;
            } else {
                $diasAtrasoTramo = (int)round(($timeHasta - $timeDesde) / 86400) + 1;
            }
        }

        if ($diasAtrasoTramo > 0) {
            $tasaMensual = (float)$tramo['tasa_mensual'];
            $interesTramo = round($saldoDeuda * ($tasaMensual / 100.0) * ($diasAtrasoTramo / 30.0), 2);
            $interesTotal = round($interesTotal + $interesTramo, 2);

            $tramosAplicados[] = [
                'tasa_interes_id' => $tramo['tasa_interes_id'] ?? null,
                'f_desde' => $tramo['f_desde'],
                'f_hasta' => $tramo['f_hasta'],
                'tasa_mensual' => $tasaMensual,
                'dias' => $diasAtrasoTramo,
                'interes' => $interesTramo
            ];
        }

        // Si la fecha de pago no supera el fin de este tramo, fue el último tramo necesario
        if ($timePago <= $timeHasta) {
            break;
        }
    }

    return [
        'interes_total' => $interesTotal,
        'dias_mora_total' => $diasMoraTotal,
        'tramos' => $tramosAplicados
    ];
}

/**
 * Calcula los importes a dos vencimientos para un conjunto de comprobantes de deuda.
 */
function sindi_calcular_intereses_volante_completo(
    array $deudas,
    string $f_pago_1,
    string $f_pago_2,
    array $tasas
): array {
    $detalles = [];
    $totalCap1 = 0.00;
    $totalInt1 = 0.00;
    $totalCap2 = 0.00;
    $totalInt2 = 0.00;

    foreach ($deudas as $deuda) {
        $compId = (int)$deuda['comprobante_id'];
        $saldo = (float)$deuda['saldo_deuda'];
        $f_vto = (string)$deuda['f_vto'];
        $periodo = (int)($deuda['periodo'] ?? 0);
        $conceptoId = !empty($deuda['concepto_id']) ? (int)$deuda['concepto_id'] : null;

        $calc1 = sindi_calcular_interes_deuda($saldo, $f_vto, $f_pago_1, $tasas);
        $calc2 = sindi_calcular_interes_deuda($saldo, $f_vto, $f_pago_2, $tasas);

        $int1 = $calc1['interes_total'];
        $sub1 = round($saldo + $int1, 2);
        $int2 = $calc2['interes_total'];
        $sub2 = round($saldo + $int2, 2);

        $totalCap1 = round($totalCap1 + $saldo, 2);
        $totalInt1 = round($totalInt1 + $int1, 2);
        $totalCap2 = round($totalCap2 + $saldo, 2);
        $totalInt2 = round($totalInt2 + $int2, 2);

        $detalles[] = [
            'comprobante_id' => $compId,
            'concepto_id' => $conceptoId,
            'periodo' => $periodo,
            'f_vto_original' => $f_vto,
            'saldo_deuda' => $saldo,
            'interes_1' => $int1,
            'subtotal_1' => $sub1,
            'tramos_1' => $calc1['tramos'],
            'interes_2' => $int2,
            'subtotal_2' => $sub2,
            'tramos_2' => $calc2['tramos']
        ];
    }

    return [
        'detalles' => $detalles,
        'f_pago_1' => $f_pago_1,
        'importe_capital_1' => $totalCap1,
        'importe_interes_1' => $totalInt1,
        'importe_total_1' => round($totalCap1 + $totalInt1, 2),
        'f_pago_2' => $f_pago_2,
        'importe_capital_2' => $totalCap2,
        'importe_interes_2' => $totalInt2,
        'importe_total_2' => round($totalCap2 + $totalInt2, 2)
    ];
}
