<?php
require_once __DIR__ . '/../../core/empresa_config.php';
require_once __DIR__ . '/../../core/formato.php';
/**
 * tesoreria_movimientos_model.php
 *
 * Modelo para Movimientos Internos de Tesorería (Transferencias entre Cuentas Propias)
 * Sistema gestion_multipyme - Módulo Tesorería
 *
 * Cumple con §4.6 del documento técnico:
 * - Regla D12: Movimiento interno entre cuentas de la misma empresa (Caja/Banco).
 * - Regla RT14: Cuenta origen y destino deben ser distintas y pertenecer a la empresa.
 * - Validación de saldo en cuenta origen.
 * - Registro atómico de egreso en origen e ingreso en destino en gestion__tesoreria_movimientos.
 * - Regla D27 / D36: Asiento contable balanceado (DEBE Destino / HABER Origen).
 * - Anulación con reversión de saldos y contrasiento / anulación de asiento.
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/tesoreria_core_model.php';

$conexion = $conn;

const COMP_TIPO_TRANSFERENCIA = 28; // TB - Transferencia
const TABLA_COMPROBANTES_ID   = 264;

if (!defined('ESTADO_BORRADOR'))   define('ESTADO_BORRADOR', 3);
if (!defined('ESTADO_CONFIRMADO')) define('ESTADO_CONFIRMADO', 5);
if (!defined('ESTADO_ANULADO'))    define('ESTADO_ANULADO', 38);

/**
 * Obtiene el listado de transferencias internas de tesorería
 */
function obtenerTransferenciasListado($conexion, $empresa_id, $filtros = [], $pagina_id = 0)
{
    $empresa_id = intval($empresa_id);

    $sql = "SELECT c.comprobante_id, c.sucursal_id, c.f_emision, c.comprobante_nro AS numero, " . emp_sql_pv_mostrar('c.comprobante_pv', 'c.empresa_id') . " AS prefijo,
                   c.importe_total, c.observaciones, c.tabla_estado_registro_id,
                   s.sucursal_nombre AS sucursal,
                   m.simbolo AS moneda_simbolo,
                   er.codigo_estandar AS estado_codigo, er.estado_registro,
                   tm_egr.tesoreria_cuenta_id AS cuenta_origen_id,
                   tc_orig.tesoreria_cuenta AS cuenta_origen,
                   tm_ing.tesoreria_cuenta_id AS cuenta_destino_id,
                   tc_dest.tesoreria_cuenta AS cuenta_destino
            FROM gestion__comprobantes c
            JOIN gestion__sucursales s ON s.sucursal_id = c.sucursal_id
            JOIN gestion__monedas m ON m.moneda_id = c.moneda_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = c.tabla_estado_registro_id
            LEFT JOIN gestion__tesoreria_movimientos tm_egr ON tm_egr.comprobante_id = c.comprobante_id AND tm_egr.tipo_movimiento = 'EGRESO'
            LEFT JOIN gestion__tesoreria_cuentas tc_orig ON tc_orig.tesoreria_cuenta_id = tm_egr.tesoreria_cuenta_id
            LEFT JOIN gestion__tesoreria_movimientos tm_ing ON tm_ing.comprobante_id = c.comprobante_id AND tm_ing.tipo_movimiento = 'INGRESO'
            LEFT JOIN gestion__tesoreria_cuentas tc_dest ON tc_dest.tesoreria_cuenta_id = tm_ing.tesoreria_cuenta_id
            WHERE c.empresa_id = ? AND c.comprobante_tipo_id = ? AND (c.observaciones IS NULL OR c.observaciones NOT LIKE '[OTRO_MOV]%') ";

    $params = [$empresa_id, COMP_TIPO_TRANSFERENCIA];
    $types  = "ii";

    if (!empty($filtros['desde'])) {
        $sql .= " AND c.f_emision >= ? ";
        $params[] = $filtros['desde'];
        $types .= "s";
    }

    if (!empty($filtros['hasta'])) {
        $sql .= " AND c.f_emision <= ? ";
        $params[] = $filtros['hasta'];
        $types .= "s";
    }

    if (!empty($filtros['cuenta_id'])) {
        $sql .= " AND (tm_egr.tesoreria_cuenta_id = ? OR tm_ing.tesoreria_cuenta_id = ?) ";
        $params[] = intval($filtros['cuenta_id']);
        $params[] = intval($filtros['cuenta_id']);
        $types .= "ii";
    }

    if (!empty($filtros['estado_id'])) {
        $sql .= " AND c.tabla_estado_registro_id = ? ";
        $params[] = intval($filtros['estado_id']);
        $types .= "i";
    }

    $sql .= " GROUP BY c.comprobante_id ORDER BY c.f_emision DESC, c.comprobante_id DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['data' => []];

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $datos = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $id = intval($row['comprobante_id']);
        $estado_id = intval($row['tabla_estado_registro_id']);

        if ($estado_id === ESTADO_CONFIRMADO) {
            $badge = '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Confirmado</span>';
        } elseif ($estado_id === ESTADO_BORRADOR) {
            $badge = '<span class="badge bg-warning text-dark"><i class="fas fa-pencil-alt me-1"></i>Borrador</span>';
        } elseif ($estado_id === ESTADO_ANULADO) {
            $badge = '<span class="badge bg-danger"><i class="fas fa-ban me-1"></i>Anulado</span>';
        } else {
            $badge = '<span class="badge bg-secondary">' . htmlspecialchars($row['estado_registro'] ?? 'Estado ' . $estado_id) . '</span>';
        }

        $comprobante_nro = sprintf("%04d-%08d", $row['prefijo'] ?? 0, $row['numero'] ?? $id);

        $botones = '<div class="btn-group btn-group-sm" role="group">';
        $botones .= '<button type="button" class="btn btn-outline-info btn-ver" data-id="' . $id . '" title="Ver Detalle"><i class="fas fa-eye"></i></button>';

        // Solo si el perfil del usuario tiene la función Anular (core/permisos.php)
        if ($estado_id === ESTADO_CONFIRMADO && intval($pagina_id) > 0 && permisos_puede($conexion, intval($pagina_id), 'anular')) {
            $botones .= '<button type="button" class="btn btn-outline-danger btn-anular" data-id="' . $id . '" data-nro="' . $comprobante_nro . '" title="Anular Transferencia"><i class="fas fa-ban"></i></button>';
        }

        $botones .= '</div>';

        $datos[] = [
            'comprobante_id' => $id,
            'f_emision'      => fmt_fecha($row['f_emision']),
            'numero'         => '<strong>' . $comprobante_nro . '</strong>',
            'sucursal'       => htmlspecialchars($row['sucursal']),
            'cuenta_origen'  => '<span class="badge bg-light text-danger border"><i class="fas fa-arrow-up me-1"></i>' . htmlspecialchars($row['cuenta_origen'] ?? 'Origen') . '</span>',
            'cuenta_destino' => '<span class="badge bg-light text-success border"><i class="fas fa-arrow-down me-1"></i>' . htmlspecialchars($row['cuenta_destino'] ?? 'Destino') . '</span>',
            'importe'        => '<span class="fw-bold fs-6">$' . fmt_numero(floatval($row['importe_total']), 2) . '</span>',
            'observaciones'  => htmlspecialchars($row['observaciones'] ?? ''),
            'estado'         => $badge,
            'acciones'       => $botones
        ];
    }
    mysqli_stmt_close($stmt);

    return ['data' => $datos];
}

/**
 * Obtiene las cuentas disponibles para transferencias internas de una empresa
 */
