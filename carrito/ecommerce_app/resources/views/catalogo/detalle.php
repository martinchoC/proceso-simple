<?php
/**
 * @var array<string,mixed> $producto
 * @var \App\Support\Csrf $csrf
 * @var \App\Services\SessionGuard $auth
 */
echo $this->partial('layouts/header', [
    'titulo' => (string) $producto['producto_nombre'],
    'auth' => $auth,
    'seccion' => 'catalogo',
]);

$pid = (int) $producto['producto_id'];
?>

<?= $this->partial('layouts/flash', ['flash' => $flash ?? []]) ?>

<p class="migas">
  <a href="<?= e(url('/catalogo')) ?>">Catálogo</a>
  <?php if (!empty($producto['producto_categoria_nombre'])): ?>
    &rsaquo; <?= e($producto['producto_categoria_nombre']) ?>
  <?php endif; ?>
</p>

<article class="detalle">
  <div class="detalle-galeria">
    <?php if (!empty($producto['imagen_id'])): ?>
      <img src="<?= e(url('/imagenes/' . (int) $producto['imagen_id'])) ?>"
           alt="<?= e($producto['producto_nombre']) ?>">
    <?php else: ?>
      <span class="producto-sinfoto">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
          <rect x="3" y="4" width="18" height="16" rx="2"/>
          <circle cx="9" cy="10" r="2"/><path d="m4 18 5-5 4 4 3-3 4 4"/>
        </svg>
      </span>
    <?php endif; ?>

    <?php if (!empty($producto['producto_descripcion'])): ?>
      <h2 class="panel-titulo">Descripción</h2>
      <p><?= nl2br(e($producto['producto_descripcion'])) ?></p>
    <?php endif; ?>

    <?php
    $specs = array_filter([
        'Material'    => $producto['material'] ?? '',
        'Color'       => $producto['color'] ?? '',
        'Dimensiones' => $producto['dimensiones'] ?? '',
        'Garantía'    => $producto['garantia'] ?? '',
    ], static fn ($v): bool => trim((string) $v) !== '' && (string) $v !== '0');
    ?>
    <?php if ($specs !== []): ?>
      <h2 class="panel-titulo">Características</h2>
      <dl class="specs">
        <?php foreach ($specs as $etiqueta => $valor): ?>
          <dt><?= e($etiqueta) ?></dt><dd><?= e($valor) ?></dd>
        <?php endforeach; ?>
      </dl>
    <?php endif; ?>
  </div>

  <div class="detalle-compra">
    <div class="panel">
      <span class="producto-codigo">Código <?= e($producto['producto_codigo']) ?></span>
      <h1><?= e($producto['producto_nombre']) ?></h1>
      <?php $compat = $producto['compatibilidad'] ?? ['marcas' => [], 'modelos' => [], 'submodelos' => [], 'anios' => [], 'combinaciones' => []]; ?>
      <?php if (!empty($compat['combinaciones']) || !empty($compat['marcas']) || !empty($compat['modelos'])): ?>
        <div class="detalle-compatibilidad-badges">
          <strong class="detalle-compatibilidad-label">Vehículo compatible:</strong>
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
          <?php endif; ?>
        </div>
      <?php elseif (!empty($producto['compatibilidad_texto'])): ?>
        <p class="detalle-compatibilidad">
          <strong>Compatibilidad:</strong> <?= e($producto['compatibilidad_texto']) ?>
        </p>
      <?php endif; ?>

      <div class="detalle-precio">
        <span class="precio"><?= e(money((float) ($producto['precio_lista'] ?? $producto['precio_neto_lista']))) ?></span>
        <span class="precio-nota">
          Precio de lista<?php if (!empty($producto['descuento_pct']) && (float) $producto['descuento_pct'] > 0): ?> &middot;
          Descuento <?= number_format((float) $producto['descuento_pct'], 0) ?>% &middot;
          Neto <?= e(money((float) $producto['precio_neto_cliente'])) ?><?php endif; ?>
        </span>
      </div>

      <?php
      $cantEnCarrito = (int) ($cantidad_carrito ?? 0);
      $enCarrito = $cantEnCarrito > 0;
      ?>
      <form method="post" action="<?= e(url('/carrito/items/fijar')) ?>" class="form-agregar" data-form-producto="<?= $pid ?>">
        <?= $csrf->field() ?>
        <input type="hidden" name="producto_id" value="<?= $pid ?>">

        <div class="campo">
          <label for="cantidad">Cantidad</label>
          <span class="cantidad" data-cantidad>
            <button type="button" data-paso="-1" aria-label="Restar">&minus;</button>
            <input type="number" id="cantidad" name="cantidad" value="<?= $cantEnCarrito ?>" min="0" max="99999" step="1">
            <button type="button" data-paso="1" aria-label="Sumar">+</button>
          </span>
        </div>

        <button type="submit" class="btn <?= $enCarrito ? 'btn-secundario' : 'btn-primario' ?> btn-bloque" data-btn-accion>
          <?= $enCarrito ? 'Actualizar en el carrito' : 'Agregar al carrito' ?>
        </button>
      </form>

      <p class="precio-nota">
        Los precios corresponden a la lista asignada a <strong><?= e($auth->entidadNombre()) ?></strong>.
      </p>
    </div>
  </div>
</article>

<?= $this->partial('layouts/footer') ?>
