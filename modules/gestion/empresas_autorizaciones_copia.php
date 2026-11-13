<?php
/**
 * Vista: ABM de Autorizaciones de Acceso a Información Básica Interempresa
 * Módulo: Gestión - Seteo Empresas (padre_id = 37, orden = 402)
 * Página ID: 122
 */

$pageTitle = "Autorizaciones de Acceso a Información Básica";
$currentPage = 'empresas_autorizaciones_copia';
$modudo_idx = 2;
$pagina_idx = 122;
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

// Empresa activa: la resuelve y valida header1.php (GET empresa_id -> sesión).
$empresa_id_activa = (int) ($current_empresa_id ?? 0);

// 2. Cargar todas las empresas activas para el selector y el modal
$todasLasEmpresas = [];
$resEmp = $conn->query("SELECT empresa_id, empresa, documento_numero, domicilio, email FROM conf__empresas WHERE tabla_estado_registro_id = 1 ORDER BY empresa ASC");
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

$empresa_activa_nombre = "Empresa ID: $empresa_id_activa";
foreach ($todasLasEmpresas as $e) {
    if ($e['empresa_id'] == $empresa_id_activa) {
        $empresa_activa_nombre = $e['empresa'] . ($e['documento_numero'] ? " (CUIT: " . $e['documento_numero'] . ")" : "");
        break;
    }
}
?>

