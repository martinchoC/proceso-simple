<?php
/**
 * Impuestos Adic. - Conf (página 73), solapa "Agente de Percepción": datos del agente de recaudación y
 * alícuotas por situación del cliente (gestion__empresas_impuestos_config_situaciones).
 * Diseño: _local/docs/gestion/09_percepciones_iibb.md §4.1 / §4.2.
 */
require_once __DIR__ . '/percepciones_iibb_core_model.php';

const EIC_AGENTE_CRITERIOS = ['ENTREGA', 'ESTABLECIMIENTO'];
const EIC_AGENTE_BASES = ['NETO', 'NETO_GRAVADO', 'TOTAL'];
const EIC_AGENTE_FORMATOS_PADRON = ['', 'SIRCIP'];
const EIC_AGENTE_FORMATOS_DDJJ = ['', 'SIRCAR_D1', 'SIRCIP'];

// Config de la empresa (o null): toda lectura/escritura de esta solapa pasa por acá.
function eicAgenteConfig($conexion, $config_id, $empresa_id) {
    $stmt = mysqli_prepare($conexion, "SELECT * FROM gestion__empresas_impuestos_config WHERE empresa_impuesto_config_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $config_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function eicObtenerAgente($conexion, $config_id, $empresa_id) {
    $cfg = eicAgenteConfig($conexion, intval($config_id), intval($empresa_id));
    if (!$cfg) return ['success' => false, 'message' => 'Configuración no encontrada.'];
    $situaciones = [];
    $stmt = mysqli_prepare($conexion, "SELECT * FROM gestion__empresas_impuestos_config_situaciones WHERE empresa_impuesto_config_id = ?");
    $id = intval($cfg['empresa_impuesto_config_id']);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_assoc($res)) $situaciones[$r['situacion']] = $r;
    mysqli_stmt_close($stmt);

    $filas = [];
    foreach (perSituacionesImpuesto($cfg['impuesto_tipo_id']) as $codigo => $nombre) {
        $s = $situaciones[$codigo] ?? null;
        $filas[] = [
            'situacion' => $codigo,
            'nombre' => $nombre,
            'activa' => $s && intval($s['tabla_estado_registro_id']) === 1 ? 1 : 0,
            'alicuota' => $s ? floatval($s['alicuota']) : 0,
            'requiere_territorio' => $s ? intval($s['requiere_territorio']) : (($codigo === 'PADRON' || intval($cfg['impuesto_tipo_id']) === PER_IVA_IMPUESTO_TIPO_ID) ? 0 : 1),
            'tipo_registro_ddjj' => $s ? $s['tipo_registro_ddjj'] : null,
            'declarar_si_cero' => $s ? intval($s['declarar_si_cero']) : 0,
            'leyenda' => $s ? $s['leyenda'] : null,
        ];
    }
    return ['success' => true, 'data' => [
        'es_percepcion_iibb' => in_array(intval($cfg['impuesto_tipo_id']), PER_TIPOS_PRACTICADOS, true),   // usa el cálculo automático
        'es_percepcion_iva' => intval($cfg['impuesto_tipo_id']) === PER_IVA_IMPUESTO_TIPO_ID,
        'numero_agente' => $cfg['numero_agente'],
        'criterio_territorial' => $cfg['criterio_territorial'] ?: 'ENTREGA',
        'base_calculo' => $cfg['base_calculo'],
        'minimo_imponible' => floatval($cfg['minimo_imponible']),
        'importe_minimo_percepcion' => floatval($cfg['importe_minimo_percepcion']),
        'arca_tributo_id' => $cfg['arca_tributo_id'],
        'formato_padron' => $cfg['formato_padron'] ?? '',
        'formato_ddjj' => $cfg['formato_ddjj'] ?? '',
        'codigo_jurisdiccion_cm' => $cfg['codigo_jurisdiccion_cm'],
        'codigo_regimen_ddjj' => $cfg['codigo_regimen_ddjj'],
        'situaciones' => $filas,
    ], 'message' => ''];
}

