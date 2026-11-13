<?php
/**
 * Vista: Panel de Auditoría y Logs del Sistema
 * Módulo: Conf (Configuración y Seguridad)
 * Archivo: modules/conf/auditoria.php
 */

$pageTitle = "Logs de Auditoría";
$currentPage = 'auditoria';
$modudo_idx = 1;
$pagina_idx = 142; // conf__paginas (20261111_auditoria_pagina_01.sql)
$ui_simple = true;

define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
require_once __DIR__ . '/auditoria_model.php';

$aniosDisponibles = audModelObtenerAnios($conexion);
$anioActual = (int)date('Y');
?>

<style>
    .audit-kpi-card {
        border-radius: 10px;
        transition: transform 0.15s ease, box-shadow 0.15s ease;
    }
    .audit-kpi-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    }
    .json-box {
        background-color: #1e1e1e;
        color: #d4d4d4;
        font-family: SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
        font-size: 0.82rem;
        padding: 12px;
        border-radius: 6px;
        max-height: 280px;
        overflow-y: auto;
        white-space: pre-wrap;
        word-break: break-all;
    }
    .badge-accion {
        font-size: 0.75rem;
        letter-spacing: 0.3px;
        font-weight: 600;
        padding: 5px 8px;
    }
    .timeline-item {
        border-left: 2px solid #0d6efd;
        padding-left: 18px;
        position: relative;
        margin-bottom: 20px;
    }
    .timeline-item::before {
        content: '';
        position: absolute;
        left: -7px;
        top: 2px;
        width: 12px;
        height: 12px;
        border-radius: 50%;
        background-color: #0d6efd;
        border: 2px solid #fff;
    }
    .timeline-item:last-child {
        margin-bottom: 0;
    }
</style>

