<?php
/**
 * core/contratos/contratos_service.php
 * 
 * Lógica central y servicios de negocio para Contratos, Órdenes de Compra de Servicios
 * y Certificaciones de Avance. Compartido entre gestion y gestion_normal.
 * 
 * Diseño técnico: _local/docs/gestion/16_contratos_y_certificaciones_avance.md
 */

// Estados genéricos de conf__estados_registros (catálogo de la tabla 458 en conf__tablas_estados_registros)
if (!defined('CERT_ESTADO_BORRADOR')) define('CERT_ESTADO_BORRADOR', 3);
if (!defined('CERT_ESTADO_REVISION')) define('CERT_ESTADO_REVISION', 4);
if (!defined('CERT_ESTADO_APROBADO')) define('CERT_ESTADO_APROBADO', 5);
if (!defined('CERT_ESTADO_FACTURADO')) define('CERT_ESTADO_FACTURADO', 12);
if (!defined('CERT_ESTADO_ELIMINADO')) define('CERT_ESTADO_ELIMINADO', 6);
if (!defined('CERT_ESTADO_ANULADO')) define('CERT_ESTADO_ANULADO', 38);

/**
 * Obtiene el resumen integral de seguimiento de un contrato:
 * - Métricas de vigencia y plazos de preaviso
 * - Avance físico certificado vs avance financiero facturado
 * - Saldos disponibles y fondo de reparo acumulado
 */
