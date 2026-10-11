<?php
/**
 * Percepciones de IIBB practicadas (empresa agente de percepción).
 * Diseño: docs/gestion/09_percepciones_iibb.md — estructura: sql/mantenimiento/20261010_percepciones_iibb_01_estructura.sql
 *
 * Servicio único de cálculo: lo usan ventas_facturas_model.php (al guardar el borrador, al editar y al confirmar)
 * y la vista previa de la pantalla. Nada propio de una provincia está acá: alícuotas por situación, leyendas,
 * territorio y formatos salen de gestion__empresas_impuestos_config (impuesto_tipo_id = 6) y de
 * gestion__empresas_impuestos_config_situaciones.
 *
 * Todas las funciones corren dentro de la transacción del llamador (no abren ni confirman la suya).
 */

if (!defined('PER_IIBB_IMPUESTO_TIPO_ID')) define('PER_IIBB_IMPUESTO_TIPO_ID', 6);   // "Percepción IIBB Efectuadas" (catálogo global)

/** Situaciones posibles del cliente frente al impuesto de una jurisdicción. */
function perIibbSituaciones() {
    return [
        'PADRON'                      => 'Incluido en el padrón',
        'INSCRIPTO_JURISDICCION'      => 'Inscripto en la jurisdicción (directo o CM con alta)',
        'SIMPLIFICADO_JURISDICCION'   => 'Régimen simplificado de la jurisdicción',
        'INSCRIPTO_OTRA_JURISDICCION' => 'Inscripto en otra jurisdicción, sin alta en esta',
        'NO_INSCRIPTO'                => 'No inscripto / sin condición IIBB',
        'EXENTO'                      => 'Exento / no alcanzado',
        'BIEN_DE_USO'                 => 'Destino bien de uso declarado',
    ];
}

/** Letras de alícuota del padrón SIRCIP (RG CA 9/2025). */
function perIibbLetrasSircip() {
    return ['A' => 0.00, 'B' => 0.01, 'C' => 0.05, 'D' => 0.10, 'E' => 0.20, 'F' => 0.30, 'G' => 0.40, 'H' => 0.50,
            'I' => 0.60, 'J' => 0.70, 'K' => 0.80, 'L' => 1.00, 'M' => 1.20, 'N' => 1.40, 'O' => 1.50, 'P' => 1.60,
            'Q' => 1.80, 'R' => 2.00, 'S' => 2.50, 'T' => 3.00, 'U' => 3.50, 'V' => 4.00, 'W' => 4.50, 'X' => 5.00];
}

function perIibbFila($conexion, $sql, $tipos, ...$params) {
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) throw new Exception('Percepciones IIBB: ' . mysqli_error($conexion));
    if ($tipos !== '') mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function perIibbFilas($conexion, $sql, $tipos, ...$params) {
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) throw new Exception('Percepciones IIBB: ' . mysqli_error($conexion));
    if ($tipos !== '') mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) $rows[] = $r;
    mysqli_stmt_close($stmt);
    return $rows;
}

/**
 * Configs de percepción IIBB de la empresa vigentes a $fecha para el subgrupo del comprobante, con sus situaciones.
 */
function perIibbConfigsVigentes($conexion, $empresa_id, $comprobante_subgrupo_id, $fecha) {
    $tipo = PER_IIBB_IMPUESTO_TIPO_ID;
    $configs = perIibbFilas($conexion,
        "SELECT cfg.*, j.jurisdiccion_nombre, j.jurisdiccion_codigo, j.provincia_id
         FROM gestion__empresas_impuestos_config cfg
         JOIN gestion__empresas_impuestos_config_subgrupos sub
              ON sub.empresa_impuesto_config_id = cfg.empresa_impuesto_config_id
             AND sub.comprobante_subgrupo_id = ? AND sub.tabla_estado_registro_id = 1
         JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = cfg.jurisdiccion_id
         WHERE cfg.empresa_id = ? AND cfg.impuesto_tipo_id = ? AND cfg.tabla_estado_registro_id = 1
           AND cfg.f_desde <= ? AND (cfg.f_hasta IS NULL OR cfg.f_hasta >= ?)
         ORDER BY cfg.prioridad, j.orden, cfg.empresa_impuesto_config_id",
        'iiiss', $comprobante_subgrupo_id, $empresa_id, $tipo, $fecha, $fecha);
    foreach ($configs as &$cfg) {
        $cfg['situaciones'] = [];
        foreach (perIibbFilas($conexion,
            "SELECT * FROM gestion__empresas_impuestos_config_situaciones
             WHERE empresa_impuesto_config_id = ? AND tabla_estado_registro_id = 1",
            'i', intval($cfg['empresa_impuesto_config_id'])) as $s) {
            $cfg['situaciones'][$s['situacion']] = $s;
        }
    }
    unset($cfg);
    return $configs;
}

