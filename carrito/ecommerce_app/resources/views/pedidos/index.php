<?php
/**
 * @var array<int,array<string,mixed>> $pedidos
 * @var \App\Services\SessionGuard $auth
 */
echo $this->partial('layouts/header', [
    'titulo' => 'Mis pedidos',
    'auth' => $auth,
    'seccion' => 'pedidos',
]);
?>

<?= $this->partial('layouts/flash', ['flash' => $flash ?? []]) ?>

<h1>Mis pedidos</h1>

<?php if ($pedidos === []): ?>
  <div class="panel vacio">
    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
         stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
      <path d="M9 3h6l1 4H8l1-4Z"/><path d="M4 7h16l-1.2 13H5.2L4 7Z"/>
    </svg>
    <p>Todavía no registraste pedidos.</p>
    <a class="btn btn-primario" href="<?= e(url('/catalogo')) ?>">Ir al catálogo</a>
  </div>
<?php else: ?>
  <div class="panel">
    <table class="tabla">
      <thead>
        <tr>
          <th scope="col">Comprobante</th>
          <th scope="col">Fecha</th>
          <th scope="col">Estado</th>
          <th scope="col" class="num">Total</th>
          <th scope="col"><span class="solo-lectores">Detalle</span></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($pedidos as $pedido): ?>
          <tr>
            <td>
              <a href="<?= e(url('/pedidos/' . (int) $pedido['venta_pedido_id'])) ?>">
                <?= e(str_pad((string) $pedido['comprobante_pv'], 4, '0', STR_PAD_LEFT)) ?>-<?= e(str_pad((string) $pedido['comprobante_nro'], 8, '0', STR_PAD_LEFT)) ?>
              </a>
            </td>
            <td><?= e(date('d/m/Y', strtotime((string) $pedido['f_emision']))) ?></td>
            <td><span class="estado"><?= e($pedido['estado_registro'] ?? '-') ?></span></td>
            <td class="num"><?= e(money((float) $pedido['importe_total'])) ?></td>
            <td class="num">
              <a class="btn btn-plano" href="<?= e(url('/pedidos/' . (int) $pedido['venta_pedido_id'])) ?>">Ver</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?= $this->partial('layouts/footer') ?>
