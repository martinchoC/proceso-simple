<?php
/**
 * cheques_cartera_model.php
 *
 * Modelo para la Administración de la Cartera de Cheques y Ciclo de Vida
 * Sistema gestion_multipyme - Módulo Tesorería
 *
 * Cumple con §4.5 del documento técnico:
 * - Cheques de Terceros: EN_CARTERA (2172) -> DEPOSITADO (2174) -> ACREDITADO (2176) / RECHAZADO (2178) / ENDOSADO (2175).
 * - Cheques Propios: EMITIDO (2173) -> DEBITADO (2176) / RECHAZADO (2178).
 * - Regla D28 / C2: Cheques propios diferidos y acreditación con asiento contable.
 * - Regla D15: Movimientos en Libro de Tesorería automáticos.
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/tesoreria_core_model.php';

$conexion = $conn;

const ESTADO_CHQ_BORRADOR   = 2171;
const ESTADO_CHQ_EN_CARTERA = 2172;
const ESTADO_CHQ_EMITIDO    = 2173;
const ESTADO_CHQ_DEPOSITADO = 2174;
const ESTADO_CHQ_ENDOSADO   = 2175;
const ESTADO_CHQ_DEBITADO   = 2176;
const ESTADO_CHQ_DEVUELTO   = 2177;
const ESTADO_CHQ_RECHAZADO  = 2178;
const ESTADO_CHQ_ANULADO    = 2179;
const ESTADO_CHQ_ELIMINADO  = 2180;

const TABLA_CHEQUES_ID      = 288;

/**
 * Obtiene el listado de cheques con filtros
 */
