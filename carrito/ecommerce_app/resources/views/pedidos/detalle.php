<?php
/**
 * @var array<string,mixed> $cabecera
 * @var array<int,array<string,mixed>> $detalles
 * @var array<int,array<string,mixed>> $remitos
 * @var \App\Services\SessionGuard $auth
 */
$puntoVenta = (string) ($cabecera['punto_venta_id'] ?? $cabecera['comprobante_pv'] ?? 1);
$numero = str_pad($puntoVenta, 4, '0', STR_PAD_LEFT)
    . '-' . str_pad((string) $cabecera['comprobante_nro'], 8, '0', STR_PAD_LEFT);

$remitos = $remitos ?? [];
$cantRemitos = count($remitos);
$cantProductos = count($detalles);

$estadoId = (int) ($cabecera['tabla_estado_registro_id'] ?? 0);
$estadoNombre = strtolower(trim((string) ($cabecera['estado_registro'] ?? '')));
$esConfirmado = ($estadoId === \App\Support\Config::int('estados.confirmado', 5))
    || str_contains($estadoNombre, 'confirm')
    || $cantRemitos > 0;

echo $this->partial('layouts/header', [
    'titulo' => 'Pedido ' . $numero,
    'auth' => $auth,
    'seccion' => 'pedidos',
]);
?>

<?= $this->partial('layouts/flash', ['flash' => $flash ?? []]) ?>

<p class="migas"><a href="<?= e(url('/pedidos')) ?>">Mis pedidos</a> &rsaquo; <?= e($numero) ?></p>

<div class="pedido-detalle-header">
  <div>
    <h1>Pedido <?= e($numero) ?></h1>
    <div class="pedido-detalle-meta">
      <span>Fecha de emisión: <strong><?= e(date('d/m/Y', strtotime((string) $cabecera['f_emision']))) ?></strong></span>
      <?php if (!empty($cabecera['sucursal_compra_nombre'])): ?>
        <span class="meta-sucursal-entrega">
          Sucursal de compra: <strong><?= e($cabecera['sucursal_compra_nombre']) ?></strong>
        </span>
      <?php endif; ?>
      <?php if (!empty($cabecera['sucursal_entrega_nombre'])): ?>
        <span class="meta-sucursal-entrega">
          Sucursal de entrega: <strong><?= e($cabecera['sucursal_entrega_nombre']) ?></strong><?= !empty($cabecera['direccion_entrega']) ? ' <small class="meta-sucursal-dir">(' . e($cabecera['direccion_entrega']) . ')</small>' : '' ?>
        </span>
      <?php endif; ?>
      <span class="estado <?= $esConfirmado ? 'estado-confirmado' : '' ?>"><?= e($cabecera['estado_registro'] ?? '-') ?></span>
    </div>
  </div>
</div>

<?php if ($esConfirmado): ?>
  <div class="pedido-tabs-wrapper">
    <nav class="pedido-tabs" role="tablist" aria-label="Secciones del pedido">
      <button type="button" class="pedido-tab-btn activo" data-tab-btn="tab-productos" role="tab" aria-selected="true" aria-controls="tab-productos">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <path d="m7.5 4.27 9 5.15"/>
          <path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/>
          <path d="m3.3 7 8.7 5 8.7-5"/>
          <path d="M12 22V12"/>
        </svg>
        <span>Productos</span>
        <span class="pedido-tab-badge"><?= $cantProductos ?></span>
      </button>

      <button type="button" class="pedido-tab-btn" data-tab-btn="tab-remitos" role="tab" aria-selected="false" aria-controls="tab-remitos">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <rect x="1" y="3" width="15" height="13"/>
          <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"/>
          <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
        </svg>
        <span>Remitos</span>
        <span class="pedido-tab-badge"><?= $cantRemitos ?></span>
      </button>
    </nav>
  </div>
<?php endif; ?>

