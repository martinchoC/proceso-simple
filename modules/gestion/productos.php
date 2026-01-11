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
                                                    <th width="80">Unidad</th>
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

            <!-- Modal para crear/editar producto - MÁS COMPACTO -->
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
                                                <input type="number" class="form-control form-control-sm" id="producto_categoria_id"
                                                    name="producto_categoria_id" min="1" required>
                                                <div class="invalid-feedback">La categoría es obligatoria</div>
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

        /* Asegurar que ambas pestañas tengan el mismo tamaño */
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

            // ========== FUNCIONES DE CARGA DE DATOS ==========

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
                            data: 'unidad_medida_info',
                            width: '80px',
                            className: 'text-center',
                            render: function (data, type, row) {
                                return type === 'export' ? (data ? data.unidad_abreviatura : '') : 
                                       (data ? `<span class="badge bg-secondary">${data.unidad_abreviatura}</span>` : '-');
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
                                        
                                        if (nombreAccion === 'editar') {
                                            claseBoton += 'btn-outline-primary';
                                            botones += `<button type="button" class="btn ${claseBoton} btn-accion" 
                                                   title="${boton.descripcion || 'Editar'}" 
                                                   data-id="${row.producto_id}" 
                                                   data-accion="${boton.accion_js}"
                                                   data-confirmable="${boton.es_confirmable || 0}"
                                                   data-producto="${row.producto_nombre}">
                                                <i class="${boton.icono_clase || 'fas fa-edit'}"></i>
                                            </button>`;
                                        }
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
                cargarUnidadesMedida();
                
                var modal = new bootstrap.Modal(document.getElementById('modalProducto'));
                modal.show();
                $('#producto_codigo').focus();
            });

            // Manejador para botones de acción dinámicos
            $(document).on('click', '.btn-accion', function () {
                var productoId = $(this).data('id');
                var accionJs = $(this).data('accion');
                var confirmable = $(this).data('confirmable');
                var producto = $(this).data('producto');

                if (accionJs === 'editar') {
                    cargarProductoParaEditar(productoId);
                } else if (accionJs === 'alta' || accionJs === 'activar') {
                    mostrarModalAlta(productoId, producto);
                } else if (confirmable == 1) {
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
                            ejecutarAccion(productoId, accionJs, producto);
                        }
                    });
                } else {
                    ejecutarAccion(productoId, accionJs, producto);
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

            // Función para ejecutar cualquier acción del backend
            function ejecutarAccion(productoId, accionJs, producto) {
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
                        $('#producto_categoria_id').val(res.producto_categoria_id);
                        $('#lado').val(res.lado || '');
                        $('#material').val(res.material || '');
                        $('#color').val(res.color || '');
                        $('#peso').val(res.peso || '');
                        $('#dimensiones').val(res.dimensiones || '');
                        $('#garantia').val(res.guarantia || '');
                        
                        cargarTiposProducto();
                        cargarUnidadesMedida();
                        
                        setTimeout(function() {
                            $('#producto_tipo_id').val(res.producto_tipo_id);
                            $('#unidad_medida_id').val(res.unidad_medida_id || '');
                        }, 300);
                        
                        $('#modalLabel').text('Editar Producto');
                        
                        // Cargar compatibilidad
                        cargarCompatibilidad(productoId);
                        
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
                $('#unidad_medida_id').empty().append('<option value="">Seleccionar unidad...</option>');
                
                // Limpiar tabla de compatibilidad
                if ($.fn.DataTable.isDataTable('#tablaCompatibilidad')) {
                    $('#tablaCompatibilidad').DataTable().destroy();
                    $('#tablaCompatibilidad tbody').empty();
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
            cargarUnidadesMedida();
            cargarMarcasFiltro();

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
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>