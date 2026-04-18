<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Configuración de Impuestos por Empresa";
$currentPage = 'empresas_impuestos_config';
$modudo_idx = 2;
$pagina_idx = 73; // ID de página para Empresas Impuestos Config

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-percent me-2"></i>Configuración de Impuestos por Empresa
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Gestión</a></li>
                        <li class="breadcrumb-item"><a href="#">Configuración</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Impuestos por Empresa</li>
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
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <div id="contenedor-boton-agregar" class="d-inline"></div>
                                        <div class="float-end">
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                                    id="btnRecargar" title="Recargar tabla">
                                                    <i class="fas fa-sync-alt"></i>
                                                </button>
                                                <button type="button"
                                                    class="btn btn-sm btn-outline-success dropdown-toggle"
                                                    data-bs-toggle="dropdown" aria-expanded="false"
                                                    title="Exportar datos">
                                                    <i class="fas fa-file-export"></i> Exportar
                                                </button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="#" id="btnExportarExcel"><i
                                                                class="fas fa-file-excel text-success"></i> Excel</a>
                                                    </li>
                                                    <li><a class="dropdown-item" href="#" id="btnExportarPDF"><i
                                                                class="fas fa-file-pdf text-danger"></i> PDF</a></li>
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                    <li><a class="dropdown-item" href="#" id="btnExportarCSV"><i
                                                                class="fas fa-file-csv text-primary"></i> CSV</a></li>
                                                    <li><a class="dropdown-item" href="#" id="btnExportarPrint"><i
                                                                class="fas fa-print text-secondary"></i> Imprimir</a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <!-- DataTable -->
                                        <table id="tablaImpuestosConfig" class="table table-striped table-bordered"
                                            style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="50">ID</th>
                                                    <th width="120">Tipo de Impuesto</th>
                                                    <th width="120">Jurisdicción</th>
                                                    <th width="120">Cuenta Contable</th>
                                                    <th width="100">Tipo Cálculo</th>
                                                    <th width="80" class="text-center">Aplica Siempre</th>
                                                    <th width="100">Prioridad</th>
                                                    <th width="100">Vigencia Desde</th>
                                                    <th width="100">Vigencia Hasta</th>
                                                    <th width="100">Estado</th>
                                                    <th width="150" class="text-center">Acciones</th>
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

            <!-- Modal para crear/editar Configuración de Impuesto (CON SOLAPAS) -->
            <div class="modal fade" id="modalImpuestoConfig" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Configuración de Impuesto</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <!-- Tabs dentro del modal -->
                            <ul class="nav nav-tabs mb-3" id="modalTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="configuracion-tab" data-bs-toggle="tab" 
                                            data-bs-target="#configuracionPanel" type="button" role="tab">
                                        <i class="fas fa-cog me-1"></i>Configuración General
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="metodos-tab" data-bs-toggle="tab" 
                                            data-bs-target="#metodosPanel" type="button" role="tab">
                                        <i class="fas fa-calculator me-1"></i>Métodos por Tipo de Bien
                                    </button>
                                </li>
                                <!-- NUEVA SOLAPA -->
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="subgrupos-tab" data-bs-toggle="tab" 
                                            data-bs-target="#subgruposPanel" type="button" role="tab">
                                        <i class="fas fa-tags me-1"></i>Subgrupos de Comprobantes
                                    </button>
                                </li>
                            </ul>

                            <div class="tab-content" id="modalTabsContent">
                                <!-- Panel de Configuración General -->
                                <div class="tab-pane fade show active" id="configuracionPanel" role="tabpanel">
                                    <form id="formImpuestoConfig" class="needs-validation" novalidate>
                                        <input type="hidden" id="empresa_impuesto_config_id" name="empresa_impuesto_config_id" />
                                        <input type="hidden" id="empresa_id" name="empresa_id" />
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="impuesto_tipo_id" class="form-label">Tipo de Impuesto *</label>
                                                <select class="form-select" id="impuesto_tipo_id" name="impuesto_tipo_id" required>
                                                    <option value="">Seleccione un tipo de impuesto...</option>
                                                </select>
                                                <div class="invalid-feedback">Seleccione un tipo de impuesto</div>
                                            </div>
                                            
                                            <div class="col-md-6 mb-3">
                                                <label for="jurisdiccion_id" class="form-label">Jurisdicción</label>
                                                <select class="form-select" id="jurisdiccion_id" name="jurisdiccion_id">
                                                    <option value="">Seleccione una jurisdicción...</option>
                                                </select>
                                                <div class="form-text">Opcional</div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="cont_cuenta_id" class="form-label">Cuenta Contable</label>
                                                <select class="form-select" id="cont_cuenta_id" name="cont_cuenta_id">
                                                    <option value="">Seleccione una cuenta contable...</option>
                                                </select>
                                                <div class="form-text">Opcional</div>
                                            </div>
                                            
                                            <div class="col-md-6 mb-3">
                                                <label for="tipo_calculo" class="form-label">Tipo de Cálculo *</label>
                                                <select class="form-select" id="tipo_calculo" name="tipo_calculo" required>
                                                    <option value="">Seleccione un tipo de cálculo...</option>
                                                    <option value="manual">Manual (alícuota fija)</option>
                                                    <option value="padron">Padrón (desde jurisdicción)</option>
                                                    <option value="regla">Regla (fórmula personalizada)</option>
                                                </select>
                                                <div class="invalid-feedback">Seleccione un tipo de cálculo</div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="prioridad" class="form-label">Prioridad</label>
                                                <input type="number" class="form-control" id="prioridad" 
                                                    name="prioridad" value="1" min="1">
                                                <div class="form-text">Orden de aplicación</div>
                                            </div>
                                            
                                            <div class="col-md-4 mb-3">
                                                <label for="f_desde" class="form-label">Vigencia Desde *</label>
                                                <input type="date" class="form-control" id="f_desde" 
                                                    name="f_desde" required>
                                                <div class="invalid-feedback">La fecha de inicio es obligatoria</div>
                                            </div>
                                            
                                            <div class="col-md-4 mb-3">
                                                <label for="f_hasta" class="form-label">Vigencia Hasta</label>
                                                <input type="date" class="form-control" id="f_hasta" 
                                                    name="f_hasta">
                                                <div class="form-text">Opcional</div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-12 mb-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" 
                                                        id="aplica_siempre" name="aplica_siempre" value="1" checked>
                                                    <label class="form-check-label" for="aplica_siempre">
                                                        <i class="fas fa-check-circle text-primary me-1"></i>Aplica siempre (sin validación adicional)
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <!-- Panel de Métodos por Tipo de Bien -->
                                <div class="tab-pane fade" id="metodosPanel" role="tabpanel">
                                    <div class="mb-3">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <label class="form-label text-muted">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Métodos de cálculo específicos por tipo de bien
                                                </label>
                                                <small class="form-text d-block">
                                                    Estos métodos sobrescriben la configuración general para tipos de bien específicos.
                                                </small>
                                            </div>
                                            <div class="col-md-6 text-end">
                                                <button type="button" class="btn btn-sm btn-success" id="btnAgregarMetodo">
                                                    <i class="fas fa-plus me-1"></i>Agregar Método
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table id="tablaMetodos" class="table table-sm table-striped table-bordered" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="50">ID</th>
                                                    <th width="150">Tipo de Bien</th>
                                                    <th width="120">Condición Fiscal</th>
                                                    <th width="100">Base Cálculo</th>
                                                    <th width="100">Alícuota (%)</th>
                                                    <th width="120">Mínimo Imponible</th>
                                                    <th width="100">Monto Fijo</th>
                                                    <th width="100">Vigencia Desde</th>
                                                    <th width="100">Vigencia Hasta</th>
                                                    <th width="80" class="text-center">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                                <!-- Panel de Subgrupos de Comprobantes -->
                                <div class="tab-pane fade" id="subgruposPanel" role="tabpanel">
                                    <div class="mb-3">
                                        <div class="row">
                                            <div class="col-md-8">
                                                <label class="form-label text-muted">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Subgrupos de comprobantes donde aplica esta configuración
                                                </label>
                                                <small class="form-text d-block">
                                                    Seleccione los subgrupos de comprobantes que utilizarán esta configuración de impuesto.
                                                </small>
                                            </div>
                                            <div class="col-md-4 text-end">
                                                <button type="button" class="btn btn-sm btn-success" id="btnAgregarSubgrupo" disabled>
                                                    <i class="fas fa-plus me-1"></i>Asignar Subgrupo
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="table-responsive">
                                        <table id="tablaSubgrupos" class="table table-sm table-striped table-bordered" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="50">ID</th>
                                                    <th width="120">Grupo</th>
                                                    <th width="200">Subgrupo</th>
                                                    <th width="100">Código</th>
                                                    <th width="80" class="text-center">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" id="btnGuardar">
                                <i class="fas fa-save me-1"></i>Guardar Configuración
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sub-modal para agregar/editar Método de Cálculo -->
            <div class="modal fade" id="modalMetodo" tabindex="-1" aria-labelledby="modalMetodoLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalMetodoLabel">Método de Cálculo por Tipo de Bien</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formMetodo" class="needs-validation" novalidate>
                                <input type="hidden" id="empresa_impuesto_config_operacion_id" name="empresa_impuesto_config_operacion_id" />
                                <input type="hidden" id="metodo_config_id" name="empresa_impuesto_config_id" />
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="producto_tipo_id" class="form-label">Tipo de Bien *</label>
                                        <select class="form-select" id="producto_tipo_id" name="producto_tipo_id" required>
                                            <option value="">Seleccione un tipo de bien...</option>
                                            <option value="0">-- APLICA A TODOS LOS TIPOS DE BIEN --</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione un tipo de bien</div>
                                        <div class="form-text">Seleccione "Aplica a todos" para que esta regla aplique independientemente del tipo de bien</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="metodo_condicion_fiscal_id" class="form-label">Condición Fiscal</label>
                                        <select class="form-select" id="metodo_condicion_fiscal_id" name="condicion_fiscal_id">
                                            <option value="">Seleccione una condición fiscal...</option>
                                        </select>
                                        <div class="form-text">Opcional - Aplica para todas si se deja vacío</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="metodo_base_calculo" class="form-label">Base de Cálculo</label>
                                        <select class="form-select" id="metodo_base_calculo" name="base_calculo">
                                            <option value="">Usar base por defecto</option>
                                            <option value="NETO_GRAVADO">NETO GRAVADO</option>
                                            <option value="TOTAL">TOTAL FACTURA</option>
                                            <option value="IMPORTE_EXENTO">IMPORTE EXENTO</option>
                                            <option value="MONTO_FIJO">MONTO FIJO</option>
                                        </select>
                                        <div class="form-text">Opcional - Sobrescribe la base general</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="metodo_alicuota" class="form-label">Alícuota (%)</label>
                                        <input type="number" step="0.01" class="form-control" id="metodo_alicuota" 
                                            name="alicuota" value="0">
                                        <div class="form-text">Opcional - Sobrescribe la alícuota general</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="metodo_minimo_imponible" class="form-label">Mínimo Imponible</label>
                                        <input type="number" step="0.01" class="form-control" id="metodo_minimo_imponible" 
                                            name="minimo_imponible" value="0">
                                        <div class="form-text">Opcional</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="metodo_monto_fijo" class="form-label">Monto Fijo</label>
                                        <input type="number" step="0.01" class="form-control" id="metodo_monto_fijo" 
                                            name="monto_fijo" value="0">
                                        <div class="form-text">Opcional</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="metodo_f_desde" class="form-label">Vigencia Desde *</label>
                                        <input type="date" class="form-control" id="metodo_f_desde" 
                                            name="f_desde" required>
                                        <div class="invalid-feedback">La fecha de inicio es obligatoria</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="metodo_f_hasta" class="form-label">Vigencia Hasta</label>
                                        <input type="date" class="form-control" id="metodo_f_hasta" 
                                            name="f_hasta">
                                        <div class="form-text">Opcional</div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" id="btnGuardarMetodo">
                                <i class="fas fa-save me-1"></i>Guardar Método
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Sub-modal para asignar subgrupo -->
            <div class="modal fade" id="modalAsignarSubgrupo" tabindex="-1" aria-labelledby="modalAsignarSubgrupoLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalAsignarSubgrupoLabel">Asignar Subgrupo de Comprobante</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formAsignarSubgrupo">
                                <div class="mb-3">
                                    <label for="comprobante_subgrupo_id" class="form-label">Subgrupo de Comprobante *</label>
                                    <select class="form-select" id="comprobante_subgrupo_id" name="comprobante_subgrupo_id" required>
                                        <option value="">Seleccione un subgrupo...</option>
                                    </select>
                                    <div class="invalid-feedback">Debe seleccionar un subgrupo</div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" id="btnGuardarAsignacion">
                                <i class="fas fa-save me-1"></i>Asignar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Estilos personalizados -->
    <style>
        @media (max-width: 640px) {
            .dataTables_wrapper .dataTable { font-size: 10px; }
            .dataTables_wrapper .dataTable td, .dataTables_wrapper .dataTable th { padding: 6px 3px; }
            .btn-group .btn-sm { padding: 2px 4px; font-size: 10px; }
        }
        [title] { cursor: help; }
        @media (max-width: 768px) {
            .dataTables_wrapper .dataTables_length, .dataTables_wrapper .dataTables_filter { float: none !important; text-align: left !important; margin-bottom: 10px; }
            .dataTables_wrapper .dataTables_filter input { width: 100%; }
            .btn-group { display: inline-flex; flex-wrap: wrap; gap: 4px; }
            .btn-group .btn { margin-bottom: 4px; }
            table.dataTable { font-size: 12px; }
            table.dataTable thead th, table.dataTable tbody td { padding: 8px 6px; }
            .btn-group .btn-sm { padding: 4px 6px; font-size: 11px; }
        }
        @media (max-width: 480px) {
            table.dataTable { font-size: 10px; }
            .btn-group .btn-sm { padding: 3px 5px; font-size: 10px; }
            .badge { font-size: 9px; padding: 3px 5px; }
        }
        td.dtr-control:before { content: '▶'; font-size: 14px; line-height: 1; }
        tr.dtr-expanded td.dtr-control:before { content: '▼'; }
        .dtr-details { width: 100%; background-color: #f8f9fa; border-radius: 4px; padding: 8px; }
        .dtr-details li { padding: 4px 8px; border-bottom: 1px solid #dee2e6; }
        .dtr-details li:last-child { border-bottom: none; }
        .dtr-details .dtr-title { font-weight: bold; width: 40%; display: inline-block; }
        .dtr-details .dtr-data { width: 55%; display: inline-block; }
        .dataTables_scroll { overflow-x: auto; }
        .table-responsive-wrapper { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .dt-buttons .btn { margin-right: 5px; margin-bottom: 5px; }
        .dt-button-collection .dropdown-menu { margin-top: 5px; }
        .dataTables_wrapper .dt-buttons { float: right; margin-top: 5px; }
        .dropdown-menu .dropdown-item i { width: 20px; text-align: center; margin-right: 8px; }
        .badge-tipo { font-size: 0.85em; padding: 4px 8px; }
        .form-check-input:checked { background-color: #0d6efd; border-color: #0d6efd; }
        .nav-tabs .nav-link { font-weight: 500; }
        .nav-tabs .nav-link i { margin-right: 5px; }
        .table-sm td, .table-sm th { padding: 0.5rem; font-size: 0.875rem; }
    </style>

    <script>
        $(document).ready(function () {
            // Variables de contexto
            const empresa_idx = <?php echo isset($_GET['empresa_id']) ? $_GET['empresa_id'] : 2; ?>;
            const pagina_idx = <?php echo $pagina_idx; ?>;

            // Variables para mantener el estado de las tablas
            var tablaConfig;
            var tablaMetodos;
            var currentConfigId = null;
            var tablaSubgrupos;
            
            // Establecer empresa_id automáticamente desde el contexto
            $('#empresa_id').val(empresa_idx);
            
            // ==================== FUNCIONES DE CARGA DE CATÁLOGOS ====================
            
            function cargarTiposImpuesto() {
                $.ajax({
                    url: 'empresas_impuestos_config_ajax.php',
                    type: 'GET',
                    data: { accion: 'listar_tipos_impuesto' },
                    dataType: 'json',
                    success: function(response) {
                        var select = $('#impuesto_tipo_id');
                        select.empty();
                        select.append('<option value="">Seleccione un tipo de impuesto...</option>');
                        if (response && response.length > 0) {
                            $.each(response, function(index, tipo) {
                                var texto = tipo.impuesto_tipo;
                                if (tipo.es_retencion) texto += ' (Retención)';
                                if (tipo.es_percepcion) texto += ' (Percepción)';
                                select.append('<option value="' + tipo.impuesto_tipo_id + '">' + escapeHtml(texto) + '</option>');
                            });
                        }
                    },
                    error: function(xhr, status, error) { console.error('Error al cargar tipos de impuesto:', error); }
                });
            }
            function cargarSubgruposDisponibles() {
                $.ajax({
                    url: 'empresas_impuestos_config_ajax.php',
                    type: 'GET',
                    data: { accion: 'listar_subgrupos_disponibles' },
                    dataType: 'json',
                    success: function(response) {
                        var select = $('#comprobante_subgrupo_id');
                        select.empty();
                        select.append('<option value="">Seleccione un subgrupo...</option>');
                        if (response && response.length > 0) {
                            $.each(response, function(index, subgrupo) {
                                var texto = subgrupo.comprobante_grupo + ' - ' + subgrupo.comprobante_subgrupo;
                                if (subgrupo.codigo) {
                                    texto += ' (' + subgrupo.codigo + ')';
                                }
                                select.append('<option value="' + subgrupo.comprobante_subgrupo_id + '">' + escapeHtml(texto) + '</option>');
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error al cargar subgrupos:', error);
                    }
                });
            }

            // Función para inicializar la tabla de subgrupos asignados
            function inicializarTablaSubgrupos(configId) {
                if (tablaSubgrupos) {
                    tablaSubgrupos.destroy();
                    $('#tablaSubgrupos tbody').empty();
                }

                tablaSubgrupos = $('#tablaSubgrupos').DataTable({
                    ajax: {
                        url: 'empresas_impuestos_config_ajax.php',
                        type: 'GET',
                        data: { accion: 'listar_subgrupos_asignados', config_id: configId },
                        dataSrc: ''
                    },
                    pageLength: 5,
                    lengthMenu: [[5, 10, 25, -1], [5, 10, 25, "Todos"]],
                    columns: [
                        { data: 'empresa_impuesto_config_subgrupo_id', className: 'text-center fw-bold' },
                        { data: 'comprobante_grupo', render: function(data, type, row) {
                            if (type === 'export') return data || '';
                            return data ? escapeHtml(data) : '<span class="text-muted">-</span>';
                        } },
                        { data: 'comprobante_subgrupo', render: function(data, type, row) {
                            if (type === 'export') return data || '';
                            return data ? escapeHtml(data) : '<span class="text-muted">-</span>';
                        } },
                        { data: 'codigo', render: function(data, type, row) {
                            if (type === 'export') return data || '';
                            return data ? '<span class="badge bg-secondary">' + escapeHtml(data) + '</span>' : '<span class="text-muted">-</span>';
                        } },
                        { data: null, orderable: false, searchable: false, className: "text-center", width: '80px', render: function(data, type, row) {
                            if (type === 'export') return '';
                            return `<button type="button" class="btn btn-sm btn-outline-danger btn-desasignar-subgrupo" 
                                        data-id="${row.empresa_impuesto_config_subgrupo_id}" 
                                        data-nombre="${escapeHtml(row.comprobante_subgrupo || '')}"
                                        title="Desasignar">
                                        <i class="fas fa-trash"></i>
                                    </button>`;
                        } }
                    ],
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' },
                    responsive: true,
                    autoWidth: false
                });
            }
            function cargarJurisdicciones() {
                $.ajax({
                    url: 'empresas_impuestos_config_ajax.php',
                    type: 'GET',
                    data: { accion: 'listar_jurisdicciones' },
                    dataType: 'json',
                    success: function(response) {
                        var select = $('#jurisdiccion_id');
                        select.empty();
                        select.append('<option value="">Seleccione una jurisdicción...</option>');
                        if (response && response.length > 0) {
                            $.each(response, function(index, jurisdiccion) {
                                select.append('<option value="' + jurisdiccion.jurisdiccion_id + '">' + 
                                    escapeHtml(jurisdiccion.jurisdiccion_nombre) + ' (' + escapeHtml(jurisdiccion.jurisdiccion_codigo) + ')</option>');
                            });
                        }
                    },
                    error: function(xhr, status, error) { console.error('Error al cargar jurisdicciones:', error); }
                });
            }

            function cargarCuentasContables() {
                $.ajax({
                    url: 'empresas_impuestos_config_ajax.php',
                    type: 'GET',
                    data: { accion: 'listar_cuentas_contables', empresa_idx: empresa_idx },
                    dataType: 'json',
                    success: function(response) {
                        var select = $('#cont_cuenta_id');
                        select.empty();
                        select.append('<option value="">Seleccione una cuenta contable...</option>');
                        if (response && response.length > 0) {
                            $.each(response, function(index, cuenta) {
                                var texto = cuenta.codigo + ' - ' + cuenta.nombre;
                                select.append('<option value="' + cuenta.cont_cuenta_id + '">' + escapeHtml(texto) + '</option>');
                            });
                        }
                    },
                    error: function(xhr, status, error) { console.error('Error al cargar cuentas contables:', error); }
                });
            }

            function cargarTiposBien() {
                console.log('Cargando tipos de bien...');
                $.ajax({
                    url: 'empresas_impuestos_config_ajax.php',
                    type: 'GET',
                    data: { accion: 'listar_tipos_bien' },
                    dataType: 'json',
                    success: function(response) {
                        var select = $('#producto_tipo_id');
                        select.empty();
                        // IMPORTANTE: El orden importa
                        select.append('<option value="">Seleccione un tipo de bien...</option>');
                        select.append('<option value="0">-- APLICA A TODOS LOS TIPOS DE BIEN --</option>');
                        
                        if (response && response.length > 0) {
                            $.each(response, function(index, tipo) {
                                select.append('<option value="' + tipo.producto_tipo_id + '">' + escapeHtml(tipo.producto_tipo) + '</option>');
                            });
                        }
                        
                        // Verificar que la opción "0" existe
                        console.log('Opciones cargadas:', select.find('option').length);
                        console.log('Opción valor 0 existe:', select.find('option[value="0"]').length > 0);
                    },
                    error: function(xhr, status, error) { 
                        console.error('Error al cargar tipos de bien:', error);
                        // Fallback: al menos mostrar la opción "todos"
                        var select = $('#producto_tipo_id');
                        select.empty();
                        select.append('<option value="">Seleccione un tipo de bien...</option>');
                        select.append('<option value="0">-- APLICA A TODOS LOS TIPOS DE BIEN --</option>');
                    }
                });
            }

            function cargarCondicionesFiscales() {
                console.log('Cargando condiciones fiscales...');
                $.ajax({
                    url: 'empresas_impuestos_config_ajax.php',
                    type: 'GET',
                    data: { accion: 'listar_condiciones_fiscales' },
                    dataType: 'json',
                    success: function(response) {
                        console.log('Condiciones fiscales recibidas:', response);
                        var select = $('#metodo_condicion_fiscal_id');
                        select.empty();
                        // Opción para aplicar a TODAS las condiciones fiscales (NULL en BD)
                        select.append('<option value="">-- TODAS LAS CONDICIONES FISCALES --</option>');
                        
                        if (response && response.length > 0) {
                            $.each(response, function(index, condicion) {
                                var texto = condicion.condicion_fiscal;
                                if (condicion.condicion_fiscal_codigo) {
                                    texto += ' (' + condicion.condicion_fiscal_codigo + ')';
                                }
                                select.append('<option value="' + condicion.condicion_fiscal_id + '">' + escapeHtml(texto) + '</option>');
                            });
                        } else {
                            console.warn('No se recibieron condiciones fiscales');
                            select.append('<option value="" disabled>No hay condiciones fiscales disponibles</option>');
                        }
                    },
                    error: function(xhr, status, error) { 
                        console.error('Error al cargar condiciones fiscales:', error);
                        $('#metodo_condicion_fiscal_id').empty().append('<option value="">-- ERROR AL CARGAR --</option>');
                    }
                });
            }

            // ==================== TABLA PRINCIPAL DE CONFIGURACIONES ====================
            
            function inicializarDataTableConfig() {
                if ($.fn.DataTable.isDataTable('#tablaImpuestosConfig')) {
                    $('#tablaImpuestosConfig').DataTable().destroy();
                    $('#tablaImpuestosConfig tbody').empty();
                }

                tablaConfig = $('#tablaImpuestosConfig').DataTable({
                    ajax: {
                        url: 'empresas_impuestos_config_ajax.php',
                        type: 'GET',
                        data: { accion: 'listar', empresa_idx: empresa_idx, pagina_idx: pagina_idx },
                        dataSrc: ''
                    },
                    responsive: true,
                    pageLength: 10,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                    columnDefs: [
                        { responsivePriority: 1, targets: 0 },
                        { responsivePriority: 2, targets: 1 },
                        { responsivePriority: 3, targets: 9 },
                        { className: 'text-center', targets: [0, 5, 6, 8, 9] }
                    ],
                    columns: [
                        { data: 'empresa_impuesto_config_id', className: 'text-center fw-bold' },
                        { data: 'impuesto_tipo', render: function (data, type, row) {
                            if (type === 'export') return data || '';
                            var badgeClass = 'bg-secondary';
                            if (row.es_retencion) badgeClass = 'bg-warning';
                            if (row.es_percepcion) badgeClass = 'bg-info';
                            return data ? `<span class="badge ${badgeClass}">${escapeHtml(data)}</span>` : '<span class="text-muted">-</span>';
                        } },
                        { data: 'jurisdiccion_nombre', render: function (data, type, row) {
                            if (type === 'export') return data || '';
                            return data ? escapeHtml(data) : '<span class="text-muted">-</span>';
                        } },
                        { data: 'cuenta_contable', render: function (data, type, row) {
                            if (type === 'export') return data || '';
                            return data ? `<span class="badge bg-info">${escapeHtml(data)}</span>` : '<span class="text-muted">-</span>';
                        } },
                        { data: 'tipo_calculo', render: function (data, type, row) {
                            if (type === 'export') return data || '';
                            var badgeClass = 'bg-primary';
                            if (data === 'manual') badgeClass = 'bg-success';
                            if (data === 'padron') badgeClass = 'bg-info';
                            if (data === 'regla') badgeClass = 'bg-warning';
                            var texto = data === 'manual' ? 'Manual' : (data === 'padron' ? 'Padrón' : 'Regla');
                            return data ? `<span class="badge ${badgeClass}">${texto}</span>` : '<span class="text-muted">-</span>';
                        } },
                        { data: 'aplica_siempre', className: 'text-center', render: function (data, type, row) {
                            if (type === 'export') return data == 1 ? 'Sí' : 'No';
                            return data == 1 ? '<i class="fas fa-check-circle text-success fa-lg" title="Aplica siempre"></i>' : '<i class="fas fa-times-circle text-danger fa-lg" title="No aplica siempre"></i>';
                        } },
                        { data: 'prioridad', className: 'text-center', render: function (data, type, row) {
                            if (type === 'export') return data || '';
                            return data ? `<span class="badge bg-secondary">${data}</span>` : '<span class="text-muted">-</span>';
                        } },
                        { data: 'f_desde', render: function (data, type, row) { return data ? escapeHtml(data) : '<span class="text-muted">-</span>'; } },
                        { data: 'f_hasta', render: function (data, type, row) { return data ? escapeHtml(data) : '<span class="text-muted">-</span>'; } },
                        { data: 'estado_info', className: 'text-center', render: function (data, type, row) {
                            if (!data || !data.estado_registro) {
                                if (type === 'export') return 'Sin estado';
                                return '<span class="fw-medium">Sin estado</span>';
                            }
                            var estado = data.estado_registro;
                            var badgeClass = data.bg_clase ? data.bg_clase.replace('bg-', '') : 'secondary';
                            if (type === 'export') return estado;
                            return `<span class="badge bg-${badgeClass}">${estado}</span>`;
                        } },
                        { data: 'botones', orderable: false, searchable: false, className: "text-center", width: '120px', render: function (data, type, row) {
                            if (type === 'export') return '';
                            var botones = '';
                            if (data && data.length > 0) {
                                var editarBoton = '', otrosBotones = '';
                                data.forEach(boton => {
                                    var claseBoton = 'btn-sm me-1 ';
                                    if (boton.bg_clase && boton.text_clase) {
                                        claseBoton += boton.bg_clase + ' ' + boton.text_clase;
                                    } else if (boton.color_clase) { claseBoton += boton.color_clase; }
                                    else { claseBoton += 'btn-outline-primary'; }
                                    var titulo = boton.descripcion || boton.nombre_funcion;
                                    var accionJs = boton.accion_js;
                                    var icono = boton.icono_clase ? `<i class="${boton.icono_clase}"></i>` : '';
                                    var esConfirmable = boton.es_confirmable || 0;
                                    var botonHtml = `<button type="button" class="btn ${claseBoton} btn-accion" title="${titulo}" data-id="${row.empresa_impuesto_config_id}" data-accion="${accionJs}" data-confirmable="${esConfirmable}" data-tipo="${escapeHtml(row.impuesto_tipo || 'Configuración')}">${icono}</button>`;
                                    if (accionJs === 'editar') { editarBoton = botonHtml; }
                                    else { otrosBotones += botonHtml; }
                                });
                                botones = editarBoton + otrosBotones;
                            } else { botones = '<span class="text-muted small">Sin acciones</span>'; }
                            return `<div class="btn-group" role="group" style="flex-wrap: wrap; gap: 4px;">${botones}</div>`;
                        } }
                    ],
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' },
                    responsive: true,
                    autoWidth: false
                });
            }

            // ==================== TABLA DE MÉTODOS (dentro del modal) ====================
            
            function inicializarTablaMetodos(configId) {
                if (tablaMetodos) {
                    tablaMetodos.destroy();
                    $('#tablaMetodos tbody').empty();
                }

                tablaMetodos = $('#tablaMetodos').DataTable({
                    ajax: {
                        url: 'empresas_impuestos_config_ajax.php',
                        type: 'GET',
                        data: { accion: 'listar_operaciones', empresa_idx: empresa_idx, config_id: configId },
                        dataSrc: ''
                    },
                    pageLength: 5,
                    lengthMenu: [[5, 10, 25, -1], [5, 10, 25, "Todos"]],
                    columnDefs: [
                        { className: 'text-center', targets: [0, 9] },
                        { className: 'text-end', targets: [4, 5, 6] }
                    ],
                    columns: [
                        { data: 'empresa_impuesto_config_operacion_id', className: 'text-center fw-bold' },
                        { data: 'producto_tipo', render: function(data, type, row) {
                            if (type === 'export') return data || '';
                            if (!data) return '<span class="text-muted">-</span>';
                            // Si es "TODOS LOS BIENES" mostrarlo destacado
                            if (data === 'TODOS LOS BIENES') {
                                return '<span class="badge bg-success">' + escapeHtml(data) + '</span>';
                            }
                            return '<span class="badge bg-primary">' + escapeHtml(data) + '</span>';
                        } },
                        { data: 'condicion_fiscal', render: function(data, type, row) {
                            if (type === 'export') return data || '';
                            return data ? `<span class="badge bg-secondary">${escapeHtml(data)}</span>` : '<span class="text-muted">-</span>';
                        } },
                        { data: 'base_calculo', render: function(data, type, row) {
                            if (type === 'export') return data || '';
                            return data ? `<span class="badge bg-info">${escapeHtml(data)}</span>` : '<span class="text-muted">-</span>';
                        } },
                        { data: 'alicuota', render: function(data, type, row) {
                            if (type === 'export') return data || '0';
                            return data && parseFloat(data) > 0 ? `${parseFloat(data).toFixed(2)}%` : '<span class="text-muted">-</span>';
                        } },
                        { data: 'minimo_imponible', render: function(data, type, row) {
                            if (type === 'export') return data || '0';
                            return parseFloat(data || 0).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        } },
                        { data: 'monto_fijo', render: function(data, type, row) {
                            if (type === 'export') return data || '0';
                            return parseFloat(data || 0).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                        } },
                        { data: 'f_desde', render: function(data, type, row) { return data ? escapeHtml(data) : '<span class="text-muted">-</span>'; } },
                        { data: 'f_hasta', render: function(data, type, row) { return data ? escapeHtml(data) : '<span class="text-muted">-</span>'; } },
                        { data: null, orderable: false, searchable: false, className: "text-center", width: '80px', render: function(data, type, row) {
                            if (type === 'export') return '';
                            return `<div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-primary btn-editar-metodo" data-id="${row.empresa_impuesto_config_operacion_id}" title="Editar"><i class="fas fa-edit"></i></button>
                                <button type="button" class="btn btn-outline-danger btn-eliminar-metodo" data-id="${row.empresa_impuesto_config_operacion_id}" data-nombre="${escapeHtml(row.producto_tipo || '')}" title="Eliminar"><i class="fas fa-trash"></i></button>
                            </div>`;
                        } }
                    ],
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' },
                    responsive: true,
                    autoWidth: false
                });
            }

            // ==================== EVENTOS GENERALES ====================
            
            function inicializarEventos() {
                $('#btnRecargar').off('click').on('click', function () {
                    var btn = $(this);
                    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                    tablaConfig.ajax.reload(function () {
                        btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
                    }, false);
                });
            }

            function cargarBotonAgregar() {
                $.get('empresas_impuestos_config_ajax.php', { accion: 'obtener_boton_agregar', pagina_idx: pagina_idx }, function (botonAgregar) {
                    if (botonAgregar && botonAgregar.nombre_funcion) {
                        var icono = botonAgregar.icono_clase ? `<i class="${botonAgregar.icono_clase} me-1"></i>` : '';
                        var colorClase = 'btn-primary';
                        if (botonAgregar.bg_clase && botonAgregar.text_clase) { colorClase = botonAgregar.bg_clase + ' ' + botonAgregar.text_clase; }
                        else if (botonAgregar.color_clase) { colorClase = botonAgregar.color_clase; }
                        $('#contenedor-boton-agregar').html(`<button type="button" class="btn ${colorClase}" id="btnNuevo">${icono}${botonAgregar.nombre_funcion}</button>`);
                    } else {
                        $('#contenedor-boton-agregar').html('<button type="button" class="btn btn-primary" id="btnNuevo"><i class="fas fa-plus me-1"></i>Agregar Configuración</button>');
                    }
                }, 'json');
            }

            // ==================== CRUD CONFIGURACIONES ====================
            
            $(document).on('click', '#btnNuevo', function () {
                resetModalConfig();
                currentConfigId = null;
                $('#modalLabel').text('Nueva Configuración de Impuesto');
                $('#aplica_siempre').prop('checked', true);
                $('#prioridad').val(1);
                $('#tipo_calculo').val('manual');
                var today = new Date().toISOString().split('T')[0];
                $('#f_desde').val(today);
                
                // Limpiar y deshabilitar la tabla de métodos en nuevo registro
                if (tablaMetodos) {
                    tablaMetodos.clear().draw();
                }
                $('#btnAgregarMetodo').prop('disabled', true);
                $('#metodosPanel .text-muted').html('<i class="fas fa-info-circle me-1"></i>Guarde la configuración primero para poder agregar métodos de cálculo.');
                
                var modal = new bootstrap.Modal(document.getElementById('modalImpuestoConfig'));
                modal.show();
                $('#impuesto_tipo_id').focus();
            });

            $(document).on('click', '.btn-accion', function () {
                var id = $(this).data('id');
                var accionJs = $(this).data('accion');
                var confirmable = $(this).data('confirmable');
                var nombre = $(this).data('tipo');

                if (accionJs === 'editar') {
                    cargarConfiguracionParaEditar(id);
                } else if (confirmable == 1) {
                    Swal.fire({
                        title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                        html: `¿Está seguro de <strong>${accionJs}</strong> la configuración de impuesto <strong>"${nombre}"</strong>?`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: `Sí, ${accionJs}`,
                        cancelButtonText: 'Cancelar',
                        reverseButtons: true,
                        allowOutsideClick: false
                    }).then((result) => {
                        if (result.isConfirmed) { ejecutarAccionConfig(id, accionJs, nombre); }
                    });
                } else {
                    ejecutarAccionConfig(id, accionJs, nombre);
                }
            });

            function ejecutarAccionConfig(id, accionJs, nombre) {
                $.post('empresas_impuestos_config_ajax.php', {
                    accion: 'ejecutar_accion',
                    empresa_impuesto_config_id: id,
                    accion_js: accionJs,
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                }, function (res) {
                    if (res.success) {
                        tablaConfig.ajax.reload(function () {
                            Swal.fire({ icon: "success", title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`, text: res.message || `Configuración "${nombre}" actualizada correctamente`, showConfirmButton: false, timer: 1500, toast: true, position: 'top-end' });
                        }, false);
                    } else {
                        Swal.fire({ icon: "error", title: "Error", text: res.error || `Error al ${accionJs} la configuración`, confirmButtonText: "Entendido" });
                    }
                }, 'json');
            }

            function cargarConfiguracionParaEditar(id) {
                $.get('empresas_impuestos_config_ajax.php', {
                    accion: 'obtener',
                    empresa_impuesto_config_id: id,
                    empresa_idx: empresa_idx
                }, function (res) {
                    if (res && res.empresa_impuesto_config_id) {
                        resetModalConfig();
                        currentConfigId = res.empresa_impuesto_config_id;
                        
                        $('#empresa_impuesto_config_id').val(res.empresa_impuesto_config_id);
                        $('#empresa_id').val(empresa_idx);
                        $('#impuesto_tipo_id').val(res.impuesto_tipo_id);
                        $('#jurisdiccion_id').val(res.jurisdiccion_id || '');
                        $('#cont_cuenta_id').val(res.cont_cuenta_id || '');
                        $('#tipo_calculo').val(res.tipo_calculo || 'manual');
                        $('#prioridad').val(res.prioridad || 1);
                        $('#f_desde').val(res.f_desde);
                        $('#f_hasta').val(res.f_hasta || '');
                        $('#aplica_siempre').prop('checked', res.aplica_siempre == 1);
                        $('#modalLabel').text('Editar Configuración de Impuesto');
                        
                        // Cargar métodos asociados
                        inicializarTablaMetodos(currentConfigId);
                        inicializarTablaSubgrupos(currentConfigId);
                        $('#btnAgregarSubgrupo').prop('disabled', false);
                        $('#btnAgregarMetodo').prop('disabled', false);
                        $('#metodosPanel .text-muted').html('<i class="fas fa-info-circle me-1"></i>Métodos de cálculo específicos por tipo de bien');
                        
                        
                        var modal = new bootstrap.Modal(document.getElementById('modalImpuestoConfig'));
                        modal.show();
                    } else {
                        Swal.fire({ icon: "error", title: "Error", text: "Error al obtener datos de la configuración", confirmButtonText: "Entendido" });
                    }
                }, 'json');
            }

            function resetModalConfig() {
                $('#formImpuestoConfig')[0].reset();
                $('#empresa_impuesto_config_id').val('');
                $('#formImpuestoConfig').removeClass('was-validated');
                $('#empresa_id').val(empresa_idx);
                $('#jurisdiccion_id').val('');
                $('#cont_cuenta_id').val('');
                $('#tipo_calculo').val('manual');
                $('#aplica_siempre').prop('checked', true);
                $('#prioridad').val(1);
                
                // Resetear pestaña a configuración general
                $('#configuracion-tab').tab('show');
                
                // Limpiar la tabla de métodos pero NO deshabilitar el botón
                if (tablaMetodos) {
                    tablaMetodos.clear().draw();
                }
                
                // Mostrar mensaje informativo pero NO deshabilitar el botón
                // Los métodos se podrán agregar después de guardar
                $('#btnAgregarMetodo').prop('disabled', true);
                $('#metodosPanel .text-muted').html('<i class="fas fa-info-circle me-1"></i>Guarde la configuración primero para poder agregar métodos de cálculo.');
            }

            $('#btnGuardar').click(function () {
                var form = document.getElementById('formImpuestoConfig');
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var id = $('#empresa_impuesto_config_id').val();
                var accionBackend = id ? 'editar' : 'agregar';
                var impuesto_tipo_id = $('#impuesto_tipo_id').val();
                var tipo_calculo = $('#tipo_calculo').val();
                var f_desde = $('#f_desde').val();

                if (!impuesto_tipo_id) {
                    $('#impuesto_tipo_id').addClass('is-invalid');
                    Swal.fire({ icon: "warning", title: "Validación", text: "Debe seleccionar un tipo de impuesto", confirmButtonText: "Entendido" });
                    return false;
                }
                if (!tipo_calculo) {
                    $('#tipo_calculo').addClass('is-invalid');
                    Swal.fire({ icon: "warning", title: "Validación", text: "Debe seleccionar un tipo de cálculo", confirmButtonText: "Entendido" });
                    return false;
                }
                if (!f_desde) {
                    $('#f_desde').addClass('is-invalid');
                    Swal.fire({ icon: "warning", title: "Validación", text: "La fecha de inicio es obligatoria", confirmButtonText: "Entendido" });
                    return false;
                }

                var btnGuardar = $(this);
                var originalText = btnGuardar.html();
                btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

                $.ajax({
                    url: 'empresas_impuestos_config_ajax.php',
                    type: 'POST',
                    data: {
                        accion: accionBackend,
                        empresa_impuesto_config_id: id,
                        empresa_id: empresa_idx,
                        impuesto_tipo_id: impuesto_tipo_id,
                        jurisdiccion_id: $('#jurisdiccion_id').val() || null,
                        cont_cuenta_id: $('#cont_cuenta_id').val() || null,
                        tipo_calculo: tipo_calculo,
                        prioridad: $('#prioridad').val() || 1,
                        f_desde: f_desde,
                        f_hasta: $('#f_hasta').val() || null,
                        aplica_siempre: $('#aplica_siempre').is(':checked') ? 1 : 0,
                        empresa_idx: empresa_idx,
                        pagina_idx: pagina_idx
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.resultado) {
                            tablaConfig.ajax.reload(function () {
                                btnGuardar.prop('disabled', false).html(originalText);
                                Swal.fire({ icon: "success", title: "¡Guardado!", text: "Configuración guardada correctamente", showConfirmButton: false, timer: 1500, toast: true, position: 'top-end' });
                                
                                // Si era nuevo, actualizar el ID y recargar métodos
                                if (!id && res.empresa_impuesto_config_id) {
                                    currentConfigId = res.empresa_impuesto_config_id;
                                    $('#empresa_impuesto_config_id').val(currentConfigId);
                                    
                                    // IMPORTANTE: Destruir la tabla existente antes de recrearla
                                    if (tablaMetodos) {
                                        tablaMetodos.destroy();
                                        tablaMetodos = null;
                                    }
                                    
                                    // Inicializar nueva tabla de métodos
                                    inicializarTablaMetodos(currentConfigId);
                                    // Inicializar tabla de subgrupos
                                    inicializarTablaSubgrupos(currentConfigId);
                                    $('#btnAgregarSubgrupo').prop('disabled', false);
                                    $('#btnAgregarMetodo').prop('disabled', false);
                                    $('#metodosPanel .text-muted').html('<i class="fas fa-info-circle me-1"></i>Métodos de cálculo específicos por tipo de bien');
                                }
                                
                                var modalEl = document.getElementById('modalImpuestoConfig');
                                var modal = bootstrap.Modal.getInstance(modalEl);
                                modal.hide();
                            }, false);
                        } else {
                            btnGuardar.prop('disabled', false).html(originalText);
                            Swal.fire({ icon: "error", title: "Error", text: res.error || "Error al guardar los datos", confirmButtonText: "Entendido" });
                        }
                    },
                    error: function(xhr, status, error) {
                        btnGuardar.prop('disabled', false).html(originalText);
                        Swal.fire({ icon: "error", title: "Error de conexión", text: "Error al comunicarse con el servidor.", confirmButtonText: "Entendido" });
                    }
                });
            });

            // ==================== CRUD MÉTODOS ====================
            
            $(document).on('click', '#btnAgregarMetodo', function () {
                if (!currentConfigId) {
                    Swal.fire({ icon: "warning", title: "Atención", text: "Debe guardar la configuración primero antes de agregar métodos.", confirmButtonText: "Entendido" });
                    return;
                }
                resetModalMetodo();
                $('#modalMetodoLabel').text('Nuevo Método de Cálculo');
                $('#metodo_config_id').val(currentConfigId);
                var today = new Date().toISOString().split('T')[0];
                $('#metodo_f_desde').val(today);
                var modal = new bootstrap.Modal(document.getElementById('modalMetodo'));
                modal.show();
                $('#producto_tipo_id').focus();
            });

            $(document).on('click', '.btn-editar-metodo', function () {
                var id = $(this).data('id');
                cargarMetodoParaEditar(id);
            });

            $(document).on('click', '.btn-eliminar-metodo', function () {
                var id = $(this).data('id');
                var nombre = $(this).data('nombre');
                var row = $(this).closest('tr'); // Guardar referencia a la fila
                
                console.log('Eliminar método - ID:', id, 'Nombre:', nombre); // Debug
                
                if (!id) {
                    Swal.fire({ 
                        icon: "error", 
                        title: "Error", 
                        text: "No se pudo identificar el método a eliminar", 
                        confirmButtonText: "Entendido" 
                    });
                    return;
                }
                
                Swal.fire({
                    title: '¿Eliminar método?',
                    html: `¿Está seguro de eliminar el método de cálculo para <strong>"${escapeHtml(nombre || 'este tipo de bien')}"</strong>?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Mostrar loading
                        Swal.fire({
                            title: 'Eliminando...',
                            text: 'Por favor espere',
                            allowOutsideClick: false,
                            didOpen: () => {
                                Swal.showLoading();
                            }
                        });
                        
                        $.ajax({
                            url: 'empresas_impuestos_config_ajax.php',
                            type: 'POST',
                            data: {
                                accion: 'eliminar_operacion',
                                empresa_impuesto_config_operacion_id: id,
                                empresa_idx: empresa_idx
                            },
                            dataType: 'json',
                            success: function(res) {
                                console.log('Respuesta eliminación:', res); // Debug
                                
                                if (res.success) {
                                    // Recargar la tabla de métodos
                                    if (tablaMetodos) {
                                        tablaMetodos.ajax.reload(function() {
                                            Swal.fire({ 
                                                icon: "success", 
                                                title: "Eliminado", 
                                                text: res.message || "Método de cálculo eliminado correctamente", 
                                                showConfirmButton: false, 
                                                timer: 1500, 
                                                toast: true, 
                                                position: 'top-end' 
                                            });
                                        }, false);
                                    } else {
                                        Swal.fire({ 
                                            icon: "success", 
                                            title: "Eliminado", 
                                            text: res.message || "Método de cálculo eliminado correctamente", 
                                            showConfirmButton: false, 
                                            timer: 1500 
                                        });
                                        // Recargar la página si no hay tabla
                                        location.reload();
                                    }
                                } else {
                                    Swal.fire({ 
                                        icon: "error", 
                                        title: "Error", 
                                        text: res.error || "Error al eliminar el método", 
                                        confirmButtonText: "Entendido" 
                                    });
                                }
                            },
                            error: function(xhr, status, error) {
                                console.error('Error en AJAX:', error);
                                console.error('Respuesta del servidor:', xhr.responseText);
                                Swal.fire({ 
                                    icon: "error", 
                                    title: "Error de conexión", 
                                    text: "Error al comunicarse con el servidor. Por favor, recargue la página y vuelva a intentar.", 
                                    confirmButtonText: "Entendido" 
                                });
                            }
                        });
                    }
                });
            });

            function resetModalMetodo() {
                $('#formMetodo')[0].reset();
                $('#empresa_impuesto_config_operacion_id').val('');
                $('#formMetodo').removeClass('was-validated');
                
                // NO resetear el combo de tipos de bien, mantener sus opciones
                // Solo establecer el valor por defecto como vacío
                $('#producto_tipo_id').val('');
                
                $('#metodo_condicion_fiscal_id').val('');
                $('#metodo_base_calculo').val('');
                $('#metodo_alicuota').val(0);
                $('#metodo_minimo_imponible').val(0);
                $('#metodo_monto_fijo').val(0);
            }

            function cargarMetodoParaEditar(id) {
                $.get('empresas_impuestos_config_ajax.php', {
                    accion: 'obtener_operacion',
                    empresa_impuesto_config_operacion_id: id,
                    empresa_idx: empresa_idx
                }, function (res) {
                    if (res && res.empresa_impuesto_config_operacion_id) {
                        resetModalMetodo();
                        $('#empresa_impuesto_config_operacion_id').val(res.empresa_impuesto_config_operacion_id);
                        $('#metodo_config_id').val(res.empresa_impuesto_config_id);
                        
                        // Manejar producto_tipo_id (puede ser 0 para "todos")
                        var producto_tipo_id = res.producto_tipo_id || 0;
                        $('#producto_tipo_id').val(producto_tipo_id);
                        
                        $('#metodo_condicion_fiscal_id').val(res.condicion_fiscal_id || '');
                        $('#metodo_base_calculo').val(res.base_calculo || '');
                        $('#metodo_alicuota').val(res.alicuota || 0);
                        $('#metodo_minimo_imponible').val(res.minimo_imponible || 0);
                        $('#metodo_monto_fijo').val(res.monto_fijo || 0);
                        $('#metodo_f_desde').val(res.f_desde);
                        $('#metodo_f_hasta').val(res.f_hasta || '');
                        $('#modalMetodoLabel').text('Editar Método de Cálculo');
                        var modal = new bootstrap.Modal(document.getElementById('modalMetodo'));
                        modal.show();
                    } else {
                        Swal.fire({ icon: "error", title: "Error", text: "Error al obtener datos", confirmButtonText: "Entendido" });
                    }
                }, 'json');
            }

            $('#btnGuardarMetodo').click(function () {
                var form = document.getElementById('formMetodo');
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var id = $('#empresa_impuesto_config_operacion_id').val();
                var accionBackend = id ? 'editar_operacion' : 'agregar_operacion';
                var config_id = $('#metodo_config_id').val();
                var producto_tipo_id = $('#producto_tipo_id').val();
                var f_desde = $('#metodo_f_desde').val();

                if (!config_id) {
                    Swal.fire({ icon: "warning", title: "Validación", text: "Configuración no válida", confirmButtonText: "Entendido" });
                    return false;
                }
                
                // Validar que se haya seleccionado algo (puede ser 0 que es válido)
                if (producto_tipo_id === null || producto_tipo_id === undefined || producto_tipo_id === '') {
                    Swal.fire({ icon: "warning", title: "Validación", text: "Debe seleccionar un tipo de bien", confirmButtonText: "Entendido" });
                    return false;
                }
                
                // El valor 0 es válido (todos los tipos)
                if (!f_desde) {
                    Swal.fire({ icon: "warning", title: "Validación", text: "La fecha de inicio es obligatoria", confirmButtonText: "Entendido" });
                    return false;
                }

                var btnGuardar = $(this);
                var originalText = btnGuardar.html();
                btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

                $.ajax({
                    url: 'empresas_impuestos_config_ajax.php',
                    type: 'POST',
                    data: {
                        accion: accionBackend,
                        empresa_impuesto_config_operacion_id: id,
                        empresa_impuesto_config_id: config_id,
                        producto_tipo_id: producto_tipo_id,
                        condicion_fiscal_id: $('#metodo_condicion_fiscal_id').val() || null,
                        base_calculo: $('#metodo_base_calculo').val() || null,
                        alicuota: $('#metodo_alicuota').val() || 0,
                        minimo_imponible: $('#metodo_minimo_imponible').val() || 0,
                        monto_fijo: $('#metodo_monto_fijo').val() || 0,
                        f_desde: f_desde,
                        f_hasta: $('#metodo_f_hasta').val() || null,
                        empresa_idx: empresa_idx
                    },
                    dataType: 'json',
                    success: function (res) {
                        if (res.success || res.resultado) {
                            if (tablaMetodos) { tablaMetodos.ajax.reload(); }
                            btnGuardar.prop('disabled', false).html(originalText);
                            Swal.fire({ icon: "success", title: "¡Guardado!", text: "Método de cálculo guardado correctamente", showConfirmButton: false, timer: 1500, toast: true, position: 'top-end' });
                            var modalEl = document.getElementById('modalMetodo');
                            var modal = bootstrap.Modal.getInstance(modalEl);
                            modal.hide();
                        } else {
                            btnGuardar.prop('disabled', false).html(originalText);
                            Swal.fire({ icon: "error", title: "Error", text: res.error || "Error al guardar los datos", confirmButtonText: "Entendido" });
                        }
                    },
                    error: function(xhr, status, error) {
                        btnGuardar.prop('disabled', false).html(originalText);
                        Swal.fire({ icon: "error", title: "Error de conexión", text: "Error al comunicarse con el servidor.", confirmButtonText: "Entendido" });
                    }
                });
            });

            // ==================== EXPORTACIONES ====================
            
            $('#btnExportarExcel').click(function (e) {
                e.preventDefault();
                var data = tablaConfig.rows().data().toArray();
                var exportData = data.map(row => ({
                    'ID': row.empresa_impuesto_config_id,
                    'Tipo Impuesto': row.impuesto_tipo || '',
                    'Jurisdicción': row.jurisdiccion_nombre || '',
                    'Cuenta Contable': row.cuenta_contable || '',
                    'Tipo Cálculo': row.tipo_calculo || '',
                    'Aplica Siempre': row.aplica_siempre == 1 ? 'Sí' : 'No',
                    'Prioridad': row.prioridad || '',
                    'Vigencia Desde': row.f_desde || '',
                    'Vigencia Hasta': row.f_hasta || '',
                    'Estado': row.estado_info?.estado_registro || ''
                }));
                var ws = XLSX.utils.json_to_sheet(exportData);
                var wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, 'ImpuestosConfig');
                XLSX.writeFile(wb, `ImpuestosConfig_${new Date().toISOString().slice(0,19)}.xlsx`);
            });

            $('#btnExportarPDF').click(function (e) {
                e.preventDefault();
                var printWindow = window.open('', '_blank');
                var content = '<html><head><title>Configuración de Impuestos</title>';
                content += '<style>table {border-collapse: collapse; width: 100%;} th, td {border: 1px solid #ddd; padding: 8px; text-align: left;} th {background-color: #f2f2f2;}</style>';
                content += '</head><body>';
                content += '<h2>Configuración de Impuestos por Empresa</h2>';
                content += '<tr>';
                content += '<thead><tr><th>ID</th><th>Tipo Impuesto</th><th>Jurisdicción</th><th>Cuenta Contable</th><th>Tipo Cálculo</th><th>Aplica</th><th>Prioridad</th><th>Desde</th><th>Hasta</th><th>Estado</th></thead><tbody>';
                var data = tablaConfig.rows().data().toArray();
                data.forEach(row => {
                    content += `<tr><td>${row.empresa_impuesto_config_id}</td><td>${escapeHtml(row.impuesto_tipo || '')}</td><td>${escapeHtml(row.jurisdiccion_nombre || '')}</td><td>${escapeHtml(row.cuenta_contable || '')}</td><td>${row.tipo_calculo || ''}</td><td>${row.aplica_siempre == 1 ? 'Sí' : 'No'}</td><td>${row.prioridad || ''}</td><td>${row.f_desde || ''}</td><td>${row.f_hasta || ''}</td><td>${row.estado_info?.estado_registro || ''}</td></tr>`;
                });
                content += '</tbody></table></body></html>';
                printWindow.document.write(content);
                printWindow.document.close();
                printWindow.print();
            });

            $('#btnExportarCSV').click(function (e) {
                e.preventDefault();
                var data = tablaConfig.rows().data().toArray();
                var csv = "ID,Tipo Impuesto,Jurisdicción,Cuenta Contable,Tipo Cálculo,Aplica Siempre,Prioridad,Vigencia Desde,Vigencia Hasta,Estado\n";
                data.forEach(row => {
                    csv += `"${row.empresa_impuesto_config_id}","${escapeCsv(row.impuesto_tipo || '')}","${escapeCsv(row.jurisdiccion_nombre || '')}","${escapeCsv(row.cuenta_contable || '')}","${escapeCsv(row.tipo_calculo || '')}","${row.aplica_siempre == 1 ? 'Sí' : 'No'}","${row.prioridad || ''}","${row.f_desde || ''}","${row.f_hasta || ''}","${escapeCsv(row.estado_info?.estado_registro || '')}"\n`;
                });
                var blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
                var link = document.createElement("a");
                var url = URL.createObjectURL(blob);
                link.setAttribute("href", url);
                link.setAttribute("download", `ImpuestosConfig_${new Date().toISOString().slice(0,19)}.csv`);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            });

            $('#btnExportarPrint').click(function (e) {
                e.preventDefault();
                window.print();
            });

            $(document).on('click', '#btnAgregarSubgrupo', function() {
                if (!currentConfigId) {
                    Swal.fire({ icon: "warning", title: "Atención", text: "Debe guardar la configuración primero antes de asignar subgrupos.", confirmButtonText: "Entendido" });
                    return;
                }
                
                $('#formAsignarSubgrupo')[0].reset();
                $('#formAsignarSubgrupo').removeClass('was-validated');
                cargarSubgruposDisponibles();
                
                var modal = new bootstrap.Modal(document.getElementById('modalAsignarSubgrupo'));
                modal.show();
            });

            $(document).on('click', '.btn-desasignar-subgrupo', function() {
                var id = $(this).data('id');
                var nombre = $(this).data('nombre');
                
                Swal.fire({
                    title: '¿Desasignar subgrupo?',
                    html: `¿Está seguro de desasignar el subgrupo <strong>"${escapeHtml(nombre)}"</strong> de esta configuración?`,
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    cancelButtonColor: '#3085d6',
                    confirmButtonText: 'Sí, desasignar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: 'empresas_impuestos_config_ajax.php',
                            type: 'POST',
                            data: {
                                accion: 'desasignar_subgrupo',
                                empresa_impuesto_config_subgrupo_id: id
                            },
                            dataType: 'json',
                            success: function(res) {
                                if (res.success) {
                                    if (tablaSubgrupos) {
                                        tablaSubgrupos.ajax.reload();
                                    }
                                    Swal.fire({ icon: "success", title: "Desasignado", text: res.message, showConfirmButton: false, timer: 1500, toast: true, position: 'top-end' });
                                } else {
                                    Swal.fire({ icon: "error", title: "Error", text: res.error || "Error al desasignar", confirmButtonText: "Entendido" });
                                }
                            },
                            error: function(xhr, status, error) {
                                Swal.fire({ icon: "error", title: "Error de conexión", text: "Error al comunicarse con el servidor.", confirmButtonText: "Entendido" });
                            }
                        });
                    }
                });
            });

            $('#btnGuardarAsignacion').click(function() {
                var form = document.getElementById('formAsignarSubgrupo');
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }
                
                var subgrupo_id = $('#comprobante_subgrupo_id').val();
                
                if (!subgrupo_id) {
                    Swal.fire({ icon: "warning", title: "Validación", text: "Debe seleccionar un subgrupo", confirmButtonText: "Entendido" });
                    return false;
                }
                
                var btnGuardar = $(this);
                var originalText = btnGuardar.html();
                btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Asignando...');
                
                $.ajax({
                    url: 'empresas_impuestos_config_ajax.php',
                    type: 'POST',
                    data: {
                        accion: 'asignar_subgrupo',
                        empresa_impuesto_config_id: currentConfigId,
                        comprobante_subgrupo_id: subgrupo_id
                    },
                    dataType: 'json',
                    success: function(res) {
                        if (res.success) {
                            if (tablaSubgrupos) {
                                tablaSubgrupos.ajax.reload();
                            }
                            btnGuardar.prop('disabled', false).html(originalText);
                            Swal.fire({ icon: "success", title: "Asignado", text: res.message, showConfirmButton: false, timer: 1500, toast: true, position: 'top-end' });
                            
                            var modalEl = document.getElementById('modalAsignarSubgrupo');
                            var modal = bootstrap.Modal.getInstance(modalEl);
                            modal.hide();
                        } else {
                            btnGuardar.prop('disabled', false).html(originalText);
                            Swal.fire({ icon: "error", title: "Error", text: res.error || "Error al asignar", confirmButtonText: "Entendido" });
                        }
                    },
                    error: function(xhr, status, error) {
                        btnGuardar.prop('disabled', false).html(originalText);
                        Swal.fire({ icon: "error", title: "Error de conexión", text: "Error al comunicarse con el servidor.", confirmButtonText: "Entendido" });
                    }
                });
            });

            // ==================== UTILIDADES ====================
            
            function escapeHtml(str) {
                if (!str) return '';
                return str.replace(/[&<>]/g, function(m) {
                    if (m === '&') return '&amp;';
                    if (m === '<') return '&lt;';
                    if (m === '>') return '&gt;';
                    return m;
                });
            }

            function escapeCsv(str) {
                if (!str) return '';
                return str.replace(/"/g, '""');
            }

            // ==================== INICIALIZACIÓN ====================
            
            inicializarDataTableConfig();
            cargarBotonAgregar();
            cargarTiposImpuesto();
            cargarJurisdicciones();
            cargarCuentasContables();
            cargarTiposBien();
            cargarCondicionesFiscales();
            inicializarEventos();
            
            $('[title]').tooltip({ trigger: 'hover', placement: 'top' });
        });
    </script>

    <!-- Librerías necesarias -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>