<?php
require_once __DIR__ . '/../../db.php';
$pageTitle = 'Facturas de Clientes';
$currentPage = 'ventas_facturas';
$modudo_idx = 5;
$pagina_idx = isset($_GET['pagina_id']) ? intval($_GET['pagina_id']) : 56;
$empresa_id = intval($_GET['empresa_id'] ?? $_SESSION['empresa_id'] ?? 0);
$modulo_id = isset($_GET['modulo_id']) ? intval($_GET['modulo_id']) : 5;
// Deep-link: llega desde ventas_remitos.php (botón "Facturado"/"Facturado Parcial") con un
// remito puntual para precargar en el alta de factura nueva.
$remito_id_inicial = isset($_GET['remito_id']) ? intval($_GET['remito_id']) : 0;
// Deep-link: llega desde la lista "Facturas de este remito" (solapa Facturar de
// ventas_remitos.php) para abrir directo una factura puntual en editar/visualizar.
$factura_id_inicial = isset($_GET['factura_id']) ? intval($_GET['factura_id']) : 0;
$factura_accion_inicial = ($_GET['factura_accion'] ?? '') === 'visualizar' ? 'visualizar' : 'editar';
define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>
<main class="app-main">
  <div class="app-content-header"><div class="container-fluid"><div class="row"><div class="col-sm-6"><h3 class="mb-0"><i class="fas fa-file-invoice-dollar me-2"></i>Facturas de Clientes</h3></div><div class="col-sm-6"><ol class="breadcrumb float-sm-end"><li class="breadcrumb-item">Ventas</li><li class="breadcrumb-item active">Facturas de Clientes</li></ol></div></div></div></div>
  <div class="app-content"><div class="container-fluid"><div class="card"><div class="card-header d-flex justify-content-between align-items-center"><div class="d-flex align-items-center gap-2"><button class="btn btn-sm btn-primary" id="btnNuevaFactura"><i class="fas fa-plus me-1"></i>Nueva Factura</button><div class="input-group input-group-sm" style="width: 250px;"><span class="input-group-text"><i class="fas fa-search"></i></span><input type="text" class="form-control" id="filtro_facturas" placeholder="Buscar factura / cliente..."></div></div><button class="btn btn-sm btn-outline-secondary" id="btnRecargar"><i class="fas fa-sync-alt"></i></button></div><div class="card-body"><table id="tablaFacturasCliente" class="table table-striped table-bordered table-sm table-slim" style="width:100%"><thead><tr><th>Número</th><th>Cliente</th><th>Tipo</th><th>Emisión</th><th>Condición De Pago</th><th>Total</th><th>CAE</th><th>Estado</th><th class="text-center">Acciones</th></tr></thead><tbody></tbody></table></div></div></div></div>
