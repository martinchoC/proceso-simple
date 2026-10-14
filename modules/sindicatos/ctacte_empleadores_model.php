<?php
declare(strict_types=1);

/**
 * modules/sindicatos/ctacte_empleadores_model.php
 * Capa de datos para la Página 1032 (Cuenta Corriente de Empleadores).
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/accesos_empleadores.php';

function sindi_obtener_ctacte_empleador(mysqli $conexion, int $empresa_id, int $usuario_id, int $empleador_id): array
{
    AccesosEmpleadoresHelper::exigirAcceso($conexion, $usuario_id, $empresa_id, $empleador_id);

    // Obtener entidad_id
    $sqlE = "SELECT emp.empleador_id, ent.entidad_id, ent.entidad_nombre AS razon_social, ent.cuit, ent.domicilio_legal
             FROM sindi__empleadores emp
             INNER JOIN gestion__entidades ent ON ent.entidad_id = emp.entidad_id
             WHERE emp.empresa_id = ? AND emp.empleador_id = ? LIMIT 1";
    $stmtE = mysqli_prepare($conexion, $sqlE);
    if (!$stmtE) return [];
    mysqli_stmt_bind_param($stmtE, "ii", $empresa_id, $empleador_id);
    mysqli_stmt_execute($stmtE);
    $empleador = mysqli_fetch_assoc(mysqli_stmt_get_result($stmtE));
    mysqli_stmt_close($stmtE);
    if (!$empleador) return [];

    $entidad_id = (int)$empleador['entidad_id'];

    // Movimientos de cuenta corriente en gestion__comprobantes
    $sqlMov = "SELECT c.comprobante_id, c.comprobante_codigo, c.f_emision, c.f_vto,
                      c.importe_total, c.importe_pendiente, c.signo, c.observaciones,
                      ct.codigo AS tipo_codigo, ct.comprobante_tipo AS tipo_nombre
               FROM gestion__comprobantes c
               INNER JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
               WHERE c.empresa_id = ? AND c.entidad_id = ? AND c.modulo = 'sindicatos'
               ORDER BY c.f_emision ASC, c.comprobante_id ASC";
    $stmtM = mysqli_prepare($conexion, $sqlMov);
    mysqli_stmt_bind_param($stmtM, "ii", $empresa_id, $entidad_id);
    mysqli_stmt_execute($stmtM);
    $resM = mysqli_stmt_get_result($stmtM);

    $movimientos = [];
    $saldoAcumulado = 0.00;
    $totalDebe = 0.00;
    $totalHaber = 0.00;

    while ($r = mysqli_fetch_assoc($resM)) {
        $signo = (int)$r['signo'];
        $imp = (float)$r['importe_total'];
        $debe = 0.00;
        $haber = 0.00;

        if ($signo === 1) {
            $debe = $imp;
            $saldoAcumulado = round($saldoAcumulado + $debe, 2);
            $totalDebe = round($totalDebe + $debe, 2);
        } else {
            $haber = $imp;
            $saldoAcumulado = round($saldoAcumulado - $haber, 2);
            $totalHaber = round($totalHaber + $haber, 2);
        }

        $r['debe'] = $debe;
        $r['haber'] = $haber;
        $r['saldo_acumulado'] = $saldoAcumulado;
        $movimientos[] = $r;
    }
    mysqli_stmt_close($stmtM);

    return [
        'empleador' => $empleador,
        'total_debe' => $totalDebe,
        'total_haber' => $totalHaber,
        'saldo_actual' => $saldoAcumulado,
        'movimientos' => $movimientos
    ];
}
