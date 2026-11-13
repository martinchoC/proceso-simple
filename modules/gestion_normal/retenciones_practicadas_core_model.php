<?php
/**
 * Retenciones practicadas en órdenes de pago (la empresa como agente de retención).
 *
 * Configuración: gestion__empresas_impuestos_config con un impuesto de retención de compras
 * (gestion__impuestos_tipos.es_retencion = 1 AND aplica_compra = 1: Ganancias, IVA, IIBB, SUSS practicadas).
 * La empresa es agente de un impuesto (y, en IIBB, de cada jurisdicción) cuando tiene una config activa y vigente;
 * se administra en Impuestos Adic. - Conf (página 73), solapa "Agente de Retención". Columnas que usa:
 *   jurisdiccion_id            IIBB: la provincia; resto: la nacional
 *   cont_cuenta_id             cuenta del pasivo "retenciones a depositar" (obligatoria para confirmar la OP)
 *   base_calculo               NETO_GRAVADO | NETO (gravado + exento + no gravado) | IVA | TOTAL, de las facturas pagadas
 *   tipo_calculo               manual = alícuota genérica | padron = alicuota_retencion de gestion__padrones_iibb
 *   alicuota                   alícuota genérica (%)
 *   sin_dato_accion            con padrón, si el CUIT no figura: GENERICA (o CONDICION, el default) | NO_RETENER
 *   minimo_imponible           base mínima del pago para retener
 *   importe_minimo_percepcion  retención mínima (debajo no se retiene)
 *   aplica_siempre             IIBB sin padrón: 0 = solo proveedores con alta en la jurisdicción
 *   numero_agente
 *
 * La base es proporcional a lo que la OP cancela de cada comprobante (importe imputado / total del comprobante).
 * Todas las funciones corren dentro de la transacción del llamador.
 */

require_once __DIR__ . '/percepciones_iibb_core_model.php';   // perIibbFila/Filas, perIibbPadron, perIibbSituacionCondicion

function retPracBases() {
    return [
        'NETO_GRAVADO' => 'Neto gravado',
        'NETO'         => 'Neto sin IVA (gravado + exento + no gravado)',
        'IVA'          => 'IVA facturado',
        'TOTAL'        => 'Total del comprobante',
    ];
}

function retPracEsIibb($impuesto_tipo) {
    $n = mb_strtoupper((string)$impuesto_tipo, 'UTF-8');
    return strpos($n, 'IIBB') !== false || strpos($n, 'INGRESOS BRUTOS') !== false;
}

/** ¿El impuesto es una retención que practica la empresa al pagar? */
function retPracEsImpuestoRetencion($conexion, $impuesto_tipo_id) {
    return (bool) perIibbFila($conexion,
        "SELECT 1 FROM gestion__impuestos_tipos WHERE impuesto_tipo_id = ? AND es_retencion = 1 AND aplica_compra = 1",
        'i', intval($impuesto_tipo_id));
}

/**
 * Configs de retención activas y vigentes a $fecha: una por impuesto (y por jurisdicción en IIBB).
 * Es la lista de "impuestos a retener" de la empresa; vacía = la empresa no es agente de retención.
 */
function retPracConfigsVigentes($conexion, $empresa_id, $fecha) {
    $filas = perIibbFilas($conexion,
        "SELECT cfg.empresa_impuesto_config_id, cfg.impuesto_tipo_id, it.impuesto_tipo, cfg.jurisdiccion_id,
                j.jurisdiccion_nombre, j.provincia_id, cfg.cont_cuenta_id, cfg.base_calculo, cfg.tipo_calculo,
                cfg.alicuota, cfg.sin_dato_accion, cfg.minimo_imponible, cfg.importe_minimo_percepcion AS importe_minimo,
                cfg.aplica_siempre, cfg.numero_agente, cfg.regimen_id
         FROM gestion__empresas_impuestos_config cfg
         JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = cfg.impuesto_tipo_id
              AND it.es_retencion = 1 AND it.aplica_compra = 1
         LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = cfg.jurisdiccion_id
         WHERE cfg.empresa_id = ? AND cfg.tabla_estado_registro_id = 1
           AND cfg.f_desde <= ? AND (cfg.f_hasta IS NULL OR cfg.f_hasta >= ?)
         ORDER BY it.impuesto_tipo, cfg.prioridad, j.orden, j.jurisdiccion_nombre, cfg.empresa_impuesto_config_id",
        'iss', intval($empresa_id), $fecha, $fecha);
    $vistas = [];
    $resultado = [];
    foreach ($filas as $f) {
        $f['es_iibb'] = retPracEsIibb($f['impuesto_tipo']) ? 1 : 0;
        // Fuera de IIBB hay una sola config por impuesto: si se superponen dos vigencias, manda la de menor prioridad
        $clave = $f['impuesto_tipo_id'] . '|' . ($f['es_iibb'] ? intval($f['jurisdiccion_id']) : 0);
        if (isset($vistas[$clave])) continue;
        $vistas[$clave] = true;
        $resultado[] = $f;
    }
    return $resultado;
}

