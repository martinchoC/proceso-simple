<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Gestión de Entidades";
$currentPage = 'entidades';
$modudo_idx = 5;
$pagina_idx = 550; // ID de página para entidades

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-building me-2"></i>Gestión de Entidades
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Gestión</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Entidades</li>
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
                                        <table id="tablaEntidades" class="table table-striped table-bordered"
                                            style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="80">ID</th>
                                                    <th width="150">Nombre</th>
                                                    <th width="150">Fantasia</th>
                                                    <th width="100">Tipo</th>
                                                    <th width="120">CUIT</th>
                                                    <th width="160">Condición Fiscal</th>
                                                    <th width="80">Proveedor</th>
                                                    <th width="80">Cliente</th>
                                                    <th width="120">Estado</th>
                                                    <th width="200" class="text-center">Acciones</th>
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

            <!-- Modal para crear/editar entidad -->
            <div class="modal fade" id="modalEntidad" tabindex="-1" aria-labelledby="modalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-xl modal-ancho">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Entidad</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <!-- Pestañas -->
                            <ul class="nav nav-tabs" id="entidadTabs" role="tablist">
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link active" id="datos-tab" data-bs-toggle="tab" 
                                            data-bs-target="#datos" type="button" role="tab" 
                                            aria-controls="datos" aria-selected="true">
                                        <i class="fas fa-info-circle me-1"></i>Datos Principales
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="sucursales-tab" data-bs-toggle="tab" 
                                            data-bs-target="#sucursales" type="button" role="tab" 
                                            aria-controls="sucursales" aria-selected="false">
                                        <i class="fas fa-store me-1"></i>Sucursales
                                        <span id="contador-sucursales" class="badge bg-secondary ms-1">0</span>
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="condiciones-clientes-tab" data-bs-toggle="tab" 
                                            data-bs-target="#condiciones-clientes" type="button" role="tab" 
                                            aria-controls="condiciones-clientes" aria-selected="false">
                                        <i class="fas fa-user-tie me-1"></i>Condiciones Clientes
                                        
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="condiciones-proveedores-tab" data-bs-toggle="tab" 
                                            data-bs-target="#condiciones-proveedores" type="button" role="tab" 
                                            aria-controls="condiciones-proveedores" aria-selected="false">
                                        <i class="fas fa-truck me-1"></i>Condiciones Proveedores
                                        
                                    </button>
                                </li>
                                <li class="nav-item" role="presentation">
                                    <button class="nav-link" id="sucursales-compra-tab" data-bs-toggle="tab" 
                                            data-bs-target="#sucursales-compra" type="button" role="tab" 
                                            aria-controls="sucursales-compra" aria-selected="false">
                                        <i class="fas fa-shopping-cart me-1"></i>Sucursales de Compra
                                        <span id="contador-sucursales-compra" class="badge bg-secondary ms-1">0</span>
                                    </button>
                                </li>
                            </ul>
                            
                            
                            <div class="tab-content p-3 border border-top-0 rounded-bottom" id="entidadTabsContent">
                                <!-- Pestaña Datos Principales -->
                                <div class="tab-pane fade show active" id="datos" role="tabpanel" aria-labelledby="datos-tab">
                                    <form id="formEntidad" class="needs-validation" novalidate>
                                        <input type="hidden" id="entidad_id" name="entidad_id" />
                                        
                                        <!-- SECCIÓN 1: IDENTIFICACIÓN -->
                                        <div class="card card-outline card-primary mb-3 shadow-sm">
                                            <div class="card-header py-2 bg-light">
                                                <h6 class="card-title mb-0 fs-6 fw-bold text-primary">
                                                    <i class="fas fa-id-card me-2"></i>1. Identificación de la Entidad
                                                </h6>
                                            </div>
                                            <div class="card-body py-3">
                                                <div class="row">
                                                    <div class="col-md-6 mb-3">
                                                        <label for="entidad_nombre" class="form-label fw-bold text-dark">
                                                            <i class="fas fa-building text-primary me-1"></i>Nombre Legal / Razón Social <span class="text-danger">*</span>
                                                        </label>
                                                        <input type="text" class="form-control" id="entidad_nombre"
                                                            name="entidad_nombre" maxlength="255" placeholder="Ej: Razón Social S.A." required>
                                                        <div class="invalid-feedback">El nombre legal es requerido.</div>
                                                    </div>
                                                    
                                                    <div class="col-md-6 mb-3">
                                                        <label for="entidad_fantasia" class="form-label fw-bold text-dark">
                                                            <i class="fas fa-tag text-info me-1"></i>Nombre Fantasía
                                                        </label>
                                                        <input type="text" class="form-control" id="entidad_fantasia"
                                                            name="entidad_fantasia" maxlength="255" placeholder="Nombre comercial o de fantasía">
                                                    </div>
                                                </div>
                                                
                                                <div class="row">
                                                    <div class="col-md-4 mb-2">
                                                        <label for="entidad_tipo_id" class="form-label fw-bold text-dark">
                                                            <i class="fas fa-users-cog text-secondary me-1"></i>Tipo de Entidad
                                                        </label>
                                                        <select class="form-select" id="entidad_tipo_id" name="entidad_tipo_id">                                                    
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="col-md-4 mb-2">
                                                        <label for="cuit" class="form-label fw-bold text-dark">
                                                            <i class="fas fa-fingerprint text-secondary me-1"></i>CUIT <small class="text-muted fw-normal">(11 dígitos sin guiones)</small>
                                                        </label>
                                                        <input type="number" class="form-control" id="cuit"
                                                            name="cuit" min="0" max="99999999999" placeholder="20123456789">
                                                    </div>
                                                    
                                                    <div class="col-md-4 mb-2">
                                                        <label for="sitio_web" class="form-label fw-bold text-dark">
                                                            <i class="fas fa-globe text-secondary me-1"></i>Sitio Web
                                                        </label>
                                                        <input type="url" class="form-control" id="sitio_web"
                                                            name="sitio_web" maxlength="150" placeholder="https://empresa.com">
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- SECCIÓN 2: SITUACIÓN FISCAL. Se guarda junto con la entidad: si algún dato
                                             difiere de la condición vigente, se cierra la vigente y se abre una nueva
                                             (gestion__entidades_condiciones_fiscales / _iibb), así queda el historial. -->
                                        <div class="card card-outline card-warning mb-3 shadow-sm">
                                            <div class="card-header py-2 bg-light d-flex justify-content-between align-items-center">
                                                <h6 class="card-title mb-0 fs-6 fw-bold text-warning-emphasis">
                                                    <i class="fas fa-file-invoice-dollar me-2"></i>2. Situación Fiscal (IVA / Ingresos Brutos)
                                                </h6>
                                                <button type="button" class="btn btn-sm btn-outline-info ms-auto" id="btnHistorialFiscal" disabled>
                                                    <i class="fas fa-history me-1"></i>Historial
                                                </button>
                                            </div>
                                            <div class="card-body py-3">
                                                <div class="row">
                                                    <div class="col-md-4 mb-2">
                                                        <label for="condicion_fiscal_id" class="form-label fw-bold text-dark">
                                                            <i class="fas fa-percent text-warning me-1"></i>Condición IVA
                                                        </label>
                                                        <select class="form-select" id="condicion_fiscal_id" name="condicion_fiscal_id">
                                                            <option value="">Seleccione condición fiscal...</option>
                                                        </select>
                                                        <div class="form-text small" id="vigencia-iva"></div>
                                                    </div>

                                                    <div class="col-md-4 mb-2">
                                                        <label for="regimen_iibb_id" class="form-label fw-bold text-dark">
                                                            <i class="fas fa-landmark text-warning me-1"></i>Régimen IIBB
                                                        </label>
                                                        <select class="form-select" id="regimen_iibb_id" name="regimen_iibb_id">
                                                            <option value="">Seleccione régimen de IIBB...</option>
                                                        </select>
                                                        <div class="form-text small" id="vigencia-iibb"></div>
                                                    </div>

                                                    <div class="col-md-4 mb-2">
                                                        <label for="numero_inscripcion_iibb" class="form-label fw-bold text-dark">
                                                            <i class="fas fa-hashtag text-warning me-1"></i>N° Inscripción IIBB
                                                        </label>
                                                        <input type="text" class="form-control" id="numero_inscripcion_iibb"
                                                            name="numero_inscripcion_iibb" maxlength="50" placeholder="N° local, CM o CUIT">
                                                    </div>
                                                </div>

                                                <div class="row">
                                                    <div class="col-md-8 mb-2">
                                                        <label for="jurisdiccion_sede_id" class="form-label fw-bold text-dark">
                                                            <i class="fas fa-map-pin text-warning me-1"></i>Jurisdicción Sede IIBB
                                                        </label>
                                                        <select class="form-select" id="jurisdiccion_sede_id" name="jurisdiccion_sede_id">
                                                            <option value="">Seleccione jurisdicción sede (código ARCA/COMARB)...</option>
                                                        </select>
                                                    </div>

                                                    <div class="col-md-4 mb-2">
                                                        <label for="fiscal_f_desde" class="form-label fw-bold text-dark">
                                                            <i class="far fa-calendar-alt text-warning me-1"></i>Cambios vigentes desde
                                                        </label>
                                                        <input type="date" class="form-control" id="fiscal_f_desde" name="fiscal_f_desde">
                                                        <div class="form-text small">Solo se usa si cambia algún dato fiscal.</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- SECCIÓN 3: UBICACIÓN Y DOMICILIO LEGAL -->
                                        <div class="card card-outline card-success mb-3 shadow-sm">
                                            <div class="card-header py-2 bg-light">
                                                <h6 class="card-title mb-0 fs-6 fw-bold text-success">
                                                    <i class="fas fa-map-marked-alt me-2"></i>3. Domicilio Legal y Ubicación
                                                </h6>
                                            </div>
                                            <div class="card-body py-3">
                                                <div class="row">
                                                    <div class="col-md-8 mb-2">
                                                        <label for="domicilio_legal" class="form-label fw-bold text-dark">
                                                            <i class="fas fa-home text-success me-1"></i>Domicilio Legal
                                                        </label>
                                                        <input type="text" class="form-control" id="domicilio_legal"
                                                            name="domicilio_legal" maxlength="150" placeholder="Calle, número, piso, dpto.">
                                                    </div>
                                                    
                                                    <div class="col-md-4 mb-2">
                                                        <label for="localidad_id" class="form-label fw-bold text-dark">
                                                            <i class="fas fa-city text-success me-1"></i>Localidad
                                                        </label>
                                                        <select class="form-select" id="localidad_id" name="localidad_id">
                                                            <option value="">Seleccionar localidad...</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- SECCIÓN 4: ROLES Y CONTABILIDAD -->
                                        <div class="card card-outline card-info mb-3 shadow-sm">
                                            <div class="card-header py-2 bg-light">
                                                <h6 class="card-title mb-0 fs-6 fw-bold text-info">
                                                    <i class="fas fa-balance-scale me-2"></i>4. Roles Comerciales y Cuentas Contables
                                                </h6>
                                            </div>
                                            <div class="card-body py-3">
                                                <div class="row align-items-center mb-2">
                                                    <div class="col-md-2 mb-3 mb-md-0">
                                                        <div class="form-check form-switch p-2 bg-light rounded border">
                                                            <input class="form-check-input ms-0 me-2" type="checkbox" id="es_proveedor" name="es_proveedor" value="1">
                                                            <label class="form-check-label fw-bold text-dark" for="es_proveedor">
                                                                <i class="fas fa-truck text-primary me-1"></i>Proveedor
                                                            </label>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-md-2 mb-3 mb-md-0">
                                                        <div class="form-check form-switch p-2 bg-light rounded border">
                                                            <input class="form-check-input ms-0 me-2" type="checkbox" id="es_cliente" name="es_cliente" value="1">
                                                            <label class="form-check-label fw-bold text-dark" for="es_cliente">
                                                                <i class="fas fa-user-tie text-success me-1"></i>Cliente
                                                            </label>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="col-md-4 mb-2">
                                                        <label for="cont_cuenta_id_proveedor" class="form-label fw-bold text-dark">
                                                            <i class="fas fa-book text-secondary me-1"></i>Cuenta Contable (Proveedor)
                                                        </label>
                                                        <select class="form-select" id="cont_cuenta_id_proveedor" name="cont_cuenta_id_proveedor">
                                                            <option value="">Seleccionar cuenta...</option>
                                                        </select>                                                
                                                    </div>
                                                    
                                                    <div class="col-md-4 mb-2">
                                                        <label for="cont_cuenta_id_cliente" class="form-label fw-bold text-dark">
                                                            <i class="fas fa-book text-secondary me-1"></i>Cuenta Contable (Cliente)
                                                        </label>
                                                        <select class="form-select" id="cont_cuenta_id_cliente" name="cont_cuenta_id_cliente">
                                                            <option value="">Seleccionar cuenta...</option>
                                                        </select>                                                
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- SECCIÓN 5: OBSERVACIONES -->
                                        <div class="card card-outline card-secondary mb-2 shadow-sm">
                                            <div class="card-header py-2 bg-light">
                                                <h6 class="card-title mb-0 fs-6 fw-bold text-secondary">
                                                    <i class="fas fa-comment-alt me-2"></i>5. Observaciones Generales
                                                </h6>
                                            </div>
                                            <div class="card-body py-2">
                                                <div class="row">
                                                    <div class="col-md-12">
                                                        <textarea class="form-control" id="observaciones" name="observaciones"
                                                            rows="3" maxlength="1000" placeholder="Comentarios o notas adicionales sobre la entidad..."></textarea>
                                                        <div class="form-text text-end small">Máximo 1000 caracteres</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>                                        
                                    </form>
                                </div>
                                
                                
                                <!-- Pestaña Sucursales -->
                                <div class="tab-pane fade" id="sucursales" role="tabpanel" aria-labelledby="sucursales-tab">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">
                                            <i class="fas fa-store me-1"></i>Sucursales de la Entidad
                                        </h6>
                                        <button type="button" class="btn btn-sm btn-primary" id="btnNuevaSucursal">
                                            <i class="fas fa-plus me-1"></i>Nueva Sucursal
                                        </button>
                                    </div>
                                    
                                    <!-- Tabla de sucursales -->
                                    <div class="table-responsive">
                                        <table id="tablaSucursales" class="table table-sm table-hover" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="80">ID</th>
                                                    <th>Nombre</th>
                                                    <th>Dirección</th>
                                                    <th>Localidad</th>
                                                    <th>Teléfono</th>
                                                    <th>Email</th>
                                                    <th>Contacto</th>
                                                    <th width="120">Estado</th>
                                                    <th width="100" class="text-center">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                    
                                    <div class="alert alert-info mt-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <small>Las sucursales representan los domicilios comerciales de la entidad.</small>
                                    </div>
                                </div>

                                <!-- Historial de situación fiscal (IVA + IIBB). Cada cambio guardado desde
                                     "Datos Principales" cierra la condición vigente y abre una nueva; desde acá se
                                     corrigen fechas o se inhabilita una fila cargada por error. -->
                                <div class="modal fade" id="modalHistorialFiscal" tabindex="-1" aria-labelledby="modalHistorialFiscalLabel" aria-hidden="true">
                                    <div class="modal-dialog modal-xl modal-ancho modal-dialog-centered modal-dialog-scrollable">
                                        <div class="modal-content">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="modalHistorialFiscalLabel">
                                                    <i class="fas fa-history me-2 text-primary"></i>Historial de Situación Fiscal
                                                </h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                            </div>
                                            <div class="modal-body p-3">
                                                <h6 class="fw-bold text-primary mb-2">
                                                    <i class="fas fa-file-invoice-dollar me-1"></i>Nivel Nacional (ARCA / IVA)
                                                </h6>
                                                <div class="table-responsive mb-4">
                                                    <table id="tablaCondicionesFiscales" class="table table-sm table-hover table-striped mb-0" style="width:100%">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th width="60" class="text-center">ID</th>
                                                                <th>Condición Fiscal</th>
                                                                <th width="100" class="text-center">Código</th>
                                                                <th width="120" class="text-center">Fecha Desde</th>
                                                                <th width="120" class="text-center">Fecha Hasta</th>
                                                                <th width="110" class="text-center">Estado</th>
                                                                <th width="120" class="text-center">Acciones</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody></tbody>
                                                    </table>
                                                </div>

                                                <h6 class="fw-bold text-success mb-2">
                                                    <i class="fas fa-landmark me-1"></i>Nivel Provincial (Ingresos Brutos)
                                                </h6>
                                                <div class="table-responsive">
                                                    <table id="tablaCondicionesIIBB" class="table table-sm table-hover table-striped mb-0" style="width:100%">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th width="60" class="text-center">ID</th>
                                                                <th>Régimen IIBB</th>
                                                                <th width="150" class="text-center">N° Inscripción</th>
                                                                <th>Sede Principal (COMARB)</th>
                                                                <th width="110" class="text-center">Fecha Desde</th>
                                                                <th width="110" class="text-center">Fecha Hasta</th>
                                                                <th width="100" class="text-center">Estado</th>
                                                                <th width="110" class="text-center">Acciones</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody></tbody>
                                                    </table>
                                                </div>

                                                <h6 class="fw-bold text-warning mt-4 mb-2">
                                                    <i class="fas fa-hand-holding-usd me-1"></i>Percepciones IIBB (cuando la empresa es agente)
                                                </h6>
                                                <div id="percepcionesIibbCliente" class="mb-1"></div>
                                            </div>
                                            <div class="modal-footer py-2">
                                                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                               <!-- Pestaña Condiciones Clientes -->
                                <div class="tab-pane fade" id="condiciones-clientes" role="tabpanel" aria-labelledby="condiciones-clientes-tab">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">
                                            <i class="fas fa-user-tie me-1"></i>Condición Comercial Vigente - Cliente
                                        </h6>
                                        <div>
                                            <button type="button" class="btn btn-sm btn-info me-2" id="btnHistorialCliente">
                                                <i class="fas fa-history me-1"></i>Ver Historial
                                            </button>
                                            <button type="button" class="btn btn-sm btn-primary" id="btnNuevaCondicionCliente">
                                                <i class="fas fa-plus me-1"></i>Nueva Condición
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Tarjeta con condición vigente -->
                                    <div class="card card-outline card-primary">
                                        
                                        <div class="card-body">
                                            <div id="condicion-cliente-vigente" class="row">
                                                <div class="col-12 text-center text-muted py-4">
                                                    <i class="fas fa-spinner fa-spin me-2"></i>Cargando condición vigente...
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Acceso web (carrito de pedidos) -->
                                    <div class="card card-outline card-secondary mt-3">
                                        <div class="card-header">
                                            <h6 class="mb-0">
                                                <i class="fas fa-key me-1"></i>Acceso Web - Carrito de Pedidos
                                            </h6>
                                        </div>
                                        <div class="card-body">
                                            <div id="usuario-web-entidad">
                                                <div class="text-center text-muted py-2">
                                                    <i class="fas fa-spinner fa-spin me-2"></i>Verificando usuario...
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Modal para historial de clientes -->
                                    <div class="modal fade" id="modalHistorialCliente" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-xl modal-ancho">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">
                                                        <i class="fas fa-history me-2"></i>Historial de Condiciones - Cliente
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <table id="tablaHistorialClientes" class="table table-sm table-hover" style="width:100%">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>ID</th>
                                                                <th>Tipo Cliente</th>    <!-- NUEVA COLUMNA -->
                                                                <th>Condición de Pago</th>
                                                                <th>Lista de Precios</th>
                                                                <th>Límite de Crédito</th>
                                                                <th>Dto. General %</th>
                                                                <th>Fecha Desde</th>
                                                                <th>Fecha Hasta</th>
                                                                <th>Estado</th>
                                                                <th class="text-center">Acciones</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody></tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pestaña Condiciones Proveedores -->
                                <div class="tab-pane fade" id="condiciones-proveedores" role="tabpanel" aria-labelledby="condiciones-proveedores-tab">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">
                                            <i class="fas fa-truck me-1"></i>Condición Comercial Vigente - Proveedor
                                        </h6>
                                        <div>
                                            <button type="button" class="btn btn-sm btn-info me-2" id="btnHistorialProveedor">
                                                <i class="fas fa-history me-1"></i>Ver Historial
                                            </button>
                                            <button type="button" class="btn btn-sm btn-primary" id="btnNuevaCondicionProveedor">
                                                <i class="fas fa-plus me-1"></i>Nueva Condición
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Tarjeta con condición vigente -->
                                    <div class="card card-outline card-primary">
                                        
                                        <div class="card-body">
                                            <div id="condicion-proveedor-vigente" class="row">
                                                <div class="col-12 text-center text-muted py-4">
                                                    <i class="fas fa-spinner fa-spin me-2"></i>Cargando condición vigente...
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Modal para historial de proveedores -->
                                    <div class="modal fade" id="modalHistorialProveedor" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-xl modal-ancho">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title">
                                                        <i class="fas fa-history me-2"></i>Historial de Condiciones - Proveedor
                                                    </h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                                </div>
                                                <div class="modal-body">
                                                    <table id="tablaHistorialProveedores" class="table table-sm table-hover" style="width:100%">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>ID</th>
                                                                <th>Condición de Pago</th>
                                                                <th>Categoría</th>
                                                                <th>Dto. General %</th>
                                                                <th>Fecha Desde</th>
                                                                <th>Fecha Hasta</th>
                                                                <th>Estado</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody></tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <!-- Pestaña Sucursales de Compra -->
                                <div class="tab-pane fade" id="sucursales-compra" role="tabpanel" aria-labelledby="sucursales-compra-tab">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">
                                            <i class="fas fa-shopping-cart me-1"></i>Sucursales donde compra
                                        </h6>
                                        <button type="button" class="btn btn-sm btn-primary" id="btnNuevaVinculacionCompra">
                                            <i class="fas fa-plus me-1"></i>Vincular Sucursal
                                        </button>
                                    </div>
                                    
                                    <div class="table-responsive">
                                        <table id="tablaSucursalesCompra" class="table table-sm table-hover" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="80">ID</th>
                                                    <th>Sucursal</th>
                                                    <th>Dirección</th>
                                                    <th>Principal</th>
                                                    <th>Fecha Desde</th>
                                                    <th>Fecha Hasta</th>
                                                    <th>Estado</th>
                                                    <th width="100" class="text-center">Acciones</th>
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
                                <i class="fas fa-save me-1"></i>Guardar Entidad
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para crear/editar sucursal -->
            <div class="modal fade" id="modalSucursal" tabindex="-1" aria-labelledby="modalSucursalLabel"
                aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalSucursalLabel">Sucursal</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formSucursal" class="needs-validation" novalidate>
                                <input type="hidden" id="sucursal_id" name="sucursal_id" />
                                <input type="hidden" id="entidad_id_sucursal" name="entidad_id" />
                                
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="sucursal_nombre" class="form-label">Nombre de la Sucursal *</label>
                                        <input type="text" class="form-control" id="sucursal_nombre"
                                            name="sucursal_nombre" maxlength="150" required>
                                        <div class="invalid-feedback">El nombre de la sucursal es obligatorio</div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <label for="sucursal_direccion" class="form-label">Dirección</label>
                                        <input type="text" class="form-control" id="sucursal_direccion"
                                            name="sucursal_direccion" maxlength="255">
                                        <div class="form-text">Domicilio comercial</div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="localidad_id_sucursal" class="form-label">Localidad</label>
                                        <select class="form-select" id="localidad_id_sucursal" name="localidad_id">
                                            <option value="">Seleccionar localidad...</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="sucursal_telefono" class="form-label">Teléfono</label>
                                        <input type="text" class="form-control" id="sucursal_telefono"
                                            name="sucursal_telefono" maxlength="50">
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="sucursal_email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="sucursal_email"
                                            name="sucursal_email" maxlength="150">
                                        <div class="form-text">Correo electrónico de contacto</div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="sucursal_contacto" class="form-label">Persona de Contacto</label>
                                        <input type="text" class="form-control" id="sucursal_contacto"
                                            name="sucursal_contacto" maxlength="100">
                                        <div class="form-text">Nombre de la persona responsable</div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" id="btnGuardarSucursal">
                                <i class="fas fa-save me-1"></i>Guardar Sucursal
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para condición de cliente -->
            <div class="modal fade" id="modalCondicionCliente" tabindex="-1" aria-labelledby="modalCondicionClienteLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalCondicionClienteLabel">Condición de Cliente</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formCondicionCliente" class="needs-validation" novalidate>
                                <input type="hidden" id="condicion_cliente_id" name="condicion_cliente_id" />
                                <input type="hidden" id="entidad_id_condicion_cliente" name="entidad_id" />
                                
                                <!-- NUEVO CAMPO: Tipo de Cliente -->
                                <div class="mb-3">
                                    <label for="entidad_cliente_tipo_id" class="form-label">Tipo de Cliente</label>
                                    <select class="form-select" id="entidad_cliente_tipo_id" name="entidad_cliente_tipo_id">
                                        <option value="">Seleccionar tipo...</option>
                                    </select>
                                    <div class="form-text" id="acceso_web_info">
                                        <i class="fas fa-info-circle me-1"></i>
                                        <span id="acceso_web_texto">Seleccione un tipo para ver el acceso a la web</span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="condicion_pago_cliente_id" class="form-label">Condición de Pago *</label>
                                    <select class="form-select" id="condicion_pago_cliente_id" name="condicion_pago_id" required>
                                        <option value="">Seleccionar...</option>
                                    </select>
                                    <div class="invalid-feedback">Seleccione una condición de pago</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="lista_precio_id" class="form-label">Lista de Precios</label>
                                    <select class="form-select" id="lista_precio_id" name="lista_precio_id">
                                        <option value="">Seleccionar...</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="limite_credito" class="form-label">Límite de Crédito ($)</label>
                                    <input type="number" class="form-control" id="limite_credito" name="limite_credito" step="0.01" min="0">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="cliente_descuento_general" class="form-label">Descuento General (%)</label>
                                    <input type="number" class="form-control" id="cliente_descuento_general" name="cliente_descuento_general" step="0.01" min="0" max="100">
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="f_desde_cliente" class="form-label">Fecha Desde *</label>
                                        <input type="date" class="form-control" id="f_desde_cliente" name="f_desde" required>
                                        <div class="invalid-feedback">Fecha requerida</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="f_hasta_cliente" class="form-label">Fecha Hasta</label>
                                        <input type="date" class="form-control" id="f_hasta_cliente" name="f_hasta">
                                        <div class="form-text">Dejar vacío si es vigente</div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" id="btnGuardarCondicionCliente">
                                <i class="fas fa-save me-1"></i>Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para condición de proveedor -->
            <div class="modal fade" id="modalCondicionProveedor" tabindex="-1" aria-labelledby="modalCondicionProveedorLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalCondicionProveedorLabel">Condición de Proveedor</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formCondicionProveedor" class="needs-validation" novalidate>
                                <input type="hidden" id="condicion_proveedor_id" name="condicion_proveedor_id" />
                                <input type="hidden" id="entidad_id_condicion_proveedor" name="entidad_id" />
                                
                                <div class="mb-3">
                                    <label for="condicion_pago_proveedor_id" class="form-label">Condición de Pago *</label>
                                    <select class="form-select" id="condicion_pago_proveedor_id" name="condicion_pago_id" required>
                                        <option value="">Seleccionar...</option>
                                    </select>
                                    <div class="invalid-feedback">Seleccione una condición de pago</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="proveedor_categoria_id" class="form-label">Categoría</label>
                                    <select class="form-select" id="proveedor_categoria_id" name="proveedor_categoria_id">
                                        <option value="">Seleccionar...</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="proveedor_descuento_general" class="form-label">Descuento General (%)</label>
                                    <input type="number" class="form-control" id="proveedor_descuento_general" name="proveedor_descuento_general" step="0.01" min="0" max="100">
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="f_desde_proveedor" class="form-label">Fecha Desde *</label>
                                        <input type="date" class="form-control" id="f_desde_proveedor" name="f_desde" required>
                                        <div class="invalid-feedback">Fecha requerida</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="f_hasta_proveedor" class="form-label">Fecha Hasta</label>
                                        <input type="date" class="form-control" id="f_hasta_proveedor" name="f_hasta">
                                        <div class="form-text">Dejar vacío si es vigente</div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" id="btnGuardarCondicionProveedor">
                                <i class="fas fa-save me-1"></i>Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para vincular sucursal de compra -->
            <div class="modal fade" id="modalVinculacionCompra" tabindex="-1" aria-labelledby="modalVinculacionCompraLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalVinculacionCompraLabel">Vincular Sucursal de Compra</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formVinculacionCompra" class="needs-validation" novalidate>
                                <input type="hidden" id="vinculacion_compra_id" name="vinculacion_id" />
                                <input type="hidden" id="entidad_id_vinculacion" name="entidad_id" />
                                
                                <div class="mb-3">
                                    <label for="sucursal_compra_id" class="form-label">Sucursal *</label>
                                    <select class="form-select" id="sucursal_compra_id" name="sucursal_id" required>
                                        <option value="">Seleccionar sucursal...</option>
                                    </select>
                                    <div class="invalid-feedback">Seleccione una sucursal</div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="es_principal_compra" name="es_principal" value="1">
                                        <label class="form-check-label" for="es_principal_compra">
                                            Sucursal principal de compra
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="vinculacion_f_desde" class="form-label">Fecha Desde *</label>
                                        <input type="date" class="form-control" id="vinculacion_f_desde" name="vinculacion_f_desde" required>
                                        <div class="invalid-feedback">Fecha requerida</div>
                                    </div>
                                    
                                    <div class="col-md-6 mb-3">
                                        <label for="vinculacion_f_hasta" class="form-label">Fecha Hasta</label>
                                        <input type="date" class="form-control" id="vinculacion_f_hasta" name="vinculacion_f_hasta">
                                        <div class="form-text">Dejar vacío si es vigente</div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="vinculacion_observaciones" class="form-label">Observaciones</label>
                                    <textarea class="form-control" id="vinculacion_observaciones" name="observaciones" rows="2" maxlength="255"></textarea>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" id="btnGuardarVinculacionCompra">
                                <i class="fas fa-save me-1"></i>Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para crear/editar Condición Fiscal -->
            <div class="modal fade" id="modalCondicionFiscal" tabindex="-1" aria-labelledby="modalCondicionFiscalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalCondicionFiscalLabel">
                                <i class="fas fa-file-invoice-dollar me-2"></i>Condición Fiscal
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formCondicionFiscal" class="needs-validation" novalidate>
                                <input type="hidden" id="entidad_condicion_fiscal_id" name="entidad_condicion_fiscal_id" />
                                <input type="hidden" id="entidad_id_condicion_fiscal" name="entidad_id" />
                                
                                <div class="mb-3">
                                    <label for="select_condicion_fiscal_id" class="form-label fw-bold">Condición Fiscal *</label>
                                    <select class="form-select" id="select_condicion_fiscal_id" name="condicion_fiscal_id" required>
                                        <option value="">Seleccione condición fiscal...</option>
                                    </select>
                                    <div class="invalid-feedback">Debe seleccionar una condición fiscal</div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="cf_f_desde" class="form-label fw-bold">Fecha Desde *</label>
                                        <input type="date" class="form-control" id="cf_f_desde" name="f_desde" required>
                                        <div class="invalid-feedback">La fecha de inicio es requerida</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="cf_f_hasta" class="form-label fw-bold">Fecha Hasta</label>
                                        <input type="date" class="form-control" id="cf_f_hasta" name="f_hasta">
                                        <small class="text-muted">Vacío para vigencia indefinida</small>
                                    </div>
                                </div>

                                <div class="form-check form-switch mb-2" id="divCerrarAnterior">
                                    <input class="form-check-input" type="checkbox" id="cerrar_anterior" name="cerrar_anterior" value="1" checked>
                                    <label class="form-check-label small" for="cerrar_anterior">
                                        Cerrar vigencia de condición anterior a la fecha de inicio
                                    </label>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-primary" id="btnGuardarCondicionFiscal">
                                <i class="fas fa-save me-1"></i>Guardar Condición
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para crear/editar Condición IIBB (Nivel Provincial) -->
            <div class="modal fade" id="modalCondicionIIBB" tabindex="-1" aria-labelledby="modalCondicionIIBBLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalCondicionIIBBLabel">
                                <i class="fas fa-landmark me-2"></i>Condición IIBB (Nivel Provincial)
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formCondicionIIBB" class="needs-validation" novalidate>
                                <input type="hidden" id="entidad_condicion_iibb_id" name="entidad_condicion_iibb_id" />
                                <input type="hidden" id="entidad_id_condicion_iibb" name="entidad_id" />
                                
                                <div class="mb-3">
                                    <label for="select_regimen_iibb_id" class="form-label fw-bold">Régimen de IIBB *</label>
                                    <select class="form-select" id="select_regimen_iibb_id" name="regimen_iibb_id" required>
                                        <option value="">Seleccione régimen de IIBB...</option>
                                    </select>
                                    <div class="invalid-feedback">Debe seleccionar un régimen de IIBB</div>
                                    <div class="form-text text-primary small mt-1" id="descripcion_regimen_iibb"></div>
                                </div>

                                <div class="mb-3" id="grupo_numero_inscripcion">
                                    <label for="iibb_numero_inscripcion" class="form-label fw-bold">Número de Inscripción en IIBB</label>
                                    <input type="text" class="form-control" id="iibb_numero_inscripcion" name="numero_inscripcion" placeholder="Número local o número de CUIT / Convenio">
                                    <small class="text-muted">Número local o número de CUIT / Convenio Interprovincial</small>
                                </div>

                                <div class="mb-3" id="grupo_jurisdiccion_sede">
                                    <label for="select_jurisdiccion_sede_id" class="form-label fw-bold">Sede Principal / Jurisdicción Sede</label>
                                    <select class="form-select" id="select_jurisdiccion_sede_id" name="jurisdiccion_sede_id">
                                        <option value="">Seleccione jurisdicción sede (código ARCA/COMARB)...</option>
                                    </select>
                                    <small class="text-muted">Código de provincia ARCA/COMARB (ej. 901 para CABA, 902 para Bs. As.)</small>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="iibb_f_desde" class="form-label fw-bold">Fecha Desde *</label>
                                        <input type="date" class="form-control" id="iibb_f_desde" name="f_desde" required>
                                        <div class="invalid-feedback">La fecha de inicio es requerida</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="iibb_f_hasta" class="form-label fw-bold">Fecha Hasta</label>
                                        <input type="date" class="form-control" id="iibb_f_hasta" name="f_hasta">
                                        <small class="text-muted">Vacío para vigencia indefinida</small>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="iibb_observaciones" class="form-label">Observaciones</label>
                                    <input type="text" class="form-control" id="iibb_observaciones" name="observaciones" placeholder="Detalles u observaciones opcionales">
                                </div>

                                <div class="form-check form-switch mb-2" id="divCerrarAnteriorIIBB">
                                    <input class="form-check-input" type="checkbox" id="cerrar_anterior_iibb" name="cerrar_anterior" value="1" checked>
                                    <label class="form-check-label small" for="cerrar_anterior_iibb">
                                        Cerrar vigencia de condición anterior a la fecha de inicio
                                    </label>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-success" id="btnGuardarCondicionIIBB">
                                <i class="fas fa-save me-1"></i>Guardar Condición IIBB
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Modales más anchos que modal-xl (1140px): hasta el 95% de la pantalla */
        @media (min-width: 1200px) {
            .modal-dialog.modal-ancho { --bs-modal-width: min(1600px, 95vw); }
        }
        .dt-buttons .btn {
            margin-right: 5px;
            margin-bottom: 5px;
        }

        .dt-button-collection .dropdown-menu {
            margin-top: 5px;
        }

        .dataTables_wrapper .dt-buttons {
            float: right;
            margin-top: 5px;
        }

        .dropdown-menu .dropdown-item i {
            width: 20px;
            text-align: center;
            margin-right: 8px;
        }
        
        .table td {
            vertical-align: middle;
        }
        
        #tablaSucursales_wrapper {
            font-size: 0.9rem;
        }
        
        .nav-tabs .nav-link {
            font-weight: 500;
        }
        
        .nav-tabs .nav-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

    /* ========== ESTILOS RESPONSIVE ========== */
    @media (max-width: 767.98px) {
        /* Header */
        .app-content-header .row > div:first-child {
            margin-bottom: 15px;
            text-align: center;
        }
        
        .app-content-header h3 {
            font-size: 1.5rem;
        }
        
        .breadcrumb {
            justify-content: center;
        }
        
        /* Card header */
        .card-header {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 10px;
        }
        
        .card-header #contenedor-boton-agregar {
            text-align: center;
            margin-bottom: 10px;
        }
        
        .card-header .float-end {
            float: none !important;
            display: flex;
            justify-content: center;
        }
        
        .card-header .btn-group {
            width: 100%;
            display: flex;
        }
        
        .card-header .btn-group .btn {
            flex: 1;
            font-size: 0.85rem;
            padding: 0.375rem 0.25rem;
        }
        
        .card-header .btn-group .dropdown-menu {
            width: 100%;
        }
        
        /* DataTables */
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            text-align: center;
            margin-bottom: 15px;
        }
        
        .dataTables_wrapper .dataTables_length label,
        .dataTables_wrapper .dataTables_filter label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 5px;
        }
        
        .dataTables_wrapper .dataTables_filter input {
            width: 100% !important;
            max-width: 300px;
            margin-left: 0 !important;
        }
        
        .dataTables_wrapper .dataTables_info,
        .dataTables_wrapper .dataTables_paginate {
            text-align: center;
            margin-top: 15px;
        }
        
        .dataTables_wrapper .dataTables_paginate {
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .dataTables_wrapper .paginate_button {
            padding: 0.3rem 0.5rem !important;
            margin: 2px !important;
        }
        
        /* Tabla */
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table td, .table th {
            white-space: nowrap;
            font-size: 0.85rem;
        }
        
        .table td .btn-group {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 2px;
        }
        
        .table td .btn {
            padding: 0.25rem 0.4rem;
            font-size: 0.75rem;
        }
        
        /* Modales */
        .modal-dialog {
            margin: 10px;
        }
        
        .modal-xl {
            max-width: 100%;
        }
        
        .modal-body {
            padding: 15px;
        }
        
        .modal-footer {
            flex-direction: column;
            gap: 10px;
        }
        
        .modal-footer .btn {
            width: 100%;
            margin: 0 !important;
        }
        
        /* Tabs */
        .nav-tabs {
            display: flex;
            flex-wrap: nowrap;
            overflow-x: auto;
            overflow-y: hidden;
            -webkit-overflow-scrolling: touch;
            white-space: nowrap;
            margin-bottom: 10px;
        }
        
        .nav-tabs .nav-item {
            float: none;
        }
        
        .nav-tabs .nav-link {
            padding: 0.5rem 0.75rem;
            font-size: 0.85rem;
        }
        
        .nav-tabs .nav-link i {
            margin-right: 3px;
        }
        
        .tab-content .row > div {
            margin-bottom: 10px;
        }
        
        /* Formularios */
        .form-label {
            font-weight: 500;
            font-size: 0.9rem;
            margin-bottom: 2px;
        }
        
        .form-control, .form-select {
            font-size: 0.9rem;
            padding: 0.4rem 0.5rem;
        }
        
        .input-group {
            flex-direction: column;
        }
        
        .input-group > * {
            width: 100%;
            margin: 2px 0 !important;
            border-radius: 0.25rem !important;
        }
        
        /* Cards de condiciones vigentes */
        .card .table-borderless {
            font-size: 0.85rem;
        }
        
        .card .table-borderless td,
        .card .table-borderless th {
            padding: 0.3rem;
        }
        
        /* Alertas */
        .alert {
            font-size: 0.85rem;
            padding: 0.75rem;
        }
        
        /* Botones de acción en modales de condiciones */
        .card-header .btn-sm,
        .card-body .btn-sm {
            width: 100%;
            margin: 2px 0 !important;
        }
        
        .card-header .d-flex {
            flex-direction: column;
            gap: 5px;
        }
        
        .card-header .d-flex .btn {
            width: 100%;
        }
        
        /* Dropdowns */
        .dropdown-menu {
            position: fixed !important;
            top: auto !important;
            left: 10px !important;
            right: 10px !important;
            width: auto !important;
            transform: none !important;
            margin-top: 5px !important;
        }
        
        .dropdown-menu .dropdown-item {
            padding: 0.75rem 1rem;
            font-size: 1rem;
        }
        
        .dropdown-menu .dropdown-item i {
            width: 25px;
        }
        
        /* Swal (SweetAlert) */
        .swal2-popup {
            width: 90% !important;
            margin: 20px auto !important;
        }
        
        .swal2-actions {
            flex-direction: column;
            gap: 5px;
        }
        
        .swal2-actions button {
            width: 100%;
            margin: 2px 0 !important;
        }
        
        /* Botón "Agregar" */
        #contenedor-boton-agregar .btn {
            width: 100%;
            padding: 0.6rem;
            font-size: 1rem;
        }
    }
    
    /* Tablets (768px - 991px) */
    @media (min-width: 768px) and (max-width: 991.98px) {
        .modal-xl {
            max-width: 95%;
        }
        
        .nav-tabs .nav-link {
            padding: 0.5rem 0.6rem;
            font-size: 0.9rem;
        }
        
        .nav-tabs .nav-link i {
            margin-right: 2px;
        }
        
        .table td .btn-group {
            flex-wrap: wrap;
            min-width: 120px;
        }
        
        .table td .btn {
            padding: 0.2rem 0.3rem;
            font-size: 0.7rem;
        }
        
        .dataTables_wrapper .dataTables_length,
        .dataTables_wrapper .dataTables_filter {
            padding: 0 10px;
        }
    }
    
    /* Pantallas pequeñas en horizontal */
    @media (max-height: 600px) and (orientation: landscape) {
        .modal-dialog {
            max-height: 90vh;
            margin: 5vh auto;
        }
        
        .modal-body {
            max-height: 60vh;
            overflow-y: auto;
        }
    }
    
    /* Mejoras para DataTables en móviles */
    .table.dataTable {
        margin-top: 0 !important;
        margin-bottom: 0 !important;
    }
    
    .dataTables_wrapper .row {
        margin: 0;
    }
    
    .dataTables_wrapper .col-sm-12 {
        padding: 0;
    }
    
    /* Tooltips en móviles */
    .tooltip {
        display: none !important;
    }
    
    /* Ajustes para badges */
    .badge {
        font-size: 0.75rem;
        padding: 0.35em 0.65em;
    }
    
    /* Ajustes para el contador de sucursales */
    #contador-sucursales {
        display: inline-block;
        min-width: 20px;
    }
