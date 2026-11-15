<?php
/**
 * Modal "Importar precios por presentación" de Listas de precios (gestion / gestion_normal). Solo se incluye si la
 * empresa vende por presentación (lpre_empresa_usa). Lo maneja assets/js/listas-precios-importar-presentaciones.js
 * (global LpreImport); backend: core/listas_precios_presentaciones_import.php (acciones importar_presentaciones_*).
 */
?>
<div class="modal fade" id="modalLpreImport" tabindex="-1" aria-labelledby="modalLpreImportLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLpreImportLabel"><i class="fas fa-box-open me-2"></i>Importar precios por presentación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="ui-seccion">
                    <div class="d-flex flex-wrap align-items-end gap-2">
                        <div class="flex-grow-1">
                            <label class="form-label small mb-1" for="lpreImpArchivo">Planilla (CSV, XLSX o XLS)
                                <?= function_exists('ui_ayuda') ? ui_ayuda('Primera fila con encabezados. La presentación se reconoce por "codigo_barras", o por "codigo" (del producto) y "presentacion" (su nombre). Precios: una columna por lista con su nombre o código de encabezado, o las columnas "lista" y "precio". Celda vacía = no cambia; 0 = quita el precio. El precio es el de la presentación entera. La plantilla trae todo lo que hay hoy.') : '' ?>
                            </label>
                            <input type="file" class="form-control form-control-sm" id="lpreImpArchivo" accept=".csv,.txt,.xlsx,.xls">
                        </div>
                        <a class="btn btn-sm btn-outline-secondary" id="lpreImpPlantilla" href="#"><i class="fas fa-download me-1"></i>Plantilla con los precios actuales</a>
                        <button type="button" class="btn btn-sm btn-primary" id="lpreImpSimular"><i class="fas fa-search me-1"></i>Simular</button>
                    </div>
                </div>
                <div id="lpreImpResultado" class="d-none mt-3">
                    <div class="row g-2 mb-2" id="lpreImpKpis"></div>
                    <div class="small text-muted mb-2" id="lpreImpNota"></div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width: 60px;">Fila</th>
                                    <th>Presentación</th>
                                    <th>Lista</th>
                                    <th class="text-end">Actual</th>
                                    <th class="text-end">Nuevo</th>
                                    <th>Resultado</th>
                                </tr>
                            </thead>
                            <tbody id="lpreImpFilas"></tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-success" id="lpreImpConfirmar" disabled><i class="fas fa-check me-1"></i>Grabar los cambios</button>
            </div>
        </div>
    </div>
</div>
