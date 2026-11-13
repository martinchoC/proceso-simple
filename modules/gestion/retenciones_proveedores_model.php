<?php
/**
 * Retenciones del proveedor (bloque del Historial de situación fiscal en proveedores / entidades):
 * condición frente a cada retención que practica la empresa, régimen habitual y certificados de exclusión,
 * todo con vigencia. Lo lee retenciones_practicadas_core_model.php al calcular la orden de pago.
 * Diseño: _local/docs/gestion/10_retenciones_percepciones_relevamiento.md
 */
require_once __DIR__ . '/retenciones_practicadas_core_model.php';
require_once __DIR__ . '/regimenes_retencion_model.php';

function rprovExigirEntidad($conexion, $empresa_id, $entidad_id) {
    if (!perIibbFila($conexion, "SELECT 1 FROM gestion__entidades WHERE entidad_id = ? AND empresa_id = ?", 'ii', intval($entidad_id), intval($empresa_id))) {
        throw new Exception('Entidad no encontrada.');
    }
}

function rprovFecha($v, $obligatoria = true) {
    $v = trim((string)$v);
    if ($v === '') {
        if ($obligatoria) throw new Exception('Falta la fecha desde.');
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) throw new Exception("Fecha inválida: $v");
    return $v;
}

/**
 * Impuesto + jurisdicción de una retención de la que la empresa es agente (config activa, vigente hoy o a futuro).
 * Devuelve la config o lanza Exception. En los impuestos nacionales la jurisdicción es NULL.
 */
function rprovConfigAgente($conexion, $empresa_id, $impuesto_tipo_id, $jurisdiccion_id) {
    $hoy = date('Y-m-d');
    foreach (retPracConfigsVigentes($conexion, $empresa_id, $hoy) as $c) {
        if (intval($c['impuesto_tipo_id']) !== intval($impuesto_tipo_id)) continue;
        if ($c['es_iibb'] && intval($c['jurisdiccion_id']) !== intval($jurisdiccion_id)) continue;
        return $c;
    }
    throw new Exception('La empresa no es agente de esa retención (Impuestos Adic. - Conf).');
}

