<?php
/**
 * Monedas de la empresa (gestion__monedas, tabla 441, diccionario Activo / Inactivo).
 * Reglas: código único por empresa; una sola moneda base, que cotiza 1 y no se cambia una vez que la empresa tiene
 * comprobantes; no se inactiva la base ni una moneda que usan cuentas de tesorería activas; el código y los
 * decimales de una moneda con comprobantes o cuentas de tesorería no se cambian.
 * Script de páginas y permisos: _local/sql/mantenimiento/20261019_monedas_tesoreria_cuentas_01_paginas.sql
 */
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
$conexion = $conn;

function monFila($conexion, $sql, $tipos = '', ...$params)
{
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) throw new Exception('Monedas: ' . mysqli_error($conexion));
    if ($tipos !== '') mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function monFilas($conexion, $sql, $tipos = '', ...$params)
{
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) throw new Exception('Monedas: ' . mysqli_error($conexion));
    if ($tipos !== '') mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

/** Columna con el nombre del estado en conf__estados_registros (varía según la versión de la tabla). */
function monColumnaEstado($conexion)
{
    $cols = [];
    $res = mysqli_query($conexion, "SHOW COLUMNS FROM conf__estados_registros");
    while ($res && ($r = mysqli_fetch_assoc($res))) $cols[] = $r['Field'];
    foreach (['estado_registro', 'nombre_estado', 'descripcion'] as $c) {
        if (in_array($c, $cols, true)) return $c;
    }
    return 'estado_registro_id';
}

