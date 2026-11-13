<?php
/**
 * Tablero entre clientes (F2): una fila por cliente activo con vencimientos, tareas, IVA del mes anterior y vinculación.
 * Estados: vencimientos (tabla 414) 28 presentado, 35 pagado, 38 anulado = cerrados; tareas (tabla 419) 21 finalizada y 26 cancelada = cerradas (17 / 18 / 19 abiertas);
 * liquidaciones de IVA 5 = vigente.
 */

declare(strict_types=1);

require_once __DIR__ . '/estudio_clientes_model.php';

function estudio_tablero_clientes(mysqli $conexion, int $estudio_id): array
{
    $periodo_ant = (int) date('Ym', strtotime('first day of last month'));
    $periodo_ant_txt = date('Y-m', strtotime('first day of last month'));

    // origen_datos existe solo si se ejecutó 20261105_estudio_clientes_origen_datos_01.sql
    $tiene_origen = false;
    $r = mysqli_query($conexion, "SHOW COLUMNS FROM estudio__clientes LIKE 'origen_datos'");
    if ($r && mysqli_num_rows($r) > 0) {
        $tiene_origen = true;
    }
    $col_origen = $tiene_origen ? "c.origen_datos" : "'LOCAL'";
    $col_nivel = $tiene_origen ? "c.nivel_servicio" : "IF(c.empresa_vinculada_id > 0, 'CONTABLE', 'FISCAL')";

    $sql = "SELECT c.cliente_id, c.razon_social, c.cuit, c.empresa_vinculada_id, $col_origen AS origen_datos, $col_nivel AS nivel_servicio,
                   (SELECT COUNT(*) FROM estudio__vencimientos_generados v
                     WHERE v.empresa_id = c.empresa_id AND v.cliente_id = c.cliente_id
                       AND v.tabla_estado_registro_id NOT IN (28, 35, 38) AND v.f_limite < CURDATE()) AS venc_vencidos,
                   (SELECT MIN(v.f_limite) FROM estudio__vencimientos_generados v
                     WHERE v.empresa_id = c.empresa_id AND v.cliente_id = c.cliente_id
                       AND v.tabla_estado_registro_id NOT IN (28, 35, 38) AND v.f_limite >= CURDATE()) AS proximo_venc,
                   (SELECT io.codigo_oficial FROM estudio__vencimientos_generados v
                     JOIN estudio__impuestos_obligaciones io ON io.impuesto_obligacion_id = v.impuesto_obligacion_id
                     WHERE v.empresa_id = c.empresa_id AND v.cliente_id = c.cliente_id
                       AND v.tabla_estado_registro_id NOT IN (28, 35, 38) AND v.f_limite >= CURDATE()
                     ORDER BY v.f_limite LIMIT 1) AS proximo_impuesto,
                   (SELECT COUNT(*) FROM estudio__tareas t
                     WHERE t.empresa_id = c.empresa_id AND t.cliente_id = c.cliente_id
                       AND t.tabla_estado_registro_id NOT IN (21, 26) AND t.f_limite < CURDATE()) AS tareas_atrasadas
            FROM estudio__clientes c
            WHERE c.empresa_id = ? AND c.tabla_estado_registro_id = 1
            ORDER BY c.razon_social";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $estudio_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $filas = [];
    while ($f = mysqli_fetch_assoc($res)) {
        $filas[] = $f;
    }
    mysqli_stmt_close($stmt);

    foreach ($filas as &$f) {
        $erp = (int) ($f['empresa_vinculada_id'] ?? 0);
        $f['venc_vencidos'] = (int) $f['venc_vencidos'];
        $f['tareas_atrasadas'] = (int) $f['tareas_atrasadas'];
        $f['iva_periodo'] = $periodo_ant_txt;
        $f['iva_saldo'] = null;
        $f['vinculacion'] = null;

        if (($f['nivel_servicio'] ?? '') === 'PADRON') {
            $f['modo'] = 'PADRON';
            $f['iva_estado'] = 'NO_APLICA';
        } elseif ($f['origen_datos'] === 'REMOTO') {
            $f['modo'] = 'REMOTO';
            $f['iva_estado'] = 'REMOTO';
        } elseif ($f['origen_datos'] === 'EXTERNO') {
            $f['modo'] = 'EXTERNO';
            $f['iva_estado'] = 'EXTERNO';
        } elseif ($erp > 0) {
            $f['modo'] = 'ERP';
            $ok = estudio_cliente_tiene_vinculacion($conexion, $estudio_id, $erp, 'impuestos');
            $f['vinculacion'] = $ok;
            if (!$ok) {
                $f['iva_estado'] = 'SIN_PERMISO';
            } else {
                $s = mysqli_prepare($conexion, "SELECT saldo_a_pagar, saldo_a_favor FROM gestion__iva_posiciones
                                                WHERE empresa_id = ? AND periodo = ? AND tabla_estado_registro_id = 5 LIMIT 1");
                mysqli_stmt_bind_param($s, "is", $erp, $periodo_ant_txt);
                mysqli_stmt_execute($s);
                $p = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
                mysqli_stmt_close($s);
                $f['iva_estado'] = $p ? 'LIQUIDADO' : 'PENDIENTE';
                if ($p) {
                    $f['iva_saldo'] = (float) $p['saldo_a_pagar'] > 0 ? (float) $p['saldo_a_pagar'] : -(float) $p['saldo_a_favor'];
                }
            }
        } else {
            $f['modo'] = 'MANUAL';
            $p = null;
            try {
                $cid = (int) $f['cliente_id'];
                $s = mysqli_prepare($conexion, "SELECT saldo_a_pagar, saldo_a_favor FROM estudio__minigestion_liquidaciones_iva
                                                WHERE empresa_id = ? AND cliente_id = ? AND periodo = ? AND tabla_estado_registro_id = 5 LIMIT 1");
                mysqli_stmt_bind_param($s, "iii", $estudio_id, $cid, $periodo_ant);
                mysqli_stmt_execute($s);
                $p = mysqli_fetch_assoc(mysqli_stmt_get_result($s));
                mysqli_stmt_close($s);
            } catch (Throwable $e) {
                $p = null;
            }
            $f['iva_estado'] = $p ? 'LIQUIDADO' : 'PENDIENTE';
            if ($p) {
                $f['iva_saldo'] = (float) $p['saldo_a_pagar'] > 0 ? (float) $p['saldo_a_pagar'] : -(float) $p['saldo_a_favor'];
            }
        }

        // Semáforo: rojo = algo vencido o sin permiso; amarillo = IVA del mes anterior sin liquidar; verde = al día
        if ($f['venc_vencidos'] > 0 || $f['tareas_atrasadas'] > 0 || $f['iva_estado'] === 'SIN_PERMISO') {
            $f['semaforo'] = 'rojo';
        } elseif ($f['iva_estado'] === 'PENDIENTE') { // NO_APLICA / REMOTO / EXTERNO no generan alerta de IVA
            $f['semaforo'] = 'amarillo';
        } else {
            $f['semaforo'] = 'verde';
        }
    }
    unset($f);

    return $filas;
}
