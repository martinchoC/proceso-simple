<?php
// pagos_retencion_print.php — certificado de una retención practicada en una orden de pago (HTML para imprimir / PDF
// del navegador). Datos del agente, del retenido, régimen, base, alícuota, importe y comprobantes que cancela la OP.
// Mismo guard que pagos_ajax.php: sesión + empresa del usuario + acceso a la página de pagos.
require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['modulo_id' => 5, 'paginas_url' => ['pagos.php']]);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/pagos_model.php';
require_once __DIR__ . '/../../core/impresion.php';

$empresa_idx = intval($ctx['empresa_id']);
$retencion_id = intval($_GET['retencion_id'] ?? 0);
if ($retencion_id <= 0) {
    http_response_code(400);
    die('Retención no indicada');
}

$ret = perIibbFila($conexion,
    "SELECT r.*, it.impuesto_tipo, j.jurisdiccion_nombre, cfg.numero_agente
     FROM gestion__pagos_retenciones r
     JOIN gestion__impuestos_tipos it ON it.impuesto_tipo_id = r.impuesto_tipo_id
     LEFT JOIN gestion__jurisdicciones j ON j.jurisdiccion_id = r.jurisdiccion_id
     LEFT JOIN gestion__empresas_impuestos_config cfg ON cfg.empresa_impuesto_config_id = r.empresa_impuesto_config_id
     WHERE r.retencion_id = ? AND r.empresa_id = ?",
    'ii', $retencion_id, $empresa_idx);
if (!$ret || empty($ret['certificado_nro'])) {
    http_response_code(404);
    die('Certificado no encontrado (la orden de pago tiene que estar confirmada)');
}

$op = obtenerPagoDetalle($conexion, intval($ret['pago_id']), $empresa_idx);
if (!$op) {
    http_response_code(404);
    die('Orden de pago no encontrada');
}
$cab = $op['cabecera'];
if (!empty($cab['sucursal_id']) && file_exists(__DIR__ . '/../../core/accesos_sucursales.php')) {
    require_once __DIR__ . '/../../core/accesos_sucursales.php';
    try {
        AccesosSucursalesHelper::exigirAccesoSucursal($conexion, $empresa_idx, intval($cab['sucursal_id']));
    } catch (RuntimeException $e) {
        http_response_code(403);
        die('Sin acceso a la sucursal de esta orden de pago');
    }
}

