<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Obligaciones de Pago";
$currentPage = 'obligaciones_pago';
$modudo_idx = 5;
$pagina_idx = 635; // ID en conf__paginas para Obligaciones de Pago
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
require_once ROOT_PATH . '/core/csrf.php';
?>

<main class="app-main">
    <?php ui_encabezado('Obligaciones de Pago', 'bi bi-cash-stack', 'Todo lo que la empresa tiene que pagar y no es una factura de compra: sueldos, cargas sociales, impuestos, anticipos y otras deudas. Se cargan acá (una por una o desde una planilla), las generan las liquidaciones de impuestos, y se pagan desde Órdenes de Pago. Si una regla de aprobación aplica, quedan pendientes hasta que las aprueben; si no, quien las carga las puede pagar.', ['Tesorería']); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div class="row g-3 mb-3">
                <div class="col-6 col-lg-3"><div class="ui-kpi"><div class="ui-kpi-etiqueta">A pagar</div><div class="ui-kpi-valor" id="kpiAPagar">-</div></div></div>
                <div class="col-6 col-lg-3"><div class="ui-kpi"><div class="ui-kpi-etiqueta">Vencen en 7 días</div><div class="ui-kpi-valor" id="kpiSemana">-</div><div class="ui-kpi-sub" id="kpiSemanaN"></div></div></div>
                <div class="col-6 col-lg-3"><div class="ui-kpi"><div class="ui-kpi-etiqueta">Vencidas</div><div class="ui-kpi-valor text-danger" id="kpiVencidas">-</div><div class="ui-kpi-sub" id="kpiVencidasN"></div></div></div>
                <div class="col-6 col-lg-3"><div class="ui-kpi"><div class="ui-kpi-etiqueta">Pendientes de aprobación</div><div class="ui-kpi-valor" id="kpiAprobacion">-</div></div></div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white py-3 border-bottom ui-barra">
                    <div class="ui-filtros d-flex flex-wrap gap-2 align-items-center">
                        <div id="contenedor-boton-agregar" class="d-inline-flex gap-1"></div>
                        <select class="form-select form-select-sm ui-f-media" id="filtroVista">
                            <option value="pendientes">Pendientes (sin pagar del todo)</option>
                            <option value="aprobacion">Pendientes de aprobación</option>
                            <option value="a_pagar">Listas para pagar</option>
                            <option value="pagadas">Pagadas</option>
                            <option value="anuladas">Anuladas o eliminadas</option>
                            <option value="todas">Todas</option>
                        </select>
                        <select class="form-select form-select-sm ui-f-media" id="filtroTipo"></select>
                        <select class="form-select form-select-sm ui-f-ancha" id="filtroEntidad"></select>
                        <input type="date" class="form-control form-control-sm ui-f-fecha" id="filtroVtoDesde" title="Vence desde">
                        <input type="date" class="form-control form-control-sm ui-f-fecha" id="filtroVtoHasta" title="Vence hasta">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Recargar"><i class="fas fa-sync-alt"></i></button>
                    </div>
                </div>
                <div class="card-body p-3">
                    <table id="tablaObligaciones" class="table table-striped table-hover align-middle w-100">
                        <thead class="table-light text-nowrap">
                            <tr>
                                <th>Vence</th>
                                <th>Tipo</th>
                                <th>Beneficiario</th>
                                <th>Descripción</th>
                                <th class="ui-detalle">Comprobante</th>
                                <th class="ui-detalle">Origen</th>
                                <th class="text-end">Importe</th>
                                <th class="text-end">Pendiente</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Alta / edición -->
    <div class="modal fade" id="modalObligacion" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold" id="modalObligacionLabel">Nueva obligación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <form id="formObligacion" novalidate>
                    <div class="modal-body p-4">
                        <input type="hidden" id="obligacion_id" name="obligacion_id">
                        <div class="ui-seccion">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label ui-requerido" for="obligacion_tipo_id">Tipo</label>
                                    <select class="form-select" id="obligacion_tipo_id" name="obligacion_tipo_id" required></select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label ui-requerido" for="entidad_id">Beneficiario <?= ui_ayuda('Se listan las entidades con el rol que pide el tipo (empleado, proveedor u organismo). Si falta alguien, asignale el rol en Entidades.') ?></label>
                                    <select class="form-select" id="entidad_id" name="entidad_id" required></select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label ui-requerido" for="importe_total">Importe</label>
                                    <input type="number" step="0.01" min="0.01" class="form-control text-end" id="importe_total" name="importe_total" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label ui-requerido" for="f_emision">Fecha</label>
                                    <input type="date" class="form-control" id="f_emision" name="f_emision" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label ui-requerido" for="f_vto">Vence</label>
                                    <input type="date" class="form-control" id="f_vto" name="f_vto" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="periodo_fiscal">Período</label>
                                    <input type="month" class="form-control" id="periodo_fiscal" name="periodo_fiscal">
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label" for="descripcion">Descripción <?= ui_ayuda('Si la dejás vacía se arma con el tipo, el período y el beneficiario.') ?></label>
                                    <input type="text" class="form-control" id="descripcion" name="descripcion" maxlength="200">
                                </div>
                            </div>
                        </div>
                        <details class="ui-mas-opciones mt-3">
                            <summary>Más opciones</summary>
                            <div class="row g-3 mt-1">
                                <div class="col-md-6">
                                    <label class="form-label" for="referencia">Referencia <?= ui_ayuda('N° de recibo de sueldo, contrato, boleta...') ?></label>
                                    <input type="text" class="form-control" id="referencia" name="referencia" maxlength="100">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="nro_vep">N° de VEP / boleta</label>
                                    <input type="text" class="form-control" id="nro_vep" name="nro_vep" maxlength="50">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="sucursal_id">Sucursal</label>
                                    <select class="form-select" id="sucursal_id" name="sucursal_id"></select>
                                </div>
                                <div class="col-md-6"></div>
                                <div class="col-md-6">
                                    <label class="form-label" for="cont_cuenta_debe_id">Cuenta del gasto <?= ui_ayuda('Vacía = la del tipo de obligación. En los anticipos es la cuenta donde queda el anticipo.') ?></label>
                                    <select class="form-select" id="cont_cuenta_debe_id" name="cont_cuenta_debe_id"></select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label" for="cont_cuenta_pasivo_id">Cuenta del pasivo <?= ui_ayuda('Vacía = la del tipo, o la cuenta del rol del beneficiario. Es la que cancela la orden de pago.') ?></label>
                                    <select class="form-select" id="cont_cuenta_pasivo_id" name="cont_cuenta_pasivo_id"></select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label" for="observaciones">Observaciones</label>
                                    <textarea class="form-control" id="observaciones" name="observaciones" rows="2"></textarea>
                                </div>
                            </div>
                        </details>
                        <div class="form-check mt-3" id="grupoConfirmarAhora">
                            <input class="form-check-input" type="checkbox" id="confirmar_ahora" name="confirmar_ahora" value="1" checked>
                            <label class="form-check-label" for="confirmar_ahora">Confirmar al guardar (si no requiere aprobación, queda lista para pagar)</label>
                        </div>
                    </div>
                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnGuardar"><i class="fas fa-save me-1"></i>Guardar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Detalle -->
    <div class="modal fade" id="modalDetalle" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">Obligación</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4" id="cuerpoDetalle"></div>
            </div>
        </div>
    </div>

    <!-- Importar -->
    <div class="modal fade" id="modalImportar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">Importar obligaciones desde una planilla</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4">
                    <form id="formImportar" class="row g-3 align-items-end" novalidate>
                        <div class="col-md-5">
                            <label class="form-label ui-requerido" for="archivoImportar">Archivo (.xlsx, .xls o .csv) <?= ui_ayuda('Primera fila con los encabezados: tipo, cuit, importe y, opcionales, f_emision, f_vto, periodo, descripcion, referencia, sucursal. No se crean entidades: tienen que existir con ese CUIT.') ?></label>
                            <input type="file" class="form-control" id="archivoImportar" name="archivo" accept=".xlsx,.xls,.csv" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="tipoImportar">Tipo para todo el archivo</label>
                            <select class="form-select" id="tipoImportar" name="obligacion_tipo_id"></select>
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <button type="submit" class="btn btn-primary" id="btnPrevisualizar"><i class="bi bi-eye me-1"></i>Revisar</button>
                            <a class="btn btn-outline-secondary" id="lnkPlantilla" href="#"><i class="bi bi-download me-1"></i>Plantilla</a>
                        </div>
                    </form>
                    <div id="resultadoImportar" class="mt-4 d-none">
                        <div id="resumenImportar" class="mb-2"></div>
                        <div class="table-responsive" style="max-height: 45vh">
                            <table class="table table-sm table-bordered align-middle" id="tablaPrevia">
                                <thead class="table-light"><tr><th>Fila</th><th>Tipo</th><th>Beneficiario</th><th>Período</th><th>Vence</th><th class="text-end">Importe</th><th>Observaciones</th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>
                        <div class="d-flex flex-wrap gap-3 align-items-center mt-3">
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="impConfirmar" checked><label class="form-check-label" for="impConfirmar">Confirmarlas al importar</label></div>
                            <div class="form-check"><input class="form-check-input" type="checkbox" id="impAsignarRoles"><label class="form-check-label" for="impAsignarRoles">Asignar el rol a las entidades que no lo tienen</label></div>
                            <button type="button" class="btn btn-success ms-auto" id="btnConfirmarImportacion"><i class="fas fa-file-import me-1"></i>Importar las filas válidas</button>
                        </div>
                    </div>
                    <hr class="my-4">
                    <h6 class="fw-bold">Importaciones anteriores</h6>
                    <div id="listaImportaciones" class="small"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Aplicar anticipos -->
    <div class="modal fade" id="modalAnticipos" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold">Aplicar anticipos <?= ui_ayuda('El saldo a favor de una orden de pago (anticipo de sueldo, anticipo a proveedor o pago a cuenta) se aplica a una deuda del mismo beneficiario: sueldo, factura u obligación. No mueve dinero.') ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body p-4">
                    <label class="form-label ui-requerido" for="antEntidad">Beneficiario</label>
                    <select class="form-select mb-3" id="antEntidad"></select>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Anticipo (orden de pago con saldo)</label>
                            <select class="form-select" id="antOrigen" size="6"></select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Deuda a cancelar</label>
                            <select class="form-select" id="antDestino" size="6"></select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="antImporte">Importe <?= ui_ayuda('Vacío = todo lo posible.') ?></label>
                            <input type="number" step="0.01" min="0" class="form-control text-end" id="antImporte">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light py-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary" id="btnAplicarAnticipo"><i class="bi bi-check2 me-1"></i>Aplicar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
    <script>
        window.PAGINA_ID = <?= (int)$pagina_idx ?>;
        window.BCO_CSRF = <?= json_encode(csrf_token()) ?>;
    </script>
    <!-- Utilidades comunes de pedidos con CSRF, botones del motor y confirmaciones (global Bco) -->
    <script src="<?= asset_local('js/bancos-comun.js') ?>?v=<?= filemtime(ROOT_PATH . '/assets/js/bancos-comun.js') ?>"></script>
    <script src="obligaciones_pago.js?v=<?= filemtime(__DIR__ . '/obligaciones_pago.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