function eicGuardarAgente($conexion, $config_id, $empresa_id, $data) {
    $config_id = intval($config_id);
    $empresa_id = intval($empresa_id);
    error_log("=== INICIO eicGuardarAgente config: $config_id ===");
    $cfg = eicAgenteConfig($conexion, $config_id, $empresa_id);
    if (!$cfg) return ['success' => false, 'message' => 'Configuración no encontrada.'];

    $numero_agente = trim((string)($data['numero_agente'] ?? '')) ?: null;
    $criterio = strtoupper(trim((string)($data['criterio_territorial'] ?? 'ENTREGA')));
    $base = strtoupper(trim((string)($data['base_calculo'] ?? 'NETO')));
    $minimo = max(0, floatval($data['minimo_imponible'] ?? 0));
    $minimo_perc = max(0, floatval($data['importe_minimo_percepcion'] ?? 0));
    $tributo = intval($data['arca_tributo_id'] ?? 0) ?: null;
    $formato_padron = strtoupper(trim((string)($data['formato_padron'] ?? '')));
    $formato_ddjj = strtoupper(trim((string)($data['formato_ddjj'] ?? '')));
    $cod_cm = preg_replace('/\D/', '', (string)($data['codigo_jurisdiccion_cm'] ?? '')) ?: null;
    $cod_regimen = preg_replace('/\D/', '', (string)($data['codigo_regimen_ddjj'] ?? '')) ?: null;

    if (!in_array($criterio, EIC_AGENTE_CRITERIOS, true)) return ['success' => false, 'message' => 'Criterio territorial inválido.'];
    if (!in_array($base, EIC_AGENTE_BASES, true)) return ['success' => false, 'message' => 'Base de cálculo inválida.'];
    if (!in_array($formato_padron, EIC_AGENTE_FORMATOS_PADRON, true)) return ['success' => false, 'message' => 'Formato de padrón inválido.'];
    if (!in_array($formato_ddjj, EIC_AGENTE_FORMATOS_DDJJ, true)) return ['success' => false, 'message' => 'Formato de DDJJ inválido.'];
    if ($cod_cm !== null && strlen($cod_cm) !== 3) return ['success' => false, 'message' => 'El código de jurisdicción CM tiene 3 dígitos (ej. 916).'];
    if ($cod_regimen !== null && strlen($cod_regimen) > 3) return ['success' => false, 'message' => 'El código de régimen tiene hasta 3 dígitos.'];
    $formato_padron = $formato_padron ?: null;
    $formato_ddjj = $formato_ddjj ?: null;

    $situaciones = $data['situaciones'] ?? [];
    if (is_string($situaciones)) $situaciones = json_decode($situaciones, true);
    if (!is_array($situaciones)) return ['success' => false, 'message' => 'Situaciones inválidas.'];
    $validas = perSituacionesImpuesto($cfg['impuesto_tipo_id']);

    mysqli_begin_transaction($conexion);
    try {
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__empresas_impuestos_config
            SET numero_agente = ?, criterio_territorial = ?, base_calculo = ?, minimo_imponible = ?, importe_minimo_percepcion = ?,
                arca_tributo_id = ?, formato_padron = ?, formato_ddjj = ?, codigo_jurisdiccion_cm = ?, codigo_regimen_ddjj = ?
            WHERE empresa_impuesto_config_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmt, 'sssddissssii', $numero_agente, $criterio, $base, $minimo, $minimo_perc,
            $tributo, $formato_padron, $formato_ddjj, $cod_cm, $cod_regimen, $config_id, $empresa_id);
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);

        $sql = "INSERT INTO gestion__empresas_impuestos_config_situaciones
                    (empresa_impuesto_config_id, situacion, requiere_territorio, alicuota, tipo_registro_ddjj, declarar_si_cero, leyenda, tabla_estado_registro_id)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE requiere_territorio = VALUES(requiere_territorio), alicuota = VALUES(alicuota),
                    tipo_registro_ddjj = VALUES(tipo_registro_ddjj), declarar_si_cero = VALUES(declarar_si_cero),
                    leyenda = VALUES(leyenda), tabla_estado_registro_id = VALUES(tabla_estado_registro_id)";
        foreach ($situaciones as $s) {
            $codigo = (string)($s['situacion'] ?? '');
            if (!isset($validas[$codigo])) throw new Exception("Situación desconocida: $codigo");
            $alic = floatval($s['alicuota'] ?? 0);
            if ($alic < 0 || $alic > 100) throw new Exception("Alícuota fuera de rango en $codigo.");
            $territorio = intval($s['requiere_territorio'] ?? 1) ? 1 : 0;
            $tipo_reg = ($s['tipo_registro_ddjj'] ?? '') !== '' && $s['tipo_registro_ddjj'] !== null ? intval($s['tipo_registro_ddjj']) : null;
            if ($tipo_reg !== null && ($tipo_reg < 1 || $tipo_reg > 5)) throw new Exception("Tipo de registro SIRCIP inválido en $codigo (1 a 5).");
            $declarar = intval($s['declarar_si_cero'] ?? 0) ? 1 : 0;
            $leyenda = trim((string)($s['leyenda'] ?? '')) ?: null;
            if ($leyenda !== null) $leyenda = mb_substr($leyenda, 0, 120);
            $estado = intval($s['activa'] ?? 0) ? 1 : 2;
            $stmt = mysqli_prepare($conexion, $sql);
            mysqli_stmt_bind_param($stmt, 'isidiisi', $config_id, $codigo, $territorio, $alic, $tipo_reg, $declarar, $leyenda, $estado);
            if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
        }
        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Datos del agente guardados.'];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log('ERROR eicGuardarAgente: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
