<?php
// Configuración de la página
$pageTitle = "Empresas - Gestión";
$currentPage = 'empresas';
$modulo_idx = 2;
$pagina_idx = 37;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <!-- Header de la Aplicación -->
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-sm-6">
                    <h3 class="mb-0 fw-bold">
                        <i class="fas fa-building text-primary me-2"></i>Empresas
                    </h3>
                    <small class="text-muted">Administración de empresas emisoras, datos fiscales y configuración general</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Seteo Empresas</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Empresas</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Contenido Principal -->
    <div class="app-content">
        <div class="container-fluid">

            <!-- Tarjetas de Resumen / Métricas Rápidas -->
            <div class="row g-3 mb-3">
                <div class="col-6 col-md-3">
                    <div class="card shadow-sm border-0 border-start border-4 border-primary h-100">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold d-block">Total Empresas</span>
                                <span class="fs-4 fw-bold text-dark" id="statTotalEmpresas">-</span>
                            </div>
                            <div class="rounded-circle bg-primary bg-opacity-10 p-3 text-primary">
                                <i class="fas fa-building fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card shadow-sm border-0 border-start border-4 border-success h-100">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold d-block">Empresas Activas</span>
                                <span class="fs-4 fw-bold text-success" id="statEmpresasActivas">-</span>
                            </div>
                            <div class="rounded-circle bg-success bg-opacity-10 p-3 text-success">
                                <i class="fas fa-check-circle fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card shadow-sm border-0 border-start border-4 border-info h-100">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold d-block">Con Condición Fiscal</span>
                                <span class="fs-4 fw-bold text-info" id="statConCondicion">-</span>
                            </div>
                            <div class="rounded-circle bg-info bg-opacity-10 p-3 text-info">
                                <i class="fas fa-file-invoice-dollar fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="card shadow-sm border-0 border-start border-4 border-warning h-100">
                        <div class="card-body p-3 d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted small text-uppercase fw-semibold d-block">Parámetros y Vigencias</span>
                                <a href="empresas_parametros.php" class="btn btn-sm btn-outline-warning mt-1 fw-bold">
                                    <i class="fas fa-sliders-h me-1"></i>Ir a Parámetros
                                </a>
                            </div>
                            <div class="rounded-circle bg-warning bg-opacity-10 p-3 text-warning">
                                <i class="fas fa-history fa-lg"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Card Principal con DataTable -->
            <div class="card card-outline card-primary shadow-sm mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h5 class="card-title fw-bold mb-0 text-dark">
                        <i class="fas fa-list me-2 text-primary"></i>Catálogo de Empresas Emisoras
                    </h5>
                    <div class="card-tools">
                        <a href="empresas_parametros.php" class="btn btn-sm btn-outline-secondary me-2">
                            <i class="fas fa-sliders-h me-1"></i>Historial de Parámetros
                        </a>
                        <button type="button" class="btn btn-sm btn-primary shadow-sm" id="btnNuevo">
                            <i class="fas fa-plus-circle me-1"></i>Nueva Empresa
                        </button>
                    </div>
                </div>
                <div class="card-body p-3">
                    <div class="table-responsive">
                        <table id="tablaempresas" class="table table-hover table-striped align-middle w-100">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 50px;">ID</th>
                                    <th>Razón Social</th>
                                    <th>Condición Fiscal</th>
                                    <th>Documento / CUIT</th>
                                    <th>Nro. IIBB</th>
                                    <th>Domicilio y Localidad</th>
                                    <th>Datos Fiscales Adicionales</th>
                                    <th style="width: 90px;">Estado</th>
                                    <th style="width: 120px;" class="text-center">Acciones</th>
                                </tr>
                                <tr class="filters">
                                    <th></th>
                                    <th><input type="text" class="form-control form-control-sm" placeholder="Filtrar Razón Social" /></th>
                                    <th><input type="text" class="form-control form-control-sm" placeholder="Filtrar Condición" /></th>
                                    <th><input type="text" class="form-control form-control-sm" placeholder="Filtrar CUIT" /></th>
                                    <th><input type="text" class="form-control form-control-sm" placeholder="Filtrar IIBB" /></th>
                                    <th><input type="text" class="form-control form-control-sm" placeholder="Filtrar Domicilio/Loc." /></th>
                                    <th><input type="text" class="form-control form-control-sm" placeholder="Filtrar Leyenda" /></th>
                                    <th><input type="text" class="form-control form-control-sm" placeholder="Filtrar Estado" /></th>
                                    <th></th>
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

