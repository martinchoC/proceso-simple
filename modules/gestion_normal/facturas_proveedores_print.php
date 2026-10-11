<?php
// facturas_proveedores_print.php — impresión de una factura de proveedor (HTML para imprimir / PDF del navegador).
// Mismo guard que facturas_proveedores_ajax.php: sesión + empresa del usuario + acceso a la página.
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 5, 'paginas_url' => ['facturas_proveedores.php']]);

require_once __DIR__ . '/../../db.php';
require_once "facturas_proveedores_model.php";

$empresa_idx = $ctx['empresa_id'];
$factura_proveedor_id = intval($_GET['factura_proveedor_id'] ?? 0);

if ($factura_proveedor_id <= 0) {
    http_response_code(400);
    die('Factura no indicada');
}

$factura = obtenerFacturaProveedorPorId($conexion, $factura_proveedor_id, $empresa_idx);
if (!$factura) {
    http_response_code(404);
    die('Factura no encontrada');
}

if (!empty($factura['sucursal_id']) && file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
    require_once __DIR__ . '/../../core/accesos_sucursales.php';
    try {
        AccesosSucursalesHelper::exigirAccesoSucursal($conexion, $empresa_idx, intval($factura['sucursal_id']));
    } catch (RuntimeException $e) {
        http_response_code(403);
        die('Sin acceso a la sucursal de esta factura');
    }
}

function fpPrintFila($conexion, $sql, $tipos, ...$params)
{
    $stmt = mysqli_prepare($conexion, $sql);
    if (!$stmt) {
        error_log("facturas_proveedores_print: " . mysqli_error($conexion));
        return [];
    }
    mysqli_stmt_bind_param($stmt, $tipos, ...$params);
    mysqli_stmt_execute($stmt);
    $fila = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $fila ?: [];
}

function fpPrintCuit($cuit)
{
    $c = preg_replace('/\D/', '', (string) $cuit);
    return strlen($c) === 11 ? substr($c, 0, 2) . '-' . substr($c, 2, 8) . '-' . substr($c, 10) : $c;
}

function fpPrintFecha($fecha)
{
    return !empty($fecha) && $fecha !== '0000-00-00' ? date('d/m/Y', strtotime($fecha)) : '';
}

function h($v)
{
    return htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
}

$empresa = fpPrintFila($conexion, "SELECT empresa, documento_numero, domicilio, telefono, email FROM conf__empresas WHERE empresa_id = ?", "i", $empresa_idx);
$sucursal = !empty($factura['sucursal_id'])
    ? fpPrintFila($conexion, "SELECT sucursal_nombre, direccion, telefono FROM gestion__sucursales WHERE sucursal_id = ? AND empresa_id = ?", "ii", $factura['sucursal_id'], $empresa_idx)
    : [];
$proveedor = fpPrintFila($conexion, "SELECT cuit, domicilio_legal FROM gestion__entidades WHERE entidad_id = ? AND empresa_id = ?", "ii", $factura['entidad_id'], $empresa_idx);
$proveedor_sucursal = !empty($factura['entidad_sucursal_id'])
    ? fpPrintFila($conexion, "SELECT sucursal_nombre FROM gestion__entidades_sucursales WHERE sucursal_id = ? AND empresa_id = ?", "ii", $factura['entidad_sucursal_id'], $empresa_idx)
    : [];
$condicion_pago = !empty($factura['condicion_pago_id'])
    ? fpPrintFila($conexion, "SELECT condicion_pago FROM gestion__condiciones_pago WHERE condicion_pago_id = ? AND empresa_id = ?", "ii", $factura['condicion_pago_id'], $empresa_idx)
    : [];
$deposito = !empty($factura['deposito_id'])
    ? fpPrintFila($conexion, "SELECT boca_nombre FROM gestion__bocas WHERE boca_id = ? AND empresa_id = ?", "ii", $factura['deposito_id'], $empresa_idx)
    : [];
$impuestos_adicionales = obtenerImpuestosFactura($conexion, $factura_proveedor_id);

$numero = str_pad((string) intval($factura['comprobante_pv'] ?? 0), 5, '0', STR_PAD_LEFT) . '-'
        . str_pad((string) intval($factura['comprobante_nro'] ?? 0), 8, '0', STR_PAD_LEFT);
$simbolo = $factura['simbolo'] ?: '$';
$confirmada = intval($factura['tabla_estado_registro_id']) === 5;

