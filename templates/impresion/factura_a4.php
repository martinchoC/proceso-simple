<?php
// templates/impresion/factura_a4.php — factura de venta en hoja A4 (formato ARCA).
// La incluye modules/*/ventas_facturas_print.php, que arma las variables ($factura, $empresa, $cfg_impresion, ...)
// y los helpers h() / vfPrintFecha() / vfPrintImporte() / vfPrintCuit().
$ver_codigo = !empty($cfg_impresion['mostrar_codigo']);
$ver_bonif = !empty($cfg_impresion['mostrar_bonificacion']);
$fuente = impresion_fuente($cfg_impresion);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($factura['comprobante_tipo']) ?> <?= h($numero) ?> - <?= h($cliente_nombre) ?></title>
    <link href="<?= asset_local('css/bootstrap-5.3.0/bootstrap.min.css') ?>" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset_local('css/fontawesome-6.4.0/css/all.min.css') ?>">
    <?= $fuente['link'] ?>
    <style>
        @page { size: A4; margin: 0; }   /* sin margen el navegador no agrega fecha/título/URL */
        body { font-family: <?= $fuente['family'] ?>; font-weight: 400; font-size: 12px; color: #000; background: #e9ecef; }
        b, strong, th, .fw-bold { font-weight: 600 !important; }
        .hoja { background: #fff; width: 190mm; min-height: 270mm; margin: 0 auto 20px; padding: 0; display: flex; flex-direction: column; }
        .marco { border: 1px solid #000; }
        .cabecera { display: flex; position: relative; border-bottom: 1px solid #000; line-height: 1.3; }
        /* el recuadro de la letra (74px) queda centrado sobre el borde: cada lado le deja 46px libres */
        .cabecera .lado { flex: 1; padding: 8px 46px 6px 12px; font-size: 11px; }
        .cabecera .lado + .lado { border-left: 1px solid #000; padding: 8px 12px 6px 46px; }
        .letra-box { position: absolute; left: 50%; top: 0; transform: translateX(-50%); width: 74px; background: #fff; border: 1px solid #000; border-top: 0; text-align: center; z-index: 2; }
        .letra-box .letra { font-size: 34px; font-weight: 600; line-height: 40px; }
        .letra-box .cod { font-size: 9px; font-weight: 600; line-height: 1.1; }
        .letra-box .copia { font-size: 8px; font-weight: 600; line-height: 1.1; letter-spacing: .5px; border-top: 1px solid #000; padding: 2px 0; margin-top: 2px; }
        /* Fila de títulos de igual alto en los dos lados: logo (o razón social) | tipo de comprobante,
           así el tipo queda alineado con el logo y los datos de ambos lados arrancan a la misma altura */
        .cab-titulo { height: 44px; display: flex; align-items: center; gap: 8px; margin-bottom: 4px; overflow: hidden; }
        .logo { max-height: 44px; max-width: 55%; object-fit: contain; object-position: left center; display: block; flex: 0 0 auto; }
        .razon-social { font-size: 22px; font-weight: 600; line-height: 1.05; text-transform: uppercase; letter-spacing: .5px; }
        .tipo-doc { font-size: 20px; font-weight: 600; line-height: 1; letter-spacing: .5px; }
        .linea { margin: 0; }
        .bloque { border-bottom: 1px solid #000; padding: 6px 12px; }
        .bloque .row > div { margin-bottom: 2px; }
        table.detalle { width: 100%; border-collapse: collapse; font-size: 11px; }
        table.detalle th { background: <?= h($color_acento) ?>; border-bottom: 1px solid #000; border-top: 1px solid #000; padding: 4px 5px; font-weight: 600; }
        table.detalle td { padding: 1px 5px; vertical-align: top; line-height: 1.15; }
        table.detalle .num { text-align: right; white-space: nowrap; }
        table.detalle .leyenda-item { font-size: 10px; color: #444; }
        .detalle-wrap { flex: 1; }
        .totales-wrap { border-top: 1px solid #000; padding: 8px 12px; display: flex; justify-content: flex-end; }
        table.totales td { padding: 2px 6px; font-size: 12px; }
        table.totales td.lbl { text-align: right; font-weight: 600; }
        table.totales td.imp { text-align: right; min-width: 120px; white-space: nowrap; }
        table.totales tr.total-final td { font-size: 15px; border-top: 1px solid #000; padding-top: 4px; }
        .transparencia { border-top: 1px solid #000; padding: 6px 12px; font-size: 11px; }
        .observaciones { border-top: 1px solid #000; padding: 6px 12px; font-size: 11px; }
        .pie { border-top: 1px solid #000; padding: 8px 12px; display: flex; align-items: center; gap: 16px; }
        .pie .qr { width: 110px; height: 110px; flex: 0 0 110px; }
        .pie .qr img, .pie .qr canvas { width: 110px !important; height: 110px !important; }
        .pie .autorizado { flex: 1; }
        .pie .autorizado .titulo { font-size: 15px; font-weight: 600; font-style: italic; }
        .pie .cae { text-align: right; font-size: 13px; }
        .pie .cae b { display: inline-block; min-width: 150px; }
        .aviso-estado { border: 2px dashed #c00; color: #c00; text-align: center; font-weight: 600; padding: 5px; margin: 6px 12px; }
        .leyenda { font-size: 10px; color: #333; text-align: center; padding: 4px 12px; }
        .no-print-bar { max-width: 190mm; margin: 15px auto; }
        @media print {
            body { background: #fff; padding: 8mm; }
            .no-print, .no-print-bar { display: none !important; }
            .hoja { margin: 0; width: auto; min-height: 279mm; page-break-after: always; break-after: page; }
            .hoja:last-of-type { page-break-after: auto; break-after: auto; }
            table.detalle th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<div class="no-print-bar d-flex justify-content-between align-items-center">
    <div class="btn-group btn-group-sm" role="group">
        <?php foreach ([1 => 'Original', 2 => '+ Duplicado', 3 => '+ Triplicado'] as $n => $txt): ?>
        <a class="btn <?= $copias === $n ? 'btn-dark' : 'btn-outline-dark' ?>"
           href="<?= h($url_copias($n)) ?>"><?= h($txt) ?></a>
        <?php endforeach; ?>
    </div>
    <div>
        <button class="btn btn-primary btn-sm" onclick="window.print()"><i class="fas fa-print me-1"></i>Imprimir</button>
        <button class="btn btn-secondary btn-sm" onclick="window.close()"><i class="fas fa-times me-1"></i>Cerrar</button>
    </div>
</div>

<?php for ($c = 0; $c < $copias; $c++): ?>
<div class="hoja marco">
    <div class="cabecera">
        <div class="letra-box">
            <div class="letra"><?= h($letra) ?></div>
            <?php if ($fiscal): ?><div class="cod">COD. <?= str_pad((string) $arca_tipo, 3, '0', STR_PAD_LEFT) ?></div><?php endif; ?>
            <div class="copia"><?= $etiquetas_copia[$c] ?></div>
        </div>
        <div class="lado">
            <div class="cab-titulo">
                <?php if ($logo_src): ?><img class="logo" src="<?= h($logo_src) ?>" alt="Logo"><?php endif; ?>
                <div class="razon-social"><?= h($empresa['empresa'] ?? '') ?></div>
            </div>
            <div class="linea"><b>Domicilio Comercial:</b>
                <?= h(trim(implode(' - ', array_filter([$empresa['domicilio'] ?? '', $empresa['localidad'] ?? '', $empresa['provincia'] ?? ''])))) ?>
            </div>
            <?php if (!empty($sucursal['sucursal_nombre'])): ?>
            <div class="linea"><b>Sucursal:</b> <?= h($sucursal['sucursal_nombre']) ?><?= !empty($sucursal['direccion']) ? ' - ' . h($sucursal['direccion']) : '' ?></div>
            <?php endif; ?>
            <?php if (!empty($empresa['telefono']) || !empty($empresa['email'])): ?>
            <div class="linea"><?= !empty($empresa['telefono']) ? '<b>Tel.:</b> ' . h($empresa['telefono']) . ' ' : '' ?><?= !empty($empresa['email']) ? '<b>Email:</b> ' . h($empresa['email']) : '' ?></div>
            <?php endif; ?>
            <div class="linea"><b>Condición frente al IVA:</b> <?= h($empresa['condicion_fiscal'] ?? '') ?></div>
            <?php if (!empty($leyenda_emisor)): ?><div class="linea"><?= h($leyenda_emisor) ?></div><?php endif; ?>
        </div>
        <div class="lado">
            <div class="cab-titulo"><div class="tipo-doc"><?= h(mb_strtoupper($titulo_tipo)) ?></div></div>
            <div class="linea"><b>Punto de Venta:</b> <?= str_pad((string) $pto_vta, 5, '0', STR_PAD_LEFT) ?>
                &nbsp;&nbsp;<b>Comp. Nro:</b> <?= $nro > 0 ? str_pad((string) $nro, 8, '0', STR_PAD_LEFT) : '<em>sin numerar</em>' ?></div>
            <div class="linea"><b>Fecha de Emisión:</b> <?= vfPrintFecha($factura['f_emision']) ?></div>
            <div class="linea"><b>CUIT:</b> <?= h(vfPrintCuit($empresa_cuit)) ?></div>
            <div class="linea"><b>Ingresos Brutos:</b> <?= h($parametros['ingresos_brutos_nro'] ?? '') ?></div>
            <div class="linea"><b>Fecha de Inicio de Actividades:</b> <?= vfPrintFecha($parametros['inicio_actividades'] ?? '') ?></div>
        </div>
    </div>

    <?php if (!$confirmada): ?>
    <div class="aviso-estado">COMPROBANTE NO CONFIRMADO — <?= h(mb_strtoupper($factura['estado_registro'] ?? '')) ?> — SIN VALIDEZ</div>
    <?php elseif ($fiscal && !$con_cae): ?>
    <div class="aviso-estado">COMPROBANTE SIN CAE DE ARCA</div>
    <?php endif; ?>

    <div class="bloque">
        <div class="row">
            <div class="col-5"><b>Fecha Vto. para el pago:</b> <?= vfPrintFecha($factura['f_vto']) ?></div>
            <div class="col-7 text-end"><b>Condición de venta:</b> <?= h($factura['condicion_pago'] ?? '') ?></div>
        </div>
    </div>

    <div class="bloque">
        <div class="row">
            <div class="col-7"><b>Razón Social:</b> <?= h($cliente_nombre) ?></div>
            <div class="col-5">
                <b><?= strlen($cliente_cuit) === 11 ? 'CUIT' : 'Doc.' ?>:</b>
                <?= strlen($cliente_cuit) === 11 ? h(vfPrintCuit($cliente_cuit)) : h($cliente_cuit ?: '-') ?>
            </div>
            <div class="col-7"><b>Domicilio:</b> <?= h($cliente_domicilio) ?></div>
            <div class="col-5"><b>Condición frente al IVA:</b> <?= h($relacion['receptor_condicion_fiscal_nombre'] ?? 'Consumidor Final') ?></div>
            <?php if (!empty($cfg_impresion['mostrar_sucursal_cliente']) && !empty($cliente_sucursal['sucursal_nombre'])): ?>
            <div class="col-12"><b>Sucursal / Entrega:</b> <?= h($cliente_sucursal['sucursal_nombre']) ?>
                <?= !empty($cliente_sucursal['sucursal_direccion']) ? ' - ' . h($cliente_sucursal['sucursal_direccion']) : '' ?>
                <?= !empty($cliente_sucursal['localidad']) ? ' - ' . h($cliente_sucursal['localidad']) : '' ?></div>
            <?php endif; ?>
            <?php if ($moneda && empty($moneda['es_moneda_base'])): ?>
            <div class="col-12"><b>Moneda:</b> <?= h($moneda['moneda'] ?? '') ?> — <b>Tipo de cambio:</b> <?= number_format(floatval($factura['tipo_cambio']), 6, ',', '.') ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="detalle-wrap">
        <table class="detalle">
            <thead>
                <tr>
                    <?php if ($ver_codigo): ?><th>Código</th><?php endif; ?>
                    <th>Producto / Servicio</th>
                    <th class="num">Cantidad</th>
                    <th class="num">Precio Unit.</th>
                    <?php if ($ver_bonif): ?><th class="num">% Bonif</th><?php endif; ?>
                    <?php if ($discrimina_iva): ?>
                    <th class="num">Subtotal</th>
                    <th class="num">Alícuota IVA</th>
                    <th class="num">Subtotal c/IVA</th>
                    <?php else: ?>
                    <th class="num">Subtotal</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $d):
                    $cant = floatval($d['cantidad']);
                    $iva_pct = floatval($d['porcentaje_iva']);
                    // A: precios netos. B/C/X: precio final (con el IVA incluido, si lo hay).
                    $precio = floatval($d['precio_unitario']);
                    $precio_mostrar = $discrimina_iva ? $precio : $precio * (1 + ($es_c ? 0 : $iva_pct) / 100);
                    $subtotal_mostrar = $discrimina_iva ? floatval($d['importe_neto']) : floatval($d['importe_linea']);
                ?>
                <tr>
                    <?php if ($ver_codigo): ?><td><?= h($d['producto_codigo'] ?? '') ?></td><?php endif; ?>
                    <td><?= h($d['producto_nombre'] ?? '') ?><?php if (trim((string)($d['leyenda'] ?? '')) !== ''): ?><div class="leyenda-item"><?= nl2br(h($d['leyenda'])) ?></div><?php endif; ?></td>
                    <td class="num"><?= number_format($cant, $cant == floor($cant) ? 0 : 2, ',', '.') ?></td>
                    <td class="num"><?= vfPrintImporte($precio_mostrar) ?></td>
                    <?php if ($ver_bonif): ?><td class="num"><?= vfPrintImporte($d['descuento_general_pct'] ?? 0) ?></td><?php endif; ?>
                    <td class="num"><?= vfPrintImporte($subtotal_mostrar) ?></td>
                    <?php if ($discrimina_iva): ?>
                    <td class="num"><?= h(str_replace('.', ',', rtrim(rtrim(number_format($iva_pct, 2, '.', ''), '0'), '.'))) ?>%</td>
                    <td class="num"><?= vfPrintImporte($d['importe_linea']) ?></td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($factura['observaciones'])): ?>
    <div class="observaciones"><b>Observaciones:</b> <?= nl2br(h($factura['observaciones'])) ?></div>
    <?php endif; ?>

    <div class="totales-wrap">
        <table class="totales">
            <?php if ($discrimina_iva): ?>
            <tr><td class="lbl">Importe Neto Gravado: <?= h($simbolo) ?></td><td class="imp"><?= vfPrintImporte($neto_gravado) ?></td></tr>
            <?php foreach (array_reverse($iva_por_alicuota, true) as $al => $imp): if (round($imp, 2) == 0) continue; ?>
            <tr><td class="lbl">IVA <?= h(str_replace('.', ',', rtrim(rtrim((string)$al, '0'), '.'))) ?>%: <?= h($simbolo) ?></td><td class="imp"><?= vfPrintImporte($imp) ?></td></tr>
            <?php endforeach; ?>
            <?php if ($exento != 0): ?>
            <tr><td class="lbl">Importe Exento: <?= h($simbolo) ?></td><td class="imp"><?= vfPrintImporte($exento) ?></td></tr>
            <?php endif; ?>
            <?php if ($no_gravado != 0): ?>
            <tr><td class="lbl">Importe No Gravado: <?= h($simbolo) ?></td><td class="imp"><?= vfPrintImporte($no_gravado) ?></td></tr>
            <?php endif; ?>
            <?php else: ?>
            <tr><td class="lbl">Subtotal: <?= h($simbolo) ?></td><td class="imp"><?= vfPrintImporte($total - $otros) ?></td></tr>
            <?php endif; ?>
            <?php foreach ($percepciones_iibb as $p): ?>
            <tr><td class="lbl"><?= h($p['leyenda'] ?: ($p['descripcion'] ?? ('Percepción IIBB ' . $p['jurisdiccion_nombre']))) ?> <?= h($fmt_pct_perc($p['alicuota'])) ?>%: <?= h($simbolo) ?></td><td class="imp"><?= vfPrintImporte($p['importe']) ?></td></tr>
            <?php endforeach; ?>
            <?php // Solo lo que no se detalló arriba: $otros ya incluye las percepciones (como factura_ticket80.php) ?>
            <?php if (round($otros_nacionales, 2) != 0): ?>
            <tr><td class="lbl">Importe Otros Tributos: <?= h($simbolo) ?></td><td class="imp"><?= vfPrintImporte($otros_nacionales) ?></td></tr>
            <?php endif; ?>
            <tr class="total-final"><td class="lbl">Importe Total: <?= h($simbolo) ?></td><td class="imp fw-bold"><?= vfPrintImporte($total) ?></td></tr>
        </table>
    </div>

    <?php if ($letra === 'B' && $iva_total > 0): ?>
    <div class="transparencia">
        <b>Régimen de Transparencia Fiscal al Consumidor (Ley 27.743)</b><br>
        IVA Contenido: <?= h($simbolo) ?> <?= vfPrintImporte($iva_total) ?>
        <?php if ($otros_nacionales != 0): ?>&nbsp;&nbsp;&nbsp; Otros Impuestos Nacionales Indirectos: <?= h($simbolo) ?> <?= vfPrintImporte($otros_nacionales) ?><?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($leyendas_fiscales): ?>
    <div class="observaciones"><?= implode('<br>', array_map('h', $leyendas_fiscales)) ?></div>
    <?php endif; ?>

    <?php if ($cfg_impresion['texto_pie'] !== ''): ?>
    <div class="observaciones"><?= nl2br(h($cfg_impresion['texto_pie'])) ?></div>
    <?php endif; ?>

    <?php if ($con_cae): ?>
    <div class="pie">
        <div class="qr" id="qr-<?= $c ?>" data-qr="<?= h($qr_url) ?>"></div>
        <div class="autorizado">
            <div class="titulo">Comprobante Autorizado</div>
            <div class="small">Esta Agencia no se responsabiliza por los datos ingresados en el detalle de la operación.</div>
        </div>
        <div class="cae">
            <div><b>CAE N°:</b> <?= h($cae) ?></div>
            <div><b>Fecha de Vto. de CAE:</b> <?= vfPrintFecha($factura['f_vto_CAE'] ?? '') ?></div>
        </div>
    </div>
    <?php elseif (!$fiscal): ?>
    <div class="leyenda border-top border-dark fw-bold">DOCUMENTO NO VÁLIDO COMO FACTURA</div>
    <?php endif; ?>

</div>
<?php endfor; ?>

<?php if ($con_cae): ?>
<script src="<?= asset_local('js/qrcodejs-1.0.0/qrcode.min.js') ?>"></script>
<script>
    document.querySelectorAll('[data-qr]').forEach(function (el) {
        new QRCode(el, { text: el.getAttribute('data-qr'), width: 220, height: 220, correctLevel: QRCode.CorrectLevel.M });
    });
</script>
<?php endif; ?>
</body>
</html>
