<?php
declare(strict_types=1);

require_once __DIR__ . '/../../db.php';
$conexion = $conn;

$pageTitle = "Declaraciones Juradas Mensuales";
$currentPage = 'ddjj';
$modudo_idx = 6;
$pagina_idx = 1020;

define('ROOT_PATH', dirname(dirname(__DIR__)));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-file-invoice-dollar me-2 text-primary"></i>Declaraciones Juradas (DDJJ)
                    </h3>
                    <small class="text-muted">Presentación mensual de aportes CCT 130/75, nómina, rectificativas y constancias</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Sindicatos</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Declaraciones Juradas</li>
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
                                <div class="col-md-6">
                                    <label for="filtroEmpleador" class="form-label small fw-semibold text-muted mb-1">Filtrar por Empleador</label>
                                    <select class="form-select form-select-sm select2" id="filtroEmpleador">
                                        <option value="">-- Todos los empleadores autorizados --</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="filtroPeriodo" class="form-label small fw-semibold text-muted mb-1">Período (AAAAMM)</label>
                                    <input type="number" class="form-control form-control-sm" id="filtroPeriodo" placeholder="ej: 202610">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tabla Principal -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-transparent py-3">
                            <div id="contenedor-boton-agregar" class="d-inline"></div>
                            <button type="button" class="btn btn-outline-secondary btn-sm float-end" id="btnRecargar" title="Recargar lista">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="tablaDdjj" class="table table-hover table-striped align-middle" style="width:100%">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Período</th>
                                            <th>Sec.</th>
                                            <th>Empleador / CUIT</th>
                                            <th>Tipo</th>
                                            <th>Origen</th>
                                            <th>Vencimiento</th>
                                            <th>Cant. Emp.</th>
                                            <th class="text-end">Total Liquidado</th>
                                            <th>Estado</th>
                                            <th>Verificación</th>
                                            <th class="text-center" style="width: 140px;">Acciones</th>
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


<!-- Modal: Crear/Editar DDJJ (Cabecera & Nómina) -->
<div class="modal fade" id="modalDdjj" tabindex="-1" aria-labelledby="modalDdjjLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="modalDdjjLabel">
                    <i class="fas fa-edit me-2"></i>Declaración Jurada Mensual
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="formDdjj">
                    <input type="hidden" name="ddjj_id" id="ddjj_id" value="0">

                    <!-- Cabecera de la DDJJ -->
                    <div class="card bg-light border-0 mb-3">
                        <div class="card-body py-2">
                            <div class="row g-2 align-items-center">
                                <div class="col-md-5">
                                    <label for="empleador_id" class="form-label small fw-semibold">Empleador Aportante <span class="text-danger">*</span></label>
                                    <select class="form-select form-select-sm" id="empleador_id" name="empleador_id" required></select>
                                </div>
                                <div class="col-md-3">
                                    <label for="periodo" class="form-label small fw-semibold">Período (AAAAMM) <span class="text-danger">*</span></label>
                                    <input type="number" class="form-control form-control-sm" id="periodo" name="periodo" required placeholder="AAAAMM">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-semibold">Secuencia</label>
                                    <input type="text" class="form-control form-control-sm bg-white" id="secuencia" readonly value="0 (Original)">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label small fw-semibold">Origen</label>
                                    <span class="badge bg-secondary d-block py-2" id="badgeOrigen">MANUAL</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botones de Acción de Nómina -->
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="mb-0 fw-bold text-secondary">
                            <i class="fas fa-users me-1"></i>Nómina de Empleados (<span id="contadorEmpleados">0</span>)
                        </h6>
                        <div>
                            <button type="button" class="btn btn-outline-primary btn-sm me-1" id="btnAgregarEmpleado">
                                <i class="fas fa-user-plus me-1"></i>Agregar Empleado
                            </button>
                            <button type="button" class="btn btn-outline-success btn-sm me-1" id="btnImportarModal">
                                <i class="fas fa-file-import me-1"></i>Importar F.931 / Planilla
                            </button>
                        </div>
                    </div>

                    <!-- Tabla de Renglones de Empleados -->
                    <div class="table-responsive border rounded" style="max-height: 380px;">
                        <table class="table table-sm table-hover align-middle mb-0" id="tablaNomina">
                            <thead class="table-secondary sticky-top">
                                <tr>
                                    <th>CUIL</th>
                                    <th>Apellido y Nombre</th>
                                    <th>Sucursal</th>
                                    <th>Categoría CCT</th>
                                    <th class="text-end">Rem. 1 (Bruto)</th>
                                    <th class="text-end">Rem. 2 (No Rem)</th>
                                    <th class="text-center">Jor. Parcial</th>
                                    <th class="text-center">Afiliado</th>
                                    <th class="text-center" style="width: 70px;">Acción</th>
                                </tr>
                            </thead>
                            <tbody id="tbodyNomina">
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">No hay empleados cargados en la nómina.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Totales y Resumen de Liquidación -->
                    <div class="card mt-3 border-primary">
                        <div class="card-body py-2">
                            <div class="row align-items-center">
                                <div class="col-md-7">
                                    <div id="resumenConceptos" class="d-flex flex-wrap gap-3 small">
                                        <span class="text-muted">Cálculo de aportes por concepto al guardar o previsualizar.</span>
                                    </div>
                                </div>
                                <div class="col-md-5 text-end">
                                    <span class="text-muted me-2">Total a Pagar:</span>
                                    <span class="fs-4 fw-bold text-primary" id="totalPreview">$ 0,00</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnGuardarBorrador">
                    <i class="fas fa-save me-1"></i>Guardar Borrador
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Importador de Archivo (F.931 / CSV) -->
<div class="modal fade" id="modalImportar" tabindex="-1" aria-labelledby="modalImportarLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="modalImportarLabel">
                    <i class="fas fa-file-import me-2"></i>Importar Nómina
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="formImportador">
                    <div class="mb-3">
                        <label for="tipoFormato" class="form-label fw-semibold">Formato del Archivo</label>
                        <select class="form-select" id="tipoFormato" name="tipoFormato" required>
                            <option value="F931_SICOSS">Archivo F.931 SICOSS de ARCA (ancho fijo .txt)</option>
                            <option value="EXCEL_CSV">Planilla Delimitada CSV (.csv / .txt)</option>
                        </select>
                        <small class="text-muted">Soporta exportaciones directas del aplicativo de AFIP/ARCA o planillas de sueldos.</small>
                    </div>

                    <div class="mb-3">
                        <label for="archivoNomina" class="form-label fw-semibold">Seleccionar Archivo <span class="text-danger">*</span></label>
                        <input type="file" class="form-control" id="archivoNomina" name="archivoNomina" accept=".txt,.csv" required>
                    </div>

                    <div id="alertaImportacion" class="alert d-none small"></div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-success text-white" id="btnEjecutarImportacion">
                    <i class="fas fa-upload me-1"></i>Procesar e Importar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Agregar Empleado Individual -->