<div id="tab-productos" class="pedido-tab-panel" data-tab-panel role="tabpanel">
  <div class="panel">
    <?php if (!empty($cabecera['observaciones'])): ?>
      <p class="pedido-observaciones"><strong>Observaciones:</strong> <?= e($cabecera['observaciones']) ?></p>
    <?php endif; ?>

    <table class="tabla">
      <thead>
        <tr>
          <th scope="col">Producto</th>
          <th scope="col" class="num">Cantidad</th>
          <th scope="col" class="num">Precio neto</th>
          <th scope="col" class="num">IVA</th>
          <th scope="col" class="num">Total</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($detalles as $detalle): ?>
          <tr>
            <td>
              <div class="pedido-producto-fila">
                <div class="pedido-producto-codigo-wrap">
                  <span class="producto-codigo"><?= e($detalle['producto_codigo']) ?></span>
                </div>
                <div class="pedido-producto-info-wrap">
                  <strong class="pedido-producto-nombre"><?= e($detalle['producto_nombre']) ?></strong>
                  <?php $compat = $detalle['compatibilidad'] ?? null; ?>
                  <?php if ($compat !== null && (!empty($compat['combinaciones']) || !empty($compat['marcas']) || !empty($compat['modelos']))): ?>
                    <?php if (!empty($compat['combinaciones'])): ?>
                      <div class="linea-compat-pills">
                        <?php foreach ($compat['combinaciones'] as $combo): ?>
                          <div class="linea-compat-grupo">
                            <?php if (!empty($combo['marca'])): ?>
                              <span class="badge-auto badge-marca"><?= e($combo['marca']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($combo['modelo'])): ?>
                              <span class="badge-auto badge-modelo"><?= e($combo['modelo']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($combo['submodelo'])): ?>
                              <span class="badge-auto badge-submodelo"><?= e($combo['submodelo']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($combo['anio'])): ?>
                              <span class="badge-auto badge-anio"><?= e($combo['anio']) ?></span>
                            <?php endif; ?>
                          </div>
                        <?php endforeach; ?>
                      </div>
                    <?php else: ?>
                      <div class="linea-compat-pills">
                        <div class="linea-compat-grupo">
                          <?php foreach ($compat['marcas'] as $m): ?>
                            <span class="badge-auto badge-marca"><?= e($m) ?></span>
                          <?php endforeach; ?>
                          <?php foreach ($compat['modelos'] as $mo): ?>
                            <span class="badge-auto badge-modelo"><?= e($mo) ?></span>
                          <?php endforeach; ?>
                          <?php foreach ($compat['submodelos'] as $sm): ?>
                            <span class="badge-auto badge-submodelo"><?= e($sm) ?></span>
                          <?php endforeach; ?>
                          <?php foreach ($compat['anios'] as $an): ?>
                            <span class="badge-auto badge-anio"><?= e($an) ?></span>
                          <?php endforeach; ?>
                        </div>
                      </div>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td class="num"><?= e(number_format((float) $detalle['cantidad'], 2, ',', '.')) ?></td>
            <td class="num"><?= e(money((float) $detalle['precio_unitario_neto'])) ?></td>
            <td class="num"><?= e(money((float) $detalle['iva_importe'])) ?></td>
            <td class="num"><?= e(money((float) $detalle['total_linea'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
      <tfoot>
        <tr><th colspan="4" class="num">Subtotal neto</th><td class="num"><?= e(money((float) $cabecera['subtotal'])) ?></td></tr>
        <tr><th colspan="4" class="num">IVA</th><td class="num"><?= e(money((float) $cabecera['impuestos'])) ?></td></tr>
        <tr><th colspan="4" class="num">Total</th><td class="num"><strong><?= e(money((float) $cabecera['total'])) ?></strong></td></tr>
      </tfoot>
    </table>
  </div>
</div>

<?php if ($esConfirmado): ?>
  <div id="tab-remitos" class="pedido-tab-panel oculto" data-tab-panel role="tabpanel">
    <div class="panel remitos-panel">
      <div class="remitos-cabecera-bloque">
        <div class="remitos-cabecera-titulo">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="1" y="3" width="15" height="13"/>
            <polygon points="16 8 20 8 23 11 23 16 16 16 8"/>
            <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
          </svg>
          <span>Remitos generados</span>
        </div>
        <span class="remitos-conteo-badge"><?= $cantRemitos ?> <?= $cantRemitos === 1 ? 'remito generado' : 'remitos generados' ?></span>
      </div>

      <?php if ($cantRemitos === 0): ?>
        <div class="remitos-vacio">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="1" y="3" width="15" height="13"/>
            <polygon points="16 8 20 8 23 11 23 16 16 16 8"/>
            <circle cx="5.5" cy="18.5" r="2.5"/><circle cx="18.5" cy="18.5" r="2.5"/>
          </svg>
          <p>El pedido está confirmado. Aún no se han emitido remitos de despacho.</p>
          <small>A medida que el depósito prepare y despache los repuestos, los remitos figurarán aquí.</small>
        </div>
      <?php else: ?>
        <div class="remitos-lista">
          <?php foreach ($remitos as $remito): ?>
            <div class="remito-card" data-remito-card>
              <button type="button" class="remito-card-cabecera" data-remito-toggle aria-expanded="true">
                <div class="remito-card-titulo-area">
                  <span class="remito-chevron">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                      <polyline points="18 15 12 9 6 15"/>
                    </svg>
                  </span>
                  <?php
                    $tipoComp = trim((string) ($remito['comprobante_tipo'] ?? 'Remito de Salida'));
                    $letraComp = trim((string) ($remito['letra'] ?? ''));
                    $tituloRemito = ($letraComp !== '' && !str_ends_with(strtoupper($tipoComp), strtoupper($letraComp)))
                        ? ($tipoComp . ' ' . $letraComp)
                        : $tipoComp;
                  ?>
                  <strong class="remito-titulo">
                    <?= e($tituloRemito) ?> #<?= (int) $remito['comprobante_nro'] ?>
                  </strong>
                  <span class="remito-fecha"><?= e(date('d/m/Y', strtotime((string) $remito['f_emision']))) ?></span>
                  <?php if (!empty($remito['deposito_nombre'])): ?>
                    <span class="remito-deposito">&bull; <?= e($remito['deposito_nombre']) ?></span>
                  <?php endif; ?>
                </div>
                <div class="remito-card-acciones">
                  <span class="estado estado-remito"><?= e($remito['estado_registro']) ?></span>
                </div>
              </button>

              <div class="remito-card-cuerpo">
                <div class="tabla-contenedor">
                  <table class="tabla tabla-remito">
                    <thead>
                      <tr>
                        <th scope="col">CÓDIGO</th>
                        <th scope="col">PRODUCTO</th>
                        <th scope="col" class="num">CANT.</th>
                        <th scope="col" class="num">IMPORTE</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($remito['items'] as $item): ?>
                        <tr>
                          <td class="col-codigo">
                            <span class="producto-codigo"><?= e($item['producto_codigo']) ?></span>
                          </td>
                          <td><strong><?= e($item['producto_nombre']) ?></strong></td>
                          <td class="num"><strong><?= e(number_format((float) $item['cantidad'], 2, ',', '.')) ?></strong></td>
                          <td class="num"><?= e(money((float) $item['importe_linea'])) ?></td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                    <?php if ($remito['total_importe'] > 0): ?>
                      <tfoot>
                        <tr>
                          <th colspan="3" class="num">Total Remito</th>
                          <td class="num"><strong><?= e(money((float) $remito['total_importe'])) ?></strong></td>
                        </tr>
                      </tfoot>
                    <?php endif; ?>
                  </table>
                </div>
                <?php if (!empty($remito['observaciones'])): ?>
                  <p class="remito-observaciones"><strong>Observaciones:</strong> <?= e($remito['observaciones']) ?></p>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php endif; ?>

<?= $this->partial('layouts/footer') ?>