function contratoObtenerResumenSeguimiento($conexion, $orden_compra_id, $empresa_id)
{
    $orden_compra_id = intval($orden_compra_id);
    $empresa_id = intval($empresa_id);

    $sql = "SELECT oc.*, 
                   e.entidad_nombre, e.entidad_fantasia, e.cuit,
                   m.simbolo AS moneda_simbolo,
                   u.usuario_nombre AS responsable_tecnico_nombre
            FROM gestion__ordenes_compra oc
            LEFT JOIN gestion__entidades e ON oc.entidad_id = e.entidad_id
            LEFT JOIN gestion__monedas m ON oc.moneda_id = m.moneda_id
            LEFT JOIN conf__usuarios u ON oc.responsable_tecnico_id = u.usuario_id
            WHERE oc.orden_compra_id = ? AND oc.empresa_id = ?";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return null;

    mysqli_stmt_bind_param($stmt, "ii", $orden_compra_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $contrato = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);

    if (!$contrato) return null;

    $monto_total = floatval($contrato['total'] ?? 0);

    // 1. Métricas de vigencia
    $hoy = new DateTime();
    $dias_restantes = null;
    $en_ventana_preaviso = false;
    $esta_vencido = false;

    if (!empty($contrato['fecha_fin'])) {
        $fecha_fin = new DateTime($contrato['fecha_fin']);
        $diff = $hoy->diff($fecha_fin);
        $dias_restantes = (int)$diff->format('%r%a'); // negativo si ya pasó
        $dias_preaviso = intval($contrato['dias_preaviso_rescision'] ?? 30);

        if ($dias_restantes < 0) {
            $esta_vencido = true;
        } elseif ($dias_restantes <= $dias_preaviso) {
            $en_ventana_preaviso = true;
        }
    }

    // 2. Acumulados de Certificaciones aprobadas y emitidas
    $sql_cert = "SELECT 
                    COUNT(*) AS total_certificados,
                    COALESCE(MAX(avance_acumulado_pct), 0) AS max_avance_pct,
                    COALESCE(SUM(CASE WHEN tabla_estado_registro_id IN (" . CERT_ESTADO_APROBADO . ", " . CERT_ESTADO_FACTURADO . ") THEN monto_bruto_periodo ELSE 0 END), 0) AS total_bruto_certificado,
                    COALESCE(SUM(CASE WHEN tabla_estado_registro_id IN (" . CERT_ESTADO_APROBADO . ", " . CERT_ESTADO_FACTURADO . ") THEN descuento_anticipo ELSE 0 END), 0) AS total_anticipo_amortizado,
                    COALESCE(SUM(CASE WHEN tabla_estado_registro_id IN (" . CERT_ESTADO_APROBADO . ", " . CERT_ESTADO_FACTURADO . ") THEN fondo_reparo_retenido ELSE 0 END), 0) AS total_fondo_reparo_retenido,
                    COALESCE(SUM(CASE WHEN tabla_estado_registro_id IN (" . CERT_ESTADO_APROBADO . ", " . CERT_ESTADO_FACTURADO . ") THEN monto_neto_facturable ELSE 0 END), 0) AS total_neto_certificado,
                    COALESCE(SUM(CASE WHEN tabla_estado_registro_id = " . CERT_ESTADO_APROBADO . " AND factura_proveedor_id IS NULL THEN monto_neto_facturable ELSE 0 END), 0) AS neto_pendiente_facturacion,
                    COALESCE(SUM(CASE WHEN tabla_estado_registro_id = " . CERT_ESTADO_REVISION . " THEN 1 ELSE 0 END), 0) AS cant_en_revision
                 FROM gestion__ordenes_compra_certificaciones
                 WHERE orden_compra_id = ? AND empresa_id = ? AND tabla_estado_registro_id != " . CERT_ESTADO_ELIMINADO;
    $stmt_c = mysqli_prepare($conexion, $sql_cert);
    mysqli_stmt_bind_param($stmt_c, "ii", $orden_compra_id, $empresa_id);
    mysqli_stmt_execute($stmt_c);
    $metricas_cert = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_c));
    mysqli_stmt_close($stmt_c);

    // 3. Acumulados de Facturas de Proveedores confirmadas vinculadas a esta OC
    $sql_fac = "SELECT 
                    COALESCE(SUM(fp.total), 0) AS total_facturado,
                    COUNT(fp.factura_proveedor_id) AS cant_facturas
                FROM gestion__facturas_proveedores fp
                WHERE fp.orden_compra_id = ? AND fp.empresa_id = ?
                  AND fp.tabla_estado_registro_id IN (5, 39, 40)"; // Confirmadas y canceladas
    $stmt_f = mysqli_prepare($conexion, $sql_fac);
    mysqli_stmt_bind_param($stmt_f, "ii", $orden_compra_id, $empresa_id);
    mysqli_stmt_execute($stmt_f);
    $metricas_fac = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_f));
    mysqli_stmt_close($stmt_f);

    $total_facturado = floatval($metricas_fac['total_facturado'] ?? 0);
    $pct_facturado = ($monto_total > 0) ? round(($total_facturado / $monto_total) * 100, 2) : 0;
    $saldo_por_ejecutar = max(0, $monto_total - floatval($metricas_cert['total_bruto_certificado']));

    return [
        'contrato' => $contrato,
        'vigencia' => [
            'dias_restantes' => $dias_restantes,
            'esta_vencido' => $esta_vencido,
            'en_ventana_preaviso' => $en_ventana_preaviso
        ],
        'certificaciones' => [
            'total_certificados' => intval($metricas_cert['total_certificados']),
            'avance_fisico_pct' => floatval($metricas_cert['max_avance_pct']),
            'total_bruto_certificado' => floatval($metricas_cert['total_bruto_certificado']),
            'total_anticipo_amortizado' => floatval($metricas_cert['total_anticipo_amortizado']),
            'total_fondo_reparo_retenido' => floatval($metricas_cert['total_fondo_reparo_retenido']),
            'total_neto_certificado' => floatval($metricas_cert['total_neto_certificado']),
            'neto_pendiente_facturacion' => floatval($metricas_cert['neto_pendiente_facturacion']),
            'cant_en_revision' => intval($metricas_cert['cant_en_revision'])
        ],
        'financiero' => [
            'monto_total_contratado' => $monto_total,
            'total_facturado' => $total_facturado,
            'pct_facturado' => $pct_facturado,
            'cant_facturas' => intval($metricas_fac['cant_facturas']),
            'saldo_por_ejecutar' => $saldo_por_ejecutar
        ]
    ];
}

/**
 * Lista las certificaciones de un contrato ordenadas correlativamente.
 */
function contratoCertificacionesListar($conexion, $orden_compra_id, $empresa_id)
{
    $orden_compra_id = intval($orden_compra_id);
    $empresa_id = intval($empresa_id);

    $sql = "SELECT c.*,
                   u_apr.usuario_nombre AS usuario_aprobacion_nombre,
                   fp.comprobante_pv AS factura_pv,
                   fp.comprobante_nro AS factura_nro,
                   ct.codigo AS factura_tipo_codigo
            FROM gestion__ordenes_compra_certificaciones c
            LEFT JOIN conf__usuarios u_apr ON c.usuario_aprobacion_id = u_apr.usuario_id
            LEFT JOIN gestion__facturas_proveedores fp ON c.factura_proveedor_id = fp.factura_proveedor_id
            LEFT JOIN gestion__comprobantes_tipos ct ON fp.comprobante_tipo_id = ct.comprobante_tipo_id
            WHERE c.orden_compra_id = ? AND c.empresa_id = ? 
              AND c.tabla_estado_registro_id != " . CERT_ESTADO_ELIMINADO . "
            ORDER BY c.nro_certificado ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];

    mysqli_stmt_bind_param($stmt, "ii", $orden_compra_id, $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $listado = [];
    while ($fila = mysqli_fetch_assoc($result)) {
        $listado[] = $fila;
    }
    mysqli_stmt_close($stmt);
    return $listado;
}

