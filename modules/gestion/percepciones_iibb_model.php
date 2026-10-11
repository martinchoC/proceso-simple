<?php
/**
 * Percepciones IIBB practicadas (página 116): listado por jurisdicción y período, exportación de la DDJJ
 * (SIRCAR Diseño N° 1 / SIRCIP) y liquidación (I_LIQ). Diseño: docs/gestion/09_percepciones_iibb.md §8.
 */
require_once __DIR__ . '/../../core/empresa_config.php';
require_once __DIR__ . '/percepciones_iibb_core_model.php';
require_once __DIR__ . '/obligaciones_rendiciones_model.php';   // registrarObligacionImpositiva (hace $conexion = $conn: incluir a nivel archivo)

// Estados del comprobante que cuentan para la DDJJ (confirmado y derivados de saldo / facturación)
const PER_ESTADOS_DECLARABLES = [5, 12, 13, 15, 39, 40];

/** Jurisdicciones en las que la empresa tiene (o tuvo) config de percepción IIBB. */
function perJurisdicciones($conexion, $empresa_id) {
    return perIibbFilas($conexion,
        "SELECT DISTINCT j.jurisdiccion_id, j.jurisdiccion_nombre
         FROM gestion__empresas_impuestos_config cfg
         JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = cfg.jurisdiccion_id
         WHERE cfg.empresa_id = ? AND cfg.impuesto_tipo_id = ?
         ORDER BY j.jurisdiccion_nombre",
        'ii', intval($empresa_id), PER_IIBB_IMPUESTO_TIPO_ID);
}

/** Config de la jurisdicción vigente el primer día del período (cualquier estado: puede estar cerrada). */
function perConfigPeriodo($conexion, $empresa_id, $jurisdiccion_id, $periodo) {
    $desde = $periodo . '-01';
    $hasta = date('Y-m-t', strtotime($desde));
    return perIibbFila($conexion,
        "SELECT cfg.*, j.jurisdiccion_nombre FROM gestion__empresas_impuestos_config cfg
         JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = cfg.jurisdiccion_id
         WHERE cfg.empresa_id = ? AND cfg.impuesto_tipo_id = ? AND cfg.jurisdiccion_id = ?
           AND cfg.f_desde <= ? AND (cfg.f_hasta IS NULL OR cfg.f_hasta >= ?)
         ORDER BY cfg.tabla_estado_registro_id = 1 DESC, cfg.f_desde DESC LIMIT 1",
        'iiiss', intval($empresa_id), PER_IIBB_IMPUESTO_TIPO_ID, intval($jurisdiccion_id), $hasta, $desde);
}

function perValidarPeriodo($periodo) {
    if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string)$periodo)) throw new Exception('Período inválido (AAAA-MM).');
    return $periodo;
}

/**
 * Percepciones del período (comprobantes declarables). $solo_pendientes = sin liquidación.
 * Cada fila trae el signo del comprobante (NC = -1) y los datos para la DDJJ.
 */
function perListado($conexion, $empresa_id, $jurisdiccion_id, $periodo, $solo_pendientes = false) {
    $periodo = perValidarPeriodo($periodo);
    $desde = $periodo . '-01';
    $hasta = date('Y-m-t', strtotime($desde));
    $estados = implode(',', array_map('intval', PER_ESTADOS_DECLARABLES));
    $sql = "SELECT ci.comprobante_impuesto_id, ci.comprobante_id, ci.base_imponible, ci.alicuota, ci.importe, ci.situacion,
                   ci.tipo_registro_ddjj, ci.padron_crc, ci.liquidacion_comprobante_id,
                   c.f_emision, c.comprobante_tipo_id, c.comprobante_pv, c.comprobante_nro, c.entidad_id,
                   IF(ct.signo = '-', -1, 1) AS signo, ct.letra, ct.comprobante_tipo,
                   e.entidad_nombre, e.cuit,
                   liq.comprobante_nro AS liquidacion_nro,
                   vf.comprobante_asociado_id
            FROM gestion__comprobantes_impuestos ci
            JOIN gestion__comprobantes c ON c.comprobante_id = ci.comprobante_id
            JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
            LEFT JOIN gestion__entidades e ON e.entidad_id = c.entidad_id
            LEFT JOIN gestion__comprobantes liq ON liq.comprobante_id = ci.liquidacion_comprobante_id
            LEFT JOIN gestion__ventas_facturas vf ON vf.comprobante_id = c.comprobante_id AND vf.empresa_id = c.empresa_id
            WHERE c.empresa_id = ? AND ci.impuesto_tipo_id = ? AND ci.jurisdiccion_id = ?
              AND ci.tabla_estado_registro_id = 1 AND c.tabla_estado_registro_id IN ($estados)
              AND c.f_emision BETWEEN ? AND ?" . ($solo_pendientes ? " AND ci.liquidacion_comprobante_id IS NULL" : "") . "
            ORDER BY c.f_emision, c.comprobante_id";
    $filas = perIibbFilas($conexion, $sql, 'iiiss', intval($empresa_id), PER_IIBB_IMPUESTO_TIPO_ID, intval($jurisdiccion_id), $desde, $hasta);
    foreach ($filas as &$f) {
        $f['numero'] = emp_numero_comprobante($conexion, $empresa_id, $f['comprobante_pv'], $f['comprobante_nro']);
        $f['importe_firmado'] = round(floatval($f['importe']) * intval($f['signo']), 2);
    }
    unset($f);
    return $filas;
}

