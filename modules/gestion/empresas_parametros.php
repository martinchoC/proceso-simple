<?php
/**
 * Vista: Parámetros Generales de Empresa e Historial Fiscal
 * Módulo: Gestión - Seteo Empresas (padre_id = 37, orden = 405)
 * Página ID: 111
 */

$pageTitle = "Parámetros de Empresa";
$currentPage = 'empresas_parametros';
$modulo_idx = 2;
$pagina_idx = 111;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$empresa_id_activa = intval($_SESSION['empresa_id'] ?? 0);
?>

<main class="app-main">
    <!-- Header -->
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 fw-bold">
                        <i class="fas fa-sliders-h me-2 text-primary"></i>Parámetros de Empresa
                    </h3>
                    <small class="text-muted">Configuración fiscal y variables generales con historial de vigencias</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Seteo Empresas</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Parámetros de Empresa</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenido -->
    <div class="app-content">
        <div class="container-fluid">
            
            <!-- Selector de Empresa -->
            <div class="card card-outline card-secondary mb-3 shadow-sm">
                <div class="card-body py-2">
                    <div class="row align-items-center g-2">
                        <div class="col-auto">
                            <label for="selectEmpresaFiltro" class="col-form-label fw-bold">
                                <i class="fas fa-building me-1 text-primary"></i>Empresa:
                            </label>
                        </div>
                        <div class="col-md-5 col-sm-8">
                            <select class="form-select form-select-sm" id="selectEmpresaFiltro">
                                <option value="">Cargando empresas...</option>
                            </select>
                        </div>
                        <div class="col-auto ms-auto">
                            <button type="button" class="btn btn-sm btn-primary" id="btnNuevaVersion">
                                <i class="fas fa-plus-circle me-1"></i>Nueva Versión de Parámetros
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card de Parámetros Vigentes -->
            <div class="card card-outline card-primary mb-3 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <h5 class="card-title mb-0 fs-6 fw-bold">
                        <i class="fas fa-check-circle text-success me-2"></i>Condición y Parámetros Fiscales Vigentes
                    </h5>
                    <div>
                        <span id="badgeEstadoVigente"></span>
                    </div>
                </div>
                <div class="card-body">
                    <div id="contenedorParametrosVigentes">
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-spinner fa-spin me-2"></i>Cargando parámetros vigentes...
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card de Configuración de Impresión (gestion__empresas_impresion) -->
            <div class="card card-outline card-info mb-3 shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center py-2">
                    <h5 class="card-title mb-0 fs-6 fw-bold">
                        <i class="fas fa-print text-info me-2"></i>Impresión de Comprobantes
                    </h5>
                    <button type="button" class="btn btn-sm btn-outline-info" id="btnNuevaImpresion">
                        <i class="fas fa-plus-circle me-1"></i>Nueva configuración
                    </button>
                </div>
                <div class="card-body p-2">
                    <div class="small text-muted px-1 mb-2">
                        La configuración <b>General</b> vale para todos los comprobantes de la empresa; una por tipo de comprobante la reemplaza para ese tipo.
                        Sin configuración se imprime en A4, con 1 copia.
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-striped table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Aplica a</th>
                                    <th>Formato</th>
                                    <th class="text-center">Copias</th>
                                    <th>Se muestra</th>
                                    <th>Texto al pie</th>
                                    <th width="90" class="text-center">Estado</th>
                                    <th width="90" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyImpresion">
                                <tr><td colspan="7" class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin me-2"></i>Cargando...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Card con Historial de Parámetros -->
            <div class="card card-outline card-secondary shadow-sm">
                <div class="card-header py-2">
                    <h5 class="card-title mb-0 fs-6 fw-bold">
                        <i class="fas fa-history text-muted me-2"></i>Historial de Modificaciones y Vigencias
                    </h5>
                </div>
                <div class="card-body p-2">
                    <div class="table-responsive">
                        <table id="tablaHistorialParametros" class="table table-sm table-striped table-hover align-middle mb-0" style="width:100%">
                            <thead class="table-light">
                                <tr>
                                    <th width="50" class="text-center">ID</th>
                                    <th>Condición IVA</th>
                                    <th>Régimen IIBB & Sede</th>
                                    <th>Nro. IIBB</th>
                                    <th>PV Defecto</th>
                                    <th class="text-center">Vigencia Desde</th>
                                    <th class="text-center">Vigencia Hasta</th>
                                    <th>Observaciones</th>
                                    <th width="90" class="text-center">Estado</th>
                                    <th width="90" class="text-center">Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Modal de Configuración de Impresión -->
    <div class="modal fade" id="modalImpresion" tabindex="-1" aria-labelledby="modalImpresionLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white py-2">
                    <h5 class="modal-title fs-6" id="modalImpresionLabel"><i class="fas fa-print me-2"></i>Configuración de Impresión</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form id="formImpresion">
                        <input type="hidden" id="empresa_impresion_id" value="">
                        <div class="row g-2">
                            <div class="col-12">
                                <label for="imp_subgrupo" class="form-label fw-bold mb-1">Aplica a</label>
                                <select class="form-select form-select-sm" id="imp_subgrupo"></select>
                            </div>
                            <div class="col-md-8">
                                <label for="imp_formato" class="form-label fw-bold mb-1">Formato</label>
                                <select class="form-select form-select-sm" id="imp_formato">
                                    <option value="A4">A4 (hoja completa)</option>
                                    <option value="TICKET80">Ticket 80 mm (impresora térmica)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="imp_copias" class="form-label fw-bold mb-1">Copias</label>
                                <select class="form-select form-select-sm" id="imp_copias">
                                    <option value="1">1 - Original</option>
                                    <option value="2">2 - + Duplicado</option>
                                    <option value="3">3 - + Triplicado</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label for="imp_fuente" class="form-label fw-bold mb-1">Tipografía</label>
                                <select class="form-select form-select-sm" id="imp_fuente">
                                    <?php require_once ROOT_PATH . '/core/impresion.php'; foreach (IMPRESION_FUENTES as $clave => $f): ?>
                                    <option value="<?= htmlspecialchars($clave) ?>"><?= htmlspecialchars($f[0]) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text mt-0">Roboto, Open Sans, Lato, Source Sans, Inter y Nunito Sans son finas; la angosta entra más texto por renglón.</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold mb-1">Se muestra</label>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="imp_logo"><label class="form-check-label" for="imp_logo">Logo</label></div>
                                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="imp_codigo"><label class="form-check-label" for="imp_codigo">Código de producto</label></div>
                                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="imp_bonif"><label class="form-check-label" for="imp_bonif">% Bonificación</label></div>
                                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="imp_sucursal"><label class="form-check-label" for="imp_sucursal">Sucursal / entrega del cliente</label></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="imp_pie" class="form-label fw-bold mb-1">Texto al pie</label>
                                <textarea class="form-control form-control-sm" id="imp_pie" rows="3" maxlength="1000" placeholder="Ej: CBU 0000000000000000000000 - Alias CASA.LUCHO - Garantía de 6 meses"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-bold mb-1">Color de los títulos de la tabla (A4)</label>
                                <div class="d-flex align-items-center gap-2">
                                    <div class="form-check mb-0"><input class="form-check-input" type="checkbox" id="imp_color_usar"><label class="form-check-label" for="imp_color_usar">Usar color</label></div>
                                    <input type="color" class="form-control form-control-color form-control-sm" id="imp_color" value="#d9d9d9" title="Color">
                                    <small class="text-muted">Sin marcar: gris.</small>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-sm btn-info text-white" id="btnGuardarImpresion"><i class="fas fa-save me-1"></i>Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Nueva Versión o Edición de Parámetros -->
    <div class="modal fade" id="modalParametros" tabindex="-1" aria-labelledby="modalParametrosLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalParametrosLabel">
                        <i class="fas fa-sliders-h me-2"></i>Parámetros de Empresa
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form id="formParametros" class="needs-validation" novalidate>
                        <input type="hidden" id="empresa_parametro_id" name="empresa_parametro_id" />
                        <input type="hidden" id="empresa_id_parametro" name="empresa_id" />

                        <!-- Alerta informativa -->
                        <div class="alert alert-info py-2 mb-3">
                            <i class="fas fa-info-circle me-1"></i>
                            <small>Al guardar una nueva vigencia, la condición fiscal del sistema y el régimen de IIBB para la empresa (<code>conf__empresas</code>) se actualizarán automáticamente.</small>
                        </div>

                        <!-- Sección 1: Nivel Nacional (IVA & ARCA/AFIP) -->
                        <div class="card card-outline card-primary mb-3">
                            <div class="card-header py-1 bg-light">
                                <span class="fw-bold text-primary small">
                                    <i class="fas fa-landmark me-1"></i>Nivel Nacional (Impuesto al Valor Agregado - IVA / AFIP-ARCA)
                                </span>
                            </div>
                            <div class="card-body py-2">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label for="select_condicion_fiscal_id" class="form-label fw-bold mb-1">
                                            Condición Fiscal Emisora *
                                        </label>
                                        <select class="form-select form-select-sm" id="select_condicion_fiscal_id" name="condicion_fiscal_id" required>
                                            <option value="">Seleccione condición fiscal...</option>
                                        </select>
                                        <div class="invalid-feedback">Debe seleccionar una condición fiscal</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="inicio_actividades" class="form-label mb-1">Fecha Inicio de Actividades</label>
                                        <input type="date" class="form-control form-control-sm" id="inicio_actividades" name="inicio_actividades" />
                                    </div>
                                    <div class="col-12">
                                        <label for="leyenda_fiscal" class="form-label mb-1">Leyenda Fiscal en Comprobantes</label>
                                        <input type="text" class="form-control form-control-sm" id="leyenda_fiscal" name="leyenda_fiscal" placeholder="Ej: IVA Responsable Inscripto" maxlength="255" />
                                    </div>
                                    <div class="col-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" id="arca_habilitado" name="arca_habilitado" value="1">
                                            <label class="form-check-label fw-bold" for="arca_habilitado">Facturación electrónica con ARCA (CAE)</label>
                                        </div>
                                        <div class="form-text mt-0">
                                            Activado: las facturas A/B se confirman recién cuando ARCA otorga el CAE, con el número que asigna ARCA
                                            (usa la integración WSFE activa de Integraciones ARCA). Desactivado: se numeran al confirmar y la aprobación es manual.
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="compra_actualiza_lista_proveedor" class="form-label mb-1">Al confirmar una factura de compra, lista del proveedor</label>
                                        <select class="form-select form-select-sm" id="compra_actualiza_lista_proveedor" name="compra_actualiza_lista_proveedor">
                                            <option value="0">No modificar</option>
                                            <option value="1">Cargar solo los productos sin precio</option>
                                            <option value="2">Actualizar siempre con el precio de la factura</option>
                                        </select>
                                        <div class="form-text mt-0">Toma el precio unitario de la línea antes de descuentos. El costo del producto se actualiza siempre.</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="compra_actualiza_listas_venta" class="form-label mb-1">Al confirmar una factura de compra, listas de venta</label>
                                        <select class="form-select form-select-sm" id="compra_actualiza_listas_venta" name="compra_actualiza_listas_venta">
                                            <option value="0">No modificar</option>
                                            <option value="1">Recalcular con el costo nuevo (reglas de cada lista)</option>
                                        </select>
                                        <div class="form-text mt-0">Igual que "Actualizar Precios de Venta" de Lista Precios Proveedores: las listas manuales o sin regla no se tocan.</div>
                                    </div>
                                    <div class="col-12">
                                        <label for="logo" class="form-label mb-1">Logo para comprobantes impresos</label>
                                        <div class="d-flex align-items-center gap-3">
                                            <div id="logoPreviewBox" class="border rounded d-flex align-items-center justify-content-center bg-white" style="width:180px;height:70px;">
                                                <img id="logoPreview" src="" alt="Logo" style="max-width:170px;max-height:62px;object-fit:contain;" class="d-none">
                                                <small id="logoSinImagen" class="text-muted">Sin logo</small>
                                            </div>
                                            <div class="flex-grow-1">
                                                <input type="file" class="form-control form-control-sm" id="logo" name="logo" accept="image/png,image/jpeg,image/gif,image/webp">
                                                <input type="hidden" id="quitar_logo" name="quitar_logo" value="0">
                                                <div class="d-flex justify-content-between align-items-center mt-1">
                                                    <div class="form-text mt-0">PNG, JPG, GIF o WEBP, hasta 1 MB. Se imprime arriba a la izquierda en las facturas. Una vigencia nueva conserva el logo anterior si no se sube otro.</div>
                                                    <button type="button" class="btn btn-xs btn-outline-danger ms-2 text-nowrap" id="btnQuitarLogo"><i class="fas fa-trash-alt me-1"></i>Quitar</button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sección 2: Nivel Provincial (IIBB) -->
                        <div class="card card-outline card-success mb-3">
                            <div class="card-header py-1 bg-light">
                                <span class="fw-bold text-success small">
                                    <i class="fas fa-map-marked-alt me-1"></i>Nivel Provincial (Impuesto sobre los Ingresos Brutos - IIBB)
                                </span>
                            </div>
                            <div class="card-body py-2">
                                <div class="row g-2">
                                    <!-- Régimen de IIBB -->
                                    <div class="col-md-6">
                                        <label for="select_iibb_regimen_id" class="form-label fw-bold mb-1">
                                            Régimen de IIBB *
                                        </label>
                                        <select class="form-select form-select-sm" id="select_iibb_regimen_id" name="iibb_regimen_id" required>
                                            <option value="">Seleccione régimen de IIBB...</option>
                                        </select>
                                        <div class="invalid-feedback">Debe seleccionar el régimen de IIBB</div>
                                        <div id="help_iibb_regimen" class="text-muted small mt-1 p-1 bg-light rounded border"></div>
                                    </div>

                                    <!-- Sede Principal / Jurisdicción Sede -->
                                    <div class="col-md-6">
                                        <label for="select_jurisdiccion_sede_id" class="form-label fw-bold mb-1">
                                            Sede Principal / Jurisdicción Sede *
                                        </label>
                                        <select class="form-select form-select-sm" id="select_jurisdiccion_sede_id" name="jurisdiccion_sede_id" required>
                                            <option value="">Seleccione jurisdicción sede...</option>
                                        </select>
                                        <small class="text-muted">Código ARCA / COMARB (ej. 901 CABA, 902 Bs. As.)</small>
                                        <div class="invalid-feedback">Debe seleccionar la jurisdicción sede</div>
                                    </div>

                                    <!-- Número de Inscripción en IIBB -->
                                    <div class="col-md-12">
                                        <label for="ingresos_brutos_nro" class="form-label fw-bold mb-1">
                                            Número de Inscripción en IIBB
                                        </label>
                                        <div class="input-group input-group-sm">
                                            <input type="text" class="form-control form-control-sm" id="ingresos_brutos_nro" name="ingresos_brutos_nro" placeholder="Número local o número de CUIT / Convenio" maxlength="50" />
                                            <button class="btn btn-outline-secondary" type="button" id="btnCopiarCuitIIBB" title="Copiar CUIT de la empresa">
                                                <i class="fas fa-copy me-1"></i>Copiar CUIT
                                            </button>
                                        </div>
                                        <small class="text-muted">Número local provincial o número de CUIT / Convenio Multilateral</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sección 3: Valores por Defecto -->
                        <div class="card card-outline card-secondary mb-3">
                            <div class="card-header py-1 bg-light">
                                <span class="fw-bold text-secondary small">
                                    <i class="fas fa-cogs me-1"></i>Valores por Defecto para Emisión de Comprobantes
                                </span>
                            </div>
                            <div class="card-body py-2">
                                <div class="row g-2">
                                    <div class="col-md-4">
                                        <label for="punto_venta_defecto_id" class="form-label mb-1">Punto de Venta Defecto</label>
                                        <select class="form-select form-select-sm" id="punto_venta_defecto_id" name="punto_venta_defecto_id">
                                            <option value="">Sin definir</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="lista_precio_defecto_id" class="form-label mb-1">Lista de Precios Defecto</label>
                                        <select class="form-select form-select-sm" id="lista_precio_defecto_id" name="lista_precio_defecto_id">
                                            <option value="">Sin definir</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="condicion_pago_defecto_id" class="form-label mb-1">Condición Pago Defecto</label>
                                        <select class="form-select form-select-sm" id="condicion_pago_defecto_id" name="condicion_pago_defecto_id">
                                            <option value="">Sin definir</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Sección 4: Vigencia y Control -->
                        <div class="card card-outline card-dark mb-0">
                            <div class="card-header py-1 bg-light">
                                <span class="fw-bold text-dark small">
                                    <i class="fas fa-calendar-alt me-1"></i>Vigencia y Control
                                </span>
                            </div>
                            <div class="card-body py-2">
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <label for="f_desde" class="form-label fw-bold mb-1">Fecha Desde (Inicio Vigencia) *</label>
                                        <input type="date" class="form-control form-control-sm" id="f_desde" name="f_desde" required />
                                        <div class="invalid-feedback">La fecha desde es requerida</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="f_hasta" class="form-label fw-bold mb-1">Fecha Hasta (Fin Vigencia)</label>
                                        <input type="date" class="form-control form-control-sm" id="f_hasta" name="f_hasta" />
                                        <small class="text-muted">Dejar vacío para vigencia activa indefinida</small>
                                    </div>
                                    <div class="col-12" id="divCerrarAnterior">
                                        <div class="form-check form-switch mt-1">
                                            <input class="form-check-input" type="checkbox" id="cerrar_anterior" name="cerrar_anterior" value="1" checked />
                                            <label class="form-check-label small" for="cerrar_anterior">
                                                Cerrar automáticamente la vigencia anterior un día antes de la nueva fecha de inicio
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-12">
                                        <label for="observaciones" class="form-label mb-1">Observaciones / Motivo del cambio</label>
                                        <textarea class="form-control form-control-sm" id="observaciones" name="observaciones" rows="2" placeholder="Motivo o resolución del cambio de parámetros..." maxlength="255"></textarea>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </form>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary btn-sm" id="btnGuardarParametros">
                        <i class="fas fa-save me-1"></i>Guardar Parámetros
                    </button>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- JS Scripts -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/locale/es.min.js"></script>

