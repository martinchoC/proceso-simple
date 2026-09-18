<?php
/**
 * Sucursales del pedido: dónde se compra y dónde se entrega.
 *
 *  - Sucursal de compra  -> gestion__ventas_pedidos.sucursal_id
 *    Las que el ERP le asignó al cliente en
 *    gestion__entidades_sucursales_compra. Si tiene más de una, elige.
 *  - Sucursal de entrega -> gestion__ventas_pedidos.entidad_sucursal_id
 *    Sucursales del propio cliente (gestion__entidades_sucursales).
 *
 * En los dos casos el servidor revalida la elegida contra las asignadas al
 * cliente antes de grabar: la lista que se ve acá no es la autorización.
 *
 * @var array<int,array<string,mixed>> $sucursalesCompra
 * @var int $compraSeleccionada
 * @var array<int,array<string,mixed>> $sucursalesEntrega
 * @var int $entregaSeleccionada
 * @var bool $editable  false en el panel lateral (sólo informativo)
 */
$editable = $editable ?? true;

/**
 * Devuelve la opción elegida de una lista, o la primera.
 *
 * @param array<int,array<string,mixed>> $opciones
 * @return array<string,mixed>|null
 */
$elegir = static function (array $opciones, int $id): ?array {
    foreach ($opciones as $o) {
        if ((int) $o['sucursal_id'] === $id) {
            return $o;
        }
    }
    return $opciones[0] ?? null;
};

/**
 * Un bloque: etiqueta + select (si hay varias y es editable) o texto.
 *
 * @param array<int,array<string,mixed>> $opciones
 */
$bloque = function (
    string $etiqueta,
    string $campo,
    array $opciones,
    int $seleccionada,
    string $vacio
) use ($editable, $elegir): void {
    echo '<div class="sucursal-dato">';
    echo '<span class="sucursal-etiqueta">' . e($etiqueta) . '</span>';

    if ($opciones === []) {
        echo '<span class="sucursal-valor sucursal-falta">' . e($vacio) . '</span>';
        echo '</div>';
        return;
    }

    if ($editable && count($opciones) > 1) {
        echo '<label class="solo-lectores" for="' . e($campo) . '">' . e($etiqueta) . '</label>';
        echo '<select id="' . e($campo) . '" name="' . e($campo) . '" class="control control-compacto">';
        foreach ($opciones as $o) {
            $id = (int) $o['sucursal_id'];
            $texto = (string) $o['sucursal_nombre'];
            if (!empty($o['direccion'])) {
                $texto .= ' — ' . $o['direccion'];
            } elseif (!empty($o['sucursal_direccion'])) {
                $texto .= ' — ' . $o['sucursal_direccion'];
            }
            echo '<option value="' . $id . '"' . ($id === $seleccionada ? ' selected' : '') . '>'
                . e($texto) . '</option>';
        }
        echo '</select>';
        echo '</div>';
        return;
    }

    // Una sola opción, o vista informativa: se muestra y viaja en un hidden
    // para que el POST lleve siempre la sucursal explícita.
    $unica = $elegir($opciones, $seleccionada);
    echo '<span class="sucursal-valor">' . e((string) $unica['sucursal_nombre']) . '</span>';

    $direccion = (string) ($unica['direccion'] ?? $unica['sucursal_direccion'] ?? '');
    if ($direccion !== '') {
        echo '<span class="sucursal-direccion">' . e($direccion) . '</span>';
    }
    if ($editable) {
        echo '<input type="hidden" name="' . e($campo) . '" value="' . (int) $unica['sucursal_id'] . '">';
    }
    echo '</div>';
};
?>
<div class="sucursales-pedido">
  <?php
  $bloque(
      'Sucursal de compra',
      'sucursal_compra_id',
      $sucursalesCompra ?? [],
      (int) ($compraSeleccionada ?? 0),
      'Sin sucursal de compra asignada — contactá a tu vendedor'
  );

  $bloque(
      'Sucursal de entrega',
      'entidad_sucursal_id',
      $sucursalesEntrega ?? [],
      (int) ($entregaSeleccionada ?? 0),
      'No tenés sucursales de entrega cargadas'
  );
  ?>
</div>
