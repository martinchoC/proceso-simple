<?php
/**
 * @var \App\Domain\ResumenCarrito $resumen
 * @var \App\Support\Csrf $csrf
 * @var \App\Services\SessionGuard $auth
 */
$sucursalesCompra = $sucursalesCompra ?? [];
$compatibilidades = $compatibilidades ?? [];
$descuentoCliente = (float) ($descuentoCliente ?? 0);
$ivaPorcentaje = 21.0;
if (!$resumen->vacio() && count($resumen->lineas) > 0) {
    $ivaPorcentaje = $resumen->lineas[0]->ivaPorcentaje;
    if ($descuentoCliente <= 0) {
        $descuentoCliente = $resumen->lineas[0]->descuentoPct;
    }
}

echo $this->partial('layouts/header', [
    'titulo' => 'Mi carrito',
    'items_carrito' => $resumen->cantidadLineas(),
    'auth' => $auth,
    'seccion' => 'carrito',
]);
?>

<?= $this->partial('layouts/flash', ['flash' => $flash ?? []]) ?>

<h1>Mi carrito</h1>

<?php if ($resumen->noDisponibles !== []): ?>
  <div class="aviso aviso-error" role="alert">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
      <path d="M12 8v5"/><path d="M12 16h.01"/><circle cx="12" cy="12" r="9"/>
    </svg>
    <span>
      Hay <?= count($resumen->noDisponibles) ?> producto(s) sin precio vigente para tu cuenta.
      Quitalos del carrito para poder confirmar el pedido.
    </span>
  </div>
<?php endif; ?>

<?php if ($resumen->vacio()): ?>
  <div class="panel vacio">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M3 3h2l.4 2M7 13h10l3-8H5.4M7 13 5.4 5M7 13l-2 5h13"/>
      <circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/>
    </svg>
    <p>Todavía no agregaste productos.</p>
    <a class="btn btn-primario" href="<?= e(url('/catalogo')) ?>">Ir al catálogo</a>
  </div>