<main class="app-main">
    <?php ui_encabezado(
        'Logs de Auditoría',
        'bi bi-shield-check',
        'Registro y trazabilidad de accesos, operaciones y eventos de seguridad organizados por año.',
        ['Seguridad y Auditoría'],
        '<div class="d-flex align-items-center gap-2">
            <label class="form-label mb-0 fw-semibold text-secondary small" for="selectAnioAuditoria">Año de Auditoría:</label>
            <select class="form-select form-select-sm fw-bold border-primary shadow-sm" id="selectAnioAuditoria" style="width: 120px;">'
                . implode('', array_map(function($a) use ($anioActual) {
                    $sel = ($a === $anioActual) ? ' selected' : '';
                    return "<option value='{$a}'{$sel}>{$a}</option>";
                }, $aniosDisponibles))
            . '</select>
            <button class="btn btn-sm btn-outline-secondary" id="btnRecargarLogs" title="Actualizar datos"><i class="fas fa-sync-alt"></i></button>
        </div>'
    ); ?>

    <div class="app-content">
        <div class="container-fluid">

            <!-- KPIs Rápidos del Año Seleccionado -->
            <div class="row g-3 mb-4">
                <div class="col-6 col-md-3">
                    <div class="card audit-kpi-card border-0 shadow-sm bg-white">
                        <div class="card-body p-3 d-flex align-items-center">
                            <div class="rounded-3 p-3 bg-primary bg-opacity-10 text-primary me-3">
                                <i class="bi bi-activity fs-3"></i>
                            </div>
                            <div>
                                <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">Total Eventos</small>
                                <h4 class="mb-0 fw-bold" id="kpiTotal">0</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card audit-kpi-card border-0 shadow-sm bg-white">
                        <div class="card-body p-3 d-flex align-items-center">
                            <div class="rounded-3 p-3 bg-success bg-opacity-10 text-success me-3">
                                <i class="bi bi-box-arrow-in-right fs-3"></i>
                            </div>
                            <div>
                                <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">Inicios Correctos</small>
                                <h4 class="mb-0 fw-bold text-success" id="kpiLoginsOk">0</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card audit-kpi-card border-0 shadow-sm bg-white">
                        <div class="card-body p-3 d-flex align-items-center">
                            <div class="rounded-3 p-3 bg-danger bg-opacity-10 text-danger me-3">
                                <i class="bi bi-shield-x fs-3"></i>
                            </div>
                            <div>
                                <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">Inicios Fallidos</small>
                                <h4 class="mb-0 fw-bold text-danger" id="kpiLoginsFail">0</h4>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card audit-kpi-card border-0 shadow-sm bg-white">
                        <div class="card-body p-3 d-flex align-items-center">
                            <div class="rounded-3 p-3 bg-warning bg-opacity-10 text-warning me-3">
                                <i class="bi bi-shield-exclamation fs-3"></i>
                            </div>
                            <div>
                                <small class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem;">Alertas Seguridad</small>
                                <h4 class="mb-0 fw-bold text-warning" id="kpiSeguridad">0</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Panel de Filtros -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white py-3">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-funnel me-2 text-primary"></i>Filtros de Búsqueda</h6>
                </div>
                <div class="card-body">
                    <form id="formFiltrosAuditoria" class="row g-3">
                        <div class="col-12 col-md-3">
                            <label class="form-label small fw-semibold" for="filtroFechaDesde">Fecha Desde</label>
                            <input type="date" class="form-control form-control-sm" id="filtroFechaDesde">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small fw-semibold" for="filtroFechaHasta">Fecha Hasta</label>
                            <input type="date" class="form-control form-control-sm" id="filtroFechaHasta">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small fw-semibold" for="filtroAccion">Tipo de Acción</label>
                            <select class="form-select form-select-sm" id="filtroAccion">
                                <option value="">Todas las acciones</option>
                                <option value="LOGIN_EXITOSO">Inicio de sesión correcto</option>
                                <option value="LOGIN_FALLIDO">Inicio de sesión fallido</option>
                                <option value="LOGOUT">Cierre de sesión</option>
                                <option value="CAMBIO_CONTEXTO">Cambio de Empresa/Módulo</option>
                                <option value="SEGURIDAD_ACCESO_DENEGADO">Acceso denegado</option>
                                <option value="SEGURIDAD_CSRF_INVALIDO">Alerta CSRF</option>
                                <option value="ALTA">Alta de registro</option>
                                <option value="MODIFICACION">Modificación</option>
                                <option value="BAJA">Eliminación / Inhabilitación</option>
                                <option value="APROBAR">Aprobación / Autorización</option>
                                <option value="ANULAR">Anulación</option>
                                <option value="FACTURAR">Facturación</option>
                                <option value="COBRAR">Cobro</option>
                                <option value="PAGAR">Pago</option>
                                <option value="IMPORTAR">Importación de datos</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label small fw-semibold" for="filtroUsuario">Usuario</label>
                            <select class="form-select form-select-sm" id="filtroUsuario">
                                <option value="">Todos los usuarios</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label small fw-semibold" for="filtroEmpresa">Empresa</label>
                            <select class="form-select form-select-sm" id="filtroEmpresa">
                                <option value="">Todas las empresas</option>
                            </select>
                        </div>
                        <div class="col-12 col-md-5">
                            <label class="form-label small fw-semibold" for="filtroBusqueda">Búsqueda libre</label>
                            <input type="text" class="form-control form-control-sm" id="filtroBusqueda" placeholder="Buscar por IP, descripción, tabla afectada...">
                        </div>
                        <div class="col-12 col-md-3 d-flex align-items-end gap-2">
                            <button type="submit" class="btn btn-sm btn-primary flex-grow-1"><i class="bi bi-search me-1"></i>Filtrar</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimpiarFiltros" title="Limpiar filtros"><i class="bi bi-eraser"></i></button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tabla de Registros -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-list-check me-2 text-primary"></i>Registros de Auditoría</h6>
                    <span class="badge bg-light text-secondary border px-3 py-2 font-monospace" id="badgeAnioActual">Año: <?= $anioActual ?></span>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="tablaAuditoria" class="table table-hover table-striped align-middle table-sm" style="width: 100%;">
                            <thead class="table-light">
                                <tr>
                                    <th># ID</th>
                                    <th>Fecha / Hora</th>
                                    <th>Usuario</th>
                                    <th>Empresa</th>
                                    <th>Módulo</th>
                                    <th>Acción</th>
                                    <th>Descripción</th>
                                    <th>IP Origen</th>
                                    <th class="text-center">Detalle</th>
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

