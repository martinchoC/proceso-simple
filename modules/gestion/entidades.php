<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Gestión de Entidades";
$currentPage = 'entidades';
$modudo_idx = 2;
$pagina_idx = 50; // ID de página para entidades

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
                <div class="modal-dialog modal-xl">
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
                            </ul>
                            
                            <div class="tab-content p-3 border border-top-0 rounded-bottom" id="entidadTabsContent">
                                <!-- Pestaña Datos Principales -->
                                <div class="tab-pane fade show active" id="datos" role="tabpanel" aria-labelledby="datos-tab">
                                    <form id="formEntidad" class="needs-validation" novalidate>
                                        <input type="hidden" id="entidad_id" name="entidad_id" />
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="entidad_nombre" class="form-label">Nombre Legal *</label>
                                                <input type="text" class="form-control" id="entidad_nombre"
                                                    name="entidad_nombre" maxlength="255" required>
                                                
                                            </div>
                                            
                                            <div class="col-md-6 mb-3">
                                                <label for="entidad_fantasia" class="form-label">Nombre Fantasía</label>
                                                <input type="text" class="form-control" id="entidad_fantasia"
                                                    name="entidad_fantasia" maxlength="255">
                                                
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="entidad_tipo_id" class="form-label">Tipo de Entidad</label>
                                                <select class="form-select" id="entidad_tipo_id" name="entidad_tipo_id">                                                    
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-4 mb-3">
                                                <label for="cuit" class="form-label">CUIT (11 dígitos sin guiones)</label>
                                                <input type="number" class="form-control" id="cuit"
                                                    name="cuit" min="0" max="99999999999">                                                
                                            </div>
                                            
                                            <div class="col-md-4 mb-3">
                                                <label for="sitio_web" class="form-label">Sitio Web (Ej: https://empresa.com)</label>
                                                <input type="url" class="form-control" id="sitio_web"
                                                    name="sitio_web" maxlength="150">
                                                
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-8 mb-3">
                                                <label for="domicilio_legal" class="form-label">Domicilio Legal</label>
                                                <input type="text" class="form-control" id="domicilio_legal"
                                                    name="domicilio_legal" maxlength="150">
                                            </div>
                                            
                                            <div class="col-md-4 mb-3">
                                                <label for="localidad_id" class="form-label">Localidad</label>
                                                <select class="form-select" id="localidad_id" name="localidad_id">
                                                    <option value="">Seleccionar localidad...</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-3 mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="es_proveedor" name="es_proveedor" value="1">
                                                    <label class="form-check-label" for="es_proveedor">Es Proveedor</label>
                                                </div>
                                            </div>
                                            
                                            <div class="col-md-3 mb-3">
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" id="es_cliente" name="es_cliente" value="1">
                                                    <label class="form-check-label" for="es_cliente">Es Cliente</label>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-12 mb-3">
                                                <label for="observaciones" class="form-label">Observaciones</label>
                                                <textarea class="form-control" id="observaciones" name="observaciones"
                                                    rows="3" maxlength="1000"></textarea>
                                                <div class="form-text">Máximo 1000 caracteres</div>
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
                                    
                                    <!-- Modal para historial de clientes -->
                                    <div class="modal fade" id="modalHistorialCliente" tabindex="-1" aria-hidden="true">
                                        <div class="modal-dialog modal-xl">
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
                                                                <th>Condición de Pago</th>
                                                                <th>Lista de Precios</th>
                                                                <th>Límite de Crédito</th>
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
                                        <div class="modal-dialog modal-xl">
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
                <div class="modal-dialog modal-lg">
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
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalCondicionClienteLabel">Condición de Cliente</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formCondicionCliente" class="needs-validation" novalidate>
                                <input type="hidden" id="condicion_cliente_id" name="condicion_cliente_id" />
                                <input type="hidden" id="entidad_id_condicion_cliente" name="entidad_id" />
                                
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
                <div class="modal-dialog">
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
        </div>
    </div>

    <style>
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
        <!-- Añadir esto antes de las librerías, después del último </style> existente -->
<style>
    /* ... estilos existentes ... */
    
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
            // Variables de contexto MULTIEMPRESA
            const empresa_idx = 2;
            const pagina_idx = <?php echo $pagina_idx; ?>;

            // Variables para mantener el estado
            var tablaEntidades;
            var tablaSucursales;
            var currentPage = 0;
            var currentOrder = [[1, 'asc']];
            var currentSearch = '';
            var entidadActualId = 0;
            var tablaCondicionesClientes;
            var tablaCondicionesProveedores;
            var tablaHistorialClientes;
            var tablaHistorialProveedores;
            
            // Función para cargar condición vigente de cliente
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
                                        <th width="40%">Condición de Pago:</th>
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
                                    
                                </table>
                            </div>
                        `;
                        $('#condicion-cliente-vigente').html(html);
                    } else {
                        $('#condicion-cliente-vigente').html('<div class="col-12 text-center text-muted py-4">No hay condición vigente para este cliente</div>');
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
                        url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
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
                        url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
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
                            // Usar lista_precio_id y lista_precio (coincide con la estructura de la tabla)
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

            // Función para inicializar DataTable de condiciones de clientes
            function inicializarDataTableCondicionesClientes(entidadId) {
                if ($.fn.DataTable.isDataTable('#tablaCondicionesClientes')) {
                    if (tablaCondicionesClientes) {
                        tablaCondicionesClientes.destroy();
                    }
                    $('#tablaCondicionesClientes tbody').empty();
                }

                if (!entidadId) {
                    $('#tablaCondicionesClientes tbody').html('<tr><td colspan="9" class="text-center text-muted">Seleccione una entidad primero</td></tr>');
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
                        '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                    pageLength: 10,
                    lengthMenu: [[5, 10, 25, 50], [5, 10, 25, 50]],
                    columns: [
                        { data: 'entidad_condicion_cliente_id', className: 'text-center fw-bold' },
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
                                if (data !== null) {
                                    return '$ ' + parseFloat(data).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                                }
                                return '<span class="text-muted">-</span>';
                            }
                        },
                        {
                            data: 'cliente_descuento_general',
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
                                            onclick="editarCondicionCliente(${row.entidad_condicion_cliente_id})">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </div>`;
                            }
                        }
                    ],
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
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
                        '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
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
                        url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
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
                    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                        '<"row"<"col-sm-12"tr>>' +
                        '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>' +
                        '<"clear">',
                    pageLength: 50,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                    buttons: [
                        {
                            extend: 'excelHtml5',
                            text: '<i class="fas fa-file-excel"></i> Excel',
                            className: 'btn btn-success btn-sm',
                            title: 'Entidades',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6, 7],
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
                                columns: [0, 1, 2, 3, 4, 5, 6, 7],
                                orthogonal: 'export'
                            }
                        },
                        {
                            extend: 'csvHtml5',
                            text: '<i class="fas fa-file-csv"></i> CSV',
                            className: 'btn btn-primary btn-sm',
                            title: 'Entidades',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6, 7]
                            }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print"></i> Imprimir',
                            className: 'btn btn-secondary btn-sm',
                            title: 'Entidades',
                            exportOptions: {
                                columns: [0, 1, 2, 3, 4, 5, 6, 7],
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
                        url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json',
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
                        '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
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
                        url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
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
                $('#formCondicionCliente').removeClass('was-validated');
            }

            function resetModalCondicionProveedor() {
                $('#formCondicionProveedor')[0].reset();
                $('#condicion_proveedor_id').val('');
                $('#formCondicionProveedor').removeClass('was-validated');
            }

            // Guardar condición de cliente
            $('#btnGuardarCondicionCliente').click(function () {
                var form = document.getElementById('formCondicionCliente');

                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var id = $('#condicion_cliente_id').val();
                var accionBackend = id ? 'editar_condicion_cliente' : 'agregar_condicion_cliente';

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
                        condicion_pago_id: $('#condicion_pago_cliente_id').val(),
                        lista_precio_id: $('#lista_precio_id').val(),
                        limite_credito: $('#limite_credito').val(),
                        cliente_descuento_general: $('#cliente_descuento_general').val(),
                        f_desde: $('#f_desde_cliente').val(),
                        f_hasta: $('#f_hasta_cliente').val(),
                        empresa_idx: empresa_idx
                    },
                    success: function (res) {
                        if (res.resultado) {
                            tablaCondicionesClientes.ajax.reload();
                            cargarCondicionClienteVigente(); // Para cliente
// o
                            cargarCondicionProveedorVigente(); // Para proveedor
                            
                            btnGuardar.prop('disabled', false).html(originalText);
                            
                            Swal.fire({
                                icon: "success",
                                title: "¡Guardado!",
                                text: "Condición guardada correctamente",
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
                            // Recargar la tabla de condiciones (si existe)
                            if (tablaCondicionesProveedores) {
                                tablaCondicionesProveedores.ajax.reload();
                            }
                            
                            // IMPORTANTE: Recargar la condición vigente
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

            // Funciones para editar condiciones
            function editarCondicionCliente(id) {
                $.get('entidades_ajax.php', {
                    accion: 'obtener_condicion_cliente',
                    condicion_cliente_id: id
                }, function (res) {
                    if (res && res.entidad_condicion_cliente_id) {
                        resetModalCondicionCliente();
                        
                        $('#condicion_cliente_id').val(res.entidad_condicion_cliente_id);
                        $('#entidad_id_condicion_cliente').val(res.entidad_id);
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
            }

            function editarCondicionProveedor(id) {
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
            }

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
                        
                        if (res.localidad_id) {
                            setTimeout(function() {
                                $('#localidad_id').val(res.localidad_id);
                            }, 100);
                        }
                        
                        // Set checkboxes CORRECTAMENTE - usando == para comparación de enteros
                        $('#es_proveedor').prop('checked', parseInt(res.es_proveedor) === 1);
                        $('#es_cliente').prop('checked', parseInt(res.es_cliente) === 1);
                        
                       
                        $('#modalLabel').text('Editar Entidad');
                        entidadActualId = res.entidad_id;
                        $('#sucursales-tab').removeClass('disabled');
                        
                        // Cargar sucursales
                        inicializarDataTableSucursales(entidadActualId);
                        inicializarDataTableCondicionesClientes(entidadActualId);
                        inicializarDataTableCondicionesProveedores(entidadActualId);
                        cargarCondicionClienteVigente();
                        cargarCondicionProveedorVigente();
                        
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
                $('#formEntidad').removeClass('was-validated');
                entidadActualId = 0;
                $('#sucursales-tab').addClass('disabled');
                $('#tablaSucursales tbody').html('<tr><td colspan="9" class="text-center text-muted">Seleccione una entidad primero</td></tr>');
                $('#contador-sucursales').text('0').addClass('bg-secondary').removeClass('bg-primary');
                
                
                // Al final de resetModal
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
            }

            // Función para resetear el modal de sucursal
            function resetModalSucursal() {
                $('#formSucursal')[0].reset();
                $('#sucursal_id').val('');
                $('#formSucursal').removeClass('was-validated');
            }

            // Validación del formulario de entidad
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
                
                // OBTENER VALORES DE CHECKBOXES CORRECTAMENTE
                var esProveedor = $('#es_proveedor').is(':checked') ? 1 : 0;
                var esCliente = $('#es_cliente').is(':checked') ? 1 : 0;
                
                // Validar CUIT si está ingresado
                if (cuit && (cuit < 0 || cuit > 99999999999)) {
                    $('#cuit').addClass('is-invalid');
                    $('#cuit').next('.invalid-feedback').remove();
                    $('#cuit').after('<div class="invalid-feedback">CUIT debe tener 11 dígitos</div>');
                    return false;
                }
                
                // Validar sitio web si está ingresado
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
                        cuit: cuit,
                        sitio_web: sitioWeb,
                        domicilio_legal: $('#domicilio_legal').val().trim(),
                        localidad_id: $('#localidad_id').val(),
                        es_proveedor: esProveedor,  // Usar la variable calculada
                        es_cliente: esCliente,      // Usar la variable calculada
                        observaciones: $('#observaciones').val().trim(),
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
                                } else if (res.entidad_id) {
                                    // Si es nueva entidad, guardar el ID para las sucursales
                                    entidadActualId = res.entidad_id;
                                    $('#entidad_id').val(entidadActualId);
                                    $('#sucursales-tab').removeClass('disabled');
                                    inicializarDataTableSucursales(entidadActualId);
                                    // AGREGAR ESTAS LÍNEAS:
                                    inicializarDataTableCondicionesClientes(entidadActualId);
                                    inicializarDataTableCondicionesProveedores(entidadActualId);
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

            // Validación del formulario de sucursal
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

            // Función auxiliar para validar URL
            function isValidUrl(string) {
                try {
                    new URL(string);
                    return true;
                } catch (_) {
                    return false;
                }
            }

            // Función auxiliar para validar email
            function isValidEmail(email) {
                var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            }

            // Manejadores para los botones del dropdown
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

            // Inicializar
            inicializarDataTableEntidades();
            cargarBotonAgregar();
            cargarTiposEntidad();
            cargarLocalidades();
            cargarCondicionesPago();
            cargarListasPrecios();
            cargarCategoriasProveedores();


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
        });
        // Añadir al final del $(document).ready(function() { ... }); antes de cerrar

// ========== FUNCIONES RESPONSIVE ==========

// Ajustar DataTables en móvil
function ajustarDataTablesMobile() {
    if (window.innerWidth < 768) {
        // Configuración para móvil
        if (tablaEntidades) {
            tablaEntidades.settings()[0].oInit.pageLength = 10;
            tablaEntidades.page.len(10).draw();
        }
        
        // Ocultar columnas menos importantes en móvil
        $('#tablaEntidades').DataTable().columns([2, 3, 6]).visible(false);
    } else {
        // Mostrar todas las columnas en desktop
        if ($.fn.DataTable.isDataTable('#tablaEntidades')) {
            $('#tablaEntidades').DataTable().columns([2, 3, 6]).visible(true);
        }
    }
}

// Manejar dropdowns en móvil
function mejorarDropdownsMobile() {
    $('.dropdown-toggle').on('click', function(e) {
        if (window.innerWidth < 768) {
            e.stopPropagation();
            var $dropdownMenu = $(this).next('.dropdown-menu');
            
            // Cerrar otros dropdowns
            $('.dropdown-menu').not($dropdownMenu).removeClass('show');
            
            // Toggle actual
            $dropdownMenu.toggleClass('show');
        }
    });
    
    // Cerrar dropdowns al hacer click fuera
    $(document).on('click', function(e) {
        if (window.innerWidth < 768 && !$(e.target).closest('.dropdown').length) {
            $('.dropdown-menu').removeClass('show');
        }
    });
}

// Ajustar modales para móvil
function ajustarModalesMobile() {
    $('.modal').on('show.bs.modal', function() {
        if (window.innerWidth < 768) {
            $('body').addClass('modal-open-mobile');
        }
    }).on('hidden.bs.modal', function() {
        $('body').removeClass('modal-open-mobile');
    });
}

// Prevenir zoom en inputs en iOS
function prevenirZoomIOS() {
    if (/iPhone|iPad|iPod/i.test(navigator.userAgent)) {
        $('input, select, textarea').on('focus', function() {
            $(this).css('font-size', '16px');
        });
    }
}

// Llamar funciones al inicio
ajustarDataTablesMobile();
mejorarDropdownsMobile();
ajustarModalesMobile();
prevenirZoomIOS();

// Escuchar cambios de tamaño de ventana
$(window).on('resize', function() {
    clearTimeout(window.resizedFinished);
    window.resizedFinished = setTimeout(function() {
        ajustarDataTablesMobile();
    }, 250);
});

// Mejorar navegación por tabs en móvil
$('.nav-tabs').on('touchstart', function(e) {
    e.stopPropagation();
});

// Ajustar tooltips en móvil
if (window.innerWidth < 768) {
    $('[data-bs-toggle="tooltip"]').tooltip('disable');
}
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
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>

</html>