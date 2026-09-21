<?php
/**
 * @var array{items:array<int,array<string,mixed>>,total:int,pagina:int,paginas:int,por_pagina:int} $resultado
 * @var array{terminos:string[],marca_id:int,modelo_id:int,submodelo_id:int} $filtros
 * @var array{marcas:array,modelos:array,submodelos:array} $opciones
 * @var \App\Support\Csrf $csrf
 * @var \App\Services\SessionGuard $auth
 */
$items_carrito = $items_carrito ?? null;
$carrito = $carrito ?? null;
$cantidades_carrito = $cantidades_carrito ?? [];

echo $this->partial('layouts/header', [
    'titulo' => 'Catálogo',
    'items_carrito' => $items_carrito,
    'auth' => $auth,
    'seccion' => 'catalogo',
    'filtros' => $filtros,
]);

$marcaId = (int) ($filtros['marca_id'] ?? 0);
$modeloId = (int) ($filtros['modelo_id'] ?? 0);
$submodeloId = (int) ($filtros['submodelo_id'] ?? 0);

$nombreMarca = '';
if ($marcaId > 0) {
    foreach ($opciones['marcas'] as $m) {
        if ((int) $m['marca_id'] === $marcaId) {
            $nombreMarca = (string) $m['marca_nombre'];
            break;
        }
    }
}

$nombreModelo = '';
if ($modeloId > 0) {
    foreach ($opciones['modelos'] as $mo) {
        if ((int) $mo['modelo_id'] === $modeloId) {
            $nombreModelo = (string) $mo['modelo_nombre'];
            break;
        }
    }
}

$nombreSubmodelo = '';
if ($submodeloId > 0) {
    foreach ($opciones['submodelos'] as $sm) {
        if ((int) $sm['submodelo_id'] === $submodeloId) {
            $nombreSubmodelo = (string) $sm['submodelo_nombre'];
            break;
        }
    }
}

$hayFiltrosVehiculo = $marcaId > 0 || $modeloId > 0 || $submodeloId > 0;
$hayFiltros = $filtros['terminos'] !== [] || $hayFiltrosVehiculo;
?>

<?= $this->partial('layouts/flash', ['flash' => $flash ?? []]) ?>