/** Config vigente para un impuesto (y jurisdicción si es IIBB), o null si la empresa no es agente. */
function retPracConfig($conexion, $empresa_id, $impuesto_tipo_id, $jurisdiccion_id, $fecha) {
    foreach (retPracConfigsVigentes($conexion, $empresa_id, $fecha) as $cfg) {
        if (intval($cfg['impuesto_tipo_id']) !== intval($impuesto_tipo_id)) continue;
        if ($cfg['es_iibb'] && intval($cfg['jurisdiccion_id']) !== intval($jurisdiccion_id)) continue;
        return $cfg;
    }
    return null;
}

/** Medio de pago RETENCION de la empresa para el impuesto (para las líneas que propone el cálculo). */
function retPracMedioPago($conexion, $empresa_id, $impuesto_tipo_id) {
    $row = perIibbFila($conexion,
        "SELECT medio_pago_id FROM gestion__medios_pago
         WHERE empresa_id = ? AND medio_pago_tipo = 'RETENCION' AND aplica_pago = 1 AND tabla_estado_registro_id = 1
           AND impuesto_tipo_id = ?
         ORDER BY orden, medio_pago_id LIMIT 1",
        'ii', intval($empresa_id), intval($impuesto_tipo_id));
    return $row ? intval($row['medio_pago_id']) : 0;
}

/** Condiciones posibles del proveedor frente a una retención. */
function retPracCondiciones() {
    return [
        'INSCRIPTO'    => 'Inscripto',
        'NO_INSCRIPTO' => 'No inscripto',
        'EXENTO'       => 'Exento',
        'NO_ALCANZADO' => 'No alcanzado',
    ];
}

/**
 * Bases de cálculo de un conjunto de imputaciones: proporción de cada comprobante que se cancela
 * (importe imputado / total del comprobante), con el signo del comprobante.
 */
function retPracBasesDeImputaciones($conexion, $empresa_id, $entidad_id, $imputaciones) {
    $bases = array_fill_keys(array_keys(retPracBases()), 0.0);
    foreach ($imputaciones as $imp) {
        $comp_id = intval($imp['comprobante_destino_id'] ?? $imp['comp_destino_id'] ?? 0);
        $monto = floatval($imp['importe_imputado'] ?? 0);
        if ($comp_id <= 0 || $monto <= 0) continue;
        $c = perIibbFila($conexion,
            "SELECT importe_neto, importe_exento, importe_no_gravado, importe_iva, importe_total, signo
             FROM gestion__comprobantes WHERE comprobante_id = ? AND empresa_id = ? AND entidad_id = ?",
            'iii', $comp_id, intval($empresa_id), intval($entidad_id));
        if (!$c || floatval($c['importe_total']) <= 0) continue;
        $factor = min(1.0, $monto / floatval($c['importe_total'])) * (intval($c['signo']) < 0 ? -1 : 1);
        $bases['NETO_GRAVADO'] += floatval($c['importe_neto']) * $factor;
        $bases['NETO']         += (floatval($c['importe_neto']) + floatval($c['importe_exento']) + floatval($c['importe_no_gravado'])) * $factor;
        $bases['IVA']          += floatval($c['importe_iva']) * $factor;
        $bases['TOTAL']        += floatval($c['importe_total']) * $factor;
    }
    return $bases;
}

/** Primer y último día del mes de $fecha. */
function retPracMes($fecha) {
    $desde = substr($fecha, 0, 7) . '-01';
    return [$desde, date('Y-m-t', strtotime($desde))];
}

/**
 * Bases de los pagos ya confirmados al proveedor en el mes de $fecha (régimen con mínimo acumulado mensual).
 * Supuesto documentado: el acumulado es por proveedor, no por régimen (un régimen habitual por proveedor e impuesto).
 */
function retPracBasesMes($conexion, $empresa_id, $entidad_id, $fecha) {
    [$desde, $hasta] = retPracMes($fecha);
    $imps = perIibbFilas($conexion,
        "SELECT ci.comp_destino_id, ci.importe_imputado
         FROM gestion__pagos p
         JOIN gestion__comprobantes_imputaciones ci ON ci.comp_origen_id = p.comprobante_id AND ci.tabla_estado_registro_id = 5
         WHERE p.empresa_id = ? AND p.entidad_id = ? AND p.tabla_estado_registro_id = 5 AND p.f_emision BETWEEN ? AND ?",
        'iiss', intval($empresa_id), intval($entidad_id), $desde, $hasta);
    return retPracBasesDeImputaciones($conexion, $empresa_id, $entidad_id, $imps);
}

