<?php
require_once __DIR__ . '/../../core/entidades_roles.php';
require_once __DIR__ . '/../../core/empresa_config.php';
require_once __DIR__ . '/../../core/vendedores.php';
/**
 * clientes_ctacte_model.php
 *
 * Cuenta corriente de clientes, calculada sobre gestion__comprobantes + imputaciones.
 * Solo lectura: no escribe nada.
 *
 * Qué comprobantes entran (sin ids de tipo fijos, sale de la matriz de cancelaciones):
 *  - DEBE:  los tipos que puede cancelar el recibo de cobro (código RCX) en
 *           gestion__comprobantes_tipos_cancelaciones (facturas, ND, ND por cheque rechazado...).
 *  - HABER: los tipos que cancelan alguno de esos (el propio RCX, NC A/B/X...).
 * Solo comprobantes en Confirmado / Cancelado parcial / Cancelado total (codigo_estandar):
 * quedan afuera borradores, anulados, eliminados y facturas "Pendiente de ARCA".
 *
 * Importes en moneda local (importe * tipo_cambio del comprobante). El saldo pendiente de cada
 * comprobante se calcula con las imputaciones confirmadas (como tesoreriaObtenerSaldoComprobante),
 * no con la caché importe_pendiente.
 *
 * El saldo es de la empresa entera: no se filtra por accesos de sucursal (un cliente debe lo
 * mismo lo mire quien lo mire). El listado de comprobantes sí respeta esos accesos
 * (cconsListar).
 */

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

const CTACTE_CODIGO_RECIBO_CLIENTE = 'RCX';

/**
 * Tipos de comprobante que forman la cuenta corriente del lado cliente.
 * @return array ['debito' => int[], 'credito' => int[]]
 */
