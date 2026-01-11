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
                                        <!-- Selector de sucursal -->
                                        <div class="mb-4">
                                            <label for="filterSucursal" class="form-label">
                                                <i class="fas fa-store me-1"></i>Sucursal
                                            </label>
                                            <select class="form-select form-select-modern" id="filterSucursal">
                                                <option value="">Todas las sucursales</option>
                                            </select>
                                        </div>
                                        
                                        <!-- Controles del árbol -->
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
                                        
                                        <!-- Estadísticas -->
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
                                                    <i class="fas fa-cube stats-icon"></i>
                                                    <div class="stats-content">
                                                        <div class="stats-number" id="totalEstantes">0</div>
                                                        <div class="stats-label">Estantes</div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Leyenda -->
                                        <div class="mt-4">
                                            <h6 class="mb-3">
                                                <i class="fas fa-key me-1"></i>Leyenda
                                            </h6>
                                            <div class="legend-item">
                                                <span class="legend-color legend-sucursal"></span>
                                                <span class="legend-text">Sucursal</span>
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
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Acciones rápidas -->
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
                                                <li>
                                                    <a class="dropdown-item btn-export-format" href="#" data-format="excel">
                                                        <i class="fas fa-file-excel text-success me-2"></i>Excel (.xlsx)
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item btn-export-format" href="#" data-format="pdf">
                                                        <i class="fas fa-file-pdf text-danger me-2"></i>PDF (.pdf)
                                                    </a>
                                                </li>
                                                <li>
                                                    <a class="dropdown-item btn-export-format" href="#" data-format="csv">
                                                        <i class="fas fa-file-csv text-info me-2"></i>CSV (.csv)
                                                    </a>
                                                </li>
                                            </ul>
                                        </div>
                                        
                                        <button type="button" class="btn btn-modern-secondary w-100 mt-2" onclick="window.print();">
                                            <i class="fas fa-print me-1"></i>Imprimir
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-lg-8 col-xl-9">
                                <!-- Árbol de ubicaciones -->
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
                                                    <span class="input-group-text">
                                                        <i class="fas fa-search"></i>
                                                    </span>
                                                    <input type="text" class="form-control" id="searchTree" 
                                                           placeholder="Buscar ubicación...">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="card-body">
                                        <div class="tree-container" id="treeContainer">
                                            <!-- El árbol se cargará aquí -->
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

            <!-- Modal para crear/editar ubicación -->
            <div class="modal fade modal-modern" id="modalSucursalUbicacion" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
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
                                        <label for="sucursal_id" class="form-label">
                                            <i class="fas fa-store me-1"></i>Sucursal *
                                        </label>
                                        <div class="input-group input-group-modern">
                                            <span class="input-group-text">
                                                <i class="fas fa-building"></i>
                                            </span>
                                            <select class="form-select" id="sucursal_id" name="sucursal_id" required>
                                                <option value="">Seleccionar sucursal...</option>
                                            </select>
                                        </div>
                                        <div class="invalid-feedback">Debe seleccionar una sucursal</div>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="seccion" class="form-label">
                                            <i class="fas fa-layer-group me-1"></i>Sección *
                                        </label>
                                        <div class="input-group input-group-modern">
                                            <span class="input-group-text">
                                                <i class="fas fa-tag"></i>
                                            </span>
                                            <input type="text" class="form-control" id="seccion" name="seccion" 
                                                   maxlength="50" required placeholder="Ej: A">
                                        </div>
                                        <div class="invalid-feedback">La sección es obligatoria</div>
                                        <small class="form-text text-muted">Letra o código</small>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="estanteria" class="form-label">
                                            <i class="fas fa-th-large me-1"></i>Estantería *
                                        </label>
                                        <div class="input-group input-group-modern">
                                            <span class="input-group-text">
                                                <i class="fas fa-th"></i>
                                            </span>
                                            <input type="text" class="form-control" id="estanteria" name="estanteria" 
                                                   maxlength="50" required placeholder="Ej: 01">
                                        </div>
                                        <div class="invalid-feedback">La estantería es obligatoria</div>
                                        <small class="form-text text-muted">Número</small>
                                    </div>
                                    
                                    <div class="col-md-4 mb-3">
                                        <label for="estante" class="form-label">
                                            <i class="fas fa-shelves me-1"></i>Estante *
                                        </label>
                                        <div class="input-group input-group-modern">
                                            <span class="input-group-text">
                                                <i class="fas fa-box"></i>
                                            </span>
                                            <input type="text" class="form-control" id="estante" name="estante" 
                                                   maxlength="50" required placeholder="Ej: 01">
                                        </div>
                                        <div class="invalid-feedback">El estante es obligatorio</div>
                                        <small class="form-text text-muted">Número</small>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label for="descripcion" class="form-label">
                                            <i class="fas fa-align-left me-1"></i>Descripción
                                        </label>
                                        <div class="input-group input-group-modern">
                                            <span class="input-group-text">
                                                <i class="fas fa-comment"></i>
                                            </span>
                                            <textarea class="form-control" id="descripcion" name="descripcion" 
                                                      maxlength="255" rows="2" placeholder="Descripción detallada de la ubicación..."></textarea>
                                        </div>
                                        <small class="form-text text-muted">Máximo 255 caracteres</small>
                                    </div>
                                    
                                    <div class="col-md-12 mb-3">
                                        <label for="estado_registro_id" class="form-label">
                                            <i class="fas fa-circle me-1"></i>Estado
                                        </label>
                                        <div class="input-group input-group-modern">
                                            <span class="input-group-text">
                                                <i class="fas fa-toggle-on"></i>
                                            </span>
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
    /* Estilos para el árbol jerárquico */
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
        padding: 1.5rem 1.5rem;
    }
    
    .card-title {
        font-weight: 600;
        font-size: 1.25rem;
    }
    
    .btn-modern-primary {
        background: linear-gradient(135deg, #3498db 0%, #2c3e50 100%);
        border: none;
        color: white;
        font-weight: 500;
        padding: 0.75rem 1.5rem;
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
        padding: 0.75rem 1.5rem;
        border-radius: 8px;
        transition: all 0.3s ease;
        width: 100%;
    }
    
    .btn-modern-secondary:hover {
        background: #e9ecef;
        transform: translateY(-2px);
    }
    
    .form-select-modern {
        border: 2px solid #e9ecef;
        border-radius: 8px;
        padding: 0.5rem 1rem;
        transition: all 0.3s ease;
    }
    
    .stats-container {
        background: #f8f9fa;
        padding: 1.25rem;
        border-radius: 10px;
        margin-top: 1.5rem;
    }
    
    .stats-card {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }
    
    .stats-item {
        display: flex;
        align-items: center;
        background: white;
        padding: 0.75rem;
        border-radius: 8px;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .stats-icon {
        font-size: 1.25rem;
        color: #3498db;
        margin-right: 0.75rem;
        background: rgba(52, 152, 219, 0.1);
        padding: 0.5rem;
        border-radius: 6px;
    }
    
    .stats-number {
        font-size: 1.1rem;
        font-weight: 600;
        color: #212529;
        line-height: 1;
    }
    
    .stats-label {
        font-size: 0.8rem;
        color: #6c757d;
        margin-top: 0.125rem;
    }
    
    .legend-item {
        display: flex;
        align-items: center;
        margin-bottom: 0.5rem;
    }
    
    .legend-color {
        width: 16px;
        height: 16px;
        border-radius: 4px;
        margin-right: 0.75rem;
    }
    
    .legend-sucursal {
        background: linear-gradient(135deg, #2c3e50 0%, #4a6491 100%);
    }
    
    .legend-seccion {
        background: linear-gradient(135deg, #3498db 0%, #2ecc71 100%);
    }
    
    .legend-estanteria {
        background: linear-gradient(135deg, #f39c12 0%, #e74c3c 100%);
    }
    
    .legend-estante {
        background: linear-gradient(135deg, #9b59b6 0%, #34495e 100%);
    }
    
    .legend-text {
        font-size: 0.9rem;
        color: #495057;
    }
    
    .tree-container {
        min-height: 600px;
        max-height: 700px;
        overflow-y: auto;
        padding: 1rem;
        background: #f8f9fa;
        border-radius: 8px;
    }
    
    .tree-loading {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 400px;
        color: #6c757d;
    }
    
    /* Estilos para el árbol jerárquico - COMPACTO */
    .tree {
        list-style: none;
        padding-left: 0;
    }
    
    .tree-node {
        margin-bottom: 0.25rem; /* Más compacto */
    }
    
    .tree-node-content {
        display: flex;
        align-items: center;
        padding: 0.5rem 0.75rem; /* Más compacto */
        background: white;
        border-radius: 8px;
        border: 1px solid #e9ecef;
        cursor: pointer;
        transition: all 0.3s ease;
        min-height: 48px; /* Altura mínima fija */
    }
    
    .tree-node-content:hover {
        transform: translateX(5px);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        border-color: #3498db;
    }
    
    .tree-node-expander {
        width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 0.5rem;
        border-radius: 4px;
        background: #f8f9fa;
        transition: all 0.3s ease;
        flex-shrink: 0;
    }
    
    .tree-node-expander i {
        font-size: 0.7rem;
        color: #495057;
        transition: transform 0.3s ease;
    }
    
    .tree-node-expanded .tree-node-expander i {
        transform: rotate(90deg);
    }
    
    .tree-node-icon {
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-right: 0.5rem;
        border-radius: 6px;
        color: white;
        font-size: 0.8rem;
        flex-shrink: 0;
    }
    
    .tree-node-sucursal .tree-node-icon {
        background: linear-gradient(135deg, #2c3e50 0%, #4a6491 100%);
    }
    
    .tree-node-seccion .tree-node-icon {
        background: linear-gradient(135deg, #3498db 0%, #2ecc71 100%);
    }
    
    .tree-node-estanteria .tree-node-icon {
        background: linear-gradient(135deg, #f39c12 0%, #e74c3c 100%);
    }
    
    .tree-node-estante .tree-node-icon {
        background: linear-gradient(135deg, #9b59b6 0%, #34495e 100%);
    }
    
    .tree-node-info {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: space-between;
        min-width: 0; /* Para que el texto no desborde */
    }
    
    .tree-node-main {
        flex: 1;
        min-width: 0;
        margin-right: 0.75rem;
    }
    
    .tree-node-title {
        font-weight: 600;
        color: #212529;
        margin-bottom: 0.125rem;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .tree-node-details {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-shrink: 0;
    }
    
    .tree-node-detail {
        display: flex;
        align-items: center;
        font-size: 0.8rem;
        color: #6c757d;
        white-space: nowrap;
    }
    
    .tree-node-detail i {
        font-size: 0.7rem;
        margin-right: 0.25rem;
        color: #adb5bd;
    }
    
    .tree-node-subtitle {
        font-size: 0.75rem;
        color: #6c757d;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    
    .tree-node-actions {
        display: flex;
        gap: 0.25rem;
        opacity: 0;
        transition: opacity 0.3s ease;
        flex-shrink: 0;
    }
    
    .tree-node-content:hover .tree-node-actions {
        opacity: 1;
    }
    
    .tree-node-action {
        width: 28px;
        height: 28px;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        background: #f8f9fa;
        border: 1px solid #dee2e6;
        color: #495057;
        transition: all 0.3s ease;
    }
    
    .tree-node-action:hover {
        background: #3498db;
        border-color: #3498db;
        color: white;
        transform: translateY(-2px);
    }
    
    .tree-children {
        list-style: none;
        padding-left: 2rem;
        margin-top: 0.25rem;
        border-left: 2px dashed #dee2e6;
        margin-left: 1rem;
    }
    
    .tree-node-empty {
        padding: 0.75rem 1rem;
        background: white;
        border-radius: 8px;
        border: 1px dashed #dee2e6;
        color: #6c757d;
        font-style: italic;
        text-align: center;
    }
    
    .location-path {
        background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        border-radius: 10px;
        padding: 1.25rem;
        margin-bottom: 1.25rem;
    }
    
    .path-header {
        display: flex;
        align-items: center;
        color: #495057;
        font-weight: 600;
        margin-bottom: 0.75rem;
        font-size: 0.9rem;
    }
    
    .path-content {
        background: white;
        padding: 0.75rem;
        border-radius: 8px;
        border: 2px solid #dee2e6;
        font-size: 0.85rem;
    }
    
    #fullPath {
        font-family: 'Courier New', monospace;
        color: #495057;
        line-height: 1.4;
    }
    
    .search-container {
        width: 300px;
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
    }
    
    .input-group-modern .form-control {
        border: 2px solid #e9ecef;
        border-left: none;
        padding: 0.5rem 1rem;
    }
    
    .input-group-modern .form-control:focus {
        border-color: #3498db;
        box-shadow: none;
    }
    
    .tree-node-highlight {
        background: linear-gradient(135deg, rgba(255, 235, 59, 0.2) 0%, rgba(255, 193, 7, 0.2) 100%);
        border-color: #ffc107;
        animation: highlightPulse 2s infinite;
    }
    
    .tree-node-editing {
        background: linear-gradient(135deg, rgba(52, 152, 219, 0.1) 0%, rgba(41, 128, 185, 0.1) 100%);
        border-color: #3498db;
        box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
    }
    
    @keyframes highlightPulse {
        0% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); }
        100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0); }
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
        padding: 1.25rem;
    }
    
    .modal-header-modern .btn-close-modern {
        filter: brightness(0) invert(1);
        opacity: 0.8;
    }
    
    .modal-header-modern .btn-close-modern:hover {
        opacity: 1;
    }
    
    .modal-subtitle {
        color: rgba(255,255,255,0.8);
        font-size: 0.85rem;
    }
    
    /* Badges compactos */
    .badge-compact {
        padding: 0.15rem 0.4rem;
        font-size: 0.7rem;
        font-weight: 500;
        border-radius: 4px;
    }
    
    /* Responsive */
    @media (max-width: 768px) {
        .search-container {
            width: 100%;
            margin-top: 1rem;
        }
        
        .tree-container {
            max-height: 500px;
        }
        
        .stats-card {
            grid-template-columns: 1fr;
        }
        
        .tree-node-details {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.25rem;
        }
        
        .tree-node-detail {
            font-size: 0.7rem;
        }
    }
    
    /* Para pantallas muy pequeñas */
    @media (max-width: 576px) {
        .tree-node-content {
            padding: 0.4rem 0.5rem;
        }
        
        .tree-node-details {
            display: none; /* Ocultar detalles en móvil muy pequeño */
        }
        
        .tree-node-actions {
            opacity: 1; /* Mostrar acciones siempre en móvil */
        }
    }
    </style>

    <script>
    $(document).ready(function(){
        // Variables de contexto MULTIEMPRESA
        const empresa_idx = 2;
        const pagina_idx = <?php echo $pagina_idx; ?>;
        
        // Variables globales
        let sucursalesData = [];
        let treeData = {};
        let estadisticas = {
            sucursales: 0,
            secciones: 0,
            estanterias: 0,
            estantes: 0
        };
        
        // Variable para recordar el nodo que se está editando
        let editingNodeId = null;
        let editingNodeScrollPosition = 0;
        
        // Función para cargar sucursales
        function cargarSucursales() {
            return new Promise((resolve, reject) => {
                $.get('sucursales_ubicaciones_ajax.php', {
                    accion: 'obtener_sucursales_activas',
                    empresa_idx: empresa_idx
                }, function(sucursales){
                    sucursalesData = sucursales;
                    
                    // Cargar en el filtro
                    var filterSelect = $('#filterSucursal');
                    filterSelect.empty();
                    filterSelect.append('<option value="">Todas las sucursales</option>');
                    
                    $.each(sucursales, function(index, sucursal){
                        var optionText = sucursal.sucursal_nombre;
                        if (sucursal.localidad) {
                            optionText += ` (${sucursal.localidad})`;
                        }
                        filterSelect.append(`<option value="${sucursal.sucursal_id}">${optionText}</option>`);
                    });
                    
                    // También cargar en el modal
                    cargarSucursalesEnModal();
                    
                    resolve(sucursales);
                }, 'json').fail(reject);
            });
        }
        
        // Cargar sucursales en el select del modal
        function cargarSucursalesEnModal() {
            var modalSelect = $('#sucursal_id');
            modalSelect.empty();
            modalSelect.append('<option value="">Seleccionar sucursal...</option>');
            
            $.each(sucursalesData, function(index, sucursal){
                var optionText = sucursal.sucursal_nombre;
                if (sucursal.localidad) {
                    optionText += ` (${sucursal.localidad})`;
                }
                modalSelect.append(`<option value="${sucursal.sucursal_id}">${optionText}</option>`);
            });
        }
        
        // Cargar estados
        function cargarEstados(selectedId = null) {
            $.get('sucursales_ubicaciones_ajax.php', {
                accion: 'obtener_estados_registro'
            }, function(estados){
                var select = $('#estado_registro_id');
                select.empty();
                select.append('<option value="">Seleccionar estado...</option>');
                
                $.each(estados, function(index, estado){
                    var selected = (selectedId && estado.estado_registro_id == selectedId) ? 'selected' : '';
                    select.append(`<option value="${estado.estado_registro_id}" ${selected}>${estado.estado_registro}</option>`);
                });
            }, 'json');
        }
        
        // Cargar árbol de ubicaciones
        function cargarArbolUbicaciones(sucursalId = '') {
            $('#treeContainer').html(`
                <div class="tree-loading">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Cargando árbol...</span>
                    </div>
                    <p class="mt-2">Cargando estructura jerárquica...</p>
                </div>
            `);
            
            $.get('sucursales_ubicaciones_ajax.php', {
                accion: 'listar',
                empresa_idx: empresa_idx,
                pagina_idx: pagina_idx,
                filter_sucursal: sucursalId
            }, function(ubicaciones){
                procesarDatosArbol(ubicaciones);
                renderizarArbol();
                actualizarEstadisticas();
                actualizarUltimaActualizacion();
                
                // Restaurar scroll si había un nodo en edición
                if (editingNodeId) {
                    setTimeout(() => {
                        resaltarNodoEditado(editingNodeId);
                        editingNodeId = null;
                    }, 300);
                }
            }, 'json');
        }
        
        // Procesar datos para el árbol
        function procesarDatosArbol(ubicaciones) {
            treeData = {};
            estadisticas = {
                sucursales: 0,
                secciones: 0,
                estanterias: 0,
                estantes: 0
            };
            
            ubicaciones.forEach(ubicacion => {
                const sucursalId = ubicacion.sucursal_id;
                const sucursalNombre = ubicacion.sucursal_nombre;
                const localidad = ubicacion.localidad || '';
                
                if (!treeData[sucursalId]) {
                    treeData[sucursalId] = {
                        id: sucursalId,
                        type: 'sucursal',
                        nombre: sucursalNombre,
                        localidad: localidad,
                        secciones: {}
                    };
                    estadisticas.sucursales++;
                }
                
                const seccion = ubicacion.seccion;
                if (!treeData[sucursalId].secciones[seccion]) {
                    treeData[sucursalId].secciones[seccion] = {
                        id: seccion,
                        type: 'seccion',
                        nombre: seccion,
                        estanterias: {}
                    };
                    estadisticas.secciones++;
                }
                
                const estanteria = ubicacion.estanteria;
                if (!treeData[sucursalId].secciones[seccion].estanterias[estanteria]) {
                    treeData[sucursalId].secciones[seccion].estanterias[estanteria] = {
                        id: estanteria,
                        type: 'estanteria',
                        nombre: estanteria,
                        estantes: {}
                    };
                    estadisticas.estanterias++;
                }
                
                const estante = ubicacion.estante;
                if (!treeData[sucursalId].secciones[seccion].estanterias[estanteria].estantes[estante]) {
                    treeData[sucursalId].secciones[seccion].estanterias[estanteria].estantes[estante] = {
                        id: ubicacion.sucursal_ubicacion_id,
                        type: 'estante',
                        nombre: estante,
                        descripcion: ubicacion.descripcion,
                        estado: ubicacion.estado_info,
                        botones: ubicacion.botones,
                        sucursal_id: sucursalId,
                        seccion: seccion,
                        estanteria: estanteria,
                        estante: estante,
                        sucursal_nombre: sucursalNombre,
                        localidad: localidad
                    };
                    estadisticas.estantes++;
                }
            });
        }
        
        // Renderizar árbol
        function renderizarArbol() {
            let html = '<ul class="tree">';
            
            if (Object.keys(treeData).length === 0) {
                html += `
                    <div class="tree-node-empty">
                        <i class="fas fa-inbox me-1"></i>
                        No hay ubicaciones registradas. 
                        <a href="#" id="btnPrimeraUbicacion" class="text-decoration-none">Crear primera ubicación</a>
                    </div>
                `;
            } else {
                Object.values(treeData).forEach(sucursal => {
                    html += renderizarNodo(sucursal);
                });
            }
            
            html += '</ul>';
            $('#treeContainer').html(html);
            
            // Inicializar todos los nodos como colapsados
            $('.tree-node').each(function() {
                if ($(this).find('.tree-children').length > 0) {
                    $(this).addClass('tree-node-collapsed');
                    $(this).find('.tree-children').hide();
                }
            });
        }
        
        // Renderizar un nodo del árbol
        function renderizarNodo(nodo, nivel = 0) {
            const tipos = {
                'sucursal': {
                    icon: 'fas fa-store',
                    class: 'tree-node-sucursal',
                    title: 'Sucursal'
                },
                'seccion': {
                    icon: 'fas fa-layer-group',
                    class: 'tree-node-seccion',
                    title: 'Sección'
                },
                'estanteria': {
                    icon: 'fas fa-th-large',
                    class: 'tree-node-estanteria',
                    title: 'Estantería'
                },
                'estante': {
                    icon: 'fas fa-shelves',
                    class: 'tree-node-estante',
                    title: 'Estante'
                }
            };
            
            const tipoInfo = tipos[nodo.type];
            const tieneHijos = nodo.type !== 'estante';
            let html = '';
            
            html += `<li class="tree-node ${tipoInfo.class}" data-id="${nodo.id}" data-type="${nodo.type}">`;
            html += `<div class="tree-node-content">`;
            
            // Expander
            if (tieneHijos) {
                html += `<div class="tree-node-expander">
                            <i class="fas fa-chevron-right"></i>
                         </div>`;
            } else {
                html += `<div class="tree-node-expander" style="visibility: hidden;"></div>`;
            }
            
            // Icono
            html += `<div class="tree-node-icon">
                        <i class="${tipoInfo.icon}"></i>
                     </div>`;
            
            // Información
            html += `<div class="tree-node-info">`;
            html += `<div class="tree-node-main">`;
            html += `<div class="tree-node-title">${nodo.nombre}</div>`;
            
            // Subtítulo según el tipo de nodo
            if (nodo.type === 'sucursal' && nodo.localidad) {
                html += `<div class="tree-node-subtitle">
                            <i class="fas fa-map-marker-alt fa-xs me-1"></i>${nodo.localidad}
                         </div>`;
            } else if (nodo.type === 'estante' && nodo.descripcion) {
                html += `<div class="tree-node-subtitle">${nodo.descripcion}</div>`;
            }
            
            html += `</div>`;
            
            // Detalles al costado (sección, estantería, estante)
            html += `<div class="tree-node-details">`;
            
            if (nodo.type === 'estante') {
                // Para estantes, mostrar los detalles jerárquicos
                html += `<div class="tree-node-detail">
                            <i class="fas fa-layer-group"></i>
                            <span>${nodo.seccion || ''}</span>
                         </div>`;
                html += `<div class="tree-node-detail">
                            <i class="fas fa-th-large"></i>
                            <span>${nodo.estanteria || ''}</span>
                         </div>`;
                html += `<div class="tree-node-detail">
                            <i class="fas fa-shelves"></i>
                            <span>${nodo.estante || ''}</span>
                         </div>`;
                
                // Estado
                const estado = nodo.estado?.estado_registro || 'Sin estado';
                const estadoColor = getEstadoColor(nodo.estado);
                html += `<span class="badge badge-compact bg-${estadoColor}">${estado}</span>`;
            } else if (nodo.type === 'estanteria') {
                // Para estanterías, mostrar solo la sección
                html += `<div class="tree-node-detail">
                            <i class="fas fa-layer-group"></i>
                            <span>Sección</span>
                         </div>`;
            }
            
            html += `</div>`;
            
            html += `</div>`; // Cierre de tree-node-info
            
            // Acciones
            html += `<div class="tree-node-actions">`;
            
            if (nodo.type === 'estante') {
                // Solo botón de editar para estantes
                nodo.botones?.forEach(boton => {
                    if (boton.accion_js === 'editar') {
                        html += `<button class="tree-node-action btn-editar" 
                                  data-id="${nodo.id}"
                                  title="${boton.descripcion}">
                                  <i class="fas fa-edit"></i>
                                </button>`;
                    }
                });
            } else {
                // Botón para agregar hijo para otros tipos
                html += `<button class="tree-node-action btn-agregar-hijo" 
                          data-id="${nodo.id}"
                          data-type="${nodo.type}"
                          title="Agregar ${getChildType(nodo.type)}">
                          <i class="fas fa-plus"></i>
                        </button>`;
            }
            
            html += `</div>`;
            html += `</div>`; // Cierre de tree-node-content
            
            // Hijos
            if (tieneHijos) {
                const childType = getChildType(nodo.type);
                const children = getChildren(nodo, childType);
                
                if (Object.keys(children).length > 0) {
                    html += `<ul class="tree-children">`;
                    Object.values(children).forEach(child => {
                        html += renderizarNodo(child, nivel + 1);
                    });
                    html += `</ul>`;
                }
            }
            
            html += `</li>`;
            return html;
        }
        
        // Obtener el tipo de hijo
        function getChildType(parentType) {
            const hierarchy = {
                'sucursal': 'seccion',
                'seccion': 'estanteria',
                'estanteria': 'estante'
            };
            return hierarchy[parentType] || null;
        }
        
        // Obtener hijos de un nodo
        function getChildren(nodo, childType) {
            switch (childType) {
                case 'seccion': return nodo.secciones || {};
                case 'estanteria': return nodo.estanterias || {};
                case 'estante': return nodo.estantes || {};
                default: return {};
            }
        }
        
        // Obtener color del estado
        function getEstadoColor(estadoInfo) {
            if (!estadoInfo) return 'secondary';
            switch (estadoInfo.codigo_estandar) {
                case 'ACTIVO': return 'success';
                case 'INACTIVO': return 'secondary';
                case 'BLOQUEADO': return 'warning';
                default: return 'secondary';
            }
        }
        
        // Resaltar nodo editado después de guardar
        function resaltarNodoEditado(nodeId) {
            // Remover cualquier resaltado anterior
            $('.tree-node').removeClass('tree-node-editing');
            
            // Encontrar y resaltar el nodo
            const $node = $(`.tree-node[data-id="${nodeId}"]`);
            if ($node.length > 0) {
                $node.addClass('tree-node-editing');
                
                // Expandir padres
                $node.parentsUntil('.tree', '.tree-node').each(function() {
                    $(this).addClass('tree-node-expanded');
                    $(this).find('.tree-children').show();
                    $(this).find('.tree-node-expander i').css('transform', 'rotate(90deg)');
                });
                
                // Hacer scroll al nodo
                const container = $('#treeContainer');
                const nodeOffset = $node.offset().top;
                const containerOffset = container.offset().top;
                const scrollPosition = nodeOffset - containerOffset - 100;
                
                container.animate({
                    scrollTop: scrollPosition
                }, 500);
                
                // Remover el resaltado después de 3 segundos
                setTimeout(() => {
                    $node.removeClass('tree-node-editing');
                }, 3000);
            }
        }
        
        // Actualizar estadísticas
        function actualizarEstadisticas() {
            $('#totalSucursales').text(estadisticas.sucursales);
            $('#totalSecciones').text(estadisticas.secciones);
            $('#totalEstanterias').text(estadisticas.estanterias);
            $('#totalEstantes').text(estadisticas.estantes);
        }
        
        // Actualizar última actualización
        function actualizarUltimaActualizacion() {
            $('#lastUpdate').text(new Date().toLocaleDateString('es-ES', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            }));
        }
        
        // Manejar expansión/colapso de nodos
        $(document).on('click', '.tree-node-expander', function(e) {
            e.stopPropagation();
            const $node = $(this).closest('.tree-node');
            const $children = $node.find('.tree-children');
            
            if ($children.is(':visible')) {
                $children.slideUp(300);
                $node.removeClass('tree-node-expanded').addClass('tree-node-collapsed');
                $(this).find('i').css('transform', 'rotate(0deg)');
            } else {
                $children.slideDown(300);
                $node.removeClass('tree-node-collapsed').addClass('tree-node-expanded');
                $(this).find('i').css('transform', 'rotate(90deg)');
            }
        });
        
        // Expandir todo
        $('#btnExpandAll').click(function() {
            $('.tree-children').slideDown(300);
            $('.tree-node').removeClass('tree-node-collapsed').addClass('tree-node-expanded');
            $('.tree-node-expander i').css('transform', 'rotate(90deg)');
        });
        
        // Contraer todo
        $('#btnCollapseAll').click(function() {
            $('.tree-children').slideUp(300);
            $('.tree-node').removeClass('tree-node-expanded').addClass('tree-node-collapsed');
            $('.tree-node-expander i').css('transform', 'rotate(0deg)');
        });
        
        // Buscar en el árbol
        $('#searchTree').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();
            
            $('.tree-node').removeClass('tree-node-highlight');
            
            if (searchTerm.length > 2) {
                $('.tree-node').each(function() {
                    const $node = $(this);
                    const text = $node.text().toLowerCase();
                    
                    if (text.includes(searchTerm)) {
                        $node.addClass('tree-node-highlight');
                        
                        // Expandir padres
                        $node.parentsUntil('.tree', '.tree-node').each(function() {
                            $(this).addClass('tree-node-expanded');
                            $(this).find('.tree-children').show();
                            $(this).find('.tree-node-expander i').css('transform', 'rotate(90deg)');
                        });
                    }
                });
            }
        });
        
        // Manejar clic en nodo
        $(document).on('click', '.tree-node-content', function(e) {
            if (!$(e.target).closest('.tree-node-action').length && !$(e.target).closest('.tree-node-expander').length) {
                const $node = $(this).closest('.tree-node');
                const type = $node.data('type');
                const id = $node.data('id');
                
                if (type === 'estante') {
                    cargarUbicacionParaEditar(id);
                }
            }
        });
        
        // Manejar agregar hijo
        $(document).on('click', '.btn-agregar-hijo', function(e) {
            e.stopPropagation();
            const parentId = $(this).data('id');
            const parentType = $(this).data('type');
            
            resetModal();
            $('#parent_type').val(parentType);
            $('#parent_id').val(parentId);
            
            // Determinar qué campos pre-llenar
            if (parentType === 'sucursal') {
                $('#sucursal_id').val(parentId);
                $('#seccion').focus();
            } else if (parentType === 'seccion') {
                const sucursalId = encontrarSucursalPorSeccion(parentId);
                if (sucursalId) {
                    $('#sucursal_id').val(sucursalId);
                    $('#seccion').val(parentId);
                    $('#estanteria').focus();
                }
            } else if (parentType === 'estanteria') {
                const ubicacion = encontrarUbicacionPorEstanteria(parentId);
                if (ubicacion) {
                    $('#sucursal_id').val(ubicacion.sucursalId);
                    $('#seccion').val(ubicacion.seccion);
                    $('#estanteria').val(ubicacion.estanteria);
                    $('#estante').focus();
                }
            }
            
            $('#modalLabel').html('<i class="fas fa-map-marker-alt me-2"></i>Nueva Ubicación');
            $('#modalSubtitle').text(`Agregar ${getChildType(parentType)} a ${parentType} seleccionado`);
            cargarEstados();
            
            var modal = new bootstrap.Modal(document.getElementById('modalSucursalUbicacion'));
            modal.show();
            
            actualizarRutaCompleta();
        });
        
        // Manejar editar - Guardar posición del scroll antes de abrir el modal
        $(document).on('click', '.btn-editar', function(e) {
            e.stopPropagation();
            const id = $(this).data('id');
            const $node = $(this).closest('.tree-node');
            
            // Guardar la posición del scroll
            editingNodeScrollPosition = $('#treeContainer').scrollTop();
            
            // Guardar el ID del nodo que se está editando
            editingNodeId = id;
            
            // Marcar el nodo como "en edición" visualmente
            $node.addClass('tree-node-editing');
            
            cargarUbicacionParaEditar(id);
        });
        
        // Buscar sucursal por sección
        function encontrarSucursalPorSeccion(seccionId) {
            for (const sucursalId in treeData) {
                if (treeData[sucursalId].secciones[seccionId]) {
                    return sucursalId;
                }
            }
            return null;
        }
        
        // Buscar ubicación por estantería
        function encontrarUbicacionPorEstanteria(estanteriaId) {
            for (const sucursalId in treeData) {
                for (const seccionId in treeData[sucursalId].secciones) {
                    if (treeData[sucursalId].secciones[seccionId].estanterias[estanteriaId]) {
                        return {
                            sucursalId: sucursalId,
                            seccion: seccionId,
                            estanteria: estanteriaId
                        };
                    }
                }
            }
            return null;
        }
        
        // Actualizar ruta completa en el modal
        function actualizarRutaCompleta() {
            const sucursalId = $('#sucursal_id').val();
            const seccion = $('#seccion').val();
            const estanteria = $('#estanteria').val();
            const estante = $('#estante').val();
            
            let ruta = '';
            
            if (sucursalId) {
                const sucursal = sucursalesData.find(s => s.sucursal_id == sucursalId);
                if (sucursal) {
                    ruta += `<strong>${sucursal.sucursal_nombre}</strong>`;
                    if (sucursal.localidad) {
                        ruta += ` <span class="text-muted">(${sucursal.localidad})</span>`;
                    }
                }
            }
            
            if (seccion) {
                ruta += ` &nbsp;&nbsp;<i class="fas fa-arrow-right text-muted"></i>&nbsp;&nbsp; <strong>Sección ${seccion}</strong>`;
            }
            
            if (estanteria) {
                ruta += ` &nbsp;&nbsp;<i class="fas fa-arrow-right text-muted"></i>&nbsp;&nbsp; <strong>Estantería ${estanteria}</strong>`;
            }
            
            if (estante) {
                ruta += ` &nbsp;&nbsp;<i class="fas fa-arrow-right text-muted"></i>&nbsp;&nbsp; <strong>Estante ${estante}</strong>`;
            }
            
            $('#fullPath').html(ruta || '<span class="text-muted">Seleccione los datos para ver la ruta completa</span>');
        }
        
        // Escuchar cambios en los campos
        $('#sucursal_id, #seccion, #estanteria, #estante').on('change input', function() {
            actualizarRutaCompleta();
        });
        
        // Cargar ubicación para editar
        function cargarUbicacionParaEditar(id) {
            $.get('sucursales_ubicaciones_ajax.php', {
                accion: 'obtener', 
                sucursal_ubicacion_id: id,
                empresa_idx: empresa_idx
            }, function(res){
                if(res && res.sucursal_ubicacion_id){
                    resetModal();
                    $('#sucursal_ubicacion_id').val(res.sucursal_ubicacion_id);
                    
                    // Asegurarse de que el select de sucursales esté cargado
                    if ($('#sucursal_id option').length <= 1) {
                        cargarSucursalesEnModal();
                    }
                    
                    setTimeout(() => {
                        $('#sucursal_id').val(res.sucursal_id);
                        $('#seccion').val(res.seccion || '');
                        $('#estanteria').val(res.estanteria || '');
                        $('#estante').val(res.estante || '');
                        $('#descripcion').val(res.descripcion || '');
                        
                        cargarEstados(res.tabla_estado_registro_id);
                        actualizarRutaCompleta();
                        
                        $('#modalLabel').html('<i class="fas fa-map-marker-alt me-2"></i>Editar Ubicación');
                        $('#modalSubtitle').text('Modificar ubicación existente');
                        
                        var modal = new bootstrap.Modal(document.getElementById('modalSucursalUbicacion'));
                        modal.show();
                    }, 100);
                    
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "Error al obtener datos de la ubicación",
                        confirmButtonText: "Entendido"
                    });
                }
            }, 'json');
        }
        
        // Cargar botón Agregar dinámicamente
        function cargarBotonAgregar() {
            $.get('sucursales_ubicaciones_ajax.php', {
                accion: 'obtener_boton_agregar',
                pagina_idx: pagina_idx
            }, function(botonAgregar){
                if (botonAgregar && botonAgregar.nombre_funcion) {
                    var icono = botonAgregar.icono_clase ? `<i class="${botonAgregar.icono_clase} me-1"></i>` : '';
                    
                    var colorClase = 'btn-modern-primary';
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
        
        // Manejador para botón "Nueva Ubicación"
        $(document).on('click', '#btnNuevo', function(){
            resetModal();
            $('#modalLabel').html('<i class="fas fa-map-marker-alt me-2"></i>Nueva Ubicación');
            $('#modalSubtitle').text('Crear una nueva ubicación desde cero');
            
            if ($('#sucursal_id option').length <= 1) {
                cargarSucursalesEnModal();
            }
            
            cargarEstados();
            
            var modal = new bootstrap.Modal(document.getElementById('modalSucursalUbicacion'));
            modal.show();
            $('#seccion').focus();
        });
        
        // Manejador para crear primera ubicación
        $(document).on('click', '#btnPrimeraUbicacion', function(e){
            e.preventDefault();
            $('#btnNuevo').click();
        });
        
        // Manejador del filtro de sucursal
        $(document).on('change', '#filterSucursal', function() {
            cargarArbolUbicaciones($(this).val());
        });
        
        // Función para exportar
        function manejarExportacion(formato) {
            Swal.fire({
                icon: 'info',
                title: 'Exportando...',
                text: `Preparando árbol en formato ${formato.toUpperCase()}`,
                showConfirmButton: false,
                allowOutsideClick: false,
                timer: 1500
            });
            
            setTimeout(() => {
                const sucursalId = $('#filterSucursal').val();
                let url = `sucursales_ubicaciones_ajax.php?accion=exportar&formato=${formato}&empresa_idx=${empresa_idx}`;
                if (sucursalId) {
                    url += `&filter_sucursal=${sucursalId}`;
                }
                window.location.href = url;
            }, 1500);
        }
        
        // Manejador para exportación
        $(document).on('click', '.btn-export-format', function(e){
            e.preventDefault();
            let formato = $(this).data('format');
            manejarExportacion(formato);
        });
        
        // Función para resetear el modal
        function resetModal() {
            $('#formSucursalUbicacion')[0].reset();
            $('#sucursal_ubicacion_id').val('');
            $('#parent_type').val('');
            $('#parent_id').val('');
            $('#formSucursalUbicacion').removeClass('was-validated');
            $('#fullPath').html('<span class="text-muted">Seleccione los datos para ver la ruta completa</span>');
        }
        
        // Validación del formulario
        $('#btnGuardar').click(function(){
            var form = document.getElementById('formSucursalUbicacion');
            
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return false;
            }
            
            var id = $('#sucursal_ubicacion_id').val();
            var accionBackend = id ? 'editar' : 'agregar';
            var sucursalId = $('#sucursal_id').val();
            var seccion = $('#seccion').val().trim();
            var estanteria = $('#estanteria').val().trim();
            var estante = $('#estante').val().trim();
            
            if (!sucursalId) {
                $('#sucursal_id').addClass('is-invalid');
                return false;
            }
            
            if (!seccion) {
                $('#seccion').addClass('is-invalid');
                return false;
            }
            
            if (!estanteria) {
                $('#estanteria').addClass('is-invalid');
                return false;
            }
            
            if (!estante) {
                $('#estante').addClass('is-invalid');
                return false;
            }

            var btnGuardar = $(this);
            var originalText = btnGuardar.html();
            btnGuardar.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-1"></i>Guardando...');
            
            $.ajax({
                url: 'sucursales_ubicaciones_ajax.php',
                type: 'POST',
                data: {
                    accion: accionBackend,
                    sucursal_ubicacion_id: id,
                    sucursal_id: sucursalId,
                    seccion: seccion,
                    estanteria: estanteria,
                    estante: estante,
                    descripcion: $('#descripcion').val().trim(),
                    estado_registro_id: $('#estado_registro_id').val() || 1,
                    empresa_idx: empresa_idx,
                    pagina_idx: pagina_idx
                },
                success: function(res){
                    if(res.resultado){
                        var modalEl = document.getElementById('modalSucursalUbicacion');
                        var modal = bootstrap.Modal.getInstance(modalEl);
                        
                        btnGuardar.prop('disabled', false).html(originalText);
                        
                        Swal.fire({                    
                            icon: "success",
                            title: "¡Guardado!",
                            text: "Ubicación guardada correctamente",
                            showConfirmButton: false,
                            timer: 1500,
                            toast: true,
                            position: 'top-end'
                        });                
                        
                        modal.hide();
                        
                        // Recargar el árbol manteniendo la posición
                        const sucursalId = $('#filterSucursal').val();
                        cargarArbolUbicaciones(sucursalId);
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
                error: function() {
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
        
        // Inicializar
        $(function() {
            cargarSucursales().then(function() {
                cargarArbolUbicaciones();
                cargarEstados();
                cargarBotonAgregar();
            }).catch(function(error) {
                console.error('Error cargando sucursales:', error);
                Swal.fire({
                    icon: "error",
                    title: "Error de conexión",
                    text: "No se pudieron cargar las sucursales",
                    confirmButtonText: "Reintentar"
                }).then(() => {
                    location.reload();
                });
            });
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
