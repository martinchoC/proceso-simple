<?php
/**
 * Vista común de la Bandeja de Documentos (Gestión 141, Gestión Normal 641). La incluye el bandeja_documentos.php de
 * cada módulo después de header1.php. JS: assets/js/bandeja-documentos.js (global Bandeja); endpoint
 * bandeja_documentos_ajax.php del módulo. Diseño: _local/docs/gestion/15_bandeja_captura.md
 */
$acciones = '<div class="d-flex gap-2 align-items-center flex-wrap">'
          . '<button type="button" class="btn btn-sm btn-outline-primary" id="btnBdjEnlaces"><i class="fas fa-link me-1"></i>Enlaces de subida</button>'
          . '<button type="button" class="btn btn-sm btn-outline-secondary" id="btnBdjSubirExtracto" data-funcion="agregar"><i class="fas fa-university me-1"></i>Subir extracto bancario</button>'
          . '<input type="file" id="bdjArchivosExtracto" class="d-none" multiple accept=".pdf,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png">'
          . '<button type="button" class="btn btn-sm btn-primary" id="btnBdjSubir" data-funcion="agregar"><i class="fas fa-upload me-1"></i>Subir documentos</button>'
          . '<input type="file" id="bdjArchivos" class="d-none" multiple accept=".pdf,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">'
          . '</div>';
