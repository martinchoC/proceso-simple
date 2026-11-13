<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Afiliados y Grupo Familiar";
$currentPage = 'afiliados';
$modudo_idx = 6;
$pagina_idx = 1050;

define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<div class="app-content-header">
    <div class="container-fluid">
        <div class="row align-items-center">
            <div class="col-sm-6">
                <h3 class="mb-0"><i class="fas fa-id-card me-2 text-primary"></i>Afiliados y Grupo Familiar</h3>
                <small class="text-muted">Afiliaciones por actividad, voluntarias y de jubilados, con su grupo familiar a cargo y vigencias</small>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-end">
                    <li class="breadcrumb-item"><a href="#">Home</a></li>
                    <li class="breadcrumb-item"><a href="#">Sindicatos</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Afiliados</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<div class="app-content">
    <div class="container-fluid">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-transparent py-3">
                <div class="row g-2 align-items-center">
                    <div class="col-md-auto">
                        <button type="button" class="btn btn-primary btn-sm d-none" id="btnNuevaAfiliacion">
                            <i class="fas fa-plus me-1"></i> Nueva afiliación
                        </button>
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control form-control-sm" id="filtroTexto" placeholder="CUIL, apellido o N° de afiliado">
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" id="filtroTipo"><option value="">Todos los tipos</option></select>
                    </div>
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" id="filtroEstado">
                            <option value="">Todos los estados</option>
                            <option value="1">Activa</option>
                            <option value="20">Vencida</option>
                            <option value="2">Baja</option>
                        </select>
                    </div>
                    <div class="col-md-auto ms-auto d-flex gap-2">
                        <div class="dropdown">
                            <button class="btn btn-outline-success btn-sm dropdown-toggle" type="button" id="dropdownMenuExportar" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-file-excel me-1"></i> Exportar
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="dropdownMenuExportar">
                                <li><a class="dropdown-item" href="#" id="btnExportarPadronExcel"><i class="fas fa-list text-success me-2"></i> Padrón filtrado actual</a></li>
                                <li><a class="dropdown-item" href="#" id="btnExportarGruposExcel"><i class="fas fa-people-arrows text-info me-2"></i> Grupos familiares</a></li>
                                <li><a class="dropdown-item" href="#" id="btnExportarCumplesExcel"><i class="fas fa-birthday-cake text-warning me-2"></i> Cumpleaños del mes</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="#" id="btnExportarBajasExcel"><i class="fas fa-user-slash text-danger me-2"></i> Bajas e inactivos</a></li>
                            </ul>
                        </div>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnAbrirReportes">
                            <i class="fas fa-chart-pie me-1"></i> Reportes
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="btnRecargar" title="Recargar tabla"><i class="fas fa-sync-alt"></i></button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table id="tablaAfiliados" class="table table-hover table-striped align-middle w-100">
                        <thead class="table-light">
                            <tr>
                                <th>N° Afiliado</th>
                                <th>Apellido y Nombre</th>
                                <th>CUIL</th>
                                <th>Tipo</th>
                                <th>Empleador</th>
                                <th>Alta</th>
                                <th class="text-center">Familiares</th>
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