function obtenerChequesListado($conexion, $empresa_id, $filtros = [])
{
    $empresa_id = intval($empresa_id);

    $sql = "SELECT chq.*, b.banco, m.simbolo AS moneda_simbolo,
                   e.entidad_nombre, e.cuit AS entidad_cuit,
                   tc.tesoreria_cuenta,
                   er.codigo_estandar AS estado_codigo, er.estado_registro
            FROM gestion__cheques chq
            JOIN conf__bancos b ON b.banco_id = chq.banco_id
            JOIN gestion__monedas m ON m.moneda_id = chq.moneda_id
            LEFT JOIN gestion__entidades e ON e.entidad_id = chq.entidad_id
            LEFT JOIN gestion__tesoreria_cuentas tc ON tc.tesoreria_cuenta_id = chq.tesoreria_cuenta_id
            LEFT JOIN conf__tablas_estados_registros ter ON ter.tabla_estado_registro_id = chq.tabla_estado_registro_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = ter.estado_registro_id
            WHERE chq.empresa_id = ? ";

    $params = [$empresa_id];
    $types  = "i";

    if (!empty($filtros['tipo']) && in_array($filtros['tipo'], ['TERCERO', 'PROPIO'])) {
        $sql .= " AND chq.cheque_tipo = ? ";
        $params[] = $filtros['tipo'];
        $types .= "s";
    }

    if (!empty($filtros['estado_id'])) {
        $sql .= " AND chq.tabla_estado_registro_id = ? ";
        $params[] = intval($filtros['estado_id']);
        $types .= "i";
    }

    if (!empty($filtros['desde'])) {
        $sql .= " AND chq.f_pago >= ? ";
        $params[] = $filtros['desde'];
        $types .= "s";
    }

    if (!empty($filtros['hasta'])) {
        $sql .= " AND chq.f_pago <= ? ";
        $params[] = $filtros['hasta'];
        $types .= "s";
    }

    if (!empty($filtros['banco_id'])) {
        $sql .= " AND chq.banco_id = ? ";
        $params[] = intval($filtros['banco_id']);
        $types .= "i";
    }

    $sql .= " ORDER BY chq.f_pago ASC, chq.cheque_id DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['data' => []];

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $datos = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $id = intval($row['cheque_id']);
        $tipo = $row['cheque_tipo'];
        $estado_id = intval($row['tabla_estado_registro_id']);
        $estado_cod = $row['estado_codigo'] ?? '';

        // Badge de Tipo
        $tipo_badge = ($tipo === 'TERCERO')
            ? '<span class="badge bg-info-subtle text-info border border-info-subtle"><i class="fas fa-hand-holding-usd me-1"></i>Tercero</span>'
            : '<span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="fas fa-check-double me-1"></i>Propio</span>';

        // Badge de Estado
        if ($estado_id === ESTADO_CHQ_EN_CARTERA) {
            $estado_badge = '<span class="badge bg-success"><i class="fas fa-wallet me-1"></i>En Cartera</span>';
        } elseif ($estado_id === ESTADO_CHQ_EMITIDO) {
            $estado_badge = '<span class="badge bg-primary"><i class="fas fa-paper-plane me-1"></i>Emitido</span>';
        } elseif ($estado_id === ESTADO_CHQ_DEPOSITADO) {
            $estado_badge = '<span class="badge bg-warning text-dark"><i class="fas fa-university me-1"></i>Depositado</span>';
        } elseif ($estado_id === ESTADO_CHQ_ENDOSADO) {
            $estado_badge = '<span class="badge bg-secondary"><i class="fas fa-exchange-alt me-1"></i>Endosado</span>';
        } elseif ($estado_id === ESTADO_CHQ_DEBITADO) {
            $estado_badge = '<span class="badge bg-dark"><i class="fas fa-check-circle me-1"></i>Debitado</span>';
        } elseif ($estado_id === ESTADO_CHQ_RECHAZADO) {
            $estado_badge = '<span class="badge bg-danger"><i class="fas fa-ban me-1"></i>Rechazado</span>';
        } elseif ($estado_id === ESTADO_CHQ_ANULADO) {
            $estado_badge = '<span class="badge bg-danger-subtle text-danger border"><i class="fas fa-times me-1"></i>Anulado</span>';
        } else {
            $estado_badge = '<span class="badge bg-secondary">' . htmlspecialchars($row['estado_registro'] ?? 'Estado ' . $estado_id) . '</span>';
        }

        // Indicador Diferido / Común
        $es_dif = intval($row['es_diferido'] ?? 0);
        $dif_badge = ($es_dif === 1)
            ? '<span class="badge bg-light text-dark border" title="Cheque Diferido">Diferido</span>'
            : '<span class="badge bg-light text-muted border" title="Cheque Común">Común</span>';

        // Botones de acción según estado y tipo
        $botones = '<div class="btn-group btn-group-sm" role="group">';
        $botones .= '<button type="button" class="btn btn-outline-info btn-accion-chq" data-id="' . $id . '" data-accion="ver" title="Ver Detalle"><i class="fas fa-eye"></i></button>';

        if ($tipo === 'TERCERO') {
            if ($estado_id === ESTADO_CHQ_EN_CARTERA) {
                $botones .= '<button type="button" class="btn btn-outline-success btn-accion-chq" data-id="' . $id . '" data-accion="depositar" title="Depositar en Banco"><i class="fas fa-university"></i></button>';
                $botones .= '<button type="button" class="btn btn-outline-danger btn-accion-chq" data-id="' . $id . '" data-accion="rechazar" title="Rechazar Cheque"><i class="fas fa-times-circle"></i></button>';
            } elseif ($estado_id === ESTADO_CHQ_DEPOSITADO) {
                $botones .= '<button type="button" class="btn btn-outline-dark btn-accion-chq" data-id="' . $id . '" data-accion="acreditar" title="Marcar como Acreditado"><i class="fas fa-check-double"></i></button>';
                $botones .= '<button type="button" class="btn btn-outline-danger btn-accion-chq" data-id="' . $id . '" data-accion="rechazar" title="Rechazar Cheque Depositado"><i class="fas fa-times-circle"></i></button>';
            }
        } elseif ($tipo === 'PROPIO') {
            if ($estado_id === ESTADO_CHQ_EMITIDO) {
                $botones .= '<button type="button" class="btn btn-outline-primary btn-accion-chq" data-id="' . $id . '" data-accion="debitar" title="Confirmar Débito Bancario"><i class="fas fa-check-circle"></i></button>';
                $botones .= '<button type="button" class="btn btn-outline-danger btn-accion-chq" data-id="' . $id . '" data-accion="rechazar" title="Registrar Rechazo de Cheque Propio"><i class="fas fa-times-circle"></i></button>';
            }
        }

        $botones .= '</div>';

        // Titular / Librador / Entidad
        $titular = !empty($row['librador_nombre']) ? htmlspecialchars($row['librador_nombre']) : htmlspecialchars($row['entidad_nombre'] ?? 'S/D');

        $datos[] = [
            'id'           => $id,
            'tipo'         => $tipo_badge,
            'numero'       => '<strong>' . htmlspecialchars($row['numero']) . '</strong><br><small class="text-muted">' . $dif_badge . '</small>',
            'banco'        => htmlspecialchars($row['banco']),
            'f_emision'    => date('d/m/Y', strtotime($row['f_emision'])),
            'f_pago'       => '<span class="fw-bold">' . date('d/m/Y', strtotime($row['f_pago'])) . '</span>',
            'titular'      => $titular,
            'cuenta'       => htmlspecialchars($row['tesoreria_cuenta'] ?? '-'),
            'importe'      => '<span class="fw-bold fs-6">$' . number_format(floatval($row['importe']), 2, ',', '.') . '</span>',
            'estado'       => $estado_badge,
            'acciones'     => $botones
        ];
    }
    mysqli_stmt_close($stmt);

    return ['data' => $datos];
}