$empresa = perIibbFila($conexion, "SELECT empresa, domicilio FROM conf__empresas WHERE empresa_id = ?", 'i', $empresa_idx);
$logo = impresion_logo_empresa($conexion, $empresa_idx);
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$m = function ($v) { return number_format(floatval($v), 2, ',', '.'); };
$cuit = function ($v) {
    $d = preg_replace('/\D/', '', (string)$v);
    return strlen($d) === 11 ? substr($d, 0, 2) . '-' . substr($d, 2, 8) . '-' . substr($d, 10) : $d;
};
$anulada = intval($ret['tabla_estado_registro_id']) === 38;
$nro_op = emp_numero_comprobante($conexion, $empresa_idx, intval($cab['comprobante_pv']), intval($cab['comprobante_nro']));
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Certificado de retención <?= $h(retPracCertificadoTexto($ret['certificado_nro'])) ?></title>
<style>
    body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; color: #000; margin: 0; background: #fff; }
    .hoja { max-width: 190mm; margin: 10mm auto; border: 1px solid #000; padding: 6mm; position: relative; }
    .cab { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #000; padding-bottom: 4mm; }
    .cab img { max-height: 18mm; max-width: 50mm; }
    h1 { font-size: 16px; margin: 0 0 2mm; text-align: right; }
    h2 { font-size: 12px; margin: 4mm 0 1mm; text-transform: uppercase; border-bottom: 1px solid #999; }
    table { width: 100%; border-collapse: collapse; }
    td, th { padding: 1mm 1.5mm; text-align: left; vertical-align: top; }
    .det th, .det td { border: 1px solid #999; }
    .num { text-align: right; white-space: nowrap; }
    .total { font-size: 14px; font-weight: bold; }
    .firma { margin-top: 18mm; display: flex; justify-content: flex-end; }
    .firma div { border-top: 1px solid #000; width: 70mm; text-align: center; padding-top: 1mm; }
    .anulada { position: absolute; top: 45%; left: 0; right: 0; text-align: center; font-size: 48px; color: rgba(200,0,0,.35); transform: rotate(-20deg); }
    .acciones { text-align: center; margin: 4mm; }
    @media print { .acciones { display: none; } .hoja { margin: 0 auto; border: none; } }
</style>
</head>
<body>
<div class="acciones"><button onclick="window.print()">Imprimir</button></div>
<div class="hoja">
    <?php if ($anulada): ?><div class="anulada">ANULADO</div><?php endif; ?>
    <div class="cab">
        <div>
            <?php if ($logo !== ''): ?><img src="<?= $h($logo) ?>" alt=""><br><?php endif; ?>
            <b><?= $h($empresa['empresa'] ?? '') ?></b><br>
            CUIT <?= $h($cuit(emp_cuit($conexion, $empresa_idx))) ?><br>
            <?= $h($empresa['domicilio'] ?? '') ?>
        </div>
        <div>
            <h1>CERTIFICADO DE RETENCIÓN</h1>
            <table>
                <tr><td>N°</td><td class="num"><b><?= $h(retPracCertificadoTexto($ret['certificado_nro'])) ?></b></td></tr>
                <tr><td>Fecha</td><td class="num"><?= $h(date('d/m/Y', strtotime($ret['f_retencion'] ?: $cab['f_emision']))) ?></td></tr>
                <tr><td>Orden de pago</td><td class="num"><?= $h($nro_op) ?></td></tr>
            </table>
        </div>
    </div>

    <h2>Agente de retención</h2>
    <table>
        <tr><td style="width:35%">Impuesto</td><td><?= $h($ret['impuesto_tipo']) ?><?= $ret['jurisdiccion_nombre'] ? ' - ' . $h($ret['jurisdiccion_nombre']) : '' ?></td></tr>
        <tr><td>N° de agente</td><td><?= $h($ret['numero_agente'] ?: '-') ?></td></tr>
        <tr><td>Régimen</td><td><?= $h($ret['regimen'] ?: '-') ?></td></tr>
    </table>

    <h2>Sujeto retenido</h2>
    <table>
        <tr><td style="width:35%">Razón social</td><td><?= $h($cab['entidad_nombre']) ?></td></tr>
        <tr><td>CUIT</td><td><?= $h($cuit($cab['cuit'] ?? '')) ?></td></tr>
        <tr><td>Domicilio</td><td><?= $h($cab['domicilio_legal'] ?? '') ?></td></tr>
    </table>

    <h2>Comprobantes que cancela el pago</h2>
    <table class="det">
        <tr><th>Comprobante</th><th>Fecha</th><th class="num">Total</th><th class="num">Importe pagado</th></tr>
        <?php foreach ($op['imputaciones'] as $i): ?>
        <tr>
            <td><?= $h($i['comprobante_tipo']) ?> <?= $h(sprintf('%04d-%08d', intval($i['comprobante_pv']), intval($i['comprobante_nro']))) ?></td>
            <td><?= $h(date('d/m/Y', strtotime($i['destino_f_emision']))) ?></td>
            <td class="num"><?= $m($i['destino_total']) ?></td>
            <td class="num"><?= $m($i['importe_imputado']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$op['imputaciones']): ?><tr><td colspan="4">Pago a cuenta, sin comprobantes imputados</td></tr><?php endif; ?>
    </table>

    <h2>Retención</h2>
    <table class="det">
        <tr><th>Base imponible</th><th class="num">Alícuota</th><th class="num">Importe retenido</th></tr>
        <tr>
            <td class="num"><?= $m($ret['base_imponible']) ?></td>
            <td class="num"><?= $h(rtrim(rtrim(number_format(floatval($ret['alicuota']), 4, ',', '.'), '0'), ',')) ?> %</td>
            <td class="num total">$ <?= $m($ret['importe']) ?></td>
        </tr>
    </table>

    <div class="firma"><div>Firma y aclaración del agente</div></div>
</div>
</body>
</html>