function obtenerCuentasEmpresa($conexion, $empresa_id)
{
    $empresa_id = intval($empresa_id);
    $sql = "SELECT tc.tesoreria_cuenta_id, tc.tesoreria_cuenta, tc.cuenta_tipo, tc.saldo_actual,
                   tc.moneda_id, tc.cont_cuenta_id, m.simbolo AS moneda_simbolo,
                   b.banco
            FROM gestion__tesoreria_cuentas tc
            JOIN gestion__monedas m ON m.moneda_id = tc.moneda_id
            LEFT JOIN conf__bancos b ON b.banco_id = tc.banco_id
            WHERE tc.empresa_id = ? AND tc.cuenta_tipo IN ('CAJA', 'BANCO') AND tc.tabla_estado_registro_id = 1
            ORDER BY tc.cuenta_tipo ASC, tc.tesoreria_cuenta ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $cuentas = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $cuentas[] = [
            'id'             => intval($row['tesoreria_cuenta_id']),
            'nombre'         => $row['tesoreria_cuenta'],
            'tipo'           => $row['cuenta_tipo'],
            'saldo'          => floatval($row['saldo_actual']),
            'moneda_id'      => intval($row['moneda_id']),
            'moneda_simbolo' => $row['moneda_simbolo'],
            'cont_cuenta_id' => intval($row['cont_cuenta_id'] ?? 0),
            'banco'          => $row['banco'] ?? null
        ];
    }
    mysqli_stmt_close($stmt);
    return $cuentas;
}

/**
 * Registra una transferencia interna entre cuentas de la misma empresa (D12 / §4.6)
 */
