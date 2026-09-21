<?php
require_once __DIR__ . '/../../db.php';
$conexion = $conn;

function vfEstadoColumna($conexion) {
    $result = mysqli_query($conexion, "SHOW COLUMNS FROM conf__estados_registros");
    $columns = [];
    while ($row = mysqli_fetch_assoc($result)) $columns[] = $row['Field'];
    if (in_array('estado_registro', $columns)) return 'estado_registro';
    if (in_array('nombre_estado', $columns)) return 'nombre_estado';
    return 'descripcion';
}

function listarVentasFacturas($conexion, $empresa_id, $pagina_id) {
    $estado = vfEstadoColumna($conexion);
    $sql = "SELECT f.venta_factura_id, f.comprobante_nro, f.f_emision, f.importe_total,
                   f.entidad_id, f.condicion_pago_id, er.$estado AS estado_registro,
                   er.codigo_estandar, ct.comprobante_tipo, e.entidad_nombre,
                   e.entidad_fantasia, cp.condicion_pago
            FROM gestion__ventas_facturas f
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = f.tabla_estado_registro_id
            LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = f.comprobante_tipo_id
            LEFT JOIN gestion__entidades e ON e.entidad_id = f.entidad_id
            LEFT JOIN gestion__condiciones_pago cp ON cp.condicion_pago_id = f.condicion_pago_id
            WHERE f.empresa_id = ?
            ORDER BY f.f_emision DESC, f.venta_factura_id DESC";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $empresa_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

function vfObtenerCondicionCliente($conexion, $entidad_id) {
    $sql = "SELECT ecc.condicion_pago_id, ecc.lista_precio_id, ecc.cliente_descuento_general,
                   cp.condicion_pago, cp.dias_primer_vencimiento,
                   lp.lista_precio_nombre
            FROM gestion__entidades_condiciones_clientes ecc
            LEFT JOIN gestion__condiciones_pago cp ON cp.condicion_pago_id = ecc.condicion_pago_id
            LEFT JOIN gestion__listas_precios lp ON lp.lista_precio_id = ecc.lista_precio_id
            WHERE ecc.entidad_id = ? AND ecc.tabla_estado_registro_id = 1
              AND ecc.f_desde <= CURDATE()
              AND (ecc.f_hasta IS NULL OR ecc.f_hasta >= CURDATE())
            ORDER BY ecc.f_desde DESC LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $entidad_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row ?: null;
}

function vfClientes($conexion, $empresa_id) {
    $sql = "SELECT DISTINCT e.entidad_id, e.entidad_nombre, e.entidad_fantasia
            FROM gestion__entidades e
            INNER JOIN gestion__entidades_roles erol ON erol.entidad_id = e.entidad_id
            INNER JOIN gestion__roles_entidades rol ON rol.rol_entidad_id = erol.rol_entidad_id
            WHERE rol.rol_entidad_codigo = 'CLI'
              AND e.tabla_estado_registro_id = 1 ORDER BY e.entidad_nombre";
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) return [];
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

function vfRemitosPendientes($conexion, $empresa_id, $entidad_id) {
    $sql = "SELECT vr.venta_remito_id, vr.comprobante_nro, vr.f_emision,
                   ct.comprobante_tipo, pv.nombre AS punto_venta_nombre,
                   rd.venta_remito_detalle_id, rd.venta_pedido_detalle_id,
                   rd.producto_id, p.producto_codigo, p.producto_nombre,
                   rd.cantidad, rd.precio_unitario_bruto, rd.descuento_general_pct,
                   rd.descuento_general, rd.precio_unitario_neto, rd.iva_alicuota_id,
                   rd.iva_porcentaje, rd.iva_importe, rd.importe_linea,
                   COALESCE(rd.facturado, 0) AS facturado
            FROM gestion__ventas_remitos vr
            INNER JOIN gestion__ventas_remitos_detalles rd ON rd.venta_remito_id = vr.venta_remito_id
            INNER JOIN gestion__productos p ON p.producto_id = rd.producto_id
            LEFT JOIN gestion__comprobantes_tipos ct ON ct.comprobante_tipo_id = vr.comprobante_tipo_id
            LEFT JOIN gestion__puntos_venta pv ON pv.punto_venta_id = vr.comprobante_pv
            LEFT JOIN conf__estados_registros er ON er.estado_registro_id = vr.tabla_estado_registro_id
            WHERE vr.empresa_id = ? AND vr.entidad_id = ?
              AND er.codigo_estandar IN ('CONFIRMADO', 'PEND_FACT')
              AND COALESCE(rd.facturado, 0) = 0
              AND rd.tabla_estado_registro_id = 1
            ORDER BY vr.f_emision, vr.venta_remito_id, rd.venta_remito_detalle_id";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $empresa_id, $entidad_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

function vfBuscarProductos($conexion, $empresa_id, $entidad_id, $q) {
    $cond = vfObtenerCondicionCliente($conexion, $entidad_id);
    if (!$cond || empty($cond['lista_precio_id'])) return [];
    $like = '%' . $q . '%';
    $sql = "SELECT p.producto_id, p.producto_codigo, p.producto_nombre,
                   lp.precio_final AS precio_unitario, p.iva_alicuota_id,
                   COALESCE(iva.porcentaje, 0) AS iva_porcentaje
            FROM gestion__listas_precios_productos lp
            INNER JOIN gestion__productos p ON p.producto_id = lp.producto_id
            LEFT JOIN gestion__impuestos__iva_alicuotas iva ON iva.iva_alicuota_id = p.iva_alicuota_id
            WHERE lp.lista_precio_id = ? AND lp.empresa_id = ? AND p.empresa_id = ?
              AND lp.tabla_estado_registro_id = 1 AND p.tabla_estado_registro_id = 1
              AND (p.producto_codigo LIKE ? OR p.producto_nombre LIKE ?)
            ORDER BY p.producto_nombre LIMIT 20";
    $stmt = mysqli_prepare($conexion, $sql);
    $lista = intval($cond['lista_precio_id']);
    mysqli_stmt_bind_param($stmt, 'iiiss', $lista, $empresa_id, $empresa_id, $like, $like);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) $rows[] = $row;
    mysqli_stmt_close($stmt);
    return $rows;
}

function vfEstadoInicial($conexion, $pagina_id) {
    $sql = "SELECT ter.tabla_estado_registro_id
            FROM conf__tablas_estados_registros ter
            INNER JOIN conf__paginas pg ON pg.tabla_id = ter.tabla_id
            WHERE pg.pagina_id = ? AND ter.es_inicial = 1 LIMIT 1";
    $stmt = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $pagina_id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return intval($row['tabla_estado_registro_id'] ?? 3);
}

function guardarVentaFactura($conexion, $empresa_id, $pagina_id, $data) {
    $detalles = $data['detalles'] ?? [];
    if (!$data['entidad_id'] || count($detalles) === 0) return ['success' => false, 'error' => 'Seleccione un cliente y agregue al menos un producto.'];
    mysqli_begin_transaction($conexion);
    try {
        $cond = vfObtenerCondicionCliente($conexion, intval($data['entidad_id']));
        $condicion_pago_id = intval($data['condicion_pago_id'] ?: ($cond['condicion_pago_id'] ?? 0));
        $estado = vfEstadoInicial($conexion, $pagina_id);
        $f_emision = $data['f_emision'] ?: date('Y-m-d');
        $f_vto = $data['f_vto'] ?: null;
        $tipo_cambio = floatval($data['tipo_cambio'] ?: 1);
        $subtotal = $descuentos = $iva = $bruto = $total = 0;
        foreach ($detalles as &$d) {
            $cantidad = floatval($d['cantidad']);
            $precio = floatval($d['precio_unitario']);
            $descPct = floatval($d['descuento_general_pct'] ?? 0);
            $brutoLinea = $cantidad * $precio;
            $desc = $brutoLinea * $descPct / 100;
            $neto = $brutoLinea - $desc;
            $ivaImporte = $neto * floatval($d['iva_porcentaje'] ?? 0) / 100;
            $linea = $neto + $ivaImporte;
            $d['_bruto'] = $brutoLinea; $d['_desc'] = $desc; $d['_neto'] = $neto; $d['_iva'] = $ivaImporte; $d['_linea'] = $linea;
            $bruto += $brutoLinea; $descuentos += $desc; $subtotal += $neto; $iva += $ivaImporte; $total += $linea;
        }
        unset($d);
        $sql = "INSERT INTO gestion__ventas_facturas
                (empresa_id, sucursal_id, comprobante_tipo_id, punto_venta_id, comprobante_nro,
                 comprobante_id, entidad_id, entidad_sucursal_id, condicion_pago_id, f_emision,
                 f_contabilidad, f_vto, moneda_id, tipo_cambio, importe_bruto, descuento_general_pct,
                 descuento_general, importe_neto, importe_exento, importe_no_gravado, importe_iva,
                 importe_otros_impuestos, importe_total, observaciones, tabla_estado_registro_id)
                VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, 0, ?, ?, ?)";
        $stmt = mysqli_prepare($conexion, $sql);
        $sucursal = intval($data['sucursal_id']); $tipo = intval($data['comprobante_tipo_id']); $pv = intval($data['punto_venta_id']);
        $nro = intval($data['comprobante_nro']); $entidad = intval($data['entidad_id']); $entidadSucursal = intval($data['entidad_sucursal_id'] ?: 0);
        $moneda = intval($data['moneda_id'] ?: 1); $pct = floatval($data['descuento_general_pct'] ?: 0); $obs = trim($data['observaciones'] ?? '');
        mysqli_stmt_bind_param($stmt, 'iiiiiiiisssidddddddsi', $empresa_id, $sucursal, $tipo, $pv, $nro, $entidad, $entidadSucursal, $condicion_pago_id, $f_emision, $f_emision, $f_vto, $moneda, $tipo_cambio, $bruto, $pct, $descuentos, $subtotal, $iva, $total, $obs, $estado);
        if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
        $factura_id = mysqli_insert_id($conexion); mysqli_stmt_close($stmt);
        $sqlDet = "INSERT INTO gestion__ventas_facturas_detalles
            (venta_factura_id, producto_id, venta_remito_detalle_id, venta_pedido_detalle_id, cantidad,
             precio_unitario, descuento_general_pct, descuento_general, precio_unitario_neto, importe_neto,
             iva_alicuota_id, porcentaje_iva, importe_iva, importe_no_gravado, importe_exento, importe_linea, tabla_estado_registro_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?, 1)";
        foreach ($detalles as $d) {
            $stmt = mysqli_prepare($conexion, $sqlDet);
            $producto = intval($d['producto_id']); $remitoDetalle = intval($d['venta_remito_detalle_id'] ?? 0) ?: null; $pedidoDetalle = intval($d['venta_pedido_detalle_id'] ?? 0) ?: null;
            $cantidad = floatval($d['cantidad']); $precio = floatval($d['precio_unitario']); $descPct = floatval($d['descuento_general_pct'] ?? 0); $desc = $d['_desc']; $netoUnit = $d['_neto'] / max($cantidad, 0.0001); $neto = $d['_neto']; $ivaId = intval($d['iva_alicuota_id'] ?? 1); $ivaPct = floatval($d['iva_porcentaje'] ?? 0); $ivaLinea = $d['_iva']; $linea = $d['_linea'];
            mysqli_stmt_bind_param($stmt, 'iiiiddddddiddd', $factura_id, $producto, $remitoDetalle, $pedidoDetalle, $cantidad, $precio, $descPct, $desc, $netoUnit, $neto, $ivaId, $ivaPct, $ivaLinea, $linea);
            if (!mysqli_stmt_execute($stmt)) throw new Exception(mysqli_stmt_error($stmt));
            mysqli_stmt_close($stmt);
            if ($remitoDetalle) {
                $upd = mysqli_prepare($conexion, "UPDATE gestion__ventas_remitos_detalles SET facturado = ? WHERE venta_remito_detalle_id = ? AND facturado = 0");
                mysqli_stmt_bind_param($upd, 'ii', $factura_id, $remitoDetalle); mysqli_stmt_execute($upd); mysqli_stmt_close($upd);
            }
        }
        mysqli_commit($conexion);
        return ['success' => true, 'venta_factura_id' => $factura_id, 'message' => 'Factura guardada correctamente.'];
    } catch (Exception $e) { mysqli_rollback($conexion); return ['success' => false, 'error' => $e->getMessage()]; }
}
?>