<!-- Modal Empresa (Formulario Completo y Organizado) -->
<div class="modal fade" id="modalempresa" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content shadow border-0">
            <div class="modal-header bg-primary text-white py-3">
                <h5 class="modal-title fw-bold" id="modalLabel">
                    <i class="fas fa-building me-2"></i>Empresa
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4">
                <form id="formempresa" novalidate>
                    <input type="hidden" id="empresa_id" name="empresa_id" />

                    <!-- Pestañas de Navegación del Modal -->
                    <ul class="nav nav-pills nav-fill mb-3 p-1 bg-light rounded" id="empresaTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active fw-bold py-2" id="tab-generales-tab" data-bs-toggle="pill" data-bs-target="#tab-generales" type="button" role="tab">
                                <i class="fas fa-id-card me-2"></i>1. Datos Generales
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link fw-bold py-2" id="tab-fiscales-tab" data-bs-toggle="pill" data-bs-target="#tab-fiscales" type="button" role="tab">
                                <i class="fas fa-file-invoice-dollar me-2 text-primary"></i>2. Datos Fiscales Principales
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="empresaTabsContent">
                        
                        <!-- TAB 1: DATOS GENERALES -->
                        <div class="tab-pane fade show active" id="tab-generales" role="tabpanel">
                            <div class="row g-3">
                                <div class="col-md-12">
                                    <label class="form-label fw-bold" for="empresa">
                                        Razón Social / Nombre Comercial <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-building text-muted"></i></span>
                                        <input type="text" class="form-control" id="empresa" name="empresa" required placeholder="Ej: Casa Lucho S.A." />
                                    </div>
                                    <div class="invalid-feedback">Por favor ingrese la Razón Social.</div>
                                </div>

                                <div class="col-md-4">
                                    <label class="form-label fw-bold" for="documento_tipo_id">Tipo Documento</label>
                                    <select class="form-select" id="documento_tipo_id" name="documento_tipo_id">
                                        <!-- Opciones cargadas por JS -->
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label class="form-label fw-bold" for="documento_numero">
                                        Número de CUIT / Documento <span class="text-danger">*</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-hashtag text-muted"></i></span>
                                        <input type="text" class="form-control font-monospace" id="documento_numero" name="documento_numero" required placeholder="Ej: 30541512590" />
                                    </div>
                                    <div class="invalid-feedback">Por favor ingrese el número de CUIT.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold" for="telefono">Teléfono / Contacto</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone text-muted"></i></span>
                                        <input type="text" class="form-control" id="telefono" name="telefono" placeholder="Ej: 0291-4556677" />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold" for="email">Correo Electrónico (Email)</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope text-muted"></i></span>
                                        <input type="email" class="form-control" id="email" name="email" placeholder="contacto@empresa.com" />
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold" for="domicilio">Domicilio Fiscal / Comercial</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-map-marker-alt text-muted"></i></span>
                                        <input type="text" class="form-control" id="domicilio" name="domicilio" placeholder="Calle, número, piso, depto." />
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold" for="localidad_id">
                                        Localidad <span class="text-danger">*</span>
                                    </label>
                                    <select class="form-select" id="localidad_id" name="localidad_id" required>
                                        <option value="">Cargando localidades...</option>
                                    </select>
                                    <div class="invalid-feedback">Por favor seleccione la localidad.</div>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label fw-bold" for="estado_registro_id">Estado del Registro</label>
                                    <select class="form-select" id="estado_registro_id" name="estado_registro_id">
                                        <option value="1">1 - Activo</option>
                                        <option value="2">2 - Inactivo / Inhabilitado</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-bold" for="base_conf">Identificador de Configuración</label>
                                    <input type="number" class="form-control" id="base_conf" name="base_conf" placeholder="Opcional" />
                                </div>
                            </div>
                        </div>

                        <!-- TAB 2: DATOS FISCALES PRINCIPALES -->
                        <div class="tab-pane fade" id="tab-fiscales" role="tabpanel">
                            
                            <div class="alert alert-light border border-primary border-opacity-25 rounded-3 p-3 mb-3">
                                <div class="d-flex align-items-start">
                                    <i class="fas fa-info-circle text-primary fs-4 me-3 mt-1"></i>
                                    <div>
                                        <h6 class="fw-bold mb-1 text-primary">Configuración Fiscal de Emisión</h6>
                                        <p class="small text-muted mb-0">
                                            Estos datos son utilizados por el motor de facturación fiscal para emitir comprobantes electrónicos y definir relaciones fiscales. Los cambios registrados aquí se sincronizan automáticamente con el historial de <a href="empresas_parametros.php" target="_blank" class="fw-bold text-decoration-none">Parámetros de Empresa</a>.
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div class="card border border-light-subtle shadow-sm mb-3">
                                <div class="card-body p-3">
                                    <div class="row g-3">
                                        
                                        <!-- Condición Fiscal -->
                                        <div class="col-md-12">
                                            <label class="form-label fw-bold" for="condicion_fiscal_id">
                                                <i class="fas fa-certificate text-warning me-1"></i>Condición Fiscal Emisora (Nivel Nacional - IVA)
                                            </label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-white"><i class="fas fa-landmark text-primary"></i></span>
                                                <select class="form-select form-select-lg fw-bold text-primary" id="condicion_fiscal_id" name="condicion_fiscal_id">
                                                    <option value="">Seleccionar condición fiscal...</option>
                                                </select>
                                            </div>
                                            <small class="text-muted d-block mt-1">
                                                Determina el tipo de comprobantes que la empresa puede emitir (Facturas A, B, C, etc.)
                                            </small>
                                        </div>

                                        <!-- Régimen de IIBB -->
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold" for="iibb_regimen_id">
                                                <i class="fas fa-map-pin text-success me-1"></i>Régimen de IIBB (Nivel Provincial)
                                            </label>
                                            <select class="form-select" id="iibb_regimen_id" name="iibb_regimen_id">
                                                <option value="">Seleccionar régimen de IIBB...</option>
                                            </select>
                                            <div id="help_conf_iibb_regimen" class="text-muted small mt-1"></div>
                                        </div>

                                        <!-- Sede Principal / Jurisdicción Sede -->
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold" for="jurisdiccion_sede_id">
                                                <i class="fas fa-map-marked-alt text-primary me-1"></i>Sede Principal / Jurisdicción (COMARB)
                                            </label>
                                            <select class="form-select" id="jurisdiccion_sede_id" name="jurisdiccion_sede_id">
                                                <option value="">Seleccionar jurisdicción sede...</option>
                                            </select>
                                            <small class="text-muted">Código ARCA / COMARB (ej. 901 CABA, 902 Bs. As.)</small>
                                        </div>

                                        <!-- Nro. IIBB -->
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold" for="ingresos_brutos_nro">
                                                <i class="fas fa-file-invoice text-primary me-1"></i>Nro. Ingresos Brutos (IIBB)
                                            </label>
                                            <div class="input-group">
                                                <input type="text" class="form-control font-monospace" id="ingresos_brutos_nro" name="ingresos_brutos_nro" placeholder="Ej: 30541512590" />
                                                <button class="btn btn-outline-secondary btn-sm" type="button" id="btnCopiarCuitAIibb" title="Copiar mismo valor del CUIT">
                                                    <i class="fas fa-copy me-1"></i>Copiar CUIT
                                                </button>
                                            </div>
                                            <small class="text-muted">Número local provincial o número de CUIT / Convenio.</small>
                                        </div>

                                        <!-- Inicio Actividades -->
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold" for="inicio_actividades">
                                                <i class="far fa-calendar-alt text-success me-1"></i>Inicio de Actividades
                                            </label>
                                            <input type="date" class="form-control" id="inicio_actividades" name="inicio_actividades" />
                                            <small class="text-muted">Fecha de inicio registrada ante AFIP/ARCA.</small>
                                        </div>

                                        <!-- Leyenda Fiscal -->
                                        <div class="col-md-12">
                                            <label class="form-label fw-bold" for="leyenda_fiscal">
                                                <i class="fas fa-heading text-secondary me-1"></i>Leyenda Fiscal
                                            </label>
                                            <input type="text" class="form-control" id="leyenda_fiscal" name="leyenda_fiscal" placeholder="Ej: IVA Responsable Inscripto" />
                                            <small class="text-muted">Texto impreso en el encabezado de comprobantes oficiales.</small>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            <div class="text-muted small">
                                <i class="fas fa-history text-secondary me-1"></i>
                                Para gestionar puntos de venta por defecto, listas de precio o períodos históricos, utilice la sección <a href="empresas_parametros.php" target="_blank" class="fw-bold">Parámetros Generales de Empresa</a>.
                            </div>

                        </div>

                    </div>
                </form>
            </div>
            <div class="modal-footer bg-light py-2">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-1"></i>Cancelar
                </button>
                <button type="button" id="btnGuardar" class="btn btn-primary px-4 fw-bold">
                    <i class="fas fa-save me-1"></i>Guardar Empresa
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Funciones de carga de catálogos
function cargarLocalidades(selectedId = null, callback = null) {
    $.ajax({
        url: 'empresas_ajax.php',
        type: 'GET',
        data: {accion: 'obtener_localidades'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Seleccionar localidad...</option>';
                $.each(res, function(index, localidad) {
                    var selected = (selectedId == localidad.localidad_id) ? 'selected' : '';
                    options += `<option value="${localidad.localidad_id}" ${selected}>${localidad.localidad}</option>`;
                });
                $('#localidad_id').html(options);
                if (typeof callback === 'function') callback();
            }
        },
        error: function() {
            $('#localidad_id').html('<option value="">Error al cargar localidades</option>');
        }
    });
}

