<?php
require_once __DIR__ . '/../../core/empresa_config.php';
/**
 * pagos_model.php
 *
 * Modelo para la Gestión de Pagos a Proveedores (Órdenes de Pago OP)
 * Sistema gestion_multipyme - Módulo Tesorería
 *
 * Cumple estrictamente con las especificaciones de docs/gestion/08_proc_tesoreria_v3_11.docx:
 * - §4.3: Orden de Pago (gestion__pagos + gestion__pagos_medios + comprobante OP).
 * - §2.5: Patrón de confirmación transaccional atómico y orden de locks (RT16 / RT21).
 * - §2.6: Patrón de anulación y reversión íntegra.
 * - C2 / RT06 / RT10: Endoso de cheques de terceros (en cartera -> entregado / reversa).
 * - D28 / C2: Cheques propios diferidos (no impactan banco de inmediato; contrapartida VALORES_A_PAGAR).
 * - D35 / D36: Resolución en cascada de cuentas contables.
 * - D27 / D36 / Anexo D: Asiento contable automático balanceado.
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/tesoreria_core_model.php';

$conexion = $conn;

const ESTADO_PAGO_BORRADOR   = 3;
const ESTADO_PAGO_CONFIRMADO = 5;
const ESTADO_PAGO_ANULADO    = 38;
const ESTADO_PAGO_ELIMINADO  = 6;

const TIPO_COMPROBANTE_OP_ID = 26;  // Orden de Pago por defecto
const TABLA_PAGOS_ID         = 276; // gestion__pagos en conf__tablas

/**
 * Resuelve el ID de comprobante_tipo para Orden de Pago de la empresa dada.
 */
function pagoTipoComprobanteOpId($conexion, $empresa_id): int
{
    $empresa_id = intval($empresa_id);
    if (function_exists('emp_comprobante_tipo_id')) {
        $id = emp_comprobante_tipo_id($conexion, $empresa_id, 'OP');
        if ($id > 0) return $id;
    }
    $stmt = mysqli_prepare($conexion, "SELECT comprobante_tipo_id FROM gestion__comprobantes_tipos WHERE empresa_id = ? AND codigo = 'OP' AND tabla_estado_registro_id = 1 LIMIT 1");
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $empresa_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($res)) {
            mysqli_stmt_close($stmt);
            return intval($row['comprobante_tipo_id']);
        }
        mysqli_stmt_close($stmt);
    }
    return TIPO_COMPROBANTE_OP_ID;
}

/**
 * Sincroniza la Orden de Pago con gestion__comprobantes (D1 / §2.5 / D23)
 */
function syncComprobantePago($conexion, $pago_id, $data)
{
    $pago_id = intval($pago_id);
    if ($pago_id <= 0) return null;

    $empresa_id          = intval($data['empresa_id'] ?? 0);
    $sucursal_id         = intval($data['sucursal_id'] ?? 0);
    $comprobante_pv      = intval($data['comprobante_pv'] ?? 0);
    $comprobante_nro     = intval($data['comprobante_nro'] ?? 0);
    $entidad_id          = intval($data['entidad_id'] ?? 0);
    $entidad_sucursal_id = !empty($data['entidad_sucursal_id']) ? intval($data['entidad_sucursal_id']) : null;
    $f_emision           = !empty($data['f_emision']) ? $data['f_emision'] : date('Y-m-d');
    $f_contabilidad      = !empty($data['f_contabilidad']) ? $data['f_contabilidad'] : $f_emision;
    $moneda_id           = emp_moneda_id($conexion, $empresa_id, $data['moneda_id'] ?? 0);
    $tipo_cambio         = floatval($data['tipo_cambio'] ?? 1.0);
    $importe_total       = floatval($data['importe_total'] ?? 0);
    $tabla_estado_id     = intval($data['tabla_estado_registro_id'] ?? ESTADO_PAGO_BORRADOR);
    $usuario_id          = !empty($data['usuario_id']) ? intval($data['usuario_id']) : 1;
    $observaciones       = trim($data['observaciones'] ?? '');
    $tabla_origen_id     = TABLA_PAGOS_ID;
    $comprobante_tipo_id = !empty($data['comprobante_tipo_id']) ? intval($data['comprobante_tipo_id']) : pagoTipoComprobanteOpId($conexion, $empresa_id);

    // Verificar si ya existe comprobante asociado
    $sql_check = "SELECT comprobante_id FROM gestion__comprobantes 
                  WHERE tabla_origen_id = ? AND registro_origen_id = ?";
    $stmt_c = mysqli_prepare($conexion, $sql_check);
    mysqli_stmt_bind_param($stmt_c, "ii", $tabla_origen_id, $pago_id);
    mysqli_stmt_execute($stmt_c);
    $res_c = mysqli_stmt_get_result($stmt_c);
    $existe = mysqli_fetch_assoc($res_c);
    mysqli_stmt_close($stmt_c);

    $comprobante_id = null;

    if ($existe) {
        $comprobante_id = intval($existe['comprobante_id']);
        $importe_pendiente = $importe_total;

        // Proteger importe_pendiente calculando imputaciones vigentes
        $sql_imp = "SELECT 
                        COALESCE(SUM(CASE WHEN ci.comp_destino_id = ? THEN ci.importe_imputado ELSE 0 END), 0) AS imp_destino,
                        COALESCE(SUM(CASE WHEN ci.comp_origen_id = ? THEN ci.importe_origen ELSE 0 END), 0) AS imp_origen,
                        COUNT(*) AS total_imputaciones
                    FROM gestion__comprobantes_imputaciones ci
                    JOIN conf__estados_registros er ON er.estado_registro_id = ci.tabla_estado_registro_id
                    WHERE (ci.comp_destino_id = ? OR ci.comp_origen_id = ?)
                      AND er.codigo_estandar = 'CONFIRMADO'";
        $stmt_imp = mysqli_prepare($conexion, $sql_imp);
        if ($stmt_imp) {
            mysqli_stmt_bind_param($stmt_imp, "iiii", $comprobante_id, $comprobante_id, $comprobante_id, $comprobante_id);
            mysqli_stmt_execute($stmt_imp);
            $res_imp = mysqli_stmt_get_result($stmt_imp);
            if ($row_imp = mysqli_fetch_assoc($res_imp)) {
                if (intval($row_imp['total_imputaciones']) > 0) {
                    $imputado_total = floatval($row_imp['imp_destino']) + floatval($row_imp['imp_origen']);
                    $importe_pendiente = max(0.0, round($importe_total - $imputado_total, 2));
                }
            }
            mysqli_stmt_close($stmt_imp);
        }

        $sql_upd = "UPDATE gestion__comprobantes SET
                        empresa_id = ?, sucursal_id = ?, comprobante_pv = ?,
                        comprobante_tipo_id = ?, comprobante_nro = ?,
                        entidad_id = ?, entidad_sucursal_id = ?,
                        f_emision = ?, f_contabilidad = ?,
                        moneda_id = ?, tipo_cambio = ?,
                        importe_bruto = ?, importe_neto = ?, importe_total = ?,
                        importe_pendiente = ?, tabla_estado_registro_id = ?,
                        observaciones = ?, usuario_modificacion_id = ?, fecha_modificacion = NOW()
                    WHERE comprobante_id = ?";
        $stmt_u = mysqli_prepare($conexion, $sql_upd);
        mysqli_stmt_bind_param(
            $stmt_u, "iiiiiisssidddddisii",
            $empresa_id, $sucursal_id, $comprobante_pv,
            $comprobante_tipo_id, $comprobante_nro,
            $entidad_id, $entidad_sucursal_id,
            $f_emision, $f_contabilidad,
            $moneda_id, $tipo_cambio,
            $importe_total, $importe_total, $importe_total,
            $importe_pendiente, $tabla_estado_id,
            $observaciones, $usuario_id, $comprobante_id
        );
        mysqli_stmt_execute($stmt_u);
        mysqli_stmt_close($stmt_u);

    } else {
        $importe_pendiente = $importe_total;
        $sql_ins = "INSERT INTO gestion__comprobantes (
                        empresa_id, sucursal_id, comprobante_pv, comprobante_tipo_id,
                        comprobante_nro, entidad_id, entidad_sucursal_id,
                        f_emision, f_contabilidad, moneda_id, tipo_cambio,
                        importe_bruto, importe_neto, importe_total,
                        importe_pendiente, tabla_origen_id, registro_origen_id,
                        tabla_estado_registro_id, observaciones, usuario_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_i = mysqli_prepare($conexion, $sql_ins);
        mysqli_stmt_bind_param(
            $stmt_i, "iiiiiisssidddddiiisi",
            $empresa_id, $sucursal_id, $comprobante_pv,
            $comprobante_tipo_id, $comprobante_nro,
            $entidad_id, $entidad_sucursal_id,
            $f_emision, $f_contabilidad,
            $moneda_id, $tipo_cambio,
            $importe_total, $importe_total, $importe_total,
            $importe_pendiente, $tabla_origen_id, $pago_id,
            $tabla_estado_id, $observaciones, $usuario_id
        );
        mysqli_stmt_execute($stmt_i);
        $comprobante_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt_i);

        // Actualizar comprobante_id en gestion__pagos
        $stmt_upd_p = mysqli_prepare($conexion, "UPDATE gestion__pagos SET comprobante_id = ? WHERE pago_id = ?");
        mysqli_stmt_bind_param($stmt_upd_p, "ii", $comprobante_id, $pago_id);
        mysqli_stmt_execute($stmt_upd_p);
        mysqli_stmt_close($stmt_upd_p);
    }

    return $comprobante_id;
}

