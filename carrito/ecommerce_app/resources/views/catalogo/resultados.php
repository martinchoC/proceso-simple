<?php
/**
 * Resultados del catálogo. Es un parcial para que la búsqueda dinámica
 * pueda pedir exactamente este fragmento y reemplazarlo, sin duplicar en
 * JavaScript el formato de precios ni el escapado.
 *
 * @var array{items:array<int,array<string,mixed>>,total:int,pagina:int,paginas:int} $resultado
 * @var array<string,mixed> $filtros
 * @var \App\Support\Csrf $csrf
 * @var string $vista
 */
$vista = $filtros['vista'] ?? 'lista';
?>

<div class="barra-resultados">
  <span class="barra-conteo">
    <strong><?= number_format($resultado['total'], 0, ',', '.') ?></strong>
    <?= $resultado['total'] === 1 ? 'producto' : 'productos' ?>
  </span>

  <div class="vista-selector" role="group" aria-label="Forma de ver los productos">
    <a class="vista-btn <?= $vista === 'lista' ? 'activa' : '' ?>"
       href="<?= e(url_catalogo(['vista' => 'lista'] + $filtros)) ?>"
       data-vista="lista" <?= $vista === 'lista' ? 'aria-current="true"' : '' ?>>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
           stroke-linecap="round" aria-hidden="true">
        <path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/>
      </svg>
      Lista
    </a>
    <a class="vista-btn <?= $vista === 'grilla' ? 'activa' : '' ?>"
       href="<?= e(url_catalogo(['vista' => 'grilla'] + $filtros)) ?>"
       data-vista="grilla" <?= $vista === 'grilla' ? 'aria-current="true"' : '' ?>>
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/>
        <rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/>
      </svg>
      Cuadrícula
    </a>
  </div>
</div>

<?php if ($resultado['items'] === []): ?>
  <div class="panel vacio">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
      <circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>
    </svg>
    <p>
      No encontramos productos que cumplan con
      <?= ($filtros['terminos'] ?? []) !== [] ? 'todos los términos buscados' : 'esos filtros' ?>.
    </p>
    <?php if (count($filtros['terminos'] ?? []) > 1): ?>
      <p class="precio-nota">Probá quitando alguno de los términos.</p>
    <?php endif; ?>
  </div>