/** Retenido en el mes al proveedor por el mismo impuesto, jurisdicción y régimen (OP confirmadas). */
function retPracRetenidoMes($conexion, $empresa_id, $entidad_id, $impuesto_tipo_id, $jurisdiccion_id, $regimen_id, $fecha) {
    [$desde, $hasta] = retPracMes($fecha);
    $row = perIibbFila($conexion,
        "SELECT COALESCE(SUM(importe), 0) AS total FROM gestion__pagos_retenciones
         WHERE empresa_id = ? AND entidad_id = ? AND impuesto_tipo_id = ? AND COALESCE(jurisdiccion_id, 0) = ?
           AND regimen_id = ? AND tabla_estado_registro_id = 5 AND f_retencion BETWEEN ? AND ?",
        'iiiiiss', intval($empresa_id), intval($entidad_id), intval($impuesto_tipo_id), intval($jurisdiccion_id),
        intval($regimen_id), $desde, $hasta);
    return floatval($row['total'] ?? 0);
}

/** Condición del proveedor vigente a $fecha para el impuesto (y jurisdicción en IIBB), o null. */
function retPracCondicionProveedor($conexion, $empresa_id, $entidad_id, $impuesto_tipo_id, $jurisdiccion_id, $fecha) {
    return perIibbFila($conexion,
        "SELECT * FROM gestion__entidades_retenciones
         WHERE empresa_id = ? AND entidad_id = ? AND impuesto_tipo_id = ? AND COALESCE(jurisdiccion_id, 0) = ?
           AND tabla_estado_registro_id = 1 AND f_desde <= ? AND (f_hasta IS NULL OR f_hasta >= ?)
         ORDER BY f_desde DESC, entidad_retencion_id DESC LIMIT 1",
        'iiiiss', intval($empresa_id), intval($entidad_id), intval($impuesto_tipo_id), intval($jurisdiccion_id), $fecha, $fecha);
}

/** Régimen activo y vigente a $fecha (global o de la empresa), o null. */
function retPracRegimen($conexion, $empresa_id, $regimen_id, $fecha) {
    return perIibbFila($conexion,
        "SELECT * FROM gestion__retenciones_regimenes
         WHERE regimen_id = ? AND empresa_id IN (0, ?) AND tabla_estado_registro_id = 1
           AND f_desde <= ? AND (f_hasta IS NULL OR f_hasta >= ?)",
        'iiss', intval($regimen_id), intval($empresa_id), $fecha, $fecha);
}

