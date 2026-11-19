<?php
require_once __DIR__ . '/../../core/entidades_roles.php';
require_once __DIR__ . '/../../core/empresa_config.php';
/**
 * proveedores_ctacte_model.php
 *
 * Cuenta corriente de proveedores, calculada sobre gestion__comprobantes + imputaciones.
 * Solo lectura: no escribe nada.
 *
 * Qué comprobantes entran (sin ids de tipo fijos, sale de la matriz de cancelaciones):
 *  - DÉBITO (Deuda): los tipos que puede cancelar la Orden de Pago (código OP) en
 *           gestion__comprobantes_tipos_cancelaciones (facturas de compra, ND, cheque propio rechazado...),
 *           excluyendo comprobantes de ventas.
 *  - CRÉDITO (Pagos/A favor): los tipos que cancelan alguno de esos (la propia OP, NC A/B/C/X de compra...).
 * Solo comprobantes en Confirmado / Cancelado parcial / Cancelado total (codigo_estandar):
 * quedan afuera borradores, anulados y eliminados.
 *
 * Importes en moneda local (importe * tipo_cambio del comprobante). El saldo pendiente de cada
 * comprobante se calcula con las imputaciones confirmadas (como tesoreriaObtenerSaldoComprobante),
 * no con la caché importe_pendiente.
 *
 * El saldo es de la empresa entera: no se filtra por accesos de sucursal (un proveedor debe lo
 * mismo lo mire quien lo mire). El listado de comprobantes sí respeta esos accesos
 * (cconsListar).
 */

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

const CTACTE_CODIGO_RECIBO_PROVEEDOR = 'OP';

/**
 * Tipos de comprobante que forman la cuenta corriente del lado proveedor.
 * @return array ['debito' => int[], 'credito' => int[]]
 */
function ctacteTiposProveedor($conexion, $empresa_id)
{
    static $cache = [];
    $empresa_id = intval($empresa_id);
    if (isset($cache[$empresa_id])) {
        return $cache[$empresa_id];
    }

    $codigo = CTACTE_CODIGO_RECIBO_PROVEEDOR;

    $debito = [];
    $stmt = mysqli_prepare($conexion, "SELECT DISTINCT tc.comprobante_tipo_destino_id
                                       FROM gestion__comprobantes_tipos_cancelaciones tc
                                       JOIN gestion__comprobantes_tipos o ON o.comprobante_tipo_id = tc.comprobante_tipo_origen_id
                                       JOIN gestion__comprobantes_tipos d ON d.comprobante_tipo_id = tc.comprobante_tipo_destino_id
                                       LEFT JOIN gestion__comprobantes_grupos cg ON cg.comprobante_grupo_id = d.comprobante_grupo_id
                                       WHERE tc.empresa_id = ? AND tc.tabla_estado_registro_id = 1 AND o.codigo = ?
                                         AND (cg.codigo IS NULL OR cg.codigo != 'VENTAS')
                                         AND d.codigo NOT LIKE 'V_%'");
    mysqli_stmt_bind_param($stmt, "is", $empresa_id, $codigo);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_row($res)) {
        $debito[] = intval($r[0]);
    }
    mysqli_stmt_close($stmt);

    $credito = [];
    if (!empty($debito)) {
        $ph = implode(',', array_fill(0, count($debito), '?'));
        $sql = "SELECT DISTINCT tc2.comprobante_tipo_origen_id
                FROM gestion__comprobantes_tipos_cancelaciones tc2
                JOIN gestion__comprobantes_tipos o2 ON o2.comprobante_tipo_id = tc2.comprobante_tipo_origen_id
                LEFT JOIN gestion__comprobantes_grupos cg ON cg.comprobante_grupo_id = o2.comprobante_grupo_id
                WHERE tc2.empresa_id = ? AND tc2.tabla_estado_registro_id = 1
                  AND tc2.comprobante_tipo_destino_id IN ($ph)
                  AND (cg.codigo IS NULL OR cg.codigo != 'VENTAS')
                  AND o2.codigo NOT LIKE 'V_%'";
        $stmt = mysqli_prepare($conexion, $sql);
        $types = 'i' . str_repeat('i', count($debito));
        $params = array_merge([$empresa_id], $debito);
        mysqli_stmt_bind_param($stmt, $types, ...$params);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        while ($r = mysqli_fetch_row($res)) {
            $id = intval($r[0]);
            if (!in_array($id, $debito, true)) {
                $credito[] = $id;
            }
        }
        mysqli_stmt_close($stmt);
    }

    if (empty($debito)) {
        error_log("=== ctacteTiposProveedor: empresa $empresa_id sin tipos cancelables por $codigo en gestion__comprobantes_tipos_cancelaciones ===");
    }

    return $cache[$empresa_id] = ['debito' => $debito, 'credito' => $credito];
}

