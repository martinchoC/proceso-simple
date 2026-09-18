<?php
/**
 * @var int $pagina
 * @var int $paginas
 * @var array<string,mixed> $filtros
 */
if (($paginas ?? 1) <= 1) {
    return;
}
?>
<nav class="paginador" aria-label="Paginación">
  <?php if ($pagina > 1): ?>
    <a class="btn btn-secundario" data-pagina="<?= (int) $pagina - 1 ?>"
       href="<?= e(url_catalogo($filtros + ['pagina' => $pagina - 1])) ?>" rel="prev">&laquo; Anterior</a>
  <?php endif; ?>

  <span>Página <?= (int) $pagina ?> de <?= (int) $paginas ?></span>

  <?php if ($pagina < $paginas): ?>
    <a class="btn btn-secundario" data-pagina="<?= (int) $pagina + 1 ?>"
       href="<?= e(url_catalogo($filtros + ['pagina' => $pagina + 1])) ?>" rel="next">Siguiente &raquo;</a>
  <?php endif; ?>
</nav>