<!-- Modal persona (alta de titular o de familiar) -->
<div class="modal fade" id="modalPersona" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="modalPersonaTitulo">Nueva afiliación</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formPersona">
                <div class="modal-body">
                    <input type="hidden" name="modo" id="modo" value="titular">
                    <input type="hidden" name="titular_afiliacion_id" id="titular_afiliacion_id" value="">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">CUIL <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="cuil" id="cuil" maxlength="11" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Apellido <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="apellido" id="apellido" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Nombre</label>
                            <input type="text" class="form-control" name="nombre" id="nombre">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Fecha de nacimiento</label>
                            <input type="date" class="form-control" name="f_nacimiento" id="f_nacimiento">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Sexo</label>
                            <select class="form-select" name="sexo" id="sexo">
                                <option value="">-</option><option value="F">F</option><option value="M">M</option><option value="X">X</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Fecha de alta</label>
                            <input type="date" class="form-control" name="f_alta" id="f_alta">
                        </div>
                        <div class="col-md-6 bloque-titular">
                            <label class="form-label fw-semibold">Tipo de afiliación <span class="text-danger">*</span></label>
                            <select class="form-select" name="tipo_codigo" id="tipo_codigo"></select>
                        </div>
                        <div class="col-md-6 bloque-titular bloque-empleador d-none">
                            <label class="form-label fw-semibold">Empleador <span class="text-danger">*</span></label>
                            <select class="form-select" name="empleador_id" id="empleador_id"><option value="">(Seleccionar)</option></select>
                        </div>
                        <div class="col-md-6 bloque-titular">
                            <label class="form-label fw-semibold">Correo electrónico</label>
                            <input type="email" class="form-control" name="email" id="email">
                        </div>
                        <div class="col-md-6 bloque-titular">
                            <label class="form-label fw-semibold">Teléfono</label>
                            <input type="text" class="form-control" name="telefono" id="telefono">
                        </div>
                        <div class="col-md-6 bloque-familiar d-none">
                            <label class="form-label fw-semibold">Parentesco <span class="text-danger">*</span></label>
                            <select class="form-select" name="parentesco_id" id="parentesco_id"></select>
                        </div>
                        <div class="col-md-6 bloque-familiar d-none">
                            <label class="form-label fw-semibold">Documento de excepción presentado</label>
                            <input type="text" class="form-control" name="documento" id="documento" placeholder="Ej.: certificado de alumno regular">
                            <small class="text-muted">Solo si el familiar supera la edad de cobertura y el parentesco admite excepción.</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4" id="btnGuardarPersona">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal ficha -->
<div class="modal fade" id="modalFicha" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold" id="fichaTitulo">Ficha del afiliado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="fichaCuerpo"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary d-none" id="btnFichaCarnet"><i class="fas fa-print me-1"></i>Carnets del grupo</button>
                <button type="button" class="btn btn-outline-primary d-none" id="btnFichaFamiliar"><i class="fas fa-plus me-1"></i>Agregar familiar</button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal baja -->
<div class="modal fade" id="modalBaja" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Dar de baja</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formBaja">
                <div class="modal-body">
                    <input type="hidden" name="afiliacion_id" id="baja_afiliacion_id">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Motivo</label>
                        <select class="form-select" name="baja_motivo_id" id="baja_motivo_id"><option value="">(Sin motivo)</option></select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Fecha de baja</label>
                        <input type="date" class="form-control" name="f_baja" id="f_baja" required>
                    </div>
                    <div class="alert alert-warning small mb-0">La baja de un titular da de baja a todo su grupo familiar.</div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger px-4">Dar de baja</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal documento -->
<div class="modal fade" id="modalDocumento" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title fw-bold">Presentar documento</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="formDocumento" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="afiliacion_id" id="doc_afiliacion_id">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Documento</label>
                        <input type="text" class="form-control" name="documento" id="doc_documento" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Fecha de presentación</label>
                        <input type="date" class="form-control" name="f_presentacion" id="doc_f_presentacion" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Archivo (PDF o imagen)</label>
                        <input type="file" class="form-control" name="archivo" accept=".pdf,.jpg,.jpeg,.png">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary px-4">Registrar</button>
                </div>
            </form>
        </div>
    </div>
