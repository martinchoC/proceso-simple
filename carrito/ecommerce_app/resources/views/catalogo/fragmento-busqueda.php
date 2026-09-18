<?php
/**
 * Respuesta de /catalogo/resultados: las pills y los resultados en una sola
 * ida, separados por marcadores para que el front sepa dónde va cada parte.
 *
 * @var array<string,mixed> $resultado
 * @var array<string,mixed> $filtros
 * @var \App\Support\Csrf $csrf
 */
?>
<div data-parte="terminos">
<?= $this->partial('catalogo/terminos', ['filtros' => $filtros]) ?>
</div>
<div data-parte="resultados">
<?= $this->partial('catalogo/resultados', [
    'resultado' => $resultado,
    'filtros'   => $filtros,
    'csrf'      => $csrf,
]) ?>
</div>