/**
 * Obtiene el listado de Órdenes de Pago con filtros
 */
function obtenerPagosListado($conexion, $empresa_id, $pagina_id = 106, $filtros = [])
{
    $empresa_id = intval($empresa_id);

    $sql = "SELECT p.pago_id, p.f_emision, " . emp_sql_pv_mostrar('p.comprobante_pv', 'p.empresa_id') . " AS comprobante_pv, p.comprobante_nro,
                   p.importe_total, p.observaciones, p.tabla_estado_registro_id,
                   p.entidad_id, COALESCE(e.entidad_nombre, 'Consumidor Final') AS proveedor_nombre, e.cuit AS proveedor_cuit,
                   s.sucursal_nombre,
                   m.simbolo AS moneda_simbolo,
                   er.codigo_estandar AS estado_codigo, er.estado_registro,
                   comp.importe_pendiente AS saldo_anticipo
            FROM gestion__pagos p
            LEFT JOIN gestion__entidades e ON e.entidad_id = p.entidad_id
            JOIN gestion__sucursales s ON s.sucursal_id = p.sucursal_id
            JOIN gestion__monedas m ON m.moneda_id = p.moneda_id
            LEFT JOIN gestion__comprobantes comp ON comp.comprobante_id = p.comprobante_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = p.tabla_estado_registro_id
            WHERE p.empresa_id = ? ";

    $params = [$empresa_id];
    $types  = "i";

    if (!empty($filtros['desde'])) {
        $sql .= " AND p.f_emision >= ? ";
        $params[] = $filtros['desde'];
        $types .= "s";
    }
    if (!empty($filtros['hasta'])) {
        $sql .= " AND p.f_emision <= ? ";
        $params[] = $filtros['hasta'];
        $types .= "s";
    }
    if (!empty($filtros['entidad_id'])) {
        $sql .= " AND p.entidad_id = ? ";
        $params[] = intval($filtros['entidad_id']);
        $types .= "i";
    }
    if (!empty($filtros['estado_id'])) {
        $sql .= " AND p.tabla_estado_registro_id = ? ";
        $params[] = intval($filtros['estado_id']);
        $types .= "i";
    }

    $sql .= " ORDER BY p.f_emision DESC, p.pago_id DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['data' => []];

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $datos = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $id = intval($row['pago_id']);
        $estado_id = intval($row['tabla_estado_registro_id']);

        // Número formateado
        $nro_formateado = ($row['comprobante_nro'] > 0)
            ? str_pad($row['comprobante_pv'], 4, '0', STR_PAD_LEFT) . '-' . str_pad($row['comprobante_nro'], 8, '0', STR_PAD_LEFT)
            : '<span class="badge bg-secondary-subtle text-secondary border">Borrador (Sin emitir)</span>';

        // Badge de estado
        if ($estado_id === ESTADO_PAGO_CONFIRMADO) {
            $estado_badge = '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Confirmada</span>';
        } elseif ($estado_id === ESTADO_PAGO_BORRADOR) {
            $estado_badge = '<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Borrador</span>';
        } elseif ($estado_id === ESTADO_PAGO_ANULADO) {
            $estado_badge = '<span class="badge bg-danger"><i class="fas fa-ban me-1"></i>Anulada</span>';
        } else {
            $estado_badge = '<span class="badge bg-secondary">' . htmlspecialchars($row['estado_registro'] ?? 'Estado ' . $estado_id) . '</span>';
        }

        // Saldo anticipo / A cuenta de proveedor
        $saldo_ant = floatval($row['saldo_anticipo'] ?? 0);
        $saldo_badge = ($saldo_ant > 0.001)
            ? '<span class="badge bg-info-subtle text-info border border-info-subtle" title="Monto no aplicado disponible como anticipo al proveedor">Anticipo: $' . number_format($saldo_ant, 2, ',', '.') . '</span>'
            : '<span class="text-muted small">Totalmente aplicado</span>';

        // Botones de acción
        $botones = '<div class="btn-group btn-group-sm" role="group">';
        $botones .= '<button type="button" class="btn btn-outline-info btn-accion" data-id="' . $id . '" data-accion="visualizar" title="Ver Orden de Pago"><i class="fas fa-eye"></i></button>';

        if ($estado_id === ESTADO_PAGO_BORRADOR) {
            $botones .= '<button type="button" class="btn btn-outline-primary btn-accion" data-id="' . $id . '" data-accion="editar" title="Editar Borrador"><i class="fas fa-edit"></i></button>';
            $botones .= '<button type="button" class="btn btn-outline-success btn-accion" data-id="' . $id . '" data-accion="confirmar" title="Confirmar Pago (Emitir OP)"><i class="fas fa-check"></i></button>';
            $botones .= '<button type="button" class="btn btn-outline-danger btn-accion" data-id="' . $id . '" data-accion="eliminar" title="Eliminar Borrador"><i class="fas fa-trash"></i></button>';
        } elseif ($estado_id === ESTADO_PAGO_CONFIRMADO) {
            $botones .= '<button type="button" class="btn btn-outline-danger btn-accion" data-id="' . $id . '" data-accion="anular" title="Anular Orden de Pago"><i class="fas fa-ban"></i></button>';
        }
        $botones .= '</div>';

        $datos[] = [
            'id'             => $id,
            'f_emision'      => date('d/m/Y', strtotime($row['f_emision'])),
            'numero'         => $nro_formateado,
            'proveedor'      => '<strong>' . htmlspecialchars($row['proveedor_nombre']) . '</strong><br><small class="text-muted">CUIT: ' . htmlspecialchars($row['proveedor_cuit'] ?? 'S/D') . '</small>',
            'sucursal'       => htmlspecialchars($row['sucursal_nombre']),
            'total'          => '<span class="fw-bold fs-6">$' . number_format(floatval($row['importe_total']), 2, ',', '.') . '</span>',
            'saldo_anticipo' => $saldo_badge,
            'estado'         => $estado_badge,
            'acciones'       => $botones
        ];
    }
    mysqli_stmt_close($stmt);

    return ['data' => $datos];
}

/**
 * Obtiene el detalle completo de un pago para ver o editar
 */
