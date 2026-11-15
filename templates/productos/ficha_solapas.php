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
    <li class="nav-item"><button class="nav-link pfic-requiere-producto" type="button" data-bs-toggle="tab" data-bs-target="#pficPrecios"><i class="fas fa-dollar-sign me-1"></i>Precios</button></li>
    <li class="nav-item"><button class="nav-link pfic-requiere-producto" type="button" data-bs-toggle="tab" data-bs-target="#pficUbicaciones"><i class="fas fa-map-marker-alt me-1"></i>Ubicaciones</button></li>
    <li class="nav-item"><button class="nav-link pfic-requiere-producto" type="button" data-bs-toggle="tab" data-bs-target="#pficImagenes"><i class="fas fa-images me-1"></i>Imágenes</button></li>
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

<div class="tab-pane fade" id="pficPrecios" role="tabpanel">
    <p class="small text-muted mb-2">Precio del producto en cada lista (el mismo dato que la página Lista de Precios). Un precio cargado acá queda como manual.</p>
    <?php if (!$pfic_ro): ?>
    <div class="row g-2 align-items-end mb-2" id="pficPreForm">
        <input type="hidden" id="pficPreId" value="">
        <div class="col-md-5">
            <label class="form-label form-label-sm" for="pficPreLista">Lista</label>
            <select class="form-select form-select-sm" id="pficPreLista"><option value="">Elegir…</option></select>
        </div>
        <div class="col-md-3">
            <label class="form-label form-label-sm" for="pficPrePrecio">Precio sin IVA</label>
            <input type="number" class="form-control form-control-sm text-end" id="pficPrePrecio" min="0" step="0.01">
        </div>
        <div class="col-md-2"></div>
        <div class="col-md-2 d-flex gap-1">
            <button type="button" class="btn btn-sm btn-primary flex-fill" id="pficPreGuardar">Agregar</button>
            <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="pficPreCancelar" title="Cancelar"><i class="fas fa-times"></i></button>
        </div>
    </div>
    <?php endif; ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Lista</th><th class="text-end">Precio sin IVA</th><th class="text-end">Precio con IVA</th><th>Actualizado</th><?php if (!$pfic_ro): ?><th style="width: 110px;"></th><?php endif; ?></tr></thead>
            <tbody id="pficPreFilas"></tbody>
        </table>
    </div>
</div>

<div class="tab-pane fade" id="pficUbicaciones" role="tabpanel">
    <p class="small text-muted mb-2">Dónde se guarda el producto en el depósito (de sus sucursales). Las ubicaciones se arman en Depósitos - Ubicaciones o acá con "Nueva ubicación".</p>
    <?php if (!$pfic_ro): ?>
    <div id="pficUbiForm">
        <div class="row g-2 align-items-end mb-2">
            <div class="col-md-8">
                <label class="form-label form-label-sm" for="pficUbiElegir">Ubicación</label>
                <select class="form-select form-select-sm" id="pficUbiElegir"><option value="">Elegir…</option></select>
            </div>
            <div class="col-md-4 d-flex gap-1">
                <button type="button" class="btn btn-sm btn-primary flex-fill" id="pficUbiAsignar">Asignar</button>
                <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" id="pficUbiNueva"><i class="fas fa-plus me-1"></i>Nueva ubicación</button>
            </div>
        </div>
        <div class="border rounded p-2 mb-2 d-none" id="pficUbiNuevaForm">
            <div class="row g-2">
                <div class="col-md-3"><label class="form-label form-label-sm" for="pficUbiSucursal">Sucursal</label><select class="form-select form-select-sm" id="pficUbiSucursal"></select></div>
                <div class="col-md-3"><label class="form-label form-label-sm" for="pficUbiBoca">Boca</label><select class="form-select form-select-sm" id="pficUbiBoca"></select></div>
                <div class="col-md-6"><label class="form-label form-label-sm" for="pficUbiDesc">Descripción</label><input type="text" class="form-control form-control-sm" id="pficUbiDesc" maxlength="255"></div>
                <div class="col-6 col-md-2"><label class="form-label form-label-sm" for="pficUbiSeccion">Sección</label><input type="text" class="form-control form-control-sm" id="pficUbiSeccion" maxlength="50"></div>
                <div class="col-6 col-md-2"><label class="form-label form-label-sm" for="pficUbiEstanteria">Estantería</label><input type="text" class="form-control form-control-sm" id="pficUbiEstanteria" maxlength="50"></div>
                <div class="col-6 col-md-2"><label class="form-label form-label-sm" for="pficUbiEstante">Estante</label><input type="text" class="form-control form-control-sm" id="pficUbiEstante" maxlength="50"></div>
                <div class="col-6 col-md-2"><label class="form-label form-label-sm" for="pficUbiPosicion">Posición</label><input type="text" class="form-control form-control-sm" id="pficUbiPosicion" maxlength="50"></div>
                <div class="col-md-4 d-flex align-items-end gap-1">
                    <button type="button" class="btn btn-sm btn-primary flex-fill" id="pficUbiCrear">Crear y asignar</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="pficUbiNuevaCancelar" title="Cancelar"><i class="fas fa-times"></i></button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    <div class="table-responsive">
        <table class="table table-sm table-hover align-middle mb-0">
            <thead class="table-light"><tr><th>Sucursal</th><th>Boca</th><th>Sección</th><th>Estantería</th><th>Estante</th><th>Posición</th><th>Descripción</th><?php if (!$pfic_ro): ?><th style="width: 60px;"></th><?php endif; ?></tr></thead>
            <tbody id="pficUbiFilas"></tbody>
        </table>
    </div>
</div>

<div class="tab-pane fade" id="pficImagenes" role="tabpanel">
    <p class="small text-muted mb-2">Fotos del producto (JPG, PNG o WebP de hasta 1 MB; se achican y se guardan livianas). La principal es la que se muestra primero.</p>
    <?php if (!$pfic_ro): ?>
    <div class="row g-2 align-items-end mb-3" id="pficImgForm">
        <div class="col-md-5">
            <label class="form-label form-label-sm" for="pficImgArchivo">Imagen</label>
            <input type="file" class="form-control form-control-sm" id="pficImgArchivo" accept="image/jpeg,image/png,image/webp,image/gif">
        </div>
        <div class="col-md-4">
            <label class="form-label form-label-sm" for="pficImgDesc">Descripción</label>
            <input type="text" class="form-control form-control-sm" id="pficImgDesc" maxlength="255">
        </div>
        <div class="col-md-1">
            <div class="form-check mb-1"><input class="form-check-input" type="checkbox" id="pficImgPrincipal"><label class="form-check-label small" for="pficImgPrincipal">Principal</label></div>
        </div>
        <div class="col-md-2"><button type="button" class="btn btn-sm btn-primary w-100" id="pficImgSubir"><i class="fas fa-upload me-1"></i>Subir</button></div>
    </div>
    <?php endif; ?>
    <div class="row g-2" id="pficImgGaleria"></div>
</div>

</div><!-- /.tab-content -->
<?php endif;
