<?php
/**
 * Impuestos Adic. - Conf (página 73), solapa "Agente de Retención": cómo aplica la empresa cada retención que
 * practica en las órdenes de pago (base, alícuota genérica o padrón, mínimos). Una config por impuesto de
 * retención de compras y, en IIBB, por jurisdicción. El cálculo está en retenciones_practicadas_core_model.php.
 */
require_once __DIR__ . '/retenciones_practicadas_core_model.php';
require_once __DIR__ . '/regimenes_retencion_model.php';

const EIC_RET_FUENTES = ['manual', 'padron'];
const EIC_RET_SIN_PADRON = ['GENERICA', 'NO_RETENER'];

// Config de la empresa con los datos del impuesto (o null)
function eicRetencionConfig($conexion, $config_id, $empresa_id) {
    return perIibbFila($conexion,
        "SELECT cfg.*, it.impuesto_tipo, it.es_retencion, it.aplica_compra, j.jurisdiccion_tipo_id
         FROM gestion__empresas_impuestos_config cfg
         JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = cfg.impuesto_tipo_id
         LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = cfg.jurisdiccion_id
         WHERE cfg.empresa_impuesto_config_id = ? AND cfg.empresa_id = ?",
        'ii', intval($config_id), intval($empresa_id));
}

function eicObtenerRetencion($conexion, $config_id, $empresa_id) {
    $cfg = eicRetencionConfig($conexion, $config_id, $empresa_id);
    if (!$cfg) return ['success' => false, 'message' => 'Configuración no encontrada.'];
    $es_retencion = intval($cfg['es_retencion']) === 1 && intval($cfg['aplica_compra']) === 1;
    return ['success' => true, 'data' => [
        'es_retencion'      => $es_retencion ? 1 : 0,
        'es_iibb'           => retPracEsIibb($cfg['impuesto_tipo']) ? 1 : 0,
        'numero_agente'     => $cfg['numero_agente'],
        'base_calculo'      => isset(retPracBases()[$cfg['base_calculo']]) ? $cfg['base_calculo'] : 'NETO_GRAVADO',
        'tipo_calculo'      => $cfg['tipo_calculo'] === 'padron' ? 'padron' : 'manual',
        'alicuota'          => floatval($cfg['alicuota']),
        'sin_dato_accion'   => $cfg['sin_dato_accion'] === 'NO_RETENER' ? 'NO_RETENER' : 'GENERICA',
        'minimo_imponible'  => floatval($cfg['minimo_imponible']),
        'importe_minimo'    => floatval($cfg['importe_minimo_percepcion']),
        'aplica_siempre'    => intval($cfg['aplica_siempre']),
        'tiene_cuenta'      => intval($cfg['cont_cuenta_id']) > 0 ? 1 : 0,
        'regimen_id'        => $cfg['regimen_id'] ? intval($cfg['regimen_id']) : null,
        'regimenes'         => rregVigentes($conexion, $empresa_id, intval($cfg['impuesto_tipo_id']),
                                   retPracEsIibb($cfg['impuesto_tipo']) ? intval($cfg['jurisdiccion_id']) : 0, date('Y-m-d')),
        'bases'             => retPracBases(),
    ], 'message' => ''];
}

