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
                                                    <span class="input-group-text">Tipo</span>
                                                    <select class="form-select" id="filtroTipo">
                                                        <option value="">Todos los tipos</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">Estado</span>
                                                    <select class="form-select" id="filtroEstado">
                                                        <option value="">Todos los estados</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text">Código</span>
                                                    <input type="text" class="form-control" id="filtroCodigo" placeholder="Buscar por código">
                                                </div>
                                            </div>
                                            <div class="col-md-3">
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
                                                    <th width="100">Tipo</th>
                                                    <th width="120">Categoría</th>
                                                    <th width="100">Unidad</th>
                                                    <th width="120">Estado</th>
                                                    <th width="250" class="text-center">Acciones</th>
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

            <!-- Modal para crear/editar producto - MÁS ANCHO CON SOLAPAS -->
            <div class="modal fade" id="modalProducto" tabindex="-1" aria-labelledby="modalLabel"
                aria-hidden="true" data-bs-backdrop="static">
                <div class="modal-dialog modal-xxl modal-dialog-centered modal-dialog-scrollable">
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

                            <div class="tab-content p-4" id="nav-tabContent">
                                <!-- Pestaña de Información -->
                                <div class="tab-pane fade show active" id="nav-info" role="tabpanel" 
                                     aria-labelledby="nav-info-tab">
                                    <form id="formProducto" class="needs-validation" novalidate>
                                        <input type="hidden" id="producto_id" name="producto_id" />
                                        <input type="hidden" id="empresa_id" name="empresa_id" value="2" />
                                        
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="producto_codigo" class="form-label">Código *</label>
                                                <input type="text" class="form-control" id="producto_codigo"
                                                    name="producto_codigo" maxlength="50" required>
                                                <div class="invalid-feedback">El código es obligatorio</div>
                                                <div class="form-text">Máximo 50 caracteres</div>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="codigo_barras" class="form-label">Código de Barras</label>
                                                <input type="text" class="form-control" id="codigo_barras"
                                                    name="codigo_barras" maxlength="150">
                                                <div class="form-text">Máximo 150 caracteres</div>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="producto_tipo_id" class="form-label">Tipo de Producto *</label>
                                                <select class="form-select" id="producto_tipo_id" name="producto_tipo_id" required>
                                                    <option value="">Seleccionar tipo...</option>
                                                </select>
                                                <div class="invalid-feedback">Seleccione un tipo de producto</div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-12 mb-3">
                                                <label for="producto_nombre" class="form-label">Nombre *</label>
                                                <input type="text" class="form-control" id="producto_nombre"
                                                    name="producto_nombre" maxlength="150" required>
                                                <div class="invalid-feedback">El nombre es obligatorio</div>
                                                <div class="form-text">Máximo 150 caracteres</div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="producto_categoria_id" class="form-label">Categoría *</label>
                                                <input type="number" class="form-control" id="producto_categoria_id"
                                                    name="producto_categoria_id" min="1" required>
                                                <div class="invalid-feedback">La categoría es obligatoria</div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="unidad_medida_id" class="form-label">Unidad de Medida</label>
                                                <select class="form-select" id="unidad_medida_id" name="unidad_medida_id">
                                                    <option value="">Seleccionar unidad...</option>
                                                </select>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-4 mb-3">
                                                <label for="lado" class="form-label">Lado</label>
                                                <input type="text" class="form-control" id="lado"
                                                    name="lado" maxlength="10">
                                                <div class="form-text">Máximo 10 caracteres</div>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="material" class="form-label">Material</label>
                                                <input type="text" class="form-control" id="material"
                                                    name="material" maxlength="50">
                                                <div class="form-text">Máximo 50 caracteres</div>
                                            </div>
                                            <div class="col-md-4 mb-3">
                                                <label for="color" class="form-label">Color</label>
                                                <input type="text" class="form-control" id="color"
                                                    name="color" maxlength="50">
                                                <div class="form-text">Máximo 50 caracteres</div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="peso" class="form-label">Peso (kg)</label>
                                                <input type="number" class="form-control" id="peso"
                                                    name="peso" min="0" step="0.01">
                                                <div class="form-text">Peso en kilogramos</div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label for="dimensiones" class="form-label">Dimensiones</label>
                                                <input type="text" class="form-control" id="dimensiones"
                                                    name="dimensiones" maxlength="50">
                                                <div class="form-text">Ej: 10x20x30 cm</div>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-12 mb-3">
                                                <label for="producto_descripcion" class="form-label">Descripción</label>
                                                <textarea class="form-control" id="producto_descripcion"
                                                    name="producto_descripcion" rows="3"></textarea>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-12 mb-3">
                                                <label for="garantia" class="form-label">Garantía</label>
                                                <input type="text" class="form-control" id="garantia"
                                                    name="garantia" maxlength="50">
                                                <div class="form-text">Ej: 1 año, 6 meses, etc.</div>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <!-- Pestaña de Compatibilidad -->
                                <div class="tab-pane fade" id="nav-compatibilidad" role="tabpanel" 
                                     aria-labelledby="nav-compatibilidad-tab">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <h5 class="mb-0">
                                            <i class="fas fa-cogs me-2 text-primary"></i>Compatibilidad del Producto
                                        </h5>
                                        <button type="button" class="btn btn-primary btn-sm" id="btnAgregarCompatibilidad">
                                            <i class="fas fa-plus me-1"></i>Agregar Compatibilidad
                                        </button>
                                    </div>

                                    <!-- Tabla de compatibilidad -->
                                    <div class="table-responsive">
                                        <table id="tablaCompatibilidad" class="table table-hover table-sm" style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="100">Marca</th>
                                                    <th width="120">Modelo</th>
                                                    <th width="120">Submodelo</th>
                                                    <th width="100">Año Desde</th>
                                                    <th width="100">Año Hasta</th>
                                                    <th width="80">Estado</th>
                                                    <th width="120" class="text-center">Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <!-- Los datos se cargarán dinámicamente -->
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="alert alert-info mt-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Esta sección permite definir con qué vehículos es compatible este producto.
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer bg-light border-top">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Cancelar
                            </button>
                            <button type="button" class="btn btn-primary px-4" id="btnGuardar">
                                <i class="fas fa-save me-1"></i>Guardar Producto
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
                                        <label for="marca_id" class="form-label">Marca *</label>
                                        <select class="form-select" id="marca_id" name="marca_id" required>
                                            <option value="">Seleccionar marca...</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione una marca</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label for="modelo_id" class="form-label">Modelo *</label>
                                        <select class="form-select" id="modelo_id" name="modelo_id" required disabled>
                                            <option value="">Seleccionar modelo...</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione un modelo</div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label for="submodelo_id" class="form-label">Submodelo</label>
                                        <select class="form-select" id="submodelo_id" name="submodelo_id" disabled>
                                            <option value="">Seleccionar submodelo...</option>
                                            <option value="0">Sin submodelo</option>
                                        </select>
                                        <div class="form-text">Opcional</div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="anio_desde" class="form-label">Año Desde *</label>
                                        <select class="form-select" id="anio_desde" name="anio_desde" required>
                                            <option value="">Año...</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione el año inicial</div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label for="anio_hasta" class="form-label">Año Hasta</label>
                                        <select class="form-select" id="anio_hasta" name="anio_hasta">
                                            <option value="">Año...</option>
                                            <option value="0">Actual</option>
                                        </select>
                                        <div class="form-text">Dejar vacío para actual</div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer bg-light border-top">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Cancelar
                            </button>
                            <button type="button" class="btn btn-info px-4" id="btnGuardarCompatibilidad">
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
        /* Modal más ancho */
        .modal-xxl {
            max-width: 1400px;
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
            min-height: 400px;
        }

        /* Tabla de compatibilidad */
        #tablaCompatibilidad {
            font-size: 0.9rem;
        }

        #tablaCompatibilidad th {
            font-weight: 600;
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
                    var filtroSelect = $('#filtroTipo');
                    
                    select.empty().append('<option value="">Seleccionar tipo...</option>');
                    filtroSelect.empty().append('<option value="">Todos los tipos</option>');
                    
                    if (tipos && tipos.length > 0) {
                        tipos.forEach(function(tipo) {
                            select.append(`<option value="${tipo.producto_tipo_id}">${tipo.producto_tipo} (${tipo.producto_tipo_codigo})</option>`);
                            filtroSelect.append(`<option value="${tipo.producto_tipo_id}">${tipo.producto_tipo}</option>`);
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

            // Cargar opciones de estados
            function cargarEstados() {
                $.get('productos_ajax.php', {
                    accion: 'obtener_estados',
                    empresa_idx: empresa_idx
                }, function (estados) {
                    var filtroSelect = $('#filtroEstado');
                    filtroSelect.empty().append('<option value="">Todos los estados</option>');
                    
                    if (estados && estados.length > 0) {
                        estados.forEach(function(estado) {
                            filtroSelect.append(`<option value="${estado.estado_registro_id}">${estado.estado_registro}</option>`);
                        });
                    }
                }, 'json');
            }

            // Cargar marcas
            function cargarMarcas() {
                $.get('productos_ajax.php', {
                    accion: 'obtener_marcas',
                    empresa_idx: empresa_idx
                }, function (marcas) {
                    var select = $('#marca_id');
                    select.empty().append('<option value="">Seleccionar marca...</option>');
                    
                    if (marcas && marcas.length > 0) {
                        marcas.forEach(function(marca) {
                            select.append(`<option value="${marca.marca_id}">${marca.marca_nombre}</option>`);
                        });
                    }
                }, 'json');
            }

            // Cargar modelos por marca
            function cargarModelos(marcaId) {
                $.get('productos_ajax.php', {
                    accion: 'obtener_modelos',
                    empresa_idx: empresa_idx,
                    marca_id: marcaId
                }, function (modelos) {
                    var select = $('#modelo_id');
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
            function cargarSubmodelos(modeloId) {
                $.get('productos_ajax.php', {
                    accion: 'obtener_submodelos',
                    empresa_idx: empresa_idx,
                    modelo_id: modeloId
                }, function (submodelos) {
                    var select = $('#submodelo_id');
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
                            d.filtro_tipo = $('#filtroTipo').val();
                            d.filtro_estado = $('#filtroEstado').val();
                            d.filtro_codigo = $('#filtroCodigo').val();
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
                        { data: 'producto_id', className: 'text-center fw-bold' },
                        { 
                            data: 'producto_codigo', 
                            className: 'text-center fw-medium',
                            render: function (data, type, row) {
                                return type === 'export' ? data : `<div class="fw-bold">${data}</div>`;
                            }
                        },
                        { 
                            data: 'producto_nombre',
                            render: function (data, type, row) {
                                if (type === 'export') return data;
                                var desc = row.producto_descripcion ? 
                                    `<small class="text-muted d-block">${row.producto_descripcion.substring(0, 50)}${row.producto_descripcion.length > 50 ? '...' : ''}</small>` : '';
                                return `<div class="fw-medium">${data}</div>${desc}`;
                            }
                        },
                        { 
                            data: 'producto_tipo_info',
                            render: function (data, type, row) {
                                return type === 'export' ? (data ? data.producto_tipo_codigo : '') : 
                                       (data ? `<span class="badge bg-info">${data.producto_tipo_codigo}</span>` : '');
                            }
                        },
                        { 
                            data: 'producto_categoria_id', 
                            className: 'text-center',
                            render: function (data, type, row) {
                                return type === 'export' ? data : `<span class="badge bg-secondary">${data}</span>`;
                            }
                        },
                        { 
                            data: 'unidad_medida_info',
                            render: function (data, type, row) {
                                return type === 'export' ? (data ? data.unidad_abreviatura : '') : 
                                       (data ? data.unidad_abreviatura : '-');
                            }
                        },
                        { 
                            data: 'estado_info', 
                            className: 'text-center',
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
                            width: '250px',
                            render: function (data, type, row) {
                                if (type === 'export') return '';

                                var botones = '';
                                if (data && data.length > 0) {
                                    var editarBoton = '';
                                    var botonAlta = '';
                                    var otrosBotones = '';

                                    data.forEach(boton => {
                                        var claseBoton = 'btn-sm me-1 ';
                                        var nombreAccion = boton.accion_js || boton.nombre_funcion.toLowerCase();
                                        
                                        if (nombreAccion === 'alta' || nombreAccion === 'activar') {
                                            claseBoton += 'btn-accion-alta';
                                        } else if (nombreAccion === 'baja' || nombreAccion === 'eliminar') {
                                            claseBoton += 'btn-accion-baja';
                                        } else if (nombreAccion === 'suspender' || nombreAccion === 'bloquear') {
                                            claseBoton += 'btn-accion-suspender';
                                        } else if (boton.bg_clase && boton.text_clase) {
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

                                        var botonHtml = `<button type="button" class="btn ${claseBoton} btn-accion" 
                                           title="${titulo}" 
                                           data-id="${row.producto_id}" 
                                           data-accion="${accionJs}"
                                           data-confirmable="${esConfirmable}"
                                           data-producto="${row.producto_nombre}">
                                        ${icono}
                                    </button>`;

                                        if (accionJs === 'editar') {
                                            editarBoton = botonHtml;
                                        } else if (accionJs === 'alta' || accionJs === 'activar') {
                                            botonAlta = botonHtml;
                                        } else {
                                            otrosBotones += botonHtml;
                                        }
                                    });

                                    botones = editarBoton + botonAlta + otrosBotones;
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
                            data: 'anio_desde',
                            className: 'text-center'
                        },
                        { 
                            data: 'anio_hasta',
                            className: 'text-center',
                            render: function(data) {
                                return data == '0' ? 'Actual' : data;
                            }
                        },
                        { 
                            data: 'estado_info',
                            className: 'text-center',
                            render: function(data) {
                                if (!data || !data.estado_registro) {
                                    return '<span class="badge badge-estado-inactivo">Sin estado</span>';
                                }
                                var estado = data.estado_registro;
                                var codigo = data.codigo_estandar || 'DESCONOCIDO';
                                var claseBadge = codigo === 'ACTIVO' ? 'badge-estado-activo' : 'badge-estado-inactivo';
                                return `<span class="badge ${claseBadge}">${estado}</span>`;
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
                    pageLength: 10
                });
            }

            // Mostrar modal de compatibilidad
            function mostrarModalCompatibilidad(productoId, compatibilidadId = null) {
                resetModalCompatibilidad();
                $('#compatibilidad_producto_id').val(productoId);
                
                if (compatibilidadId) {
                    // Modo edición
                    $('#modalCompatibilidadLabel').html('<i class="fas fa-edit me-2"></i>Editar Compatibilidad');
                    $('#compatibilidad_id').val(compatibilidadId);
                    
                    // Cargar datos de la compatibilidad
                    $.get('productos_ajax.php', {
                        accion: 'obtener_compatibilidad_por_id',
                        compatibilidad_id: compatibilidadId,
                        empresa_idx: empresa_idx
                    }, function(res) {
                        if (res && res.compatibilidad_id) {
                            // Cargar marcas primero
                            cargarMarcas();
                            
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
                } else {
                    // Modo creación
                    $('#modalCompatibilidadLabel').html('<i class="fas fa-plus me-2"></i>Agregar Compatibilidad');
                    cargarMarcas();
                }
                
                cargarAnios();
                
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
                    $('#filtroTipo').val('');
                    $('#filtroEstado').val('');
                    $('#filtroCodigo').val('');
                    tabla.ajax.reload();
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

                // Cambio de marca -> cargar modelos
                $(document).on('change', '#marca_id', function() {
                    var marcaId = $(this).val();
                    if (marcaId) {
                        cargarModelos(marcaId);
                    } else {
                        $('#modelo_id').empty().append('<option value="">Seleccionar modelo...</option>').prop('disabled', true);
                        $('#submodelo_id').empty().append('<option value="">Seleccionar submodelo...</option>').prop('disabled', true);
                    }
                });

                // Cambio de modelo -> cargar submodelos
                $(document).on('change', '#modelo_id', function() {
                    var modeloId = $(this).val();
                    if (modeloId) {
                        cargarSubmodelos(modeloId);
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
                        $('#garantia').val(res.garantia || '');
                        
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

            // ========== INICIALIZACIÓN ==========

            // Inicializar
            inicializarDataTable();
            cargarBotonAgregar();
            cargarTiposProducto();
            cargarUnidadesMedida();
            cargarEstados();

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