</style>

<!-- Añadir meta viewport si no existe -->
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">

    </style>

   <script>
$(document).ready(function () {
    // ============================================
    // VARIABLES DE CONTEXTO MULTIEMPRESA
    // ============================================
    const empresa_idx = <?= intval($current_empresa_id) ?>;
    const pagina_idx = <?php echo $pagina_idx; ?>;

    // ============================================
    // VARIABLES GLOBALES DEL MÓDULO
    // ============================================
    var tablaEntidades;
    var tablaSucursales;
    var tablaSucursalesCompra;
    var tablaCondicionesClientes;
    var tablaCondicionesProveedores;
    var tablaCondicionesFiscales;
    var catalogoCondicionesFiscales = [];
    var tablaCondicionesIIBB;
    var catalogoRegimenesIIBB = [];
    var catalogoJurisdiccionesSedesIIBB = [];
    var tablaHistorialClientes;
    var tablaHistorialProveedores;
    var currentPage = 0;
    var currentOrder = [[1, 'asc']];
    var currentSearch = '';
    var entidadActualId = 0;

    // ============================================
    // FUNCIONES PARA VINCULACIÓN ENTIDAD - SUCURSAL DE COMPRA
    // ============================================

    // Función para inicializar DataTable de sucursales de compra
    function inicializarDataTableSucursalesCompra(entidadId) {
        if ($.fn.DataTable.isDataTable('#tablaSucursalesCompra')) {
            if (tablaSucursalesCompra) {
                tablaSucursalesCompra.destroy();
                tablaSucursalesCompra = null;
            }
            $('#tablaSucursalesCompra tbody').empty();
        }

        if (!entidadId || entidadId === 0) {
            $('#tablaSucursalesCompra tbody').html('<tr><td colspan="8" class="text-center text-muted">Seleccione una entidad primero</td></tr>');
            $('#contador-sucursales-compra').text('0').addClass('bg-secondary').removeClass('bg-primary');
            return;
        }

        tablaSucursalesCompra = $('#tablaSucursalesCompra').DataTable({
            ajax: {
                url: 'entidades_ajax.php',
                type: 'GET',
                data: {
                    accion: 'obtener_sucursales_compra',
                    empresa_idx: empresa_idx,
                    entidad_id: entidadId
                },
                dataSrc: function(json) {
                    if (json.error) {
                        console.error('Error:', json.error);
                        return [];
                    }
                    return json;
                }
            },
            dom: '<"row"<"col-sm-12"tr>>' +
                '<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>',
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
            columns: [
                { data: 'entidad_sucursal_compra_id', className: 'text-center fw-bold' },
                { 
                    data: 'sucursal_nombre',
                    render: function(data, type, row) {
                        if (type === 'export') return data || '';
                        return `<div class="fw-medium">${data || 'N/D'}</div>`;
                    }
                },
                {
                    data: 'direccion',
                    render: function(data, type, row) {
                        if (type === 'export') return data || '';
                        return data || '<span class="text-muted fst-italic">No especificada</span>';
                    }
                },
                {
                    data: 'es_principal',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (type === 'export') return data == 1 ? 'Sí' : 'No';
                        return data == 1 ? 
                            '<span class="badge bg-warning text-dark"><i class="fas fa-star me-1"></i>Principal</span>' : 
                            '<span class="text-muted">No</span>';
                    }
                },
                {
                    data: 'f_desde',
                    className: 'text-center',
                    render: function(data) {
                        return data ? moment(data).format('DD/MM/YYYY') : '-';
                    }
                },
                {
                    data: 'f_hasta',
                    className: 'text-center',
                    render: function(data) {
                        return data ? moment(data).format('DD/MM/YYYY') : '<span class="badge bg-success">Vigente</span>';
                    }
                },
                {
                    data: 'estado_info',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (!data || !data.estado_registro) {
                            return '<span class="fw-medium">Sin estado</span>';
                        }
                        var badgeClass = data.bg_clase ? 'badge ' + data.bg_clase : 'badge bg-dark';
                        return `<span class="${badgeClass}">${data.estado_registro}</span>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    render: function(data, type, row) {
                        if (type === 'export') return '';
                        
                        var botones = '';
                        
                        // Solo mostrar botón de cerrar si está activa (sin fecha hasta)
                        if (!row.f_hasta && row.tabla_estado_registro_id == 1) {
                            botones += `<button type="button" class="btn btn-xs btn-outline-danger" 
                                           onclick="cerrarVinculacionCompra(${row.entidad_sucursal_compra_id}, '${row.sucursal_nombre || ''}')"
                                           title="Cerrar vinculación">
                                        <i class="fas fa-times"></i>
                                    </button>`;
                        }
                        
                        return `<div class="btn-group" role="group">${botones}</div>`;
                    }
                }
            ],
            language: {
                url: '<?= asset_local("js/datatables-es-ES.json") ?>'
            },
            responsive: true,
            drawCallback: function() {
                var total = tablaSucursalesCompra ? tablaSucursalesCompra.rows().count() : 0;
                $('#contador-sucursales-compra').text(total).toggleClass('bg-secondary', total === 0).toggleClass('bg-primary', total > 0);
            }
        });
    }

    // Función para cargar sucursales de la sociedad en el combo
    function cargarSucursalesSociedad() {
        $.get('entidades_ajax.php', {
            accion: 'obtener_sucursales_sociedad',
            empresa_idx: empresa_idx
        }, function (sucursales) {
            var select = $('#sucursal_compra_id');
            select.empty();
            select.append('<option value="">Seleccionar sucursal...</option>');
            
            if (sucursales && sucursales.length > 0) {
                $.each(sucursales, function (index, sucursal) {
                    var texto = sucursal.sucursal_nombre;
                    if (sucursal.direccion) {
                        texto += ' - ' + sucursal.direccion;
                    }
                    select.append('<option value="' + sucursal.sucursal_id + '">' + texto + '</option>');
                });
            }
        }, 'json');
    }

    // Función para cargar tipos de cliente
    function cargarTiposCliente() {
        $.get('entidades_ajax.php', {
            accion: 'obtener_tipos_cliente',
            empresa_idx: empresa_idx
        }, function (tipos) {
            var select = $('#entidad_cliente_tipo_id');
            select.empty();
            select.append('<option value="">Seleccionar tipo...</option>');
            
            if (tipos && tipos.length > 0) {
                $.each(tipos, function (index, tipo) {
                    select.append('<option value="' + tipo.entidad_cliente_tipo_id + '">' + tipo.entidad_cliente_tipo + '</option>');
                });
            }
        }, 'json');
    }

    // Función para resetear el modal de vinculación
    function resetModalVinculacionCompra() {
        $('#formVinculacionCompra')[0].reset();
        $('#vinculacion_compra_id').val('');
        $('#formVinculacionCompra').removeClass('was-validated');
        $('#es_principal_compra').prop('checked', false);
        $('#vinculacion_f_desde').val('');
        $('#vinculacion_f_hasta').val('');
        $('#vinculacion_observaciones').val('');
    }

    // ============================================
    // FUNCIONES PARA CERRAR VINCULACIÓN (GLOBAL)
    // ============================================
    window.cerrarVinculacionCompra = function(vinculacionId, sucursalNombre) {
        Swal.fire({
            title: '¿Cerrar vinculación?',
            html: `¿Está seguro de cerrar la vinculación con la sucursal <strong>"${sucursalNombre || 'desconocida'}"</strong>?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: 'Sí, cerrar',
            cancelButtonText: 'Cancelar',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('entidades_ajax.php', {
                    accion: 'cerrar_vinculacion_compra',
                    vinculacion_id: vinculacionId,
                    empresa_idx: empresa_idx
                }, function (res) {
                    if (res.resultado) {
                        if (tablaSucursalesCompra) {
                            tablaSucursalesCompra.ajax.reload();
                        }
                        
                        Swal.fire({
                            icon: "success",
                            title: "¡Cerrada!",
                            text: "La vinculación se cerró correctamente",
                            showConfirmButton: false,
                            timer: 1500,
                            toast: true,
                            position: 'top-end'
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || "Error al cerrar la vinculación",
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }
        });
    };

    // ============================================
    // EVENTOS PARA VINCULACIÓN DE COMPRA
    // ============================================

    // Manejador para botón "Vincular Sucursal"
    $(document).on('click', '#btnNuevaVinculacionCompra', function () {
        if (!entidadActualId) {
            Swal.fire({
                icon: "warning",
                title: "Advertencia",
                text: "Debe guardar la entidad primero antes de vincular sucursales",
                confirmButtonText: "Entendido"
            });
            return;
        }
        
        // Verificar si ya hay sucursales activas para esta entidad
        $.get('entidades_ajax.php', {
            accion: 'obtener_sucursales_compra',
            empresa_idx: empresa_idx,
            entidad_id: entidadActualId
        }, function (sucursalesActivas) {
            resetModalVinculacionCompra();
            cargarSucursalesSociedad();
            $('#modalVinculacionCompraLabel').text('Vincular Sucursal de Compra');
            $('#entidad_id_vinculacion').val(entidadActualId);
            
            // Set fecha desde por defecto a hoy
            var today = new Date().toISOString().split('T')[0];
            $('#vinculacion_f_desde').val(today);
            
            // Si no hay sucursales activas, marcar automáticamente como principal
            if (!sucursalesActivas || sucursalesActivas.length === 0) {
                $('#es_principal_compra').prop('checked', true);
                // Mostrar mensaje informativo
                Swal.fire({
                    icon: "info",
                    title: "Primera vinculación",
                    text: "Esta será la sucursal principal de compra por ser la primera",
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            } else {
                // Verificar si ya hay una principal
                var tienePrincipal = sucursalesActivas.some(function(item) {
                    return item.es_principal == 1;
                });
                
                if (tienePrincipal) {
                    // Si ya hay principal, desmarcar el checkbox y mostrar mensaje
                    $('#es_principal_compra').prop('checked', false);
                    $('#es_principal_compra').prop('disabled', false);
                } else {
                    // Si no hay principal, ofrecer marcarla
                    $('#es_principal_compra').prop('checked', false);
                    $('#es_principal_compra').prop('disabled', false);
                }
            }
            
            var modal = new bootstrap.Modal(document.getElementById('modalVinculacionCompra'));
            modal.show();
        }).fail(function() {
            // Si falla la consulta, abrir el modal sin verificar
            resetModalVinculacionCompra();
            cargarSucursalesSociedad();
            $('#modalVinculacionCompraLabel').text('Vincular Sucursal de Compra');
            $('#entidad_id_vinculacion').val(entidadActualId);
            
            var today = new Date().toISOString().split('T')[0];
            $('#vinculacion_f_desde').val(today);
            
            var modal = new bootstrap.Modal(document.getElementById('modalVinculacionCompra'));
            modal.show();
        });
    });

  // Manejador para guardar vinculación
    $(document).on('click', '#btnGuardarVinculacionCompra', function () {
        var form = document.getElementById('formVinculacionCompra');

        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }

        var fDesde = $('#vinculacion_f_desde').val();
        var fHasta = $('#vinculacion_f_hasta').val();
        var hoy = new Date().toISOString().split('T')[0];
        
        if (fDesde < hoy) {
            Swal.fire({
                icon: "warning",
                title: "Fecha inválida",
                text: "La fecha desde no puede ser menor al día de hoy",
                confirmButtonText: "Entendido"
            });
            $('#vinculacion_f_desde').focus();
            return false;
        }
        
        if (fHasta && fHasta < fDesde) {
            Swal.fire({
                icon: "warning",
                title: "Fechas inválidas",
                text: "La fecha hasta debe ser mayor o igual a la fecha desde",
                confirmButtonText: "Entendido"
            });
            $('#vinculacion_f_hasta').focus();
            return false;
        }
        
        if (fHasta && fHasta < hoy) {
            Swal.fire({
                icon: "warning",
                title: "Fecha inválida",
                text: "La fecha hasta no puede ser menor al día de hoy",
                confirmButtonText: "Entendido"
            });
            $('#vinculacion_f_hasta').focus();
            return false;
        }

        var btnGuardar = $(this);
        var originalText = btnGuardar.html();
        btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: 'entidades_ajax.php',
            type: 'POST',
            data: {
                accion: 'agregar_vinculacion_compra',
                entidad_id: $('#entidad_id_vinculacion').val(),
                sucursal_id: $('#sucursal_compra_id').val(),
                es_principal: $('#es_principal_compra').is(':checked') ? 1 : 0,
                f_desde: fDesde,
                f_hasta: fHasta || null,
                observaciones: $('#vinculacion_observaciones').val().trim(),
                empresa_idx: empresa_idx
            },
            success: function (res) {
                if (res.resultado) {
                    if (tablaSucursalesCompra) {
                        tablaSucursalesCompra.ajax.reload();
                    }
                    
                    btnGuardar.prop('disabled', false).html(originalText);
                    
                    var mensaje = res.message || "La sucursal se vinculó correctamente a la entidad";
                    Swal.fire({
                        icon: "success",
                        title: "¡Vinculación creada!",
                        text: mensaje,
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });
                    
                    var modalEl = document.getElementById('modalVinculacionCompra');
                    var modal = bootstrap.Modal.getInstance(modalEl);
                    modal.hide();
                } else {
                    btnGuardar.prop('disabled', false).html(originalText);
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || "Error al guardar la vinculación",
                        confirmButtonText: "Entendido"
                    });
                }
            },
            error: function () {
                btnGuardar.prop('disabled', false).html(originalText);
                Swal.fire({
                    icon: "error",
                    title: "Error de conexión",
                    text: "Error al comunicarse con el servidor",
                    confirmButtonText: "Entendido"
                });
            }
        });
    });

    // ============================================
    // FUNCIONES EXISTENTES (CARGAR CONDICIONES, ETC)
    // ============================================

    // Función para cargar condición vigente de cliente - CON TIPO DE CLIENTE
    function cargarCondicionClienteVigente() {
        if (!entidadActualId) {
            $('#condicion-cliente-vigente').html('<div class="col-12 text-center text-muted py-4">Seleccione una entidad primero</div>');
            return;
        }
        
        $.get('entidades_ajax.php', {
            accion: 'obtener_condicion_cliente_vigente',
            entidad_id: entidadActualId
        }, function (condicion) {
            if (condicion && condicion.entidad_condicion_cliente_id) {
                var html = `
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th width="40%">Tipo de Cliente:</th>
                                <td><span class="badge bg-info">${condicion.entidad_cliente_tipo || 'No especificado'}</span></td>
                            </tr>
                            <tr>
                                <th>Condición de Pago:</th>
                                <td><span class="badge bg-info">${condicion.condicion_pago || 'No especificado'}</span></td>
                            </tr>
                            <tr>
                                <th>Lista de Precios:</th>
                                <td>${condicion.lista_precio || 'No especificada'}</td>
                            </tr>
                            <tr>
                                <th>Descuento General:</th>
                                <td>${condicion.cliente_descuento_general ? condicion.cliente_descuento_general + '%' : '-'}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th width="40%">Límite de Crédito:</th>
                                <td><span class="fw-bold">${condicion.limite_credito ? '$ ' + parseFloat(condicion.limite_credito).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') : '-'}</span></td>
                            </tr>
                            <tr>
                                <th>Vigencia Desde:</th>
                                <td>${moment(condicion.f_desde).format('DD/MM/YYYY')}</td>
                            </tr>
                            <tr>
                                <th>Acceso Web:</th>
                                <td>${condicion.acceso_web_badge || (condicion.acceso_web == 1 ? '<span class="badge bg-success">Accede</span>' : '<span class="badge bg-danger">No accede</span>')}</td>
                            </tr>
                        </table>
                    </div>
                `;
                $('#condicion-cliente-vigente').html(html);
            } else {
                $('#condicion-cliente-vigente').html('<div class="col-12 text-center text-muted py-4">No hay condición vigente para este cliente</div>');
            }
        }, 'json');
    }

    // Verificar/mostrar usuario web (carrito de pedidos) de la entidad
    function cargarUsuarioWebEntidad() {
        if (!entidadActualId) {
            $('#usuario-web-entidad').html('<div class="text-muted">Seleccione una entidad primero</div>');
            return;
        }

        $('#usuario-web-entidad').html('<div class="text-center text-muted py-2"><i class="fas fa-spinner fa-spin me-2"></i>Verificando usuario...</div>');

        $.get('entidades_ajax.php', {
            accion: 'verificar_usuario_web_entidad',
            entidad_id: entidadActualId
        }, function (res) {
            if (res.tiene_usuario) {
                var passwordHtml = res.password
                    ? '<code>' + res.password + '</code>'
                    : '<span class="text-muted">(ya no disponible)</span>';
                var html = `
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-sm table-borderless mb-0">
                                <tr><th width="40%">Usuario (CUIT):</th><td><code>${res.usuario}</code></td></tr>
                                <tr><th>Contraseña:</th><td>${passwordHtml}</td></tr>
                            </table>
                        </div>
                        <div class="col-md-6 d-flex align-items-center justify-content-between">
                            <table class="table table-sm table-borderless mb-0">
                                <tr><th width="40%">Email:</th><td>${res.email}</td></tr>
                            </table>
                            <button type="button" class="btn btn-sm btn-outline-secondary ms-2" id="btnRegenerarPasswordUsuarioWeb" title="Regenerar contraseña">
                                <i class="fas fa-sync-alt me-1"></i>Regenerar contraseña
                            </button>
                        </div>
                    </div>
                    <div class="alert alert-warning mt-2 mb-0 py-2">
                        <small><i class="fas fa-exclamation-triangle me-1"></i>La contraseña se muestra en texto plano de forma temporal, mientras el carrito de pedidos no pasa a desarrollo/producción.</small>
                    </div>
                `;
                $('#usuario-web-entidad').html(html);
            } else if (res.sin_cuit) {
                $('#usuario-web-entidad').html('<div class="alert alert-secondary mb-0 py-2"><small>La entidad no tiene CUIT cargado. Complételo en la solapa Datos para poder generar el acceso web.</small></div>');
            } else {
                $('#usuario-web-entidad').html(`
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="text-muted">Esta entidad todavía no tiene usuario de acceso web.</span>
                        <button type="button" class="btn btn-sm btn-primary" id="btnAltaUsuarioWeb">
                            <i class="fas fa-user-plus me-1"></i>Dar de alta usuario
                        </button>
                    </div>
                `);
            }
        }, 'json').fail(function () {
            $('#usuario-web-entidad').html('<div class="text-danger">Error al verificar el usuario web.</div>');
        });
    }

    $(document).on('click', '#btnAltaUsuarioWeb', function () {
        if (!entidadActualId) return;

        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Generando...');

        $.post('entidades_ajax.php', {
            accion: 'alta_usuario_web_entidad',
            entidad_id: entidadActualId
        }, function (res) {
            if (res.resultado) {
                Swal.fire({
                    icon: 'success',
                    title: 'Usuario creado',
                    text: 'Se generó el usuario y contraseña de acceso al carrito.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000
                });
                cargarUsuarioWebEntidad();
            } else {
                Swal.fire('Error', res.error || 'No se pudo dar de alta el usuario', 'error');
                btn.prop('disabled', false).html('<i class="fas fa-user-plus me-1"></i>Dar de alta usuario');
            }
        }, 'json').fail(function () {
            Swal.fire('Error', 'Error al comunicarse con el servidor', 'error');
            btn.prop('disabled', false).html('<i class="fas fa-user-plus me-1"></i>Dar de alta usuario');
        });
    });

    $(document).on('click', '#btnRegenerarPasswordUsuarioWeb', function () {
        if (!entidadActualId) return;

        var btn = $(this);

        Swal.fire({
            icon: 'warning',
            title: '¿Regenerar contraseña?',
            text: 'La contraseña actual dejará de funcionar de inmediato. Va a tener que informarle la nueva al cliente.',
            showCancelButton: true,
            confirmButtonText: 'Sí, regenerar',
            cancelButtonText: 'Cancelar'
        }).then(function (confirmacion) {
            if (!confirmacion.isConfirmed) return;

            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Regenerando...');

            $.post('entidades_ajax.php', {
                accion: 'regenerar_password_usuario_web_entidad',
                entidad_id: entidadActualId
            }, function (res) {
                if (res.resultado) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Contraseña regenerada',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 2000
                    });
                    cargarUsuarioWebEntidad();
                } else {
                    Swal.fire('Error', res.error || 'No se pudo regenerar la contraseña', 'error');
                    btn.prop('disabled', false).html('<i class="fas fa-sync-alt me-1"></i>Regenerar contraseña');
                }
            }, 'json').fail(function () {
                Swal.fire('Error', 'Error al comunicarse con el servidor', 'error');
                btn.prop('disabled', false).html('<i class="fas fa-sync-alt me-1"></i>Regenerar contraseña');
            });
        });
    });

    // Cargar cuentas contables para proveedores y clientes
    function cargarCuentasContables() {
        $.get('entidades_ajax.php', {
            accion: 'obtener_cuentas_contables',
            empresa_idx: empresa_idx
        }, function(cuentas) {
            var selectProv = $('#cont_cuenta_id_proveedor');
            var selectCli = $('#cont_cuenta_id_cliente');
            
            selectProv.empty().append('<option value="">Seleccionar cuenta...</option>');
            selectCli.empty().append('<option value="">Seleccionar cuenta...</option>');
            
            if (cuentas && cuentas.length > 0) {
                cuentas.forEach(function(cuenta) {
                    var prefix = '';
                    for (var i = 1; i < cuenta.nivel; i++) {
                        prefix += '&nbsp;&nbsp;';
                    }
                    var texto = prefix + cuenta.codigo + ' - ' + cuenta.nombre;
                    selectProv.append(`<option value="${cuenta.cont_cuenta_id}">${texto}</option>`);
                    selectCli.append(`<option value="${cuenta.cont_cuenta_id}">${texto}</option>`);
                });
            }
        }, 'json');
    }

    // Función para cargar condición vigente de proveedor
    function cargarCondicionProveedorVigente() {
        if (!entidadActualId) {
            $('#condicion-proveedor-vigente').html('<div class="col-12 text-center text-muted py-4">Seleccione una entidad primero</div>');
            return;
        }
        
        $.get('entidades_ajax.php', {
            accion: 'obtener_condicion_proveedor_vigente',
            entidad_id: entidadActualId
        }, function (condicion) {
            if (condicion && condicion.entidad_condicion_proveedor_id) {
                var html = `
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th width="40%">Condición de Pago:</th>
                                <td><span class="badge bg-info">${condicion.condicion_pago || 'No especificado'}</span></td>
                            </tr>
                            <tr>
                                <th>Categoría:</th>
                                <td>${condicion.proveedor_categoria || 'No especificada'}</td>
                            </tr>
                            <tr>
                                <th>Descuento General:</th>
                                <td>${condicion.proveedor_descuento_general ? condicion.proveedor_descuento_general + '%' : '-'}</td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-sm table-borderless">
                            <tr>
                                <th width="40%">Vigencia Desde:</th>
                                <td>${moment(condicion.f_desde).format('DD/MM/YYYY')}</td>
                            </tr>
                            <tr>
                                <th>Vigencia Hasta:</th>
                                <td>${condicion.f_hasta ? moment(condicion.f_hasta).format('DD/MM/YYYY') : '<span class="badge bg-success">Vigente</span>'}</td>
                            </tr>
                        </table>
                    </div>
                `;
                $('#condicion-proveedor-vigente').html(html);
            } else {
                $('#condicion-proveedor-vigente').html('<div class="col-12 text-center text-muted py-4">No hay condición vigente para este proveedor</div>');
            }
        }, 'json');
    }

    // Función para inicializar tabla de historial de clientes
    function inicializarTablaHistorialClientes() {
        if ($.fn.DataTable.isDataTable('#tablaHistorialClientes')) {
            $('#tablaHistorialClientes').DataTable().destroy();
        }
        
        tablaHistorialClientes = $('#tablaHistorialClientes').DataTable({
            ajax: {
                url: 'entidades_ajax.php',
                type: 'GET',
                data: {
                    accion: 'listar_historial_condiciones_cliente',
                    entidad_id: entidadActualId
                },
                dataSrc: ''
            },
            columns: [
                { data: 'entidad_condicion_cliente_id', className: 'text-center' },
                { data: 'condicion_pago' },
                { data: 'lista_precio' },
                { 
                    data: 'limite_credito',
                    className: 'text-end',
                    render: function(data) {
                        return data ? '$ ' + parseFloat(data).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,') : '-';
                    }
                },
                { 
                    data: 'cliente_descuento_general',
                    className: 'text-center',
                    render: function(data) {
                        return data ? data + '%' : '-';
                    }
                },
                { 
                    data: 'f_desde',
                    render: function(data) {
                        return moment(data).format('DD/MM/YYYY');
                    }
                },
                { 
                    data: 'f_hasta',
                    render: function(data) {
                        return data ? moment(data).format('DD/MM/YYYY') : '<span class="badge bg-success">Vigente</span>';
                    }
                },
                {
                    data: 'estado_info',
                    render: function(data) {
                        if (!data || !data.estado_registro) return '-';
                        var badgeClass = data.bg_clase ? 'badge ' + data.bg_clase : 'badge bg-secondary';
                        return `<span class="${badgeClass}">${data.estado_registro}</span>`;
                    }
                }
            ],
            language: {
                url: '<?= asset_local("js/datatables-es-ES.json") ?>'
            },
            order: [[5, 'desc']]
        });
    }

    // Función para inicializar tabla de historial de proveedores
    function inicializarTablaHistorialProveedores() {
        if ($.fn.DataTable.isDataTable('#tablaHistorialProveedores')) {
            $('#tablaHistorialProveedores').DataTable().destroy();
        }
        
        tablaHistorialProveedores = $('#tablaHistorialProveedores').DataTable({
            ajax: {
                url: 'entidades_ajax.php',
                type: 'GET',
                data: {
                    accion: 'listar_historial_condiciones_proveedor',
                    entidad_id: entidadActualId
                },
                dataSrc: ''
            },
            columns: [
                { data: 'entidad_condicion_proveedor_id', className: 'text-center' },
                { data: 'condicion_pago' },
                { data: 'proveedor_categoria' },
                { 
                    data: 'proveedor_descuento_general',
                    className: 'text-center',
                    render: function(data) {
                        return data ? data + '%' : '-';
                    }
                },
                { 
                    data: 'f_desde',
                    render: function(data) {
                        return moment(data).format('DD/MM/YYYY');
                    }
                },
                { 
                    data: 'f_hasta',
                    render: function(data) {
                        return data ? moment(data).format('DD/MM/YYYY') : '<span class="badge bg-success">Vigente</span>';
                    }
                },
                {
                    data: 'estado_info',
                    render: function(data) {
                        if (!data || !data.estado_registro) return '-';
                        var badgeClass = data.bg_clase ? 'badge ' + data.bg_clase : 'badge bg-secondary';
                        return `<span class="${badgeClass}">${data.estado_registro}</span>`;
                    }
                }
            ],
            language: {
                url: '<?= asset_local("js/datatables-es-ES.json") ?>'
            },
            order: [[4, 'desc']]
        });
    }

    function cargarCondicionesPago() {
        $.get('entidades_ajax.php', {
            accion: 'obtener_condiciones_pago',
            empresa_idx: empresa_idx
        }, function (condiciones) {
            var selectCliente = $('#condicion_pago_cliente_id');
            var selectProveedor = $('#condicion_pago_proveedor_id');
            
            selectCliente.empty();
            selectCliente.append('<option value="">Seleccionar...</option>');
            selectProveedor.empty();
            selectProveedor.append('<option value="">Seleccionar...</option>');
            
            if (condiciones && condiciones.length > 0) {
                $.each(condiciones, function (index, cond) {
                    selectCliente.append('<option value="' + cond.condicion_pago_id + '">' + cond.condicion_pago + '</option>');
                    selectProveedor.append('<option value="' + cond.condicion_pago_id + '">' + cond.condicion_pago + '</option>');
                });
            }
        }, 'json');
    }

    // Función para cargar listas de precios
    function cargarListasPrecios() {
        $.get('entidades_ajax.php', {
            accion: 'obtener_listas_precios',
            empresa_idx: empresa_idx
        }, function (listas) {
            var select = $('#lista_precio_id');
            select.empty();
            select.append('<option value="">Seleccionar...</option>');
            
            if (listas && listas.length > 0) {
                $.each(listas, function (index, lista) {
                    select.append('<option value="' + lista.lista_precio_id + '">' + lista.lista_precio + '</option>');
                });
            }
        }, 'json');
    }

    // Función para cargar categorías de proveedores
    function cargarCategoriasProveedores() {
        $.get('entidades_ajax.php', {
            accion: 'obtener_categorias_proveedores',
            empresa_idx: empresa_idx
        }, function (categorias) {
            var select = $('#proveedor_categoria_id');
            select.empty();
            select.append('<option value="">Seleccionar...</option>');
            
            if (categorias && categorias.length > 0) {
                $.each(categorias, function (index, cat) {
                    select.append('<option value="' + cat.proveedor_categoria_id + '">' + cat.proveedor_categoria + '</option>');
                });
            }
        }, 'json');
    }

    // Función para inicializar DataTable de condiciones de clientes - CON TIPO DE CLIENTE
    function inicializarDataTableCondicionesClientes(entidadId) {
        if ($.fn.DataTable.isDataTable('#tablaCondicionesClientes')) {
            if (tablaCondicionesClientes) {
                tablaCondicionesClientes.destroy();
            }
            $('#tablaCondicionesClientes tbody').empty();
        }

        if (!entidadId) {
            $('#tablaCondicionesClientes tbody').html('<tr><td colspan="10" class="text-center text-muted">Seleccione una entidad primero</td></tr>');
            return;
        }

        tablaCondicionesClientes = $('#tablaCondicionesClientes').DataTable({
            ajax: {
                url: 'entidades_ajax.php',
                type: 'GET',
                data: {
                    accion: 'listar_condiciones_cliente',
                    empresa_idx: empresa_idx,
                    entidad_id: entidadId
                },
                dataSrc: ''
            },
            dom: '<"row"<"col-sm-12"tr>>' +
                '<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>',
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
            columns: [
                { data: 'entidad_condicion_cliente_id', className: 'text-center fw-bold' },
                {
                    data: 'entidad_cliente_tipo',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (type === 'export') return data || '';
                        return data ? `<span class="badge bg-info">${data}</span>` : '<span class="text-muted fst-italic">Sin tipo</span>';
                    }
                },
                { 
                    data: 'condicion_pago',
                    render: function(data, type, row) {
                        return data || '<span class="text-muted fst-italic">No especificado</span>';
                    }
                },
                {
                    data: 'lista_precio',
                    render: function(data, type, row) {
                        return data || '<span class="text-muted fst-italic">No especificada</span>';
                    }
                },
                {
                    data: 'limite_credito',
                    className: 'text-end',
                    render: function(data, type, row) {
                        if (type === 'export') {
                            return data || '';
                        }
                        if (data !== null && data !== '') {
                            return '$ ' + parseFloat(data).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                        }
                        return '<span class="text-muted">-</span>';
                    }
                },
                {
                    data: 'cliente_descuento_general',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (data !== null && data !== '') {
                            return parseFloat(data).toFixed(2) + '%';
                        }
                        return '<span class="text-muted">-</span>';
                    }
                },
                { 
                    data: 'f_desde',
                    className: 'text-center',
                    render: function(data) {
                        return data ? moment(data).format('DD/MM/YYYY') : '-';
                    }
                },
                { 
                    data: 'f_hasta',
                    className: 'text-center',
                    render: function(data) {
                        return data ? moment(data).format('DD/MM/YYYY') : '<span class="badge bg-success">Vigente</span>';
                    }
                },
                {
                    data: 'estado_info',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (!data || !data.estado_registro) {
                            return '<span class="fw-medium">Sin estado</span>';
                        }
                        var badgeClass = data.bg_clase ? 'badge ' + data.bg_clase : 'badge bg-dark';
                        return `<span class="${badgeClass}">${data.estado_registro}</span>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    render: function(data, type, row) {
                        if (type === 'export') return '';
                        
                        return `<div class="btn-group" role="group">
                            <button type="button" class="btn btn-xs btn-outline-primary" 
                                    onclick="editarCondicionCliente(${row.entidad_condicion_cliente_id})">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>`;
                    }
                }
            ],
            language: {
                url: '<?= asset_local("js/datatables-es-ES.json") ?>'
            },
            responsive: true,
            createdRow: function(row, data, dataIndex) {
                if (data.estado_info && data.estado_info.codigo_estandar === 'INACTIVO') {
                    $(row).addClass('table-secondary');
                }
            },
            drawCallback: function() {
                var total = tablaCondicionesClientes.rows().count();
                $('#contador-condiciones-clientes').text(total).toggleClass('bg-secondary', total === 0).toggleClass('bg-primary', total > 0);
            }
        });
    }

    // Función para inicializar DataTable de condiciones de proveedores
    function inicializarDataTableCondicionesProveedores(entidadId) {
        if ($.fn.DataTable.isDataTable('#tablaCondicionesProveedores')) {
            if (tablaCondicionesProveedores) {
                tablaCondicionesProveedores.destroy();
            }
            $('#tablaCondicionesProveedores tbody').empty();
        }

        if (!entidadId) {
            $('#tablaCondicionesProveedores tbody').html('<tr><td colspan="8" class="text-center text-muted">Seleccione una entidad primero</td></tr>');
            return;
        }

        tablaCondicionesProveedores = $('#tablaCondicionesProveedores').DataTable({
            ajax: {
                url: 'entidades_ajax.php',
                type: 'GET',
                data: {
                    accion: 'listar_condiciones_proveedor',
                    empresa_idx: empresa_idx,
                    entidad_id: entidadId
                },
                dataSrc: ''
            },
            dom: '<"row"<"col-sm-12"tr>>' +
                '<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>',
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
            columns: [
                { data: 'entidad_condicion_proveedor_id', className: 'text-center fw-bold' },
                { 
                    data: 'condicion_pago',
                    render: function(data, type, row) {
                        return data || '<span class="text-muted fst-italic">No especificado</span>';
                    }
                },
                {
                    data: 'proveedor_categoria',
                    render: function(data, type, row) {
                        return data || '<span class="text-muted fst-italic">No especificada</span>';
                    }
                },
                {
                    data: 'proveedor_descuento_general',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (data !== null) {
                            return parseFloat(data).toFixed(2) + '%';
                        }
                        return '<span class="text-muted">-</span>';
                    }
                },
                { 
                    data: 'f_desde',
                    className: 'text-center',
                    render: function(data) {
                        return data ? moment(data).format('DD/MM/YYYY') : '-';
                    }
                },
                { 
                    data: 'f_hasta',
                    className: 'text-center',
                    render: function(data) {
                        return data ? moment(data).format('DD/MM/YYYY') : '<span class="badge bg-success">Vigente</span>';
                    }
                },
                {
                    data: 'estado_info',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (!data || !data.estado_registro) {
                            return '<span class="fw-medium">Sin estado</span>';
                        }
                        var badgeClass = data.bg_clase ? 'badge ' + data.bg_clase : 'badge bg-dark';
                        return `<span class="${badgeClass}">${data.estado_registro}</span>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    render: function(data, type, row) {
                        if (type === 'export') return '';
                        
                        return `<div class="btn-group" role="group">
                            <button type="button" class="btn btn-xs btn-outline-primary" 
                                    onclick="editarCondicionProveedor(${row.entidad_condicion_proveedor_id})">
                                <i class="fas fa-edit"></i>
                            </button>
                        </div>`;
                    }
                }
            ],
            language: {
                url: '<?= asset_local("js/datatables-es-ES.json") ?>'
            },
            responsive: true,
            createdRow: function(row, data, dataIndex) {
                if (data.estado_info && data.estado_info.codigo_estandar === 'INACTIVO') {
                    $(row).addClass('table-secondary');
                }
            },
            drawCallback: function() {
                var total = tablaCondicionesProveedores.rows().count();
                $('#contador-condiciones-proveedores').text(total).toggleClass('bg-secondary', total === 0).toggleClass('bg-primary', total > 0);
            }
        });
    }

    // Función para cargar tipos de entidad
    function cargarTiposEntidad() {
        $.get('entidades_ajax.php', {
            accion: 'obtener_tipos_entidad',
            empresa_idx: empresa_idx
        }, function (tipos) {
            var select = $('#entidad_tipo_id');
            select.empty();
            select.append('<option value="">Seleccionar tipo...</option>');
            
            if (tipos && tipos.length > 0) {
                $.each(tipos, function (index, tipo) {
                    select.append('<option value="' + tipo.entidad_tipo_id + '">' + tipo.entidad_tipo + '</option>');
                });
            }
        }, 'json');
    }

    // Función para cargar localidades
    function cargarLocalidades() {
        $.get('entidades_ajax.php', {
            accion: 'obtener_localidades',
            empresa_idx: empresa_idx
        }, function (localidades) {
            var selectEntidad = $('#localidad_id');
            var selectSucursal = $('#localidad_id_sucursal');
            
            selectEntidad.empty();
            selectEntidad.append('<option value="">Seleccionar localidad...</option>');
            
            if (selectSucursal.length) {
                selectSucursal.empty();
                selectSucursal.append('<option value="">Seleccionar localidad...</option>');
            }
            
            if (localidades && localidades.length > 0) {
                $.each(localidades, function (index, localidad) {
                    selectEntidad.append('<option value="' + localidad.localidad_id + '">' + localidad.localidad + '</option>');
                    if (selectSucursal.length) {
                        selectSucursal.append('<option value="' + localidad.localidad_id + '">' + localidad.localidad + '</option>');
                    }
                });
            }
        }, 'json');
    }

    // Función para inicializar DataTable de entidades
    function inicializarDataTableEntidades() {
        if ($.fn.DataTable.isDataTable('#tablaEntidades')) {
            $('#tablaEntidades').DataTable().destroy();
            $('#tablaEntidades tbody').empty();
        }

        tablaEntidades = $('#tablaEntidades').DataTable({
            ajax: {
                url: 'entidades_ajax.php',
                type: 'GET',
                data: {
                    accion: 'listar_entidades',
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                },
                dataSrc: ''
            },
            stateSave: true,
            stateSaveParams: function (settings, data) {
                data.page = currentPage;
                data.order = currentOrder;
                
                if (currentSearch !== '-1' && currentSearch !== '') {
                    data.search = { search: currentSearch };
                } else {
                    data.search = { search: '' };
                }
                
                delete data.columns;
                return data;
            },
            stateLoadParams: function (settings, data) {
                if (data.page !== undefined) currentPage = data.page;
                if (data.order !== undefined && data.order.length > 0) currentOrder = data.order;

                if (data.search && data.search.search !== undefined) {
                    var searchValue = data.search.search;
                    if (searchValue === '-1' || searchValue === '') {
                        currentSearch = '';
                    } else {
                        currentSearch = searchValue;
                    }
                } else {
                    currentSearch = '';
                }

                data.search = { search: currentSearch };
            },
            stateLoadCallback: function (settings) {
                var savedData = localStorage.getItem('DataTables_' + settings.sInstance);
                if (savedData) {
                    var data = JSON.parse(savedData);

                    if (data.search && (data.search.search === '-1' || data.search.search === '')) {
                        data.search.search = '';
                    }

                    if (data.columns) {
                        $.each(data.columns, function (i, col) {
                            if (col.search && col.search.search === '-1') {
                                col.search.search = '';
                            }
                        });
                    }

                    return data;
                }
                return null;
            },
            dom: '<"row align-items-center mb-2"<"col-12 col-md-6"><"col-12 col-md-6 text-end"f>>' +
                '<"row"<"col-sm-12"tr>>' +
                '<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>' +
                '<"clear">',
            pageLength: 10,
            lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
            buttons: [
                {
                    extend: 'excelHtml5',
                    text: '<i class="fas fa-file-excel"></i> Excel',
                    className: 'btn btn-success btn-sm',
                    title: 'Entidades',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7, 8],
                        orthogonal: 'export'
                    }
                },
                {
                    extend: 'pdfHtml5',
                    text: '<i class="fas fa-file-pdf"></i> PDF',
                    className: 'btn btn-danger btn-sm',
                    title: 'Entidades',
                    orientation: 'portrait',
                    pageSize: 'A4',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7, 8],
                        orthogonal: 'export'
                    }
                },
                {
                    extend: 'csvHtml5',
                    text: '<i class="fas fa-file-csv"></i> CSV',
                    className: 'btn btn-primary btn-sm',
                    title: 'Entidades',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
                    }
                },
                {
                    extend: 'print',
                    text: '<i class="fas fa-print"></i> Imprimir',
                    className: 'btn btn-secondary btn-sm',
                    title: 'Entidades',
                    exportOptions: {
                        columns: [0, 1, 2, 3, 4, 5, 6, 7, 8],
                        stripHtml: false
                    }
                }
            ],
            columns: [
                {
                    data: 'entidad_id',
                    className: 'text-center fw-bold'
                },
                {
                    data: 'entidad_nombre',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return data;
                        }
                        return `<div class="fw-medium">${data}</div>`;
                    }
                },
                {
                    data: 'entidad_fantasia',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return data || '';
                        }
                        return data ? `<div class="text-muted">${data}</div>` : '<span class="text-muted fst-italic">No especificado</span>';
                    }
                },
                {
                    data: 'entidad_tipo_info',
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return data && data.entidad_tipo ? data.entidad_tipo : '';
                        }
                        
                        if (!data || !data.entidad_tipo) {
                            return '<span class="text-muted fst-italic">No especificado</span>';
                        }
                        
                        var badgeClass = data.bg_clase ? 'badge ' + data.bg_clase : 'badge bg-secondary';
                        return `<span class="${badgeClass}">${data.entidad_tipo}</span>`;
                    }
                },
                {
                    data: 'cuit',
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return data || '';
                        }
                        
                        if (!data) {
                            return '<span class="text-muted fst-italic">N/D</span>';
                        }
                        
                        var cuitStr = data.toString().padStart(11, '0');
                        var formattedCuit = cuitStr.replace(/^(\d{2})(\d{8})(\d{1})$/, '$1-$2-$3');
                        return `<span class="fw-medium">${formattedCuit}</span>`;
                    }
                },
                {
                    data: 'condicion_fiscal_info',
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (!data || !data.condicion_fiscal) {
                            if (type === 'export') {
                                return 'Sin condición';
                            }
                            return '<span class="text-muted fst-italic">Sin condición</span>';
                        }
                        if (type === 'export') {
                            return data.condicion_fiscal + (data.condicion_fiscal_codigo ? ' (' + data.condicion_fiscal_codigo + ')' : '');
                        }
                        return `<span class="badge bg-light text-dark border">${data.condicion_fiscal}</span>` +
                            (data.condicion_fiscal_codigo ? ` <small class="text-muted fw-bold">(${data.condicion_fiscal_codigo})</small>` : '');
                    }
                },
                {
                    data: 'es_proveedor',
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return data == 1 ? 'Sí' : 'No';
                        }
                        
                        if (data == 1) {
                            return '<i class="fas fa-check-circle text-success"></i>';
                        } else {
                            return '<i class="fas fa-times-circle text-secondary"></i>';
                        }
                    }
                },
                {
                    data: 'es_cliente',
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return data == 1 ? 'Sí' : 'No';
                        }
                        
                        if (data == 1) {
                            return '<i class="fas fa-check-circle text-success"></i>';
                        } else {
                            return '<i class="fas fa-times-circle text-secondary"></i>';
                        }
                    }
                },
                {
                    data: 'estado_info',
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (!data || !data.estado_registro) {
                            if (type === 'export') {
                                return 'Sin estado';
                            }
                            return '<span class="fw-medium">Sin estado</span>';
                        }

                        var estado = data.estado_registro;

                        if (type === 'export') {
                            return estado;
                        }

                        var badgeClass = data.bg_clase ? 'badge ' + data.bg_clase : 'badge bg-dark';
                        return `<span class="${badgeClass}">${estado}</span>`;
                    }
                },
                {
                    data: 'botones',
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    width: '200px',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return '';
                        }

                        var botones = '';

                        if (data && data.length > 0) {
                            var editarBoton = '';
                            var otrosBotones = '';

                            data.forEach(boton => {
                                var claseBoton = 'btn-sm me-1 ';
                                if (boton.bg_clase && boton.text_clase) {
                                    claseBoton += boton.bg_clase + ' ' + boton.text_clase;
                                } else if (boton.color_clase) {
                                    claseBoton += boton.color_clase;
                                } else {
                                    claseBoton += 'btn-outline-primary';
                                }

                                var titulo = boton.descripcion || boton.nombre_funcion;
                                var accionJs = boton.accion_js;
                                var icono = boton.icono_clase ? `<i class="${boton.icono_clase}"></i>` : '';
                                var esConfirmable = boton.es_confirmable || 0;

                                var botonHtml = `<button type="button" class="btn ${claseBoton} btn-accion-entidad" 
                                               title="${titulo}" 
                                               data-id="${row.entidad_id}" 
                                               data-accion="${accionJs}"
                                               data-confirmable="${esConfirmable}"
                                               data-entidad="${row.entidad_nombre}">
                                            ${icono}
                                        </button>`;

                                if (accionJs === 'editar') {
                                    editarBoton = botonHtml;
                                } else {
                                    otrosBotones += botonHtml;
                                }
                            });

                            botones = editarBoton + otrosBotones;
                        } else {
                            botones = '<span class="text-muted small">Sin acciones</span>';
                        }

                        return `<div class="btn-group" role="group">${botones}</div>`;
                    }
                }
            ],
            language: {
                url: '<?= asset_local("js/datatables-es-ES.json") ?>',
                buttons: {
                    excel: 'Excel',
                    pdf: 'PDF',
                    csv: 'CSV',
                    print: 'Imprimir'
                }
            },
            order: currentOrder,
            responsive: true,
            createdRow: function (row, data, dataIndex) {
                if (data.estado_info && data.estado_info.codigo_estandar === 'INACTIVO') {
                    $(row).addClass('table-secondary');
                } else if (data.estado_info && data.estado_info.codigo_estandar === 'BLOQUEADO') {
                    $(row).addClass('table-warning');
                }
                
                if (data.es_proveedor == 1 && data.es_cliente == 1) {
                    $(row).addClass('table-info');
                }
            },
            initComplete: function () {
                var buttons = new $.fn.dataTable.Buttons(tablaEntidades, {
                    buttons: ['excelHtml5', 'pdfHtml5', 'csvHtml5', 'print']
                }).container().appendTo($('#tablaEntidades_wrapper .col-md-6:eq(1)'));

                $(tablaEntidades.table().container()).on('page.dt', function (e) {
                    currentPage = tablaEntidades.page();
                });

                $(tablaEntidades.table().container()).on('order.dt', function (e, settings, details) {
                    currentOrder = tablaEntidades.order();
                });

                $(tablaEntidades.table().container()).on('search.dt', function (e, settings) {
                    currentSearch = tablaEntidades.search();
                });

                setTimeout(function () {
                    var searchInput = $('.dataTables_filter input');
                    if (searchInput.val() === '-1' || searchInput.val() === '') {
                        searchInput.val('');
                        currentSearch = '';

                        var savedData = localStorage.getItem('DataTables_' + tablaEntidades.settings()[0].sInstance);
                        if (savedData) {
                            var data = JSON.parse(savedData);
                            if (data.search && (data.search.search === '-1' || data.search.search === '')) {
                                data.search.search = '';
                                localStorage.setItem('DataTables_' + tablaEntidades.settings()[0].sInstance, JSON.stringify(data));
                            }
                        }
                    }
                }, 100);
            }
        });

        inicializarEventos();
    }

    // Función para inicializar DataTable de sucursales
    function inicializarDataTableSucursales(entidadId) {
        if ($.fn.DataTable.isDataTable('#tablaSucursales')) {
            if (tablaSucursales) {
                tablaSucursales.destroy();
            }
            $('#tablaSucursales tbody').empty();
        }

        if (!entidadId) {
            $('#tablaSucursales tbody').html('<tr><td colspan="9" class="text-center text-muted">Seleccione una entidad primero</td></tr>');
            return;
        }

        tablaSucursales = $('#tablaSucursales').DataTable({
            ajax: {
                url: 'entidades_ajax.php',
                type: 'GET',
                data: {
                    accion: 'listar_sucursales',
                    empresa_idx: empresa_idx,
                    entidad_id: entidadId
                },
                dataSrc: ''
            },
            dom: '<"row"<"col-sm-12"tr>>' +
                '<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>',
            pageLength: 10,
            lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
            columns: [
                {
                    data: 'sucursal_id',
                    className: 'text-center fw-bold'
                },
                {
                    data: 'sucursal_nombre',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return data;
                        }
                        return `<div class="fw-medium">${data}</div>`;
                    }
                },
                {
                    data: 'sucursal_direccion',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return data || '';
                        }
                        return data || '<span class="text-muted fst-italic">No especificada</span>';
                    }
                },
                {
                    data: 'localidad_info',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return data && data.localidad ? data.localidad : '';
                        }
                        
                        if (!data || !data.localidad) {
                            return '<span class="text-muted fst-italic">No especificada</span>';
                        }
                        
                        return data.localidad;
                    }
                },
                {
                    data: 'sucursal_telefono',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return data || '';
                        }
                        return data || '<span class="text-muted fst-italic">N/D</span>';
                    }
                },
                {
                    data: 'sucursal_email',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return data || '';
                        }
                        return data ? `<a href="mailto:${data}">${data}</a>` : '<span class="text-muted fst-italic">N/D</span>';
                    }
                },
                {
                    data: 'sucursal_contacto',
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return data || '';
                        }
                        return data || '<span class="text-muted fst-italic">No especificado</span>';
                    }
                },
                {
                    data: 'estado_info',
                    className: 'text-center',
                    render: function (data, type, row) {
                        if (!data || !data.estado_registro) {
                            if (type === 'export') {
                                return 'Sin estado';
                            }
                            return '<span class="fw-medium">Sin estado</span>';
                        }

                        var estado = data.estado_registro;

                        if (type === 'export') {
                            return estado;
                        }

                        var badgeClass = data.bg_clase ? 'badge ' + data.bg_clase : 'badge bg-dark';
                        return `<span class="${badgeClass}">${estado}</span>`;
                    }
                },
                {
                    data: 'botones',
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    render: function (data, type, row) {
                        if (type === 'export') {
                            return '';
                        }

                        var botones = '';

                        if (data && data.length > 0) {
                            data.forEach(boton => {
                                var claseBoton = 'btn-xs me-1 ';
                                if (boton.bg_clase && boton.text_clase) {
                                    claseBoton += boton.bg_clase + ' ' + boton.text_clase;
                                } else if (boton.color_clase) {
                                    claseBoton += boton.color_clase;
                                } else {
                                    claseBoton += 'btn-outline-primary';
                                }

                                var titulo = boton.descripcion || boton.nombre_funcion;
                                var accionJs = boton.accion_js;
                                var icono = boton.icono_clase ? `<i class="${boton.icono_clase}"></i>` : '';
                                var esConfirmable = boton.es_confirmable || 0;

                                botones += `<button type="button" class="btn ${claseBoton} btn-accion-sucursal" 
                                   title="${titulo}" 
                                   data-id="${row.sucursal_id}" 
                                   data-accion="${accionJs}"
                                   data-confirmable="${esConfirmable}"
                                   data-sucursal="${row.sucursal_nombre}">
                                ${icono}
                            </button>`;
                            });
                        } else {
                            botones = '<span class="text-muted small">Sin acciones</span>';
                        }

                        return `<div class="btn-group" role="group">${botones}</div>`;
                    }
                }
            ],
            language: {
                url: '<?= asset_local("js/datatables-es-ES.json") ?>'
            },
            responsive: true,
            createdRow: function (row, data, dataIndex) {
                if (data.estado_info && data.estado_info.codigo_estandar === 'INACTIVO') {
                    $(row).addClass('table-secondary');
                }
            },
            drawCallback: function () {
                var total = tablaSucursales.rows().count();
                $('#contador-sucursales').text(total).toggleClass('bg-secondary', total === 0).toggleClass('bg-primary', total > 0);
            }
        });
    }

    // Función para inicializar eventos
    function inicializarEventos() {
        $('#btnRecargar').off('click').on('click', function () {
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');

            var savedState = {
                page: tablaEntidades.page(),
                order: tablaEntidades.order(),
                search: tablaEntidades.search()
            };

            tablaEntidades.ajax.reload(function (json) {
                if (savedState.page !== undefined) {
                    tablaEntidades.page(savedState.page).draw('page');
                }
                if (savedState.search && savedState.search !== '') {
                    tablaEntidades.search(savedState.search).draw();
                }
                btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
            }, false);
        });
    }

    // Cargar botón Agregar dinámicamente
    function cargarBotonAgregar() {
        $.get('entidades_ajax.php', {
            accion: 'obtener_boton_agregar',
            pagina_idx: pagina_idx
        }, function (botonAgregar) {
            if (botonAgregar && botonAgregar.nombre_funcion) {
                var icono = botonAgregar.icono_clase ? `<i class="${botonAgregar.icono_clase} me-1"></i>` : '';

                var colorClase = 'btn-primary';
                if (botonAgregar.bg_clase && botonAgregar.text_clase) {
                    colorClase = botonAgregar.bg_clase + ' ' + botonAgregar.text_clase;
                } else if (botonAgregar.color_clase) {
                    colorClase = botonAgregar.color_clase;
                }

                $('#contenedor-boton-agregar').html(
                    `<button type="button" class="btn ${colorClase}" id="btnNuevo">
                        ${icono}${botonAgregar.nombre_funcion}
                     </button>`
                );
            } else {
                $('#contenedor-boton-agregar').html(
                    '<button type="button" class="btn btn-primary" id="btnNuevo">' +
                    '<i class="fas fa-plus me-1"></i>Agregar Entidad</button>'
                );
            }
        }, 'json');
    }

    // ============================================
    // EVENTOS EXISTENTES
    // ============================================

    $(document).on('click', '#btnHistorialCliente', function () {
        if (!entidadActualId) {
            Swal.fire({
                icon: "warning",
                title: "Advertencia",
                text: "Seleccione una entidad primero",
                confirmButtonText: "Entendido"
            });
            return;
        }
        
        inicializarTablaHistorialClientes();
        var modal = new bootstrap.Modal(document.getElementById('modalHistorialCliente'));
        modal.show();
    });

    // Manejador para botón "Ver Historial" de proveedor
    $(document).on('click', '#btnHistorialProveedor', function () {
        if (!entidadActualId) {
            Swal.fire({
                icon: "warning",
                title: "Advertencia",
                text: "Seleccione una entidad primero",
                confirmButtonText: "Entendido"
            });
            return;
        }
        
        inicializarTablaHistorialProveedores();
        var modal = new bootstrap.Modal(document.getElementById('modalHistorialProveedor'));
        modal.show();
    });

    // Manejador para botón "Agregar"
    $(document).on('click', '#btnNuevo', function () {
        resetModal();
        $('#modalLabel').text('Nueva Entidad');
        $('#sucursales-tab').addClass('disabled');
        cargarCuentasContables();
        var modal = new bootstrap.Modal(document.getElementById('modalEntidad'));
        modal.show();
        $('#entidad_nombre').focus();
    });

    // Manejador para botón "Nueva Sucursal"
    $(document).on('click', '#btnNuevaSucursal', function () {
        if (!entidadActualId) {
            Swal.fire({
                icon: "warning",
                title: "Advertencia",
                text: "Debe guardar la entidad primero antes de agregar sucursales",
                confirmButtonText: "Entendido"
            });
            return;
        }
        
        resetModalSucursal();
        $('#modalSucursalLabel').text('Nueva Sucursal');
        $('#entidad_id_sucursal').val(entidadActualId);
        var modal = new bootstrap.Modal(document.getElementById('modalSucursal'));
        modal.show();
        $('#sucursal_nombre').focus();
    });

    // Manejador para botones de acción de entidades
    $(document).on('click', '.btn-accion-entidad', function () {
        var entidadId = $(this).data('id');
        var accionJs = $(this).data('accion');
        var confirmable = $(this).data('confirmable');
        var entidad = $(this).data('entidad');

        if (accionJs === 'editar') {
            cargarEntidadParaEditar(entidadId);
        } else if (confirmable == 1) {
            Swal.fire({
                title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                html: `¿Está seguro de <strong>${accionJs}</strong> la entidad <strong>"${entidad}"</strong>?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: `Sí, ${accionJs}`,
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    ejecutarAccionEntidad(entidadId, accionJs, entidad);
                }
            });
        } else {
            ejecutarAccionEntidad(entidadId, accionJs, entidad);
        }
    });

    // Manejador para botones de acción de sucursales
    $(document).on('click', '.btn-accion-sucursal', function () {
        var sucursalId = $(this).data('id');
        var accionJs = $(this).data('accion');
        var confirmable = $(this).data('confirmable');
        var sucursal = $(this).data('sucursal');

        if (accionJs === 'editar') {
            cargarSucursalParaEditar(sucursalId);
        } else if (confirmable == 1) {
            Swal.fire({
                title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                html: `¿Está seguro de <strong>${accionJs}</strong> la sucursal <strong>"${sucursal}"</strong>?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: `Sí, ${accionJs}`,
                cancelButtonText: 'Cancelar',
                reverseButtons: true,
                allowOutsideClick: false
            }).then((result) => {
                if (result.isConfirmed) {
                    ejecutarAccionSucursal(sucursalId, accionJs, sucursal);
                }
            });
        } else {
            ejecutarAccionSucursal(sucursalId, accionJs, sucursal);
        }
    });

    // Manejador para botón "Nueva Condición Cliente"
    $(document).on('click', '#btnNuevaCondicionCliente', function () {
        if (!entidadActualId) {
            Swal.fire({
                icon: "warning",
                title: "Advertencia",
                text: "Debe guardar la entidad primero antes de agregar condiciones",
                confirmButtonText: "Entendido"
            });
            return;
        }
        
        // Mostrar indicador de carga
        Swal.fire({
            title: 'Cargando...',
            text: 'Obteniendo datos de la condición vigente',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Cargar tipos de cliente
        cargarTiposCliente();
        
        // Obtener condición vigente para precargar datos
        $.get('entidades_ajax.php', {
            accion: 'obtener_condicion_cliente_vigente',
            entidad_id: entidadActualId
        }, function (condicionVigente) {
            Swal.close();
            
            resetModalCondicionCliente();
            $('#modalCondicionClienteLabel').text('Nueva Condición de Cliente (basada en la actual)');
            $('#entidad_id_condicion_cliente').val(entidadActualId);
            
            // Precargar datos de la condición vigente si existe
            if (condicionVigente && condicionVigente.entidad_condicion_cliente_id) {
                // Esperar a que se carguen los tipos
                setTimeout(function() {
                    $('#entidad_cliente_tipo_id').val(condicionVigente.entidad_cliente_tipo_id || '');
                    actualizarAccesoWeb();
                }, 200);
                
                $('#condicion_pago_cliente_id').val(condicionVigente.condicion_pago_id);
                $('#lista_precio_id').val(condicionVigente.lista_precio_id);
                $('#limite_credito').val(condicionVigente.limite_credito);
                $('#cliente_descuento_general').val(condicionVigente.cliente_descuento_general);
                
                // Mostrar mensaje informativo
                Swal.fire({
                    icon: "info",
                    title: "Datos precargados",
                    text: "Se cargaron los datos de la condición vigente. Modifique solo lo necesario.",
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            }
            
            // Set fecha desde por defecto a hoy
            var today = new Date().toISOString().split('T')[0];
            $('#f_desde_cliente').val(today);
            
            var modal = new bootstrap.Modal(document.getElementById('modalCondicionCliente'));
            modal.show();
            
        }).fail(function() {
            Swal.close();
            resetModalCondicionCliente();
            $('#modalCondicionClienteLabel').text('Nueva Condición de Cliente');
            $('#entidad_id_condicion_cliente').val(entidadActualId);
            
            var today = new Date().toISOString().split('T')[0];
            $('#f_desde_cliente').val(today);
            
            var modal = new bootstrap.Modal(document.getElementById('modalCondicionCliente'));
            modal.show();
        });
    });

    // Manejador para botón "Nueva Condición Proveedor"
    $(document).on('click', '#btnNuevaCondicionProveedor', function () {
        if (!entidadActualId) {
            Swal.fire({
                icon: "warning",
                title: "Advertencia",
                text: "Debe guardar la entidad primero antes de agregar condiciones",
                confirmButtonText: "Entendido"
            });
            return;
        }
        
        // Mostrar indicador de carga
        Swal.fire({
            title: 'Cargando...',
            text: 'Obteniendo datos de la condición vigente',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        // Obtener condición vigente para precargar datos
        $.get('entidades_ajax.php', {
            accion: 'obtener_condicion_proveedor_vigente',
            entidad_id: entidadActualId
        }, function (condicionVigente) {
            Swal.close();
            
            resetModalCondicionProveedor();
            $('#modalCondicionProveedorLabel').text('Nueva Condición de Proveedor (basada en la actual)');
            $('#entidad_id_condicion_proveedor').val(entidadActualId);
            
            // Precargar datos de la condición vigente si existe
            if (condicionVigente && condicionVigente.entidad_condicion_proveedor_id) {
                $('#condicion_pago_proveedor_id').val(condicionVigente.condicion_pago_id);
                $('#proveedor_categoria_id').val(condicionVigente.proveedor_categoria_id);
                $('#proveedor_descuento_general').val(condicionVigente.proveedor_descuento_general);
                $('#entidad_cliente_tipo_id').val(condicionVigente.entidad_cliente_tipo_id || '');
                
                // Mostrar mensaje informativo
                Swal.fire({
                    icon: "info",
                    title: "Datos precargados",
                    text: "Se cargaron los datos de la condición vigente. Modifique solo lo necesario.",
                    timer: 2000,
                    showConfirmButton: false,
                    toast: true,
                    position: 'top-end'
                });
            }
            
            // Set fecha desde por defecto a hoy
            var today = new Date().toISOString().split('T')[0];
            $('#f_desde_proveedor').val(today);
            
            var modal = new bootstrap.Modal(document.getElementById('modalCondicionProveedor'));
            modal.show();
            
        }).fail(function() {
            Swal.close();
            resetModalCondicionProveedor();
            $('#modalCondicionProveedorLabel').text('Nueva Condición de Proveedor');
            $('#entidad_id_condicion_proveedor').val(entidadActualId);
            
            var today = new Date().toISOString().split('T')[0];
            $('#f_desde_proveedor').val(today);
            
            var modal = new bootstrap.Modal(document.getElementById('modalCondicionProveedor'));
            modal.show();
        });
    });

    // Funciones para resetear modales
    function resetModalCondicionCliente() {
        $('#formCondicionCliente')[0].reset();
        $('#condicion_cliente_id').val('');
        $('#entidad_cliente_tipo_id').val('');
        $('#acceso_web_texto').html('Seleccione un tipo para ver el acceso a la web');
        $('#formCondicionCliente').removeClass('was-validated');
        
        // Recargar los tipos de cliente cada vez que se abre el modal
        cargarTiposCliente();
    }
    function resetModalCondicionProveedor() {
        $('#formCondicionProveedor')[0].reset();
        $('#condicion_proveedor_id').val('');
        $('#formCondicionProveedor').removeClass('was-validated');
    }
     // Guardar condición de cliente - CORREGIDO
    $('#btnGuardarCondicionCliente').click(function () {
        var form = document.getElementById('formCondicionCliente');

        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }

        var id = $('#condicion_cliente_id').val();
        var accionBackend = id ? 'editar_condicion_cliente' : 'agregar_condicion_cliente';
        
        // Obtener valores correctamente
        var entidadClienteTipoId = $('#entidad_cliente_tipo_id').val();
        
        // Debug
        console.log('Tipo de cliente seleccionado:', entidadClienteTipoId);
        console.log('f_desde:', $('#f_desde_cliente').val());

        var btnGuardar = $(this);
        var originalText = btnGuardar.html();
        btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: 'entidades_ajax.php',
            type: 'POST',
            data: {
                accion: accionBackend,
                condicion_cliente_id: id,
                entidad_id: $('#entidad_id_condicion_cliente').val(),
                entidad_cliente_tipo_id: entidadClienteTipoId || null,  // Enviar null si está vacío
                condicion_pago_id: $('#condicion_pago_cliente_id').val(),
                lista_precio_id: $('#lista_precio_id').val() || null,
                limite_credito: $('#limite_credito').val() || null,
                cliente_descuento_general: $('#cliente_descuento_general').val() || null,
                f_desde: $('#f_desde_cliente').val(),
                f_hasta: $('#f_hasta_cliente').val() || null,
                empresa_idx: empresa_idx
            },
            success: function (res) {
                if (res.resultado) {
                    tablaCondicionesClientes.ajax.reload();
                    cargarCondicionClienteVigente();
                    cargarCuentasContables();
                    
                    btnGuardar.prop('disabled', false).html(originalText);
                    
                    Swal.fire({
                        icon: "success",
                        title: "¡Guardado!",
                        text: res.message || "Condición guardada correctamente",
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });
                    
                    var modalEl = document.getElementById('modalCondicionCliente');
                    var modal = bootstrap.Modal.getInstance(modalEl);
                    modal.hide();
                } else {
                    btnGuardar.prop('disabled', false).html(originalText);
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || "Error al guardar los datos",
                        confirmButtonText: "Entendido"
                    });
                }
            },
            error: function (xhr, status, error) {
                console.error('Error AJAX:', error);
                console.error('Respuesta:', xhr.responseText);
                btnGuardar.prop('disabled', false).html(originalText);
                Swal.fire({
                    icon: "error",
                    title: "Error de conexión",
                    text: "Error al comunicarse con el servidor",
                    confirmButtonText: "Entendido"
                });
            }
        });
    });

    // Guardar condición de proveedor
    $('#btnGuardarCondicionProveedor').click(function () {
        var form = document.getElementById('formCondicionProveedor');

        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }

        var id = $('#condicion_proveedor_id').val();
        var accionBackend = id ? 'editar_condicion_proveedor' : 'agregar_condicion_proveedor';

        var btnGuardar = $(this);
        var originalText = btnGuardar.html();
        btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: 'entidades_ajax.php',
            type: 'POST',
            data: {
                accion: accionBackend,
                condicion_proveedor_id: id,
                entidad_id: $('#entidad_id_condicion_proveedor').val(),
                condicion_pago_id: $('#condicion_pago_proveedor_id').val(),
                proveedor_categoria_id: $('#proveedor_categoria_id').val(),
                proveedor_descuento_general: $('#proveedor_descuento_general').val(),
                f_desde: $('#f_desde_proveedor').val(),
                f_hasta: $('#f_hasta_proveedor').val(),
                empresa_idx: empresa_idx
            },
            success: function (res) {
                if (res.resultado) {
                    if (tablaCondicionesProveedores) {
                        tablaCondicionesProveedores.ajax.reload();
                    }
                    
                    cargarCondicionProveedorVigente();
                    
                    btnGuardar.prop('disabled', false).html(originalText);
                    
                    Swal.fire({
                        icon: "success",
                        title: "¡Guardado!",
                        text: res.message || "Condición guardada correctamente",
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });
                    
                    var modalEl = document.getElementById('modalCondicionProveedor');
                    var modal = bootstrap.Modal.getInstance(modalEl);
                    modal.hide();
                } else {
                    btnGuardar.prop('disabled', false).html(originalText);
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || "Error al guardar los datos",
                        confirmButtonText: "Entendido"
                    });
                }
            },
            error: function () {
                btnGuardar.prop('disabled', false).html(originalText);
                Swal.fire({
                    icon: "error",
                    title: "Error de conexión",
                    text: "Error al comunicarse con el servidor",
                    confirmButtonText: "Entendido"
                });
            }
        });
    });

    // Funciones para editar condiciones (globales)
    window.editarCondicionCliente = function(id) {
        $.get('entidades_ajax.php', {
            accion: 'obtener_condicion_cliente',
            condicion_cliente_id: id
        }, function (res) {
            if (res && res.entidad_condicion_cliente_id) {
                resetModalCondicionCliente();
                
                // Cargar tipos de cliente primero
                cargarTiposCliente();
                
                $('#condicion_cliente_id').val(res.entidad_condicion_cliente_id);
                $('#entidad_id_condicion_cliente').val(res.entidad_id);
                
                // Esperar un momento para que se carguen los tipos
                setTimeout(function() {
                    $('#entidad_cliente_tipo_id').val(res.entidad_cliente_tipo_id || '');
                    actualizarAccesoWeb();
                }, 100);
                
                $('#condicion_pago_cliente_id').val(res.condicion_pago_id);
                $('#lista_precio_id').val(res.lista_precio_id);
                $('#limite_credito').val(res.limite_credito);
                $('#cliente_descuento_general').val(res.cliente_descuento_general);
                $('#f_desde_cliente').val(res.f_desde);
                $('#f_hasta_cliente').val(res.f_hasta);
                
                $('#modalCondicionClienteLabel').text('Editar Condición de Cliente');
                var modal = new bootstrap.Modal(document.getElementById('modalCondicionCliente'));
                modal.show();
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos de la condición",
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json');
    };

    window.editarCondicionProveedor = function(id) {
        $.get('entidades_ajax.php', {
            accion: 'obtener_condicion_proveedor',
            condicion_proveedor_id: id
        }, function (res) {
            if (res && res.entidad_condicion_proveedor_id) {
                resetModalCondicionProveedor();
                
                $('#condicion_proveedor_id').val(res.entidad_condicion_proveedor_id);
                $('#entidad_id_condicion_proveedor').val(res.entidad_id);
                $('#condicion_pago_proveedor_id').val(res.condicion_pago_id);
                $('#proveedor_categoria_id').val(res.proveedor_categoria_id);
                $('#proveedor_descuento_general').val(res.proveedor_descuento_general);
                $('#f_desde_proveedor').val(res.f_desde);
                $('#f_hasta_proveedor').val(res.f_hasta);
                
                $('#modalCondicionProveedorLabel').text('Editar Condición de Proveedor');
                var modal = new bootstrap.Modal(document.getElementById('modalCondicionProveedor'));
                modal.show();
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos de la condición",
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json');
    };

    // Función para ejecutar cualquier acción del backend (entidades)
    function ejecutarAccionEntidad(entidadId, accionJs, entidad) {
        var savedState = {
            page: tablaEntidades.page(),
            order: tablaEntidades.order(),
            search: tablaEntidades.search()
        };

        $.post('entidades_ajax.php', {
            accion: 'ejecutar_accion_entidad',
            entidad_id: entidadId,
            accion_js: accionJs,
            empresa_idx: empresa_idx,
            pagina_idx: pagina_idx
        }, function (res) {
            if (res.success) {
                tablaEntidades.ajax.reload(function (json) {
                    if (savedState.page !== undefined) {
                        tablaEntidades.page(savedState.page).draw('page');
                    }
                    if (savedState.search && savedState.search !== '') {
                        tablaEntidades.search(savedState.search).draw();
                    }

                    tablaEntidades.rows().every(function (rowIdx, tableLoop, rowLoop) {
                        var data = this.data();
                        if (data.entidad_id == entidadId) {
                            $(this.node()).addClass('table-success');
                            setTimeout(function () {
                                $(this.node()).removeClass('table-success');
                            }.bind(this), 2000);
                        }
                    });

                    Swal.fire({
                        icon: "success",
                        title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                        text: res.message || `Entidad "${entidad}" actualizada correctamente`,
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });
                }, false);
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: res.error || `Error al ${accionJs} la entidad`,
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json');
    }

    // Función para ejecutar cualquier acción del backend (sucursales)
    function ejecutarAccionSucursal(sucursalId, accionJs, sucursal) {
        $.post('entidades_ajax.php', {
            accion: 'ejecutar_accion_sucursal',
            sucursal_id: sucursalId,
            accion_js: accionJs,
            empresa_idx: empresa_idx,
            pagina_idx: pagina_idx
        }, function (res) {
            if (res.success) {
                tablaSucursales.ajax.reload(function (json) {
                    tablaSucursales.rows().every(function (rowIdx, tableLoop, rowLoop) {
                        var data = this.data();
                        if (data.sucursal_id == sucursalId) {
                            $(this.node()).addClass('table-success');
                            setTimeout(function () {
                                $(this.node()).removeClass('table-success');
                            }.bind(this), 2000);
                        }
                    });

                    Swal.fire({
                        icon: "success",
                        title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                        text: res.message || `Sucursal "${sucursal}" actualizada correctamente`,
                        showConfirmButton: false,
                        timer: 1500,
                        toast: true,
                        position: 'top-end'
                    });
                }, false);
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: res.error || `Error al ${accionJs} la sucursal`,
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json');
    }

    // Función para cargar entidad en modal de edición
    function cargarEntidadParaEditar(entidadId) {
        $.get('entidades_ajax.php', {
            accion: 'obtener_entidad',
            entidad_id: entidadId,
            empresa_idx: empresa_idx
        }, function (res) {
            if (res && res.entidad_id) {
                resetModal();
                cargarCuentasContables();
                
                $('#entidad_id').val(res.entidad_id);
                $('#entidad_nombre').val(res.entidad_nombre);
                $('#entidad_fantasia').val(res.entidad_fantasia || '');
                $('#cuit').val(res.cuit || '');
                $('#sitio_web').val(res.sitio_web || '');
                $('#domicilio_legal').val(res.domicilio_legal || '');
                $('#observaciones').val(res.observaciones || '');
                
                // Set selects
                if (res.entidad_tipo_id) {
                    setTimeout(function() {
                        $('#entidad_tipo_id').val(res.entidad_tipo_id);
                    }, 100);
                }

                setTimeout(function() {
                    $('#cont_cuenta_id_proveedor').val(res.cont_cuenta_id_proveedor || '');
                    $('#cont_cuenta_id_cliente').val(res.cont_cuenta_id_cliente || '');
                }, 100);

                if (res.localidad_id) {
                    setTimeout(function() {
                        $('#localidad_id').val(res.localidad_id);
                    }, 100);
                }
                
                // Set checkboxes
                $('#es_proveedor').prop('checked', parseInt(res.es_proveedor) === 1);
                $('#es_cliente').prop('checked', parseInt(res.es_cliente) === 1);
                
                $('#modalLabel').text('Editar Entidad');
                entidadActualId = res.entidad_id;
                $('#sucursales-tab').removeClass('disabled');
                
                // Cargar todas las tablas
                inicializarDataTableSucursales(entidadActualId);
                inicializarDataTableCondicionesFiscales(entidadActualId);
                cargarCondicionFiscalVigente(entidadActualId);
                inicializarDataTableCondicionesIIBB(entidadActualId);
                cargarCondicionIIBBVigente(entidadActualId);
                inicializarDataTableCondicionesClientes(entidadActualId);
                inicializarDataTableCondicionesProveedores(entidadActualId);
                cargarCondicionClienteVigente();
                cargarCondicionProveedorVigente();
                cargarUsuarioWebEntidad();
                inicializarDataTableSucursalesCompra(entidadActualId);
                cargarTiposCliente();
                
                var modal = new bootstrap.Modal(document.getElementById('modalEntidad'));
                modal.show();

            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos de la entidad",
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json');
    }

    // Función para cargar sucursal en modal de edición
    function cargarSucursalParaEditar(sucursalId) {
        $.get('entidades_ajax.php', {
            accion: 'obtener_sucursal',
            sucursal_id: sucursalId,
            empresa_idx: empresa_idx
        }, function (res) {
            if (res && res.sucursal_id) {
                resetModalSucursal();
                
                $('#sucursal_id').val(res.sucursal_id);
                $('#entidad_id_sucursal').val(res.entidad_id);
                $('#sucursal_nombre').val(res.sucursal_nombre);
                $('#sucursal_direccion').val(res.sucursal_direccion || '');
                $('#sucursal_telefono').val(res.sucursal_telefono || '');
                $('#sucursal_email').val(res.sucursal_email || '');
                $('#sucursal_contacto').val(res.sucursal_contacto || '');
                
                // Set select
                if (res.localidad_id) {
                    setTimeout(function() {
                        $('#localidad_id_sucursal').val(res.localidad_id);
                    }, 100);
                }
                
                $('#modalSucursalLabel').text('Editar Sucursal');
                var modal = new bootstrap.Modal(document.getElementById('modalSucursal'));
                modal.show();

            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos de la sucursal",
                    confirmButtonText: "Entendido"
                });
            }
        }, 'json');
    }

    // Función para resetear el modal de entidad
    function resetModal() {
        $('#formEntidad')[0].reset();
        $('#entidad_id').val('');
        $('#cont_cuenta_id_proveedor').val('');
        $('#cont_cuenta_id_cliente').val('');
        $('#formEntidad').removeClass('was-validated');
        entidadActualId = 0;
        $('#sucursales-tab').addClass('disabled');
        $('#tablaSucursales tbody').html('<tr><td colspan="9" class="text-center text-muted">Seleccione una entidad primero</td></tr>');
        $('#contador-sucursales').text('0').addClass('bg-secondary').removeClass('bg-primary');
        
        // Resetear situación fiscal (sección de Datos Principales)
        resetSituacionFiscal();
        if ($.fn.DataTable.isDataTable('#tablaCondicionesFiscales')) {
            $('#tablaCondicionesFiscales tbody').html('<tr><td colspan="7" class="text-center text-muted">Seleccione una entidad primero</td></tr>');
        }
        if ($.fn.DataTable.isDataTable('#tablaCondicionesIIBB')) {
            $('#tablaCondicionesIIBB tbody').html('<tr><td colspan="8" class="text-center text-muted">Seleccione una entidad primero</td></tr>');
        }
        
        // Resetear condiciones
        if ($.fn.DataTable.isDataTable('#tablaCondicionesClientes')) {
            $('#tablaCondicionesClientes tbody').html('<tr><td colspan="9" class="text-center text-muted">Seleccione una entidad primero</td></tr>');
        }
        if ($.fn.DataTable.isDataTable('#tablaCondicionesProveedores')) {
            $('#tablaCondicionesProveedores tbody').html('<tr><td colspan="8" class="text-center text-muted">Seleccione una entidad primero</td></tr>');
        }
        $('#contador-condiciones-clientes').text('0').addClass('bg-secondary').removeClass('bg-primary');
        $('#contador-condiciones-proveedores').text('0').addClass('bg-secondary').removeClass('bg-primary');
        $('#condicion-cliente-vigente').html('<div class="col-12 text-center text-muted py-4">Seleccione una entidad primero</div>');
        $('#condicion-proveedor-vigente').html('<div class="col-12 text-center text-muted py-4">Seleccione una entidad primero</div>');
        $('#usuario-web-entidad').html('<div class="text-muted">Seleccione una entidad primero</div>');
        
        // Resetear sucursales de compra
        if ($.fn.DataTable.isDataTable('#tablaSucursalesCompra')) {
            $('#tablaSucursalesCompra tbody').html('<tr><td colspan="8" class="text-center text-muted">Seleccione una entidad primero</td></tr>');
        }
        $('#contador-sucursales-compra').text('0').addClass('bg-secondary').removeClass('bg-primary');
    }

    // Función para resetear el modal de sucursal
    function resetModalSucursal() {
        $('#formSucursal')[0].reset();
        $('#sucursal_id').val('');
        $('#formSucursal').removeClass('was-validated');
    }

    // ============================================
    // GUARDAR ENTIDAD
    // ============================================
    $('#btnGuardar').click(function () {
        var form = document.getElementById('formEntidad');

        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }

        var id = $('#entidad_id').val();
        var accionBackend = id ? 'editar_entidad' : 'agregar_entidad';
        var entidadNombre = $('#entidad_nombre').val().trim();
        var cuit = $('#cuit').val();
        var sitioWeb = $('#sitio_web').val();
        
        var esProveedor = $('#es_proveedor').is(':checked') ? 1 : 0;
        var esCliente = $('#es_cliente').is(':checked') ? 1 : 0;
        
        if (cuit && (cuit < 0 || cuit > 99999999999)) {
            $('#cuit').addClass('is-invalid');
            $('#cuit').next('.invalid-feedback').remove();
            $('#cuit').after('<div class="invalid-feedback">CUIT debe tener 11 dígitos</div>');
            return false;
        }
        
        if (sitioWeb && !isValidUrl(sitioWeb)) {
            $('#sitio_web').addClass('is-invalid');
            $('#sitio_web').next('.invalid-feedback').remove();
            $('#sitio_web').after('<div class="invalid-feedback">URL inválida</div>');
            return false;
        }

        if (!entidadNombre) {
            $('#entidad_nombre').addClass('is-invalid');
            return false;
        }

        if (entidadNombre.length > 255) {
            $('#entidad_nombre').addClass('is-invalid');
            return false;
        }

        var btnGuardar = $(this);
        var originalText = btnGuardar.html();
        btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        var savedState = {
            page: tablaEntidades.page(),
            order: tablaEntidades.order(),
            search: tablaEntidades.search()
        };

        $.ajax({
            url: 'entidades_ajax.php',
            type: 'POST',
            data: {
                accion: accionBackend,
                entidad_id: id,
                entidad_nombre: entidadNombre,
                entidad_fantasia: $('#entidad_fantasia').val().trim(),
                entidad_tipo_id: $('#entidad_tipo_id').val(),
                cont_cuenta_id_proveedor: $('#cont_cuenta_id_proveedor').val() || null,
                cont_cuenta_id_cliente: $('#cont_cuenta_id_cliente').val() || null,
                cuit: cuit,
                sitio_web: sitioWeb,
                domicilio_legal: $('#domicilio_legal').val().trim(),
                localidad_id: $('#localidad_id').val(),
                es_proveedor: esProveedor,
                es_cliente: esCliente,
                observaciones: $('#observaciones').val().trim(),
                condicion_fiscal_id: $('#condicion_fiscal_id').val(),
                regimen_iibb_id: $('#regimen_iibb_id').val(),
                numero_inscripcion_iibb: $('#numero_inscripcion_iibb').val().trim(),
                jurisdiccion_sede_id: $('#jurisdiccion_sede_id').val(),
                fiscal_f_desde: $('#fiscal_f_desde').val(),
                empresa_idx: empresa_idx,
                pagina_idx: pagina_idx
            },
            success: function (res) {
                if (res.resultado) {
                    tablaEntidades.ajax.reload(function (json) {
                        if (savedState.page !== undefined) {
                            tablaEntidades.page(savedState.page).draw('page');
                        }
                        if (savedState.search && savedState.search !== '') {
                            tablaEntidades.search(savedState.search).draw();
                        }

                        if (id) {
                            tablaEntidades.rows().every(function (rowIdx, tableLoop, rowLoop) {
                                var data = this.data();
                                if (data.entidad_id == id) {
                                    $(this.node()).addClass('table-success');
                                    setTimeout(function () {
                                        $(this.node()).removeClass('table-success');
                                    }.bind(this), 2000);
                                }
                            });
                            // El CUIT pudo haber cambiado: refrescar estado de usuario web
                            cargarUsuarioWebEntidad();
                            // Situación fiscal: mostrar la vigente (y su historial) tras el cambio
                            cargarCondicionFiscalVigente(id);
                            cargarCondicionIIBBVigente(id);
                            if (tablaCondicionesFiscales) tablaCondicionesFiscales.ajax.reload();
                            if (tablaCondicionesIIBB) tablaCondicionesIIBB.ajax.reload();
                        } else if (res.entidad_id) {
                            // NUEVA ENTIDAD: Inicializar todas las tablas
                            entidadActualId = res.entidad_id;
                            $('#entidad_id').val(entidadActualId);
                            $('#sucursales-tab').removeClass('disabled');
                            
                            inicializarDataTableSucursales(entidadActualId);
                            inicializarDataTableCondicionesFiscales(entidadActualId);
                            cargarCondicionFiscalVigente(entidadActualId);
                            inicializarDataTableCondicionesIIBB(entidadActualId);
                            cargarCondicionIIBBVigente(entidadActualId);
                            inicializarDataTableCondicionesClientes(entidadActualId);
                            inicializarDataTableCondicionesProveedores(entidadActualId);
                            inicializarDataTableSucursalesCompra(entidadActualId);
                            cargarUsuarioWebEntidad();
                        }

                        btnGuardar.prop('disabled', false).html(originalText);

                        Swal.fire({
                            icon: "success",
                            title: "¡Guardado!",
                            text: "Entidad guardada correctamente",
                            showConfirmButton: false,
                            timer: 1500,
                            toast: true,
                            position: 'top-end'
                        });

                    }, false);
                } else {
                    btnGuardar.prop('disabled', false).html(originalText);
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || "Error al guardar los datos",
                        confirmButtonText: "Entendido"
                    });
                }
            },
            error: function () {
                btnGuardar.prop('disabled', false).html(originalText);
                Swal.fire({
                    icon: "error",
                    title: "Error de conexión",
                    text: "Error al comunicarse con el servidor",
                    confirmButtonText: "Entendido"
                });
            }
        });
    });

    // ============================================
    // GUARDAR SUCURSAL
    // ============================================
    $('#btnGuardarSucursal').click(function () {
        var form = document.getElementById('formSucursal');

        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }

        var id = $('#sucursal_id').val();
        var accionBackend = id ? 'editar_sucursal' : 'agregar_sucursal';
        var sucursalNombre = $('#sucursal_nombre').val().trim();
        var sucursalEmail = $('#sucursal_email').val();

        if (!sucursalNombre) {
            $('#sucursal_nombre').addClass('is-invalid');
            return false;
        }

        if (sucursalNombre.length > 150) {
            $('#sucursal_nombre').addClass('is-invalid');
            return false;
        }

        if (sucursalEmail && !isValidEmail(sucursalEmail)) {
            $('#sucursal_email').addClass('is-invalid');
            $('#sucursal_email').next('.invalid-feedback').remove();
            $('#sucursal_email').after('<div class="invalid-feedback">Email inválido</div>');
            return false;
        }

        var btnGuardar = $(this);
        var originalText = btnGuardar.html();
        btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: 'entidades_ajax.php',
            type: 'POST',
            data: {
                accion: accionBackend,
                sucursal_id: id,
                entidad_id: $('#entidad_id_sucursal').val(),
                sucursal_nombre: sucursalNombre,
                sucursal_direccion: $('#sucursal_direccion').val().trim(),
                localidad_id: $('#localidad_id_sucursal').val(),
                sucursal_telefono: $('#sucursal_telefono').val().trim(),
                sucursal_email: sucursalEmail,
                sucursal_contacto: $('#sucursal_contacto').val().trim(),
                empresa_idx: empresa_idx
            },
            success: function (res) {
                if (res.resultado) {
                    tablaSucursales.ajax.reload(function (json) {
                        if (id) {
                            tablaSucursales.rows().every(function (rowIdx, tableLoop, rowLoop) {
                                var data = this.data();
                                if (data.sucursal_id == id) {
                                    $(this.node()).addClass('table-success');
                                    setTimeout(function () {
                                        $(this.node()).removeClass('table-success');
                                    }.bind(this), 2000);
                                }
                            });
                        }

                        btnGuardar.prop('disabled', false).html(originalText);

                        Swal.fire({
                            icon: "success",
                            title: "¡Guardado!",
                            text: "Sucursal guardada correctamente",
                            showConfirmButton: false,
                            timer: 1500,
                            toast: true,
                            position: 'top-end'
                        });

                        var modalEl = document.getElementById('modalSucursal');
                        var modal = bootstrap.Modal.getInstance(modalEl);
                        modal.hide();
                    }, false);
                } else {
                    btnGuardar.prop('disabled', false).html(originalText);
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || "Error al guardar los datos",
                        confirmButtonText: "Entendido"
                    });
                }
            },
            error: function () {
                btnGuardar.prop('disabled', false).html(originalText);
                Swal.fire({
                    icon: "error",
                    title: "Error de conexión",
                    text: "Error al comunicarse con el servidor",
                    confirmButtonText: "Entendido"
                });
            }
        });
    });

    // ============================================
    // FUNCIONES AUXILIARES
    // ============================================
    function isValidUrl(string) {
        try {
            new URL(string);
            return true;
        } catch (_) {
            return false;
        }
    }

    function isValidEmail(email) {
        var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    // ============================================
    // BOTONES DEL DROPDOWN
    // ============================================
    $('#btnExportarExcel').click(function (e) {
        e.preventDefault();
        $('.buttons-excel').click();
    });

    $('#btnExportarPDF').click(function (e) {
        e.preventDefault();
        $('.buttons-pdf').click();
    });

    $('#btnExportarCSV').click(function (e) {
        e.preventDefault();
        $('.buttons-csv').click();
    });

    $('#btnExportarPrint').click(function (e) {
        e.preventDefault();
        $('.buttons-print').click();
    });

    // ============================================
    // INICIALIZACIÓN
    // ============================================
    inicializarDataTableEntidades();
    cargarBotonAgregar();
    cargarTiposEntidad();
    cargarLocalidades();
    cargarCondicionesPago();
    cargarListasPrecios();
    cargarCategoriasProveedores();
    cargarTiposCliente();
    cargarCatalogoCondicionesFiscales();
    cargarCatalogosIIBB();

    // Agregar tooltips a los botones
    $('[title]').tooltip({
        trigger: 'hover',
        placement: 'top'
    });

    // Limpiar localStorage si tiene el bug del "-1"
    $(window).on('load', function () {
        setTimeout(function () {
            var savedData = localStorage.getItem('DataTables_tablaEntidades');
            if (savedData) {
                var data = JSON.parse(savedData);
                if (data.search) {
                    if (data.search.search === '-1' || data.search.search === '') {
                        data.search.search = '';
                        localStorage.setItem('DataTables_tablaEntidades', JSON.stringify(data));
                    }
                }
            }
        }, 500);
    });
    // Función para actualizar la información de acceso web al seleccionar un tipo
    function actualizarAccesoWeb() {
        var tipoId = $('#entidad_cliente_tipo_id').val();
        
        if (!tipoId) {
            $('#acceso_web_texto').html('Seleccione un tipo para ver el acceso a la web');
            return;
        }
        
        // Buscar el tipo en la lista cargada
        var tipoSeleccionado = tiposClienteData.find(function(t) {
            return t.entidad_cliente_tipo_id == tipoId;
        });
        
        if (tipoSeleccionado) {
            var acceso = tipoSeleccionado.acceso_web == 1;
            var icono = acceso ? 'fa-check-circle text-success' : 'fa-times-circle text-danger';
            var texto = acceso ? 'Este tipo de cliente tiene <strong>acceso al carrito virtual</strong>' 
                            : 'Este tipo de cliente <strong>NO tiene acceso</strong> al carrito virtual';
            var badge = acceso 
                ? '<span class="badge bg-success">Acceso web</span>' 
                : '<span class="badge bg-danger">Sin acceso web</span>';
            
            $('#acceso_web_texto').html(badge + ' ' + texto);
        }
    }

   // Variable para almacenar los tipos de cliente
    var tiposClienteData = [];

    // Función para cargar tipos de cliente
    function cargarTiposCliente() {
        $.get('entidades_ajax.php', {
            accion: 'obtener_tipos_cliente',
            empresa_idx: empresa_idx
        }, function (tipos) {
            tiposClienteData = tipos;
            var select = $('#entidad_cliente_tipo_id');
            select.empty();
            select.append('<option value="">Seleccionar tipo...</option>');
            
            if (tipos && tipos.length > 0) {
                $.each(tipos, function (index, tipo) {
                    var accesoLabel = tipo.acceso_web == 1 ? '🔓' : '🔒';
                    select.append('<option value="' + tipo.entidad_cliente_tipo_id + '">' + 
                                tipo.entidad_cliente_tipo + ' ' + accesoLabel + '</option>');
                });
            }
            
            // Si hay un valor seleccionado, actualizar la información
            if ($('#entidad_cliente_tipo_id').val()) {
                actualizarAccesoWeb();
            }
        }, 'json');
    }

    // Evento para actualizar acceso web al cambiar el tipo
    $(document).on('change', '#entidad_cliente_tipo_id', function() {
        actualizarAccesoWeb();
    });

    // ============================================
    // FUNCIONES PARA CONDICIONES FISCALES
    // ============================================

    function cargarCatalogoCondicionesFiscales() {
        $.get('entidades_ajax.php', {
            accion: 'obtener_condiciones_fiscales_catalogo'
        }, function(res) {
            catalogoCondicionesFiscales = res || [];
            var select = $('#select_condicion_fiscal_id, #condicion_fiscal_id');
            select.empty();
            select.append('<option value="">Seleccione condición fiscal...</option>');
            if (catalogoCondicionesFiscales.length > 0) {
                catalogoCondicionesFiscales.forEach(function(item) {
                    var texto = item.condicion_fiscal + (item.condicion_fiscal_codigo ? ' (' + item.condicion_fiscal_codigo + ')' : '');
                    select.append(`<option value="${item.condicion_fiscal_id}">${texto}</option>`);
                });
            }
        }, 'json');
    }

    // La condición vigente ya no se muestra en una solapa: carga los campos de la sección
    // "Situación Fiscal" de Datos Principales (se llama al abrir la entidad, al guardarla y al
    // corregir una fila desde el Historial).
    function cargarCondicionFiscalVigente(entidadId) {
        $('#btnHistorialFiscal').prop('disabled', !entidadId);
        if (!entidadId) {
            $('#condicion_fiscal_id').val('');
            $('#vigencia-iva').text('');
            return;
        }

        $.get('entidades_ajax.php', {
            accion: 'obtener_condicion_fiscal_vigente',
            entidad_id: entidadId
        }, function(condicion) {
            if (condicion && condicion.entidad_condicion_fiscal_id) {
                $('#condicion_fiscal_id').val(condicion.condicion_fiscal_id);
                $('#vigencia-iva').html('<i class="fas fa-check-circle text-success me-1"></i>Vigente desde ' + moment(condicion.f_desde).format('DD/MM/YYYY'));
            } else {
                $('#condicion_fiscal_id').val('');
                $('#vigencia-iva').html('<i class="fas fa-exclamation-circle text-warning me-1"></i>Sin condición IVA vigente');
            }
        }, 'json').fail(function() {
            $('#vigencia-iva').html('<span class="text-danger">Error al cargar la condición IVA</span>');
        });
    }

    function inicializarDataTableCondicionesFiscales(entidadId) {
        if ($.fn.DataTable.isDataTable('#tablaCondicionesFiscales')) {
            if (tablaCondicionesFiscales) {
                tablaCondicionesFiscales.destroy();
                tablaCondicionesFiscales = null;
            }
            $('#tablaCondicionesFiscales tbody').empty();
        }

        if (!entidadId) {
            $('#tablaCondicionesFiscales tbody').html('<tr><td colspan="7" class="text-center text-muted">Seleccione una entidad primero</td></tr>');
            $('#contador-condicion-fiscal').text('0').addClass('bg-secondary').removeClass('bg-primary');
            return;
        }

        tablaCondicionesFiscales = $('#tablaCondicionesFiscales').DataTable({
            ajax: {
                url: 'entidades_ajax.php',
                type: 'GET',
                data: {
                    accion: 'obtener_historial_condiciones_fiscales',
                    entidad_id: entidadId
                },
                dataSrc: function(json) {
                    if (json.error) {
                        console.error('Error:', json.error);
                        return [];
                    }
                    return json || [];
                }
            },
            dom: '<"row"<"col-sm-12"tr>>' +
                 '<"row align-items-center mt-2"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>',
            pageLength: 5,
            lengthMenu: [[5, 10, 25], [5, 10, 25]],
            order: [[3, 'desc']],
            columns: [
                { data: 'entidad_condicion_fiscal_id', className: 'text-center fw-bold' },
                {
                    data: 'condicion_fiscal',
                    render: function(data, type, row) {
                        var vigenteBadge = (!row.f_hasta && row.tabla_estado_registro_id == 1)
                            ? ' <span class="badge bg-success ms-1"><i class="fas fa-check me-1"></i>Vigente</span>'
                            : '';
                        return `<strong>${data || '-'}</strong>${vigenteBadge}`;
                    }
                },
                {
                    data: 'condicion_fiscal_codigo',
                    className: 'text-center',
                    render: function(data) {
                        return data ? `<span class="badge bg-light text-dark border">${data}</span>` : '-';
                    }
                },
                {
                    data: 'f_desde',
                    className: 'text-center',
                    render: function(data) {
                        return data ? moment(data).format('DD/MM/YYYY') : '-';
                    }
                },
                {
                    data: 'f_hasta',
                    className: 'text-center',
                    render: function(data) {
                        return data ? moment(data).format('DD/MM/YYYY') : '<span class="badge bg-success">Abierta</span>';
                    }
                },
                {
                    data: 'estado_info',
                    className: 'text-center',
                    render: function(data) {
                        if (!data || !data.estado_registro) {
                            return '<span class="badge bg-secondary">Sin estado</span>';
                        }
                        var badgeClass = data.bg_clase ? 'badge ' + data.bg_clase : 'badge bg-secondary';
                        return `<span class="${badgeClass}">${data.estado_registro}</span>`;
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    render: function(data, type, row) {
                        var botones = '';
                        botones += `<button type="button" class="btn btn-xs btn-outline-primary btn-editar-cf me-1" 
                                        data-id="${row.entidad_condicion_fiscal_id}" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>`;
                        if (row.tabla_estado_registro_id == 1) {
                            botones += `<button type="button" class="btn btn-xs btn-outline-danger btn-estado-cf" 
                                            data-id="${row.entidad_condicion_fiscal_id}" data-estado="2" title="Inhabilitar">
                                        <i class="fas fa-ban"></i>
                                    </button>`;
                        } else {
                            botones += `<button type="button" class="btn btn-xs btn-outline-success btn-estado-cf" 
                                            data-id="${row.entidad_condicion_fiscal_id}" data-estado="1" title="Habilitar">
                                        <i class="fas fa-check"></i>
                                    </button>`;
                        }
                        return `<div class="btn-group" role="group">${botones}</div>`;
                    }
                }
            ],
            language: {
                url: '<?= asset_local("js/datatables-es-ES.json") ?>'
            },
            responsive: true,
            drawCallback: function() {
                var total = tablaCondicionesFiscales ? tablaCondicionesFiscales.rows().count() : 0;
                $('#contador-condicion-fiscal').text(total).toggleClass('bg-secondary', total === 0).toggleClass('bg-primary', total > 0);
            }
        });
    }

    function resetModalCondicionFiscal() {
        $('#formCondicionFiscal')[0].reset();
        $('#entidad_condicion_fiscal_id').val('');
        $('#entidad_id_condicion_fiscal').val(entidadActualId);
        $('#formCondicionFiscal').removeClass('was-validated');
        $('#divCerrarAnterior').show();
        $('#cerrar_anterior').prop('checked', true);
        var today = new Date().toISOString().split('T')[0];
        $('#cf_f_desde').val(today);
        $('#cf_f_hasta').val('');
        $('#modalCondicionFiscalLabel').html('<i class="fas fa-file-invoice-dollar me-2"></i>Nueva Condición Fiscal');
    }

    function cargarCondicionFiscalParaEditar(id) {
        $.get('entidades_ajax.php', {
            accion: 'obtener_condicion_fiscal',
            entidad_condicion_fiscal_id: id
        }, function(res) {
            if (res && res.entidad_condicion_fiscal_id) {
                resetModalCondicionFiscal();
                $('#entidad_condicion_fiscal_id').val(res.entidad_condicion_fiscal_id);
                $('#entidad_id_condicion_fiscal').val(res.entidad_id);
                $('#select_condicion_fiscal_id').val(res.condicion_fiscal_id);
                $('#cf_f_desde').val(res.f_desde);
                $('#cf_f_hasta').val(res.f_hasta || '');
                $('#divCerrarAnterior').hide();
                $('#modalCondicionFiscalLabel').html('<i class="fas fa-file-invoice-dollar me-2"></i>Editar Condición Fiscal');
                var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCondicionFiscal'));
                modal.show();
            } else {
                Swal.fire('Error', res.error || 'No se pudo cargar la condición fiscal', 'error');
            }
        }, 'json');
    }

    // Historial de situación fiscal (IVA + IIBB en un solo modal)
    $(document).on('click', '#btnHistorialFiscal', function () {
        if (!entidadActualId) {
            Swal.fire({
                icon: "warning",
                title: "Advertencia",
                text: "Guarde la entidad primero",
                confirmButtonText: "Entendido"
            });
            return;
        }
        inicializarDataTableCondicionesFiscales(entidadActualId);
        inicializarDataTableCondicionesIIBB(entidadActualId);
        $('#modalHistorialFiscal').attr('data-entidad-id', entidadActualId);
        var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalHistorialFiscal'));
        modal.show();
    });

    $(document).on('click', '#btnNuevaCondicionFiscal, #btnAsignarPrimeraCondicionFiscal', function() {
        if (!entidadActualId) {
            Swal.fire('Atención', 'Seleccione o guarde la entidad primero', 'warning');
            return;
        }
        resetModalCondicionFiscal();
        var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCondicionFiscal'));
        modal.show();
    });

    $(document).on('click', '.btn-editar-cf', function() {
        var id = $(this).data('id');
        var modalHist = bootstrap.Modal.getInstance(document.getElementById('modalHistorialFiscal'));
        if (modalHist) modalHist.hide();
        cargarCondicionFiscalParaEditar(id);
    });

    $(document).on('click', '.btn-estado-cf', function() {
        var id = $(this).data('id');
        var nuevoEstado = $(this).data('estado');
        var accionTexto = nuevoEstado == 1 ? 'habilitar' : 'inhabilitar';

        Swal.fire({
            title: `¿Desea ${accionTexto} el registro?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Cancelar'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.post('entidades_ajax.php', {
                    accion: 'cambiar_estado_condicion_fiscal',
                    entidad_condicion_fiscal_id: id,
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
                        if (tablaCondicionesFiscales) tablaCondicionesFiscales.ajax.reload();
                        cargarCondicionFiscalVigente(entidadActualId);
                    } else {
                        Swal.fire('Error', (res && res.error) || 'No se pudo cambiar el estado', 'error');
                    }
                }, 'json');
            }
        });
    });

    $(document).on('click', '#btnGuardarCondicionFiscal', function() {
        var form = document.getElementById('formCondicionFiscal');
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }

        var fDesde = $('#cf_f_desde').val();
        var fHasta = $('#cf_f_hasta').val();
        if (fHasta && fHasta < fDesde) {
            Swal.fire('Fechas inválidas', 'La fecha hasta debe ser posterior o igual a la fecha desde', 'warning');
            return;
        }

        var btn = $(this);
        var origHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: 'entidades_ajax.php',
            type: 'POST',
            data: {
                accion: 'guardar_condicion_fiscal',
                entidad_condicion_fiscal_id: $('#entidad_condicion_fiscal_id').val(),
                entidad_id: $('#entidad_id_condicion_fiscal').val(),
                condicion_fiscal_id: $('#select_condicion_fiscal_id').val(),
                f_desde: fDesde,
                f_hasta: fHasta,
                cerrar_anterior: $('#cerrar_anterior').is(':checked') ? 1 : 0
            },
            dataType: 'json',
            success: function(res) {
                btn.prop('disabled', false).html(origHtml);
                if (res && res.resultado) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Guardado',
                        text: res.mensaje || 'Condición fiscal guardada con éxito',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    var modalEl = document.getElementById('modalCondicionFiscal');
                    var modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (modalInstance) modalInstance.hide();

                    if (tablaCondicionesFiscales) tablaCondicionesFiscales.ajax.reload();
                    cargarCondicionFiscalVigente(entidadActualId);
                } else {
                    Swal.fire('Error', (res && res.error) || 'No se pudo guardar la condición fiscal', 'error');
                }
            },
            error: function() {
                btn.prop('disabled', false).html(origHtml);
                Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
            }
        });
    });

    // ============================================
    // FUNCIONES NIVEL PROVINCIAL (IIBB)
    // ============================================

    function cargarCatalogosIIBB() {
        $.get('entidades_ajax.php', {
            accion: 'obtener_regimenes_iibb'
        }, function(res) {
            catalogoRegimenesIIBB = res || [];
            var select = $('#select_regimen_iibb_id, #regimen_iibb_id');
            select.empty();
            select.append('<option value="">Seleccione régimen de IIBB...</option>');
            catalogoRegimenesIIBB.forEach(function(item) {
                select.append(`<option value="${item.regimen_iibb_id}" data-codigo="${item.regimen_iibb_codigo}" data-desc="${item.regimen_iibb_descripcion || ''}">${item.regimen_iibb}</option>`);
            });
        }, 'json');

        $.get('entidades_ajax.php', {
            accion: 'obtener_jurisdicciones_sedes_iibb'
        }, function(res) {
            catalogoJurisdiccionesSedesIIBB = res || [];
            var select = $('#select_jurisdiccion_sede_id, #jurisdiccion_sede_id');
            select.empty();
            select.append('<option value="">Seleccione jurisdicción sede (código ARCA/COMARB)...</option>');
            catalogoJurisdiccionesSedesIIBB.forEach(function(item) {
                var label = `${item.codigo_comarb} - ${item.jurisdiccion_sede}` + (item.organismo ? ` (${item.organismo})` : '');
                select.append(`<option value="${item.jurisdiccion_sede_id}">${label}</option>`);
            });
        }, 'json');
    }

    $(document).on('change', '#select_regimen_iibb_id', function() {
        var selected = $(this).find('option:selected');
        var desc = selected.data('desc') || '';
        var codigo = selected.data('codigo') || '';
        $('#descripcion_regimen_iibb').text(desc);

        if (codigo === 'EXENTO') {
            $('#iibb_numero_inscripcion').attr('placeholder', 'Exento / No alcanzado (opcional)');
        } else if (codigo === 'CM') {
            $('#iibb_numero_inscripcion').attr('placeholder', 'Número de CUIT / Convenio Interprovincial');
        } else {
            $('#iibb_numero_inscripcion').attr('placeholder', 'Número local de inscripción (o CUIT)');
        }
    });

    function cargarCondicionIIBBVigente(entidadId) {
        if (!entidadId) {
            $('#regimen_iibb_id, #jurisdiccion_sede_id, #numero_inscripcion_iibb').val('');
            $('#vigencia-iibb').text('');
            return;
        }

        $.get('entidades_ajax.php', {
            accion: 'obtener_condicion_iibb_vigente',
            entidad_id: entidadId
        }, function(condicion) {
            if (condicion && condicion.entidad_condicion_iibb_id) {
                $('#regimen_iibb_id').val(condicion.regimen_iibb_id);
                $('#numero_inscripcion_iibb').val(condicion.numero_inscripcion || '');
                $('#jurisdiccion_sede_id').val(condicion.jurisdiccion_sede_id || '');
                var desde = condicion.f_desde ? ' desde ' + moment(condicion.f_desde).format('DD/MM/YYYY') : '';
                $('#vigencia-iibb').html('<i class="fas fa-check-circle text-success me-1"></i>Vigente' + desde);
            } else {
                $('#regimen_iibb_id, #jurisdiccion_sede_id, #numero_inscripcion_iibb').val('');
                $('#vigencia-iibb').html('<i class="fas fa-exclamation-circle text-warning me-1"></i>Sin condición IIBB vigente');
            }
        }, 'json').fail(function() {
            $('#vigencia-iibb').html('<span class="text-danger">Error al cargar la condición IIBB</span>');
        });
    }

    function resetSituacionFiscal() {
        $('#condicion_fiscal_id, #regimen_iibb_id, #jurisdiccion_sede_id, #numero_inscripcion_iibb').val('');
        $('#vigencia-iva, #vigencia-iibb').text('');
        $('#fiscal_f_desde').val(moment().format('YYYY-MM-DD')).attr('max', moment().format('YYYY-MM-DD'));
        $('#btnHistorialFiscal').prop('disabled', true);
    }

    function inicializarDataTableCondicionesIIBB(entidadId) {
        if ($.fn.DataTable.isDataTable('#tablaCondicionesIIBB')) {
            if (tablaCondicionesIIBB) {
                tablaCondicionesIIBB.destroy();
                tablaCondicionesIIBB = null;
            }
            $('#tablaCondicionesIIBB tbody').empty();
        }

        if (!entidadId) {
            $('#tablaCondicionesIIBB tbody').html('<tr><td colspan="8" class="text-center text-muted">Seleccione una entidad primero</td></tr>');
            return;
        }

        tablaCondicionesIIBB = $('#tablaCondicionesIIBB').DataTable({
            ajax: {
                url: 'entidades_ajax.php',
                type: 'GET',
                data: {
                    accion: 'obtener_historial_condiciones_iibb',
                    entidad_id: entidadId
                },
                dataSrc: function(json) {
                    if (json.error) {
                        console.error('Error IIBB:', json.error);
                        return [];
                    }
                    return json || [];
                }
            },
            dom: '<"row"<"col-sm-12"tr>>' +
                 '<"row align-items-center mt-2"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>',
            pageLength: 5,
            lengthMenu: [[5, 10, 25], [5, 10, 25]],
            order: [[4, 'desc']],
            columns: [
                { data: 'entidad_condicion_iibb_id', className: 'text-center fw-bold' },
                {
                    data: 'regimen_iibb',
                    render: function(data, type, row) {
                        var badge = row.regimen_iibb_codigo ? ` <span class="badge bg-light text-dark border">${row.regimen_iibb_codigo}</span>` : '';
                        var vigenteBadge = (!row.f_hasta && row.tabla_estado_registro_id == 1)
                            ? ' <span class="badge bg-success ms-1"><i class="fas fa-check me-1"></i>Vigente</span>'
                            : '';
                        return `<strong>${data || '-'}</strong>${badge}${vigenteBadge}`;
                    }
                },
                {
                    data: 'numero_inscripcion',
                    className: 'text-center',
                    render: function(data) {
                        return data ? `<span class="fw-medium">${data}</span>` : '<span class="text-muted fst-italic">-</span>';
                    }
                },
                {
                    data: null,
                    render: function(data, type, row) {
                        if (row.codigo_comarb) {
                            return `<span class="badge bg-secondary me-1">${row.codigo_comarb}</span> ${row.jurisdiccion_sede || ''}` + (row.organismo ? ` <small class="text-muted">(${row.organismo})</small>` : '');
                        }
                        return '<span class="text-muted fst-italic">Sin sede</span>';
                    }
                },
                {
                    data: 'f_desde',
                    className: 'text-center',
                    render: function(data) {
                        return data ? moment(data).format('DD/MM/YYYY') : '-';
                    }
                },
                {
                    data: 'f_hasta',
                    className: 'text-center',
                    render: function(data) {
                        return data ? moment(data).format('DD/MM/YYYY') : '<span class="badge bg-success">Abierta</span>';
                    }
                },
                {
                    data: 'estado_info',
                    className: 'text-center',
                    render: function(data, type, row) {
                        if (row.tabla_estado_registro_id == 1) {
                            return '<span class="badge bg-success">Activo</span>';
                        }
                        return '<span class="badge bg-secondary">Inactivo</span>';
                    }
                },
                {
                    data: null,
                    orderable: false,
                    searchable: false,
                    className: "text-center",
                    render: function(data, type, row) {
                        var botones = '';
                        botones += `<button type="button" class="btn btn-xs btn-outline-primary btn-editar-iibb me-1" 
                                        data-id="${row.entidad_condicion_iibb_id}" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>`;
                        if (row.tabla_estado_registro_id == 1) {
                            botones += `<button type="button" class="btn btn-xs btn-outline-danger btn-estado-iibb" 
                                            data-id="${row.entidad_condicion_iibb_id}" data-estado="2" title="Inhabilitar">
                                        <i class="fas fa-ban"></i>
                                    </button>`;
                        } else {
                            botones += `<button type="button" class="btn btn-xs btn-outline-success btn-estado-iibb" 
                                            data-id="${row.entidad_condicion_iibb_id}" data-estado="1" title="Habilitar">
                                        <i class="fas fa-check"></i>
                                    </button>`;
                        }
                        return `<div class="btn-group" role="group">${botones}</div>`;
                    }
                }
            ],
            language: {
                url: '<?= asset_local("js/datatables-es-ES.json") ?>'
            },
            responsive: true
        });
    }

    function resetModalCondicionIIBB() {
        $('#formCondicionIIBB')[0].reset();
        $('#entidad_condicion_iibb_id').val('');
        $('#entidad_id_condicion_iibb').val(entidadActualId);
        $('#formCondicionIIBB').removeClass('was-validated');
        $('#descripcion_regimen_iibb').text('');
        $('#divCerrarAnteriorIIBB').show();
        $('#cerrar_anterior_iibb').prop('checked', true);
        var today = new Date().toISOString().split('T')[0];
        $('#iibb_f_desde').val(today);
        $('#iibb_f_hasta').val('');
        $('#modalCondicionIIBBLabel').html('<i class="fas fa-landmark me-2"></i>Nueva Condición IIBB (Nivel Provincial)');
    }

    function cargarCondicionIIBBParaEditar(id) {
        $.get('entidades_ajax.php', {
            accion: 'obtener_condicion_iibb',
            entidad_condicion_iibb_id: id
        }, function(res) {
            if (res && res.entidad_condicion_iibb_id) {
                resetModalCondicionIIBB();
                $('#entidad_condicion_iibb_id').val(res.entidad_condicion_iibb_id);
                $('#entidad_id_condicion_iibb').val(res.entidad_id);
                $('#select_regimen_iibb_id').val(res.regimen_iibb_id).trigger('change');
                $('#iibb_numero_inscripcion').val(res.numero_inscripcion || '');
                $('#select_jurisdiccion_sede_id').val(res.jurisdiccion_sede_id || '');
                $('#iibb_f_desde').val(res.f_desde);
                $('#iibb_f_hasta').val(res.f_hasta || '');
                $('#iibb_observaciones').val(res.observaciones || '');
                $('#divCerrarAnteriorIIBB').hide();
                $('#modalCondicionIIBBLabel').html('<i class="fas fa-landmark me-2"></i>Editar Condición IIBB (Nivel Provincial)');
                var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCondicionIIBB'));
                modal.show();
            } else {
                Swal.fire('Error', res.error || 'No se pudo cargar la condición IIBB', 'error');
            }
        }, 'json');
    }


    $(document).on('click', '#btnNuevaCondicionIIBB, #btnAsignarPrimeraCondicionIIBB', function() {
        if (!entidadActualId) {
            Swal.fire('Atención', 'Seleccione o guarde la entidad primero', 'warning');
            return;
        }
        resetModalCondicionIIBB();
        var modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCondicionIIBB'));
        modal.show();
    });

    $(document).on('click', '.btn-editar-iibb', function() {
        var id = $(this).data('id');
        var modalHist = bootstrap.Modal.getInstance(document.getElementById('modalHistorialFiscal'));
        if (modalHist) modalHist.hide();
        cargarCondicionIIBBParaEditar(id);
    });

    $(document).on('click', '.btn-estado-iibb', function() {
        var id = $(this).data('id');
        var nuevoEstado = $(this).data('estado');
        var accionTexto = nuevoEstado == 1 ? 'habilitar' : 'inhabilitar';

        Swal.fire({
            title: `¿Desea ${accionTexto} el registro de IIBB?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Sí, continuar',
            cancelButtonText: 'Cancelar'
        }).then(function(result) {
            if (result.isConfirmed) {
                $.post('entidades_ajax.php', {
                    accion: 'cambiar_estado_condicion_iibb',
                    entidad_condicion_iibb_id: id,
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
                        if (tablaCondicionesIIBB) tablaCondicionesIIBB.ajax.reload();
                        cargarCondicionIIBBVigente(entidadActualId);
                    } else {
                        Swal.fire('Error', (res && res.error) || 'No se pudo cambiar el estado', 'error');
                    }
                }, 'json');
            }
        });
    });

    $(document).on('click', '#btnGuardarCondicionIIBB', function() {
        var form = document.getElementById('formCondicionIIBB');
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }

        var fDesde = $('#iibb_f_desde').val();
        var fHasta = $('#iibb_f_hasta').val();
        if (fHasta && fHasta < fDesde) {
            Swal.fire('Fechas inválidas', 'La fecha hasta debe ser posterior o igual a la fecha desde', 'warning');
            return;
        }

        var btn = $(this);
        var origHtml = btn.html();
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

        $.ajax({
            url: 'entidades_ajax.php',
            type: 'POST',
            data: {
                accion: 'guardar_condicion_iibb',
                entidad_condicion_iibb_id: $('#entidad_condicion_iibb_id').val(),
                entidad_id: $('#entidad_id_condicion_iibb').val(),
                regimen_iibb_id: $('#select_regimen_iibb_id').val(),
                numero_inscripcion: $('#iibb_numero_inscripcion').val().trim(),
                jurisdiccion_sede_id: $('#select_jurisdiccion_sede_id').val(),
                f_desde: fDesde,
                f_hasta: fHasta,
                observaciones: $('#iibb_observaciones').val().trim(),
                cerrar_anterior: $('#cerrar_anterior_iibb').is(':checked') ? 1 : 0
            },
            dataType: 'json',
            success: function(res) {
                btn.prop('disabled', false).html(origHtml);
                if (res && res.resultado) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Guardado',
                        text: res.mensaje || 'Condición IIBB guardada con éxito',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 1500
                    });
                    var modalEl = document.getElementById('modalCondicionIIBB');
                    var modalInstance = bootstrap.Modal.getInstance(modalEl);
                    if (modalInstance) modalInstance.hide();

                    if (tablaCondicionesIIBB) tablaCondicionesIIBB.ajax.reload();
                    cargarCondicionIIBBVigente(entidadActualId);
                } else {
                    Swal.fire('Error', (res && res.error) || 'No se pudo guardar la condición IIBB', 'error');
                }
            },
            error: function() {
                btn.prop('disabled', false).html(origHtml);
                Swal.fire('Error', 'Error de comunicación con el servidor', 'error');
            }
        });
    });

    // Ajuste de DataTables al abrir modales de historial
    $('#modalHistorialFiscal').on('shown.bs.modal', function () {
        if (tablaCondicionesFiscales) {
            tablaCondicionesFiscales.columns.adjust().draw();
        }
        if (tablaCondicionesIIBB) {
            tablaCondicionesIIBB.columns.adjust().draw();
        }
    });

    // ============================================
    // FUNCIONES RESPONSIVE
    // ============================================
    function ajustarDataTablesMobile() {
        if (window.innerWidth < 768) {
            if (tablaEntidades) {
                tablaEntidades.settings()[0].oInit.pageLength = 10;
                tablaEntidades.page.len(10).draw();
            }
            $('#tablaEntidades').DataTable().columns([2, 3, 6]).visible(false);
        } else {
            if ($.fn.DataTable.isDataTable('#tablaEntidades')) {
                $('#tablaEntidades').DataTable().columns([2, 3, 6]).visible(true);
            }
        }
    }

    function mejorarDropdownsMobile() {
        $('.dropdown-toggle').on('click', function(e) {
            if (window.innerWidth < 768) {
                e.stopPropagation();
                var $dropdownMenu = $(this).next('.dropdown-menu');
                $('.dropdown-menu').not($dropdownMenu).removeClass('show');
                $dropdownMenu.toggleClass('show');
            }
        });
        
        $(document).on('click', function(e) {
            if (window.innerWidth < 768 && !$(e.target).closest('.dropdown').length) {
                $('.dropdown-menu').removeClass('show');
            }
        });
    }

    function ajustarModalesMobile() {
        $('.modal').on('show.bs.modal', function() {
            if (window.innerWidth < 768) {
                $('body').addClass('modal-open-mobile');
            }
        }).on('hidden.bs.modal', function() {
            $('body').removeClass('modal-open-mobile');
        });
    }

    function prevenirZoomIOS() {
        if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
            $('input, select, textarea').on('focus', function() {
                $(this).css('font-size', '16px');
            });
        }
    }

    ajustarDataTablesMobile();
    mejorarDropdownsMobile();
    ajustarModalesMobile();
    prevenirZoomIOS();

    $(window).on('resize', function() {
        clearTimeout(window.resizedFinished);
        window.resizedFinished = setTimeout(function() {
            ajustarDataTablesMobile();
        }, 250);
    });

    $('.nav-tabs').on('touchstart', function(e) {
        e.stopPropagation();
    });

    if (window.innerWidth < 768) {
        $('[data-bs-toggle="tooltip"]').tooltip('disable');
    }
});
</script>

    <!-- Librerías necesarias para DataTables Buttons -->
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">

    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/locale/es.min.js"></script>
<script src="percepciones_iibb_clientes.js?v=<?= filemtime(__DIR__ . '/percepciones_iibb_clientes.js') ?>"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>

</html>