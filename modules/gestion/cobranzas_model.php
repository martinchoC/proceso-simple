<?php
require_once __DIR__ . '/../../core/empresa_config.php';
/**
 * cobranzas_model.php
 *
 * Modelo para la Gestión de Cobranzas a Clientes (Comprobante RCX)
 * Sistema gestion_multipyme - Módulo Tesorería
 *
 * Implementa las especificaciones de docs/gestion/08_proc_tesoreria_v3_11.docx:
 * - §4.2: Cobranza (gestion__cobranzas + gestion__cobranzas_medios + comprobante RCX).
 * - §2.5: Patrón de confirmación transaccional atómico y orden de locks.
 * - §2.6: Patrón de anulación y bloqueo si hay cheques fuera de cartera.
 * - D1:   Comprobante desde Borrador (syncComprobante).
 * - D6:   Cobros a cuenta y anticipos permitidos (SUM imputaciones <= importe_total).
 * - D27/D36/Anexo D: Asiento contable de cobranza balanceado automático.
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/tesoreria_core_model.php';

$conexion = $conn;

// Estados estándar tomados de conf__estados_registros (estado_registro_id), idéntico al patrón de ventas_facturas.php
const ESTADO_COBRANZA_BORRADOR   = 3;  // Borrador
const ESTADO_COBRANZA_CONFIRMADO = 5;  // Confirmado
const ESTADO_COBRANZA_ANULADO    = 38; // Anulado
const ESTADO_COBRANZA_ELIMINADO  = 6;  // Eliminado

const TIPO_COMPROBANTE_RCX_ID    = 24; // Recibo de Cobro
const TABLA_COBRANZAS_ID         = 275; // gestion__cobranzas en conf__tablas

/**
 * Retenciones sufridas (medios de tipo RETENCION): IVA, Ganancias o Ingresos Brutos que nos retiene el cliente.
 * El impuesto es un gestion__impuestos_tipos con es_retencion = 1 y aplica_venta = 1; IIBB lleva jurisdicción provincial.
 */
function cobImpuestoEsIibb($impuesto_tipo)
{
    $n = mb_strtoupper((string)$impuesto_tipo, 'UTF-8');
    return strpos($n, 'IIBB') !== false || strpos($n, 'INGRESOS BRUTOS') !== false;
}

/**
 * Valida las líneas de retención de una cobranza y completa sus datos: impuesto del medio si no vino,
 * jurisdicción solo para IIBB. Certificado obligatorio y sin repetir para el mismo cliente, impuesto y jurisdicción.
 */
function cobValidarRetenciones($conexion, $empresa_id, $entidad_id, $cobranza_id, array &$medios)
{
    $stmt_mp = mysqli_prepare($conexion, "SELECT medio_pago, medio_pago_tipo, impuesto_tipo_id FROM gestion__medios_pago WHERE medio_pago_id = ? AND empresa_id = ? LIMIT 1");
    $stmt_it = mysqli_prepare($conexion, "SELECT impuesto_tipo FROM gestion__impuestos_tipos WHERE impuesto_tipo_id = ? AND es_retencion = 1 AND aplica_venta = 1 AND tabla_estado_registro_id = 1 LIMIT 1");
    $stmt_jur = mysqli_prepare($conexion, "SELECT jurisdiccion_nombre FROM gestion__jurisdicciones WHERE jurisdiccion_id = ? AND jurisdiccion_tipo_id = 2 AND tabla_estado_registro_id = 1 LIMIT 1");
    $stmt_dup = mysqli_prepare($conexion, "SELECT c.cobranza_id, c.comprobante_nro
                                           FROM gestion__cobranzas_medios cm
                                           JOIN gestion__cobranzas c ON c.cobranza_id = cm.cobranza_id
                                           WHERE c.empresa_id = ? AND c.entidad_id = ? AND c.cobranza_id <> ?
                                             AND c.tabla_estado_registro_id NOT IN (?, ?)
                                             AND cm.impuesto_tipo_id = ? AND COALESCE(cm.jurisdiccion_id, 0) = ?
                                             AND cm.retencion_certificado_nro = ?
                                           LIMIT 1");
    $est_anulado = ESTADO_COBRANZA_ANULADO;
    $est_eliminado = ESTADO_COBRANZA_ELIMINADO;
    $vistos = [];

    foreach ($medios as $i => &$m) {
        $fila = 'Medio ' . ($i + 1) . ': ';
        $medio_pago_id = intval($m['medio_pago_id'] ?? 0);
        mysqli_stmt_bind_param($stmt_mp, "ii", $medio_pago_id, $empresa_id);
        mysqli_stmt_execute($stmt_mp);
        $mp = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_mp));
        if (!$mp) {
            throw new Exception($fila . 'el medio de cobro no existe en la empresa.');
        }
        if ($mp['medio_pago_tipo'] !== 'RETENCION') {
            continue;
        }

        if ($entidad_id <= 0) {
            throw new Exception($fila . 'las retenciones requieren un cliente identificado (un consumidor final no retiene).');
        }

        $imp_medio = intval($mp['impuesto_tipo_id'] ?? 0);
        $imp_id = intval($m['impuesto_tipo_id'] ?? 0) ?: $imp_medio;
        if ($imp_medio > 0 && $imp_id !== $imp_medio) {
            throw new Exception($fila . "el medio \"{$mp['medio_pago']}\" corresponde a otro impuesto.");
        }
        mysqli_stmt_bind_param($stmt_it, "i", $imp_id);
        mysqli_stmt_execute($stmt_it);
        $it = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_it));
        if (!$it) {
            throw new Exception($fila . 'seleccione el impuesto retenido (IVA, Ganancias o Ingresos Brutos).');
        }

        $cert = trim((string)($m['retencion_certificado_nro'] ?? ''));
        if ($cert === '') {
            throw new Exception($fila . "ingrese el número de certificado de la {$it['impuesto_tipo']}.");
        }

        $jur_id = 0;
        if (cobImpuestoEsIibb($it['impuesto_tipo'])) {
            $jur_id = intval($m['jurisdiccion_id'] ?? 0);
            mysqli_stmt_bind_param($stmt_jur, "i", $jur_id);
            mysqli_stmt_execute($stmt_jur);
            if ($jur_id <= 0 || !mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_jur))) {
                throw new Exception($fila . 'seleccione la jurisdicción (provincia) de la retención de Ingresos Brutos.');
            }
        }

        $clave = $imp_id . '|' . $jur_id . '|' . mb_strtoupper($cert, 'UTF-8');
        if (isset($vistos[$clave])) {
            throw new Exception($fila . "el certificado $cert está cargado dos veces.");
        }
        $vistos[$clave] = true;

        mysqli_stmt_bind_param($stmt_dup, "iiiiiiis", $empresa_id, $entidad_id, $cobranza_id, $est_anulado, $est_eliminado, $imp_id, $jur_id, $cert);
        mysqli_stmt_execute($stmt_dup);
        $dup = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_dup));
        if ($dup) {
            $ref = intval($dup['comprobante_nro']) > 0 ? 'recibo N° ' . intval($dup['comprobante_nro']) : 'borrador de cobranza #' . intval($dup['cobranza_id']);
            throw new Exception($fila . "el certificado $cert de {$it['impuesto_tipo']} ya está registrado para este cliente ($ref).");
        }

        $m['impuesto_tipo_id'] = $imp_id;
        $m['jurisdiccion_id'] = $jur_id > 0 ? $jur_id : null;
        $m['retencion_certificado_nro'] = $cert;
    }
    unset($m);

    mysqli_stmt_close($stmt_mp);
    mysqli_stmt_close($stmt_it);
    mysqli_stmt_close($stmt_jur);
    mysqli_stmt_close($stmt_dup);
}

/**
 * Cuenta contable (activo) de una retención sufrida, para el asiento del recibo.
 * - IIBB: la de la configuración de impuestos de la empresa para ese impuesto y jurisdicción, vigente a la fecha
 *   (gestion__empresas_impuestos_config, igual que las percepciones recibidas). Sin configuración, error: no se
 *   imputa una retención de una provincia en la cuenta de otra.
 * - IVA / Ganancias: la del medio de cobro (la Posición Mensual de IVA acredita esa misma cuenta).
 */