/**
 * Subconsulta con un renglón por comprobante de cuenta corriente:
 * signo (+1 debe / -1 haber), importe y pendiente en moneda local.
 * Devuelve [sql, types, params]; vacío (null) si la empresa no tiene la matriz configurada.
 */
function ctacteSqlComprobantes($conexion, $empresa_id, $entidad_id = null)
{
    $tipos = ctacteTiposProveedor($conexion, $empresa_id);
    if (empty($tipos['debito'])) {
        return null;
    }
    $todos = array_merge($tipos['debito'], $tipos['credito']);
    $ph_deb = implode(',', array_fill(0, count($tipos['debito']), '?'));
    $ph_todos = implode(',', array_fill(0, count($todos), '?'));

    $sql = "SELECT c.comprobante_id, c.entidad_id, c.sucursal_id, c.comprobante_tipo_id,
                   " . emp_sql_pv_mostrar('c.comprobante_pv', 'c.empresa_id', 'c.tabla_origen_id') . " AS comprobante_pv, c.comprobante_nro, c.f_emision, c.f_vto,
                   c.moneda_id, c.tipo_cambio, c.importe_total, c.observaciones,
                   c.tabla_origen_id, c.registro_origen_id, c.tabla_estado_registro_id AS estado_id,
                   er.codigo_estandar,
                   CASE WHEN c.comprobante_tipo_id IN ($ph_deb) THEN 1 ELSE -1 END AS signo_ctacte,
                   ROUND(c.importe_total * c.tipo_cambio, 2) AS importe_local,
                   GREATEST(0, c.importe_total - COALESCE(imd.imputado, 0) - COALESCE(imo.imputado, 0)) AS pendiente,
                   ROUND(GREATEST(0, c.importe_total - COALESCE(imd.imputado, 0) - COALESCE(imo.imputado, 0)) * c.tipo_cambio, 2) AS pendiente_local
            FROM gestion__comprobantes c
            JOIN conf__estados_registros er ON er.estado_registro_id = c.tabla_estado_registro_id
            LEFT JOIN (
                SELECT ci.comp_destino_id AS comprobante_id, SUM(ci.importe_imputado) AS imputado
                FROM gestion__comprobantes_imputaciones ci
                JOIN conf__estados_registros eri ON eri.estado_registro_id = ci.tabla_estado_registro_id
                WHERE ci.empresa_id = ? AND eri.codigo_estandar = 'CONFIRMADO'
                GROUP BY ci.comp_destino_id
            ) imd ON imd.comprobante_id = c.comprobante_id
            LEFT JOIN (
                SELECT ci.comp_origen_id AS comprobante_id, SUM(ci.importe_origen) AS imputado
                FROM gestion__comprobantes_imputaciones ci
                JOIN conf__estados_registros eri ON eri.estado_registro_id = ci.tabla_estado_registro_id
                WHERE ci.empresa_id = ? AND eri.codigo_estandar = 'CONFIRMADO'
                GROUP BY ci.comp_origen_id
            ) imo ON imo.comprobante_id = c.comprobante_id
            WHERE c.empresa_id = ?
              AND c.comprobante_tipo_id IN ($ph_todos)
              AND er.codigo_estandar IN ('CONFIRMADO', 'CANC_PARCIAL', 'CANC_TOTAL')";

    $types = str_repeat('i', count($tipos['debito'])) . 'iii' . str_repeat('i', count($todos));
    $params = array_merge($tipos['debito'], [$empresa_id, $empresa_id, $empresa_id], $todos);

    if (!empty($entidad_id)) {
        $sql .= " AND c.entidad_id = ?";
        $types .= 'i';
        $params[] = intval($entidad_id);
    }

    return [$sql, $types, $params];
}

