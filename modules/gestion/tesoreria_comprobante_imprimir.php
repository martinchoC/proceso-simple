<?php
/**
 * Comprobante Soporte Imprimible de Tesorería (MC - Ingreso / Egreso contra Cuenta Contable).
 * Sirve como documento soporte de aportes de socios, rendimientos, préstamos u otros movimientos de caja/banco.
 */
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';
require_once __DIR__ . '/../../core/empresa_logo.php';
require_once __DIR__ . '/tesoreria_core_model.php';
require_once __DIR__ . '/tesoreria_movimientos_model.php';

if (session_status() === PHP_SESSION_NONE) session_start();
$empresa_id = intval($_SESSION['empresa_id'] ?? 0);
$comprobante_id = intval($_GET['id'] ?? 0);
if ($empresa_id <= 0 || $comprobante_id <= 0) {
    http_response_code(400);
    echo 'Comprobante inválido o sesión expirada.';
    exit;
}

$comp = obtenerOtroMovimientoPorId($conexion, $comprobante_id, $empresa_id);
if (!$comp) {
    http_response_code(404);
    echo 'Comprobante no encontrado.';
    exit;
}

$est = intval($comp['tabla_estado_registro_id']);
$est_badge = [
    3  => ['BORRADOR — SIN IMPACTO EN CAJA NI CONTABILIDAD', '#6c757d'],
    4  => ['PENDIENTE DE APROBACIÓN — SIN IMPACTO EN CAJA', '#fd7e14'],
    5  => ['CONFIRMADO / APROBADO', '#198754'],
    38 => ['ANULADO', '#dc3545'],
][$est] ?? ['ESTADO ' . $est, '#6c757d'];

$titulo_doc = $comp['tipo_movimiento'] === 'INGRESO'
    ? 'COMPROBANTE DE INGRESO DE TESORERÍA'
    : 'COMPROBANTE DE EGRESO DE TESORERÍA';