<div class="modal fade" id="modalEmpleado" tabindex="-1" aria-labelledby="modalEmpleadoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-light">
                <h5 class="modal-title" id="modalEmpleadoLabel">
                    <i class="fas fa-user-edit me-2"></i>Renglón de Empleado
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="formEmpleadoIndividual">
                    <input type="hidden" id="emp_indice" value="-1">

                    <div class="mb-3">
                        <label for="emp_cuil" class="form-label fw-semibold">CUIL <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="emp_cuil" maxlength="11" required placeholder="11 dígitos sin guiones">
                    </div>

                    <div class="mb-3">
                        <label for="emp_nombre" class="form-label fw-semibold">Apellido y Nombre <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="emp_nombre" required placeholder="Apellido Nombre">
                    </div>

                    <div class="mb-3">
                        <label for="emp_sucursal_id" class="form-label fw-semibold">Sucursal donde trabaja</label>
                        <select class="form-select" id="emp_sucursal_id"><option value="">(La última declarada o la Casa Central)</option></select>
                    </div>

                    <div class="mb-3">
                        <label for="emp_categoria_id" class="form-label fw-semibold">Categoría CCT 130/75 <span class="text-danger">*</span></label>
                        <select class="form-select" id="emp_categoria_id" required></select>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label for="emp_rem1" class="form-label fw-semibold">Remunerativo ($) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control" id="emp_rem1" required value="0.00">
                        </div>
                        <div class="col-6">
                            <label for="emp_rem2" class="form-label fw-semibold">No Remunerativo ($)</label>
                            <input type="number" step="0.01" class="form-control" id="emp_rem2" value="0.00">
                        </div>
                    </div>

                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="emp_media_jornada">
                                <label class="form-check-label" for="emp_media_jornada">Media Jornada</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-check form-switch mt-2">
                                <input class="form-check-input" type="checkbox" id="emp_afiliado">
                                <label class="form-check-label" for="emp_afiliado">Afiliado Sindical</label>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnAceptarEmpleado">
                    <i class="fas fa-check me-1"></i>Aceptar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Constancia Oficial de Presentación -->
<div class="modal fade" id="modalConstancia" tabindex="-1" aria-labelledby="modalConstanciaLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title" id="modalConstanciaLabel">
                    <i class="fas fa-certificate me-2 text-warning"></i>Constancia Oficial de Presentación de DDJJ
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4" id="cuerpoConstancia">
                <!-- Se llena dinámicamente -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                <button type="button" class="btn btn-primary" id="btnImprimirConstancia">
                    <i class="fas fa-print me-1"></i>Imprimir / Guardar PDF
                </button>
            </div>
        </div>
    </div>
    </div>
</div>

<script src="ddjj.js"></script>
<?php require_once ROOT_PATH . '/templates/adminlte/footer1.php'; ?>
