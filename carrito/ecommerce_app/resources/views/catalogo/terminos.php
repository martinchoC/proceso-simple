<?php
/**
 * Pills de los términos activos. Parcial propio porque la búsqueda dinámica
 * lo reemplaza junto con los resultados.
 *
 * @var array<string,mixed> $filtros
 */
$terminos = $filtros['terminos'] ?? [];
?>
<?php if ($terminos !== []): ?>
  <div class="busqueda-activa">
    <span class="busqueda-activa-titulo">Filtrando por</span>

    <?php foreach ($terminos as $indice => $termino): ?>
      <?php
      $resto = $terminos;
      unset($resto[$indice]);
      $sinTermino = ['terminos' => array_values($resto)] + $filtros;
      ?>
      <span class="pill">
        <?= e($termino) ?>
        <a class="pill-quitar" href="<?= e(url_catalogo($sinTermino)) ?>"
           data-quitar-termino="<?= e($termino) ?>"
           aria-label="Quitar el término <?= e($termino) ?>" title="Quitar">&times;</a>
      </span>
    <?php endforeach; ?>

    <a class="pill-limpiar" data-limpiar-terminos
       href="<?= e(url_catalogo([
           'categorias' => $filtros['categorias'] ?? [],
           'marca_id'   => $filtros['marca_id'] ?? 0,
           'modelo_id'  => $filtros['modelo_id'] ?? 0,
           'vista'      => $filtros['vista'] ?? 'lista',
       ])) ?>">
      Limpiar búsqueda
    </a>
  </div>
<?php endif; ?>