/** ¿La empresa es agente de percepción IIBB en alguna jurisdicción a esa fecha? (para validar datos de la factura) */
function perIibbEmpresaEsAgente($conexion, $empresa_id, $fecha) {
    $tipo = PER_IIBB_IMPUESTO_TIPO_ID;
    return (bool) perIibbFila($conexion,
        "SELECT 1 FROM gestion__empresas_impuestos_config
         WHERE empresa_id = ? AND impuesto_tipo_id = ? AND tabla_estado_registro_id = 1
           AND f_desde <= ? AND (f_hasta IS NULL OR f_hasta >= ?) LIMIT 1",
        'iiss', $empresa_id, $tipo, $fecha, $fecha);
}

/** Provincia (conf__provincias) donde se considera hecha la operación, según el criterio de la config. */
function perIibbProvinciaOperacion($conexion, $empresa_id, $cfg, $cab) {
    if (($cfg['criterio_territorial'] ?? 'ENTREGA') === 'ESTABLECIMIENTO') {
        $row = perIibbFila($conexion,
            "SELECT l.provincia_id FROM gestion__sucursales s
             JOIN conf__localidades l ON l.localidad_id = s.localidad_id
             WHERE s.sucursal_id = ? AND s.empresa_id = ?",
            'ii', intval($cab['sucursal_id'] ?? 0), $empresa_id);
        return $row ? intval($row['provincia_id']) : null;
    }
    // ENTREGA: localidad de la sucursal de la entidad (decisión 2026-10-10)
    $row = perIibbFila($conexion,
        "SELECT l.provincia_id FROM gestion__entidades_sucursales es
         JOIN conf__localidades l ON l.localidad_id = es.localidad_id
         WHERE es.sucursal_id = ? AND es.entidad_id = ?",
        'ii', intval($cab['entidad_sucursal_id'] ?? 0), intval($cab['entidad_id'] ?? 0));
    return $row ? intval($row['provincia_id']) : null;
}

/** Fila vigente del padrón para el CUIT (período = mes de la fecha). */
function perIibbPadron($conexion, $empresa_id, $jurisdiccion_id, $cuit, $fecha) {
    $periodo = substr(str_replace('-', '', $fecha), 0, 6);
    return perIibbFila($conexion,
        "SELECT * FROM gestion__padrones_iibb
         WHERE empresa_id = ? AND jurisdiccion_id = ? AND periodo = ? AND cuit = ? LIMIT 1",
        'iiss', $empresa_id, $jurisdiccion_id, $periodo, $cuit);
}

/** Situación del cliente según su condición IIBB vigente (sin padrón). */
function perIibbSituacionCondicion($conexion, $entidad_id, $provincia_jurisdiccion_id, $fecha) {
    $cond = perIibbFila($conexion,
        "SELECT ec.entidad_condicion_iibb_id, r.regimen_iibb_codigo, s.provincia_id AS sede_provincia_id
         FROM gestion__entidades_condiciones_iibb ec
         JOIN gestion__regimenes_iibb r ON r.regimen_iibb_id = ec.regimen_iibb_id
         LEFT JOIN gestion__iibb_jurisdicciones_sedes s ON s.jurisdiccion_sede_id = ec.jurisdiccion_sede_id
         WHERE ec.entidad_id = ? AND ec.tabla_estado_registro_id = 1
           AND ec.f_desde <= ? AND (ec.f_hasta IS NULL OR ec.f_hasta >= ?)
         ORDER BY ec.f_desde DESC LIMIT 1",
        'iss', $entidad_id, $fecha, $fecha);
    if (!$cond) return 'NO_INSCRIPTO';
    $codigo = $cond['regimen_iibb_codigo'];
    if ($codigo === 'EXENTO') return 'EXENTO';

    $en_jurisdiccion = $provincia_jurisdiccion_id > 0 && intval($cond['sede_provincia_id'] ?? 0) === $provincia_jurisdiccion_id;
    if (!$en_jurisdiccion && $provincia_jurisdiccion_id > 0) {
        $en_jurisdiccion = (bool) perIibbFila($conexion,
            "SELECT 1 FROM gestion__entidades_condiciones_iibb_jurisdicciones cj
             JOIN gestion__iibb_jurisdicciones_sedes s ON s.jurisdiccion_sede_id = cj.jurisdiccion_sede_id
             WHERE cj.entidad_condicion_iibb_id = ? AND s.provincia_id = ? AND cj.tabla_estado_registro_id = 1
               AND (cj.f_desde IS NULL OR cj.f_desde <= ?) AND (cj.f_hasta IS NULL OR cj.f_hasta >= ?) LIMIT 1",
            'iiss', intval($cond['entidad_condicion_iibb_id']), $provincia_jurisdiccion_id, $fecha, $fecha);
    }
    if (!$en_jurisdiccion) return 'INSCRIPTO_OTRA_JURISDICCION';
    return $codigo === 'SIMPLIFICADO' ? 'SIMPLIFICADO_JURISDICCION' : 'INSCRIPTO_JURISDICCION';
}

