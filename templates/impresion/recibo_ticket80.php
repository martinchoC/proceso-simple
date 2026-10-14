<?php
// templates/impresion/recibo_ticket80.php — recibo de cobranza en ticket de 80 mm.
// La incluye modules/*/cobranzas_print.php
$fuente = impresion_fuente($cfg_impresion);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($titulo_tipo) ?> <?= h($numero) ?> - <?= h($cliente_nombre) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <?= $fuente['link'] ?>
    <style>
        @page { size: 80mm auto; margin: 0; }
        body { font-family: <?= $fuente['family'] ?>; font-weight: 400; font-size: 11px; color: #000; background: #e9ecef; }
        b, strong, .fw-bold { font-weight: 600 !important; }
        .ticket { background: #fff; width: 72mm; margin: 0 auto 16px; padding: 3mm 2mm; }
        .centro { text-align: center; }
        .sep { border-top: 1px dashed #000; margin: 4px 0; }
        .logo { max-width: 40mm; max-height: 18mm; object-fit: contain; display: block; margin: 0 auto 3px; }
        .razon-social { font-size: 13px; font-weight: 600; }
        .letra { display: inline-block; border: 1px solid #000; font-size: 20px; font-weight: 600; width: 32px; line-height: 30px; }
        .tipo-doc { font-size: 13px; font-weight: 600; }
        .copia { font-size: 9px; font-weight: 600; letter-spacing: 1px; }
        .linea { line-height: 1.25; }
        table.items { width: 100%; border-collapse: collapse; font-size: 10.5px; }
        table.items td { padding: 1px 0; vertical-align: top; line-height: 1.15; }
        .num { text-align: right; white-space: nowrap; }
        table.totales { width: 100%; font-size: 11px; }
        table.totales td { padding: 1px 0; }
        table.totales tr.total-final td { font-size: 13px; font-weight: 600; border-top: 1px solid #000; padding-top: 2px; }
        .aviso-estado { border: 1px dashed #000; text-align: center; font-weight: 600; padding: 2px; margin: 3px 0; font-size: 10px; }
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
        <div class="tipo-doc"><?= h(mb_strtoupper($titulo_tipo)) ?></div>
        <div class="linea">Nro: <?= $nro > 0 ? h($numero) : '<em>sin numerar</em>' ?></div>
        <div class="linea">Fecha: <?= vfPrintFecha($cabecera['f_emision']) ?></div>
        <div class="copia"><?= $etiquetas_copia[$c] ?></div>
    </div>

    <?php if (!$confirmada): ?>
    <div class="aviso-estado">NO CONFIRMADO — <?= h(mb_strtoupper($cabecera['estado_registro'] ?? '')) ?> — SIN VALIDEZ</div>
    <?php elseif ($anulada): ?>
    <div class="aviso-estado">COMPROBANTE ANULADO</div>
    <?php endif; ?>

    <div class="sep"></div>
    <div class="linea"><b>Cliente:</b> <?= h($cliente_nombre) ?></div>
    <div class="linea"><b><?= strlen($cliente_cuit) === 11 ? 'CUIT' : 'Doc.' ?>:</b> <?= strlen($cliente_cuit) === 11 ? h(vfPrintCuit($cliente_cuit)) : h($cliente_cuit ?: '-') ?></div>
    <div class="linea"><b>Cond. IVA:</b> <?= h($cliente_condicion_fiscal ?: 'Consumidor Final') ?></div>

    <div class="sep"></div>
    <div class="fw-bold mb-1">VALORES RECIBIDOS:</div>
    <table class="items">
        <?php foreach ($medios as $m): 
            $detalle = $m['medio_pago'];
            if ($m['medio_pago_tipo'] === 'EFECTIVO' && !empty($m['tesoreria_cuenta'])) {
                $detalle .= " ({$m['tesoreria_cuenta']})";
            } elseif ($m['medio_pago_tipo'] === 'TRANSFERENCIA') {
                $detalle .= " (" . ($m['tesoreria_cuenta'] ?: 'Banco') . (!empty($m['nro_operacion'] ?? $m['numero_operacion']) ? ' Op:' . ($m['nro_operacion'] ?? $m['numero_operacion']) : '') . ")";
            } elseif ($m['medio_pago_tipo'] === 'CHEQUE_TERCERO') {
                $detalle .= " (" . ($m['cheque_banco'] ?: 'Banco') . " N° " . ($m['cheque_nro'] ?: 'S/N') . ")";
            }
        ?>
        <tr>
            <td><?= h($detalle) ?></td>
            <td class="num fw-bold"><?= h($simbolo) ?> <?= vfPrintImporte($m['importe_comprobante']) ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <div class="sep"></div>
    <div class="fw-bold mb-1">COMPROBANTES IMPUTADOS:</div>
    <table class="items">
        <?php if (!empty($imputaciones)): ?>
            <?php foreach ($imputaciones as $imp): 
                $tipoCmp = $imp['comprobante_tipo_codigo'] ?: 'COMP';
                $pvImp = str_pad((string)intval($imp['comprobante_pv'] ?? 0), 4, '0', STR_PAD_LEFT);
                $nroImp = str_pad((string)intval($imp['comprobante_nro'] ?? 0), 8, '0', STR_PAD_LEFT);
            ?>
            <tr>
                <td><?= h("$tipoCmp $pvImp-$nroImp") ?></td>
                <td class="num"><?= h($simbolo) ?> <?= vfPrintImporte($imp['importe_imputado']) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr><td colspan="2" class="text-center text-muted">A cuenta / anticipo</td></tr>
        <?php endif; ?>
    </table>

    <div class="sep"></div>
    <table class="totales">
        <tr><td>Total Valores:</td><td class="num"><?= h($simbolo) ?> <?= vfPrintImporte($total_recibido) ?></td></tr>
        <tr><td>Total Imputado:</td><td class="num"><?= h($simbolo) ?> <?= vfPrintImporte($total_imputado) ?></td></tr>
        <?php if ($saldo_a_cuenta > 0.001): ?>
        <tr><td>A Cuenta (A favor):</td><td class="num fw-bold"><?= h($simbolo) ?> <?= vfPrintImporte($saldo_a_cuenta) ?></td></tr>
        <?php endif; ?>
        <tr class="total-final"><td>TOTAL RECIBO:</td><td class="num"><?= h($simbolo) ?> <?= vfPrintImporte($total_recibido) ?></td></tr>
    </table>

    <?php if (!empty($cabecera['observaciones'])): ?>
    <div class="sep"></div>
    <div class="linea"><b>Obs:</b> <?= nl2br(h($cabecera['observaciones'])) ?></div>
    <?php endif; ?>

    <?php if ($cfg_impresion['texto_pie'] !== ''): ?>
    <div class="sep"></div>
    <div class="linea centro"><?= nl2br(h($cfg_impresion['texto_pie'])) ?></div>
    <?php endif; ?>

    <div class="sep"></div>
    <div class="centro fw-bold" style="font-size: 8px;">DOCUMENTO NO VÁLIDO COMO FACTURA</div>
</div>
<?php endfor; ?>
</body>
</html>