$logo = function_exists('empresa_logo_img_src') ? empresa_logo_img_src($conexion, $empresa_id) : '';
$h = static fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<title><?= $h($comp['numero_formateado']) ?> - <?= $h($titulo_doc) ?></title>
<style>
  body { font-family: Arial, Helvetica, sans-serif; color: #222; margin: 24px; background: #f5f6f8; }
  .hoja { max-width: 780px; margin: 0 auto; background: #fff; border: 1px solid #d0d5dd; border-radius: 6px; padding: 28px 32px; box-shadow: 0 2px 8px rgba(0,0,0,.06); }
  .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #222; padding-bottom: 14px; margin-bottom: 18px; }
  .logo { max-height: 54px; margin-bottom: 6px; }
  .emp-nombre { font-size: 18px; font-weight: bold; }
  .doc-box { text-align: right; }
  .doc-titulo { font-size: 14px; font-weight: bold; letter-spacing: .4px; color: #111; }
  .doc-nro { font-size: 20px; font-weight: bold; margin-top: 4px; font-family: monospace; }
  .badge { display: inline-block; margin-top: 6px; padding: 4px 10px; border-radius: 4px; color: #fff; font-size: 11px; font-weight: bold; }
  .grid { width: 100%; border-collapse: collapse; margin-bottom: 18px; }
  .grid td { padding: 8px 10px; border: 1px solid #e2e6ea; font-size: 13px; }
  .grid td.lbl { width: 220px; background: #f8f9fa; font-weight: bold; color: #444; }
  .importe-box { background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 6px; padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
  .importe-val { font-size: 24px; font-weight: bold; color: #0f172a; }
  .audit { font-size: 12px; color: #555; border-top: 1px dashed #ccc; padding-top: 12px; margin-bottom: 34px; line-height: 1.6; }
  .firmas { display: flex; justify-content: space-between; gap: 36px; margin-top: 28px; }
  .firma { flex: 1; text-align: center; border-top: 1px solid #444; padding-top: 6px; font-size: 12px; color: #444; }
  .acciones { max-width: 780px; margin: 0 auto 14px; text-align: right; }
  .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; font-weight: bold; }
  .btn-print { background: #0d6efd; color: #fff; }
  .btn-close { background: #6c757d; color: #fff; margin-left: 6px; }
  @media print {
    body { background: #fff; margin: 0; }
    .hoja { border: none; box-shadow: none; padding: 0; }
    .acciones { display: none; }
  }
</style>
</head>
<body>
  <div class="acciones">
    <button class="btn btn-print" onclick="window.print()">Imprimir / Guardar PDF</button>
    <button class="btn btn-close" onclick="window.close()">Cerrar</button>
  </div>
  <div class="hoja">
    <div class="header">
      <div>
        <?php if ($logo): ?><img src="<?= $h($logo) ?>" class="logo" alt="Logo"><br><?php endif; ?>
        <div class="emp-nombre"><?= $h($comp['empresa_nombre'] ?? '') ?></div>
        <?php if (!empty($comp['empresa_cuit'])): ?><div style="font-size:12px;color:#555;">CUIT: <?= $h($comp['empresa_cuit']) ?></div><?php endif; ?>
        <div style="font-size:12px;color:#555;">Sucursal: <?= $h($comp['sucursal'] ?? '') ?></div>
      </div>
      <div class="doc-box">
        <div class="doc-titulo"><?= $h($titulo_doc) ?></div>
        <div class="doc-nro"><?= $h($comp['numero_formateado']) ?></div>
        <div style="font-size:12px;color:#555;margin-top:2px;">Fecha: <?= $h(date('d/m/Y', strtotime($comp['f_emision']))) ?></div>
        <span class="badge" style="background:<?= $est_badge[1] ?>;"><?= $h($est_badge[0]) ?></span>
      </div>
    </div>

    <table class="grid">
      <tr>
        <td class="lbl">Tipo de Operación</td>
        <td><strong><?= $h($comp['tipo_movimiento']) ?></strong> (<?= $h($comp['comprobante_tipo'] ?: 'Movimiento de Caja') ?>)</td>
      </tr>
      <?php if (!empty($comp['entidad_nombre'])): ?>
      <tr>
        <td class="lbl">Socio / Entidad</td>
        <td><?= $h($comp['entidad_nombre']) ?><?= !empty($comp['entidad_cuit']) ? ' — CUIT/CUIL: ' . $h($comp['entidad_cuit']) : '' ?></td>
      </tr>
      <?php endif; ?>
      <tr>
        <td class="lbl">Cuenta de Tesorería</td>
        <td><?= $h($comp['cuenta_nombre']) ?></td>
      </tr>
      <tr>
        <td class="lbl">Cuenta Contable Contrapartida</td>
        <td><?= $h(trim(($comp['cont_codigo'] ?? '') . ' - ' . ($comp['cont_nombre'] ?? ''), ' -')) ?></td>
      </tr>
      <tr>
        <td class="lbl">Concepto / Detalle</td>
        <td><?= $h($comp['observaciones']) ?></td>
      </tr>
      <?php if (!empty($comp['observacion_aprobacion'])): ?>
      <tr>
        <td class="lbl">Observación de Revisión</td>
        <td><?= $h($comp['observacion_aprobacion']) ?></td>
      </tr>
      <?php endif; ?>
    </table>

    <div class="importe-box">
      <span style="font-size:14px;font-weight:bold;color:#334155;">IMPORTE TOTAL</span>
      <span class="importe-val"><?= $h($comp['moneda_simbolo'] ?: '$') ?> <?= number_format(floatval($comp['importe_total']), 2, ',', '.') ?></span>
    </div>

    <div class="audit">
      <div><strong>Cargado por:</strong> <?= $h($comp['usuario_creador'] ?: 'Sistema') ?> (<?= $h($comp['fecha_creacion']) ?>)</div>
      <?php if (!empty($comp['fecha_aprobacion'])): ?>
        <div><strong>Confirmado / Aprobado por:</strong> <?= $h($comp['usuario_aprobador'] ?: '-') ?> (<?= $h($comp['fecha_aprobacion']) ?>)</div>
      <?php endif; ?>
      <?php if (!empty($comp['asiento_id'])): ?>
        <div><strong>Asiento Contable N°:</strong> #<?= intval($comp['asiento_id']) ?></div>
      <?php endif; ?>
    </div>

    <div class="firmas">
      <div class="firma">Entregué / Recibí Conforme<br><span style="font-size:11px;color:#777;">Firma y Aclaración</span></div>
      <div class="firma">Responsable de Tesorería<br><span style="font-size:11px;color:#777;">Firma y Aclaración</span></div>
      <div class="firma">Aprobación Gerencia / Dirección<br><span style="font-size:11px;color:#777;">Firma y Aclaración</span></div>
    </div>
  </div>
</body>
</html>