<!-- Modal Reportes y Estadísticas del Padrón -->
<div class="modal fade" id="modalReportes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-light border-bottom">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-primary fs-6 p-2"><i class="fas fa-chart-pie"></i></span>
                    <div>
                        <h5 class="modal-title fw-bold mb-0">Reportes y Estadísticas del Padrón</h5>
                        <small class="text-muted">Cumpleaños, estado del padrón y grupos familiares con exportación</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-0">
                <!-- Tabs -->
                <ul class="nav nav-tabs nav-fill bg-light px-3 pt-2 border-bottom" id="reportesTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active fw-semibold" id="tab-cumples-btn" data-bs-toggle="tab" data-bs-target="#tab-cumples" type="button" role="tab">
                            <i class="fas fa-birthday-cake text-warning me-2"></i>Cumpleaños del Mes
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold" id="tab-activos-btn" data-bs-toggle="tab" data-bs-target="#tab-activos" type="button" role="tab">
                            <i class="fas fa-users text-primary me-2"></i>Activos / Inactivos / Bajas
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link fw-semibold" id="tab-grupos-btn" data-bs-toggle="tab" data-bs-target="#tab-grupos" type="button" role="tab">
                            <i class="fas fa-people-arrows text-info me-2"></i>Grupos Familiares
                        </button>
                    </li>
                </ul>

                <div class="tab-content p-4" id="reportesTabsContent">
                    <!-- Tab 1: Cumpleaños -->
                    <div class="tab-pane fade show active" id="tab-cumples" role="tabpanel">
                        <div class="row g-2 align-items-center mb-3 p-3 bg-light rounded border">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-muted mb-1">Mes de Cumpleaños</label>
                                <select class="form-select form-select-sm" id="repCumpleMes">
                                    <option value="1">Enero</option>
                                    <option value="2">Febrero</option>
                                    <option value="3">Marzo</option>
                                    <option value="4">Abril</option>
                                    <option value="5">Mayo</option>
                                    <option value="6">Junio</option>
                                    <option value="7">Julio</option>
                                    <option value="8">Agosto</option>
                                    <option value="9">Septiembre</option>
                                    <option value="10">Octubre</option>
                                    <option value="11">Noviembre</option>
                                    <option value="12">Diciembre</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-muted mb-1">Estado de Afiliación</label>
                                <select class="form-select form-select-sm" id="repCumpleEstado">
                                    <option value="1" selected>Solo Activos</option>
                                    <option value="">Todos los estados</option>
                                    <option value="20">Solo Vencidos</option>
                                    <option value="2">Solo Bajas</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end gap-2 mt-auto">
                                <button type="button" class="btn btn-primary btn-sm px-3" id="btnRepCumpleFiltrar">
                                    <i class="fas fa-search me-1"></i> Filtrar
                                </button>
                                <button type="button" class="btn btn-outline-success btn-sm px-3" id="btnRepCumpleExcelModal">
                                    <i class="fas fa-file-excel me-1"></i> Descargar Excel
                                </button>
                            </div>
                            <div class="col-md-3 text-md-end mt-auto">
                                <span class="badge bg-secondary p-2" id="repCumpleContador">0 cumpleañeros</span>
                            </div>
                        </div>

                        <div class="table-responsive" style="max-height: 480px;">
                            <table class="table table-sm table-hover table-striped align-middle border" id="tablaRepCumple">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th class="text-center" style="width: 70px;">Día</th>
                                        <th>Apellido y Nombre</th>
                                        <th>CUIL</th>
                                        <th>Tipo / Rol</th>
                                        <th>Titular a Cargo</th>
                                        <th class="text-center">Edad a Cumplir</th>
                                        <th>Empleador</th>
                                        <th class="text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="8" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Tab 2: Activos / Inactivos -->
                    <div class="tab-pane fade" id="tab-activos" role="tabpanel">
                        <!-- KPI Cards -->
                        <div class="row g-3 mb-4" id="kpiActivosCards">
                            <div class="col-md-3">
                                <div class="card shadow-sm border-start border-4 border-primary">
                                    <div class="card-body py-2 px-3">
                                        <div class="text-muted small text-uppercase fw-semibold">Padrón Total</div>
                                        <div class="fs-4 fw-bold text-primary" id="kpiTotal">-</div>
                                        <div class="small text-muted">Titulares + Familiares</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card shadow-sm border-start border-4 border-success">
                                    <div class="card-body py-2 px-3">
                                        <div class="text-muted small text-uppercase fw-semibold">Afiliados Activos</div>
                                        <div class="fs-4 fw-bold text-success" id="kpiActivos">-</div>
                                        <div class="small text-muted">Con cobertura vigente</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card shadow-sm border-start border-4 border-warning">
                                    <div class="card-body py-2 px-3">
                                        <div class="text-muted small text-uppercase fw-semibold">Vencidos / A renovar</div>
                                        <div class="fs-4 fw-bold text-warning" id="kpiVencidos">-</div>
                                        <div class="small text-muted">Doc. requerida</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card shadow-sm border-start border-4 border-secondary">
                                    <div class="card-body py-2 px-3">
                                        <div class="text-muted small text-uppercase fw-semibold">Bajas Registradas</div>
                                        <div class="fs-4 fw-bold text-secondary" id="kpiBajas">-</div>
                                        <div class="small text-muted">Inactivos definitivos</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Detalle por Tipo y Bajas -->
                        <div class="row g-4">
                            <div class="col-lg-6">
                                <div class="card shadow-sm border h-100">
                                    <div class="card-header bg-light fw-bold d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-layer-group text-primary me-2"></i>Desglose por Tipo de Afiliación</span>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover align-middle mb-0" id="tablaRepDesglose">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th>Tipo</th>
                                                        <th>Grupo</th>
                                                        <th class="text-center text-success">Activos</th>
                                                        <th class="text-center text-warning">Vencidos</th>
                                                        <th class="text-center text-secondary">Bajas</th>
                                                        <th class="text-end fw-bold">Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr><td colspan="6" class="text-center py-3 text-muted">Cargando...</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="card shadow-sm border h-100">
                                    <div class="card-header bg-light fw-bold d-flex justify-content-between align-items-center">
                                        <span><i class="fas fa-user-slash text-danger me-2"></i>Últimas Bajas Registradas</span>
                                        <button type="button" class="btn btn-outline-danger btn-sm" id="btnRepBajasExcelModal">
                                            <i class="fas fa-file-excel me-1"></i> Excel Bajas
                                        </button>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive" style="max-height: 380px;">
                                            <table class="table table-sm table-hover align-middle mb-0" id="tablaRepBajas">
                                                <thead class="table-light sticky-top">
                                                    <tr>
                                                        <th>Fecha</th>
                                                        <th>Afiliado</th>
                                                        <th>CUIL</th>
                                                        <th>Tipo</th>
                                                        <th>Motivo</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr><td colspan="5" class="text-center py-3 text-muted">Cargando...</td></tr>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Tab 3: Grupos Familiares -->
                    <div class="tab-pane fade" id="tab-grupos" role="tabpanel">
                        <div class="row g-2 align-items-center mb-3 p-3 bg-light rounded border">
                            <div class="col-md-5">
                                <label class="form-label small fw-bold text-muted mb-1">Filtrar por titular o familiar</label>
                                <div class="input-group input-group-sm">
                                    <input type="text" class="form-control" id="repGruposTexto" placeholder="Nombre, CUIL o N° de afiliado...">
                                    <button class="btn btn-primary" type="button" id="btnRepGruposBuscar">
                                        <i class="fas fa-search me-1"></i> Buscar
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-7 d-flex justify-content-md-end align-items-end gap-2 mt-auto">
                                <button type="button" class="btn btn-outline-success btn-sm px-3" id="btnRepGruposExcelModal">
                                    <i class="fas fa-file-excel me-1"></i> Descargar Padrón con Grupos en Excel
                                </button>
                                <span class="badge bg-info text-dark p-2" id="repGruposContador">0 personas</span>
                            </div>
                        </div>

                        <div class="table-responsive" style="max-height: 480px;">
                            <table class="table table-sm table-hover align-middle border" id="tablaRepGrupos">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th style="width: 110px;">N° Afiliado</th>
                                        <th>Rol / Parentesco</th>
                                        <th>Apellido y Nombre</th>
                                        <th>CUIL</th>
                                        <th class="text-center">Edad</th>
                                        <th>Titular / Empleador</th>
                                        <th>Vigencia Doc.</th>
                                        <th class="text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr><td colspan="8" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Cargando...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="afiliados.js"></script>
<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
