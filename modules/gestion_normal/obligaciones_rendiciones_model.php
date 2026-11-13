<?php
require_once __DIR__ . '/../../core/empresa_config.php';
require_once __DIR__ . '/../../core/formato.php';
/**
 * obligaciones_rendiciones_model.php
 *
 * Modelo para Liquidaciones Impositivas (I_LIQ, I_PAG) y Rendición de Gastos (RG_ANT, RG_REND, RG_REEM)
 * Sistema gestion_multipyme - Módulo Tesorería
 *
 * Cumple con §4.7 y §4.8 del documento técnico:
 * - I_LIQ (46): Liquidación impositiva / DDJJ devengada.
 * - I_PAG (47): Pago de obligación impositiva (VEP/banco) con egreso en libro de tesorería y recálculo D8.
 * - RG_ANT (48): Anticipo de fondos a rendir con egreso de tesorería.
 * - RG_REND (49): Rendición con detalle de comprobantes y aplicación de anticipos.
 * - RG_REEM (50): Reembolso de saldos a favor del empleado con tesorería.
 * - D27 / D36: Asientos contables automáticos balanceados en todas las operaciones.
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/tesoreria_core_model.php';
require_once __DIR__ . '/../../core/obligaciones/obligaciones_service.php';

$conexion = $conn;

// Tipos de comprobante: se resuelven por código y empresa con orTipoComprobanteId() (antes ids fijos 46-50 de la empresa 2).

// Tablas ID
// conf__tablas.tabla_id de cada tabla origen (gestion__comprobantes.tabla_origen_id).
// recalcularEstadoDestino() reconoce I_LIQ y RG_REND por este id.
if (!defined('TABLA_OBLIGACIONES_ID')) define('TABLA_OBLIGACIONES_ID', 286); // gestion__obligaciones_pago (antes obligaciones_impositivas)
if (!defined('TABLA_PAGOS_IMP_ID'))    define('TABLA_PAGOS_IMP_ID', 277);    // gestion__pagos_impuestos
if (!defined('TABLA_RENDICIONES_ID'))  define('TABLA_RENDICIONES_ID', 287);  // gestion__rendiciones
if (!defined('TABLA_ANTICIPOS_ID'))    define('TABLA_ANTICIPOS_ID', 281);    // gestion__rendiciones_anticipos
if (!defined('TABLA_REEMBOLSOS_ID'))   define('TABLA_REEMBOLSOS_ID', 282);   // gestion__rendiciones_reembolsos

// Tipo de comprobante de la empresa por código (I_LIQ, I_PAG, RG_ANT, RG_REND, RG_REEM): los ids de
// gestion__comprobantes_tipos son por empresa (46 = I_LIQ solo en la empresa 2), nunca constantes.
function orTipoComprobanteId($conexion, $empresa_id, $codigo)
{
    $id = emp_comprobante_tipo_id($conexion, intval($empresa_id), $codigo);
    if ($id <= 0) throw new Exception("La empresa no tiene configurado el tipo de comprobante $codigo.");
    return $id;
}

function orProximoNumero($conexion, $empresa_id, $comprobante_tipo_id, $pv)
{
    $stmt = mysqli_prepare($conexion, "SELECT COALESCE(MAX(comprobante_nro), 0) + 1 AS prox_nro FROM gestion__comprobantes
                                       WHERE empresa_id = ? AND comprobante_tipo_id = ? AND comprobante_pv = ?");
    mysqli_stmt_bind_param($stmt, 'iii', $empresa_id, $comprobante_tipo_id, $pv);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return intval($row['prox_nro'] ?? 1);
}

if (!defined('ESTADO_BORRADOR'))   define('ESTADO_BORRADOR', 3);
if (!defined('ESTADO_CONFIRMADO')) define('ESTADO_CONFIRMADO', 5);
if (!defined('ESTADO_ANULADO'))    define('ESTADO_ANULADO', 38);

// =========================================================================
// 1. LIQUIDACIONES IMPOSITIVAS (I_LIQ / I_PAG)
// =========================================================================

/**
 * Listado de Obligaciones Impositivas
 */