<!-- Modal: Inspector de Registro de Auditoría (Forense & Payload) -->
<div class="modal fade" id="modalDetalleAuditoria" tabindex="-1" aria-labelledby="modalDetalleAuditoriaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white py-2">
                <h6 class="modal-title fw-bold" id="modalDetalleAuditoriaLabel"><i class="bi bi-shield-shaded me-2 text-info"></i>Detalle de Evento de Auditoría</h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <div class="row g-3 mb-3">
                    <div class="col-6 col-md-4">
                        <small class="text-muted d-block">ID de Log:</small>
                        <span class="fw-bold font-monospace" id="detLogId">-</span>
                    </div>
                    <div class="col-6 col-md-4">
                        <small class="text-muted d-block">Fecha y Hora:</small>
                        <span class="fw-bold" id="detFecha">-</span>
                    </div>
                    <div class="col-6 col-md-4">
                        <small class="text-muted d-block">Acción:</small>
                        <span id="detAccion">-</span>
                    </div>
                    <div class="col-6 col-md-4">
                        <small class="text-muted d-block">Usuario:</small>
                        <span class="fw-bold" id="detUsuario">-</span>
                    </div>
                    <div class="col-6 col-md-4">
                        <small class="text-muted d-block">Empresa:</small>
                        <span class="fw-bold" id="detEmpresa">-</span>
                    </div>
                    <div class="col-6 col-md-4">
                        <small class="text-muted d-block">Módulo / Página:</small>
                        <span id="detModuloPagina">-</span>
                    </div>
                    <div class="col-6 col-md-4">
                        <small class="text-muted d-block">Dirección IP:</small>
                        <span class="font-monospace text-primary fw-bold" id="detIp">-</span>
                    </div>
                    <div class="col-6 col-md-4">
                        <small class="text-muted d-block">Método / Solicitud:</small>
                        <span class="badge bg-secondary font-monospace" id="detMetodo">-</span>
                    </div>
                    <div class="col-6 col-md-4">
                        <small class="text-muted d-block">Tabla Afectada:</small>
                        <div class="d-flex align-items-center gap-1">
                            <span class="badge bg-light text-dark border font-monospace" id="detTabla">-</span>
                            <span class="badge bg-secondary font-monospace" id="detTablaId" title="ID de Tabla en catálogo conf__tablas">-</span>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <small class="text-muted d-block" id="detLblRegistroId">ID de Registro (Afectado / Creado):</small>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-primary font-monospace fs-6" id="detRegistroId">-</span>
                            <button type="button" class="btn btn-xs btn-outline-info py-0 px-2" id="btnVerHistoriaRegistro" style="display: none;" title="Ver todos los cambios históricos de este registro">
                                <i class="bi bi-clock-history me-1"></i>Ver Historia
                            </button>
                        </div>
                    </div>
                    <div class="col-12">
                        <small class="text-muted d-block">URL Solicitada:</small>
                        <code class="p-1 rounded bg-light border d-block text-break" id="detUrl">-</code>
                    </div>
                    <div class="col-12">
                        <small class="text-muted d-block">Navegador / Cliente (User Agent):</small>
                        <span class="small text-secondary font-monospace text-break d-block" id="detUserAgent">-</span>
                    </div>
                    <div class="col-12">
                        <small class="text-muted d-block">Descripción:</small>
                        <div class="p-2 rounded bg-light border" id="detDescripcion">-</div>
                    </div>
                </div>

                <!-- Bloque de Tablas Involucradas en el Proceso -->
                <div class="row mb-3" id="bloqueTablasInvolucradas" style="display: none;">
                    <div class="col-12">
                        <div class="card border border-info border-opacity-25 bg-info bg-opacity-10 shadow-none">
                            <div class="card-header bg-transparent py-2 border-info border-opacity-25 d-flex align-items-center justify-content-between">
                                <span class="small fw-bold text-dark"><i class="bi bi-diagram-3 me-2 text-info"></i>Todas las Tablas y Entidades Involucradas en este Proceso</span>
                                <span class="badge bg-info text-dark" id="badgeTotalTablasInvolucradas">0 tablas</span>
                            </div>
                            <div class="card-body p-2">
                                <div class="table-responsive">
                                    <table class="table table-sm table-hover align-middle mb-0 bg-white rounded border">
                                        <thead class="table-light small">
                                            <tr>
                                                <th>Tabla / Catálogo</th>
                                                <th>Rol / Entidad</th>
                                                <th>ID Registro</th>
                                                <th>Acción</th>
                                                <th class="text-end">Trazabilidad</th>
                                            </tr>
                                        </thead>
                                        <tbody id="tbodyTablasInvolucradas" class="small">
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Bloques de Datos Antes y Después (JSON Diff) -->
                <div class="row g-3" id="bloquePayloads" style="display: none;">
                    <div class="col-12 col-md-6" id="colDatosAntes">
                        <label class="form-label small fw-bold text-danger"><i class="bi bi-dash-circle me-1"></i>Datos Anteriores (Antes del cambio)</label>
                        <pre class="json-box" id="detDatosAntes">{}</pre>
                    </div>
                    <div class="col-12 col-md-6" id="colDatosDespues">
                        <label class="form-label small fw-bold text-success"><i class="bi bi-plus-circle me-1"></i>Datos Nuevos (Después del cambio)</label>
                        <pre class="json-box" id="detDatosDespues">{}</pre>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light py-2 justify-content-between">
                <button type="button" class="btn btn-sm btn-outline-info" id="btnVerHistoriaRegistroFooter" style="display: none;">
                    <i class="bi bi-clock-history me-1"></i>Ver Historia Completa de este Registro
                </button>
                <button type="button" class="btn btn-sm btn-secondary ms-auto" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Historia del Registro (Timeline Completo) -->