function cobCuentaRetencion($conexion, $empresa_id, array $m, $fecha)
{
    $imp_id = intval($m['impuesto_tipo_id'] ?? 0);
    $jur_id = intval($m['jurisdiccion_id'] ?? 0);
    $impuesto = trim(($m['impuesto_tipo'] ?? 'Retención') . ' ' . ($m['jurisdiccion_nombre'] ?? ''));

    if ($jur_id > 0) {
        $stmt = mysqli_prepare($conexion, "SELECT cont_cuenta_id FROM gestion__empresas_impuestos_config
                                           WHERE empresa_id = ? AND impuesto_tipo_id = ? AND jurisdiccion_id = ?
                                             AND cont_cuenta_id > 0 AND tabla_estado_registro_id = 1
                                             AND f_desde <= ? AND (f_hasta IS NULL OR f_hasta >= ?)
                                           ORDER BY prioridad ASC, empresa_impuesto_config_id ASC LIMIT 1");
        mysqli_stmt_bind_param($stmt, "iiiss", $empresa_id, $imp_id, $jur_id, $fecha, $fecha);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!$row) {
            throw new Exception("Falta la cuenta contable de la $impuesto: configurarla en los impuestos de la empresa (Configuración de Impuestos, por jurisdicción).");
        }
        return intval($row['cont_cuenta_id']);
    }

    $medio_pago_id = intval($m['medio_pago_id'] ?? 0);
    $stmt = mysqli_prepare($conexion, "SELECT cont_cuenta_id FROM gestion__medios_pago WHERE medio_pago_id = ? AND empresa_id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "ii", $medio_pago_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $cta = intval(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt))['cont_cuenta_id'] ?? 0);
    mysqli_stmt_close($stmt);
    if ($cta <= 0) {
        throw new Exception("Falta la cuenta contable de la $impuesto: cargarla en el medio de cobro \"" . ($m['medio_pago'] ?? '') . "\" (Tesorería > Medios de Pago).");
    }
    return $cta;
}

/**
 * Obtiene el estado inicial de la cobranza consultando el estado_registro_id inicial en conf__tablas_estados_registros
 * Mismo patrón que vfEstadoInicial() en ventas_facturas_model.php
 */
function obtenerEstadoInicialCobranza($conexion, $pagina_id = 105)
{
    $sql = "SELECT ter.estado_registro_id
            FROM conf__tablas_estados_registros ter
            INNER JOIN conf__paginas pg ON pg.tabla_id = ter.tabla_id
            WHERE pg.pagina_id = ? AND ter.es_inicial = 1 LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if ($stmt) {
        mysqli_stmt_bind_param($stmt, 'i', $pagina_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
        mysqli_stmt_close($stmt);
        if (!empty($row['estado_registro_id'])) {
            return intval($row['estado_registro_id']);
        }
    }
    return ESTADO_COBRANZA_BORRADOR;
}

/**
 * Sincroniza la cobranza con gestion__comprobantes (D1 / §2.5 / D23)
 */
function syncComprobanteCobranza($conexion, $cobranza_id, $data)
{
    $cobranza_id = intval($cobranza_id);
    if ($cobranza_id <= 0) return null;

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
    $tabla_estado_id     = intval($data['tabla_estado_registro_id'] ?? ESTADO_COBRANZA_BORRADOR);
    $usuario_id          = !empty($data['usuario_id']) ? intval($data['usuario_id']) : 1;
    $observaciones       = trim($data['observaciones'] ?? '');
    $tabla_origen_id     = TABLA_COBRANZAS_ID;
    $comprobante_tipo_id = intval($data['comprobante_tipo_id'] ?? 0);
    if ($comprobante_tipo_id <= 0) {
        $comprobante_tipo_id = TIPO_COMPROBANTE_RCX_ID;
    }

    // Verificar si ya existe el comprobante para esta cobranza
    $sql_check = "SELECT comprobante_id FROM gestion__comprobantes 
                  WHERE tabla_origen_id = ? AND registro_origen_id = ?";
    $stmt_c = mysqli_prepare($conexion, $sql_check);
    mysqli_stmt_bind_param($stmt_c, "ii", $tabla_origen_id, $cobranza_id);
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
                        observaciones = ?, usuario_modificacion_id = ?,
                        fecha_modificacion = NOW()
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
            $observaciones, $usuario_id,
            $comprobante_id
        );
        mysqli_stmt_execute($stmt_u);
        mysqli_stmt_close($stmt_u);
    } else {
        $importe_pendiente = $importe_total;
        $sql_ins = "INSERT INTO gestion__comprobantes (
                        empresa_id, sucursal_id, comprobante_pv,
                        comprobante_tipo_id, comprobante_nro,
                        entidad_id, entidad_sucursal_id,
                        f_emision, f_contabilidad,
                        moneda_id, tipo_cambio,
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
            $importe_pendiente, $tabla_origen_id, $cobranza_id,
            $tabla_estado_id, $observaciones, $usuario_id
        );
        mysqli_stmt_execute($stmt_i);
        $comprobante_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt_i);

        // Actualizar comprobante_id en la cobranza
        $stmt_upd_cob = mysqli_prepare($conexion, "UPDATE gestion__cobranzas SET comprobante_id = ? WHERE cobranza_id = ?");
        mysqli_stmt_bind_param($stmt_upd_cob, "ii", $comprobante_id, $cobranza_id);
        mysqli_stmt_execute($stmt_upd_cob);
        mysqli_stmt_close($stmt_upd_cob);
    }

    return $comprobante_id;
}

/**
 * Obtiene el listado de cobranzas con filtros
 */
function obtenerCobranzasListado($conexion, $empresa_id, $pagina_id = 105, $filtros = [])
{
    $empresa_id = intval($empresa_id);

    $sql = "SELECT c.cobranza_id, c.f_emision, " . emp_sql_pv_mostrar('c.comprobante_pv', 'c.empresa_id') . " AS comprobante_pv, c.comprobante_nro,
                   c.comprobante_tipo_id, ct.comprobante_tipo, ct.codigo AS comprobante_codigo, ct.letra AS comprobante_letra,
                   c.importe_total, c.observaciones, c.tabla_estado_registro_id,
                   c.entidad_id, COALESCE(e.entidad_nombre, 'Consumidor Final') AS cliente_nombre, e.cuit AS cliente_cuit,
                   s.sucursal_nombre,
                   m.simbolo AS moneda_simbolo,
                   er.codigo_estandar AS estado_codigo, er.estado_registro,
                   comp.importe_pendiente AS saldo_a_cuenta
            FROM gestion__cobranzas c
            LEFT JOIN gestion__entidades e ON e.entidad_id = c.entidad_id
            JOIN gestion__sucursales s ON s.sucursal_id = c.sucursal_id
            JOIN gestion__monedas m ON m.moneda_id = c.moneda_id
            LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
            LEFT JOIN gestion__comprobantes comp ON comp.comprobante_id = c.comprobante_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = c.tabla_estado_registro_id
            WHERE c.empresa_id = ? ";

    $params = [$empresa_id];
    $types  = "i";

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
    if (!empty($filtros['entidad_id'])) {
        $sql .= " AND c.entidad_id = ? ";
        $params[] = intval($filtros['entidad_id']);
        $types .= "i";
    }
    if (!empty($filtros['estado_id'])) {
        $sql .= " AND c.tabla_estado_registro_id = ? ";
        $params[] = intval($filtros['estado_id']);
        $types .= "i";
    }
    if (!empty($filtros['comprobante_pv'])) {
        $sql .= " AND c.comprobante_pv = ? ";
        $params[] = intval($filtros['comprobante_pv']);
        $types .= "i";
    }

    if (file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
        require_once __DIR__ . '/../../core/accesos_sucursales.php';
        $uid = intval($_SESSION['usuario_id'] ?? 0);
        if ($uid > 0) {
            $sql .= AccesosSucursalesHelper::filtroSqlSucursal($conexion, $uid, $empresa_id, 'c.sucursal_id');
        }
    }

    $sql .= " ORDER BY c.f_emision DESC, c.cobranza_id DESC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return ['data' => []];

    mysqli_stmt_bind_param($stmt, $types, ...$params);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $datos = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $id = intval($row['cobranza_id']);
        $estado_id = intval($row['tabla_estado_registro_id']);
        $estado_cod = $row['estado_codigo'] ?? 'BORRADOR';

        // Número formateado
        $nro_formateado = ($row['comprobante_nro'] > 0)
            ? str_pad($row['comprobante_pv'], 4, '0', STR_PAD_LEFT) . '-' . str_pad($row['comprobante_nro'], 8, '0', STR_PAD_LEFT)
            : '<span class="badge bg-secondary-subtle text-secondary border">Borrador (Sin emitir)</span>';

        // Badge de estado
        if ($estado_id === ESTADO_COBRANZA_CONFIRMADO) {
            $estado_badge = '<span class="badge bg-success"><i class="fas fa-check-circle me-1"></i>Confirmada</span>';
        } elseif ($estado_id === ESTADO_COBRANZA_BORRADOR) {
            $estado_badge = '<span class="badge bg-warning text-dark"><i class="fas fa-clock me-1"></i>Borrador</span>';
        } elseif ($estado_id === ESTADO_COBRANZA_ANULADO) {
            $estado_badge = '<span class="badge bg-danger"><i class="fas fa-ban me-1"></i>Anulada</span>';
        } else {
            $estado_badge = '<span class="badge bg-secondary">' . htmlspecialchars($row['estado_registro'] ?? 'Estado ' . $estado_id) . '</span>';
        }

        // Saldo a favor / Cobro a cuenta
        $saldo_ac = floatval($row['saldo_a_cuenta'] ?? 0);
        $saldo_badge = ($saldo_ac > 0.001)
            ? '<span class="badge bg-info-subtle text-info border border-info-subtle" title="Monto no aplicado disponible a favor del cliente">A cuenta: $' . number_format($saldo_ac, 2, ',', '.') . '</span>'
            : '<span class="text-muted small">Totalmente aplicado</span>';

        // Botones de acción según estado
        $botones = '<div class="btn-group btn-group-sm" role="group">';
        $botones .= '<button type="button" class="btn btn-outline-info btn-accion" data-id="' . $id . '" data-accion="visualizar" title="Ver Recibo Completo"><i class="fas fa-eye"></i></button>';
        $botones .= '<button type="button" class="btn btn-outline-secondary btn-accion" data-id="' . $id . '" data-accion="imprimir" title="Imprimir Recibo (Formato Factura)"><i class="fas fa-print"></i></button>';

        if ($estado_id === ESTADO_COBRANZA_BORRADOR) {
            $botones .= '<button type="button" class="btn btn-outline-primary btn-accion" data-id="' . $id . '" data-accion="editar" title="Editar Borrador"><i class="fas fa-edit"></i></button>';
            $botones .= '<button type="button" class="btn btn-outline-success btn-accion" data-id="' . $id . '" data-accion="confirmar" title="Confirmar Cobranza (Emitir Recibo)"><i class="fas fa-check"></i></button>';
            $botones .= '<button type="button" class="btn btn-outline-danger btn-accion" data-id="' . $id . '" data-accion="eliminar" title="Eliminar Borrador"><i class="fas fa-trash"></i></button>';
        } elseif ($estado_id === ESTADO_COBRANZA_CONFIRMADO) {
            $botones .= '<button type="button" class="btn btn-outline-danger btn-accion" data-id="' . $id . '" data-accion="anular" title="Anular Recibo"><i class="fas fa-ban"></i></button>';
        }
        $botones .= '</div>';

        $datos[] = [
            'id'             => $id,
            'f_emision'      => date('d/m/Y', strtotime($row['f_emision'])),
            'numero'         => $nro_formateado,
            'cliente'        => '<strong>' . htmlspecialchars($row['cliente_nombre']) . '</strong><br><small class="text-muted">CUIT: ' . htmlspecialchars($row['cliente_cuit'] ?? 'S/D') . '</small>',
            'sucursal'       => htmlspecialchars($row['sucursal_nombre']),
            'total'          => '<span class="fw-bold fs-6">$' . number_format(floatval($row['importe_total']), 2, ',', '.') . '</span>',
            'saldo_a_cuenta' => $saldo_badge,
            'estado'         => $estado_badge,
            'acciones'       => $botones
        ];
    }
    mysqli_stmt_close($stmt);

    return ['data' => $datos];
}