/** Ejecuta un SELECT preparado y devuelve todas las filas. */
function ctacteFetchAll($conexion, $sql, $types, array $params)
{
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("=== ERROR ctacte proveedor: " . mysqli_error($conexion));
        throw new Exception('No se pudo consultar la cuenta corriente.');
    }
    if ($types !== '') {
        mysqli_stmt_bind_param($stmt, $types, ...$params);
    }
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $filas = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $filas[] = $r;
    }
    mysqli_stmt_close($stmt);
    return $filas;
}

/** Datos para mostrar cada comprobante (tipo, sucursal, moneda), unidos a la subconsulta. */
function ctacteSqlDecorado($sql_base)
{
    return "SELECT b.*, ct.codigo AS tipo_codigo, ct.comprobante_tipo, ct.letra,
                   s.sucursal_nombre, m.simbolo AS moneda_simbolo
            FROM ($sql_base) b
            JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = b.comprobante_tipo_id
            LEFT JOIN gestion__sucursales s ON s.sucursal_id = b.sucursal_id
            LEFT JOIN gestion__monedas m ON m.moneda_id = b.moneda_id";
}

function ctacteNormalizar(array $r)
{
    foreach (['comprobante_id', 'entidad_id', 'sucursal_id', 'comprobante_tipo_id', 'comprobante_pv',
              'comprobante_nro', 'moneda_id', 'tabla_origen_id', 'registro_origen_id', 'estado_id', 'signo_ctacte'] as $k) {
        if (array_key_exists($k, $r)) {
            $r[$k] = intval($r[$k]);
        }
    }
    foreach (['tipo_cambio', 'importe_total', 'importe_local', 'pendiente', 'pendiente_local'] as $k) {
        if (array_key_exists($k, $r)) {
            $r[$k] = floatval($r[$k]);
        }
    }
    return $r;
}

/**
 * Resumen de la cuenta de un proveedor: saldo, deuda pendiente, vencida, saldo a favor sin
 * aplicar y último pago. Saldo = deuda - a favor si las imputaciones están completas.
 */
function ctacteResumen($conexion, $empresa_id, $entidad_id)
{
    $vacio = ['saldo' => 0.0, 'deuda' => 0.0, 'vencido' => 0.0, 'a_favor' => 0.0, 'comprobantes_pendientes' => 0,
              'comprobantes_vencidos' => 0, 'ultimo_pago' => null, 'ultimo_pago_importe' => 0.0,
              'primer_vencimiento' => null, 'configurado' => true];

    $base = ctacteSqlComprobantes($conexion, $empresa_id, $entidad_id);
    if ($base === null) {
        $vacio['configurado'] = false;
        return $vacio;
    }
    [$sql, $types, $params] = $base;

    $sql_res = "SELECT
                    COALESCE(SUM(b.signo_ctacte * b.importe_local), 0) AS saldo,
                    COALESCE(SUM(CASE WHEN b.signo_ctacte = 1 THEN b.pendiente_local ELSE 0 END), 0) AS deuda,
                    COALESCE(SUM(CASE WHEN b.signo_ctacte = 1 AND COALESCE(b.f_vto, b.f_emision) < CURDATE()
                                      THEN b.pendiente_local ELSE 0 END), 0) AS vencido,
                    COALESCE(SUM(CASE WHEN b.signo_ctacte = -1 THEN b.pendiente_local ELSE 0 END), 0) AS a_favor,
                    SUM(CASE WHEN b.signo_ctacte = 1 AND b.pendiente > 0.009 THEN 1 ELSE 0 END) AS comprobantes_pendientes,
                    SUM(CASE WHEN b.signo_ctacte = 1 AND b.pendiente > 0.009 AND COALESCE(b.f_vto, b.f_emision) < CURDATE()
                             THEN 1 ELSE 0 END) AS comprobantes_vencidos,
                    MIN(CASE WHEN b.signo_ctacte = 1 AND b.pendiente > 0.009 THEN COALESCE(b.f_vto, b.f_emision) END) AS primer_vencimiento
                FROM ($sql) b";
    $fila = ctacteFetchAll($conexion, $sql_res, $types, $params)[0] ?? [];

    // Último pago: el comprobante de Orden de Pago (OP) más reciente
    $sql_pago = "SELECT b.f_emision, b.importe_local
                 FROM ($sql) b
                 JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = b.comprobante_tipo_id
                 WHERE ct.codigo = ?
                 ORDER BY b.f_emision DESC, b.comprobante_id DESC LIMIT 1";
    $pago = ctacteFetchAll($conexion, $sql_pago, $types . 's', array_merge($params, [CTACTE_CODIGO_RECIBO_PROVEEDOR]))[0] ?? null;

    return [
        'saldo'                   => round(floatval($fila['saldo'] ?? 0), 2),
        'deuda'                   => round(floatval($fila['deuda'] ?? 0), 2),
        'vencido'                 => round(floatval($fila['vencido'] ?? 0), 2),
        'a_favor'                 => round(floatval($fila['a_favor'] ?? 0), 2),
        'comprobantes_pendientes' => intval($fila['comprobantes_pendientes'] ?? 0),
        'comprobantes_vencidos'   => intval($fila['comprobantes_vencidos'] ?? 0),
        'primer_vencimiento'      => $fila['primer_vencimiento'] ?? null,
        'ultimo_pago'             => $pago['f_emision'] ?? null,
        'ultimo_pago_importe'     => $pago ? round(floatval($pago['importe_local']), 2) : 0.0,
        'configurado'             => true,
    ];
}