/**
 * Crea o actualiza una certificación de avance calculando automáticamente los importes
 * financieros según las cláusulas de la OC/Contrato.
 */
function contratoCertificacionGuardar($conexion, $empresa_id, $usuario_id, $data)
{
    $empresa_id = intval($empresa_id);
    $usuario_id = intval($usuario_id);
    $orden_compra_id = intval($data['orden_compra_id'] ?? 0);
    $certificacion_id = intval($data['certificacion_id'] ?? 0);

    if ($orden_compra_id <= 0) {
        throw new Exception("Orden de compra / contrato obligatorio.");
    }

    // Obtener datos del contrato (monto total y % de anticipo / fondo de reparo)
    $sql_oc = "SELECT total, pct_anticipo_financiero, pct_fondo_reparo, requiere_certificacion_avance 
               FROM gestion__ordenes_compra 
               WHERE orden_compra_id = ? AND empresa_id = ?";
    $stmt_oc = mysqli_prepare($conexion, $sql_oc);
    mysqli_stmt_bind_param($stmt_oc, "ii", $orden_compra_id, $empresa_id);
    mysqli_stmt_execute($stmt_oc);
    $oc = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_oc));
    mysqli_stmt_close($stmt_oc);

    if (!$oc) {
        throw new Exception("El contrato u orden de compra no existe.");
    }

    $total_contrato = floatval($oc['total'] ?? 0);
    $pct_anticipo = floatval($oc['pct_anticipo_financiero'] ?? 0);
    $pct_reparo = floatval($oc['pct_fondo_reparo'] ?? 0);

    $fecha_medicion = !empty($data['fecha_medicion']) ? $data['fecha_medicion'] : date('Y-m-d');
    $periodo_mes = !empty($data['periodo_mes']) ? trim($data['periodo_mes']) : date('Y-m', strtotime($fecha_medicion));
    $observaciones = trim($data['observaciones_tecnicas'] ?? '');
    $archivo_adjunto = trim($data['archivo_adjunto'] ?? '');

    // Avances
    $avance_acumulado_pct = floatval($data['avance_acumulado_pct'] ?? 0);
    if ($avance_acumulado_pct < 0 || $avance_acumulado_pct > 100) {
        throw new Exception("El porcentaje acumulado de avance debe estar entre 0% y 100%.");
    }

    // Obtener último porcentaje acumulado previo aprobado o en trámite
    $sql_prev = "SELECT COALESCE(MAX(avance_acumulado_pct), 0) AS max_prev 
                 FROM gestion__ordenes_compra_certificaciones 
                 WHERE orden_compra_id = ? AND empresa_id = ? 
                   AND certificacion_id != ? AND tabla_estado_registro_id != " . CERT_ESTADO_ELIMINADO;
    $stmt_p = mysqli_prepare($conexion, $sql_prev);
    mysqli_stmt_bind_param($stmt_p, "iii", $orden_compra_id, $empresa_id, $certificacion_id);
    mysqli_stmt_execute($stmt_p);
    $max_prev = floatval(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_p))['max_prev'] ?? 0);
    mysqli_stmt_close($stmt_p);

    if ($avance_acumulado_pct < $max_prev) {
        throw new Exception("El porcentaje acumulado ({$avance_acumulado_pct}%) no puede ser menor al acumulado previo ({$max_prev}%).");
    }

    $avance_periodo_pct = round($avance_acumulado_pct - $max_prev, 2);

    // Cálculos financieros
    if (isset($data['monto_bruto_periodo']) && floatval($data['monto_bruto_periodo']) > 0) {
        $monto_bruto = floatval($data['monto_bruto_periodo']);
    } else {
        $monto_bruto = round($total_contrato * ($avance_periodo_pct / 100), 2);
    }

    $descuento_anticipo = round($monto_bruto * ($pct_anticipo / 100), 2);
    $fondo_reparo = round($monto_bruto * ($pct_reparo / 100), 2);
    $monto_neto = round($monto_bruto - $descuento_anticipo - $fondo_reparo, 2);

    if ($certificacion_id > 0) {
        // Actualizar existente (solo si no está facturado)
        $sql_chk = "SELECT tabla_estado_registro_id, factura_proveedor_id FROM gestion__ordenes_compra_certificaciones WHERE certificacion_id = ? AND empresa_id = ?";
        $stmt_c = mysqli_prepare($conexion, $sql_chk);
        mysqli_stmt_bind_param($stmt_c, "ii", $certificacion_id, $empresa_id);
        mysqli_stmt_execute($stmt_c);
        $act = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_c));
        mysqli_stmt_close($stmt_c);

        if (!$act) throw new Exception("Certificación no encontrada.");
        if (intval($act['tabla_estado_registro_id']) === CERT_ESTADO_FACTURADO || !empty($act['factura_proveedor_id'])) {
            throw new Exception("No se puede editar una certificación que ya fue facturada.");
        }

        $sql_upd = "UPDATE gestion__ordenes_compra_certificaciones
                    SET fecha_medicion = ?, periodo_mes = ?, avance_acumulado_pct = ?, avance_periodo_pct = ?,
                        monto_bruto_periodo = ?, descuento_anticipo = ?, fondo_reparo_retenido = ?, monto_neto_facturable = ?,
                        observaciones_tecnicas = ?, usuario_modificacion_id = ?, fecha_modificacion = NOW()
                    WHERE certificacion_id = ? AND empresa_id = ?";
        $stmt_u = mysqli_prepare($conexion, $sql_upd);
        mysqli_stmt_bind_param($stmt_u, "ssddddddsiii",
            $fecha_medicion, $periodo_mes, $avance_acumulado_pct, $avance_periodo_pct,
            $monto_bruto, $descuento_anticipo, $fondo_reparo, $monto_neto,
            $observaciones, $usuario_id, $certificacion_id, $empresa_id);
        $ok = mysqli_stmt_execute($stmt_u);
        mysqli_stmt_close($stmt_u);
        if (!$ok) throw new Exception("Error al actualizar certificación: " . mysqli_error($conexion));

        return ['resultado' => true, 'certificacion_id' => $certificacion_id, 'monto_neto_facturable' => $monto_neto];
    } else {
        // Asignar siguiente correlativo nro_certificado
        $sql_nro = "SELECT COALESCE(MAX(nro_certificado), 0) + 1 AS proximo 
                    FROM gestion__ordenes_compra_certificaciones 
                    WHERE orden_compra_id = ? FOR UPDATE";
        $stmt_n = mysqli_prepare($conexion, $sql_nro);
        mysqli_stmt_bind_param($stmt_n, "i", $orden_compra_id);
        mysqli_stmt_execute($stmt_n);
        $proximo_nro = intval(mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_n))['proximo'] ?? 1);
        mysqli_stmt_close($stmt_n);

        $estado_inicial = !empty($data['enviar_a_revision']) ? CERT_ESTADO_REVISION : CERT_ESTADO_BORRADOR;

        $sql_ins = "INSERT INTO gestion__ordenes_compra_certificaciones
                    (empresa_id, orden_compra_id, nro_certificado, fecha_medicion, periodo_mes,
                     avance_acumulado_pct, avance_periodo_pct, monto_bruto_periodo, descuento_anticipo,
                     fondo_reparo_retenido, monto_neto_facturable, tabla_estado_registro_id,
                     observaciones_tecnicas, archivo_adjunto, usuario_creacion_id, fecha_creacion)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        $stmt_i = mysqli_prepare($conexion, $sql_ins);
        mysqli_stmt_bind_param($stmt_i, "iiissddddddissi",
            $empresa_id, $orden_compra_id, $proximo_nro, $fecha_medicion, $periodo_mes,
            $avance_acumulado_pct, $avance_periodo_pct, $monto_bruto, $descuento_anticipo,
            $fondo_reparo, $monto_neto, $estado_inicial,
            $observaciones, $archivo_adjunto, $usuario_id);
        $ok = mysqli_stmt_execute($stmt_i);
        $nuevo_id = mysqli_insert_id($conexion);
        mysqli_stmt_close($stmt_i);

        if (!$ok) throw new Exception("Error al insertar certificación: " . mysqli_error($conexion));

        return [
            'resultado' => true,
            'certificacion_id' => $nuevo_id,
            'nro_certificado' => $proximo_nro,
            'monto_bruto' => $monto_bruto,
            'monto_descuento_anticipo' => $descuento_anticipo,
            'monto_fondo_reparo' => $fondo_reparo,
            'monto_neto_facturable' => $monto_neto
        ];
    }
}

