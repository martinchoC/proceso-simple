<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Integraciones - ARCA";
$currentPage = 'arca_integraciones';
$modudo_idx = 5;
$pagina_idx = 599; // ID de página en conf__paginas (ver sql/mantenimiento/20261001_arca_integraciones.sql)
$empresa_idx = intval($_GET['empresa_id'] ?? $_SESSION['empresa_id'] ?? 0);

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-plug me-2"></i>Integraciones - ARCA
                    </h3>
                    <small class="text-muted">Certificados, Ticket de Acceso y pruebas de WSAA / WSFE por empresa y ambiente</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Seteo Empresas</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Integraciones - ARCA</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="content-wrapper">
                <section class="content">
                    <div class="container-fluid">
                        <!-- Configuraciones -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <div id="contenedor-boton-agregar" class="d-inline"></div>
                                        <div class="d-inline-block ms-2" style="min-width: 160px;">
                                            <select class="form-select form-select-sm" id="filtroEstado" title="Filtrar por estado">
                                            </select>
                                        </div>
                                        <div class="float-end">
                                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                                id="btnRecargar" title="Recargar tabla">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <table id="tablaIntegraciones" class="table table-striped table-bordered"
                                            style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>Integración</th>
                                                    <th width="120" class="text-center">Ambiente</th>
                                                    <th width="80">Servicio</th>
                                                    <th width="110">CUIT</th>
                                                    <th>Certificado</th>
                                                    <th width="160" class="text-center">Ticket de Acceso</th>
                                                    <th width="90" class="text-center">Estado</th>
                                                    <th width="130" class="text-center">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Historial de llamadas -->
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <h5 class="card-title mb-0 d-inline">
                                            <i class="fas fa-history me-2"></i>Historial de llamadas
                                        </h5>
                                        <small class="text-muted ms-2">últimas 500 · Token, Sign y CMS se guardan enmascarados</small>
                                        <div class="float-end">
                                            <button type="button" class="btn btn-sm btn-outline-secondary"
                                                id="btnRecargarResultados" title="Recargar historial">
                                                <i class="fas fa-sync-alt"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <table id="tablaResultados" class="table table-sm table-striped table-bordered"
                                            style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="70">#</th>
                                                    <th width="140">Fecha</th>
                                                    <th width="110">Ambiente</th>
                                                    <th>Operación</th>
                                                    <th width="90" class="text-center">Resultado</th>
                                                    <th width="140">Código</th>
                                                    <th>Descripción</th>
                                                    <th width="50" class="text-center"></th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- ========== MODALES ========== -->
            <!-- Modal alta / edición -->
            <div class="modal fade" id="modalIntegracion" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true"
                data-bs-backdrop="static">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header bg-gradient-primary text-white border-0">
                            <h5 class="modal-title" id="modalLabel">
                                <i class="fas fa-plug me-2"></i>Integración
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formIntegracion" class="needs-validation" novalidate>
                                <input type="hidden" id="integracion_empresa_id" name="integracion_empresa_id" />

                                <div class="row mb-2">
                                    <div class="col-md-5 mb-2">
                                        <label for="integracion_ambiente_id" class="form-label small mb-1">Ambiente *</label>
                                        <select class="form-select form-select-sm" id="integracion_ambiente_id" required></select>
                                        <div class="invalid-feedback small">Seleccione un ambiente</div>
                                    </div>
                                    <div class="col-md-3 mb-2">
                                        <label for="servicio" class="form-label small mb-1">Servicio *</label>
                                        <input type="text" class="form-control form-control-sm" id="servicio" maxlength="30"
                                            value="wsfe" required pattern="[a-zA-Z0-9_]{2,30}">
                                        <div class="invalid-feedback small">Ej.: wsfe</div>
                                    </div>
                                    <div class="col-md-4 mb-2">
                                        <label for="cuit" class="form-label small mb-1">CUIT emisor *</label>
                                        <input type="text" class="form-control form-control-sm" id="cuit" maxlength="13"
                                            required pattern="\d{2}-?\d{8}-?\d" placeholder="20123456789">
                                        <div class="invalid-feedback small">11 dígitos</div>
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-12 mb-2">
                                        <label for="certificado_ruta" class="form-label small mb-1">Certificado (.crt) *</label>
                                        <input type="text" class="form-control form-control-sm" id="certificado_ruta"
                                            list="listaCertificados" maxlength="255" required placeholder="arca/wsaa/cert/xxxx.crt">
                                        <datalist id="listaCertificados"></datalist>
                                        <div class="form-text small">Ruta relativa a la raíz del proyecto; debe estar dentro de <code>arca/</code>.</div>
                                    </div>
                                    <div class="col-12 mb-2">
                                        <label for="clave_privada_ruta" class="form-label small mb-1">Clave privada (.key) *</label>
                                        <input type="text" class="form-control form-control-sm" id="clave_privada_ruta"
                                            list="listaClaves" maxlength="255" required placeholder="arca/wsaa/cert/xxxx.key">
                                        <datalist id="listaClaves"></datalist>
                                    </div>
                                </div>

                                <div class="row mb-2">
                                    <div class="col-12">
                                        <label for="observaciones" class="form-label small mb-1">Observaciones</label>
                                        <input type="text" class="form-control form-control-sm" id="observaciones" maxlength="255">
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer border-0">
                            <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary btn-sm" id="btnGuardar">Guardar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal de pruebas -->
            <div class="modal fade" id="modalPruebas" tabindex="-1" aria-labelledby="modalPruebasLabel" aria-hidden="true"
                data-bs-backdrop="static">
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header bg-info text-white border-0">
                            <h5 class="modal-title" id="modalPruebasLabel">
                                <i class="fas fa-wrench me-2"></i>Pruebas ARCA
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <!-- Operaciones -->
                                <div class="col-lg-6">
                                    <div class="border rounded p-2 mb-3">
                                        <h6 class="mb-2"><i class="fas fa-key me-1"></i>1. Autenticación (WSAA)</h6>
                                        <div class="small mb-2">Ticket de Acceso: <span id="pruebaEstadoTa" class="fw-medium">-</span></div>
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-prueba" data-operacion="wsaa_login">
                                            Obtener TA <small>(reutiliza el vigente)</small>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-prueba" data-operacion="wsaa_importar_ta"
                                            title="Carga un TA vigente desde arca/*/respuesta/loginTicketResponse_*.xml">
                                            Importar TA guardado
                                        </button>
                                        <div class="form-text small">Si WSAA responde <code>coe.alreadyAuthenticated</code>, el TA se pidió fuera del ERP (ej. <code>arca/wsaa/index.php</code>): importelo.</div>
                                    </div>

                                    <div class="border rounded p-2 mb-3">
                                        <h6 class="mb-2"><i class="fas fa-server me-1"></i>2. Servidores (WSFE)</h6>
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-prueba" data-operacion="fe_dummy">FEDummy</button>
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-prueba" data-operacion="ptos_venta">Puntos de venta habilitados</button>
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-prueba" data-operacion="tipos_tributos" title="Códigos para percepciones IIBB (Impuestos Adic. - Conf)">Tipos de tributo</button>
                                    </div>

                                    <div class="border rounded p-2 mb-3">
                                        <h6 class="mb-2"><i class="fas fa-search me-1"></i>3. Consultas / conciliación</h6>
                                        <div class="row g-2 mb-2">
                                            <div class="col-3">
                                                <label class="form-label small mb-0" for="prueba_pto_vta">Pto. vta.</label>
                                                <input type="number" class="form-control form-control-sm" id="prueba_pto_vta" min="1" value="1" list="listaPuntosVenta">
                                                <datalist id="listaPuntosVenta"></datalist>
                                            </div>
                                            <div class="col-6">
                                                <label class="form-label small mb-0" for="prueba_cbte_tipo">Tipo de comprobante</label>
                                                <select class="form-select form-select-sm" id="prueba_cbte_tipo"></select>
                                            </div>
                                            <div class="col-3">
                                                <label class="form-label small mb-0" for="prueba_cbte_nro">Número</label>
                                                <input type="number" class="form-control form-control-sm" id="prueba_cbte_nro" min="1">
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-prueba" data-operacion="ultimo_autorizado">Último autorizado</button>
                                        <button type="button" class="btn btn-sm btn-outline-primary btn-prueba" data-operacion="comp_consultar">Consultar comprobante</button>
                                    </div>

                                    <div class="border rounded p-2 mb-3" id="seccionCae">
                                        <h6 class="mb-2"><i class="fas fa-file-invoice me-1"></i>4. Solicitar CAE de prueba
                                            <span class="badge bg-warning text-dark ms-1">solo Homologación</span></h6>
                                        <div class="row g-2 mb-2">
                                            <div class="col-4">
                                                <label class="form-label small mb-0" for="cae_doc_tipo">Doc. receptor</label>
                                                <select class="form-select form-select-sm" id="cae_doc_tipo">
                                                    <option value="99">99 - Consumidor Final</option>
                                                    <option value="80">80 - CUIT</option>
                                                    <option value="86">86 - CUIL</option>
                                                    <option value="96">96 - DNI</option>
                                                </select>
                                            </div>
                                            <div class="col-4">
                                                <label class="form-label small mb-0" for="cae_doc_nro">Nro. documento</label>
                                                <input type="text" class="form-control form-control-sm" id="cae_doc_nro" maxlength="13" disabled>
                                            </div>
                                            <div class="col-4">
                                                <label class="form-label small mb-0" for="cae_condicion_iva">Cond. IVA receptor</label>
                                                <select class="form-select form-select-sm" id="cae_condicion_iva">
                                                    <option value="5">5 - Consumidor Final</option>
                                                    <option value="1">1 - Resp. Inscripto</option>
                                                    <option value="6">6 - Monotributo</option>
                                                    <option value="4">4 - Exento</option>
                                                </select>
                                            </div>
                                            <div class="col-4">
                                                <label class="form-label small mb-0" for="cae_imp_total">Importe total</label>
                                                <input type="number" class="form-control form-control-sm" id="cae_imp_total" min="0.01" step="0.01" value="121.00">
                                            </div>
                                            <div class="col-8 small text-muted d-flex align-items-end">
                                                Usa pto. vta. y tipo de la sección 3 (Factura A/B/C), concepto Productos y
                                                número = último autorizado + 1. A/B: neto + IVA 21%. C: sin IVA.
                                            </div>
                                        </div>
                                        <button type="button" class="btn btn-sm btn-warning btn-prueba" data-operacion="cae_solicitar">Solicitar CAE</button>
                                    </div>
                                </div>

                                <!-- Resultado -->
                                <div class="col-lg-6">
                                    <h6 class="mb-2"><i class="fas fa-terminal me-1"></i>Resultado</h6>
                                    <div id="pruebaResultado" class="border rounded p-2 bg-light small" style="min-height: 300px;">
                                        <span class="text-muted">Ejecute una operación para ver el resultado.</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal detalle de llamada -->
            <div class="modal fade" id="modalResultado" tabindex="-1" aria-labelledby="modalResultadoLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header border-0">
                            <h5 class="modal-title" id="modalResultadoLabel">Detalle de llamada</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body" id="detalleResultado"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .btn-xs {
            padding: 0.15rem 0.4rem;
            font-size: 0.75rem;
        }

        .xml-tecnico {
            max-height: 340px;
            overflow: auto;
            white-space: pre-wrap;
            word-break: break-all;
            font-size: 0.75rem;
        }
    </style>

    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        const PAGINA_ID = <?= intval($pagina_idx) ?>;
        const EMPRESA_ID = <?= intval($empresa_idx) ?>;
    </script>
    <script src="arca_integraciones.js?v=<?= filemtime(__DIR__ . '/arca_integraciones.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
