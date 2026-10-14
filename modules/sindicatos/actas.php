<?php
/**
 * modules/sindicatos/actas.php
 * Vista de Actas de Inspección Sindical (Página 1041).
 */

declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Actas de Inspección y Fiscalización";
$currentPage = 'actas';
$modudo_idx = 6;
$pagina_idx = 1041;

define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-file-contract me-2 text-primary"></i>Actas de Inspección
                    </h3>
                    <small class="text-muted">Apertura de inspecciones, determinación de deuda por concepto e intereses resarcitorios</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Sindicatos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Actas</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Filtros Superiores -->
            <div class="row mb-3">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-body py-3">
                            <div class="row g-3 align-items-center">
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold text-muted mb-1">Filtrar por Empleador</label>
                                    <select class="form-select form-select-sm" id="filtroEmpleador">
                                        <option value="">-- Todos los empleadores --</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold text-muted mb-1">Estado</label>
                                    <select class="form-select form-select-sm" id="filtroEstado">
                                        <option value="">-- Todos los estados --</option>
                                        <option value="3">Borrador</option>
                                        <option value="18">En Curso</option>
                                        <option value="5">Confirmado</option>
                                        <option value="40">Cancelado Total</option>
                                        <option value="38">Anulado</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold text-muted mb-1">Buscar Número o Razón Social</label>
                                    <input type="text" class="form-control form-control-sm" id="filtroBusqueda" placeholder="Ej: ACT-2026-..., Comercio">
                                </div>
                                <div class="col-md-2 text-md-end mt-md-4">
                                    <button type="button" class="btn btn-primary btn-sm" id="btnNuevaActa">
                                        <i class="fas fa-plus-circle me-1"></i> Nueva Acta
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm ms-1" id="btnRecargar" title="Recargar lista">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla de Actas -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0" id="tablaActas">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Número</th>
                                            <th>Empleador / CUIT</th>
                                            <th>Tipo</th>
                                            <th>F. Inicio</th>
                                            <th>Inspector</th>
                                            <th class="text-end">Diferencia Cap.</th>
                                            <th class="text-end">Intereses</th>
                                            <th class="text-end">Total Exigible</th>
                                            <th class="text-center">Estado</th>
                                            <th class="text-end" style="min-width: 150px;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyActas">
                                        <tr>
                                            <td colspan="10" class="text-center py-5 text-muted">
                                                <i class="fas fa-spinner fa-spin fa-2x mb-2 text-primary"></i><br>Cargando actas de inspección...
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
<!-- Modal Nueva Acta -->
<div class="modal fade" id="modalNuevaActa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fas fa-file-contract me-2"></i>Nueva Acta de Inspección
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <form id="formNuevaActa">
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-8">
                            <label class="form-label small fw-semibold">Empleador *</label>
                            <select class="form-select form-select-sm" id="nuevoEmpleadorId" name="empleador_id" required>
                                <option value="">-- Seleccionar empleador --</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">Tipo de Acta</label>
                            <select class="form-select form-select-sm" id="nuevoTipoActa" name="tipo_acta">
                                <option value="NORMAL">Normal</option>
                                <option value="LIBRE_DEUDA">Libre Deuda</option>
                                <option value="COMPLEMENTARIA">Complementaria</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Inspector Asignado</label>
                            <select class="form-select form-select-sm" id="nuevoInspectorId" name="inspector_usuario_id">
                                <option value="">-- Seleccionar inspector --</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Fecha Inicio *</label>
                            <input type="date" class="form-control form-control-sm" id="nuevoFInicio" name="f_inicio" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Fecha Notificación</label>
                            <input type="date" class="form-control form-control-sm" id="nuevoFNotificacion" name="f_notificacion">
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Observaciones</label>
                            <textarea class="form-control form-control-sm" id="nuevoObservaciones" name="observaciones" rows="2" placeholder="Motivo de inspección, período fiscalizado..."></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnGuardarActa">
                        <i class="fas fa-save me-1"></i> Abrir Acta
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Ver/Editar Períodos del Acta -->
<div class="modal fade" id="modalDetalleActa" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="detalleActaTitulo">
                    <i class="fas fa-search me-2"></i>Detalle de Acta
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <!-- Info cabecera -->
                <div class="row g-2 mb-3 bg-light p-3 rounded">
                    <div class="col-md-4">
                        <small class="text-muted d-block">Empleador:</small>
                        <strong id="detRazonSocial">-</strong> (<span id="detCuit">-</span>)
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted d-block">Tipo:</small>
                        <span class="badge bg-secondary" id="detTipo">-</span>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted d-block">Fecha Inicio:</small>
                        <span id="detFInicio">-</span>
                    </div>
                    <div class="col-md-2">
                        <small class="text-muted d-block">Plazo 72 hs:</small>
                        <span class="text-danger fw-bold" id="detF72h">-</span>
                    </div>
                    <div class="col-md-2 text-end">
                        <small class="text-muted d-block">Total Determinado:</small>
                        <strong class="text-primary fs-5" id="detTotalActa">$0.00</strong>
                    </div>
                </div>

                <!-- Formulario rápido agregar período -->
                <div class="card mb-3 border" id="cardAgregarPeriodo">
                    <div class="card-header bg-light py-2">
                        <h6 class="mb-0 fw-semibold text-primary"><i class="fas fa-plus-circle me-1"></i>Agregar Período e Importes Determinados</h6>
                    </div>
                    <div class="card-body py-2">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-2">
                                <label class="form-label small mb-1">Período (AAAAMM)</label>
                                <input type="number" class="form-control form-control-sm" id="itemPeriodo" placeholder="202601">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Determinado Fondo Soc. (100)</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="itemDetFondo" placeholder="0.00">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small mb-1">Determinado Cuota Sind. (101)</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="itemDetCuota" placeholder="0.00">
                            </div>
                            <div class="col-md-2">
                                <label class="form-label small mb-1">Pagado Prev.</label>
                                <input type="number" step="0.01" class="form-control form-control-sm" id="itemPagado" placeholder="0.00" value="0.00">
                            </div>
                            <div class="col-md-2 text-end">
                                <button type="button" class="btn btn-success btn-sm w-100" id="btnAgregarPeriodo">
                                    <i class="fas fa-check me-1"></i> Liquidar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabla de períodos del acta -->
                <h6 class="fw-bold mb-2">Períodos Inspeccionados</h6>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle text-center" id="tablaPeriodosActa">
                        <thead class="table-light">
                            <tr>
                                <th>Período</th>
                                <th>Vto. Orig.</th>
                                <th class="text-end">Declarado</th>
                                <th class="text-end">Determinado</th>
                                <th class="text-end">Pagado</th>
                                <th class="text-end">Dif. Capital</th>
                                <th class="text-end">Intereses</th>
                                <th class="text-end">Total Período</th>
                            </tr>
                        </thead>
                        <tbody id="tbodyPeriodosActa">
                        </tbody>
                    </table>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <div>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="btnAnularActa">
                        <i class="fas fa-ban me-1"></i> Anular Acta
                    </button>
                </div>
                <div>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                    <button type="button" class="btn btn-primary btn-sm ms-2" id="btnConfirmarActa">
                        <i class="fas fa-check-double me-1"></i> Confirmar Acta y Emitir Deuda (ACT)
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="modules/sindicatos/actas.js"></script>
<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
