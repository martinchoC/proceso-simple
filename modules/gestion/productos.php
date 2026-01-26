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
                                                    <input type="text" class="form-control" id="filtroCodigo" placeholder="Buscar código">
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
                                                <button type="button" class="btn btn-sm btn-outline-primary" id="btnAplicarFiltros">
                                                    <i class="fas fa-filter me-1"></i>Filtrar
                                                </button>
                                                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnLimpiarFiltros">
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
            <div class="modal fade" id="modalProducto" tabindex="-1" aria-labelledby="modalLabel"
                aria-hidden="true" data-bs-backdrop="static">
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
                                            data-bs-target="#nav-info" type="button" role="tab" 
                                            aria-controls="nav-info" aria-selected="true">
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
                                                <label for="producto_codigo" class="form-label form-label-sm">Código *</label>
                                                <input type="text" class="form-control form-control-sm" id="producto_codigo"
                                                    name="producto_codigo" maxlength="50" required>
                                                <div class="invalid-feedback">El código es obligatorio</div>
                                            </div>
                                            <div class="col-md-4">
                                                <label for="codigo_barras" class="form-label form-label-sm">Código de Barras</label>
                                                <input type="text" class="form-control form-control-sm" id="codigo_barras"
                                                    name="codigo_barras" maxlength="150">
                                            </div>
                                            <div class="col-md-4">
                                                <label for="producto_tipo_id" class="form-label form-label-sm">Tipo *</label>
                                                <select class="form-select form-select-sm" id="producto_tipo_id" name="producto_tipo_id" required>
                                                    <option value="">Seleccionar...</option>
                                                </select>
                                                <div class="invalid-feedback">Seleccione un tipo</div>
                                            </div>
                                        </div>
                                        
                                        <div class="row g-2 mt-1">
                                            <div class="col-md-12">
                                                <label for="producto_nombre" class="form-label form-label-sm">Nombre *</label>
                                                <input type="text" class="form-control form-control-sm" id="producto_nombre"
                                                    name="producto_nombre" maxlength="150" required>
                                                <div class="invalid-feedback">El nombre es obligatorio</div>
                                            </div>
                                        </div>
                                        
                                        <div class="row g-2 mt-1">
                                            <div class="col-md-6">
                                                <label for="producto_categoria_id" class="form-label form-label-sm">Categoría *</label>
                                                <select class="form-select form-select-sm" id="producto_categoria_id" 
                                                        name="producto_categoria_id" required>
                                                    <option value="">Seleccionar categoría...</option>
                                                </select>
                                                <div class="invalid-feedback">Seleccione una categoría</div>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="unidad_medida_id" class="form-label form-label-sm">Unidad de Medida</label>
                                                <select class="form-select form-select-sm" id="unidad_medida_id" name="unidad_medida_id">
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
                                        </div>
                                        
                                        <div class="row g-2 mt-1">
                                            <div class="col-md-12">
                                                <label for="producto_descripcion" class="form-label form-label-sm">Descripción</label>
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
                                        <button type="button" class="btn btn-primary btn-sm" id="btnAgregarCompatibilidad">
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
                                        Puedes arrastrar y soltar imágenes para cambiar su orden. La primera imagen será la principal.
                                    </div>
                                </div>
                                <!-- Pestaña de Ubicaciones -->
                                <div class="tab-pane fade" id="nav-ubicaciones" role="tabpanel" 
                                    aria-labelledby="nav-ubicaciones-tab">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">
                                            <i class="fas fa-map-marker-alt me-2 text-warning"></i>Ubicaciones del Producto
                                        </h6>
                                        <div class="btn-group">
                                            <button type="button" class="btn btn-warning btn-sm" id="btnAgregarUbicacion">
                                                <i class="fas fa-plus me-1"></i>Agregar Ubicación
                                            </button>
                                            <button type="button" class="btn btn-outline-warning btn-sm" id="btnNuevaUbicacion">
                                                <i class="fas fa-plus-circle me-1"></i>Nueva Ubicación
                                            </button>
                                        </div>
                                    </div>

                                    <!-- Tabla de ubicaciones -->
                                    <div class="table-responsive" style="max-height: 300px;">
                                        <table id="tablaUbicaciones" class="table table-sm table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="25%" class="py-1">Sucursal</th>
                                                    <th width="20%" class="py-1">Sección</th>
                                                    <th width="20%" class="py-1">Estantería</th>
                                                    <th width="20%" class="py-1">Estante/Posición</th>
                                                    <th width="15%" class="py-1 text-center">Acciones</th>
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
                                        <select class="form-select form-select-sm" id="marca_id" name="marca_id" required>
                                            <option value="">Seleccionar marca...</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione una marca</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="modelo_id" class="form-label form-label-sm">Modelo *</label>
                                        <select class="form-select form-select-sm" id="modelo_id" name="modelo_id" required disabled>
                                            <option value="">Seleccionar modelo...</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione un modelo</div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="submodelo_id" class="form-label form-label-sm">Submodelo</label>
                                        <select class="form-select form-select-sm" id="submodelo_id" name="submodelo_id" disabled>
                                            <option value="">Seleccionar submodelo...</option>
                                            <option value="0">Sin submodelo</option>
                                        </select>
                                        <div class="form-text">Opcional</div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="anio_desde" class="form-label form-label-sm">Año Desde *</label>
                                        <select class="form-select form-select-sm" id="anio_desde" name="anio_desde" required>
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
            <div class="modal fade" id="modalImagen" tabindex="-1" aria-labelledby="modalImagenLabel"
                aria-hidden="true" data-bs-backdrop="static">
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
                                        <div class="form-text">Formatos permitidos: JPG, PNG, GIF, WebP. Tamaño máximo: 5MB</div>
                                    </div>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-12">
                                        <label for="descripcion_imagen" class="form-label form-label-sm">Descripción</label>
                                        <input type="text" class="form-control form-control-sm" id="descripcion_imagen"
                                            name="descripcion" maxlength="255" placeholder="Descripción de la imagen">
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="es_principal_imagen" class="form-label form-label-sm">¿Es principal?</label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" role="switch" 
                                                   id="es_principal_imagen" name="es_principal" value="1">
                                            <label class="form-check-label" for="es_principal_imagen">Marcar como imagen principal</label>
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
                                            <img id="vistaPreviaImagen" src="" alt="Vista previa" class="img-fluid rounded" style="max-height: 200px;">
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
                                        <label for="sucursal_ubicacion_id" class="form-label form-label-sm">Ubicación *</label>
                                        <select class="form-select form-select-sm" id="sucursal_ubicacion_id" 
                                                name="sucursal_ubicacion_id" required>
                                            <option value="">Seleccionar ubicación...</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione una ubicación</div>
                                        <div class="form-text">Seleccione una ubicación existente para asignarla al producto</div>
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
                                        <select class="form-select form-select-sm" id="sucursal_id" name="sucursal_id" required>
                                            <option value="">Seleccionar sucursal...</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione una sucursal</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="seccion" class="form-label form-label-sm">Sección *</label>
                                        <input type="text" class="form-control form-control-sm" id="seccion"
                                            name="seccion" maxlength="50" required placeholder="Ej: Almacén, Sala de ventas">
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
                                        <label for="descripcion_ubicacion" class="form-label form-label-sm">Descripción</label>
                                        <textarea class="form-control form-control-sm" id="descripcion_ubicacion"
                                            name="descripcion" rows="2" maxlength="255" placeholder="Descripción opcional de la ubicación"></textarea>
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
                                    <textarea class="form-control" id="motivo_alta" name="motivo_alta" 
                                        rows="3" maxlength="500" required></textarea>
                                    <div class="invalid-feedback">Por favor ingrese el motivo del alta</div>
                                    <div class="form-text">Máximo 500 caracteres</div>
                                </div>
                                <div class="mb-3">
                                    <label for="fecha_alta" class="form-label">Fecha de Alta *</label>
                                    <input type="date" class="form-control" id="fecha_alta" 
                                        name="fecha_alta" required>
                                    <div class="invalid-feedback">Seleccione la fecha de alta</div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer bg-light border-top">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
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
        .tab-content {
            min-height: 350px;
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
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
    </style>

    <script>
        $(document).ready(function () {
            // Variables de contexto MULTIEMPRESA
            const empresa_idx = 2;
            const pagina_idx = <?php echo $pagina_idx; ?>;

            // Variables globales
            var tabla;
            var tablaCompatibilidad;
            var currentPage = 0;
            var currentOrder = [[1, 'asc']];
            var currentSearch = '';
            var productoActualId = null;
            var productoActualCompatibilidad = null;
            var productoActualImagenes = null;

            // ========== FUNCIONES DE CARGA DE DATOS ==========
            // Agrega esta función al inicio del script, después de las variables globales:
            // Función global para mostrar imagen en grande desde la tabla
            window.mostrarImagenGrande = function(url, titulo) {
            // Asegurar que la URL sea absoluta
            var rutaCompleta = url;
            
            // Crear modal dinámico si no existe
            if ($('#modalImagenTabla').length === 0) {
                $('body').append(`
                    <div class="modal fade" id="modalImagenTabla" tabindex="-1" aria-hidden="true">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title" id="tituloImagenTabla">${titulo || 'Imagen del producto'}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                </div>
                                <div class="modal-body text-center">
                                    <img id="imagenGrandeTabla" src="" alt="Imagen del producto" class="img-fluid rounded">
                                </div>
                            </div>
                        </div>
                    </div>
                `);
            }
            
            $('#imagenGrandeTabla').attr('src', rutaCompleta);
            $('#tituloImagenTabla').text(titulo || 'Imagen del producto');
            
            // Mostrar el modal
            var modal = new bootstrap.Modal(document.getElementById('modalImagenTabla'));
            modal.show();
        };
            // Cargar opciones de tipos de producto
            function cargarTiposProducto() {
                $.get('productos_ajax.php', {
                    accion: 'obtener_tipos_producto',
                    empresa_idx: empresa_idx
                }, function (tipos) {
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
                }, function (categorias) {
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
                }, function (unidades) {
                    var select = $('#unidad_medida_id');
                    select.empty().append('<option value="">Seleccionar unidad...</option>');
                    
                    if (unidades && unidades.length > 0) {
                        unidades.forEach(function(unidad) {
                            select.append(`<option value="${unidad.unidad_medida_id}">${unidad.unidad_nombre} (${unidad.unidad_abreviatura})</option>`);
                        });
                    }
                }, 'json');
            }

            // Función para cargar marcas en los filtros y modal
            function cargarMarcasFiltro() {
                $.get('productos_ajax.php', {
                    accion: 'obtener_marcas',
                    empresa_idx: empresa_idx
                }, function (marcas) {
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
            // ========== FUNCIONES DE UBICACIONES ==========

            // Variable global para ubicaciones
            var productoActualUbicaciones = null;
            var tablaUbicaciones = null;

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
                        { 
                            data: 'sucursal_nombre',
                            render: function(data) {
                                return data || '-';
                            }
                        },
                        { 
                            data: 'seccion',
                            render: function(data) {
                                return data || '-';
                            }
                        },
                        { 
                            data: 'estanteria',
                            render: function(data) {
                                return data || '-';
                            }
                        },
                        { 
                            data: null,
                            render: function(data) {
                                var estante = data.estante || ' - ';
                                var posicion = data.posicion || ' - ';
                                return `${estante} - ${posicion}`;
                            }
                        },
                        { 
                            data: 'producto_ubicacion_id',
                            orderable: false,
                            searchable: false,
                            className: "text-center",
                            render: function(data, type, row) {
                                return `
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-danger btn-eliminar-ubicacion" 
                                                data-id="${data}" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                `;
                            }
                        }
                    ],
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
                    },
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
                }, function (sucursales) {
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
                }, function (ubicaciones) {
                    var select = $('#sucursal_ubicacion_id');
                    select.empty().append('<option value="">Seleccionar ubicación...</option>');
                    
                    if (ubicaciones && ubicaciones.length > 0) {
                        var sucursalActual = '';
                        ubicaciones.forEach(function(ubicacion) {
                            // Agrupar por sucursal
                            if (ubicacion.sucursal_nombre !== sucursalActual) {
                                if (sucursalActual !== '') {
                                    select.append('</optgroup>');
                                }
                                sucursalActual = ubicacion.sucursal_nombre;
                                select.append(`<optgroup label="${sucursalActual}">`);
                            }
                            
                            var descripcionUbicacion = `${ubicacion.seccion} - ${ubicacion.estanteria} - Est. ${ubicacion.estante} Pos. ${ubicacion.posicion}`;
                            if (ubicacion.descripcion) {
                                descripcionUbicacion += ` (${ubicacion.descripcion})`;
                            }
                            
                            select.append(`<option value="${ubicacion.sucursal_ubicacion_id}">${descripcionUbicacion}</option>`);
                        });
                        
                        if (sucursalActual !== '') {
                            select.append('</optgroup>');
                        }
                    }
                }, 'json');
            }

            // Mostrar modal para agregar ubicación existente
            function mostrarModalUbicacion(productoId) {
                resetModalUbicacion();
                $('#ubicacion_producto_id').val(productoId);
                cargarUbicacionesSucursales();
                
                var modal = new bootstrap.Modal(document.getElementById('modalUbicacion'));
                modal.show();
            }

            // Mostrar modal para crear nueva ubicación
            function mostrarModalNuevaUbicacion(productoId) {
                resetModalNuevaUbicacion();
                $('#nueva_ubicacion_producto_id').val(productoId);
                cargarSucursales();
                
                var modal = new bootstrap.Modal(document.getElementById('modalNuevaUbicacion'));
                modal.show();
            }

            // Resetear modal de ubicación existente
            function resetModalUbicacion() {
                $('#formUbicacion')[0].reset();
                $('#formUbicacion').removeClass('was-validated');
                $('#sucursal_ubicacion_id').empty().append('<option value="">Seleccionar ubicación...</option>');
            }

            // Resetear modal de nueva ubicación
            function resetModalNuevaUbicacion() {
                $('#formNuevaUbicacion')[0].reset();
                $('#formNuevaUbicacion').removeClass('was-validated');
                $('#sucursal_id').empty().append('<option value="">Seleccionar sucursal...</option>');
            }

            // Agregar estos eventos en la función inicializarEventos():

            // Eventos de ubicaciones
            $('#btnAgregarUbicacion').click(function() {
                if (productoActualUbicaciones) {
                    mostrarModalUbicacion(productoActualUbicaciones);
                }
            });

            $('#btnNuevaUbicacion').click(function() {
                if (productoActualUbicaciones) {
                    mostrarModalNuevaUbicacion(productoActualUbicaciones);
                }
            });

            $(document).on('click', '.btn-eliminar-ubicacion', function() {
                var productoUbicacionId = $(this).data('id');
                
                Swal.fire({
                    title: '¿Eliminar Ubicación?',
                    text: 'Esta acción no se puede deshacer',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, eliminar',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.post('productos_ajax.php', {
                            accion: 'eliminar_ubicacion_producto',
                            producto_ubicacion_id: productoUbicacionId,
                            empresa_idx: empresa_idx
                        }, function(res) {
                            if (res.success) {
                                Swal.fire('¡Eliminado!', 'Ubicación eliminada correctamente', 'success');
                                if (tablaUbicaciones) {
                                    tablaUbicaciones.ajax.reload();
                                }
                            } else {
                                Swal.fire('Error', res.error || 'Error al eliminar', 'error');
                            }
                        }, 'json');
                    }
                });
            });

            // Guardar ubicación existente
            $('#btnGuardarUbicacion').click(function() {
                var form = document.getElementById('formUbicacion');
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var btn = $(this);
                var originalText = btn.html();
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

                var datos = {
                    accion: 'agregar_ubicacion_producto',
                    producto_id: $('#ubicacion_producto_id').val(),
                    sucursal_ubicacion_id: $('#sucursal_ubicacion_id').val(),
                    empresa_idx: empresa_idx
                };

                $.post('productos_ajax.php', datos, function(res) {
                    btn.prop('disabled', false).html(originalText);
                    
                    if (res.resultado) {
                        Swal.fire('¡Guardado!', 'Ubicación asignada correctamente', 'success');
                        var modal = bootstrap.Modal.getInstance(document.getElementById('modalUbicacion'));
                        modal.hide();
                        
                        if (tablaUbicaciones) {
                            tablaUbicaciones.ajax.reload();
                        }
                    } else {
                        Swal.fire('Error', res.error || 'Error al guardar', 'error');
                    }
                }, 'json');
            });

            // Guardar nueva ubicación
            $('#btnGuardarNuevaUbicacion').click(function() {
                var form = document.getElementById('formNuevaUbicacion');
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var btn = $(this);
                var originalText = btn.html();
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Creando...');

                var datos = {
                    accion: 'crear_ubicacion_sucursal',
                    empresa_id: empresa_idx,
                    sucursal_id: $('#sucursal_id').val(),
                    seccion: $('#seccion').val(),
                    estanteria: $('#estanteria').val(),
                    estante: $('#estante').val(),
                    posicion: $('#posicion').val(),
                    descripcion: $('#descripcion_ubicacion').val()
                };

                $.post('productos_ajax.php', datos, function(res) {
                    btn.prop('disabled', false).html(originalText);
                    
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
                            // Cerrar modal de creación
                            var modalCreacion = bootstrap.Modal.getInstance(document.getElementById('modalNuevaUbicacion'));
                            modalCreacion.hide();
                            
                            if (result.isConfirmed) {
                                // Asignar la nueva ubicación al producto
                                $.post('productos_ajax.php', {
                                    accion: 'agregar_ubicacion_producto',
                                    producto_id: $('#nueva_ubicacion_producto_id').val(),
                                    sucursal_ubicacion_id: res.sucursal_ubicacion_id,
                                    empresa_idx: empresa_idx
                                }, function(res2) {
                                    if (res2.resultado) {
                                        Swal.fire('¡Asignada!', 'Ubicación creada y asignada correctamente', 'success');
                                        if (tablaUbicaciones) {
                                            tablaUbicaciones.ajax.reload();
                                        }
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
            // Cargar modelos por marca
            function cargarModelos(marcaId, targetId = '#modelo_id') {
                $.get('productos_ajax.php', {
                    accion: 'obtener_modelos',
                    empresa_idx: empresa_idx,
                    marca_id: marcaId
                }, function (modelos) {
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
                $.get('productos_ajax.php', {
                    accion: 'obtener_submodelos',
                    empresa_idx: empresa_idx,
                    modelo_id: modeloId
                }, function (submodelos) {
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
                
                // Año desde
                var selectDesde = $('#anio_desde');
                selectDesde.empty().append('<option value="">Año...</option>');
                
                // Año hasta
                var selectHasta = $('#anio_hasta');
                selectHasta.empty().append('<option value="">Año...</option>')
                                .append('<option value="0">Actual</option>');
                
                for (var year = startYear; year <= currentYear + 10; year++) {
                    selectDesde.append(`<option value="${year}">${year}</option>`);
                    selectHasta.append(`<option value="${year}">${year}</option>`);
                }
            }

            // ========== FUNCIONES DE TABLA PRINCIPAL ==========

            // Función para inicializar DataTable de productos
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
                        data: function (d) {
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
                    dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>' +
                        '<"row"<"col-sm-12"tr>>' +
                        '<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>' +
                        '<"clear">',
                    pageLength: 50,
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                    columns: [
                    { 
                        data: 'producto_id', 
                        className: 'text-center fw-bold',
                        width: '80px'
                    },
                    { 
                        data: 'producto_codigo', 
                        className: 'text-center fw-medium',
                        width: '100px',
                        render: function (data, type, row) {
                            return type === 'export' ? data : `<div class="fw-bold">${data}</div>`;
                        }
                    },
                    { 
                        data: 'producto_nombre',
                        width: '200px',
                        render: function (data, type, row) {
                            if (type === 'export') return data;
                            var desc = row.producto_descripcion ? 
                                `<small class="text-muted d-block">${row.producto_descripcion.substring(0, 40)}${row.producto_descripcion.length > 40 ? '...' : ''}</small>` : '';
                            return `<div class="fw-medium">${data}</div>${desc}`;
                        }
                    },
                    { 
                        data: 'marcas_compatibles',
                        width: '150px',
                        render: function (data, type, row) {
                            if (type === 'export') return data || '';
                            if (!data || data === '') return '<span class="text-muted">-</span>';
                            return `<span class="badge badge-compatibilidad bg-info text-white" title="${data}">${data}</span>`;
                        }
                    },
                    { 
                        data: 'modelos_compatibles',
                        width: '150px',
                        render: function (data, type, row) {
                            if (type === 'export') return data || '';
                            if (!data || data === '') return '<span class="text-muted">-</span>';
                            return `<span class="badge badge-compatibilidad bg-success text-white" title="${data}">${data}</span>`;
                        }
                    },
                    { 
                        data: 'submodelos_compatibles',
                        width: '150px',
                        render: function (data, type, row) {
                            if (type === 'export') return data || '';
                            if (!data || data === '') return '<span class="text-muted">-</span>';
                            return `<span class="badge badge-compatibilidad bg-warning text-dark" title="${data}">${data}</span>`;
                        }
                    },
                    { 
                        data: 'ubicaciones_info',  // Nueva columna de ubicaciones
                        width: '200px',
                        render: function (data, type, row) {
                            if (type === 'export') return data || '';
                            if (!data || data === '') return '<span class="text-muted">-</span>';
                            
                            // Limitar a 3 ubicaciones para no hacer muy larga la celda
                            var ubicaciones = data.split('; ');
                            var mostrar = ubicaciones.slice(0, 3);
                            var html = '';
                            
                            mostrar.forEach(function(ubicacion) {
                                html += `<span class="badge bg-secondary mb-1 d-block text-start" style="font-size: 0.7rem;">${ubicacion}</span>`;
                            });
                            
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
                        render: function (data, type, row) {
                            if (type === 'export') return data ? 'Sí' : 'No';
                            
                            if (data) {
                                var rutaImagen = 'get_imagen.php?id=' + data;
                                return `<img src="${rutaImagen}" alt="Imagen principal" 
                                        class="img-thumbnail rounded-circle" 
                                        style="width: 50px; height: 50px; object-fit: cover; cursor: pointer;"
                                        onclick="window.mostrarImagenGrande('${rutaImagen}', '${row.producto_nombre}')"
                                        title="Click para ver imagen"
                                        onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"50\" height=\"50\"><circle cx=\"25\" cy=\"25\" r=\"25\" fill=\"#f8f9fa\"/><text x=\"25\" y=\"28\" text-anchor=\"middle\" fill=\"#6c757d\" font-family=\"Arial\" font-size=\"10\">?</text></svg>';">
                                        `;
                            } else {
                                return `<div class="text-center text-muted">
                                        <i class="fas fa-image fa-lg"></i>
                                        <div class="small">Sin imagen</div>
                                        </div>`;
                            }
                        }
                    
                    },
                    { 
                        data: 'estado_info', 
                        className: 'text-center',
                        width: '100px',
                        render: function (data, type, row) {
                            if (!data || !data.estado_registro) {
                                return type === 'export' ? 'Sin estado' : '<span class="badge badge-estado-inactivo">Sin estado</span>';
                            }

                            var estado = data.estado_registro;
                            var codigo = data.codigo_estandar || 'DESCONOCIDO';

                            if (type === 'export') return estado;

                            var claseBadge = 'badge-estado-inactivo';
                            if (codigo === 'ACTIVO') claseBadge = 'badge-estado-activo';
                            else if (codigo === 'SUSPENDIDO') claseBadge = 'badge-estado-suspendido';
                            else if (codigo === 'BLOQUEADO') claseBadge = 'badge-estado-bloqueado';

                            return `<span class="badge ${claseBadge}">${estado}</span>`;
                        }
                    },
                    { 
                        data: 'botones', 
                        orderable: false, 
                        searchable: false,
                        className: "text-center",
                        width: '120px',
                        render: function (data, type, row) {
                            if (type === 'export') return '';
                            
                            var botones = '';
                            if (data && data.length > 0) {
                                data.forEach(boton => {
                                    var claseBoton = 'btn-xs me-1 ';
                                    var nombreAccion = boton.accion_js || boton.nombre_funcion.toLowerCase();
                                    var icono = boton.icono_clase || 'fas fa-cog';
                                    
                                    // Determinar clase de botón basado en la acción
                                    if (nombreAccion === 'editar') {
                                        claseBoton += 'btn-outline-primary';
                                    } else if (nombreAccion === 'eliminar' || nombreAccion === 'baja') {
                                        claseBoton += 'btn-outline-danger';
                                    } else if (nombreAccion === 'alta' || nombreAccion === 'activar') {
                                        claseBoton += 'btn-outline-success';
                                    } else if (nombreAccion === 'suspender' || nombreAccion === 'bloquear') {
                                        claseBoton += 'btn-outline-warning';
                                    } else {
                                        claseBoton += 'btn-outline-secondary';
                                    }
                                    
                                    botones += `<button type="button" class="btn ${claseBoton} btn-accion" 
                                        title="${boton.descripcion || boton.nombre_funcion}" 
                                        data-id="${row.producto_id}" 
                                        data-accion="${boton.accion_js}"
                                        data-confirmable="${boton.es_confirmable || 0}"
                                        data-producto="${row.producto_nombre}">
                                        <i class="${icono}"></i>
                                    </button>`;
                                });
                            }
                            
                            return botones || '<span class="text-muted small">-</span>';
                        }
                    }
                ],
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
                    },
                    order: currentOrder,
                    responsive: true,
                    createdRow: function (row, data, dataIndex) {
                        if (data.estado_info && data.estado_info.codigo_estandar === 'INACTIVO') {
                            $(row).addClass('table-secondary');
                        } else if (data.estado_info && data.estado_info.codigo_estandar === 'BLOQUEADO') {
                            $(row).addClass('table-warning');
                        }
                    },
                    initComplete: function () {
                        inicializarEventos();
                    }
                });
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
                        { 
                            data: 'marca_nombre',
                            render: function(data) {
                                return data || '-';
                            }
                        },
                        { 
                            data: 'modelo_nombre',
                            render: function(data) {
                                return data || '-';
                            }
                        },
                        { 
                            data: 'submodelo_nombre',
                            render: function(data) {
                                return data || '-';
                            }
                        },
                        { 
                            data: null,
                            className: 'text-center',
                            render: function(data) {
                                var anioDesde = data.anio_desde || '';
                                var anioHasta = data.anio_hasta == '0' ? 'Actual' : (data.anio_hasta || '');
                                if (anioHasta && anioHasta !== 'Actual') {
                                    return `${anioDesde} - ${anioHasta}`;
                                }
                                return anioDesde || '-';
                            }
                        },
                        { 
                            data: 'compatibilidad_id',
                            orderable: false,
                            searchable: false,
                            className: "text-center",
                            render: function(data, type, row) {
                                return `
                                    <div class="btn-group btn-group-sm" role="group">
                                        <button type="button" class="btn btn-outline-primary btn-editar-compatibilidad" 
                                                data-id="${data}" title="Editar">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <button type="button" class="btn btn-outline-danger btn-eliminar-compatibilidad" 
                                                data-id="${data}" title="Eliminar">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                `;
                            }
                        }
                    ],
                    language: {
                        url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json'
                    },
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
                
                // Cargar marcas primero
                cargarMarcasFiltro();
                cargarAnios();
                
                if (compatibilidadId) {
                    // Modo edición
                    $('#modalCompatibilidadLabel').html('<i class="fas fa-edit me-2"></i>Editar Compatibilidad');
                    $('#compatibilidad_id').val(compatibilidadId);
                    
                    // Cargar datos de la compatibilidad después de que carguen las marcas
                    setTimeout(function() {
                        $.get('productos_ajax.php', {
                            accion: 'obtener_compatibilidad_por_id',
                            compatibilidad_id: compatibilidadId,
                            empresa_idx: empresa_idx
                        }, function(res) {
                            if (res && res.compatibilidad_id) {
                                setTimeout(function() {
                                    $('#marca_id').val(res.marca_id);
                                    // Cargar modelos de esta marca
                                    cargarModelos(res.marca_id);
                                    
                                    setTimeout(function() {
                                        $('#modelo_id').val(res.modelo_id);
                                        // Cargar submodelos de este modelo
                                        cargarSubmodelos(res.modelo_id);
                                        
                                        setTimeout(function() {
                                            $('#submodelo_id').val(res.submodelo_id || '');
                                            $('#anio_desde').val(res.anio_desde);
                                            $('#anio_hasta').val(res.anio_hasta || '');
                                        }, 300);
                                    }, 300);
                                }, 300);
                            }
                        }, 'json');
                    }, 500);
                } else {
                    // Modo creación
                    $('#modalCompatibilidadLabel').html('<i class="fas fa-plus me-2"></i>Agregar Compatibilidad');
                }
                
                var modal = new bootstrap.Modal(document.getElementById('modalCompatibilidad'));
                modal.show();
            }

            // Resetear modal de compatibilidad
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
                    var galeria = $('#galeriaImagenes');
                    var sinImagenes = $('#sinImagenes');
                    
                    galeria.empty();
                    
                    if (imagenes && imagenes.length > 0) {
                        sinImagenes.hide();
                        
                        imagenes.forEach(function(imagen, index) {
                            // Usar imagen_url desde el servidor
                            var srcImagen = imagen.imagen_url || 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="150" height="150"><rect width="150" height="150" fill="#f8f9fa"/><text x="75" y="75" text-anchor="middle" fill="#6c757d" font-family="Arial" font-size="12">Imagen</text></svg>';
                            
                            var esPrincipal = imagen.es_principal == 1;
                            var clasePrincipal = esPrincipal ? 'card-imagen-principal' : '';
                            
                            var cardHtml = `
                                <div class="col-md-3 mb-3" data-id="${imagen.producto_imagen_id}" data-orden="${imagen.orden || 0}">
                                    <div class="card card-imagen ${clasePrincipal} h-100">
                                        <div class="position-relative">
                                            <img src="${srcImagen}" class="card-img-top imagen-miniatura" 
                                                alt="${imagen.descripcion || 'Imagen del producto'}"
                                                onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"150\" height=\"150\"><rect width=\"150\" height=\"150\" fill=\"#f8f9fa\"/><text x=\"75\" y=\"75\" text-anchor=\"middle\" fill=\"#6c757d\" font-family=\"Arial\" font-size=\"12\">Error</text></svg>';"
                                                onclick="mostrarImagenGrande('${srcImagen}', '${imagen.descripcion || ''}')">
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
                        
                        // Inicializar ordenamiento por arrastre
                        inicializarSortable();
                    } else {
                        sinImagenes.show();
                    }
                }, 'json');
            }
            
            // Función para mostrar imagen en grande desde la tabla principal
            window.mostrarImagenGrande = function(url, titulo) {
                // Asegurar que la URL sea correcta
                var rutaCompleta = url;
                
                // Crear modal dinámico si no existe
                if ($('#modalImagenTabla').length === 0) {
                    $('body').append(`
                        <div class="modal fade" id="modalImagenTabla" tabindex="-1" aria-hidden="true">
                            <div class="modal-dialog modal-dialog-centered modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title" id="tituloImagenTabla">${titulo || 'Imagen del producto'}</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                                    </div>
                                    <div class="modal-body text-center">
                                        <img id="imagenGrandeTabla" src="" alt="Imagen del producto" class="img-fluid rounded">
                                    </div>
                                </div>
                            </div>
                        </div>
                    `);
                }
                
                $('#imagenGrandeTabla').attr('src', rutaCompleta);
                $('#tituloImagenTabla').text(titulo || 'Imagen del producto');
                
                // Mostrar el modal
                var modal = new bootstrap.Modal(document.getElementById('modalImagenTabla'));
                modal.show();
            };
            
            // Función para formatear bytes a formato legible
            function formatBytes(bytes, decimals = 2) {
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const dm = decimals < 0 ? 0 : decimals;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
            }

            // Función global para mostrar imagen en grande
            function mostrarImagenGrande(ruta, descripcion) {
                // Asegurar que la ruta tenga / al inicio
                var rutaCompleta = ruta.startsWith('/') ? ruta : '/' + ruta;
                $('#imagenGrande').attr('src', rutaCompleta);
                $('#descripcionImagenGrande').text(descripcion || 'Sin descripción');
                
                // Mostrar el modal
                var modal = new bootstrap.Modal(document.getElementById('modalVerImagen'));
                modal.show();
            }

            // Inicializar ordenamiento por arrastre
            function inicializarSortable() {
                if (typeof Sortable !== 'undefined') {
                    var galeria = document.getElementById('galeriaImagenes');
                    new Sortable(galeria, {
                        animation: 150,
                        ghostClass: 'sortable-ghost',
                        chosenClass: 'sortable-chosen',
                        onEnd: function(evt) {
                            // Actualizar orden en base de datos
                            actualizarOrdenImagenes();
                        }
                    });
                }
            }

            // Actualizar orden de imágenes después de arrastrar
            function actualizarOrdenImagenes() {
                var ordenes = [];
                $('#galeriaImagenes .col-md-3').each(function(index) {
                    var id = $(this).data('id');
                    ordenes.push({
                        producto_imagen_id: id,
                        orden: index
                    });
                });
                
                // Actualizar cada imagen con su nuevo orden
                ordenes.forEach(function(item) {
                    $.post('productos_ajax.php', {
                        accion: 'actualizar_imagen_producto',
                        producto_imagen_id: item.producto_imagen_id,
                        orden: item.orden,
                        empresa_idx: empresa_idx
                    }, function(res) {
                        if (!res.resultado) {
                            console.error('Error al actualizar orden:', res.error);
                        }
                    }, 'json');
                });
            }

            // Mostrar modal para subir/editar imagen
            function mostrarModalImagen(productoId, productoImagenId = null) {
                resetModalImagen();
                $('#imagen_producto_id').val(productoId);
                
                if (productoImagenId) {
                    // Modo edición
                    $('#modalImagenLabel').html('<i class="fas fa-edit me-2"></i>Editar Imagen');
                    $('#producto_imagen_id').val(productoImagenId);
                    
                    // Cargar datos de la imagen
                    $.get('productos_ajax.php', {
                        accion: 'obtener_imagen_por_id',
                        producto_imagen_id: productoImagenId,
                        empresa_idx: empresa_idx
                    }, function(res) {
                        if (res && res.producto_imagen_id) {
                            $('#descripcion_imagen').val(res.descripcion || '');
                            $('#es_principal_imagen').prop('checked', res.es_principal == 1);
                            $('#orden_imagen').val(res.orden || 0);
                            
                            // Mostrar vista previa
                            $('#vistaPreviaContainer').show();
                            $('#vistaPreviaImagen').attr('src', '/' + res.imagen_ruta);
                            
                            // No requerir archivo en modo edición
                            $('#imagen_archivo').removeAttr('required');
                        }
                    }, 'json');
                } else {
                    // Modo creación
                    $('#modalImagenLabel').html('<i class="fas fa-plus me-2"></i>Agregar Imagen');
                    $('#imagen_archivo').attr('required', 'required');
                }
                
                var modal = new bootstrap.Modal(document.getElementById('modalImagen'));
                modal.show();
            }

            // Resetear modal de imagen
            function resetModalImagen() {
                $('#formImagen')[0].reset();
                $('#producto_imagen_id').val('');
                $('#formImagen').removeClass('was-validated');
                $('#vistaPreviaContainer').hide();
                $('#vistaPreviaImagen').attr('src', '');
            }

            // ========== EVENTOS ==========

            function inicializarEventos() {
                // Botón recargar
                $('#btnRecargar').off('click').on('click', function () {
                    var btn = $(this);
                    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                    tabla.ajax.reload(function () {
                        btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>');
                    });
                });

                // Filtros
                $('#btnAplicarFiltros').click(function () {
                    tabla.ajax.reload();
                });

                $('#btnLimpiarFiltros').click(function () {
                    $('#filtroCodigo').val('');
                    $('#filtroMarca').val('');
                    $('#filtroModelo').val('').prop('disabled', true);
                    $('#filtroSubmodelo').val('').prop('disabled', true);
                    tabla.ajax.reload();
                });

                // Eventos para los filtros dependientes
                $('#filtroMarca').change(function() {
                    var marcaId = $(this).val();
                    if (marcaId) {
                        cargarModelos(marcaId, '#filtroModelo');
                    } else {
                        $('#filtroModelo').empty().append('<option value="">Todos los modelos</option>').prop('disabled', true);
                        $('#filtroSubmodelo').empty().append('<option value="">Todos los submodelos</option>').prop('disabled', true);
                    }
                });

                $('#filtroModelo').change(function() {
                    var modeloId = $(this).val();
                    if (modeloId) {
                        cargarSubmodelos(modeloId, '#filtroSubmodelo');
                    } else {
                        $('#filtroSubmodelo').empty().append('<option value="">Todos los submodelos</option>').prop('disabled', true);
                    }
                });

                // Eventos de compatibilidad
                $('#btnAgregarCompatibilidad').click(function() {
                    if (productoActualCompatibilidad) {
                        mostrarModalCompatibilidad(productoActualCompatibilidad);
                    }
                });

                $(document).on('click', '.btn-editar-compatibilidad', function() {
                    var compatibilidadId = $(this).data('id');
                    if (productoActualCompatibilidad) {
                        mostrarModalCompatibilidad(productoActualCompatibilidad, compatibilidadId);
                    }
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
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.post('productos_ajax.php', {
                                accion: 'eliminar_compatibilidad',
                                compatibilidad_id: compatibilidadId,
                                empresa_idx: empresa_idx
                            }, function(res) {
                                if (res.success) {
                                    Swal.fire('¡Eliminado!', 'Compatibilidad eliminada correctamente', 'success');
                                    if (tablaCompatibilidad) {
                                        tablaCompatibilidad.ajax.reload();
                                    }
                                } else {
                                    Swal.fire('Error', res.error || 'Error al eliminar', 'error');
                                }
                            }, 'json');
                        }
                    });
                });

                // Cambio de marca -> cargar modelos (para modal de compatibilidad)
                $(document).on('change', '#marca_id', function() {
                    var marcaId = $(this).val();
                    if (marcaId) {
                        cargarModelos(marcaId, '#modelo_id');
                    } else {
                        $('#modelo_id').empty().append('<option value="">Seleccionar modelo...</option>').prop('disabled', true);
                        $('#submodelo_id').empty().append('<option value="">Seleccionar submodelo...</option>').prop('disabled', true);
                    }
                });

                // Cambio de modelo -> cargar submodelos (para modal de compatibilidad)
                $(document).on('change', '#modelo_id', function() {
                    var modeloId = $(this).val();
                    if (modeloId) {
                        cargarSubmodelos(modeloId, '#submodelo_id');
                    } else {
                        $('#submodelo_id').empty().append('<option value="">Seleccionar submodelo...</option>').prop('disabled', true);
                    }
                });

                // Guardar compatibilidad
                $('#btnGuardarCompatibilidad').click(function() {
                    var form = document.getElementById('formCompatibilidad');
                    if (!form.checkValidity()) {
                        form.classList.add('was-validated');
                        return false;
                    }

                    var btn = $(this);
                    var originalText = btn.html();
                    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

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
                        btn.prop('disabled', false).html(originalText);
                        
                        if (res.success || res.resultado) {
                            Swal.fire('¡Guardado!', 'Compatibilidad guardada correctamente', 'success');
                            var modal = bootstrap.Modal.getInstance(document.getElementById('modalCompatibilidad'));
                            modal.hide();
                            
                            if (tablaCompatibilidad) {
                                tablaCompatibilidad.ajax.reload();
                            }
                        } else {
                            Swal.fire('Error', res.error || 'Error al guardar', 'error');
                        }
                    }, 'json');
                });

                // Eventos de imágenes
                $('#btnAgregarImagen').click(function() {
                    if (productoActualImagenes) {
                        mostrarModalImagen(productoActualImagenes);
                    }
                });

                $(document).on('click', '.btn-editar-imagen', function() {
                    var productoImagenId = $(this).data('id');
                    if (productoActualImagenes) {
                        mostrarModalImagen(productoActualImagenes, productoImagenId);
                    }
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
                        confirmButtonText: 'Sí, marcar como principal',
                        cancelButtonText: 'Cancelar'
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
                                } else {
                                    Swal.fire('Error', res.error || 'Error al actualizar', 'error');
                                }
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
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
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
                                } else {
                                    Swal.fire('Error', res.error || 'Error al eliminar', 'error');
                                }
                            }, 'json');
                        }
                    });
                });

                // Vista previa de imagen al seleccionar archivo
                $('#imagen_archivo').change(function() {
                    var archivo = this.files[0];
                    if (archivo) {
                        var reader = new FileReader();
                        reader.onload = function(e) {
                            $('#vistaPreviaContainer').show();
                            $('#vistaPreviaImagen').attr('src', e.target.result);
                        };
                        reader.readAsDataURL(archivo);
                    } else {
                        $('#vistaPreviaContainer').hide();
                    }
                });

                // Guardar imagen
                $('#btnGuardarImagen').click(function() {
                    var form = document.getElementById('formImagen');
                    var productoImagenId = $('#producto_imagen_id').val();
                    
                    // En modo creación, validar que se haya seleccionado archivo
                    if (!productoImagenId) {
                        var archivo = $('#imagen_archivo')[0].files[0];
                        if (!archivo) {
                            form.classList.add('was-validated');
                            return false;
                        }
                    }

                    if (!form.checkValidity()) {
                        form.classList.add('was-validated');
                        return false;
                    }

                    var btn = $(this);
                    var originalText = btn.html();
                    btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

                    var formData = new FormData(form);
                    formData.append('accion', productoImagenId ? 'actualizar_imagen_producto' : 'subir_imagen_producto');
                    formData.append('producto_id', $('#imagen_producto_id').val());
                    if (productoImagenId) {
                        formData.append('producto_imagen_id', productoImagenId);
                    }
                    formData.append('empresa_idx', empresa_idx);

                    $.ajax({
                        url: 'productos_ajax.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(res) {
                            btn.prop('disabled', false).html(originalText);
                            
                            if (res.resultado) {
                                Swal.fire('¡Guardado!', 'Imagen guardada correctamente', 'success');
                                var modal = bootstrap.Modal.getInstance(document.getElementById('modalImagen'));
                                modal.hide();
                                
                                cargarImagenesProducto(productoActualImagenes);
                            } else {
                                Swal.fire('Error', res.error || 'Error al guardar', 'error');
                            }
                        },
                        error: function() {
                            btn.prop('disabled', false).html(originalText);
                            Swal.fire('Error', 'Error de conexión al servidor', 'error');
                        }
                    });
                });
            }

            // ========== FUNCIONES PRINCIPALES ==========

            // Cargar botón Agregar dinámicamente
            function cargarBotonAgregar() {
                $.get('productos_ajax.php', {
                    accion: 'obtener_boton_agregar',
                    pagina_idx: pagina_idx
                }, function (botonAgregar) {
                    if (botonAgregar && botonAgregar.nombre_funcion) {
                        var icono = botonAgregar.icono_clase ? `<i class="${botonAgregar.icono_clase} me-1"></i>` : '';
                        var colorClase = botonAgregar.bg_clase && botonAgregar.text_clase ? 
                            botonAgregar.bg_clase + ' ' + botonAgregar.text_clase : 
                            (botonAgregar.color_clase || 'btn-primary');

                        $('#contenedor-boton-agregar').html(
                            `<button type="button" class="btn ${colorClase}" id="btnNuevo">
                                ${icono}${botonAgregar.nombre_funcion}
                            </button>`
                        );
                    } else {
                        $('#contenedor-boton-agregar').html(
                            '<button type="button" class="btn btn-primary" id="btnNuevo">' +
                            '<i class="fas fa-plus me-1"></i>Agregar Producto</button>'
                        );
                    }
                }, 'json');
            }

            // Manejador para botón "Agregar"
            $(document).on('click', '#btnNuevo', function () {
                resetModal();
                $('#modalLabel').text('Nuevo Producto');
                cargarTiposProducto();
                cargarCategoriasProducto();
                cargarUnidadesMedida();
                
                var modal = new bootstrap.Modal(document.getElementById('modalProducto'));
                modal.show();
                $('#producto_codigo').focus();
            });

            $(document).on('click', '.btn-accion', function () {
                var productoId = $(this).data('id');
                var accionJs = $(this).data('accion');
                var confirmable = $(this).data('confirmable');
                var producto = $(this).data('producto');

                if (accionJs === 'editar') {
                    cargarProductoParaEditar(productoId);
                } else {
                    ejecutarAccion(productoId, accionJs, producto, confirmable);
                }
            });

            // Función para mostrar modal de alta
            function mostrarModalAlta(productoId, producto) {
                productoActualId = productoId;
                $('#alta_producto_id').val(productoId);
                $('#mensajeAlta').html(`¿Está seguro de dar de ALTA el producto <strong>"${producto}"</strong>?`);
                $('#formAltaProducto')[0].reset();
                $('#formAltaProducto').removeClass('was-validated');
                
                var hoy = new Date().toISOString().split('T')[0];
                $('#fecha_alta').val(hoy);
                
                var modal = new bootstrap.Modal(document.getElementById('modalAltaProducto'));
                modal.show();
                $('#motivo_alta').focus();
            }

            // Función para ejecutar cualquier acción del backend (MEJORADA)
            function ejecutarAccion(productoId, accionJs, producto, esConfirmable = 0) {
                // Si requiere confirmación y no se ha mostrado el modal de alta
                if (esConfirmable == 1 && (accionJs === 'alta' || accionJs === 'activar')) {
                    mostrarModalAlta(productoId, producto);
                    return;
                }
                
                // Para otras acciones confirmables
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
                    }).then((result) => {
                        if (result.isConfirmed) {
                            enviarAccionBackend(productoId, accionJs, producto);
                        }
                    });
                } else {
                    enviarAccionBackend(productoId, accionJs, producto);
                }
            }

            // Función auxiliar para enviar la acción al backend
            function enviarAccionBackend(productoId, accionJs, producto) {
                $.post('productos_ajax.php', {
                    accion: 'ejecutar_accion',
                    producto_id: productoId,
                    accion_js: accionJs,
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                }, function (res) {
                    if (res.success) {
                        tabla.ajax.reload(function () {
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
                                if (modalAlta) {
                                    modalAlta.hide();
                                }
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || `Error al ${accionJs} el producto`,
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            // Función para cargar producto en modal de edición
            function cargarProductoParaEditar(productoId) {
                $.get('productos_ajax.php', {
                    accion: 'obtener',
                    producto_id: productoId,
                    empresa_idx: empresa_idx
                }, function (res) {
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
                        $('#garantia').val(res.guarantia || '');
                        
                        // Cargar selects
                        cargarTiposProducto();
                        cargarCategoriasProducto();
                        cargarUnidadesMedida();
                        
                        setTimeout(function() {
                            // Establecer valores seleccionados
                            $('#producto_tipo_id').val(res.producto_tipo_id);
                            $('#producto_categoria_id').val(res.producto_categoria_id);
                            $('#unidad_medida_id').val(res.unidad_medida_id || '');
                        }, 500);
                        
                        $('#modalLabel').text('Editar Producto');
                        
                        // Cargar compatibilidad
                        cargarCompatibilidad(productoId);
                        
                        // Cargar imágenes
                        cargarImagenesProducto(productoId);
                        // Cargar ubicaciones
                        cargarUbicacionesProducto(productoId);
                        
                        var modal = new bootstrap.Modal(document.getElementById('modalProducto'));
                        modal.show();
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "Error al obtener datos del producto",
                            confirmButtonText: "Entendido"
                        });
                    }
                }, 'json');
            }

            // Función para resetear el modal
            function resetModal() {
                $('#formProducto')[0].reset();
                $('#producto_id').val('');
                $('#formProducto').removeClass('was-validated');
                
                $('#producto_tipo_id').empty().append('<option value="">Seleccionar tipo...</option>');
                $('#producto_categoria_id').empty().append('<option value="">Seleccionar categoría...</option>');
                $('#unidad_medida_id').empty().append('<option value="">Seleccionar unidad...</option>');
                
                // Limpiar tabla de compatibilidad
                if ($.fn.DataTable.isDataTable('#tablaCompatibilidad')) {
                    $('#tablaCompatibilidad').DataTable().destroy();
                    $('#tablaCompatibilidad tbody').empty();
                }
                
                // Limpiar galería de imágenes
                $('#galeriaImagenes').empty();
                $('#sinImagenes').show();
                // Limpiar tabla de ubicaciones
                if ($.fn.DataTable.isDataTable('#tablaUbicaciones')) {
                    $('#tablaUbicaciones').DataTable().destroy();
                    $('#tablaUbicaciones tbody').empty();
                }
            }

            // Validación del formulario de producto
            $('#btnGuardar').click(function () {
                var form = document.getElementById('formProducto');
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                var id = $('#producto_id').val();
                var accionBackend = id ? 'editar' : 'agregar';
                
                var productoCodigo = $('#producto_codigo').val().trim();
                var productoNombre = $('#producto_nombre').val().trim();
                var productoTipoId = $('#producto_tipo_id').val();
                var productoCategoriaId = $('#producto_categoria_id').val();

                if (!productoCodigo) {
                    $('#producto_codigo').addClass('is-invalid');
                    return false;
                }
                if (!productoNombre) {
                    $('#producto_nombre').addClass('is-invalid');
                    return false;
                }
                if (!productoTipoId) {
                    $('#producto_tipo_id').addClass('is-invalid');
                    return false;
                }
                if (!productoCategoriaId || productoCategoriaId < 1) {
                    $('#producto_categoria_id').addClass('is-invalid');
                    return false;
                }

                var btnGuardar = $(this);
                var originalText = btnGuardar.html();
                btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');

                $.ajax({
                    url: 'productos_ajax.php',
                    type: 'POST',
                    data: {
                        accion: accionBackend,
                        producto_id: id,
                        producto_codigo: productoCodigo,
                        producto_nombre: productoNombre,
                        codigo_barras: $('#codigo_barras').val(),
                        producto_descripcion: $('#producto_descripcion').val(),
                        producto_categoria_id: productoCategoriaId,
                        producto_tipo_id: productoTipoId,
                        unidad_medida_id: $('#unidad_medida_id').val() || null,
                        lado: $('#lado').val(),
                        material: $('#material').val(),
                        color: $('#color').val(),
                        peso: $('#peso').val(),
                        dimensiones: $('#dimensiones').val(),
                        garantia: $('#garantia').val(),
                        empresa_idx: empresa_idx,
                        pagina_idx: pagina_idx
                    },
                    success: function (res) {
                        btnGuardar.prop('disabled', false).html(originalText);
                        
                        if (res.resultado) {
                            tabla.ajax.reload(function () {
                                Swal.fire({
                                    icon: "success",
                                    title: "¡Guardado!",
                                    text: "Producto guardado correctamente",
                                    showConfirmButton: false,
                                    timer: 1500,
                                    toast: true,
                                    position: 'top-end'
                                });

                                var modalEl = document.getElementById('modalProducto');
                                var modal = bootstrap.Modal.getInstance(modalEl);
                                modal.hide();
                            });
                        } else {
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

            // Validación del formulario de alta
            $('#btnConfirmarAlta').click(function () {
                var form = document.getElementById('formAltaProducto');
                if (!form.checkValidity()) {
                    form.classList.add('was-validated');
                    return false;
                }

                Swal.fire({
                    title: '¿Confirmar Alta?',
                    html: `¿Está seguro de dar de ALTA este producto?`,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#28a745',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Sí, dar de Alta',
                    cancelButtonText: 'Cancelar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        ejecutarAccion(productoActualId, 'alta', 'Producto');
                    }
                });
            });

            // Evento para mantener tamaño del modal al cambiar pestañas
            $(document).on('shown.bs.tab', function (e) {
                // Reajustar el modal si está visible
                var modal = $('#modalProducto');
                if (modal.hasClass('show')) {
                    // Forzar un redimensionamiento
                    modal.find('.modal-dialog').css('margin-top', '1.75rem');
                }
            });

            // ========== INICIALIZACIÓN ==========

            // Inicializar
            inicializarDataTable();
            cargarBotonAgregar();
            cargarTiposProducto();
            cargarCategoriasProducto();
            cargarUnidadesMedida();
            cargarMarcasFiltro();
            cargarSucursales(); // ← Agrega esta línea

            // Agregar tooltips
            $('[title]').tooltip({
                trigger: 'hover',
                placement: 'top'
            });
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

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>