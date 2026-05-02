<?php
// Configuración de la página
require_once __DIR__ . '/../../db.php';

$pageTitle = "Gestión de Productos";
$currentPage = 'productos';
$modudo_idx = 2;
$pagina_idx = 40; // ID de página para gestión de productos

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-boxes me-2"></i>Gestión de Productos
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Gestión</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Productos</li>
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
                                        <!-- Filtros -->
                                        <div class="row mb-3">
                                            <div class="col-md-3">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">Código</span>
                                                    <input type="text" class="form-control" id="filtroCodigo"
                                                        placeholder="Buscar código">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">Marca</span>
                                                    <select class="form-select" id="filtroMarca">
                                                        <option value="">Todas las marcas</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">Modelo</span>
                                                    <select class="form-select" id="filtroModelo" disabled>
                                                        <option value="">Todos los modelos</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">Submodelo</span>
                                                    <select class="form-select" id="filtroSubmodelo" disabled>
                                                        <option value="">Todos los submodelos</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="row mb-3">
                                            <div class="col-md-12">
                                                <button type="button" class="btn btn-sm btn-outline-primary"
                                                    id="btnAplicarFiltros">
                                                    <i class="fas fa-filter me-1"></i>Filtrar
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary"
                                                    id="btnLimpiarFiltros">
                                                    <i class="fas fa-times me-1"></i>Limpiar
                                                </button>
                                            </div>
                                        </div>

                                        <!-- DataTable -->
                                        <table id="tablaProductos" class="table table-striped table-bordered"
                                            style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="80">ID</th>
                                                    <th width="100">Código</th>
                                                    <th width="200">Nombre</th>
                                                    <th width="150">Marcas</th>
                                                    <th width="150">Modelos</th>
                                                    <th width="150">Submodelos</th>
                                                    <th width="200">Ubicaciones</th>
                                                    <th width="80">Imagen</th>
                                                    <th width="100">Estado</th>
                                                    <th width="120" class="text-center">Acciones</th>
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

            <!-- Modal para crear/editar producto - CON PESTAÑA DE IMÁGENES -->
            <div class="modal fade" id="modalProducto" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true"
                data-bs-backdrop="static">
                <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header bg-gradient-primary text-white border-0">
                            <h5 class="modal-title" id="modalLabel">
                                <i class="fas fa-box me-2"></i>Producto
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-0">
                            <!-- Navegación por pestañas -->
                            <nav>
                                <div class="nav nav-tabs border-0" id="nav-tab" role="tablist">
                                    <button class="nav-link active" id="nav-info-tab" data-bs-toggle="tab"
                                        data-bs-target="#nav-info" type="button" role="tab" aria-controls="nav-info"
                                        aria-selected="true">
                                        <i class="fas fa-info-circle me-2"></i>Información
                                    </button>
                                    <button class="nav-link" id="nav-compatibilidad-tab" data-bs-toggle="tab"
                                        data-bs-target="#nav-compatibilidad" type="button" role="tab"
                                        aria-controls="nav-compatibilidad" aria-selected="false">
                                        <i class="fas fa-car me-2"></i>Compatibilidad
                                    </button>
                                    <button class="nav-link" id="nav-imagenes-tab" data-bs-toggle="tab"
                                        data-bs-target="#nav-imagenes" type="button" role="tab"
                                        aria-controls="nav-imagenes" aria-selected="false">
                                        <i class="fas fa-images me-2"></i>Imágenes
                                    </button>
                                    <button class="nav-link" id="nav-ubicaciones-tab" data-bs-toggle="tab"
                                        data-bs-target="#nav-ubicaciones" type="button" role="tab"
                                        aria-controls="nav-ubicaciones" aria-selected="false">
                                        <i class="fas fa-map-marker-alt me-2"></i>Ubicaciones
                                    </button>
                                    <button class="nav-link" id="nav-proveedores-tab" data-bs-toggle="tab"
                                        data-bs-target="#nav-proveedores" type="button" role="tab"
                                        aria-controls="nav-proveedores" aria-selected="false">
                                        <i class="fas fa-truck me-2"></i>Proveedores
                                    </button>
                                </div>
                            </nav>

                            <div class="tab-content p-3" id="nav-tabContent">
                                <!-- Pestaña de Información -->
                                <div class="tab-pane fade show active" id="nav-info" role="tabpanel"
                                    aria-labelledby="nav-info-tab">
                                    <form id="formProducto" class="needs-validation" novalidate>
                                        <input type="hidden" id="producto_id" name="producto_id" />
                                        <input type="hidden" id="empresa_id" name="empresa_id" value="2" />

                                        <div class="row g-2">
                                            <div class="col-md-4">
                                                <label for="producto_codigo" class="form-label form-label-sm">Código
                                                    *</label>
                                                <input type="text" class="form-control form-control-sm"
                                                    id="producto_codigo" name="producto_codigo" maxlength="50" required>
                                                <div class="invalid-feedback">El código es obligatorio</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="codigo_barras" class="form-label form-label-sm">Código de
                                                    Barras</label>
                                                <input type="text" class="form-control form-control-sm"
                                                    id="codigo_barras" name="codigo_barras" maxlength="150">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="producto_tipo_id" class="form-label form-label-sm">Tipo
                                                    *</label>
                                                <select class="form-select form-select-sm" id="producto_tipo_id"
                                                    name="producto_tipo_id" required>
                                                    <option value="">Seleccionar...</option>
                                                </select>
                                                <div class="invalid-feedback">Seleccione un tipo</div>
                                            </div>
                                        </div>

                                        <div class="row g-2 mt-1">
                                            <div class="col-md-12">
                                                <label for="producto_nombre" class="form-label form-label-sm">Nombre
                                                    *</label>
                                                <input type="text" class="form-control form-control-sm"
                                                    id="producto_nombre" name="producto_nombre" maxlength="150"
                                                    required>
                                                <div class="invalid-feedback">El nombre es obligatorio</div>
                                            </div>
                                        </div>

                                        <div class="row g-2 mt-1">
                                            <div class="col-md-6">
                                                <label for="producto_categoria_id"
                                                    class="form-label form-label-sm">Categoría *</label>
                                                <select class="form-select form-select-sm" id="producto_categoria_id"
                                                    name="producto_categoria_id" required>
                                                    <option value="">Seleccionar categoría...</option>
                                                </select>
                                                <div class="invalid-feedback">Seleccione una categoría</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="unidad_medida_id" class="form-label form-label-sm">Unidad de
                                                    Medida</label>
                                                <select class="form-select form-select-sm" id="unidad_medida_id"
                                                    name="unidad_medida_id">
                                                    <option value="">Seleccionar...</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row g-2 mt-1">
                                            <div class="col-md-4">
                                                <label for="lado" class="form-label form-label-sm">Lado</label>
                                                <input type="text" class="form-control form-control-sm" id="lado"
                                                    name="lado" maxlength="10">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="material" class="form-label form-label-sm">Material</label>
                                                <input type="text" class="form-control form-control-sm" id="material"
                                                    name="material" maxlength="50">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="color" class="form-label form-label-sm">Color</label>
                                                <input type="text" class="form-control form-control-sm" id="color"
                                                    name="color" maxlength="50">
                                            </div>
                                        </div>

                                        <div class="row g-2 mt-1">
                                            <div class="col-md-6">
                                                <label for="peso" class="form-label form-label-sm">Peso (kg)</label>
                                                <input type="number" class="form-control form-control-sm" id="peso"
                                                    name="peso" min="0" step="0.01">
                                            </div>
                                            <div class="col-md-6">
                                                <label for="garantia" class="form-label form-label-sm">Garantía</label>
                                                <input type="text" class="form-control form-control-sm" id="garantia"
                                                    name="garantia" maxlength="50">
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-check form-switch mt-4">
                                                    <input class="form-check-input" type="checkbox" role="switch" 
                                                        id="controla_stock" name="controla_stock" value="1" checked>
                                                    <label class="form-check-label" for="controla_stock">
                                                        <i class="fas fa-boxes me-1"></i>Controla Stock
                                                    </label>
                                                    <div class="form-text">Si está activado, el producto controlará inventario</div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="row g-2 mt-1">
                                            <div class="col-md-6">
                                                <label for="cont_cuenta_id" class="form-label form-label-sm">Cuenta Contable</label>
                                                <select class="form-select form-select-sm" id="cont_cuenta_id" name="cont_cuenta_id">
                                                    <option value="">Seleccionar cuenta...</option>
                                                </select>
                                                <div class="form-text">Cuenta contable para imputación del producto</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="iva_alicuota_id" class="form-label form-label-sm">IVA Alícuota</label>
                                                <select class="form-select form-select-sm" id="iva_alicuota_id" name="iva_alicuota_id">
                                                    <option value="">Seleccionar alícuota...</option>
                                                </select>
                                                <div class="form-text">Alícuota de IVA aplicable al producto</div>
                                            </div>
                                        </div>

                                        <div class="row g-2 mt-1">
                                            <div class="col-md-12">
                                                <label for="producto_descripcion"
                                                    class="form-label form-label-sm">Descripción</label>
                                                <textarea class="form-control form-control-sm" id="producto_descripcion"
                                                    name="producto_descripcion" rows="2"></textarea>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <!-- Pestaña de Compatibilidad -->
                                <div class="tab-pane fade" id="nav-compatibilidad" role="tabpanel"
                                    aria-labelledby="nav-compatibilidad-tab">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">
                                            <i class="fas fa-cogs me-2 text-primary"></i>Compatibilidad del Producto
                                        </h6>
                                        <button type="button" class="btn btn-primary btn-sm"
                                            id="btnAgregarCompatibilidad">
                                            <i class="fas fa-plus me-1"></i>Agregar
                                        </button>
                                    </div>

                                    <!-- Tabla de compatibilidad más compacta -->
                                    <div class="table-responsive" style="max-height: 300px;">
                                        <table id="tablaCompatibilidad" class="table table-sm table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="25%" class="py-1">Marca</th>
                                                    <th width="25%" class="py-1">Modelo</th>
                                                    <th width="25%" class="py-1">Submodelo</th>
                                                    <th width="15%" class="py-1 text-center">Años</th>
                                                    <th width="10%" class="py-1 text-center">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Pestaña de Imágenes -->
                                <div class="tab-pane fade" id="nav-imagenes" role="tabpanel"
                                    aria-labelledby="nav-imagenes-tab">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">
                                            <i class="fas fa-images me-2 text-success"></i>Imágenes del Producto
                                        </h6>
                                        <button type="button" class="btn btn-success btn-sm" id="btnAgregarImagen">
                                            <i class="fas fa-plus me-1"></i>Agregar Imagen
                                        </button>
                                    </div>

                                    <!-- Contenedor de imágenes -->
                                    <div id="galeriaImagenes" class="row g-2">
                                        <!-- Las imágenes se cargarán dinámicamente aquí -->
                                        <div class="col-12 text-center py-5" id="sinImagenes">
                                            <i class="fas fa-image fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No hay imágenes para este producto</p>
                                        </div>
                                    </div>

                                    <div class="alert alert-info mt-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Puedes arrastrar y soltar imágenes para cambiar su orden. La primera imagen será
                                        la principal.
                                    </div>
                                </div>
                                <!-- Pestaña de Ubicaciones -->
                                <div class="tab-pane fade" id="nav-ubicaciones" role="tabpanel"
                                    aria-labelledby="nav-ubicaciones-tab">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">
                                            <i class="fas fa-map-marker-alt me-2 text-warning"></i>Ubicaciones del
                                            Producto
                                        </h6>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-warning btn-sm"
                                                id="btnAgregarUbicacion">
                                                <i class="fas fa-plus me-1"></i>Agregar Ubicación
                                            </button>
                                            <button type="button" class="btn btn-outline-warning btn-sm"
                                                id="btnNuevaUbicacion">
                                                <i class="fas fa-plus-circle me-1"></i>Nueva Ubicación
                                            </button>
                                        </div>
                                    </div>
                                     

                                    <!-- Tabla de ubicaciones -->
                                    <div class="table-responsive" style="max-height: 300px;">
                                        <table id="tablaUbicaciones" class="table table-sm table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="20%" class="py-1">Sucursal</th>
                                                    <th width="15%" class="py-1">Depósito</th>
                                                    <th width="15%" class="py-1">Sección</th>
                                                    <th width="15%" class="py-1">Estantería</th>
                                                    <th width="15%" class="py-1">Estante</th>
                                                    <th width="15%" class="py-1">Posición</th>
                                                    <th width="5%" class="py-1 text-center">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>

                                    <div class="alert alert-warning mt-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Un producto puede tener múltiples ubicaciones en diferentes sucursales.
                                    </div>
                                </div>
                                <!-- Pestaña de Proveedores -->
                                <div class="tab-pane fade" id="nav-proveedores" role="tabpanel"
                                    aria-labelledby="nav-proveedores-tab">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">
                                            <i class="fas fa-truck me-2 text-info"></i>Proveedores del Producto
                                        </h6>
                                        <button type="button" class="btn btn-info btn-sm" id="btnAgregarProveedor">
                                            <i class="fas fa-plus me-1"></i>Agregar Proveedor
                                        </button>
                                    </div>

                                    <!-- Tabla de proveedores -->
                                    <div class="table-responsive" style="max-height: 300px;">
                                        <table id="tablaProveedores" class="table table-sm table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="35%" class="py-1">Proveedor</th>
                                                    <th width="20%" class="py-1">CUIT</th>
                                                    <th width="30%" class="py-1">Código de Proveedor</th>
                                                    <th width="15%" class="py-1 text-center">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>

                                    <div class="alert alert-info mt-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Puede asignar múltiples proveedores a un mismo producto con diferentes códigos.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light border-top py-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Cancelar
                            </button>
                            <button type="button" class="btn btn-sm btn-primary px-3" id="btnGuardar">
                                <i class="fas fa-save me-1"></i>Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para agregar/editar compatibilidad -->
            <div class="modal fade" id="modalCompatibilidad" tabindex="-1" aria-labelledby="modalCompatibilidadLabel"
                aria-hidden="true" data-bs-backdrop="static">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header bg-gradient-info text-white border-0">
                            <h5 class="modal-title" id="modalCompatibilidadLabel">
                                <i class="fas fa-cog me-2"></i>Compatibilidad
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-4">
                            <form id="formCompatibilidad" class="needs-validation" novalidate>
                                <input type="hidden" id="compatibilidad_id" name="compatibilidad_id" />
                                <input type="hidden" id="compatibilidad_producto_id" name="producto_id" />

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="marca_id" class="form-label form-label-sm">Marca *</label>
                                        <select class="form-select form-select-sm" id="marca_id" name="marca_id"
                                            required>
                                            <option value="">Seleccionar marca...</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione una marca</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="modelo_id" class="form-label form-label-sm">Modelo *</label>
                                        <select class="form-select form-select-sm" id="modelo_id" name="modelo_id"
                                            required disabled>
                                            <option value="">Seleccionar modelo...</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione un modelo</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="submodelo_id" class="form-label form-label-sm">Submodelo</label>
                                        <select class="form-select form-select-sm" id="submodelo_id" name="submodelo_id"
                                            disabled>
                                            <option value="">Seleccionar submodelo...</option>
                                            <option value="0">Sin submodelo</option>
                                        </select>
                                        <div class="form-text">Opcional</div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="anio_desde" class="form-label form-label-sm">Año Desde *</label>
                                        <select class="form-select form-select-sm" id="anio_desde" name="anio_desde"
                                            required>
                                            <option value="">Año...</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione el año inicial</div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="anio_hasta" class="form-label form-label-sm">Año Hasta</label>
                                        <select class="form-select form-select-sm" id="anio_hasta" name="anio_hasta">
                                            <option value="">Año...</option>
                                            <option value="0">Actual</option>
                                        </select>
                                        <div class="form-text">Dejar vacío para actual</div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer bg-light border-top">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Cancelar
                            </button>
                            <button type="button" class="btn btn-sm btn-info px-3" id="btnGuardarCompatibilidad">
                                <i class="fas fa-save me-1"></i>Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para subir/editar imagen -->
            <div class="modal fade" id="modalImagen" tabindex="-1" aria-labelledby="modalImagenLabel" aria-hidden="true"
                data-bs-backdrop="static">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header bg-gradient-success text-white border-0">
                            <h5 class="modal-title" id="modalImagenLabel">
                                <i class="fas fa-image me-2"></i>Imagen del Producto
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-4">
                            <form id="formImagen" class="needs-validation" novalidate enctype="multipart/form-data">
                                <input type="hidden" id="imagen_producto_id" name="producto_id" />
                                <input type="hidden" id="producto_imagen_id" name="producto_imagen_id" />

                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label for="imagen_archivo" class="form-label form-label-sm">Imagen *</label>
                                        <input type="file" class="form-control form-control-sm" id="imagen_archivo"
                                            name="imagen" accept="image/*" required>
                                        <div class="invalid-feedback">Seleccione una imagen</div>
                                        <div class="form-text">Formatos permitidos: JPG, PNG, GIF, WebP. Tamaño máximo:
                                            5MB</div>
                                    </div>
                                </div>

                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label for="descripcion_imagen"
                                            class="form-label form-label-sm">Descripción</label>
                                        <input type="text" class="form-control form-control-sm" id="descripcion_imagen"
                                            name="descripcion" maxlength="255" placeholder="Descripción de la imagen">
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="es_principal_imagen" class="form-label form-label-sm">¿Es
                                            principal?</label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch"
                                                id="es_principal_imagen" name="es_principal" value="1">
                                            <label class="form-check-label" for="es_principal_imagen">Marcar como imagen
                                                principal</label>
                                        </div>
                                        <div class="form-text">La imagen principal se mostrará como destacada</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="orden_imagen" class="form-label form-label-sm">Orden</label>
                                        <input type="number" class="form-control form-control-sm" id="orden_imagen"
                                            name="orden" min="0" value="0">
                                        <div class="form-text">Orden de visualización (menor = primero)</div>
                                    </div>
                                </div>

                                <!-- Vista previa de imagen -->
                                <div class="row mt-3" id="vistaPreviaContainer" style="display: none;">
                                    <div class="col-md-12">
                                        <label class="form-label form-label-sm">Vista previa:</label>
                                        <div class="border rounded p-2 text-center">
                                            <img id="vistaPreviaImagen" src="" alt="Vista previa"
                                                class="img-fluid rounded" style="max-height: 200px;">
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer bg-light border-top">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Cancelar
                            </button>
                            <button type="button" class="btn btn-sm btn-success px-3" id="btnGuardarImagen">
                                <i class="fas fa-save me-1"></i>Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Modal para agregar ubicación existente -->
            <div class="modal fade" id="modalUbicacion" tabindex="-1" aria-labelledby="modalUbicacionLabel"
                aria-hidden="true" data-bs-backdrop="static">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header bg-gradient-warning text-white border-0">
                            <h5 class="modal-title" id="modalUbicacionLabel">
                                <i class="fas fa-map-marker-alt me-2"></i>Agregar Ubicación
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-4">
                            <form id="formUbicacion" class="needs-validation" novalidate>
                                <input type="hidden" id="ubicacion_producto_id" name="producto_id" />

                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="sucursal_ubicacion_id" class="form-label form-label-sm">Ubicación
                                            *</label>
                                        <select class="form-select form-select-sm" id="sucursal_ubicacion_id"
                                            name="sucursal_ubicacion_id" required>
                                            <option value="">Seleccionar ubicación...</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione una ubicación</div>
                                        <div class="form-text">Seleccione una ubicación existente para asignarla al
                                            producto</div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer bg-light border-top">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Cancelar
                            </button>
                            <button type="button" class="btn btn-sm btn-warning px-3" id="btnGuardarUbicacion">
                                <i class="fas fa-save me-1"></i>Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para crear nueva ubicación -->
            <div class="modal fade" id="modalNuevaUbicacion" tabindex="-1" aria-labelledby="modalNuevaUbicacionLabel"
                aria-hidden="true" data-bs-backdrop="static">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header bg-gradient-warning text-white border-0">
                            <h5 class="modal-title" id="modalNuevaUbicacionLabel">
                                <i class="fas fa-plus-circle me-2"></i>Crear Nueva Ubicación
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-4">
                            <form id="formNuevaUbicacion" class="needs-validation" novalidate>
                                <input type="hidden" id="nueva_ubicacion_producto_id" name="producto_id" />

                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="sucursal_id" class="form-label form-label-sm">Sucursal *</label>
                                        <select class="form-select form-select-sm" id="sucursal_id" name="sucursal_id"
                                            required>
                                            <option value="">Seleccionar sucursal...</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione una sucursal</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="deposito_id_nueva" class="form-label form-label-sm">Depósito *</label>
                                        <select class="form-select form-select-sm" id="deposito_id_nueva" name="deposito_id" required>
                                            <option value="">Primero seleccione una sucursal...</option>
                                        </select>
                                        <div class="invalid-feedback">Debe seleccionar un depósito</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="seccion" class="form-label form-label-sm">Sección *</label>
                                        <input type="text" class="form-control form-control-sm" id="seccion"
                                            name="seccion" maxlength="50" required
                                            placeholder="Ej: Almacén, Sala de ventas">
                                        <div class="invalid-feedback">La sección es obligatoria</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <label for="estanteria" class="form-label form-label-sm">Estantería *</label>
                                        <input type="text" class="form-control form-control-sm" id="estanteria"
                                            name="estanteria" maxlength="50" required placeholder="Ej: A, B, C">
                                        <div class="invalid-feedback">La estantería es obligatoria</div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="estante" class="form-label form-label-sm">Estante *</label>
                                        <input type="text" class="form-control form-control-sm" id="estante"
                                            name="estante" maxlength="50" required placeholder="Ej: 1, 2, 3">
                                        <div class="invalid-feedback">El estante es obligatorio</div>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <label for="posicion" class="form-label form-label-sm">Posición *</label>
                                        <input type="text" class="form-control form-control-sm" id="posicion"
                                            name="posicion" maxlength="50" required placeholder="Ej: 1, 2, 3, A, B">
                                        <div class="invalid-feedback">La posición es obligatoria</div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="descripcion_ubicacion"
                                            class="form-label form-label-sm">Descripción</label>
                                        <textarea class="form-control form-control-sm" id="descripcion_ubicacion"
                                            name="descripcion" rows="2" maxlength="255"
                                            placeholder="Descripción opcional de la ubicación"></textarea>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer bg-light border-top">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Cancelar
                            </button>
                            <button type="button" class="btn btn-sm btn-warning px-3" id="btnGuardarNuevaUbicacion">
                                <i class="fas fa-save me-1"></i>Crear Ubicación
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- Modal para agregar/editar proveedor -->
            <div class="modal fade" id="modalProveedor" tabindex="-1" aria-labelledby="modalProveedorLabel"
                aria-hidden="true" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header bg-gradient-info text-white border-0">
                            <h5 class="modal-title" id="modalProveedorLabel">
                                <i class="fas fa-truck me-2"></i>Proveedor del Producto
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-4">
                            <form id="formProveedor" class="needs-validation" novalidate>
                                <input type="hidden" id="proveedor_producto_id" name="producto_id" />
                                <input type="hidden" id="producto_proveedor_id" name="producto_proveedor_id" />

                                <div class="mb-3">
                                    <label for="entidad_id" class="form-label">Proveedor *</label>
                                    <select class="form-select" id="entidad_id" name="entidad_id" required>
                                        <option value="">Seleccionar proveedor...</option>
                                    </select>
                                    <div class="invalid-feedback">Seleccione un proveedor</div>
                                </div>

                                <div class="mb-3">
                                    <label for="codigo_proveedor" class="form-label">Código de Proveedor</label>
                                    <input type="text" class="form-control" id="codigo_proveedor"
                                        name="codigo_proveedor" maxlength="50" placeholder="Código que usa el proveedor">
                                    <div class="form-text">Opcional - Código con el que el proveedor identifica este producto</div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer bg-light border-top">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Cancelar
                            </button>
                            <button type="button" class="btn btn-sm btn-info px-3" id="btnGuardarProveedor">
                                <i class="fas fa-save me-1"></i>Guardar
                            </button>
                        </div>
                    </div>
                </div>
            </div>
           
            <!-- Modal para dar de alta un producto -->
            <div class="modal fade" id="modalAltaProducto" tabindex="-1" aria-labelledby="modalAltaLabel"
                aria-hidden="true" data-bs-backdrop="static">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow-lg">
                        <div class="modal-header bg-gradient-success text-white border-0">
                            <h5 class="modal-title" id="modalAltaLabel">Dar de Alta Producto</h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                                aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body p-4">
                            <p id="mensajeAlta"></p>
                            <form id="formAltaProducto" class="needs-validation" novalidate>
                                <input type="hidden" id="alta_producto_id" name="alta_producto_id" />
                                <div class="mb-3">
                                    <label for="motivo_alta" class="form-label">Motivo del Alta *</label>
                                    <textarea class="form-control" id="motivo_alta" name="motivo_alta" rows="3"
                                        maxlength="500" required></textarea>
                                    <div class="invalid-feedback">Por favor ingrese el motivo del alta</div>
                                    <div class="form-text">Máximo 500 caracteres</div>
                                </div>
                                <div class="mb-3">
                                    <label for="fecha_alta" class="form-label">Fecha de Alta *</label>
                                    <input type="date" class="form-control" id="fecha_alta" name="fecha_alta" required>
                                    <div class="invalid-feedback">Seleccione la fecha de alta</div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer bg-light border-top">
                            <button type="button" class="btn btn-outline-secondary"
                                data-bs-dismiss="modal">Cancelar</button>
                            <button type="button" class="btn btn-success" id="btnConfirmarAlta">
                                <i class="fas fa-check me-1"></i>Confirmar Alta
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Estilos personalizados -->
    <style>
        /* Reducir tamaño del modal */
        .modal-xl {
            max-width: 1100px;
        }

        /* Hacer más compacto el contenido del modal */
        .modal-body .form-label-sm {
            font-size: 0.85rem;
            margin-bottom: 0.2rem;
        }

        .modal-body .form-control-sm,
        .modal-body .form-select-sm {
            font-size: 0.85rem;
            padding: 0.25rem 0.5rem;
            height: calc(1.5em + 0.5rem + 2px);
        }

        .modal-footer {
            padding: 0.5rem 1rem;
        }

        /* Hacer la tabla más compacta */
        #tablaCompatibilidad {
            font-size: 0.8rem;
        }

        #tablaCompatibilidad th,
        #tablaCompatibilidad td {
            padding: 0.25rem 0.5rem;
        }

        /* Asegurar que todas las pestañas tengan el mismo tamaño */
        /* Hacer que todas las pestañas tengan el mismo tamaño */
        .tab-content {
            min-height: 450px; /* Altura mínima fija */
            background-color: white;
            transition: min-height 0.3s ease;
        }

        /* Ajustar según el contenido */
        .tab-pane {
            height: 100%;
            overflow-y: auto; /* Scroll si el contenido es muy grande */
        }

        /* Estilos para las tablas dentro de pestañas */
        .tab-pane .table-responsive {
            max-height: 300px;
            overflow-y: auto;
        }

        /* Para la pestaña de información que tiene formulario */
        #nav-info {
            min-height: 400px;
        }

        /* Para la pestaña de imágenes */
        #nav-imagenes {
            min-height: 400px;
        }

        /* Asegurar que todas las pestañas tengan scroll si es necesario */
        .tab-pane {
            max-height: 500px;
            overflow-y: auto;
            padding-right: 5px;
        }

        /* Personalizar la barra de scroll para que sea más delgada */
        .tab-pane::-webkit-scrollbar {
            width: 5px;
        }

        .tab-pane::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }

        .tab-pane::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }

        .tab-pane::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        /* Estilos para pestañas */
        .nav-tabs {
            background-color: #f8f9fa;
            padding: 0.5rem 1.5rem 0;
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 0.75rem 1.5rem;
            margin-right: 0.5rem;
            border-radius: 0.5rem 0.5rem 0 0;
        }

        .nav-tabs .nav-link.active {
            background-color: white;
            color: #0d6efd;
            border-bottom: 3px solid #0d6efd;
        }

        .nav-tabs .nav-link:hover:not(.active) {
            background-color: rgba(0, 0, 0, 0.05);
        }

        /* Contenido de pestañas */
        .tab-content {
            background-color: white;
        }

        /* Botones de acción */
        .btn-accion-alta {
            background-color: #28a745;
            color: white;
            border-color: #28a745;
        }

        .btn-accion-baja {
            background-color: #dc3545;
            color: white;
            border-color: #dc3545;
        }

        .btn-accion-suspender {
            background-color: #ffc107;
            color: #212529;
            border-color: #ffc107;
        }

        .badge-estado-activo {
            background-color: #28a745;
            color: white;
        }

        .badge-estado-inactivo {
            background-color: #6c757d;
            color: white;
        }

        /* Estilos generales del modal */
        .modal-content {
            border-radius: 0.5rem;
        }

        .modal-header {
            border-radius: 0.5rem 0.5rem 0 0;
        }

        .btn-close-white {
            filter: invert(1) grayscale(100%) brightness(200%);
        }

        .bg-gradient-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .bg-gradient-success {
            background: linear-gradient(135deg, #42e695 0%, #3bb2b8 100%);
        }

        .bg-gradient-info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }

        /* Badges para compatibilidad en la tabla */
        .badge-compatibilidad {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
        }

        /* Estilos para galería de imágenes */
        .card-imagen {
            transition: transform 0.2s;
            cursor: move;
        }

        .card-imagen:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .card-imagen-principal {
            border: 2px solid #28a745;
        }

        .imagen-miniatura {
            height: 120px;
            object-fit: cover;
            background-color: #f8f9fa;
        }

        .badge-imagen-principal {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1;
        }

        /* Ordenamiento por arrastre */
        .sortable-ghost {
            opacity: 0.4;
        }

        .sortable-chosen {
            background-color: #f8f9fa;
        }

        /* Agrega estos estilos al final de la sección de estilos: */
        .img-thumbnail {
            transition: transform 0.2s;
            border: 2px solid #dee2e6;
        }

        .img-thumbnail:hover {
            transform: scale(1.1);
            border-color: #0d6efd;
            box-shadow: 0 0 10px rgba(13, 110, 253, 0.5);
        }

        /* Ajustar tamaño de los badges de ubicaciones */
        .badge-compatibilidad {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: inline-block;
        }

        /* Para ubicaciones múltiples */
        .badge-ubicacion {
            font-size: 0.7rem;
            padding: 0.2rem 0.4rem;
            margin-bottom: 0.1rem;
            display: block;
            text-align: left;
            border-radius: 0.25rem;
        }

        /* Asegurar que la imagen en miniatura sea circular */
        .rounded-circle {
            border-radius: 50% !important;
        }
        /* Estilos para el carrusel de imágenes */
            .carousel-container {
                position: relative;
                background: #f8f9fa;
                border-radius: 8px;
                padding: 20px;
            }

            .carousel-imagen-principal {
                max-height: 500px;
                object-fit: contain;
                background: white;
            }

            .carousel-thumbnails {
                display: flex;
                justify-content: center;
                gap: 10px;
                margin-top: 15px;
                flex-wrap: wrap;
            }

            .thumbnail-item {
                width: 80px;
                height: 80px;
                cursor: pointer;
                border: 2px solid transparent;
                border-radius: 8px;
                overflow: hidden;
                transition: all 0.3s;
            }

            .thumbnail-item:hover {
                transform: scale(1.1);
            }

            .thumbnail-item.active {
                border-color: #007bff;
                box-shadow: 0 0 10px rgba(0,123,255,0.5);
            }

            .thumbnail-img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .carousel-control-prev,
            .carousel-control-next {
                width: 10%;
                background: rgba(0,0,0,0.2);
                border-radius: 50%;
                height: 50px;
                top: 50%;
                transform: translateY(-50%);
            }

            .carousel-indicators {
                position: static;
                margin: 10px 0 0;
            }

            .carousel-indicators button {
                width: 12px;
                height: 12px;
                border-radius: 50%;
                background-color: #007bff;
                margin: 0 5px;
            }
            #carruselProducto .carousel-control-prev,
            #carruselProducto .carousel-control-next {
                opacity: 0.8;
                transition: opacity 0.3s;
            }

            #carruselProducto .carousel-control-prev:hover,
            #carruselProducto .carousel-control-next:hover {
                opacity: 1;
            }

            #carruselProducto .carousel-control-prev-icon,
            #carruselProducto .carousel-control-next-icon {
                background-size: 1.5rem;
            }

            #carruselProducto .carousel-indicators {
                margin-bottom: 0.5rem;
            }

            #carruselProducto .carousel-indicators button {
                width: 12px;
                height: 12px;
                border-radius: 50%;
                background-color: #007bff;
                opacity: 0.5;
                margin: 0 5px;
                border: none;
            }

            #carruselProducto .carousel-indicators button.active {
                opacity: 1;
            }

            /* Animación para el fade */
            .carousel-fade .carousel-item {
                opacity: 0;
                transition: opacity 0.5s ease;
            }

            .carousel-fade .carousel-item.active {
                opacity: 1;
            }

            /* Hover en thumbnails */
            .thumbnail-wrapper:hover {
                transform: scale(1.05);
                box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            }
            /* Contador de imágenes */
            .imagen-contador {
                position: absolute;
                bottom: 10px;
                right: 10px;
                background: rgba(0,0,0,0.7);
                color: white;
                padding: 5px 10px;
                border-radius: 20px;
                font-size: 0.9rem;
                z-index: 10;
            }
    </style>
