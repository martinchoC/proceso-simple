<?php
/**
 * Vista: Asistente de Inicio de Empresa y Clonación Autorizada
 * Módulo: Gestión - Seteo Empresas (padre_id = 37, orden = 401)
 * Página ID: 121
 */

$pageTitle = "Inicio de Empresa - Asistente y Copia";
$currentPage = 'empresa_inicio';
$modudo_idx = 2;
$pagina_idx = 121;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

// Empresa activa: la resuelve y valida header1.php (GET empresa_id -> sesión).
$empresa_id_activa = (int) ($current_empresa_id ?? 0);

$origen_id_url = intval($_GET['origen_id'] ?? 0);

// 2. Cargar todas las empresas activas para el selector
$todasLasEmpresas = [];
$resEmp = $conn->query("SELECT empresa_id, empresa, documento_numero FROM conf__empresas WHERE tabla_estado_registro_id = 1 ORDER BY empresa ASC");
if ($resEmp) {
    while ($rEmp = $resEmp->fetch_assoc()) {
        $todasLasEmpresas[] = $rEmp;
    }
}

// El selector de empresa activa solo ofrece las empresas a las que el usuario tiene acceso
// ($empresas_modulos y $es_superusuario vienen de header1.php).
$empresasAccesibles = array_values(array_filter($todasLasEmpresas, function ($e) use ($empresas_modulos, $es_superusuario) {
    return $es_superusuario || isset($empresas_modulos[(int) $e['empresa_id']]);
}));

// Nombre de la empresa activa: la que puede autorizar la copia de su configuración a la empresa nueva
$empresaActivaNombre = '';
foreach ($empresasAccesibles as $e) {
    if ((int) $e['empresa_id'] === $empresa_id_activa) {
        $empresaActivaNombre = (string) $e['empresa'];
    }
}

// Alta de empresa nueva desde la constancia de CUIT (IA); header1.php oculta el botón sin la función alta_empresa
$accionesEncabezado = '<button type="button" class="btn btn-primary btn-sm" id="btnAltaEmpresaConstancia" data-funcion="alta_empresa">'
    . '<i class="fas fa-file-invoice me-1"></i>Nueva empresa desde constancia de CUIT</button>';
?>