/**
 * Obtiene el detalle completo de una cobranza para ver o editar
 */
function obtenerCobranzaDetalle($conexion, $cobranza_id, $empresa_id)
{
    $cobranza_id = intval($cobranza_id);
    $empresa_id  = intval($empresa_id);

    // Cabecera
    $sql_c = "SELECT c.*, " . emp_sql_pv_mostrar('c.comprobante_pv', 'c.empresa_id') . " AS pv_nro, ct.comprobante_tipo, ct.codigo AS comprobante_codigo, ct.letra AS comprobante_letra,
                     COALESCE(e.entidad_nombre, 'Consumidor Final') AS entidad_nombre, e.cuit, e.domicilio_legal,
                     e.es_cliente, e.es_proveedor, e.cont_cuenta_id_cliente, e.cont_cuenta_id_proveedor,
                     s.sucursal_nombre, m.codigo AS moneda_codigo, m.simbolo AS moneda_simbolo,
                     er.codigo_estandar AS estado_codigo, er.estado_registro
              FROM gestion__cobranzas c
              LEFT JOIN gestion__entidades e ON e.entidad_id = c.entidad_id
              JOIN gestion__sucursales s ON s.sucursal_id = c.sucursal_id
              JOIN gestion__monedas m ON m.moneda_id = c.moneda_id
              LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = c.comprobante_tipo_id
              LEFT JOIN conf__estados_registros er ON er.estado_registro_id = c.tabla_estado_registro_id
              WHERE c.cobranza_id = ? AND c.empresa_id = ?";
    $stmt_c = mysqli_prepare($conexion, $sql_c);
    mysqli_stmt_bind_param($stmt_c, "ii", $cobranza_id, $empresa_id);
    mysqli_stmt_execute($stmt_c);
    $res_c = mysqli_stmt_get_result($stmt_c);
    $cabecera = mysqli_fetch_assoc($res_c);
    mysqli_stmt_close($stmt_c);

    if (!$cabecera) return null;

    // Medios de Cobro
    $sql_m = "SELECT cm.*, mp.codigo AS medio_codigo, mp.medio_pago, mp.medio_pago_tipo,
                     tc.tesoreria_cuenta, tc.cuenta_tipo,
                     chq.numero AS cheque_nro, chq.librador_nombre, chq.f_pago AS cheque_f_pago,
                     b.banco AS cheque_banco,
                     it.impuesto_tipo,
                     j.jurisdiccion_nombre, j.jurisdiccion_codigo
              FROM gestion__cobranzas_medios cm
              JOIN gestion__medios_pago mp ON mp.medio_pago_id = cm.medio_pago_id
              LEFT JOIN gestion__tesoreria_cuentas tc ON tc.tesoreria_cuenta_id = cm.tesoreria_cuenta_id
              LEFT JOIN gestion__cheques chq ON chq.cheque_id = cm.cheque_id
              LEFT JOIN conf__bancos b ON b.banco_id = chq.banco_id
              LEFT JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = cm.impuesto_tipo_id
              LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = cm.jurisdiccion_id
              WHERE cm.cobranza_id = ?
              ORDER BY cm.cobranza_medio_id ASC";
    $stmt_m = mysqli_prepare($conexion, $sql_m);
    mysqli_stmt_bind_param($stmt_m, "i", $cobranza_id);
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
        $sql_i = "SELECT ci.*, " . emp_sql_pv_mostrar('cd.comprobante_pv', 'cd.empresa_id', 'cd.tabla_origen_id') . " AS comprobante_pv, cd.comprobante_nro, cd.f_emision AS destino_f_emision,
                         cd.importe_total AS destino_total, cd.importe_pendiente AS destino_pendiente,
                         ct.codigo AS comprobante_tipo_codigo, ct.comprobante_tipo
                  FROM gestion__comprobantes_imputaciones ci
                  JOIN gestion__comprobantes cd ON cd.comprobante_id = ci.comp_destino_id
                  JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = cd.comprobante_tipo_id
                  WHERE ci.comp_origen_id = ?
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
        'imputaciones' => $imputaciones,
        'asiento'      => cobranzaObtenerAsiento($conexion, $cobranza_id, $empresa_id)
    ];
}

/**
 * Obtiene el asiento contable automático vinculado a la cobranza
 */
function cobranzaObtenerAsiento($conexion, $cobranza_id, $empresa_id)
{
    $cobranza_id = intval($cobranza_id);
    $empresa_id  = intval($empresa_id);

    $stmt_c = mysqli_prepare($conexion, "SELECT comprobante_id, tabla_estado_registro_id, importe_total FROM gestion__cobranzas WHERE cobranza_id = ? AND empresa_id = ? LIMIT 1");
    if (!$stmt_c) return null;
    mysqli_stmt_bind_param($stmt_c, "ii", $cobranza_id, $empresa_id);
    mysqli_stmt_execute($stmt_c);
    $cob = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_c));
    mysqli_stmt_close($stmt_c);

    if (!$cob) return null;
    $comp_id = intval($cob['comprobante_id'] ?? 0);

    // Si está confirmada y con importe mayor a 0 pero no tiene asiento generado, generarlo automáticamente
    if ($comp_id > 0 && intval($cob['tabla_estado_registro_id']) === ESTADO_COBRANZA_CONFIRMADO && floatval($cob['importe_total']) > 0) {
        $check = mysqli_prepare($conexion, "SELECT cont_asiento_id FROM gestion__cont_asientos WHERE comprobante_id = ? AND empresa_id = ? LIMIT 1");
        if ($check) {
            mysqli_stmt_bind_param($check, "ii", $comp_id, $empresa_id);
            mysqli_stmt_execute($check);
            $has_asiento = mysqli_fetch_assoc(mysqli_stmt_get_result($check));
            mysqli_stmt_close($check);
            if (!$has_asiento) {
                cobranzaGenerarAsientoContable($conexion, $cobranza_id, $empresa_id);
            }
        }
    }

    if ($comp_id <= 0) return null;

    $sql = "SELECT a.cont_asiento_id, a.empresa_id, a.sucursal_id, a.deposito_id,
                   a.comprobante_id, a.entidad_id, a.cont_tipo_asiento_id, a.f_asiento,
                   a.anio, a.mes, a.descripcion, a.moneda_id, a.tipo_cambio,
                   ta.cont_tipo_asiento, er.estado_registro
            FROM gestion__cont_asientos a
            LEFT JOIN gestion__cont_tipos_asientos ta ON ta.cont_tipo_asiento_id = a.cont_tipo_asiento_id
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = a.tabla_estado_registro_id
            WHERE a.comprobante_id = ? AND a.empresa_id = ?
            LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;
    mysqli_stmt_bind_param($stmt, "ii", $comp_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $asiento = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$asiento) return null;

    $sql_det = "SELECT d.cont_asiento_id, d.cuenta_id, d.importe_local, d.descripcion,
                       d.tipo, d.estado,
                       c.codigo AS cuenta_codigo, c.nombre AS cuenta_nombre,
                       IF(d.importe_local > 0, d.importe_local, 0) AS debe,
                       IF(d.importe_local < 0, ABS(d.importe_local), 0) AS haber
                FROM gestion__cont_asientos_detalles d
                LEFT JOIN gestion__cont_cuentas c ON c.cont_cuenta_id = d.cuenta_id
                WHERE d.cont_asiento_id = ?
                ORDER BY d.importe_local DESC, d.cuenta_id ASC";
    $stmt_det = mysqli_prepare($conexion, $sql_det);
    if (!$stmt_det) return $asiento;
    mysqli_stmt_bind_param($stmt_det, "i", $asiento['cont_asiento_id']);
    mysqli_stmt_execute($stmt_det);
    $res_det = mysqli_stmt_get_result($stmt_det);
    $detalles = [];
    $total_debe = 0;
    $total_haber = 0;
    while ($r = mysqli_fetch_assoc($res_det)) {
        $total_debe += floatval($r['debe']);
        $total_haber += floatval($r['haber']);
        $detalles[] = $r;
    }
    mysqli_stmt_close($stmt_det);

    $asiento['detalles'] = $detalles;
    $asiento['total_debe'] = $total_debe;
    $asiento['total_haber'] = $total_haber;
    return $asiento;
}

/**
 * Genera el asiento contable para una cobranza confirmada (D27 / D36 / Anexo D)
 */