function obtenerObligacionesListado($conexion, $empresa_id, $filtros = [])
{
    $empresa_id = intval($empresa_id);

    $sql = "SELECT oi.*, c.importe_pendiente, " . emp_sql_pv_mostrar('c.comprobante_pv', 'c.empresa_id') . " AS comprobante_pv, c.comprobante_nro,
                   COALESCE(NULLIF(TRIM(e.entidad_fantasia), ''), e.entidad_nombre) AS organismo,
                   s.sucursal_nombre AS sucursal,
                   er.codigo_estandar AS estado_codigo, er.estado_registro
            FROM gestion__obligaciones_pago oi
            JOIN gestion__comprobantes c ON c.comprobante_id = oi.comprobante_id
            JOIN gestion__sucursales s ON s.sucursal_id = oi.sucursal_id
            LEFT JOIN gestion__entidades e ON e.entidad_id = oi.entidad_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = c.tabla_estado_registro_id
            WHERE oi.empresa_id = ? ";

    $params = [$empresa_id];
    $types  = "i";

    if (!empty($filtros['periodo'])) {
        $sql .= " AND oi.periodo_fiscal = ? ";
        $params[] = $filtros['periodo'];
        $types .= "s";
    }

    if (!empty($filtros['desde'])) {
        $sql .= " AND oi.f_vto >= ? ";
        $params[] = $filtros['desde'];
        $types .= "s";
    }

    if (!empty($filtros['hasta'])) {
        $sql .= " AND oi.f_vto <= ? ";
        $params[] = $filtros['hasta'];
        $types .= "s";
    }

    if (!empty($filtros['estado_id'])) {
        $sql .= " AND c.tabla_estado_registro_id = ? ";
        $params[] = intval($filtros['estado_id']);
        $types .= "i";
    }

    $sql .= " ORDER BY oi.f_vto ASC, oi.obligacion_id DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['data' => []];

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $datos = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $id = intval($row['obligacion_id']);
        $comp_id = intval($row['comprobante_id']);
        $total = floatval($row['importe_total']);
        $pendiente = floatval($row['importe_pendiente']);
        $estado_id = intval($row['tabla_estado_registro_id']);

        if ($pendiente <= 0.001 && $estado_id !== ESTADO_ANULADO) {
            $badge = '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Cancelada</span>';
        } elseif ($pendiente < $total && $estado_id !== ESTADO_ANULADO) {
            $badge = '<span class="badge bg-warning text-dark"><i class="fas fa-hourglass-half me-1"></i>Parcial</span>';
        } elseif ($estado_id === ESTADO_ANULADO) {
            $badge = '<span class="badge bg-danger"><i class="fas fa-ban me-1"></i>Anulada</span>';
        } else {
            $badge = '<span class="badge bg-primary"><i class="fas fa-clock me-1"></i>Pendiente</span>';
        }

        $comprobante_nro = sprintf("%04d-%08d", $row['comprobante_pv'], $row['comprobante_nro']);

        $botones = '<div class="btn-group btn-group-sm" role="group">';
        if ($pendiente > 0 && $estado_id !== ESTADO_ANULADO) {
            $botones .= '<button type="button" class="btn btn-success btn-pagar-imp" 
                            data-id="' . $id . '" 
                            data-comp-id="' . $comp_id . '" 
                            data-nro="' . $comprobante_nro . '" 
                            data-vep="' . htmlspecialchars($row['nro_vep'] ?? '') . '" 
                            data-pendiente="' . $pendiente . '" 
                            data-desc="' . htmlspecialchars($row['descripcion']) . '" 
                            title="Pagar Obligación / VEP">
                            <i class="fas fa-credit-card me-1"></i>Pagar
                         </button>';
        }
        $botones .= '</div>';

        $datos[] = [
            'id'                => $id,
            'comprobante_id'    => $comp_id,
            'comprobante_nro'   => '<strong>' . $comprobante_nro . '</strong>',
            'periodo_fiscal'    => '<span class="badge bg-light text-dark border">' . htmlspecialchars($row['periodo_fiscal']) . '</span>',
            'descripcion'       => htmlspecialchars($row['descripcion']),
            'organismo'         => htmlspecialchars($row['organismo'] ?? 'ARCA / AFIP'),
            'f_vto'             => fmt_fecha($row['f_vto']),
            'nro_vep'           => htmlspecialchars($row['nro_vep'] ?? '-'),
            'importe_total'     => '<span class="fw-bold">$' . fmt_numero($total, 2) . '</span>',
            'importe_pendiente' => '<span class="fw-bold text-danger">$' . fmt_numero($pendiente, 2) . '</span>',
            'estado'            => $badge,
            'acciones'          => $botones
        ];
    }
    mysqli_stmt_close($stmt);

    return ['data' => $datos];
}

function registrarObligacionImpositiva($conexion, $datos, $empresa_id, $usuario_id)
{
    // Desde 2026-10-30 toda obligación (impuestos, sueldos, anticipos) se registra en gestion__obligaciones_pago con
    // core/obligaciones/obligaciones_service.php y se paga con Órdenes de Pago. Esta función queda como puerta de
    // entrada de los generadores impositivos (IVA, percepciones IIBB, retenciones) y de la carga manual vieja.
    // Opcionales en $datos:
    //  - cuenta_debe_id: cuenta del Debe del devengamiento (por defecto la del tipo o Otros Gastos Generales). Las
    //    percepciones IIBB practicadas ya están en el pasivo: se liquidan con Debe = Haber = la cuenta a depositar.
    //  - sin_asiento: el llamador genera su propio asiento sobre esta I_LIQ (liquidación de la posición de IVA).
    //  - en_transaccion: true = corre dentro de la transacción del llamador.
    $impuesto = intval($datos['impuesto_tipo_id'] ?? 7) ?: 7;
    if ($impuesto === 7) {
        [$tipo, $origen] = ['IMP_IVA', 'IVA_POSICION'];
    } elseif ($impuesto === 6) {
        [$tipo, $origen] = ['IMP_PER_IIBB', 'PERCEPCIONES_IIBB'];
    } elseif ($impuesto === 9) {
        [$tipo, $origen] = ['IMP_IIBB', 'IIBB_POSICION'];
    } elseif (in_array($impuesto, [14, 15, 16, 17, 22], true)) {
        [$tipo, $origen] = ['IMP_RET', 'RETENCIONES_LIQ'];
    } else {
        [$tipo, $origen] = ['IMP_VARIOS', 'MANUAL'];
    }
    $res = obl_registrar($conexion, [
        'tipo_codigo'           => $datos['tipo_codigo'] ?? $tipo,
        'origen'                => $datos['origen'] ?? $origen,
        'origen_registro_id'    => $datos['origen_registro_id'] ?? null,
        'entidad_id'            => intval($datos['entidad_id'] ?? 0),
        'sucursal_id'           => intval($datos['sucursal_id'] ?? 0),
        'f_emision'             => $datos['f_emision'] ?? date('Y-m-d'),
        'f_vto'                 => $datos['f_vto'] ?? date('Y-m-d', strtotime('+10 days')),
        'periodo_fiscal'        => $datos['periodo_fiscal'] ?? date('Y-m'),
        'descripcion'           => $datos['descripcion'] ?? 'Liquidación Impositiva',
        'nro_ddjj'              => $datos['nro_ddjj'] ?? '',
        'nro_vep'               => $datos['nro_vep'] ?? '',
        'importe_total'         => floatval($datos['importe_total'] ?? 0),
        'moneda_id'             => $datos['moneda_id'] ?? 0,
        'impuesto_tipo_id'      => $impuesto,
        'jurisdiccion_id'       => $datos['jurisdiccion_id'] ?? null,
        'cont_cuenta_pasivo_id' => intval($datos['cont_cuenta_id'] ?? 0) ?: emp_cuenta_concepto_id($conexion, $empresa_id, 'IVA_A_PAGAR'),
        'cont_cuenta_debe_id'   => intval($datos['cuenta_debe_id'] ?? 0),
        'confirmar'             => 'FORZAR',
        'sin_asiento'           => !empty($datos['sin_asiento']),
    ], intval($empresa_id), intval($usuario_id), !empty($datos['en_transaccion']));
    return [
        'success'        => true,
        'obligacion_id'  => $res['obligacion_id'],
        'comprobante_id' => $res['comprobante_id'],
        'numero'         => $res['numero'],
        'asiento_id'     => $res['asiento_id'],
        'mensaje'        => "Obligación impositiva {$res['numero']} registrada: se paga desde Órdenes de Pago.",
    ];
}