function perResumen($filas) {
    $r = ['cantidad' => count($filas), 'base' => 0.0, 'percibido' => 0.0, 'pendiente' => 0.0, 'por_situacion' => []];
    foreach ($filas as $f) {
        $s = intval($f['signo']);
        $r['base'] += floatval($f['base_imponible']) * $s;
        $r['percibido'] += $f['importe_firmado'];
        if ($f['liquidacion_comprobante_id'] === null) $r['pendiente'] += $f['importe_firmado'];
        $k = $f['situacion'] ?: '-';
        $r['por_situacion'][$k] = round(($r['por_situacion'][$k] ?? 0) + $f['importe_firmado'], 2);
    }
    foreach (['base', 'percibido', 'pendiente'] as $k) $r[$k] = round($r[$k], 2);
    return $r;
}

/** Código de tipo de comprobante de las tablas SIRCAR / SIRCIP. */
function perCodigoTipoDdjj($conexion, $empresa_id, $comprobante_tipo_id) {
    $t = perIibbTipoComprobante($conexion, $empresa_id, intval($comprobante_tipo_id));
    $clase = $t['clase'] ?? 'FA';
    return $clase === 'NC' ? 102 : ($clase === 'ND' ? 2 : 1);
}

function perNum($v) { return number_format(abs(floatval($v)), 2, '.', ''); }

/**
 * Arma el archivo de la DDJJ. Devuelve ['nombre' => ..., 'mime' => ..., 'contenido' => binario].
 */
