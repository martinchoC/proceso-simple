<?php
/**
 * Vista: Tarifarios y Conceptos de Honorarios
 * Módulo 4: Estudio Contable (honorarios_conceptos.php)
 */

require_once __DIR__ . '/../../db.php';

$pageTitle = "Tarifarios y Honorarios";
$currentPage = 'honorarios_conceptos';
$modudo_idx = 4;
$pagina_idx = 405;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 fw-bold">
                        <i class="fas fa-file-invoice-dollar me-2 text-primary"></i>Tarifarios y Honorarios
                    </h3>
                    <small class="text-muted">Administración de conceptos facturables, escalas arancelarias y abonos mensuales por cliente</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="index.php">Estudio Contable</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Honorarios y Tarifarios</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <!-- Nav Tabs -->
            <ul class="nav nav-tabs mb-3" id="honorariosTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active fw-bold" id="abonos-tab" data-bs-toggle="tab" data-bs-target="#abonos-pane" type="button" role="tab">
                        <i class="fas fa-users-cog me-1 text-primary"></i>Abonos Mensuales por Cliente
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link fw-bold" id="conceptos-tab" data-bs-toggle="tab" data-bs-target="#conceptos-pane" type="button" role="tab">
                        <i class="fas fa-tags me-1 text-info"></i>Catálogo de Conceptos
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="honorariosTabContent">
                <!-- PANE 1: ABONOS POR CLIENTE -->
                <div class="tab-pane fade show active" id="abonos-pane" role="tabpanel">
                    <div class="card card-outline card-primary mb-3 shadow-sm">
                        <div class="card-body py-2">
                            <div class="row g-2 align-items-center">
                                <div class="col-md-5 col-sm-6">
                                    <label class="form-label form-label-sm mb-1 text-muted">Filtrar por Cliente</label>
                                    <select id="filtroAbonoCliente" class="form-select form-select-sm">
                                        <option value="0">-- Todos los clientes con abono --</option>
                                    </select>
                                </div>
                                <div class="col-md-4 col-sm-6"></div>
                                <div class="col-md-3 col-sm-12 text-end mt-auto pb-1">
                                    <button type="button" class="btn btn-sm btn-primary w-100" id="btnNuevoAbono">
                                        <i class="fas fa-plus-circle me-1"></i>Nuevo Abono Recurrente
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm">
                        <div class="card-header bg-white py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold text-primary"><i class="fas fa-file-invoice-dollar me-2"></i>Abonos Mensuales</h6>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargarAbonos" title="Recargar abonos">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnExportarCopyAbonos" title="Copiar al portapapeles">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success" id="btnExportarExcelAbonos" title="Exportar a Excel">
                                        <i class="fas fa-file-excel"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" id="btnExportarPDFAbonos" title="Exportar a PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnExportarCSVAbonos" title="Exportar a CSV">
                                        <i class="fas fa-file-csv"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnExportarPrintAbonos" title="Imprimir tabla">
                                        <i class="fas fa-print"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table id="tablaAbonos" class="table table-hover table-striped align-middle mb-0 w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Cliente</th>
                                            <th>Concepto</th>
                                            <th>Punto Venta</th>
                                            <th class="text-end">Importe Base ($)</th>
                                            <th>Día Fact.</th>
                                            <th>Excedentes / Adicionales</th>
                                            <th>Último Período</th>
                                            <th style="width: 80px;" class="text-center">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Dinámico -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- PANE 2: CATALOGO DE CONCEPTOS -->
                <div class="tab-pane fade" id="conceptos-pane" role="tabpanel">
                    <div class="card card-outline card-info mb-3 shadow-sm">
                        <div class="card-body py-2 d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-muted fs-7">Conceptos vinculados a servicios del ERP para facturación automática</span>
                            </div>
                            <div>
                                <button type="button" class="btn btn-sm btn-info text-white" id="btnNuevoConcepto">
                                    <i class="fas fa-plus me-1"></i>Nuevo Concepto
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm">
                        <div class="card-header bg-white py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0 fw-bold text-info"><i class="fas fa-tags me-2"></i>Catálogo de Conceptos</h6>
                                <div class="btn-group" role="group">
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRecargarConceptos" title="Recargar conceptos">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnExportarCopyConceptos" title="Copiar al portapapeles">
                                        <i class="fas fa-copy"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-success" id="btnExportarExcelConceptos" title="Exportar a Excel">
                                        <i class="fas fa-file-excel"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-danger" id="btnExportarPDFConceptos" title="Exportar a PDF">
                                        <i class="fas fa-file-pdf"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-primary" id="btnExportarCSVConceptos" title="Exportar a CSV">
                                        <i class="fas fa-file-csv"></i>
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnExportarPrintConceptos" title="Imprimir tabla">
                                        <i class="fas fa-print"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table id="tablaConceptos" class="table table-hover table-striped align-middle mb-0 w-100">
                                    <thead class="table-light">
                                        <tr>
                                            <th>ID</th>
                                            <th>Concepto Honorario</th>
                                            <th>Producto Base ERP</th>
                                            <th>Modalidad / Tipo Cargo</th>
                                            <th>Descripción</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <!-- Dinámico -->
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