/**
 * Pago de obligaciones con I_PAG: reemplazado por Órdenes de Pago (2026-10-30). Las I_PAG históricas quedan como están.
 */
function pagarObligacionImpositiva($conexion, $datos, $empresa_id, $usuario_id)
{
    throw new RuntimeException('Las obligaciones se pagan desde Tesorería > Órdenes de Pago.');
}
// =========================================================================
// 2. RENDICIÓN DE GASTOS Y ANTICIPOS (RG_ANT / RG_REND / RG_REEM)
// =========================================================================

/**
 * Listado de Rendiciones de Gastos
 */
function obtenerRendicionesListado($conexion, $empresa_id, $filtros = [], $pagina_id = 0)
{
    $empresa_id = intval($empresa_id);

    $sql = "SELECT r.*, " . emp_sql_pv_mostrar('c.comprobante_pv', 'c.empresa_id') . " AS comprobante_pv, c.comprobante_nro,
                   COALESCE(NULLIF(TRIM(e.entidad_fantasia), ''), e.entidad_nombre) AS responsable,
                   s.sucursal_nombre AS sucursal,
                   er.codigo_estandar AS estado_codigo, er.estado_registro
            FROM gestion__rendiciones r
            JOIN gestion__comprobantes c ON c.comprobante_id = r.comprobante_id
            JOIN gestion__sucursales s ON s.sucursal_id = r.sucursal_id
            LEFT JOIN gestion__entidades e ON e.entidad_id = r.entidad_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = c.tabla_estado_registro_id
            WHERE r.empresa_id = ? ";

    $params = [$empresa_id];
    $types  = "i";

    if (!empty($filtros['desde'])) {
        $sql .= " AND r.f_emision >= ? ";
        $params[] = $filtros['desde'];
        $types .= "s";
    }

    if (!empty($filtros['hasta'])) {
        $sql .= " AND r.f_emision <= ? ";
        $params[] = $filtros['hasta'];
        $types .= "s";
    }

    $sql .= " ORDER BY r.rendicion_id DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['data' => []];

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $datos = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $id = intval($row['rendicion_id']);
        $total = floatval($row['importe_total']);
        $anticipos = floatval($row['importe_anticipos']);
        $reembolsar = floatval($row['importe_a_reembolsar']);

        $comprobante_nro = sprintf("%04d-%08d", $row['comprobante_pv'], $row['comprobante_nro']);

        // r.tabla_estado_registro_id: estado propio de la rendición (Reembolsada lo deriva
        // recalcularEstadoDestino() cuando el saldo llega a 0).
        if (intval($row['tabla_estado_registro_id']) === ESTADO_RG_REND_REEMBOLSADA) {
            $badge = '<span class="badge bg-secondary"><i class="fas fa-check-double me-1"></i>Reembolsada</span>';
        } else {
            $badge = '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Aprobada</span>';
        }

        $botones = '<div class="btn-group btn-group-sm" role="group">';
        // Solo si el perfil del usuario tiene la función Reembolsar (core/permisos.php)
        if ($reembolsar > 0 && intval($pagina_id) > 0 && permisos_puede($conexion, intval($pagina_id), 'reembolsar')) {
            $botones .= '<button type="button" class="btn btn-primary btn-reembolsar-rend" 
                            data-id="' . $id . '" 
                            data-nro="' . $comprobante_nro . '" 
                            data-monto="' . $reembolsar . '" 
                            title="Reembolsar Saldo a Favor">
                            <i class="fas fa-hand-holding-usd me-1"></i>Reembolsar
                         </button>';
        }
        $botones .= '</div>';

        $datos[] = [
            'id'                   => $id,
            'comprobante_nro'      => '<strong>' . $comprobante_nro . '</strong>',
            'f_emision'            => fmt_fecha($row['f_emision']),
            'responsable'          => htmlspecialchars($row['responsable'] ?? 'Personal'),
            'descripcion'          => htmlspecialchars($row['descripcion']),
            'importe_total'        => '<span class="fw-bold fs-6">$' . fmt_numero($total, 2) . '</span>',
            'importe_anticipos'    => '<span class="text-secondary">$' . fmt_numero($anticipos, 2) . '</span>',
            'importe_a_reembolsar' => ($reembolsar > 0) ? '<span class="fw-bold text-success">$' . fmt_numero($reembolsar, 2) . '</span>' : '<span class="text-muted">$ 0,00</span>',
            'estado'               => $badge,
            'acciones'             => $botones
        ];
    }
    mysqli_stmt_close($stmt);

    return ['data' => $datos];
}

/**
 * Registra un Anticipo a Rendir (RG_ANT - D15 / D27)
 */