<script>
$(document).ready(function() {
    var empresaActualId = <?php echo $empresa_id_activa; ?>;
    var tablaHistorial = null;
    var catalogos = null;

    // Cargar Catálogos
    function cargarCatalogos(callback) {
        $.get('empresas_parametros_ajax.php', {
            accion: 'obtener_catalogos',
            empresa_id: empresaActualId
        }, function(res) {
            catalogos = res || {};
            
            // Llenar combo de empresas
            var selEmp = $('#selectEmpresaFiltro');
            selEmp.empty();
            if (catalogos.empresas && catalogos.empresas.length > 0) {
                catalogos.empresas.forEach(function(e) {
                    var cuit = e.documento_numero ? ' (' + e.documento_numero + ')' : '';
                    var sel = (e.empresa_id == empresaActualId) ? 'selected' : '';
                    selEmp.append(`<option value="${e.empresa_id}" ${sel}>${e.empresa}${cuit}</option>`);
                });
            }

            // Llenar combo de condiciones fiscales
            var selCf = $('#select_condicion_fiscal_id');
            selCf.empty();
            selCf.append('<option value="">Seleccione condición fiscal...</option>');
            if (catalogos.condiciones_fiscales && catalogos.condiciones_fiscales.length > 0) {
                catalogos.condiciones_fiscales.forEach(function(cf) {
                    var cod = cf.condicion_fiscal_codigo ? ' (' + cf.condicion_fiscal_codigo + ')' : '';
                    selCf.append(`<option value="${cf.condicion_fiscal_id}">${cf.condicion_fiscal}${cod}</option>`);
                });
            }

            // Llenar combo de regímenes de IIBB
            var selReg = $('#select_iibb_regimen_id');
            selReg.empty();
            selReg.append('<option value="">Seleccione régimen de IIBB...</option>');
            if (catalogos.iibb_regimenes && catalogos.iibb_regimenes.length > 0) {
                catalogos.iibb_regimenes.forEach(function(r) {
                    selReg.append(`<option value="${r.iibb_regimen_id}" data-desc="${r.descripcion || ''}">${r.iibb_regimen_nombre}</option>`);
                });
            }
            actualizarDescripcionRegimen();

            // Llenar combo de jurisdicciones sedes (24 COMARB)
            var selSede = $('#select_jurisdiccion_sede_id');
            selSede.empty();
            selSede.append('<option value="">Seleccione jurisdicción sede...</option>');
            if (catalogos.jurisdicciones_sedes && catalogos.jurisdicciones_sedes.length > 0) {
                catalogos.jurisdicciones_sedes.forEach(function(s) {
                    var org = s.organismo ? ` (${s.organismo})` : '';
                    selSede.append(`<option value="${s.jurisdiccion_sede_id}">${s.codigo_comarb} - ${s.jurisdiccion_sede}${org}</option>`);
                });
            }

            // Llenar puntos de venta
            var selPv = $('#punto_venta_defecto_id');
            selPv.empty();
            selPv.append('<option value="">Sin definir</option>');
            if (catalogos.puntos_venta && catalogos.puntos_venta.length > 0) {
                catalogos.puntos_venta.forEach(function(pv) {
                    var desc = pv.punto_venta_descripcion ? ' - ' + pv.punto_venta_descripcion : '';
                    selPv.append(`<option value="${pv.punto_venta_id}">PV ${pv.punto_venta_numero}${desc}</option>`);
                });
            }

            // Llenar listas de precio
            var selLp = $('#lista_precio_defecto_id');
            selLp.empty();
            selLp.append('<option value="">Sin definir</option>');
            if (catalogos.listas_precios && catalogos.listas_precios.length > 0) {
                catalogos.listas_precios.forEach(function(lp) {
                    selLp.append(`<option value="${lp.lista_precio_id}">${lp.lista_precio}</option>`);
                });
            }

            // Llenar condiciones de pago
            var selCp = $('#condicion_pago_defecto_id');
            selCp.empty();
            selCp.append('<option value="">Sin definir</option>');
            if (catalogos.condiciones_pago && catalogos.condiciones_pago.length > 0) {
                catalogos.condiciones_pago.forEach(function(cp) {
                    selCp.append(`<option value="${cp.condicion_pago_id}">${cp.condicion_pago}</option>`);
                });
            }

            if (typeof callback === 'function') callback();
        }, 'json');
    }

    function actualizarDescripcionRegimen() {
        var desc = $('#select_iibb_regimen_id option:selected').data('desc') || '';
        if (desc) {
            $('#help_iibb_regimen').html('<i class="fas fa-info-circle me-1 text-success"></i>' + desc).show();
        } else {
            $('#help_iibb_regimen').html('<i class="fas fa-info-circle me-1 text-muted"></i>Seleccione un régimen para visualizar su alcance normativo.').show();
        }
    }

    $('#select_iibb_regimen_id').on('change', actualizarDescripcionRegimen);

    $('#btnCopiarCuitIIBB').on('click', function() {
        var emp = (catalogos && catalogos.empresas) ? catalogos.empresas.find(function(e) { return e.empresa_id == empresaActualId; }) : null;
        if (emp && emp.documento_numero) {
            $('#ingresos_brutos_nro').val(emp.documento_numero);
        }
    });

    function formatoFecha(d) {
        if (!d) return '';
        var p = d.split('-');
        return (p.length === 3) ? (p[2] + '/' + p[1] + '/' + p[0]) : d;
    }

    // Cargar Parámetros Vigentes
    function cargarParametrosVigentes() {
        $('#contenedorParametrosVigentes').html('<div class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando parámetros vigentes...</div>');

        $.get('empresas_parametros_ajax.php', {
            accion: 'obtener_vigentes',
            empresa_id: empresaActualId
        }, function(res) {
            logoVigente = (res && res.logo_data) || '';
            if (res && res.empresa_parametro_id) {
                var fDesde = res.f_desde ? formatoFecha(res.f_desde) : '-';
                var fHasta = res.f_hasta ? formatoFecha(res.f_hasta) : '<span class="badge bg-success">Abierta / Vigente</span>';
                var fInicioAct = res.inicio_actividades ? formatoFecha(res.inicio_actividades) : '<span class="text-muted">No especificada</span>';
                var pv = res.punto_venta_numero ? 'PV ' + res.punto_venta_numero + (res.punto_venta_descripcion ? ' (' + res.punto_venta_descripcion + ')' : '') : '<span class="text-muted">Sin definir</span>';
                var lp = res.lista_precio ? res.lista_precio : '<span class="text-muted">Sin definir</span>';
                var cp = res.condicion_pago ? res.condicion_pago : '<span class="text-muted">Sin definir</span>';

                var regBadge = '<span class="badge bg-secondary">No definido</span>';
                if (res.iibb_regimen_id == 1) {
                    regBadge = '<span class="badge bg-primary fs-7"><i class="fas fa-map-pin me-1"></i>Local / Simplificado</span>';
                } else if (res.iibb_regimen_id == 2) {
                    regBadge = '<span class="badge fs-7" style="background-color:#6f42c1;color:white;"><i class="fas fa-network-wired me-1"></i>Convenio Multilateral</span>';
                } else if (res.iibb_regimen_id == 3) {
                    regBadge = '<span class="badge bg-warning text-dark fs-7"><i class="fas fa-shield-alt me-1"></i>Exento / No alcanzado</span>';
                }

                var sedeLabel = '<span class="text-muted">-</span>';
                if (res.codigo_comarb) {
                    var org = res.jurisdiccion_sede_organismo ? ` <small class="text-muted">(${res.jurisdiccion_sede_organismo})</small>` : '';
                    sedeLabel = `<span class="badge bg-dark">${res.codigo_comarb}</span> <strong>${res.jurisdiccion_sede || ''}</strong>${org}`;
                }

                $('#badgeEstadoVigente').html(`<span class="badge ${res.bg_clase || 'bg-success'} fs-7">${res.estado_registro || 'Activo'}</span>`);

                var html = `
                    <div class="row g-3">
                        <!-- Nivel Nacional (IVA) -->
                        <div class="col-md-6 col-lg-3 border-end">
                            <h6 class="text-uppercase text-primary fw-bold mb-2 small">
                                <i class="fas fa-landmark me-1"></i>Nivel Nacional (IVA)
                            </h6>
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <th width="45%" class="text-muted">Condición:</th>
                                    <td><span class="fs-6 fw-bold text-primary">${res.condicion_fiscal}</span> <span class="badge bg-secondary">${res.condicion_fiscal_codigo || ''}</span></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">CUIT Emisora:</th>
                                    <td><strong>${res.empresa_cuit || '-'}</strong></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Inicio Act.:</th>
                                    <td>${fInicioAct}</td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Leyenda:</th>
                                    <td><em>${res.leyenda_fiscal || '-'}</em></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">ARCA (CAE):</th>
                                    <td>${parseInt(res.arca_habilitado, 10) === 1
                                        ? '<span class="badge bg-success"><i class="fas fa-plug me-1"></i>Habilitado</span>'
                                        : '<span class="badge bg-secondary">No habilitado</span>'}</td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Compras:</th>
                                    <td><small>Lista prov.: ${['No modifica', 'Solo sin precio', 'Siempre'][parseInt(res.compra_actualiza_lista_proveedor, 10) || 0] || 'No modifica'}
                                        · Listas venta: ${parseInt(res.compra_actualiza_listas_venta, 10) === 1 ? 'Recalcula' : 'No modifica'}</small></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Logo:</th>
                                    <td>${res.logo_data
                                        ? '<img src="' + res.logo_data + '" alt="Logo" style="max-width:140px;max-height:48px;object-fit:contain;">'
                                        : '<span class="text-muted">Sin logo</span>'}</td>
                                </tr>
                            </table>
                        </div>

                        <!-- Nivel Provincial (IIBB) -->
                        <div class="col-md-6 col-lg-4 border-end">
                            <h6 class="text-uppercase text-success fw-bold mb-2 small">
                                <i class="fas fa-map-marked-alt me-1"></i>Nivel Provincial (IIBB)
                            </h6>
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <th width="38%" class="text-muted">Régimen IIBB:</th>
                                    <td>${regBadge}</td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Alcance:</th>
                                    <td><small class="text-muted">${res.iibb_regimen_descripcion || '-'}</small></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Nro. IIBB:</th>
                                    <td><span class="fs-6 fw-bold text-dark font-monospace">${res.ingresos_brutos_nro || '-'}</span></td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Sede Principal:</th>
                                    <td>${sedeLabel}</td>
                                </tr>
                            </table>
                        </div>

                        <!-- Valores por Defecto -->
                        <div class="col-md-6 col-lg-2 border-end">
                            <h6 class="text-uppercase text-muted fw-bold mb-2 small">
                                <i class="fas fa-cogs me-1"></i>Valores Defecto
                            </h6>
                            <table class="table table-sm table-borderless mb-0">
                                <tr>
                                    <th width="45%" class="text-muted">P. Venta:</th>
                                    <td>${pv}</td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Lista Precios:</th>
                                    <td>${lp}</td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Cond. Pago:</th>
                                    <td>${cp}</td>
                                </tr>
                            </table>
                        </div>

                        <!-- Vigencia y Control -->
                        <div class="col-md-6 col-lg-3">
                            <h6 class="text-uppercase text-muted fw-bold mb-2 small">
                                <i class="fas fa-calendar-alt me-1"></i>Vigencia y Control
                            </h6>
                            <table class="table table-sm table-borderless mb-2">
                                <tr>
                                    <th width="45%" class="text-muted">Desde:</th>
                                    <td><i class="far fa-calendar-check text-success me-1"></i>${fDesde}</td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Hasta:</th>
                                    <td>${fHasta}</td>
                                </tr>
                                <tr>
                                    <th class="text-muted">Obs:</th>
                                    <td><small class="text-muted">${res.observaciones || 'Sin observaciones'}</small></td>
                                </tr>
                            </table>
                            <div class="mt-2 text-end">
                                <button type="button" class="btn btn-xs btn-outline-primary btn-editar-param" data-id="${res.empresa_parametro_id}">
                                    <i class="fas fa-edit me-1"></i>Editar Registro Vigente
                                </button>
                            </div>
                        </div>
                    </div>
                `;
                $('#contenedorParametrosVigentes').html(html);
            } else {
                $('#badgeEstadoVigente').empty();
                $('#contenedorParametrosVigentes').html(`
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-exclamation-triangle text-warning fs-3 d-block mb-2"></i>
                        Esta empresa no tiene parámetros vigentes configurados.
                        <div class="mt-3">
                            <button type="button" class="btn btn-sm btn-primary" id="btnCrearPrimerosParametros">
                                <i class="fas fa-plus me-1"></i>Configurar Parámetros Iniciales
                            </button>
                        </div>
                    </div>
                `);
            }
        }, 'json').fail(function() {
            $('#contenedorParametrosVigentes').html('<div class="alert alert-danger mb-0 py-2">Error al consultar parámetros vigentes.</div>');
        });
    }

    // Inicializar DataTable Historial
    function inicializarDataTableHistorial() {
        if ($.fn.DataTable.isDataTable('#tablaHistorialParametros')) {
            if (tablaHistorial) {
                tablaHistorial.destroy();
                tablaHistorial = null;
            }
            $('#tablaHistorialParametros tbody').empty();
        }

        tablaHistorial = $('#tablaHistorialParametros').DataTable({
            ajax: {
                url: 'empresas_parametros_ajax.php',
                type: 'GET',
                data: function(d) {
                    d.accion = 'listar_historial';
                    d.empresa_id = empresaActualId;
                },
                dataSrc: function(json) {
                    return json || [];
                }
            },
            dom: '<"row"<"col-sm-12"tr>>' +
                 '<"row align-items-center mt-2"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>',
            pageLength: 10,
            order: [[5, 'desc']],
            columns: [
                { data: 'empresa_parametro_id', className: 'text-center fw-bold' },
                {
                    data: 'condicion_fiscal',
                    render: function(data, type, row) {
                        var vigente = (!row.f_hasta && row.tabla_estado_registro_id == 1)
                            ? ' <span class="badge bg-success ms-1"><i class="fas fa-check me-1"></i>Vigente</span>'
                            : '';
                        var cod = row.condicion_fiscal_codigo ? ` <span class="badge bg-secondary">${row.condicion_fiscal_codigo}</span>` : '';
                        return `<strong>${data || '-'}</strong>${cod}${vigente}`;
                    }
                },
                {
                    data: 'iibb_regimen_nombre',
                    render: function(data, type, row) {
                        var badgeClass = 'bg-primary';
                        if (row.iibb_regimen_id == 2) badgeClass = 'text-white" style="background-color:#6f42c1 !important;';
                        else if (row.iibb_regimen_id == 3) badgeClass = 'bg-warning text-dark';
                        var regBadge = `<span class="badge ${badgeClass} mb-1">${data || 'Local / Simplificado'}</span>`;
                        var sede = (row.codigo_comarb) ? `<br><small class="text-muted"><span class="badge bg-light text-dark border">${row.codigo_comarb}</span> ${row.jurisdiccion_sede || ''}</small>` : '';
                        return `${regBadge}${sede}`;
                    }
                },
                {
                    data: 'ingresos_brutos_nro',
                    render: function(data) {
                        return data ? `<span class="font-monospace fw-bold">${data}</span>` : '<span class="text-muted">-</span>';
                    }
                },
                {
                    data: 'punto_venta_numero',
                    render: function(data, type, row) {
                        if (!data) return '<span class="text-muted">-</span>';
                        return `PV ${data}` + (row.punto_venta_descripcion ? ` <small class="text-muted">(${row.punto_venta_descripcion})</small>` : '');
                    }
                },
                {
                    data: 'f_desde',
                    className: 'text-center',
                    render: function(data) {
                        if (!data) return '-';
                        var p = data.split('-');
                        return (p.length === 3) ? (p[2] + '/' + p[1] + '/' + p[0]) : data;
                    }
                },
                {
                    data: 'f_hasta',
                    className: 'text-center',
                    render: function(data) {
                        if (!data) return '<span class="badge bg-success">Abierta</span>';
                        var p = data.split('-');
                        return (p.length === 3) ? (p[2] + '/' + p[1] + '/' + p[0]) : data;
                    }
                },
                {
                    data: 'observaciones',
                    render: function(data) {
                        return data ? `<span title="${data}">${data}</span>` : '<span class="text-muted">-</span>';
                    }
                },
                {
                    data: 'estado_info',
                    className: 'text-center',
                    render: function(data) {
                        if (!data || !data.estado_registro) return '<span class="badge bg-secondary">Sin estado</span>';
                        var badgeClass = data.bg_clase ? 'badge ' + data.bg_clase : 'badge bg-secondary';
                        return `<span class="${badgeClass}">${data.estado_registro}</span>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    render: function(data, type, row) {
                        var botones = '';
                        botones += `<button type="button" class="btn btn-xs btn-outline-primary btn-editar-param me-1" 
                                        data-id="${row.empresa_parametro_id}" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>`;
                        if (row.tabla_estado_registro_id == 1) {
                            botones += `<button type="button" class="btn btn-xs btn-outline-danger btn-estado-param" 
                                            data-id="${row.empresa_parametro_id}" data-estado="2" title="Inhabilitar">
                                        <i class="fas fa-ban"></i>
                                    </button>`;
                        } else {
                            botones += `<button type="button" class="btn btn-xs btn-outline-success btn-estado-param" 
                                            data-id="${row.empresa_parametro_id}" data-estado="1" title="Habilitar">
                                        <i class="fas fa-check"></i>
                                    </button>`;
                        }
                        return `<div class="btn-group" role="group">${botones}</div>`;
                    }
                }
            ],
            language: {
                "lengthMenu": "Mostrar _MENU_ registros por página",
                "zeroRecords": "No se encontraron registros",
                "info": "Mostrando página _PAGE_ de _PAGES_",
                "infoEmpty": "No hay registros disponibles",
                "infoFiltered": "(filtrado de _MAX_ registros totales)",
                "search": "Buscar:",
                "paginate": {
                    "first": "Primero",
                    "last": "Último",
                    "next": "Siguiente",
                    "previous": "Anterior"
                }
            },
            responsive: true
        });
    }

    // Resetear Modal
    function resetModalParametros() {
        $('#formParametros')[0].reset();
        $('#empresa_parametro_id').val('');
        $('#empresa_id_parametro').val(empresaActualId);
        $('#select_iibb_regimen_id').val(1);
        $('#select_jurisdiccion_sede_id').val(2); // 902 Buenos Aires
        actualizarDescripcionRegimen();
        $('#formParametros').removeClass('was-validated');
        $('#divCerrarAnterior').show();
        $('#cerrar_anterior').prop('checked', true);
        var today = new Date().toISOString().split('T')[0];
        $('#f_desde').val(today);
        $('#f_hasta').val('');
        $('#modalParametrosLabel').html('<i class="fas fa-sliders-h me-2"></i>Nueva Versión de Parámetros');
        logoActual = logoVigente; // una vigencia nueva hereda el logo actual salvo que se quite o reemplace
        mostrarLogo(logoActual);
    }

    // Logo: vista previa del registro (logo_data viene como data URI) o del archivo elegido
    var logoActual = '', logoVigente = '';
    function mostrarLogo(src) {
        $('#logo').val('');
        $('#quitar_logo').val('0');
        pintarLogo(src);
    }
    function pintarLogo(src) {
        if (src) {
            $('#logoPreview').attr('src', src).removeClass('d-none');
            $('#logoSinImagen').addClass('d-none');
        } else {
            $('#logoPreview').attr('src', '').addClass('d-none');
            $('#logoSinImagen').removeClass('d-none');
        }
        $('#btnQuitarLogo').toggle(!!src);
    }
    $('#logo').on('change', function() {
        var archivo = this.files && this.files[0];
        if (!archivo) { pintarLogo($('#quitar_logo').val() === '1' ? '' : logoActual); return; }
        if (archivo.size > 1048576) {
            Swal.fire('Logo demasiado grande', 'El logo no puede superar 1 MB.', 'warning');
            $(this).val('');
            return;
        }
        var lector = new FileReader();
        lector.onload = function(e) { pintarLogo(e.target.result); };
        lector.readAsDataURL(archivo);
        $('#quitar_logo').val('0');
    });
    $('#btnQuitarLogo').on('click', function() {
        $('#logo').val('');
        $('#quitar_logo').val('1');
        pintarLogo('');
    });

    // Cargar para editar
    function cargarParametroParaEditar(id) {
        $.get('empresas_parametros_ajax.php', {
            accion: 'obtener_parametro',
            empresa_parametro_id: id
        }, function(res) {
            if (res && res.empresa_parametro_id) {
                resetModalParametros();
                $('#empresa_parametro_id').val(res.empresa_parametro_id);
                $('#empresa_id_parametro').val(res.empresa_id);
                $('#select_condicion_fiscal_id').val(res.condicion_fiscal_id);
                $('#select_iibb_regimen_id').val(res.iibb_regimen_id || 1);
                $('#select_jurisdiccion_sede_id').val(res.jurisdiccion_sede_id || 2);
                actualizarDescripcionRegimen();
                $('#ingresos_brutos_nro').val(res.ingresos_brutos_nro || '');
                $('#inicio_actividades').val(res.inicio_actividades || '');
                $('#leyenda_fiscal').val(res.leyenda_fiscal || '');
                $('#arca_habilitado').prop('checked', parseInt(res.arca_habilitado, 10) === 1);
                $('#compra_actualiza_lista_proveedor').val(String(parseInt(res.compra_actualiza_lista_proveedor, 10) || 0));
                $('#compra_actualiza_listas_venta').val(String(parseInt(res.compra_actualiza_listas_venta, 10) || 0));
                $('#punto_venta_defecto_id').val(res.punto_venta_defecto_id || '');
                $('#lista_precio_defecto_id').val(res.lista_precio_defecto_id || '');
                $('#condicion_pago_defecto_id').val(res.condicion_pago_defecto_id || '');
                $('#f_desde').val(res.f_desde);
                $('#f_hasta').val(res.f_hasta || '');
                $('#observaciones').val(res.observaciones || '');
                $('#divCerrarAnterior').hide();
                logoActual = res.logo_data || '';
                mostrarLogo(logoActual);
                $('#modalParametrosLabel').html('<i class="fas fa-edit me-2"></i>Editar Parámetros de Empresa');
                
                var modal = new bootstrap.Modal(document.getElementById('modalParametros'));
                modal.show();
            } else {
                Swal.fire('Error', res.error || 'No se pudo cargar el registro', 'error');
            }
        }, 'json');
    }

    // Eventos
    $('#selectEmpresaFiltro').on('change', function() {
        empresaActualId = parseInt($(this).val()) || 2;
        cargarImpresion();
        cargarCatalogos(function() {
            cargarParametrosVigentes();
            if (tablaHistorial) {
                tablaHistorial.ajax.reload();
            }
        });
    });

    $(document).on('click', '#btnNuevaVersion, #btnCrearPrimerosParametros', function() {
        resetModalParametros();
        var modal = new bootstrap.Modal(document.getElementById('modalParametros'));
        modal.show();
    });

    $(document).on('click', '.btn-editar-param', function() {
        var id = $(this).data('id');
        cargarParametroParaEditar(id);
    });

    $(document).on('click', '.btn-estado-param', function() {
        var id = $(this).data('id');
        var nuevoEstado = $(this).data('estado');
        var accionTxt = nuevoEstado == 1 ? 'habilitar' : 'inhabilitar';

        Swal.fire({
            title: `¿Desea ${accionTxt} este registro?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Cancelar'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.post('empresas_parametros_ajax.php', {
                    accion: 'cambiar_estado',
                    empresa_parametro_id: id,
                    nuevo_estado: nuevoEstado
                }, function(res) {
                    if (res && res.resultado) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Estado actualizado',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 1500
                        });
                        cargarParametrosVigentes();
                        if (tablaHistorial) tablaHistorial.ajax.reload();
                    } else {
                        Swal.fire('Error', (res && res.error) || 'No se pudo cambiar el estado', 'error');
                    }
                }, 'json');
            }
        });
    });

    $('#btnGuardarParametros').on('click', function() {
        var form = document.getElementById('formParametros');
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }

        var fDesde = $('#f_desde').val();
        var fHasta = $('#f_hasta').val();
        if (fHasta && fHasta < fDesde) {
            Swal.fire('Fechas inválidas', 'La fecha hasta debe ser posterior o igual a la fecha desde', 'warning');
            return;
        }

        var btn = $(this);
        var origHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        // multipart: viaja el archivo del logo junto con los parámetros
        var datos = new FormData();
        var campos = {
            accion: 'guardar',
            empresa_parametro_id: $('#empresa_parametro_id').val(),
            empresa_id: $('#empresa_id_parametro').val(),
            condicion_fiscal_id: $('#select_condicion_fiscal_id').val(),
            iibb_regimen_id: $('#select_iibb_regimen_id').val(),
            jurisdiccion_sede_id: $('#select_jurisdiccion_sede_id').val(),
            ingresos_brutos_nro: $('#ingresos_brutos_nro').val(),
            inicio_actividades: $('#inicio_actividades').val(),
            leyenda_fiscal: $('#leyenda_fiscal').val(),
            arca_habilitado: $('#arca_habilitado').is(':checked') ? 1 : 0,
            compra_actualiza_lista_proveedor: $('#compra_actualiza_lista_proveedor').val(),
            compra_actualiza_listas_venta: $('#compra_actualiza_listas_venta').val(),
            punto_venta_defecto_id: $('#punto_venta_defecto_id').val(),
            lista_precio_defecto_id: $('#lista_precio_defecto_id').val(),
            condicion_pago_defecto_id: $('#condicion_pago_defecto_id').val(),
            f_desde: fDesde,
            f_hasta: fHasta,
            observaciones: $('#observaciones').val(),
            cerrar_anterior: $('#cerrar_anterior').is(':checked') ? 1 : 0,
            quitar_logo: $('#quitar_logo').val()
        };
        $.each(campos, function(k, v) { datos.append(k, v == null ? '' : v); });
        var archivoLogo = $('#logo')[0].files[0];
        if (archivoLogo) datos.append('logo', archivoLogo);

        $.ajax({
            url: 'empresas_parametros_ajax.php',
            type: 'POST',
            data: datos,
            processData: false,
            contentType: false,
            dataType: 'json',
            success: function(res) {
                btn.prop('disabled', false).html(origHtml);
                if (res && res.resultado) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Parámetros guardados',
                        text: res.mensaje || 'Configuración guardada correctamente',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 1500
                    });

                    var modalEl = document.getElementById('modalParametros');
                    var modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (modalInstance) modalInstance.hide();

                    cargarParametrosVigentes();
                    if (tablaHistorial) tablaHistorial.ajax.reload();
                } else {
                    Swal.fire('Error', (res && res.error) || 'No se pudo guardar la configuración', 'error');
                }
            },
            error: function() {
                btn.prop('disabled', false).html(origHtml);
                Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
            }
        });
    });

    // ---------- Configuración de impresión ----------
    var configsImpresion = [];

    function escaparHtml(t) {
        return $('<div>').text(t == null ? '' : String(t)).html();
    }

    function cargarImpresion() {
        $.get('empresas_parametros_ajax.php', { accion: 'impresion_listar', empresa_id: empresaActualId }, function(res) {
            configsImpresion = Array.isArray(res) ? res : [];
            if (!configsImpresion.length) {
                $('#tbodyImpresion').html('<tr><td colspan="7" class="text-center text-muted py-3">Sin configuración: se imprime en A4, con 1 copia, con logo y todas las columnas.</td></tr>');
                return;
            }
            var html = '';
            configsImpresion.forEach(function(r) {
                var muestra = [];
                if (r.mostrar_logo == 1) muestra.push('Logo');
                if (r.mostrar_codigo == 1) muestra.push('Código');
                if (r.mostrar_bonificacion == 1) muestra.push('% Bonif.');
                if (r.mostrar_sucursal_cliente == 1) muestra.push('Sucursal cliente');
                var activo = r.tabla_estado_registro_id == 1;
                var pie = r.texto_pie ? escaparHtml(r.texto_pie.length > 60 ? r.texto_pie.substr(0, 60) + '…' : r.texto_pie) : '<span class="text-muted">-</span>';
                html += '<tr>' +
                    '<td>' + (r.comprobante_subgrupo_id == 0 ? '<b>General</b>' : escaparHtml(r.comprobante_subgrupo || ('Subgrupo ' + r.comprobante_subgrupo_id))) + '</td>' +
                    '<td>' + escaparHtml(r.formato_nombre) + '<br><small class="text-muted">' + escaparHtml(r.fuente_nombre || '') + '</small>' + (r.color_acento ? ' <span class="d-inline-block border rounded align-middle" style="width:14px;height:14px;background:' + escaparHtml(r.color_acento) + '"></span>' : '') + '</td>' +
                    '<td class="text-center">' + parseInt(r.copias_defecto, 10) + '</td>' +
                    '<td><small>' + (muestra.join(', ') || '-') + '</small></td>' +
                    '<td><small>' + pie + '</small></td>' +
                    '<td class="text-center"><span class="badge ' + (activo ? 'bg-success' : 'bg-secondary') + '">' + (activo ? 'Activo' : 'Inactivo') + '</span></td>' +
                    '<td class="text-center"><div class="btn-group">' +
                        '<button type="button" class="btn btn-xs btn-outline-primary btn-editar-impresion" data-id="' + r.empresa_impresion_id + '" title="Editar"><i class="fas fa-edit"></i></button>' +
                        '<button type="button" class="btn btn-xs ' + (activo ? 'btn-outline-danger' : 'btn-outline-success') + ' btn-estado-impresion" data-id="' + r.empresa_impresion_id + '" data-estado="' + (activo ? 2 : 1) + '" title="' + (activo ? 'Inhabilitar' : 'Habilitar') + '"><i class="fas ' + (activo ? 'fa-ban' : 'fa-check') + '"></i></button>' +
                    '</div></td></tr>';
            });
            $('#tbodyImpresion').html(html);
        }, 'json');
    }

    function abrirModalImpresion(cfg) {
        $.get('empresas_parametros_ajax.php', { accion: 'impresion_subgrupos', empresa_id: empresaActualId }, function(subgrupos) {
            var opts = '<option value="0">General (todos los comprobantes)</option>';
            var grupoActual = null;
            (subgrupos || []).forEach(function(s) {
                var grupo = s.comprobante_grupo || 'Otros';
                if (grupo !== grupoActual) {
                    if (grupoActual !== null) opts += '</optgroup>';
                    opts += '<optgroup label="' + escaparHtml(grupo) + '">';
                    grupoActual = grupo;
                }
                opts += '<option value="' + s.comprobante_subgrupo_id + '">' + escaparHtml(s.comprobante_subgrupo) + '</option>';
            });
            if (grupoActual !== null) opts += '</optgroup>';
            $('#imp_subgrupo').html(opts);

            cfg = cfg || {};
            var nuevo = !cfg.empresa_impresion_id;
            $('#empresa_impresion_id').val(cfg.empresa_impresion_id || '');
            $('#imp_subgrupo').val(String(cfg.comprobante_subgrupo_id || 0));
            $('#imp_formato').val(cfg.formato || 'A4');
            $('#imp_copias').val(String(cfg.copias_defecto || 1));
            $('#imp_fuente').val(cfg.fuente || 'roboto');
            $('#imp_logo').prop('checked', nuevo || cfg.mostrar_logo == 1);
            $('#imp_codigo').prop('checked', nuevo || cfg.mostrar_codigo == 1);
            $('#imp_bonif').prop('checked', nuevo || cfg.mostrar_bonificacion == 1);
            $('#imp_sucursal').prop('checked', nuevo || cfg.mostrar_sucursal_cliente == 1);
            $('#imp_pie').val(cfg.texto_pie || '');
            $('#imp_color_usar').prop('checked', !!cfg.color_acento);
            $('#imp_color').val(cfg.color_acento || '#d9d9d9');
            $('#modalImpresionLabel').html('<i class="fas fa-print me-2"></i>' + (nuevo ? 'Nueva configuración de impresión' : 'Editar configuración de impresión'));
            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalImpresion')).show();
        }, 'json');
    }

    $('#btnNuevaImpresion').on('click', function() { abrirModalImpresion(null); });

    $(document).on('click', '.btn-editar-impresion', function() {
        var id = $(this).data('id');
        abrirModalImpresion(configsImpresion.find(function(r) { return r.empresa_impresion_id == id; }));
    });

    $(document).on('click', '.btn-estado-impresion', function() {
        $.post('empresas_parametros_ajax.php', {
            accion: 'impresion_cambiar_estado',
            empresa_id: empresaActualId,
            empresa_impresion_id: $(this).data('id'),
            nuevo_estado: $(this).data('estado')
        }, function(res) {
            if (res && res.resultado) cargarImpresion();
            else Swal.fire('Error', (res && res.error) || 'No se pudo cambiar el estado', 'error');
        }, 'json');
    });

    $('#btnGuardarImpresion').on('click', function() {
        var btn = $(this);
        btn.prop('disabled', true);
        $.post('empresas_parametros_ajax.php', {
            accion: 'impresion_guardar',
            empresa_id: empresaActualId,
            empresa_impresion_id: $('#empresa_impresion_id').val() || 0,
            comprobante_subgrupo_id: $('#imp_subgrupo').val(),
            formato: $('#imp_formato').val(),
            copias_defecto: $('#imp_copias').val(),
            fuente: $('#imp_fuente').val(),
            mostrar_logo: $('#imp_logo').is(':checked') ? 1 : 0,
            mostrar_codigo: $('#imp_codigo').is(':checked') ? 1 : 0,
            mostrar_bonificacion: $('#imp_bonif').is(':checked') ? 1 : 0,
            mostrar_sucursal_cliente: $('#imp_sucursal').is(':checked') ? 1 : 0,
            texto_pie: $('#imp_pie').val(),
            color_acento: $('#imp_color_usar').is(':checked') ? $('#imp_color').val() : ''
        }, function(res) {
            btn.prop('disabled', false);
            if (res && res.resultado) {
                bootstrap.Modal.getInstance(document.getElementById('modalImpresion')).hide();
                Swal.fire({ icon: 'success', title: res.mensaje || 'Guardado', toast: true, position: 'top-end', showConfirmButton: false, timer: 1500 });
                cargarImpresion();
            } else {
                Swal.fire('Error', (res && res.error) || 'No se pudo guardar', 'error');
            }
        }, 'json').fail(function() {
            btn.prop('disabled', false);
            Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
        });
    });

    // Inicio
    cargarImpresion();
    cargarCatalogos(function() {
        cargarParametrosVigentes();
        inicializarDataTableHistorial();
    });
});
</script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