<main class="app-main">
    <!-- Header -->
    <?php ui_encabezado('Autorizaciones de copia','fas fa-shield-alt', 'Concede o revoca permisos a otras empresas para copiar planes de cuentas, catálogos y configuraciones iniciales', ['Seteo Empresas']); ?>

    <!-- Contenido -->
    <div class="app-content">
        <div class="container-fluid">
            
            <!-- Barra superior: Selector de Empresa y Accesos Directos -->
            <div class="card card-outline card-primary mb-3 shadow-sm">
                <div class="card-body py-2">
                    <div class="row align-items-center g-2">
                        <div class="col-auto">
                            <label for="selectEmpresaFiltro" class="col-form-label fw-bold">
                                <i class="fas fa-building me-1 text-primary"></i>Empresa Activa:
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
                            <a href="empresa_inicio.php" class="btn btn-sm btn-outline-info" title="Ir al Asistente de Inicio de Empresa">
                                <i class="fas fa-rocket me-1"></i>Asistente de Inicio
                            </a>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargar" title="Actualizar datos">
                                <i class="fas fa-sync-alt me-1"></i>Actualizar
                            </button>
                            <button type="button" class="btn btn-sm btn-primary" id="btnNuevaAutorizacion" data-funcion="agregar" data-bs-toggle="modal" data-bs-target="#modalAutorizacion">
                                <i class="fas fa-plus-circle me-1"></i>Otorgar Nueva Autorización
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tarjetas de Resumen / KPIs -->
            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="card bg-primary-subtle border-0 shadow-sm">
                        <div class="card-body py-3 d-flex align-items-center">
                            <div class="rounded-circle bg-primary text-white p-3 me-3">
                                <i class="fas fa-share-alt fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-0 fw-normal">Autorizaciones Otorgadas</h6>
                                <h3 class="fw-bold mb-0 text-primary" id="kpiOtorgadas">0</h3>
                                <small class="text-muted" id="kpiOtorgadasActivas">0 activas</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-success-subtle border-0 shadow-sm">
                        <div class="card-body py-3 d-flex align-items-center">
                            <div class="rounded-circle bg-success text-white p-3 me-3">
                                <i class="fas fa-handshake fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-0 fw-normal">Autorizaciones Recibidas</h6>
                                <h3 class="fw-bold mb-0 text-success" id="kpiRecibidas">0</h3>
                                <small class="text-muted" id="kpiRecibidasActivas">0 activas</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card bg-info-subtle border-0 shadow-sm">
                        <div class="card-body py-3 d-flex align-items-center">
                            <div class="rounded-circle bg-info text-white p-3 me-3">
                                <i class="fas fa-history fa-lg"></i>
                            </div>
                            <div>
                                <h6 class="text-muted mb-0 fw-normal">Copias Registradas</h6>
                                <h3 class="fw-bold mb-0 text-info" id="kpiCopias">0</h3>
                                <?= ui_ayuda('En el historial de auditoría') ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pestañas de Navegación -->
            <div class="card card-outline card-secondary shadow-sm mb-4">
                <div class="card-header p-2 bg-light">
                    <ul class="nav nav-pills" id="tabsAutorizaciones" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-bold" id="tab-otorgadas-btn" data-bs-toggle="pill" data-bs-target="#tab-otorgadas" type="button" role="tab">
                                <i class="fas fa-arrow-up-right-from-square me-1"></i>Permisos Otorgados a Otras Empresas
                                <span class="badge bg-primary ms-1" id="badgeCountOtorgadas">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold" id="tab-recibidas-btn" data-bs-toggle="pill" data-bs-target="#tab-recibidas" type="button" role="tab">
                                <i class="fas fa-arrow-down-left-and-up-right-to-center me-1"></i>Permisos Recibidos de Otras Empresas
                                <span class="badge bg-success ms-1" id="badgeCountRecibidas">0</span>
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold" id="tab-log-btn" data-bs-toggle="pill" data-bs-target="#tab-log" type="button" role="tab">
                                <i class="fas fa-clipboard-list me-1"></i>Auditoría de Copias Realizadas
                            </button>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-3">
                    <div class="tab-content" id="tabsAutorizacionesContent">
                        
                        <!-- TAB 1: OTORGADAS -->
                        <div class="tab-pane fade show active" id="tab-otorgadas" role="tabpanel">
                            <div class="table-responsive">
                                <table id="tablaOtorgadas" class="table table-hover table-striped table-bordered align-middle w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Empresa Destino Autorizada</th>
                                            <th>CUIT</th>
                                            <th>Componentes Autorizados</th>
                                            <th class="text-center" width="130">Vigencia</th>
                                            <th>Motivo / Justificación</th>
                                            <th class="text-center" width="100">Estado</th>
                                            <th class="text-center" width="130">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyOtorgadas">
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <i class="fas fa-spinner fa-spin me-2"></i>Cargando autorizaciones otorgadas...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB 2: RECIBIDAS -->
                        <div class="tab-pane fade" id="tab-recibidas" role="tabpanel">
                            <div class="alert alert-info py-2 mb-3">
                                <i class="fas fa-info-circle me-1"></i>Estas son las empresas que han autorizado a la empresa activa actual para copiar sus datos básicos iniciales. Podés utilizar estos permisos desde el <a href="empresa_inicio.php?empresa_id=<?= $empresa_id_activa ?>" class="alert-link fw-bold">Asistente de Inicio de Empresa</a>.
                            </div>
                            <div class="table-responsive">
                                <table id="tablaRecibidas" class="table table-hover table-striped table-bordered align-middle w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Empresa Propietaria (Origen)</th>
                                            <th>CUIT</th>
                                            <th>Componentes Habilitados para Copiar</th>
                                            <th class="text-center" width="130">Vigencia</th>
                                            <th>Motivo Registrado</th>
                                            <th class="text-center" width="100">Estado</th>
                                            <th class="text-center" width="130">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyRecibidas">
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <i class="fas fa-spinner fa-spin me-2"></i>Cargando autorizaciones recibidas...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- TAB 3: AUDITORIA / LOG -->
                        <div class="tab-pane fade" id="tab-log" role="tabpanel">
                            <div class="table-responsive">
                                <table id="tablaLog" class="table table-hover table-striped table-bordered align-middle w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="80" class="text-center">ID</th>
                                            <th width="150">Fecha y Hora</th>
                                            <th>Empresa Origen</th>
                                            <th>Empresa Destino</th>
                                            <th>Componentes Copiados</th>
                                            <th class="text-center" width="110">Total Reg.</th>
                                            <th>Detalle de Registros Creados</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyLog">
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <i class="fas fa-spinner fa-spin me-2"></i>Cargando historial de copias...
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>
    </div>
</main>