function cobranzaGenerarAsientoContable($conexion, $cobranza_id, $empresa_id, $usuario_id = 1)
{
    $cob_info = obtenerCobranzaDetalle($conexion, $cobranza_id, $empresa_id);
    if (!$cob_info) {
        return ['success' => false, 'error' => 'Cobranza no encontrada'];
    }

    $cab = $cob_info['cabecera'];
    if (intval($cab['tabla_estado_registro_id']) !== ESTADO_COBRANZA_CONFIRMADO) {
        return ['success' => false, 'error' => 'Solo se pueden generar asientos para cobranzas confirmadas'];
    }

    $medios = $cob_info['medios'];
    $comp_origen_id = intval($cab['comprobante_id']);
    if ($comp_origen_id <= 0) {
        return ['success' => false, 'error' => 'Comprobante origen no asignado a la cobranza'];
    }

    $lineas_asiento = [];
    $total_debe = 0.0;

    foreach ($medios as $m) {
        $cta_contable_id = 0;
        $monto_linea = floatval($m['importe_comprobante']);
        if ($monto_linea <= 0) continue;

        if (!empty($m['tesoreria_cuenta_id'])) {
            $res_cta = $conexion->query("SELECT cont_cuenta_id FROM gestion__tesoreria_cuentas WHERE tesoreria_cuenta_id = " . intval($m['tesoreria_cuenta_id']) . " AND empresa_id = $empresa_id");
            $cta_contable_id = intval($res_cta->fetch_assoc()['cont_cuenta_id'] ?? 0);
        } elseif ($m['medio_pago_tipo'] === 'CHEQUE_TERCERO') {
            $res_val = $conexion->query("SELECT cont_cuenta_id FROM gestion__tesoreria_cuentas WHERE empresa_id = $empresa_id AND cuenta_tipo = 'VALORES' LIMIT 1");
            $cta_contable_id = intval($res_val->fetch_assoc()['cont_cuenta_id'] ?? 0);
            if ($cta_contable_id <= 0) {
                $res_mp = $conexion->query("SELECT cont_cuenta_id FROM gestion__medios_pago WHERE medio_pago_id = " . intval($m['medio_pago_id']) . " AND empresa_id = $empresa_id");
                $cta_contable_id = intval($res_mp->fetch_assoc()['cont_cuenta_id'] ?? 0);
            }
        } elseif ($m['medio_pago_tipo'] === 'RETENCION') {
            $cta_contable_id = cobCuentaRetencion($conexion, $empresa_id, $m, $cab['f_contabilidad'] ?: $cab['f_emision']);
        }

        $cuenta_valida = tesoreriaResolverCuentaContable($conexion, $empresa_id, $m['medio_pago'], $cta_contable_id);

        $desc_medio = $m['medio_pago'];
        if ($m['medio_pago_tipo'] === 'RETENCION') {
            $desc_medio = trim(($m['impuesto_tipo'] ?: $m['medio_pago']) . ' ' . ($m['jurisdiccion_nombre'] ?? '')) . ' Cert. ' . ($m['retencion_certificado_nro'] ?? 'S/N');
        }
        $lineas_asiento[] = [
            'cuenta_id'   => $cuenta_valida['cont_cuenta_id'],
            'debe'        => $monto_linea,
            'haber'       => 0.0,
            'descripcion' => "Cobranza Recibo: " . $desc_medio . " - " . ($cab['entidad_nombre'] ?? '')
        ];
        $total_debe += $monto_linea;
    }

    if ($total_debe <= 0.0001) {
        return ['success' => false, 'error' => 'Recibo en $0 o sin medios: no requiere asiento contable'];
    }

    $es_devolucion_proveedor = intval($cab['es_proveedor'] ?? 0) === 1 && intval($cab['es_cliente'] ?? 0) !== 1;
    if ($es_devolucion_proveedor) {
        $cta_cliente_id = intval($cab['cont_cuenta_id_proveedor'] ?? 0);
        if ($cta_cliente_id <= 0) {
            $cta_cliente_id = emp_cuenta_id($conexion, $empresa_id, 'PROVEEDORES', 'cuenta_proveedores_id');
        }
        $cta_cli_valida = tesoreriaResolverCuentaContable($conexion, $empresa_id, 'Proveedores Locales', $cta_cliente_id);
    } else {
        $cta_cliente_id = intval($cab['cont_cuenta_id_cliente'] ?? 0);
        if ($cta_cliente_id <= 0) {
            $cta_cliente_id = emp_cuenta_id($conexion, $empresa_id, 'DEUDORES_VENTAS', 'cuenta_deudores_ventas_id');
        }
        $cta_cli_valida = tesoreriaResolverCuentaContable($conexion, $empresa_id, 'Deudores por Ventas', $cta_cliente_id);
    }

    $lineas_asiento[] = [
        'cuenta_id'   => $cta_cli_valida['cont_cuenta_id'],
        'debe'        => 0.0,
        'haber'       => $total_debe,
        'descripcion' => "Cancelación cuenta corriente cliente " . ($cab['entidad_nombre'] ?? '')
    ];

    $nro_rec = emp_numero_comprobante($conexion, $empresa_id, intval($cab['comprobante_pv']), intval($cab['comprobante_nro']));
    $descripcion_asiento = "Recibo Cobranza " . $nro_rec . " - " . ($cab['entidad_nombre'] ?? '');

    $asiento_id = tesoreriaGenerarAsiento($conexion, [
        'empresa_id'           => $empresa_id,
        'sucursal_id'          => intval($cab['sucursal_id']),
        'comprobante_id'       => $comp_origen_id,
        'entidad_id'           => intval($cab['entidad_id']),
        'f_asiento'            => $cab['f_emision'],
        'descripcion'          => $descripcion_asiento,
        'usuario_id'           => $usuario_id,
    ], $lineas_asiento);

    return [
        'success'    => true,
        'asiento_id' => $asiento_id,
        'message'    => "Asiento contable #$asiento_id generado correctamente."
    ];
}

/**
 * Guarda o actualiza una cobranza en estado Borrador
 */