function cargarDocumentosTipos(selectedId = null, callback = null) {
    $.ajax({
        url: 'empresas_ajax.php',
        type: 'GET',
        data: {accion: 'obtener_documentos_tipos'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '';
                $.each(res, function(index, dt) {
                    var selected = (selectedId == dt.documento_tipo_id) ? 'selected' : '';
                    options += `<option value="${dt.documento_tipo_id}" ${selected}>${dt.documento_tipo}</option>`;
                });
                $('#documento_tipo_id').html(options);
                if (typeof callback === 'function') callback();
            }
        }
    });
}

function cargarCondicionesFiscales(selectedId = null, callback = null) {
    $.ajax({
        url: 'empresas_ajax.php',
        type: 'GET',
        data: {accion: 'obtener_condiciones_fiscales'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Seleccionar condición fiscal...</option>';
                $.each(res, function(index, cf) {
                    var selected = (selectedId == cf.condicion_fiscal_id) ? 'selected' : '';
                    var codigo = cf.condicion_fiscal_codigo ? ' (' + cf.condicion_fiscal_codigo + ')' : '';
                    options += `<option value="${cf.condicion_fiscal_id}" ${selected}>${cf.condicion_fiscal}${codigo}</option>`;
                });
                $('#condicion_fiscal_id').html(options);
                if (typeof callback === 'function') callback();
            }
        },
        error: function() {
            $('#condicion_fiscal_id').html('<option value="">Error al cargar condiciones fiscales</option>');
        }
    });
}