/** Porcentaje de exclusión vigente (certificado de no percepción) del cliente en la jurisdicción. */
function perIibbExclusion($conexion, $empresa_id, $entidad_id, $jurisdiccion_id, $fecha) {
    $row = perIibbFila($conexion,
        "SELECT MAX(porcentaje_exclusion) AS pct FROM gestion__entidades_iibb_exclusiones
         WHERE empresa_id = ? AND entidad_id = ? AND jurisdiccion_id = ? AND tabla_estado_registro_id = 1
           AND f_desde <= ? AND (f_hasta IS NULL OR f_hasta >= ?)",
        'iiiss', $empresa_id, $entidad_id, $jurisdiccion_id, $fecha, $fecha);
    return $row && $row['pct'] !== null ? floatval($row['pct']) : 0.0;
}

/** Datos del tipo de comprobante: subgrupo, fiscal, signo, clase (FA / ND / NC). */
function perIibbTipoComprobante($conexion, $empresa_id, $tipo_id) {
    $row = perIibbFila($conexion,
        "SELECT ct.comprobante_tipo_id, ct.comprobante_subgrupo_id, ct.comprobante_fiscal_id, ct.letra, ct.signo,
                CAST(cf.codigo AS UNSIGNED) AS codigo_fiscal,
                (SELECT COUNT(*) FROM gestion__comprobantes_tipos_dependencias d
                  WHERE d.comprobante_tipo_id = ct.comprobante_tipo_id AND d.tabla_estado_registro_id = 1) AS es_dependiente
         FROM gestion__comprobantes_tipos ct
         LEFT JOIN gestion__comprobantes_fiscales cf ON cf.comprobante_fiscal_id = ct.comprobante_fiscal_id
         WHERE ct.comprobante_tipo_id = ? AND ct.empresa_id IN (0, ?) LIMIT 1",
        'ii', $tipo_id, $empresa_id);
    if (!$row) return null;
    $cod = intval($row['codigo_fiscal'] ?? 0);
    if (in_array($cod, [3, 8, 13, 21, 53], true) || $row['signo'] === '-') $row['clase'] = 'NC';
    elseif (in_array($cod, [2, 7, 12, 20, 52], true) || intval($row['es_dependiente']) > 0) $row['clase'] = 'ND';
    else $row['clase'] = 'FA';
    return $row;
}

/**
 * Calcula las percepciones de un comprobante de venta.
 *
 * $cab: comprobante_tipo_id, entidad_id, entidad_sucursal_id, sucursal_id, f_emision, destino_bien_uso,
 *       comprobante_asociado_id (comprobante_id de gestion__comprobantes, para NC)
 * $detalles: [['importe_neto' => x, 'es_exento' => 0/1, 'es_no_gravado' => 0/1], ...]
 *
 * Devuelve ['percepciones' => [...], 'total' => float, 'avisos' => [...]]. Lanza Exception si falta un dato
 * obligatorio (sucursal de la entidad sin localidad, config sin cuenta contable).
 */
