<?php
/**
 * Percepciones practicadas (empresa agente de percepción): IIBB por jurisdicción (impuesto 6) y, desde la etapa 7 de
 * _local/docs/gestion/10_retenciones_percepciones_relevamiento.md, IVA (impuesto 22, script 20261016_percepcion_iva_01.sql).
 * Diseño IIBB: _local/docs/gestion/09_percepciones_iibb.md — estructura: _local/sql/mantenimiento/20261010_percepciones_iibb_01_estructura.sql
 * Las dos usan la misma configuración (gestion__empresas_impuestos_config + _situaciones + _subgrupos) y se graban en
 * gestion__comprobantes_impuestos; ventas_facturas llama a perCalcularPracticadas() / perIibbGuardar() /
 * perIibbDelComprobante(), que cubren los dos impuestos (PER_TIPOS_PRACTICADOS).
 *
 * Servicio único de cálculo: lo usan ventas_facturas_model.php (al guardar el borrador, al editar y al confirmar)
 * y la vista previa de la pantalla. Nada propio de una provincia está acá: alícuotas por situación, leyendas,
 * territorio y formatos salen de gestion__empresas_impuestos_config (impuesto_tipo_id = 6) y de
 * gestion__empresas_impuestos_config_situaciones.
 *
 * Todas las funciones corren dentro de la transacción del llamador (no abren ni confirman la suya).
 */

if (!defined('PER_IIBB_IMPUESTO_TIPO_ID')) define('PER_IIBB_IMPUESTO_TIPO_ID', 6);   // "Percepción IIBB Efectuadas" (catálogo global)
if (!defined('PER_IVA_IMPUESTO_TIPO_ID')) define('PER_IVA_IMPUESTO_TIPO_ID', 22);    // "Percepción IVA Efectuada" (catálogo global)
if (!defined('PER_TIPOS_PRACTICADOS')) define('PER_TIPOS_PRACTICADOS', [6, 22]);

/** Situaciones del cliente frente a la percepción de IVA: su condición frente al IVA. */
function perIvaSituaciones() {
    return [
        'RESPONSABLE_INSCRIPTO' => 'Responsable inscripto',
        'NO_CATEGORIZADO'       => 'No categorizado',
        'MONOTRIBUTO'           => 'Monotributista',
        'EXENTO'                => 'Exento en IVA',
        'CONSUMIDOR_FINAL'      => 'Consumidor final identificado',
    ];
}

/** Situaciones posibles según el impuesto percibido (para la solapa Agente de Percepción). */
function perSituacionesImpuesto($impuesto_tipo_id) {
    return intval($impuesto_tipo_id) === PER_IVA_IMPUESTO_TIPO_ID ? perIvaSituaciones() : perIibbSituaciones();
}

