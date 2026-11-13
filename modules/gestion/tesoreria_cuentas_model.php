<?php
/**
 * Cuentas de tesorería de la empresa (gestion__tesoreria_cuentas, tabla 271, diccionario Activo / Inactivo):
 * cajas (por sucursal), cuentas bancarias, valores en cartera y cupones de tarjeta.
 * Reglas: todo de la empresa (sucursal, moneda, cuenta contable imputable); BANCO con banco y tipo de cuenta;
 * CBU de 22 dígitos, único en la empresa; el saldo lo mantiene el libro de movimientos y no se edita; con
 * movimientos no cambian el tipo ni la moneda; se inactiva con saldo 0 y sin medios de pago activos que la tengan fija.
 * Script de páginas y permisos: _local/sql/mantenimiento/20261019_monedas_tesoreria_cuentas_01_paginas.sql
 */
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/formato.php';
require_once __DIR__ . '/monedas_model.php';   // monFila / monFilas / monBotones / monColumnaEstado
$conexion = $conn;

const TC_TIPOS = ['CAJA' => 'Caja', 'BANCO' => 'Banco', 'VALORES' => 'Valores en cartera', 'CUPONES' => 'Cupones de tarjeta'];
const TC_BANCO_CUENTA_TIPOS = ['CC' => 'Cuenta corriente', 'CA' => 'Caja de ahorro', 'VIRTUAL' => 'Cuenta virtual'];