/**
 * Obtiene el resumen de importes y cantidades de la cartera
 */
function obtenerResumenCartera($conexion, $empresa_id)
{
    $empresa_id = intval($empresa_id);

    $sql = "SELECT 
                SUM(CASE WHEN cheque_tipo = 'TERCERO' AND tabla_estado_registro_id = 2172 THEN importe ELSE 0 END) AS cartera_total,
                COUNT(CASE WHEN cheque_tipo = 'TERCERO' AND tabla_estado_registro_id = 2172 THEN 1 END) AS cartera_cant,

                SUM(CASE WHEN cheque_tipo = 'TERCERO' AND tabla_estado_registro_id = 2174 THEN importe ELSE 0 END) AS depositados_total,
                COUNT(CASE WHEN cheque_tipo = 'TERCERO' AND tabla_estado_registro_id = 2174 THEN 1 END) AS depositados_cant,

                SUM(CASE WHEN cheque_tipo = 'PROPIO' AND tabla_estado_registro_id = 2173 THEN importe ELSE 0 END) AS propios_total,
                COUNT(CASE WHEN cheque_tipo = 'PROPIO' AND tabla_estado_registro_id = 2173 THEN 1 END) AS propios_cant,

                SUM(CASE WHEN tabla_estado_registro_id = 2178 THEN importe ELSE 0 END) AS rechazados_total,
                COUNT(CASE WHEN tabla_estado_registro_id = 2178 THEN 1 END) AS rechazados_cant
            FROM gestion__cheques
            WHERE empresa_id = ?";

    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    return [
        'cartera'     => ['total' => floatval($row['cartera_total'] ?? 0), 'cant' => intval($row['cartera_cant'] ?? 0)],
        'depositados' => ['total' => floatval($row['depositados_total'] ?? 0), 'cant' => intval($row['depositados_cant'] ?? 0)],
        'propios'     => ['total' => floatval($row['propios_total'] ?? 0), 'cant' => intval($row['propios_cant'] ?? 0)],
        'rechazados'  => ['total' => floatval($row['rechazados_total'] ?? 0), 'cant' => intval($row['rechazados_cant'] ?? 0)]
    ];
}

/**
 * Deposita un cheque de tercero en una cuenta bancaria (D15 / §4.5)
 */