<?php elseif ($vista === 'lista'): ?>

  <div class="tabla-envoltorio">
    <table class="tabla-productos">
      <thead>
        <tr>
          <th scope="col" class="col-producto">Producto</th>
          <th scope="col" class="col-num">Lista</th>
          <th scope="col" class="col-num">Con desc.</th>
          <th scope="col" class="col-num">Con IVA</th>
          <th scope="col" class="col-cant">Cantidad</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($resultado['items'] as $producto): ?>
          <?php
          $pid       = (int) $producto['producto_id'];
          $lista     = (float) ($producto['precio_neto_lista'] ?? 0);
          $conDesc   = (float) ($producto['precio_neto_cliente'] ?? 0);
          $conIva    = (float) ($producto['precio_final'] ?? 0);
          $descPct   = (float) ($producto['descuento_pct'] ?? 0);
          $ivaPct    = (float) ($producto['iva_porcentaje'] ?? 0);
          ?>
          <tr data-producto="<?= $pid ?>">
            <td class="col-producto">
              <div class="fila-producto">
                <a class="fila-foto" href="<?= e(url('/productos/' . $pid)) ?>"
                   aria-label="Ver <?= e($producto['producto_nombre']) ?>">
                  <?php if (!empty($producto['imagen_id'])): ?>
                    <img src="<?= e(url('/imagenes/' . (int) $producto['imagen_id'])) ?>"
                         alt="<?= e($producto['producto_nombre']) ?>" loading="lazy"
                         width="52" height="52">
                  <?php else: ?>
                    <span class="producto-sinfoto">
                      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                           stroke-width="1.5" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="16" rx="2"/>
                        <circle cx="9" cy="10" r="2"/><path d="m4 18 5-5 4 4 3-3 4 4"/>
                      </svg>
                    </span>
                  <?php endif; ?>
                </a>

                <div class="fila-datos">
                  <a class="fila-nombre" href="<?= e(url('/productos/' . $pid)) ?>">
                    <?= e($producto['producto_nombre']) ?>
                  </a>
                  <span class="fila-meta">
                    <span class="producto-codigo"><?= e($producto['producto_codigo']) ?></span>
                    <?php if (!empty($producto['producto_categoria_nombre'])): ?>
                      &middot; <?= e($producto['producto_categoria_nombre']) ?>
                    <?php endif; ?>
                  </span>
                  <?php if (!empty($producto['compatibilidad_texto'])): ?>
                    <span class="fila-compat" title="<?= e($producto['compatibilidad_texto']) ?>">
                      <?= e(mb_strimwidth((string) $producto['compatibilidad_texto'], 0, 90, '…')) ?>
                    </span>
                  <?php endif; ?>
                </div>
              </div>
            </td>

            <td class="col-num">
              <span class="<?= $descPct > 0 ? 'precio-tachado' : 'precio-plano' ?>">
                <?= e(money($lista)) ?>
              </span>
            </td>

            <td class="col-num">
              <span class="precio-neto"><?= e(money($conDesc)) ?></span>
              <?php if ($descPct > 0): ?>
                <span class="etiqueta etiqueta-mini"><?= e(number_format($descPct, 0)) ?>% OFF</span>
              <?php endif; ?>
            </td>

            <td class="col-num">
              <span class="precio"><?= e(money($conIva)) ?></span>
              <span class="precio-nota">IVA <?= e(number_format($ivaPct, 0)) ?>%</span>
            </td>

            <td class="col-cant">
              <?php /* El input vive fuera del form y se asocia con el atributo
                       form=: la cantidad viaja igual en el POST y sigue
                       funcionando sin JavaScript. */ ?>
              <div class="celda-compra">
                <span class="cantidad" data-cantidad>
                  <button type="button" data-paso="-1" aria-label="Restar uno">&minus;</button>
                  <label class="solo-lectores" for="cant-<?= $pid ?>">Cantidad de <?= e($producto['producto_nombre']) ?></label>
                  <input type="number" id="cant-<?= $pid ?>" name="cantidad" form="agregar-<?= $pid ?>"
                         value="1" min="1" max="99999" step="1">
                  <button type="button" data-paso="1" aria-label="Sumar uno">+</button>
                </span>

                <form id="agregar-<?= $pid ?>" method="post" action="<?= e(url('/carrito/items')) ?>" class="form-agregar">
                  <?= $csrf->field() ?>
                  <input type="hidden" name="producto_id" value="<?= $pid ?>">
                  <button type="submit" class="btn btn-primario btn-agregar" title="Agregar al carrito" aria-label="Agregar <?= e($producto['producto_nombre']) ?> al carrito">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                         stroke-width="2.2" stroke-linecap="round" aria-hidden="true">
                      <path d="M3 3h2l.4 2M7 13h10l3-8H5.4M7 13 5.4 5M7 13l-2 5h13"/>
                      <circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/>
                    </svg>
                    <span class="btn-agregar-texto">Agregar</span>
                  </button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<?php else: ?>

  <ul class="grilla">
    <?php foreach ($resultado['items'] as $producto): ?>
      <?php $pid = (int) $producto['producto_id']; ?>
      <li class="producto">
        <a class="producto-figura" href="<?= e(url('/productos/' . $pid)) ?>">
          <?php if (!empty($producto['imagen_id'])): ?>
            <img src="<?= e(url('/imagenes/' . (int) $producto['imagen_id'])) ?>"
                 alt="<?= e($producto['producto_nombre']) ?>" loading="lazy">
          <?php else: ?>
            <span class="producto-sinfoto">
              <svg width="42" height="42" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                <rect x="3" y="4" width="18" height="16" rx="2"/>
                <circle cx="9" cy="10" r="2"/><path d="m4 18 5-5 4 4 3-3 4 4"/>
              </svg>
            </span>
          <?php endif; ?>
        </a>

        <div class="producto-cuerpo">
          <div class="producto-info">
            <span class="producto-codigo"><?= e($producto['producto_codigo']) ?></span>
            <h2 class="producto-titulo">
              <a href="<?= e(url('/productos/' . $pid)) ?>"><?= e($producto['producto_nombre']) ?></a>
            </h2>
            <?php if (!empty($producto['producto_categoria_nombre'])): ?>
              <span class="producto-categoria"><?= e($producto['producto_categoria_nombre']) ?></span>
            <?php endif; ?>
          </div>

          <p class="producto-precio">
            <?php if ((float) $producto['descuento_pct'] > 0): ?>
              <span class="precio-tachado"><?= e(money((float) $producto['precio_neto_lista'])) ?></span>
            <?php endif; ?>
            <span class="precio"><?= e(money((float) $producto['precio_final'])) ?></span>
            <span class="precio-nota">IVA incluido</span>
            <?php if ((float) $producto['descuento_pct'] > 0): ?>
              <span class="etiqueta"><?= e(number_format((float) $producto['descuento_pct'], 0)) ?>% OFF</span>
            <?php endif; ?>
          </p>

          <form method="post" action="<?= e(url('/carrito/items')) ?>" class="producto-acciones form-agregar">
            <?= $csrf->field() ?>
            <input type="hidden" name="producto_id" value="<?= $pid ?>">
            <span class="cantidad" data-cantidad>
              <button type="button" data-paso="-1" aria-label="Restar uno">&minus;</button>
              <label class="solo-lectores" for="cantg-<?= $pid ?>">Cantidad</label>
              <input type="number" id="cantg-<?= $pid ?>" name="cantidad" value="1" min="1" max="99999" step="1">
              <button type="button" data-paso="1" aria-label="Sumar uno">+</button>
            </span>
            <button type="submit" class="btn btn-primario">Agregar</button>
          </form>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>

<?php endif; ?>

<?= $this->partial('layouts/paginador', [
    'pagina'  => $resultado['pagina'],
    'paginas' => $resultado['paginas'],
    'filtros' => $filtros,
]) ?>