/** Botones del motor para un estado (conf__paginas_funciones de la página). */
function monBotones($conexion, $pagina_id, $estado_id)
{
    static $cache = [];
    $clave = intval($pagina_id) . '|' . intval($estado_id);
    if (isset($cache[$clave])) return $cache[$clave];
    // Por estado (origen) y, además, solo las funciones asignadas al perfil del usuario
    return $cache[$clave] = permisos_filtrar_funciones($conexion, monFilas($conexion,
        "SELECT pf.pagina_funcion_id, pf.pagina_id, pf.nombre_funcion, pf.accion_js, pf.descripcion, pf.tabla_estado_registro_destino_id AS estado_destino_id,
                IF(pf.tabla_estado_registro_destino_id <> pf.tabla_estado_registro_origen_id, 1, 0) AS es_confirmable,
                i.icono_clase, c.color_clase, c.bg_clase, c.text_clase
         FROM conf__paginas_funciones pf
         LEFT JOIN conf__iconos i ON i.icono_id = pf.icono_id
         LEFT JOIN conf__colores c ON c.color_id = pf.color_id
         WHERE pf.pagina_id = ? AND pf.tabla_estado_registro_origen_id = ? AND pf.tabla_estado_registro_id = 1
         ORDER BY pf.orden", 'ii', intval($pagina_id), intval($estado_id)));
}

function monBotonAgregar($conexion, $pagina_id)
{
    $b = monBotones($conexion, $pagina_id, 0);
    return $b[0] ?? null;
}

/** Uso de la moneda: comprobantes y cuentas de tesorería (activas o no). */
function monUso($conexion, $empresa_id, $moneda_id)
{
    return [
        'comprobantes' => intval(monFila($conexion, "SELECT COUNT(*) n FROM gestion__comprobantes WHERE empresa_id = ? AND moneda_id = ?",
                                         'ii', $empresa_id, $moneda_id)['n'] ?? 0),
        'cuentas' => intval(monFila($conexion, "SELECT COUNT(*) n FROM gestion__tesoreria_cuentas WHERE empresa_id = ? AND moneda_id = ?",
                                    'ii', $empresa_id, $moneda_id)['n'] ?? 0),
        'cuentas_activas' => intval(monFila($conexion, "SELECT COUNT(*) n FROM gestion__tesoreria_cuentas
                                                        WHERE empresa_id = ? AND moneda_id = ? AND tabla_estado_registro_id = 1",
                                            'ii', $empresa_id, $moneda_id)['n'] ?? 0),
    ];
}

function monListar($conexion, $empresa_id, $pagina_id)
{
    $col = monColumnaEstado($conexion);
    $rows = monFilas($conexion,
        "SELECT m.*, er.$col AS estado_registro, er.codigo_estandar, c.bg_clase, c.text_clase,
                (SELECT COUNT(*) FROM gestion__tesoreria_cuentas t WHERE t.empresa_id = m.empresa_id AND t.moneda_id = m.moneda_id
                   AND t.tabla_estado_registro_id = 1) AS cuentas_tesoreria
         FROM gestion__monedas m
         LEFT JOIN conf__estados_registros er ON er.estado_registro_id = m.tabla_estado_registro_id
         LEFT JOIN conf__colores c ON c.color_id = er.color_id
         WHERE m.empresa_id = ?
         ORDER BY m.es_moneda_base DESC, m.orden, m.codigo", 'i', intval($empresa_id));
    $botones = [];
    foreach ($rows as &$r) {
        $e = intval($r['tabla_estado_registro_id']);
        $botones[$e] = $botones[$e] ?? monBotones($conexion, $pagina_id, $e);
        $r['botones'] = $botones[$e];
    }
    return $rows;
}

function monObtener($conexion, $empresa_id, $moneda_id)
{
    $m = monFila($conexion, "SELECT * FROM gestion__monedas WHERE moneda_id = ? AND empresa_id = ?", 'ii', intval($moneda_id), intval($empresa_id));
    if ($m) $m['uso'] = monUso($conexion, intval($empresa_id), intval($moneda_id));
    return $m;
}

/** Alta o modificación. Devuelve el id; lanza Exception con el motivo si no se puede. */
function monGuardar($conexion, $empresa_id, array $d)
{
    $empresa_id = intval($empresa_id);
    $id = intval($d['moneda_id'] ?? 0);
    $codigo = strtoupper(trim((string)($d['codigo'] ?? '')));
    $nombre = trim((string)($d['moneda'] ?? ''));
    $simbolo = trim((string)($d['simbolo'] ?? ''));
    $decimales = intval($d['cantidad_decimales'] ?? 2);
    $cotizacion = floatval(str_replace(',', '.', (string)($d['cotizacion_actual'] ?? '1')));
    $edita_cot = !empty($d['permite_editar_cotizacion']) ? 1 : 0;
    $orden = intval($d['orden'] ?? 0);
    $base = !empty($d['es_moneda_base']) ? 1 : 0;

    if (!preg_match('/^[A-Z0-9]{1,10}$/', $codigo)) throw new Exception('El código es obligatorio: letras y números, hasta 10 (p. ej. ARS, USD).');
    if ($nombre === '' || mb_strlen($nombre) > 100) throw new Exception('El nombre es obligatorio (hasta 100 caracteres).');
    if ($simbolo === '' || mb_strlen($simbolo) > 10) throw new Exception('El símbolo es obligatorio (hasta 10 caracteres).');
    if ($decimales < 0 || $decimales > 6) throw new Exception('Los decimales van de 0 a 6.');
    if ($cotizacion <= 0) throw new Exception('La cotización tiene que ser mayor que cero.');

    $actual = $id > 0 ? monFila($conexion, "SELECT * FROM gestion__monedas WHERE moneda_id = ? AND empresa_id = ?", 'ii', $id, $empresa_id) : null;
    if ($id > 0 && !$actual) throw new Exception('Moneda no encontrada.');
    if (monFila($conexion, "SELECT 1 FROM gestion__monedas WHERE empresa_id = ? AND codigo = ? AND moneda_id <> ?", 'isi', $empresa_id, $codigo, $id)) {
        throw new Exception("La empresa ya tiene la moneda $codigo.");
    }
    $base_actual = monFila($conexion, "SELECT moneda_id, codigo FROM gestion__monedas WHERE empresa_id = ? AND es_moneda_base = 1 AND tabla_estado_registro_id = 1
                                       ORDER BY moneda_id LIMIT 1", 'i', $empresa_id);
    if (!$base_actual) {
        $base = 1;   // la primera moneda de la empresa es la base
    }
    $cambia_base = $base === 1 && $base_actual && intval($base_actual['moneda_id']) !== $id;
    if ($actual && intval($actual['es_moneda_base']) === 1 && $base === 0) {
        throw new Exception('La empresa necesita una moneda base: marque otra como base en lugar de desmarcar esta.');
    }
    if ($cambia_base) {
        $n = intval(monFila($conexion, "SELECT COUNT(*) n FROM gestion__comprobantes WHERE empresa_id = ?", 'i', $empresa_id)['n'] ?? 0);
        if ($n > 0) throw new Exception("La moneda base ({$base_actual['codigo']}) no se cambia: la empresa ya tiene comprobantes registrados en ella.");
    }
    if ($base === 1) {
        $cotizacion = 1.0;   // la base cotiza 1 contra sí misma
        $edita_cot = 0;
    }
    if ($actual) {
        $uso = monUso($conexion, $empresa_id, $id);
        if (($uso['comprobantes'] + $uso['cuentas']) > 0 && ($actual['codigo'] !== $codigo || intval($actual['cantidad_decimales']) !== $decimales)) {
            throw new Exception('El código y los decimales no se cambian: la moneda ya tiene comprobantes o cuentas de tesorería.');
        }
    }

    mysqli_begin_transaction($conexion);
    try {
        if ($cambia_base) {
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__monedas SET es_moneda_base = 0 WHERE empresa_id = ? AND es_moneda_base = 1");
            mysqli_stmt_bind_param($stmt, 'i', $empresa_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
        }
        if ($actual) {
            $stmt = mysqli_prepare($conexion, "UPDATE gestion__monedas SET codigo = ?, moneda = ?, simbolo = ?, es_moneda_base = ?, cantidad_decimales = ?,
                                                      cotizacion_actual = ?, permite_editar_cotizacion = ?, orden = ?
                                               WHERE moneda_id = ? AND empresa_id = ?");
            mysqli_stmt_bind_param($stmt, 'sssiidiiii', $codigo, $nombre, $simbolo, $base, $decimales, $cotizacion, $edita_cot, $orden, $id, $empresa_id);
        } else {
            $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__monedas (empresa_id, codigo, moneda, simbolo, es_moneda_base, cantidad_decimales,
                                                      cotizacion_actual, permite_editar_cotizacion, orden, tabla_estado_registro_id)
                                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            mysqli_stmt_bind_param($stmt, 'isssiidii', $empresa_id, $codigo, $nombre, $simbolo, $base, $decimales, $cotizacion, $edita_cot, $orden);
        }
        if (!mysqli_stmt_execute($stmt)) throw new Exception('No se pudo guardar la moneda: ' . mysqli_stmt_error($stmt));
        if (!$actual) $id = intval(mysqli_insert_id($conexion));
        mysqli_stmt_close($stmt);
        mysqli_commit($conexion);
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        throw $e;
    }
    error_log("=== monGuardar empresa $empresa_id moneda $id $codigo" . ($base ? ' (base)' : '') . " ===");
    return $id;
}

/** Transición del motor (inactivar / habilitar). */
function monEjecutarAccion($conexion, $empresa_id, $pagina_id, $moneda_id, $accion_js)
{
    $empresa_id = intval($empresa_id);
    $m = monFila($conexion, "SELECT * FROM gestion__monedas WHERE moneda_id = ? AND empresa_id = ?", 'ii', intval($moneda_id), $empresa_id);
    if (!$m) throw new Exception('Moneda no encontrada.');
    $f = monFila($conexion, "SELECT tabla_estado_registro_destino_id destino FROM conf__paginas_funciones
                             WHERE pagina_id = ? AND accion_js = ? AND tabla_estado_registro_origen_id = ? AND tabla_estado_registro_id = 1 LIMIT 1",
                 'isi', intval($pagina_id), $accion_js, intval($m['tabla_estado_registro_id']));
    if (!$f) throw new Exception('La acción no corresponde al estado de la moneda.');
    $destino = intval($f['destino']);
    if ($destino === 2) {
        if (intval($m['es_moneda_base']) === 1) throw new Exception('La moneda base no se inactiva.');
        $uso = monUso($conexion, $empresa_id, intval($m['moneda_id']));
        if ($uso['cuentas_activas'] > 0) throw new Exception("La usan {$uso['cuentas_activas']} cuentas de tesorería activas: inactívelas antes.");
    }
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__monedas SET tabla_estado_registro_id = ? WHERE moneda_id = ? AND empresa_id = ?");
    $mid = intval($m['moneda_id']);
    mysqli_stmt_bind_param($stmt, 'iii', $destino, $mid, $empresa_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $destino;
}
