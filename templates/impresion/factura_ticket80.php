<?php
// templates/impresion/factura_ticket80.php — factura de venta en ticket de 80 mm (impresora térmica / comandera).
// La incluye modules/*/ventas_facturas_print.php, que arma las variables ($factura, $empresa, $cfg_impresion, ...)
// y los helpers h() / vfPrintFecha() / vfPrintImporte() / vfPrintCuit().
// Mismo contenido fiscal que la A4 (letra, código, emisor, receptor, totales, CAE y QR) en una columna.
$ver_codigo = !empty($cfg_impresion['mostrar_codigo']);
$ver_bonif = !empty($cfg_impresion['mostrar_bonificacion']);
$fuente = impresion_fuente($cfg_impresion);
$fmt_pct = function ($v) {
    return str_replace('.', ',', rtrim(rtrim(number_format(floatval($v), 2, '.', ''), '0'), '.'));
};
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($factura['comprobante_tipo']) ?> <?= h($numero) ?> - <?= h($cliente_nombre) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        .qr { width: 32mm; height: 32mm; margin: 4px auto; }
        .qr img, .qr canvas { width: 32mm !important; height: 32mm !important; }
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
        <div class="linea">CUIT: <?= h(vfPrintCuit($empresa_cuit)) ?> - <?= h($empresa['condicion_fiscal'] ?? '') ?></div>
        <div class="linea">IIBB: <?= h($parametros['ingresos_brutos_nro'] ?? '') ?> - Inicio Act.: <?= vfPrintFecha($parametros['inicio_actividades'] ?? '') ?></div>
    </div>

    <div class="sep"></div>
    <div class="centro">
        <span class="letra"><?= h($letra) ?></span>
        <?php if ($fiscal): ?><div class="cod">COD. <?= str_pad((string) $arca_tipo, 3, '0', STR_PAD_LEFT) ?></div><?php endif; ?>
        <div class="tipo-doc"><?= h(mb_strtoupper($titulo_tipo)) ?></div>
        <div class="linea">Nro: <?= $nro > 0 ? h($numero) : '<em>sin numerar</em>' ?></div>
        <div class="linea">Fecha: <?= vfPrintFecha($factura['f_emision']) ?></div>
        <div class="copia"><?= $etiquetas_copia[$c] ?></div>
    </div>

    <?php if (!$confirmada): ?>
    <div class="aviso-estado">NO CONFIRMADO — <?= h(mb_strtoupper($factura['estado_registro'] ?? '')) ?> — SIN VALIDEZ</div>
    <?php elseif ($fiscal && !$con_cae): ?>
    <div class="aviso-estado">COMPROBANTE SIN CAE DE ARCA</div>
    <?php endif; ?>

    <div class="sep"></div>
    <div class="linea"><b>Razón Social:</b> <?= h($cliente_nombre) ?></div>
    <div class="linea"><b><?= strlen($cliente_cuit) === 11 ? 'CUIT' : 'Doc.' ?>:</b>
        <?= strlen($cliente_cuit) === 11 ? h(vfPrintCuit($cliente_cuit)) : h($cliente_cuit ?: '-') ?></div>
    <?php if ($cliente_domicilio !== ''): ?><div class="linea"><b>Domicilio:</b> <?= h($cliente_domicilio) ?></div><?php endif; ?>
    <div class="linea"><b>Cond. IVA:</b> <?= h($relacion['receptor_condicion_fiscal_nombre'] ?? 'Consumidor Final') ?></div>
    <?php if (!empty($cfg_impresion['mostrar_sucursal_cliente']) && !empty($cliente_sucursal['sucursal_nombre'])): ?>
    <div class="linea"><b>Entrega:</b> <?= h($cliente_sucursal['sucursal_nombre']) ?><?= !empty($cliente_sucursal['sucursal_direccion']) ? ' - ' . h($cliente_sucursal['sucursal_direccion']) : '' ?></div>
    <?php endif; ?>
    <div class="linea"><b>Cond. venta:</b> <?= h($factura['condicion_pago'] ?? '') ?></div>
    <?php if ($moneda && empty($moneda['es_moneda_base'])): ?>
    <div class="linea"><b>Moneda:</b> <?= h($moneda['moneda'] ?? '') ?> (T.C. <?= number_format(floatval($factura['tipo_cambio']), 6, ',', '.') ?>)</div>
    <?php endif; ?>

    <div class="sep"></div>
    <table class="items">
        <?php foreach ($detalles as $d):
            $cant = floatval($d['cantidad']);
            $iva_pct = floatval($d['porcentaje_iva']);
            $precio = floatval($d['precio_unitario']);
            // A: precios netos y alícuota por línea. B/C/X: precio final (con el IVA incluido, si lo hay).
            $precio_mostrar = $discrimina_iva ? $precio : $precio * (1 + ($es_c ? 0 : $iva_pct) / 100);
            $subtotal_mostrar = $discrimina_iva ? floatval($d['importe_neto']) : floatval($d['importe_linea']);
            $bonif = floatval($d['descuento_general_pct'] ?? 0);
        ?>
        <tr class="desc"><td colspan="2"><?= $ver_codigo && !empty($d['producto_codigo']) ? h($d['producto_codigo']) . ' ' : '' ?><?= h($d['producto_nombre'] ?? '') ?></td></tr>
        <tr>
            <td><?= number_format($cant, $cant == floor($cant) ? 0 : 2, ',', '.') ?> x <?= vfPrintImporte($precio_mostrar) ?>
                <?= $ver_bonif && $bonif != 0 ? ' (-' . h($fmt_pct($bonif)) . '%)' : '' ?>
                <?= $discrimina_iva ? ' IVA ' . h($fmt_pct($iva_pct)) . '%' : '' ?></td>
            <td class="num"><?= vfPrintImporte($subtotal_mostrar) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <div class="sep"></div>
    <table class="totales">
        <?php if ($discrimina_iva): ?>
        <tr><td>Neto gravado</td><td class="num"><?= h($simbolo) ?> <?= vfPrintImporte($neto_gravado) ?></td></tr>
        <?php foreach ($iva_por_alicuota as $al => $imp): if ($imp == 0) continue; ?>
        <tr><td>IVA <?= h($fmt_pct($al)) ?>%</td><td class="num"><?= h($simbolo) ?> <?= vfPrintImporte($imp) ?></td></tr>
        <?php endforeach; ?>
        <?php if ($exento != 0): ?><tr><td>Exento</td><td class="num"><?= h($simbolo) ?> <?= vfPrintImporte($exento) ?></td></tr><?php endif; ?>
        <?php if ($no_gravado != 0): ?><tr><td>No gravado</td><td class="num"><?= h($simbolo) ?> <?= vfPrintImporte($no_gravado) ?></td></tr><?php endif; ?>
        <?php endif; ?>
        <?php foreach ($percepciones_iibb as $p): ?><tr><td><?= h($p['leyenda'] ?: ('Percepción IIBB ' . $p['jurisdiccion_nombre'])) ?> <?= h($fmt_pct_perc($p['alicuota'])) ?>%</td><td class="num"><?= h($simbolo) ?> <?= vfPrintImporte($p['importe']) ?></td></tr><?php endforeach; ?>
        <?php if ($otros_nacionales != 0): ?><tr><td>Otros tributos</td><td class="num"><?= h($simbolo) ?> <?= vfPrintImporte($otros_nacionales) ?></td></tr><?php endif; ?>
        <tr class="total-final"><td>TOTAL</td><td class="num"><?= h($simbolo) ?> <?= vfPrintImporte($total) ?></td></tr>
    </table>

    <?php if ($letra === 'B' && $iva_total > 0): ?>
    <div class="sep"></div>
    <div class="pequeno">
        <b>Régimen de Transparencia Fiscal al Consumidor (Ley 27.743)</b><br>
        IVA Contenido: <?= h($simbolo) ?> <?= vfPrintImporte($iva_total) ?>
        <?php if ($otros_nacionales != 0): ?><br>Otros Imp. Nac. Indirectos: <?= h($simbolo) ?> <?= vfPrintImporte($otros_nacionales) ?><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($factura['observaciones'])): ?>
    <div class="sep"></div>
    <div class="pequeno"><b>Obs.:</b> <?= nl2br(h($factura['observaciones'])) ?></div>
    <?php endif; ?>

    <?php if ($leyendas_fiscales): ?>
    <div class="sep"></div>
    <div class="pequeno"><?= implode('<br>', array_map('h', $leyendas_fiscales)) ?></div>
    <?php endif; ?>

    <?php if ($cfg_impresion['texto_pie'] !== ''): ?>
    <div class="sep"></div>
    <div class="pequeno centro"><?= nl2br(h($cfg_impresion['texto_pie'])) ?></div>
    <?php endif; ?>

    <?php if ($con_cae): ?>
    <div class="sep"></div>
    <div class="qr" data-qr="<?= h($qr_url) ?>"></div>
    <div class="centro">
        <div><b>CAE:</b> <?= h($cae) ?></div>
        <div><b>Vto. CAE:</b> <?= vfPrintFecha($factura['f_vto_CAE'] ?? '') ?></div>
        <div class="pequeno"><i>Comprobante Autorizado</i></div>
    </div>
    <?php elseif (!$fiscal): ?>
    <div class="sep"></div>
    <div class="centro fw-bold pequeno">DOCUMENTO NO VÁLIDO COMO FACTURA</div>
    <?php endif; ?>
</div>
<?php endfor; ?>

<?php if ($con_cae): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script>
    document.querySelectorAll('[data-qr]').forEach(function (el) {
        new QRCode(el, { text: el.getAttribute('data-qr'), width: 220, height: 220, correctLevel: QRCode.CorrectLevel.M });
    });
</script>
<?php endif; ?>
</body>
</html>