function perExportar($conexion, $empresa_id, $jurisdiccion_id, $periodo) {
    $periodo = perValidarPeriodo($periodo);
    $cfg = perConfigPeriodo($conexion, $empresa_id, $jurisdiccion_id, $periodo);
    if (!$cfg) throw new Exception('No hay configuración de percepción para esa jurisdicción en el período.');
    $formato = $cfg['formato_ddjj'] ?? '';
    if ($formato === '') throw new Exception('La configuración no tiene formato de DDJJ (Impuestos Adic. - Conf → Agente de Percepción).');
    $cod_jur = $cfg['codigo_jurisdiccion_cm'] ?? '';
    if (strlen((string)$cod_jur) !== 3) throw new Exception('Falta el código de jurisdicción CM en la configuración.');

    $filas = perListado($conexion, $empresa_id, $jurisdiccion_id, $periodo);
    if (!$filas) throw new Exception('No hay percepciones en el período.');

    $lineas = [];
    $renglon = 0;
    foreach ($filas as $f) {
        $cuit = preg_replace('/\D/', '', (string)$f['cuit']);
        if (strlen($cuit) !== 11) throw new Exception('El comprobante ' . $f['numero'] . ' es de un cliente sin CUIT válido.');
        $tipo = perCodigoTipoDdjj($conexion, $empresa_id, $f['comprobante_tipo_id']);
        $letra = strtoupper(trim((string)$f['letra'])) ?: 'Z';
        $pv = emp_pv_numero($conexion, $empresa_id, $f['comprobante_pv']);
        $nro = intval($f['comprobante_nro']);
        $fecha = date('d/m/Y', strtotime($f['f_emision']));

        if ($formato === 'SIRCAR_D1') {
            $regimen = (string)($cfg['codigo_regimen_ddjj'] ?? '');
            if ($regimen === '') throw new Exception('Falta el código de régimen de la DDJJ (SIRCAR campo 10) en la configuración.');
            $renglon++;
            $lineas[] = implode(',', [
                $renglon, $tipo, $letra, sprintf('%04d%08d', $pv, $nro), $cuit, $fecha,
                perNum($f['base_imponible']), perNum($f['alicuota']), perNum($f['importe']),
                str_pad($regimen, 3, '0', STR_PAD_LEFT), $cod_jur,
            ]);
        } elseif ($formato === 'SIRCIP') {
            $crc = $f['padron_crc'] !== null ? (string)intval($f['padron_crc']) : '';
            $original = '';
            if ($tipo === 102 && intval($f['comprobante_asociado_id']) > 0) {
                $o = perIibbFila($conexion, "SELECT comprobante_tipo_id, comprobante_pv, comprobante_nro FROM gestion__comprobantes
                                             WHERE comprobante_id = ? AND empresa_id = ?", 'ii', intval($f['comprobante_asociado_id']), intval($empresa_id));
                if ($o) {
                    $ot = perIibbTipoComprobante($conexion, $empresa_id, intval($o['comprobante_tipo_id']));
                    $original = sprintf('%03d', perCodigoTipoDdjj($conexion, $empresa_id, $o['comprobante_tipo_id']))
                        . (strtoupper(trim((string)($ot['letra'] ?? ''))) ?: 'Z')
                        . sprintf('%05d%08d', emp_pv_numero($conexion, $empresa_id, $o['comprobante_pv']), intval($o['comprobante_nro']));
                }
            }
            $lineas[] = implode(',', [
                $cuit, $crc, $fecha, ($cfg['codigo_regimen_ddjj'] ?? '') !== '' ? intval($cfg['codigo_regimen_ddjj']) : 1,
                intval($f['tipo_registro_ddjj'] ?? 0) ?: 1, 0, $cod_jur, $tipo, $letra, $pv, $nro,
                perNum($f['base_imponible']), perNum($f['alicuota']), perNum($f['importe']),
                $original, '', 'A',
            ]);
        } else {
            throw new Exception("Formato de DDJJ desconocido: $formato");
        }
    }

    $cuit_emp = preg_replace('/\D/', '', emp_cuit($conexion, $empresa_id));
    $base = 'percepciones_iibb_' . $cod_jur . '_' . str_replace('-', '', $periodo) . ($cuit_emp ? '_' . $cuit_emp : '');
    $txt = implode("\r\n", $lineas) . "\r\n";
    if ($formato === 'SIRCIP') {
        // SIRCIP exige un ZIP con un único TXT
        if (!class_exists('ZipArchive')) throw new Exception('El servidor no tiene la extensión zip de PHP: no se puede generar el archivo SIRCIP.');
        $tmp = tempnam(sys_get_temp_dir(), 'sircip');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) throw new Exception('No se pudo crear el ZIP.');
        $zip->addFromString($base . '.txt', $txt);
        $zip->close();
        $contenido = file_get_contents($tmp);
        @unlink($tmp);
        return ['nombre' => $base . '.zip', 'mime' => 'application/zip', 'contenido' => $contenido, 'registros' => count($lineas)];
    }
    return ['nombre' => $base . '.txt', 'mime' => 'text/plain', 'contenido' => $txt, 'registros' => count($lineas)];
}

