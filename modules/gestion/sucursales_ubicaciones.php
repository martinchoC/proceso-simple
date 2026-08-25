<?php
// Configuración de la página
$pageTitle = "Gestión de Ubicaciones de Sucursales";
$currentPage = 'sucursales_ubicaciones';
$modudo_idx = 2;
$pagina_idx = 38; // ✅ ID de página para ubicaciones de sucursales

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-sitemap me-2"></i>Árbol de Ubicaciones de Sucursales
                    </h3>
                    <small class="text-muted">Sistema Declarativo Multiempresa - Vista Jerárquica</small>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Gestión</a></li>
                        <li class="breadcrumb-item"><a href="sucursales.php">Sucursales</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Árbol de Ubicaciones</li>
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
                            <div class="col-lg-4 col-xl-3">
                                <!-- Panel de filtros y estadísticas -->
                                <div class="card card-modern mb-4">
                                    <div class="card-header card-header-modern">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-filter me-2"></i>Filtros y Controles
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-4">
                                            <label for="filterSucursal" class="form-label">
                                                <i class="fas fa-store me-1"></i>Sucursal
                                            </label>
                                            <select class="form-select form-select-modern" id="filterSucursal">
                                                <option value="">Todas las sucursales</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="form-label">
                                                <i class="fas fa-expand-alt me-1"></i>Controles del Árbol
                                            </label>
                                            <div class="d-grid gap-2">
                                                <button type="button" class="btn btn-modern-primary" id="btnExpandAll">
                                                    <i class="fas fa-expand me-1"></i>Expandir Todo
                                                </button>
                                                <button type="button" class="btn btn-modern-secondary" id="btnCollapseAll">
                                                    <i class="fas fa-compress me-1"></i>Contraer Todo
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <div class="stats-container">
                                            <h6 class="mb-3">
                                                <i class="fas fa-chart-pie me-1"></i>Estadísticas
                                            </h6>
                                            <div class="stats-card">
                                                <div class="stats-item">
                                                    <i class="fas fa-store stats-icon"></i>
                                                    <div class="stats-content">
                                                        <div class="stats-number" id="totalSucursales">0</div>
                                                        <div class="stats-label">Sucursales</div>
                                                    </div>
                                                </div>
                                                <div class="stats-item">
                                                    <i class="fas fa-warehouse stats-icon"></i>
                                                    <div class="stats-content">
                                                        <div class="stats-number" id="totalDepositos">0</div>
                                                        <div class="stats-label">Depósitos</div>
                                                    </div>
                                                </div>
                                                <div class="stats-item">
                                                    <i class="fas fa-layer-group stats-icon"></i>
                                                    <div class="stats-content">
                                                        <div class="stats-number" id="totalSecciones">0</div>
                                                        <div class="stats-label">Secciones</div>
                                                    </div>
                                                </div>
                                                <div class="stats-item">
                                                    <i class="fas fa-th-large stats-icon"></i>
                                                    <div class="stats-content">
                                                        <div class="stats-number" id="totalEstanterias">0</div>
                                                        <div class="stats-label">Estanterías</div>
                                                    </div>
                                                </div>
                                                <div class="stats-item">
                                                    <i class="fas fa-shelves stats-icon"></i>
                                                    <div class="stats-content">
                                                        <div class="stats-number" id="totalEstantes">0</div>
                                                        <div class="stats-label">Estantes</div>
                                                    </div>
                                                </div>
                                                <div class="stats-item">
                                                    <i class="fas fa-cube stats-icon"></i>
                                                    <div class="stats-content">
                                                        <div class="stats-number" id="totalPosiciones">0</div>
                                                        <div class="stats-label">Posiciones</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="mt-4">
                                            <h6 class="mb-3">
                                                <i class="fas fa-key me-1"></i>Leyenda
                                            </h6>
                                            <div class="legend-item">
                                                <span class="legend-color legend-sucursal"></span>
                                                <span class="legend-text">Sucursal</span>
                                            </div>
                                            <div class="legend-item">
                                                <span class="legend-color legend-deposito"></span>
                                                <span class="legend-text">Depósito</span>
                                            </div>
                                            <div class="legend-item">
                                                <span class="legend-color legend-seccion"></span>
                                                <span class="legend-text">Sección</span>
                                            </div>
                                            <div class="legend-item">
                                                <span class="legend-color legend-estanteria"></span>
                                                <span class="legend-text">Estantería</span>
                                            </div>
                                            <div class="legend-item">
                                                <span class="legend-color legend-estante"></span>
                                                <span class="legend-text">Estante</span>
                                            </div>
                                            <div class="legend-item">
                                                <span class="legend-color legend-posicion"></span>
                                                <span class="legend-text">Posición</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card card-modern">
                                    <div class="card-header card-header-modern">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-bolt me-2"></i>Acciones Rápidas
                                        </h5>
                                    </div>
                                    <div class="card-body">
                                        <div id="contenedor-boton-agregar" class="d-grid mb-3"></div>
                                        
                                        <div class="btn-group w-100">
                                            <button type="button" class="btn btn-modern-secondary dropdown-toggle" 
                                                    id="btnExportar" data-bs-toggle="dropdown" aria-expanded="false">
                                                <i class="fas fa-download me-1"></i>Exportar
                                            </button>
                                            <ul class="dropdown-menu dropdown-menu-end w-100">
                                                <li><a class="dropdown-item btn-export-format" href="#" data-format="excel"><i class="fas fa-file-excel text-success me-2"></i>Excel (.xlsx)</a></li>
                                                <li><a class="dropdown-item btn-export-format" href="#" data-format="pdf"><i class="fas fa-file-pdf text-danger me-2"></i>PDF (.pdf)</a></li>
                                                <li><a class="dropdown-item btn-export-format" href="#" data-format="csv"><i class="fas fa-file-csv text-info me-2"></i>CSV (.csv)</a></li>
                                            </ul>
                                        </div>
                                        
                                        <button type="button" class="btn btn-modern-secondary w-100 mt-2" onclick="window.print();">
                                            <i class="fas fa-print me-1"></i>Imprimir
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-8 col-xl-9">
                                <div class="card card-modern">
                                    <div class="card-header card-header-modern">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h5 class="card-title mb-0">
                                                    <i class="fas fa-sitemap me-2"></i>Árbol Jerárquico de Ubicaciones
                                                </h5>
                                                <small class="text-muted">Explora las ubicaciones jerárquicamente</small>
                                            </div>
                                            <div class="search-container">
                                                <div class="input-group input-group-modern">
                                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                                    <input type="text" class="form-control" id="searchTree" placeholder="Buscar ubicación...">
                                                    <button class="btn btn-outline-secondary" type="button" id="btnClearSearch">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card-body">
                                        <div class="tree-container" id="treeContainer">
                                            <div class="tree-loading">
                                                <div class="spinner-border text-primary" role="status">
                                                    <span class="visually-hidden">Cargando árbol...</span>
                                                </div>
                                                <p class="mt-2">Cargando estructura jerárquica...</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card-footer">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <small class="text-muted">
                                                    <i class="fas fa-info-circle me-1"></i>
                                                    Haz clic en <i class="fas fa-chevron-right"></i> para expandir
                                                </small>
                                            </div>
                                            <div class="col-md-6 text-end">
                                                <small class="text-muted">
                                                    <i class="fas fa-sync-alt me-1"></i>Última actualización: 
                                                    <span id="lastUpdate"><?php echo date('d/m/Y H:i:s'); ?></span>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Modal -->
            <div class="modal fade modal-modern" id="modalSucursalUbicacion" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content">
                        <div class="modal-header modal-header-modern">
                            <div>
                                <h5 class="modal-title" id="modalLabel">
                                    <i class="fas fa-map-marker-alt me-2"></i>Ubicación de Sucursal
                                </h5>
                                <p class="modal-subtitle mb-0" id="modalSubtitle"></p>
                            </div>
                            <button type="button" class="btn-close btn-close-modern" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <div class="location-path mb-4">
                                <div class="path-header">
                                    <i class="fas fa-road me-2"></i>Ruta Completa
                                </div>
                                <div class="path-content">
                                    <div id="fullPath"></div>
                                </div>
                            </div>
                            
                            <form id="formSucursalUbicacion" class="needs-validation" novalidate>
                                <input type="hidden" id="sucursal_ubicacion_id" name="sucursal_ubicacion_id" />
                                <input type="hidden" id="parent_type" name="parent_type" />
                                <input type="hidden" id="parent_id" name="parent_id" />
                                
                                <div class="row">
                                    <div class="col-md-12 mb-3">
                                        <label for="sucursal_id" class="form-label"><i class="fas fa-store me-1"></i>Sucursal *</label>
                                        <div class="input-group input-group-modern">
                                            <span class="input-group-text"><i class="fas fa-building"></i></span>
                                            <select class="form-select" id="sucursal_id" name="sucursal_id" required>
                                                <option value="">Seleccionar sucursal...</option>
                                            </select>
                                        </div>
                                        <div class="invalid-feedback">Debe seleccionar una sucursal</div>
                                    </div>
                                    <div class="col-md-12 mb-3">
                                        <label for="deposito_id" class="form-label"><i class="fas fa-warehouse me-1"></i>Depósito *</label>
                                        <div class="input-group input-group-modern">
                                            <span class="input-group-text"><i class="fas fa-building"></i></span>
                                            <select class="form-select" id="deposito_id" name="deposito_id" required>
                                                <option value="">Primero seleccione una sucursal...</option>
                                            </select>
                                        </div>
                                        <div class="invalid-feedback">Debe seleccionar un depósito</div>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <label for="seccion" class="form-label"><i class="fas fa-layer-group me-1"></i>Sección *</label>
                                        <div class="input-group input-group-modern">
                                            <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                            <input type="text" class="form-control" id="seccion" name="seccion" maxlength="50" required placeholder="Ej: A">
                                        </div>
                                        <div class="invalid-feedback">La sección es obligatoria</div>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <label for="estanteria" class="form-label"><i class="fas fa-th-large me-1"></i>Estantería *</label>
                                        <div class="input-group input-group-modern">
                                            <span class="input-group-text"><i class="fas fa-th"></i></span>
                                            <input type="text" class="form-control" id="estanteria" name="estanteria" maxlength="50" required placeholder="Ej: 01">
                                        </div>
                                        <div class="invalid-feedback">La estantería es obligatoria</div>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <label for="estante" class="form-label"><i class="fas fa-shelves me-1"></i>Estante *</label>
                                        <div class="input-group input-group-modern">
                                            <span class="input-group-text"><i class="fas fa-box"></i></span>
                                            <input type="text" class="form-control" id="estante" name="estante" maxlength="50" required placeholder="Ej: 01">
                                        </div>
                                        <div class="invalid-feedback">El estante es obligatorio</div>
                                    </div>
                                    
                                    <div class="col-md-3 mb-3">
                                        <label for="posicion" class="form-label"><i class="fas fa-cube me-1"></i>Posición *</label>
                                        <div class="input-group input-group-modern">
                                            <span class="input-group-text"><i class="fas fa-cube"></i></span>
                                            <input type="text" class="form-control" id="posicion" name="posicion" maxlength="50" required placeholder="Ej: 1A">
                                        </div>
                                        <div class="invalid-feedback">La posición es obligatoria</div>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label for="descripcion" class="form-label"><i class="fas fa-align-left me-1"></i>Descripción</label>
                                        <div class="input-group input-group-modern">
                                            <span class="input-group-text"><i class="fas fa-comment"></i></span>
                                            <textarea class="form-control" id="descripcion" name="descripcion" maxlength="255" rows="2" placeholder="Descripción detallada..."></textarea>
                                        </div>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label for="estado_registro_id" class="form-label"><i class="fas fa-circle me-1"></i>Estado</label>
                                        <div class="input-group input-group-modern">
                                            <span class="input-group-text"><i class="fas fa-toggle-on"></i></span>
                                            <select class="form-select" id="estado_registro_id" name="estado_registro_id">
                                                <option value="">Seleccionar estado...</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer modal-footer-modern">
                            <button type="button" class="btn btn-modern-secondary" data-bs-dismiss="modal">
                                <i class="fas fa-times me-1"></i>Cancelar
                            </button>
                            <button type="button" class="btn btn-modern-primary" id="btnGuardar">
                                <i class="fas fa-save me-1"></i>Guardar Ubicación
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
    /* Estilos simplificados */
    .card-modern {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        overflow: hidden;
        height: 100%;
    }
    
    .card-header-modern {
        background: linear-gradient(135deg, #2c3e50 0%, #4a6491 100%);
        color: white;
        border-bottom: none;
        padding: 1rem 1.25rem;
    }
    
    .card-title {
        font-weight: 600;
        font-size: 1.1rem;
    }
    
    .btn-modern-primary {
        background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
        border: none;
        color: white;
        font-weight: 500;
        padding: 0.6rem 1.2rem;
        border-radius: 8px;
        transition: all 0.3s ease;
        width: 100%;
    }
    
    .btn-modern-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 12px rgba(52, 152, 219, 0.4);
    }
    
    .btn-modern-secondary {
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        color: #495057;
        font-weight: 500;
        padding: 0.6rem 1.2rem;
        border-radius: 8px;
        transition: all 0.3s ease;
        width: 100%;
    }
    
    .btn-modern-secondary:hover {
        background: #e9ecef;
        transform: translateY(-2px);
    }
    
    .stats-container {
        background: #f8f9fa;
        padding: 1rem;
        border-radius: 10px;
        margin-top: 1rem;
    }
    
    .stats-card {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }
    
    .stats-item {
        display: flex;
        align-items: center;
        background: white;
        padding: 0.5rem;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .stats-icon {
        font-size: 1rem;
        color: #3498db;
        margin-right: 0.5rem;
        background: rgba(52, 152, 219, 0.1);
        padding: 0.35rem;
        border-radius: 6px;
    }
    
    .stats-number {
        font-size: 1rem;
        font-weight: 600;
        color: #212529;
        line-height: 1;
    }
    
    .stats-label {
        font-size: 0.7rem;
        color: #6c757d;
    }
    
    .legend-item {
        display: flex;
        align-items: center;
        margin-bottom: 0.35rem;
    }
    
    .legend-color {
        width: 14px;
        height: 14px;
        border-radius: 4px;
        margin-right: 0.5rem;
    }
    .legend-deposito { background: linear-gradient(135deg, #16a085 0%, #27ae60 100%); }
    .legend-sucursal { background: linear-gradient(135deg, #2c3e50 0%, #4a6491 100%); }
    .legend-seccion { background: linear-gradient(135deg, #3498db 0%, #2ecc71 100%); }
    .legend-estanteria { background: linear-gradient(135deg, #f39c12 0%, #e74c3c 100%); }
    .legend-estante { background: linear-gradient(135deg, #9b59b6 0%, #34495e 100%); }
    .legend-posicion { background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%); }
    
    .legend-text {
        font-size: 0.85rem;
        color: #495057;
    }
    
    .tree-container {
        min-height: 500px;
        max-height: 650px;
        overflow-y: auto;
        padding: 0.75rem;
        background: #f8f9fa;
        border-radius: 8px;
    }
    
    .tree-loading {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 300px;
        color: #6c757d;
    }
    
    /* Árbol simplificado */
    .tree {
        list-style: none;
        padding-left: 0;
        margin: 0;
    }
    
    .tree-node {
        margin-bottom: 2px;
    }
    
    .tree-node-content {
        display: flex;
        align-items: center;
        padding: 0.35rem 0.6rem;
        background: white;
        border-radius: 6px;
        border: 1px solid #e9ecef;
        cursor: pointer;
        transition: all 0.2s ease;
        min-height: 38px;
    }
    
    .tree-node-content:hover {
        transform: translateX(3px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border-color: #3498db;
    }
    
    .tree-node-expander {
        width: 18px;
        height: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 0.35rem;
        border-radius: 4px;
        background: #f8f9fa;
        transition: all 0.2s ease;
        flex-shrink: 0;
        cursor: pointer;
    }
    
    .tree-node-expander i {
        font-size: 0.6rem;
        color: #495057;
        transition: transform 0.2s ease !important;
    }
    
    .tree-node-expanded .tree-node-expander i {
        transform: rotate(90deg) !important;
    }
    
    .tree-node-icon {
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 0.4rem;
        border-radius: 5px;
        color: white;
        font-size: 0.7rem;
        flex-shrink: 0;
    }
    
    .tree-node-sucursal .tree-node-icon { background: linear-gradient(135deg, #2c3e50 0%, #4a6491 100%); }
    .tree-node-deposito .tree-node-icon { background: linear-gradient(135deg, #16a085 0%, #27ae60 100%); }
    .tree-node-seccion .tree-node-icon { background: linear-gradient(135deg, #3498db 0%, #2ecc71 100%); }
    .tree-node-estanteria .tree-node-icon { background: linear-gradient(135deg, #f39c12 0%, #e74c3c 100%); }
    .tree-node-estante .tree-node-icon { background: linear-gradient(135deg, #9b59b6 0%, #34495e 100%); }
    .tree-node-posicion .tree-node-icon { background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%); }
    
    .tree-node-info {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-width: 0;
    }
    
    .tree-node-main {
        flex: 1;
        min-width: 0;
        margin-right: 0.5rem;
    }
    
    .tree-node-title {
        font-weight: 600;
        color: #212529;
        font-size: 0.9rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .tree-node-subtitle {
        font-size: 0.7rem;
        color: #6c757d;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .tree-node-details {
        display: flex;
        align-items: center;
        gap: 0.4rem;
        flex-shrink: 0;
    }
    
    .tree-node-detail {
        display: flex;
        align-items: center;
        font-size: 0.7rem;
        color: #6c757d;
    }
    
    .tree-node-detail i {
        font-size: 0.6rem;
        margin-right: 0.15rem;
        color: #adb5bd;
    }
    
    .tree-node-actions {
        display: flex;
        gap: 0.15rem;
        opacity: 0;
        transition: opacity 0.2s ease;
        flex-shrink: 0;
    }
    
    .tree-node-content:hover .tree-node-actions {
        opacity: 1;
    }
    
    .tree-node-action {
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 4px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        color: #495057;
        transition: all 0.2s ease;
        font-size: 0.65rem;
    }
    
    .tree-node-action:hover {
        background: #3498db;
        border-color: #3498db;
        color: white;
    }
    
    .tree-children {
        list-style: none;
        padding-left: 1.5rem;
        margin-top: 2px;
        border-left: 2px dashed #dee2e6;
        margin-left: 0.75rem;
    }
    
    .tree-node-empty {
        padding: 0.5rem 1rem;
        background: white;
        border-radius: 8px;
        border: 1px dashed #dee2e6;
        color: #6c757d;
        text-align: center;
    }
    
    .badge-compact {
        padding: 0.1rem 0.35rem;
        font-size: 0.6rem;
        font-weight: 500;
        border-radius: 4px;
    }
    
    .search-container {
        width: 250px;
    }
    
    .input-group-modern {
        border-radius: 8px;
        overflow: hidden;
    }
    
    .input-group-modern .input-group-text {
        background: #f8f9fa;
        border: 2px solid #e9ecef;
        border-right: none;
        color: #6c757d;
        padding: 0.3rem 0.6rem;
    }
    
    .input-group-modern .form-control {
        border: 2px solid #e9ecef;
        border-left: none;
        padding: 0.3rem 0.6rem;
        font-size: 0.85rem;
    }
    
    .input-group-modern .form-control:focus {
        border-color: #3498db;
        box-shadow: none;
    }
    
    .tree-node-highlight {
        background: rgba(255, 235, 59, 0.2);
        border-color: #ffc107;
    }
    
    .location-path {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 10px;
        padding: 1rem;
        margin-bottom: 1rem;
    }
    
    .path-header {
        display: flex;
        align-items: center;
        color: #495057;
        font-weight: 600;
        margin-bottom: 0.5rem;
        font-size: 0.85rem;
    }
    
    .path-content {
        background: white;
        padding: 0.5rem;
        border-radius: 8px;
        border: 2px solid #dee2e6;
        font-size: 0.8rem;
    }
    
    #fullPath {
        font-family: 'Courier New', monospace;
        color: #495057;
        line-height: 1.4;
    }
    
    .modal-modern .modal-content {
        border-radius: 12px;
        border: none;
        box-shadow: 0 10px 40px rgba(0,0,0,0.1);
    }
    
    .modal-header-modern {
        background: linear-gradient(135deg, #2c3e50 0%, #4a6491 100%);
        color: white;
        border-bottom: none;
        padding: 1rem 1.25rem;
    }
    
    .modal-header-modern .btn-close-modern {
        filter: brightness(0) invert(1);
        opacity: 0.8;
    }
    
    .modal-subtitle {
        color: rgba(255,255,255,0.8);
        font-size: 0.8rem;
    }
    
    @media (max-width: 768px) {
        .search-container { width: 100%; margin-top: 0.5rem; }
        .tree-container { max-height: 400px; }
        .stats-card { grid-template-columns: 1fr; }
        .tree-node-details { display: none; }
        .tree-node-actions { opacity: 1; }
    }
    </style>

    <script>
    $(document).ready(function(){
        const empresa_idx = 2;
        const pagina_idx = <?php echo $pagina_idx; ?>;
        
        let sucursalesData = [];
        let treeData = {};
        let estadisticas = { sucursales: 0, depositos: 0, secciones: 0, estanterias: 0, estantes: 0, posiciones: 0 };
        let depositosPorSucursal = {};
        let ubicacionesCache = null; // Cache para evitar recargas innecesarias
        
        // Cargar sucursales
        function cargarSucursales() {
            return new Promise((resolve, reject) => {
                $.get('sucursales_ubicaciones_ajax.php', {
                    accion: 'obtener_sucursales_activas',
                    empresa_idx: empresa_idx
                }, function(sucursales){
                    sucursalesData = sucursales;
                    
                    var filterSelect = $('#filterSucursal');
                    filterSelect.empty();
                    filterSelect.append('<option value="">Todas las sucursales</option>');
                    
                    $.each(sucursales, function(index, sucursal){
                        var text = sucursal.sucursal_nombre + (sucursal.localidad ? ' (' + sucursal.localidad + ')' : '');
                        filterSelect.append('<option value="' + sucursal.sucursal_id + '">' + text + '</option>');
                    });
                    
                    cargarSucursalesEnModal();
                    resolve(sucursales);
                }, 'json').fail(reject);
            });
        }
        
        function cargarSucursalesEnModal() {
            var modalSelect = $('#sucursal_id');
            modalSelect.empty();
            modalSelect.append('<option value="">Seleccionar sucursal...</option>');
            
            $.each(sucursalesData, function(index, sucursal){
                var text = sucursal.sucursal_nombre + (sucursal.localidad ? ' (' + sucursal.localidad + ')' : '');
                modalSelect.append('<option value="' + sucursal.sucursal_id + '">' + text + '</option>');
            });
        }
        
        function cargarDepositos(sucursalId, selectedId = null) {
            if (!sucursalId) {
                $('#deposito_id').html('<option value="">Primero seleccione una sucursal...</option>');
                return;
            }
            
            $.get('sucursales_ubicaciones_ajax.php', {
                accion: 'obtener_depositos_por_sucursal',
                sucursal_id: sucursalId
            }, function(depositos) {
                depositosPorSucursal[sucursalId] = depositos;
                
                var select = $('#deposito_id');
                select.empty();
                select.append('<option value="">Seleccionar depósito...</option>');
                
                $.each(depositos, function(index, deposito) {
                    var selected = (selectedId && deposito.deposito_id == selectedId) ? 'selected' : '';
                    var nombre = deposito.deposito_nombre + (deposito.es_principal ? ' (Principal)' : '');
                    select.append('<option value="' + deposito.deposito_id + '" ' + selected + '>' + nombre + '</option>');
                });
            }, 'json');
        }
        
        function cargarEstados(selectedId = null) {
            $.get('sucursales_ubicaciones_ajax.php', { accion: 'obtener_estados_registro' }, function(estados){
                var select = $('#estado_registro_id');
                select.empty();
                select.append('<option value="">Seleccionar estado...</option>');
                
                $.each(estados, function(index, estado){
                    var selected = (selectedId && estado.estado_registro_id == selectedId) ? 'selected' : '';
                    select.append('<option value="' + estado.estado_registro_id + '" ' + selected + '>' + estado.estado_registro + '</option>');
                });
            }, 'json');
        }
        
        // Función principal para cargar ubicaciones - Optimizada
        function cargarUbicaciones(sucursalId = '') {
            // Si hay cache y no hay filtro, usar cache
            if (ubicacionesCache && !sucursalId) {
                procesarDatosArbol(ubicacionesCache);
                renderizarArbol();
                actualizarEstadisticas();
                actualizarUltimaActualizacion();
                return;
            }
            
            $('#treeContainer').html(`
                <div class="tree-loading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <p class="mt-2">Cargando ubicaciones...</p>
                </div>
            `);
            
            $.get('sucursales_ubicaciones_ajax.php', {
                accion: 'listar',
                empresa_idx: empresa_idx,
                pagina_idx: pagina_idx,
                filter_sucursal: sucursalId
            }, function(ubicaciones){
                // Guardar cache
                ubicacionesCache = ubicaciones;
                procesarDatosArbol(ubicaciones);
                renderizarArbol();
                actualizarEstadisticas();
                actualizarUltimaActualizacion();
                
                setTimeout(function() {
                    inicializarEstadoArbol();
                }, 50);
            }, 'json');
        }
        
        // Procesar datos de forma más eficiente
        function procesarDatosArbol(ubicaciones) {
            treeData = {};
            estadisticas = { sucursales: 0, depositos: 0, secciones: 0, estanterias: 0, estantes: 0, posiciones: 0 };
            
            for (var i = 0; i < ubicaciones.length; i++) {
                var u = ubicaciones[i];
                var sucursalId = u.sucursal_id;
                var depositoId = u.deposito_id;
                var seccion = u.seccion;
                var estanteria = u.estanteria;
                var estante = u.estante;
                var posicion = u.posicion || '1A';
                
                if (!treeData[sucursalId]) {
                    treeData[sucursalId] = {
                        id: sucursalId,
                        type: 'sucursal',
                        nombre: u.sucursal_nombre,
                        localidad: u.localidad || '',
                        depositos: {}
                    };
                    estadisticas.sucursales++;
                }
                
                if (!treeData[sucursalId].depositos[depositoId]) {
                    treeData[sucursalId].depositos[depositoId] = {
                        id: depositoId,
                        type: 'deposito',
                        nombre: u.deposito_nombre,
                        parentSucursalId: sucursalId,
                        secciones: {}
                    };
                    estadisticas.depositos++;
                }
                
                if (!treeData[sucursalId].depositos[depositoId].secciones[seccion]) {
                    treeData[sucursalId].depositos[depositoId].secciones[seccion] = {
                        id: seccion,
                        type: 'seccion',
                        nombre: 'Sección ' + seccion,
                        parentSucursalId: sucursalId,
                        parentDepositoId: depositoId,
                        estanterias: {}
                    };
                    estadisticas.secciones++;
                }
                
                if (!treeData[sucursalId].depositos[depositoId].secciones[seccion].estanterias[estanteria]) {
                    treeData[sucursalId].depositos[depositoId].secciones[seccion].estanterias[estanteria] = {
                        id: estanteria,
                        type: 'estanteria',
                        nombre: 'Estantería ' + estanteria,
                        parentSucursalId: sucursalId,
                        parentDepositoId: depositoId,
                        parentSeccion: seccion,
                        estantes: {}
                    };
                    estadisticas.estanterias++;
                }
                
                if (!treeData[sucursalId].depositos[depositoId].secciones[seccion].estanterias[estanteria].estantes[estante]) {
                    treeData[sucursalId].depositos[depositoId].secciones[seccion].estanterias[estanteria].estantes[estante] = {
                        id: estante,
                        type: 'estante',
                        nombre: 'Estante ' + estante,
                        parentSucursalId: sucursalId,
                        parentDepositoId: depositoId,
                        parentSeccion: seccion,
                        parentEstanteria: estanteria,
                        posiciones: {}
                    };
                    estadisticas.estantes++;
                }
                
                var estanteObj = treeData[sucursalId].depositos[depositoId].secciones[seccion].estanterias[estanteria].estantes[estante];
                if (!estanteObj.posiciones[posicion]) {
                    estanteObj.posiciones[posicion] = {
                        id: u.sucursal_ubicacion_id,
                        type: 'posicion',
                        nombre: 'Posición ' + posicion,
                        descripcion: u.descripcion || '',
                        estado: u.estado_info,
                        botones: u.botones || [],
                        seccion: seccion,
                        estanteria: estanteria,
                        estante: estante,
                        posicion: posicion
                    };
                    estadisticas.posiciones++;
                }
            }
        }
        
        // Renderizar árbol de forma más eficiente
        function renderizarArbol() {
            var html = '<ul class="tree">';
            var keys = Object.keys(treeData);
            
            if (keys.length === 0) {
                html += '<div class="tree-node-empty"><i class="fas fa-inbox me-1"></i>No hay ubicaciones registradas.</div>';
            } else {
                for (var i = 0; i < keys.length; i++) {
                    html += renderizarNodo(treeData[keys[i]], true);
                }
            }
            
            html += '</ul>';
            $('#treeContainer').html(html);
        }
        
        function renderizarNodo(nodo, expandir = false) {
            var tipos = {
                'sucursal': { icon: 'fas fa-store', cls: 'tree-node-sucursal' },
                'deposito': { icon: 'fas fa-warehouse', cls: 'tree-node-deposito' },
                'seccion': { icon: 'fas fa-layer-group', cls: 'tree-node-seccion' },
                'estanteria': { icon: 'fas fa-th-large', cls: 'tree-node-estanteria' },
                'estante': { icon: 'fas fa-shelves', cls: 'tree-node-estante' },
                'posicion': { icon: 'fas fa-cube', cls: 'tree-node-posicion' }
            };
            
            var tipo = tipos[nodo.type] || tipos['sucursal'];
            var tieneHijos = nodo.type !== 'posicion';
            var html = '';
            
            var nodoId = nodo.id;
            if (nodo.type === 'deposito' && nodo.parentSucursalId) {
                nodoId = nodo.parentSucursalId + '_' + nodo.id;
            } else if (nodo.type === 'seccion' && nodo.parentSucursalId && nodo.parentDepositoId) {
                nodoId = nodo.parentSucursalId + '_' + nodo.parentDepositoId + '_' + nodo.id;
            } else if (nodo.type === 'estanteria' && nodo.parentSucursalId && nodo.parentDepositoId && nodo.parentSeccion) {
                nodoId = nodo.parentSucursalId + '_' + nodo.parentDepositoId + '_' + nodo.parentSeccion + '_' + nodo.id;
            } else if (nodo.type === 'estante' && nodo.parentSucursalId && nodo.parentDepositoId && nodo.parentSeccion && nodo.parentEstanteria) {
                nodoId = nodo.parentSucursalId + '_' + nodo.parentDepositoId + '_' + nodo.parentSeccion + '_' + nodo.parentEstanteria + '_' + nodo.id;
            }
            
            var expandClass = (expandir && tieneHijos) ? 'tree-node-expanded' : '';
            
            html += '<li class="tree-node ' + tipo.cls + ' ' + expandClass + '" data-id="' + nodoId + '" data-type="' + nodo.type + '">';
            html += '<div class="tree-node-content">';
            
            if (tieneHijos) {
                html += '<div class="tree-node-expander"><i class="fas fa-chevron-right"></i></div>';
            } else {
                html += '<div class="tree-node-expander" style="visibility:hidden;"></div>';
            }
            
            html += '<div class="tree-node-icon"><i class="' + tipo.icon + '"></i></div>';
            html += '<div class="tree-node-info">';
            html += '<div class="tree-node-main">';
            html += '<div class="tree-node-title">' + nodo.nombre + '</div>';
            
            if (nodo.type === 'sucursal' && nodo.localidad) {
                html += '<div class="tree-node-subtitle"><i class="fas fa-map-marker-alt fa-xs me-1"></i>' + nodo.localidad + '</div>';
            } else if (nodo.type === 'posicion' && nodo.descripcion) {
                html += '<div class="tree-node-subtitle">' + nodo.descripcion.substring(0, 30) + '</div>';
            }
            html += '</div>';
            
            html += '<div class="tree-node-details">';
            if (nodo.type === 'posicion') {
                html += '<span class="badge badge-compact bg-' + getEstadoColor(nodo.estado) + '">' + (nodo.estado?.estado_registro || 'Sin estado') + '</span>';
            }
            html += '</div>';
            html += '</div>';
            
            html += '<div class="tree-node-actions">';
            if (nodo.type === 'posicion') {
                html += '<button class="tree-node-action btn-editar" data-id="' + nodo.id + '" title="Editar"><i class="fas fa-edit"></i></button>';
            } else {
                html += '<button class="tree-node-action btn-agregar-hijo" data-id="' + nodoId + '" data-type="' + nodo.type + '" title="Agregar"><i class="fas fa-plus"></i></button>';
            }
            html += '</div>';
            html += '</div>';
            
            // Hijos - Solo para nodos que no son posiciones
            if (tieneHijos) {
                var childType = getChildType(nodo.type);
                var children = getChildren(nodo, childType);
                var hasChildren = false;
                var childHtml = '';
                
                for (var key in children) {
                    if (children.hasOwnProperty(key)) {
                        hasChildren = true;
                        // Solo expandir si es sucursal
                        var expandirHijo = (nodo.type === 'sucursal');
                        childHtml += renderizarNodo(children[key], expandirHijo);
                    }
                }
                
                if (hasChildren) {
                    var displayStyle = (nodo.type === 'sucursal') ? '' : ' style="display:none;"';
                    html += '<ul class="tree-children"' + displayStyle + '>' + childHtml + '</ul>';
                }
            }
            
            html += '</li>';
            return html;
        }
        
        function getChildType(parentType) {
            var map = { 'sucursal': 'deposito', 'deposito': 'seccion', 'seccion': 'estanteria', 'estanteria': 'estante', 'estante': 'posicion' };
            return map[parentType] || null;
        }
        
        function getChildren(nodo, childType) {
            var map = { 'deposito': 'depositos', 'seccion': 'secciones', 'estanteria': 'estanterias', 'estante': 'estantes', 'posicion': 'posiciones' };
            var key = map[childType];
            return (key && nodo[key]) ? nodo[key] : {};
        }
        
        function getEstadoColor(estadoInfo) {
            if (!estadoInfo) return 'secondary';
            var map = { 'ACTIVO': 'success', 'INACTIVO': 'secondary', 'BLOQUEADO': 'warning' };
            return map[estadoInfo.codigo_estandar] || 'secondary';
        }
        
        function actualizarEstadisticas() {
            $('#totalSucursales').text(estadisticas.sucursales);
            $('#totalDepositos').text(estadisticas.depositos);
            $('#totalSecciones').text(estadisticas.secciones);
            $('#totalEstanterias').text(estadisticas.estanterias);
            $('#totalEstantes').text(estadisticas.estantes);
            $('#totalPosiciones').text(estadisticas.posiciones);
        }
        
        function actualizarUltimaActualizacion() {
            var now = new Date();
            $('#lastUpdate').text(now.toLocaleDateString('es-ES') + ' ' + now.toLocaleTimeString('es-ES'));
        }
        
        function inicializarEstadoArbol() {
            $('.tree-node').each(function() {
                var $node = $(this);
                var $children = $node.find('.tree-children');
                var $expander = $node.find('.tree-node-expander');
                var $icon = $expander.find('i');
                
                if ($children.length > 0) {
                    if ($children.is(':visible')) {
                        $node.addClass('tree-node-expanded');
                        $icon.css('transform', 'rotate(90deg)');
                    } else {
                        $node.addClass('tree-node-collapsed');
                        $icon.css('transform', 'rotate(0deg)');
                    }
                }
            });
        }
        
        // EVENTOS
        
        $(document).on('click', '.tree-node-expander', function(e) {
            e.stopPropagation();
            var $expander = $(this);
            var $node = $expander.closest('.tree-node');
            var $children = $node.find('.tree-children');
            var $icon = $expander.find('i');
            
            if ($children.length > 0) {
                if ($children.is(':visible')) {
                    $children.slideUp(200);
                    $node.removeClass('tree-node-expanded').addClass('tree-node-collapsed');
                    $icon.css('transform', 'rotate(0deg)');
                } else {
                    $children.slideDown(200);
                    $node.removeClass('tree-node-collapsed').addClass('tree-node-expanded');
                    $icon.css('transform', 'rotate(90deg)');
                }
            }
        });
        
        $('#btnExpandAll').click(function() {
            $('.tree-children').slideDown(200);
            $('.tree-node').each(function() {
                var $node = $(this);
                var $icon = $node.find('.tree-node-expander i');
                $node.removeClass('tree-node-collapsed').addClass('tree-node-expanded');
                $icon.css('transform', 'rotate(90deg)');
            });
        });
        
        $('#btnCollapseAll').click(function() {
            $('.tree-children').slideUp(200);
            $('.tree-node').each(function() {
                var $node = $(this);
                var $icon = $node.find('.tree-node-expander i');
                $node.removeClass('tree-node-expanded').addClass('tree-node-collapsed');
                $icon.css('transform', 'rotate(0deg)');
            });
        });
        
        $(document).on('click', '.btn-agregar-hijo', function(e) {
            e.stopPropagation();
            var parentId = $(this).data('id');
            var parentType = $(this).data('type');
            
            resetModal();
            $('#parent_type').val(parentType);
            $('#parent_id').val(parentId);
            
            $.get('sucursales_ubicaciones_ajax.php', {
                accion: 'obtener_valores_por_defecto',
                parent_type: parentType,
                parent_id: parentId,
                empresa_idx: empresa_idx
            }, function(valores) {
                if (valores.sucursal_id) {
                    $('#sucursal_id').val(valores.sucursal_id);
                    cargarDepositos(valores.sucursal_id, valores.deposito_id);
                }
                if (valores.seccion) $('#seccion').val(valores.seccion);
                if (valores.estanteria) $('#estanteria').val(valores.estanteria);
                if (valores.estante) $('#estante').val(valores.estante);
                if (valores.posicion) $('#posicion').val(valores.posicion);
                
                $('#modalLabel').html('<i class="fas fa-map-marker-alt me-2"></i>Nueva Ubicación');
                $('#modalSubtitle').text('Agregar ' + (getChildType(parentType) || 'posición'));
                cargarEstados();
                actualizarRutaCompleta();
                
                var modal = new bootstrap.Modal(document.getElementById('modalSucursalUbicacion'));
                modal.show();
            }, 'json').fail(function() {
                $('#modalLabel').html('<i class="fas fa-map-marker-alt me-2"></i>Nueva Ubicación');
                $('#modalSubtitle').text('Agregar ubicación');
                cargarEstados();
                actualizarRutaCompleta();
                
                var modal = new bootstrap.Modal(document.getElementById('modalSucursalUbicacion'));
                modal.show();
            });
        });
        
        $(document).on('click', '.btn-editar', function(e) {
            e.stopPropagation();
            var id = $(this).data('id');
            
            $.get('sucursales_ubicaciones_ajax.php', {
                accion: 'obtener',
                sucursal_ubicacion_id: id,
                empresa_idx: empresa_idx
            }, function(res) {
                if (res && res.sucursal_ubicacion_id) {
                    resetModal();
                    $('#sucursal_ubicacion_id').val(res.sucursal_ubicacion_id);
                    $('#sucursal_id').val(res.sucursal_id);
                    cargarDepositos(res.sucursal_id, res.deposito_id);
                    $('#seccion').val(res.seccion || '');
                    $('#estanteria').val(res.estanteria || '');
                    $('#estante').val(res.estante || '');
                    $('#posicion').val(res.posicion || '');
                    $('#descripcion').val(res.descripcion || '');
                    
                    cargarEstados(res.tabla_estado_registro_id);
                    actualizarRutaCompleta();
                    
                    $('#modalLabel').html('<i class="fas fa-map-marker-alt me-2"></i>Editar Ubicación');
                    $('#modalSubtitle').text('Modificar ubicación existente');
                    
                    var modal = new bootstrap.Modal(document.getElementById('modalSucursalUbicacion'));
                    modal.show();
                }
            }, 'json');
        });
        
        $(document).on('click', '#btnNuevo', function() {
            resetModal();
            $('#modalLabel').html('<i class="fas fa-map-marker-alt me-2"></i>Nueva Ubicación');
            $('#modalSubtitle').text('Crear una nueva ubicación');
            cargarEstados();
            actualizarRutaCompleta();
            
            var modal = new bootstrap.Modal(document.getElementById('modalSucursalUbicacion'));
            modal.show();
            $('#seccion').focus();
        });
        
        $(document).on('change', '#filterSucursal', function() {
            cargarUbicaciones($(this).val());
        });
        
        $(document).on('change', '#sucursal_id', function() {
            cargarDepositos($(this).val());
        });
        
        function resetModal() {
            $('#formSucursalUbicacion')[0].reset();
            $('#sucursal_ubicacion_id').val('');
            $('#parent_type').val('');
            $('#parent_id').val('');
            $('#formSucursalUbicacion').removeClass('was-validated');
            $('#fullPath').html('<span class="text-muted">Seleccione los datos para ver la ruta completa</span>');
        }
        
        function actualizarRutaCompleta() {
            var sucursalId = $('#sucursal_id').val();
            var depositoId = $('#deposito_id').val();
            var seccion = $('#seccion').val();
            var estanteria = $('#estanteria').val();
            var estante = $('#estante').val();
            var posicion = $('#posicion').val();
            
            var ruta = '';
            
            if (sucursalId) {
                var sucursal = sucursalesData.find(s => s.sucursal_id == sucursalId);
                if (sucursal) {
                    ruta += '<strong>' + sucursal.sucursal_nombre + '</strong>';
                    if (sucursal.localidad) ruta += ' <span class="text-muted">(' + sucursal.localidad + ')</span>';
                }
            }
            
            if (depositoId && depositosPorSucursal[sucursalId]) {
                var deposito = depositosPorSucursal[sucursalId].find(d => d.deposito_id == depositoId);
                if (deposito) {
                    ruta += ' &nbsp;&nbsp;<i class="fas fa-arrow-right text-muted"></i>&nbsp;&nbsp; <strong>' + deposito.deposito_nombre + '</strong>';
                }
            }
            
            if (seccion) ruta += ' &nbsp;&nbsp;<i class="fas fa-arrow-right text-muted"></i>&nbsp;&nbsp; <strong>Sección ' + seccion + '</strong>';
            if (estanteria) ruta += ' &nbsp;&nbsp;<i class="fas fa-arrow-right text-muted"></i>&nbsp;&nbsp; <strong>Estantería ' + estanteria + '</strong>';
            if (estante) ruta += ' &nbsp;&nbsp;<i class="fas fa-arrow-right text-muted"></i>&nbsp;&nbsp; <strong>Estante ' + estante + '</strong>';
            if (posicion) ruta += ' &nbsp;&nbsp;<i class="fas fa-arrow-right text-muted"></i>&nbsp;&nbsp; <strong>Posición ' + posicion + '</strong>';
            
            $('#fullPath').html(ruta || '<span class="text-muted">Seleccione los datos para ver la ruta completa</span>');
        }
        
        // Búsqueda
        $('#searchTree').on('input', function() {
            var term = $(this).val().toLowerCase().trim();
            
            if (term.length > 0) {
                $('.tree-node').hide();
                $('.tree-node').each(function() {
                    var $node = $(this);
                    if ($node.text().toLowerCase().includes(term)) {
                        $node.addClass('tree-node-highlight').show();
                        $node.parentsUntil('.tree', '.tree-node').show();
                        $node.parentsUntil('.tree', '.tree-node').each(function() {
                            var $parent = $(this);
                            $parent.addClass('tree-node-expanded');
                            $parent.find('.tree-children').show();
                            $parent.find('.tree-node-expander i').css('transform', 'rotate(90deg)');
                        });
                    }
                });
            } else {
                $('.tree-node').show().removeClass('tree-node-highlight');
                $('.tree-children').hide();
                $('.tree-node-sucursal .tree-children').show();
                $('.tree-node-sucursal').addClass('tree-node-expanded');
                $('.tree-node-sucursal .tree-node-expander i').css('transform', 'rotate(90deg)');
            }
        });
        
        $('#btnClearSearch').click(function() {
            $('#searchTree').val('').trigger('input');
        });
        
        // Guardar
        $('#btnGuardar').click(function() {
            var form = document.getElementById('formSucursalUbicacion');
            
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return false;
            }
            
            var id = $('#sucursal_ubicacion_id').val();
            var accionBackend = id ? 'editar' : 'agregar';
            
            var btn = $(this);
            var originalText = btn.html();
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');
            
            $.ajax({
                url: 'sucursales_ubicaciones_ajax.php',
                type: 'POST',
                data: {
                    accion: accionBackend,
                    sucursal_ubicacion_id: id,
                    sucursal_id: $('#sucursal_id').val(),
                    deposito_id: $('#deposito_id').val(),
                    seccion: $('#seccion').val().trim(),
                    estanteria: $('#estanteria').val().trim(),
                    estante: $('#estante').val().trim(),
                    posicion: $('#posicion').val().trim(),
                    descripcion: $('#descripcion').val().trim(),
                    estado_registro_id: $('#estado_registro_id').val() || 1,
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                },
                success: function(res) {
                    btn.prop('disabled', false).html(originalText);
                    
                    if (res.resultado) {
                        var modalEl = document.getElementById('modalSucursalUbicacion');
                        var modal = bootstrap.Modal.getInstance(modalEl);
                        modal.hide();
                        
                        // Limpiar cache para recargar
                        ubicacionesCache = null;
                        cargarUbicaciones($('#filterSucursal').val());
                        
                        Swal.fire({
                            icon: "success",
                            title: "¡Guardado!",
                            text: "Ubicación guardada correctamente",
                            showConfirmButton: false,
                            timer: 1200,
                            toast: true,
                            position: 'top-end'
                        });
                    } else {
                        Swal.fire({ icon: "error", title: "Error", text: res.error || "Error al guardar", confirmButtonText: "Entendido" });
                    }
                },
                error: function() {
                    btn.prop('disabled', false).html(originalText);
                    Swal.fire({ icon: "error", title: "Error de conexión", text: "Error al comunicarse con el servidor", confirmButtonText: "Entendido" });
                }
            });
        });
        
        // Cargar botón agregar
        function cargarBotonAgregar() {
            $.get('sucursales_ubicaciones_ajax.php', {
                accion: 'obtener_boton_agregar',
                pagina_idx: pagina_idx
            }, function(boton) {
                if (boton && boton.nombre_funcion) {
                    var icono = boton.icono_clase ? '<i class="' + boton.icono_clase + ' me-1"></i>' : '';
                    $('#contenedor-boton-agregar').html(
                        '<button type="button" class="btn btn-modern-primary" id="btnNuevo">' +
                        icono + boton.nombre_funcion + '</button>'
                    );
                } else {
                    $('#contenedor-boton-agregar').html(
                        '<button type="button" class="btn btn-modern-primary" id="btnNuevo">' +
                        '<i class="fas fa-plus me-1"></i>Nueva Ubicación</button>'
                    );
                }
            }, 'json').fail(function() {
                $('#contenedor-boton-agregar').html(
                    '<button type="button" class="btn btn-modern-primary" id="btnNuevo">' +
                    '<i class="fas fa-plus me-1"></i>Nueva Ubicación</button>'
                );
            });
        }
        
        // Inicializar
        cargarSucursales().then(function() {
            cargarUbicaciones();
            cargarEstados();
            cargarBotonAgregar();
        });
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>