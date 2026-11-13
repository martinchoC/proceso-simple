
<?php
/**
 * cobranzas.php
 *
 * Módulo de Cobranzas a Clientes
 * Sistema gestion_multipyme - Módulo Tesorería
 */

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../core/permisos.php';

$pageTitle = "Cobranzas a Clientes";
$currentPage = 'cobranzas';
$modudo_idx = 5; // Typo intencional y consistente con todo el ERP (ver CLAUDE.md)
$pagina_idx = 605;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <?php ui_encabezado('Cobranzas', 'bi bi-cash-coin',
        'Recibos de cobro a clientes: qué se cobró y qué comprobantes cancela.', ['Tesorería'],
        permisos_puede($conexion, $pagina_idx, 'agregar')
            ? '<button type="button" class="btn btn-primary" id="btnNuevaCobranza"><i class="bi bi-plus-lg me-1"></i>Nueva cobranza</button>' : ''); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header">
                    <form id="formFiltros" class="ui-filtros">
                        <div class="ui-f-fecha">
                            <label class="form-label" for="filtroDesde">Desde</label>
                            <input type="date" class="form-control form-control-sm" id="filtroDesde" name="desde">
                        </div>
                        <div class="ui-f-fecha">
                            <label class="form-label" for="filtroHasta">Hasta</label>
                            <input type="date" class="form-control form-control-sm" id="filtroHasta" name="hasta">
                        </div>
                        <div class="ui-f-ancha">
                            <label class="form-label" for="filtroCliente">Cliente</label>
                            <select class="form-select form-select-sm select2" id="filtroCliente" name="entidad_id">
                                <option value="">Todos</option>
                            </select>
                        </div>
                        <div class="ui-f-media">
                            <label class="form-label" for="filtroPuntoVenta">Punto de venta</label>
                            <select class="form-select form-select-sm" id="filtroPuntoVenta" name="comprobante_pv">
                                <option value="">Todos</option>
                            </select>
                        </div>
                        <div class="ui-f-fecha">
                            <label class="form-label" for="filtroEstado">Estado</label>
                            <select class="form-select form-select-sm" id="filtroEstado" name="estado_id">
                                <option value="">Todos</option>
                                <option value="3">Borrador</option>
                                <option value="5">Confirmado</option>
                                <option value="38">Anulado</option>
                            </select>
                        </div>
                        <div class="ms-auto d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-light" id="btnFiltrar" title="Aplicar filtros">
                                <i class="bi bi-funnel"></i>
                            </button>
                            <button type="button" class="btn btn-sm btn-light" id="btnRecargar" title="Recargar">
                                <i class="bi bi-arrow-clockwise"></i>
                            </button>
                        </div>
                    </form>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tablaCobranzas" class="table table-hover align-middle w-100">
                            <thead>
                                <tr>
                                    <th>Emisión</th>
                                    <th>Recibo N°</th>
                                    <th>Cliente</th>
                                    <th>CUIT</th>
                                    <th>Sucursal</th>
                                    <th class="text-end">Total</th>
                                    <th>Aplicación</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- MODAL DE EMISIÓN / EDICIÓN DE COBRANZA -->