<!-- Modal: Otorgar / Editar Autorización -->
<div class="modal fade" id="modalAutorizacion" tabindex="-1" aria-labelledby="modalAutorizacionLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <form id="formAutorizacion">
                <input type="hidden" id="aut_id" name="autorizacion_id" value="0">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold" id="modalAutorizacionLabel">
                        <i class="fas fa-key text-primary me-2"></i>Otorgar Autorización a Otra Empresa
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    
                    <div class="alert alert-light border py-2 mb-3">
                        <i class="fas fa-building text-primary me-1"></i>Empresa Otorgante: 
                        <strong id="modalEmpresaOrigenNombre"><?= htmlspecialchars($empresa_activa_nombre) ?></strong>
                    </div>

                    <!-- Empresa Destino -->
                    <div class="mb-3">
                        <label for="aut_empresa_destino_id" class="form-label fw-bold">
                            Empresa Destino a Autorizar <span class="text-danger">*</span>
                        </label>
                        <select class="form-select" id="aut_empresa_destino_id" name="empresa_destino_id" required>
                            <option value="">Seleccione la empresa destino...</option>
                            <?php foreach ($todasLasEmpresas as $emp): ?>
                                <?php if ($emp['empresa_id'] != $empresa_id_activa): ?>
                                    <option value="<?= $emp['empresa_id'] ?>">
                                        <?= htmlspecialchars($emp['empresa']) ?> (ID: <?= $emp['empresa_id'] ?><?= $emp['documento_numero'] ? ' - CUIT: ' . $emp['documento_numero'] : '' ?>)
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">Esta empresa podrá leer y copiar los catálogos y parámetros básicos seleccionados abajo.</div>
                    </div>

                    <!-- Componentes Autorizados -->
                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-bold mb-0">Componentes de Información Autorizados <span class="text-danger">*</span></label>
                            <div>
                                <button type="button" class="btn btn-xs btn-outline-primary me-1" id="btnSelTodosComp">
                                    <i class="fas fa-check-double me-1"></i>Todos
                                </button>
                                <button type="button" class="btn btn-xs btn-outline-secondary" id="btnDeselTodosComp">
                                    <i class="fas fa-times me-1"></i>Ninguno
                                </button>
                            </div>
                        </div>
                        
                        <div class="border rounded p-3 bg-light">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input comp-chk" type="checkbox" id="chk_plan_cuentas" name="permite_plan_cuentas" value="1" checked>
                                        <label class="form-check-label fw-semibold" for="chk_plan_cuentas">
                                            <i class="fas fa-sitemap text-primary me-1"></i>Plan de Cuentas Contables
                                        </label>
                                        <small class="d-block text-muted">Árbol de cuentas contables e imputables</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input comp-chk" type="checkbox" id="chk_conceptos_contables" name="permite_conceptos_contables" value="1" checked>
                                        <label class="form-check-label fw-semibold" for="chk_conceptos_contables">
                                            <i class="fas fa-tags text-info me-1"></i>Conceptos Contables Operativos
                                        </label>
                                        <small class="d-block text-muted">Cuentas por defecto para IVA, Valores, etc.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input comp-chk" type="checkbox" id="chk_roles_cuentas" name="permite_roles_cuentas" value="1" checked>
                                        <label class="form-check-label fw-semibold" for="chk_roles_cuentas">
                                            <i class="fas fa-users-cog text-success me-1"></i>Cuentas por Rol de Entidad
                                        </label>
                                        <small class="d-block text-muted">Cuentas para Clientes, Proveedores, etc.</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input comp-chk" type="checkbox" id="chk_comprobantes_tipos" name="permite_comprobantes_tipos" value="1" checked>
                                        <label class="form-check-label fw-semibold" for="chk_comprobantes_tipos">
                                            <i class="fas fa-file-invoice text-warning me-1"></i>Tipos de Comprobantes
                                        </label>
                                        <small class="d-block text-muted">Facturas, Remitos, Recibos y signos de stock</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input comp-chk" type="checkbox" id="chk_condiciones_pago" name="permite_condiciones_pago" value="1" checked>
                                        <label class="form-check-label fw-semibold" for="chk_condiciones_pago">
                                            <i class="fas fa-credit-card text-danger me-1"></i>Condiciones de Pago
                                        </label>
                                        <small class="d-block text-muted">Contado, Cuenta Corriente, Cuotas y plazos</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input comp-chk" type="checkbox" id="chk_sucursales_bocas" name="permite_sucursales_bocas" value="1" checked>
                                        <label class="form-check-label fw-semibold" for="chk_sucursales_bocas">
                                            <i class="fas fa-store text-secondary me-1"></i>Sucursales y Bocas Base
                                        </label>
                                        <small class="d-block text-muted">Estructura física y depósitos modelo</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input comp-chk" type="checkbox" id="chk_listas_precios" name="permite_listas_precios" value="1" checked>
                                        <label class="form-check-label fw-semibold" for="chk_listas_precios">
                                            <i class="fas fa-list-ol text-primary me-1"></i>Listas de Precios Base
                                        </label>
                                        <small class="d-block text-muted">Estructura base de listas de precios</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input comp-chk" type="checkbox" id="chk_parametros" name="permite_parametros" value="1" checked>
                                        <label class="form-check-label fw-semibold" for="chk_parametros">
                                            <i class="fas fa-sliders-h text-dark me-1"></i>Parámetros Generales
                                        </label>
                                        <small class="d-block text-muted">Reglas comerciales y defaults de emisión</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input comp-chk" type="checkbox" id="chk_datos_basicos" name="permite_datos_basicos" value="1" checked>
                                        <label class="form-check-label fw-semibold" for="chk_datos_basicos">
                                            <i class="fas fa-database text-success me-1"></i>Datos Básicos
                                        </label>
                                        <small class="d-block text-muted">Monedas, tipos de asiento, alícuotas de IVA, tipos y categorías de producto, categorías de gasto</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input comp-chk" type="checkbox" id="chk_tesoreria" name="permite_tesoreria" value="1" checked>
                                        <label class="form-check-label fw-semibold" for="chk_tesoreria">
                                            <i class="fas fa-cash-register text-info me-1"></i>Tesorería
                                        </label>
                                        <small class="d-block text-muted">Cajas, valores y cupones (sin saldo ni cuentas bancarias) y medios de cobro y pago</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input comp-chk" type="checkbox" id="chk_impuestos_config" name="permite_impuestos_config" value="1" checked>
                                        <label class="form-check-label fw-semibold" for="chk_impuestos_config">
                                            <i class="fas fa-percent text-warning me-1"></i>Configuración de Impuestos
                                        </label>
                                        <small class="d-block text-muted">Reglas de IIBB, percepciones, retenciones, padrones y alícuotas</small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input comp-chk" type="checkbox" id="chk_productos" name="permite_productos" value="1" checked>
                                        <label class="form-check-label fw-semibold" for="chk_productos">
                                            <i class="fas fa-boxes text-primary me-1"></i>Catálogo de Productos y Servicios
                                        </label>
                                        <small class="d-block text-muted">Productos y servicios con sus tipos, categorías y cuentas</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vigencia y Estado -->
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label for="aut_fecha_desde" class="form-label fw-bold">Fecha Desde <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="aut_fecha_desde" name="fecha_desde" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label for="aut_fecha_hasta" class="form-label fw-bold">Fecha Límite <?= ui_ayuda('Vacio = sin caducidad') ?></label>
                            <input type="date" class="form-control" id="aut_fecha_hasta" name="fecha_hasta">
                        </div>
                        <div class="col-md-4">
                            <label for="aut_tabla_estado_registro_id" class="form-label fw-bold">Estado</label>
                            <select class="form-select" id="aut_tabla_estado_registro_id" name="tabla_estado_registro_id">
                                <option value="1">Activa / Autorizada</option>
                                <option value="2">Inactiva / Revocada</option>
                            </select>
                        </div>
                    </div>

                    <!-- Motivo -->
                    <div class="mb-3">
                        <label for="aut_motivo" class="form-label fw-bold">Motivo o Justificación</label>
                        <input type="text" class="form-control" id="aut_motivo" name="motivo" placeholder="Ej: Alta de sucursal/empresa del grupo, clonación inicial">
                    </div>

                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancelar
                    </button>
                    <button type="submit" class="btn btn-primary" id="btnGuardarAutorizacion">
                        <i class="fas fa-save me-1"></i>Guardar Autorización
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const EMPRESA_ACTIVA_ID = <?= $empresa_id_activa ?>;
    const TODAS_EMPRESAS = <?= json_encode($todasLasEmpresas) ?>;
</script>
<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="empresas_autorizaciones_copia.js?v=<?= filemtime(__DIR__ . '/empresas_autorizaciones_copia.js') ?>"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
