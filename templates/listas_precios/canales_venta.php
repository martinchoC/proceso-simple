<?php
/**
 * Ventana "Canales de venta" de Listas de precios (gestion / gestion_normal). Solo se incluye si la empresa usa
 * canales de venta (cvta_empresa_usa). La maneja assets/js/canales-venta.js (global CanalesVenta); backend:
 * cvta_despachar() de core/canales_venta.php (acciones canales_listar / canal_guardar).
 */
?>
<div class="modal fade" id="modalCanalesVenta" tabindex="-1" aria-labelledby="modalCanalesVentaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCanalesVentaLabel"><i class="fas fa-route me-2"></i>Canales de venta
                    <?= function_exists('ui_ayuda') ? ui_ayuda('El canal (minorista, mayorista) se asigna a cada lista de precios y el cliente toma el de su lista. En Sucursales se elige qué canales vende cada una. Un canal inactivo deja de contar en listas y sucursales.') : '' ?>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-2">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th style="width: 9rem">Código</th>
                                <th style="width: 6rem">Orden</th>
                                <th style="width: 6rem">Activo</th>
                                <th style="width: 6rem"></th>
                            </tr>
                        </thead>
                        <tbody id="cvtaFilas"></tbody>
                    </table>
                </div>
                <button type="button" class="btn btn-sm btn-outline-primary" id="cvtaNuevo" data-funcion="editar"><i class="fas fa-plus me-1"></i>Agregar canal</button>
            </div>
        </div>
    </div>
</div>