</main>
<div class="modal fade" id="modalFacturaCliente" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header bg-primary text-white">
        <h5 class="modal-title"><i class="fas fa-file-invoice-dollar me-2"></i>Nueva Factura De Cliente</h5>
        <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <!-- SOLAPAS / TABS -->
        <ul class="nav nav-tabs mb-3" id="facturaTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-factura-btn" data-bs-toggle="tab" data-bs-target="#tab-factura-contenido" type="button" role="tab">
              <i class="fas fa-file-invoice me-1"></i>Factura
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-pagos-btn" data-bs-toggle="tab" data-bs-target="#tab-pagos-contenido" type="button" role="tab">
              <i class="fas fa-money-bill-wave me-1"></i>Medios de Pago
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-asiento-btn" data-bs-toggle="tab" data-bs-target="#tab-asiento-contenido" type="button" role="tab">
              <i class="fas fa-book me-1"></i>Asiento Contable <span class="badge bg-secondary ms-1 d-none" id="badgeAsientoEstado"></span>
            </button>
          </li>
        </ul>

        <div class="tab-content" id="facturaTabsContent">
          <!-- SOLAPA 1: FACTURA -->
          <div class="tab-pane fade show active" id="tab-factura-contenido" role="tabpanel">
            <form id="formFacturaCliente">
              <div class="row g-2 mb-2">
                <div class="col-md-4">
                  <label class="form-label">Cliente</label>
                  <select class="form-select form-select-sm" id="factura_entidad_id" required></select>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Condición De Pago</label>
                  <select class="form-select form-select-sm" id="factura_condicion_pago_id"></select>
                </div>
                <div class="col-md-2">
                  <label class="form-label">Fecha Emisión</label>
                  <input type="date" class="form-control form-control-sm" id="factura_f_emision" required>
                </div>
                <div class="col-md-3">
                  <label class="form-label">Vencimiento</label>
                  <input type="date" class="form-control form-control-sm" id="factura_f_vto" required>
                </div>
              </div>
              <!-- Solo con "Consumidor Final" elegido en el combo de cliente (precio de mostrador, contado) -->
              <div class="row g-2 mb-2" id="grupo_factura_consumidor_final" style="display: none;">
                <div class="col-md-4">
                  <label class="form-label">Nombre Del Consumidor Final</label>
                  <input type="text" class="form-control form-control-sm" id="factura_consumidor_final_nombre" maxlength="150" placeholder="Nombre y apellido / referencia">
                </div>
              </div>
              <div class="alert small py-1 px-2 mb-3" id="condicionClienteInfo">Seleccione un cliente para cargar sus condiciones comerciales.</div>
              <div class="row g-2 mb-3">
                <div class="col-md-4">
                  <label class="form-label">Punto De Venta</label>
                  <select class="form-select form-select-sm" id="factura_punto_venta_id" required><option value="">Seleccionar</option></select>
                  <input type="hidden" id="factura_sucursal_id">
                </div>
                <div class="col-md-4">
                  <label class="form-label">Tipo Comprobante</label>
                  <select class="form-select form-select-sm" id="factura_comprobante_tipo_id" required><option value="">Seleccionar PV</option></select>
                </div>
                <div class="col-md-12 d-none" id="factura_asociado_wrap">
                  <label class="form-label">Comprobante asociado <small class="text-muted">(nota de crédito / débito)</small></label>
                  <select class="form-select form-select-sm" id="factura_comprobante_asociado_id"><option value="">Sin comprobante asociado</option></select>
                  <small class="text-muted">Si el comprobante asociado tiene saldo, la nota de crédito se le aplica al confirmarla (con recibo en $0).</small>
                </div>
                <div class="col-md-4">
                  <label class="form-label">Número</label>
                  <input type="text" class="form-control form-control-sm" id="factura_comprobante_nro" value="Se asigna al confirmar" readonly>
                  <div class="form-text" id="factura_cae_info"></div>
                </div>
              </div>
              <div class="card border-primary mb-3" id="cardRemitosFactura">
                <div class="card-header py-2" id="tituloCardRemitosFactura">Remitos Pendientes De Facturar</div>
                <div class="card-body p-2" id="contenedorRemitosFactura"><div class="text-muted small">Seleccione un cliente.</div></div>
              </div>
              <div class="card border-secondary mb-3" id="cardAgregarProductoManual">
                <div class="card-body p-2">
                  <div class="row g-2">
                    <div class="col-md-4">
                      <input class="form-control form-control-sm" id="factura_producto_busqueda" placeholder="Código, nombre o compatibilidad (ej: amortiguador s-10 1999)">
                      <div id="factura_productos_resultados" class="list-group"></div>
                    </div>
                    <div class="col-md-2"><input type="number" class="form-control form-control-sm" id="factura_producto_cantidad" min="0.01" step="0.01" value="1" placeholder="Cant."></div>
                    <div class="col-md-2"><input type="number" class="form-control form-control-sm" id="factura_producto_precio" min="0" step="0.01" value="0" placeholder="Precio bruto"></div>
                    <div class="col-md-2"><input type="number" class="form-control form-control-sm" id="factura_producto_descuento_pct" min="0" max="100" step="0.01" value="0" placeholder="% Desc."></div>
                    <div class="col-md-2"><button type="button" class="btn btn-sm btn-outline-primary w-100" id="btnAgregarProductoFactura">Agregar</button></div>
                  </div>
                </div>
              </div>
              <div id="contenedorDetalleFactura"></div>
              <textarea class="form-control form-control-sm mt-3" id="factura_observaciones" placeholder="Observaciones"></textarea>
              <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" id="factura_destino_bien_uso" value="1">
                <label class="form-check-label small" for="factura_destino_bien_uso">El cliente declara destino <strong>bien de uso / insumo</strong> (sin percepción de IIBB; se imprime en la factura)</label>
              </div>
            </form>
          </div>

          <!-- SOLAPA 2: MEDIOS DE PAGO -->
          <div class="tab-pane fade" id="tab-pagos-contenido" role="tabpanel">
            <div class="card border-info mb-3">
              <div class="card-header py-2 bg-info bg-opacity-10 d-flex justify-content-between align-items-center">
                <span class="fw-semibold text-info"><i class="fas fa-money-bill-wave me-2"></i>Medios de Pago y Cobranzas</span>
                <span class="badge bg-info text-dark" id="badgeEstadoPago">Pendiente de Pago</span>
              </div>
              <div class="card-body p-3" id="contenedorMediosPago">
                <!-- Resumen de saldo -->
                <div class="factura-totales-box mb-3">
                  <div class="factura-totales-item"><span class="factura-totales-label">Total Factura</span><span class="factura-totales-valor" id="cobroTotalFactura">$0,00</span></div>
                  <div class="factura-totales-item"><span class="factura-totales-label">Cobrado / Aplicado</span><span class="factura-totales-valor" id="cobroCobrado">$0,00</span></div>
                  <div class="factura-totales-final"><span class="factura-totales-label">Saldo Pendiente</span><span class="factura-totales-valor" id="cobroSaldo">$0,00</span></div>
                </div>

                <div class="alert alert-light border small py-2 mb-3" id="cobroMotivo" style="display:none;"></div>

                <!-- Nuevo recibo -->
                <div id="cobroFormulario" style="display:none;">
                  <div class="row g-2 mb-2">
                    <div class="col-md-4">
                      <label class="form-label">Punto De Venta Del Recibo</label>
                      <select class="form-select form-select-sm" id="cobro_punto_venta_id"></select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Tipo De Recibo</label>
                      <select class="form-select form-select-sm" id="cobro_comprobante_tipo_id"></select>
                    </div>
                    <div class="col-md-4">
                      <label class="form-label">Fecha Del Recibo</label>
                      <input type="date" class="form-control form-control-sm" id="cobro_f_emision">
                    </div>
                  </div>
                  <table class="table table-sm table-bordered mb-2" id="tablaCobroMedios">
                    <thead class="table-light">
                      <tr><th style="width:22%">Medio</th><th>Detalle</th><th style="width:16%" class="text-end">Importe</th><th style="width:40px"></th></tr>
                    </thead>
                    <tbody></tbody>
                    <tfoot>
                      <tr>
                        <td colspan="2">
                          <button type="button" class="btn btn-sm btn-outline-primary" id="btnCobroAgregarMedio"><i class="fas fa-plus me-1"></i>Agregar Medio</button>
                          <button type="button" class="btn btn-sm btn-outline-secondary" id="btnCobroCompletarSaldo" title="Completar la última fila con el saldo pendiente"><i class="fas fa-equals me-1"></i>Completar Saldo</button>
                        </td>
                        <td class="text-end fw-bold" id="cobroTotalMedios">$0,00</td>
                        <td></td>
                      </tr>
                    </tfoot>
                  </table>
                  <input type="text" class="form-control form-control-sm mb-2" id="cobro_observaciones" maxlength="200" placeholder="Observaciones del recibo (opcional)">
                  <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted" id="cobroDiferencia"></small>
                    <button type="button" class="btn btn-sm btn-success px-4" id="btnEmitirRecibo"><i class="fas fa-receipt me-1"></i>Emitir Recibo</button>
                  </div>
                </div>

                <!-- Recibos / comprobantes ya aplicados -->
                <h6 class="mt-4 mb-2 fw-semibold"><i class="fas fa-list me-1"></i>Cobros Aplicados</h6>
                <table class="table table-sm table-bordered mb-0" id="tablaCobrosAplicados">
                  <thead class="table-light">
                    <tr><th>Comprobante</th><th>Fecha</th><th>Medios</th><th class="text-end">Importe Aplicado</th><th>Estado</th></tr>
                  </thead>
                  <tbody><tr><td colspan="5" class="text-muted text-center">Sin cobros aplicados.</td></tr></tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- SOLAPA 3: ASIENTO CONTABLE -->
          <div class="tab-pane fade" id="tab-asiento-contenido" role="tabpanel">
            <div id="contenedorAsientoVacio" class="text-center py-5 text-muted">
              <i class="fas fa-book fa-3x mb-2 text-secondary opacity-50"></i>
              <h6 class="fw-bold text-dark">Asiento Contable Pendiente</h6>
              <p class="small text-muted mb-3">El asiento contable se genera automáticamente al <strong>confirmar</strong> la factura.</p>
              <button type="button" class="btn btn-sm btn-outline-primary" id="btnGenerarAsientoManual" style="display:none;">
                <i class="fas fa-sync-alt me-1"></i>Generar Asiento Ahora
              </button>
            </div>
            <div id="contenedorAsientoDetalle" style="display:none;">
              <div class="card border-secondary mb-3">
                <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                  <div>
                    <span class="fw-bold me-2"><i class="fas fa-book me-1 text-primary"></i>Asiento Contable #<span id="asientoIdSpan"></span></span>
                    <span class="badge bg-primary" id="asientoTipoSpan">Automático</span>
                    <span class="badge bg-success ms-1" id="asientoEstadoSpan">Registrado</span>
                  </div>
                  <div>
                    <small class="text-muted me-3">Fecha: <strong id="asientoFechaSpan"></strong></small>
                    <button type="button" class="btn btn-xs btn-outline-secondary" id="btnRefrescarAsiento" title="Actualizar Asiento">
                      <i class="fas fa-sync-alt me-1"></i>Regenerar
                    </button>
                  </div>
                </div>
                <div class="card-body p-2">
                  <div class="small text-muted mb-2 fst-italic" id="asientoDescripcionSpan"></div>
                  <table class="table table-sm table-bordered table-hover mb-0" id="tablaAsientoDetalles" style="font-size: .8rem;">
                    <thead class="table-light">
                      <tr>
                        <th style="width: 14%;">Cuenta</th>
                        <th style="width: 30%;">Nombre de Cuenta</th>
                        <th style="width: 14%;" class="text-end">DEBE</th>
                        <th style="width: 14%;" class="text-end">HABER</th>
                        <th style="width: 28%;">Descripción / Movimiento</th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                    <tfoot class="table-light fw-bold">
                      <tr>
                        <td colspan="2" class="text-end">TOTALES:</td>
                        <td class="text-end text-primary" id="asientoTotalDebe">$0.00</td>
                        <td class="text-end text-primary" id="asientoTotalHaber">$0.00</td>
                        <td class="text-center" id="asientoBalanceBadge"><span class="badge bg-success"><i class="fas fa-check me-1"></i>Balanceado</span></td>
                      </tr>
                    </tfoot>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <div id="modalFacturaAccionesExtra" class="btn-group me-2"></div>
        <strong class="me-auto">Total: $<span id="factura_total">0.00</span></strong>
        <button class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Cancelar</button>
        <button class="btn btn-primary btn-sm px-4" id="btnGuardarFactura">Guardar Factura</button>
      </div>
    </div>
  </div>