function perIibbCalcular($conexion, $empresa_id, $cab, $detalles) {
    $resultado = ['percepciones' => [], 'total' => 0.0, 'avisos' => []];
    $empresa_id = intval($empresa_id);
    $fecha = substr((string)($cab['f_emision'] ?? date('Y-m-d')), 0, 10);
    $entidad_id = intval($cab['entidad_id'] ?? 0);

    $tipo = perIibbTipoComprobante($conexion, $empresa_id, intval($cab['comprobante_tipo_id'] ?? 0));
    // Solo comprobantes fiscales (A/B/C...): los X no son documento fiscal y no llevan percepción
    if (!$tipo || intval($tipo['comprobante_fiscal_id']) <= 0) return $resultado;
    // Consumidor final: excluido en todos los regímenes
    if ($entidad_id <= 0) return $resultado;

    $configs = perIibbConfigsVigentes($conexion, $empresa_id, intval($tipo['comprobante_subgrupo_id']), $fecha);
    if (!$configs) return $resultado;

    $ent = perIibbFila($conexion, "SELECT cuit FROM gestion__entidades WHERE entidad_id = ? AND empresa_id = ?", 'ii', $entidad_id, $empresa_id);
    $cuit = preg_replace('/\D/', '', (string)($ent['cuit'] ?? ''));
    if (strlen($cuit) !== 11) {
        $resultado['avisos'][] = 'El cliente no tiene CUIT: no se calculan percepciones de IIBB.';
        return $resultado;
    }

    // NC con comprobante asociado: se revierte lo percibido en el original
    $original = [];
    $asociado_id = intval($cab['comprobante_asociado_id'] ?? 0);
    if ($tipo['clase'] === 'NC' && $asociado_id > 0) {
        foreach (perIibbFilas($conexion,
            "SELECT ci.*, c.f_emision AS original_f_emision
             FROM gestion__comprobantes_impuestos ci
             JOIN gestion__comprobantes c ON c.comprobante_id = ci.comprobante_id
             WHERE ci.comprobante_id = ? AND c.empresa_id = ? AND ci.impuesto_tipo_id = ? AND ci.tabla_estado_registro_id = 1",
            'iii', $asociado_id, $empresa_id, PER_IIBB_IMPUESTO_TIPO_ID) as $o) {
            $original[intval($o['jurisdiccion_id'])] = $o;
        }
    }

    foreach ($configs as $cfg) {
        $jur_id = intval($cfg['jurisdiccion_id']);
        $jur_prov = intval($cfg['provincia_id'] ?? 0);
        $nombre_jur = $cfg['jurisdiccion_nombre'];

        // Base: neto sin IVA (NETO), solo gravado (NETO_GRAVADO, default) o con IVA (TOTAL)
        $base = 0.0;
        foreach ($detalles as $d) {
            $neto = floatval($d['importe_neto'] ?? 0);
            switch ($cfg['base_calculo']) {
                case 'NETO':  $base += $neto; break;
                case 'TOTAL': $base += $neto + floatval($d['importe_iva'] ?? 0); break;
                default:      if (empty($d['es_exento']) && empty($d['es_no_gravado'])) $base += $neto;
            }
        }
        $base = round($base, 2);
        if ($base <= 0) continue;

        if ($tipo['clase'] === 'NC' && isset($original[$jur_id])) {
            $o = $original[$jur_id];
            $alic = floatval($o['alicuota']);
            $importe = min(round($base * $alic / 100, 2), round(floatval($o['importe']), 2));
            if ($importe <= 0 && !intval($o['tipo_registro_ddjj'] ?? 0)) continue;
            $aviso = null;
            if ($cfg['formato_ddjj'] === 'SIRCIP' && substr($o['original_f_emision'], 0, 7) !== substr($fecha, 0, 7)) {
                $aviso = "La factura original es de otro mes: SIRCIP solo admite anular la percepción dentro del mismo mes. Consulte con el contador.";
                $resultado['avisos'][] = "$nombre_jur: $aviso";
            }
            $resultado['percepciones'][] = perIibbArmar($cfg, $o['situacion'] ?: 'PADRON', $base, $alic, $importe,
                intval($o['tipo_registro_ddjj'] ?? 0) ?: null, $o['padron_iibb_id'] ?? null, $o['padron_crc'] ?? null,
                $o['leyenda'] ?? null, 'Reversión de ' . $asociado_id . ($aviso ? ' (no compensable)' : ''));
            continue;
        }

        // Situación del cliente
        $padron = null;
        if (!empty($cab['destino_bien_uso'])) {
            $situacion = 'BIEN_DE_USO';
        } else {
            if (!empty($cfg['formato_padron'])) $padron = perIibbPadron($conexion, $empresa_id, $jur_id, $cuit, $fecha);
            // Campo 7 de SIRCIP para la jurisdicción: 1 adherida con alta (alícuota del padrón), 2 adherida sin alta
            // (percepción por falta de alta), 3 adherida sin alta y sin sobre alícuota (no se percibe),
            // 4/5 jurisdicción no adherida (el padrón no aplica: se sigue por la condición del cliente).
            $digito = $padron && $padron['situacion_jurisdiccion'] !== null ? intval($padron['situacion_jurisdiccion']) : ($padron ? 1 : 0);
            if ($digito === 3) continue;
            if ($digito === 1) $situacion = 'PADRON';
            elseif ($digito === 2) $situacion = 'INSCRIPTO_OTRA_JURISDICCION';
            else { $padron = null; $situacion = perIibbSituacionCondicion($conexion, $entidad_id, $jur_prov, $fecha); }
        }
        $sit = $cfg['situaciones'][$situacion] ?? null;
        if (!$sit) continue;   // situación no alcanzada por el régimen

        if (intval($sit['requiere_territorio']) === 1) {
            $prov = perIibbProvinciaOperacion($conexion, $empresa_id, $cfg, $cab);
            if ($prov === null) {
                throw new Exception(($cfg['criterio_territorial'] ?? 'ENTREGA') === 'ESTABLECIMIENTO'
                    ? "Percepción IIBB $nombre_jur: la sucursal que emite no tiene localidad cargada."
                    : "Percepción IIBB $nombre_jur: elija la sucursal del cliente (con localidad) para saber dónde es la operación.");
            }
            if ($prov !== $jur_prov) continue;
        }

        $alic = $situacion === 'PADRON' ? floatval($padron['alicuota_percepcion']) : floatval($sit['alicuota']);
        $excl = perIibbExclusion($conexion, $empresa_id, $entidad_id, $jur_id, $fecha);
        if ($excl > 0) $alic = round($alic * (1 - min($excl, 100) / 100), 4);

        $importe = round($base * $alic / 100, 2);
        if ($base < floatval($cfg['minimo_imponible'] ?? 0)) $importe = 0.0;
        if ($importe > 0 && $importe < floatval($cfg['importe_minimo_percepcion'] ?? 0)) $importe = 0.0;
        if ($importe <= 0 && intval($sit['declarar_si_cero']) !== 1) continue;

        if ($importe > 0 && intval($cfg['cont_cuenta_id'] ?? 0) <= 0) {
            throw new Exception("Percepción IIBB $nombre_jur: falta la cuenta contable en Impuestos Adic. - Conf (página 73).");
        }

        $resultado['percepciones'][] = perIibbArmar($cfg, $situacion, $base, $alic, $importe,
            $sit['tipo_registro_ddjj'] !== null ? intval($sit['tipo_registro_ddjj']) : null,
            $padron['padron_iibb_id'] ?? null, $padron['crc'] ?? null, $sit['leyenda'] ?? null,
            perIibbSituaciones()[$situacion] . ($excl > 0 ? " (exclusión $excl%)" : ''));
    }

    foreach ($resultado['percepciones'] as $p) $resultado['total'] += $p['importe'];
    $resultado['total'] = round($resultado['total'], 2);
    return $resultado;
}