function cargarIibbRegimenes(selectedId = null, callback = null) {
    $.ajax({
        url: 'empresas_ajax.php',
        type: 'GET',
        data: {accion: 'obtener_iibb_regimenes'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Seleccionar régimen de IIBB...</option>';
                $.each(res, function(index, reg) {
                    var selected = (selectedId == reg.iibb_regimen_id) ? 'selected' : '';
                    options += `<option value="${reg.iibb_regimen_id}" data-desc="${reg.descripcion || ''}" ${selected}>${reg.iibb_regimen_nombre}</option>`;
                });
                $('#iibb_regimen_id').html(options);
                actualizarConfDescripcionRegimen();
                if (typeof callback === 'function') callback();
            }
        }
    });
}

function cargarJurisdiccionesSedes(selectedId = null, callback = null) {
    $.ajax({
        url: 'empresas_ajax.php',
        type: 'GET',
        data: {accion: 'obtener_jurisdicciones_sedes'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Seleccionar jurisdicción sede...</option>';
                $.each(res, function(index, s) {
                    var selected = (selectedId == s.jurisdiccion_sede_id) ? 'selected' : '';
                    var org = s.organismo ? ` (${s.organismo})` : '';
                    options += `<option value="${s.jurisdiccion_sede_id}" ${selected}>${s.codigo_comarb} - ${s.jurisdiccion_sede}${org}</option>`;
                });
                $('#jurisdiccion_sede_id').html(options);
                if (typeof callback === 'function') callback();
            }
        }
    });
}

function actualizarConfDescripcionRegimen() {
    var desc = $('#iibb_regimen_id option:selected').data('desc') || '';
    if (desc) {
        $('#help_conf_iibb_regimen').html('<i class="fas fa-info-circle me-1 text-success"></i>' + desc).show();
    } else {
        $('#help_conf_iibb_regimen').empty().hide();
    }
}