// $estado_imputaciones: estado de las imputaciones a leer; null = según el estado de la OP (3 borrador, 5 confirmadas).
function obtenerPagoDetalle($conexion, $pago_id, $empresa_id, $estado_imputaciones = null)
{
    $pago_id    = intval($pago_id);
    $empresa_id = intval($empresa_id);

    // Cabecera
    $sql_c = "SELECT p.*, " . emp_sql_pv_mostrar('p.comprobante_pv', 'p.empresa_id') . " AS pv_nro, COALESCE(e.entidad_nombre, 'Consumidor Final') AS entidad_nombre, e.cuit, e.domicilio_legal,
                     e.es_cliente, e.es_proveedor, e.cont_cuenta_id_cliente, e.cont_cuenta_id_proveedor,
                     s.sucursal_nombre, m.codigo AS moneda_codigo, m.simbolo AS moneda_simbolo,
                     er.codigo_estandar AS estado_codigo, er.estado_registro
              FROM gestion__pagos p
              LEFT JOIN gestion__entidades e ON e.entidad_id = p.entidad_id
              JOIN gestion__sucursales s ON s.sucursal_id = p.sucursal_id
              JOIN gestion__monedas m ON m.moneda_id = p.moneda_id
              LEFT JOIN conf__estados_registros er ON er.estado_registro_id = p.tabla_estado_registro_id
              WHERE p.pago_id = ? AND p.empresa_id = ?";
    $stmt_c = mysqli_prepare($conexion, $sql_c);
    mysqli_stmt_bind_param($stmt_c, "ii", $pago_id, $empresa_id);
    mysqli_stmt_execute($stmt_c);
    $res_c = mysqli_stmt_get_result($stmt_c);
    $cabecera = mysqli_fetch_assoc($res_c);
    mysqli_stmt_close($stmt_c);

    if (!$cabecera) return null;

    // Medios de Pago
    $sql_m = "SELECT pm.*, mp.codigo AS medio_codigo, mp.medio_pago, mp.medio_pago_tipo,
                     tc.tesoreria_cuenta, tc.cuenta_tipo,
                     chq.numero AS cheque_nro, chq.librador_nombre, chq.f_pago AS cheque_f_pago, chq.cheque_tipo,
                     b.banco AS cheque_banco
              FROM gestion__pagos_medios pm
              JOIN gestion__medios_pago mp ON mp.medio_pago_id = pm.medio_pago_id
              LEFT JOIN gestion__tesoreria_cuentas tc ON tc.tesoreria_cuenta_id = pm.tesoreria_cuenta_id
              LEFT JOIN gestion__cheques chq ON chq.cheque_id = pm.cheque_id
              LEFT JOIN conf__bancos b ON b.banco_id = chq.banco_id
              WHERE pm.pago_id = ?
              ORDER BY pm.pago_medio_id ASC";
    $stmt_m = mysqli_prepare($conexion, $sql_m);
    mysqli_stmt_bind_param($stmt_m, "i", $pago_id);
    mysqli_stmt_execute($stmt_m);
    $res_m = mysqli_stmt_get_result($stmt_m);
    $medios = [];
    while ($m = mysqli_fetch_assoc($res_m)) {
        $medios[] = $m;
    }
    mysqli_stmt_close($stmt_m);

    // Imputaciones
    $comp_origen_id = intval($cabecera['comprobante_id']);
    $imputaciones = [];
    if ($comp_origen_id > 0) {
        $estado_op = intval($cabecera['tabla_estado_registro_id']);
        if ($estado_imputaciones !== null) $estado_op = intval($estado_imputaciones);
        $filtro_estado = ($estado_op === 5) ? " AND ci.tabla_estado_registro_id = 5" : (($estado_op === 3) ? " AND ci.tabla_estado_registro_id = 3" : " AND ci.tabla_estado_registro_id != 2");
        $sql_i = "SELECT ci.*, " . emp_sql_pv_mostrar('cd.comprobante_pv', 'cd.empresa_id', 'cd.tabla_origen_id') . " AS comprobante_pv, cd.comprobante_nro, cd.f_emision AS destino_f_emision,
                         cd.importe_total AS destino_total, cd.importe_pendiente AS destino_pendiente,
                         ct.codigo AS comprobante_tipo_codigo, ct.comprobante_tipo
                  FROM gestion__comprobantes_imputaciones ci
                  JOIN gestion__comprobantes cd ON cd.comprobante_id = ci.comp_destino_id
                  JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = cd.comprobante_tipo_id
                  WHERE ci.comp_origen_id = ?$filtro_estado
                  ORDER BY ci.imputacion_id ASC";
        $stmt_i = mysqli_prepare($conexion, $sql_i);
        mysqli_stmt_bind_param($stmt_i, "i", $comp_origen_id);
        mysqli_stmt_execute($stmt_i);
        $res_i = mysqli_stmt_get_result($stmt_i);
        while ($imp = mysqli_fetch_assoc($res_i)) {
            $imputaciones[] = $imp;
        }
        mysqli_stmt_close($stmt_i);
    }

    return [
        'cabecera'     => $cabecera,
        'medios'       => $medios,
        'imputaciones' => $imputaciones
    ];
}

/**
 * Guarda o actualiza una Orden de Pago en estado Borrador
 */
