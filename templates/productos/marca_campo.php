<?php
/**
 * Campo "Marca" del formulario de productos (doc 25), común a productos.php de gestion y gestion_normal y a la consulta
 * del 5. Solo se muestra con el indicador ($usa_marcas_comerciales). Comportamiento: assets/js/productos-marcas.js
 * (global ProdMarcas): carga las marcas, la ventana para administrarlas y la asignación en masa.
 * Variables: $usa_marcas_comerciales (bool), $pmar_col_clase (clase de la columna, por defecto col-md-4),
 * $pmar_solo_lectura (sin el botón de administrar).
 */
if (!empty($usa_marcas_comerciales)): ?>
<div class="<?= htmlspecialchars($pmar_col_clase ?? 'col-md-4') ?>">
    <label for="marca_comercial_id" class="form-label form-label-sm">Marca</label>
    <div class="input-group input-group-sm">
        <select class="form-select form-select-sm" id="marca_comercial_id" name="marca_comercial_id">
            <option value="">Sin marca</option>
        </select>
        <?php if (empty($pmar_solo_lectura)): ?>
        <button type="button" class="btn btn-outline-secondary" id="pmarAdministrar" title="Administrar marcas"><i class="fas fa-tags"></i></button>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