$('#iibb_regimen_id').on('change', actualizarConfDescripcionRegimen);

function formatoFechaDMA(str) {
    if (!str) return '-';
    var p = str.split('-');
    return (p.length === 3) ? (p[2] + '/' + p[1] + '/' + p[0]) : str;
}

$(document).ready(function(){
    cargarLocalidades();
    cargarDocumentosTipos();
    cargarCondicionesFiscales();
    cargarIibbRegimenes();
    cargarJurisdiccionesSedes();

    // Configuración de DataTable con filtros por columna
    var tabla = $('#tablaempresas').DataTable({
        dom: '<"row mb-3"<"col-12 col-md-6"B><"col-12 col-md-6 text-end"f>>rt<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>',
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel me-1"></i> Excel',
                titleAttr: 'Exportar a Excel',
                className: 'btn btn-success btn-sm me-2 rounded-2',
                exportOptions: { columns: ':visible' }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="fas fa-file-pdf me-1"></i> PDF',
                titleAttr: 'Exportar a PDF',
                className: 'btn btn-danger btn-sm rounded-2',
                orientation: 'landscape',
                pageSize: 'A4',
                exportOptions: { columns: ':visible' }
            }
        ],
        ajax: {
            url: 'empresas_ajax.php?accion=listar',
            dataSrc: function(json) {
                var data = json || [];
                // Actualizar tarjetas de resumen
                $('#statTotalEmpresas').text(data.length);
                var activas = data.filter(function(e){ return e.tabla_estado_registro_id == 1; }).length;
                $('#statEmpresasActivas').text(activas);
                var conCf = data.filter(function(e){ return e.condicion_fiscal_id > 0; }).length;
                $('#statConCondicion').text(conCf);
                return data;
            }
        },
        initComplete: function() {
            this.api().columns().every(function() {
                var column = this;
                var colIdx = column.index();
                if (colIdx === 8 || colIdx === 0) return;
                $('input', $('.filters th').eq(colIdx)).on('keyup change clear', function() {
                    if (column.search() !== this.value) {
                        column.search(this.value).draw();
                    }
                });
            });
        },
        language: {
            "lengthMenu": "Mostrar _MENU_ registros por página",
            "zeroRecords": "No se encontraron empresas registradas",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ empresas",
            "infoEmpty": "No hay registros disponibles",
            "infoFiltered": "(filtrado de _MAX_ totales)",
            "search": "Buscar:",
            "paginate": {
                "first": "Primero",
                "last": "Último",
                "next": "Siguiente",
                "previous": "Anterior"
            }
        },
        columns: [
            { 
                data: 'empresa_id',
                className: 'text-center fw-bold text-muted',
                render: function(data) {
                    return `<span class="badge bg-light text-dark border">#${data}</span>`;
                }
            },
            { 
                data: 'empresa',
                className: 'fw-bold',
                render: function(data, type, row) {
                    return `<i class="fas fa-building text-primary me-2"></i>${data || '-'}`;
                }
            },
            { 
                data: 'condicion_fiscal',
                render: function(data, type, row) {
                    if (!data) return '<span class="badge bg-secondary">Sin definir</span>';
                    var cod = row.condicion_fiscal_codigo ? ` <span class="badge bg-light text-dark border ms-1">${row.condicion_fiscal_codigo}</span>` : '';
                    return `<span class="badge bg-primary text-white">${data}</span>${cod}`;
                }
            },
            { 
                data: 'documento_numero',
                render: function(data, type, row) {
                    var tipo = row.documento_tipo || 'CUIT';
                    return `<div><span class="badge bg-light text-muted border me-1">${tipo}</span><span class="font-monospace fw-semibold">${data || '-'}</span></div>`;
                }
            },
            { 
                data: 'ingresos_brutos_nro',
                render: function(data, type, row) {
                    var num = data ? `<span class="font-monospace small fw-bold">${data}</span>` : '<span class="text-muted small">No reg.</span>';
                    var regBadge = '';
                    if (row.iibb_regimen_id == 1) {
                        regBadge = '<span class="badge bg-primary mb-1" style="font-size:0.7rem;">Local</span>';
                    } else if (row.iibb_regimen_id == 2) {
                        regBadge = '<span class="badge mb-1 text-white" style="background-color:#6f42c1;font-size:0.7rem;">Convenio</span>';
                    } else if (row.iibb_regimen_id == 3) {
                        regBadge = '<span class="badge bg-warning text-dark mb-1" style="font-size:0.7rem;">Exento</span>';
                    }
                    var sede = row.codigo_comarb ? `<br><small class="text-muted"><span class="badge bg-light text-dark border">${row.codigo_comarb}</span> ${row.jurisdiccion_sede || ''}</small>` : '';
                    return `<div>${regBadge} ${num}${sede}</div>`;
                }
            },
            { 
                data: 'domicilio',
                render: function(data, type, row) {
                    var loc = row.localidad ? `<br><small class="text-muted"><i class="fas fa-map-pin me-1 text-danger"></i>${row.localidad}</small>` : '';
                    return `<div>${data || '-'}${loc}</div>`;
                }
            },
            { 
                data: null,
                render: function(data, type, row) {
                    var inicio = row.inicio_actividades ? `<i class="far fa-calendar-alt text-success me-1"></i>${formatoFechaDMA(row.inicio_actividades)}` : '<span class="text-muted">Inicio: -</span>';
                    var leyenda = row.leyenda_fiscal ? `<div class="text-truncate text-muted small" style="max-width: 180px;" title="${row.leyenda_fiscal}"><i class="fas fa-quote-left text-muted me-1"></i>${row.leyenda_fiscal}</div>` : '';
                    return `<div class="small">${inicio}${leyenda}</div>`;
                }
            },
            { 
                data: 'tabla_estado_registro_id',
                className: 'text-center',
                render: function(data, type, row) {
                    if (data == 1) {
                        return '<span class="badge bg-success"><i class="fas fa-check me-1"></i>Activo</span>';
                    } else {
                        return '<span class="badge bg-danger"><i class="fas fa-ban me-1"></i>Inactivo</span>';
                    }
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: "text-center",
                render: function(data, type, row) {
                    return `
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-primary btnEditar" data-id="${row.empresa_id}" title="Editar Empresa y Datos Fiscales">
                                <i class="fas fa-pencil-alt"></i>
                            </button>
                            <a href="empresas_parametros.php?empresa_id=${row.empresa_id}" class="btn btn-sm btn-outline-warning" title="Gestionar Parámetros y Vigencias">
                                <i class="fas fa-sliders-h"></i>
                            </a>
                            <button class="btn btn-sm btn-outline-danger btnEliminar" data-id="${row.empresa_id}" data-nombre="${row.empresa}" title="Eliminar">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    `;
                }
            }
        ]
    });

    // Copiar CUIT a IIBB
    $('#btnCopiarCuitAIibb').click(function(){
        var cuit = $('#documento_numero').val();
        if (cuit) {
            $('#ingresos_brutos_nro').val(cuit);
        }
    });

    // Sugerir Leyenda Fiscal al cambiar condición fiscal
    $('#condicion_fiscal_id').change(function(){
        var cfTexto = $(this).find('option:selected').text();
        if (cfTexto && !$('#leyenda_fiscal').val()) {
            var limpio = cfTexto.split('(')[0].trim();
            $('#leyenda_fiscal').val('IVA ' + limpio);
        }
    });

    // Botón Nueva Empresa
    $('#btnNuevo').click(function(){
        $('#formempresa')[0].reset();
        $('#empresa_id').val('');
        $('#formempresa').removeClass('was-validated');
        $('#tab-generales-tab').tab('show');

        cargarDocumentosTipos(1);
        cargarLocalidades(null);
        cargarCondicionesFiscales(null);
        cargarIibbRegimenes(1);
        cargarJurisdiccionesSedes(2);

        $('#modalLabel').html('<i class="fas fa-plus-circle me-2"></i>Nueva Empresa');
        var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalempresa'));
        modal.show();
    });

    // Botón Editar Empresa
    $('#tablaempresas tbody').on('click', '.btnEditar', function(){
        var id = $(this).data('id');
        $.get('empresas_ajax.php', {accion: 'obtener', empresa_id: id}, function(res){
            if(res){
                $('#formempresa')[0].reset();
                $('#formempresa').removeClass('was-validated');
                $('#tab-generales-tab').tab('show');

                $('#empresa_id').val(res.empresa_id);
                $('#empresa').val(res.empresa || '');
                $('#documento_numero').val(res.documento_numero || '');
                $('#telefono').val(res.telefono || '');
                $('#domicilio').val(res.domicilio || '');
                $('#email').val(res.email || '');
                $('#base_conf').val(res.base_conf || '');
                $('#estado_registro_id').val(res.tabla_estado_registro_id || 1);

                // Campos fiscales principales
                $('#ingresos_brutos_nro').val(res.ingresos_brutos_nro || '');
                $('#inicio_actividades').val(res.inicio_actividades || '');
                $('#leyenda_fiscal').val(res.leyenda_fiscal || '');

                cargarDocumentosTipos((res.documento_tipo_id && parseInt(res.documento_tipo_id) > 0) ? res.documento_tipo_id : 1);
                cargarLocalidades(res.localidad_id);
                cargarCondicionesFiscales(res.condicion_fiscal_id);
                cargarIibbRegimenes(res.iibb_regimen_id || 1);
                cargarJurisdiccionesSedes(res.jurisdiccion_sede_id || 2);

                $('#modalLabel').html('<i class="fas fa-edit me-2"></i>Editar Empresa: ' + (res.empresa || ''));
                var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalempresa'));
                modal.show();
            } else {
                Swal.fire('Error', 'No se pudieron obtener los datos de la empresa', 'error');
            }
        }, 'json');
    });

    // Botón Eliminar Empresa
    $('#tablaempresas tbody').on('click', '.btnEliminar', function(){
        var id = $(this).data('id');
        var nombre = $(this).data('nombre');
        
        Swal.fire({
            title: "¿Eliminar Empresa?",
            text: `¿Confirma que desea eliminar "${nombre}"? Esta acción no se puede deshacer.`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#6c757d",
            confirmButtonText: "Sí, eliminar",
            cancelButtonText: "Cancelar"
        }).then((result) => {
            if (result.isConfirmed) {
                $.get('empresas_ajax.php', {accion: 'eliminar', empresa_id: id}, function(res){
                    if(res.resultado){
                        tabla.ajax.reload();
                        Swal.fire({
                            icon: "success",
                            title: "Empresa eliminada",
                            timer: 1500,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire('Error', 'No se pudo eliminar la empresa. Es posible que tenga comprobantes vinculados.', 'error');
                    }
                }, 'json');
            }
        });
    });

    // Botón Guardar Empresa
    $('#btnGuardar').click(function(){
        var form = $('#formempresa')[0];
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            // Si el error está en la primera solapa, activarla
            if ($('#empresa:invalid, #documento_numero:invalid, #localidad_id:invalid').length > 0) {
                $('#tab-generales-tab').tab('show');
            }
            return false;
        }
        
        var id = $('#empresa_id').val();
        var accion = id ? 'editar' : 'agregar';
        
        var formData = {
            accion: accion,
            empresa_id: id,
            empresa: $('#empresa').val(),
            documento_tipo_id: $('#documento_tipo_id').val(),
            documento_numero: $('#documento_numero').val(),
            telefono: $('#telefono').val(),
            domicilio: $('#domicilio').val(),
            localidad_id: $('#localidad_id').val(),
            condicion_fiscal_id: $('#condicion_fiscal_id').val(),
            iibb_regimen_id: $('#iibb_regimen_id').val(),
            jurisdiccion_sede_id: $('#jurisdiccion_sede_id').val(),
            ingresos_brutos_nro: $('#ingresos_brutos_nro').val(),
            inicio_actividades: $('#inicio_actividades').val(),
            leyenda_fiscal: $('#leyenda_fiscal').val(),
            email: $('#email').val(),
            base_conf: $('#base_conf').val(),
            tabla_estado_registro_id: $('#estado_registro_id').val()
        };

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.post('empresas_ajax.php', formData, function(res){
            $btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Empresa');
            if(res.resultado){
                tabla.ajax.reload();
                var modal = bootstrap.Modal.getInstance(document.getElementById('modalempresa'));
                if (modal) modal.hide();
                Swal.fire({
                    icon: "success",
                    title: id ? "Empresa y Parámetros Fiscales Actualizados" : "Empresa Creada con Éxito",
                    timer: 1600,
                    showConfirmButton: false
                });
            } else {
                Swal.fire('Error', res.error || 'Ocurrió un error al guardar los datos.', 'error');
            }
        }, 'json').fail(function(){
            $btn.prop('disabled', false).html('<i class="fas fa-save me-1"></i>Guardar Empresa');
            Swal.fire('Error de red', 'No se pudo comunicar con el servidor.', 'error');
        });
    });
});
</script>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