<script>
    $(document).ready(function () {
        // Variables de contexto MULTIEMPRESA
        const empresa_idx = 2;
        const pagina_idx = <?php echo $pagina_idx; ?>;

        // Variables globales
        var tabla;
        var tablaCompatibilidad;
        var tablaUbicaciones;
        var currentPage = 0;
        var currentOrder = [[1, 'asc']];
        var currentSearch = '';
        var productoActualId = null;
        var productoActualCompatibilidad = null;
        var productoActualImagenes = null;
        var productoActualUbicaciones = null;
        var imagenesProductoActual = [];
        var tablaProveedores;
        var productoActualProveedores = null;

        // ========== FUNCIÓN PARA MOSTRAR CARRUSEL (DEFINIR UNA SOLA VEZ) ==========
        window.mostrarImagenGrande = function(url, titulo, productoId) {
            console.log("mostrarImagenGrande llamada con:", {url, titulo, productoId});
            
            // Si tenemos productoId, cargar todas las imágenes del producto
            if (productoId) {
                $.ajax({
                    url: 'productos_ajax.php',
                    type: 'GET',
                    data: {
                        accion: 'obtener_imagenes_producto',
                        producto_id: productoId,
                        empresa_idx: empresa_idx
                    },
                    dataType: 'json',
                    success: function(imagenes) {
                        console.log("Imágenes recibidas:", imagenes);
                        if (imagenes && imagenes.length > 0) {
                            // Encontrar el índice de la imagen actual
                            var indiceActual = 0;
                            var imagenIdActual = url.split('=')[1]; // Extraer el ID de la imagen de la URL

                            imagenes.forEach(function(img, idx) {
                                // Comparar por ID de imagen en lugar de URL completa
                                var imgId = img.imagen_url.split('=')[1];
                                if (imgId == imagenIdActual) {
                                    indiceActual = idx;
                                }
                            });
                            mostrarCarruselImagenes(imagenes, indiceActual);
                        } else {
                            // Si no hay imágenes, mostrar solo esta
                            mostrarCarruselImagenes([{
                                imagen_url: url,
                                descripcion: titulo || 'Imagen del producto'
                            }], 0);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Error al cargar imágenes:", error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'No se pudieron cargar las imágenes'
                        });
                    }
                });
            } else {
                // Si no tenemos productoId, mostrar solo la imagen proporcionada
                mostrarCarruselImagenes([{
                    imagen_url: url,
                    descripcion: titulo || 'Imagen del producto'
                }], 0);
            }
        };
        // Función para cargar depósitos según sucursal seleccionada
        function cargarDepositosParaNuevaUbicacion(sucursalId, selectedId = null) {
            if (!sucursalId) {
                $('#deposito_id_nueva').html('<option value="">Primero seleccione una sucursal...</option>');
                return;
            }
            
            $.get('productos_ajax.php', {
                accion: 'obtener_depositos_por_sucursal',
                sucursal_id: sucursalId,
                empresa_idx: empresa_idx
            }, function(depositos) {
                var select = $('#deposito_id_nueva');
                select.empty();
                select.append('<option value="">Seleccionar depósito...</option>');
                
                $.each(depositos, function(index, deposito) {
                    var selected = (selectedId && deposito.deposito_id == selectedId) ? 'selected' : '';
                    var nombre = deposito.deposito_nombre;
                    if (deposito.es_principal) {
                        nombre += ' (Principal)';
                    }
                    select.append(`<option value="${deposito.deposito_id}" ${selected}>${nombre}</option>`);
                });
            }, 'json');
        }

        // Evento change del select de sucursal en modal de nueva ubicación
        $(document).on('change', '#sucursal_id', function() {
            cargarDepositosParaNuevaUbicacion($(this).val());
        });
        // Cargar opciones de cuentas contables
       function cargarCuentasContables() {
            console.log("=== Iniciando cargarCuentasContables ===");
            console.log("empresa_idx:", empresa_idx);
            
            $.get('productos_ajax.php', {
                accion: 'obtener_cuentas_contables',
                empresa_idx: empresa_idx
            }, function(cuentas) {
                console.log("Respuesta recibida:", cuentas);
                var select = $('#cont_cuenta_id');
                console.log("Select encontrado:", select.length);
                select.empty().append('<option value="">Seleccionar cuenta...</option>');
                
                if (cuentas && cuentas.length > 0) {
                    console.log("Número de cuentas:", cuentas.length);
                    cuentas.forEach(function(cuenta) {
                        var prefix = '';
                        for (var i = 1; i < cuenta.nivel; i++) {
                            prefix += '&nbsp;&nbsp;';
                        }
                        var texto = prefix + cuenta.codigo + ' - ' + cuenta.nombre;
                        select.append(`<option value="${cuenta.cont_cuenta_id}">${texto}</option>`);
                    });
                } else {
                    console.log("No se recibieron cuentas o array vacío");
                }
            }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
                console.error("Error en la petición:", textStatus, errorThrown);
                console.error("Respuesta del servidor:", jqXHR.responseText);
            });
        }
        // Función para mostrar carrusel de imágenes
        // Función para mostrar carrusel de imágenes (MEJORADA)
        function mostrarCarruselImagenes(imagenes, indiceInicial) {
            console.log("Mostrando carrusel con imágenes:", imagenes);
            
            // Validar que haya imágenes
            if (!imagenes || imagenes.length === 0) {
                Swal.fire({
                    icon: 'info',
                    title: 'Sin imágenes',
                    text: 'Este producto no tiene imágenes para mostrar',
                    confirmButtonText: 'Entendido'
                });
                return;
            }

            // Guardar las imágenes globalmente
            imagenesProductoActual = imagenes;

            // Asegurar que todas las imágenes tengan URL válida
            imagenes = imagenes.map(img => {
                if (!img.imagen_url) {
                    img.imagen_url = 'get_imagen.php?id=' + img.imagen_id;
                }
                return img;
            });

            // Construir el carrusel MEJORADO con controles personalizados
            var carouselHtml = `
                <div id="carruselProducto" class="carousel slide carousel-fade" data-bs-ride="false" data-bs-interval="false">
                    <!-- Indicadores -->
                    <div class="carousel-indicators">
                        ${imagenes.map((_, idx) => `
                            <button type="button" 
                                    data-bs-target="#carruselProducto" 
                                    data-bs-slide-to="${idx}" 
                                    class="${idx === indiceInicial ? 'active' : ''}"
                                    aria-current="${idx === indiceInicial ? 'true' : 'false'}"
                                    aria-label="Imagen ${idx + 1}">
                            </button>
                        `).join('')}
                    </div>
                    
                    <!-- Imágenes -->
                    <div class="carousel-inner bg-dark" style="border-radius: 10px; min-height: 500px; display: flex; align-items: center;">
                        ${imagenes.map((img, idx) => `
                            <div class="carousel-item ${idx === indiceInicial ? 'active' : ''}" style="text-align: center;">
                                <img src="${img.imagen_url}" 
                                    class="d-block mx-auto" 
                                    style="max-height: 500px; max-width: 100%; object-fit: contain;"
                                    alt="${img.descripcion || 'Imagen ' + (idx + 1)}"
                                    onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'500\' height=\'500\'><rect width=\'500\' height=\'500\' fill=\'#f8f9fa\'/><text x=\'250\' y=\'250\' text-anchor=\'middle\' fill=\'#6c757d\' font-family=\'Arial\' font-size=\'24\'>Error</text></svg>';">
                                
                                <!-- Descripción de la imagen (opcional) -->
                                ${img.descripcion ? `
                                    <div class="carousel-caption d-none d-md-block bg-dark bg-opacity-50 rounded p-2" style="bottom: 20px;">
                                        <p class="mb-0 text-white">${img.descripcion}</p>
                                    </div>
                                ` : ''}
                            </div>
                        `).join('')}
                    </div>
                    
                    <!-- Controles de navegación MEJORADOS -->
                    <button class="carousel-control-prev" type="button" data-bs-target="#carruselProducto" data-bs-slide="prev" style="width: 10%; background: linear-gradient(90deg, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0) 100%); border: none;">
                        <span class="carousel-control-prev-icon" aria-hidden="true" style="width: 3rem; height: 3rem; background-color: rgba(0,0,0,0.5); border-radius: 50%; padding: 1rem;"></span>
                        <span class="visually-hidden">Anterior</span>
                    </button>
                    <button class="carousel-control-next" type="button" data-bs-target="#carruselProducto" data-bs-slide="next" style="width: 10%; background: linear-gradient(270deg, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0) 100%); border: none;">
                        <span class="carousel-control-next-icon" aria-hidden="true" style="width: 3rem; height: 3rem; background-color: rgba(0,0,0,0.5); border-radius: 50%; padding: 1rem;"></span>
                        <span class="visually-hidden">Siguiente</span>
                    </button>
                    
                    <!-- Contador de imágenes -->
                    <div class="position-absolute top-0 end-0 m-3 bg-dark bg-opacity-75 text-white px-3 py-2 rounded-pill" style="z-index: 10;">
                        <span id="imagenActual">${indiceInicial + 1}</span> / ${imagenes.length}
                    </div>
                </div>
                
                <!-- Thumbnails debajo del carrusel -->
                <div class="row mt-4 justify-content-center" id="thumbnailsContainer">
                    ${imagenes.map((img, idx) => `
                        <div class="col-auto mb-2">
                            <div class="thumbnail-wrapper" 
                                onclick="$('#carruselProducto').carousel(${idx})"
                                style="cursor: pointer; border: 3px solid ${idx === indiceInicial ? '#007bff' : 'transparent'}; border-radius: 8px; overflow: hidden; transition: all 0.3s;">
                                <img src="${img.imagen_url}" 
                                    style="width: 80px; height: 80px; object-fit: cover;" 
                                    alt="Miniatura ${idx + 1}"
                                    onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'80\' height=\'80\'><rect width=\'80\' height=\'80\' fill=\'#f8f9fa\'/><text x=\'40\' y=\'40\' text-anchor=\'middle\' fill=\'#6c757d\' font-family=\'Arial\' font-size=\'12\'>Error</text></svg>';">
                            </div>
                        </div>
                    `).join('')}
                </div>
                
                <div class="text-center mt-3">
                    <button type="button" class="btn btn-outline-secondary" onclick="cerrarModalCarrusel()">
                        <i class="fas fa-times me-1"></i>Cerrar
                    </button>
                </div>
            `;

            // Actualizar el contenido del modal
            $('#contenidoCarrusel').html(carouselHtml);
            
            // Inicializar el carrusel de Bootstrap
            setTimeout(function() {
                var carruselElement = document.getElementById('carruselProducto');
                if (carruselElement) {
                    var carrusel = new bootstrap.Carousel(carruselElement, {
                        interval: false,
                        wrap: true,
                        touch: true
                    });
                }
                
                // Actualizar contador y thumbnails cuando cambie la imagen
                $('#carruselProducto').on('slid.bs.carousel', function(e) {
                    var currentIndex = $('.carousel-item.active').index();
                    $('#imagenActual').text(currentIndex + 1);
                    
                    // Actualizar thumbnails activos
                    $('.thumbnail-wrapper').css('border-color', 'transparent');
                    $('.thumbnail-wrapper').eq(currentIndex).css('border-color', '#007bff');
                });
            }, 100);

            // Mostrar el modal
            var modalElement = document.getElementById('modalCarrusel');
            if (modalElement) {
                var modal = new bootstrap.Modal(modalElement);
                modal.show();
            } else {
                console.error("Modal carrusel no encontrado");
            }
        }

        // Función para cerrar el modal
        window.cerrarModalCarrusel = function() {
            var modalElement = document.getElementById('modalCarrusel');
            if (modalElement) {
                var modal = bootstrap.Modal.getInstance(modalElement);
                if (modal) {
                    modal.hide();
                }
            }
        };

        // ========== FUNCIONES DE CARGA DE DATOS ==========
        // ========== FUNCIONES DE PROVEEDORES ==========

        // Cargar proveedores de un producto
        function cargarProveedoresProducto(productoId) {
            productoActualProveedores = productoId;

            if ($.fn.DataTable.isDataTable('#tablaProveedores')) {
                $('#tablaProveedores').DataTable().destroy();
                $('#tablaProveedores tbody').empty();
            }

            tablaProveedores = $('#tablaProveedores').DataTable({
                ajax: {
                    url: 'productos_ajax.php',
                    type: 'GET',
                    data: {
                        accion: 'obtener_proveedores_producto',
                        producto_id: productoId,
                        empresa_idx: empresa_idx
                    },
                    dataSrc: ''
                },
                columns: [
                    { data: 'entidad_nombre', render: function(data) { return data || '-'; } },
                    { data: 'cuit', render: function(data) { return data || '-'; } },
                    { data: 'codigo_proveedor', render: function(data) { return data || '-'; } },
                    { 
                        data: 'producto_proveedor_id', 
                        orderable: false, 
                        searchable: false, 
                        className: "text-center",
                        render: function(data) {
                            return `<div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-primary btn-editar-proveedor" 
                                        data-id="${data}" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-eliminar-proveedor" 
                                        data-id="${data}" title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>`;
                        }
                    }
                ],
                language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' },
                responsive: true,
                pageLength: 10,
                searching: false,
                paging: false,
                info: false
            });
        }

        // Cargar entidades proveedoras
        function cargarEntidadesProveedores() {
            $.get('productos_ajax.php', {
                accion: 'obtener_entidades_proveedores',
                empresa_idx: empresa_idx
            }, function(entidades) {
                var select = $('#entidad_id');
                select.empty().append('<option value="">Seleccionar proveedor...</option>');
                if (entidades && entidades.length > 0) {
                    entidades.forEach(function(entidad) {
                        var texto = entidad.entidad_nombre;
                        if (entidad.cuit) texto += ' (' + entidad.cuit + ')';
                        select.append(`<option value="${entidad.entidad_id}">${texto}</option>`);
                    });
                }
            }, 'json');
        }

        // Mostrar modal de proveedor
        function mostrarModalProveedor(productoId, productoProveedorId = null) {
            resetModalProveedor();
            $('#proveedor_producto_id').val(productoId);
            cargarEntidadesProveedores();

            if (productoProveedorId) {
                $('#modalProveedorLabel').html('<i class="fas fa-edit me-2"></i>Editar Proveedor');
                $('#producto_proveedor_id').val(productoProveedorId);

                setTimeout(function() {
                    $.get('productos_ajax.php', {
                        accion: 'obtener_proveedor_producto_por_id',
                        producto_proveedor_id: productoProveedorId,
                        empresa_idx: empresa_idx
                    }, function(res) {
                        if (res && res.producto_proveedor_id) {
                            $('#entidad_id').val(res.entidad_id);
                            $('#codigo_proveedor').val(res.codigo_proveedor || '');
                            
                            // Deshabilitar cambio de proveedor en edición
                            $('#entidad_id').prop('disabled', true);
                        }
                    }, 'json');
                }, 300);
            } else {
                $('#modalProveedorLabel').html('<i class="fas fa-plus me-2"></i>Agregar Proveedor');
                $('#entidad_id').prop('disabled', false);
            }

            new bootstrap.Modal(document.getElementById('modalProveedor')).show();
        }

        function resetModalProveedor() {
            $('#formProveedor')[0].reset();
            $('#producto_proveedor_id').val('');
            $('#formProveedor').removeClass('was-validated');
            $('#entidad_id').empty().append('<option value="">Seleccionar proveedor...</option>').prop('disabled', false);
        }

        // Cargar monedas
        function cargarMonedas() {
            $.get('productos_ajax.php', {
                accion: 'obtener_monedas',
                empresa_idx: empresa_idx
            }, function(monedas) {
                var select = $('#moneda_id');
                select.empty().append('<option value="">Seleccionar moneda...</option>');
                if (monedas && monedas.length > 0) {
                    monedas.forEach(function(moneda) {
                        var texto = moneda.moneda_nombre;
                        if (moneda.moneda_simbolo) texto += ' (' + moneda.moneda_simbolo + ')';
                        select.append(`<option value="${moneda.moneda_id}">${texto}</option>`);
                    });
                }
            }, 'json');
        }

        // Mostrar modal de proveedor
        function mostrarModalProveedor(productoId, productoProveedorId = null) {
            resetModalProveedor();
            $('#proveedor_producto_id').val(productoId);
            cargarEntidadesProveedores();
            cargarMonedas();

            if (productoProveedorId) {
                $('#modalProveedorLabel').html('<i class="fas fa-edit me-2"></i>Editar Proveedor');
                $('#producto_proveedor_id').val(productoProveedorId);

                setTimeout(function() {
                    $.get('productos_ajax.php', {
                        accion: 'obtener_proveedor_producto_por_id',
                        producto_proveedor_id: productoProveedorId,
                        empresa_idx: empresa_idx
                    }, function(res) {
                        if (res && res.producto_proveedor_id) {
                            $('#entidad_id').val(res.entidad_id);
                            $('#codigo_proveedor').val(res.codigo_proveedor || '');
                            $('#costo').val(res.costo || '');
                            $('#moneda_id').val(res.moneda_id || '');
                            $('#plazo_entrega_dias').val(res.plazo_entrega_dias || '');
                            
                            // Deshabilitar cambio de proveedor en edición
                            $('#entidad_id').prop('disabled', true);
                        }
                    }, 'json');
                }, 300);
            } else {
                $('#modalProveedorLabel').html('<i class="fas fa-plus me-2"></i>Agregar Proveedor');
                $('#entidad_id').prop('disabled', false);
            }

            new bootstrap.Modal(document.getElementById('modalProveedor')).show();
        }

        function resetModalProveedor() {
            $('#formProveedor')[0].reset();
            $('#producto_proveedor_id').val('');
            $('#formProveedor').removeClass('was-validated');
            $('#entidad_id').empty().append('<option value="">Seleccionar proveedor...</option>').prop('disabled', false);
            $('#moneda_id').empty().append('<option value="">Seleccionar moneda...</option>');
        }
        // Cargar opciones de tipos de producto
        function cargarTiposProducto() {
            $.get('productos_ajax.php', {
                accion: 'obtener_tipos_producto',
                empresa_idx: empresa_idx
            }, function(tipos) {
                var select = $('#producto_tipo_id');
                select.empty().append('<option value="">Seleccionar tipo...</option>');
                if (tipos && tipos.length > 0) {
                    tipos.forEach(function(tipo) {
                        select.append(`<option value="${tipo.producto_tipo_id}">${tipo.producto_tipo} (${tipo.producto_tipo_codigo})</option>`);
                    });
                }
            }, 'json');
        }

        // Cargar opciones de categorías de producto
        function cargarCategoriasProducto() {
            $.get('productos_ajax.php', {
                accion: 'obtener_categorias',
                empresa_idx: empresa_idx
            }, function(categorias) {
                var select = $('#producto_categoria_id');
                select.empty().append('<option value="">Seleccionar categoría...</option>');
                if (categorias && categorias.length > 0) {
                    categorias.forEach(function(categoria) {
                        select.append(`<option value="${categoria.producto_categoria_id}">${categoria.producto_categoria_nombre}</option>`);
                    });
                }
            }, 'json');
        }

        // Cargar opciones de unidades de medida
        function cargarUnidadesMedida() {
            $.get('productos_ajax.php', {
                accion: 'obtener_unidades_medida',
                empresa_idx: empresa_idx
            }, function(unidades) {
                var select = $('#unidad_medida_id');
                select.empty().append('<option value="">Seleccionar unidad...</option>');
                if (unidades && unidades.length > 0) {
                    unidades.forEach(function(unidad) {
                        select.append(`<option value="${unidad.unidad_medida_id}">${unidad.unidad_nombre} (${unidad.unidad_abreviatura})</option>`);
                    });
                }
            }, 'json');
        }

        // Cargar opciones de IVA alícuotas
        function cargarIvaAlicuotas() {
            $.get('productos_ajax.php', {
                accion: 'obtener_iva_alicuotas',
                empresa_idx: empresa_idx
            }, function(alicuotas) {
                var select = $('#iva_alicuota_id');
                select.empty().append('<option value="">Seleccionar alícuota...</option>');
                
                if (alicuotas && alicuotas.length > 0) {
                    alicuotas.forEach(function(alicuota) {
                        var porcentaje = alicuota.porcentaje ? parseFloat(alicuota.porcentaje).toFixed(2) + '%' : '';
                        var texto = alicuota.iva_alicuota + (porcentaje ? ' (' + porcentaje + ')' : '');
                        
                        select.append(`<option value="${alicuota.iva_alicuota_id}" 
                            data-porcentaje="${alicuota.porcentaje || 0}"
                            data-gravado="${alicuota.es_gravado || 0}"
                            data-exento="${alicuota.es_exento || 0}"
                            data-no-gravado="${alicuota.es_no_gravado || 0}">
                            ${texto}
                        </option>`);
                    });
                }
                
                select.off('change').on('change', function() {
                    var selected = $(this).find('option:selected');
                    var porcentaje = selected.data('porcentaje') || 0;
                    var gravado = selected.data('gravado');
                    var exento = selected.data('exento');
                    var noGravado = selected.data('no-gravado');
                    
                    $('#iva_porcentaje').html(porcentaje > 0 ? '<span class="text-primary">' + parseFloat(porcentaje).toFixed(2) + '%</span>' : '<span class="text-muted">0%</span>');
                    
                    var infoHtml = '';
                    if (exento == 1) {
                        infoHtml = '<span class="badge bg-success">EXENTO</span>';
                    } else if (noGravado == 1) {
                        infoHtml = '<span class="badge bg-warning">NO GRAVADO</span>';
                    } else if (gravado == 1) {
                        infoHtml = '<span class="badge bg-info">GRAVADO</span>';
                    } else {
                        infoHtml = '<span class="text-muted">No especificado</span>';
                    }
                    
                    $('#iva_info').html(infoHtml);
                });
                
                if (select.val()) {
                    select.trigger('change');
                }
            }, 'json');
        }

        // Función para cargar marcas
        function cargarMarcas() {
            $.get('productos_ajax.php', {
                accion: 'obtener_marcas',
                empresa_idx: empresa_idx
            }, function(marcas) {
                var selectFiltro = $('#filtroMarca');
                var selectModal = $('#marca_id');

                selectFiltro.empty().append('<option value="">Todas las marcas</option>');
                selectModal.empty().append('<option value="">Seleccionar marca...</option>');

                if (marcas && marcas.length > 0) {
                    marcas.forEach(function(marca) {
                        selectFiltro.append(`<option value="${marca.marca_id}">${marca.marca_nombre}</option>`);
                        selectModal.append(`<option value="${marca.marca_id}">${marca.marca_nombre}</option>`);
                    });
                }
            }, 'json');
        }

        // Cargar modelos por marca
        function cargarModelos(marcaId, targetId = '#modelo_id') {
            if (!marcaId) {
                $(targetId).empty().append('<option value="">Seleccionar modelo...</option>').prop('disabled', true);
                return;
            }
            
            $.get('productos_ajax.php', {
                accion: 'obtener_modelos',
                empresa_idx: empresa_idx,
                marca_id: marcaId
            }, function(modelos) {
                var select = $(targetId);
                select.empty().append('<option value="">Seleccionar modelo...</option>');

                if (modelos && modelos.length > 0) {
                    select.prop('disabled', false);
                    modelos.forEach(function(modelo) {
                        select.append(`<option value="${modelo.modelo_id}">${modelo.modelo_nombre}</option>`);
                    });
                } else {
                    select.prop('disabled', true);
                }
            }, 'json');
        }

        // Cargar submodelos por modelo
        function cargarSubmodelos(modeloId, targetId = '#submodelo_id') {
            if (!modeloId) {
                $(targetId).empty().append('<option value="">Seleccionar submodelo...</option>').prop('disabled', true);
                return;
            }
            
            $.get('productos_ajax.php', {
                accion: 'obtener_submodelos',
                empresa_idx: empresa_idx,
                modelo_id: modeloId
            }, function(submodelos) {
                var select = $(targetId);
                select.empty().append('<option value="">Seleccionar submodelo...</option>')
                    .append('<option value="0">Sin submodelo</option>');

                if (submodelos && submodelos.length > 0) {
                    select.prop('disabled', false);
                    submodelos.forEach(function(submodelo) {
                        select.append(`<option value="${submodelo.submodelo_id}">${submodelo.submodelo_nombre}</option>`);
                    });
                } else {
                    select.prop('disabled', false);
                }
            }, 'json');
        }

        // Cargar años para selects
        function cargarAnios() {
            var currentYear = new Date().getFullYear();
            var startYear = 1950;

            var selectDesde = $('#anio_desde');
            selectDesde.empty().append('<option value="">Año...</option>');

            var selectHasta = $('#anio_hasta');
            selectHasta.empty().append('<option value="">Año...</option>')
                .append('<option value="0">Actual</option>');

            for (var year = startYear; year <= currentYear + 10; year++) {
                selectDesde.append(`<option value="${year}">${year}</option>`);
                selectHasta.append(`<option value="${year}">${year}</option>`);
            }
        }

        // ========== FUNCIONES DE UBICACIONES ==========

        // Cargar ubicaciones de un producto
       function cargarUbicacionesProducto(productoId) {
            productoActualUbicaciones = productoId;

            if ($.fn.DataTable.isDataTable('#tablaUbicaciones')) {
                $('#tablaUbicaciones').DataTable().destroy();
                $('#tablaUbicaciones tbody').empty();
            }

            tablaUbicaciones = $('#tablaUbicaciones').DataTable({
                ajax: {
                    url: 'productos_ajax.php',
                    type: 'GET',
                    data: {
                        accion: 'obtener_ubicaciones_producto',
                        producto_id: productoId,
                        empresa_idx: empresa_idx
                    },
                    dataSrc: ''
                },
                columns: [
                    { data: 'sucursal_nombre', render: function(data) { return data || '-'; } },
                    { data: 'deposito_nombre', render: function(data) { return data || '-'; } },
                    { data: 'seccion', render: function(data) { return data || '-'; } },
                    { data: 'estanteria', render: function(data) { return data || '-'; } },
                    { data: 'estante', render: function(data) { return data || '-'; } },
                    { data: 'posicion', render: function(data) { return data || '-'; } },
                    { 
                        data: 'producto_ubicacion_id', 
                        orderable: false, 
                        searchable: false, 
                        className: "text-center",
                        render: function(data) {
                            return `<div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-danger btn-eliminar-ubicacion" 
                                        data-id="${data}" title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>`;
                        }
                    }
                ],
                language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' },
                responsive: true,
                pageLength: 10,
                searching: false,
                paging: false,
                info: false
            });
        }

        // Cargar sucursales
        function cargarSucursales() {
            $.get('productos_ajax.php', {
                accion: 'obtener_sucursales',
                empresa_idx: empresa_idx
            }, function(sucursales) {
                var select = $('#sucursal_id');
                select.empty().append('<option value="">Seleccionar sucursal...</option>');
                if (sucursales && sucursales.length > 0) {
                    sucursales.forEach(function(sucursal) {
                        select.append(`<option value="${sucursal.sucursal_id}">${sucursal.sucursal_nombre}</option>`);
                    });
                }
            }, 'json');
        }

        // Cargar ubicaciones de sucursales
        function cargarUbicacionesSucursales() {
            $.get('productos_ajax.php', {
                accion: 'obtener_ubicaciones_sucursales',
                empresa_idx: empresa_idx
            }, function(ubicaciones) {
                var select = $('#sucursal_ubicacion_id');
                select.empty().append('<option value="">Seleccionar ubicación...</option>');

                if (ubicaciones && ubicaciones.length > 0) {
                    var sucursalActual = '';
                    ubicaciones.forEach(function(ubicacion) {
                        if (ubicacion.sucursal_nombre !== sucursalActual) {
                            if (sucursalActual !== '') select.append('</optgroup>');
                            sucursalActual = ubicacion.sucursal_nombre;
                            select.append(`<optgroup label="${sucursalActual}">`);
                        }

                        var descripcionUbicacion = `${ubicacion.seccion} - ${ubicacion.estanteria} - Est. ${ubicacion.estante} Pos. ${ubicacion.posicion}`;
                        if (ubicacion.descripcion) descripcionUbicacion += ` (${ubicacion.descripcion})`;

                        select.append(`<option value="${ubicacion.sucursal_ubicacion_id}">${descripcionUbicacion}</option>`);
                    });
                    if (sucursalActual !== '') select.append('</optgroup>');
                }
            }, 'json');
        }

        // ========== FUNCIONES DE COMPATIBILIDAD ==========

        // Cargar compatibilidad de un producto
        function cargarCompatibilidad(productoId) {
            productoActualCompatibilidad = productoId;

            if ($.fn.DataTable.isDataTable('#tablaCompatibilidad')) {
                $('#tablaCompatibilidad').DataTable().destroy();
                $('#tablaCompatibilidad tbody').empty();
            }

            tablaCompatibilidad = $('#tablaCompatibilidad').DataTable({
                ajax: {
                    url: 'productos_ajax.php',
                    type: 'GET',
                    data: {
                        accion: 'obtener_compatibilidad',
                        producto_id: productoId,
                        empresa_idx: empresa_idx
                    },
                    dataSrc: ''
                },
                columns: [
                    { data: 'marca_nombre', render: function(data) { return data || '-'; } },
                    { data: 'modelo_nombre', render: function(data) { return data || '-'; } },
                    { data: 'submodelo_nombre', render: function(data) { return data || '-'; } },
                    { 
                        data: null, 
                        className: 'text-center',
                        render: function(data) {
                            var anioDesde = data.anio_desde || '';
                            var anioHasta = data.anio_hasta == '0' ? 'Actual' : (data.anio_hasta || '');
                            if (anioHasta && anioHasta !== 'Actual') return `${anioDesde} - ${anioHasta}`;
                            return anioDesde || '-';
                        }
                    },
                    { 
                        data: 'compatibilidad_id', 
                        orderable: false, 
                        searchable: false, 
                        className: "text-center",
                        render: function(data) {
                            return `<div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-primary btn-editar-compatibilidad" 
                                        data-id="${data}" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-eliminar-compatibilidad" 
                                        data-id="${data}" title="Eliminar">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>`;
                        }
                    }
                ],
                language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' },
                responsive: true,
                pageLength: 10,
                searching: false,
                paging: false,
                info: false
            });
        }

        // Mostrar modal de compatibilidad
        function mostrarModalCompatibilidad(productoId, compatibilidadId = null) {
            resetModalCompatibilidad();
            $('#compatibilidad_producto_id').val(productoId);
            cargarMarcas();
            cargarAnios();

            if (compatibilidadId) {
                $('#modalCompatibilidadLabel').html('<i class="fas fa-edit me-2"></i>Editar Compatibilidad');
                $('#compatibilidad_id').val(compatibilidadId);

                setTimeout(function() {
                    $.get('productos_ajax.php', {
                        accion: 'obtener_compatibilidad_por_id',
                        compatibilidad_id: compatibilidadId,
                        empresa_idx: empresa_idx
                    }, function(res) {
                        if (res && res.compatibilidad_id) {
                            $('#marca_id').val(res.marca_id);
                            cargarModelos(res.marca_id);
                            
                            setTimeout(function() {
                                $('#modelo_id').val(res.modelo_id);
                                cargarSubmodelos(res.modelo_id);
                                
                                setTimeout(function() {
                                    $('#submodelo_id').val(res.submodelo_id || '');
                                    $('#anio_desde').val(res.anio_desde);
                                    $('#anio_hasta').val(res.anio_hasta || '');
                                }, 300);
                            }, 300);
                        }
                    }, 'json');
                }, 500);
            } else {
                $('#modalCompatibilidadLabel').html('<i class="fas fa-plus me-2"></i>Agregar Compatibilidad');
            }

            new bootstrap.Modal(document.getElementById('modalCompatibilidad')).show();
        }

        function resetModalCompatibilidad() {
            $('#formCompatibilidad')[0].reset();
            $('#compatibilidad_id').val('');
            $('#formCompatibilidad').removeClass('was-validated');
            $('#marca_id').empty().append('<option value="">Seleccionar marca...</option>');
            $('#modelo_id').empty().append('<option value="">Seleccionar modelo...</option>').prop('disabled', true);
            $('#submodelo_id').empty().append('<option value="">Seleccionar submodelo...</option>').prop('disabled', true);
        }

        // ========== FUNCIONES DE IMÁGENES ==========

        // Cargar imágenes de un producto
       function cargarImagenesProducto(productoId) {
            productoActualImagenes = productoId;

            $.get('productos_ajax.php', {
                accion: 'obtener_imagenes_producto',
                producto_id: productoId,
                empresa_idx: empresa_idx
            }, function(imagenes) {
                console.log("Imágenes cargadas para producto", productoId, ":", imagenes);
                var galeria = $('#galeriaImagenes');
                var sinImagenes = $('#sinImagenes');
                galeria.empty();

                if (imagenes && imagenes.length > 0) {
                    sinImagenes.hide();

                    imagenes.forEach(function(imagen, index) {
                        var srcImagen = imagen.imagen_url; // Ahora viene del servidor
                        console.log("URL de imagen:", srcImagen);
                        
                        var esPrincipal = imagen.es_principal == 1;
                        var clasePrincipal = esPrincipal ? 'card-imagen-principal' : '';

                        var cardHtml = `
                            <div class="col-md-3 mb-3" data-id="${imagen.producto_imagen_id}" data-orden="${imagen.orden || 0}">
                                <div class="card card-imagen ${clasePrincipal} h-100">
                                    <div class="position-relative">
                                        <img src="${srcImagen}" class="card-img-top imagen-miniatura" 
                                            alt="${imagen.descripcion || 'Imagen del producto'}"
                                            onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'150\' height=\'150\'><rect width=\'150\' height=\'150\' fill=\'#f8f9fa\'/><text x=\'75\' y=\'75\' text-anchor=\'middle\' fill=\'#6c757d\' font-family=\'Arial\' font-size=\'12\'>Error</text></svg>';"
                                            onclick="mostrarImagenGrande('${srcImagen}', '${imagen.descripcion || ''}', ${productoId})">
                                        ${esPrincipal ? '<span class="badge bg-success badge-imagen-principal">Principal</span>' : ''}
                                    </div>
                                    <div class="card-body p-2">
                                        <h6 class="card-title mb-1 text-truncate" title="${imagen.descripcion || 'Sin descripción'}">
                                            ${imagen.descripcion || 'Sin descripción'}
                                        </h6>
                                        <p class="card-text small text-muted mb-1">
                                            <i class="fas fa-sort-numeric-up me-1"></i>Orden: ${imagen.orden || 0}
                                        </p>
                                        <p class="card-text small text-muted mb-2">
                                            <i class="fas fa-weight-hanging me-1"></i>${formatBytes(imagen.imagen_tamanio)}
                                        </p>
                                        <p class="card-text small text-muted mb-2">
                                            <i class="fas fa-folder me-1"></i>${imagen.imagen_nombre}
                                        </p>
                                        <div class="btn-group btn-group-sm w-100" role="group">
                                            <button type="button" class="btn btn-outline-primary btn-editar-imagen" 
                                                    data-id="${imagen.producto_imagen_id}" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-success btn-imagen-principal" 
                                                    data-id="${imagen.producto_imagen_id}" title="${esPrincipal ? 'Ya es principal' : 'Marcar como principal'}" 
                                                    ${esPrincipal ? 'disabled' : ''}>
                                                <i class="fas fa-star"></i>
                                            </button>
                                            <button type="button" class="btn btn-outline-danger btn-eliminar-imagen" 
                                                    data-id="${imagen.producto_imagen_id}" title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                        galeria.append(cardHtml);
                    });
                    inicializarSortable();
                } else {
                    sinImagenes.show();
                }
            }, 'json').fail(function(xhr, status, error) {
                console.error("Error al cargar imágenes:", error);
                console.error("Respuesta:", xhr.responseText);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudieron cargar las imágenes'
                });
            });
        }


        // Función para formatear bytes
        function formatBytes(bytes, decimals = 2) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const dm = decimals < 0 ? 0 : decimals;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
        }

        // Inicializar ordenamiento por arrastre
        function inicializarSortable() {
            if (typeof Sortable !== 'undefined') {
                new Sortable(document.getElementById('galeriaImagenes'), {
                    animation: 150,
                    ghostClass: 'sortable-ghost',
                    chosenClass: 'sortable-chosen',
                    onEnd: actualizarOrdenImagenes
                });
            }
        }

        // Actualizar orden de imágenes
        function actualizarOrdenImagenes() {
            $('#galeriaImagenes .col-md-3').each(function(index) {
                $.post('productos_ajax.php', {
                    accion: 'actualizar_imagen_producto',
                    producto_imagen_id: $(this).data('id'),
                    orden: index,
                    empresa_idx: empresa_idx
                }, function(res) {
                    if (!res.resultado) console.error('Error al actualizar orden:', res.error);
                }, 'json');
            });
        }

        // Mostrar modal para subir/editar imagen
        function mostrarModalImagen(productoId, productoImagenId = null) {
            resetModalImagen();
            $('#imagen_producto_id').val(productoId);

            if (productoImagenId) {
                $('#modalImagenLabel').html('<i class="fas fa-edit me-2"></i>Editar Imagen');
                $('#producto_imagen_id').val(productoImagenId);

                $.get('productos_ajax.php', {
                    accion: 'obtener_imagen_por_id',
                    producto_imagen_id: productoImagenId,
                    empresa_idx: empresa_idx
                }, function(res) {
                    if (res && res.producto_imagen_id) {
                        $('#descripcion_imagen').val(res.descripcion || '');
                        $('#es_principal_imagen').prop('checked', res.es_principal == 1);
                        $('#orden_imagen').val(res.orden || 0);
                        $('#vistaPreviaContainer').show();
                        $('#vistaPreviaImagen').attr('src', 'get_imagen.php?id=' + res.imagen_id);
                        $('#imagen_archivo').removeAttr('required');
                    }
                }, 'json');
            } else {
                $('#modalImagenLabel').html('<i class="fas fa-plus me-2"></i>Agregar Imagen');
                $('#imagen_archivo').attr('required', 'required');
            }

            new bootstrap.Modal(document.getElementById('modalImagen')).show();
        }

        function resetModalImagen() {
            $('#formImagen')[0].reset();
            $('#producto_imagen_id').val('');
            $('#formImagen').removeClass('was-validated');
            $('#vistaPreviaContainer').hide();
            $('#vistaPreviaImagen').attr('src', '');
        }

        // ========== FUNCIONES DE TABLA PRINCIPAL ==========

        function inicializarDataTable() {
            if ($.fn.DataTable.isDataTable('#tablaProductos')) {
                $('#tablaProductos').DataTable().destroy();
                $('#tablaProductos tbody').empty();
            }

            tabla = $('#tablaProductos').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: 'productos_ajax.php',
                    type: 'GET',
                    data: function(d) {
                        d.accion = 'listar';
                        d.empresa_idx = empresa_idx;
                        d.pagina_idx = pagina_idx;
                        d.filtro_codigo = $('#filtroCodigo').val();
                        d.filtro_marca = $('#filtroMarca').val();
                        d.filtro_modelo = $('#filtroModelo').val();
                        d.filtro_submodelo = $('#filtroSubmodelo').val();
                    }
                },
                stateSave: true,
                stateSaveParams: function(settings, data) {
                    data.page = currentPage;
                    data.order = currentOrder;
                    data.search = { search: currentSearch !== '-1' ? currentSearch : '' };
                    delete data.columns;
                    return data;
                },
                stateLoadParams: function(settings, data) {
                    if (data.page !== undefined) currentPage = data.page;
                    if (data.order !== undefined) currentOrder = data.order;
                    currentSearch = (data.search && data.search.search) ? data.search.search : '';
                    data.search = { search: currentSearch };
                },
                dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                     '<"row"<"col-sm-12"tr>>' +
                     '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>' +
                     '<"clear">',
                pageLength: 50,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                columns: [
                    { data: 'producto_id', className: 'text-center fw-bold', width: '80px' },
                    { data: 'producto_codigo', className: 'text-center fw-medium', width: '100px' },
                    { 
                        data: 'producto_nombre', 
                        width: '200px',
                        render: function(data, type, row) {
                            if (type === 'export') return data;
                            var desc = row.producto_descripcion ? 
                                `<small class="text-muted d-block">${row.producto_descripcion.substring(0, 40)}${row.producto_descripcion.length > 40 ? '...' : ''}</small>` : '';
                            return `<div class="fw-medium">${data}</div>${desc}`;
                        }
                    },
                    { 
                        data: 'marcas_compatibles', 
                        width: '150px',
                        render: function(data) {
                            return data ? `<span class="badge badge-compatibilidad bg-info text-white" title="${data}">${data}</span>` : '<span class="text-muted">-</span>';
                        }
                    },
                    { 
                        data: 'modelos_compatibles', 
                        width: '150px',
                        render: function(data) {
                            return data ? `<span class="badge badge-compatibilidad bg-success text-white" title="${data}">${data}</span>` : '<span class="text-muted">-</span>';
                        }
                    },
                    { 
                        data: 'submodelos_compatibles', 
                        width: '150px',
                        render: function(data) {
                            return data ? `<span class="badge badge-compatibilidad bg-warning text-dark" title="${data}">${data}</span>` : '<span class="text-muted">-</span>';
                        }
                    },
                    { 
                        data: 'ubicaciones_info', 
                        width: '200px',
                        render: function(data) {
                            if (!data) return '<span class="text-muted">-</span>';
                            var ubicaciones = data.split('; ');
                            var html = ubicaciones.slice(0, 3).map(u => 
                                `<span class="badge bg-secondary mb-1 d-block text-start" style="font-size: 0.7rem;">${u}</span>`
                            ).join('');
                            if (ubicaciones.length > 3) {
                                html += `<span class="badge bg-light text-dark d-block" style="font-size: 0.7rem;">+${ubicaciones.length - 3} más</span>`;
                            }
                            return html;
                        }
                    },
                    { 
                        data: 'imagen_id_principal', 
                        width: '80px', 
                        className: 'text-center', 
                        orderable: false, 
                        searchable: false,
                        render: function(data, type, row) {
                            if (type === 'export') return data ? 'Sí' : 'No';
                            if (data) {
                                var rutaImagen = 'get_imagen.php?id=' + data;
                                // Escapar correctamente el título para el onclick
                                var titulo = (row.producto_nombre || '').replace(/'/g, "\\'");
                                return `<img src="${rutaImagen}" alt="Prod" 
                                    class="img-thumbnail rounded-circle" 
                                    style="width: 50px; height: 50px; object-fit: cover; cursor: pointer;" 
                                    onclick="mostrarImagenGrande('${rutaImagen}', '${titulo}', ${row.producto_id})"
                                    onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'50\' height=\'50\'><rect width=\'50\' height=\'50\' fill=\'#f8f9fa\'/><text x=\'25\' y=\'25\' text-anchor=\'middle\' fill=\'#6c757d\' font-family=\'Arial\' font-size=\'10\'>Error</text></svg>';">`;
                            }
                            return '<div class="text-center text-muted"><i class="fas fa-image fa-lg"></i></div>';
                        }
                    },
                    { 
                        data: 'estado_info', 
                        className: 'text-center', 
                        width: '100px',
                        render: function(data, type) {
                            if (!data || !data.estado_registro) return type === 'export' ? 'Sin estado' : '<span class="badge badge-estado-inactivo">Sin estado</span>';
                            if (type === 'export') return data.estado_registro;
                            var clase = 'badge-estado-inactivo';
                            if (data.codigo_estandar === 'ACTIVO') clase = 'badge-estado-activo';
                            return `<span class="badge ${clase}">${data.estado_registro}</span>`;
                        }
                    },
                    { 
                        data: 'botones', 
                        orderable: false, 
                        searchable: false, 
                        className: "text-center", 
                        width: '120px',
                        render: function(data, type, row) {
                            if (type === 'export' || !data) return '';
                            return data.map(boton => {
                                var clase = 'btn-xs me-1 ';
                                var nombre = boton.accion_js || boton.nombre_funcion.toLowerCase();
                                clase += nombre === 'editar' ? 'btn-outline-primary' :
                                        (nombre === 'eliminar' || nombre === 'baja') ? 'btn-outline-danger' :
                                        (nombre === 'alta' || nombre === 'activar') ? 'btn-outline-success' :
                                        (nombre === 'suspender' || nombre === 'bloquear') ? 'btn-outline-warning' : 'btn-outline-secondary';
                                return `<button type="button" class="btn ${clase} btn-accion" 
                                    title="${boton.descripcion || boton.nombre_funcion}" 
                                    data-id="${row.producto_id}" 
                                    data-accion="${boton.accion_js}"
                                    data-confirmable="${boton.es_confirmable || 0}"
                                    data-producto="${row.producto_nombre}">
                                    <i class="${boton.icono_clase || 'fas fa-cog'}"></i>
                                </button>`;
                            }).join('') || '<span class="text-muted small">-</span>';
                        }
                    }
                ],
                language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' },
                order: currentOrder,
                responsive: true,
                createdRow: function(row, data) {
                    if (data.estado_info && data.estado_info.codigo_estandar === 'INACTIVO') $(row).addClass('table-secondary');
                    else if (data.estado_info && data.estado_info.codigo_estandar === 'BLOQUEADO') $(row).addClass('table-warning');
                },
                initComplete: inicializarEventos
            });
        }

        // ========== FUNCIONES DE EVENTOS ==========

        function inicializarEventos() {
            $('#btnRecargar').off('click').on('click', function() {
                var btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                tabla.ajax.reload(() => btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>'));
            });

            $('#btnAplicarFiltros').click(() => tabla.ajax.reload());
            
            $('#btnLimpiarFiltros').click(function() {
                $('#filtroCodigo, #filtroMarca').val('');
                $('#filtroModelo, #filtroSubmodelo').val('').prop('disabled', true);
                tabla.ajax.reload();
            });

            $('#filtroMarca').change(function() {
                var marcaId = $(this).val();
                if (marcaId) cargarModelos(marcaId, '#filtroModelo');
                else $('#filtroModelo, #filtroSubmodelo').val('').prop('disabled', true);
            });

            $('#filtroModelo').change(function() {
                var modeloId = $(this).val();
                if (modeloId) cargarSubmodelos(modeloId, '#filtroSubmodelo');
                else $('#filtroSubmodelo').val('').prop('disabled', true);
            });

            $('#btnAgregarCompatibilidad').click(() => productoActualCompatibilidad && mostrarModalCompatibilidad(productoActualCompatibilidad));

            $(document).on('click', '.btn-editar-compatibilidad', function() {
                productoActualCompatibilidad && mostrarModalCompatibilidad(productoActualCompatibilidad, $(this).data('id'));
            });

            $(document).on('click', '.btn-eliminar-compatibilidad', function() {
                var compatibilidadId = $(this).data('id');
                Swal.fire({
                    title: '¿Eliminar Compatibilidad?',
                    text: 'Esta acción no se puede deshacer',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, eliminar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.post('productos_ajax.php', {
                            accion: 'eliminar_compatibilidad',
                            compatibilidad_id: compatibilidadId,
                            empresa_idx: empresa_idx
                        }, function(res) {
                            if (res.success) {
                                Swal.fire('¡Eliminado!', 'Compatibilidad eliminada correctamente', 'success');
                                tablaCompatibilidad.ajax.reload();
                            } else Swal.fire('Error', res.error || 'Error al eliminar', 'error');
                        }, 'json');
                    }
                });
            });

            $(document).on('change', '#marca_id', function() {
                var marcaId = $(this).val();
                if (marcaId) cargarModelos(marcaId);
                else $('#modelo_id, #submodelo_id').val('').prop('disabled', true);
            });

            $(document).on('change', '#modelo_id', function() {
                var modeloId = $(this).val();
                if (modeloId) cargarSubmodelos(modeloId);
                else $('#submodelo_id').val('').prop('disabled', true);
            });

            $('#btnGuardarCompatibilidad').click(function() {
                var form = document.getElementById('formCompatibilidad');
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');
                var datos = {
                    accion: $('#compatibilidad_id').val() ? 'editar_compatibilidad' : 'agregar_compatibilidad',
                    compatibilidad_id: $('#compatibilidad_id').val() || '',
                    producto_id: $('#compatibilidad_producto_id').val(),
                    marca_id: $('#marca_id').val(),
                    modelo_id: $('#modelo_id').val(),
                    submodelo_id: $('#submodelo_id').val() || null,
                    anio_desde: $('#anio_desde').val(),
                    anio_hasta: $('#anio_hasta').val() || null,
                    empresa_idx: empresa_idx
                };

                $.post('productos_ajax.php', datos, function(res) {
                    btn.prop('disabled', false).html('Guardar');
                    if (res.success || res.resultado) {
                        Swal.fire('¡Guardado!', 'Compatibilidad guardada correctamente', 'success');
                        bootstrap.Modal.getInstance(document.getElementById('modalCompatibilidad')).hide();
                        tablaCompatibilidad.ajax.reload();
                    } else Swal.fire('Error', res.error || 'Error al guardar', 'error');
                }, 'json');
            });

            $('#btnAgregarImagen').click(() => productoActualImagenes && mostrarModalImagen(productoActualImagenes));

            $(document).on('click', '.btn-editar-imagen', function() {
                productoActualImagenes && mostrarModalImagen(productoActualImagenes, $(this).data('id'));
            });

            $(document).on('click', '.btn-imagen-principal', function() {
                var productoImagenId = $(this).data('id');
                Swal.fire({
                    title: '¿Marcar como principal?',
                    text: 'Esta imagen será mostrada como la principal del producto',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, marcar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.post('productos_ajax.php', {
                            accion: 'actualizar_imagen_producto',
                            producto_imagen_id: productoImagenId,
                            es_principal: 1,
                            empresa_idx: empresa_idx
                        }, function(res) {
                            if (res.resultado) {
                                Swal.fire('¡Actualizado!', 'Imagen marcada como principal', 'success');
                                cargarImagenesProducto(productoActualImagenes);
                            } else Swal.fire('Error', res.error || 'Error al actualizar', 'error');
                        }, 'json');
                    }
                });
            });

            $(document).on('click', '.btn-eliminar-imagen', function() {
                var productoImagenId = $(this).data('id');
                Swal.fire({
                    title: '¿Eliminar Imagen?',
                    text: 'Esta acción no se puede deshacer',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, eliminar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.post('productos_ajax.php', {
                            accion: 'eliminar_imagen_producto',
                            producto_imagen_id: productoImagenId,
                            empresa_idx: empresa_idx
                        }, function(res) {
                            if (res.success) {
                                Swal.fire('¡Eliminado!', 'Imagen eliminada correctamente', 'success');
                                cargarImagenesProducto(productoActualImagenes);
                            } else Swal.fire('Error', res.error || 'Error al eliminar', 'error');
                        }, 'json');
                    }
                });
            });

            $('#imagen_archivo').change(function() {
                if (this.files[0]) {
                    var reader = new FileReader();
                    reader.onload = (e) => $('#vistaPreviaContainer').show().find('#vistaPreviaImagen').attr('src', e.target.result);
                    reader.readAsDataURL(this.files[0]);
                } else $('#vistaPreviaContainer').hide();
            });

            $('#btnGuardarImagen').click(function() {
                var form = document.getElementById('formImagen');
                var productoImagenId = $('#producto_imagen_id').val();

                if (!productoImagenId && !$('#imagen_archivo')[0].files[0]) {
                    form.classList.add('was-validated');
                    return false;
                }

                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');
                var formData = new FormData(form);
                formData.append('accion', productoImagenId ? 'actualizar_imagen_producto' : 'subir_imagen_producto');
                formData.append('producto_id', $('#imagen_producto_id').val());
                if (productoImagenId) formData.append('producto_imagen_id', productoImagenId);
                formData.append('empresa_idx', empresa_idx);

                $.ajax({
                    url: 'productos_ajax.php',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(res) {
                        btn.prop('disabled', false).html('Guardar');
                        if (res.resultado) {
                            Swal.fire('¡Guardado!', 'Imagen guardada correctamente', 'success');
                            bootstrap.Modal.getInstance(document.getElementById('modalImagen')).hide();
                            cargarImagenesProducto(productoActualImagenes);
                        } else Swal.fire('Error', res.error || 'Error al guardar', 'error');
                    },
                    error: function() {
                        btn.prop('disabled', false).html('Guardar');
                        Swal.fire('Error', 'Error de conexión al servidor', 'error');
                    }
                });
            });

            // Eventos de ubicaciones
            $('#btnAgregarUbicacion').click(() => productoActualUbicaciones && mostrarModalUbicacion(productoActualUbicaciones));
            $('#btnNuevaUbicacion').click(() => productoActualUbicaciones && mostrarModalNuevaUbicacion(productoActualUbicaciones));

            $(document).on('click', '.btn-eliminar-ubicacion', function() {
                var productoUbicacionId = $(this).data('id');
                Swal.fire({
                    title: '¿Eliminar Ubicación?',
                    text: 'Esta acción no se puede deshacer',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, eliminar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.post('productos_ajax.php', {
                            accion: 'eliminar_ubicacion_producto',
                            producto_ubicacion_id: productoUbicacionId,
                            empresa_idx: empresa_idx
                        }, function(res) {
                            if (res.success) {
                                Swal.fire('¡Eliminado!', 'Ubicación eliminada correctamente', 'success');
                                tablaUbicaciones.ajax.reload();
                            } else Swal.fire('Error', res.error || 'Error al eliminar', 'error');
                        }, 'json');
                    }
                });
            });

            $('#btnGuardarUbicacion').click(function() {
                var form = document.getElementById('formUbicacion');
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');
                $.post('productos_ajax.php', {
                    accion: 'agregar_ubicacion_producto',
                    producto_id: $('#ubicacion_producto_id').val(),
                    sucursal_ubicacion_id: $('#sucursal_ubicacion_id').val(),
                    empresa_idx: empresa_idx
                }, function(res) {
                    btn.prop('disabled', false).html('Guardar');
                    if (res.resultado) {
                        Swal.fire('¡Guardado!', 'Ubicación asignada correctamente', 'success');
                        bootstrap.Modal.getInstance(document.getElementById('modalUbicacion')).hide();
                        tablaUbicaciones.ajax.reload();
                    } else Swal.fire('Error', res.error || 'Error al guardar', 'error');
                }, 'json');
            });

            $('#btnGuardarNuevaUbicacion').click(function() {
            var form = document.getElementById('formNuevaUbicacion');
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return false;
            }

            var btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Creando...');
            $.post('productos_ajax.php', {
                accion: 'crear_ubicacion_sucursal',
                empresa_id: empresa_idx,
                sucursal_id: $('#sucursal_id').val(),
                deposito_id: $('#deposito_id_nueva').val(),  // AGREGADO
                seccion: $('#seccion').val(),
                estanteria: $('#estanteria').val(),
                estante: $('#estante').val(),
                posicion: $('#posicion').val(),
                descripcion: $('#descripcion_ubicacion').val()
            }, function(res) {
                btn.prop('disabled', false).html('Crear');
                if (res.resultado) {
                    Swal.fire({
                        title: '¡Creada!',
                        html: 'Ubicación creada correctamente. ¿Desea asignarla a este producto?',
                        icon: 'success',
                        showCancelButton: true,
                        confirmButtonColor: '#28a745',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: 'Sí, asignar',
                        cancelButtonText: 'No, solo crear'
                    }).then((result) => {
                        bootstrap.Modal.getInstance(document.getElementById('modalNuevaUbicacion')).hide();
                        if (result.isConfirmed) {
                            $.post('productos_ajax.php', {
                                accion: 'agregar_ubicacion_producto',
                                producto_id: $('#nueva_ubicacion_producto_id').val(),
                                sucursal_ubicacion_id: res.sucursal_ubicacion_id,
                                empresa_idx: empresa_idx
                            }, function(res2) {
                                if (res2.resultado) {
                                    Swal.fire('¡Asignada!', 'Ubicación creada y asignada correctamente', 'success');
                                    tablaUbicaciones.ajax.reload();
                                } else {
                                    Swal.fire('¡Creada!', 'Ubicación creada correctamente', 'success');
                                }
                            }, 'json');
                        }
                    });
                } else {
                    Swal.fire('Error', res.error || 'Error al crear la ubicación', 'error');
                }
            }, 'json');
        });
            // Eventos de proveedores
            $('#btnAgregarProveedor').click(() => productoActualProveedores && mostrarModalProveedor(productoActualProveedores));

            $(document).on('click', '.btn-editar-proveedor', function() {
                productoActualProveedores && mostrarModalProveedor(productoActualProveedores, $(this).data('id'));
            });

            $(document).on('click', '.btn-eliminar-proveedor', function() {
                var productoProveedorId = $(this).data('id');
                Swal.fire({
                    title: '¿Eliminar Proveedor?',
                    text: 'Esta acción no se puede deshacer',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, eliminar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.post('productos_ajax.php', {
                            accion: 'eliminar_proveedor_producto',
                            producto_proveedor_id: productoProveedorId,
                            empresa_idx: empresa_idx
                        }, function(res) {
                            if (res.success) {
                                Swal.fire('¡Eliminado!', 'Proveedor eliminado correctamente', 'success');
                                tablaProveedores.ajax.reload();
                            } else {
                                Swal.fire('Error', res.error || 'Error al eliminar', 'error');
                            }
                        }, 'json');
                    }
                });
            });

            $('#btnGuardarProveedor').click(function() {
                var form = document.getElementById('formProveedor');
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var productoProveedorId = $('#producto_proveedor_id').val();
                var accionBackend = productoProveedorId ? 'editar_proveedor_producto' : 'agregar_proveedor_producto';

                var btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

                $.post('productos_ajax.php', {
                    accion: accionBackend,
                    producto_proveedor_id: productoProveedorId,
                    producto_id: $('#proveedor_producto_id').val(),
                    entidad_id: $('#entidad_id').val(),
                    codigo_proveedor: $('#codigo_proveedor').val(),
                    empresa_idx: empresa_idx
                }, function(res) {
                    btn.prop('disabled', false).html('Guardar');
                    if (res.resultado || res.success) {
                        Swal.fire('¡Guardado!', 'Proveedor guardado correctamente', 'success');
                        bootstrap.Modal.getInstance(document.getElementById('modalProveedor')).hide();
                        tablaProveedores.ajax.reload();
                    } else {
                        Swal.fire('Error', res.error || 'Error al guardar', 'error');
                    }
                }, 'json');
            });
        }

        // Mostrar modal para agregar ubicación existente
        function mostrarModalUbicacion(productoId) {
            resetModalUbicacion();
            $('#ubicacion_producto_id').val(productoId);
            cargarUbicacionesSucursales();
            new bootstrap.Modal(document.getElementById('modalUbicacion')).show();
        }

        // Mostrar modal para crear nueva ubicación
        function mostrarModalNuevaUbicacion(productoId) {
            resetModalNuevaUbicacion();
            $('#nueva_ubicacion_producto_id').val(productoId);
            cargarSucursales();
            new bootstrap.Modal(document.getElementById('modalNuevaUbicacion')).show();
        }

        function resetModalUbicacion() {
            $('#formUbicacion')[0].reset();
            $('#formUbicacion').removeClass('was-validated');
            $('#sucursal_ubicacion_id').empty().append('<option value="">Seleccionar ubicación...</option>');
        }

        function resetModalNuevaUbicacion() {
            $('#formNuevaUbicacion')[0].reset();
            $('#formNuevaUbicacion').removeClass('was-validated');
            $('#sucursal_id').empty().append('<option value="">Seleccionar sucursal...</option>');
            $('#deposito_id_nueva').empty().append('<option value="">Primero seleccione una sucursal...</option>');
        }

        // ========== FUNCIONES DE ACCIONES ==========

        function cargarBotonAgregar() {
            $.get('productos_ajax.php', {
                accion: 'obtener_boton_agregar',
                pagina_idx: pagina_idx
            }, function(botonAgregar) {
                if (botonAgregar && botonAgregar.nombre_funcion) {
                    var icono = botonAgregar.icono_clase ? `<i class="${botonAgregar.icono_clase} me-1"></i>` : '';
                    var colorClase = botonAgregar.bg_clase && botonAgregar.text_clase ? botonAgregar.bg_clase + ' ' + botonAgregar.text_clase : (botonAgregar.color_clase || 'btn-primary');
                    $('#contenedor-boton-agregar').html(`<button type="button" class="btn ${colorClase}" id="btnNuevo">${icono}${botonAgregar.nombre_funcion}</button>`);
                } else {
                    $('#contenedor-boton-agregar').html('<button type="button" class="btn btn-primary" id="btnNuevo"><i class="fas fa-plus me-1"></i>Agregar Producto</button>');
                }
            }, 'json');
        }

        $(document).on('click', '#btnNuevo', function() {
            resetModal();
            $('#modalLabel').text('Nuevo Producto');
            cargarTiposProducto();
            cargarCategoriasProducto();
            cargarUnidadesMedida();
            cargarIvaAlicuotas();
            cargarCuentasContables();  // <--- AGREGAR ESTA LÍNEA
            new bootstrap.Modal(document.getElementById('modalProducto')).show();
            $('#producto_codigo').focus();
        });

        $(document).on('click', '.btn-accion', function() {
            var productoId = $(this).data('id');
            var accionJs = $(this).data('accion');
            var confirmable = $(this).data('confirmable');
            var producto = $(this).data('producto');

            if (accionJs === 'editar') cargarProductoParaEditar(productoId);
            else ejecutarAccion(productoId, accionJs, producto, confirmable);
        });

        function mostrarModalAlta(productoId, producto) {
            productoActualId = productoId;
            $('#alta_producto_id').val(productoId);
            $('#mensajeAlta').html(`¿Está seguro de dar de ALTA el producto <strong>"${producto}"</strong>?`);
            $('#formAltaProducto')[0].reset();
            $('#formAltaProducto').removeClass('was-validated');
            $('#fecha_alta').val(new Date().toISOString().split('T')[0]);
            new bootstrap.Modal(document.getElementById('modalAltaProducto')).show();
            $('#motivo_alta').focus();
        }

        function ejecutarAccion(productoId, accionJs, producto, esConfirmable) {
            if (esConfirmable == 1 && (accionJs === 'alta' || accionJs === 'activar')) {
                mostrarModalAlta(productoId, producto);
                return;
            }

            if (esConfirmable == 1) {
                Swal.fire({
                    title: `¿${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}?`,
                    html: `¿Está seguro de <strong>${accionJs}</strong> el producto <strong>"${producto}"</strong>?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: `Sí, ${accionJs}`,
                    cancelButtonText: 'Cancelar'
                }).then((result) => result.isConfirmed && enviarAccionBackend(productoId, accionJs, producto));
            } else {
                enviarAccionBackend(productoId, accionJs, producto);
            }
        }

        function enviarAccionBackend(productoId, accionJs, producto) {
            $.post('productos_ajax.php', {
                accion: 'ejecutar_accion',
                producto_id: productoId,
                accion_js: accionJs,
                empresa_idx: empresa_idx,
                pagina_idx: pagina_idx
            }, function(res) {
                if (res.success) {
                    tabla.ajax.reload(() => {
                        Swal.fire({
                            icon: "success",
                            title: `¡${accionJs.charAt(0).toUpperCase() + accionJs.slice(1)}!`,
                            text: res.message || `Producto "${producto}" actualizado correctamente`,
                            showConfirmButton: false,
                            timer: 1500,
                            toast: true,
                            position: 'top-end'
                        });
                        if (accionJs === 'alta' || accionJs === 'activar') {
                            var modalAlta = bootstrap.Modal.getInstance(document.getElementById('modalAltaProducto'));
                            if (modalAlta) modalAlta.hide();
                        }
                    });
                } else {
                    Swal.fire({ icon: "error", title: "Error", text: res.error || `Error al ${accionJs} el producto` });
                }
            }, 'json');
        }

        function cargarProductoParaEditar(productoId) {
            $.get('productos_ajax.php', {
                accion: 'obtener',
                producto_id: productoId,
                empresa_idx: empresa_idx
            }, function(res) {
                if (res && res.producto_id) {
                    resetModal();

                    $('#producto_id').val(res.producto_id);
                    $('#producto_codigo').val(res.producto_codigo);
                    $('#producto_nombre').val(res.producto_nombre);
                    $('#codigo_barras').val(res.codigo_barras);
                    $('#producto_descripcion').val(res.producto_descripcion || '');
                    $('#lado').val(res.lado || '');
                    $('#material').val(res.material || '');
                    $('#color').val(res.color || '');
                    $('#peso').val(res.peso || '');
                    $('#dimensiones').val(res.dimensiones || '');
                    $('#garantia').val(res.garantia || '');
                    $('#controla_stock').prop('checked', res.controla_stock == 1);  // <--- AGREGAR

                    cargarTiposProducto();
                    cargarCategoriasProducto();
                    cargarUnidadesMedida();
                    cargarIvaAlicuotas();
                    cargarCuentasContables();  // <--- AGREGAR ESTA LÍNEA

                    setTimeout(function() {
                        $('#producto_tipo_id').val(res.producto_tipo_id);
                        $('#producto_categoria_id').val(res.producto_categoria_id);
                        $('#unidad_medida_id').val(res.unidad_medida_id || '');
                        $('#cont_cuenta_id').val(res.cont_cuenta_id || '');
                        $('#iva_alicuota_id').val(res.iva_alicuota_id || '');
                        if (res.iva_alicuota_id) $('#iva_alicuota_id').trigger('change');
                    }, 500);

                    $('#modalLabel').text('Editar Producto');

                    cargarCompatibilidad(productoId);
                    cargarImagenesProducto(productoId);
                    cargarUbicacionesProducto(productoId);
                    cargarProveedoresProducto(productoId); // AGREGAR ESTA LÍNEA

                    new bootstrap.Modal(document.getElementById('modalProducto')).show();
                } else {
                    Swal.fire({ icon: "error", title: "Error", text: "Error al obtener datos del producto" });
                }
            }, 'json');
        }

        function resetModal() {
            $('#formProducto')[0].reset();
            $('#producto_id').val('');
            $('#formProducto').removeClass('was-validated');
            $('#iva_alicuota_id').empty().append('<option value="">Seleccionar alícuota...</option>');
            $('#iva_info').html('<span class="text-muted">Seleccione una alícuota...</span>');
            $('#cont_cuenta_id').empty().append('<option value="">Seleccionar cuenta...</option>');
            $('#iva_porcentaje').html('<span class="text-muted">0%</span>');
            $('#producto_tipo_id').empty().append('<option value="">Seleccionar tipo...</option>');
            $('#producto_categoria_id').empty().append('<option value="">Seleccionar categoría...</option>');
            $('#unidad_medida_id').empty().append('<option value="">Seleccionar unidad...</option>');
            $('#controla_stock').prop('checked', true);  // <--- AGREGAR

            if ($.fn.DataTable.isDataTable('#tablaCompatibilidad')) {
                $('#tablaCompatibilidad').DataTable().destroy();
                $('#tablaCompatibilidad tbody').empty();
            }

            $('#galeriaImagenes').empty();
            $('#sinImagenes').show();

            if ($.fn.DataTable.isDataTable('#tablaUbicaciones')) {
                $('#tablaUbicaciones').DataTable().destroy();
                $('#tablaUbicaciones tbody').empty();
            }
            if ($.fn.DataTable.isDataTable('#tablaProveedores')) {
                $('#tablaProveedores').DataTable().destroy();
                $('#tablaProveedores tbody').empty();
            }
        }

        $('#btnGuardar').click(function() {
            var form = document.getElementById('formProducto');
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return false;
            }

            var id = $('#producto_id').val();
            var accionBackend = id ? 'editar' : 'agregar';

            if (!$('#producto_codigo').val().trim()) {
                $('#producto_codigo').addClass('is-invalid');
                return false;
            }
            if (!$('#producto_nombre').val().trim()) {
                $('#producto_nombre').addClass('is-invalid');
                return false;
            }
            if (!$('#producto_tipo_id').val()) {
                $('#producto_tipo_id').addClass('is-invalid');
                return false;
            }
            if (!$('#producto_categoria_id').val()) {
                $('#producto_categoria_id').addClass('is-invalid');
                return false;
            }

            var btnGuardar = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

            $.ajax({
                url: 'productos_ajax.php',
                type: 'POST',
                data: {
                    accion: accionBackend,
                    producto_id: id,
                    producto_codigo: $('#producto_codigo').val().trim(),
                    producto_nombre: $('#producto_nombre').val().trim(),
                    codigo_barras: $('#codigo_barras').val(),
                    producto_descripcion: $('#producto_descripcion').val(),
                    producto_categoria_id: $('#producto_categoria_id').val(),
                    producto_tipo_id: $('#producto_tipo_id').val(),
                    unidad_medida_id: $('#unidad_medida_id').val() || null,
                    cont_cuenta_id: $('#cont_cuenta_id').val() || null,
                    iva_alicuota_id: $('#iva_alicuota_id').val() || null,
                    lado: $('#lado').val(),
                    material: $('#material').val(),
                    color: $('#color').val(),
                    peso: $('#peso').val(),
                    dimensiones: $('#dimensiones').val(),
                    garantia: $('#garantia').val(),
                    controla_stock: $('#controla_stock').is(':checked') ? 1 : 0,  // <--- AGREGAR ESTA LÍNEA
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                },
                success: function(res) {
                    btnGuardar.prop('disabled', false).html('Guardar');
                    if (res.resultado) {
                        tabla.ajax.reload(() => {
                            Swal.fire({ icon: "success", title: "¡Guardado!", text: "Producto guardado correctamente", showConfirmButton: false, timer: 1500, toast: true, position: 'top-end' });
                            bootstrap.Modal.getInstance(document.getElementById('modalProducto')).hide();
                        });
                    } else {
                        Swal.fire({ icon: "error", title: "Error", text: res.error || "Error al guardar los datos" });
                    }
                },
                error: function() {
                    btnGuardar.prop('disabled', false).html('Guardar');
                    Swal.fire({ icon: "error", title: "Error de conexión", text: "Error al comunicarse con el servidor" });
                }
            });
        });

        $('#btnConfirmarAlta').click(function() {
            var form = document.getElementById('formAltaProducto');
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return false;
            }

            Swal.fire({
                title: '¿Confirmar Alta?',
                html: '¿Está seguro de dar de ALTA este producto?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, dar de Alta'
            }).then((result) => result.isConfirmed && ejecutarAccion(productoActualId, 'alta', 'Producto', 0));
        });

        // ========== INICIALIZACIÓN ==========
        inicializarDataTable();
        cargarBotonAgregar();
        cargarTiposProducto();
        cargarCategoriasProducto();
        cargarUnidadesMedida();
        cargarMarcas();
        cargarSucursales();
        cargarIvaAlicuotas();
        cargarCuentasContables();  // <--- Esta línea está presente

        $('[title]').tooltip({ trigger: 'hover', placement: 'top' });
    });
</script>

    <!-- Librerías necesarias -->
    <link rel="stylesheet" type="text/css"
        href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Sortable.js para arrastrar imágenes -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.14.0/Sortable.min.js"></script>
</main>

<!-- Modal para ver imagen en grande -->
<div class="modal fade" id="modalVerImagen" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="descripcionImagenGrande"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body text-center">
                <img id="imagenGrande" src="" alt="Imagen del producto" class="img-fluid rounded">
            </div>
        </div>
    </div>
</div>
<!-- Modal para carrusel de imágenes -->
<div class="modal fade" id="modalCarrusel" tabindex="-1" aria-labelledby="modalCarruselLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary text-white">
                <h5 class="modal-title" id="modalCarruselLabel">
                    <i class="fas fa-images me-2"></i>Galería de Imágenes
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body p-4" id="contenidoCarrusel">
                <!-- El carrusel se cargará dinámicamente aquí -->
                <div class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-3x text-primary mb-3"></i>
                    <p class="text-muted">Cargando imágenes...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>

</html>