/**
 * Aprueba técnicamente un certificado de avance, habilitando su facturación.
 */
function contratoCertificacionAprobar($conexion, $certificacion_id, $usuario_id, $empresa_id)
{
    $certificacion_id = intval($certificacion_id);
    $usuario_id = intval($usuario_id);
    $empresa_id = intval($empresa_id);

    $sql = "UPDATE gestion__ordenes_compra_certificaciones
            SET tabla_estado_registro_id = " . CERT_ESTADO_APROBADO . ",
                usuario_aprobacion_id = ?,
                fecha_aprobacion = NOW(),
                usuario_modificacion_id = ?
            WHERE certificacion_id = ? AND empresa_id = ? 
              AND tabla_estado_registro_id IN (" . CERT_ESTADO_BORRADOR . ", " . CERT_ESTADO_REVISION . ")";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, "iiii", $usuario_id, $usuario_id, $certificacion_id, $empresa_id);
    $ok = mysqli_stmt_execute($stmt);
    $affected = mysqli_stmt_affected_rows($stmt);
    mysqli_stmt_close($stmt);

    if (!$ok || $affected === 0) {
        throw new Exception("No se pudo aprobar la certificación. Verifique que exista y esté en Borrador o En Revisión.");
    }
    return ['resultado' => true, 'message' => 'Certificado aprobado. Ya se encuentra habilitado para facturar.'];
}