// Totales por alícuota de IVA a partir del detalle
$iva_por_alicuota = [];
foreach ($factura['detalles'] as $d) {
    $clave = number_format(floatval($d['iva_porcentaje']), 2);
    $iva_por_alicuota[$clave] = ($iva_por_alicuota[$clave] ?? 0) + floatval($d['iva_importe']);
}
ksort($iva_por_alicuota);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($factura['comprobante_tipo']) ?> <?= h($numero) ?> - <?= h($factura['entidad_nombre']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: Arial, sans-serif; font-size: 13px; background: #fff; padding: 20px; }
        .encabezado { border: 1px solid #333; }
        .encabezado .col-6 { padding: 12px 16px; }
        .encabezado .col-6 + .col-6 { border-left: 1px solid #333; }
        .razon-social { font-size: 20px; font-weight: bold; }
        .tipo-doc { font-size: 18px; font-weight: bold; }
        .datos { border: 1px solid #333; border-top: 0; padding: 10px 16px; }
        .etiqueta { font-weight: bold; display: inline-block; min-width: 130px; }
        .table-detalle { margin-top: 15px; font-size: 12px; }
        .table-detalle th { background: #f0f0f0; }
        .totales { width: 340px; margin-left: auto; }
        .totales td { padding: 3px 6px; }
        .totales .total-final td { font-size: 16px; font-weight: bold; border-top: 2px solid #333; }
        .aviso-estado { border: 2px dashed #c00; color: #c00; text-align: center; font-weight: bold; padding: 6px; margin-bottom: 12px; }
        .pie { margin-top: 40px; text-align: center; font-size: 11px; color: #666; }
        @media print {
            .no-print { display: none; }
            body { padding: 0; }
        }
    </style>
</head>
<body>
<div class="container">
    <div class="no-print text-end mb-3">
        <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print me-1"></i>Imprimir</button>
        <button class="btn btn-secondary" onclick="window.close()"><i class="fas fa-times me-1"></i>Cerrar</button>
    </div>

    <?php if (!$confirmada): ?>
    <div class="aviso-estado">COMPROBANTE NO CONFIRMADO — <?= h(mb_strtoupper($factura['estado_registro'] ?? '')) ?></div>
    <?php endif; ?>

    <div class="row g-0 encabezado">
        <div class="col-6">
            <div class="text-muted small">Comprobante recibido de</div>
            <div class="razon-social"><?= h($factura['entidad_nombre']) ?></div>
            <?php if (!empty($factura['entidad_fantasia'])): ?><div><?= h($factura['entidad_fantasia']) ?></div><?php endif; ?>
            <?php if (!empty($proveedor_sucursal['sucursal_nombre'])): ?><div>Sucursal: <?= h($proveedor_sucursal['sucursal_nombre']) ?></div><?php endif; ?>
            <?php if (!empty($proveedor['domicilio_legal'])): ?><div><?= h($proveedor['domicilio_legal']) ?></div><?php endif; ?>
            <?php if (!empty($proveedor['cuit'])): ?><div>CUIT: <?= h(fpPrintCuit($proveedor['cuit'])) ?></div><?php endif; ?>
        </div>
        <div class="col-6">
            <div class="tipo-doc"><?= h($factura['comprobante_tipo']) ?></div>
            <div class="fs-5">N° <?= h($numero) ?></div>
            <div><span class="etiqueta">Fecha emisión:</span><?= fpPrintFecha($factura['f_emision']) ?></div>
            <div><span class="etiqueta">Fecha contable:</span><?= fpPrintFecha($factura['f_contabilidad'] ?? $factura['f_emision']) ?></div>
            <div><span class="etiqueta">Vencimiento:</span><?= fpPrintFecha($factura['f_vencimiento']) ?></div>
        </div>
    </div>

    <div class="row g-0 datos">
        <div class="col-6">
            <div><span class="etiqueta">Empresa:</span><?= h($empresa['empresa'] ?? '') ?></div>
            <?php if (!empty($empresa['documento_numero'])): ?>
            <div><span class="etiqueta">CUIT:</span><?= h(fpPrintCuit(number_format((float) $empresa['documento_numero'], 0, '', ''))) ?></div>
            <?php endif; ?>
            <div><span class="etiqueta">Sucursal:</span><?= h($sucursal['sucursal_nombre'] ?? '') ?></div>
            <?php if (!empty($deposito['boca_nombre'])): ?>
            <div><span class="etiqueta">Depósito:</span><?= h($deposito['boca_nombre']) ?></div>
            <?php endif; ?>
        </div>
        <div class="col-6">
            <div><span class="etiqueta">Condición de pago:</span><?= h($condicion_pago['condicion_pago'] ?? '') ?></div>
            <div><span class="etiqueta">Moneda:</span><?= h($factura['moneda'] ?? '') ?>
                <?php if (floatval($factura['tipo_cambio'] ?? 1) != 1): ?>(T.C. <?= number_format(floatval($factura['tipo_cambio']), 6, ',', '.') ?>)<?php endif; ?>
            </div>
            <?php if (!empty($factura['orden_compra_nro']) || !empty($factura['orden_compra_id'])): ?>
            <div><span class="etiqueta">Orden de compra:</span>N° <?= h($factura['orden_compra_nro'] ?: $factura['orden_compra_id']) ?></div>
            <?php endif; ?>
            <div><span class="etiqueta">Estado:</span><?= h($factura['estado_registro'] ?? '') ?></div>
        </div>
    </div>

    <table class="table table-bordered table-sm table-detalle">
        <thead>
            <tr>
                <th>Cód. prov.</th>
                <th>Producto</th>
                <th class="text-end">Cant.</th>
                <th class="text-end">Precio unit.</th>
                <th class="text-end">Dto.</th>
                <th class="text-end">Neto</th>
                <th class="text-end">IVA %</th>
                <th class="text-end">IVA</th>
                <th class="text-end">No grav. / Exento</th>
                <th class="text-end">Total</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($factura['detalles'] as $d): ?>
            <tr>
                <td><?= h($d['codigo_proveedor'] ?? '') ?></td>
                <td><?= h($d['producto_nombre']) ?><?= !empty($d['ordenes_compra_detalle_id']) ? ' <small class="text-muted">(OC)</small>' : '' ?></td>
                <td class="text-end"><?= number_format(floatval($d['cantidad']), 2, ',', '.') ?></td>
                <td class="text-end"><?= number_format(floatval($d['precio_unitario']), 2, ',', '.') ?></td>
                <td class="text-end"><?= number_format(floatval($d['descuento']), 2, ',', '.') ?></td>
                <td class="text-end"><?= number_format(floatval($d['neto_gravado']), 2, ',', '.') ?></td>
                <td class="text-end"><?= number_format(floatval($d['iva_porcentaje']), 2, ',', '.') ?>%</td>
                <td class="text-end"><?= number_format(floatval($d['iva_importe']), 2, ',', '.') ?></td>
                <td class="text-end"><?= number_format(floatval($d['no_gravado']) + floatval($d['exento']), 2, ',', '.') ?></td>
                <td class="text-end fw-bold"><?= number_format(floatval($d['total_linea']), 2, ',', '.') ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <table class="totales">
        <tr><td>Neto gravado</td><td class="text-end"><?= h($simbolo) ?> <?= number_format(floatval($factura['subtotal']), 2, ',', '.') ?></td></tr>
        <?php if (floatval($factura['descuentos']) != 0): ?>
        <tr><td>Descuentos</td><td class="text-end"><?= h($simbolo) ?> <?= number_format(floatval($factura['descuentos']), 2, ',', '.') ?></td></tr>
        <?php endif; ?>
        <?php if (floatval($factura['no_gravado']) != 0): ?>
        <tr><td>No gravado</td><td class="text-end"><?= h($simbolo) ?> <?= number_format(floatval($factura['no_gravado']), 2, ',', '.') ?></td></tr>
        <?php endif; ?>
        <?php if (floatval($factura['exento']) != 0): ?>
        <tr><td>Exento</td><td class="text-end"><?= h($simbolo) ?> <?= number_format(floatval($factura['exento']), 2, ',', '.') ?></td></tr>
        <?php endif; ?>
        <?php foreach ($iva_por_alicuota as $alicuota => $importe): ?>
        <?php if ($importe != 0): ?>
        <tr><td>IVA <?= h(str_replace('.', ',', rtrim(rtrim($alicuota, '0'), '.'))) ?>%</td><td class="text-end"><?= h($simbolo) ?> <?= number_format($importe, 2, ',', '.') ?></td></tr>
        <?php endif; ?>
        <?php endforeach; ?>
        <?php foreach ($impuestos_adicionales as $imp): ?>
        <tr>
            <td><?= h($imp['impuesto_tipo'] ?: 'Impuesto') ?><?= !empty($imp['jurisdiccion_nombre']) ? ' ' . h($imp['jurisdiccion_nombre']) : '' ?>
                <?php if (floatval($imp['alicuota']) > 0): ?><small class="text-muted">(<?= number_format(floatval($imp['alicuota']), 2, ',', '.') ?>%)</small><?php endif; ?></td>
            <td class="text-end"><?= h($simbolo) ?> <?= number_format(floatval($imp['importe']), 2, ',', '.') ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="total-final"><td>TOTAL</td><td class="text-end"><?= h($simbolo) ?> <?= number_format(floatval($factura['total']), 2, ',', '.') ?></td></tr>
    </table>

    <?php if (!empty($factura['observaciones'])): ?>
    <div class="mt-4">
        <div class="etiqueta">Observaciones:</div>
        <p><?= nl2br(h($factura['observaciones'])) ?></p>
    </div>
    <?php endif; ?>

    <div class="pie">
        Comprobante interno de registro de compra — generado el <?= date('d/m/Y H:i') ?>
    </div>
</div>
</body>
</html>
<?php
if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
