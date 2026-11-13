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
            WHERE c.empresa_id = ? AND c.comprobante_tipo_id = ? ";

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