/**
 * Certificados de un proveedor aprobados y disponibles para ser imputados a una factura.
 * Utilizado por Facturas de Proveedores (ERP) y por el Portal de Proveedores.
 */
function contratoCertificacionesDisponiblesFacturar($conexion, $entidad_id, $empresa_id, $orden_compra_id = 0)
{
    $entidad_id = intval($entidad_id);
    $empresa_id = intval($empresa_id);
    $orden_compra_id = intval($orden_compra_id);

    $sql = "SELECT c.certificacion_id, c.orden_compra_id, c.nro_certificado, c.periodo_mes,
                   c.avance_acumulado_pct, c.monto_bruto_periodo AS monto_bruto, c.descuento_anticipo AS monto_descuento_anticipo,
                   c.fondo_reparo_retenido AS monto_fondo_reparo, c.monto_neto_facturable, c.fecha_medicion,
                   c.observaciones_tecnicas AS observaciones,
                   oc.comprobante_nro, oc.contrato_nro_legal, oc.total AS contrato_total,
                   m.simbolo AS moneda_simbolo,
                   ocd.producto_id, ocd.iva_alicuota_id, ocd.iva_porcentaje
            FROM gestion__ordenes_compra_certificaciones c
            INNER JOIN gestion__ordenes_compra oc ON c.orden_compra_id = oc.orden_compra_id AND oc.empresa_id = c.empresa_id
            LEFT JOIN gestion__monedas m ON oc.moneda_id = m.moneda_id
            LEFT JOIN gestion__ordenes_compra_detalle ocd
                   ON ocd.ordenes_compra_detalle_id = (SELECT MIN(d.ordenes_compra_detalle_id)
                                                       FROM gestion__ordenes_compra_detalle d
                                                       WHERE d.orden_compra_id = oc.orden_compra_id)
            WHERE oc.entidad_id = ? AND c.empresa_id = ?
              AND c.tabla_estado_registro_id = " . CERT_ESTADO_APROBADO . "
              AND (c.factura_proveedor_id IS NULL OR c.factura_proveedor_id = 0)
              AND (? = 0 OR c.orden_compra_id = ?)
            ORDER BY oc.orden_compra_id DESC, c.nro_certificado ASC";

    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log('=== contratoCertificacionesDisponiblesFacturar: ' . mysqli_error($conexion) . ' ===');
        return [];
    }

    mysqli_stmt_bind_param($stmt, "iiii", $entidad_id, $empresa_id, $orden_compra_id, $orden_compra_id);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);

    $disponibles = [];
    while ($r = mysqli_fetch_assoc($res)) {
        $disponibles[] = $r;
    }
    mysqli_stmt_close($stmt);
    return $disponibles;
}
