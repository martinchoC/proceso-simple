<?php
// templates/impresion/recibo_a4.php — recibo de cobranza en hoja A4 siguiendo el formato de la factura a clientes.
// La incluye modules/*/cobranzas_print.php, que arma las variables ($cabecera, $medios, $imputaciones, $empresa, $cfg_impresion, ...)
// y los helpers h() / vfPrintFecha() / vfPrintImporte() / vfPrintCuit().
$fuente = impresion_fuente($cfg_impresion);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($titulo_tipo) ?> <?= h($numero) ?> - <?= h($cliente_nombre) ?></title>
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
        .cab-titulo { height: 44px; display: flex; align-items: center; gap: 8px; margin-bottom: 4px; overflow: hidden; }
        .logo { max-height: 44px; max-width: 55%; object-fit: contain; object-position: left center; display: block; flex: 0 0 auto; }
        .razon-social { font-size: 22px; font-weight: 600; line-height: 1.05; text-transform: uppercase; letter-spacing: .5px; }
        .tipo-doc { font-size: 20px; font-weight: 600; line-height: 1; letter-spacing: .5px; }
        .linea { margin: 0; }
        .bloque { border-bottom: 1px solid #000; padding: 6px 12px; }
        .bloque .row > div { margin-bottom: 2px; }
        .seccion-titulo { font-weight: 600; font-size: 11px; padding: 4px 12px; text-transform: uppercase; background: #f8f9fa; border-bottom: 1px solid #000; letter-spacing: .5px; }
        table.detalle { width: 100%; border-collapse: collapse; font-size: 11px; }
        table.detalle th { background: <?= h($color_acento) ?>; border-bottom: 1px solid #000; border-top: 1px solid #000; padding: 4px 6px; font-weight: 600; }
        table.detalle td { padding: 3px 6px; vertical-align: top; line-height: 1.2; border-bottom: 1px solid #f0f0f0; }
        table.detalle .num { text-align: right; white-space: nowrap; }
        .detalle-wrap { flex: initial; }
        .totales-wrap { border-top: 1px solid #000; padding: 8px 12px; display: flex; justify-content: flex-end; margin-top: auto; }
        table.totales td { padding: 2px 6px; font-size: 12px; }
        table.totales td.lbl { text-align: right; font-weight: 600; }
        table.totales td.imp { text-align: right; min-width: 140px; white-space: nowrap; }
        table.totales tr.total-final td { font-size: 15px; border-top: 1px solid #000; padding-top: 4px; }
        .observaciones { border-top: 1px solid #000; padding: 6px 12px; font-size: 11px; }
        .aviso-estado { border: 2px dashed #c00; color: #c00; text-align: center; font-weight: 600; padding: 5px; margin: 6px 12px; }
        .leyenda { font-size: 10px; color: #333; text-align: center; padding: 4px 12px; }
        .no-print-bar { max-width: 190mm; margin: 15px auto; }
        @media print {
            body { background: #fff; padding: 8mm; }
            .no-print, .no-print-bar { display: none !important; }
            .hoja { margin: 0; width: auto; min-height: 279mm; page-break-after: always; break-after: page; }
            .hoja:last-of-type { page-break-after: auto; break-after: auto; }
            table.detalle th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .seccion-titulo { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
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
            <div class="cab-titulo"><div class="tipo-doc"><?= h(mb_strtoupper($titulo_tipo)) ?></div></div>
            <div class="linea"><b>Punto de Venta:</b> <?= str_pad((string) $pto_vta, 5, '0', STR_PAD_LEFT) ?>
                &nbsp;&nbsp;<b>Comp. Nro:</b> <?= $nro > 0 ? str_pad((string) $nro, 8, '0', STR_PAD_LEFT) : '<em>sin numerar</em>' ?></div>
            <div class="linea"><b>Fecha de Emisión:</b> <?= vfPrintFecha($cabecera['f_emision']) ?></div>
            <div class="linea"><b>CUIT:</b> <?= h(vfPrintCuit($empresa_cuit)) ?></div>
            <div class="linea"><b>Ingresos Brutos:</b> <?= h($parametros['ingresos_brutos_nro'] ?? '') ?></div>
            <div class="linea"><b>Fecha de Inicio de Actividades:</b> <?= vfPrintFecha($parametros['inicio_actividades'] ?? '') ?></div>
        </div>
    </div>

    <?php if (!$confirmada): ?>
    <div class="aviso-estado">COMPROBANTE NO CONFIRMADO — <?= h(mb_strtoupper($cabecera['estado_registro'] ?? 'BORRADOR')) ?> — SIN VALIDEZ</div>
    <?php elseif ($anulada): ?>
    <div class="aviso-estado">COMPROBANTE ANULADO</div>
    <?php endif; ?>

    <div class="bloque">
        <div class="row">
            <div class="col-7"><b>Cliente:</b> <?= h($cliente_nombre) ?></div>
            <div class="col-5">
                <b><?= strlen($cliente_cuit) === 11 ? 'CUIT' : 'Doc.' ?>:</b>
                <?= strlen($cliente_cuit) === 11 ? h(vfPrintCuit($cliente_cuit)) : h($cliente_cuit ?: '-') ?>
            </div>
            <div class="col-7"><b>Domicilio:</b> <?= h($cliente_domicilio ?: 'Domicilio S/D') ?></div>
            <div class="col-5"><b>Condición frente al IVA:</b> <?= h($cliente_condicion_fiscal ?: 'Consumidor Final') ?></div>
            <?php if (!empty($cabecera['f_contabilidad']) && $cabecera['f_contabilidad'] !== $cabecera['f_emision']): ?>
            <div class="col-12"><b>Fecha Contable:</b> <?= vfPrintFecha($cabecera['f_contabilidad']) ?></div>
            <?php endif; ?>
        </div>
    </div>

    <!-- 1. Medios de Cobro y Valores Recibidos -->
    <div class="detalle-wrap">
        <div class="seccion-titulo">
            <i class="fas fa-money-bill-wave me-1"></i> Valores y Medios de Pago Recibidos
        </div>
        <table class="detalle">
            <thead>
                <tr>
                    <th style="width: 28%;">Medio de Pago</th>
                    <th>Detalle / Cuenta Destino / Datos Específicos</th>
                    <th class="num" style="width: 140px;">Importe</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($medios as $m): 
                    $detalle = $m['medio_pago'];
                    if ($m['medio_pago_tipo'] === 'EFECTIVO' && !empty($m['tesoreria_cuenta'])) {
                        $detalle = $m['tesoreria_cuenta'];
                    } elseif ($m['medio_pago_tipo'] === 'TRANSFERENCIA') {
                        $detalle = ($m['tesoreria_cuenta'] ?: 'Banco') . (!empty($m['nro_operacion'] ?? $m['numero_operacion']) ? ' - Op: ' . ($m['nro_operacion'] ?? $m['numero_operacion']) : '');
                    } elseif ($m['medio_pago_tipo'] === 'CHEQUE_TERCERO') {
                        $detalle = ($m['cheque_banco'] ?: 'Banco') . ' N° ' . ($m['cheque_nro'] ?: 'S/N') . (!empty($m['cheque_f_pago']) ? ' - Vto: ' . vfPrintFecha($m['cheque_f_pago']) : '');
                    } elseif ($m['medio_pago_tipo'] === 'RETENCION') {
                        $detRet = $m['impuesto_tipo'] ?: 'Retención';
                        if (!empty($m['jurisdiccion_nombre'])) $detRet .= ' - ' . $m['jurisdiccion_nombre'];
                        $detRet .= ' (Cert. N° ' . ($m['retencion_certificado_nro'] ?: 'S/N');
                        if (floatval($m['retencion_base_imponible'] ?? 0) > 0) $detRet .= ' · Base: $' . vfPrintImporte($m['retencion_base_imponible']);
                        if (floatval($m['retencion_alicuota'] ?? 0) > 0) $detRet .= ' @ ' . vfPrintImporte($m['retencion_alicuota']) . '%';
                        $detRet .= ')';
                        $detalle = $detRet;
                    }
                ?>
                <tr>
                    <td><b><?= h($m['medio_pago']) ?></b></td>
                    <td><?= h($detalle) ?></td>
                    <td class="num fw-bold"><?= h($simbolo) ?> <?= vfPrintImporte($m['importe_comprobante']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- 2. Comprobantes Imputados / Cancelados -->
    <div class="detalle-wrap" style="border-top: 1px solid #000;">
        <div class="seccion-titulo">
            <i class="fas fa-file-invoice-dollar me-1"></i> Comprobantes Cancelados / Imputados
        </div>
        <table class="detalle">
            <thead>
                <tr>
                    <th style="width: 35%;">Comprobante</th>
                    <th style="width: 15%; text-align: center;">Fecha Emisión</th>
                    <th class="num" style="width: 25%;">Total Comprobante</th>
                    <th class="num" style="width: 25%;">Importe Imputado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($imputaciones)): ?>
                    <?php foreach ($imputaciones as $imp): 
                        $tipoCmp = $imp['comprobante_tipo_codigo'] ?: 'COMP';
                        $pvImp = str_pad((string)intval($imp['comprobante_pv'] ?? 0), 4, '0', STR_PAD_LEFT);
                        $nroImp = str_pad((string)intval($imp['comprobante_nro'] ?? 0), 8, '0', STR_PAD_LEFT);
                        $nroFactura = "$tipoCmp $pvImp-$nroImp";
                    ?>
                    <tr>
                        <td><b><?= h($nroFactura) ?></b> <?= !empty($imp['comprobante_tipo']) ? '<small class="text-muted">(' . h($imp['comprobante_tipo']) . ')</small>' : '' ?></td>
                        <td style="text-align: center;"><?= vfPrintFecha($imp['destino_f_emision']) ?></td>
                        <td class="num"><?= h($simbolo) ?> <?= vfPrintImporte($imp['destino_total']) ?></td>
                        <td class="num fw-bold"><?= h($simbolo) ?> <?= vfPrintImporte($imp['importe_imputado']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center text-muted py-3">
                            <em>Cobro ingresado sin comprobantes aplicados (Cobro a cuenta / Anticipo a favor del cliente).</em>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($cabecera['observaciones'])): ?>
    <div class="observaciones"><b>Observaciones:</b> <?= nl2br(h($cabecera['observaciones'])) ?></div>
    <?php endif; ?>

    <div class="totales-wrap">
        <table class="totales">
            <tr><td class="lbl">Total Valores Recibidos: <?= h($simbolo) ?></td><td class="imp fw-bold"><?= vfPrintImporte($total_recibido) ?></td></tr>
            <tr><td class="lbl">Total Imputado a Comprobantes: <?= h($simbolo) ?></td><td class="imp"><?= vfPrintImporte($total_imputado) ?></td></tr>
            <?php if ($saldo_a_cuenta > 0.001): ?>
            <tr><td class="lbl" style="color: #0d6efd;">Saldo a Cuenta (A Favor): <?= h($simbolo) ?></td><td class="imp fw-bold" style="color: #0d6efd;"><?= vfPrintImporte($saldo_a_cuenta) ?></td></tr>
            <?php endif; ?>
            <tr class="total-final"><td class="lbl">TOTAL RECIBO: <?= h($simbolo) ?></td><td class="imp fw-bold"><?= vfPrintImporte($total_recibido) ?></td></tr>
        </table>
    </div>

    <?php if ($cfg_impresion['texto_pie'] !== ''): ?>
    <div class="observaciones"><?= nl2br(h($cfg_impresion['texto_pie'])) ?></div>
    <?php endif; ?>

    <div class="leyenda border-top border-dark fw-bold">DOCUMENTO NO VÁLIDO COMO FACTURA</div>

</div>
<?php endfor; ?>
</body>
</html>
