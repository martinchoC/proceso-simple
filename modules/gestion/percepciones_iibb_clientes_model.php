<?php
/**
 * Datos del cliente para percepciones IIBB (se editan desde Historial de Situación Fiscal de clientes / entidades):
 *  - jurisdicciones de alta de la condición IIBB vigente (Convenio Multilateral): gestion__entidades_condiciones_iibb_jurisdicciones
 *  - certificados de no percepción / exclusión: gestion__entidades_iibb_exclusiones
 * Diseño: _local/docs/gestion/09_percepciones_iibb.md §4.3 / §4.5.
 */
require_once __DIR__ . '/percepciones_iibb_core_model.php';

function pcliExigirEntidad($conexion, $empresa_id, $entidad_id) {
    if (!perIibbFila($conexion, "SELECT 1 FROM gestion__entidades WHERE entidad_id = ? AND empresa_id = ?", 'ii', intval($entidad_id), intval($empresa_id))) {
        throw new Exception('Entidad no encontrada.');
    }
}

function pcliFecha($v, $obligatoria = true) {
    $v = trim((string)$v);
    if ($v === '') {
        if ($obligatoria) throw new Exception('Falta la fecha.');
        return null;
    }
    $d = DateTime::createFromFormat('Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) throw new Exception("Fecha inválida: $v");
    return $v;
}

function pcliDatos($conexion, $empresa_id, $entidad_id) {
    pcliExigirEntidad($conexion, $empresa_id, $entidad_id);
    $hoy = date('Y-m-d');
    $cond = perIibbFila($conexion,
        "SELECT ec.entidad_condicion_iibb_id, r.regimen_iibb, r.regimen_iibb_codigo, s.jurisdiccion_sede AS sede
         FROM gestion__entidades_condiciones_iibb ec
         JOIN gestion__regimenes_iibb r ON r.regimen_iibb_id = ec.regimen_iibb_id
         LEFT JOIN gestion__iibb_jurisdicciones_sedes s ON s.jurisdiccion_sede_id = ec.jurisdiccion_sede_id
         WHERE ec.entidad_id = ? AND ec.tabla_estado_registro_id = 1 AND ec.f_desde <= ? AND (ec.f_hasta IS NULL OR ec.f_hasta >= ?)
         ORDER BY ec.f_desde DESC LIMIT 1",
        'iss', intval($entidad_id), $hoy, $hoy);
    $jurisdicciones = $cond ? perIibbFilas($conexion,
        "SELECT cj.entidad_condicion_iibb_jurisdiccion_id, cj.jurisdiccion_sede_id, cj.f_desde, cj.f_hasta, s.codigo_comarb, s.jurisdiccion_sede
         FROM gestion__entidades_condiciones_iibb_jurisdicciones cj
         JOIN gestion__iibb_jurisdicciones_sedes s ON s.jurisdiccion_sede_id = cj.jurisdiccion_sede_id
         WHERE cj.entidad_condicion_iibb_id = ? AND cj.tabla_estado_registro_id = 1
         ORDER BY s.codigo_comarb", 'i', intval($cond['entidad_condicion_iibb_id'])) : [];
    $exclusiones = perIibbFilas($conexion,
        "SELECT x.*, j.jurisdiccion_nombre FROM gestion__entidades_iibb_exclusiones x
         JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = x.jurisdiccion_id
         WHERE x.empresa_id = ? AND x.entidad_id = ? AND x.sentido = 'PERCEPCION' AND x.tabla_estado_registro_id = 1
         ORDER BY x.f_desde DESC", 'ii', intval($empresa_id), intval($entidad_id));
    return [
        'condicion' => $cond,
        'jurisdicciones' => $jurisdicciones,
        'exclusiones' => $exclusiones,
        'sedes' => perIibbFilas($conexion, "SELECT jurisdiccion_sede_id, codigo_comarb, jurisdiccion_sede FROM gestion__iibb_jurisdicciones_sedes
                                            WHERE tabla_estado_registro_id = 1 ORDER BY codigo_comarb", ''),
        // Provincias (exclusión de percepción IIBB) + la nacional (tipo 1: exclusión de percepción de IVA)
        'jurisdicciones_provinciales' => perIibbFilas($conexion, "SELECT jurisdiccion_id,
                                                   IF(jurisdiccion_tipo_id = 1, CONCAT('IVA - ', jurisdiccion_nombre), jurisdiccion_nombre) AS jurisdiccion_nombre
                                            FROM gestion__jurisdicciones
                                            WHERE tabla_estado_registro_id = 1 AND (provincia_id IS NOT NULL OR jurisdiccion_tipo_id = 1)
                                            ORDER BY jurisdiccion_tipo_id <> 1, jurisdiccion_nombre", ''),
    ];
}

function pcliAgregarJurisdiccion($conexion, $empresa_id, $entidad_id, $data) {
    pcliExigirEntidad($conexion, $empresa_id, $entidad_id);
    $cond_id = intval($data['entidad_condicion_iibb_id'] ?? 0);
    $sede_id = intval($data['jurisdiccion_sede_id'] ?? 0);
    if (!perIibbFila($conexion, "SELECT 1 FROM gestion__entidades_condiciones_iibb WHERE entidad_condicion_iibb_id = ? AND entidad_id = ?", 'ii', $cond_id, intval($entidad_id))) {
        throw new Exception('El cliente no tiene esa condición IIBB.');
    }
    if (!perIibbFila($conexion, "SELECT 1 FROM gestion__iibb_jurisdicciones_sedes WHERE jurisdiccion_sede_id = ?", 'i', $sede_id)) {
        throw new Exception('Jurisdicción inválida.');
    }
    $f_desde = pcliFecha($data['f_desde'] ?? '', false);
    $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__entidades_condiciones_iibb_jurisdicciones
            (entidad_condicion_iibb_id, jurisdiccion_sede_id, f_desde, f_hasta, tabla_estado_registro_id)
            VALUES (?, ?, ?, NULL, 1)
            ON DUPLICATE KEY UPDATE f_desde = VALUES(f_desde), f_hasta = NULL, tabla_estado_registro_id = 1");
    mysqli_stmt_bind_param($stmt, 'iis', $cond_id, $sede_id, $f_desde);
    if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
}

function pcliQuitarJurisdiccion($conexion, $empresa_id, $id) {
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__entidades_condiciones_iibb_jurisdicciones cj
            JOIN gestion__entidades_condiciones_iibb ec ON ec.entidad_condicion_iibb_id = cj.entidad_condicion_iibb_id
            JOIN gestion__entidades e ON e.entidad_id = ec.entidad_id
            SET cj.tabla_estado_registro_id = 2, cj.f_hasta = CURDATE()
            WHERE cj.entidad_condicion_iibb_jurisdiccion_id = ? AND e.empresa_id = ?");
    $id = intval($id); $empresa_id = intval($empresa_id);
    mysqli_stmt_bind_param($stmt, 'ii', $id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $n = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($n < 1) throw new Exception('Jurisdicción no encontrada.');
}

function pcliAgregarExclusion($conexion, $empresa_id, $entidad_id, $data) {
    pcliExigirEntidad($conexion, $empresa_id, $entidad_id);
    $jur = intval($data['jurisdiccion_id'] ?? 0);
    $j = perIibbFila($conexion, "SELECT jurisdiccion_tipo_id FROM gestion__jurisdicciones WHERE jurisdiccion_id = ?", 'i', $jur);
    if (!$j) throw new Exception('Jurisdicción inválida.');
    $impuesto = intval($j['jurisdiccion_tipo_id']) === 1 ? PER_IVA_IMPUESTO_TIPO_ID : null;   // nacional: exclusión de percepción de IVA
    $pct = floatval($data['porcentaje_exclusion'] ?? 100);
    if ($pct <= 0 || $pct > 100) throw new Exception('El porcentaje de exclusión va de 0,01 a 100.');
    $desde = pcliFecha($data['f_desde'] ?? '');
    $hasta = pcliFecha($data['f_hasta'] ?? '', false);
    if ($hasta !== null && $hasta < $desde) throw new Exception('La fecha hasta es anterior a la fecha desde.');
    $nro = trim((string)($data['numero_certificado'] ?? '')) ?: null;
    $obs = trim((string)($data['observaciones'] ?? '')) ?: null;
    $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__entidades_iibb_exclusiones
            (empresa_id, entidad_id, impuesto_tipo_id, jurisdiccion_id, porcentaje_exclusion, numero_certificado, f_desde, f_hasta, observaciones, tabla_estado_registro_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
    $empresa_id = intval($empresa_id); $entidad_id = intval($entidad_id);
    mysqli_stmt_bind_param($stmt, 'iiiidssss', $empresa_id, $entidad_id, $impuesto, $jur, $pct, $nro, $desde, $hasta, $obs);
    if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
}

function pcliInhabilitarExclusion($conexion, $empresa_id, $id) {
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__entidades_iibb_exclusiones SET tabla_estado_registro_id = 2
                                       WHERE entidad_iibb_exclusion_id = ? AND empresa_id = ?");
    $id = intval($id); $empresa_id = intval($empresa_id);
    mysqli_stmt_bind_param($stmt, 'ii', $id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $n = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($n < 1) throw new Exception('Certificado no encontrado.');
}
