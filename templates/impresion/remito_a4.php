<?php
// templates/impresion/remito_a4.php — remito de venta en hoja A4.
// La incluye modules/*/ventas_remitos_print.php, que arma las variables ($remito, $empresa, $cfg_impresion, ...)
// y los helpers h() / vrPrintCuit() / vrPrintCantidad(). Fechas e importes con el formato de la empresa (core/formato.php).
// También la usa ventas_pedidos_print.php ($titulo_tipo = 'PEDIDO', $datos_extra con los datos del pedido y sin conformidad).
$datos_extra = $datos_extra ?? [];
$mostrar_conformidad = $mostrar_conformidad ?? true;
$ver_codigo = !empty($cfg_impresion['mostrar_codigo']);
$ver_bonif = !empty($cfg_impresion['mostrar_bonificacion']) && $valorizado;
$ver_pedido = !empty($pedidos_origen);
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
        @page { size: A4; margin: 0; }   /* sin margen el navegador no agrega fecha/título/URL */
        body { font-family: <?= $fuente['family'] ?>; font-weight: 400; font-size: 12px; color: #000; background: #e9ecef; }
        b, strong, th, .fw-bold { font-weight: 600 !important; }
        .hoja { background: #fff; width: 190mm; min-height: 270mm; margin: 0 auto 20px; padding: 0; display: flex; flex-direction: column; }
        .marco { border: 1px solid #000; }
        .cabecera { display: flex; position: relative; border-bottom: 1px solid #000; line-height: 1.3; }
        .cabecera .lado { flex: 1; padding: 8px 46px 6px 12px; font-size: 11px; }
        .cabecera .lado + .lado { border-left: 1px solid #000; padding: 8px 12px 6px 46px; }
        .letra-box { position: absolute; left: 50%; top: 0; transform: translateX(-50%); width: 74px; background: #fff; border: 1px solid #000; border-top: 0; text-align: center; z-index: 2; }
        .letra-box .letra { font-size: 34px; font-weight: 600; line-height: 40px; }
        .letra-box .cod { font-size: 9px; font-weight: 600; line-height: 1.1; }
        .letra-box .copia { font-size: 8px; font-weight: 600; line-height: 1.1; letter-spacing: .5px; border-top: 1px solid #000; padding: 2px 0; margin-top: 2px; }
        .cab-titulo { height: 44px; display: flex; align-items: center; gap: 8px; margin-bottom: 4px; overflow: hidden; }
        .logo { max-height: 44px; max-width: 55%; object-fit: contain; object-position: left center; display: block; flex: 0 0 auto; }
        .razon-social { font-size: 22px; font-weight: 600; line-height: 1.05; text-transform: uppercase; letter-spacing: .5px; }
        .tipo-doc { font-size: 20px; font-weight: 600; line-height: 1; letter-spacing: .5px; }
        .linea { margin: 0; }
        .bloque { border-bottom: 1px solid #000; padding: 6px 12px; }
        .bloque .row > div { margin-bottom: 2px; }
        table.detalle { width: 100%; border-collapse: collapse; font-size: 11px; }
        table.detalle th { background: <?= h($color_acento) ?>; border-bottom: 1px solid #000; padding: 4px 5px; font-weight: 600; }
        table.detalle td { padding: 1px 5px; vertical-align: top; line-height: 1.15; }
        table.detalle .num { text-align: right; white-space: nowrap; }
        .detalle-wrap { flex: 1; }
        .totales-wrap { border-top: 1px solid #000; padding: 8px 12px; display: flex; justify-content: space-between; align-items: flex-start; }
        table.totales td { padding: 2px 6px; font-size: 12px; }
        table.totales td.lbl { text-align: right; font-weight: 600; }
        table.totales td.imp { text-align: right; min-width: 120px; white-space: nowrap; }
        table.totales tr.total-final td { font-size: 15px; border-top: 1px solid #000; padding-top: 4px; }
        .observaciones { border-top: 1px solid #000; padding: 6px 12px; font-size: 11px; }
        .conformidad { border-top: 1px solid #000; padding: 10px 12px 8px; display: flex; gap: 24px; font-size: 11px; }
        .conformidad .titulo { font-weight: 600; margin-bottom: 26px; }
        .conformidad .firma { flex: 1; border-top: 1px solid #000; padding-top: 2px; text-align: center; }
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
    <div class="d-flex gap-2">
        <div class="btn-group btn-group-sm" role="group">
            <?php foreach ([1 => 'Original', 2 => '+ Duplicado', 3 => '+ Triplicado'] as $n => $txt): ?>
            <a class="btn <?= $copias === $n ? 'btn-dark' : 'btn-outline-dark' ?>"
               href="<?= h($url_copias($n)) ?>"><?= h($txt) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="btn-group btn-group-sm" role="group">
            <a class="btn <?= !$valorizado ? 'btn-dark' : 'btn-outline-dark' ?>" href="<?= h($url_copias($copias, false)) ?>">Sin precios</a>
            <a class="btn <?= $valorizado ? 'btn-dark' : 'btn-outline-dark' ?>" href="<?= h($url_copias($copias, true)) ?>">Valorizado</a>
        </div>
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
            <?php if ($fiscal): ?><div class="cod">COD. 091</div><?php endif; ?>
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
        </div>
        <div class="lado">
            <div class="cab-titulo"><div class="tipo-doc"><?= h($titulo_tipo) ?></div></div>
            <div class="linea"><b>Punto de Venta:</b> <?= str_pad((string) $pto_vta, 4, '0', STR_PAD_LEFT) ?>
                &nbsp;&nbsp;<b>Comp. Nro:</b> <?= $nro > 0 ? str_pad((string) $nro, 8, '0', STR_PAD_LEFT) : '<em>sin numerar</em>' ?></div>
            <div class="linea"><b>Fecha de Emisión:</b> <?= h(fmt_fecha($remito['f_emision'])) ?></div>
            <div class="linea"><b>CUIT:</b> <?= h(vrPrintCuit($empresa_cuit)) ?></div>
            <div class="linea"><b>Ingresos Brutos:</b> <?= h($parametros['ingresos_brutos_nro'] ?? '') ?></div>
            <div class="linea"><b>Fecha de Inicio de Actividades:</b> <?= h(fmt_fecha($parametros['inicio_actividades'] ?? '')) ?></div>
        </div>
    </div>

    <?php if (!$confirmado): ?>
    <div class="aviso-estado"><?= h($titulo_tipo) ?> NO CONFIRMADO — <?= h(mb_strtoupper($remito['estado_registro'] ?? '')) ?> — SIN VALIDEZ</div>
    <?php endif; ?>

    <div class="bloque">
        <div class="row">
            <div class="col-7"><b>Razón Social:</b> <?= h($cliente_nombre) ?></div>
            <div class="col-5">
                <b><?= strlen($cliente_cuit) === 11 ? 'CUIT' : 'Doc.' ?>:</b>
                <?= strlen($cliente_cuit) === 11 ? h(vrPrintCuit($cliente_cuit)) : h($cliente_cuit ?: '-') ?>
            </div>
            <div class="col-7"><b>Domicilio:</b> <?= h($cliente_domicilio) ?></div>
            <div class="col-5"><b>Condición frente al IVA:</b> <?= h($cliente_condicion) ?></div>
            <?php if (!empty($cliente_sucursal['sucursal_nombre'])): ?>
            <div class="col-12"><b>Lugar de entrega:</b> <?= h($cliente_sucursal['sucursal_nombre']) ?>
                <?= !empty($cliente_sucursal['sucursal_direccion']) ? ' - ' . h($cliente_sucursal['sucursal_direccion']) : '' ?>
                <?= !empty($cliente_sucursal['localidad']) ? ' - ' . h($cliente_sucursal['localidad']) : '' ?></div>
            <?php endif; ?>
            <?php foreach ($datos_extra as $etiqueta => $valor): ?>
            <div class="<?= $etiqueta === 'Lugar de entrega' ? 'col-12' : 'col-4' ?>"><b><?= h($etiqueta) ?>:</b> <?= h($valor) ?></div>
            <?php endforeach; ?>
            <?php if ($ver_pedido): ?>
            <div class="col-12"><b>Pedido<?= count($pedidos_origen) > 1 ? 's' : '' ?>:</b> <?= h(implode(', ', $pedidos_origen)) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <div class="detalle-wrap">
        <table class="detalle">
            <thead>
                <tr>
                    <?php if ($ver_codigo): ?><th>Código</th><?php endif; ?>
                    <th>Producto</th>
                    <?php if ($ver_pedido): ?><th class="num">Pedido</th><?php endif; ?>
                    <th class="num">Cantidad</th>
                    <?php if ($valorizado): ?>
                    <th class="num">Precio Unit.</th>
                    <?php if ($ver_bonif): ?><th class="num">% Bonif</th><?php endif; ?>
                    <th class="num">Subtotal</th>
                    <th class="num">Alícuota IVA</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($detalles as $d): ?>
                <tr>
                    <?php if ($ver_codigo): ?><td><?= h($d['producto_codigo'] ?? '') ?></td><?php endif; ?>
                    <td><?= h($d['producto_nombre'] ?? '') ?></td>
                    <?php if ($ver_pedido): ?><td class="num"><?= !empty($d['pedido_comprobante_nro']) ? h($d['pedido_comprobante_nro']) : '' ?></td><?php endif; ?>
                    <td class="num"><?= h(vrPrintCantidad($d['cantidad'])) ?></td>
                    <?php if ($valorizado): ?>
                    <td class="num"><?= h(fmt_numero($ver_bonif ? $d['precio_unitario_bruto'] : $d['precio_unitario_neto'])) ?></td>
                    <?php if ($ver_bonif): ?><td class="num"><?= h(fmt_numero($d['descuento_general_pct'])) ?></td><?php endif; ?>
                    <td class="num"><?= h(fmt_numero($d['importe_linea'])) ?></td>
                    <td class="num"><?= h(fmt_numero($d['iva_porcentaje'])) ?>%</td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($remito['observaciones'])): ?>
    <div class="observaciones"><b>Observaciones:</b> <?= nl2br(h($remito['observaciones'])) ?></div>
    <?php endif; ?>

    <div class="totales-wrap">
        <div><b>Total de unidades:</b> <?= h(vrPrintCantidad($total_unidades)) ?> &nbsp;&nbsp; <b>Ítems:</b> <?= count($detalles) ?></div>
        <?php if ($valorizado): ?>
        <table class="totales">
            <tr><td class="lbl">Importe Neto:</td><td class="imp"><?= h(fmt_numero($neto)) ?></td></tr>
            <tr><td class="lbl">IVA:</td><td class="imp"><?= h(fmt_numero($iva)) ?></td></tr>
            <tr class="total-final"><td class="lbl">Importe Total:</td><td class="imp fw-bold"><?= h(fmt_numero($total)) ?></td></tr>
        </table>
        <?php endif; ?>
    </div>

    <?php if ($cfg_impresion['texto_pie'] !== ''): ?>
    <div class="observaciones"><?= nl2br(h($cfg_impresion['texto_pie'])) ?></div>
    <?php endif; ?>

    <?php if ($mostrar_conformidad): ?>
    <div class="conformidad">
        <div class="flex-fill">
            <div class="titulo">Recibí conforme la mercadería detallada</div>
            <div class="d-flex gap-4">
                <div class="firma">Firma</div>
                <div class="firma">Aclaración</div>
                <div class="firma">DNI</div>
                <div class="firma">Fecha</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="leyenda border-top border-dark fw-bold">DOCUMENTO NO VÁLIDO COMO FACTURA</div>
</div>
<?php endfor; ?>
</body>
</html>