function perIibbArmar($cfg, $situacion, $base, $alic, $importe, $tipo_registro, $padron_id, $crc, $leyenda, $detalle) {
    $leyenda = $leyenda ?: ('Percepción IIBB ' . $cfg['jurisdiccion_nombre']);
    return [
        'empresa_impuesto_config_id' => intval($cfg['empresa_impuesto_config_id']),
        'jurisdiccion_id' => intval($cfg['jurisdiccion_id']),
        'jurisdiccion_nombre' => $cfg['jurisdiccion_nombre'],
        'tipo_calculo' => $situacion === 'PADRON' ? 'padron' : 'regla',
        'situacion' => $situacion,
        'base_imponible' => round($base, 2),
        'alicuota' => round($alic, 4),
        'importe' => round($importe, 2),
        'tipo_registro_ddjj' => $tipo_registro,
        'padron_iibb_id' => $padron_id ? intval($padron_id) : null,
        'padron_crc' => $crc !== null && $crc !== '' ? intval($crc) : null,
        'leyenda' => $leyenda,
        'detalle_origen' => mb_substr($detalle, 0, 255),
        'arca_tributo_id' => $cfg['arca_tributo_id'] !== null ? intval($cfg['arca_tributo_id']) : null,
        'cont_cuenta_id' => intval($cfg['cont_cuenta_id'] ?? 0),
        'numero_agente' => $cfg['numero_agente'],
    ];
}