function ctacteTiposCliente($conexion, $empresa_id)
{
    static $cache = [];
    $empresa_id = intval($empresa_id);
    if (isset($cache[$empresa_id])) {
        return $cache[$empresa_id];
    }

    $codigo = CTACTE_CODIGO_RECIBO_CLIENTE;

    $debito = [];
    $stmt = mysqli_prepare($conexion, "SELECT DISTINCT tc.comprobante_tipo_destino_id
                                       FROM gestion__comprobantes_tipos_cancelaciones tc
                                       JOIN gestion__comprobantes_tipos o ON o.comprobante_tipo_id = tc.comprobante_tipo_origen_id
                                       WHERE tc.empresa_id = ? AND tc.tabla_estado_registro_id = 1 AND o.codigo = ?");
    mysqli_stmt_bind_param($stmt, "is", $empresa_id, $codigo);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_row($res)) {
        $debito[] = intval($r[0]);
    }
    mysqli_stmt_close($stmt);

    $credito = [];
    $stmt = mysqli_prepare($conexion, "SELECT DISTINCT tc2.comprobante_tipo_origen_id
                                       FROM gestion__comprobantes_tipos_cancelaciones tc2
                                       JOIN gestion__comprobantes_tipos_cancelaciones tc
                                            ON tc.empresa_id = tc2.empresa_id
                                           AND tc.comprobante_tipo_destino_id = tc2.comprobante_tipo_destino_id
                                           AND tc.tabla_estado_registro_id = 1
                                       JOIN gestion__comprobantes_tipos o ON o.comprobante_tipo_id = tc.comprobante_tipo_origen_id
                                       WHERE tc2.empresa_id = ? AND tc2.tabla_estado_registro_id = 1 AND o.codigo = ?");
    mysqli_stmt_bind_param($stmt, "is", $empresa_id, $codigo);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    while ($r = mysqli_fetch_row($res)) {
        $id = intval($r[0]);
        if (!in_array($id, $debito, true)) {
            $credito[] = $id;
        }
    }
    mysqli_stmt_close($stmt);

    if (empty($debito)) {
        error_log("=== ctacteTiposCliente: empresa $empresa_id sin tipos cancelables por $codigo en gestion__comprobantes_tipos_cancelaciones ===");
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
    $tipos = ctacteTiposCliente($conexion, $empresa_id);
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
        error_log("=== ERROR ctacte: " . mysqli_error($conexion));
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
 * Resumen de la cuenta de un cliente: saldo, deuda pendiente, vencida, saldo a favor sin
 * aplicar y último cobro. Saldo = deuda - a favor si las imputaciones están completas.
 */
function ctacteResumen($conexion, $empresa_id, $entidad_id)
{
    $vacio = ['saldo' => 0.0, 'deuda' => 0.0, 'vencido' => 0.0, 'a_favor' => 0.0, 'comprobantes_pendientes' => 0,
              'comprobantes_vencidos' => 0, 'ultimo_cobro' => null, 'ultimo_cobro_importe' => 0.0,
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

    // Último cobro: el comprobante del recibo de cliente más reciente
    $sql_cobro = "SELECT b.f_emision, b.importe_local
                  FROM ($sql) b
                  JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = b.comprobante_tipo_id
                  WHERE ct.codigo = ?
                  ORDER BY b.f_emision DESC, b.comprobante_id DESC LIMIT 1";
    $cobro = ctacteFetchAll($conexion, $sql_cobro, $types . 's', array_merge($params, [CTACTE_CODIGO_RECIBO_CLIENTE]))[0] ?? null;

    return [
        'saldo'                   => round(floatval($fila['saldo'] ?? 0), 2),
        'deuda'                   => round(floatval($fila['deuda'] ?? 0), 2),
        'vencido'                 => round(floatval($fila['vencido'] ?? 0), 2),
        'a_favor'                 => round(floatval($fila['a_favor'] ?? 0), 2),
        'comprobantes_pendientes' => intval($fila['comprobantes_pendientes'] ?? 0),
        'comprobantes_vencidos'   => intval($fila['comprobantes_vencidos'] ?? 0),
        'primer_vencimiento'      => $fila['primer_vencimiento'] ?? null,
        'ultimo_cobro'            => $cobro['f_emision'] ?? null,
        'ultimo_cobro_importe'    => $cobro ? round(floatval($cobro['importe_local']), 2) : 0.0,
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
    // Mismo día: primero los débitos, así un recibo no deja el saldo negativo por un instante
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
 * (recibos/NC con importe sin aplicar).
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
 * Saldos por cliente (para el listado general). Filtros: texto (nombre/CUIT),
 * solo_con_saldo (saldo, deuda o a favor distinto de 0), solo_vencidos.
 */
function ctacteSaldosClientes($conexion, $empresa_id, array $filtros = [])
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
                   WHERE 1 = 1"
        . vcar_sql_filtro_cliente($conexion, intval($empresa_id), intval($filtros['usuario_id'] ?? ($_SESSION['usuario_id'] ?? 0)), 'b.entidad_id');

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

/** Cabecera mínima del cliente (nombre, CUIT, condición fiscal vigente). */
function ctacteDatosCliente($conexion, $entidad_id)
{
    $sql = "SELECT e.entidad_id, e.entidad_nombre, e.entidad_fantasia, e.cuit, IF(" . ent_sql_tiene_rol('e.entidad_id', 'CLI') . ", 1, 0) AS es_cliente,
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
            WHERE e.entidad_id = ?";
    $fila = ctacteFetchAll($conexion, $sql, 'i', [intval($entidad_id)])[0] ?? null;
    if ($fila) {
        $fila['entidad_id'] = intval($fila['entidad_id']);
    }
    return $fila;
}

// ---------------------------------------------------------------------------------------------
// Envío por correo del detalle de una solapa (Deuda / Cuenta corriente / Comprobantes emitidos)
// ---------------------------------------------------------------------------------------------

/** El cliente pertenece a la empresa (el detalle por correo nunca sale de otra empresa). */
function ctacteEntidadDeEmpresa($conexion, $empresa_id, $entidad_id): bool
{
    return (bool) ctacteFetchAll($conexion, "SELECT 1 AS ok FROM gestion__entidades WHERE entidad_id = ? AND empresa_id = ?",
                                 'ii', [intval($entidad_id), intval($empresa_id)]);
}

/** Correos cargados en las sucursales activas del cliente, sin repetir (para precargar el destinatario). */
function ctacteEmailsCliente($conexion, $empresa_id, $entidad_id): array
{
    $filas = ctacteFetchAll($conexion,
        "SELECT TRIM(es.sucursal_email) AS email
         FROM gestion__entidades_sucursales es
         JOIN gestion__entidades e ON e.entidad_id = es.entidad_id AND e.empresa_id = ?
         WHERE es.entidad_id = ? AND es.tabla_estado_registro_id = 1
           AND es.sucursal_email IS NOT NULL AND TRIM(es.sucursal_email) <> ''
         ORDER BY es.sucursal_numero, es.sucursal_id",
        'ii', [intval($empresa_id), intval($entidad_id)]);
    $emails = [];
    foreach ($filas as $f) {
        // Una sucursal puede tener varios, separados por coma o punto y coma
        foreach (preg_split('/[;,\s]+/', (string) $f['email']) as $em) {
            if ($em !== '' && filter_var($em, FILTER_VALIDATE_EMAIL)
                && !in_array(strtolower($em), array_map('strtolower', $emails), true)) {
                $emails[] = $em;
            }
        }
    }
    return $emails;
}

/** "FA A 0001-00000012" / "PC (borrador)", igual que ComprobantesUI.numero(): comprobante_pv ya viene para mostrar. */
function ctacteNumeroComprobante(array $r): string
{
    $tipo = (string) ($r['tipo_codigo'] ?? '');
    $letra = (string) ($r['letra'] ?? '');
    $prefijo = trim($tipo . ($letra !== '' && strpos($tipo, $letra) === false ? ' ' . $letra : ''));
    if (intval($r['comprobante_nro'] ?? 0) <= 0) {
        return $prefijo . ' (borrador)';
    }
    return $prefijo . ' ' . sprintf('%04d-%08d', intval($r['comprobante_pv'] ?? 0), intval($r['comprobante_nro']));
}

/**
 * Arma el correo con el detalle de una solapa a partir de los mismos datos que la pantalla
 * (nunca del HTML del navegador). $solapa: 'deuda' | 'cta' | 'comp'. Devuelve ['asunto', 'html'].
 */
function ctacteCorreoSolapa($conexion, $empresa_id, $usuario_id, $entidad_id, $solapa, $desde, $hasta, $mensaje = ''): array
{
    require_once __DIR__ . '/../../core/formato.php';
    // comprobantes_consulta_model.php se incluye a nivel de archivo (usa $conn global), como en clientes_ctacte_ajax.php
    if (!function_exists('cconsListar')) {
        throw new RuntimeException('Falta incluir comprobantes_consulta_model.php');
    }
    $h = function ($v) { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); };
    $mon = function ($v, $simbolo = null) { return fmt_moneda(floatval($v), 2, $simbolo ?: '$'); };

    $cliente = ctacteDatosCliente($conexion, $entidad_id) ?: [];
    $empresa = ctacteFetchAll($conexion, "SELECT empresa FROM conf__empresas WHERE empresa_id = ?", 'i', [intval($empresa_id)])[0]['empresa'] ?? '';
    $nombre_cliente = trim((string) ($cliente['entidad_nombre'] ?? ''));

    $estilo = 'border:1px solid #dee2e6;padding:5px 8px;font-size:12px;';
    $tabla = function (array $cabecera, array $filas, array $pie = []) use ($estilo) {
        $html = '<table cellspacing="0" cellpadding="0" style="border-collapse:collapse;width:100%;font-family:Arial,sans-serif"><thead><tr>';
        foreach ($cabecera as [$titulo, $der]) {
            $html .= '<th style="' . $estilo . 'background:#f1f3f5;text-align:' . ($der ? 'right' : 'left') . '">' . $titulo . '</th>';
        }
        $html .= '</tr></thead><tbody>';
        if (!$filas) {
            $html .= '<tr><td style="' . $estilo . '" colspan="' . count($cabecera) . '">Sin movimientos.</td></tr>';
        }
        foreach ($filas as $f) {
            $html .= '<tr>';
            foreach ($f as $i => $celda) {
                $html .= '<td style="' . $estilo . ($cabecera[$i][1] ? 'text-align:right;white-space:nowrap' : '') . '">' . $celda . '</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody>';
        if ($pie) {
            // $pie[0] = rótulo; el resto, las últimas columnas
            $html .= '<tfoot><tr><td style="' . $estilo . 'text-align:right;font-weight:bold" colspan="' . (count($cabecera) - count($pie) + 1) . '">' . $pie[0] . '</td>';
            for ($i = 1; $i < count($pie); $i++) {
                $html .= '<td style="' . $estilo . 'text-align:right;font-weight:bold;white-space:nowrap">' . $pie[$i] . '</td>';
            }
            $html .= '</tr></tfoot>';
        }
        return $html . '</table>';
    };
    $periodo = ($desde || $hasta)
        ? 'Período' . ($desde ? ' desde ' . fmt_fecha($desde) : '') . ($hasta ? ' hasta ' . fmt_fecha($hasta) : '')
        : '';

    $cuerpo = '';
    if ($solapa === 'deuda') {
        $titulo = 'Detalle de deuda';
        $d = ctactePendientes($conexion, $empresa_id, $entidad_id);
        $filas = [];
        $total = 0.0;
        foreach ($d['deuda'] as $r) {
            $total += $r['pendiente_local'];
            $filas[] = [fmt_fecha($r['f_emision']), fmt_fecha($r['f_vto'] ?: $r['f_emision']), $h(ctacteNumeroComprobante($r)),
                        $h($r['sucursal_nombre'] ?? ''), $mon($r['importe_total'], $r['moneda_simbolo'] ?? null),
                        $mon($r['pendiente'], $r['moneda_simbolo'] ?? null),
                        intval($r['dias_atraso'] ?? 0) > 0 ? intval($r['dias_atraso']) . ' días' : 'al día'];
        }
        $cuerpo .= $tabla([['Emisión', false], ['Vencimiento', false], ['Comprobante', false], ['Sucursal', false],
                           ['Total', true], ['Pendiente', true], ['Atraso', true]],
                          $filas, ['Total adeudado', $mon($total), '']);
        if (!empty($d['a_favor'])) {
            $filas = [];
            foreach ($d['a_favor'] as $r) {
                $filas[] = [fmt_fecha($r['f_emision']), $h(ctacteNumeroComprobante($r)),
                            $mon($r['importe_total'], $r['moneda_simbolo'] ?? null), $mon($r['pendiente'], $r['moneda_simbolo'] ?? null)];
            }
            $cuerpo .= '<h4 style="font-family:Arial,sans-serif;margin:18px 0 6px">Saldo a favor sin aplicar</h4>'
                     . $tabla([['Emisión', false], ['Comprobante', false], ['Total', true], ['Sin aplicar', true]], $filas);
        }
    } elseif ($solapa === 'cta') {
        $titulo = 'Cuenta corriente';
        $d = ctacteMovimientos($conexion, $empresa_id, $entidad_id, $desde, $hasta);
        $filas = [];
        foreach ($d['movimientos'] as $r) {
            $filas[] = [fmt_fecha($r['f_emision']), $r['f_vto'] ? fmt_fecha($r['f_vto']) : '', $h(ctacteNumeroComprobante($r)),
                        $h($r['sucursal_nombre'] ?? ''), $r['debe'] ? $mon($r['debe']) : '', $r['haber'] ? $mon($r['haber']) : '',
                        $mon($r['saldo'])];
        }
        if ($desde) {
            $cuerpo .= '<p style="font-family:Arial,sans-serif;font-size:13px">Saldo anterior al ' . fmt_fecha($desde) . ': <b>' . $mon($d['saldo_anterior']) . '</b></p>';
        }
        $cuerpo .= $tabla([['Fecha', false], ['Vencimiento', false], ['Comprobante', false], ['Sucursal', false],
                           ['Debe', true], ['Haber', true], ['Saldo', true]],
                          $filas, ['Totales del período', $mon($d['total_debe']), $mon($d['total_haber']), $mon($d['saldo_final'])]);
    } else {
        $titulo = 'Comprobantes emitidos';
        $res = cconsListar($conexion, $empresa_id, $usuario_id, ['entidad_id' => $entidad_id, 'desde' => $desde, 'hasta' => $hasta]);
        $filas = [];
        foreach ($res['filas'] as $r) {
            $filas[] = [fmt_fecha($r['f_emision']),
                        $h(ctacteNumeroComprobante($r)) . '<br><span style="color:#6c757d">' . $h($r['comprobante_tipo'] ?? '') . '</span>',
                        $h($r['sucursal_nombre'] ?? ''), $mon($r['importe_total'], $r['moneda_simbolo'] ?? null),
                        ($r['saldo'] ?? null) === null ? '-' : $mon($r['saldo'], $r['moneda_simbolo'] ?? null), $h($r['estado'] ?? '')];
        }
        $cuerpo .= $tabla([['Emisión', false], ['Comprobante', false], ['Sucursal', false], ['Total', true], ['Saldo', true], ['Estado', false]], $filas);
        if (!empty($res['truncado'])) {
            $cuerpo .= '<p style="font-family:Arial,sans-serif;font-size:12px;color:#6c757d">Se incluyen los primeros ' . intval($res['limite']) . ' comprobantes.</p>';
        }
    }

    $html = '<div style="font-family:Arial,sans-serif;color:#212529;max-width:900px">'
          . '<h3 style="margin:0 0 4px">' . $h($titulo) . '</h3>'
          . '<p style="margin:0 0 4px;font-size:13px"><b>' . $h($nombre_cliente) . '</b>'
          . (!empty($cliente['cuit']) ? ' · CUIT ' . $h($cliente['cuit']) : '') . '</p>'
          . '<p style="margin:0 0 12px;font-size:12px;color:#6c757d">' . $h(trim($periodo . ($periodo ? ' · ' : '') . 'Emitido el ' . fmt_fecha(date('Y-m-d')))) . '</p>'
          . (trim($mensaje) !== '' ? '<p style="font-size:13px;white-space:pre-line">' . $h(trim($mensaje)) . '</p>' : '')
          . $cuerpo
          . '<p style="margin-top:16px;font-size:12px;color:#6c757d">' . $h($empresa) . '</p></div>';

    return ['asunto' => $titulo . ' - ' . $nombre_cliente . ($empresa !== '' ? ' - ' . $empresa : ''), 'html' => $html];
}