function guardarPagoBorrador($conexion, $data)
{
    $empresa_id     = intval($data['empresa_id'] ?? 0);
    $sucursal_id    = intval($data['sucursal_id'] ?? 0);
    $entidad_id     = intval($data['entidad_id'] ?? 0);
    $f_emision      = !empty($data['f_emision']) ? $data['f_emision'] : date('Y-m-d');
    $f_contabilidad = !empty($data['f_contabilidad']) ? $data['f_contabilidad'] : $f_emision;
    $moneda_id      = emp_moneda_id($conexion, $empresa_id, $data['moneda_id'] ?? 0);
    $tipo_cambio    = floatval($data['tipo_cambio'] ?? 1.0);
    $observaciones  = trim($data['observaciones'] ?? '');
    $usuario_id     = intval($data['usuario_id'] ?? 1);
    $pago_id        = intval($data['pago_id'] ?? 0);

    $medios         = $data['medios'] ?? [];
    $imputaciones   = $data['imputaciones'] ?? [];

    // consumidor_final: devolución automática de una nota de crédito a consumidor final (entidad NULL)
    if ($empresa_id <= 0 || ($entidad_id <= 0 && empty($data['consumidor_final']))) {
        throw new Exception("Debe seleccionar un proveedor y empresa válidos.");
    }
    $entidad_pago = $entidad_id > 0 ? $entidad_id : null;   // FK a entidades: NULL para consumidor final
    // pago_cero: OP en $0 que documenta la aplicación de notas de crédito de proveedor (sin medios)
    if (empty($medios) && empty($data['pago_cero'])) {
        throw new Exception("Debe agregar al menos un medio de pago.");
    }

    // Calcular total de medios
    $importe_total = 0.0;
    foreach ($medios as $m) {
        $imp_comp = floatval($m['importe_comprobante'] ?? $m['importe'] ?? 0);
        if ($imp_comp <= 0) throw new Exception("Cada medio de pago debe tener un importe mayor a cero.");
        $importe_total += $imp_comp;
    }
    $importe_total = round($importe_total, 2);

    // Calcular total de imputaciones (RT02 / D6)
    $total_imputado = 0.0;
    foreach ($imputaciones as $imp) {
        $imp_val = floatval($imp['importe_imputado'] ?? 0);
        if ($imp_val > 0) {
            $total_imputado += $imp_val;
        }
    }
    $total_imputado = round($total_imputado, 2);

    if ($total_imputado > round($importe_total + 0.001, 2)) {
        throw new Exception("El total imputado ($" . number_format($total_imputado, 2) . ") no puede superar el total pagado ($" . number_format($importe_total, 2) . ").");
    }

    // Punto de venta de tesorería para la sucursal (D29)
    $comprobante_pv = intval($data['comprobante_pv'] ?? 0);
    if ($comprobante_pv <= 0) {
        $sql_pv = "SELECT punto_venta_id FROM gestion__puntos_venta 
                   WHERE empresa_id = ? AND sucursal_id = ? AND (nombre LIKE 'Tesoreria%' OR codigo_fiscal >= 10000)
                   ORDER BY (nombre LIKE 'Tesoreria%') DESC, punto_venta_id LIMIT 1";
        $stmt_pv = mysqli_prepare($conexion, $sql_pv);
        mysqli_stmt_bind_param($stmt_pv, "ii", $empresa_id, $sucursal_id);
        mysqli_stmt_execute($stmt_pv);
        $res_pv = mysqli_stmt_get_result($stmt_pv);
        $row_pv = mysqli_fetch_assoc($res_pv);
        mysqli_stmt_close($stmt_pv);
        if ($row_pv) {
            $comprobante_pv = intval($row_pv['punto_venta_id']);
        } else {
            $sql_pv2 = "SELECT punto_venta_id FROM gestion__puntos_venta WHERE empresa_id = ? ORDER BY (sucursal_id = ?) DESC, punto_venta_id LIMIT 1";
            $stmt_pv2 = mysqli_prepare($conexion, $sql_pv2);
            mysqli_stmt_bind_param($stmt_pv2, "ii", $empresa_id, $sucursal_id);
            mysqli_stmt_execute($stmt_pv2);
            $res_pv2 = mysqli_stmt_get_result($stmt_pv2);
            $row_pv2 = mysqli_fetch_assoc($res_pv2);
            mysqli_stmt_close($stmt_pv2);
            $comprobante_pv = $row_pv2 ? intval($row_pv2['punto_venta_id']) : 1;
        }
    }

    $comprobante_tipo_id = !empty($data['comprobante_tipo_id']) ? intval($data['comprobante_tipo_id']) : pagoTipoComprobanteOpId($conexion, $empresa_id);
    $estado_borrador     = ESTADO_PAGO_BORRADOR;

    if ($pago_id > 0) {
        // UPDATE Borrador existente
        $sql_upd = "UPDATE gestion__pagos SET
                        sucursal_id = ?, comprobante_pv = ?, entidad_id = ?,
                        f_emision = ?, f_contabilidad = ?, moneda_id = ?,
                        tipo_cambio = ?, importe_total = ?, observaciones = ?,
                        usuario_modificacion_id = ?, fecha_modificacion = NOW()
                    WHERE pago_id = ? AND empresa_id = ? AND tabla_estado_registro_id = ?";
        $stmt_u = mysqli_prepare($conexion, $sql_upd);
        mysqli_stmt_bind_param(
            $stmt_u, "iiissiddsiiii",
            $sucursal_id, $comprobante_pv, $entidad_pago,
            $f_emision, $f_contabilidad, $moneda_id,
            $tipo_cambio, $importe_total, $observaciones,
            $usuario_id, $pago_id, $empresa_id, $estado_borrador
        );
        mysqli_stmt_execute($stmt_u);
        mysqli_stmt_close($stmt_u);

        // Borrar medios e imputaciones en borrador para reinsertar
        $conexion->query("DELETE FROM gestion__pagos_medios WHERE pago_id = $pago_id");
        $res_cid = $conexion->query("SELECT comprobante_id FROM gestion__pagos WHERE pago_id = $pago_id");
        $cid = intval($res_cid->fetch_assoc()['comprobante_id'] ?? 0);
        if ($cid > 0) {
            $conexion->query("DELETE FROM gestion__comprobantes_imputaciones WHERE comp_origen_id = $cid AND tabla_estado_registro_id = 3");
            // Borrar cheques propios en borrador que se hayan creado en este pago
            $conexion->query("DELETE FROM gestion__cheques WHERE comp_origen_id = $cid AND cheque_tipo = 'PROPIO' AND tabla_estado_registro_id = 3");
        }
    } else {
        // INSERT nuevo Borrador
        $sql_ins = "INSERT INTO gestion__pagos (
                        empresa_id, sucursal_id, comprobante_tipo_id, comprobante_pv,
                        comprobante_nro, comprobante_id, entidad_id, f_emision,
                        f_contabilidad, moneda_id, tipo_cambio, importe_total,
                        observaciones, tabla_estado_registro_id, usuario_id
                    ) VALUES (?, ?, ?, ?, 0, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_i = mysqli_prepare($conexion, $sql_ins);
        mysqli_stmt_bind_param(
            $stmt_i, "iiiiisssidssi",
            $empresa_id, $sucursal_id, $comprobante_tipo_id, $comprobante_pv,
            $entidad_pago, $f_emision, $f_contabilidad,
            $moneda_id, $tipo_cambio, $importe_total,
            $observaciones, $estado_borrador, $usuario_id
        );
        mysqli_stmt_execute($stmt_i);
        $pago_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt_i);
    }

    // Sincronizar comprobante OP
    $comprobante_id = syncComprobantePago($conexion, $pago_id, [
        'empresa_id'               => $empresa_id,
        'sucursal_id'              => $sucursal_id,
        'comprobante_pv'           => $comprobante_pv,
        'comprobante_nro'          => 0,
        'entidad_id'               => $entidad_id,
        'f_emision'                => $f_emision,
        'f_contabilidad'           => $f_contabilidad,
        'moneda_id'                => $moneda_id,
        'tipo_cambio'              => $tipo_cambio,
        'importe_total'            => $importe_total,
        'tabla_estado_registro_id' => $estado_borrador,
        'observaciones'            => $observaciones,
        'usuario_id'               => $usuario_id
    ]);

    // Insertar Medios en gestion__pagos_medios
    $sql_med = "INSERT INTO gestion__pagos_medios (
                    pago_id, empresa_id, medio_pago_id, tesoreria_cuenta_id,
                    moneda_id, tipo_cambio, importe, importe_comprobante,
                    cheque_id, nro_operacion, f_valor, tarjeta_marca, tarjeta_cupon,
                    impuesto_tipo_id, retencion_certificado_nro, retencion_base_imponible,
                    retencion_alicuota, observaciones, usuario_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_med = mysqli_prepare($conexion, $sql_med);

    foreach ($medios as $m) {
        $medio_pago_id   = intval($m['medio_pago_id']);
        $tes_cuenta_id   = !empty($m['tesoreria_cuenta_id']) ? intval($m['tesoreria_cuenta_id']) : null;
        $mon_id          = emp_moneda_id($conexion, $empresa_id, $m['moneda_id'] ?? $moneda_id);
        $tc              = floatval($m['tipo_cambio'] ?? 1.0);
        $imp             = floatval($m['importe'] ?? 0);
        $imp_comp        = floatval($m['importe_comprobante'] ?? $imp);
        $nro_op          = !empty($m['nro_operacion']) ? trim($m['nro_operacion']) : null;
        $f_val           = !empty($m['f_valor']) ? $m['f_valor'] : $f_emision;
        $tarj_marca      = !empty($m['tarjeta_marca']) ? trim($m['tarjeta_marca']) : null;
        $tarj_cupon      = !empty($m['tarjeta_cupon']) ? trim($m['tarjeta_cupon']) : null;
        $imp_tipo_id     = !empty($m['impuesto_tipo_id']) ? intval($m['impuesto_tipo_id']) : null;
        $ret_cert        = !empty($m['retencion_certificado_nro']) ? trim($m['retencion_certificado_nro']) : null;
        $ret_base        = !empty($m['retencion_base_imponible']) ? floatval($m['retencion_base_imponible']) : null;
        $ret_ali         = !empty($m['retencion_alicuota']) ? floatval($m['retencion_alicuota']) : null;
        $obs_m           = !empty($m['observaciones']) ? trim($m['observaciones']) : null;
        $cheque_id       = !empty($m['cheque_id']) ? intval($m['cheque_id']) : null;

        // Caso 1: Emisión de Cheque Propio
        if (!empty($m['cheque_propio']) && $comprobante_id > 0) {
            $chq_banco_id = intval($m['cheque_propio']['banco_id'] ?? 1);
            $chq_numero   = trim($m['cheque_propio']['numero'] ?? '');
            $chq_f_pago   = !empty($m['cheque_propio']['f_pago']) ? $m['cheque_propio']['f_pago'] : $f_emision;
            $es_dif       = ($chq_f_pago > $f_emision) ? 1 : 0;
            $chq_cuenta_id = !empty($m['cheque_propio']['tesoreria_cuenta_id']) ? intval($m['cheque_propio']['tesoreria_cuenta_id']) : $tes_cuenta_id;

            $sql_ins_cp = "INSERT INTO gestion__cheques (
                                empresa_id, sucursal_id, cheque_tipo, es_echeq,
                                banco_id, tesoreria_cuenta_id, numero, f_emision,
                                f_pago, es_diferido, moneda_id, importe,
                                entidad_id, endosable, comp_origen_id,
                                clave_propio_cuenta_id, tabla_estado_registro_id, usuario_id
                           ) VALUES (?, ?, 'PROPIO', 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, 3, ?)";
            $stmt_cp = mysqli_prepare($conexion, $sql_ins_cp);
            if ($stmt_cp) {
                mysqli_stmt_bind_param(
                    $stmt_cp, "iiiisssidsiiii",
                    $empresa_id, $sucursal_id, $chq_banco_id, $chq_cuenta_id,
                    $chq_numero, $f_emision, $chq_f_pago, $es_dif,
                    $mon_id, $imp_comp, $entidad_id, $comprobante_id,
                    $chq_cuenta_id, $usuario_id
                );
                mysqli_stmt_execute($stmt_cp);
                $cheque_id = mysqli_insert_id($conexion);
                mysqli_stmt_close($stmt_cp);
            }
        }

        // Caso 2: Endoso de Cheque de Tercero existente en Cartera (C2 / RT06)
        if (!empty($m['cheque_tercero_id'])) {
            $cheque_id = intval($m['cheque_tercero_id']);
        }

        mysqli_stmt_bind_param(
            $stmt_med, "iiiiddddissssisddsi",
            $pago_id, $empresa_id, $medio_pago_id, $tes_cuenta_id,
            $mon_id, $tc, $imp, $imp_comp,
            $cheque_id, $nro_op, $f_val, $tarj_marca, $tarj_cupon,
            $imp_tipo_id, $ret_cert, $ret_base,
            $ret_ali, $obs_m, $usuario_id
        );
        mysqli_stmt_execute($stmt_med);
    }
    mysqli_stmt_close($stmt_med);

    // Insertar Imputaciones tentativas en Borrador (3)
    if (!empty($imputaciones) && $comprobante_id > 0) {
        $sql_imp_ins = "INSERT INTO gestion__comprobantes_imputaciones (
                            empresa_id, comp_origen_id, comp_destino_id, f_imputacion,
                            importe_imputado, moneda_origen_id, tipo_cambio, importe_origen,
                            tabla_estado_registro_id, usuario_id
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 3, ?)";
        $stmt_ii = mysqli_prepare($conexion, $sql_imp_ins);

        foreach ($imputaciones as $imp) {
            $imp_destino_id = intval($imp['comprobante_destino_id'] ?? 0);
            $imp_monto = floatval($imp['importe_imputado'] ?? 0);
            if ($imp_destino_id > 0 && $imp_monto > 0) {
                mysqli_stmt_bind_param(
                    $stmt_ii, "iiisdiddi",
                    $empresa_id, $comprobante_id, $imp_destino_id, $f_emision,
                    $imp_monto, $moneda_id, $tipo_cambio, $imp_monto,
                    $usuario_id
                );
                mysqli_stmt_execute($stmt_ii);
            }
        }
        mysqli_stmt_close($stmt_ii);
    }

    return $pago_id;
}