function rprovDatos($conexion, $empresa_id, $entidad_id) {
    rprovExigirEntidad($conexion, $empresa_id, $entidad_id);
    $hoy = date('Y-m-d');
    $agente = [];
    foreach (retPracConfigsVigentes($conexion, $empresa_id, $hoy) as $c) {
        $jur = $c['es_iibb'] ? intval($c['jurisdiccion_id']) : 0;
        $vigente = retPracCondicionProveedor($conexion, $empresa_id, $entidad_id, intval($c['impuesto_tipo_id']), $jur, $hoy);
        $agente[] = [
            'impuesto_tipo_id'    => intval($c['impuesto_tipo_id']),
            'jurisdiccion_id'     => $c['es_iibb'] ? $jur : null,
            'nombre'              => $c['impuesto_tipo'] . ($c['es_iibb'] ? ' - ' . $c['jurisdiccion_nombre'] : ''),
            'es_iibb'             => $c['es_iibb'],
            'condicion_vigente'   => $vigente ? $vigente['condicion'] : null,
            'regimenes'           => rregVigentes($conexion, $empresa_id, intval($c['impuesto_tipo_id']), $jur, $hoy),
            'regimen_defecto_id'  => $c['regimen_id'] ? intval($c['regimen_id']) : null,
        ];
    }
    $condiciones = perIibbFilas($conexion,
        "SELECT er.*, it.impuesto_tipo, j.jurisdiccion_nombre, r.codigo AS regimen_codigo, r.regimen AS regimen_nombre
         FROM gestion__entidades_retenciones er
         JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = er.impuesto_tipo_id
         LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = er.jurisdiccion_id
         LEFT JOIN gestion__retenciones_regimenes r ON r.regimen_id = er.regimen_id
         WHERE er.empresa_id = ? AND er.entidad_id = ? AND er.tabla_estado_registro_id = 1
         ORDER BY it.impuesto_tipo, j.jurisdiccion_nombre, er.f_desde DESC", 'ii', intval($empresa_id), intval($entidad_id));
    $exclusiones = perIibbFilas($conexion,
        "SELECT x.*, it.impuesto_tipo, j.jurisdiccion_nombre
         FROM gestion__entidades_iibb_exclusiones x
         LEFT JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = x.impuesto_tipo_id
         LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = x.jurisdiccion_id
         WHERE x.empresa_id = ? AND x.entidad_id = ? AND x.sentido = 'RETENCION' AND x.tabla_estado_registro_id = 1
         ORDER BY x.f_desde DESC", 'ii', intval($empresa_id), intval($entidad_id));
    return ['agente' => $agente, 'condiciones' => $condiciones, 'exclusiones' => $exclusiones, 'tipos' => retPracCondiciones()];
}

/**
 * Alta de la condición del proveedor frente a una retención. La condición abierta anterior del mismo impuesto y
 * jurisdicción se cierra el día previo; si empieza el mismo día o después, se rechaza.
 */
function rprovAgregarCondicion($conexion, $empresa_id, $entidad_id, $data, $usuario_id) {
    rprovExigirEntidad($conexion, $empresa_id, $entidad_id);
    $impuesto_id = intval($data['impuesto_tipo_id'] ?? 0);
    $jur_in = intval($data['jurisdiccion_id'] ?? 0);
    $cfg = rprovConfigAgente($conexion, $empresa_id, $impuesto_id, $jur_in);
    $jurisdiccion_id = $cfg['es_iibb'] ? $jur_in : null;
    $condicion = strtoupper(trim((string)($data['condicion'] ?? '')));
    if (!isset(retPracCondiciones()[$condicion])) throw new Exception('Condición inválida.');
    $regimen_id = intval($data['regimen_id'] ?? 0) ?: null;
    if ($regimen_id !== null) {
        $ok = false;
        foreach (rregVigentes($conexion, $empresa_id, $impuesto_id, intval($jurisdiccion_id), date('Y-m-d')) as $r) {
            if (intval($r['regimen_id']) === $regimen_id) $ok = true;
        }
        if (!$ok) throw new Exception('El régimen no corresponde a esa retención o no está vigente.');
    }
    $f_desde = rprovFecha($data['f_desde'] ?? '');
    $f_hasta = rprovFecha($data['f_hasta'] ?? '', false);
    if ($f_hasta !== null && $f_hasta < $f_desde) throw new Exception('La fecha hasta es anterior a la fecha desde.');
    $obs = mb_substr(trim((string)($data['observaciones'] ?? '')), 0, 255) ?: null;
    $empresa_id = intval($empresa_id);
    $entidad_id = intval($entidad_id);
    $usuario_id = intval($usuario_id);
    $jur_cmp = intval($jurisdiccion_id);

    mysqli_begin_transaction($conexion);
    try {
        $abiertas = perIibbFilas($conexion,
            "SELECT entidad_retencion_id, f_desde FROM gestion__entidades_retenciones
             WHERE empresa_id = ? AND entidad_id = ? AND impuesto_tipo_id = ? AND COALESCE(jurisdiccion_id, 0) = ?
               AND tabla_estado_registro_id = 1 AND (f_hasta IS NULL OR f_hasta >= ?) FOR UPDATE",
            'iiiis', $empresa_id, $entidad_id, $impuesto_id, $jur_cmp, $f_desde);
        foreach ($abiertas as $a) {
            if ($a['f_desde'] >= $f_desde) throw new Exception('Ya hay una condición que empieza el ' . $a['f_desde'] . ' o después: inhabilítela primero.');
            $cierre = date('Y-m-d', strtotime($f_desde . ' -1 day'));
            $id = intval($a['entidad_retencion_id']);
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__entidades_retenciones SET f_hasta = ?, usuario_modificacion_id = ? WHERE entidad_retencion_id = ?");
            mysqli_stmt_bind_param($stmt, 'sii', $cierre, $usuario_id, $id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__entidades_retenciones
            (empresa_id, entidad_id, impuesto_tipo_id, jurisdiccion_id, condicion, regimen_id, f_desde, f_hasta, observaciones, tabla_estado_registro_id, usuario_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)");
        mysqli_stmt_bind_param($stmt, 'iiiisisssi', $empresa_id, $entidad_id, $impuesto_id, $jurisdiccion_id, $condicion, $regimen_id,
            $f_desde, $f_hasta, $obs, $usuario_id);
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        mysqli_stmt_close($stmt);
        mysqli_commit($conexion);
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        throw $e;
    }
}

function rprovInhabilitar($conexion, $empresa_id, $tabla, $id, $usuario_id) {
    $empresa_id = intval($empresa_id);
    $id = intval($id);
    if ($tabla === 'condicion') {
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__entidades_retenciones SET tabla_estado_registro_id = 2, usuario_modificacion_id = ?
                                           WHERE entidad_retencion_id = ? AND empresa_id = ?");
        $usuario_id = intval($usuario_id);
        mysqli_stmt_bind_param($stmt, 'iii', $usuario_id, $id, $empresa_id);
    } else {
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__entidades_iibb_exclusiones SET tabla_estado_registro_id = 2
                                           WHERE entidad_iibb_exclusion_id = ? AND empresa_id = ? AND sentido = 'RETENCION'");
        mysqli_stmt_bind_param($stmt, 'ii', $id, $empresa_id);
    }
    mysqli_stmt_execute($stmt);
    $n = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($n !== 1) throw new Exception('Registro no encontrado.');
}

/** Certificado de exclusión / no retención (porcentaje con vigencia) para una retención. */
function rprovAgregarExclusion($conexion, $empresa_id, $entidad_id, $data) {
    rprovExigirEntidad($conexion, $empresa_id, $entidad_id);
    $impuesto_id = intval($data['impuesto_tipo_id'] ?? 0);
    $cfg = rprovConfigAgente($conexion, $empresa_id, $impuesto_id, intval($data['jurisdiccion_id'] ?? 0));
    $jurisdiccion_id = intval($cfg['jurisdiccion_id']);   // IIBB: la provincia; nacionales: la de la config
    $pct = floatval($data['porcentaje_exclusion'] ?? 0);
    if ($pct <= 0 || $pct > 100) throw new Exception('El porcentaje de exclusión va de 0 a 100.');
    $cert = trim((string)($data['numero_certificado'] ?? ''));
    if ($cert === '') throw new Exception('Ingrese el número de certificado.');
    $f_desde = rprovFecha($data['f_desde'] ?? '');
    $f_hasta = rprovFecha($data['f_hasta'] ?? '', false);
    if ($f_hasta === null) throw new Exception('El certificado de exclusión tiene que tener fecha de vencimiento.');
    if ($f_hasta < $f_desde) throw new Exception('La fecha hasta es anterior a la fecha desde.');
    $obs = mb_substr(trim((string)($data['observaciones'] ?? '')), 0, 255) ?: null;
    $cert = mb_substr($cert, 0, 50);
    $empresa_id = intval($empresa_id);
    $entidad_id = intval($entidad_id);
    $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__entidades_iibb_exclusiones
        (empresa_id, entidad_id, sentido, impuesto_tipo_id, jurisdiccion_id, porcentaje_exclusion, numero_certificado, f_desde, f_hasta, observaciones, tabla_estado_registro_id)
        VALUES (?, ?, 'RETENCION', ?, ?, ?, ?, ?, ?, ?, 1)");
    mysqli_stmt_bind_param($stmt, 'iiiidssss', $empresa_id, $entidad_id, $impuesto_id, $jurisdiccion_id, $pct, $cert, $f_desde, $f_hasta, $obs);
    if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
}