<div class="modal fade" id="modalCobranza" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="modalCobranzaLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-lg-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalCobranzaLabel">
                    <i class="bi bi-cash-coin me-2"></i>Nueva cobranza
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="formCobranza">
                    <input type="hidden" id="cobranza_id" name="cobranza_id" value="0">

                    <!-- Datos del recibo -->
                    <section class="ui-seccion">
                        <div class="row g-3">
                            <div class="col-md-5 position-relative">
                                <label class="form-label ui-requerido" for="inputBuscarCliente">Cliente</label>
                                <input type="hidden" id="selectCliente" name="entidad_id" value="">
                                <div class="input-group">
                                    <span class="input-group-text bg-white text-muted"><i class="bi bi-search"></i></span>
                                    <input type="text" class="form-control" id="inputBuscarCliente" autocomplete="off" placeholder="Buscar por Razón Social, CUIT o Nombre de Fantasía…">
                                    <button class="btn btn-outline-secondary d-none" type="button" id="btnLimpiarCliente" title="Limpiar cliente">
                                        <i class="bi bi-x-lg"></i>
                                    </button>
                                </div>
                                <div id="dropdownClientes" class="dropdown-menu w-100 shadow-sm p-0" style="max-height: 260px; overflow-y: auto; z-index: 1060; display: none;"></div>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label ui-requerido" for="inputFechaEmision">Fecha</label>
                                <input type="date" class="form-control" id="inputFechaEmision" name="f_emision" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                            <div class="col-6 col-md-2">
                                <label class="form-label ui-requerido" for="selectSucursal">Sucursal</label>
                                <select class="form-select" id="selectSucursal" name="sucursal_id" required>
                                </select>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label ui-requerido" for="selectPuntoVenta">Punto de venta</label>
                                <select class="form-select" id="selectPuntoVenta" name="comprobante_pv" required>
                                    <option value="">Elegí…</option>
                                </select>
                            </div>
                        </div>
                        <details class="ui-mas-opciones">
                            <summary>Más datos del recibo</summary>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label ui-requerido" for="selectComprobanteTipo">Tipo de comprobante</label>
                                    <select class="form-select" id="selectComprobanteTipo" name="comprobante_tipo_id" required>
                                        <option value="">Elegí el punto de venta</option>
                                    </select>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label" for="inputComprobanteNro">Número</label>
                                    <input type="text" class="form-control font-monospace" id="inputComprobanteNro" value="Al confirmar" readonly>
                                </div>
                                <div class="col-6 col-md-2">
                                    <label class="form-label" for="inputFechaContable">Fecha contable</label>
                                    <input type="date" class="form-control" id="inputFechaContable" name="f_contabilidad" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label" for="inputObservaciones">Observaciones</label>
                                    <input type="text" class="form-control" id="inputObservaciones" name="observaciones" placeholder="Opcional">
                                </div>
                            </div>
                        </details>
                    </section>

                    <!-- Medios de cobro -->
                    <section class="ui-seccion">
                        <div class="ui-seccion-cabecera">
                            <h6 class="ui-seccion-titulo">Qué se cobró</h6>
                            <div class="d-flex gap-1">
                                <button type="button" class="btn btn-sm btn-light" id="btnCompletarDiferencia" title="Carga como importe la diferencia entre lo imputado y lo cobrado">
                                    <i class="bi bi-calculator me-1"></i>Completar diferencia
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAgregarMedio">
                                    <i class="bi bi-plus-lg me-1"></i>Agregar medio
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0" id="tablaMedios">
                                <thead>
                                    <tr>
                                        <th style="width: 180px;">Medio</th>
                                        <th>Detalle</th>
                                        <th class="text-end" style="width: 140px;">Importe</th>
                                        <th style="width: 44px;"><span class="visually-hidden">Quitar</span></th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyMedios">
                                    <!-- Se agregan dinámicamente -->
                                </tbody>
                                <tfoot>
                                    <tr class="fw-semibold">
                                        <td colspan="2" class="text-end">Total cobrado</td>
                                        <td class="text-end ui-num" id="totalMediosDisplay">$ 0,00</td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </section>

                    <!-- Imputación -->
                    <section class="ui-seccion">
                        <div class="ui-seccion-cabecera">
                            <h6 class="ui-seccion-titulo">Qué comprobantes cancela</h6>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-light" id="btnAutoImputar" title="Imputa desde el vencimiento más antiguo">
                                    <i class="bi bi-magic me-1"></i>Automático
                                </button>
                                <button type="button" class="btn btn-sm btn-light" id="btnLimpiarImputaciones" title="Borra los importes imputados">
                                    <i class="bi bi-eraser me-1"></i>Limpiar
                                </button>
                            </div>
                        </div>
                        <div id="spinnerPendientes" class="text-center py-3 d-none">
                            <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                            <span class="ms-2 small text-muted">Cargando…</span>
                        </div>
                        <div id="vacioPendientes" class="text-muted small text-center py-3">
                            Elegí un cliente para ver sus comprobantes pendientes.
                        </div>
                        <div class="table-responsive d-none" id="contenedorTablaPendientes">
                            <table class="table table-sm align-middle mb-0" id="tablaPendientes">
                                <thead>
                                    <tr>
                                        <th class="text-center" style="width: 40px;">
                                            <input class="form-check-input" type="checkbox" id="checkTodosPendientes" title="Todos">
                                        </th>
                                        <th>Comprobante</th>
                                        <th>Emisión</th>
                                        <th>Vence</th>
                                        <th class="text-end">Total</th>
                                        <th class="text-end">Pendiente</th>
                                        <th class="text-end" style="width: 140px;">A imputar</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyPendientes">
                                    <!-- Se agregan dinámicamente -->
                                </tbody>
                                <tfoot>
                                    <tr class="fw-semibold">
                                        <td colspan="6" class="text-end">Total imputado</td>
                                        <td class="text-end ui-num" id="totalImputadoDisplay">$ 0,00</td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </section>

                    <!-- Resumen -->
                    <div class="row g-2" id="cardBalance">
                        <div class="col-4">
                            <div class="ui-kpi ui-kpi-chico">
                                <div class="ui-kpi-etiqueta">Cobrado</div>
                                <div class="ui-kpi-valor" id="resumenTotalMedios">$ 0,00</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="ui-kpi ui-kpi-chico">
                                <div class="ui-kpi-etiqueta">Imputado</div>
                                <div class="ui-kpi-valor" id="resumenTotalImputado">$ 0,00</div>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="ui-kpi ui-kpi-chico">
                                <div class="ui-kpi-etiqueta" id="labelDiferencia">Queda a favor</div>
                                <div class="ui-kpi-valor" id="resumenDiferencia">$ 0,00</div>
                            </div>
                        </div>
                        <div class="col-12">
                            <div id="alertaDesbalance" class="alert alert-danger ui-aviso mb-0 d-none">
                                <i class="bi bi-exclamation-triangle"></i>
                                <span>Lo imputado supera lo cobrado. Ajustá los importes o agregá otro medio.</span>
                            </div>
                            <div id="avisoCobroACuenta" class="alert alert-info ui-aviso mb-0 d-none">
                                <i class="bi bi-info-circle"></i>
                                <span>Lo que sobra queda a favor del cliente para usarlo más adelante.</span>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-outline-secondary" id="btnGuardarBorrador">Guardar borrador</button>
                <button type="button" class="btn btn-primary" id="btnConfirmarEmision">
                    <i class="bi bi-check-lg me-1"></i>Confirmar y emitir
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE VISUALIZACIÓN DE RECIBO -->
<div class="modal fade" id="modalVerRecibo" tabindex="-1" aria-labelledby="modalVerReciboLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable modal-fullscreen-lg-down">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalVerReciboLabel">
                    <i class="bi bi-receipt me-2"></i>Recibo de cobro
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4" id="cuerpoReciboImpresion">
                <!-- Se llena vía JS con formato formal de recibo y asiento contable automático -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="btnImprimirRecibo">
                    <i class="bi bi-printer me-1"></i>Imprimir
                </button>
            </div>
        </div>
    </div>
