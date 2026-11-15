<?php
/**
 * Solapas de la ficha de producto del módulo 5 (doc 26). Se incluye dos veces alrededor del <form> del modal:
 *   $pfic_parte = 'inicio' (antes del form): las solapas y la apertura del panel "Información";
 *   $pfic_parte = 'fin'    (después del form): cierre de "Información" y los demás paneles.
 * Solo con $usa_ficha_producto_completa. $pfic_solo_lectura: sin botones de alta / edición (consulta de productos).
 * Comportamiento: assets/js/productos-ficha.js (global ProdFicha).
 */
if (empty($usa_ficha_producto_completa)) {
    return;
}
$pfic_ro = !empty($pfic_solo_lectura);
if (($pfic_parte ?? '') === 'inicio'): ?>
<ul class="nav ui-solapas mb-3" id="pficSolapas" role="tablist">
    <li class="nav-item"><button class="nav-link active" type="button" data-bs-toggle="tab" data-bs-target="#pficInfo"><i class="fas fa-id-card me-1"></i>Información</button></li>
    <li class="nav-item"><button class="nav-link pfic-requiere-producto" type="button" data-bs-toggle="tab" data-bs-target="#pficProveedores"><i class="fas fa-truck me-1"></i>Proveedores</button></li>
</ul>
<div class="tab-content">
<div class="tab-pane fade show active" id="pficInfo" role="tabpanel">
<?php else: ?>
</div><!-- /#pficInfo -->

<div class="tab-pane fade" id="pficProveedores" role="tabpanel">
    <p class="small text-muted mb-2">Proveedores que venden este producto y el código con que lo identifica cada uno (para leer sus listas y facturas). El principal es el que actualiza el costo.</p>
    <?php if (!$pfic_ro): ?>
    <div class="row g-2 align-items-end mb-2" id="pficProvForm">
        <input type="hidden" id="pficProvId" value="">
        <div class="col-md-5">
            <label class="form-label form-label-sm" for="pficProvEntidad">Proveedor</label>
            <select class="form-select form-select-sm" id="pficProvEntidad"><option value="">Elegir…</option></select>
        </div>
        <div class="col-md-3">
            <label class="form-label form-label-sm" for="pficProvCodigo">Código del proveedor</label>
            <input type="text" class="form-control form-control-sm" id="pficProvCodigo" maxlength="50">
        </div>
        <div class="col-md-2">
            <div class="form-check mb-1">
                <input class="form-check-input" type="checkbox" id="pficProvPrincipal">
                <label class="form-check-label small" for="pficProvPrincipal">Principal</label>
            </div>
        </div>
        <div class="col-md-2 d-flex gap-1">
            <button type="button" class="btn btn-sm btn-primary flex-fill" id="pficProvGuardar">Agregar</button>
            <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="pficProvCancelar" title="Cancelar"><i class="fas fa-times"></i></button>
        </div>
    </div>
    <?php endif; ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th style="width: 90px;">Principal</th><th>Proveedor</th><th>CUIT</th><th>Código del proveedor</th><?php if (!$pfic_ro): ?><th style="width: 110px;"></th><?php endif; ?></tr></thead>
            <tbody id="pficProvFilas"></tbody>
        </table>
    </div>
</div>

</div><!-- /.tab-content -->
<?php endif;