function depositarChequeTercero($conexion, $cheque_id, $banco_cuenta_id, $f_deposito, $empresa_id, $usuario_id)
{
    $cheque_id       = intval($cheque_id);
    $banco_cuenta_id = intval($banco_cuenta_id);
    $empresa_id      = intval($empresa_id);
    $usuario_id      = intval($usuario_id);
    $f_deposito      = !empty($f_deposito) ? $f_deposito : date('Y-m-d');

    if ($banco_cuenta_id <= 0) {
        throw new Exception("Debe seleccionar una cuenta bancaria de destino para el depósito.");
    }

    mysqli_begin_transaction($conexion);

    try {
        // Lock del cheque
        $sql = "SELECT chq.*, b.banco, m.simbolo AS moneda_simbolo
                FROM gestion__cheques chq
                JOIN conf__bancos b ON b.banco_id = chq.banco_id
                JOIN gestion__monedas m ON m.moneda_id = chq.moneda_id
                WHERE chq.cheque_id = ? AND chq.empresa_id = ?
                FOR UPDATE";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $cheque_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $chq = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$chq) throw new Exception("Cheque no encontrado.");
        if ($chq['cheque_tipo'] !== 'TERCERO') throw new Exception("Solo se pueden depositar cheques de terceros.");
        if (intval($chq['tabla_estado_registro_id']) !== ESTADO_CHQ_EN_CARTERA) {
            throw new Exception("El cheque no está en estado En Cartera (estado actual: {$chq['tabla_estado_registro_id']}).");
        }

        // Obtener cuenta de Valores en Cartera (origen)
        $cta_valores_id = intval($chq['tesoreria_cuenta_id']);
        if ($cta_valores_id <= 0) {
            $res_v = $conexion->query("SELECT tesoreria_cuenta_id FROM gestion__tesoreria_cuentas WHERE empresa_id = $empresa_id AND cuenta_tipo = 'VALORES' LIMIT 1");
            $cta_valores_id = intval($res_v->fetch_assoc()['tesoreria_cuenta_id'] ?? 6);
        }

        // Obtener cuenta contable del banco destino
        $res_cb = $conexion->query("SELECT tesoreria_cuenta, cont_cuenta_id FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = $banco_cuenta_id");
        $banco_info = $res_cb->fetch_assoc();
        if (!$banco_info) throw new Exception("Cuenta bancaria destino no válida.");
        $cta_contable_banco = intval($banco_info['cont_cuenta_id']);

        $monto = floatval($chq['importe']);

        // 1. Transición de estado a DEPOSITADO (2174)
        $sql_upd = "UPDATE gestion__cheques 
                    SET tabla_estado_registro_id = ?, tesoreria_cuenta_id = ?, usuario_modificacion_id = ?, fecha_modificacion = NOW()
                    WHERE cheque_id = ?";
        $stmt_u = mysqli_prepare($conexion, $sql_upd);
        $est_dep = ESTADO_CHQ_DEPOSITADO;
        mysqli_stmt_bind_param($stmt_u, "iiii", $est_dep, $banco_cuenta_id, $usuario_id, $cheque_id);
        mysqli_stmt_execute($stmt_u);
        mysqli_stmt_close($stmt_u);

        // 2. Movimiento de Tesorería: EGRESO de Valores en Cartera (D15)
        tesoreriaRegistrarMovimiento($conexion, [
            'empresa_id'          => $empresa_id,
            'sucursal_id'         => intval($chq['sucursal_id']),
            'tesoreria_cuenta_id' => $cta_valores_id,
            'comprobante_id'      => intval($chq['comp_origen_id']),
            'tipo_movimiento'     => 'EGRESO',
            'importe'             => $monto,
            'f_movimiento'        => $f_deposito,
            'f_valor'             => $f_deposito,
            'moneda_id'           => intval($chq['moneda_id']),
            'tipo_cambio'         => 1.0,
            'medio_pago_id'       => 2, // Cheque de tercero
            'concepto'            => "Depósito Chq N° {$chq['numero']} en {$banco_info['tesoreria_cuenta']}",
            'tabla_origen_id'     => TABLA_CHEQUES_ID,
            'registro_origen_id'  => $cheque_id,
            'usuario_id'          => $usuario_id
        ]);

        // 3. Movimiento de Tesorería: INGRESO en Cuenta Bancaria destino (D15)
        tesoreriaRegistrarMovimiento($conexion, [
            'empresa_id'          => $empresa_id,
            'sucursal_id'         => intval($chq['sucursal_id']),
            'tesoreria_cuenta_id' => $banco_cuenta_id,
            'comprobante_id'      => intval($chq['comp_origen_id']),
            'tipo_movimiento'     => 'INGRESO',
            'importe'             => $monto,
            'f_movimiento'        => $f_deposito,
            'f_valor'             => $f_deposito,
            'moneda_id'           => intval($chq['moneda_id']),
            'tipo_cambio'         => 1.0,
            'medio_pago_id'       => 2, // Cheque de tercero
            'concepto'            => "Depósito Chq N° {$chq['numero']} de {$chq['banco']}",
            'tabla_origen_id'     => TABLA_CHEQUES_ID,
            'registro_origen_id'  => $cheque_id,
            'usuario_id'          => $usuario_id
        ]);

        // 4. Asiento Contable Automático: DEBE Banco / HABER Valores a Depositar (D27 / D36)
        $res_val = tesoreriaResolverCuentaContable($conexion, $empresa_id, 'Valores en Cartera', 851);
        $res_ban = tesoreriaResolverCuentaContable($conexion, $empresa_id, 'Banco', $cta_contable_banco);

        tesoreriaGenerarAsiento($conexion, [
            'empresa_id'           => $empresa_id,
            'sucursal_id'          => intval($chq['sucursal_id']),
            'comprobante_id'       => intval($chq['comp_origen_id']),
            'entidad_id'           => intval($chq['entidad_id'] ?? 0),
            'f_asiento'            => $f_deposito,
            'descripcion'          => "Depósito Cheque N° {$chq['numero']} en {$banco_info['tesoreria_cuenta']}",
            'usuario_id'           => $usuario_id,
            'cont_tipo_asiento_id' => 2 // AUT
        ], [
            [
                'cuenta_id'   => $res_ban['cont_cuenta_id'],
                'debe'        => $monto,
                'haber'       => 0.0,
                'descripcion' => "Depósito Cheque N° {$chq['numero']}"
            ],
            [
                'cuenta_id'   => $res_val['cont_cuenta_id'],
                'debe'        => 0.0,
                'haber'       => $monto,
                'descripcion' => "Salida de Valores en Cartera"
            ]
        ]);

        mysqli_commit($conexion);

        return [
            'success' => true,
            'message' => "Cheque N° {$chq['numero']} depositado con éxito en {$banco_info['tesoreria_cuenta']}."
        ];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("Error depositando cheque $cheque_id: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Marca como debitado en banco un cheque propio (D28 / C2)
 */
function debitarChequePropio($conexion, $cheque_id, $f_debito, $empresa_id, $usuario_id)
{
    $cheque_id  = intval($cheque_id);
    $empresa_id = intval($empresa_id);
    $usuario_id = intval($usuario_id);
    $f_debito   = !empty($f_debito) ? $f_debito : date('Y-m-d');

    mysqli_begin_transaction($conexion);

    try {
        $sql = "SELECT chq.*, b.banco, tc.tesoreria_cuenta, tc.cont_cuenta_id AS cont_cuenta_id_banco
                FROM gestion__cheques chq
                JOIN conf__bancos b ON b.banco_id = chq.banco_id
                JOIN gestion__tesoreria_cuentas tc ON tc.tesoreria_cuenta_id = chq.tesoreria_cuenta_id
                WHERE chq.cheque_id = ? AND chq.empresa_id = ?
                FOR UPDATE";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $cheque_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $chq = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$chq) throw new Exception("Cheque propio no encontrado.");
        if ($chq['cheque_tipo'] !== 'PROPIO') throw new Exception("Solo se pueden debitar cheques propios.");
        if (intval($chq['tabla_estado_registro_id']) !== ESTADO_CHQ_EMITIDO) {
            throw new Exception("El cheque propio debe estar en estado Emitido.");
        }

        $monto = floatval($chq['importe']);
        $cta_banco = intval($chq['tesoreria_cuenta_id']);

        // 1. Transición a DEBITADO (2176)
        $sql_upd = "UPDATE gestion__cheques 
                    SET tabla_estado_registro_id = ?, usuario_modificacion_id = ?, fecha_modificacion = NOW()
                    WHERE cheque_id = ?";
        $stmt_u = mysqli_prepare($conexion, $sql_upd);
        $est_deb = ESTADO_CHQ_DEBITADO;
        mysqli_stmt_bind_param($stmt_u, "iii", $est_deb, $usuario_id, $cheque_id);
        mysqli_stmt_execute($stmt_u);
        mysqli_stmt_close($stmt_u);

        // 2. Si era diferido, impactar egreso de tesorería y asiento Valores a Pagar -> Banco (D28/C2)
        if (intval($chq['es_diferido']) === 1) {
            tesoreriaRegistrarMovimiento($conexion, [
                'empresa_id'          => $empresa_id,
                'sucursal_id'         => intval($chq['sucursal_id']),
                'tesoreria_cuenta_id' => $cta_banco,
                'comprobante_id'      => intval($chq['comp_origen_id']),
                'tipo_movimiento'     => 'EGRESO',
                'importe'             => $monto,
                'f_movimiento'        => $f_debito,
                'f_valor'             => $f_debito,
                'moneda_id'           => intval($chq['moneda_id']),
                'tipo_cambio'         => 1.0,
                'medio_pago_id'       => 3, // Cheque propio
                'concepto'            => "Débito bancario Chq Propio N° {$chq['numero']}",
                'tabla_origen_id'     => TABLA_CHEQUES_ID,
                'registro_origen_id'  => $cheque_id,
                'usuario_id'          => $usuario_id
            ]);

            // Asiento contable: DEBE Valores a Pagar / HABER Banco
            $res_vap = tesoreriaResolverCuentaContable($conexion, $empresa_id, 'VALORES_A_PAGAR');
            $res_ban = tesoreriaResolverCuentaContable($conexion, $empresa_id, 'Banco', intval($chq['cont_cuenta_id_banco']));

            tesoreriaGenerarAsiento($conexion, [
                'empresa_id'           => $empresa_id,
                'sucursal_id'          => intval($chq['sucursal_id']),
                'comprobante_id'       => intval($chq['comp_origen_id']),
                'entidad_id'           => intval($chq['entidad_id'] ?? 0),
                'f_asiento'            => $f_debito,
                'descripcion'          => "Compensación Débito Chq Propio N° {$chq['numero']}",
                'usuario_id'           => $usuario_id,
                'cont_tipo_asiento_id' => 2 // AUT
            ], [
                [
                    'cuenta_id'   => $res_vap['cont_cuenta_id'],
                    'debe'        => $monto,
                    'haber'       => 0.0,
                    'descripcion' => "Cancelación Valores a Pagar Chq N° {$chq['numero']}"
                ],
                [
                    'cuenta_id'   => $res_ban['cont_cuenta_id'],
                    'debe'        => 0.0,
                    'haber'       => $monto,
                    'descripcion' => "Débito en cuenta {$chq['tesoreria_cuenta']}"
                ]
            ]);
        }

        mysqli_commit($conexion);

        return [
            'success' => true,
            'message' => "Cheque propio N° {$chq['numero']} marcado como debitado correctamente."
        ];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("Error debitando cheque propio $cheque_id: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Registra el rechazo de un cheque con motivo obligatorio
 */
function rechazarCheque($conexion, $cheque_id, $motivo, $f_rechazo, $empresa_id, $usuario_id)
{
    $cheque_id  = intval($cheque_id);
    $empresa_id = intval($empresa_id);
    $usuario_id = intval($usuario_id);
    $motivo     = trim($motivo);
    $f_rechazo  = !empty($f_rechazo) ? $f_rechazo : date('Y-m-d');

    if (empty($motivo)) {
        throw new Exception("Debe ingresar el motivo de rechazo del cheque.");
    }

    mysqli_begin_transaction($conexion);

    try {
        $sql = "SELECT * FROM gestion__cheques WHERE cheque_id = ? AND empresa_id = ? FOR UPDATE";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $cheque_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $chq = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);

        if (!$chq) throw new Exception("Cheque no encontrado.");

        $est_ant = intval($chq['tabla_estado_registro_id']);
        $monto = floatval($chq['importe']);

        // Transición a RECHAZADO (2178)
        $sql_upd = "UPDATE gestion__cheques 
                    SET tabla_estado_registro_id = 2178, motivo_rechazo = ?, usuario_modificacion_id = ?, fecha_modificacion = NOW()
                    WHERE cheque_id = ?";
        $stmt_u = mysqli_prepare($conexion, $sql_upd);
        mysqli_stmt_bind_param($stmt_u, "sii", $motivo, $usuario_id, $cheque_id);
        mysqli_stmt_execute($stmt_u);
        mysqli_stmt_close($stmt_u);

        // Si estaba depositado, debitar el monto del banco donde se depositó
        if ($est_ant === ESTADO_CHQ_DEPOSITADO && !empty($chq['tesoreria_cuenta_id'])) {
            tesoreriaRegistrarMovimiento($conexion, [
                'empresa_id'          => $empresa_id,
                'sucursal_id'         => intval($chq['sucursal_id']),
                'tesoreria_cuenta_id' => intval($chq['tesoreria_cuenta_id']),
                'comprobante_id'      => intval($chq['comp_origen_id']),
                'tipo_movimiento'     => 'EGRESO',
                'importe'             => $monto,
                'f_movimiento'        => $f_rechazo,
                'f_valor'             => $f_rechazo,
                'moneda_id'           => intval($chq['moneda_id']),
                'tipo_cambio'         => 1.0,
                'medio_pago_id'       => 2,
                'concepto'            => "Rechazo bancario Chq N° {$chq['numero']}: $motivo",
                'tabla_origen_id'     => TABLA_CHEQUES_ID,
                'registro_origen_id'  => $cheque_id,
                'usuario_id'          => $usuario_id
            ]);
        }

        mysqli_commit($conexion);

        return [
            'success' => true,
            'message' => "Cheque N° {$chq['numero']} registrado como RECHAZADO."
        ];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("Error rechazando cheque $cheque_id: " . $e->getMessage());
        throw $e;
    }
}