</div>

<!-- MODAL DE ANULACIÓN CON MOTIVO OBLIGATORIO (§2.6 / RT08) -->
<div class="modal fade" id="modalAnular" tabindex="-1" aria-labelledby="modalAnularLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalAnularLabel">
                    <i class="bi bi-slash-circle me-2"></i>Anular recibo
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formAnular">
                <div class="modal-body">
                    <input type="hidden" id="anular_cobranza_id" value="0">
                    <div class="alert alert-warning ui-aviso">
                        <i class="bi bi-exclamation-triangle"></i>
                        <span>Se revierten los movimientos de tesorería y el asiento, y los comprobantes vuelven a quedar pendientes.</span>
                    </div>
                    <label class="form-label ui-requerido" for="motivoAnulacion">Motivo</label>
                    <textarea class="form-control" id="motivoAnulacion" rows="3" required placeholder="¿Por qué se anula?"></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger" id="btnConfirmarAnulacion">Anular recibo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- SweetAlert2 -->
<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>

<!-- Scripts específicos del módulo -->
<script>
    const PAGINA_ID = <?= $pagina_idx ?>;
    const EMPRESA_ID = window.EMPRESA_ID; // empresa activa (header1.php)
</script>
<script src="cobranzas.js?v=<?= filemtime(__DIR__ . '/cobranzas.js') ?>"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