<?php else: ?>
  <div class="carrito">
    <div class="panel">
      <h2 class="panel-titulo" data-carrito-titulo><?= $resumen->cantidadLineas() ?> producto(s)</h2>

      <?php foreach ($resumen->lineas as $linea): ?>
        <div class="linea" data-linea-id="<?= $linea->productoId ?>">
          <div class="linea-foto">
            <span class="producto-sinfoto">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <rect x="3" y="4" width="18" height="16" rx="2"/>
                <circle cx="9" cy="10" r="2"/><path d="m4 18 5-5 4 4 3-3 4 4"/>
              </svg>
            </span>
          </div>

          <div class="linea-detalle">
            <div class="linea-cabecera-datos">
              <div class="linea-col-codigo">
                <span class="linea-col-label">Código</span>
                <strong class="linea-codigo-texto"><?= e($linea->codigo) ?></strong>
              </div>
              <div class="linea-col-descripcion">
                <span class="linea-col-label">Descripción</span>
                <h2 class="linea-nombre-texto">
                  <a href="<?= e(url('/productos/' . $linea->productoId)) ?>"><?= e($linea->nombre) ?></a>
                </h2>

                <?php
                  $compat = !empty($linea->compatibilidad) ? $linea->compatibilidad : ($compatibilidades[$linea->productoId] ?? null);
                ?>
                <?php if ($compat !== null): ?>
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
                  <?php elseif (!empty($compat['marcas']) || !empty($compat['modelos']) || !empty($compat['submodelos']) || !empty($compat['anios'])): ?>
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

            <div class="linea-datos">
              <div class="linea-acciones-cantidad" data-url-fijar="<?= e(url('/carrito/items/fijar')) ?>" data-producto-id="<?= $linea->productoId ?>">
                <span class="cantidad con-unidades" data-cantidad>
                  <button type="button" data-paso="-1" aria-label="Restar" <?= (float) $linea->cantidad <= 1 ? 'disabled' : '' ?>>&minus;</button>
                  <label class="solo-lectores" for="cant-<?= $linea->productoId ?>">Cantidad</label>
                  <input type="number" id="cant-<?= $linea->productoId ?>" name="cantidad"
                         data-prod-id="<?= $linea->productoId ?>"
                         data-cant-confirmada="<?= (int) $linea->cantidad ?>"
                         value="<?= e(rtrim(rtrim(number_format($linea->cantidad, 2, '.', ''), '0'), '.')) ?>"
                         min="1" max="99999" step="1">
                  <button type="button" data-paso="1" aria-label="Sumar">+</button>
                </span>
              </div>

              <form method="post" action="<?= e(url('/carrito/items/eliminar')) ?>" data-form-quitar>
                <?= $csrf->field() ?>
                <input type="hidden" name="producto_id" value="<?= $linea->productoId ?>">
                <button type="submit" class="btn btn-peligro">Quitar</button>
              </form>
            </div>
          </div>

          <div class="linea-importe">
            <div class="linea-precio-item">
              <span class="linea-precio-label">Precio de Lista:</span>
              <span class="linea-precio-val" data-precio-lista>
                <?= e(money((float) $linea->cantidad > 1 ? $linea->precioListaTotal() : $linea->precioListaNeto)) ?>
                <?php if ((float) $linea->cantidad > 1): ?>
                  <small class="mini-unitario">(<?= e(money($linea->precioListaNeto)) ?> c/u)</small>
                <?php endif; ?>
              </span>
            </div>
            <div class="linea-precio-item <?= $linea->descuentoPct > 0 ? '' : 'oculto' ?>" data-fila-descuento>
              <span class="linea-precio-label">Descuento (<span data-desc-pct><?= number_format($linea->descuentoPct, 0) ?></span>%):</span>
              <span class="linea-precio-val texto-descuento" data-precio-descuento>&minus; <?= e(money($linea->descuentoTotal())) ?></span>
            </div>
            <div class="linea-precio-item linea-precio-destacado">
              <span class="linea-precio-label">Neto:</span>
              <strong class="linea-precio-val" data-precio-neto><?= e(money($linea->netoGravado())) ?></strong>
            </div>
          </div>
        </div>
      <?php endforeach; ?>

      <form method="post" action="<?= e(url('/carrito/vaciar')) ?>">
        <?= $csrf->field() ?>
        <button type="submit" class="btn btn-plano">Vaciar carrito</button>
      </form>
    </div>

    <aside class="panel resumen">
      <h2 class="panel-titulo">Resumen del pedido</h2>

      <div class="resumen-fila">
        <span>Total Precio Lista</span>
        <strong data-resumen-subtotal-bruto><?= e(money($resumen->subtotalBruto())) ?></strong>
      </div>
      <div class="resumen-fila <?= ($descuentoCliente > 0 || $resumen->descuentos() > 0) ? '' : 'oculto' ?>" data-resumen-fila-descuento>
        <span>Descuento (<span data-resumen-descuento-pct><?= number_format($descuentoCliente, 0) ?></span>%)</span>
        <strong class="texto-descuento" data-resumen-descuento-importe>
          <?php if ($resumen->descuentos() > 0): ?>
            &minus; <?= e(money($resumen->descuentos())) ?>
          <?php else: ?>
            <?= number_format($descuentoCliente, 0) ?>%
          <?php endif; ?>
        </strong>
      </div>
      <div class="resumen-fila">
        <span>Total Neto</span>
        <strong data-resumen-subtotal-neto><?= e(money($resumen->subtotalNeto())) ?></strong>
      </div>
      <div class="resumen-fila">
        <span>IVA (<span data-resumen-iva-pct><?= number_format($ivaPorcentaje, 0) ?></span>%)</span>
        <strong data-resumen-iva><?= e(money($resumen->iva())) ?></strong>
      </div>
      <div class="resumen-total">
        <span>Total</span>
        <strong data-resumen-total><?= e(money($resumen->total())) ?></strong>
      </div>

      <form method="post" action="<?= e(url('/pedidos')) ?>">
        <?= $csrf->field() ?>

        <?php
          $sucursalesEntrega = $sucursalesEntrega ?? [];
          $cantSucursalesEntrega = count($sucursalesEntrega);

          $sucursalesCompra = $sucursalesCompra ?? [];
          $cantSucursalesCompra = count($sucursalesCompra);
        ?>

        <?php if ($cantSucursalesCompra > 0 || $cantSucursalesEntrega > 0): ?>
          <div class="seccion-sucursales-pedido">
            <?php if ($cantSucursalesCompra > 1): ?>
              <div class="campo-sucursal">
                <label for="sucursal_compra_id" class="campo-sucursal-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 21h18M3 7v1a3 3 0 0 0 6 0V7m0 1a3 3 0 0 0 6 0V7m0 1a3 3 0 0 0 6 0V7H3l2-4h14l2 4M5 21V10.85M19 21V10.85M9 21v-4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v4"/>
                  </svg>
                  <span>Sucursal de compra</span>
                </label>
                <div class="select-moderno-wrap">
                  <select id="sucursal_compra_id" name="sucursal_compra_id" class="select-moderno" required>
                    <?php foreach ($sucursalesCompra as $sucC): ?>
                      <option value="<?= (int) $sucC['sucursal_id'] ?>" <?= (!empty($sucC['es_principal']) ? 'selected' : '') ?>>
                        <?= e($sucC['sucursal_nombre']) ?><?= !empty($sucC['sucursal_direccion']) ? ' — ' . e($sucC['sucursal_direccion']) : '' ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <span class="select-moderno-arrow" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                    </svg>
                  </span>
                </div>
              </div>
            <?php elseif ($cantSucursalesCompra === 1): ?>
              <div class="campo-sucursal">
                <span class="campo-sucursal-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 21h18M3 7v1a3 3 0 0 0 6 0V7m0 1a3 3 0 0 0 6 0V7m0 1a3 3 0 0 0 6 0V7H3l2-4h14l2 4M5 21V10.85M19 21V10.85M9 21v-4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v4"/>
                  </svg>
                  <span>Sucursal de compra</span>
                </span>
                <input type="hidden" name="sucursal_compra_id" value="<?= (int) $sucursalesCompra[0]['sucursal_id'] ?>">
                <div class="sucursal-tarjeta-fija">
                  <div class="sucursal-tarjeta-icono compra" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M3 21h18M3 7v1a3 3 0 0 0 6 0V7m0 1a3 3 0 0 0 6 0V7m0 1a3 3 0 0 0 6 0V7H3l2-4h14l2 4M5 21V10.85M19 21V10.85M9 21v-4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v4"/>
                    </svg>
                  </div>
                  <div class="sucursal-tarjeta-detalles">
                    <span class="sucursal-tarjeta-nombre"><?= e($sucursalesCompra[0]['sucursal_nombre']) ?></span>
                    <?php if (!empty($sucursalesCompra[0]['sucursal_direccion'])): ?>
                      <span class="sucursal-tarjeta-direccion"><?= e($sucursalesCompra[0]['sucursal_direccion']) ?></span>
                    <?php endif; ?>
                  </div>
                  <span class="sucursal-tarjeta-badge azul">Asignada</span>
                </div>
              </div>
            <?php else: ?>
              <input type="hidden" name="sucursal_compra_id" value="0">
            <?php endif; ?>

            <?php if ($cantSucursalesEntrega > 1): ?>
              <div class="campo-sucursal">
                <label for="entidad_sucursal_id" class="campo-sucursal-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/>
                    <path d="M15 18H9"/>
                    <path d="M19 18h2a1 1 0 0 0 1-1v-5l-3-4h-4v10"/>
                    <circle cx="7" cy="18" r="2"/>
                    <circle cx="17" cy="18" r="2"/>
                  </svg>
                  <span>Sucursal de entrega</span>
                </label>
                <div class="select-moderno-wrap">
                  <select id="entidad_sucursal_id" name="entidad_sucursal_id" class="select-moderno" required>
                    <?php foreach ($sucursalesEntrega as $sucE): ?>
                      <option value="<?= (int) $sucE['sucursal_id'] ?>">
                        <?= e($sucE['sucursal_nombre']) ?><?= !empty($sucE['sucursal_direccion']) ? ' — ' . e($sucE['sucursal_direccion']) : '' ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                  <span class="select-moderno-arrow" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="currentColor">
                      <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 011.06.02L10 11.168l3.71-3.938a.75.75 0 111.08 1.04l-4.25 4.5a.75.75 0 01-1.08 0l-4.25-4.5a.75.75 0 01.02-1.06z" clip-rule="evenodd"/>
                    </svg>
                  </span>
                </div>
              </div>
            <?php elseif ($cantSucursalesEntrega === 1): ?>
              <div class="campo-sucursal">
                <span class="campo-sucursal-label">
                  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/>
                    <path d="M15 18H9"/>
                    <path d="M19 18h2a1 1 0 0 0 1-1v-5l-3-4h-4v10"/>
                    <circle cx="7" cy="18" r="2"/>
                    <circle cx="17" cy="18" r="2"/>
                  </svg>
                  <span>Sucursal de entrega</span>
                </span>
                <input type="hidden" name="entidad_sucursal_id" value="<?= (int) $sucursalesEntrega[0]['sucursal_id'] ?>">
                <div class="sucursal-tarjeta-fija">
                  <div class="sucursal-tarjeta-icono entrega" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                      <path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2"/>
                      <path d="M15 18H9"/>
                      <path d="M19 18h2a1 1 0 0 0 1-1v-5l-3-4h-4v10"/>
                      <circle cx="7" cy="18" r="2"/>
                      <circle cx="17" cy="18" r="2"/>
                    </svg>
                  </div>
                  <div class="sucursal-tarjeta-detalles">
                    <span class="sucursal-tarjeta-nombre"><?= e($sucursalesEntrega[0]['sucursal_nombre']) ?></span>
                    <?php if (!empty($sucursalesEntrega[0]['sucursal_direccion'])): ?>
                      <span class="sucursal-tarjeta-direccion"><?= e($sucursalesEntrega[0]['sucursal_direccion']) ?></span>
                    <?php endif; ?>
                  </div>
                  <span class="sucursal-tarjeta-badge verde">Destino</span>
                </div>
              </div>
            <?php else: ?>
              <input type="hidden" name="entidad_sucursal_id" value="0">
            <?php endif; ?>
          </div>
        <?php else: ?>
          <input type="hidden" name="sucursal_compra_id" value="0">
          <input type="hidden" name="entidad_sucursal_id" value="0">
        <?php endif; ?>

        <div class="campo">
          <label for="observaciones">Observaciones (opcional)</label>
          <textarea id="observaciones" name="observaciones" class="control" rows="2" maxlength="500"
                    placeholder="Referencia de compra, indicaciones de entrega..."></textarea>
        </div>
        <button type="submit" class="btn btn-primario btn-bloque"
          <?= $resumen->noDisponibles !== [] ? 'disabled' : '' ?>>Confirmar pedido</button>
      </form>

      <p class="precio-nota">
        El pedido queda registrado y confirmado en el sistema.
      </p>
    </aside>
  </div>
<?php endif; ?>

<?= $this->partial('layouts/footer') ?>
