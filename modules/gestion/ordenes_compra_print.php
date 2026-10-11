<?php
// ordenes_compra_print.php
// Mismo guard que ordenes_compra_ajax.php: sesión + empresa del usuario + acceso a la página.
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 2, 'paginas_url' => ['ordenes_compra.php']]);

require_once __DIR__ . '/../../db.php';
require_once "ordenes_compra_model.php";

$orden_compra_id = intval($_GET['orden_compra_id'] ?? 0);
$empresa_idx = $ctx['empresa_id'];

if (!$orden_compra_id) {
    die('ID de orden no proporcionado');
}

$orden = obtenerOrdenCompraPorId($conexion, $orden_compra_id, $empresa_idx);

if (!$orden) {
    die('Orden no encontrada');
}

// Obtener datos de la empresa (gestion__empresas no existe: la empresa vive en conf__empresas)
$sql_empresa = "SELECT empresa AS empresa_nombre, documento_numero, domicilio, telefono FROM conf__empresas WHERE empresa_id = ?";
$stmt = mysqli_prepare($conexion, $sql_empresa);
mysqli_stmt_bind_param($stmt, "i", $empresa_idx);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$empresa = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Obtener datos de la sucursal
$sql_sucursal = "SELECT sucursal_nombre, direccion AS sucursal_direccion, telefono AS sucursal_telefono
                 FROM gestion__sucursales WHERE sucursal_id = ? AND empresa_id = ?";
$stmt = mysqli_prepare($conexion, $sql_sucursal);
mysqli_stmt_bind_param($stmt, "ii", $orden['sucursal_id'], $empresa_idx);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$sucursal = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

// Punto de venta (la OC guarda punto_venta_id, no comprobante_pv) y condición de pago
$stmt = mysqli_prepare($conexion, "SELECT codigo_fiscal FROM gestion__puntos_venta WHERE punto_venta_id = ? AND empresa_id = ?");
mysqli_stmt_bind_param($stmt, "ii", $orden['punto_venta_id'], $empresa_idx);
mysqli_stmt_execute($stmt);
$pv = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
mysqli_stmt_close($stmt);
$orden['comprobante_pv'] = str_pad((string) intval($pv['codigo_fiscal'] ?? 0), 5, '0', STR_PAD_LEFT);

