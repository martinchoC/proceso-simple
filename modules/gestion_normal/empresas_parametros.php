<?php
/**
 * Vista: Parámetros Generales de Empresa e Historial Fiscal
 * Módulo: Gestión - Seteo Empresas (padre_id = 37, orden = 405)
 * Página ID: 111
 */

$pageTitle = "Parámetros de Empresa";
$currentPage = 'empresas_parametros';
$modulo_idx = 5;
$pagina_idx = 611;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$empresa_id_activa = intval($_SESSION['empresa_id'] ?? 0);
?>

<main class="app-main">
    <?php ui_encabezado('Parámetros de empresa', 'bi bi-sliders',
        'Datos fiscales y valores por defecto de la empresa. Cada cambio queda como una versión con su fecha de vigencia.',
        ['Seteo Empresas'],
        '<select class="form-select form-select-sm" id="selectEmpresaFiltro" style="width: 240px;" aria-label="Empresa">'
        . '<option value="">Cargando empresas…</option></select>'
        . '<button type="button" class="btn btn-primary" id="btnNuevaVersion" data-funcion="guardar"><i class="bi bi-plus-lg me-1"></i>Nueva versión</button>'); ?>

    <div class="app-content">
        <div class="container-fluid">
            <div class="card">
                <div class="card-header pb-0 border-0">
                    <ul class="nav ui-solapas" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#solapaVigentes" type="button" role="tab">Vigentes</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#solapaImpresion" type="button" role="tab">Impresión</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#solapaHistorial" type="button" role="tab">Historial</button>
                        </li>
                    </ul>
                </div>
                <div class="card-body">
                    <div class="tab-content">

                        <!-- Parámetros vigentes -->
                        <div class="tab-pane fade show active" id="solapaVigentes" role="tabpanel">
                            <div class="mb-3" id="badgeEstadoVigente"></div>
                            <div id="contenedorParametrosVigentes">
                                <div class="text-center py-4 text-muted">
                                    <span class="spinner-border spinner-border-sm me-2"></span>Cargando…
                                </div>
                            </div>
                        </div>

                        <!-- Configuración de impresión (gestion__empresas_impresion) -->
                        <div class="tab-pane fade" id="solapaImpresion" role="tabpanel">
                            <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
                                <span class="text-muted small">Formato de los comprobantes impresos
                                    <?= ui_ayuda('La configuración General vale para todos los comprobantes; una por tipo de comprobante la reemplaza para ese tipo. Sin configuración se imprime en A4, con 1 copia.') ?></span>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="btnNuevaImpresion" data-funcion="editar">
                                    <i class="bi bi-plus-lg me-1"></i>Nueva configuración
                                </button>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Aplica a</th>
                                            <th>Formato</th>
                                            <th class="text-center">Copias</th>
                                            <th>Se muestra</th>
                                            <th>Texto al pie</th>
                                            <th>Estado</th>
                                            <th class="text-end">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyImpresion">
                                        <tr><td colspan="7" class="text-center text-muted py-3"><span class="spinner-border spinner-border-sm me-2"></span>Cargando…</td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Historial de versiones -->
                        <div class="tab-pane fade" id="solapaHistorial" role="tabpanel">
                            <div class="table-responsive">
                                <table id="tablaHistorialParametros" class="table table-hover align-middle mb-0" style="width:100%">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Condición IVA</th>
                                            <th>IIBB y sede</th>
                                            <th>N° IIBB</th>
                                            <th>PV por defecto</th>
                                            <th>Desde</th>
                                            <th>Hasta</th>
                                            <th>Observaciones</th>
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
        </div>
    </div>

    <!-- Modal de Configuración de Impresión -->
    <div class="modal fade" id="modalImpresion" tabindex="-1" aria-labelledby="modalImpresionLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalImpresionLabel"><i class="bi bi-printer me-2"></i>Impresión</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form id="formImpresion">
                        <input type="hidden" id="empresa_impresion_id" value="">
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="imp_subgrupo" class="form-label">Aplica a</label>
                                <select class="form-select" id="imp_subgrupo"></select>
                            </div>
                            <div class="col-8">
                                <label for="imp_formato" class="form-label">Formato</label>
                                <select class="form-select" id="imp_formato">
                                    <option value="A4">A4 (hoja completa)</option>
                                    <option value="TICKET80">Ticket 80 mm (impresora térmica)</option>
                                </select>
                            </div>
                            <div class="col-4">
                                <label for="imp_copias" class="form-label">Copias</label>
                                <select class="form-select" id="imp_copias">
                                    <option value="1">1 - Original</option>
                                    <option value="2">2 - + Duplicado</option>
                                    <option value="3">3 - + Triplicado</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Se muestra</label>
                                <div class="d-flex flex-wrap gap-3">
                                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="imp_logo"><label class="form-check-label" for="imp_logo">Logo</label></div>
                                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="imp_codigo"><label class="form-check-label" for="imp_codigo">Código de producto</label></div>
                                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="imp_bonif"><label class="form-check-label" for="imp_bonif">% Bonificación</label></div>
                                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="imp_sucursal"><label class="form-check-label" for="imp_sucursal">Sucursal / entrega del cliente</label></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="imp_pie" class="form-label">Texto al pie</label>
                                <textarea class="form-control" id="imp_pie" rows="2" maxlength="1000" placeholder="CBU, alias, garantía…"></textarea>
                            </div>
                        </div>
                        <details class="ui-mas-opciones">
                            <summary>Tipografía y color</summary>
                            <div class="row g-3">
                                <div class="col-12">
                                    <label for="imp_fuente" class="form-label">Tipografía
                                        <?= ui_ayuda('Roboto, Open Sans, Lato, Source Sans, Inter y Nunito Sans son finas; la angosta entra más texto por renglón.') ?></label>
                                    <select class="form-select" id="imp_fuente">
                                        <?php require_once ROOT_PATH . '/core/impresion.php'; foreach (IMPRESION_FUENTES as $clave => $f): ?>
                                        <option value="<?= htmlspecialchars($clave) ?>"><?= htmlspecialchars($f[0]) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Color de los títulos de la tabla (A4)
                                        <?= ui_ayuda('Sin marcar, los títulos salen en gris.') ?></label>
                                    <div class="d-flex align-items-center gap-2">
                                        <div class="form-check mb-0"><input class="form-check-input" type="checkbox" id="imp_color_usar"><label class="form-check-label" for="imp_color_usar">Usar color</label></div>
                                        <input type="color" class="form-control form-control-color form-control-sm" id="imp_color" value="#d9d9d9" title="Color">
                                    </div>
                                </div>
                            </div>
                        </details>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarImpresion">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Nueva Versión o Edición de Parámetros -->
    <div class="modal fade" id="modalParametros" tabindex="-1" aria-labelledby="modalParametrosLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable modal-fullscreen-md-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalParametrosLabel">
                        <i class="bi bi-sliders me-2"></i>Parámetros de Empresa
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form id="formParametros" class="needs-validation" novalidate>
                        <input type="hidden" id="empresa_parametro_id" name="empresa_parametro_id" />
                        <input type="hidden" id="empresa_id_parametro" name="empresa_id" />

                        <!-- Datos fiscales: IVA e Ingresos Brutos -->
                        <section class="ui-seccion">
                            <div class="ui-seccion-cabecera">
                                <h6 class="ui-seccion-titulo">Datos fiscales
                                    <?= ui_ayuda('Al guardar, la condición fiscal y el régimen de IIBB de la empresa se actualizan también en su ficha.') ?></h6>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="select_condicion_fiscal_id" class="form-label ui-requerido">Condición frente al IVA</label>
                                    <select class="form-select" id="select_condicion_fiscal_id" name="condicion_fiscal_id" required>
                                        <option value="">Elegí…</option>
                                    </select>
                                    <div class="invalid-feedback">Elegí la condición fiscal.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="inicio_actividades" class="form-label">Inicio de actividades</label>
                                    <input type="date" class="form-control" id="inicio_actividades" name="inicio_actividades" />
                                </div>
                                <div class="col-md-6">
                                    <label for="mes_cierre_ejercicio" class="form-label ui-requerido">Mes de cierre del ejercicio
                                        <?= ui_ayuda('Define el año contable: el ejercicio termina el último día de este mes. Lo usa Contabilidad > Cierre de Períodos Contables.') ?></label>
                                    <select class="form-select" id="mes_cierre_ejercicio" name="mes_cierre_ejercicio" required>
                                        <?php foreach (['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'] as $i => $nombre_mes): ?>
                                            <option value="<?= $i + 1 ?>"<?= $i === 11 ? ' selected' : '' ?>><?= $nombre_mes ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="select_iibb_regimen_id" class="form-label ui-requerido">Régimen de IIBB</label>
                                    <select class="form-select" id="select_iibb_regimen_id" name="iibb_regimen_id" required>
                                        <option value="">Elegí…</option>
                                    </select>
                                    <div class="invalid-feedback">Elegí el régimen de IIBB.</div>
                                    <div id="help_iibb_regimen" class="ui-sub mt-1"></div>
                                </div>
                                <div class="col-md-6">
                                    <label for="select_jurisdiccion_sede_id" class="form-label ui-requerido">Sede
                                        <?= ui_ayuda('Jurisdicción con su código ARCA / COMARB (ej. 901 CABA, 902 Bs. As.).') ?></label>
                                    <select class="form-select" id="select_jurisdiccion_sede_id" name="jurisdiccion_sede_id" required>
                                        <option value="">Elegí…</option>
                                    </select>
                                    <div class="invalid-feedback">Elegí la jurisdicción sede.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="ingresos_brutos_nro" class="form-label">N° de IIBB
                                        <?= ui_ayuda('Número local de la provincia o el CUIT / número de Convenio Multilateral.') ?></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="ingresos_brutos_nro" name="ingresos_brutos_nro" maxlength="50" />
                                        <button class="btn btn-outline-secondary" type="button" id="btnCopiarCuitIIBB" title="Copiar el CUIT de la empresa">Usar CUIT</button>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="leyenda_fiscal" class="form-label">Leyenda en comprobantes</label>
                                    <input type="text" class="form-control" id="leyenda_fiscal" name="leyenda_fiscal" placeholder="IVA Responsable Inscripto" maxlength="255" />
                                </div>
                            </div>
                        </section>

                        <!-- Factura electrónica -->
                        <section class="ui-seccion">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" role="switch" id="arca_habilitado" name="arca_habilitado" value="1">
                                <label class="form-check-label fw-medium" for="arca_habilitado">Factura electrónica con ARCA (CAE)
                                    <?= ui_ayuda('Activada: las facturas A/B se confirman recién cuando ARCA da el CAE, con el número de ARCA. Desactivada: se numeran al confirmar y la aprobación es manual.') ?></label>
                            </div>
                            <div class="mt-2" id="divArcaIntegracion" style="display:none;">
                                <label for="arca_integracion_empresa_id" class="form-label ui-requerido">Certificado para pedir el CAE
                                    <?= ui_ayuda('Certificados WSFE de la empresa y los que otra empresa le delegó. Homologación da CAE de prueba, sin valor fiscal; Producción emite comprobantes reales. Solo aparecen los activos con certificado vigente.') ?></label>
                                <select class="form-select" id="arca_integracion_empresa_id" name="arca_integracion_empresa_id">
                                    <option value="">Elegí el certificado…</option>
                                </select>
                            </div>
                        </section>

                        <!-- Valores por defecto -->
                        <section class="ui-seccion">
                            <div class="ui-seccion-cabecera">
                                <h6 class="ui-seccion-titulo">Por defecto al emitir</h6>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="punto_venta_defecto_id" class="form-label">Punto de venta</label>
                                    <select class="form-select" id="punto_venta_defecto_id" name="punto_venta_defecto_id">
                                        <option value="">Sin definir</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="lista_precio_defecto_id" class="form-label">Lista de precios</label>
                                    <select class="form-select" id="lista_precio_defecto_id" name="lista_precio_defecto_id">
                                        <option value="">Sin definir</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="condicion_pago_defecto_id" class="form-label">Condición de pago</label>
                                    <select class="form-select" id="condicion_pago_defecto_id" name="condicion_pago_defecto_id">
                                        <option value="">Sin definir</option>
                                    </select>
                                </div>
                            </div>
                        </section>

                        <!-- Vigencia -->
                        <section class="ui-seccion">
                            <div class="ui-seccion-cabecera">
                                <h6 class="ui-seccion-titulo">Vigencia</h6>
                            </div>
                            <div class="row g-3">
                                <div class="col-6">
                                    <label for="f_desde" class="form-label ui-requerido">Desde</label>
                                    <input type="date" class="form-control" id="f_desde" name="f_desde" required />
                                    <div class="invalid-feedback">Indicá desde cuándo rige.</div>
                                </div>
                                <div class="col-6">
                                    <label for="f_hasta" class="form-label">Hasta
                                        <?= ui_ayuda('Vacío: rige sin fecha de fin.') ?></label>
                                    <input type="date" class="form-control" id="f_hasta" name="f_hasta" />
                                </div>
                                <div class="col-12" id="divCerrarAnterior">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="cerrar_anterior" name="cerrar_anterior" value="1" checked />
                                        <label class="form-check-label small" for="cerrar_anterior">Cerrar la versión anterior el día previo</label>
                                    </div>
                                </div>
                                <div class="col-12">
                                    <label for="observaciones" class="form-label">Motivo del cambio</label>
                                    <textarea class="form-control" id="observaciones" name="observaciones" rows="2" placeholder="Opcional" maxlength="255"></textarea>
                                </div>
                            </div>
                        </section>

                        <details class="ui-mas-opciones">
                            <summary>Compras y logo</summary>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="compra_actualiza_lista_proveedor" class="form-label">Al confirmar una compra, lista del proveedor
                                        <?= ui_ayuda('Toma el precio unitario de la línea antes de descuentos. El costo del producto se actualiza siempre.') ?></label>
                                    <select class="form-select" id="compra_actualiza_lista_proveedor" name="compra_actualiza_lista_proveedor">
                                        <option value="0">No modificar</option>
                                        <option value="1">Cargar solo los productos sin precio</option>
                                        <option value="2">Actualizar siempre con el precio de la factura</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="compra_actualiza_listas_venta" class="form-label">Al confirmar una compra, listas de venta
                                        <?= ui_ayuda('Igual que "Actualizar precios de venta" de Lista Precios Proveedores: las listas manuales o sin regla no se tocan.') ?></label>
                                    <select class="form-select" id="compra_actualiza_listas_venta" name="compra_actualiza_listas_venta">
                                        <option value="0">No modificar</option>
                                        <option value="1">Recalcular con el costo nuevo (reglas de cada lista)</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <div class="small text-muted border rounded p-2 bg-light" id="ia_estado_empresa">
                                        <i class="fas fa-robot me-1"></i>Si la empresa usa la inteligencia artificial y con qué clave se define en Configuración &gt; Empresas (vale para todos los módulos). Acá solo van los topes por día.
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label for="ia_lectura_tope_diario" class="form-label">Lecturas de facturas con IA por día
                                        <?= ui_ayuda('Tope de envíos a la IA por día para la empresa al leer facturas de proveedores (cada uno tiene costo).') ?></label>
                                    <input type="number" class="form-control" id="ia_lectura_tope_diario" name="ia_lectura_tope_diario" min="0" max="1000" step="1" value="20">
                                </div>
                                <div class="col-md-6">
                                    <label for="ia_asistente_tope_diario" class="form-label">Pedidos al Asistente IA por día
                                        <?= ui_ayuda('Tope de pedidos al Asistente IA por día para toda la empresa (cada uno tiene costo).') ?></label>
                                    <input type="number" class="form-control" id="ia_asistente_tope_diario" name="ia_asistente_tope_diario" min="0" max="1000" step="1" value="50">
                                </div>
                                <div class="col-12">
                                    <label for="logo" class="form-label">Logo de los comprobantes
                                        <?= ui_ayuda('PNG, JPG, GIF o WEBP, hasta 1 MB. Va arriba a la izquierda en las facturas. Una versión nueva conserva el logo anterior si no se sube otro.') ?></label>
                                    <div class="d-flex align-items-center gap-3 flex-wrap">
                                        <div id="logoPreviewBox" class="border rounded d-flex align-items-center justify-content-center bg-white" style="width:180px;height:70px;">
                                            <img id="logoPreview" src="" alt="Logo" style="max-width:170px;max-height:62px;object-fit:contain;" class="d-none">
                                            <small id="logoSinImagen" class="text-muted">Sin logo</small>
                                        </div>
                                        <div class="flex-grow-1 d-flex gap-2 align-items-center">
                                            <input type="file" class="form-control" id="logo" name="logo" accept="image/png,image/jpeg,image/gif,image/webp">
                                            <input type="hidden" id="quitar_logo" name="quitar_logo" value="0">
                                            <button type="button" class="btn btn-light text-danger text-nowrap" id="btnQuitarLogo"><i class="bi bi-trash me-1"></i>Quitar</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </details>

                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="btnGuardarParametros">Guardar</button>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- JS Scripts -->
