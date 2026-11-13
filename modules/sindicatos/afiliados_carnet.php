<?php
declare(strict_types=1);
require_once __DIR__ . '/../../core/assets.php';

/**
 * modules/sindicatos/afiliados_carnet.php
 * Credencial del afiliado (página 1050): tarjeta CR80 (85,6 x 54 mm), frente y dorso, en hoja A4 con marcas de corte.
 *   ?afiliacion_id=N          credencial de esa afiliación (titular o familiar)
 *   ?afiliacion_id=N&grupo=1  titular y familiares activos
 *   ?verificar=CODIGO         verificación del QR (personal del sindicato con sesión): firma y estado actual
 * El QR lleva un código firmado con la clave del servidor (sindi_carnet_codigo), no datos personales.
 */

require_once __DIR__ . '/../../core/ajax_guard.php';
$ctx = ajax_guard(['pagina_idx' => 1050]);
require_once __DIR__ . '/sindi_base_model.php';
sindi_contexto($ctx);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;
require_once __DIR__ . '/afiliados_model.php';
require_once __DIR__ . '/../../core/impresion.php';

$empresa_id = (int)$ctx['empresa_id'];

function h($v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
}

function carnet_fecha($f): string
{
    return $f ? date('d/m/Y', strtotime((string)$f)) : '';
}

function carnet_cuil($c): string
{
    $c = preg_replace('/\D/', '', (string)$c);
    return strlen($c) === 11 ? substr($c, 0, 2) . '-' . substr($c, 2, 8) . '-' . substr($c, 10) : $c;
}

function carnet_error(string $mensaje, int $codigo = 400): void
{
    http_response_code($codigo);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><title>Credencial</title></head><body style="font-family:sans-serif;padding:24px">'
        . '<p>' . h($mensaje) . '</p></body></html>';
    exit;
}

$stmt = mysqli_prepare($conexion, "SELECT empresa, documento_numero, domicilio, telefono, email FROM conf__empresas WHERE empresa_id = ?");
mysqli_stmt_bind_param($stmt, "i", $empresa_id);
mysqli_stmt_execute($stmt);
$empresa = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: [];
mysqli_stmt_close($stmt);

// ---------- Verificación del QR ----------
if (isset($_GET['verificar'])) {
    try {
        sindi_exigir_funcion($conexion, 1050, 'ver');
        $v = sindi_afiliados_verificar_carnet($conexion, $empresa_id, (string)$_GET['verificar']);
    } catch (RuntimeException $e) {
        carnet_error($e->getMessage(), 403);
    }
    $a = $v['afiliacion'] ?? [];
    $estado = [1 => 'Activa', 2 => 'Baja', 20 => 'Vencida'][(int)($a['tabla_estado_registro_id'] ?? 0)] ?? 'Sin estado';
    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificar credencial</title>
    <link href="<?= asset_local('css/bootstrap-5.3.0/bootstrap.min.css') ?>" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-4" style="max-width: 520px">
    <h5 class="mb-3"><?= h($empresa['empresa'] ?? '') ?> - Verificación de credencial</h5>
    <?php if (!$v['valida']): ?>
        <div class="alert alert-danger" id="resultado" data-resultado="INVALIDA"><strong>Credencial no válida.</strong> <?= h($v['motivo']) ?></div>
    <?php else: ?>
        <div class="alert <?= $v['vigente'] ? 'alert-success' : 'alert-warning' ?>" id="resultado" data-resultado="<?= $v['vigente'] ? 'VIGENTE' : 'NO_VIGENTE' ?>">
            <strong><?= $v['vigente'] ? 'Afiliación vigente.' : 'Credencial auténtica, pero la afiliación no está vigente.' ?></strong>
        </div>
        <table class="table table-sm bg-white">
            <tr><th>Afiliado</th><td><?= h($a['apellido'] . ' ' . $a['nombre']) ?></td></tr>
            <tr><th>CUIL</th><td><?= h(carnet_cuil($a['cuil'])) ?></td></tr>
            <tr><th>N° de afiliado</th><td><?= h($a['numero_afiliado']) ?></td></tr>
            <tr><th>Afiliación</th><td><?= h($a['parentesco'] ? $a['parentesco'] . ' de ' . $a['titular_nombre'] . ' (N° ' . $a['titular_numero'] . ')' : $a['tipo_nombre']) ?></td></tr>
            <tr><th>Estado</th><td><?= h($estado) ?><?= $a['f_baja'] ? ' desde ' . h(carnet_fecha($a['f_baja'])) : '' ?></td></tr>
            <?php if (!empty($a['f_vigencia'])): ?><tr><th>Cobertura hasta</th><td><?= h(carnet_fecha($a['f_vigencia'])) ?></td></tr><?php endif; ?>
            <tr><th>Credencial emitida</th><td><?= h(carnet_fecha($v['f_emision'])) ?></td></tr>
        </table>
        <p class="small text-muted">Pida el DNI para confirmar la identidad del portador.</p>
    <?php endif; ?>
</div>
</body>
</html>
    <?php
    exit;
}