/**
 * Reemplaza las percepciones IIBB del comprobante por las calculadas. Si alguna ya fue declarada
 * (liquidacion_comprobante_id), no se toca nada y se lanza Exception.
 */
function perIibbGuardar($conexion, $comprobante_id, $percepciones) {
    $comprobante_id = intval($comprobante_id);
    $tipo = PER_IIBB_IMPUESTO_TIPO_ID;
    if (perIibbFila($conexion,
        "SELECT 1 FROM gestion__comprobantes_impuestos
         WHERE comprobante_id = ? AND impuesto_tipo_id = ? AND tabla_estado_registro_id = 1
           AND liquidacion_comprobante_id IS NOT NULL LIMIT 1", 'ii', $comprobante_id, $tipo)) {
        throw new Exception('Las percepciones de IIBB de este comprobante ya fueron declaradas: no se pueden modificar.');
    }
    $stmt = mysqli_prepare($conexion, "DELETE FROM gestion__comprobantes_impuestos WHERE comprobante_id = ? AND impuesto_tipo_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $comprobante_id, $tipo);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $sql = "INSERT INTO gestion__comprobantes_impuestos
            (comprobante_id, empresa_impuesto_config_id, impuesto_tipo_id, jurisdiccion_id, tipo_origen, base_imponible,
             alicuota, importe, detalle_origen, situacion, tipo_registro_ddjj, padron_iibb_id, padron_crc, leyenda,
             tabla_estado_registro_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
    foreach ($percepciones as $p) {
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, 'iiiisdddssiiis',
            $comprobante_id, $p['empresa_impuesto_config_id'], $tipo, $p['jurisdiccion_id'], $p['tipo_calculo'],
            $p['base_imponible'], $p['alicuota'], $p['importe'], $p['detalle_origen'], $p['situacion'],
            $p['tipo_registro_ddjj'], $p['padron_iibb_id'], $p['padron_crc'], $p['leyenda']);
        if (!mysqli_stmt_execute($stmt)) throw new Exception('Percepciones IIBB: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
    }
}

/** Percepciones IIBB activas de un comprobante (con datos de la config para asiento, ARCA e impresión). */
function perIibbDelComprobante($conexion, $comprobante_id) {
    return perIibbFilas($conexion,
        "SELECT ci.*, cfg.cont_cuenta_id, cfg.arca_tributo_id, cfg.numero_agente, j.jurisdiccion_nombre
         FROM gestion__comprobantes_impuestos ci
         LEFT JOIN gestion__empresas_impuestos_config cfg ON cfg.empresa_impuesto_config_id = ci.empresa_impuesto_config_id
         LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = ci.jurisdiccion_id
         WHERE ci.comprobante_id = ? AND ci.impuesto_tipo_id = ? AND ci.tabla_estado_registro_id = 1
         ORDER BY ci.comprobante_impuesto_id",
        'ii', intval($comprobante_id), PER_IIBB_IMPUESTO_TIPO_ID);
}

/** Al eliminar/anular el comprobante: las percepciones pasan a Eliminado (6). Bloquea si ya se declararon. */
function perIibbAnularDelComprobante($conexion, $comprobante_id) {
    $comprobante_id = intval($comprobante_id);
    $tipo = PER_IIBB_IMPUESTO_TIPO_ID;
    if (perIibbFila($conexion,
        "SELECT 1 FROM gestion__comprobantes_impuestos
         WHERE comprobante_id = ? AND impuesto_tipo_id = ? AND tabla_estado_registro_id = 1
           AND liquidacion_comprobante_id IS NOT NULL LIMIT 1", 'ii', $comprobante_id, $tipo)) {
        throw new Exception('Las percepciones de IIBB de este comprobante ya fueron declaradas: anúlelo con nota de crédito.');
    }
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__comprobantes_impuestos SET tabla_estado_registro_id = 6 WHERE comprobante_id = ? AND impuesto_tipo_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $comprobante_id, $tipo);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