/** Situaciones posibles del cliente frente al impuesto de una jurisdicción. */
function perIibbSituaciones() {
    return [
        'PADRON'                      => 'Incluido en el padrón',
        'INSCRIPTO_JURISDICCION'      => 'Inscripto directo (local) en la jurisdicción',
        'CONVENIO_JURISDICCION'       => 'Convenio Multilateral con alta en la jurisdicción',
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

/**
 * Fila del padrón vigente a $fecha para el CUIT, cualquiera sea su fuente (archivo, servicio web o carga manual).
 * Si hay varias, gana la carga manual, después el servicio web, después el archivo (docs §12.5).
 */
function perIibbPadron($conexion, $empresa_id, $jurisdiccion_id, $cuit, $fecha) {
    return perIibbFila($conexion,
        "SELECT * FROM gestion__padrones_iibb
         WHERE empresa_id = ? AND jurisdiccion_id = ? AND cuit = ? AND tabla_estado_registro_id = 1
           AND f_desde <= ? AND (f_hasta IS NULL OR f_hasta >= ?)
         ORDER BY FIELD(fuente, 'MANUAL', 'WS', 'ARCHIVO'), f_desde DESC
         LIMIT 1",
        'iisss', $empresa_id, $jurisdiccion_id, $cuit, $fecha, $fecha);
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
    if ($codigo === 'SIMPLIFICADO') return 'SIMPLIFICADO_JURISDICCION';
    return $codigo === 'CM' ? 'CONVENIO_JURISDICCION' : 'INSCRIPTO_JURISDICCION';
}

/** Porcentaje de exclusión vigente (certificado de no percepción) del cliente en la jurisdicción. */
function perIibbExclusion($conexion, $empresa_id, $entidad_id, $jurisdiccion_id, $fecha) {
    $row = perIibbFila($conexion,
        "SELECT MAX(porcentaje_exclusion) AS pct FROM gestion__entidades_iibb_exclusiones
         WHERE empresa_id = ? AND entidad_id = ? AND jurisdiccion_id = ? AND sentido = 'PERCEPCION' AND tabla_estado_registro_id = 1
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
            // El driver de cada fuente ya tradujo sus códigos a la situación normalizada de la fila (docs §12.3)
            $padron = perIibbPadron($conexion, $empresa_id, $jur_id, $cuit, $fecha);
            switch ($padron['situacion'] ?? null) {
                case 'NO_PERCIBIR':
                    continue 2;
                case 'PADRON':
                case 'INSCRIPTO_OTRA_JURISDICCION':
                    $situacion = $padron['situacion'];
                    break;
                default:
                    $padron = null;
                    $situacion = perIibbSituacionCondicion($conexion, $entidad_id, $jur_prov, $fecha);
            }
        }
        $sit = $cfg['situaciones'][$situacion] ?? null;
        // Config sin fila propia para el CM (antes de 20261027_percepciones_iibb_05): vale la del directo
        if (!$sit && $situacion === 'CONVENIO_JURISDICCION') $sit = $cfg['situaciones']['INSCRIPTO_JURISDICCION'] ?? null;
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
    $es_iva = intval($cfg['impuesto_tipo_id'] ?? PER_IIBB_IMPUESTO_TIPO_ID) === PER_IVA_IMPUESTO_TIPO_ID;
    $leyenda = $leyenda ?: ($es_iva ? 'Percepción IVA' : ('Percepción IIBB ' . $cfg['jurisdiccion_nombre']));
    return [
        'impuesto_tipo_id' => $es_iva ? PER_IVA_IMPUESTO_TIPO_ID : PER_IIBB_IMPUESTO_TIPO_ID,
        'empresa_impuesto_config_id' => intval($cfg['empresa_impuesto_config_id']),
        'jurisdiccion_id' => intval($cfg['jurisdiccion_id'] ?? 0),
        'jurisdiccion_nombre' => $cfg['jurisdiccion_nombre'] ?? null,
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
 * Reemplaza las percepciones practicadas (IIBB e IVA) del comprobante por las calculadas. Si alguna ya fue declarada
 * (liquidacion_comprobante_id), no se toca nada y se lanza Exception.
 */
function perIibbGuardar($conexion, $comprobante_id, $percepciones) {
    $comprobante_id = intval($comprobante_id);
    $in = implode(',', array_map('intval', PER_TIPOS_PRACTICADOS));
    if (perIibbFila($conexion,
        "SELECT 1 FROM gestion__comprobantes_impuestos
         WHERE comprobante_id = ? AND impuesto_tipo_id IN ($in) AND tabla_estado_registro_id = 1
           AND liquidacion_comprobante_id IS NOT NULL LIMIT 1", 'i', $comprobante_id)) {
        throw new Exception('Las percepciones de este comprobante ya fueron declaradas: no se pueden modificar.');
    }
    $stmt = mysqli_prepare($conexion, "DELETE FROM gestion__comprobantes_impuestos WHERE comprobante_id = ? AND impuesto_tipo_id IN ($in)");
    mysqli_stmt_bind_param($stmt, 'i', $comprobante_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);

    $sql = "INSERT INTO gestion__comprobantes_impuestos
            (comprobante_id, empresa_impuesto_config_id, impuesto_tipo_id, jurisdiccion_id, tipo_origen, base_imponible,
             alicuota, importe, detalle_origen, situacion, tipo_registro_ddjj, padron_iibb_id, padron_crc, leyenda,
             tabla_estado_registro_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
    foreach ($percepciones as $p) {
        $tipo = intval($p['impuesto_tipo_id'] ?? PER_IIBB_IMPUESTO_TIPO_ID);
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, 'iiiisdddssiiis',
            $comprobante_id, $p['empresa_impuesto_config_id'], $tipo, $p['jurisdiccion_id'], $p['tipo_calculo'],
            $p['base_imponible'], $p['alicuota'], $p['importe'], $p['detalle_origen'], $p['situacion'],
            $p['tipo_registro_ddjj'], $p['padron_iibb_id'], $p['padron_crc'], $p['leyenda']);
        if (!mysqli_stmt_execute($stmt)) throw new Exception('Percepciones: ' . mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
    }
}

/**
 * Percepciones practicadas activas de un comprobante, IIBB e IVA (con datos de la config para asiento, ARCA e
 * impresión). 'descripcion' = "Percepción IIBB <provincia>" o "Percepción IVA".
 */
function perIibbDelComprobante($conexion, $comprobante_id) {
    $in = implode(',', array_map('intval', PER_TIPOS_PRACTICADOS));
    $filas = perIibbFilas($conexion,
        "SELECT ci.*, cfg.cont_cuenta_id, cfg.arca_tributo_id, cfg.numero_agente, j.jurisdiccion_nombre
         FROM gestion__comprobantes_impuestos ci
         LEFT JOIN gestion__empresas_impuestos_config cfg ON cfg.empresa_impuesto_config_id = ci.empresa_impuesto_config_id
         LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = ci.jurisdiccion_id
         WHERE ci.comprobante_id = ? AND ci.impuesto_tipo_id IN ($in) AND ci.tabla_estado_registro_id = 1
         ORDER BY ci.impuesto_tipo_id, ci.comprobante_impuesto_id",
        'i', intval($comprobante_id));
    foreach ($filas as &$f) {
        $f['descripcion'] = intval($f['impuesto_tipo_id']) === PER_IVA_IMPUESTO_TIPO_ID ? 'Percepción IVA' : 'Percepción IIBB ' . $f['jurisdiccion_nombre'];
    }
    unset($f);
    return $filas;
}

/** Al eliminar/anular el comprobante: las percepciones (IIBB e IVA) pasan a Eliminado (6). Bloquea si ya se declararon. */
function perIibbAnularDelComprobante($conexion, $comprobante_id) {
    $comprobante_id = intval($comprobante_id);
    $in = implode(',', array_map('intval', PER_TIPOS_PRACTICADOS));
    if (perIibbFila($conexion,
        "SELECT 1 FROM gestion__comprobantes_impuestos
         WHERE comprobante_id = ? AND impuesto_tipo_id IN ($in) AND tabla_estado_registro_id = 1
           AND liquidacion_comprobante_id IS NOT NULL LIMIT 1", 'i', $comprobante_id)) {
        throw new Exception('Las percepciones de este comprobante ya fueron declaradas: anúlelo con nota de crédito.');
    }
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__comprobantes_impuestos SET tabla_estado_registro_id = 6 WHERE comprobante_id = ? AND impuesto_tipo_id IN ($in)");
    mysqli_stmt_bind_param($stmt, 'i', $comprobante_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

// ------------------------------------------------------------------ percepción de IVA (impuesto 22)
/** Configs de percepción de IVA vigentes para el subgrupo del comprobante, con sus situaciones. */
function perIvaConfigsVigentes($conexion, $empresa_id, $comprobante_subgrupo_id, $fecha) {
    $tipo = PER_IVA_IMPUESTO_TIPO_ID;
    $configs = perIibbFilas($conexion,
        "SELECT cfg.*, j.jurisdiccion_nombre
         FROM gestion__empresas_impuestos_config cfg
         JOIN gestion__empresas_impuestos_config_subgrupos sub
              ON sub.empresa_impuesto_config_id = cfg.empresa_impuesto_config_id
             AND sub.comprobante_subgrupo_id = ? AND sub.tabla_estado_registro_id = 1
         LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = cfg.jurisdiccion_id
         WHERE cfg.empresa_id = ? AND cfg.impuesto_tipo_id = ? AND cfg.tabla_estado_registro_id = 1
           AND cfg.f_desde <= ? AND (cfg.f_hasta IS NULL OR cfg.f_hasta >= ?)
         ORDER BY cfg.prioridad, cfg.empresa_impuesto_config_id",
        'iiiss', $comprobante_subgrupo_id, $empresa_id, $tipo, $fecha, $fecha);
    foreach ($configs as &$cfg) {
        $cfg['situaciones'] = [];
        foreach (perIibbFilas($conexion,
            "SELECT * FROM gestion__empresas_impuestos_config_situaciones WHERE empresa_impuesto_config_id = ? AND tabla_estado_registro_id = 1",
            'i', intval($cfg['empresa_impuesto_config_id'])) as $s) {
            $cfg['situaciones'][$s['situacion']] = $s;
        }
    }
    unset($cfg);
    return $configs;
}

/** Situación del cliente frente al IVA según su condición fiscal vigente (null si no tiene). */
function perIvaSituacionCliente($conexion, $entidad_id, $fecha) {
    $row = perIibbFila($conexion,
        "SELECT cf.condicion_fiscal_codigo FROM gestion__entidades_condiciones_fiscales ecf
         JOIN gestion__condiciones_fiscales cf ON cf.condicion_fiscal_id = ecf.condicion_fiscal_id
         WHERE ecf.entidad_id = ? AND ecf.tabla_estado_registro_id = 1 AND ecf.f_desde <= ? AND (ecf.f_hasta IS NULL OR ecf.f_hasta >= ?)
         ORDER BY ecf.f_desde DESC LIMIT 1", 'iss', intval($entidad_id), $fecha, $fecha);
    if (!$row) return null;
    $mapa = ['RI' => 'RESPONSABLE_INSCRIPTO', 'NC' => 'NO_CATEGORIZADO', 'MT' => 'MONOTRIBUTO', 'EX' => 'EXENTO', 'CF' => 'CONSUMIDOR_FINAL'];
    return $mapa[$row['condicion_fiscal_codigo']] ?? null;   // EXT (exterior): no alcanzado
}

/** % del certificado de exclusión de percepción de IVA vigente del cliente (sentido PERCEPCION, impuesto 22). */
function perIvaExclusion($conexion, $empresa_id, $entidad_id, $fecha) {
    $row = perIibbFila($conexion,
        "SELECT MAX(porcentaje_exclusion) AS pct FROM gestion__entidades_iibb_exclusiones
         WHERE empresa_id = ? AND entidad_id = ? AND sentido = 'PERCEPCION' AND impuesto_tipo_id = ? AND tabla_estado_registro_id = 1
           AND f_desde <= ? AND (f_hasta IS NULL OR f_hasta >= ?)",
        'iiiss', intval($empresa_id), intval($entidad_id), PER_IVA_IMPUESTO_TIPO_ID, $fecha, $fecha);
    return $row && $row['pct'] !== null ? floatval($row['pct']) : 0.0;
}

/**
 * Percepción de IVA de un comprobante de venta. Misma entrada y salida que perIibbCalcular(). Sin valores
 * normativos en el código: alícuota por situación del cliente, base, mínimos y leyenda salen de la config.
 * Solo comprobantes fiscales con cliente identificado; la situación es la condición frente al IVA del cliente
 * (situación no configurada = no se percibe); la NC revierte lo percibido en la factura asociada.
 */
function perIvaCalcular($conexion, $empresa_id, $cab, $detalles) {
    $resultado = ['percepciones' => [], 'total' => 0.0, 'avisos' => []];
    $empresa_id = intval($empresa_id);
    $fecha = substr((string)($cab['f_emision'] ?? date('Y-m-d')), 0, 10);
    $entidad_id = intval($cab['entidad_id'] ?? 0);
    $tipo = perIibbTipoComprobante($conexion, $empresa_id, intval($cab['comprobante_tipo_id'] ?? 0));
    if (!$tipo || intval($tipo['comprobante_fiscal_id']) <= 0 || $entidad_id <= 0) return $resultado;
    $configs = perIvaConfigsVigentes($conexion, $empresa_id, intval($tipo['comprobante_subgrupo_id']), $fecha);
    if (!$configs) return $resultado;

    $original = null;
    $asociado_id = intval($cab['comprobante_asociado_id'] ?? 0);
    if ($tipo['clase'] === 'NC' && $asociado_id > 0) {
        $original = perIibbFila($conexion,
            "SELECT ci.* FROM gestion__comprobantes_impuestos ci JOIN gestion__comprobantes c ON c.comprobante_id = ci.comprobante_id
             WHERE ci.comprobante_id = ? AND c.empresa_id = ? AND ci.impuesto_tipo_id = ? AND ci.tabla_estado_registro_id = 1 LIMIT 1",
            'iii', $asociado_id, $empresa_id, PER_IVA_IMPUESTO_TIPO_ID);
    }
    $situacion = perIvaSituacionCliente($conexion, $entidad_id, $fecha);

    foreach ($configs as $cfg) {
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

        if ($tipo['clase'] === 'NC' && $original) {
            $alic = floatval($original['alicuota']);
            $importe = min(round($base * $alic / 100, 2), round(floatval($original['importe']), 2));
            if ($importe <= 0) continue;
            $resultado['percepciones'][] = perIibbArmar($cfg, $original['situacion'] ?: 'RESPONSABLE_INSCRIPTO', $base, $alic, $importe,
                null, null, null, $original['leyenda'] ?? null, 'Reversión de ' . $asociado_id);
            break;
        }
        if ($situacion === null) {
            $resultado['avisos'][] = 'Percepción de IVA: el cliente no tiene condición frente al IVA vigente (o es del exterior): no se percibe.';
            break;
        }
        $sit = $cfg['situaciones'][$situacion] ?? null;
        if (!$sit) break;   // situación no alcanzada por el régimen configurado

        $alic = floatval($sit['alicuota']);
        if ($alic <= 0) {
            $resultado['avisos'][] = 'Percepción de IVA: la situación "' . perIvaSituaciones()[$situacion] . '" no tiene alícuota cargada (a confirmar): no se percibe.';
            break;
        }
        $excl = perIvaExclusion($conexion, $empresa_id, $entidad_id, $fecha);
        if ($excl > 0) $alic = round($alic * (1 - min($excl, 100) / 100), 4);
        $importe = round($base * $alic / 100, 2);
        if ($base < floatval($cfg['minimo_imponible'] ?? 0)) $importe = 0.0;
        if ($importe > 0 && $importe < floatval($cfg['importe_minimo_percepcion'] ?? 0)) $importe = 0.0;
        if ($importe <= 0) break;
        if (intval($cfg['cont_cuenta_id'] ?? 0) <= 0) {
            throw new Exception('Percepción de IVA: falta la cuenta contable en Impuestos Adic. - Conf (página 73).');
        }
        $resultado['percepciones'][] = perIibbArmar($cfg, $situacion, $base, $alic, $importe, null, null, null, $sit['leyenda'] ?? null,
            perIvaSituaciones()[$situacion] . ($excl > 0 ? " (exclusión $excl%)" : ''));
        break;   // una sola percepción de IVA por comprobante: gana la config de mayor prioridad
    }
    foreach ($resultado['percepciones'] as $p) $resultado['total'] += $p['importe'];
    $resultado['total'] = round($resultado['total'], 2);
    return $resultado;
}

/** Percepciones practicadas de un comprobante: IIBB (por jurisdicción) + IVA, con avisos de las dos. */
function perCalcularPracticadas($conexion, $empresa_id, $cab, $detalles) {
    $iibb = perIibbCalcular($conexion, $empresa_id, $cab, $detalles);
    $iva = perIvaCalcular($conexion, $empresa_id, $cab, $detalles);
    return ['percepciones' => array_merge($iibb['percepciones'], $iva['percepciones']),
            'total' => round($iibb['total'] + $iva['total'], 2),
            'avisos' => array_merge($iibb['avisos'], $iva['avisos'])];
}