$orden['condicion_pago'] = '';
if (!empty($orden['condicion_pago_id'])) {
    $stmt = mysqli_prepare($conexion, "SELECT condicion_pago FROM gestion__condiciones_pago WHERE condicion_pago_id = ? AND empresa_id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $orden['condicion_pago_id'], $empresa_idx);
    mysqli_stmt_execute($stmt);
    $cp = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    $orden['condicion_pago'] = $cp['condicion_pago'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Orden de Compra <?= htmlspecialchars($orden['comprobante_pv'] . '-' . $orden['comprobante_nro']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #fff;
            padding: 20px;
        }
        .print-header {
            border-bottom: 2px solid #333;
            margin-bottom: 20px;
            padding-bottom: 10px;
        }
        .company-info {
            text-align: center;
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .document-title {
            font-size: 20px;
            font-weight: bold;
            text-align: center;
            margin: 20px 0;
            text-decoration: underline;
        }
        .info-row {
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 150px;
        }
        .table-detalle {
            margin-top: 20px;
        }
        .table-detalle th {
            background-color: #f0f0f0;
        }
        .totales {
            margin-top: 20px;
            text-align: right;
        }
        .total-line {
            font-size: 16px;
            margin-bottom: 5px;
        }
        .total-final {
            font-size: 18px;
            font-weight: bold;
            border-top: 2px solid #333;
            padding-top: 5px;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 12px;
            color: #666;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
        .btn-print {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="no-print text-end mb-3">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="fas fa-print"></i> Imprimir
            </button>
            <button class="btn btn-secondary" onclick="window.close()">
                <i class="fas fa-times"></i> Cerrar
            </button>
        </div>

        <div class="print-header">
            <div class="company-info">
                <div class="company-name"><?= htmlspecialchars($empresa['empresa_nombre'] ?? 'Mi Empresa') ?></div>
                <div><?= htmlspecialchars($sucursal['sucursal_nombre'] ?? 'Sucursal Principal') ?></div>
                <div><?= htmlspecialchars($sucursal['sucursal_direccion'] ?? '') ?></div>
                <div><?= htmlspecialchars($sucursal['sucursal_telefono'] ?? '') ?></div>
            </div>
            <div class="document-title">
                ORDEN DE COMPRA
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="info-row">
                    <span class="info-label">Número:</span>
                    <span><?= htmlspecialchars($orden['comprobante_tipo'] ?? '') ?> <?= $orden['comprobante_pv'] ?>-<?= $orden['comprobante_nro'] ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Fecha Emisión:</span>
                    <span><?= date('d/m/Y', strtotime($orden['f_emision'])) ?></span>
                </div>
                <?php if ($orden['f_entrega_estimada']): ?>
                <div class="info-row">
                    <span class="info-label">Entrega Estimada:</span>
                    <span><?= date('d/m/Y', strtotime($orden['f_entrega_estimada'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <div class="info-row">
                    <span class="info-label">Proveedor:</span>
                    <span><?= htmlspecialchars($orden['entidad_nombre'] ?? '') ?></span>
                </div>
                <?php if (!empty($orden['entidad_fantasia'])): ?>
                <div class="info-row">
                    <span class="info-label">Nombre Fantasía:</span>
                    <span><?= htmlspecialchars($orden['entidad_fantasia']) ?></span>
                </div>
                <?php endif; ?>
                <div class="info-row">
                    <span class="info-label">Moneda:</span>
                    <span><?= htmlspecialchars($orden['moneda'] ?? '') ?> (T.C. <?= number_format($orden['tipo_cambio'], 6) ?>)</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Condición de Pago:</span>
                    <span><?= htmlspecialchars($orden['condicion_pago'] ?? '') ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($orden['direccion_entrega'])): ?>
        <div class="row mt-3">
            <div class="col-12">
                <div class="info-row">
                    <span class="info-label">Dirección de Entrega:</span>
                    <span><?= htmlspecialchars($orden['direccion_entrega']) ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <table class="table table-bordered table-detalle">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th class="text-center">Cantidad</th>
                    <th class="text-center">Recibido</th>
                    <th class="text-end">Precio Unit.</th>
                    <th class="text-center">IVA %</th>
                    <th class="text-end">IVA $</th>
                    <th class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $totalNeto = 0;
                $totalIVA = 0;
                $totalNoGravado = 0;
                $totalExento = 0;
                
                foreach ($orden['detalles'] as $detalle): 
                    $totalNeto += $detalle['neto_gravado'];
                    $totalIVA += $detalle['iva_importe'];
                    $totalNoGravado += $detalle['no_gravado'];
                    $totalExento += $detalle['exento'];
                ?>
                <tr>
                    <td><?= htmlspecialchars($detalle['codigo_proveedor'] ?? $detalle['producto_id']) ?></td>
                    <td><?= htmlspecialchars($detalle['producto_nombre']) ?></td>
                    <td class="text-center"><?= number_format($detalle['cantidad'], 2) ?></td>
                    <td class="text-center"><?= number_format($detalle['cantidad_recibida'] ?? 0, 2) ?></td>
                    <td class="text-end">$ <?= number_format($detalle['precio_unitario'], 4) ?></td>
                    <td class="text-center"><?= number_format($detalle['iva_porcentaje'], 2) ?>%</td>
                    <td class="text-end">$ <?= number_format($detalle['iva_importe'], 2) ?></td>
                    <td class="text-end">$ <?= number_format($detalle['total_linea'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <div class="totales">
            <div class="total-line">
                <span>Total Neto Gravado:</span>
                <span>$ <?= number_format($totalNeto, 2) ?></span>
            </div>
            <?php if ($totalNoGravado > 0): ?>
            <div class="total-line">
                <span>Total No Gravado:</span>
                <span>$ <?= number_format($totalNoGravado, 2) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($totalExento > 0): ?>
            <div class="total-line">
                <span>Total Exento:</span>
                <span>$ <?= number_format($totalExento, 2) ?></span>
            </div>
            <?php endif; ?>
            <div class="total-line">
                <span>Total IVA:</span>
                <span>$ <?= number_format($totalIVA, 2) ?></span>
            </div>
            <div class="total-line total-final">
                <span>TOTAL:</span>
                <span>$ <?= number_format($orden['total'], 2) ?></span>
            </div>
        </div>

        <?php if (!empty($orden['observaciones'])): ?>
        <div class="row mt-4">
            <div class="col-12">
                <div class="info-label">Observaciones:</div>
                <p><?= nl2br(htmlspecialchars($orden['observaciones'])) ?></p>
            </div>
        </div>
        <?php endif; ?>

        <div class="footer">
            <p>Documento generado el <?= date('d/m/Y H:i:s') ?></p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
if (isset($conexion) && $conexion) {
    mysqli_close($conexion);
}
?>