/** Retención por escala: tramo de la base sujeta, importe fijo + alícuota sobre el excedente. Devuelve [importe, alícuota]. */
function retPracEscala($conexion, $regimen_id, $base_sujeta) {
    $tramos = perIibbFilas($conexion,
        "SELECT desde, hasta, importe_fijo, alicuota_excedente FROM gestion__retenciones_regimenes_escalas
         WHERE regimen_id = ? ORDER BY desde", 'i', intval($regimen_id));
    foreach ($tramos as $t) {
        if ($base_sujeta >= floatval($t['desde']) && ($t['hasta'] === null || $base_sujeta < floatval($t['hasta']))) {
            return [floatval($t['importe_fijo']) + ($base_sujeta - floatval($t['desde'])) * floatval($t['alicuota_excedente']) / 100,
                    floatval($t['alicuota_excedente'])];
        }
    }
    return [null, null];
}

/** % de exclusión vigente del proveedor para la retención (certificado de exclusión / no retención). */
function retPracExclusion($conexion, $empresa_id, $entidad_id, $impuesto_tipo_id, $jurisdiccion_id, $fecha) {
    $row = perIibbFila($conexion,
        "SELECT MAX(porcentaje_exclusion) AS pct FROM gestion__entidades_iibb_exclusiones
         WHERE empresa_id = ? AND entidad_id = ? AND sentido = 'RETENCION' AND impuesto_tipo_id = ? AND jurisdiccion_id = ?
           AND tabla_estado_registro_id = 1 AND f_desde <= ? AND (f_hasta IS NULL OR f_hasta >= ?)",
        'iiiiss', intval($empresa_id), intval($entidad_id), intval($impuesto_tipo_id), intval($jurisdiccion_id), $fecha, $fecha);
    return $row && $row['pct'] !== null ? floatval($row['pct']) : 0.0;
}

/**
 * Calcula las retenciones que corresponden a un pago.
 *
 * $imputaciones: [['comprobante_destino_id' => id de gestion__comprobantes, 'importe_imputado' => x], ...]
 * Por cada impuesto (y jurisdicción) del que la empresa es agente:
 *  - Régimen: el habitual del proveedor (gestion__entidades_retenciones) o el de la config del agente. Con régimen,
 *    la condición del proveedor elige la alícuota (inscripto / no inscripto, escala para inscriptos si el régimen la
 *    usa) y manda el mínimo no sujeto del régimen, por pago o acumulado en el mes (restando lo ya retenido en el mes).
 *    Sin régimen se usan la base, alícuota genérica y mínimos de la config (cálculo de la etapa 1).
 *  - En IIBB el padrón de la jurisdicción (alicuota_retencion), si la config lo usa, gana sobre lo anterior.
 *  - Exentos / no alcanzados no se retienen; el certificado de exclusión reduce la retención en su %.
 * Devuelve ['retenciones' => [...], 'avisos' => [...]].
 */
function retPracCalcular($conexion, $empresa_id, $entidad_id, $fecha, $imputaciones) {
    $empresa_id = intval($empresa_id);
    $entidad_id = intval($entidad_id);
    $fecha = substr((string)($fecha ?: date('Y-m-d')), 0, 10);
    $resultado = ['retenciones' => [], 'avisos' => []];

    $configs = retPracConfigsVigentes($conexion, $empresa_id, $fecha);
    if (!$configs) return $resultado;

    $entidad = perIibbFila($conexion,
        "SELECT entidad_id, entidad_nombre, cuit FROM gestion__entidades WHERE entidad_id = ? AND empresa_id = ?",
        'ii', $entidad_id, $empresa_id);
    if (!$entidad) throw new Exception('Proveedor no encontrado.');
    $cuit = preg_replace('/\D/', '', (string)$entidad['cuit']);

    $bases = retPracBasesDeImputaciones($conexion, $empresa_id, $entidad_id, $imputaciones);
    if ($bases['TOTAL'] <= 0.004) {
        $resultado['avisos'][] = 'Impute primero los comprobantes que cancela el pago: la retención se calcula sobre ellos.';
        return $resultado;
    }
    $bases_mes = null;
    $condiciones = retPracCondiciones();

    foreach ($configs as $cfg) {
        $nombre = $cfg['impuesto_tipo'] . ($cfg['es_iibb'] ? ' - ' . $cfg['jurisdiccion_nombre'] : '');
        $impuesto_id = intval($cfg['impuesto_tipo_id']);
        $jur_id = $cfg['es_iibb'] ? intval($cfg['jurisdiccion_id']) : 0;

        $prov = retPracCondicionProveedor($conexion, $empresa_id, $entidad_id, $impuesto_id, $jur_id, $fecha);
        $condicion = $prov ? $prov['condicion'] : null;
        if (in_array($condicion, ['EXENTO', 'NO_ALCANZADO'], true)) {
            $resultado['avisos'][] = "$nombre: el proveedor está " . mb_strtolower($condiciones[$condicion], 'UTF-8') . '.';
            continue;
        }

        $regimen_id = intval($prov['regimen_id'] ?? 0) ?: intval($cfg['regimen_id'] ?? 0);
        $reg = null;
        if ($regimen_id > 0) {
            $reg = retPracRegimen($conexion, $empresa_id, $regimen_id, $fecha);
            if (!$reg) {
                $resultado['avisos'][] = "$nombre: el régimen asignado no está activo o vigente al $fecha.";
                continue;
            }
        }

        $base_calculo = $reg ? $reg['base_calculo'] : $cfg['base_calculo'];
        if (!isset($bases[$base_calculo])) $base_calculo = 'NETO_GRAVADO';
        $base = round($bases[$base_calculo], 2);
        if ($base <= 0) continue;

        // IIBB: situación frente al impuesto en la jurisdicción (condición IIBB del proveedor) si no se cargó otra
        if ($cfg['es_iibb'] && $condicion === null) {
            $situacion = perIibbSituacionCondicion($conexion, $entidad_id, intval($cfg['provincia_id'] ?? 0), $fecha);
            if ($situacion === 'EXENTO') {
                $resultado['avisos'][] = "$nombre: el proveedor es exento.";
                continue;
            }
            $con_alta = in_array($situacion, ['INSCRIPTO_JURISDICCION', 'CONVENIO_JURISDICCION', 'SIMPLIFICADO_JURISDICCION'], true);
            $condicion = $con_alta ? 'INSCRIPTO' : 'NO_INSCRIPTO';
        }

        $padron = null;
        if ($cfg['tipo_calculo'] === 'padron' && $cuit !== '' && intval($cfg['jurisdiccion_id']) > 0) {
            $padron = perIibbPadron($conexion, $empresa_id, intval($cfg['jurisdiccion_id']), $cuit, $fecha);
            if ($padron && $padron['alicuota_retencion'] === null) $padron = null;
        }
        if ($cfg['es_iibb'] && !$padron && intval($cfg['aplica_siempre']) !== 1 && $condicion !== 'INSCRIPTO'
                && !($prov && $prov['condicion'] === 'NO_INSCRIPTO')) {
            continue;   // sin alta en la jurisdicción
        }
        if ($cfg['tipo_calculo'] === 'padron' && !$padron && $cfg['sin_dato_accion'] === 'NO_RETENER') {
            $resultado['avisos'][] = "$nombre: el proveedor no figura en el padrón.";
            continue;
        }

        $base_mes = null;
        $previas = null;
        if ($reg) {
            if ($condicion === null) {
                $resultado['avisos'][] = "$nombre: el proveedor no tiene condición definida frente al impuesto "
                    . '(Historial de situación fiscal del proveedor). No se calculó.';
                continue;
            }
            // Base sujeta según el mínimo no sujeto del régimen, por pago o acumulada en el mes
            $base_ref = $base;
            if ($reg['tipo_minimo'] === 'ACUMULADO_MENSUAL') {
                if ($bases_mes === null) $bases_mes = retPracBasesMes($conexion, $empresa_id, $entidad_id, $fecha);
                $base_mes = round($bases_mes[$base_calculo] + $base, 2);
                $previas = round(retPracRetenidoMes($conexion, $empresa_id, $entidad_id, $impuesto_id, $jur_id, $regimen_id, $fecha), 2);
                $base_ref = $base_mes;
            }
            $minimo = floatval($reg['minimo_no_sujeto']);
            $sujeta = $reg['modo_minimo'] === 'UMBRAL' ? ($base_ref > $minimo ? $base_ref : 0.0) : max(0.0, $base_ref - $minimo);
            if ($sujeta <= 0) {
                $resultado['avisos'][] = "$nombre: la base ($base_ref) no supera el mínimo no sujeto del régimen ($minimo).";
                continue;
            }
            if ($padron) {
                $alicuota = floatval($padron['alicuota_retencion']);
                $retencion = $sujeta * $alicuota / 100;
                $origen = 'PADRON';
            } elseif ($condicion === 'INSCRIPTO' && intval($reg['usa_escala']) === 1) {
                [$retencion, $alicuota] = retPracEscala($conexion, $regimen_id, $sujeta);
                if ($retencion === null) {
                    $resultado['avisos'][] = "$nombre: la escala del régimen no tiene tramo para la base $sujeta.";
                    continue;
                }
                $origen = 'ESCALA';
            } else {
                $alic_reg = $condicion === 'INSCRIPTO' ? $reg['alicuota_inscripto'] : $reg['alicuota_no_inscripto'];
                if ($alic_reg === null) {
                    $resultado['avisos'][] = "$nombre: el régimen {$reg['codigo']} no tiene alícuota para "
                        . mb_strtolower($condiciones[$condicion], 'UTF-8') . 's (a confirmar).';
                    continue;
                }
                $alicuota = floatval($alic_reg);
                $retencion = $sujeta * $alicuota / 100;
                $origen = 'REGIMEN';
            }
            $importe = round($retencion - floatval($previas), 2);
            $minimo_ret = floatval($reg['minimo_retencion']);
        } else {
            // Sin régimen: base, alícuota genérica y mínimos de la config del agente
            if ($base < floatval($cfg['minimo_imponible'])) {
                $resultado['avisos'][] = "$nombre: la base ($base) no llega al mínimo imponible configurado.";
                continue;
            }
            if ($padron) {
                $alicuota = floatval($padron['alicuota_retencion']);
                $origen = 'PADRON';
            } else {
                $alicuota = floatval($cfg['alicuota']);
                $origen = 'GENERICA';
            }
            $importe = round($base * $alicuota / 100, 2);
            $minimo_ret = floatval($cfg['importe_minimo']);
        }
        if ($alicuota <= 0 || $importe <= 0) continue;

        $exclusion = retPracExclusion($conexion, $empresa_id, $entidad_id, $impuesto_id, intval($cfg['jurisdiccion_id']), $fecha);
        if ($exclusion > 0) {
            $importe = round($importe * (1 - min(100.0, $exclusion) / 100), 2);
            $resultado['avisos'][] = "$nombre: certificado de exclusión del $exclusion %.";
            if ($importe <= 0) continue;
        }
        if ($importe < $minimo_ret) {
            $resultado['avisos'][] = "$nombre: la retención ($importe) no llega al mínimo ($minimo_ret).";
            continue;
        }
        $medio_pago_id = retPracMedioPago($conexion, $empresa_id, $impuesto_id);
        if ($medio_pago_id <= 0) {
            $resultado['avisos'][] = "$nombre: la empresa no tiene un medio de pago de retención para este impuesto.";
            continue;
        }
        $resultado['retenciones'][] = [
            'empresa_impuesto_config_id' => intval($cfg['empresa_impuesto_config_id']),
            'medio_pago_id'              => $medio_pago_id,
            'impuesto_tipo_id'           => $impuesto_id,
            'impuesto_tipo'              => $cfg['impuesto_tipo'],
            'jurisdiccion_id'            => $cfg['es_iibb'] ? $jur_id : null,
            'jurisdiccion_nombre'        => $cfg['es_iibb'] ? $cfg['jurisdiccion_nombre'] : null,
            'regimen_id'                 => $reg ? $regimen_id : null,
            'regimen'                    => $reg ? $reg['codigo'] . ' - ' . $reg['regimen'] : null,
            'condicion'                  => $condicion,
            'base_calculo'               => $base_calculo,
            'retencion_base_imponible'   => $base,
            'base_acumulada_mes'         => $base_mes,
            'retenciones_previas_mes'    => $previas,
            'porcentaje_exclusion'       => $exclusion > 0 ? $exclusion : null,
            'retencion_alicuota'         => round($alicuota, 4),
            'importe'                    => $importe,
            'origen'                     => $origen,
        ];
    }
    return $resultado;
}

/**
 * Valida y completa las líneas RETENCION de una OP, antes de escribir nada: el impuesto sale del medio si no vino,
 * la empresa tiene que ser agente de ese impuesto (y de la jurisdicción, que es obligatoria en IIBB y se descarta en
 * el resto) a la fecha del pago. Recalcula la retención con la config sobre las imputaciones de la OP: si el importe
 * cargado difiere de lo calculado, exige motivo. Deja en $m['_ret'] lo que registra retPracRegistrarBorrador().
 * El certificado no se carga: se numera al confirmar la OP. Lanza Exception con el motivo.
 */
function retPracValidarMedios($conexion, $empresa_id, $entidad_id, $fecha, array $imputaciones, array &$medios) {
    $empresa_id = intval($empresa_id);
    $calculo = null;
    foreach ($medios as &$m) {
        $medio = perIibbFila($conexion,
            "SELECT medio_pago, medio_pago_tipo, impuesto_tipo_id FROM gestion__medios_pago WHERE medio_pago_id = ? AND empresa_id = ?",
            'ii', intval($m['medio_pago_id'] ?? 0), $empresa_id);
        if (!$medio) throw new Exception('Medio de pago inexistente para la empresa.');
        if ($medio['medio_pago_tipo'] !== 'RETENCION') {
            $m['impuesto_tipo_id'] = null;
            $m['jurisdiccion_id'] = null;
            continue;
        }
        $impuesto_id = intval($m['impuesto_tipo_id'] ?? 0) ?: intval($medio['impuesto_tipo_id']);
        if ($impuesto_id <= 0) throw new Exception("Indique el impuesto de la línea \"{$medio['medio_pago']}\".");
        $cfgs = array_values(array_filter(retPracConfigsVigentes($conexion, $empresa_id, $fecha),
            function ($c) use ($impuesto_id) { return intval($c['impuesto_tipo_id']) === $impuesto_id; }));
        if (!$cfgs) throw new Exception("La empresa no es agente de retención de \"{$medio['medio_pago']}\" al $fecha (Impuestos Adic. - Conf).");

        $jurisdiccion_id = null;
        if ($cfgs[0]['es_iibb']) {
            $jurisdiccion_id = intval($m['jurisdiccion_id'] ?? 0);
            if ($jurisdiccion_id <= 0) throw new Exception('Indique la jurisdicción de la retención de IIBB.');
            $ok = false;
            foreach ($cfgs as $c) if (intval($c['jurisdiccion_id']) === $jurisdiccion_id) $ok = true;
            if (!$ok) throw new Exception('La empresa no es agente de retención de IIBB en la jurisdicción indicada.');
        }
        $m['impuesto_tipo_id'] = $impuesto_id;
        $m['jurisdiccion_id'] = $jurisdiccion_id;

        $m['retencion_certificado_nro'] = null;   // lo asigna retPracConfirmar()

        // Importe de referencia: lo que calcula la config para este impuesto / jurisdicción sobre lo imputado
        if ($calculo === null) {
            $calculo = intval($entidad_id) > 0 ? retPracCalcular($conexion, $empresa_id, $entidad_id, $fecha, $imputaciones) : ['retenciones' => []];
        }
        $calc = null;
        foreach ($calculo['retenciones'] as $r) {
            if (intval($r['impuesto_tipo_id']) === $impuesto_id && intval($r['jurisdiccion_id']) === intval($jurisdiccion_id)) $calc = $r;
        }
        $cfg = retPracConfig($conexion, $empresa_id, $impuesto_id, $jurisdiccion_id, $fecha);
        $importe = round(floatval($m['importe_comprobante'] ?? $m['importe'] ?? 0), 2);
        $calculado = $calc ? round(floatval($calc['importe']), 2) : null;
        $motivo = trim((string)($m['retencion_motivo_ajuste'] ?? ''));
        if (abs($importe - floatval($calculado)) > 0.009 && $motivo === '') {
            throw new Exception("Retención \"{$medio['medio_pago']}\": el importe ($importe) difiere de lo calculado ("
                . ($calculado === null ? 'no corresponde retener' : $calculado) . '). Indique el motivo del ajuste.');
        }
        $alicuota = floatval($m['retencion_alicuota'] ?? 0);
        $m['_ret'] = [
            'empresa_impuesto_config_id' => $cfg ? intval($cfg['empresa_impuesto_config_id']) : null,
            'importe_calculado'          => $calculado,
            'motivo_ajuste'              => $motivo !== '' ? mb_substr($motivo, 0, 255) : null,
            'origen_alicuota'            => $calc && abs($alicuota - floatval($calc['retencion_alicuota'])) < 0.00005 ? $calc['origen'] : 'MANUAL',
            'regimen_id'                 => $calc['regimen_id'] ?? null,
            'regimen'                    => $calc['regimen'] ?? null,
            'condicion'                  => $calc['condicion'] ?? null,
            'base_acumulada_mes'         => $calc['base_acumulada_mes'] ?? null,
            'retenciones_previas_mes'    => $calc['retenciones_previas_mes'] ?? null,
            'porcentaje_exclusion'       => $calc['porcentaje_exclusion'] ?? null,
        ];
    }
    unset($m);
}

/**
 * Registra en gestion__pagos_retenciones (Borrador) una fila por línea RETENCION ya insertada en gestion__pagos_medios
 * ($m['_pago_medio_id']). Reemplaza las del borrador anterior de la OP.
 */
function retPracRegistrarBorrador($conexion, $empresa_id, $pago_id, $entidad_id, $fecha, array $medios, $usuario_id) {
    $empresa_id = intval($empresa_id);
    $pago_id = intval($pago_id);
    $stmt = mysqli_prepare($conexion, "DELETE FROM gestion__pagos_retenciones WHERE pago_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 3");
    mysqli_stmt_bind_param($stmt, 'ii', $pago_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $sql = "INSERT INTO gestion__pagos_retenciones
                (empresa_id, pago_id, pago_medio_id, entidad_id, impuesto_tipo_id, jurisdiccion_id, empresa_impuesto_config_id,
                 regimen, f_retencion, base_imponible, alicuota, origen_alicuota, importe, importe_calculado, motivo_ajuste,
                 tabla_estado_registro_id, usuario_id,
                 regimen_id, condicion, base_acumulada_mes, retenciones_previas_mes, porcentaje_exclusion)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 3, ?, ?, ?, ?, ?, ?)";
    $entidad = intval($entidad_id) ?: null;
    $usuario_id = intval($usuario_id);
    foreach ($medios as $m) {
        if (empty($m['_ret']) || empty($m['_pago_medio_id'])) continue;
        $r = $m['_ret'];
        $medio_id = intval($m['_pago_medio_id']);
        $impuesto = intval($m['impuesto_tipo_id']);
        $jurisdiccion = $m['jurisdiccion_id'] ? intval($m['jurisdiccion_id']) : null;
        $config = $r['empresa_impuesto_config_id'];
        $base = round(floatval($m['retencion_base_imponible'] ?? 0), 2);
        $alicuota = floatval($m['retencion_alicuota'] ?? 0);
        $origen = $r['origen_alicuota'];
        $importe = round(floatval($m['importe_comprobante'] ?? $m['importe'] ?? 0), 2);
        $calculado = $r['importe_calculado'];
        $motivo = $r['motivo_ajuste'];
        $regimen_id = $r['regimen_id'] ? intval($r['regimen_id']) : null;
        $regimen = $r['regimen'] !== null ? mb_substr($r['regimen'], 0, 100) : null;
        $condicion = $r['condicion'];
        $base_mes = $r['base_acumulada_mes'];
        $previas = $r['retenciones_previas_mes'];
        $exclusion = $r['porcentaje_exclusion'];
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, 'iiiiiiissddsddsiisddd', $empresa_id, $pago_id, $medio_id, $entidad, $impuesto, $jurisdiccion,
            $config, $regimen, $fecha, $base, $alicuota, $origen, $importe, $calculado, $motivo, $usuario_id,
            $regimen_id, $condicion, $base_mes, $previas, $exclusion);
        if (!mysqli_stmt_execute($stmt)) throw new Exception('Error registrando la retención: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
    }
}

/** Número de certificado para mostrar e imprimir. */
function retPracCertificadoTexto($numero) {
    return $numero ? str_pad((string)intval($numero), 8, '0', STR_PAD_LEFT) : '';
}

/**
 * Al confirmar la OP: numera el certificado de cada retención en Borrador (correlativo por empresa, impuesto y
 * jurisdicción, gestion__retenciones_numeradores bajo FOR UPDATE), la pasa a Confirmada y copia el número a la línea del medio.
 * Corre dentro de la transacción de confirmarPago().
 */
function retPracConfirmar($conexion, $empresa_id, $pago_id, $fecha, $usuario_id) {
    $empresa_id = intval($empresa_id);
    $pago_id = intval($pago_id);
    $usuario_id = intval($usuario_id);
    $filas = perIibbFilas($conexion,
        "SELECT retencion_id, pago_medio_id, impuesto_tipo_id, jurisdiccion_id FROM gestion__pagos_retenciones
         WHERE pago_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 3 ORDER BY impuesto_tipo_id, jurisdiccion_id, retencion_id",
        'ii', $pago_id, $empresa_id);
    foreach ($filas as $f) {
        $impuesto = intval($f['impuesto_tipo_id']);
        $jurisdiccion = intval($f['jurisdiccion_id']);   // 0 = impuesto nacional
        $stmt = mysqli_prepare($conexion, "INSERT IGNORE INTO gestion__retenciones_numeradores (empresa_id, impuesto_tipo_id, jurisdiccion_id, ultimo_numero, usuario_id) VALUES (?, ?, ?, 0, ?)");
        mysqli_stmt_bind_param($stmt, 'iiii', $empresa_id, $impuesto, $jurisdiccion, $usuario_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $num = perIibbFila($conexion,
            "SELECT retencion_numerador_id, ultimo_numero FROM gestion__retenciones_numeradores
             WHERE empresa_id = ? AND impuesto_tipo_id = ? AND jurisdiccion_id = ? FOR UPDATE", 'iii', $empresa_id, $impuesto, $jurisdiccion);
        $nro = intval($num['ultimo_numero']) + 1;
        $num_id = intval($num['retencion_numerador_id']);

        $stmt = mysqli_prepare($conexion, "UPDATE gestion__retenciones_numeradores SET ultimo_numero = ?, usuario_modificacion_id = ? WHERE retencion_numerador_id = ?");
        mysqli_stmt_bind_param($stmt, 'iii', $nro, $usuario_id, $num_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        $ret_id = intval($f['retencion_id']);
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__pagos_retenciones
            SET certificado_nro = ?, f_retencion = ?, tabla_estado_registro_id = 5, usuario_modificacion_id = ? WHERE retencion_id = ?");
        mysqli_stmt_bind_param($stmt, 'isii', $nro, $fecha, $usuario_id, $ret_id);
        if (!mysqli_stmt_execute($stmt)) throw new Exception('Error numerando el certificado: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $texto = retPracCertificadoTexto($nro);
        $medio_id = intval($f['pago_medio_id']);
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__pagos_medios SET retencion_certificado_nro = ? WHERE pago_medio_id = ? AND pago_id = ?");
        mysqli_stmt_bind_param($stmt, 'sii', $texto, $medio_id, $pago_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
}

/**
 * Al anular la OP: sus retenciones quedan Anuladas con su número (el certificado no se reutiliza). No se puede si
 * alguna ya está incluida en una liquidación (comp_liquidacion_id): primero hay que anular la liquidación.
 */
function retPracAnular($conexion, $empresa_id, $pago_id, $usuario_id, $motivo) {
    $empresa_id = intval($empresa_id);
    $pago_id = intval($pago_id);
    $usuario_id = intval($usuario_id);
    $liquidada = perIibbFila($conexion,
        "SELECT certificado_nro FROM gestion__pagos_retenciones
         WHERE pago_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 5 AND comp_liquidacion_id IS NOT NULL LIMIT 1",
        'ii', $pago_id, $empresa_id);
    if ($liquidada) {
        throw new Exception('La retención con certificado ' . retPracCertificadoTexto($liquidada['certificado_nro'])
            . ' ya está liquidada: anule primero la liquidación.');
    }
    $obs = mb_substr('Anulada con la OP: ' . trim((string)$motivo), 0, 255);
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__pagos_retenciones
        SET tabla_estado_registro_id = 38, observaciones = ?, usuario_modificacion_id = ?
        WHERE pago_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 5");
    mysqli_stmt_bind_param($stmt, 'siii', $obs, $usuario_id, $pago_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

/** Retenciones registradas de una OP (para el detalle y la impresión del certificado). */
function retPracDeLaOp($conexion, $empresa_id, $pago_id) {
    return perIibbFilas($conexion,
        "SELECT r.*, it.impuesto_tipo, j.jurisdiccion_nombre, er.estado_registro
         FROM gestion__pagos_retenciones r
         JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = r.impuesto_tipo_id
         LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = r.jurisdiccion_id
         LEFT JOIN conf__estados_registros er ON er.estado_registro_id = r.tabla_estado_registro_id
         WHERE r.pago_id = ? AND r.empresa_id = ? ORDER BY r.retencion_id",
        'ii', intval($pago_id), intval($empresa_id));
}

/** Cuenta contable de la retención: la de su config (vigente a la fecha del pago). */
function retPracCuentaContable($conexion, $empresa_id, $impuesto_tipo_id, $jurisdiccion_id, $fecha) {
    $cfg = retPracConfig($conexion, $empresa_id, $impuesto_tipo_id, $jurisdiccion_id, $fecha);
    return $cfg ? intval($cfg['cont_cuenta_id']) : 0;
}