/**
 * Movimientos de la cuenta corriente con saldo acumulado (orden cronológico).
 * Si viene $desde, el saldo de los movimientos anteriores se informa como saldo_anterior.
 */
function ctacteMovimientos($conexion, $empresa_id, $entidad_id, $desde = null, $hasta = null)
{
    $base = ctacteSqlComprobantes($conexion, $empresa_id, $entidad_id);
    if ($base === null) {
        return ['saldo_anterior' => 0.0, 'movimientos' => [], 'total_debe' => 0.0, 'total_haber' => 0.0, 'saldo_final' => 0.0, 'configurado' => false];
    }
    [$sql, $types, $params] = $base;

    $saldo_anterior = 0.0;
    if (!empty($desde)) {
        $fila = ctacteFetchAll($conexion, "SELECT COALESCE(SUM(b.signo_ctacte * b.importe_local), 0) AS saldo FROM ($sql) b WHERE b.f_emision < ?",
                               $types . 's', array_merge($params, [$desde]))[0] ?? [];
        $saldo_anterior = round(floatval($fila['saldo'] ?? 0), 2);
    }

    $sql_mov = ctacteSqlDecorado($sql) . " WHERE 1 = 1";
    $types_mov = $types;
    $params_mov = $params;
    if (!empty($desde)) {
        $sql_mov .= " AND b.f_emision >= ?";
        $types_mov .= 's';
        $params_mov[] = $desde;
    }
    if (!empty($hasta)) {
        $sql_mov .= " AND b.f_emision <= ?";
        $types_mov .= 's';
        $params_mov[] = $hasta;
    }
    // Mismo día: primero los débitos (facturas recibidas), así una OP no deja el saldo negativo temporalmente
    $sql_mov .= " ORDER BY b.f_emision ASC, b.signo_ctacte DESC, b.comprobante_id ASC";

    $saldo = $saldo_anterior;
    $total_debe = 0.0;
    $total_haber = 0.0;
    $movimientos = [];
    foreach (ctacteFetchAll($conexion, $sql_mov, $types_mov, $params_mov) as $r) {
        $r = ctacteNormalizar($r);
        $r['debe'] = $r['signo_ctacte'] === 1 ? $r['importe_local'] : 0.0;
        $r['haber'] = $r['signo_ctacte'] === -1 ? $r['importe_local'] : 0.0;
        $saldo = round($saldo + $r['debe'] - $r['haber'], 2);
        $r['saldo'] = $saldo;
        $total_debe += $r['debe'];
        $total_haber += $r['haber'];
        $movimientos[] = $r;
    }

    return [
        'saldo_anterior' => $saldo_anterior,
        'movimientos'    => $movimientos,
        'total_debe'     => round($total_debe, 2),
        'total_haber'    => round($total_haber, 2),
        'saldo_final'    => $saldo,
        'configurado'    => true,
    ];
}