</div>
<style>
#factura_productos_resultados:empty{display:none}
#factura_productos_resultados .list-group-item{cursor:pointer}
.factura-remito{border-bottom:1px solid #dee2e6;padding:.5rem}
.factura-remito:last-child{border-bottom:0}
.factura-cantidad-stepper{display:inline-flex;align-items:center;gap:0;border:1px solid #ced4da;border-radius:4px;overflow:hidden;width:fit-content;margin-left:auto}
.factura-cantidad-stepper .btn{border:none;border-radius:0;padding:.1rem .4rem;line-height:1}
.factura-cantidad-stepper input{width:56px;text-align:center;border:none;border-left:1px solid #ced4da;border-right:1px solid #ced4da;border-radius:0}
.factura-cantidad-stepper input:focus{box-shadow:none;background:#eef6ff}
.no-spinner::-webkit-inner-spin-button,.no-spinner::-webkit-outer-spin-button{-webkit-appearance:none;margin:0}
.no-spinner{-moz-appearance:textfield;appearance:textfield}

/* ===== TABLA MÁS COMPACTA (slim) ===== */
#tablaFacturasCliente.table-slim th,
#tablaFacturasCliente.table-slim td {
    font-size: 0.78rem !important;
    padding: 0.3rem 0.5rem !important;
    line-height: 1.2 !important;
    vertical-align: middle !important;
}
#tablaFacturasCliente.table-slim td small {
    font-size: 0.7rem !important;
}
#tablaFacturasCliente.table-slim .btn-accion-factura {
    padding: 0.15rem 0.4rem !important;
    font-size: 0.7rem !important;
}

/* Modal más ancho que el modal-xl estándar de Bootstrap (~1140px), pero sin romper
   responsive: max-width solo pesa cuando el viewport lo permite, en pantallas chicas
   Bootstrap sigue acotando el modal-dialog por su cuenta. */
#modalFacturaCliente .modal-dialog.modal-xl{max-width:1300px}
#modalFacturaCliente .modal-body{font-size:.82rem}
#modalFacturaCliente .modal-title{font-size:1.05rem}
#modalFacturaCliente .form-label{font-size:.72rem;margin-bottom:.15rem}
#modalFacturaCliente .card-header{font-size:.8rem;padding:.4rem .6rem}
#modalFacturaCliente table{font-size:.78rem}
#modalFacturaCliente .btn-sm{font-size:.75rem}
#modalFacturaCliente .factura-cantidad-stepper input{font-size:.78rem}
@media (max-width: 768px){
    #modalFacturaCliente .modal-body{padding:.75rem;font-size:.8rem}
}

/* Condición comercial del cliente: compacta, resaltada en pastel (no un alert gris genérico) */
#condicionClienteInfo{background:#eef2ff;border:1px solid #dbe4ff;color:#3949ab;border-radius:6px;font-size:.75rem;line-height:1.4}

/* Barra de totales: que la línea "Total A Pagar" se note más que el resto del desglose */
.factura-totales-box{background:#f8f9fb;border:1px solid #dee2e6;border-radius:8px;padding:.5rem .6rem;display:flex;align-items:stretch;gap:.4rem}
.factura-totales-item{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center}
.factura-totales-item .factura-totales-label{font-size:.66rem;text-transform:uppercase;letter-spacing:.3px;color:#6c757d}
.factura-totales-item .factura-totales-valor{font-weight:600;font-size:.85rem;color:#343a40}
.factura-totales-final{flex:1.3;display:flex;flex-direction:column;align-items:center;justify-content:center;background:#1a73e8;color:#fff;border-radius:6px;padding:.3rem .5rem}
.factura-totales-final .factura-totales-label{font-size:.68rem;text-transform:uppercase;letter-spacing:.4px;color:#fff;opacity:.85}
.factura-totales-final .factura-totales-valor{font-size:1.15rem;font-weight:700;color:#fff}
.modal-footer strong{color:#1a73e8;font-size:.95rem}
</style>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>const PAGINA_ID = <?= $pagina_idx ?>; const EMPRESA_ID = <?= $empresa_id ?>; const REMITO_ID_INICIAL = <?= $remito_id_inicial ?>; const FACTURA_ID_INICIAL = <?= $factura_id_inicial ?>; const FACTURA_ACCION_INICIAL = '<?= $factura_accion_inicial ?>';</script><script src="ventas_facturas.js?v=<?= filemtime(__DIR__.'/ventas_facturas.js') ?>"></script>
<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