// ---------- Impresión ----------
$afiliacion_id = (int)($_GET['afiliacion_id'] ?? 0);
if ($afiliacion_id <= 0) {
    carnet_error('Afiliación no indicada.');
}
try {
    sindi_exigir_funcion($conexion, 1050, 'carnet');
    $carnets = sindi_afiliados_carnets($conexion, $empresa_id, $afiliacion_id, !empty($_GET['grupo']));
} catch (RuntimeException $e) {
    carnet_error($e->getMessage(), 403);
}

$cfg = impresion_config($conexion, $empresa_id);
$fuente = impresion_fuente($cfg);
$acento = $cfg['color_acento'] ?: '#1f4e79';
$logo = $cfg['mostrar_logo'] ? impresion_logo_empresa($conexion, $empresa_id) : '';
$texto_dorso = $cfg['texto_pie'] !== '' ? $cfg['texto_pie']
    : 'Esta credencial es personal e intransferible. Debe presentarse junto con el documento de identidad.';
$contacto = array_filter([$empresa['domicilio'] ?? '', $empresa['telefono'] ?? '', $empresa['email'] ?? '']);

// URL de verificación (absoluta si hay servidor; en CLI solo el código)
$base_verificar = '';
if (!empty($_SERVER['HTTP_HOST'])) {
    $esquema = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $base_verificar = $esquema . '://' . $_SERVER['HTTP_HOST'] . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\') . '/afiliados_carnet.php?verificar=';
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Credencial <?= h($carnets[0]['numero_afiliado']) ?> - <?= h($carnets[0]['apellido']) ?></title>
    <?= $fuente['link'] ?>
    <style>
        @page { size: A4; margin: 10mm; }
        * { box-sizing: border-box; }
        body { font-family: <?= $fuente['family'] ?>; margin: 0; background: #e9ecef; color: #111; }
        .barra { background: #fff; padding: 10px 16px; display: flex; gap: 8px; align-items: center; border-bottom: 1px solid #ccc; }
        .barra button { padding: 6px 14px; cursor: pointer; }
        .hoja { width: 190mm; margin: 12px auto; background: #fff; padding: 6mm 0; }
        .par { display: flex; justify-content: center; gap: 6mm; margin-bottom: 6mm; break-inside: avoid; page-break-inside: avoid; }
        .tarjeta { width: 85.6mm; height: 54mm; border: 0.2mm dashed #999; border-radius: 3mm; overflow: hidden; position: relative; font-size: 2.6mm; }
        .frente .banda { background: <?= h($acento) ?>; color: #fff; height: 12mm; display: flex; align-items: center; gap: 2mm; padding: 0 3mm; }
        .frente .banda img { max-height: 9mm; max-width: 22mm; background: #fff; border-radius: 1mm; padding: 0.5mm; }
        .frente .banda .entidad { font-weight: 700; font-size: 2.9mm; line-height: 1.1; text-transform: uppercase; }
        .frente .banda .titulo { font-size: 2.1mm; letter-spacing: 0.3mm; opacity: .9; }
        .frente .cuerpo { display: flex; padding: 2.5mm 3mm 0; gap: 2mm; }
        .frente .datos { flex: 1; min-width: 0; line-height: 1.25; }
        .frente .nombre { font-size: 3.6mm; font-weight: 700; text-transform: uppercase; margin-bottom: 1mm; word-wrap: break-word; }
        .frente .etq { color: #555; font-size: 2.1mm; text-transform: uppercase; }
        .frente .numero { font-size: 4.4mm; font-weight: 700; letter-spacing: 0.3mm; color: <?= h($acento) ?>; }
        .frente .qr { width: 21mm; height: 21mm; flex: 0 0 21mm; }
        .frente .qr img, .frente .qr canvas { width: 21mm !important; height: 21mm !important; }
        .frente .pie { position: absolute; bottom: 0; left: 0; right: 0; padding: 1mm 3mm; font-size: 2.1mm; background: #f1f3f5; display: flex; justify-content: space-between; }
        .dorso { padding: 4mm; display: flex; flex-direction: column; justify-content: space-between; }
        .dorso .texto { font-size: 2.4mm; line-height: 1.35; }
        .dorso .firma { border-top: 0.2mm solid #333; width: 45mm; margin: 0 auto; text-align: center; font-size: 2.1mm; padding-top: 0.8mm; }
        .dorso .contacto { font-size: 2.1mm; color: #444; text-align: center; }
        .dorso .codigo { font-family: monospace; font-size: 1.9mm; color: #777; text-align: center; }
        @media print {
            body { background: #fff; }
            .barra { display: none; }
            .hoja { margin: 0; width: auto; padding: 0; }
            .tarjeta { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>
<div class="barra">
    <button type="button" onclick="window.print()">Imprimir</button>
    <span><?= count($carnets) ?> credencial(es). Cortar por la línea punteada; el dorso va a la derecha de cada frente.</span>
</div>
<div class="hoja">
    <?php foreach ($carnets as $c):
        $familiar = !empty($c['titular_afiliacion_id']);
        $qr = $base_verificar !== '' ? $base_verificar . rawurlencode($c['codigo']) : $c['codigo']; ?>
    <div class="par" data-afiliacion="<?= (int)$c['afiliacion_id'] ?>">
        <div class="tarjeta frente">
            <div class="banda">
                <?php if ($logo): ?><img src="<?= h($logo) ?>" alt=""><?php endif; ?>
                <div>
                    <div class="entidad"><?= h($empresa['empresa'] ?? '') ?></div>
                    <div class="titulo">CREDENCIAL DE AFILIADO<?= $familiar ? ' - GRUPO FAMILIAR' : '' ?></div>
                </div>
            </div>
            <div class="cuerpo">
                <div class="datos">
                    <div class="nombre"><?= h($c['apellido'] . ', ' . $c['nombre']) ?></div>
                    <div><span class="etq">CUIL</span> <?= h(carnet_cuil($c['cuil'])) ?></div>
                    <?php if ($familiar): ?>
                        <div><span class="etq"><?= h($c['parentesco']) ?> de</span> <?= h($c['titular_nombre']) ?></div>
                    <?php else: ?>
                        <div><span class="etq">Afiliación</span> <?= h($c['tipo_nombre']) ?></div>
                        <?php if (!empty($c['empleador_nombre'])): ?><div><span class="etq">Empleador</span> <?= h($c['empleador_nombre']) ?></div><?php endif; ?>
                    <?php endif; ?>
                    <div class="etq" style="margin-top:1mm">N° de afiliado</div>
                    <div class="numero"><?= h($c['numero_afiliado']) ?></div>
                </div>
                <div class="qr" data-qr="<?= h($qr) ?>"></div>
            </div>
            <div class="pie">
                <span><?= $familiar ? 'Titular N° ' . h($c['titular_numero']) : 'Afiliado desde ' . h(carnet_fecha($c['f_alta'])) ?></span>
                <span><?= !empty($c['f_vigencia']) ? 'Válida hasta ' . h(carnet_fecha($c['f_vigencia'])) : 'Emitida ' . h(carnet_fecha($c['f_emision'])) ?></span>
            </div>
        </div>
        <div class="tarjeta dorso">
            <div class="texto"><?= nl2br(h($texto_dorso)) ?></div>
            <div class="firma">Firma del afiliado</div>
            <div>
                <?php if ($contacto): ?><div class="contacto"><?= h(implode(' · ', $contacto)) ?></div><?php endif; ?>
                <div class="codigo"><?= h($c['codigo']) ?></div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<script src="<?= asset_local('js/qrcodejs-1.0.0/qrcode.min.js') ?>"></script>
<script>
    document.querySelectorAll('.qr[data-qr]').forEach(function (el) {
        new QRCode(el, { text: el.getAttribute('data-qr'), width: 240, height: 240, correctLevel: QRCode.CorrectLevel.M });
    });
</script>
</body>
</html>