function registrarTransferenciaInterna($conexion, $datos, $empresa_id, $usuario_id)
{
    $empresa_id         = intval($empresa_id);
    $usuario_id         = intval($usuario_id);
    $sucursal_id        = intval($datos['sucursal_id'] ?? 0);
    $f_movimiento       = !empty($datos['f_movimiento']) ? $datos['f_movimiento'] : date('Y-m-d');
    $cuenta_origen_id   = intval($datos['cuenta_origen_id'] ?? 0);
    $cuenta_destino_id  = intval($datos['cuenta_destino_id'] ?? 0);
    $importe            = floatval($datos['importe'] ?? 0);
    $moneda_id          = emp_moneda_id($conexion, $empresa_id, $datos['moneda_id'] ?? 0);
    $observaciones      = trim($datos['observaciones'] ?? '');

    // Validaciones
    if ($cuenta_origen_id <= 0 || $cuenta_destino_id <= 0) {
        throw new Exception("Debe especificar la cuenta de origen y la cuenta de destino.");
    }

    // Regla RT14: Origen y destino no pueden ser la misma cuenta
    if ($cuenta_origen_id === $cuenta_destino_id) {
        throw new Exception("La cuenta de origen y destino no pueden ser iguales (Regla RT14).");
    }

    if ($importe <= 0) {
        throw new Exception("El importe a transferir debe ser mayor a cero.");
    }

    mysqli_begin_transaction($conexion);

    try {
        // Validar y bloquear cuenta origen
        $sql_co = "SELECT tesoreria_cuenta_id, tesoreria_cuenta, cuenta_tipo, saldo_actual, permite_saldo_negativo, cont_cuenta_id, empresa_id
                   FROM gestion__tesoreria_cuentas 
                   WHERE tesoreria_cuenta_id = ? AND empresa_id = ?
                   FOR UPDATE";
        $stmt_co = mysqli_prepare($conexion, $sql_co);
        mysqli_stmt_bind_param($stmt_co, "ii", $cuenta_origen_id, $empresa_id);
        mysqli_stmt_execute($stmt_co);
        $cta_orig = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_co));
        mysqli_stmt_close($stmt_co);

        if (!$cta_orig) throw new Exception("La cuenta de origen no existe o no pertenece a la empresa.");

        // Validar saldo origen
        $saldo_orig = floatval($cta_orig['saldo_actual']);
        if (intval($cta_orig['permite_saldo_negativo']) !== 1 && $saldo_orig < $importe) {
            throw new Exception("Saldo insuficiente en cuenta origen '{$cta_orig['tesoreria_cuenta']}'. Saldo disponible: $" . fmt_numero($saldo_orig, 2) . ", importe requerido: $" . fmt_numero($importe, 2));
        }

        // Validar y bloquear cuenta destino
        $sql_cd = "SELECT tesoreria_cuenta_id, tesoreria_cuenta, cuenta_tipo, saldo_actual, cont_cuenta_id, empresa_id
                   FROM gestion__tesoreria_cuentas 
                   WHERE tesoreria_cuenta_id = ? AND empresa_id = ?
                   FOR UPDATE";
        $stmt_cd = mysqli_prepare($conexion, $sql_cd);
        mysqli_stmt_bind_param($stmt_cd, "ii", $cuenta_destino_id, $empresa_id);
        mysqli_stmt_execute($stmt_cd);
        $cta_dest = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cd));
        mysqli_stmt_close($stmt_cd);

        if (!$cta_dest) throw new Exception("La cuenta de destino no existe o no pertenece a la empresa.");

        // Obtener siguiente correlativo de comprobante para TB (Transferencia)
        $prefijo = emp_punto_venta_id($conexion, $empresa_id);   // punto de venta de la empresa
        if ($prefijo <= 0) throw new Exception('La empresa no tiene puntos de venta: no se puede numerar la transferencia.');
        $res_n = $conexion->query("SELECT COALESCE(MAX(comprobante_nro), 0) + 1 AS prox_nro FROM gestion__comprobantes WHERE empresa_id = $empresa_id AND comprobante_tipo_id = " . COMP_TIPO_TRANSFERENCIA . " AND comprobante_pv = $prefijo");
        $numero = intval($res_n->fetch_assoc()['prox_nro'] ?? 1);

        // Obtener identificador único para idx_origen (tabla_origen_id = 264)
        $res_reg = $conexion->query("SELECT COALESCE(MAX(registro_origen_id), 0) + 1 AS prox_reg FROM gestion__comprobantes WHERE tabla_origen_id = 264");
        $registro_origen_id = intval($res_reg->fetch_assoc()['prox_reg'] ?? 1);
        $tabla_origen_id = 264;
        $modulo = 'TESORERIA';
        $entidad_id = 0;

        // 1. Insertar Cabecera de Comprobante
        $concepto_completo = "Traspaso de fondos: {$cta_orig['tesoreria_cuenta']} -> {$cta_dest['tesoreria_cuenta']}" . ($observaciones ? " ({$observaciones})" : "");

        $sql_comp = "INSERT INTO gestion__comprobantes (
                        tabla_origen_id, registro_origen_id, modulo, entidad_id,
                        empresa_id, sucursal_id, comprobante_tipo_id, comprobante_pv, comprobante_nro,
                        f_emision, f_contabilidad, f_vto, moneda_id, tipo_cambio,
                        importe_bruto, importe_neto, importe_total, importe_pendiente,
                        observaciones, tabla_estado_registro_id, usuario_id
                     ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1.0, ?, ?, ?, 0.0, ?, ?, ?)";

        $stmt_c = mysqli_prepare($conexion, $sql_comp);
        $est_conf = ESTADO_CONFIRMADO;
        $comp_tipo_id = COMP_TIPO_TRANSFERENCIA;
        mysqli_stmt_bind_param(
            $stmt_c, "iisiiiiiisssidddsii",
            $tabla_origen_id, $registro_origen_id, $modulo, $entidad_id,
            $empresa_id, $sucursal_id, $comp_tipo_id, $prefijo, $numero,
            $f_movimiento, $f_movimiento, $f_movimiento, $moneda_id,
            $importe, $importe, $importe,
            $concepto_completo, $est_conf, $usuario_id
        );
        mysqli_stmt_execute($stmt_c);
        $comprobante_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt_c);

        if ($comprobante_id > 0) {
            $conexion->query("UPDATE gestion__comprobantes SET registro_origen_id = $comprobante_id WHERE comprobante_id = $comprobante_id");
        }

        // 2. Movimiento de Tesorería: EGRESO de cuenta origen (D15)
        tesoreriaRegistrarMovimiento($conexion, [
            'empresa_id'          => $empresa_id,
            'sucursal_id'         => $sucursal_id,
            'tesoreria_cuenta_id' => $cuenta_origen_id,
            'comprobante_id'      => $comprobante_id,
            'tipo_movimiento'     => 'EGRESO',
            'importe'             => $importe,
            'f_movimiento'        => $f_movimiento,
            'f_valor'             => $f_movimiento,
            'moneda_id'           => $moneda_id,
            'tipo_cambio'         => 1.0,
            'medio_pago_id'       => 1, // Efectivo / Fondos directos
            'concepto'            => "Transferencia a {$cta_dest['tesoreria_cuenta']}" . ($observaciones ? ": $observaciones" : ""),
            'tabla_origen_id'     => TABLA_COMPROBANTES_ID,
            'registro_origen_id'  => $comprobante_id,
            'usuario_id'          => $usuario_id
        ]);

        // 3. Movimiento de Tesorería: INGRESO en cuenta destino (D15)
        tesoreriaRegistrarMovimiento($conexion, [
            'empresa_id'          => $empresa_id,
            'sucursal_id'         => $sucursal_id,
            'tesoreria_cuenta_id' => $cuenta_destino_id,
            'comprobante_id'      => $comprobante_id,
            'tipo_movimiento'     => 'INGRESO',
            'importe'             => $importe,
            'f_movimiento'        => $f_movimiento,
            'f_valor'             => $f_movimiento,
            'moneda_id'           => $moneda_id,
            'tipo_cambio'         => 1.0,
            'medio_pago_id'       => 1,
            'concepto'            => "Transferencia desde {$cta_orig['tesoreria_cuenta']}" . ($observaciones ? ": $observaciones" : ""),
            'tabla_origen_id'     => TABLA_COMPROBANTES_ID,
            'registro_origen_id'  => $comprobante_id,
            'usuario_id'          => $usuario_id
        ]);

        // 4. Asiento Contable Automático: DEBE Destino / HABER Origen (D27 / D36)
        $cta_cont_dest = intval($cta_dest['cont_cuenta_id']);
        if ($cta_cont_dest <= 0) {
            $concepto_desc = ($cta_dest['cuenta_tipo'] === 'BANCO') ? 'Banco' : 'Caja';
            $r_cc_dest = tesoreriaResolverCuentaContable($conexion, $empresa_id, $concepto_desc);
            $cta_cont_dest = $r_cc_dest['cont_cuenta_id'];
        }

        $cta_cont_orig = intval($cta_orig['cont_cuenta_id']);
        if ($cta_cont_orig <= 0) {
            $concepto_desc = ($cta_orig['cuenta_tipo'] === 'BANCO') ? 'Banco' : 'Caja';
            $r_cc_orig = tesoreriaResolverCuentaContable($conexion, $empresa_id, $concepto_desc);
            $cta_cont_orig = $r_cc_orig['cont_cuenta_id'];
        }

        $asiento_id = tesoreriaGenerarAsiento($conexion, [
            'empresa_id'           => $empresa_id,
            'sucursal_id'          => $sucursal_id,
            'comprobante_id'       => $comprobante_id,
            'entidad_id'           => 0,
            'f_asiento'            => $f_movimiento,
            'descripcion'          => "Transferencia interna TB {$prefijo}-{$numero}: {$cta_orig['tesoreria_cuenta']} -> {$cta_dest['tesoreria_cuenta']}",
            'usuario_id'           => $usuario_id,
        ], [
            [
                'cuenta_id'   => $cta_cont_dest,
                'debe'        => $importe,
                'haber'       => 0.0,
                'descripcion' => "Ingreso fondos en {$cta_dest['tesoreria_cuenta']}"
            ],
            [
                'cuenta_id'   => $cta_cont_orig,
                'debe'        => 0.0,
                'haber'       => $importe,
                'descripcion' => "Egreso fondos de {$cta_orig['tesoreria_cuenta']}"
            ]
        ]);

        mysqli_commit($conexion);

        return [
            'success'        => true,
            'comprobante_id' => $comprobante_id,
            'numero'         => emp_numero_comprobante($conexion, $empresa_id, $prefijo, $numero),
            'asiento_id'     => $asiento_id,
            'mensaje'        => "Transferencia N° " . emp_numero_comprobante($conexion, $empresa_id, $prefijo, $numero) . " registrada con éxito."
        ];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("Error en registrarTransferenciaInterna: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Anula una transferencia interna y revierte saldos y asientos
 */
function anularTransferenciaInterna($conexion, $comprobante_id, $empresa_id, $usuario_id)
{
    $comprobante_id = intval($comprobante_id);
    $empresa_id     = intval($empresa_id);
    $usuario_id     = intval($usuario_id);

    mysqli_begin_transaction($conexion);

    try {
        // Bloquear comprobante
        $sql = "SELECT c.*, tm_egr.tesoreria_cuenta_id AS origen_id, tm_ing.tesoreria_cuenta_id AS destino_id
                FROM gestion__comprobantes c
                LEFT JOIN gestion__tesoreria_movimientos tm_egr ON tm_egr.comprobante_id = c.comprobante_id AND tm_egr.tipo_movimiento = 'EGRESO'
                LEFT JOIN gestion__tesoreria_movimientos tm_ing ON tm_ing.comprobante_id = c.comprobante_id AND tm_ing.tipo_movimiento = 'INGRESO'
                WHERE c.comprobante_id = ? AND c.empresa_id = ?
                FOR UPDATE";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $comprobante_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $comp = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$comp) throw new Exception("Transferencia no encontrada.");
        if (intval($comp['tabla_estado_registro_id']) === ESTADO_ANULADO) {
            throw new Exception("La transferencia ya se encuentra anulada.");
        }
        cont_periodo_exigir_comprobante($conexion, $empresa_id, $comprobante_id, 'anular la transferencia');

        $monto = floatval($comp['importe_total']);
        $origen_id = intval($comp['origen_id']);
        $destino_id = intval($comp['destino_id']);
        $f_hoy = date('Y-m-d');

        // Reversión de saldos en tesorería:
        // Egreso de la cuenta destino (donde habían entrado los fondos)
        if ($destino_id > 0) {
            tesoreriaRegistrarMovimiento($conexion, [
                'empresa_id'          => $empresa_id,
                'sucursal_id'         => intval($comp['sucursal_id']),
                'tesoreria_cuenta_id' => $destino_id,
                'comprobante_id'      => $comprobante_id,
                'tipo_movimiento'     => 'EGRESO',
                'importe'             => $monto,
                'f_movimiento'        => $f_hoy,
                'f_valor'             => $f_hoy,
                'moneda_id'           => intval($comp['moneda_id']),
                'tipo_cambio'         => 1.0,
                'medio_pago_id'       => 1,
                'concepto'            => "Anulación de Transferencia N° {$comp['comprobante_pv']}-{$comp['comprobante_nro']}",
                'tabla_origen_id'     => TABLA_COMPROBANTES_ID,
                'registro_origen_id'  => $comprobante_id,
                'usuario_id'          => $usuario_id
            ]);
        }

        // Ingreso en la cuenta origen (de donde habían salido los fondos)
        if ($origen_id > 0) {
            tesoreriaRegistrarMovimiento($conexion, [
                'empresa_id'          => $empresa_id,
                'sucursal_id'         => intval($comp['sucursal_id']),
                'tesoreria_cuenta_id' => $origen_id,
                'comprobante_id'      => $comprobante_id,
                'tipo_movimiento'     => 'INGRESO',
                'importe'             => $monto,
                'f_movimiento'        => $f_hoy,
                'f_valor'             => $f_hoy,
                'moneda_id'           => intval($comp['moneda_id']),
                'tipo_cambio'         => 1.0,
                'medio_pago_id'       => 1,
                'concepto'            => "Reversa por Anulación Transferencia N° {$comp['comprobante_pv']}-{$comp['comprobante_nro']}",
                'tabla_origen_id'     => TABLA_COMPROBANTES_ID,
                'registro_origen_id'  => $comprobante_id,
                'usuario_id'          => $usuario_id
            ]);
        }

        // Marcar comprobante como ANULADO (38)
        $sql_upd = "UPDATE gestion__comprobantes 
                    SET tabla_estado_registro_id = ?, usuario_modificacion_id = ?, fecha_modificacion = NOW()
                    WHERE comprobante_id = ?";
        $stmt_u = mysqli_prepare($conexion, $sql_upd);
        $est_anul = ESTADO_ANULADO;
        mysqli_stmt_bind_param($stmt_u, "iii", $est_anul, $usuario_id, $comprobante_id);
        mysqli_stmt_execute($stmt_u);
        mysqli_stmt_close($stmt_u);

        // Anular asiento contable asociado: 2 (Inactivo), igual que pagos y cobranzas; el cierre de
        // ejercicio excluye los asientos con estado 2.
        $conexion->query("UPDATE gestion__cont_asientos
                          SET tabla_estado_registro_id = 2, usuario_modificacion_id = $usuario_id
                          WHERE comprobante_id = $comprobante_id");

        mysqli_commit($conexion);

        return [
            'success' => true,
            'mensaje' => "Transferencia N° " . emp_numero_comprobante($conexion, $empresa_id, $comp['comprobante_pv'], $comp['comprobante_nro']) . " anulada correctamente con reversión de saldos."
        ];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("Error al anular transferencia $comprobante_id: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Obtiene el comprobante_tipo_id de Movimiento de Caja (MC) para la empresa.
 */
function obtenerTipoComprobanteMC($conexion, int $empresa_id): int
{
    $stmt = mysqli_prepare($conexion, "SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos WHERE empresa_id = ? AND codigo = 'MC' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $empresa_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ? intval($row['comprobante_tipo_id']) : COMP_TIPO_TRANSFERENCIA;
}

/**
 * Listado de Otros Ingresos / Egresos de Tesorería (comprobantes soporte MC con ciclo de vida + legados [OTRO_MOV]).
 */
function obtenerOtrosMovimientosListado($conexion, $empresa_id, $filtros = [], $pagina_id = 0)
{
    $empresa_id = intval($empresa_id);
    $mc_tipo_id = obtenerTipoComprobanteMC($conexion, $empresa_id);
    $sql = "SELECT c.comprobante_id, c.sucursal_id, c.f_emision, c.comprobante_nro AS numero,
                   " . emp_sql_pv_mostrar('c.comprobante_pv', 'c.empresa_id') . " AS prefijo,
                   c.comprobante_tipo_id, ct.codigo AS tipo_codigo,
                   c.importe_total, c.observaciones, c.tabla_estado_registro_id,
                   s.sucursal_nombre AS sucursal, m.simbolo AS moneda_simbolo,
                   er.codigo_estandar AS estado_codigo, er.estado_registro,
                   COALESCE(sp.tipo_movimiento, tm.tipo_movimiento, 'INGRESO') AS tipo_movimiento,
                   COALESCE(sp.tesoreria_cuenta_id, tm.tesoreria_cuenta_id, 0) AS tesoreria_cuenta_id,
                   COALESCE(tc_sp.tesoreria_cuenta, tc_tm.tesoreria_cuenta, '') AS cuenta_nombre,
                   COALESCE(sp.cont_cuenta_contrapartida_id, 0) AS cont_cuenta_contrapartida_id,
                   CONCAT(COALESCE(cc.codigo, ''), CASE WHEN cc.codigo IS NOT NULL THEN ' - ' ELSE '' END, COALESCE(cc.nombre, '')) AS cuenta_contable,
                   COALESCE(sp.entidad_id, c.entidad_id, 0) AS entidad_id,
                   COALESCE(e.entidad_nombre, '') AS entidad_nombre,
                   sp.observacion_aprobacion,
                   COALESCE(tm.conciliado, 0) AS conciliado
            FROM gestion__comprobantes c
            LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
            LEFT JOIN gestion__tesoreria_comprobantes_soporte sp ON sp.comprobante_id = c.comprobante_id
            LEFT JOIN gestion__tesoreria_movimientos tm ON tm.comprobante_id = c.comprobante_id AND tm.reversa_de_movimiento_id IS NULL
            LEFT JOIN gestion__tesoreria_cuentas tc_sp ON tc_sp.tesoreria_cuenta_id = sp.tesoreria_cuenta_id
            LEFT JOIN gestion__tesoreria_cuentas tc_tm ON tc_tm.tesoreria_cuenta_id = tm.tesoreria_cuenta_id
            LEFT JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = sp.cont_cuenta_contrapartida_id
            LEFT JOIN gestion__entidades e ON e.entidad_id = COALESCE(sp.entidad_id, c.entidad_id)
            LEFT JOIN gestion__sucursales s ON s.sucursal_id = c.sucursal_id
            LEFT JOIN gestion__monedas m ON m.moneda_id = c.moneda_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = c.tabla_estado_registro_id
            WHERE c.empresa_id = ?
              AND c.tabla_estado_registro_id <> 6
              AND (sp.soporte_id IS NOT NULL OR c.comprobante_tipo_id = ? OR (c.comprobante_tipo_id = ? AND c.observaciones LIKE '[OTRO_MOV]%'))";

    $params = [$empresa_id, $mc_tipo_id, COMP_TIPO_TRANSFERENCIA];
    $types  = "iii";
    if (!empty($filtros['desde'])) { $sql .= " AND c.f_emision >= ?"; $params[] = $filtros['desde']; $types .= "s"; }
    if (!empty($filtros['hasta'])) { $sql .= " AND c.f_emision <= ?"; $params[] = $filtros['hasta']; $types .= "s"; }
    if (!empty($filtros['cuenta_id'])) { $sql .= " AND COALESCE(sp.tesoreria_cuenta_id, tm.tesoreria_cuenta_id) = ?"; $params[] = intval($filtros['cuenta_id']); $types .= "i"; }
    if (!empty($filtros['estado_id'])) { $sql .= " AND c.tabla_estado_registro_id = ?"; $params[] = intval($filtros['estado_id']); $types .= "i"; }
    $sql .= " GROUP BY c.comprobante_id ORDER BY c.f_emision DESC, c.comprobante_id DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $puede_ver       = $pagina_id > 0 && (permisos_puede($conexion, $pagina_id, 'visualizar') || permisos_puede($conexion, $pagina_id, 'consultar'));
    $puede_agregar   = $pagina_id > 0 && permisos_puede($conexion, $pagina_id, 'agregar');
    $puede_editar    = $pagina_id > 0 && permisos_puede($conexion, $pagina_id, 'editar');
    $puede_confirmar = $pagina_id > 0 && permisos_puede($conexion, $pagina_id, 'confirmar');
    $puede_aprobar   = $pagina_id > 0 && permisos_puede($conexion, $pagina_id, 'aprobar');
    $puede_rechazar  = $pagina_id > 0 && permisos_puede($conexion, $pagina_id, 'rechazar');
    $puede_eliminar  = $pagina_id > 0 && permisos_puede($conexion, $pagina_id, 'eliminar');
    $puede_anular    = $pagina_id > 0 && permisos_puede($conexion, $pagina_id, 'anular');

    $filas = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $obs = preg_replace('/^\[OTRO_MOV\]\s*/', '', (string)($r['observaciones'] ?? ''));
        $est = intval($r['tabla_estado_registro_id']);
        $pref = str_pad((string)$r['prefijo'], 4, '0', STR_PAD_LEFT) . '-' . str_pad((string)$r['numero'], 8, '0', STR_PAD_LEFT);
        $cod  = trim((string)($r['tipo_codigo'] ?? 'MC'));
        $filas[] = [
            'comprobante_id'               => intval($r['comprobante_id']),
            'sucursal_id'                  => intval($r['sucursal_id']),
            'f_emision'                    => $r['f_emision'],
            'numero'                       => ($cod !== '' ? "$cod " : '') . $pref,
            'sucursal'                     => $r['sucursal'] ?? '',
            'tipo_movimiento'              => $r['tipo_movimiento'] ?? 'INGRESO',
            'tesoreria_cuenta_id'          => intval($r['tesoreria_cuenta_id']),
            'cuenta_nombre'                => $r['cuenta_nombre'] ?? '',
            'cont_cuenta_contrapartida_id' => intval($r['cont_cuenta_contrapartida_id']),
            'cuenta_contable'              => $r['cuenta_contable'] ?? '',
            'entidad_id'                   => intval($r['entidad_id']),
            'entidad_nombre'               => $r['entidad_nombre'] ?? '',
            'importe_total'                => floatval($r['importe_total']),
            'moneda_simbolo'               => $r['moneda_simbolo'] ?? '$',
            'observaciones'                => $obs,
            'observacion_aprobacion'       => $r['observacion_aprobacion'] ?? '',
            'conciliado'                   => intval($r['conciliado'] ?? 0) === 1,
            'estado_id'                    => $est,
            'estado_registro'              => $r['estado_registro'] ?? ($est === 3 ? 'Borrador' : ($est === 4 ? 'Pendiente Aprobación' : ($est === 5 ? 'Confirmado' : 'Anulado'))),
            'puede_ver'                    => $puede_ver,
            'puede_editar'                 => $puede_editar && $est === 3,
            'puede_enviar_aprobacion'      => ($puede_agregar || $puede_editar) && $est === 3,
            'puede_confirmar'              => $puede_confirmar && $est === 3,
            'puede_aprobar'                => $puede_aprobar && $est === 4,
            'puede_rechazar'               => $puede_rechazar && $est === 4,
            'puede_eliminar'               => $puede_eliminar && in_array($est, [3, 4], true),
            'puede_anular'                 => $puede_anular && $est === 5,
        ];
    }
    mysqli_stmt_close($stmt);
    return ['success' => true, 'data' => $filas];
}

/**
 * Obtiene el detalle completo de un comprobante soporte MC (para editar o imprimir).
 */
function obtenerOtroMovimientoPorId($conexion, int $comprobante_id, int $empresa_id): ?array
{
    $sql = "SELECT c.comprobante_id, c.empresa_id, c.sucursal_id, c.f_emision, c.comprobante_pv, c.comprobante_nro,
                   c.importe_total, c.observaciones, c.tabla_estado_registro_id, c.fecha_creacion,
                   ct.codigo AS tipo_codigo, ct.comprobante_tipo,
                   emp.empresa AS empresa_nombre, emp.documento_numero AS empresa_cuit,
                   s.sucursal_nombre AS sucursal, m.simbolo AS moneda_simbolo,
                   er.estado_registro,
                   COALESCE(sp.tipo_movimiento, tm.tipo_movimiento, 'INGRESO') AS tipo_movimiento,
                   COALESCE(sp.tesoreria_cuenta_id, tm.tesoreria_cuenta_id, 0) AS tesoreria_cuenta_id,
                   COALESCE(tc_sp.tesoreria_cuenta, tc_tm.tesoreria_cuenta, '') AS cuenta_nombre,
                   COALESCE(sp.cont_cuenta_contrapartida_id, 0) AS cont_cuenta_contrapartida_id,
                   cc.codigo AS cont_codigo, cc.nombre AS cont_nombre,
                   COALESCE(sp.entidad_id, c.entidad_id, 0) AS entidad_id,
                   e.entidad_nombre, e.cuit AS entidad_cuit,
                   u_crea.usuario_nombre AS usuario_creador,
                   u_apr.usuario_nombre AS usuario_aprobador,
                   sp.fecha_aprobacion, sp.observacion_aprobacion,
                   (SELECT a.cont_asiento_id FROM gestion__cont_asientos a WHERE a.comprobante_id = c.comprobante_id AND a.tabla_estado_registro_id IN (1, 5) ORDER BY a.cont_asiento_id DESC LIMIT 1) AS asiento_id
            FROM gestion__comprobantes c
            LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
            LEFT JOIN conf__empresas emp ON emp.empresa_id = c.empresa_id
            LEFT JOIN gestion__tesoreria_comprobantes_soporte sp ON sp.comprobante_id = c.comprobante_id
            LEFT JOIN gestion__tesoreria_movimientos tm ON tm.comprobante_id = c.comprobante_id AND tm.reversa_de_movimiento_id IS NULL
            LEFT JOIN gestion__tesoreria_cuentas tc_sp ON tc_sp.tesoreria_cuenta_id = sp.tesoreria_cuenta_id
            LEFT JOIN gestion__tesoreria_cuentas tc_tm ON tc_tm.tesoreria_cuenta_id = tm.tesoreria_cuenta_id
            LEFT JOIN gestion__cont_cuentas cc ON cc.cont_cuenta_id = sp.cont_cuenta_contrapartida_id
            LEFT JOIN gestion__entidades e ON e.entidad_id = COALESCE(sp.entidad_id, c.entidad_id)
            LEFT JOIN gestion__sucursales s ON s.sucursal_id = c.sucursal_id
            LEFT JOIN gestion__monedas m ON m.moneda_id = c.moneda_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = c.tabla_estado_registro_id
            LEFT JOIN conf__usuarios u_crea ON u_crea.usuario_id = c.usuario_id
            LEFT JOIN conf__usuarios u_apr ON u_apr.usuario_id = sp.usuario_aprobacion_id
            WHERE c.comprobante_id = ? AND c.empresa_id = ?
            LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $comprobante_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    if (!$row) return null;
    $row['observaciones'] = preg_replace('/^\[OTRO_MOV\]\s*/', '', (string)($row['observaciones'] ?? ''));
    $row['numero_formateado'] = trim((string)($row['tipo_codigo'] ?? 'MC')) . ' '
        . str_pad((string)$row['comprobante_pv'], 4, '0', STR_PAD_LEFT) . '-'
        . str_pad((string)$row['comprobante_nro'], 8, '0', STR_PAD_LEFT);
    return $row;
}

/**
 * Crea (o actualiza en Borrador) un Comprobante Soporte de Tesorería (MC).
 * Por defecto nace en Borrador (3) SIN mover caja/banco ni generar asiento hasta su confirmación/aprobación.
 */
function registrarOtroMovimientoTesoreria($conexion, $datos, $empresa_id, $usuario_id)
{
    $empresa_id     = intval($empresa_id);
    $usuario_id     = intval($usuario_id);
    $comprobante_id = intval($datos['comprobante_id'] ?? 0);
    $tipo           = strtoupper(trim((string)($datos['tipo_movimiento'] ?? $datos['tipo'] ?? '')));
    $tc_id          = intval($datos['tesoreria_cuenta_id'] ?? 0);
    $cp_id          = intval($datos['cont_cuenta_contrapartida_id'] ?? 0);
    $entidad_id     = intval($datos['entidad_id'] ?? 0);
    $suc_id         = intval($datos['sucursal_id'] ?? 0);
    $f_mov          = !empty($datos['f_movimiento']) ? $datos['f_movimiento'] : date('Y-m-d');
    $importe        = round(floatval($datos['importe'] ?? 0), 2);
    $concepto       = mb_substr(trim((string)($datos['concepto'] ?? $datos['observaciones'] ?? '')), 0, 200);
    $estado_inicial = intval($datos['estado_inicial'] ?? 3);
    if (!in_array($estado_inicial, [3, 4], true)) $estado_inicial = 3;

    if (!in_array($tipo, ['INGRESO', 'EGRESO'], true)) throw new Exception('El tipo debe ser INGRESO o EGRESO.');
    if ($tc_id <= 0) throw new Exception('Seleccione una cuenta de tesorería.');
    if ($cp_id <= 0) throw new Exception('Seleccione la cuenta contable contrapartida.');
    if ($importe <= 0) throw new Exception('El importe debe ser mayor a cero.');
    if ($concepto === '') throw new Exception('Ingrese un concepto para el comprobante.');

    mysqli_begin_transaction($conexion);
    try {
        $stmt_tc = mysqli_prepare($conexion, "SELECT tesoreria_cuenta_id, tesoreria_cuenta, sucursal_id, moneda_id, cont_cuenta_id
                                              FROM gestion__tesoreria_cuentas
                                              WHERE tesoreria_cuenta_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1");
        mysqli_stmt_bind_param($stmt_tc, 'ii', $tc_id, $empresa_id);
        mysqli_stmt_execute($stmt_tc);
        $tc = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_tc));
        mysqli_stmt_close($stmt_tc);
        if (!$tc) throw new Exception('Cuenta de tesorería no encontrada.');
        if (intval($tc['cont_cuenta_id']) <= 0) throw new Exception("La cuenta {$tc['tesoreria_cuenta']} no tiene cuenta contable asociada.");

        $stmt_cp = mysqli_prepare($conexion, "SELECT cont_cuenta_id, es_imputable FROM gestion__cont_cuentas WHERE cont_cuenta_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1");
        mysqli_stmt_bind_param($stmt_cp, 'ii', $cp_id, $empresa_id);
        mysqli_stmt_execute($stmt_cp);
        $cp = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_cp));
        mysqli_stmt_close($stmt_cp);
        if (!$cp || intval($cp['es_imputable']) !== 1) throw new Exception('La cuenta contable contrapartida debe ser imputable.');

        if ($suc_id <= 0) $suc_id = intval($tc['sucursal_id'] ?: 0);
        if ($suc_id <= 0) {
            $rs = $conexion->query("SELECT sucursal_id FROM gestion__sucursales WHERE empresa_id = $empresa_id AND tabla_estado_registro_id = 1 ORDER BY sucursal_id ASC LIMIT 1");
            $suc_id = intval(($rs ? $rs->fetch_assoc()['sucursal_id'] : 1) ?? 1);
        }
        $moneda_id = intval($tc['moneda_id'] ?: emp_moneda_id($conexion, $empresa_id, 0));
        $ent_null  = $entidad_id > 0 ? $entidad_id : null;
        $signo     = $tipo === 'INGRESO' ? 1 : -1;

        if ($comprobante_id > 0) {
            // Edición de Borrador existente
            $stmt_chk = mysqli_prepare($conexion, "SELECT comprobante_id, tabla_estado_registro_id, comprobante_pv, comprobante_nro FROM gestion__comprobantes WHERE comprobante_id = ? AND empresa_id = ? FOR UPDATE");
            mysqli_stmt_bind_param($stmt_chk, 'ii', $comprobante_id, $empresa_id);
            mysqli_stmt_execute($stmt_chk);
            $prev = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_chk));
            mysqli_stmt_close($stmt_chk);
            if (!$prev) throw new Exception('Comprobante no encontrado.');
            if (intval($prev['tabla_estado_registro_id']) !== 3) throw new Exception('Solo se pueden editar comprobantes en estado Borrador.');

            $stmt_u = mysqli_prepare($conexion, "UPDATE gestion__comprobantes
                SET sucursal_id = ?, entidad_id = ?, f_emision = ?, f_contabilidad = ?, moneda_id = ?,
                    importe_total = ?, signo = ?, tabla_estado_registro_id = ?, observaciones = ?,
                    usuario_modificacion_id = ?, fecha_modificacion = NOW()
                WHERE comprobante_id = ? AND empresa_id = ?");
            mysqli_stmt_bind_param($stmt_u, 'iissidiisiii',
                $suc_id, $entidad_id, $f_mov, $f_mov, $moneda_id,
                $importe, $signo, $estado_inicial, $concepto,
                $usuario_id, $comprobante_id, $empresa_id
            );
            mysqli_stmt_execute($stmt_u);
            mysqli_stmt_close($stmt_u);

            $stmt_sp = mysqli_prepare($conexion, "UPDATE gestion__tesoreria_comprobantes_soporte
                SET tipo_movimiento = ?, tesoreria_cuenta_id = ?, cont_cuenta_contrapartida_id = ?, entidad_id = ?
                WHERE comprobante_id = ? AND empresa_id = ?");
            mysqli_stmt_bind_param($stmt_sp, 'siiiii', $tipo, $tc_id, $cp_id, $ent_null, $comprobante_id, $empresa_id);
            mysqli_stmt_execute($stmt_sp);
            mysqli_stmt_close($stmt_sp);

            $numero_fmt = 'MC ' . str_pad((string)$prev['comprobante_pv'], 4, '0', STR_PAD_LEFT) . '-' . str_pad((string)$prev['comprobante_nro'], 8, '0', STR_PAD_LEFT);
        } else {
            $mc_tipo_id = obtenerTipoComprobanteMC($conexion, $empresa_id);
            $prefijo    = 1;
            $res_n      = $conexion->query("SELECT COALESCE(MAX(comprobante_nro), 0) + 1 AS prox_nro FROM gestion__comprobantes WHERE empresa_id = $empresa_id AND comprobante_tipo_id = $mc_tipo_id AND comprobante_pv = $prefijo");
            $prox_nro   = intval($res_n->fetch_assoc()['prox_nro'] ?? 1);
            $tabla_ori  = TABLA_COMPROBANTES_ID;
            $mod        = 'TESORERIA';

            $stmt_c = mysqli_prepare($conexion, "INSERT INTO gestion__comprobantes (
                    tabla_origen_id, registro_origen_id, modulo, empresa_id, sucursal_id,
                    comprobante_tipo_id, comprobante_pv, comprobante_nro, entidad_id,
                    f_emision, f_contabilidad, moneda_id, tipo_cambio,
                    importe_total, importe_pendiente, signo, tabla_estado_registro_id,
                    observaciones, usuario_id, usuario_modificacion_id
                ) VALUES (?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1.0, ?, 0, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_c, 'isiiiiiissidiisii',
                $tabla_ori, $mod, $empresa_id, $suc_id,
                $mc_tipo_id, $prefijo, $prox_nro, $entidad_id,
                $f_mov, $f_mov, $moneda_id, $importe,
                $signo, $estado_inicial, $concepto, $usuario_id, $usuario_id
            );
            if (!mysqli_stmt_execute($stmt_c)) throw new Exception('Error creando comprobante soporte: ' . mysqli_stmt_error($stmt_c));
            $comprobante_id = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmt_c);
            $conexion->query("UPDATE gestion__comprobantes SET registro_origen_id = $comprobante_id WHERE comprobante_id = $comprobante_id");

            $stmt_sp = mysqli_prepare($conexion, "INSERT INTO gestion__tesoreria_comprobantes_soporte
                (empresa_id, comprobante_id, tipo_movimiento, tesoreria_cuenta_id, cont_cuenta_contrapartida_id, entidad_id)
                VALUES (?, ?, ?, ?, ?, ?)");
            mysqli_stmt_bind_param($stmt_sp, 'iisiii', $empresa_id, $comprobante_id, $tipo, $tc_id, $cp_id, $ent_null);
            if (!mysqli_stmt_execute($stmt_sp)) throw new Exception('Error guardando detalle de soporte: ' . mysqli_stmt_error($stmt_sp));
            mysqli_stmt_close($stmt_sp);

            $numero_fmt = 'MC ' . str_pad((string)$prefijo, 4, '0', STR_PAD_LEFT) . '-' . str_pad((string)$prox_nro, 8, '0', STR_PAD_LEFT);
        }

        mysqli_commit($conexion);

        $est_txt = $estado_inicial === 4 ? 'Pendiente de Aprobación' : 'Borrador';
        return [
            'success'        => true,
            'comprobante_id' => intval($comprobante_id),
            'numero'         => $numero_fmt,
            'estado_id'      => $estado_inicial,
            'message'        => "Comprobante $numero_fmt guardado en $est_txt (pendiente de confirmar/aprobar para impactar caja y contabilidad).",
        ];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        throw $e;
    }
}

/**
 * Envía un comprobante MC de Borrador (3) a Pendiente de Aprobación (4).
 */
function enviarAprobacionOtroMovimiento($conexion, int $comprobante_id, int $empresa_id, int $usuario_id): array
{
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__comprobantes
        SET tabla_estado_registro_id = 4, usuario_modificacion_id = ?, fecha_modificacion = NOW()
        WHERE comprobante_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 3");
    mysqli_stmt_bind_param($stmt, 'iii', $usuario_id, $comprobante_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $af = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($af <= 0) throw new Exception('El comprobante debe estar en Borrador para enviarlo a aprobación.');
    return ['success' => true, 'message' => 'Comprobante enviado a Pendiente de Aprobación.'];
}

/**
 * Rechaza un comprobante MC en Pendiente de Aprobación (4) y lo devuelve a Borrador (3).
 */
function rechazarOtroMovimiento($conexion, int $comprobante_id, int $empresa_id, int $usuario_id, string $motivo = ''): array
{
    $motivo = mb_substr(trim($motivo), 0, 250);
    mysqli_begin_transaction($conexion);
    try {
        $stmt = mysqli_prepare($conexion, "UPDATE gestion__comprobantes
            SET tabla_estado_registro_id = 3, usuario_modificacion_id = ?, fecha_modificacion = NOW()
            WHERE comprobante_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 4");
        mysqli_stmt_bind_param($stmt, 'iii', $usuario_id, $comprobante_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $af = mysqli_stmt_affected_rows($stmt);
        mysqli_stmt_close($stmt);
        if ($af <= 0) throw new Exception('El comprobante debe estar Pendiente de Aprobación para rechazarlo.');

        $stmt_sp = mysqli_prepare($conexion, "UPDATE gestion__tesoreria_comprobantes_soporte
            SET observacion_aprobacion = ? WHERE comprobante_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmt_sp, 'sii', $motivo, $comprobante_id, $empresa_id);
        mysqli_stmt_execute($stmt_sp);
        mysqli_stmt_close($stmt_sp);

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Comprobante devuelto a Borrador.'];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        throw $e;
    }
}

/**
 * Confirma (3 -> 5) o Aprueba (4 -> 5) un comprobante soporte MC:
 * recién aquí impacta tesorería (saldo + conciliable en banco) y genera el asiento contable automático.
 */
function confirmarOtroMovimientoTesoreria($conexion, int $comprobante_id, int $empresa_id, int $usuario_id, bool $es_aprobacion = false): array
{
    mysqli_begin_transaction($conexion);
    try {
        $stmt = mysqli_prepare($conexion, "SELECT c.comprobante_id, c.sucursal_id, c.f_emision, c.moneda_id, c.importe_total,
                                                  c.observaciones, c.tabla_estado_registro_id, c.comprobante_pv, c.comprobante_nro,
                                                  sp.tipo_movimiento, sp.tesoreria_cuenta_id, sp.cont_cuenta_contrapartida_id, sp.entidad_id
                                           FROM gestion__comprobantes c
                                           JOIN gestion__tesoreria_comprobantes_soporte sp ON sp.comprobante_id = c.comprobante_id
                                           WHERE c.comprobante_id = ? AND c.empresa_id = ?
                                           FOR UPDATE");
        mysqli_stmt_bind_param($stmt, 'ii', $comprobante_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$row) throw new Exception('Comprobante soporte no encontrado.');

        $est_actual = intval($row['tabla_estado_registro_id']);
        $est_esperado = $es_aprobacion ? 4 : 3;
        if ($est_actual !== $est_esperado) {
            throw new Exception($es_aprobacion
                ? 'El comprobante debe estar Pendiente de Aprobación para aprobarlo.'
                : 'El comprobante debe estar en Borrador para confirmarlo.');
        }

        $f_mov    = $row['f_emision'];
        $tipo     = $row['tipo_movimiento'];
        $tc_id    = intval($row['tesoreria_cuenta_id']);
        $cp_id    = intval($row['cont_cuenta_contrapartida_id']);
        $suc_id   = intval($row['sucursal_id']);
        $mon_id   = intval($row['moneda_id']);
        $importe  = round(floatval($row['importe_total']), 2);
        $concepto = trim((string)$row['observaciones']);
        $num_fmt  = 'MC ' . str_pad((string)$row['comprobante_pv'], 4, '0', STR_PAD_LEFT) . '-' . str_pad((string)$row['comprobante_nro'], 8, '0', STR_PAD_LEFT);

        cont_periodo_exigir_abierto($conexion, $empresa_id, $f_mov, 'confirmar el comprobante de tesorería');

        $stmt_tc = mysqli_prepare($conexion, "SELECT tesoreria_cuenta_id, tesoreria_cuenta, cont_cuenta_id, saldo_actual, permite_saldo_negativo
                                              FROM gestion__tesoreria_cuentas
                                              WHERE tesoreria_cuenta_id = ? AND empresa_id = ? AND tabla_estado_registro_id = 1 FOR UPDATE");
        mysqli_stmt_bind_param($stmt_tc, 'ii', $tc_id, $empresa_id);
        mysqli_stmt_execute($stmt_tc);
        $tc = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_tc));
        mysqli_stmt_close($stmt_tc);
        if (!$tc) throw new Exception('Cuenta de tesorería no encontrada.');
        if (intval($tc['cont_cuenta_id']) <= 0) throw new Exception("La cuenta {$tc['tesoreria_cuenta']} no tiene cuenta contable asociada.");
        if ($tipo === 'EGRESO' && intval($tc['permite_saldo_negativo']) === 0 && floatval($tc['saldo_actual']) < $importe) {
            throw new Exception("Saldo insuficiente en {$tc['tesoreria_cuenta']}.");
        }

        tesoreriaRegistrarMovimiento($conexion, [
            'empresa_id'          => $empresa_id,
            'sucursal_id'         => $suc_id,
            'tesoreria_cuenta_id' => $tc_id,
            'comprobante_id'      => $comprobante_id,
            'tipo_movimiento'     => $tipo,
            'importe'             => $importe,
            'f_movimiento'        => $f_mov,
            'f_valor'             => $f_mov,
            'moneda_id'           => $mon_id,
            'tipo_cambio'         => 1.0,
            'medio_pago_id'       => 1,
            'concepto'            => "$num_fmt - $concepto",
            'tabla_origen_id'     => TABLA_COMPROBANTES_ID,
            'registro_origen_id'  => $comprobante_id,
            'usuario_id'          => $usuario_id,
        ]);

        $cta_tes_cont = intval($tc['cont_cuenta_id']);
        $lineas = $tipo === 'INGRESO'
            ? [
                ['cuenta_id' => $cta_tes_cont, 'debe' => $importe, 'haber' => 0.0,      'descripcion' => "$num_fmt - $concepto"],
                ['cuenta_id' => $cp_id,        'debe' => 0.0,      'haber' => $importe, 'descripcion' => "$num_fmt - $concepto"],
              ]
            : [
                ['cuenta_id' => $cp_id,        'debe' => $importe, 'haber' => 0.0,      'descripcion' => "$num_fmt - $concepto"],
                ['cuenta_id' => $cta_tes_cont, 'debe' => 0.0,      'haber' => $importe, 'descripcion' => "$num_fmt - $concepto"],
              ];
        $asiento_id = tesoreriaGenerarAsiento($conexion, [
            'empresa_id'     => $empresa_id,
            'sucursal_id'    => $suc_id,
            'comprobante_id' => $comprobante_id,
            'entidad_id'     => intval($row['entidad_id'] ?? 0),
            'f_asiento'      => $f_mov,
            'descripcion'    => "$tipo Tesorería $num_fmt: $concepto",
            'usuario_id'     => $usuario_id,
        ], $lineas);

        $est_conf = ESTADO_CONFIRMADO;
        $stmt_u = mysqli_prepare($conexion, "UPDATE gestion__comprobantes
            SET tabla_estado_registro_id = ?, usuario_modificacion_id = ?, fecha_modificacion = NOW()
            WHERE comprobante_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmt_u, 'iiii', $est_conf, $usuario_id, $comprobante_id, $empresa_id);
        mysqli_stmt_execute($stmt_u);
        mysqli_stmt_close($stmt_u);

        $stmt_sp = mysqli_prepare($conexion, "UPDATE gestion__tesoreria_comprobantes_soporte
            SET usuario_aprobacion_id = ?, fecha_aprobacion = NOW(), observacion_aprobacion = NULL
            WHERE comprobante_id = ? AND empresa_id = ?");
        mysqli_stmt_bind_param($stmt_sp, 'iii', $usuario_id, $comprobante_id, $empresa_id);
        mysqli_stmt_execute($stmt_sp);
        mysqli_stmt_close($stmt_sp);

        mysqli_commit($conexion);
        $accion_txt = $es_aprobacion ? 'aprobado' : 'confirmado';
        return [
            'success'        => true,
            'comprobante_id' => $comprobante_id,
            'asiento_id'     => intval($asiento_id),
            'message'        => "Comprobante $num_fmt $accion_txt e impactado en {$tc['tesoreria_cuenta']} (Asiento #$asiento_id).",
        ];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        throw $e;
    }
}

/**
 * Elimina un comprobante soporte MC en Borrador (3) o Pendiente (4) sin impacto contable ni de caja.
 */
function eliminarOtroMovimientoBorrador($conexion, int $comprobante_id, int $empresa_id, int $usuario_id): array
{
    $stmt = mysqli_prepare($conexion, "UPDATE gestion__comprobantes
        SET tabla_estado_registro_id = 6, usuario_modificacion_id = ?, fecha_modificacion = NOW()
        WHERE comprobante_id = ? AND empresa_id = ? AND tabla_estado_registro_id IN (3, 4)");
    mysqli_stmt_bind_param($stmt, 'iii', $usuario_id, $comprobante_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $af = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);
    if ($af <= 0) throw new Exception('Solo se pueden eliminar comprobantes en Borrador o Pendiente de Aprobación.');
    return ['success' => true, 'message' => 'Comprobante borrador eliminado correctamente.'];
}

/**
 * Anula un Otro Ingreso / Egreso de Tesorería confirmado (revierte movimiento y anula asiento).
 */
function anularOtroMovimientoTesoreria($conexion, $comprobante_id, $empresa_id, $usuario_id)
{
    $comprobante_id = intval($comprobante_id);
    $empresa_id     = intval($empresa_id);
    $usuario_id     = intval($usuario_id);

    mysqli_begin_transaction($conexion);
    try {
        $stmt = mysqli_prepare($conexion, "SELECT c.comprobante_id, c.tabla_estado_registro_id
                                           FROM gestion__comprobantes c
                                           LEFT JOIN gestion__tesoreria_comprobantes_soporte sp ON sp.comprobante_id = c.comprobante_id
                                           WHERE c.comprobante_id = ? AND c.empresa_id = ?
                                             AND (sp.soporte_id IS NOT NULL OR c.observaciones LIKE '[OTRO_MOV]%')
                                           FOR UPDATE");
        mysqli_stmt_bind_param($stmt, 'ii', $comprobante_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $comp = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$comp) throw new Exception('Comprobante no encontrado.');
        if (intval($comp['tabla_estado_registro_id']) === ESTADO_ANULADO) throw new Exception('El comprobante ya está anulado.');
        if (intval($comp['tabla_estado_registro_id']) !== ESTADO_CONFIRMADO) throw new Exception('Solo se anulan comprobantes confirmados (los borradores se eliminan).');

        cont_periodo_exigir_comprobante($conexion, $empresa_id, $comprobante_id, 'anular el comprobante');

        $res_mv = $conexion->query("SELECT tesoreria_movimiento_id FROM gestion__tesoreria_movimientos WHERE comprobante_id = $comprobante_id AND reversa_de_movimiento_id IS NULL");
        while ($mv = $res_mv->fetch_assoc()) {
            tesoreriaAnularMovimiento($conexion, intval($mv['tesoreria_movimiento_id']), $usuario_id, 'Anulación de Comprobante de Tesorería');
        }
        $est_anul = ESTADO_ANULADO;
        $conexion->query("UPDATE gestion__comprobantes SET tabla_estado_registro_id = $est_anul, usuario_modificacion_id = $usuario_id, fecha_modificacion = NOW() WHERE comprobante_id = $comprobante_id");
        $conexion->query("UPDATE gestion__cont_asientos SET tabla_estado_registro_id = 2, usuario_modificacion_id = $usuario_id WHERE comprobante_id = $comprobante_id");

        mysqli_commit($conexion);
        return ['success' => true, 'message' => 'Comprobante anulado y saldo revertido correctamente.'];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        throw $e;
    }
}
