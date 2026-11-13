<?php
/**
 * Sección "Presentaciones" del formulario de productos (gestion / gestion_normal).
 * Se incluye DENTRO del <form> del producto solo si la empresa usa presentaciones (prpr_empresa_usa).
 * La maneja assets/js/productos-presentaciones.js (global ProdPres) y se graba con el producto
 * (POST presentaciones = JSON, ver core/productos_presentaciones.php).
 */
?>
<div class="prpr" id="seccionPresentaciones">
    <div class="prpr-header">
        <span class="prpr-icono"><i class="fas fa-box-open"></i></span>
        <div class="flex-grow-1">
            <div class="fw-semibold">Presentaciones
                <?= function_exists('ui_ayuda') ? ui_ayuda('Formas en que se compra o se vende el producto además de la unidad: caja, pack, pallet. Cada una dice cuántas unidades contiene y puede tener su propio código de barras (por ejemplo el DUN-14 de la caja).') : '' ?>
            </div>
            <div class="small text-muted">Unidad base: <strong class="prpr-unidad-base">unidad</strong> (la del producto, con su código de barras)</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-primary prpr-agregar"><i class="fas fa-plus me-1"></i>Agregar</button>
    </div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0 prpr-tabla">
            <thead>
                <tr>
                    <th style="min-width: 150px;">Nombre</th>
                    <th style="width: 120px;">Contiene</th>
                    <th style="min-width: 150px;">Código de barras</th>
                    <th style="width: 95px;">Peso (kg)</th>
                    <th style="min-width: 200px;">Medidas L × A × H (cm)</th>
                    <th class="text-center" style="width: 90px;" title="Presentación habitual de compra">Compra</th>
                    <th class="text-center" style="width: 90px;" title="Presentación habitual de venta">Venta</th>
                    <th style="width: 40px;"></th>
                </tr>
            </thead>
            <tbody>
                <tr class="prpr-vacio"><td colspan="8" class="text-center text-muted small py-2">Sin presentaciones: se compra y se vende solo por unidad.</td></tr>
            </tbody>
        </table>
    </div>
</div>

<style>
    .prpr { border: 1px solid var(--bs-border-color); border-radius: .5rem; overflow: hidden; background: var(--bs-body-bg); }
    .prpr-header { display: flex; align-items: center; gap: .75rem; padding: .6rem .85rem; background: var(--bs-tertiary-bg); border-bottom: 1px solid var(--bs-border-color); }
    .prpr-icono { display: inline-flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: .5rem; background: var(--bs-primary-bg-subtle); color: var(--bs-primary); flex-shrink: 0; }
    .prpr-tabla thead th { font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; color: var(--bs-secondary-color); font-weight: 600; padding: .45rem .5rem; background: transparent; }
    .prpr-tabla tbody td { padding: .35rem .5rem; border-color: var(--bs-border-color-translucent); }
    .prpr-tabla tbody tr:last-child td { border-bottom: 0; }
    .prpr-tabla input[type=number] { text-align: right; font-variant-numeric: tabular-nums; }
    .prpr-tabla .prpr-medidas { display: flex; gap: .25rem; }
    .prpr-tabla .prpr-medidas input { min-width: 0; }
    .prpr-tabla .prpr-uso { display: flex; flex-direction: column; align-items: center; gap: .1rem; font-size: .72rem; color: var(--bs-secondary-color); }
</style>