/**
 * Confirmación Transaccional de Orden de Pago (§2.5 / RT01 - RT21 / C2 / D27 - D36)
 */
// $en_transaccion = true: corre dentro de la transacción del llamador (OP en $0 al aplicar notas de crédito).
function confirmarPago($conexion, $pago_id, $empresa_id, $usuario_id, $en_transaccion = false)
{
    $pago_id    = intval($pago_id);
    $empresa_id = intval($empresa_id);
    $usuario_id = intval($usuario_id);

    if (!$en_transaccion) {
        mysqli_begin_transaction($conexion);
    }

    try {
        // 1. Transición optimista RT04
        $sql_opt = "UPDATE gestion__pagos 
                    SET tabla_estado_registro_id = ?, usuario_modificacion_id = ?, fecha_modificacion = NOW()
                    WHERE pago_id = ? AND empresa_id = ? AND tabla_estado_registro_id = ?";
        $stmt_opt = mysqli_prepare($conexion, $sql_opt);
        $est_conf = ESTADO_PAGO_CONFIRMADO;
        $est_borr = ESTADO_PAGO_BORRADOR;
        mysqli_stmt_bind_param($stmt_opt, "iiiii", $est_conf, $usuario_id, $pago_id, $empresa_id, $est_borr);
        mysqli_stmt_execute($stmt_opt);
        $afectadas = mysqli_stmt_affected_rows($stmt_opt);
        mysqli_stmt_close($stmt_opt);

        if ($afectadas !== 1) {
            throw new Exception("El pago ya no está en estado Borrador o fue modificado por otro usuario.");
        }

        // Obtener detalle completo
        // La OP ya pasó a Confirmado (paso 1): sus imputaciones todavía están en Borrador
        $pago_info = obtenerPagoDetalle($conexion, $pago_id, $empresa_id, ESTADO_PAGO_BORRADOR);
        if (!$pago_info) throw new Exception("No se pudo obtener el detalle de la orden de pago.");

        $cab = $pago_info['cabecera'];
        $medios = $pago_info['medios'];
        $comp_origen_id = intval($cab['comprobante_id']);
        $importe_total = floatval($cab['importe_total']);

        // 2. Lock del comprobante origen y destinos en orden ascendente (RT16)
        $destinos_ids = [];
        foreach ($pago_info['imputaciones'] as $imp) {
            $destinos_ids[] = intval($imp['comp_destino_id']);
        }
        $destinos_ids = array_unique($destinos_ids);

        $todos_comps = array_merge([$comp_origen_id], $destinos_ids);
        sort($todos_comps);

        $placeholders = implode(',', array_fill(0, count($todos_comps), '?'));
        $sql_lock = "SELECT comprobante_id, comprobante_tipo_id, importe_total, importe_pendiente, tabla_origen_id, registro_origen_id
                     FROM gestion__comprobantes
                     WHERE comprobante_id IN ($placeholders) AND empresa_id = ?
                     ORDER BY comprobante_id ASC
                     FOR UPDATE";
        $stmt_l = mysqli_prepare($conexion, $sql_lock);
        $types_l = str_repeat('i', count($todos_comps)) . 'i';
        $params_l = array_merge($todos_comps, [$empresa_id]);
        mysqli_stmt_bind_param($stmt_l, $types_l, ...$params_l);
        mysqli_stmt_execute($stmt_l);
        $res_l = mysqli_stmt_get_result($stmt_l);

        $comprobantes_locked = [];
        while ($row_l = mysqli_fetch_assoc($res_l)) {
            $comprobantes_locked[intval($row_l['comprobante_id'])] = $row_l;
        }
        mysqli_stmt_close($stmt_l);

        // 3. Confirmar Imputaciones (limpiar previas en borrador)
        $conexion->query("DELETE FROM gestion__comprobantes_imputaciones WHERE comp_origen_id = $comp_origen_id AND tabla_estado_registro_id = 3");
        $total_imputado = 0.0;
        foreach ($pago_info['imputaciones'] as $imp) {
            $dest_id = intval($imp['comp_destino_id']);
            $monto_imp = floatval($imp['importe_imputado']);

            if (!isset($comprobantes_locked[$dest_id])) {
                throw new Exception("Comprobante destino $dest_id no encontrado o no pertenece a la empresa.");
            }

            $saldo_dest = floatval($comprobantes_locked[$dest_id]['importe_pendiente']);
            if ($monto_imp > round($saldo_dest + 0.001, 2)) {
                throw new Exception("El monto a imputar (\$$monto_imp) supera el saldo pendiente (\$$saldo_dest) del comprobante destino $dest_id.");
            }

            // Registrar imputación oficial con tesoreriaRegistrarImputacion
            tesoreriaRegistrarImputacion($conexion, [
                'empresa_id'       => $empresa_id,
                'comp_origen_id'   => $comp_origen_id,
                'comp_destino_id'  => $dest_id,
                'importe_imputado' => $monto_imp,
                'f_imputacion'     => $cab['f_emision'],
                'moneda_origen_id' => emp_moneda_id($conexion, $empresa_id, $cab['moneda_id'] ?? 0),
                'tipo_cambio'      => floatval($cab['tipo_cambio'] ?? 1.0),
                'usuario_id'       => $usuario_id
            ]);

            $total_imputado += $monto_imp;
        }

        // Saldo anticipo si no se aplicó el 100% (Regla D6 / RT02)
        $saldo_op = max(0.0, round($importe_total - $total_imputado, 2));

        // 4. Numeración correlativa interna con FOR UPDATE (RT21)
        $punto_venta_id = intval($cab['comprobante_pv']);
        $sql_num = "SELECT numerador_id, ultimo_numero 
                    FROM gestion__comprobantes_numeradores 
                    WHERE empresa_id = ? AND punto_venta_id = ? AND comprobante_tipo_id = ? 
                    FOR UPDATE";
        $stmt_num = mysqli_prepare($conexion, $sql_num);
        $tipo_op = !empty($cab['comprobante_tipo_id']) ? intval($cab['comprobante_tipo_id']) : pagoTipoComprobanteOpId($conexion, $empresa_id);
        mysqli_stmt_bind_param($stmt_num, "iii", $empresa_id, $punto_venta_id, $tipo_op);
        mysqli_stmt_execute($stmt_num);
        $res_num = mysqli_stmt_get_result($stmt_num);
        $row_num = mysqli_fetch_assoc($res_num);
        mysqli_stmt_close($stmt_num);

        if (!$row_num) {
            // Si no existe numerador para OP en este punto de venta, inicializarlo
            $conn_ins_num = "INSERT INTO gestion__comprobantes_numeradores (empresa_id, punto_venta_id, comprobante_tipo_id, ultimo_numero) VALUES (?, ?, ?, 0)";
            $stmt_in = mysqli_prepare($conexion, $conn_ins_num);
            mysqli_stmt_bind_param($stmt_in, "iii", $empresa_id, $punto_venta_id, $tipo_op);
            mysqli_stmt_execute($stmt_in);
            $num_id = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmt_in);
            $row_num = ['numerador_id' => $num_id, 'ultimo_numero' => 0];
        }

        $proximo_nro = intval($row_num['ultimo_numero']) + 1;

        $stmt_upd_n = mysqli_prepare($conexion, "UPDATE gestion__comprobantes_numeradores SET ultimo_numero = ? WHERE numerador_id = ?");
        mysqli_stmt_bind_param($stmt_upd_n, "ii", $proximo_nro, $row_num['numerador_id']);
        mysqli_stmt_execute($stmt_upd_n);
        mysqli_stmt_close($stmt_upd_n);

        // Asignar número oficial al pago y comprobante
        $stmt_upd_pn = mysqli_prepare($conexion, "UPDATE gestion__pagos SET comprobante_nro = ? WHERE pago_id = ?");
        mysqli_stmt_bind_param($stmt_upd_pn, "ii", $proximo_nro, $pago_id);
        mysqli_stmt_execute($stmt_upd_pn);
        mysqli_stmt_close($stmt_upd_pn);

        $stmt_upd_compn = mysqli_prepare($conexion, "UPDATE gestion__comprobantes SET comprobante_nro = ?, importe_pendiente = ?, tabla_estado_registro_id = ? WHERE comprobante_id = ?");
        mysqli_stmt_bind_param($stmt_upd_compn, "idii", $proximo_nro, $saldo_op, $est_conf, $comp_origen_id);
        mysqli_stmt_execute($stmt_upd_compn);
        mysqli_stmt_close($stmt_upd_compn);

        // 5. Movimientos en Libro de Tesorería y Gestión de Cheques
        $concepto_op = "Orden de Pago OP " . emp_numero_comprobante($conexion, $empresa_id, $punto_venta_id, $proximo_nro) . " - " . ($cab['entidad_nombre'] ?? 'Proveedor');

        foreach ($medios as $m) {
            $monto_m = floatval($m['importe_comprobante']);
            $tipo_medio = $m['medio_pago_tipo'] ?? '';
            $cta_id = !empty($m['tesoreria_cuenta_id']) ? intval($m['tesoreria_cuenta_id']) : null;
            $chq_id = !empty($m['cheque_id']) ? intval($m['cheque_id']) : null;

            // Caso A: Efectivo o Transferencia -> EGRESO de cuenta de tesorería
            if ($tipo_medio === 'EFECTIVO' || $tipo_medio === 'TRANSFERENCIA') {
                if ($cta_id > 0) {
                    tesoreriaRegistrarMovimiento($conexion, [
                        'empresa_id'          => $empresa_id,
                        'sucursal_id'         => intval($cab['sucursal_id']),
                        'tesoreria_cuenta_id' => $cta_id,
                        'comprobante_id'      => $comp_origen_id,
                        'tipo_movimiento'     => 'EGRESO',
                        'importe'             => $monto_m,
                        'f_movimiento'        => $cab['f_emision'],
                        'f_valor'             => $m['f_valor'] ?? $cab['f_emision'],
                        'moneda_id'           => emp_moneda_id($conexion, $empresa_id, $cab['moneda_id'] ?? 0),
                        'tipo_cambio'         => floatval($cab['tipo_cambio'] ?? 1.0),
                        'medio_pago_id'       => intval($m['medio_pago_id']),
                        'concepto'            => $concepto_op,
                        'tabla_origen_id'     => TABLA_PAGOS_ID,
                        'registro_origen_id'  => $pago_id,
                        'usuario_id'          => $usuario_id
                    ]);
                }
            }

            // Caso B: Cheque de Tercero Endosado (C2 / RT06 / RT10)
            elseif ($tipo_medio === 'CHEQUE_TERCERO' && $chq_id > 0) {
                // Verificar que el cheque siga en cartera
                $res_chq = $conexion->query("SELECT cheque_id, numero, tabla_estado_registro_id, tesoreria_cuenta_id FROM gestion__cheques WHERE cheque_id = $chq_id FOR UPDATE");
                $row_chq = $res_chq->fetch_assoc();
                if (!$row_chq || intval($row_chq['tabla_estado_registro_id']) !== 29) {
                    throw new Exception("El cheque de tercero N° {$row_chq['numero']} ya no se encuentra en cartera para ser endosado (RT06).");
                }

                // Transición a ENDOSADO (33) y comp_destino_id = OP
                $conexion->query("UPDATE gestion__cheques SET tabla_estado_registro_id = 33, comp_destino_id = $comp_origen_id, usuario_modificacion_id = $usuario_id WHERE cheque_id = $chq_id");

                // EGRESO en la cuenta de Valores en Cartera (D15)
                $cta_valores = intval($row_chq['tesoreria_cuenta_id']);
                if ($cta_valores <= 0) {
                    $res_vc = $conexion->query("SELECT tesoreria_cuenta_id FROM gestion__tesoreria_cuentas WHERE empresa_id = $empresa_id AND cuenta_tipo = 'VALORES' LIMIT 1");
                    $cta_valores = intval($res_vc->fetch_assoc()['tesoreria_cuenta_id'] ?? 0);
                    if ($cta_valores <= 0) {
                        throw new Exception('La empresa no tiene una cuenta de tesorería de Valores en cartera.');
                    }
                }

                tesoreriaRegistrarMovimiento($conexion, [
                    'empresa_id'          => $empresa_id,
                    'sucursal_id'         => intval($cab['sucursal_id']),
                    'tesoreria_cuenta_id' => $cta_valores,
                    'comprobante_id'      => $comp_origen_id,
                    'tipo_movimiento'     => 'EGRESO',
                    'importe'             => $monto_m,
                    'f_movimiento'        => $cab['f_emision'],
                    'f_valor'             => $cab['f_emision'],
                    'moneda_id'           => emp_moneda_id($conexion, $empresa_id, $cab['moneda_id'] ?? 0),
                    'tipo_cambio'         => floatval($cab['tipo_cambio'] ?? 1.0),
                    'medio_pago_id'       => intval($m['medio_pago_id']),
                    'concepto'            => $concepto_op . " (Endoso Chq N° {$row_chq['numero']})",
                    'tabla_origen_id'     => TABLA_PAGOS_ID,
                    'registro_origen_id'  => $pago_id,
                    'usuario_id'          => $usuario_id
                ]);
            }

            // Caso C: Cheque Propio (D28 / C2)
            elseif ($tipo_medio === 'CHEQUE_PROPIO' && $chq_id > 0) {
                // Cheque propio pasa a EMITIDO (31)
                $conexion->query("UPDATE gestion__cheques SET tabla_estado_registro_id = 31, usuario_modificacion_id = $usuario_id WHERE cheque_id = $chq_id");

                // Verificar si es común o diferido
                $res_cp_info = $conexion->query("SELECT es_diferido, tesoreria_cuenta_id, numero FROM gestion__cheques WHERE cheque_id = $chq_id");
                $cp_info = $res_cp_info->fetch_assoc();
                $es_diferido = intval($cp_info['es_diferido'] ?? 0);
                $cta_banco = intval($cp_info['tesoreria_cuenta_id']);

                // Si es COMÚN, impacta egreso bancario de inmediato
                if (!$es_diferido && $cta_banco > 0) {
                    tesoreriaRegistrarMovimiento($conexion, [
                        'empresa_id'          => $empresa_id,
                        'sucursal_id'         => intval($cab['sucursal_id']),
                        'tesoreria_cuenta_id' => $cta_banco,
                        'comprobante_id'      => $comp_origen_id,
                        'tipo_movimiento'     => 'EGRESO',
                        'importe'             => $monto_m,
                        'f_movimiento'        => $cab['f_emision'],
                        'f_valor'             => $cab['f_emision'],
                        'moneda_id'           => emp_moneda_id($conexion, $empresa_id, $cab['moneda_id'] ?? 0),
                        'tipo_cambio'         => floatval($cab['tipo_cambio'] ?? 1.0),
                        'medio_pago_id'       => intval($m['medio_pago_id']),
                        'concepto'            => $concepto_op . " (Chq Propio N° {$cp_info['numero']})",
                        'tabla_origen_id'     => TABLA_PAGOS_ID,
                        'registro_origen_id'  => $pago_id,
                        'usuario_id'          => $usuario_id
                    ]);
                }
                // Si es DIFERIDO: regla D28 / C2: no impacta banco de inmediato. Se registrará al acreditarse/debitarse.
            }
        }

        // 6. Asiento Contable Automático (D27 / D36 / Anexo D)
        // DEBE: Cuenta del Proveedor (Proveedores Locales)
        // HABER: Cuentas de contrapartida de cada medio (Caja, Banco, Valores en cartera, Valores a pagar, etc.)
        $lineas_asiento = [];
        $total_haber = 0.0;

        foreach ($medios as $m) {
            $monto_linea = floatval($m['importe_comprobante']);
            $cta_contable_id = 0;
            $tipo_medio = $m['medio_pago_tipo'] ?? '';

            if (!empty($m['tesoreria_cuenta_id'])) {
                $res_cta = $conexion->query("SELECT cont_cuenta_id FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = " . intval($m['tesoreria_cuenta_id']) . " AND empresa_id = $empresa_id");
                $cta_contable_id = intval($res_cta->fetch_assoc()['cont_cuenta_id'] ?? 0);
            } elseif ($tipo_medio === 'CHEQUE_TERCERO') {
                // Cuenta de Valores en Cartera de la empresa
                $res_val = $conexion->query("SELECT cont_cuenta_id FROM gestion__tesoreria_cuentas WHERE empresa_id = $empresa_id AND cuenta_tipo = 'VALORES' LIMIT 1");
                $cta_contable_id = intval($res_val->fetch_assoc()['cont_cuenta_id'] ?? 0);
            } elseif ($tipo_medio === 'CHEQUE_PROPIO') {
                // Si es diferido, cuenta VALORES_A_PAGAR (D35/D36)
                $res_ch = $conexion->query("SELECT es_diferido, tesoreria_cuenta_id FROM gestion__cheques WHERE cheque_id = " . intval($m['cheque_id']));
                $ch_d = $res_ch->fetch_assoc();
                if ($ch_d && intval($ch_d['es_diferido']) === 1) {
                    $res_vap = tesoreriaResolverCuentaContable($conexion, $empresa_id, 'VALORES_A_PAGAR');
                    $cta_contable_id = $res_vap['cont_cuenta_id'];
                } else {
                    $cta_banco = intval($ch_d['tesoreria_cuenta_id'] ?? 0);
                    $res_cta_b = $conexion->query("SELECT cont_cuenta_id FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = $cta_banco AND empresa_id = $empresa_id");
                    $cta_contable_id = intval($res_cta_b->fetch_assoc()['cont_cuenta_id'] ?? 0);
                }
            } elseif ($tipo_medio === 'RETENCION') {
                if (!empty($m['impuesto_tipo_id'])) {
                    $res_it = $conexion->query("SELECT cuenta_contable_id FROM gestion__impuestos_tipos WHERE impuesto_tipo_id = " . intval($m['impuesto_tipo_id']));
                    $cta_contable_id = intval($res_it->fetch_assoc()['cuenta_contable_id'] ?? 0);
                }
                if ($cta_contable_id <= 0) {
                    $res_mp = $conexion->query("SELECT cont_cuenta_id FROM gestion__medios_pago WHERE medio_pago_id = " . intval($m['medio_pago_id']) . " AND empresa_id = $empresa_id");
                    $cta_contable_id = intval($res_mp->fetch_assoc()['cont_cuenta_id'] ?? 0);
                }
            }

            // Validar cuenta con resolver central (D36)
            $cuenta_valida = tesoreriaResolverCuentaContable($conexion, $empresa_id, $m['medio_pago'] ?? 'Medio de Pago', $cta_contable_id);

            $lineas_asiento[] = [
                'cuenta_id'   => $cuenta_valida['cont_cuenta_id'],
                'debe'        => 0.0,
                'haber'       => $monto_linea,
                'descripcion' => "Pago OP: " . ($m['medio_pago'] ?? '') . " - " . ($cab['entidad_nombre'] ?? '')
            ];
            $total_haber += $monto_linea;
        }

        // Cuenta de la entidad (DEBE). Un cliente al que se le devuelve dinero de su nota de crédito cancela
        // Deudores por Ventas; un proveedor, la cuenta del proveedor o la de proveedores de la empresa.
        $es_devolucion_cliente = intval($cab['entidad_id'] ?? 0) <= 0   // consumidor final
                              || (intval($cab['es_cliente'] ?? 0) === 1 && intval($cab['es_proveedor'] ?? 0) !== 1);
        if ($es_devolucion_cliente) {
            $cta_prov_id = intval($cab['cont_cuenta_id_cliente'] ?? 0);
            if ($cta_prov_id <= 0) {
                $cta_prov_id = emp_cuenta_id($conexion, $empresa_id, 'DEUDORES_VENTAS', 'cuenta_deudores_ventas_id');
            }
            $cta_prov_valida = tesoreriaResolverCuentaContable($conexion, $empresa_id, 'Deudores por Ventas', $cta_prov_id);
        } else {
            $cta_prov_id = intval($cab['cont_cuenta_id_proveedor'] ?? 0);
            if ($cta_prov_id <= 0) {
                $cta_prov_id = emp_cuenta_id($conexion, $empresa_id, 'PROVEEDORES', 'cuenta_proveedores_id');
            }
            $cta_prov_valida = tesoreriaResolverCuentaContable($conexion, $empresa_id, 'Proveedores Locales', $cta_prov_id);
        }

        array_unshift($lineas_asiento, [
            'cuenta_id'   => $cta_prov_valida['cont_cuenta_id'],
            'debe'        => $total_haber,
            'haber'       => 0.0,
            'descripcion' => "Cancelación cuenta corriente proveedor " . ($cab['entidad_nombre'] ?? '')
        ]);

        $descripcion_asiento = "Orden de Pago OP " . emp_numero_comprobante($conexion, $empresa_id, $punto_venta_id, $proximo_nro) . " - " . ($cab['entidad_nombre'] ?? '');

        // OP en $0 (aplicación de notas de crédito): no mueve fondos ni genera asiento
        if ($total_haber > 0.0001) {
            tesoreriaGenerarAsiento($conexion, [
                'empresa_id'           => $empresa_id,
                'sucursal_id'          => intval($cab['sucursal_id']),
                'comprobante_id'       => $comp_origen_id,
                'entidad_id'           => intval($cab['entidad_id']),
                'f_asiento'            => $cab['f_emision'],
                'descripcion'          => $descripcion_asiento,
                'usuario_id'           => $usuario_id,
            ], $lineas_asiento);
        }

        if (!$en_transaccion) {
            mysqli_commit($conexion);
        }

        return [
            'success'         => true,
            'comprobante_id'  => $comp_origen_id,
            'comprobante_nro' => $proximo_nro,
            'message'         => "Orden de Pago confirmada con éxito. OP N° " . emp_numero_comprobante($conexion, $empresa_id, $punto_venta_id, $proximo_nro)
        ];

    } catch (Exception $e) {
        if (!$en_transaccion) {
            mysqli_rollback($conexion);
        }
        error_log("Error confirmando pago $pago_id: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Anulación de Orden de Pago (§2.6 / RT08)
 */
// $en_transaccion = true: corre dentro de la transacción del llamador (p. ej. anulación de la factura de proveedor
// que generó esta OP automática) y relanza la excepción sin rollback.
function anularPago($conexion, $pago_id, $empresa_id, $usuario_id, $motivo = '', $en_transaccion = false)
{
    $pago_id    = intval($pago_id);
    $empresa_id = intval($empresa_id);
    $usuario_id = intval($usuario_id);
    $motivo     = trim($motivo);

    if (empty($motivo)) {
        throw new Exception("Debe ingresar un motivo de anulación.");
    }

    if (!$en_transaccion) mysqli_begin_transaction($conexion);

    try {
        $sql = "SELECT p.*, comp.comprobante_id
                FROM gestion__pagos p
                JOIN gestion__comprobantes comp ON comp.comprobante_id = p.comprobante_id
                WHERE p.pago_id = ? AND p.empresa_id = ?
                FOR UPDATE";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $pago_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $pago = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if (!$pago) throw new Exception("Orden de Pago no encontrada.");
        if (intval($pago['tabla_estado_registro_id']) !== ESTADO_PAGO_CONFIRMADO) {
            throw new Exception("Solo se pueden anular órdenes de pago en estado Confirmado.");
        }

        $comp_id = intval($pago['comprobante_id']);

        // 1. Transición de estado a ANULADO
        $sql_upd = "UPDATE gestion__pagos 
                    SET tabla_estado_registro_id = ?, motivo_anulacion = ?, usuario_modificacion_id = ?, fecha_modificacion = NOW()
                    WHERE pago_id = ?";
        $stmt_u = mysqli_prepare($conexion, $sql_upd);
        $est_anul = ESTADO_PAGO_ANULADO;
        mysqli_stmt_bind_param($stmt_u, "isii", $est_anul, $motivo, $usuario_id, $pago_id);
        mysqli_stmt_execute($stmt_u);
        mysqli_stmt_close($stmt_u);

        // 2. Anular imputaciones vigentes reabriendo las facturas de compra (§2.6)
        $sql_imps = "SELECT imputacion_id FROM gestion__comprobantes_imputaciones 
                     WHERE comp_origen_id = ? AND tabla_estado_registro_id = 5";
        $stmt_im = mysqli_prepare($conexion, $sql_imps);
        mysqli_stmt_bind_param($stmt_im, "i", $comp_id);
        mysqli_stmt_execute($stmt_im);
        $res_im = mysqli_stmt_get_result($stmt_im);
        while ($imp = mysqli_fetch_assoc($res_im)) {
            tesoreriaAnularImputacion($conexion, intval($imp['imputacion_id']), $usuario_id, $motivo);
        }
        mysqli_stmt_close($stmt_im);

        // 3. Revertir movimientos en libro de tesorería (D15)
        $sql_movs = "SELECT tesoreria_movimiento_id FROM gestion__tesoreria_movimientos 
                     WHERE comprobante_id = ? AND reversa_de_movimiento_id IS NULL";
        $stmt_mv = mysqli_prepare($conexion, $sql_movs);
        mysqli_stmt_bind_param($stmt_mv, "i", $comp_id);
        mysqli_stmt_execute($stmt_mv);
        $res_mv = mysqli_stmt_get_result($stmt_mv);
        while ($mv = mysqli_fetch_assoc($res_mv)) {
            tesoreriaAnularMovimiento($conexion, intval($mv['tesoreria_movimiento_id']), $usuario_id, $motivo);
        }
        mysqli_stmt_close($stmt_mv);

        // 4. Gestión de cheques afectados:
        // A) Cheques propios: pasan a ANULADOS (38)
        $conexion->query("UPDATE gestion__cheques SET tabla_estado_registro_id = 38, usuario_modificacion_id = $usuario_id WHERE comp_origen_id = $comp_id AND cheque_tipo = 'PROPIO'");

        // B) Cheques de terceros que fueron endosados: vuelven a EN_CARTERA (29) y se desvincula comp_destino_id (C2 / RT06)
        $conexion->query("UPDATE gestion__cheques SET tabla_estado_registro_id = 29, comp_destino_id = NULL, usuario_modificacion_id = $usuario_id WHERE comp_destino_id = $comp_id AND cheque_tipo = 'TERCERO'");

        // 5. Anular comprobante OP
        $conexion->query("UPDATE gestion__comprobantes SET tabla_estado_registro_id = $est_anul, importe_pendiente = 0, usuario_modificacion_id = $usuario_id WHERE comprobante_id = $comp_id");

        // 6. Anular asiento contable asociado
        $conexion->query("UPDATE gestion__cont_asientos SET tabla_estado_registro_id = 2, usuario_modificacion_id = $usuario_id WHERE comprobante_id = $comp_id");

        if (!$en_transaccion) mysqli_commit($conexion);

        return [
            'success' => true,
            'message' => "Orden de Pago anulada exitosamente y saldos revertidos."
        ];

    } catch (Exception $e) {
        if (!$en_transaccion) mysqli_rollback($conexion);
        error_log("Error anulando pago $pago_id: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Elimina un borrador de Orden de Pago
 */
function eliminarPagoBorrador($conexion, $pago_id, $empresa_id, $usuario_id)
{
    $pago_id    = intval($pago_id);
    $empresa_id = intval($empresa_id);

    $sql = "SELECT p.pago_id, p.comprobante_id, p.tabla_estado_registro_id
            FROM gestion__pagos p
            WHERE p.pago_id = ? AND p.empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $pago_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $p = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$p) throw new Exception("Orden de Pago no encontrada.");
    if (intval($p['tabla_estado_registro_id']) !== ESTADO_PAGO_BORRADOR) {
        throw new Exception("Solo se pueden eliminar pagos en estado Borrador.");
    }

    $comp_id = intval($p['comprobante_id']);

    mysqli_begin_transaction($conexion);
    try {
        $conexion->query("DELETE FROM gestion__pagos_medios WHERE pago_id = $pago_id");
        if ($comp_id > 0) {
            $conexion->query("DELETE FROM gestion__cheques WHERE comp_origen_id = $comp_id AND cheque_tipo = 'PROPIO'");
            $conexion->query("DELETE FROM gestion__comprobantes_imputaciones WHERE comp_origen_id = $comp_id");
            $conexion->query("DELETE FROM gestion__comprobantes WHERE comprobante_id = $comp_id");
        }
        $conexion->query("DELETE FROM gestion__pagos WHERE pago_id = $pago_id");
        mysqli_commit($conexion);
        return ['success' => true, 'message' => "Borrador de Orden de Pago eliminado."];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        throw $e;
    }
}

/**
 * Obtiene los proveedores de la empresa
 */
function obtenerProveedoresPago($conexion, $empresa_id)
{
    $empresa_id = intval($empresa_id);
    // Incluye clientes: devolución de dinero de una nota de crédito de venta
    $sql = "SELECT entidad_id, entidad_nombre, cuit, domicilio_legal, es_cliente, es_proveedor
            FROM gestion__entidades
            WHERE empresa_id = ? AND (es_proveedor = 1 OR es_cliente = 1) AND tabla_estado_registro_id = 1
            ORDER BY entidad_nombre ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = $r;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

/**
 * Obtiene cheques de terceros que están en cartera disponibles para ser endosados (C2 / RT06)
 */
function obtenerChequesTercerosEnCartera($conexion, $empresa_id)
{
    $empresa_id = intval($empresa_id);
    $sql = "SELECT chq.cheque_id, chq.numero, chq.f_pago, chq.importe, chq.librador_nombre,
                   b.banco, m.simbolo AS moneda_simbolo
            FROM gestion__cheques chq
            JOIN conf__bancos b ON b.banco_id = chq.banco_id
            JOIN gestion__monedas m ON m.moneda_id = chq.moneda_id
            WHERE chq.empresa_id = ? 
              AND chq.cheque_tipo = 'TERCERO'
              AND chq.tabla_estado_registro_id = 29
            ORDER BY chq.f_pago ASC, chq.numero ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = $r;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}

/**
 * Obtiene las cuentas bancarias de la empresa con chequera
 */
function obtenerCuentasBancariasChequeras($conexion, $empresa_id)
{
    $empresa_id = intval($empresa_id);
    $sql = "SELECT tc.tesoreria_cuenta_id, tc.tesoreria_cuenta, tc.banco_id, b.banco
            FROM gestion__tesoreria_cuentas tc
            LEFT JOIN conf__bancos b ON b.banco_id = tc.banco_id
            WHERE tc.empresa_id = ? AND tc.cuenta_tipo = 'BANCO' AND tc.tabla_estado_registro_id = 1
            ORDER BY tc.tesoreria_cuenta ASC";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "i", $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = $r;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}