?>
<main class="app-main">
    <?php ui_encabezado('Bandeja de documentos', 'fas fa-inbox',
        'Todos los comprobantes y extractos bancarios que entran desde archivo: facturas de proveedores y extractos bancarios. '
        . 'Las facturas y los gastos bancarios (Resumen Bancario C_RB) se revisan, clasifican y confirman directamente desde esta bandeja.',
        ['Importación de Comprobantes'], $acciones); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div class="row g-3 mb-3">
                <div class="col-6 col-lg-3"><div class="ui-kpi"><div class="ui-kpi-etiqueta">Sin leer o con error</div><div class="ui-kpi-valor" id="kpiBdjPendientes">-</div></div></div>
                <div class="col-6 col-lg-3"><div class="ui-kpi"><div class="ui-kpi-etiqueta">Listos para guardar</div><div class="ui-kpi-valor" id="kpiBdjListos">-</div></div></div>
                <div class="col-6 col-lg-3"><div class="ui-kpi"><div class="ui-kpi-etiqueta">Para revisar</div><div class="ui-kpi-valor" id="kpiBdjRevisar">-</div></div></div>
                <div class="col-6 col-lg-3"><div class="ui-kpi"><div class="ui-kpi-etiqueta">Guardados este mes</div><div class="ui-kpi-valor" id="kpiBdjProcesados">-</div></div></div>
            </div>

            <div class="alert alert-light border py-2 small d-none" id="bdjIaAviso"></div>

            <ul class="nav nav-tabs ui-solapas mb-3" id="bdjVistas" role="tablist">
                <li class="nav-item"><button class="nav-link active" data-vista="pendientes" type="button">Pendientes</button></li>
                <li class="nav-item"><button class="nav-link" data-vista="procesados" type="button">Guardados</button></li>
                <li class="nav-item"><button class="nav-link" data-vista="descartados" type="button">Descartados y repetidos</button></li>
                <li class="nav-item"><button class="nav-link" data-vista="todos" type="button">Todos</button></li>
            </ul>

            <div class="card shadow-sm border-0" id="bdjCard">
                <div class="card-header bg-white py-2 ui-barra">
                    <div class="ui-filtros d-flex flex-wrap gap-2 align-items-center">
                        <input type="date" class="form-control form-control-sm ui-f-fecha" id="fBdjDesde" title="Recibidos desde">
                        <input type="date" class="form-control form-control-sm ui-f-fecha" id="fBdjHasta" title="Recibidos hasta">
                        <select class="form-select form-select-sm ui-f-media" id="fBdjOrigen">
                            <option value="">Todos los orígenes</option>
                            <option value="PANTALLA">Subidos en pantalla</option>
                            <option value="PORTAL">Portal / Enlace externo</option>
                            <option value="CARPETA">Carpeta de entrada</option>
                            <option value="ANTERIOR">Anteriores a la bandeja</option>
                        </select>
                        <input type="search" class="form-control form-control-sm ui-f-media" id="fBdjTexto" placeholder="Archivo, proveedor o CUIT">
                    </div>
                    <div class="d-flex gap-2 align-items-center flex-wrap mt-2" id="bdjAccionesLote">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="btnBdjLeer" data-funcion="agregar">
                            <i class="fas fa-magic me-1"></i>Leer los que faltan</button>
                        <select class="form-select form-select-sm" id="bdjSucursal" style="max-width: 220px;" aria-label="Sucursal"></select>
                        <button type="button" class="btn btn-sm btn-success" id="btnBdjGuardar" data-funcion="agregar" disabled>
                            <i class="fas fa-save me-1"></i>Guardar en Borrador</button>
                        <button type="button" class="btn btn-sm btn-outline-danger" id="btnBdjDescartar" data-funcion="inhabilitar" disabled>
                            <i class="fas fa-ban me-1"></i>Descartar</button>
                        <span class="small text-muted" id="bdjProgreso"></span>
                    </div>
                </div>
                <div class="card-body p-3">
                    <table id="tablaBandeja" class="table table-sm table-hover align-middle w-100">
                        <thead class="table-light text-nowrap">
                            <tr>
                                <th style="width: 28px;"><input type="checkbox" class="form-check-input" id="bdjTodos" aria-label="Elegir todos"></th>
                                <th>Recibido</th>
                                <th>Archivo</th>
                                <th>Estado</th>
                                <th>Comprobante leído</th>
                                <th>Proveedor</th>
                                <th class="text-end">Total</th>
                                <th class="ui-detalle">Motivo</th>
                                <th class="ui-detalle">Origen</th>
                                <th class="ui-detalle">Subido por</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Enlaces de Subida Externa (Fase 2) -->
    <div class="modal fade" id="modalBdjEnlaces" tabindex="-1" aria-labelledby="modalBdjEnlacesLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalBdjEnlacesLabel">
                        <i class="fas fa-link text-primary me-2"></i>Enlaces para subida externa de comprobantes
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Genere enlaces públicos para que sus clientes o proveedores suban comprobantes directamente a la bandeja, sin necesidad de usuario ni contraseña.
                    </p>

                    <!-- Formulario de creación de enlace -->
                    <div class="card bg-light border-0 mb-3">
                        <div class="card-body p-3">
                            <h6 class="fw-bold mb-2">Crear nuevo enlace de subida</h6>
                            <form id="formBdjNuevoEnlace" class="row g-2 align-items-end">
                                <div class="col-md-5">
                                    <label class="form-label small fw-semibold mb-1" for="enlaceEtiqueta">Etiqueta / Descripción</label>
                                    <input type="text" class="form-control form-control-sm" id="enlaceEtiqueta" placeholder="Ej: Comprobantes Noviembre - Cliente Pérez" required maxlength="150">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold mb-1" for="enlaceVencimiento">Vencimiento (opcional)</label>
                                    <input type="date" class="form-control form-control-sm" id="enlaceVencimiento">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-semibold mb-1" for="enlaceMaxDia" title="Máximo de archivos diarios">Límite diario</label>
                                    <input type="number" class="form-control form-control-sm" id="enlaceMaxDia" value="50" min="1" max="500">
                                </div>
                                <div class="col-md-2">
                                    <button type="submit" class="btn btn-sm btn-primary w-100" id="btnCrearEnlace" data-funcion="agregar">
                                        <i class="fas fa-plus me-1"></i>Generar
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Alerta de enlace recién creado -->
                    <div class="alert alert-success d-none mb-3" id="boxEnlaceCreado">
                        <div class="fw-bold mb-1"><i class="fas fa-check-circle me-1"></i>¡Enlace generado exitosamente!</div>
                        <div class="small text-muted mb-2">Comparta la siguiente URL con el cliente o proveedor:</div>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" id="txtNuevoEnlaceUrl" readonly>
                            <button class="btn btn-outline-success" type="button" id="btnCopiarNuevoEnlace">
                                <i class="fas fa-copy me-1"></i>Copiar
                            </button>
                        </div>
                    </div>

                    <!-- Tabla de enlaces existentes -->
                    <h6 class="fw-bold mb-2">Enlaces creados</h6>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0" id="tablaBdjEnlaces">
                            <thead class="table-light small">
                                <tr>
                                    <th>Etiqueta</th>
                                    <th>Creado</th>
                                    <th>Vencimiento</th>
                                    <th>Envíos (Hoy / Total)</th>
                                    <th>Estado</th>
                                    <th class="text-end">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyBdjEnlaces">
                                <tr><td colspan="6" class="text-center text-muted py-3">Cargando enlaces...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Revisión de Factura de Proveedor dentro de la Bandeja -->
    <div class="modal fade" id="modalBdjRevisarFactura" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-fullscreen" style="padding: 8px;">
            <div class="modal-content shadow-lg border-0" style="border-radius: 8px; overflow: hidden;">
                <div class="modal-header py-1 px-3 bg-dark text-white d-flex justify-content-between align-items-center" style="min-height: 38px;">
                    <span class="small fw-semibold"><i class="fas fa-file-invoice me-2"></i>Revisión de comprobante en Bandeja de Documentos</span>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Volver a la bandeja"></button>
                </div>
                <div class="modal-body p-0" style="height: calc(100vh - 54px);">
                    <iframe id="iframeBdjFactura" src="about:blank" style="width: 100%; height: 100%; border: 0; display: block;"></iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Asistente de Extracto y Gastos Bancarios dentro de la Bandeja -->
    <div class="modal fade" id="modalBdjExtracto" tabindex="-1" aria-hidden="true" data-bs-backdrop="static">
        <div class="modal-dialog modal-xl modal-dialog-scrollable" style="max-width: 96vw;">
            <div class="modal-content">
                <div class="modal-header py-2 bg-light">
                    <h5 class="modal-title fs-6 fw-bold">
                        <i class="fas fa-university text-primary me-2"></i>Extracto y Gastos Bancarios — <span id="bdjExtNombreArchivo" class="text-muted fw-normal"></span>
                    </h5>
                    <div class="d-flex align-items-center gap-2 ms-auto me-2">
                        <a href="#" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary" id="btnBdjExtVerArchivo">
                            <i class="fas fa-eye me-1"></i>Ver archivo original
                        </a>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <!-- Barra superior: Cuenta bancaria, Proveedor del banco y Punto de venta OP -->
                    <div class="card bg-light border-0 mb-3">
                        <div class="card-body py-2 px-3">
                            <div class="row g-2 align-items-end">
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold mb-1" for="bdjExtCuenta">Cuenta bancaria</label>
                                    <select class="form-select form-select-sm" id="bdjExtCuenta"></select>
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-semibold mb-1">Proveedor del banco (para el Resumen Bancario C_RB)</label>
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="badge bg-white text-dark border px-2 py-1" id="bdjExtProveedorNombre">Sin asignar</span>
                                        <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" id="btnBdjExtProveedor">
                                            <i class="fas fa-building me-1"></i>Elegir proveedor
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-3 d-none" id="grupoBdjExtOpPv">
                                    <label class="form-label small fw-semibold mb-1" for="bdjExtOpPv">Punto de venta OP</label>
                                    <select class="form-select form-select-sm" id="bdjExtOpPv"></select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="bdjExtCargando" class="text-center text-muted py-4 d-none">
                        <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                        Analizando extracto bancario...
                    </div>

                    <!-- PASO 1: Previsualización e importación de movimientos del extracto -->
                    <div id="bdjExtPasoImportar" class="d-none">
                        <div id="bdjExtPrevBloqueos" class="alert alert-danger py-2 small d-none"></div>
                        <div id="bdjExtPrevAvisos" class="alert alert-warning py-2 small d-none"></div>

                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                            <div class="d-flex flex-wrap gap-2" id="bdjExtPrevKpis"></div>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnBdjExtNuevoConcepto">
                                    <i class="fas fa-plus me-1"></i>Nuevo concepto bancario
                                </button>
                                <button type="button" class="btn btn-sm btn-primary" id="btnBdjExtImportar">
                                    <i class="fas fa-file-import me-1"></i>Importar movimientos en la cuenta
                                </button>
                            </div>
                        </div>

                        <div class="table-responsive" style="max-height: 52vh;">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Fila</th>
                                        <th>Fecha</th>
                                        <th>Descripción en el banco</th>
                                        <th>Concepto bancario</th>
                                        <th>Referencia</th>
                                        <th class="text-end">Importe</th>
                                        <th class="text-end">Saldo</th>
                                        <th>Estado</th>
                                    </tr>
                                </thead>
                                <tbody id="bdjExtPrevTbody"></tbody>
                            </table>
                        </div>
                    </div>

                    <!-- PASO 2: Clasificación de gastos y generación de Resumen Bancario (C_RB) -->
                    <div id="bdjExtPasoGastos" class="d-none">
                        <ul class="nav nav-tabs mb-3" id="bdjExtTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active fw-semibold" id="tabBtnBdjExtGastos" data-bs-toggle="tab" data-bs-target="#paneBdjExtGastos" type="button" role="tab">
                                    <i class="fas fa-file-invoice-dollar me-1"></i>1. Generar Resumen Bancario (C_RB)
                                </button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link fw-semibold" id="tabBtnBdjExtMovs" data-bs-toggle="tab" data-bs-target="#paneBdjExtMovs" type="button" role="tab">
                                    <i class="fas fa-tags me-1"></i>2. Clasificar movimientos del extracto (<span id="bdjExtCantMovs">0</span>)
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <!-- Solapa 1: Resumen Bancario C_RB -->
                            <div class="tab-pane fade show active" id="paneBdjExtGastos" role="tabpanel">
                                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
                                    <div class="d-flex flex-wrap gap-1 align-items-center" id="bdjExtMeses"></div>
                                    <div class="d-flex gap-2">
                                        <button type="button" class="btn btn-sm btn-outline-secondary" id="btnBdjExtNuevoConcepto2">
                                            <i class="fas fa-plus me-1"></i>Nuevo concepto bancario
                                        </button>
                                    </div>
                                </div>

                                <div id="bdjExtGastosBloqueos" class="alert alert-danger py-2 small d-none"></div>
                                <div id="bdjExtGastosAvisos" class="alert alert-warning py-2 small d-none"></div>

                                <div class="row g-2 mb-3" id="bdjExtGastosKpis"></div>

                                <div class="row g-3">
                                    <div class="col-lg-5">
                                        <div class="card border shadow-none h-100">
                                            <div class="card-header bg-light py-2 fw-semibold small">
                                                <i class="fas fa-receipt me-1 text-primary"></i>Vista previa del Resumen Bancario (C_RB)
                                            </div>
                                            <div class="card-body p-2">
                                                <table class="table table-sm align-middle mb-0">
                                                    <thead class="table-light small">
                                                        <tr>
                                                            <th>Concepto / Impuesto</th>
                                                            <th class="text-center">Movs.</th>
                                                            <th class="text-end">Importe</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="bdjExtComprobante"></tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-7">
                                        <div class="card border shadow-none h-100">
                                            <div class="card-header bg-light py-2 fw-semibold small d-flex justify-content-between align-items-center">
                                                <span><i class="fas fa-list-check me-1 text-primary"></i>Débitos bancarios incluidos en el período</span>
                                                <span class="text-muted fw-normal small">Desmarque los que no quiera incluir ahora</span>
                                            </div>
                                            <div class="card-body p-0">
                                                <div class="table-responsive" style="max-height: 40vh;">
                                                    <table class="table table-sm table-hover align-middle mb-0">
                                                        <thead class="table-light sticky-top small">
                                                            <tr>
                                                                <th style="width: 30px;">Inc.</th>
                                                                <th>Fecha</th>
                                                                <th>Descripción</th>
                                                                <th>Concepto</th>
                                                                <th class="text-end">Importe</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="bdjExtLineasGasto"></tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Solapa 2: Clasificar todos los movimientos del extracto -->
                            <div class="tab-pane fade" id="paneBdjExtMovs" role="tabpanel">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <div class="btn-group btn-group-sm" role="group" id="bdjExtFiltroMovs">
                                        <button type="button" class="btn btn-outline-secondary active" data-filtro="TODOS">Todos</button>
                                        <button type="button" class="btn btn-outline-secondary" data-filtro="SIN_CONCEPTO">Sin concepto</button>
                                        <button type="button" class="btn btn-outline-secondary" data-filtro="GASTO">Gastos del banco</button>
                                    </div>
                                    <span class="small text-muted">Al asignar un concepto de Gasto del banco o IVA/Percepción, se actualiza automáticamente el Resumen Bancario (C_RB).</span>
                                </div>
                                <div class="table-responsive" style="max-height: 48vh;">
                                    <table class="table table-sm table-hover align-middle mb-0">
                                        <thead class="table-light sticky-top small">
                                            <tr>
                                                <th>Fecha</th>
                                                <th>Descripción en el banco</th>
                                                <th>Referencia</th>
                                                <th>Concepto bancario</th>
                                                <th class="text-end">Importe</th>
                                                <th>Estado</th>
                                            </tr>
                                        </thead>
                                        <tbody id="bdjExtTablaMovimientos"></tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-secondary d-none" id="btnBdjExtFinalizar">
                            <i class="fas fa-check me-1"></i>Dejar importado sin generar C_RB (Marcar procesado)
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        <button type="button" class="btn btn-sm btn-success d-none" id="btnBdjExtGenerar">
                            <i class="fas fa-check-double me-1"></i>Generar Resumen Bancario (C_RB) y conciliar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Submodal: Elegir proveedor del banco -->
    <div class="modal fade" id="modalBdjExtProveedor" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-bold">Proveedor del banco</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2">Busque la entidad proveedora que representa al banco para emitir el Resumen Bancario (C_RB).</p>
                    <input type="search" class="form-control form-control-sm mb-2" id="bdjExtProvTexto" placeholder="Nombre, razón social o CUIT del banco...">
                    <div class="list-group list-group-flush border rounded" id="bdjExtProvLista" style="max-height: 240px; overflow-y: auto;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Submodal: Crear nuevo concepto bancario -->
    <div class="modal fade" id="modalBdjExtConcepto" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header py-2">
                    <h6 class="modal-title fw-bold">Nuevo concepto bancario</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label small fw-semibold mb-1" for="bdjConNombre">Nombre del concepto</label>
                        <input type="text" class="form-control form-control-sm" id="bdjConNombre" placeholder="Ej: Comisión mantenimiento de cuenta">
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold mb-1" for="bdjConNaturaleza">Qué es</label>
                            <select class="form-select form-select-sm" id="bdjConNaturaleza">
                                <option value="GASTO_BANCARIO">Gasto / Impuesto del banco (va al C_RB)</option>
                                <option value="MOVIMIENTO_PROPIO">Movimiento propio (transferencia, cheque, venta)</option>
                            </select>
                        </div>
                        <div class="col-6" id="grupoBdjConRol">
                            <label class="form-label small fw-semibold mb-1" for="bdjConRol">Cómo entra en el C_RB</label>
                            <select class="form-select form-select-sm" id="bdjConRol">
                                <option value="ITEM">Gasto / Comisión gravada o exenta</option>
                                <option value="IVA">IVA sobre gastos bancarios</option>
                                <option value="PERCEPCION_IVA">Percepción de IVA</option>
                                <option value="PERCEPCION_IIBB">Percepción de Ingresos Brutos</option>
                                <option value="RETENCION_IIBB">Retención / SIRCREB IIBB</option>
                                <option value="IMPUESTO_LEY_25413">Impuesto Ley 25.413 (Débitos/Créditos)</option>
                            </select>
                        </div>
                    </div>
                    <div class="mb-2" id="grupoBdjConProducto">
                        <label class="form-label small fw-semibold mb-1" for="bdjConProducto">Producto para el ítem del C_RB</label>
                        <select class="form-select form-select-sm" id="bdjConProducto"></select>
                    </div>
                    <div class="mb-2 d-none" id="grupoBdjConJurisdiccion">
                        <label class="form-label small fw-semibold mb-1" for="bdjConJurisdiccion">Jurisdicción IIBB</label>
                        <select class="form-select form-select-sm" id="bdjConJurisdiccion"></select>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label small fw-semibold mb-1" for="bdjConSentido">Sentido habitual</label>
                            <select class="form-select form-select-sm" id="bdjConSentido">
                                <option value="DEBITO">Débito (resta)</option>
                                <option value="CREDITO">Crédito (suma)</option>
                                <option value="AMBOS">Ambos</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-semibold mb-1" for="bdjConCodigo">Código corto</label>
                            <input type="text" class="form-control form-control-sm" id="bdjConCodigo" placeholder="Automático si se deja vacío">
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-primary" id="btnBdjConGuardar">Guardar concepto</button>
                </div>
            </div>
        </div>
    </div>
</main>
