<?php
require_once __DIR__ . '/../../core/permisos.php';
/**
 * Regímenes de Retención (página 124 / 624): catálogo GLOBAL (empresa_id = 0) de regímenes con vigencia y escala.
 * Lo administra el superadmin (la página no tiene permisos para perfiles). Valores normativos: los carga el usuario;
 * NULL en una alícuota = "a confirmar con el contador" (el cálculo avisa y no retiene).
 * Lo usa retenciones_practicadas_core_model.php. Diseño: _local/docs/gestion/10_retenciones_percepciones_relevamiento.md
 */
require_once __DIR__ . '/retenciones_practicadas_core_model.php';

const RREG_TIPOS_MINIMO = ['POR_PAGO' => 'Por pago', 'ACUMULADO_MENSUAL' => 'Acumulado mensual del proveedor'];
const RREG_MODOS_MINIMO = ['DEDUCIBLE' => 'Se resta de la base', 'UMBRAL' => 'Umbral: si se supera, sobre el total'];

function rregFunciones($conexion, $pagina_id) {
    return perIibbFilas($conexion,
        "SELECT pf.*, i.icono_clase, c.color_clase, c.bg_clase, c.text_clase
         FROM conf__paginas_funciones pf
         LEFT JOIN conf__iconos i ON pf.icono_id = i.icono_id
         LEFT JOIN conf__colores c ON pf.color_id = c.color_id
         WHERE pf.pagina_id = ? AND pf.tabla_estado_registro_id = 1
         ORDER BY pf.tabla_estado_registro_origen_id, pf.orden", 'i', intval($pagina_id));
}

function rregBoton($f) {
    return [
        'nombre_funcion' => $f['nombre_funcion'],
        'accion_js'      => $f['accion_js'],
        'icono_clase'    => $f['icono_clase'],
        'color_clase'    => $f['color_clase'] ?: 'btn-outline-primary',
        'bg_clase'       => $f['bg_clase'] ?? '',
        'text_clase'     => $f['text_clase'] ?? '',
        'descripcion'    => $f['descripcion'],
        'es_confirmable' => intval($f['tabla_estado_registro_origen_id']) !== intval($f['tabla_estado_registro_destino_id']) ? 1 : 0,
    ];
}

function rregBotonAgregar($conexion, $pagina_id) {
    foreach (permisos_filtrar_funciones($conexion, rregFunciones($conexion, $pagina_id)) as $f) {
        if (intval($f['tabla_estado_registro_origen_id']) === 0) return rregBoton($f);
    }
    return null;
}

/** Catálogos del formulario: impuestos de retención practicada y jurisdicciones provinciales. */
function rregCatalogos($conexion) {
    $impuestos = perIibbFilas($conexion,
        "SELECT impuesto_tipo_id, impuesto_tipo FROM gestion__impuestos_tipos
         WHERE es_retencion = 1 AND aplica_compra = 1 AND tabla_estado_registro_id = 1 ORDER BY impuesto_tipo", '');
    foreach ($impuestos as &$i) $i['es_iibb'] = retPracEsIibb($i['impuesto_tipo']) ? 1 : 0;
    unset($i);
    return [
        'impuestos'      => $impuestos,
        'jurisdicciones' => perIibbFilas($conexion,
            "SELECT jurisdiccion_id, jurisdiccion_nombre FROM gestion__jurisdicciones
             WHERE jurisdiccion_tipo_id = 2 AND tabla_estado_registro_id = 1 ORDER BY jurisdiccion_nombre", ''),
        'bases'          => retPracBases(),
        'tipos_minimo'   => RREG_TIPOS_MINIMO,
        'modos_minimo'   => RREG_MODOS_MINIMO,
    ];
}

function rregListar($conexion, $pagina_id) {
    $filas = perIibbFilas($conexion,
        "SELECT r.*, it.impuesto_tipo, j.jurisdiccion_nombre, er.estado_registro,
                (SELECT COUNT(*) FROM gestion__retenciones_regimenes_escalas e WHERE e.regimen_id = r.regimen_id) AS tramos
         FROM gestion__retenciones_regimenes r
         JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = r.impuesto_tipo_id
         LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = r.jurisdiccion_id
         LEFT JOIN conf__estados_registros er ON er.estado_registro_id = r.tabla_estado_registro_id
         WHERE r.empresa_id = 0
         ORDER BY it.impuesto_tipo, j.jurisdiccion_nombre, r.codigo, r.f_desde DESC", '');
    $funciones = permisos_filtrar_funciones($conexion, rregFunciones($conexion, $pagina_id));
    foreach ($filas as &$f) {
        $f['botones'] = [];
        foreach ($funciones as $fn) {
            if (intval($fn['tabla_estado_registro_origen_id']) === intval($f['tabla_estado_registro_id'])) $f['botones'][] = rregBoton($fn);
        }
    }
    unset($f);
    return $filas;
}

function rregObtener($conexion, $regimen_id) {
    $r = perIibbFila($conexion, "SELECT * FROM gestion__retenciones_regimenes WHERE regimen_id = ? AND empresa_id = 0", 'i', intval($regimen_id));
    if (!$r) return null;
    $r['escalas'] = perIibbFilas($conexion,
        "SELECT desde, hasta, importe_fijo, alicuota_excedente FROM gestion__retenciones_regimenes_escalas
         WHERE regimen_id = ? ORDER BY desde", 'i', intval($regimen_id));
    return $r;
}

function rregNumeroONull($v) {
    $v = trim((string)($v ?? ''));
    return $v === '' ? null : floatval(str_replace(',', '.', $v));
}

/** Valida y normaliza el formulario. Devuelve [datos, escalas] o lanza Exception con el motivo. */
function rregValidar($conexion, $data, $regimen_id) {
    $impuesto_id = intval($data['impuesto_tipo_id'] ?? 0);
    $imp = perIibbFila($conexion,
        "SELECT impuesto_tipo FROM gestion__impuestos_tipos WHERE impuesto_tipo_id = ? AND es_retencion = 1 AND aplica_compra = 1",
        'i', $impuesto_id);
    if (!$imp) throw new Exception('Elija un impuesto de retención practicada.');
    $jurisdiccion_id = null;
    if (retPracEsIibb($imp['impuesto_tipo'])) {
        $jurisdiccion_id = intval($data['jurisdiccion_id'] ?? 0);
        if (!perIibbFila($conexion, "SELECT 1 FROM gestion__jurisdicciones WHERE jurisdiccion_id = ? AND jurisdiccion_tipo_id = 2", 'i', $jurisdiccion_id)) {
            throw new Exception('Un régimen de IIBB necesita la jurisdicción (provincia).');
        }
    }
    $codigo = trim((string)($data['codigo'] ?? ''));
    $regimen = trim((string)($data['regimen'] ?? ''));
    if ($codigo === '' || mb_strlen($codigo) > 10) throw new Exception('El código es obligatorio (hasta 10 caracteres).');
    if ($regimen === '' || mb_strlen($regimen) > 150) throw new Exception('El concepto es obligatorio (hasta 150 caracteres).');
    $base = strtoupper(trim((string)($data['base_calculo'] ?? '')));
    if (!isset(retPracBases()[$base])) throw new Exception('Base de cálculo inválida.');
    $alic_ins = rregNumeroONull($data['alicuota_inscripto'] ?? null);
    $alic_no = rregNumeroONull($data['alicuota_no_inscripto'] ?? null);
    foreach ([$alic_ins, $alic_no] as $a) {
        if ($a !== null && ($a < 0 || $a > 100)) throw new Exception('Las alícuotas van de 0 a 100.');
    }
    $minimo = max(0, floatval($data['minimo_no_sujeto'] ?? 0));
    $minimo_ret = max(0, floatval($data['minimo_retencion'] ?? 0));
    $tipo_minimo = strtoupper(trim((string)($data['tipo_minimo'] ?? 'POR_PAGO')));
    $modo_minimo = strtoupper(trim((string)($data['modo_minimo'] ?? 'DEDUCIBLE')));
    if (!isset(RREG_TIPOS_MINIMO[$tipo_minimo])) throw new Exception('Tipo de mínimo inválido.');
    if (!isset(RREG_MODOS_MINIMO[$modo_minimo])) throw new Exception('Modo del mínimo inválido.');
    $f_desde = trim((string)($data['f_desde'] ?? ''));
    $f_hasta = trim((string)($data['f_hasta'] ?? '')) ?: null;
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_desde)) throw new Exception('La vigencia desde es obligatoria.');
    if ($f_hasta !== null && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_hasta) || $f_hasta < $f_desde)) {
        throw new Exception('La vigencia hasta tiene que ser posterior a la vigencia desde.');
    }
    $usa_escala = intval($data['usa_escala'] ?? 0) ? 1 : 0;

    $escalas = $data['escalas'] ?? [];
    if (is_string($escalas)) $escalas = json_decode($escalas, true);
    if (!is_array($escalas)) $escalas = [];
    $tramos = [];
    foreach ($escalas as $e) {
        $desde = rregNumeroONull($e['desde'] ?? null);
        if ($desde === null) continue;
        $hasta = rregNumeroONull($e['hasta'] ?? null);
        $fijo = rregNumeroONull($e['importe_fijo'] ?? null) ?? 0.0;
        $alic = rregNumeroONull($e['alicuota_excedente'] ?? null) ?? 0.0;
        if ($desde < 0 || ($hasta !== null && $hasta <= $desde)) throw new Exception("Tramo inválido: \"hasta\" tiene que ser mayor que \"desde\" ($desde).");
        if ($fijo < 0 || $alic < 0 || $alic > 100) throw new Exception("Tramo desde $desde: importe fijo o alícuota fuera de rango.");
        $tramos[] = ['desde' => $desde, 'hasta' => $hasta, 'importe_fijo' => $fijo, 'alicuota_excedente' => $alic];
    }
    usort($tramos, function ($a, $b) { return $a['desde'] <=> $b['desde']; });
    for ($i = 1; $i < count($tramos); $i++) {
        if ($tramos[$i - 1]['hasta'] === null || $tramos[$i - 1]['hasta'] > $tramos[$i]['desde']) {
            throw new Exception('Los tramos de la escala se superponen.');
        }
    }
    if ($usa_escala && !$tramos) throw new Exception('El régimen usa escala: cargue al menos un tramo.');

    // Un solo régimen activo por impuesto + jurisdicción + código en cada fecha
    $solapa = perIibbFila($conexion,
        "SELECT regimen_id FROM gestion__retenciones_regimenes
         WHERE empresa_id = 0 AND impuesto_tipo_id = ? AND COALESCE(jurisdiccion_id, 0) = ? AND codigo = ?
           AND regimen_id <> ? AND tabla_estado_registro_id = 1
           AND f_desde <= COALESCE(?, '9999-12-31') AND COALESCE(f_hasta, '9999-12-31') >= ? LIMIT 1",
        'iisiss', $impuesto_id, intval($jurisdiccion_id), $codigo, intval($regimen_id), $f_hasta, $f_desde);
    if ($solapa) throw new Exception("Ya hay un régimen $codigo activo con vigencia superpuesta (#{$solapa['regimen_id']}): cierre su vigencia primero.");

    return [[
        'impuesto_tipo_id' => $impuesto_id, 'jurisdiccion_id' => $jurisdiccion_id, 'codigo' => $codigo, 'regimen' => $regimen,
        'base_calculo' => $base, 'alicuota_inscripto' => $alic_ins, 'alicuota_no_inscripto' => $alic_no,
        'minimo_no_sujeto' => $minimo, 'tipo_minimo' => $tipo_minimo, 'modo_minimo' => $modo_minimo,
        'minimo_retencion' => $minimo_ret, 'usa_escala' => $usa_escala, 'f_desde' => $f_desde, 'f_hasta' => $f_hasta,
        'observaciones' => mb_substr(trim((string)($data['observaciones'] ?? '')), 0, 255) ?: null,
    ], $tramos];
}

function rregGuardar($conexion, $data, $usuario_id) {
    $regimen_id = intval($data['regimen_id'] ?? 0);
    error_log("=== INICIO rregGuardar ID: $regimen_id ===");
    try {
        if ($regimen_id > 0 && !rregObtener($conexion, $regimen_id)) throw new Exception('Régimen no encontrado.');
        [$d, $tramos] = rregValidar($conexion, $data, $regimen_id);
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
    $usuario_id = intval($usuario_id);
    mysqli_begin_transaction($conexion);
    try {
        if ($regimen_id > 0) {
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__retenciones_regimenes
                SET impuesto_tipo_id = ?, jurisdiccion_id = ?, codigo = ?, regimen = ?, base_calculo = ?, alicuota_inscripto = ?,
                    alicuota_no_inscripto = ?, minimo_no_sujeto = ?, tipo_minimo = ?, modo_minimo = ?, minimo_retencion = ?,
                    usa_escala = ?, f_desde = ?, f_hasta = ?, observaciones = ?, usuario_modificacion_id = ?
                WHERE regimen_id = ? AND empresa_id = 0");
            mysqli_stmt_bind_param($stmt, 'iisssdddssdisssii', $d['impuesto_tipo_id'], $d['jurisdiccion_id'], $d['codigo'], $d['regimen'],
                $d['base_calculo'], $d['alicuota_inscripto'], $d['alicuota_no_inscripto'], $d['minimo_no_sujeto'], $d['tipo_minimo'],
                $d['modo_minimo'], $d['minimo_retencion'], $d['usa_escala'], $d['f_desde'], $d['f_hasta'], $d['observaciones'],
                $usuario_id, $regimen_id);
        } else {
            $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__retenciones_regimenes
                (empresa_id, impuesto_tipo_id, jurisdiccion_id, codigo, regimen, base_calculo, alicuota_inscripto, alicuota_no_inscripto,
                 minimo_no_sujeto, tipo_minimo, modo_minimo, minimo_retencion, usa_escala, f_desde, f_hasta, observaciones,
                 tabla_estado_registro_id, usuario_id)
                VALUES (0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?)");
            mysqli_stmt_bind_param($stmt, 'iisssdddssdisssi', $d['impuesto_tipo_id'], $d['jurisdiccion_id'], $d['codigo'], $d['regimen'],
                $d['base_calculo'], $d['alicuota_inscripto'], $d['alicuota_no_inscripto'], $d['minimo_no_sujeto'], $d['tipo_minimo'],
                $d['modo_minimo'], $d['minimo_retencion'], $d['usa_escala'], $d['f_desde'], $d['f_hasta'], $d['observaciones'], $usuario_id);
        }
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        if ($regimen_id <= 0) $regimen_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt);

        $stmt = mysqli_prepare($conexion, "DELETE FROM gestion__retenciones_regimenes_escalas WHERE regimen_id = ?");
        mysqli_stmt_bind_param($stmt, 'i', $regimen_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
        $orden = 0;
        foreach ($tramos as $t) {
            $orden++;
            $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__retenciones_regimenes_escalas
                (regimen_id, desde, hasta, importe_fijo, alicuota_excedente, orden) VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt, 'iddddi', $regimen_id, $t['desde'], $t['hasta'], $t['importe_fijo'], $t['alicuota_excedente'], $orden);
            if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
        }
        mysqli_commit($conexion);
        return ['success' => true, 'data' => ['regimen_id' => $regimen_id], 'message' => 'Régimen guardado.'];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log('ERROR rregGuardar: ' . $e->getMessage());
        return ['success' => false, 'message' => 'No se pudo guardar: ' . $e->getMessage()];
    }
}

/** Transición de estado por el motor de funciones (inactivar / habilitar). */
function rregEjecutarAccion($conexion, $regimen_id, $accion_js, $pagina_id, $usuario_id) {
    $r = rregObtener($conexion, $regimen_id);
    if (!$r) return ['success' => false, 'message' => 'Régimen no encontrado.'];
    $fn = perIibbFila($conexion,
        "SELECT tabla_estado_registro_destino_id FROM conf__paginas_funciones
         WHERE pagina_id = ? AND tabla_estado_registro_origen_id = ? AND accion_js = ? AND tabla_estado_registro_id = 1 LIMIT 1",
        'iis', intval($pagina_id), intval($r['tabla_estado_registro_id']), (string)$accion_js);
    if (!$fn) return ['success' => false, 'message' => 'Acción no permitida en el estado actual.'];
    $destino = intval($fn['tabla_estado_registro_destino_id']);
    $id = intval($regimen_id);
    $usuario_id = intval($usuario_id);
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__retenciones_regimenes SET tabla_estado_registro_id = ?, usuario_modificacion_id = ? WHERE regimen_id = ? AND empresa_id = 0");
    mysqli_stmt_bind_param($stmt, 'iii', $destino, $usuario_id, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok ? ['success' => true, 'message' => 'Estado actualizado.'] : ['success' => false, 'message' => 'No se pudo actualizar el estado.'];
}

/** Regímenes activos y vigentes a $fecha para un impuesto (y jurisdicción en IIBB): combos de config y proveedor. */
function rregVigentes($conexion, $empresa_id, $impuesto_tipo_id, $jurisdiccion_id, $fecha) {
    return perIibbFilas($conexion,
        "SELECT regimen_id, codigo, regimen, alicuota_inscripto, alicuota_no_inscripto, tipo_minimo, f_desde, f_hasta
         FROM gestion__retenciones_regimenes
         WHERE empresa_id IN (0, ?) AND impuesto_tipo_id = ? AND COALESCE(jurisdiccion_id, 0) = ? AND tabla_estado_registro_id = 1
           AND (f_hasta IS NULL OR f_hasta >= ?)
         ORDER BY codigo, f_desde DESC",
        'iiis', intval($empresa_id), intval($impuesto_tipo_id), intval($jurisdiccion_id), $fecha);
}