function tcListar($conexion, $empresa_id, $pagina_id)
{
    $col = monColumnaEstado($conexion);
    $rows = monFilas($conexion,
        "SELECT t.*, s.sucursal_nombre, m.codigo AS moneda_codigo, m.simbolo, b.banco, cc.codigo AS cont_cuenta_codigo,
                cc.nombre AS cont_cuenta_nombre, er.$col AS estado_registro, er.codigo_estandar, c.bg_clase, c.text_clase,
                (SELECT COUNT(*) FROM gestion__tesoreria_movimientos tm WHERE tm.tesoreria_cuenta_id = t.tesoreria_cuenta_id) AS movimientos
         FROM gestion__tesoreria_cuentas t
         LEFT JOIN gestion__sucursales s ON s.sucursal_id = t.sucursal_id
         LEFT JOIN gestion__monedas m ON m.moneda_id = t.moneda_id
         LEFT JOIN conf__bancos b ON b.banco_id = t.banco_id
         LEFT JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = t.cont_cuenta_id
         LEFT JOIN conf__estados_registros er ON er.estado_registro_id = t.tabla_estado_registro_id
         LEFT JOIN conf__colores c ON c.color_id = er.color_id
         WHERE t.empresa_id = ?
         ORDER BY FIELD(t.cuenta_tipo, 'CAJA', 'BANCO', 'VALORES', 'CUPONES'), t.orden, t.tesoreria_cuenta", 'i', intval($empresa_id));
    $botones = [];
    foreach ($rows as &$r) {
        $e = intval($r['tabla_estado_registro_id']);
        $botones[$e] = $botones[$e] ?? monBotones($conexion, $pagina_id, $e);
        $r['botones'] = $botones[$e];
    }
    return $rows;
}

function tcObtener($conexion, $empresa_id, $id)
{
    $t = monFila($conexion, "SELECT t.*, (SELECT COUNT(*) FROM gestion__tesoreria_movimientos tm WHERE tm.tesoreria_cuenta_id = t.tesoreria_cuenta_id) AS movimientos
                             FROM gestion__tesoreria_cuentas t WHERE t.tesoreria_cuenta_id = ? AND t.empresa_id = ?", 'ii', intval($id), intval($empresa_id));
    return $t;
}

/** Combos de la pantalla: todo de la empresa. */
function tcMaestros($conexion, $empresa_id)
{
    $e = intval($empresa_id);
    return [
        'tipos' => TC_TIPOS,
        'banco_cuenta_tipos' => TC_BANCO_CUENTA_TIPOS,
        'sucursales' => monFilas($conexion, "SELECT sucursal_id, sucursal_nombre FROM gestion__sucursales WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                                             ORDER BY sucursal_nombre", 'i', $e),
        'monedas' => monFilas($conexion, "SELECT moneda_id, codigo, moneda, es_moneda_base FROM gestion__monedas WHERE empresa_id = ? AND tabla_estado_registro_id = 1
                                          ORDER BY es_moneda_base DESC, orden, codigo", 'i', $e),
        'bancos' => monFilas($conexion, "SELECT banco_id, banco, codigo_bcra FROM conf__bancos WHERE tabla_estado_registro_id = 1 ORDER BY orden, banco"),
        'cont_cuentas' => monFilas($conexion, "SELECT cont_cuenta_id, codigo, nombre, es_caja_banco FROM gestion__cont_cuentas
                                               WHERE empresa_id = ? AND es_imputable = 1 AND tabla_estado_registro_id = 1 ORDER BY codigo", 'i', $e),
    ];
}

/** Alta o modificación. Devuelve el id; lanza Exception con el motivo si no se puede. */
function tcGuardar($conexion, $empresa_id, $usuario_id, array $d)
{
    $empresa_id = intval($empresa_id);
    $id = intval($d['tesoreria_cuenta_id'] ?? 0);
    $tipo = strtoupper(trim((string)($d['cuenta_tipo'] ?? '')));
    $nombre = trim((string)($d['tesoreria_cuenta'] ?? ''));
    $codigo = strtoupper(trim((string)($d['codigo'] ?? '')));
    $moneda_id = intval($d['moneda_id'] ?? 0);
    $sucursal_id = intval($d['sucursal_id'] ?? 0);
    $cont_cuenta_id = intval($d['cont_cuenta_id'] ?? 0);
    $banco_id = intval($d['banco_id'] ?? 0);
    $banco_cuenta_tipo = strtoupper(trim((string)($d['banco_cuenta_tipo'] ?? '')));
    $numero = trim((string)($d['numero'] ?? ''));
    $cbu = preg_replace('/\D/', '', (string)($d['cbu'] ?? ''));
    $alias = trim((string)($d['alias'] ?? ''));
    $negativo = !empty($d['permite_saldo_negativo']) ? 1 : 0;
    $orden = intval($d['orden'] ?? 1);

    if (!isset(TC_TIPOS[$tipo])) throw new Exception('Elija el tipo de cuenta (caja, banco, valores o cupones).');
    if ($nombre === '' || mb_strlen($nombre) > 100) throw new Exception('El nombre es obligatorio (hasta 100 caracteres).');
    if ($codigo !== '' && !preg_match('/^[A-Z0-9_\-]{1,20}$/', $codigo)) throw new Exception('El código admite letras, números, guion y guion bajo (hasta 20).');
    if (!monFila($conexion, "SELECT 1 FROM gestion__monedas WHERE moneda_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1", 'ii', $moneda_id, $empresa_id)) {
        throw new Exception('Elija una moneda activa de la empresa (Tesorería > Monedas).');
    }
    if ($sucursal_id > 0 && !monFila($conexion, "SELECT 1 FROM gestion__sucursales WHERE sucursal_id = ? AND empresa_id = ?", 'ii', $sucursal_id, $empresa_id)) {
        throw new Exception('La sucursal no es de la empresa.');
    }
    if ($tipo === 'CAJA' && $sucursal_id <= 0) throw new Exception('Una caja pertenece a una sucursal: elíjala.');
    $cta = monFila($conexion, "SELECT es_imputable FROM gestion__cont_cuentas WHERE cont_cuenta_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1",
                   'ii', $cont_cuenta_id, $empresa_id);
    if (!$cta) throw new Exception('Elija la cuenta contable (del plan de cuentas de la empresa).');
    if (intval($cta['es_imputable']) !== 1) throw new Exception('La cuenta contable tiene que ser imputable.');
    if ($tipo === 'BANCO') {
        if (!monFila($conexion, "SELECT 1 FROM conf__bancos WHERE banco_id = ?", 'i', $banco_id)) throw new Exception('Una cuenta bancaria lleva el banco.');
        if (!isset(TC_BANCO_CUENTA_TIPOS[$banco_cuenta_tipo])) throw new Exception('Elija el tipo de cuenta bancaria (corriente, caja de ahorro o virtual).');
        if ($cbu !== '' && strlen($cbu) !== 22) throw new Exception('El CBU / CVU tiene 22 dígitos.');
        if (mb_strlen($numero) > 50 || mb_strlen($alias) > 50) throw new Exception('Número y alias: hasta 50 caracteres.');
    } else {
        $banco_id = 0;
        $banco_cuenta_tipo = $numero = $cbu = $alias = '';
    }
    if ($codigo !== '' && monFila($conexion, "SELECT 1 FROM gestion__tesoreria_cuentas WHERE empresa_id = ? AND codigo = ? AND tesoreria_cuenta_id <> ?",
                                  'isi', $empresa_id, $codigo, $id)) {
        throw new Exception("La empresa ya tiene una cuenta de tesorería con el código $codigo.");
    }
    if ($cbu !== '' && monFila($conexion, "SELECT 1 FROM gestion__tesoreria_cuentas WHERE empresa_id = ? AND cbu = ? AND tesoreria_cuenta_id <> ?",
                               'isi', $empresa_id, $cbu, $id)) {
        throw new Exception('La empresa ya tiene una cuenta con ese CBU.');
    }
    $actual = $id > 0 ? tcObtener($conexion, $empresa_id, $id) : null;
    if ($id > 0 && !$actual) throw new Exception('Cuenta de tesorería no encontrada.');
    if ($actual && intval($actual['movimientos']) > 0 && ($actual['cuenta_tipo'] !== $tipo || intval($actual['moneda_id']) !== $moneda_id)) {
        throw new Exception('La cuenta ya tiene movimientos: no se cambian el tipo ni la moneda (cree otra cuenta).');
    }

    $suc = $sucursal_id > 0 ? $sucursal_id : null;
    $ban = $banco_id > 0 ? $banco_id : null;
    $bct = $banco_cuenta_tipo !== '' ? $banco_cuenta_tipo : null;
    $num = $numero !== '' ? $numero : null;
    $cb = $cbu !== '' ? $cbu : null;
    $al = $alias !== '' ? $alias : null;
    $cod = $codigo !== '' ? $codigo : null;
    $uid = intval($usuario_id);
    if ($actual) {
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__tesoreria_cuentas SET sucursal_id = ?, cuenta_tipo = ?, codigo = ?, tesoreria_cuenta = ?, moneda_id = ?,
                                                  banco_id = ?, banco_cuenta_tipo = ?, numero = ?, cbu = ?, alias = ?, cont_cuenta_id = ?,
                                                  permite_saldo_negativo = ?, orden = ?, usuario_modificacion_id = ?
                                           WHERE tesoreria_cuenta_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmt, 'isssiissssiiiiii', $suc, $tipo, $cod, $nombre, $moneda_id, $ban, $bct, $num, $cb, $al, $cont_cuenta_id,
                               $negativo, $orden, $uid, $id, $empresa_id);
    } else {
        $stmt = mysqli_prepare($conexion, "INSERT INTO gestion__tesoreria_cuentas (empresa_id, sucursal_id, cuenta_tipo, codigo, tesoreria_cuenta, moneda_id,
                                                  banco_id, banco_cuenta_tipo, numero, cbu, alias, cont_cuenta_id, saldo_actual, permite_saldo_negativo, orden,
                                                  tabla_estado_registro_id, usuario_id)
                                           VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?, 1, ?)");
        mysqli_stmt_bind_param($stmt, 'iisssiissssiiii', $empresa_id, $suc, $tipo, $cod, $nombre, $moneda_id, $ban, $bct, $num, $cb, $al, $cont_cuenta_id,
                               $negativo, $orden, $uid);
    }
    if (!mysqli_stmt_execute($stmt)) throw new Exception('No se pudo guardar la cuenta: ' . mysqli_stmt_error($stmt));
    if (!$actual) $id = intval(mysqli_insert_id($conexion));
    mysqli_stmt_close($stmt);
    error_log("=== tcGuardar empresa $empresa_id cuenta $id $tipo '$nombre' ===");
    return $id;
}

/** Transición del motor (inactivar / habilitar). */
function tcEjecutarAccion($conexion, $empresa_id, $pagina_id, $id, $accion_js, $usuario_id)
{
    $empresa_id = intval($empresa_id);
    $t = tcObtener($conexion, $empresa_id, $id);
    if (!$t) throw new Exception('Cuenta de tesorería no encontrada.');
    $f = monFila($conexion, "SELECT tabla_estado_registro_destino_id destino FROM conf__paginas_funciones
                             WHERE pagina_id = ? AND accion_js = ? AND tabla_estado_registro_origen_id = ? AND tabla_estado_registro_id = 1 LIMIT 1",
                 'isi', intval($pagina_id), $accion_js, intval($t['tabla_estado_registro_id']));
    if (!$f) throw new Exception('La acción no corresponde al estado de la cuenta.');
    $destino = intval($f['destino']);
    if ($destino === 2) {
        if (abs(floatval($t['saldo_actual'])) >= 0.005) {
            throw new Exception('La cuenta tiene saldo (' . fmt_numero(floatval($t['saldo_actual']), 2) . '): transfiéralo antes de inactivarla.');
        }
        $medios = monFilas($conexion, "SELECT codigo FROM gestion__medios_pago WHERE empresa_id = ? AND tesoreria_cuenta_id = ? AND tabla_estado_registro_id = 1",
                           'ii', $empresa_id, intval($t['tesoreria_cuenta_id']));
        if ($medios) throw new Exception('La tienen como cuenta fija los medios de pago ' . implode(', ', array_column($medios, 'codigo')) . ': cámbielos antes.');
    } elseif (!monFila($conexion, "SELECT 1 FROM gestion__monedas WHERE moneda_id = ? AND tabla_estado_registro_id = 1", 'i', intval($t['moneda_id']))) {
        throw new Exception('Su moneda está inactiva: habilítela antes (Tesorería > Monedas).');
    }
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__tesoreria_cuentas SET tabla_estado_registro_id = ?, usuario_modificacion_id = ?
                                       WHERE tesoreria_cuenta_id = ? AND empresa_id = ?");
    $tid = intval($t['tesoreria_cuenta_id']);
    $uid = intval($usuario_id);
    mysqli_stmt_bind_param($stmt, 'iiii', $destino, $uid, $tid, $empresa_id);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $destino;
}