/**
 * Comprobantes con saldo: deuda (débitos con pendiente, con días de atraso) y saldo a favor
 * (OP / NC con importe sin aplicar).
 */
function ctactePendientes($conexion, $empresa_id, $entidad_id)
{
    $base = ctacteSqlComprobantes($conexion, $empresa_id, $entidad_id);
    if ($base === null) {
        return ['deuda' => [], 'a_favor' => [], 'configurado' => false];
    }
    [$sql, $types, $params] = $base;

    $sql_pend = ctacteSqlDecorado($sql) . " WHERE b.pendiente > 0.009
                 ORDER BY COALESCE(b.f_vto, b.f_emision) ASC, b.comprobante_id ASC";

    $hoy = new DateTime('today');
    $deuda = [];
    $a_favor = [];
    foreach (ctacteFetchAll($conexion, $sql_pend, $types, $params) as $r) {
        $r = ctacteNormalizar($r);
        if ($r['signo_ctacte'] === 1) {
            $vto = new DateTime($r['f_vto'] ?: $r['f_emision']);
            $r['dias_atraso'] = $vto < $hoy ? intval($vto->diff($hoy)->days) : 0;
            $deuda[] = $r;
        } else {
            $a_favor[] = $r;
        }
    }

    return ['deuda' => $deuda, 'a_favor' => $a_favor, 'configurado' => true];
}

/**
 * Saldos por proveedor (para el listado general). Filtros: texto (nombre/CUIT),
 * solo_con_saldo (saldo, deuda o a favor distinto de 0), solo_vencidos.
 */
function ctacteSaldosProveedores($conexion, $empresa_id, array $filtros = [])
{
    $base = ctacteSqlComprobantes($conexion, $empresa_id);
    if ($base === null) {
        return ['filas' => [], 'configurado' => false];
    }
    [$sql, $types, $params] = $base;

    $sql_saldos = "SELECT b.entidad_id, e.entidad_nombre, e.entidad_fantasia, e.cuit,
                          SUM(b.signo_ctacte * b.importe_local) AS saldo,
                          SUM(CASE WHEN b.signo_ctacte = 1 THEN b.pendiente_local ELSE 0 END) AS deuda,
                          SUM(CASE WHEN b.signo_ctacte = 1 AND COALESCE(b.f_vto, b.f_emision) < CURDATE()
                                   THEN b.pendiente_local ELSE 0 END) AS vencido,
                          SUM(CASE WHEN b.signo_ctacte = -1 THEN b.pendiente_local ELSE 0 END) AS a_favor,
                          MIN(CASE WHEN b.signo_ctacte = 1 AND b.pendiente > 0.009 THEN COALESCE(b.f_vto, b.f_emision) END) AS primer_vencimiento,
                          MAX(b.f_emision) AS ultimo_movimiento,
                          COUNT(*) AS comprobantes
                   FROM ($sql) b
                   JOIN gestion__entidades e ON e.entidad_id = b.entidad_id
                   WHERE 1 = 1";

    if (!empty($filtros['texto'])) {
        $like = '%' . trim($filtros['texto']) . '%';
        $sql_saldos .= " AND (e.entidad_nombre LIKE ? OR e.entidad_fantasia LIKE ? OR CAST(e.cuit AS CHAR) LIKE ?)";
        $types .= 'sss';
        array_push($params, $like, $like, $like);
    }

    $sql_saldos .= " GROUP BY b.entidad_id, e.entidad_nombre, e.entidad_fantasia, e.cuit";

    $having = [];
    if (!empty($filtros['solo_con_saldo'])) {
        $having[] = "(ABS(SUM(b.signo_ctacte * b.importe_local)) > 0.009
                      OR SUM(CASE WHEN b.signo_ctacte = 1 THEN b.pendiente_local ELSE 0 END) > 0.009
                      OR SUM(CASE WHEN b.signo_ctacte = -1 THEN b.pendiente_local ELSE 0 END) > 0.009)";
    }
    if (!empty($filtros['solo_vencidos'])) {
        $having[] = "SUM(CASE WHEN b.signo_ctacte = 1 AND COALESCE(b.f_vto, b.f_emision) < CURDATE()
                              THEN b.pendiente_local ELSE 0 END) > 0.009";
    }
    if ($having) {
        $sql_saldos .= " HAVING " . implode(' AND ', $having);
    }
    $sql_saldos .= " ORDER BY saldo DESC, e.entidad_nombre";

    $filas = [];
    foreach (ctacteFetchAll($conexion, $sql_saldos, $types, $params) as $r) {
        $r['entidad_id'] = intval($r['entidad_id']);
        $r['comprobantes'] = intval($r['comprobantes']);
        foreach (['saldo', 'deuda', 'vencido', 'a_favor'] as $k) {
            $r[$k] = round(floatval($r[$k]), 2);
        }
        $filas[] = $r;
    }

    return ['filas' => $filas, 'configurado' => true];
}