/** Sucursales activas de la empresa (la I_LIQ lleva sucursal). */
function perSucursales($conexion, $empresa_id) {
    return perIibbFilas($conexion, "SELECT sucursal_id, sucursal_nombre FROM gestion__sucursales
                                    WHERE empresa_id = ? AND tabla_estado_registro_id = 1 ORDER BY sucursal_id", 'i', intval($empresa_id));
}

/** Organismos recaudadores posibles para la I_LIQ: entidades proveedoras activas de la empresa. */
function perEntidadesOrganismo($conexion, $empresa_id) {
    return perIibbFilas($conexion,
        "SELECT entidad_id, entidad_nombre FROM gestion__entidades
         WHERE empresa_id = ? AND es_proveedor = 1 AND tabla_estado_registro_id = 1
         ORDER BY entidad_nombre", 'i', intval($empresa_id));
}

/**
 * Genera la I_LIQ por lo pendiente del período y marca esas percepciones como declaradas, en una sola
 * transacción. Asiento: Debe y Haber la cuenta "a depositar" de la config (la percepción ya está en el
 * pasivo desde la factura); el pago (I_PAG, página 109) la cancela.
 */
function perLiquidar($conexion, $empresa_id, $usuario_id, $data) {
    $empresa_id = intval($empresa_id);
    $jurisdiccion_id = intval($data['jurisdiccion_id'] ?? 0);
    $periodo = perValidarPeriodo($data['periodo'] ?? '');
    $entidad_id = intval($data['entidad_id'] ?? 0);
    $sucursal_id = intval($data['sucursal_id'] ?? 0);
    $f_vto = trim((string)($data['f_vto'] ?? '')) ?: date('Y-m-d', strtotime('+10 days'));
    error_log("=== INICIO perLiquidar empresa $empresa_id jur $jurisdiccion_id período $periodo ===");

    if ($entidad_id <= 0 || !perIibbFila($conexion, "SELECT 1 FROM gestion__entidades WHERE entidad_id = ? AND empresa_id = ?", 'ii', $entidad_id, $empresa_id)) {
        throw new Exception('Elija el organismo recaudador (entidad proveedora de la empresa).');
    }
    if ($sucursal_id <= 0 || !perIibbFila($conexion, "SELECT 1 FROM gestion__sucursales WHERE sucursal_id = ? AND empresa_id = ?", 'ii', $sucursal_id, $empresa_id)) {
        throw new Exception('Elija la sucursal de la liquidación.');
    }
    $cfg = perConfigPeriodo($conexion, $empresa_id, $jurisdiccion_id, $periodo);
    if (!$cfg) throw new Exception('No hay configuración de percepción para esa jurisdicción en el período.');
    $cuenta = intval($cfg['cont_cuenta_id'] ?? 0);
    if ($cuenta <= 0) throw new Exception('La configuración no tiene cuenta contable.');

    mysqli_begin_transaction($conexion);
    try {
        $filas = perListado($conexion, $empresa_id, $jurisdiccion_id, $periodo, true);
        if (!$filas) throw new Exception('No hay percepciones pendientes de liquidar en el período.');
        $ids = array_map(function ($f) { return intval($f['comprobante_impuesto_id']); }, $filas);
        sort($ids);
        // Bloqueo de las filas a declarar (orden por id) y re-chequeo de que sigan pendientes
        $in = implode(',', array_fill(0, count($ids), '?'));
        $bloq = perIibbFilas($conexion, "SELECT comprobante_impuesto_id FROM gestion__comprobantes_impuestos
                                         WHERE comprobante_impuesto_id IN ($in) AND liquidacion_comprobante_id IS NULL
                                         ORDER BY comprobante_impuesto_id FOR UPDATE", str_repeat('i', count($ids)), ...$ids);
        if (count($bloq) !== count($ids)) throw new Exception('Otra liquidación tomó parte de estas percepciones. Actualice la pantalla.');

        $total = round(array_sum(array_column($filas, 'importe_firmado')), 2);
        if ($total <= 0) throw new Exception('El neto a liquidar del período no es positivo ($' . number_format($total, 2, ',', '.') . '): revise las notas de crédito.');

        $res = registrarObligacionImpositiva($conexion, [
            'en_transaccion' => true,
            'entidad_id' => $entidad_id,
            'sucursal_id' => $sucursal_id,
            'f_emision' => date('Y-m-d'),
            'f_vto' => $f_vto,
            'periodo_fiscal' => $periodo,
            'descripcion' => 'Percepciones IIBB ' . $cfg['jurisdiccion_nombre'] . ' ' . $periodo,
            'nro_ddjj' => trim((string)($data['nro_ddjj'] ?? '')),
            'importe_total' => $total,
            'cont_cuenta_id' => $cuenta,
            'cuenta_debe_id' => $cuenta,
            'impuesto_tipo_id' => PER_IIBB_IMPUESTO_TIPO_ID,
        ], $empresa_id, $usuario_id);

        $liq_id = intval($res['comprobante_id']);
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__comprobantes_impuestos SET liquidacion_comprobante_id = ?
                                           WHERE comprobante_impuesto_id IN ($in) AND liquidacion_comprobante_id IS NULL");
        $params = array_merge([$liq_id], $ids);
        mysqli_stmt_bind_param($stmt, 'i' . str_repeat('i', count($ids)), ...$params);
        mysqli_stmt_execute($stmt);
        $marcadas = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        if ($marcadas !== count($ids)) throw new Exception('No se pudieron marcar todas las percepciones como declaradas.');

        mysqli_commit($conexion);
        return ['comprobante_id' => $liq_id, 'numero' => $res['numero'], 'total' => $total, 'registros' => count($ids),
                'message' => 'Liquidación ' . $res['numero'] . ' por $' . number_format($total, 2, ',', '.') . ' (' . count($ids) . ' percepciones). Se paga desde Obligaciones y Rendiciones.'];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log('ERROR perLiquidar: ' . $e->getMessage());
        throw $e;
    }
}