function registrarAnticipoRendir($conexion, $datos, $empresa_id, $usuario_id)
{
    $empresa_id          = intval($empresa_id);
    $usuario_id          = intval($usuario_id);
    $sucursal_id         = intval($datos['sucursal_id'] ?? 0);
    $entidad_id          = intval($datos['entidad_id'] ?? 3); // Empleado / Beneficiario
    $tesoreria_cuenta_id = intval($datos['tesoreria_cuenta_id'] ?? 0); // Caja o Banco
    $f_emision           = !empty($datos['f_emision']) ? $datos['f_emision'] : date('Y-m-d');
    $importe_total       = floatval($datos['importe_total'] ?? 0);
    $descripcion         = trim($datos['descripcion'] ?? 'Anticipo de gastos a rendir');

    if ($importe_total <= 0) throw new Exception("El importe del anticipo debe ser mayor a cero.");

    mysqli_begin_transaction($conexion);

    try {
        // Validar saldo tesorería
        $sql_cta = "SELECT tesoreria_cuenta, saldo_actual, permite_saldo_negativo, cont_cuenta_id FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = ? AND empresa_id = ? FOR UPDATE";
        $stmt_c = mysqli_prepare($conexion, $sql_cta);
        mysqli_stmt_bind_param($stmt_c, "ii", $tesoreria_cuenta_id, $empresa_id);
        mysqli_stmt_execute($stmt_c);
        $cta = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_c));
        mysqli_stmt_close($stmt_c);

        if (!$cta) throw new Exception("Cuenta de tesorería inválida.");
        if (intval($cta['permite_saldo_negativo']) !== 1 && floatval($cta['saldo_actual']) < $importe_total) {
            throw new Exception("Saldo insuficiente en '{$cta['tesoreria_cuenta']}' para entregar el anticipo.");
        }

        // Correlativo RG_ANT
        $prefijo = emp_punto_venta_id($conexion, $empresa_id);   // punto de venta de la empresa
        if ($prefijo <= 0) throw new Exception('La empresa no tiene puntos de venta: no se puede numerar el comprobante.');
        $tipo_rg_ant = orTipoComprobanteId($conexion, $empresa_id, 'RG_ANT');
        $numero = orProximoNumero($conexion, $empresa_id, $tipo_rg_ant, $prefijo);

        // 1. Cabecera en gestion__comprobantes
        $res_reg = $conexion->query("SELECT COALESCE(MAX(registro_origen_id), 0) + 1 AS prox_reg FROM gestion__comprobantes WHERE tabla_origen_id = " . TABLA_ANTICIPOS_ID);
        $registro_origen_id = intval($res_reg->fetch_assoc()['prox_reg'] ?? 1);
        $tabla_origen_id = TABLA_ANTICIPOS_ID;
        $modulo = 'TESORERIA';

        $sql_comp = "INSERT INTO gestion__comprobantes (
                        tabla_origen_id, registro_origen_id, modulo, entidad_id,
                        empresa_id, sucursal_id, comprobante_tipo_id, comprobante_pv, comprobante_nro,
                        f_emision, f_contabilidad, f_vto, moneda_id, tipo_cambio,
                        importe_bruto, importe_neto, importe_total, importe_pendiente,
                        observaciones, tabla_estado_registro_id, usuario_id
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1.0, ?, ?, ?, ?, ?, ?, ?)";

        $stmt_comp = mysqli_prepare($conexion, $sql_comp);
        $est_conf = ESTADO_CONFIRMADO;
        $comp_tipo_ant = $tipo_rg_ant;
        mysqli_stmt_bind_param(
            $stmt_comp, "iisiiiiiisssddddsii",
            $tabla_origen_id, $registro_origen_id, $modulo, $entidad_id,
            $empresa_id, $sucursal_id, $comp_tipo_ant, $prefijo, $numero,
            $f_emision, $f_emision, $f_emision,
            $importe_total, $importe_total, $importe_total, $importe_total,
            $descripcion, $est_conf, $usuario_id
        );
        mysqli_stmt_execute($stmt_comp);
        $comprobante_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt_comp);

        // 2. Insertar en gestion__rendiciones_anticipos
        $sql_ant = "INSERT INTO gestion__rendiciones_anticipos (
                        empresa_id, sucursal_id, comprobante_tipo_id, comprobante_pv, comprobante_nro,
                        comprobante_id, entidad_id, f_emision, f_contabilidad, f_vto,
                        descripcion, tesoreria_cuenta_id, medio_pago_id, moneda_id, tipo_cambio,
                        importe_total, tabla_estado_registro_id, usuario_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1, 1.0, ?, ?, ?)";
        $stmt_ant = mysqli_prepare($conexion, $sql_ant);
        mysqli_stmt_bind_param(
            $stmt_ant, "iiiiiiissssidii",
            $empresa_id, $sucursal_id, $comp_tipo_ant, $prefijo, $numero,
            $comprobante_id, $entidad_id, $f_emision, $f_emision, $f_emision,
            $descripcion, $tesoreria_cuenta_id, $importe_total, $est_conf, $usuario_id
        );
        mysqli_stmt_execute($stmt_ant);
        $anticipo_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt_ant);

        $conexion->query("UPDATE gestion__comprobantes SET registro_origen_id = $anticipo_id WHERE comprobante_id = $comprobante_id");

        // 3. Egreso en Libro de Tesorería (D15)
        tesoreriaRegistrarMovimiento($conexion, [
            'empresa_id'          => $empresa_id,
            'sucursal_id'         => $sucursal_id,
            'tesoreria_cuenta_id' => $tesoreria_cuenta_id,
            'comprobante_id'      => $comprobante_id,
            'tipo_movimiento'     => 'EGRESO',
            'importe'             => $importe_total,
            'f_movimiento'        => $f_emision,
            'f_valor'             => $f_emision,
            'moneda_id'           => emp_moneda_base_id($conexion, $empresa_id),
            'tipo_cambio'         => 1.0,
            'medio_pago_id'       => emp_medio_pago_id($conexion, $empresa_id, 'EFECTIVO'),
            'concepto'            => "Anticipo a Rendir N° {$prefijo}-{$numero} a personal",
            'tabla_origen_id'     => TABLA_ANTICIPOS_ID,
            'registro_origen_id'  => $anticipo_id,
            'usuario_id'          => $usuario_id
        ]);

        // 4. Asiento Contable Automático: DEBE Anticipos al Personal / HABER Caja o Banco (D27)
        $res_ant_cta = tesoreriaResolverCuentaContable($conexion, $empresa_id, 'Empleados - Anticipos y Rendiciones', emp_cuenta_concepto_id($conexion, $empresa_id, 'EMPLEADOS'));
        $cta_cont_origen = intval($cta['cont_cuenta_id']);
        if ($cta_cont_origen <= 0) {
            $r_or = tesoreriaResolverCuentaContable($conexion, $empresa_id, 'Caja');
            $cta_cont_origen = $r_or['cont_cuenta_id'];
        }

        $asiento_id = tesoreriaGenerarAsiento($conexion, [
            'empresa_id'           => $empresa_id,
            'sucursal_id'          => $sucursal_id,
            'comprobante_id'       => $comprobante_id,
            'entidad_id'           => $entidad_id,
            'f_asiento'            => $f_emision,
            'descripcion'          => "Anticipo de gastos a rendir RG_ANT {$prefijo}-{$numero}",
            'usuario_id'           => $usuario_id,
        ], [
            [
                'cuenta_id'   => $res_ant_cta['cont_cuenta_id'],
                'debe'        => $importe_total,
                'haber'       => 0.0,
                'descripcion' => "Anticipo entregado a rendir"
            ],
            [
                'cuenta_id'   => $cta_cont_origen,
                'debe'        => 0.0,
                'haber'       => $importe_total,
                'descripcion' => "Salida de fondos {$cta['tesoreria_cuenta']}"
            ]
        ]);

        mysqli_commit($conexion);

        return [
            'success'        => true,
            'anticipo_id'    => $anticipo_id,
            'comprobante_id' => $comprobante_id,
            'numero'         => emp_numero_comprobante($conexion, $empresa_id, $prefijo, $numero),
            'asiento_id'     => $asiento_id,
            'mensaje'        => "Anticipo N° " . emp_numero_comprobante($conexion, $empresa_id, $prefijo, $numero) . " registrado con éxito."
        ];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("Error en registrarAnticipoRendir: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Registra una Rendición de Gastos (RG_REND) con comprobantes de gastos e imputación de anticipo
 */
function registrarRendicionGastos($conexion, $datos, $empresa_id, $usuario_id)
{
    $empresa_id    = intval($empresa_id);
    $usuario_id    = intval($usuario_id);
    $sucursal_id   = intval($datos['sucursal_id'] ?? 0);
    $entidad_id    = intval($datos['entidad_id'] ?? 3); // Empleado que rinde
    $anticipo_id   = intval($datos['anticipo_id'] ?? 0); // Opcional: Anticipo a descontar
    $f_emision     = !empty($datos['f_emision']) ? $datos['f_emision'] : date('Y-m-d');
    $descripcion   = trim($datos['descripcion'] ?? 'Rendición de Gastos Operativos');
    $items         = $datos['items'] ?? []; // Lista de gastos

    if (empty($items)) throw new Exception("Debe incluir al menos un comprobante de gasto en la rendición.");

    mysqli_begin_transaction($conexion);

    try {
        $total_gastos = 0.0;
        foreach ($items as $it) {
            $imp = floatval($it['importe'] ?? 0);
            if ($imp <= 0) throw new Exception("El importe de cada gasto debe ser positivo.");
            $total_gastos += $imp;
        }

        // Consultar anticipo si fue provisto
        $monto_anticipo = 0.0;
        $comp_ant_id = 0;
        if ($anticipo_id > 0) {
            $sql_a = "SELECT ra.*, c.comprobante_id AS comp_ant_id, c.importe_pendiente 
                      FROM gestion__rendiciones_anticipos ra
                      JOIN gestion__comprobantes c ON c.comprobante_id = ra.comprobante_id
                      WHERE ra.rendicion_anticipo_id = ? AND ra.empresa_id = ? FOR UPDATE";
            $stmt_a = mysqli_prepare($conexion, $sql_a);
            mysqli_stmt_bind_param($stmt_a, "ii", $anticipo_id, $empresa_id);
            mysqli_stmt_execute($stmt_a);
            $ant = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_a));
            mysqli_stmt_close($stmt_a);

            if ($ant) {
                $comp_ant_id = intval($ant['comp_ant_id']);
                $pend_ant = floatval($ant['importe_pendiente']);
                $monto_anticipo = min($pend_ant, $total_gastos);
            }
        }

        // Balance a reembolsar (si los gastos superan al anticipo)
        $monto_a_reembolsar = max(0.0, $total_gastos - $monto_anticipo);

        // Correlativo RG_REND
        $prefijo = emp_punto_venta_id($conexion, $empresa_id);   // punto de venta de la empresa
        if ($prefijo <= 0) throw new Exception('La empresa no tiene puntos de venta: no se puede numerar el comprobante.');
        $tipo_rg_rend = orTipoComprobanteId($conexion, $empresa_id, 'RG_REND');
        $numero = orProximoNumero($conexion, $empresa_id, $tipo_rg_rend, $prefijo);

        // 1. Cabecera en gestion__comprobantes
        $res_reg = $conexion->query("SELECT COALESCE(MAX(registro_origen_id), 0) + 1 AS prox_reg FROM gestion__comprobantes WHERE tabla_origen_id = " . TABLA_RENDICIONES_ID);
        $registro_origen_id = intval($res_reg->fetch_assoc()['prox_reg'] ?? 1);
        $tabla_origen_id = TABLA_RENDICIONES_ID;
        $modulo = 'TESORERIA';

        $sql_comp = "INSERT INTO gestion__comprobantes (
                        tabla_origen_id, registro_origen_id, modulo, entidad_id,
                        empresa_id, sucursal_id, comprobante_tipo_id, comprobante_pv, comprobante_nro,
                        f_emision, f_contabilidad, f_vto, moneda_id, tipo_cambio,
                        importe_bruto, importe_neto, importe_total, importe_pendiente,
                        observaciones, tabla_estado_registro_id, usuario_id
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1.0, ?, ?, ?, ?, ?, ?, ?)";

        $stmt_comp = mysqli_prepare($conexion, $sql_comp);
        $est_conf = ESTADO_CONFIRMADO;
        $comp_tipo_rend = $tipo_rg_rend;
        mysqli_stmt_bind_param(
            $stmt_comp, "iisiiiiiisssddddsii",
            $tabla_origen_id, $registro_origen_id, $modulo, $entidad_id,
            $empresa_id, $sucursal_id, $comp_tipo_rend, $prefijo, $numero,
            $f_emision, $f_emision, $f_emision,
            $total_gastos, $total_gastos, $total_gastos, $monto_a_reembolsar,
            $descripcion, $est_conf, $usuario_id
        );
        mysqli_stmt_execute($stmt_comp);
        $comprobante_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt_comp);

        // 2. Insertar en gestion__rendiciones
        $sql_r = "INSERT INTO gestion__rendiciones (
                    empresa_id, sucursal_id, comprobante_tipo_id, comprobante_pv, comprobante_nro,
                    comprobante_id, entidad_id, f_emision, f_contabilidad, periodo,
                    descripcion, importe_bruto, importe_aprobado, importe_anticipos,
                    importe_a_reembolsar, moneda_id, tipo_cambio, importe_total,
                    tabla_estado_registro_id, usuario_id
                  ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1.0, ?, ?, ?)";
        $stmt_r = mysqli_prepare($conexion, $sql_r);
        $periodo = date('Y-m', strtotime($f_emision));
        mysqli_stmt_bind_param(
            $stmt_r, "iiiiiiissssdddddii",
            $empresa_id, $sucursal_id, $comp_tipo_rend, $prefijo, $numero,
            $comprobante_id, $entidad_id, $f_emision, $f_emision, $periodo,
            $descripcion, $total_gastos, $total_gastos, $monto_anticipo,
            $monto_a_reembolsar, $total_gastos, $est_conf, $usuario_id
        );
        mysqli_stmt_execute($stmt_r);
        $rendicion_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt_r);

        $conexion->query("UPDATE gestion__comprobantes SET registro_origen_id = $rendicion_id WHERE comprobante_id = $comprobante_id");

        // 3. Insertar Items de Gastos
        $sql_it = "INSERT INTO gestion__rendiciones_items (
                    rendicion_id, empresa_id, f_gasto, categoria_gasto_id, descripcion,
                    proveedor_nombre, proveedor_cuit, comprobante_externo_nro, tiene_comprobante,
                    moneda_id, tipo_cambio, importe, importe_convertido, cont_cuenta_id,
                    tabla_estado_registro_id, usuario_id
                   ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1, ?, 1.0, ?, ?, ?, 1, ?)";
        $stmt_it = mysqli_prepare($conexion, $sql_it);

        $lineas_asiento_gastos = [];
        $moneda_it = emp_moneda_base_id($conexion, $empresa_id);

        foreach ($items as $it) {
            $f_gasto = !empty($it['f_gasto']) ? $it['f_gasto'] : $f_emision;
            $cat_id = intval($it['categoria_gasto_id'] ?? 0);
            $desc_it = trim($it['descripcion'] ?? 'Gasto general');
            $prov_nom = trim($it['proveedor_nombre'] ?? 'Varios');
            $prov_cuit = !empty($it['proveedor_cuit']) ? intval($it['proveedor_cuit']) : null;
            $ext_nro = trim($it['comprobante_externo_nro'] ?? '');
            $imp_it = floatval($it['importe']);
            $cuenta_gasto_id = intval($it['cont_cuenta_id'] ?? 0);

            if ($cuenta_gasto_id <= 0) {
                $r_cg = tesoreriaResolverCuentaContable($conexion, $empresa_id, 'Otros Gastos Generales', emp_cuenta_concepto_id($conexion, $empresa_id, 'GASTOS_GENERALES'));
                $cuenta_gasto_id = $r_cg['cont_cuenta_id'];
            }

            mysqli_stmt_bind_param(
                $stmt_it, "iisissisiddii",
                $rendicion_id, $empresa_id, $f_gasto, $cat_id, $desc_it,
                $prov_nom, $prov_cuit, $ext_nro, $moneda_it,
                $imp_it, $imp_it, $cuenta_gasto_id, $usuario_id
            );
            mysqli_stmt_execute($stmt_it);

            $lineas_asiento_gastos[] = [
                'cuenta_id'   => $cuenta_gasto_id,
                'debe'        => $imp_it,
                'haber'       => 0.0,
                'descripcion' => "Gasto {$desc_it} ({$prov_nom})"
            ];
        }
        mysqli_stmt_close($stmt_it);

        // 4. Imputar anticipo si hubo absorción
        if ($comp_ant_id > 0 && $monto_anticipo > 0) {
            $sql_imp = "INSERT INTO gestion__comprobantes_imputaciones (
                            empresa_id, comp_origen_id, comp_destino_id, f_imputacion,
                            importe_imputado, moneda_origen_id, tipo_cambio, importe_origen,
                            tabla_estado_registro_id, usuario_id
                        ) VALUES (?, ?, ?, ?, ?, ?, 1.0, ?, 5, ?)";
            $moneda_imp = emp_moneda_base_id($conexion, $empresa_id);
            $stmt_imp = mysqli_prepare($conexion, $sql_imp);
            mysqli_stmt_bind_param($stmt_imp, "iiisdidi", $empresa_id, $comprobante_id, $comp_ant_id, $f_emision, $monto_anticipo, $moneda_imp, $monto_anticipo, $usuario_id);
            mysqli_stmt_execute($stmt_imp);
            mysqli_stmt_close($stmt_imp);

            $conexion->query("UPDATE gestion__comprobantes 
                              SET importe_pendiente = GREATEST(0.00, importe_pendiente - $monto_anticipo),
                                  usuario_modificacion_id = $usuario_id,
                                  fecha_modificacion = NOW()
                              WHERE comprobante_id = $comp_ant_id");

            recalcularEstadoDestino($conexion, $comp_ant_id, $usuario_id);
        }

        // 5. Asiento Contable Automático (D27 / D36):
        // DEBE: Cuentas de Gasto ($total_gastos)
        // HABER: Anticipos al Personal ($monto_anticipo)
        // HABER: Cuentas por Pagar al Personal ($monto_a_reembolsar)
        $res_cta_ant = tesoreriaResolverCuentaContable($conexion, $empresa_id, 'Empleados - Anticipos y Rendiciones', emp_cuenta_concepto_id($conexion, $empresa_id, 'EMPLEADOS'));
        $res_cta_pas = tesoreriaResolverCuentaContable($conexion, $empresa_id, 'Proveedores Nacionales', emp_cuenta_concepto_id($conexion, $empresa_id, 'PROVEEDORES'));

        $detalles_asiento = $lineas_asiento_gastos;

        if ($monto_anticipo > 0) {
            $detalles_asiento[] = [
                'cuenta_id'   => $res_cta_ant['cont_cuenta_id'],
                'debe'        => 0.0,
                'haber'       => $monto_anticipo,
                'descripcion' => "Aplicación de anticipo a rendir"
            ];
        }

        if ($monto_a_reembolsar > 0) {
            $detalles_asiento[] = [
                'cuenta_id'   => $res_cta_pas['cont_cuenta_id'],
                'debe'        => 0.0,
                'haber'       => $monto_a_reembolsar,
                'descripcion' => "Saldo a favor del responsable por reembolsar"
            ];
        }

        $asiento_id = tesoreriaGenerarAsiento($conexion, [
            'empresa_id'           => $empresa_id,
            'sucursal_id'          => $sucursal_id,
            'comprobante_id'       => $comprobante_id,
            'entidad_id'           => $entidad_id,
            'f_asiento'            => $f_emision,
            'descripcion'          => "Rendición de Gastos RG_REND {$prefijo}-{$numero}: {$descripcion}",
            'usuario_id'           => $usuario_id,
        ], $detalles_asiento);

        mysqli_commit($conexion);

        return [
            'success'              => true,
            'rendicion_id'         => $rendicion_id,
            'comprobante_id'       => $comprobante_id,
            'numero'               => emp_numero_comprobante($conexion, $empresa_id, $prefijo, $numero),
            'asiento_id'           => $asiento_id,
            'monto_a_reembolsar'   => $monto_a_reembolsar,
            'mensaje'              => "Rendición N° " . emp_numero_comprobante($conexion, $empresa_id, $prefijo, $numero) . " registrada y aprobada con éxito."
        ];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("Error en registrarRendicionGastos: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Reembolsa saldo a favor en Rendición de Gastos (RG_REEM - D15 / D27)
 */
function reembolsarRendicion($conexion, $datos, $empresa_id, $usuario_id)
{
    $empresa_id          = intval($empresa_id);
    $usuario_id          = intval($usuario_id);
    $rendicion_id        = intval($datos['rendicion_id'] ?? 0);
    $tesoreria_cuenta_id = intval($datos['tesoreria_cuenta_id'] ?? 0); // Caja o Banco
    $f_pago              = !empty($datos['f_pago']) ? $datos['f_pago'] : date('Y-m-d');
    $importe_reembolso   = floatval($datos['importe_reembolso'] ?? 0);

    if ($rendicion_id <= 0) throw new Exception("ID de rendición inválido.");
    if ($importe_reembolso <= 0) throw new Exception("El importe a reembolsar debe ser mayor a cero.");

    mysqli_begin_transaction($conexion);

    try {
        // Bloquear rendición
        $sql_r = "SELECT r.*, c.comprobante_id AS comp_rend_id, c.importe_pendiente
                  FROM gestion__rendiciones r
                  JOIN gestion__comprobantes c ON c.comprobante_id = r.comprobante_id
                  WHERE r.rendicion_id = ? AND r.empresa_id = ? FOR UPDATE";
        $stmt_r = mysqli_prepare($conexion, $sql_r);
        mysqli_stmt_bind_param($stmt_r, "ii", $rendicion_id, $empresa_id);
        mysqli_stmt_execute($stmt_r);
        $rend = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_r));
        mysqli_stmt_close($stmt_r);

        if (!$rend) throw new Exception("Rendición no encontrada.");
        $pendiente = floatval($rend['importe_pendiente']);

        if ($importe_reembolso > $pendiente + 0.01) {
            throw new Exception("El importe a reembolsar supera el saldo pendiente de reembolso ($" . fmt_numero($pendiente, 2) . ").");
        }

        // Validar saldo tesorería
        $sql_cta = "SELECT tesoreria_cuenta, saldo_actual, permite_saldo_negativo, cont_cuenta_id FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = ? AND empresa_id = ? FOR UPDATE";
        $stmt_c = mysqli_prepare($conexion, $sql_cta);
        mysqli_stmt_bind_param($stmt_c, "ii", $tesoreria_cuenta_id, $empresa_id);
        mysqli_stmt_execute($stmt_c);
        $cta = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_c));
        mysqli_stmt_close($stmt_c);

        if (!$cta) throw new Exception("Cuenta de tesorería inválida.");
        if (intval($cta['permite_saldo_negativo']) !== 1 && floatval($cta['saldo_actual']) < $importe_reembolso) {
            throw new Exception("Saldo insuficiente en '{$cta['tesoreria_cuenta']}' para realizar el reembolso.");
        }

        // Correlativo RG_REEM
        $prefijo = emp_punto_venta_id($conexion, $empresa_id);   // punto de venta de la empresa
        if ($prefijo <= 0) throw new Exception('La empresa no tiene puntos de venta: no se puede numerar el comprobante.');
        $tipo_rg_reem = orTipoComprobanteId($conexion, $empresa_id, 'RG_REEM');
        $numero = orProximoNumero($conexion, $empresa_id, $tipo_rg_reem, $prefijo);

        // 1. Cabecera en gestion__comprobantes
        $res_reg = $conexion->query("SELECT COALESCE(MAX(registro_origen_id), 0) + 1 AS prox_reg FROM gestion__comprobantes WHERE tabla_origen_id = " . TABLA_REEMBOLSOS_ID);
        $registro_origen_id = intval($res_reg->fetch_assoc()['prox_reg'] ?? 1);
        $tabla_origen_id = TABLA_REEMBOLSOS_ID;
        $modulo = 'TESORERIA';
        $desc_reem = "Reembolso por Gastos Excedentes Rendición " . emp_numero_comprobante($conexion, $empresa_id, $rend['comprobante_pv'], $rend['comprobante_nro']) . "";

        $sql_comp = "INSERT INTO gestion__comprobantes (
                        tabla_origen_id, registro_origen_id, modulo, entidad_id,
                        empresa_id, sucursal_id, comprobante_tipo_id, comprobante_pv, comprobante_nro,
                        f_emision, f_contabilidad, f_vto, moneda_id, tipo_cambio,
                        importe_bruto, importe_neto, importe_total, importe_pendiente,
                        observaciones, tabla_estado_registro_id, usuario_id
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1.0, ?, ?, ?, ?, ?, ?, ?)";

        $stmt_comp = mysqli_prepare($conexion, $sql_comp);
        $est_conf = ESTADO_CONFIRMADO;
        $comp_tipo_reem = $tipo_rg_reem;
        $rend_ent_id = intval($rend['entidad_id']);
        $rend_suc_id = intval($rend['sucursal_id']);
        $pendiente_cero = 0.0;
        mysqli_stmt_bind_param(
            $stmt_comp, "iisiiiiiisssddddsii",
            $tabla_origen_id, $registro_origen_id, $modulo, $rend_ent_id,
            $empresa_id, $rend_suc_id, $comp_tipo_reem, $prefijo, $numero,
            $f_pago, $f_pago, $f_pago,
            $importe_reembolso, $importe_reembolso, $importe_reembolso, $pendiente_cero,
            $desc_reem, $est_conf, $usuario_id
        );
        mysqli_stmt_execute($stmt_comp);
        $comprobante_reem_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt_comp);

        // 2. Insertar en gestion__rendiciones_reembolsos
        $sql_reem = "INSERT INTO gestion__rendiciones_reembolsos (
                        empresa_id, sucursal_id, comprobante_tipo_id, comprobante_pv, comprobante_nro,
                        comprobante_id, entidad_id, f_emision, f_contabilidad, rendicion_id,
                        moneda_id, tipo_cambio, importe_total, observaciones,
                        tabla_estado_registro_id, usuario_id
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, 1.0, ?, ?, ?, ?)";
        $stmt_reem = mysqli_prepare($conexion, $sql_reem);
        $rend_suc_id = intval($rend['sucursal_id']);
        $rend_ent_id = intval($rend['entidad_id']);
        mysqli_stmt_bind_param(
            $stmt_reem, "iiiiiiissidsii",
            $empresa_id, $rend_suc_id, $comp_tipo_reem, $prefijo, $numero,
            $comprobante_reem_id, $rend_ent_id, $f_pago, $f_pago, $rendicion_id,
            $importe_reembolso, $desc_reem, $est_conf, $usuario_id
        );
        mysqli_stmt_execute($stmt_reem);
        $reembolso_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt_reem);

        $conexion->query("UPDATE gestion__comprobantes SET registro_origen_id = $reembolso_id WHERE comprobante_id = $comprobante_reem_id");

        // 3. Egreso en Libro de Tesorería (D15)
        tesoreriaRegistrarMovimiento($conexion, [
            'empresa_id'          => $empresa_id,
            'sucursal_id'         => intval($rend['sucursal_id']),
            'tesoreria_cuenta_id' => $tesoreria_cuenta_id,
            'comprobante_id'      => $comprobante_reem_id,
            'tipo_movimiento'     => 'EGRESO',
            'importe'             => $importe_reembolso,
            'f_movimiento'        => $f_pago,
            'f_valor'             => $f_pago,
            'moneda_id'           => emp_moneda_base_id($conexion, $empresa_id),
            'tipo_cambio'         => 1.0,
            'medio_pago_id'       => emp_medio_pago_id($conexion, $empresa_id, 'EFECTIVO'),
            'concepto'            => "Reembolso Rendición N° " . emp_numero_comprobante($conexion, $empresa_id, $rend['comprobante_pv'], $rend['comprobante_nro']) . "",
            'tabla_origen_id'     => TABLA_REEMBOLSOS_ID,
            'registro_origen_id'  => $reembolso_id,
            'usuario_id'          => $usuario_id
        ]);

        // 4. Imputar comprobante RG_REEM a comprobante RG_REND y Recalcular D8
        $comp_rend_id = intval($rend['comp_rend_id']);
        $sql_imp = "INSERT INTO gestion__comprobantes_imputaciones (
                        empresa_id, comp_origen_id, comp_destino_id, f_imputacion,
                        importe_imputado, moneda_origen_id, tipo_cambio, importe_origen,
                        tabla_estado_registro_id, usuario_id
                    ) VALUES (?, ?, ?, ?, ?, ?, 1.0, ?, 5, ?)";
        $moneda_imp = emp_moneda_base_id($conexion, $empresa_id);
        $stmt_imp = mysqli_prepare($conexion, $sql_imp);
        mysqli_stmt_bind_param($stmt_imp, "iiisdidi", $empresa_id, $comprobante_reem_id, $comp_rend_id, $f_pago, $importe_reembolso, $moneda_imp, $importe_reembolso, $usuario_id);
        mysqli_stmt_execute($stmt_imp);
        mysqli_stmt_close($stmt_imp);

        $conexion->query("UPDATE gestion__comprobantes 
                          SET importe_pendiente = GREATEST(0.00, importe_pendiente - $importe_reembolso),
                              usuario_modificacion_id = $usuario_id,
                              fecha_modificacion = NOW()
                          WHERE comprobante_id = $comp_rend_id");

        recalcularEstadoDestino($conexion, $comp_rend_id, $usuario_id);

        // 5. Asiento Contable Automático: DEBE Cuentas por Pagar al Personal / HABER Caja o Banco (D27)
        $res_cta_pas = tesoreriaResolverCuentaContable($conexion, $empresa_id, 'Proveedores Nacionales', emp_cuenta_concepto_id($conexion, $empresa_id, 'PROVEEDORES'));
        $cta_cont_origen = intval($cta['cont_cuenta_id']);
        if ($cta_cont_origen <= 0) {
            $r_or = tesoreriaResolverCuentaContable($conexion, $empresa_id, 'Caja');
            $cta_cont_origen = $r_or['cont_cuenta_id'];
        }

        $asiento_id = tesoreriaGenerarAsiento($conexion, [
            'empresa_id'           => $empresa_id,
            'sucursal_id'          => intval($rend['sucursal_id']),
            'comprobante_id'       => $comprobante_reem_id,
            'entidad_id'           => intval($rend['entidad_id']),
            'f_asiento'            => $f_pago,
            'descripcion'          => "Reembolso por rendición RG_REEM {$prefijo}-{$numero}",
            'usuario_id'           => $usuario_id,
        ], [
            [
                'cuenta_id'   => $res_cta_pas['cont_cuenta_id'],
                'debe'        => $importe_reembolso,
                'haber'       => 0.0,
                'descripcion' => "Cancelación deuda por gastos rendidos"
            ],
            [
                'cuenta_id'   => $cta_cont_origen,
                'debe'        => 0.0,
                'haber'       => $importe_reembolso,
                'descripcion' => "Salida fondos {$cta['tesoreria_cuenta']}"
            ]
        ]);

        mysqli_commit($conexion);

        return [
            'success'             => true,
            'reembolso_id'        => $reembolso_id,
            'comprobante_reem_id' => $comprobante_reem_id,
            'numero'              => emp_numero_comprobante($conexion, $empresa_id, $prefijo, $numero),
            'asiento_id'          => $asiento_id,
            'mensaje'             => "Reembolso N° " . emp_numero_comprobante($conexion, $empresa_id, $prefijo, $numero) . " registrado con éxito."
        ];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("Error en reembolsarRendicion: " . $e->getMessage());
        throw $e;
    }
}