function eicGuardarRetencion($conexion, $config_id, $empresa_id, $data) {
    $config_id = intval($config_id);
    $empresa_id = intval($empresa_id);
    error_log("=== INICIO eicGuardarRetencion config: $config_id ===");
    $cfg = eicRetencionConfig($conexion, $config_id, $empresa_id);
    if (!$cfg) return ['success' => false, 'message' => 'Configuración no encontrada.'];
    if (intval($cfg['es_retencion']) !== 1 || intval($cfg['aplica_compra']) !== 1) {
        return ['success' => false, 'message' => 'El tipo de impuesto de esta configuración no es una retención practicada en pagos.'];
    }
    $es_iibb = retPracEsIibb($cfg['impuesto_tipo']);
    if ($es_iibb && intval($cfg['jurisdiccion_tipo_id'] ?? 0) !== 2) {
        return ['success' => false, 'message' => 'La retención de IIBB necesita una jurisdicción provincial (solapa Configuración General).'];
    }

    $numero_agente = trim((string)($data['numero_agente'] ?? '')) ?: null;
    if ($numero_agente !== null) $numero_agente = mb_substr($numero_agente, 0, 30);
    $base = strtoupper(trim((string)($data['base_calculo'] ?? '')));
    $fuente = strtolower(trim((string)($data['tipo_calculo'] ?? 'manual')));
    $alicuota = floatval($data['alicuota'] ?? 0);
    $sin_padron = strtoupper(trim((string)($data['sin_dato_accion'] ?? 'GENERICA')));
    $minimo = max(0, floatval($data['minimo_imponible'] ?? 0));
    $minimo_ret = max(0, floatval($data['importe_minimo'] ?? 0));
    $aplica_siempre = intval($data['aplica_siempre'] ?? 1) ? 1 : 0;
    $regimen_id = intval($data['regimen_id'] ?? 0) ?: null;
    if ($regimen_id !== null) {
        $validos = array_map('intval', array_column(rregVigentes($conexion, $empresa_id, intval($cfg['impuesto_tipo_id']),
            $es_iibb ? intval($cfg['jurisdiccion_id']) : 0, date('Y-m-d')), 'regimen_id'));
        if (!in_array($regimen_id, $validos, true)) return ['success' => false, 'message' => 'El régimen no corresponde a este impuesto / jurisdicción o no está vigente.'];
    }

    if (!isset(retPracBases()[$base])) return ['success' => false, 'message' => 'Base de cálculo inválida.'];
    if (!in_array($fuente, EIC_RET_FUENTES, true)) return ['success' => false, 'message' => 'Fuente de alícuota inválida.'];
    if (!in_array($sin_padron, EIC_RET_SIN_PADRON, true)) return ['success' => false, 'message' => 'Opción "sin padrón" inválida.'];
    if ($alicuota < 0 || $alicuota > 100) return ['success' => false, 'message' => 'La alícuota va de 0 a 100.'];
    if ($fuente === 'manual' && $alicuota <= 0 && $regimen_id === null) return ['success' => false, 'message' => 'Indique la alícuota genérica.'];
    if ($fuente === 'padron' && $sin_padron === 'GENERICA' && $alicuota <= 0 && $regimen_id === null) {
        return ['success' => false, 'message' => 'Indique la alícuota genérica que se aplica a quien no figura en el padrón, o elija "No retener".'];
    }

    $stmt = mysqli_prepare($conexion, "UPDATE gestion__empresas_impuestos_config
        SET numero_agente = ?, base_calculo = ?, tipo_calculo = ?, alicuota = ?, sin_dato_accion = ?,
            minimo_imponible = ?, importe_minimo_percepcion = ?, aplica_siempre = ?, regimen_id = ?
        WHERE empresa_impuesto_config_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, 'sssdsddiiii', $numero_agente, $base, $fuente, $alicuota, $sin_padron,
        $minimo, $minimo_ret, $aplica_siempre, $regimen_id, $config_id, $empresa_id);
    $ok = mysqli_stmt_execute($stmt);
    $error = mysqli_stmt_error($stmt);
    mysqli_stmt_close($stmt);
    if (!$ok) {
        error_log('ERROR eicGuardarRetencion: ' . $error);
        return ['success' => false, 'message' => 'No se pudo guardar: ' . $error];
    }
    $mensaje = 'Datos del agente de retención guardados.';
    if (intval($cfg['cont_cuenta_id']) <= 0) $mensaje .= ' Falta la cuenta contable (solapa Configuración General): sin ella no se puede confirmar una OP con esta retención.';
    return ['success' => true, 'message' => $mensaje];
}