<!-- Modal Nuevo Abono -->
<div class="modal fade" id="modalAbono" tabindex="-1" aria-labelledby="modalAbonoLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <form id="formAbono">
                <input type="hidden" id="ma_abono_id" name="abono_id" value="0">
                <div class="modal-header bg-primary text-white py-2">
                    <h5 class="modal-title fs-6" id="modalAbonoLabel">
                        <i class="fas fa-receipt me-2"></i>Asignar Abono Mensual
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-md-7 mb-2">
                            <label class="form-label form-label-sm fw-semibold">Cliente <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="ma_cliente_id" name="cliente_id" required>
                                <option value="">Seleccione cliente...</option>
                            </select>
                        </div>
                        <div class="col-md-5 mb-2">
                            <label class="form-label form-label-sm fw-semibold">Concepto Facturable <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="ma_concepto_id" name="concepto_id" required>
                                <option value="">Seleccione concepto...</option>
                            </select>
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-4 mb-2">
                            <label class="form-label form-label-sm fw-semibold">Punto de Venta <span class="text-danger">*</span></label>
                            <select class="form-select form-select-sm" id="ma_punto_venta_id" name="punto_venta_id" required>
                                <option value="">Seleccione...</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label form-label-sm fw-semibold">Condición Venta</label>
                            <select class="form-select form-select-sm" id="ma_condicion_venta_id" name="condicion_venta_id">
                                <option value="1">Contado</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-2">
                            <label class="form-label form-label-sm fw-semibold">Importe Base Mensual ($) <span class="text-danger">*</span></label>
                            <input type="number" step="0.01" class="form-control form-control-sm text-end fw-bold" id="ma_importe_base" name="importe_base" required min="1" placeholder="0.00">
                        </div>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-4 mb-2">
                            <label class="form-label form-label-sm fw-semibold">Día de Facturación</label>
                            <input type="number" class="form-control form-control-sm" id="ma_dia_facturacion" name="dia_facturacion" value="1" min="1" max="31">
                        </div>
                        <div class="col-md-8 mb-2">
                            <div class="form-check mt-4">
                                <input class="form-check-input" type="checkbox" id="ma_aplica_adicionales" name="aplica_adicionales_automaticos" value="1">
                                <label class="form-check-label form-label-sm fw-semibold" for="ma_aplica_adicionales">
                                    Calcular Adicionales Automáticos por Excedentes de Carga
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="p-2 border rounded bg-light mt-2" id="seccion_adicionales">
                        <h6 class="fs-8 text-uppercase fw-bold text-muted mb-2">Parámetros de Escalas Excedentes</h6>
                        <div class="row g-2">
                            <div class="col-md-3">
                                <label class="form-label form-label-sm mb-1">Tope Comprobantes</label>
                                <input type="number" class="form-control form-control-sm" id="ma_tope_comp" name="tope_comprobantes_incluidos" value="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label form-label-sm mb-1">Precio Excedente ($)</label>
                                <input type="number" step="0.01" class="form-control form-control-sm text-end" id="ma_precio_comp" name="precio_excedente_comprobante" value="0.00">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label form-label-sm mb-1">Tope Legajos/Sueldos</label>
                                <input type="number" class="form-control form-control-sm" id="ma_tope_leg" name="tope_legajos_incluidos" value="0">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label form-label-sm mb-1">Precio Excedente ($)</label>
                                <input type="number" step="0.01" class="form-control form-control-sm text-end" id="ma_precio_leg" name="precio_excedente_legajo" value="0.00">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-save me-1"></i>Guardar Abono
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Nuevo Concepto -->
<div class="modal fade" id="modalConcepto" tabindex="-1" aria-labelledby="modalConceptoLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form id="formConcepto">
                <input type="hidden" id="mc_concepto_id" name="concepto_id" value="0">
                <div class="modal-header bg-info text-white py-2">
                    <h5 class="modal-title fs-6" id="modalConceptoLabel">
                        <i class="fas fa-tag me-2"></i>Registrar Concepto de Honorarios
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Nombre del Concepto <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-sm" id="mc_nombre" name="nombre" required placeholder="Ej: Asesoramiento Contable e Impositivo">
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Servicio Base ERP (gestion__productos) <span class="text-danger">*</span></label>
                        <select class="form-select form-select-sm" id="mc_producto_id" name="producto_id" required>
                            <option value="">Seleccione servicio del ERP...</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Modalidad de Cargo</label>
                        <select class="form-select form-select-sm" id="mc_tipo_cargo" name="tipo_cargo">
                            <option value="FIJO_MENSUAL">Fijo Mensual</option>
                            <option value="POR_COMPROBANTE">Por Comprobante Procesado</option>
                            <option value="POR_LEGAJO">Por Legajo / Sueldo Liquidado</option>
                            <option value="POR_HORA">Por Hora Profesional</option>
                            <option value="EVENTUAL">Eventual / Adicional</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label form-label-sm fw-semibold">Descripción Detallada</label>
                        <textarea class="form-control form-control-sm" id="mc_descripcion" name="descripcion" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer py-2">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-sm btn-info text-white">
                        <i class="fas fa-save me-1"></i>Guardar Concepto
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="honorarios_conceptos.js"></script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