function guardarCobranzaBorrador($conexion, $data)
{
    $empresa_id = intval($data['empresa_id'] ?? 0);
    $sucursal_id = intval($data['sucursal_id'] ?? 0);
    $entidad_id = intval($data['entidad_id'] ?? 0);
    $f_emision = !empty($data['f_emision']) ? $data['f_emision'] : date('Y-m-d');
    $f_contabilidad = !empty($data['f_contabilidad']) ? $data['f_contabilidad'] : $f_emision;
    $moneda_id = emp_moneda_id($conexion, $empresa_id, $data['moneda_id'] ?? 0);
    $tipo_cambio = floatval($data['tipo_cambio'] ?? 1.0);
    $observaciones = trim($data['observaciones'] ?? '');
    $usuario_id = intval($data['usuario_id'] ?? 1);
    $cobranza_id = intval($data['cobranza_id'] ?? 0);

    $medios = $data['medios'] ?? [];
    $imputaciones = $data['imputaciones'] ?? [];

    // consumidor_final: recibo automático de una venta de contado a consumidor final (entidad 0).
    // recibo_cero: recibo en $0 que documenta la aplicación de notas de crédito (sin medios ni imputaciones).
    $es_consumidor_final = !empty($data['consumidor_final']);
    $es_recibo_cero = !empty($data['recibo_cero']);
    if ($empresa_id <= 0 || ($entidad_id <= 0 && !$es_consumidor_final)) {
        throw new Exception("Debe seleccionar un cliente y empresa válidos.");
    }
    if (empty($medios) && !$es_recibo_cero) {
        throw new Exception("Debe agregar al menos un medio de cobro.");
    }
    // Consumidor final: entidad_id NULL en gestion__cobranzas (FK a entidades); en gestion__comprobantes queda 0
    $entidad_cob = $entidad_id > 0 ? $entidad_id : null;

    if (file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
        require_once __DIR__ . '/../../core/accesos_sucursales.php';
        AccesosSucursalesHelper::exigirAccesoSucursal($conexion, $empresa_id, $sucursal_id, $usuario_id);
    }

    // Retenciones sufridas (IVA, Ganancias, IIBB): impuesto, jurisdicción y certificado
    cobValidarRetenciones($conexion, $empresa_id, $entidad_id, $cobranza_id, $medios);

    // Calcular total de medios
    $importe_total = 0.0;
    foreach ($medios as $m) {
        $imp_comp = floatval($m['importe_comprobante'] ?? $m['importe'] ?? 0);
        if ($imp_comp <= 0) throw new Exception("Cada medio de cobro debe tener un importe mayor a cero.");
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
        throw new Exception("El total imputado ($" . number_format($total_imputado, 2) . ") no puede superar el total cobrado ($" . number_format($importe_total, 2) . ").");
    }

    // Determinar punto de venta: respetar el punto de venta de la boca elegido por el usuario
    $comprobante_pv = intval($data['comprobante_pv'] ?? 0);
    if ($comprobante_pv <= 0) {
        $pvs_disponibles = obtenerPuntosVentaCobranza($conexion, $empresa_id, 105);
        if (!empty($pvs_disponibles)) {
            $comprobante_pv = intval($pvs_disponibles[0]['punto_venta_id']);
        }
        if ($comprobante_pv <= 0) $comprobante_pv = 1;
    }

    $comprobante_tipo_id = intval($data['comprobante_tipo_id'] ?? 0);
    if ($comprobante_tipo_id <= 0 && $comprobante_pv > 0) {
        $tipos_pv = obtenerTiposComprobanteCobranza($conexion, $empresa_id, $comprobante_pv, 105);
        if (!empty($tipos_pv)) {
            $comprobante_tipo_id = intval($tipos_pv[0]['comprobante_tipo_id']);
        }
    }
    if ($comprobante_tipo_id <= 0) {
        $comprobante_tipo_id = TIPO_COMPROBANTE_RCX_ID;
    }

    $estado_borrador = obtenerEstadoInicialCobranza($conexion, 105);

    if ($cobranza_id > 0) {
        // UPDATE Borrador existente
        $sql_upd = "UPDATE gestion__cobranzas SET
                        sucursal_id = ?, comprobante_tipo_id = ?, comprobante_pv = ?, entidad_id = ?,
                        f_emision = ?, f_contabilidad = ?, moneda_id = ?,
                        tipo_cambio = ?, importe_total = ?, observaciones = ?,
                        usuario_modificacion_id = ?, fecha_modificacion = NOW()
                    WHERE cobranza_id = ? AND empresa_id = ? AND tabla_estado_registro_id = ?";
        $stmt_u = mysqli_prepare($conexion, $sql_upd);
        mysqli_stmt_bind_param(
            $stmt_u, "iiiissiddsiiii",
            $sucursal_id, $comprobante_tipo_id, $comprobante_pv, $entidad_cob,
            $f_emision, $f_contabilidad, $moneda_id,
            $tipo_cambio, $importe_total, $observaciones,
            $usuario_id, $cobranza_id, $empresa_id, $estado_borrador
        );
        mysqli_stmt_execute($stmt_u);
        mysqli_stmt_close($stmt_u);

        // Borrar medios e imputaciones en borrador para reinsertar
        $conexion->query("DELETE FROM gestion__cobranzas_medios WHERE cobranza_id = $cobranza_id");
        $res_cid = $conexion->query("SELECT comprobante_id FROM gestion__cobranzas WHERE cobranza_id = $cobranza_id");
        $cid = intval($res_cid->fetch_assoc()['comprobante_id'] ?? 0);
        if ($cid > 0) {
            $conexion->query("DELETE FROM gestion__comprobantes_imputaciones WHERE comp_origen_id = $cid AND tabla_estado_registro_id = 3");
        }
    } else {
        // INSERT nuevo Borrador
        $sql_ins = "INSERT INTO gestion__cobranzas (
                        empresa_id, sucursal_id, comprobante_tipo_id, comprobante_pv,
                        comprobante_nro, comprobante_id, entidad_id, f_emision,
                        f_contabilidad, moneda_id, tipo_cambio, importe_total,
                        observaciones, tabla_estado_registro_id, usuario_id
                    ) VALUES (?, ?, ?, ?, 0, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_i = mysqli_prepare($conexion, $sql_ins);
        mysqli_stmt_bind_param(
            $stmt_i, "iiiisssiddssi",
            $empresa_id, $sucursal_id, $comprobante_tipo_id, $comprobante_pv,
            $entidad_cob, $f_emision, $f_contabilidad, $moneda_id,
            $tipo_cambio, $importe_total, $observaciones,
            $estado_borrador, $usuario_id
        );
        if (!mysqli_stmt_execute($stmt_i)) {
            throw new Exception("Error al insertar cobranza: " . mysqli_stmt_error($stmt_i));
        }
        $cobranza_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt_i);
    }

    // Sincronizar comprobante RCX desde Borrador (D1)
    $comprobante_id = syncComprobanteCobranza($conexion, $cobranza_id, [
        'empresa_id'               => $empresa_id,
        'sucursal_id'              => $sucursal_id,
        'comprobante_tipo_id'      => $comprobante_tipo_id,
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

    // Insertar Medios de Pago en gestion__cobranzas_medios
    $sql_med = "INSERT INTO gestion__cobranzas_medios (
                    cobranza_id, empresa_id, medio_pago_id, tesoreria_cuenta_id,
                    moneda_id, tipo_cambio, importe, importe_comprobante,
                    cheque_id, nro_operacion, f_valor, tarjeta_marca, tarjeta_cupon,
                    impuesto_tipo_id, jurisdiccion_id, retencion_certificado_nro, retencion_base_imponible,
                    retencion_alicuota, observaciones, usuario_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_med = mysqli_prepare($conexion, $sql_med);

    foreach ($medios as $m) {
        $medio_pago_id   = intval($m['medio_pago_id']);
        $tes_cuenta_id   = !empty($m['tesoreria_cuenta_id']) ? intval($m['tesoreria_cuenta_id']) : null;

        // Si no viene cuenta de tesorería y el medio es EFECTIVO (o cuenta_tipo CAJA), asignar la caja única de la sucursal
        if (empty($tes_cuenta_id)) {
            $res_mp = $conexion->query("SELECT medio_pago_tipo, tesoreria_cuenta_tipo FROM gestion__medios_pago WHERE medio_pago_id = $medio_pago_id AND empresa_id = $empresa_id LIMIT 1");
            if ($row_mp = $res_mp->fetch_assoc()) {
                if ($row_mp['medio_pago_tipo'] === 'EFECTIVO' || $row_mp['tesoreria_cuenta_tipo'] === 'CAJA') {
                    $res_caja = $conexion->query("SELECT tesoreria_cuenta_id FROM gestion__tesoreria_cuentas WHERE empresa_id = $empresa_id AND sucursal_id = $sucursal_id AND cuenta_tipo = 'CAJA' AND tabla_estado_registro_id = 1 LIMIT 1");
                    if ($row_caja = $res_caja->fetch_assoc()) {
                        $tes_cuenta_id = intval($row_caja['tesoreria_cuenta_id']);
                    }
                }
            }
        }

        $mon_id          = emp_moneda_id($conexion, $empresa_id, $m['moneda_id'] ?? $moneda_id);

        $tc              = floatval($m['tipo_cambio'] ?? 1.0);
        $imp             = floatval($m['importe'] ?? 0);
        $imp_comp        = floatval($m['importe_comprobante'] ?? $imp);
        $nro_op          = !empty($m['nro_operacion']) ? trim($m['nro_operacion']) : (!empty($m['numero_operacion']) ? trim($m['numero_operacion']) : null);
        $f_val           = !empty($m['f_valor']) ? $m['f_valor'] : $f_emision;
        $tarj_marca      = !empty($m['tarjeta_marca']) ? trim($m['tarjeta_marca']) : null;
        $tarj_cupon      = !empty($m['tarjeta_cupon']) ? trim($m['tarjeta_cupon']) : null;
        $imp_tipo_id     = !empty($m['impuesto_tipo_id']) ? intval($m['impuesto_tipo_id']) : null;
        $jur_id          = !empty($m['jurisdiccion_id']) ? intval($m['jurisdiccion_id']) : null;
        $ret_cert        = !empty($m['retencion_certificado_nro']) ? trim($m['retencion_certificado_nro']) : null;
        $ret_base        = !empty($m['retencion_base_imponible']) ? floatval($m['retencion_base_imponible']) : null;
        $ret_ali         = !empty($m['retencion_alicuota']) ? floatval($m['retencion_alicuota']) : null;
        $obs_m           = !empty($m['observaciones']) ? trim($m['observaciones']) : null;
        $cheque_id       = !empty($m['cheque_id']) ? intval($m['cheque_id']) : null;

        // Si es cheque de tercero y vienen los datos del cheque, registrarlo en gestion__cheques
        if (!empty($m['cheque']) && $comprobante_id > 0) {
            $chq_banco_id = intval($m['cheque']['banco_id'] ?? 1);
            $chq_numero   = trim($m['cheque']['numero'] ?? '');
            $chq_f_pago   = !empty($m['cheque']['f_pago']) ? $m['cheque']['f_pago'] : $f_emision;
            $chq_librador = !empty($m['cheque']['librador_nombre']) ? trim($m['cheque']['librador_nombre']) : null;
            $chq_cuit     = !empty($m['cheque']['librador_cuit']) ? intval($m['cheque']['librador_cuit']) : null;
            $es_dif       = ($chq_f_pago > $f_emision) ? 1 : 0;

            // Cuenta de tesorería para valores en cartera de la empresa
            $res_v = $conexion->query("SELECT tesoreria_cuenta_id FROM gestion__tesoreria_cuentas WHERE empresa_id = $empresa_id AND cuenta_tipo = 'VALORES' LIMIT 1");
            $chq_tes_cuenta_id = intval($res_v->fetch_assoc()['tesoreria_cuenta_id'] ?? 0);
            if ($chq_tes_cuenta_id <= 0) {
                throw new Exception('La empresa no tiene una cuenta de tesorería de Valores en cartera para registrar el cheque.');
            }

            $sql_ins_chq = "INSERT INTO gestion__cheques (
                                empresa_id, sucursal_id, cheque_tipo, es_echeq,
                                banco_id, tesoreria_cuenta_id, numero, f_emision,
                                f_pago, es_diferido, moneda_id, importe,
                                librador_nombre, librador_cuit, entidad_id, endosable,
                                comp_origen_id, clave_tercero_empresa_id,
                                tabla_estado_registro_id, usuario_id
                            ) VALUES (?, ?, 'TERCERO', 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, 3, ?)";
            $stmt_chq = mysqli_prepare($conexion, $sql_ins_chq);
            if ($stmt_chq) {
                mysqli_stmt_bind_param(
                    $stmt_chq, "iiiisssiidsiiiii",
                    $empresa_id, $sucursal_id, $chq_banco_id, $chq_tes_cuenta_id,
                    $chq_numero, $f_emision, $chq_f_pago, $es_dif,
                    $mon_id, $imp_comp, $chq_librador, $chq_cuit,
                    $entidad_id, $comprobante_id, $empresa_id, $usuario_id
                );
                mysqli_stmt_execute($stmt_chq);
                $cheque_id = mysqli_insert_id($conexion);
                mysqli_stmt_close($stmt_chq);
            }
        }

        mysqli_stmt_bind_param(
            $stmt_med, "iiiiidddissssiisddsi",
            $cobranza_id, $empresa_id, $medio_pago_id, $tes_cuenta_id,
            $mon_id, $tc, $imp, $imp_comp,
            $cheque_id, $nro_op, $f_val, $tarj_marca, $tarj_cupon,
            $imp_tipo_id, $jur_id, $ret_cert, $ret_base,
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

    return $cobranza_id;
}

/**
 * Confirmación Transaccional de Cobranza (§2.5 / RT01 - RT21 / D27 - D36)
 */
// $en_transaccion = true: corre dentro de la transacción del llamador (recibo automático al confirmar una
// factura de contado o al aplicar notas de crédito); no abre, confirma ni revierte la suya.
function confirmarCobranza($conexion, $cobranza_id, $empresa_id, $usuario_id, $en_transaccion = false)
{
    $cobranza_id = intval($cobranza_id);
    $empresa_id  = intval($empresa_id);
    $usuario_id  = intval($usuario_id);

    if (!$en_transaccion) {
        mysqli_begin_transaction($conexion);
    }

    try {
        // 1. Transición optimista de la operativa (RT04)
        $sql_opt = "UPDATE gestion__cobranzas 
                    SET tabla_estado_registro_id = ?, usuario_modificacion_id = ?, fecha_modificacion = NOW()
                    WHERE cobranza_id = ? AND empresa_id = ? AND tabla_estado_registro_id = ?";
        $stmt_opt = mysqli_prepare($conexion, $sql_opt);
        $est_conf = ESTADO_COBRANZA_CONFIRMADO;
        $est_borr = ESTADO_COBRANZA_BORRADOR;
        mysqli_stmt_bind_param($stmt_opt, "iiiii", $est_conf, $usuario_id, $cobranza_id, $empresa_id, $est_borr);
        mysqli_stmt_execute($stmt_opt);
        $afectadas = mysqli_stmt_affected_rows($stmt_opt);
        mysqli_stmt_close($stmt_opt);

        if ($afectadas !== 1) {
            throw new Exception("El documento ya no está en estado Borrador o fue modificado por otro usuario (RT04).");
        }

        // Obtener datos completos de la cobranza
        $cob_info = obtenerCobranzaDetalle($conexion, $cobranza_id, $empresa_id);
        if (!$cob_info) throw new Exception("No se pudo obtener el detalle de la cobranza.");

        $cab = $cob_info['cabecera'];
        $medios = $cob_info['medios'];
        $comp_origen_id = intval($cab['comprobante_id']);
        $importe_total = floatval($cab['importe_total']);

        if (file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
            require_once __DIR__ . '/../../core/accesos_sucursales.php';
            AccesosSucursalesHelper::exigirAccesoSucursal($conexion, $empresa_id, intval($cab['sucursal_id']), $usuario_id);
        }

        // 2. Lock del comprobante origen y de todos los destinos en orden ascendente (RT16)
        $destinos_ids = [];
        foreach ($cob_info['imputaciones'] as $imp) {
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
        $comps_lock = [];
        while ($cl = mysqli_fetch_assoc($res_l)) {
            $comps_lock[intval($cl['comprobante_id'])] = $cl;
        }
        mysqli_stmt_close($stmt_l);

        // 3. Confirmar Imputaciones e Impactos en Saldos
        $total_imputado = 0.0;
        foreach ($cob_info['imputaciones'] as $imp) {
            $imp_id = intval($imp['imputacion_id']);
            $dest_id = intval($imp['comp_destino_id']);
            $imp_monto = floatval($imp['importe_imputado']);

            if (!isset($comps_lock[$dest_id])) {
                throw new Exception("Comprobante destino ID $dest_id no encontrado bajo lock.");
            }
            $dest_actual = $comps_lock[$dest_id];
            $pend_actual = floatval($dest_actual['importe_pendiente']);

            if ($imp_monto > round($pend_actual + 0.001, 2)) {
                throw new Exception("Sobre-imputación rechazada en comprobante {$dest_actual['comprobante_id']}: importe requerido ($" . number_format($imp_monto, 2) . ") supera saldo pendiente ($" . number_format($pend_actual, 2) . ").");
            }

            // Actualizar imputación a CONFIRMADA (5)
            $sql_upd_imp = "UPDATE gestion__comprobantes_imputaciones 
                            SET tabla_estado_registro_id = 5, usuario_modificacion_id = ?, fecha_modificacion = NOW()
                            WHERE imputacion_id = ?";
            $stmt_ui = mysqli_prepare($conexion, $sql_upd_imp);
            mysqli_stmt_bind_param($stmt_ui, "ii", $usuario_id, $imp_id);
            mysqli_stmt_execute($stmt_ui);
            mysqli_stmt_close($stmt_ui);

            // Descontar saldo con guarda en el destino (D2)
            $sql_desc = "UPDATE gestion__comprobantes 
                         SET importe_pendiente = importe_pendiente - ?, usuario_modificacion_id = ?, fecha_modificacion = NOW()
                         WHERE comprobante_id = ? AND empresa_id = ? AND importe_pendiente >= ?";
            $stmt_desc = mysqli_prepare($conexion, $sql_desc);
            mysqli_stmt_bind_param($stmt_desc, "diiid", $imp_monto, $usuario_id, $dest_id, $empresa_id, $imp_monto);
            mysqli_stmt_execute($stmt_desc);
            if (mysqli_stmt_affected_rows($stmt_desc) !== 1) {
                throw new Exception("Fallo al actualizar saldo de comprobante destino $dest_id (guarda violada).");
            }
            mysqli_stmt_close($stmt_desc);

            // Recalcular estado derivado del destino (D8 / §3.4)
            recalcularEstadoDestino($conexion, $dest_id, $usuario_id);

            $total_imputado += $imp_monto;
        }

        // Remanente queda como saldo a cuenta / anticipo en el RCX (D6)
        $saldo_rcx = max(0.0, round($importe_total - $total_imputado, 2));

        // 4. Numeración atómica con FOR UPDATE (RT21 / D29)
        $punto_venta_id = intval($cab['comprobante_pv']);
        $comprobante_tipo_id = intval($cab['comprobante_tipo_id'] ?? 0);
        if ($comprobante_tipo_id <= 0) {
            $comprobante_tipo_id = TIPO_COMPROBANTE_RCX_ID;
        }

        $sql_num = "SELECT numerador_id, ultimo_numero 
                    FROM gestion__comprobantes_numeradores 
                    WHERE empresa_id = ? AND punto_venta_id = ? AND comprobante_tipo_id = ?
                    FOR UPDATE";
        $stmt_num = mysqli_prepare($conexion, $sql_num);
        mysqli_stmt_bind_param($stmt_num, "iii", $empresa_id, $punto_venta_id, $comprobante_tipo_id);
        mysqli_stmt_execute($stmt_num);
        $res_num = mysqli_stmt_get_result($stmt_num);
        $row_num = mysqli_fetch_assoc($res_num);
        mysqli_stmt_close($stmt_num);

        if (!$row_num) {
            $sql_ins_num = "INSERT INTO gestion__comprobantes_numeradores (empresa_id, punto_venta_id, comprobante_tipo_id, ultimo_numero) VALUES (?, ?, ?, 0)";
            $stmt_in = mysqli_prepare($conexion, $sql_ins_num);
            mysqli_stmt_bind_param($stmt_in, "iii", $empresa_id, $punto_venta_id, $comprobante_tipo_id);
            mysqli_stmt_execute($stmt_in);
            $num_id = mysqli_insert_id($conexion);
            mysqli_stmt_close($stmt_in);
            $row_num = ['numerador_id' => $num_id, 'ultimo_numero' => 0];
        }
        // Proteger contra desincronización con comprobantes o cobranzas existentes
        $res_max_comp = $conexion->query("SELECT MAX(comprobante_nro) AS max_nro FROM gestion__comprobantes WHERE empresa_id = $empresa_id AND comprobante_pv = $punto_venta_id AND comprobante_tipo_id = $comprobante_tipo_id");
        $max_comp_nro = intval($res_max_comp->fetch_assoc()['max_nro'] ?? 0);

        $res_max_cob = $conexion->query("SELECT MAX(comprobante_nro) AS max_nro FROM gestion__cobranzas WHERE empresa_id = $empresa_id AND comprobante_pv = $punto_venta_id AND comprobante_tipo_id = $comprobante_tipo_id");
        $max_cob_nro = intval($res_max_cob->fetch_assoc()['max_nro'] ?? 0);

        $ultimo_existente = max(intval($row_num['ultimo_numero']), $max_comp_nro, $max_cob_nro);
        $proximo_nro = $ultimo_existente + 1;

        // Actualizar numerador
        $stmt_upd_n = mysqli_prepare($conexion, "UPDATE gestion__comprobantes_numeradores SET ultimo_numero = ? WHERE numerador_id = ?");
        mysqli_stmt_bind_param($stmt_upd_n, "ii", $proximo_nro, $row_num['numerador_id']);
        mysqli_stmt_execute($stmt_upd_n);
        mysqli_stmt_close($stmt_upd_n);

        // Asignar número a la cobranza y al comprobante
        $stmt_upd_cn = mysqli_prepare($conexion, "UPDATE gestion__cobranzas SET comprobante_nro = ? WHERE cobranza_id = ?");
        mysqli_stmt_bind_param($stmt_upd_cn, "ii", $proximo_nro, $cobranza_id);
        mysqli_stmt_execute($stmt_upd_cn);
        mysqli_stmt_close($stmt_upd_cn);

        $stmt_upd_compn = mysqli_prepare($conexion, "UPDATE gestion__comprobantes SET comprobante_nro = ?, importe_pendiente = ?, tabla_estado_registro_id = ? WHERE comprobante_id = ?");
        mysqli_stmt_bind_param($stmt_upd_compn, "idii", $proximo_nro, $saldo_rcx, $est_conf, $comp_origen_id);
        mysqli_stmt_execute($stmt_upd_compn);
        mysqli_stmt_close($stmt_upd_compn);

        // 4b. Pasar cheques de terceros asociados a estado EN_CARTERA (29)
        $conexion->query("UPDATE gestion__cheques SET tabla_estado_registro_id = 29, usuario_modificacion_id = $usuario_id WHERE comp_origen_id = $comp_origen_id");

        // 5. Movimientos en Libro de Tesorería (D4 / D15 / §4.6)
        $cuentas_afectadas = [];
        foreach ($medios as $m) {
            if (!empty($m['tesoreria_cuenta_id'])) {
                $cuentas_afectadas[] = intval($m['tesoreria_cuenta_id']);
            }
        }
        $cuentas_afectadas = array_unique($cuentas_afectadas);
        sort($cuentas_afectadas); // Lock ordenado de cuentas (RT20)

        // Registrar cada movimiento de ingreso en tesorería
        foreach ($medios as &$m) {
            $cta_id = !empty($m['tesoreria_cuenta_id']) ? intval($m['tesoreria_cuenta_id']) : null;
            $monto_m = floatval($m['importe_comprobante']);

            // Si es cheque de tercero y no tiene cuenta explícita, va a la cuenta de Valores en Cartera (D4/D15)
            if ($m['medio_pago_tipo'] === 'CHEQUE_TERCERO' && empty($cta_id)) {
                $res_val = $conexion->query("SELECT tesoreria_cuenta_id, cont_cuenta_id FROM gestion__tesoreria_cuentas WHERE empresa_id = $empresa_id AND cuenta_tipo = 'VALORES' LIMIT 1");
                if ($row_val = $res_val->fetch_assoc()) {
                    $cta_id = intval($row_val['tesoreria_cuenta_id']);
                    $m['tesoreria_cuenta_id'] = $cta_id;
                    $m['cont_cuenta_id_valores'] = intval($row_val['cont_cuenta_id']);
                }
            }

            // Si es EFECTIVO y no tiene cuenta, va a la caja única de la sucursal de la cobranza
            if ($m['medio_pago_tipo'] === 'EFECTIVO' && empty($cta_id)) {
                $suc_id = intval($cab['sucursal_id']);
                $res_caja = $conexion->query("SELECT tesoreria_cuenta_id, cont_cuenta_id FROM gestion__tesoreria_cuentas WHERE empresa_id = $empresa_id AND sucursal_id = $suc_id AND cuenta_tipo = 'CAJA' AND tabla_estado_registro_id = 1 LIMIT 1");
                if ($row_caja = $res_caja->fetch_assoc()) {
                    $cta_id = intval($row_caja['tesoreria_cuenta_id']);
                    $m['tesoreria_cuenta_id'] = $cta_id;
                }
            }


            if ($cta_id > 0) {
                $concepto_mov = "Cobranza Recibo " . emp_numero_comprobante($conexion, $empresa_id, $punto_venta_id, $proximo_nro) . " - " . ($cab['entidad_nombre'] ?? 'Cliente');

                tesoreriaRegistrarMovimiento($conexion, [
                    'empresa_id'          => $empresa_id,
                    'sucursal_id'         => intval($cab['sucursal_id']),
                    'tesoreria_cuenta_id' => $cta_id,
                    'comprobante_id'      => $comp_origen_id,
                    'tipo_movimiento'     => 'INGRESO',
                    'importe'             => $monto_m,
                    'f_movimiento'        => $cab['f_emision'],
                    'f_valor'             => $m['f_valor'] ?? $cab['f_emision'],
                    'moneda_id'           => emp_moneda_id($conexion, $empresa_id, $cab['moneda_id'] ?? 0),
                    'tipo_cambio'         => floatval($cab['tipo_cambio'] ?? 1.0),
                    'medio_pago_id'       => intval($m['medio_pago_id']),
                    'concepto'            => $concepto_mov,
                    'tabla_origen_id'     => TABLA_COBRANZAS_ID,
                    'registro_origen_id'  => $cobranza_id,
                    'usuario_id'          => $usuario_id
                ]);
            }
        }
        unset($m);

        // 6. Generar Asiento Contable Automático (D27 / D36 / Anexo D)
        if ($total_debe > 0.0001) {
            cobranzaGenerarAsientoContable($conexion, $cobranza_id, $empresa_id, $usuario_id);
        }

        if (!$en_transaccion) {
            mysqli_commit($conexion);
        }

        return [
            'success'         => true,
            'comprobante_nro' => $proximo_nro,
            'comprobante_id'  => $comp_origen_id,
            'message'         => "Cobranza confirmada con éxito. Recibo N° " . emp_numero_comprobante($conexion, $empresa_id, $punto_venta_id, $proximo_nro)
        ];

    } catch (Exception $e) {
        if (!$en_transaccion) {
            mysqli_rollback($conexion);
        }
        error_log("Error confirmando cobranza $cobranza_id: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Anulación de Cobranza (§2.6 / RT08 / C7 / D25)
 */
function anularCobranza($conexion, $cobranza_id, $empresa_id, $usuario_id, $motivo = '')
{
    $cobranza_id = intval($cobranza_id);
    $empresa_id  = intval($empresa_id);
    $usuario_id  = intval($usuario_id);
    $motivo      = trim($motivo);

    if (empty($motivo)) {
        throw new Exception("Debe ingresar un motivo de anulación.");
    }

    mysqli_begin_transaction($conexion);

    try {
        // Lock de la cobranza
        $sql = "SELECT c.*, comp.comprobante_id
                FROM gestion__cobranzas c
                JOIN gestion__comprobantes comp ON comp.comprobante_id = c.comprobante_id
                WHERE c.cobranza_id = ? AND c.empresa_id = ?
                FOR UPDATE";
        $stmt = mysqli_prepare($conexion, $sql);
        mysqli_stmt_bind_param($stmt, "ii", $cobranza_id, $empresa_id);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        $cob = mysqli_fetch_assoc($res);
        mysqli_stmt_close($stmt);

        if (!$cob) throw new Exception("Cobranza no encontrada.");
        if (intval($cob['tabla_estado_registro_id']) !== ESTADO_COBRANZA_CONFIRMADO) {
            throw new Exception("Solo se pueden anular cobranzas en estado Confirmado.");
        }

        $comp_id = intval($cob['comprobante_id']);

        // Control C7 / RT08: Si recibió cheques, verificar que sigan EN_CARTERA
        $sql_chq = "SELECT chq.cheque_id, chq.numero, er.codigo_estandar AS estado_cheque
                    FROM gestion__cheques chq
                    JOIN conf__estados_registros er ON er.estado_registro_id = chq.tabla_estado_registro_id
                    WHERE chq.comp_origen_id = ?";
        $stmt_ch = mysqli_prepare($conexion, $sql_chq);
        mysqli_stmt_bind_param($stmt_ch, "i", $comp_id);
        mysqli_stmt_execute($stmt_ch);
        $res_ch = mysqli_stmt_get_result($stmt_ch);
        while ($ch = mysqli_fetch_assoc($res_ch)) {
            if ($ch['estado_cheque'] !== 'EN_CARTERA') {
                throw new Exception("No se puede anular la cobranza porque el cheque N° {$ch['numero']} ya no está en cartera (estado actual: {$ch['estado_cheque']}). Primero debe revertirse la operación del cheque.");
            }
        }
        mysqli_stmt_close($stmt_ch);

        // 1. Transición de estado a ANULADO
        $sql_upd = "UPDATE gestion__cobranzas 
                    SET tabla_estado_registro_id = ?, motivo_anulacion = ?, usuario_modificacion_id = ?, fecha_modificacion = NOW()
                    WHERE cobranza_id = ?";
        $stmt_u = mysqli_prepare($conexion, $sql_upd);
        $est_anul = ESTADO_COBRANZA_ANULADO;
        mysqli_stmt_bind_param($stmt_u, "isii", $est_anul, $motivo, $usuario_id, $cobranza_id);
        mysqli_stmt_execute($stmt_u);
        mysqli_stmt_close($stmt_u);

        // 2. Anular imputaciones vigentes (§2.6)
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

        // 4. Anular cheques asociados
        $conexion->query("UPDATE gestion__cheques SET tabla_estado_registro_id = 38, usuario_modificacion_id = $usuario_id WHERE comp_origen_id = $comp_id");

        // 5. Anular comprobante RCX
        $conexion->query("UPDATE gestion__comprobantes SET tabla_estado_registro_id = $est_anul, importe_pendiente = 0, usuario_modificacion_id = $usuario_id WHERE comprobante_id = $comp_id");

        // 6. Anular asiento contable asociado
        $conexion->query("UPDATE gestion__cont_asientos SET tabla_estado_registro_id = 2, usuario_modificacion_id = $usuario_id WHERE comprobante_id = $comp_id");

        mysqli_commit($conexion);

        return [
            'success' => true,
            'message' => "Cobranza anulada exitosamente y saldos revertidos."
        ];

    } catch (Exception $e) {
        mysqli_rollback($conexion);
        error_log("Error anulando cobranza $cobranza_id: " . $e->getMessage());
        throw $e;
    }
}

/**
 * Elimina un borrador de cobranza que nunca fue confirmado
 */
function eliminarCobranzaBorrador($conexion, $cobranza_id, $empresa_id, $usuario_id)
{
    $cobranza_id = intval($cobranza_id);
    $empresa_id  = intval($empresa_id);

    $sql = "SELECT c.cobranza_id, c.comprobante_id, c.tabla_estado_registro_id
            FROM gestion__cobranzas c
            WHERE c.cobranza_id = ? AND c.empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $cobranza_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $cob = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if (!$cob) throw new Exception("Cobranza no encontrada.");
    if (intval($cob['tabla_estado_registro_id']) !== ESTADO_COBRANZA_BORRADOR) {
        throw new Exception("Solo se pueden eliminar cobranzas en estado Borrador.");
    }

    $comp_id = intval($cob['comprobante_id']);

    mysqli_begin_transaction($conexion);
    try {
        $conexion->query("DELETE FROM gestion__cobranzas_medios WHERE cobranza_id = $cobranza_id");
        if ($comp_id > 0) {
            $conexion->query("DELETE FROM gestion__comprobantes_imputaciones WHERE comp_origen_id = $comp_id");
            $conexion->query("DELETE FROM gestion__comprobantes WHERE comprobante_id = $comp_id");
        }
        $conexion->query("DELETE FROM gestion__cobranzas WHERE cobranza_id = $cobranza_id");
        mysqli_commit($conexion);
        return ['success' => true, 'message' => "Borrador de cobranza eliminado."];
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        throw $e;
    }
}

/**
 * Obtiene los clientes de la empresa
 */
function obtenerClientesCobranza($conexion, $empresa_id)
{
    $empresa_id = intval($empresa_id);
    // Incluye proveedores: cobro de la devolución de dinero de una nota de crédito de proveedor
    $sql = "SELECT entidad_id, entidad_nombre, cuit, domicilio_legal, es_cliente, es_proveedor
            FROM gestion__entidades
            WHERE empresa_id = ? AND (es_cliente = 1 OR es_proveedor = 1) AND tabla_estado_registro_id = 1
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
 * Obtiene los medios de pago disponibles para cobranzas
 */
function obtenerMediosPagoDisponibles($conexion, $empresa_id = null)
{
    $empresa_id = intval($empresa_id ?? ($_SESSION['empresa_id'] ?? 0));
    $sql = "SELECT medio_pago_id, codigo, medio_pago, medio_pago_tipo, cont_cuenta_id, impuesto_tipo_id
            FROM gestion__medios_pago
            WHERE empresa_id = $empresa_id
              AND tabla_estado_registro_id = 1
              AND aplica_cobro = 1
              AND medio_pago_tipo != 'CHEQUE_PROPIO'
            ORDER BY medio_pago_id ASC";
    $res = $conexion->query($sql);
    $rows = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    return $rows;
}

/**
 * Obtiene las cuentas de tesorería (cajas y bancos) de la empresa
 */
function obtenerCuentasTesoreriaEmpresa($conexion, $empresa_id)
{
    $empresa_id = intval($empresa_id);
    $sql = "SELECT tc.tesoreria_cuenta_id, tc.sucursal_id, tc.tesoreria_cuenta,
                   tc.cuenta_tipo, tc.moneda_id, tc.cont_cuenta_id, m.simbolo AS moneda_simbolo
            FROM gestion__tesoreria_cuentas tc
            JOIN gestion__monedas m ON m.moneda_id = tc.moneda_id
            WHERE tc.empresa_id = ? AND tc.tabla_estado_registro_id = 1
            ORDER BY tc.cuenta_tipo ASC, tc.tesoreria_cuenta ASC";
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
 * Obtiene bancos disponibles para cheques
 */
function obtenerBancosDisponibles($conexion)
{
    $sql = "SELECT banco_id, banco FROM conf__bancos ORDER BY banco ASC";
    $res = $conexion->query($sql);
    $rows = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    return $rows;
}

/**
 * Obtiene tipos de impuestos de retención.
 * $sentido: 'COBRO' = sufridas (aplica_venta), 'PAGO' = practicadas (aplica_compra), null = todas.
 */
function obtenerImpuestosRetencion($conexion, $sentido = null)
{
    $filtro = '';
    if ($sentido === 'COBRO') {
        $filtro = ' AND aplica_venta = 1';
    } elseif ($sentido === 'PAGO') {
        $filtro = ' AND aplica_compra = 1';
    }
    $sql = "SELECT impuesto_tipo_id, codigo_afip, impuesto_tipo
            FROM gestion__impuestos_tipos
            WHERE tabla_estado_registro_id = 1 AND es_retencion = 1 $filtro
            ORDER BY impuesto_tipo ASC";
    $res = $conexion->query($sql);
    $rows = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $r['es_iibb'] = cobImpuestoEsIibb($r['impuesto_tipo']) ? 1 : 0;
            $rows[] = $r;
        }
    }
    return $rows;
}

/**
 * Obtiene las jurisdicciones provinciales de Ingresos Brutos (IIBB) para retenciones sufridas
 */
function obtenerJurisdicciones($conexion)
{
    $sql = "SELECT jurisdiccion_id, jurisdiccion_codigo, jurisdiccion_nombre, organismo_recaudador
            FROM gestion__jurisdicciones
            WHERE tabla_estado_registro_id = 1 AND jurisdiccion_tipo_id = 2
            ORDER BY orden ASC, jurisdiccion_nombre ASC";
    $res = $conexion->query($sql);
    $rows = [];
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = $r;
        }
    }
    return $rows;
}

/**
 * Obtiene sucursales de la empresa
 */
function obtenerSucursalesEmpresa($conexion, $empresa_id)
{
    $empresa_id = intval($empresa_id);
    $sql = "SELECT sucursal_id, sucursal_nombre
            FROM gestion__sucursales
            WHERE empresa_id = ? AND tabla_estado_registro_id = 1
            ORDER BY sucursal_id ASC";
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

    if (file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
        require_once __DIR__ . '/../../core/accesos_sucursales.php';
        $rows = AccesosSucursalesHelper::filtrarSucursales($conexion, $empresa_id, $rows);
    }

    return $rows;
}

/**
 * Obtiene los puntos de venta habilitados para los comprobantes de la página (gestion__cobranzas)
 */
function obtenerPuntosVentaCobranza($conexion, $empresa_id, $pagina_id = 105)
{
    $empresa_id = intval($empresa_id);
    $pagina_id  = intval($pagina_id);

    $stmt_p = mysqli_prepare($conexion, "SELECT tabla_id FROM conf__paginas WHERE pagina_id = ?");
    mysqli_stmt_bind_param($stmt_p, 'i', $pagina_id);
    mysqli_stmt_execute($stmt_p);
    $pag_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_p));
    mysqli_stmt_close($stmt_p);
    $tabla_id = intval($pag_row['tabla_id'] ?? 275);

    $sql = "SELECT DISTINCT pv.punto_venta_id, pv.sucursal_id, pv.boca_id, pv.nombre, pv.codigo_fiscal,
                   s.sucursal_nombre, b.boca_nombre
            FROM gestion__puntos_venta pv
            LEFT JOIN gestion__sucursales s ON s.sucursal_id = pv.sucursal_id
            LEFT JOIN gestion__bocas b ON b.boca_id = pv.boca_id
            INNER JOIN gestion__puntos_venta_comprobantes pvc 
                    ON pvc.punto_venta_id = pv.punto_venta_id 
                   AND pvc.empresa_id = pv.empresa_id 
                   AND pvc.tabla_estado_registro_id = 1
            INNER JOIN gestion__comprobantes_tipos ct
                    ON ct.comprobante_tipo_id = pvc.comprobante_tipo_id
                   AND ct.tabla_estado_registro_id = 1
            INNER JOIN gestion__comprobantes_subgrupos cs
                    ON cs.comprobante_subgrupo_id = ct.comprobante_subgrupo_id
                   AND cs.tabla_estado_registro_id = 1
                   AND cs.tabla_id = ?
            WHERE pv.empresa_id = ? AND pv.tabla_estado_registro_id = 1
            ORDER BY s.sucursal_nombre, b.boca_nombre, pv.nombre";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, "ii", $tabla_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $rows[] = $r;
    }
    mysqli_stmt_close($stmt);

    if (file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
        require_once __DIR__ . '/../../core/accesos_sucursales.php';
        $rows = AccesosSucursalesHelper::filtrarPuntosVenta($conexion, $empresa_id, $rows);
    }

    return $rows;
}

/**
 * Obtiene los tipos de comprobante habilitados para un punto de venta según la tabla de la página
 * (idéntico al criterio de ventas_facturas.php / vfTiposPorPuntoVenta)
 */
function obtenerTiposComprobanteCobranza($conexion, $empresa_id, $punto_venta_id, $pagina_id = 105)
{
    $empresa_id     = intval($empresa_id);
    $punto_venta_id = intval($punto_venta_id);
    $pagina_id      = intval($pagina_id);

    if (empty($punto_venta_id)) return [];

    $stmt = mysqli_prepare($conexion, "SELECT tabla_id FROM conf__paginas WHERE pagina_id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $pagina_id);
    mysqli_stmt_execute($stmt);
    $pagina = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $tabla_id = intval($pagina['tabla_id'] ?? 0);
    if (empty($tabla_id)) {
        error_log("obtenerTiposComprobanteCobranza: sin tabla_id configurado para pagina_id=$pagina_id");
        return [];
    }

    $sql = "SELECT ct.comprobante_tipo_id, ct.comprobante_tipo, ct.letra, ct.codigo,
                   ct.comprobante_fiscal_id
            FROM gestion__comprobantes_tipos ct
            INNER JOIN gestion__comprobantes_subgrupos cs
                ON cs.comprobante_subgrupo_id = ct.comprobante_subgrupo_id
                AND cs.tabla_estado_registro_id = 1
                AND cs.tabla_id = ?
                AND cs.empresa_id IN (0, ?)
            INNER JOIN gestion__puntos_venta_comprobantes pvc
                ON pvc.comprobante_tipo_id = ct.comprobante_tipo_id
                AND pvc.punto_venta_id = ?
                AND pvc.empresa_id = ?
                AND pvc.tabla_estado_registro_id = 1
            WHERE ct.tabla_estado_registro_id = 1
              AND ct.empresa_id IN (0, ?)
            ORDER BY ct.orden, ct.comprobante_tipo";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_bind_param($stmt, 'iiiii', $tabla_id, $empresa_id, $punto_venta_id, $empresa_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }
    mysqli_stmt_close($stmt);
    return $rows;
}