<div class="modal fade" id="modalHistoriaRegistro" tabindex="-1" aria-labelledby="modalHistoriaRegistroLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white py-2">
                <h6 class="modal-title fw-bold" id="modalHistoriaRegistroLabel">
                    <i class="bi bi-clock-history me-2"></i>Historial de Cambios del Registro
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <!-- Tarjeta Resumen de la Entidad -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-body p-3 d-flex flex-wrap align-items-center justify-content-between gap-3">
                        <div>
                            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">Tabla / Entidad</small>
                            <span class="fw-bold font-monospace text-primary fs-6" id="historiaTablaNombre">-</span>
                            <span class="badge bg-secondary font-monospace ms-1" id="historiaTablaId" title="ID de Tabla en conf__tablas">-</span>
                        </div>
                        <div>
                            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">ID de Registro</small>
                            <span class="badge bg-primary font-monospace fs-6" id="historiaRegistroId">-</span>
                        </div>
                        <div>
                            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 0.72rem;">Total de Modificaciones</small>
                            <span class="badge bg-dark fs-6" id="historiaTotalEventos">0</span>
                        </div>
                        <div class="form-check form-switch pt-2">
                            <input class="form-check-input" type="checkbox" id="checkTodosAniosHistoria" checked>
                            <label class="form-check-label small fw-semibold" for="checkTodosAniosHistoria">Todos los años</label>
                        </div>
                    </div>
                </div>

                <!-- Contenedor del Timeline de Cambios -->
                <div id="contenedorTimelineHistoria">
                    <!-- Se puebla dinámicamente -->
                </div>
            </div>
            <div class="modal-footer bg-white py-2">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cerrar Historial</button>
            </div>
        </div>
    </div>
</div>

<script src="<?= asset_local('js/sweetalert2-11.26.25/sweetalert2.all.min.js') ?>"></script>
<script src="auditoria.js?v=<?= filemtime(__DIR__ . '/auditoria.js') ?>"></script>

<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>

