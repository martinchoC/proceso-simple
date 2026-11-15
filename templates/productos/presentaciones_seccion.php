<?php
/**
 * Sección "Presentaciones" del formulario de productos (gestion / gestion_normal).
 * Se incluye DENTRO del <form> del producto solo si la empresa usa presentaciones (prpr_empresa_usa).
 * La maneja assets/js/productos-presentaciones.js (global ProdPres) y se graba con el producto
 * (POST presentaciones = JSON, ver core/productos_presentaciones.php).
 *
 * Si además la empresa vende por presentación (lpre_empresa_usa, core/listas_precios_presentaciones.php), muestra
 * mínimo / múltiplo de venta de la unidad y de cada presentación, y el precio de cada presentación por lista.
 * Usa la conexión ($conexion o $conn) y $empresa_idx de la página que la incluye.
 */
$prpr_conn = $conexion ?? $conn;
$prpr_venta = function_exists('lpre_empresa_usa') && lpre_empresa_usa($prpr_conn, (int) ($empresa_idx ?? 0));
$prpr_listas = $prpr_venta ? lpre_listas_empresa($prpr_conn, (int) $empresa_idx) : [];
?>
<div class="prpr" id="seccionPresentaciones" data-venta="<?= $prpr_venta ? 1 : 0 ?>"
     data-listas="<?= htmlspecialchars(json_encode($prpr_listas, JSON_UNESCAPED_UNICODE), ENT_QUOTES) ?>">
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
    <?php if ($prpr_venta): ?>
    <div class="prpr-unidad-venta">
        <span class="small fw-semibold">Venta de la unidad suelta
            <?= function_exists('ui_ayuda') ? ui_ayuda('Cantidad mínima y múltiplo con que se vende la unidad suelta (vacío = sin restricción). Las listas que no venden la unidad suelta ofrecen solo las presentaciones con precio.') : '' ?>
        </span>
        <div class="input-group input-group-sm prpr-restr">
            <span class="input-group-text">Mínimo</span>
            <input type="number" step="any" min="0" class="form-control prpr-unidad-min" placeholder="—">
        </div>
        <div class="input-group input-group-sm prpr-restr">
            <span class="input-group-text">De a</span>
            <input type="number" step="any" min="0" class="form-control prpr-unidad-mul" placeholder="—">
        </div>
    </div>
    <?php endif; ?>
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
                    <?php if ($prpr_venta): ?>
                    <th style="width: 85px;" title="Cantidad mínima de venta, en esta presentación">Mínimo</th>
                    <th style="width: 85px;" title="Se vende de a esta cantidad, en esta presentación">De a</th>
                    <?php endif; ?>
                    <th style="width: 40px;"></th>
                </tr>
            </thead>
            <tbody>
                <tr class="prpr-vacio"><td colspan="<?= $prpr_venta ? 10 : 8 ?>" class="text-center text-muted small py-2">Sin presentaciones: se compra y se vende solo por unidad.</td></tr>
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
    .prpr-unidad-venta { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem 1rem; padding: .5rem .85rem; border-bottom: 1px solid var(--bs-border-color); }
    .prpr-unidad-venta .prpr-restr { width: 150px; }
    .prpr-unidad-venta input { text-align: right; }
    .prpr-tabla tr.prpr-fila-con-precios td { border-bottom: 0; }
    .prpr-tabla tr.prpr-precios td { padding-top: 0; }
    .prpr-precios-caja { display: flex; flex-wrap: wrap; gap: .5rem 1rem; align-items: flex-start; padding: .35rem .5rem; border-radius: .375rem; background: var(--bs-tertiary-bg); }
    .prpr-precios-caja .prpr-precios-titulo { font-size: .72rem; text-transform: uppercase; letter-spacing: .03em; color: var(--bs-secondary-color); font-weight: 600; align-self: center; }
    .prpr-precio-lista { width: 190px; }
    .prpr-precio-lista input { text-align: right; font-variant-numeric: tabular-nums; }
    .prpr-precio-ref { font-size: .72rem; color: var(--bs-secondary-color); margin-top: .1rem; min-height: 1em; }
</style>
