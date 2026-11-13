<?php
/**
 * Modal de alta rápida de un concepto bancario (Conciliación Bancaria y Extractos Bancarios, gestion y gestion_normal).
 * Lo maneja assets/js/bancos-concepto-rapido.js; el _ajax.php de la página atiende agregar_concepto y clasificar.
 */
?>
            <div class="modal fade" id="modalConcepto" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-light">
                            <h5 class="modal-title fw-bold">Nuevo concepto</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <form id="formConcepto" novalidate>
                            <div class="modal-body p-4">
                                <div class="alert alert-light border small py-2 d-none" id="kLinea"></div>
                                <div class="row g-3">
                                    <div class="col-12">
                                        <label class="form-label ui-requerido" for="k_concepto">Nombre</label>
                                        <input type="text" class="form-control" id="k_concepto" name="concepto" maxlength="120" required>
                                    </div>
                                    <div class="col-12">
                                        <label class="form-label ui-requerido">Tipo</label>
                                        <div class="d-flex flex-wrap gap-3">
                                            <div class="form-check"><input class="form-check-input" type="radio" name="naturaleza" id="k_nat_gasto" value="GASTO_BANCARIO">
                                                <label class="form-check-label" for="k_nat_gasto">Gasto del banco <?= ui_ayuda('Lo cobra el banco: comisiones, impuestos, percepciones. Se registra desde Gastos Bancarios.') ?></label></div>
                                            <div class="form-check"><input class="form-check-input" type="radio" name="naturaleza" id="k_nat_propio" value="MOVIMIENTO_PROPIO">
                                                <label class="form-check-label" for="k_nat_propio">Movimiento propio <?= ui_ayuda('Ya está registrado en tesorería (cobranzas, pagos, transferencias, cheques): solo se concilia.') ?></label></div>
                                        </div>
                                    </div>
                                    <div class="col-12 k-solo-gasto">
                                        <label class="form-label ui-requerido" for="k_rol">Cómo va en el comprobante</label>
                                        <select class="form-select" id="k_rol" name="rol">
                                            <option value="ITEM">Línea con producto (comisión, impuesto no gravado...)</option>
                                            <option value="IVA">IVA del gasto</option>
                                            <option value="PERCEPCION_IVA">Percepción de IVA</option>
                                            <option value="PERCEPCION_IIBB">Percepción de IIBB</option>
                                            <option value="PERCEPCION_GANANCIAS">Percepción de Ganancias</option>
                                            <option value="RETENCION_IIBB">IIBB sobre créditos (SIRCREB)</option>
                                        </select>
                                    </div>
                                    <div class="col-12 k-solo-item">
                                        <label class="form-label ui-requerido" for="k_producto_id">Producto <?= ui_ayuda('Servicio sin stock. De él salen la cuenta contable de compra y la alícuota de IVA.') ?></label>
                                        <select class="form-select" id="k_producto_id" name="producto_id"></select>
                                    </div>
                                    <div class="col-12 k-solo-jurisdiccion">
                                        <label class="form-label ui-requerido" for="k_jurisdiccion_id">Provincia <?= ui_ayuda('Salen las provincias con la percepción de IIBB configurada en Impuestos de la empresa.') ?></label>
                                        <select class="form-select" id="k_jurisdiccion_id" name="jurisdiccion_id"></select>
                                    </div>
                                    <div class="col-12">
                                        <details class="ui-mas-opciones">
                                            <summary>Más opciones</summary>
                                            <div class="row g-3 mt-1">
                                                <div class="col-8">
                                                    <label class="form-label ui-requerido" for="k_codigo">Código <?= ui_ayuda('Sale del nombre; mayúsculas, números y guión bajo.') ?></label>
                                                    <input type="text" class="form-control text-uppercase font-monospace" id="k_codigo" name="codigo" maxlength="30" required pattern="[A-Za-z0-9_]{1,30}">
                                                </div>
                                                <div class="col-4">
                                                    <label class="form-label" for="k_orden">Orden</label>
                                                    <input type="number" class="form-control" id="k_orden" name="orden" min="0" max="9999" value="1">
                                                </div>
                                            </div>
                                        </details>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer bg-light py-2">
                                <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                                <button type="submit" class="btn btn-primary" id="btnGuardarConcepto"><i class="fas fa-save me-1"></i>Guardar</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