/** Cabecera mínima del proveedor (nombre, CUIT, condición fiscal vigente). */
function ctacteDatosProveedor($conexion, $entidad_id, $empresa_id = 0)
{
    $sql = "SELECT e.entidad_id, e.entidad_nombre, e.entidad_fantasia, e.cuit, IF(" . ent_sql_tiene_rol('e.entidad_id', 'PRO') . ", 1, 0) AS es_proveedor,
                   cf.condicion_fiscal
            FROM gestion__entidades e
            LEFT JOIN gestion__entidades_condiciones_fiscales ecf
                   ON ecf.entidad_condicion_fiscal_id = (
                        SELECT ecf2.entidad_condicion_fiscal_id
                        FROM gestion__entidades_condiciones_fiscales ecf2
                        WHERE ecf2.entidad_id = e.entidad_id
                          AND ecf2.tabla_estado_registro_id = 1
                          AND (ecf2.f_desde IS NULL OR ecf2.f_desde <= CURDATE())
                          AND (ecf2.f_hasta IS NULL OR ecf2.f_hasta >= CURDATE())
                        ORDER BY ecf2.f_desde DESC
                        LIMIT 1
                   )
            LEFT JOIN gestion__condiciones_fiscales cf ON cf.condicion_fiscal_id = ecf.condicion_fiscal_id
            WHERE e.entidad_id = ? AND (? = 0 OR e.empresa_id = ?)";
    $empresa_id = intval($empresa_id);
    $fila = ctacteFetchAll($conexion, $sql, 'iii', [intval($entidad_id), $empresa_id, $empresa_id])[0] ?? null;
    if ($fila) {
        $fila['entidad_id'] = intval($fila['entidad_id']);
    }
    return $fila;
}

/** Búsqueda de proveedores para autocompletar o filtros. */
function ctacteBuscarProveedores($conexion, $empresa_id, $texto, $limite = 30)
{
    $texto = trim((string) $texto);
    $like = '%' . $texto . '%';
    $limite = max(1, min(100, intval($limite)));

    $sql = "SELECT entidad_id, entidad_nombre, entidad_fantasia, cuit
            FROM gestion__entidades
            WHERE empresa_id = ?
              AND tabla_estado_registro_id IN (1, 2)
              AND " . ent_sql_tiene_rol('gestion__entidades.entidad_id', 'PRO') . "
              AND (entidad_nombre LIKE ? OR entidad_fantasia LIKE ? OR CAST(cuit AS CHAR) LIKE ?)
            ORDER BY entidad_nombre LIMIT $limite";

    $stmt = mysqli_prepare($conexion, $sql);
    $empresa_id = intval($empresa_id);
    mysqli_stmt_bind_param($stmt, "isss", $empresa_id, $like, $like, $like);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lista = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $r['entidad_id'] = intval($r['entidad_id']);
        $lista[] = $r;
    }
    mysqli_stmt_close($stmt);
    return $lista;
}