<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="<?= asset_local('js/moment-2.29.4/moment.min.js') ?>"></script>
<script src="<?= asset_local('js/moment-2.29.4/locale/es.min.js') ?>"></script>

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

            // Integraciones ARCA (WSFE): certificado + ambiente con el que se pide el CAE
            var selArca = $('#arca_integracion_empresa_id');
            selArca.empty();
            selArca.append('<option value="">Seleccione la integración ARCA...</option>');
            (catalogos.integraciones_arca || []).forEach(function(i) {
                selArca.append($('<option>').val(i.integracion_empresa_id).text(textoIntegracionArca(i))
                    .prop('disabled', parseInt(i.utilizable, 10) !== 1));
            });

            if (typeof callback === 'function') callback();
        }, 'json');
    }

    function textoIntegracionArca(i) {
        var t = (i.ambiente_nombre || i.ambiente_codigo) + ' · ' + i.certificado_archivo;
        if (parseInt(i.delegada, 10) === 1) t += ' · delegado por ' + (i.titular_empresa || ('empresa ' + i.titular_empresa_id)) + ' (CUIT ' + i.cuit + ')';
        if (i.observaciones) t += ' · ' + i.observaciones;
        if (i.certificado_vence) t += ' · vence ' + formatoFecha(i.certificado_vence);
        if (parseInt(i.tabla_estado_registro_id, 10) !== 1) t += ' (inactiva)';
        else if (parseInt(i.ambiente_activo, 10) !== 1) t += ' (ambiente inactivo)';
        else if (parseInt(i.certificado_vencido, 10) === 1) t += ' (certificado vencido o no encontrado)';
        return t;
    }

    // Con ARCA activado se elige el certificado; si hay uno solo utilizable, se propone
    function actualizarArcaIntegracion() {
        var activo = $('#arca_habilitado').is(':checked');
        $('#divArcaIntegracion').toggle(activo);
        $('#arca_integracion_empresa_id').prop('required', activo);
        if (activo && !$('#arca_integracion_empresa_id').val()) {
            var utilizables = $('#arca_integracion_empresa_id option[value!=""]:not(:disabled)');
            if (utilizables.length === 1) $('#arca_integracion_empresa_id').val(utilizables.val());
        }
    }
    $('#arca_habilitado').on('change', actualizarArcaIntegracion);

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
        return Fmt.fecha(d);
    }

    // Cargar Parámetros Vigentes
    function cargarParametrosVigentes() {
        $('#contenedorParametrosVigentes').html('<div class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando parámetros vigentes...</div>');

        $.get('empresas_parametros_ajax.php', {
            accion: 'obtener_vigentes',
            empresa_id: empresaActualId
        }, function(res) {
            logoVigente = (res && res.logo_data) || '';
            integracionArcaVigente = (res && res.arca_integracion_empresa_id) || '';
            mesCierreVigente = (res && parseInt(res.mes_cierre_ejercicio, 10)) || 12;
            if (res && res.empresa_parametro_id) {
                var fDesde = res.f_desde ? formatoFecha(res.f_desde) : '-';
                var fHasta = res.f_hasta ? formatoFecha(res.f_hasta) : '<span class="badge bg-success">Abierta / Vigente</span>';
                var fInicioAct = res.inicio_actividades ? formatoFecha(res.inicio_actividades) : '<span class="text-muted">No especificada</span>';
                var mesCierre = $('#mes_cierre_ejercicio option[value="' + (parseInt(res.mes_cierre_ejercicio, 10) || 12) + '"]').text();
                var pv = res.punto_venta_numero ? 'PV ' + res.punto_venta_numero + (res.punto_venta_descripcion ? ' (' + res.punto_venta_descripcion + ')' : '') : '<span class="text-muted">Sin definir</span>';
                var lp = res.lista_precio ? res.lista_precio : '<span class="text-muted">Sin definir</span>';
                var cp = res.condicion_pago ? res.condicion_pago : '<span class="text-muted">Sin definir</span>';

                var regimen = ['No definido', 'Local / Simplificado', 'Convenio Multilateral', 'Exento / No alcanzado'][parseInt(res.iibb_regimen_id, 10) || 0] || 'No definido';
                var regBadge = regimen + (res.iibb_regimen_descripcion
                    ? ' <span class="ui-ayuda" tabindex="0" data-bs-toggle="tooltip" title="' + $('<div>').text(res.iibb_regimen_descripcion).html() + '"><i class="bi bi-question-circle"></i></span>'
                    : '');

                var sedeLabel = '<span class="text-muted">-</span>';
                if (res.codigo_comarb) {
                    var org = res.jurisdiccion_sede_organismo ? ` <span class="ui-sub">(${res.jurisdiccion_sede_organismo})</span>` : '';
                    sedeLabel = `${res.jurisdiccion_sede || ''} <span class="ui-sub">${res.codigo_comarb}</span>${org}`;
                }

                $('#badgeEstadoVigente').html(`<span class="badge ${res.bg_clase || 'bg-success'}">${res.estado_registro || 'Activo'}</span>`
                    + ` <span class="ui-sub ms-1">desde ${fDesde}${res.f_hasta ? ' hasta ' + formatoFecha(res.f_hasta) : ''}</span>`);

                var arca = parseInt(res.arca_habilitado, 10) === 1
                    ? 'Habilitada' + (res.arca_integracion
                        ? ' · ' + $('<div>').text(res.arca_integracion.ambiente_nombre).html()
                          + '<div class="ui-sub">' + $('<div>').text(textoIntegracionArca(res.arca_integracion)).html() + '</div>'
                        : ' <span class="ui-sub">(única integración WSFE activa)</span>')
                    : '<span class="text-muted">No habilitada</span>';

                var dato = function (etiqueta, valor) {
                    return '<div class="col-sm-6 col-lg-4"><dt>' + etiqueta + '</dt><dd>' + valor + '</dd></div>';
                };
                var listaProv = ['no se modifica', 'solo sin precio', 'siempre'][parseInt(res.compra_actualiza_lista_proveedor, 10) || 0] || 'no se modifica';
                var listasVenta = parseInt(res.compra_actualiza_listas_venta, 10) === 1 ? 'se recalculan' : 'no se modifican';
                // Uso de la IA: se define en Configuración > Empresas (core/ia_empresa.php); acá solo los topes
                var iaOn = parseInt(res.ia_empresa_habilitada, 10) === 1;
                var iaClave = res.ia_empresa_origen === 'propia' ? 'clave propia ' + (res.ia_empresa_mascara || '')
                    : (res.ia_empresa_origen === 'principal' ? 'clave de la empresa principal' + (res.ia_empresa_principal ? ' (' + res.ia_empresa_principal + ')' : '')
                    : 'clave del servidor');
                var usoIa = iaOn
                    ? 'Habilitada <span class="ui-sub">· ' + $('<span>').text(iaClave).html() + '</span>'
                      + '<div class="ui-sub">Hasta ' + (parseInt(res.ia_lectura_tope_diario, 10) || 0) + ' lecturas de facturas y '
                      + (parseInt(res.ia_asistente_tope_diario, 10) || 0) + ' pedidos al asistente por día</div>'
                    : '<span class="text-muted">Deshabilitada</span> <span class="ui-sub">(Configuración &gt; Empresas)</span>';
                $('#ia_estado_empresa').html('<i class="fas fa-robot me-1"></i>Inteligencia artificial: ' + usoIa
                    + '<div>Se habilita y se carga la clave en Configuración &gt; Empresas (vale para todos los módulos). Acá solo van los topes por día.</div>');
                var logo = res.logo_data
                    ? '<img src="' + res.logo_data + '" alt="Logo" style="max-width:140px;max-height:48px;object-fit:contain;">'
                    : '<span class="text-muted">Sin logo</span>';

                var html =
                    '<div class="ui-seccion"><h6 class="ui-seccion-titulo mb-2">Datos fiscales</h6><dl class="row g-3 ui-datos">' +
                        dato('Condición frente al IVA', res.condicion_fiscal + (res.condicion_fiscal_codigo ? ' <span class="ui-sub">' + res.condicion_fiscal_codigo + '</span>' : '')) +
                        dato('CUIT', res.empresa_cuit || '-') +
                        dato('Inicio de actividades', fInicioAct) +
                        dato('Cierre del ejercicio', mesCierre) +
                        dato('Ingresos Brutos', regBadge) +
                        dato('N° de IIBB', res.ingresos_brutos_nro || '-') +
                        dato('Sede', sedeLabel) +
                        dato('Leyenda en comprobantes', res.leyenda_fiscal || '-') +
                    '</dl></div>' +
                    '<div class="ui-seccion"><h6 class="ui-seccion-titulo mb-2">Facturación y compras</h6><dl class="row g-3 ui-datos">' +
                        dato('Factura electrónica (ARCA)', arca) +
                        dato('Al confirmar una compra', 'Lista del proveedor: ' + listaProv + '<div>Listas de venta: ' + listasVenta + '</div>') +
                        dato('Inteligencia artificial', usoIa) +
                        dato('Logo', logo) +
                    '</dl></div>' +
                    '<div class="ui-seccion"><h6 class="ui-seccion-titulo mb-2">Por defecto al emitir</h6><dl class="row g-3 ui-datos">' +
                        dato('Punto de venta', pv) +
                        dato('Lista de precios', lp) +
                        dato('Condición de pago', cp) +
                    '</dl></div>' +
                    '<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 border-top pt-3">' +
                        '<span class="ui-sub">' + (res.observaciones ? $('<div>').text(res.observaciones).html() : '') + '</span>' +
                        '<button type="button" class="btn btn-sm btn-outline-primary btn-editar-param" data-funcion="editar" data-id="' + res.empresa_parametro_id + '">' +
                            '<i class="bi bi-pencil me-1"></i>Editar</button>' +
                    '</div>';
                $('#contenedorParametrosVigentes').html(html);
                if (window.UiSimple) UiSimple.tooltips('#contenedorParametrosVigentes');
            } else {
                $('#badgeEstadoVigente').empty();
                $('#contenedorParametrosVigentes').html(`
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-exclamation-triangle text-warning fs-3 d-block mb-2"></i>
                        Esta empresa no tiene parámetros vigentes configurados.
                        <div class="mt-3">
                            <button type="button" class="btn btn-sm btn-primary" id="btnCrearPrimerosParametros" data-funcion="guardar">
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
                { data: 'empresa_parametro_id', className: 'ui-detalle' },
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
                    className: 'ui-detalle',
                    render: function(data) {
                        return data ? `<span class="font-monospace fw-bold">${data}</span>` : '<span class="text-muted">-</span>';
                    }
                },
                {
                    data: 'punto_venta_numero',
                    className: 'ui-detalle',
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
                        return Fmt.fecha(data);
                    }
                },
                {
                    data: 'f_hasta',
                    className: 'text-center',
                    render: function(data) {
                        if (!data) return '<span class="badge bg-success">Abierta</span>';
                        return Fmt.fecha(data);
                    }
                },
                {
                    data: 'observaciones',
                    className: 'ui-detalle',
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
                    className: 'ui-acciones',
                    render: function(data, type, row) {
                        var botones = '';
                        botones += `<button type="button" class="btn btn-xs btn-outline-primary btn-editar-param me-1" data-funcion="editar" 
                                        data-id="${row.empresa_parametro_id}" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>`;
                        if (row.tabla_estado_registro_id == 1) {
                            botones += `<button type="button" class="btn btn-xs btn-outline-danger btn-estado-param" data-funcion="inactivar" 
                                            data-id="${row.empresa_parametro_id}" data-estado="2" title="Inhabilitar">
                                        <i class="fas fa-ban"></i>
                                    </button>`;
                        } else {
                            botones += `<button type="button" class="btn btn-xs btn-outline-success btn-estado-param" data-funcion="habilitar" 
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
        // Una vigencia nueva propone el certificado de la vigente
        $('#arca_integracion_empresa_id').val(integracionArcaVigente || '');
        actualizarArcaIntegracion();
        // y el mes de cierre del ejercicio vigente
        $('#mes_cierre_ejercicio').val(String(mesCierreVigente));
        var today = new Date().toISOString().split('T')[0];
        $('#f_desde').val(today);
        $('#f_hasta').val('');
        $('#modalParametrosLabel').html('<i class="fas fa-sliders-h me-2"></i>Nueva Versión de Parámetros');
        logoActual = logoVigente; // una vigencia nueva hereda el logo actual salvo que se quite o reemplace
        mostrarLogo(logoActual);
    }

    // Logo: vista previa del registro (logo_data viene como data URI) o del archivo elegido
    var logoActual = '', logoVigente = '';
    var integracionArcaVigente = '';
    var mesCierreVigente = 12;
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
                $('#mes_cierre_ejercicio').val(String(parseInt(res.mes_cierre_ejercicio, 10) || 12));
                $('#leyenda_fiscal').val(res.leyenda_fiscal || '');
                $('#arca_habilitado').prop('checked', parseInt(res.arca_habilitado, 10) === 1);
                $('#arca_integracion_empresa_id').val(res.arca_integracion_empresa_id || '');
                actualizarArcaIntegracion();
                $('#compra_actualiza_lista_proveedor').val(String(parseInt(res.compra_actualiza_lista_proveedor, 10) || 0));
                $('#compra_actualiza_listas_venta').val(String(parseInt(res.compra_actualiza_listas_venta, 10) || 0));
                $('#ia_lectura_tope_diario').val(res.ia_lectura_tope_diario !== undefined && res.ia_lectura_tope_diario !== null ? res.ia_lectura_tope_diario : 20);
                $('#ia_asistente_tope_diario').val(res.ia_asistente_tope_diario !== undefined && res.ia_asistente_tope_diario !== null ? res.ia_asistente_tope_diario : 50);
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
            mes_cierre_ejercicio: $('#mes_cierre_ejercicio').val(),
            leyenda_fiscal: $('#leyenda_fiscal').val(),
            arca_habilitado: $('#arca_habilitado').is(':checked') ? 1 : 0,
            arca_integracion_empresa_id: $('#arca_integracion_empresa_id').val(),
            compra_actualiza_lista_proveedor: $('#compra_actualiza_lista_proveedor').val(),
            compra_actualiza_listas_venta: $('#compra_actualiza_listas_venta').val(),
            ia_lectura_tope_diario: $('#ia_lectura_tope_diario').val(),
            ia_asistente_tope_diario: $('#ia_asistente_tope_diario').val(),
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
                    '<td class="ui-acciones"><div class="btn-group">' +
                        '<button type="button" class="btn btn-xs btn-outline-primary btn-editar-impresion" data-funcion="editar" data-id="' + r.empresa_impresion_id + '" title="Editar"><i class="fas fa-edit"></i></button>' +
                        '<button type="button" class="btn btn-xs ' + (activo ? 'btn-outline-danger' : 'btn-outline-success') + ' btn-estado-impresion" data-funcion="editar" data-id="' + r.empresa_impresion_id + '" data-estado="' + (activo ? 2 : 1) + '" title="' + (activo ? 'Inhabilitar' : 'Habilitar') + '"><i class="fas ' + (activo ? 'fa-ban' : 'fa-check') + '"></i></button>' +
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
