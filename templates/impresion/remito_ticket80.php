<?php
// templates/impresion/remito_ticket80.php — remito de venta en ticket de 80 mm (impresora térmica / comandera).
// La incluye modules/*/ventas_remitos_print.php, que arma las variables ($remito, $empresa, $cfg_impresion, ...)
// y los helpers h() / vrPrintCuit() / vrPrintCantidad(). Mismo contenido que la A4 en una columna.
// También la usa ventas_pedidos_print.php ($titulo_tipo = 'PEDIDO', $datos_extra con los datos del pedido y sin conformidad).
$datos_extra = $datos_extra ?? [];
$mostrar_conformidad = $mostrar_conformidad ?? true;
$ver_codigo = !empty($cfg_impresion['mostrar_codigo']);
$fuente = impresion_fuente($cfg_impresion);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($remito['comprobante_tipo']) ?> <?= h($numero) ?> - <?= h($cliente_nombre) ?></title>
    <link href="<?= asset_local('css/bootstrap-5.3.0/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset_local('css/fontawesome-6.4.0/css/all.min.css') ?>">
    <?= $fuente['link'] ?>
    <style>
        @page { size: 80mm auto; margin: 0; }   /* sin margen el navegador no agrega fecha/título/URL */
        body { font-family: <?= $fuente['family'] ?>; font-weight: 400; font-size: 11px; color: #000; background: #e9ecef; }
        b, strong, .fw-bold { font-weight: 600 !important; }
        .ticket { background: #fff; width: 72mm; margin: 0 auto 16px; padding: 3mm 2mm; }
        .centro { text-align: center; }
        .sep { border-top: 1px dashed #000; margin: 4px 0; }
        .logo { max-width: 40mm; max-height: 18mm; object-fit: contain; display: block; margin: 0 auto 3px; }
        .razon-social { font-size: 13px; font-weight: 600; }
        .letra { display: inline-block; border: 1px solid #000; font-size: 20px; font-weight: 600; width: 32px; line-height: 30px; }
        .cod { font-size: 8px; font-weight: 600; }
        .tipo-doc { font-size: 13px; font-weight: 600; }
        .copia { font-size: 9px; font-weight: 600; letter-spacing: 1px; }
        .linea { line-height: 1.25; }
        table.items { width: 100%; border-collapse: collapse; font-size: 10.5px; }
        table.items td { padding: 0 1px; vertical-align: top; line-height: 1.15; }
        table.items .desc td { padding-top: 2px; }
        .num { text-align: right; white-space: nowrap; }
        table.totales { width: 100%; font-size: 11px; }
        table.totales td { padding: 0 1px; }
        table.totales tr.total-final td { font-size: 14px; font-weight: 600; border-top: 1px solid #000; padding-top: 2px; }
        .aviso-estado { border: 1px dashed #000; text-align: center; font-weight: 600; padding: 2px; margin: 3px 0; font-size: 10px; }
        .firma { border-top: 1px solid #000; margin-top: 22px; text-align: center; font-size: 9px; }
        .pequeno { font-size: 9px; }
        .no-print-bar { max-width: 72mm; margin: 12px auto; }
        @media print {
            body { background: #fff; }
            .no-print-bar { display: none !important; }
            .ticket { margin: 0; page-break-after: always; break-after: page; }
            .ticket:last-of-type { page-break-after: auto; break-after: auto; }
        }
    </style>
</head>
<body>
<div class="no-print-bar d-flex flex-wrap gap-1 justify-content-between">
    <div class="btn-group btn-group-sm" role="group">
        <?php foreach ([1 => '1', 2 => '2', 3 => '3'] as $n => $txt): ?>
        <a class="btn <?= $copias === $n ? 'btn-dark' : 'btn-outline-dark' ?>" title="Copias" href="<?= h($url_copias($n)) ?>"><?= h($txt) ?></a>
        <?php endforeach; ?>
    </div>
    <a class="btn btn-sm <?= $valorizado ? 'btn-dark' : 'btn-outline-dark' ?>" title="Mostrar precios"
       href="<?= h($url_copias($copias, !$valorizado)) ?>"><i class="fas fa-dollar-sign"></i></a>
    <div>
        <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="fas fa-print"></i></button>
        <button class="btn btn-secondary btn-sm" onclick="window.close()"><i class="fas fa-times"></i></button>
    </div>
</div>

<?php for ($c = 0; $c < $copias; $c++): ?>
<div class="ticket">
    <div class="centro">
        <?php if ($logo_src): ?><img class="logo" src="<?= h($logo_src) ?>" alt="Logo"><?php endif; ?>
        <div class="razon-social"><?= h($empresa['empresa'] ?? '') ?></div>
        <div class="linea"><?= h(trim(implode(' - ', array_filter([$empresa['domicilio'] ?? '', $empresa['localidad'] ?? ''])))) ?></div>
        <?php if (!empty($sucursal['sucursal_nombre'])): ?>
        <div class="linea">Suc.: <?= h($sucursal['sucursal_nombre']) ?><?= !empty($sucursal['direccion']) ? ' - ' . h($sucursal['direccion']) : '' ?></div>
        <?php endif; ?>
        <div class="linea">CUIT: <?= h(vrPrintCuit($empresa_cuit)) ?> - <?= h($empresa['condicion_fiscal'] ?? '') ?></div>
        <div class="linea">IIBB: <?= h($parametros['ingresos_brutos_nro'] ?? '') ?> - Inicio Act.: <?= h(fmt_fecha($parametros['inicio_actividades'] ?? '')) ?></div>
    </div>

    <div class="sep"></div>
    <div class="centro">
        <span class="letra"><?= h($letra) ?></span>
        <?php if ($fiscal): ?><div class="cod">COD. 091</div><?php endif; ?>
        <div class="tipo-doc"><?= h($titulo_tipo) ?></div>
        <div class="linea">Nro: <?= $nro > 0 ? h($numero) : '<em>sin numerar</em>' ?></div>
        <div class="linea">Fecha: <?= h(fmt_fecha($remito['f_emision'])) ?></div>
        <div class="copia"><?= $etiquetas_copia[$c] ?></div>
    </div>

    <?php if (!$confirmado): ?>
    <div class="aviso-estado">NO CONFIRMADO — <?= h(mb_strtoupper($remito['estado_registro'] ?? '')) ?> — SIN VALIDEZ</div>
    <?php endif; ?>

    <div class="sep"></div>
    <div class="linea"><b>Razón Social:</b> <?= h($cliente_nombre) ?></div>
    <div class="linea"><b><?= strlen($cliente_cuit) === 11 ? 'CUIT' : 'Doc.' ?>:</b>
        <?= strlen($cliente_cuit) === 11 ? h(vrPrintCuit($cliente_cuit)) : h($cliente_cuit ?: '-') ?></div>
    <?php if ($cliente_domicilio !== ''): ?><div class="linea"><b>Domicilio:</b> <?= h($cliente_domicilio) ?></div><?php endif; ?>
    <div class="linea"><b>Cond. IVA:</b> <?= h($cliente_condicion) ?></div>
    <?php if (!empty($cliente_sucursal['sucursal_nombre'])): ?>
    <div class="linea"><b>Entrega:</b> <?= h($cliente_sucursal['sucursal_nombre']) ?><?= !empty($cliente_sucursal['sucursal_direccion']) ? ' - ' . h($cliente_sucursal['sucursal_direccion']) : '' ?></div>
    <?php endif; ?>
    <?php foreach ($datos_extra as $etiqueta => $valor): ?>
    <div class="linea"><b><?= h($etiqueta) ?>:</b> <?= h($valor) ?></div>
    <?php endforeach; ?>
    <?php if (!empty($pedidos_origen)): ?>
    <div class="linea"><b>Pedido<?= count($pedidos_origen) > 1 ? 's' : '' ?>:</b> <?= h(implode(', ', $pedidos_origen)) ?></div>
    <?php endif; ?>

    <div class="sep"></div>
    <table class="items">
        <?php foreach ($detalles as $d): ?>
        <tr class="desc"><td colspan="2"><?= $ver_codigo && !empty($d['producto_codigo']) ? h($d['producto_codigo']) . ' ' : '' ?><?= h($d['producto_nombre'] ?? '') ?></td></tr>
        <tr>
            <td><?= h(vrPrintCantidad($d['cantidad'])) ?><?= $valorizado ? ' x ' . h(fmt_numero($d['precio_unitario_neto'])) . ' IVA ' . h(fmt_numero($d['iva_porcentaje'])) . '%' : ' u.' ?></td>
            <td class="num"><?= $valorizado ? h(fmt_numero($d['importe_linea'])) : '' ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <div class="sep"></div>
    <table class="totales">
        <tr><td>Unidades</td><td class="num"><?= h(vrPrintCantidad($total_unidades)) ?></td></tr>
        <?php if ($valorizado): ?>
        <tr><td>Neto</td><td class="num"><?= h(fmt_numero($neto)) ?></td></tr>
        <tr><td>IVA</td><td class="num"><?= h(fmt_numero($iva)) ?></td></tr>
        <tr class="total-final"><td>TOTAL</td><td class="num"><?= h(fmt_numero($total)) ?></td></tr>
        <?php endif; ?>
    </table>

    <?php if (!empty($remito['observaciones'])): ?>
    <div class="sep"></div>
    <div class="pequeno"><b>Obs.:</b> <?= nl2br(h($remito['observaciones'])) ?></div>
    <?php endif; ?>

    <?php if ($cfg_impresion['texto_pie'] !== ''): ?>
    <div class="sep"></div>
    <div class="pequeno centro"><?= nl2br(h($cfg_impresion['texto_pie'])) ?></div>
    <?php endif; ?>

    <?php if ($mostrar_conformidad): ?>
    <div class="sep"></div>
    <div class="pequeno fw-bold">Recibí conforme</div>
    <div class="firma">Firma y aclaración</div>
    <div class="firma">DNI</div>
    <?php endif; ?>

    <div class="sep"></div>
    <div class="centro fw-bold pequeno">DOCUMENTO NO VÁLIDO COMO FACTURA</div>
</div>
<?php endfor; ?>
</body>
</html>
