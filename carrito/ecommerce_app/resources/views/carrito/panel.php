<?php
/**
 * Resumen del carrito que acompaña al catálogo en la columna derecha.
 * Es un parcial: al agregar un producto el front pide este fragmento y lo
 * reemplaza, así los importes los sigue calculando el servidor.
 *
 * @var \App\Domain\ResumenCarrito $resumen
 * @var \App\Support\Csrf $csrf
 * @var array<int,array<string,mixed>> $sucursalesCompra
 * @var int $compraSeleccionada
 * @var array<int,array<string,mixed>> $sucursalesEntrega
 * @var int $entregaSeleccionada
 */
?>
<div class="panel-carrito-cabecera">
  <h2 class="panel-titulo">
    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M3 3h2l.4 2M7 13h10l3-8H5.4M7 13 5.4 5M7 13l-2 5h13"/>
      <circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/>
    </svg>
    Mi carrito
  </h2>
  <?php if (!$resumen->vacio()): ?>
    <span class="panel-carrito-conteo"><?= $resumen->cantidadLineas() ?></span>
  <?php endif; ?>
</div>

<?php if ($resumen->vacio()): ?>
  <p class="panel-carrito-vacio">Todavía no agregaste productos.</p>
<?php else: ?>

  <ul class="panel-carrito-lineas">
    <?php foreach ($resumen->lineas as $linea): ?>
      <li class="panel-carrito-linea">
        <div class="panel-carrito-datos">
          <a href="<?= e(url('/productos/' . $linea->productoId)) ?>" class="panel-carrito-nombre">
            <?= e($linea->nombre) ?>
          </a>
          <span class="panel-carrito-detalle">
            <?= e(rtrim(rtrim(number_format($linea->cantidad, 2, ',', '.'), '0'), ',')) ?>
            &times; <?= e(money($linea->precioUnitarioNeto())) ?>
          </span>
        </div>

        <div class="panel-carrito-derecha">
          <strong><?= e(money($linea->total())) ?></strong>
          <form method="post" action="<?= e(url('/carrito/items/eliminar')) ?>" class="form-carrito">
            <?= $csrf->field() ?>
            <input type="hidden" name="producto_id" value="<?= $linea->productoId ?>">
            <button type="submit" class="btn-quitar" aria-label="Quitar <?= e($linea->nombre) ?>">&times;</button>
          </form>
        </div>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($resumen->noDisponibles !== []): ?>
    <p class="panel-carrito-alerta">
      <?= count($resumen->noDisponibles) ?> producto(s) sin precio vigente. Quitalos para poder confirmar.
    </p>
  <?php endif; ?>

  <div class="panel-carrito-totales">
    <div class="resumen-fila"><span>Subtotal neto</span><span><?= e(money($resumen->subtotalNeto())) ?></span></div>
    <?php if ($resumen->descuentos() > 0): ?>
      <div class="resumen-fila"><span>Descuento</span><span>&minus; <?= e(money($resumen->descuentos())) ?></span></div>
    <?php endif; ?>
    <div class="resumen-fila"><span>IVA</span><span><?= e(money($resumen->iva())) ?></span></div>
    <div class="resumen-total"><span>Total</span><span><?= e(money($resumen->total())) ?></span></div>
  </div>

  <?= $this->partial('carrito/sucursales', [
      'sucursalesCompra'    => $sucursalesCompra ?? [],
      'compraSeleccionada'  => $compraSeleccionada ?? 0,
      'sucursalesEntrega'   => $sucursalesEntrega ?? [],
      'entregaSeleccionada' => $entregaSeleccionada ?? 0,
      'editable'            => false,
  ]) ?>

  <a class="btn btn-primario btn-bloque" href="<?= e(url('/carrito')) ?>">Ir al carrito</a>
<?php endif; ?>