<main class="app-main">
    <!-- Header -->
    <?php ui_encabezado('Inicio de empresa','fas fa-rocket', 'Guía completa paso a paso para configurar los elementos esenciales de la empresa y copiar datos desde otra empresa autorizada. Con "Nueva empresa desde constancia de CUIT" se da de alta otra empresa leyendo con IA la Constancia de Inscripción de ARCA', ['Seteo Empresas'], $accionesEncabezado); ?>

    <!-- Contenido -->
    <div class="app-content">
        <div class="container-fluid">
            
            <!-- Barra superior: Selector de Empresa y Accesos -->
            <div class="card card-outline card-primary mb-3 shadow-sm">
                <div class="card-body py-2">
                    <div class="row align-items-center g-2">
                        <div class="col-auto">
                            <label for="selectEmpresaFiltro" class="col-form-label fw-bold">
                                <i class="fas fa-building me-1 text-primary"></i>Empresa a Configurar:
                            </label>
                        </div>
                        <div class="col-md-5 col-sm-6">
                            <select class="form-select form-select-sm" id="selectEmpresaFiltro">
                                <?php foreach ($empresasAccesibles as $emp): ?>
                                    <option value="<?= $emp['empresa_id'] ?>" <?= ($emp['empresa_id'] == $empresa_id_activa) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($emp['empresa']) ?> (ID: <?= $emp['empresa_id'] ?><?= $emp['documento_numero'] ? ' - CUIT: ' . $emp['documento_numero'] : '' ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-auto ms-auto d-flex gap-2">
                            <a href="empresas_autorizaciones_copia.php" class="btn btn-sm btn-outline-primary" title="Administrar autorizaciones que esta empresa otorga a otras">
                                <i class="fas fa-key me-1"></i>Autorizaciones de Acceso
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnActualizarDiagnostico" title="Refrescar estado de inicio">
                                <i class="fas fa-sync-alt me-1"></i>Actualizar Estado
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scorecard & Progreso de Configuración -->
            <div class="card card-outline card-info mb-3 shadow-sm">
                <div class="card-body py-3">
                    <div class="row align-items-center g-3">
                        <div class="col-lg-7 col-md-6 border-end">
                            <div class="d-flex align-items-center mb-2">
                                <div class="bg-primary text-white rounded-circle p-3 me-3">
                                    <i class="fas fa-building fa-2x"></i>
                                </div>
                                <div>
                                    <h4 class="mb-0 fw-bold" id="empresaRazonSocial">Cargando...</h4>
                                    <div class="text-muted small">
                                        <span>CUIT: <strong id="empresaCuit">...</strong></span> | 
                                        <span>Condición Fiscal: <strong id="empresaCondFiscal">...</strong></span> | 
                                        <span>Sede: <span id="empresaLocalidad">...</span></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5 col-md-6">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold">Nivel de Completitud Inicial:</span>
                                <span class="badge fs-6" id="badgeScore">0%</span>
                            </div>
                            <div class="progress mb-2" style="height: 18px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated bg-warning" id="progressBarScore" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                            </div>
                            <small class="text-muted d-block" id="scoreMensaje">Analizando componentes configurados...</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SECCIÓN DESTACADA: COPIAR DATOS INICIALES DESDE EMPRESA AUTORIZADA -->
            <div class="card card-outline card-success mb-4 shadow-sm" id="cardCopiaAutorizada">
                <div class="card-header bg-success-subtle py-2 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fs-6 fw-bold text-success-emphasis">
                        <i class="fas fa-clone me-2"></i>Copia Asistida de Datos Iniciales (Desde Empresa Autorizada)
                    </h5>
                    <span class="badge bg-success" id="badgeEmpresasAutorizadasCount">0 autorizaciones disponibles</span>
                </div>
                <div class="card-body">
                    <div id="contenedorSinAutorizaciones" style="display: none;">
                        <div class="alert alert-warning mb-0 d-flex align-items-center">
                            <i class="fas fa-shield-alt fa-2x text-warning me-3"></i>
                            <div>
                                <strong>No se registran autorizaciones activas de otras empresas para copiar datos.</strong>
                                <p class="mb-1 small">Para copiar el plan de cuentas, los datos básicos (monedas, IVA, tipos de asiento, categorías), la tesorería o los tipos de comprobantes desde otra empresa existente (por ej. Casa Lucho o Developsam), esa empresa debe autorizar previamente a esta empresa destino.</p>
                                <a href="empresas_autorizaciones_copia.php" class="btn btn-xs btn-outline-dark mt-1">
                                    <i class="fas fa-external-link-alt me-1"></i>Ver o gestionar autorizaciones interempresa
                                </a>
                            </div>
                        </div>
                    </div>

                    <div id="contenedorConAutorizaciones" style="display: none;">
                        <div class="row g-3 align-items-end mb-3">
                            <div class="col-md-5">
                                <label for="selectEmpresaOrigenCopia" class="form-label fw-bold">
                                    <i class="fas fa-building-circle-arrow-right text-success me-1"></i>Empresa Origen Autorizada:
                                </label>
                                <select class="form-select" id="selectEmpresaOrigenCopia">
                                    <option value="">Seleccione una empresa autorizada...</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-2 bg-light small" id="infoAutorizacionSeleccionada">
                                    <em>Seleccione una empresa para ver los componentes habilitados.</em>
                                </div>
                            </div>
                            <div class="col-md-3 text-end d-flex gap-2 justify-content-end">
                                <button type="button" class="btn btn-outline-info" id="btnPrevisualizarCopia" disabled>
                                    <i class="fas fa-eye me-1"></i>Previsualizar
                                </button>
                                <button type="button" class="btn btn-success fw-bold" id="btnEjecutarCopia" disabled>
                                    <i class="fas fa-download me-1"></i>Copiar Datos Iniciales
                                </button>
                            </div>
                        </div>

                        <!-- Checkboxes de componentes autorizados para copiar -->
                        <div class="border rounded p-3 bg-light mb-2" id="boxComponentesCopia">
                            <label class="form-label fw-bold small text-muted text-uppercase mb-2">Seleccione los componentes a importar a esta empresa:</label>
                            <div class="row g-2" id="checkboxesComponentes">
                                <!-- Generado dinámicamente según la autorización -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- CHECKLIST INTERACTIVO DE PUNTOS A CONSIDERAR -->
            <div class="card card-outline card-secondary shadow-sm mb-4">
                <div class="card-header bg-light py-2 d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0 fs-6 fw-bold">
                        <i class="fas fa-tasks text-primary me-2"></i>Puntos Esenciales para Iniciar la Empresa en Gestión
                    </h5>
                    <?= ui_ayuda('Revisá cada punto a continuación para asegurar que la empresa opere con total normalidad') ?>
                </div>
                <div class="card-body p-3">
                    
                    <div class="accordion" id="accordionInicio">

                        <!-- 0. TIPO DE OPERACIÓN Y PERFILES -->
                        <div class="accordion-item mb-2 border rounded shadow-xs">
                            <h2 class="accordion-header" id="headingPerf">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePerf" aria-expanded="false" aria-controls="collapsePerf">
                                    <span class="badge me-2" id="chkBadgePerf"><i class="fas fa-times"></i></span>
                                    <strong class="me-2">0. Tipo de Operación y Perfiles de Usuario</strong>
                                    <span class="text-muted small" id="chkResumenPerf">(Sin datos)</span>
                                </button>
                            </h2>
                            <div id="collapsePerf" class="accordion-collapse collapse" aria-labelledby="headingPerf" data-bs-parent="#accordionInicio">
                                <div class="accordion-body bg-light-subtle">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <p class="mb-2"><strong>¿Por qué es necesario?</strong> Una empresa que solo presta servicios no usa pedidos de clientes, remitos ni stock. El tipo de operación decide qué perfiles predefinidos se le ofrecen y, en <em>Servicios</em>, esas pantallas se omiten de los perfiles para que no aparezcan en el menú.</p>
                                            <div class="d-flex align-items-center gap-2 mb-3">
                                                <label class="form-label fw-bold mb-0 text-nowrap" for="selectTipoOperacion">Tipo de operación:</label>
                                                <select class="form-select form-select-sm w-auto" id="selectTipoOperacion">
                                                    <option value="SERVICIOS">Servicios</option>
                                                    <option value="BIENES">Venta de bienes</option>
                                                    <option value="MIXTA">Bienes y servicios</option>
                                                </select>
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="btnGuardarTipoOperacion" disabled>
                                                    <i class="fas fa-save me-1"></i>Guardar
                                                </button>
                                            </div>
                                            <div class="fw-semibold small mb-1">Perfiles predefinidos para este tipo:</div>
                                            <div id="listaPerfilesPredefinidos"></div>
                                            <div id="boxPerfilesBienes" class="mt-3" style="display: none;">
                                                <div class="fw-semibold small mb-1 text-warning-emphasis"><i class="fas fa-exclamation-triangle me-1"></i>Perfiles que todavía muestran pedidos, remitos o stock:</div>
                                                <div id="listaPerfilesBienes"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-end d-flex flex-column gap-2 justify-content-center">
                                            <a href="../conf/empresas_perfiles_funciones.php" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt me-1"></i>Ir a Perfiles - Funciones
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-success" id="btnCrearPerfilesPredefinidos" disabled>
                                                <i class="fas fa-user-plus me-1"></i>Crear Perfiles Predefinidos Faltantes
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-warning" id="btnQuitarFuncionesBienes" style="display: none;">
                                                <i class="fas fa-eye-slash me-1"></i>Ocultar Pedidos, Remitos y Stock
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 1. SUCURSALES -->
                        <div class="accordion-item mb-2 border rounded shadow-xs">
                            <h2 class="accordion-header" id="headingSuc">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseSuc" aria-expanded="false" aria-controls="collapseSuc">
                                    <span class="badge me-2" id="chkBadgeSuc"><i class="fas fa-times"></i></span>
                                    <strong class="me-2">1. Sucursales de la Empresa</strong>
                                    <span class="text-muted small" id="chkResumenSuc">(0 registradas)</span>
                                </button>
                            </h2>
                            <div id="collapseSuc" class="accordion-collapse collapse" aria-labelledby="headingSuc" data-bs-parent="#accordionInicio">
                                <div class="accordion-body bg-light-subtle">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <p class="mb-1"><strong>¿Por qué es necesario?</strong> Representa la sede física, depósito central, planta o locales comerciales de la empresa. Todo movimiento de mercadería y emisión de comprobantes se realiza en el marco de una sucursal.</p>
                                            <div id="listaSucursalesDetalle" class="mt-2"></div>
                                        </div>
                                        <div class="col-md-4 text-end d-flex flex-column gap-2 justify-content-center">
                                            <a href="sucursales.php" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt me-1"></i>Ir a ABM de Sucursales
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-success" id="btnCrearSucursalRapida">
                                                <i class="fas fa-bolt me-1"></i>Crear Casa Central y Depósito Rápido
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. BOCAS DE STOCK -->
                        <div class="accordion-item mb-2 border rounded shadow-xs">
                            <h2 class="accordion-header" id="headingBoc">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseBoc" aria-expanded="false" aria-controls="collapseBoc">
                                    <span class="badge me-2" id="chkBadgeBoc"><i class="fas fa-times"></i></span>
                                    <strong class="me-2">2. Bocas de Depósito y Stock</strong>
                                    <span class="text-muted small" id="chkResumenBoc">(0 registradas)</span>
                                </button>
                            </h2>
                            <div id="collapseBoc" class="accordion-collapse collapse" aria-labelledby="headingBoc" data-bs-parent="#accordionInicio">
                                <div class="accordion-body bg-light-subtle">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <p class="mb-1"><strong>¿Por qué es necesario?</strong> Las bocas son las ubicaciones físicas o lógicas dentro de una sucursal donde reside el stock. Todo remito de entrada/salida y facturación directa mueve existencias en una boca determinada.</p>
                                            <div id="listaBocasDetalle" class="mt-2"></div>
                                        </div>
                                        <div class="col-md-4 text-end d-flex flex-column gap-2 justify-content-center">
                                            <a href="bocas.php" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt me-1"></i>Ir a ABM de Bocas
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 3. PUNTOS DE VENTA -->
                        <div class="accordion-item mb-2 border rounded shadow-xs">
                            <h2 class="accordion-header" id="headingPv">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePv" aria-expanded="false" aria-controls="collapsePv">
                                    <span class="badge me-2" id="chkBadgePv"><i class="fas fa-times"></i></span>
                                    <strong class="me-2">3. Puntos de Venta (Bocas de Emisión)</strong>
                                    <span class="text-muted small" id="chkResumenPv">(0 registrados)</span>
                                </button>
                            </h2>
                            <div id="collapsePv" class="accordion-collapse collapse" aria-labelledby="headingPv" data-bs-parent="#accordionInicio">
                                <div class="accordion-body bg-light-subtle">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <p class="mb-1"><strong>¿Por qué es necesario?</strong> Requerido para numerar y emitir comprobantes fiscales (ARCA - WSFE / Factura Electrónica, RCEL) o comprobantes manuales/internos. Se asocia a una sucursal y a una boca de stock.</p>
                                            <div id="listaPvDetalle" class="mt-2"></div>
                                        </div>
                                        <div class="col-md-4 text-end d-flex flex-column gap-2 justify-content-center">
                                            <a href="puntos_venta.php" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt me-1"></i>Ir a Puntos de Venta
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 4. TIPOS DE COMPROBANTES -->
                        <div class="accordion-item mb-2 border rounded shadow-xs">
                            <h2 class="accordion-header" id="headingComp">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseComp" aria-expanded="false" aria-controls="collapseComp">
                                    <span class="badge me-2" id="chkBadgeComp"><i class="fas fa-times"></i></span>
                                    <strong class="me-2">4. Tipos de Comprobantes Habilitados</strong>
                                    <span class="text-muted small" id="chkResumenComp">(0 configurados)</span>
                                </button>
                            </h2>
                            <div id="collapseComp" class="accordion-collapse collapse" aria-labelledby="headingComp" data-bs-parent="#accordionInicio">
                                <div class="accordion-body bg-light-subtle">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <p class="mb-1"><strong>¿Por qué es necesario?</strong> Define las reglas de stock, contabilidad y cuenta corriente para cada comprobante (Facturas A/B/C, Notas de Crédito, Remitos de entrega, Recibos de cobranza, Órdenes de pago). Cada empresa dispone de su catálogo propio parametrizado.</p>
                                            <div id="listaCompDetalle" class="mt-2"></div>
                                        </div>
                                        <div class="col-md-4 text-end d-flex flex-column gap-2 justify-content-center">
                                            <a href="comprobantes_tipos.php" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt me-1"></i>Ir a Tipos de Comprobantes
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 5. ACCESOS POR PERFIL -->
                        <div class="accordion-item mb-2 border rounded shadow-xs">
                            <h2 class="accordion-header" id="headingAcc">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAcc" aria-expanded="false" aria-controls="collapseAcc">
                                    <span class="badge me-2" id="chkBadgeAcc"><i class="fas fa-times"></i></span>
                                    <strong class="me-2">5. Accesos a Sucursales y Comprobantes por Perfil</strong>
                                    <span class="text-muted small" id="chkResumenAcc">(0 permisos otorgados)</span>
                                </button>
                            </h2>
                            <div id="collapseAcc" class="accordion-collapse collapse" aria-labelledby="headingAcc" data-bs-parent="#accordionInicio">
                                <div class="accordion-body bg-light-subtle">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <p class="mb-1"><strong>¿Por qué es necesario?</strong> En <code>gestion__perfiles_sucursales</code> se otorga permiso a cada perfil de usuario para operar en sucursales, bocas y puntos de venta. Si un perfil no tiene asignación, sus usuarios no podrán emitir ni consultar comprobantes.</p>
                                            <div id="listaAccDetalle" class="mt-2"></div>
                                        </div>
                                        <div class="col-md-4 text-end d-flex flex-column gap-2 justify-content-center">
                                            <a href="accesos_perfiles.php" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt me-1"></i>Ir a Accesos por Perfil
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-success" id="btnAsignarAccesosGlobales">
                                                <i class="fas fa-user-shield me-1"></i>Conceder Acceso Completo a Todos los Perfiles
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 6. PLAN DE CUENTAS CONTABLES -->
                        <div class="accordion-item mb-2 border rounded shadow-xs">
                            <h2 class="accordion-header" id="headingPlan">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePlan" aria-expanded="false" aria-controls="collapsePlan">
                                    <span class="badge me-2" id="chkBadgePlan"><i class="fas fa-times"></i></span>
                                    <strong class="me-2">6. Plan de Cuentas Contables</strong>
                                    <span class="text-muted small" id="chkResumenPlan">(0 cuentas)</span>
                                </button>
                            </h2>
                            <div id="collapsePlan" class="accordion-collapse collapse" aria-labelledby="headingPlan" data-bs-parent="#accordionInicio">
                                <div class="accordion-body bg-light-subtle">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <p class="mb-1"><strong>¿Por qué es necesario?</strong> La contabilidad bimonetaria de Gestión genera asientos automáticos en ventas, compras, cobranzas, pagos y movimientos de tesorería. Se requiere el árbol jerárquico de cuentas imputables (Activo, Pasivo, PN, Ingresos, Egresos).</p>
                                            <div id="listaPlanDetalle" class="mt-2"></div>
                                        </div>
                                        <div class="col-md-4 text-end d-flex flex-column gap-2 justify-content-center">
                                            <a href="plan_cuentas.php" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt me-1"></i>Ir a Plan de Cuentas
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 7. CONCEPTOS CONTABLES -->
                        <div class="accordion-item mb-2 border rounded shadow-xs">
                            <h2 class="accordion-header" id="headingConc">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseConc" aria-expanded="false" aria-controls="collapseConc">
                                    <span class="badge me-2" id="chkBadgeConc"><i class="fas fa-times"></i></span>
                                    <strong class="me-2">7. Conceptos Contables de la Empresa</strong>
                                    <span class="text-muted small" id="chkResumenConc">(0 configurados)</span>
                                </button>
                            </h2>
                            <div id="collapseConc" class="accordion-collapse collapse" aria-labelledby="headingConc" data-bs-parent="#accordionInicio">
                                <div class="accordion-body bg-light-subtle">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <p class="mb-1"><strong>¿Por qué es necesario?</strong> Mapea los conceptos automáticos del sistema (ej: <code>IVA_DEBITO_FISCAL</code>, <code>IVA_CREDITO_FISCAL</code>, <code>VALORES_A_PAGAR</code>, <code>VALORES_A_DEPOSITAR</code>) a las cuentas del plan contable de la empresa.</p>
                                            <div id="listaConcDetalle" class="mt-2"></div>
                                        </div>
                                        <div class="col-md-4 text-end d-flex flex-column gap-2 justify-content-center">
                                            <a href="empresas_cuentas_conceptos.php" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt me-1"></i>Ir a Conceptos Contables
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 8. ROLES Y CUENTAS CONTABLES -->
                        <div class="accordion-item mb-2 border rounded shadow-xs">
                            <h2 class="accordion-header" id="headingRoles">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseRoles" aria-expanded="false" aria-controls="collapseRoles">
                                    <span class="badge me-2" id="chkBadgeRoles"><i class="fas fa-times"></i></span>
                                    <strong class="me-2">8. Cuentas Contables por Rol de Entidad</strong>
                                    <span class="text-muted small" id="chkResumenRoles">(0 roles seteados)</span>
                                </button>
                            </h2>
                            <div id="collapseRoles" class="accordion-collapse collapse" aria-labelledby="headingRoles" data-bs-parent="#accordionInicio">
                                <div class="accordion-body bg-light-subtle">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <p class="mb-1"><strong>¿Por qué es necesario?</strong> En <code>gestion__empresas_roles_cuentas</code> se define la cuenta por defecto para cada rol: Clientes (Deudores por Ventas), Proveedores (Proveedores Nacionales), Empleados, Organismos y Bancos.</p>
                                            <div id="listaRolesDetalle" class="mt-2"></div>
                                        </div>
                                        <div class="col-md-4 text-end d-flex flex-column gap-2 justify-content-center">
                                            <a href="empresas_roles_cuentas.php" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt me-1"></i>Ir a Roles y Cuentas Contables
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 9. CONDICIONES DE PAGO -->
                        <div class="accordion-item mb-2 border rounded shadow-xs">
                            <h2 class="accordion-header" id="headingCond">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseCond" aria-expanded="false" aria-controls="collapseCond">
                                    <span class="badge me-2" id="chkBadgeCond"><i class="fas fa-times"></i></span>
                                    <strong class="me-2">9. Condiciones de Pago</strong>
                                    <span class="text-muted small" id="chkResumenCond">(0 condiciones)</span>
                                </button>
                            </h2>
                            <div id="collapseCond" class="accordion-collapse collapse" aria-labelledby="headingCond" data-bs-parent="#accordionInicio">
                                <div class="accordion-body bg-light-subtle">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <p class="mb-1"><strong>¿Por qué es necesario?</strong> Determina los plazos de vencimiento y cuotas de facturas y órdenes de compra: Contado, Cuenta Corriente 30 días, etc.</p>
                                            <div id="listaCondDetalle" class="mt-2"></div>
                                        </div>
                                        <div class="col-md-4 text-end d-flex flex-column gap-2 justify-content-center">
                                            <a href="condiciones_pago.php" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt me-1"></i>Ir a Condiciones de Pago
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 10. PARÁMETROS GENERALES DE EMPRESA -->
                        <div class="accordion-item mb-2 border rounded shadow-xs">
                            <h2 class="accordion-header" id="headingParam">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseParam" aria-expanded="false" aria-controls="collapseParam">
                                    <span class="badge me-2" id="chkBadgeParam"><i class="fas fa-times"></i></span>
                                    <strong class="me-2">10. Parámetros Generales y Facturación Fiscal</strong>
                                    <span class="text-muted small" id="chkResumenParam">(Sin parámetros vigentes)</span>
                                </button>
                            </h2>
                            <div id="collapseParam" class="accordion-collapse collapse" aria-labelledby="headingParam" data-bs-parent="#accordionInicio">
                                <div class="accordion-body bg-light-subtle">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <p class="mb-1"><strong>¿Por qué es necesario?</strong> Configura los valores predeterminados para nuevos comprobantes (Punto de Venta por defecto, Lista de Precios por defecto, Condición de Pago por defecto) y habilita la facturación con CAE en ARCA.</p>
                                            <div id="listaParamDetalle" class="mt-2"></div>
                                        </div>
                                        <div class="col-md-4 text-end d-flex flex-column gap-2 justify-content-center">
                                            <a href="empresas_parametros.php" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt me-1"></i>Ir a Parámetros de Empresa
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 11. MONEDA BASE -->
                        <div class="accordion-item mb-2 border rounded shadow-xs">
                            <h2 class="accordion-header" id="headingMon">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMon" aria-expanded="false" aria-controls="collapseMon">
                                    <span class="badge me-2" id="chkBadgeMon"><i class="fas fa-times"></i></span>
                                    <strong class="me-2">11. Moneda base</strong>
                                    <span class="text-muted small" id="chkResumenMon">(Sin moneda base)</span>
                                </button>
                            </h2>
                            <div id="collapseMon" class="accordion-collapse collapse" aria-labelledby="headingMon" data-bs-parent="#accordionInicio">
                                <div class="accordion-body bg-light-subtle">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <p class="mb-1"><strong>¿Por qué es necesario?</strong> La moneda base de la empresa: todos los comprobantes, asientos y cuentas de tesorería la necesitan. Las demás monedas llevan su cotización.</p>
                                            <div id="listaMonDetalle" class="mt-2"></div>
                                        </div>
                                        <div class="col-md-4 text-end d-flex flex-column gap-2 justify-content-center">
                                            <a href="monedas.php" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt me-1"></i>Ir a Monedas
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 12. ALÍCUOTAS DE IVA -->
                        <div class="accordion-item mb-2 border rounded shadow-xs">
                            <h2 class="accordion-header" id="headingIva">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseIva" aria-expanded="false" aria-controls="collapseIva">
                                    <span class="badge me-2" id="chkBadgeIva"><i class="fas fa-times"></i></span>
                                    <strong class="me-2">12. Alícuotas de IVA</strong>
                                    <span class="text-muted small" id="chkResumenIva">(0 alícuotas)</span>
                                </button>
                            </h2>
                            <div id="collapseIva" class="accordion-collapse collapse" aria-labelledby="headingIva" data-bs-parent="#accordionInicio">
                                <div class="accordion-body bg-light-subtle">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <p class="mb-1"><strong>¿Por qué es necesario?</strong> Las alícuotas (21 %, 10,5 %, exento...) con su cuenta contable de débito y crédito fiscal. Sin al menos una gravada no se factura.</p>
                                            <div id="listaIvaDetalle" class="mt-2"></div>
                                        </div>
                                        <div class="col-md-4 text-end d-flex flex-column gap-2 justify-content-center">
                                            <a href="iva_alicuotas.php" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt me-1"></i>Ir a Impuestos IVA
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 13. TIPO DE ASIENTO AUTOMÁTICO (AUT) -->
                        <div class="accordion-item mb-2 border rounded shadow-xs">
                            <h2 class="accordion-header" id="headingAsi">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAsi" aria-expanded="false" aria-controls="collapseAsi">
                                    <span class="badge me-2" id="chkBadgeAsi"><i class="fas fa-times"></i></span>
                                    <strong class="me-2">13. Tipo de asiento automático (AUT)</strong>
                                    <span class="text-muted small" id="chkResumenAsi">(Sin tipo AUT)</span>
                                </button>
                            </h2>
                            <div id="collapseAsi" class="accordion-collapse collapse" aria-labelledby="headingAsi" data-bs-parent="#accordionInicio">
                                <div class="accordion-body bg-light-subtle">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <p class="mb-1"><strong>¿Por qué es necesario?</strong> Todos los asientos automáticos (facturas, recibos, órdenes de pago, tesorería) se registran con el tipo de asiento de código AUT de la empresa.</p>
                                            <div id="listaAsiDetalle" class="mt-2"></div>
                                        </div>
                                        <div class="col-md-4 text-end d-flex flex-column gap-2 justify-content-center">
                                            <a href="cont_tipos_asientos.php" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt me-1"></i>Ir a Tipos de Asientos
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 14. CUENTAS DE TESORERÍA -->
                        <div class="accordion-item mb-2 border rounded shadow-xs">
                            <h2 class="accordion-header" id="headingTes">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseTes" aria-expanded="false" aria-controls="collapseTes">
                                    <span class="badge me-2" id="chkBadgeTes"><i class="fas fa-times"></i></span>
                                    <strong class="me-2">14. Cuentas de Tesorería</strong>
                                    <span class="text-muted small" id="chkResumenTes">(0 cuentas)</span>
                                </button>
                            </h2>
                            <div id="collapseTes" class="accordion-collapse collapse" aria-labelledby="headingTes" data-bs-parent="#accordionInicio">
                                <div class="accordion-body bg-light-subtle">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <p class="mb-1"><strong>¿Por qué es necesario?</strong> Cajas por sucursal, cuentas bancarias y valores en cartera, cada una con su cuenta contable. Sin una caja no se cobra en efectivo; sin valores no se reciben cheques.</p>
                                            <div id="listaTesDetalle" class="mt-2"></div>
                                        </div>
                                        <div class="col-md-4 text-end d-flex flex-column gap-2 justify-content-center">
                                            <a href="tesoreria_cuentas.php" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt me-1"></i>Ir a Cuentas de Tesorería
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 15. MEDIOS DE COBRO Y PAGO -->
                        <div class="accordion-item mb-2 border rounded shadow-xs">
                            <h2 class="accordion-header" id="headingMed">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMed" aria-expanded="false" aria-controls="collapseMed">
                                    <span class="badge me-2" id="chkBadgeMed"><i class="fas fa-times"></i></span>
                                    <strong class="me-2">15. Medios de Cobro y Pago</strong>
                                    <span class="text-muted small" id="chkResumenMed">(0 medios)</span>
                                </button>
                            </h2>
                            <div id="collapseMed" class="accordion-collapse collapse" aria-labelledby="headingMed" data-bs-parent="#accordionInicio">
                                <div class="accordion-body bg-light-subtle">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <p class="mb-1"><strong>¿Por qué es necesario?</strong> Efectivo, transferencia, cheques y retenciones sufridas que se ofrecen en recibos y órdenes de pago. Los de dinero llevan a qué cuenta de tesorería van; las retenciones, su cuenta contable.</p>
                                            <div id="listaMedDetalle" class="mt-2"></div>
                                        </div>
                                        <div class="col-md-4 text-end d-flex flex-column gap-2 justify-content-center">
                                            <a href="medios_pago.php" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt me-1"></i>Ir a Medios de Pago
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 16. COMPROBANTES POR PUNTO DE VENTA -->
                        <div class="accordion-item mb-2 border rounded shadow-xs">
                            <h2 class="accordion-header" id="headingPvComp">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePvComp" aria-expanded="false" aria-controls="collapsePvComp">
                                    <span class="badge me-2" id="chkBadgePvComp"><i class="fas fa-times"></i></span>
                                    <strong class="me-2">16. Comprobantes por Punto de Venta</strong>
                                    <span class="text-muted small" id="chkResumenPvComp">(Sin revisar)</span>
                                </button>
                            </h2>
                            <div id="collapsePvComp" class="accordion-collapse collapse" aria-labelledby="headingPvComp" data-bs-parent="#accordionInicio">
                                <div class="accordion-body bg-light-subtle">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <p class="mb-1"><strong>¿Por qué es necesario?</strong> Qué tipos de comprobante emite cada punto de venta (facturas, notas, recibos, remitos) y su numeración. Un punto de venta sin tipos habilitados no emite nada.</p>
                                            <div id="listaPvCompDetalle" class="mt-2"></div>
                                        </div>
                                        <div class="col-md-4 text-end d-flex flex-column gap-2 justify-content-center">
                                            <a href="puntos_venta.php" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt me-1"></i>Ir a Puntos de Venta
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 17. CONFIGURACIÓN DE IMPUESTOS -->
                        <div class="accordion-item mb-2 border rounded shadow-xs">
                            <h2 class="accordion-header" id="headingImpCfg">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseImpCfg" aria-expanded="false" aria-controls="collapseImpCfg">
                                    <span class="badge me-2" id="chkBadgeImpCfg"><i class="fas fa-times"></i></span>
                                    <strong class="me-2">17. Configuración de Impuestos</strong>
                                    <span class="text-muted small" id="chkResumenImpCfg">(0 configurados)</span>
                                </button>
                            </h2>
                            <div id="collapseImpCfg" class="accordion-collapse collapse" aria-labelledby="headingImpCfg" data-bs-parent="#accordionInicio">
                                <div class="accordion-body bg-light-subtle">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <p class="mb-1"><strong>¿Por qué es necesario?</strong> Parametriza las percepciones y retenciones aplicables a la empresa (Ingresos Brutos por jurisdicción, regímenes especiales, padrones, situaciones fiscales y cuentas contables de imputación).</p>
                                            <div id="listaImpCfgDetalle" class="mt-2"></div>
                                        </div>
                                        <div class="col-md-4 text-end d-flex flex-column gap-2 justify-content-center">
                                            <a href="empresas_impuestos_config.php" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt me-1"></i>Ir a Configuración de Impuestos
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 18. CATÁLOGO DE PRODUCTOS Y SERVICIOS -->
                        <div class="accordion-item mb-2 border rounded shadow-xs">
                            <h2 class="accordion-header" id="headingProd">
                                <button class="accordion-button collapsed py-2" type="button" data-bs-toggle="collapse" data-bs-target="#collapseProd" aria-expanded="false" aria-controls="collapseProd">
                                    <span class="badge me-2" id="chkBadgeProd"><i class="fas fa-times"></i></span>
                                    <strong class="me-2">18. Catálogo de Productos y Servicios</strong>
                                    <span class="text-muted small" id="chkResumenProd">(0 ítems)</span>
                                </button>
                            </h2>
                            <div id="collapseProd" class="accordion-collapse collapse" aria-labelledby="headingProd" data-bs-parent="#accordionInicio">
                                <div class="accordion-body bg-light-subtle">
                                    <div class="row g-3">
                                        <div class="col-md-8">
                                            <p class="mb-1"><strong>¿Por qué es necesario?</strong> Bienes y servicios que la empresa comercializa o adquiere, con su alícuota de IVA, categoría, unidad de medida y cuentas contables asociadas.</p>
                                            <div id="listaProdDetalle" class="mt-2"></div>
                                        </div>
                                        <div class="col-md-4 text-end d-flex flex-column gap-2 justify-content-center">
                                            <a href="productos.php" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-external-link-alt me-1"></i>Ir a Productos y Servicios
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>

                </div>
            </div>

        </div>
    </div>
</main>

<!-- Modal: Previsualización de Copia -->
<div class="modal fade" id="modalPrevisualizacion" tabindex="-1" aria-labelledby="modalPrevisualizacionLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold" id="modalPrevisualizacionLabel">
                    <i class="fas fa-search-plus text-info me-2"></i>Previsualización de Datos a Copiar
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body" id="modalPrevisualizacionBody">
                <div class="text-center py-4 text-muted">
                    <i class="fas fa-spinner fa-spin me-2"></i>Consultando datos de la empresa origen...
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-success fw-bold" id="btnConfirmarCopiaDesdeModal">
                    <i class="fas fa-download me-1"></i>Proceder con la Copia
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Alta de empresa nueva desde la constancia de CUIT (datos leídos con IA, revisados por el usuario) -->
<div class="modal fade" id="modalAltaEmpresa" tabindex="-1" aria-labelledby="modalAltaEmpresaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalAltaEmpresaLabel">
                    <i class="fas fa-building text-primary me-2"></i>Nueva empresa
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="formAltaEmpresa" autocomplete="off" novalidate>
                    <div id="altaAvisos"></div>

                    <div class="ui-seccion">
                        <div class="ui-seccion-titulo">Empresa</div>
                        <div class="row g-2">
                            <div class="col-md-8">
                                <label class="form-label ui-requerido" for="altaRazonSocial">Razón social</label>
                                <input type="text" class="form-control form-control-sm" id="altaRazonSocial" name="razon_social" maxlength="150" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label ui-requerido" for="altaCuit">CUIT</label>
                                <input type="text" class="form-control form-control-sm" id="altaCuit" name="cuit" maxlength="13" inputmode="numeric" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label ui-requerido" for="altaCondicion">Condición frente al IVA</label>
                                <select class="form-select form-select-sm" id="altaCondicion" name="condicion_fiscal_id" required></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label ui-requerido" for="altaTipoOperacion">Tipo de operación
                                    <?= ui_ayuda('Decide qué perfiles predefinidos se crean. En Servicios, los perfiles no muestran pedidos, remitos ni stock.') ?></label>
                                <select class="form-select form-select-sm" id="altaTipoOperacion" name="tipo_operacion" required>
                                    <option value="SERVICIOS">Servicios</option>
                                    <option value="BIENES">Venta de bienes</option>
                                    <option value="MIXTA">Bienes y servicios</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="ui-seccion">
                        <div class="ui-seccion-titulo">Domicilio fiscal</div>
                        <div class="row g-2">
                            <div class="col-12">
                                <label class="form-label" for="altaDomicilio">Domicilio</label>
                                <input type="text" class="form-control form-control-sm" id="altaDomicilio" name="domicilio" maxlength="150">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label ui-requerido" for="altaProvincia">Provincia</label>
                                <select class="form-select form-select-sm" id="altaProvincia" required></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label ui-requerido" for="altaLocalidad">Localidad</label>
                                <select class="form-select form-select-sm" id="altaLocalidad" name="localidad_id" required>
                                    <option value="">Elegí la provincia</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <details class="ui-mas-opciones" open>
                        <summary>Datos fiscales</summary>
                        <div class="row g-2 mt-1">
                            <div class="col-md-6">
                                <label class="form-label" for="altaIibbRegimen">Régimen de Ingresos Brutos
                                    <?= ui_ayuda('La constancia de ARCA no informa Ingresos Brutos: revisalo.') ?></label>
                                <select class="form-select form-select-sm" id="altaIibbRegimen" name="iibb_regimen_id"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="altaJurisdiccionSede">Jurisdicción sede</label>
                                <select class="form-select form-select-sm" id="altaJurisdiccionSede" name="jurisdiccion_sede_id"></select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="altaInicioActividades">Inicio de actividades
                                    <?= ui_ayuda('La constancia no lo imprime: se propone el alta más antigua de los impuestos. Confirmalo.') ?></label>
                                <input type="date" class="form-control form-control-sm" id="altaInicioActividades" name="inicio_actividades">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="altaMesCierre">Mes de cierre del ejercicio</label>
                                <select class="form-select form-select-sm" id="altaMesCierre" name="mes_cierre_ejercicio"></select>
                            </div>
                        </div>
                    </details>

                    <?php if ($empresa_id_activa > 0 && $empresaActivaNombre !== ''): ?>
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" id="altaCopiarDesdeActual" name="copiar_desde_actual" value="1" checked>
                        <label class="form-check-label" for="altaCopiarDesdeActual">
                            Copiar la configuración de <strong><?= htmlspecialchars($empresaActivaNombre) ?></strong>
                            <?= ui_ayuda('Esta empresa autoriza a la nueva a copiar su configuración. La copia se hace después, en el asistente de la empresa nueva, con previsualización.') ?>
                        </label>
                    </div>
                    <?php endif; ?>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary me-auto" id="btnReleerConstancia">
                    <i class="fas fa-file-import me-1"></i>Leer otra constancia
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary fw-bold" id="btnGuardarAltaEmpresa">
                    <i class="fas fa-check me-1"></i>Crear empresa
                </button>
            </div>
        </div>
    </div>
</div>

<!-- SweetAlert2 -->
<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script>
    const EMPRESA_ACTIVA_ID = <?= (int)$empresa_id_activa ?>;
    const ORIGEN_ID_URL = <?= (int)$origen_id_url ?>;
</script>
<script src="<?= asset_local('js/constancia-cuit-ia.js') ?>?v=<?= filemtime(ROOT_PATH . '/assets/js/constancia-cuit-ia.js') ?>"></script>
<script src="empresa_inicio.js?v=<?= filemtime(__DIR__ . '/empresa_inicio.js') ?>"></script>


<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