<div class="layout">

  <aside class="panel filtros filtros-panel">
    <div class="filtros-vehiculo-header">
      <div class="filtros-vehiculo-icono">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
          <path d="M5 17h14M7 17l1.5-5h7l1.5 5M5 17a2 2 0 1 1-4 0 2 2 0 0 1 4 0Zm14 0a2 2 0 1 1 4 0 2 2 0 0 1-4 0Z"/>
          <path d="M4 11l2-4h12l2 4"/>
        </svg>
      </div>
      <div>
        <h2 class="filtros-vehiculo-titulo">Buscar por vehículo</h2>
        <p class="filtros-vehiculo-desc">Filtrá por marca, modelo y submodelo compatible</p>
      </div>
    </div>

    <button type="button" class="btn btn-secundario btn-bloque filtros-toggle" data-filtros-toggle>
      Filtrar por vehículo
    </button>

    <form method="get" action="<?= e(url('/catalogo')) ?>" class="filtros-cuerpo" data-filtros-cuerpo id="form-filtro-vehiculo">
      <?php foreach ($filtros['terminos'] as $termino): ?>
        <input type="hidden" name="q[]" value="<?= e($termino) ?>">
      <?php endforeach; ?>

      <div class="filtros-grupo">
        <div class="campo">
          <label for="marca_id" class="campo-label">Marca</label>
          <select id="marca_id" name="marca_id" class="control" data-select-marca data-url-modelos="<?= e(url('/catalogo/modelos')) ?>">
            <option value="0">Todas las marcas</option>
            <?php foreach ($opciones['marcas'] as $marca): ?>
              <option value="<?= (int) $marca['marca_id'] ?>"
                <?= (int) $marca['marca_id'] === $marcaId ? 'selected' : '' ?>>
                <?= e($marca['marca_nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo">
          <label for="modelo_id" class="campo-label">Modelo</label>
          <select id="modelo_id" name="modelo_id" class="control" data-select-modelo data-url-submodelos="<?= e(url('/catalogo/submodelos')) ?>" <?= $marcaId === 0 ? 'disabled' : '' ?>>
            <option value="0"><?= $marcaId === 0 ? 'Seleccione una marca' : 'Todos los modelos' ?></option>
            <?php foreach ($opciones['modelos'] as $modelo): ?>
              <option value="<?= (int) $modelo['modelo_id'] ?>"
                <?= (int) $modelo['modelo_id'] === $modeloId ? 'selected' : '' ?>>
                <?= e($modelo['modelo_nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="campo">
          <label for="submodelo_id" class="campo-label">Submodelo / Versión</label>
          <select id="submodelo_id" name="submodelo_id" class="control" data-select-submodelo <?= $modeloId === 0 ? 'disabled' : '' ?>>
            <option value="0"><?= $modeloId === 0 ? 'Seleccione un modelo' : 'Todos los submodelos' ?></option>
            <?php foreach ($opciones['submodelos'] as $submodelo): ?>
              <option value="<?= (int) $submodelo['submodelo_id'] ?>"
                <?= (int) $submodelo['submodelo_id'] === $submodeloId ? 'selected' : '' ?>>
                <?= e($submodelo['submodelo_nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

      <div class="filtros-grupo">
        <button type="submit" class="btn btn-primario btn-bloque">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px; vertical-align: -2px; margin-right: 4px;" aria-hidden="true">
            <circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>
          </svg>
          Buscar repuestos
        </button>
        <?php if ($hayFiltrosVehiculo): ?>
          <a class="btn btn-plano btn-bloque" href="<?= e(url_catalogo(['terminos' => $filtros['terminos']])) ?>">Limpiar vehículo</a>
        <?php endif; ?>
      </div>
    </form>
  </aside>

  <section class="panel-productos">
    <div class="catalogo-buscador-area">
      <form class="buscador buscador-catalogo" method="get" action="<?= e(url('/catalogo')) ?>" role="search" data-buscador-form>
        <div data-buscador-hidden-q>
          <?php foreach ($filtros['terminos'] as $termino): ?>
            <input type="hidden" name="q[]" value="<?= e($termino) ?>">
          <?php endforeach; ?>
        </div>
        <?php if ($marcaId > 0): ?>
          <input type="hidden" name="marca_id" value="<?= $marcaId ?>">
        <?php endif; ?>
        <?php if ($modeloId > 0): ?>
          <input type="hidden" name="modelo_id" value="<?= $modeloId ?>">
        <?php endif; ?>
        <?php if ($submodeloId > 0): ?>
          <input type="hidden" name="submodelo_id" value="<?= $submodeloId ?>">
        <?php endif; ?>

        <div class="buscador-caja" data-buscador-caja>
          <label class="solo-lectores" for="q">Buscar productos</label>
          <div class="buscador-pills-contenedor" data-buscador-pills-contenedor>
            <div class="buscador-pills" data-buscador-pills>
              <?php foreach ($filtros['terminos'] as $termino): ?>
                <span class="buscador-pill" data-termino="<?= e($termino) ?>">
                  <span class="buscador-pill-texto"><?= e($termino) ?></span>
                  <button type="button" class="buscador-pill-quitar" data-quitar-pill aria-label="Quitar <?= e($termino) ?>">&times;</button>
                </span>
              <?php endforeach; ?>
              <input type="text" id="q" class="buscador-input" maxlength="60" value=""
                     placeholder="<?= $filtros['terminos'] === [] ? 'Buscar por código, nombre o descripción (Espacio para agregar filtro)…' : 'Escriba y presione espacio…' ?>"
                     autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false">
            </div>
          </div>
          <button type="button" class="buscador-btn-limpiar <?= $filtros['terminos'] === [] ? 'oculto' : '' ?>" data-buscador-limpiar title="Limpiar términos" aria-label="Limpiar términos">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
              <line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line>
            </svg>
          </button>
          <button type="submit" class="buscador-btn-submit" aria-label="Buscar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                 stroke-linecap="round" aria-hidden="true">
              <circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>
            </svg>
          </button>
        </div>
      </form>
    </div>

    <!-- Contenedor dinámico que se reemplaza vía AJAX sin recargar la página -->
    <div class="catalogo-resultados-dinamicos" data-catalogo-resultados>
      <?php if ($hayFiltrosVehiculo): ?>
        <div class="busqueda-activa">
          <span class="busqueda-activa-titulo">Vehículo seleccionado:</span>

          <?php if ($nombreMarca !== ''): ?>
            <span class="pill pill-vehiculo">
              <strong>Marca:</strong> <?= e($nombreMarca) ?>
              <a class="pill-quitar" href="<?= e(url_catalogo(['terminos' => $filtros['terminos']])) ?>"
                 aria-label="Quitar marca" title="Quitar marca">&times;</a>
            </span>
          <?php endif; ?>

          <?php if ($nombreModelo !== ''): ?>
            <span class="pill pill-vehiculo">
              <strong>Modelo:</strong> <?= e($nombreModelo) ?>
              <a class="pill-quitar" href="<?= e(url_catalogo(['terminos' => $filtros['terminos'], 'marca_id' => $marcaId])) ?>"
                 aria-label="Quitar modelo" title="Quitar modelo">&times;</a>
            </span>
          <?php endif; ?>

          <?php if ($nombreSubmodelo !== ''): ?>
            <span class="pill pill-vehiculo">
              <strong>Submodelo:</strong> <?= e($nombreSubmodelo) ?>
              <a class="pill-quitar" href="<?= e(url_catalogo(['terminos' => $filtros['terminos'], 'marca_id' => $marcaId, 'modelo_id' => $modeloId])) ?>"
                 aria-label="Quitar submodelo" title="Quitar submodelo">&times;</a>
            </span>
          <?php endif; ?>

          <a class="pill-limpiar" href="<?= e(url_catalogo(['terminos' => $filtros['terminos']])) ?>">
            Limpiar vehículo
          </a>
        </div>
      <?php endif; ?>

    <div class="barra-resultados">
      <div class="conteo-resultados">
        <strong><?= number_format($resultado['total'], 0, ',', '.') ?></strong>
        <span><?= $resultado['total'] === 1 ? 'producto disponible' : 'productos disponibles' ?></span>
      </div>

      <div class="vista-selectores" role="group" aria-label="Modo de visualización">
        <button type="button" class="btn-vista" data-vista="grilla" aria-pressed="false" title="Vista en tarjetas">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/>
            <rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>
          </svg>
          <span class="btn-vista-texto">Tarjetas</span>
        </button>
        <button type="button" class="btn-vista activo" data-vista="lista" aria-pressed="true" title="Vista en lista">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/>
            <line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>
          </svg>
          <span class="btn-vista-texto">Lista</span>
        </button>
      </div>
    </div>

    <?php if ($resultado['items'] === []): ?>
      <div class="panel vacio">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
          <circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>
        </svg>
        <p>
          No encontramos productos que cumplan con
          <?= $filtros['terminos'] !== [] ? 'todos los términos buscados' : 'esos filtros' ?>.
        </p>
        <?php if (count($filtros['terminos']) > 1): ?>
          <p class="precio-nota">Probá quitando alguno de los términos de arriba.</p>
        <?php endif; ?>
        <a class="btn btn-secundario" href="<?= e(url('/catalogo')) ?>">Ver todo el catálogo</a>
      </div>
    <?php else: ?>
      <div class="tabla-contenedor" data-tabla-contenedor>
        <div class="tabla-cabecera" data-tabla-cabecera>
          <span class="tabla-th col-foto" aria-hidden="true"></span>
          <span class="tabla-th col-codigo">Código</span>
          <span class="tabla-th col-nombre">Descripción</span>
          <span class="tabla-th col-precio">Precio de lista</span>
          <span class="tabla-th col-cantidad">Cantidad</span>
        </div>

        <ul class="grilla vista-lista" data-grilla-productos>
          <?php foreach ($resultado['items'] as $producto): ?>
            <?php
            $pid = (int) $producto['producto_id'];
            ?>
            <li class="producto">
              <a class="producto-figura" href="<?= e(url('/productos/' . $pid)) ?>">
                <?php if (!empty($producto['imagen_id'])): ?>
                  <img src="<?= e(url('/imagenes/' . (int) $producto['imagen_id'])) ?>"
                       alt="<?= e($producto['producto_nombre']) ?>" loading="lazy">
                <?php else: ?>
                  <span class="producto-sinfoto">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
                      <rect x="3" y="4" width="18" height="16" rx="2"/>
                      <circle cx="9" cy="10" r="2"/><path d="m4 18 5-5 4 4 3-3 4 4"/>
                    </svg>
                  </span>
                <?php endif; ?>
              </a>

              <div class="producto-cuerpo">
                <div class="producto-col-codigo">
                  <span class="producto-codigo"><?= e($producto['producto_codigo']) ?></span>
                </div>

                <div class="producto-info">
                  <h2 class="producto-titulo">
                    <a href="<?= e(url('/productos/' . $pid)) ?>"><strong><?= e($producto['producto_nombre']) ?></strong></a>
                  </h2>
                  <?php $compat = $producto['compatibilidad'] ?? null; ?>
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
                  <?php elseif (!empty($producto['compatibilidad_texto'])): ?>
                    <p class="producto-compatibilidad" title="<?= e($producto['compatibilidad_texto']) ?>">
                      <?= e($producto['compatibilidad_texto']) ?>
                    </p>
                  <?php endif; ?>
                </div>

                <div class="producto-col-precio">
                  <span class="precio"><?= e(money((float) ($producto['precio_lista'] ?? $producto['precio_neto_lista']))) ?></span>
                </div>

                <?php
                $cantEnCarrito = (int) ($cantidades_carrito[$pid] ?? 0);
                $enCarrito = $cantEnCarrito > 0;
                ?>
                <div class="producto-acciones" data-url-fijar="<?= e(url('/carrito/items/fijar')) ?>" data-producto-id="<?= $pid ?>">
                  <span class="cantidad <?= $enCarrito ? 'con-unidades' : '' ?>" data-cantidad>
                    <button type="button" data-paso="-1" aria-label="Restar unidad" <?= $enCarrito ? '' : 'disabled' ?>>&minus;</button>
                    <label class="solo-lectores" for="cant-<?= $pid ?>">Cantidad</label>
                    <input type="number" id="cant-<?= $pid ?>" name="cantidad" value="<?= $cantEnCarrito ?>" min="0" max="99999" step="1" autocomplete="off" data-prod-id="<?= $pid ?>" data-cant-confirmada="<?= $cantEnCarrito ?>">
                    <button type="button" data-paso="1" aria-label="Sumar unidad">+</button>
                  </span>
                </div>
              </div>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <?= $this->partial('layouts/paginador', [
          'pagina'  => $resultado['pagina'],
          'paginas' => $resultado['paginas'],
          'filtros' => $filtros,
      ]) ?>
    <?php endif; ?>
    </div>
  </section>

  <?php
  $descuentoCliente = (float) ($descuento_cliente ?? 0);
  $ivaPorcentaje = 21.0;
  if ($carrito !== null && !$carrito->vacio() && count($carrito->lineas) > 0) {
      $ivaPorcentaje = $carrito->lineas[0]->ivaPorcentaje;
      if ($descuentoCliente <= 0) {
          $descuentoCliente = $carrito->lineas[0]->descuentoPct;
      }
  }
  ?>
  <aside class="panel carrito-lateral" data-carrito-lateral>
    <div class="carrito-lateral-cabecera">
      <div class="carrito-lateral-cabecera-info">
        <div class="carrito-lateral-titulo">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M3 3h2l.4 2M7 13h10l3-8H5.4M7 13 5.4 5M7 13l-2 5h13"/>
            <circle cx="9" cy="20" r="1"/><circle cx="17" cy="20" r="1"/>
          </svg>
          <span>Mi Pedido</span>
        </div>
        <div class="carrito-lateral-subtitulo <?= ($descuentoCliente > 0) ? '' : 'oculto' ?>" data-carrito-lateral-descuento-subtitulo>
          <span>Descuento aplicado:</span>
          <strong data-carrito-lateral-descuento-subtitulo-pct><?= number_format($descuentoCliente, 0) ?>%</strong>
        </div>
      </div>
      <span class="badge-carrito" data-carrito-lateral-conteo><?= ($carrito !== null) ? $carrito->cantidadLineas() : 0 ?></span>
    </div>

    <div class="carrito-lateral-cuerpo">
      <div class="carrito-lateral-vacio <?= ($carrito !== null && !$carrito->vacio()) ? 'oculto' : '' ?>" data-carrito-lateral-vacio>
        <p>Tu carrito está vacío</p>
        <small>Elegí la cantidad y agregá productos para armar tu pedido.</small>
      </div>

      <div class="carrito-lateral-contenido <?= ($carrito !== null && !$carrito->vacio()) ? '' : 'oculto' ?>" data-carrito-lateral-contenido>
        <ul class="carrito-lateral-items" data-carrito-lateral-items>
          <?php if ($carrito !== null && !$carrito->vacio()): ?>
            <?php foreach ($carrito->lineas as $linea): ?>
              <?php
                $cantLinea = (float) $linea->cantidad;
              ?>
              <li class="carrito-item-mini" data-item-id="<?= (int) $linea->productoId ?>">
                <div class="carrito-item-mini-info">
                  <div class="carrito-item-mini-top">
                    <span class="carrito-item-mini-codigo"><?= e($linea->codigo) ?></span>
                    <button type="button" class="carrito-item-mini-quitar" data-quitar-item="<?= (int) $linea->productoId ?>" title="Quitar del carrito">&times;</button>
                  </div>
                  <strong class="carrito-item-mini-nombre"><?= e($linea->nombre) ?></strong>
                  <?php if (!empty($linea->compatibilidad)): ?>
                    <?php if (!empty($linea->compatibilidad['combinaciones'])): ?>
                      <div class="linea-compat-pills">
                        <?php foreach ($linea->compatibilidad['combinaciones'] as $combo): ?>
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
                    <?php endif; ?>
                  <?php endif; ?>
                  <span class="carrito-item-mini-cant"><?= $cantLinea ?> <?= ($cantLinea === 1.0) ? 'unidad' : 'unidades' ?></span>

                  <div class="carrito-item-mini-precios">
                    <div class="mini-precio-fila mini-precio-lista">
                      <span class="mini-label">Precio de Lista:</span>
                      <span class="mini-valor">
                        <?= e(money($cantLinea > 1 ? $linea->precioListaTotal() : $linea->precioListaNeto)) ?>
                        <?php if ($cantLinea > 1): ?>
                          <small class="mini-unitario">(<?= e(money($linea->precioListaNeto)) ?> c/u)</small>
                        <?php endif; ?>
                      </span>
                    </div>
                    <?php if ($linea->descuentoPct > 0): ?>
                      <div class="mini-precio-fila mini-precio-descuento">
                        <span class="mini-label">Descuento (<?= number_format($linea->descuentoPct, 0) ?>%):</span>
                        <span class="mini-valor texto-descuento">&minus; <?= e(money($linea->descuentoTotal())) ?></span>
                      </div>
                    <?php endif; ?>
                    <div class="mini-precio-fila mini-precio-neto">
                      <span class="mini-label">Neto:</span>
                      <strong class="mini-valor"><?= e(money($linea->netoGravado())) ?></strong>
                    </div>
                  </div>
                </div>
              </li>
            <?php endforeach; ?>
          <?php endif; ?>
        </ul>

        <div class="carrito-lateral-totales">
          <div class="carrito-lateral-fila">
            <span>Total Precio Lista</span>
            <strong data-carrito-lateral-subtotal-bruto><?= ($carrito !== null) ? e(money($carrito->subtotalBruto())) : '$ 0,00' ?></strong>
          </div>
          <div class="carrito-lateral-fila <?= ($descuentoCliente > 0) ? '' : 'oculto' ?>" data-carrito-lateral-descuento-fila>
            <span data-carrito-lateral-descuento-etiqueta>Descuento (<?= number_format($descuentoCliente, 0) ?>%)</span>
            <strong data-carrito-lateral-descuento class="texto-descuento">
              <?php if ($carrito !== null && $carrito->descuentos() > 0): ?>
                &minus; <?= e(money($carrito->descuentos())) ?>
              <?php else: ?>
                <?= number_format($descuentoCliente, 0) ?>%
              <?php endif; ?>
            </strong>
          </div>
          <div class="carrito-lateral-fila">
            <span>Total Neto</span>
            <strong data-carrito-lateral-subtotal-neto data-carrito-lateral-subtotal><?= ($carrito !== null) ? e(money($carrito->subtotalNeto())) : '$ 0,00' ?></strong>
          </div>
          <div class="carrito-lateral-fila">
            <span data-carrito-lateral-iva-etiqueta>IVA (<?= number_format($ivaPorcentaje, 0) ?>%)</span>
            <strong data-carrito-lateral-iva><?= ($carrito !== null) ? e(money($carrito->iva())) : '$ 0,00' ?></strong>
          </div>
          <div class="carrito-lateral-fila carrito-lateral-total">
            <span>Total</span>
            <strong data-carrito-lateral-total><?= ($carrito !== null) ? e(money($carrito->total())) : '$ 0,00' ?></strong>
          </div>
        </div>

        <div class="carrito-lateral-acciones">
          <a href="<?= e(url('/carrito')) ?>" class="btn btn-primario btn-bloque">
            Ver Carrito y Pedir &rarr;
          </a>
        </div>
      </div>
    </div>
  </aside>
</div>

<?= $this->partial('layouts/footer') ?>
