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
                                            <div class="legend-item">
                                                <span class="legend-color legend-posicion"></span>
                                                <span class="legend-text">Posición</span>
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
                                                    <button class="btn btn-outline-secondary" type="button" id="btnClearSearch">
                                                        <i class="fas fa-times"></i>
                                                    </button>
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
                                    
                                    <div class="col-md-3 mb-3">
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
                                    
                                    <div class="col-md-3 mb-3">
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
                                    
                                    <div class="col-md-3 mb-3">
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
                                    
                                    <div class="col-md-3 mb-3">
                                        <label for="posicion" class="form-label">
                                            <i class="fas fa-cube me-1"></i>Posición *
                                        </label>
                                        <div class="input-group input-group-modern">
                                            <span class="input-group-text">
                                                <i class="fas fa-cube"></i>
                                            </span>
                                            <input type="text" class="form-control" id="posicion" name="posicion" 
                                                   maxlength="50" required placeholder="Ej: 01">
                                        </div>
                                        <div class="invalid-feedback">La posición es obligatoria</div>
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
    
    .legend-posicion {
        background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%);
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
        cursor: pointer;
    }
    
    .tree-node-expander i {
        font-size: 0.7rem;
        color: #495057;
        transition: transform 0.3s ease !important;
    }
    
    .tree-node-expanded .tree-node-expander i {
        transform: rotate(90deg) !important;
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
    
    .tree-node-posicion .tree-node-icon {
        background: linear-gradient(135deg, #1abc9c 0%, #16a085 100%);
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
    
    .tree-node-subtitle {
        font-size: 0.75rem;
        color: #6c757d;
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
    /* ========== ESTILOS PARA CUADRÍCULA DE ESTANTERÍAS ========== */
    .estanterias-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
    gap: 1.5rem;
    margin-top: 1rem;
}

.estanteria-card {
    min-height: 350px;
    margin-bottom: 1.5rem;
}

.estantes-grid {
    display: flex;
    flex-wrap: nowrap;
    overflow-x: auto;
    gap: 1.5rem;
    padding: 0.5rem 0;
    scrollbar-width: thin;
}

.estantes-grid::-webkit-scrollbar {
    height: 6px;
}

.estantes-grid::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.estantes-grid::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.estante-card {
    flex: 0 0 auto;
    width: 320px;
    background: white;
    border-radius: 8px;
    padding: 1rem;
    border: 1px solid #dee2e6;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
}

.estante-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.estante-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #e9ecef;
}

.estante-header h6 {
    font-size: 0.95rem;
    font-weight: 600;
    color: #2c3e50;
    margin: 0;
}

/* ========== CUADRÍCULA DE POSICIONES (COLUMNAS) - MEJORADO ========== */
.posiciones-cuadricula {
    width: 100%;
    overflow-x: auto;
}

.posiciones-cabecera {
    display: grid;
    grid-template-columns: 50px repeat(4, 1fr);
    gap: 4px;
    margin-bottom: 4px;
    min-width: 300px;
}

.celda-vacia {
    /* Celda vacía para alinear con los números de fila */
}

.columna-cabecera {
    text-align: center;
    font-size: 0.8rem;
    font-weight: bold;
    color: #2c3e50;
    padding: 6px 2px;
    background: #f8f9fa;
    border-radius: 4px;
    border: 1px solid #dee2e6;
    min-width: 40px;
}

.posiciones-fila {
    display: grid;
    grid-template-columns: 50px repeat(4, 1fr);
    gap: 4px;
    margin-bottom: 4px;
    min-width: 300px;
}

.fila-cabecera {
    text-align: center;
    font-size: 0.8rem;
    font-weight: bold;
    color: #2c3e50;
    padding: 6px 2px;
    background: #f8f9fa;
    border-radius: 4px;
    border: 1px solid #dee2e6;
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
}

.posicion-celda {
    aspect-ratio: 1;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    position: relative;
    transition: all 0.3s ease;
    cursor: pointer;
    overflow: hidden;
    min-width: 40px;
    min-height: 40px;
}

.posicion-contenido {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 4px;
}

.posicion-vacia {
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    color: #6c757d;
}

.posicion-vacia:hover {
    background: linear-gradient(135deg, #e9ecef 0%, #dee2e6 100%);
    border-color: #3498db;
    transform: scale(1.05);
    box-shadow: 0 3px 6px rgba(52, 152, 219, 0.2);
}

.posicion-ocupada {
    background: white;
    color: #212529;
    border: 2px solid transparent;
    position: relative;
}

.posicion-ocupada:hover {
    transform: scale(1.05);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    z-index: 1;
    border-color: #3498db;
}

.posicion-numero {
    font-size: 0.75rem;
    font-weight: 600;
    color: inherit;
    text-align: center;
    line-height: 1;
    margin-bottom: 3px;
}

.posicion-estado {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    margin-top: 2px;
}

.posicion-vacia-icono {
    font-size: 0.8rem;
    opacity: 0.6;
    margin-top: 2px;
    transition: all 0.3s ease;
}

.posicion-vacia:hover .posicion-vacia-icono {
    opacity: 1;
    transform: scale(1.2);
    color: #3498db;
}

.posicion-acciones {
    position: absolute;
    top: 3px;
    right: 3px;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.posicion-celda:hover .posicion-acciones {
    opacity: 1;
}

.btn-editar-posicion {
    width: 20px;
    height: 20px;
    padding: 0;
    background: rgba(255,255,255,0.95);
    border: 1px solid #dee2e6;
    border-radius: 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s ease;
    font-size: 0.6rem;
}

.btn-editar-posicion:hover {
    background: #3498db;
    border-color: #3498db;
    color: white;
    transform: scale(1.1);
}

.estante-footer {
    padding-top: 0.5rem;
    border-top: 1px solid #e9ecef;
    font-size: 0.75rem;
}

/* Colores para estados */
.bg-success {
    background-color: #28a745 !important;
}

.bg-warning {
    background-color: #ffc107 !important;
}

.bg-secondary {
    background-color: #6c757d !important;
}

.bg-danger {
    background-color: #dc3545 !important;
}

.bg-info {
    background-color: #17a2b8 !important;
}

/* Tooltip mejorado */
.posicion-celda[title] {
    position: relative;
}

.posicion-celda[title]:hover::after {
    content: attr(title);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.9);
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 0.8rem;
    white-space: pre-line;
    z-index: 1000;
    min-width: 250px;
    text-align: left;
    margin-bottom: 5px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    line-height: 1.4;
}

.posicion-celda[title]:hover::before {
    content: '';
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: rgba(0, 0, 0, 0.9);
    margin-bottom: -5px;
    z-index: 1000;
}
/* ========== ESTADO DE POSICIONES ========== */
.bg-success {
    background-color: #28a745 !important;
}

.bg-warning {
    background-color: #ffc107 !important;
}

.bg-secondary {
    background-color: #6c757d !important;
}

/* ========== RESPONSIVE ========== */
@media (max-width: 768px) {
    .estanterias-grid {
        grid-template-columns: 1fr;
    }
    
    .estante-card {
        width: 100%;
        max-width: 350px;
        margin: 0 auto;
    }
    
    .posiciones-cabecera {
        grid-template-columns: 35px repeat(4, 1fr);
        gap: 3px;
    }
    
    .posiciones-fila {
        grid-template-columns: 35px repeat(4, 1fr);
        gap: 3px;
    }
    
    .columna-cabecera,
    .fila-cabecera {
        font-size: 0.75rem;
        padding: 5px 1px;
    }
    
    .posicion-numero {
        font-size: 0.7rem;
    }
}

/* ========== ANIMACIONES ========== */
@keyframes highlightPosition {
    0% { 
        box-shadow: 0 0 0 0 rgba(52, 152, 219, 0.7);
        transform: scale(1);
    }
    50% { 
        box-shadow: 0 0 0 10px rgba(52, 152, 219, 0);
        transform: scale(1.05);
    }
    100% { 
        box-shadow: 0 0 0 0 rgba(52, 152, 219, 0);
        transform: scale(1);
    }
}

.posicion-nueva {
    animation: highlightPosition 1.5s ease-in-out;
}

.tree-node-editing {
    background: linear-gradient(135deg, rgba(52, 152, 219, 0.1) 0%, rgba(41, 128, 185, 0.1) 100%);
    border-color: #3498db;
    box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
}

/* ========== TOOLTIP MEJORADO ========== */
.posicion-celda[title] {
    position: relative;
}

.posicion-celda[title]:hover::after {
    content: attr(title);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 0.8rem;
    white-space: pre-line;
    z-index: 1000;
    min-width: 200px;
    text-align: left;
    margin-bottom: 5px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.posicion-celda[title]:hover::before {
    content: '';
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    border: 5px solid transparent;
    border-top-color: rgba(0, 0, 0, 0.8);
    margin-bottom: -5px;
    z-index: 1000;
}

    /* ========== ESTILOS PARA EL ÁRBOL ========== */
    .tree-node-expander {
        cursor: pointer;
    }

    .tree-node-expanded .tree-node-expander i {
        transform: rotate(90deg) !important;
    }

    /* ========== RESPONSIVE ========== */
    @media (max-width: 768px) {
        .estanterias-grid {
            grid-template-columns: 1fr;
        }
        
        .estante-card {
            width: 280px;
        }
        
        .posiciones-grid {
            font-size: 0.6rem;
        }
        
        .posicion-numero {
            font-size: 0.6rem;
        }
    }

    /* ========== ANIMACIONES ========== */
    @keyframes highlightCell {
        0% { box-shadow: 0 0 0 0 rgba(52, 152, 219, 0.4); }
        70% { box-shadow: 0 0 0 10px rgba(52, 152, 219, 0); }
        100% { box-shadow: 0 0 0 0 rgba(52, 152, 219, 0); }
    }

    .posicion-nueva {
        animation: highlightCell 2s ease-in-out;
    }

    .tree-node-editing {
        background: linear-gradient(135deg, rgba(52, 152, 219, 0.1) 0%, rgba(41, 128, 185, 0.1) 100%);
        border-color: #3498db;
        box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
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
        estantes: 0,
        posiciones: 0
    };
    
    // Modo de visualización
    let modoVisualizacion = 'arbol'; // 'arbol' o 'cuadricula'
    
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
    
    // Cargar ubicaciones
    function cargarUbicaciones(sucursalId = '') {
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
            procesarDatosArbol(ubicaciones);
            
            if (modoVisualizacion === 'cuadricula') {
                renderizarCuadriculaEstanterias();
            } else {
                renderizarArbol();
            }
            
            actualizarEstadisticas();
            actualizarUltimaActualizacion();
            
            // Inicializar estado del árbol
            setTimeout(function() {
                inicializarEstadoArbol();
            }, 100);
        }, 'json');
    }
    
    // Procesar datos para el árbol
    function procesarDatosArbol(ubicaciones) {
    treeData = {};
    estadisticas = {
        sucursales: 0,
        secciones: 0,
        estanterias: 0,
        estantes: 0,
        posiciones: 0
    };
    
    ubicaciones.forEach(ubicacion => {
        const sucursalId = ubicacion.sucursal_id;
        const sucursalNombre = ubicacion.sucursal_nombre;
        const localidad = ubicacion.localidad || '';
        const seccion = ubicacion.seccion;
        const estanteria = ubicacion.estanteria;
        const estante = ubicacion.estante;
        const posicion = ubicacion.posicion || '01';
        
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
        
        if (!treeData[sucursalId].secciones[seccion]) {
            treeData[sucursalId].secciones[seccion] = {
                id: seccion,
                type: 'seccion',
                nombre: `Sección ${seccion}`,
                parentSucursalId: sucursalId,
                estanterias: {}
            };
            estadisticas.secciones++;
        }
        
        if (!treeData[sucursalId].secciones[seccion].estanterias[estanteria]) {
            treeData[sucursalId].secciones[seccion].estanterias[estanteria] = {
                id: estanteria,
                type: 'estanteria',
                nombre: `Estantería ${estanteria}`,
                parentSucursalId: sucursalId,
                parentSeccion: seccion,
                estantes: {}
            };
            estadisticas.estanterias++;
        }
        
        if (!treeData[sucursalId].secciones[seccion].estanterias[estanteria].estantes[estante]) {
            treeData[sucursalId].secciones[seccion].estanterias[estanteria].estantes[estante] = {
                id: estante,
                type: 'estante',
                nombre: `Estante ${estante}`,
                parentSucursalId: sucursalId,
                parentSeccion: seccion,
                parentEstanteria: estanteria,
                posiciones: {}
            };
            estadisticas.estantes++;
        }
        
        // AGREGAR POSICIONES
        if (!treeData[sucursalId].secciones[seccion].estanterias[estanteria].estantes[estante].posiciones[posicion]) {
            treeData[sucursalId].secciones[seccion].estanterias[estanteria].estantes[estante].posiciones[posicion] = {
                id: ubicacion.sucursal_ubicacion_id,
                type: 'posicion',
                nombre: `Posición ${posicion}`,
                descripcion: ubicacion.descripcion,
                estado: ubicacion.estado_info,
                botones: ubicacion.botones,
                sucursal_id: sucursalId,
                seccion: seccion,
                estanteria: estanteria,
                estante: estante,
                posicion: posicion,
                sucursal_nombre: sucursalNombre,
                localidad: localidad
            };
            estadisticas.posiciones++;
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
            // Inicializar con todas las sucursales expandidas por defecto
            Object.values(treeData).forEach(sucursal => {
                html += renderizarNodo(sucursal, 0, true);
            });
        }
        
        html += '</ul>';
        $('#treeContainer').html(html);
    }
    
    // Renderizar un nodo del árbol
   function renderizarNodo(nodo, nivel = 0, expandirInicial = false) {
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
        },
        'posicion': {
            icon: 'fas fa-cube',
            class: 'tree-node-posicion',
            title: 'Posición'
        }
    };
    
    const tipoInfo = tipos[nodo.type];
    const tieneHijos = nodo.type !== 'posicion';
    let html = '';
    
    // Determinar el ID según el tipo
    let nodoId = nodo.id;
    if (nodo.type === 'seccion' && nodo.parentSucursalId) {
        // Para secciones: sucursalId_seccion
        nodoId = `${nodo.parentSucursalId}_${nodo.id}`;
    } else if (nodo.type === 'estanteria' && nodo.parentSucursalId && nodo.parentSeccion) {
        // Para estanterías: sucursalId_seccion_estanteria
        nodoId = `${nodo.parentSucursalId}_${nodo.parentSeccion}_${nodo.id}`;
    } else if (nodo.type === 'estante' && nodo.parentSucursalId && nodo.parentSeccion && nodo.parentEstanteria) {
        // Para estantes: sucursalId_seccion_estanteria_estante
        nodoId = `${nodo.parentSucursalId}_${nodo.parentSeccion}_${nodo.parentEstanteria}_${nodo.id}`;
    }
    
    html += `<li class="tree-node ${tipoInfo.class}" data-id="${nodoId}" data-type="${nodo.type}">`;
        html += `<div class="tree-node-content">`;
        
        // Expander (SOLO si tiene hijos)
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
        } else if (nodo.type === 'seccion') {
            html += `<div class="tree-node-subtitle">
                        <i class="fas fa-layer-group fa-xs me-1"></i>Sección
                    </div>`;
        } else if (nodo.type === 'estanteria') {
            html += `<div class="tree-node-subtitle">
                        <i class="fas fa-th-large fa-xs me-1"></i>Estantería
                    </div>`;
        } else if (nodo.type === 'posicion' && nodo.descripcion) {
            html += `<div class="tree-node-subtitle">${nodo.descripcion}</div>`;
        }
        html += `</div>`;
        
        // Detalles al costado
        html += `<div class="tree-node-details">`;
        
        if (nodo.type === 'posicion') {
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
            html += `<div class="tree-node-detail">
                        <i class="fas fa-cube"></i>
                        <span>${nodo.posicion || ''}</span>
                     </div>`;
            
            const estado = nodo.estado?.estado_registro || 'Sin estado';
            const estadoColor = getEstadoColor(nodo.estado);
            html += `<span class="badge badge-compact bg-${estadoColor}">${estado}</span>`;
        } else if (nodo.type === 'estante') {
            html += `<div class="tree-node-detail">
                        <i class="fas fa-layer-group"></i>
                        <span>Sección</span>
                     </div>`;
            html += `<div class="tree-node-detail">
                        <i class="fas fa-th-large"></i>
                        <span>Estantería</span>
                     </div>`;
        } else if (nodo.type === 'estanteria') {
            html += `<div class="tree-node-detail">
                        <i class="fas fa-layer-group"></i>
                        <span>Sección</span>
                     </div>`;
        }
        
        html += `</div>`;
        html += `</div>`; // Cierre de tree-node-info
        
        // Acciones
        html += `<div class="tree-node-actions">`;
        
        if (nodo.type === 'posicion') {
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
                // Si es sucursal y estamos expandiendo inicialmente, mostrar hijos
                const mostrarHijos = (nodo.type === 'sucursal' && expandirInicial) ? '' : ' style="display: none;"';
                html += `<ul class="tree-children" ${mostrarHijos}>`;
                Object.values(children).forEach(child => {
                    // Solo expandir sucursales por defecto
                    const expandirHijo = (nodo.type === 'sucursal' && expandirInicial);
                    html += renderizarNodo(child, nivel + 1, expandirHijo);
                });
                html += `</ul>`;
            }
        }
        
        html += `</li>`;
        return html;
    }
    
    // ==================== EXPANSION DE NODOS DEL ARBOL ====================

    // Manejar expansión/colapso de nodos del árbol
    $(document).on('click', '.tree-node-expander', function(e){
        e.stopPropagation();
        
        const $expander = $(this);
        const $node = $expander.closest('.tree-node');
        const $children = $node.find('.tree-children');
        const $icon = $expander.find('i');
        
        if ($children.length > 0) {
            if ($children.is(':visible')) {
                // Colapsar
                $children.slideUp(300);
                $node.removeClass('tree-node-expanded').addClass('tree-node-collapsed');
                $icon.css('transform', 'rotate(0deg)');
            } else {
                // Expandir
                $children.slideDown(300);
                $node.removeClass('tree-node-collapsed').addClass('tree-node-expanded');
                $icon.css('transform', 'rotate(90deg)');
            }
        }
    });

    $(document).on('click', '#btnClearSearch', function() {
        $('#searchTree').val('').trigger('input');
    });

    // Doble clic en nodo para expandir/colapsar
    $(document).on('dblclick', '.tree-node-content', function(e) {
        if (!$(e.target).closest('.tree-node-action').length) {
            const $expander = $(this).find('.tree-node-expander');
            if ($expander.length > 0) {
                $expander.trigger('click');
            }
        }
    });

    // Inicializar estado del árbol después de renderizar
    function inicializarEstadoArbol() {
        $('.tree-node').each(function() {
            const $node = $(this);
            const $children = $node.find('.tree-children');
            const $expander = $node.find('.tree-node-expander');
            const $icon = $expander.find('i');
            
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
    
    // Renderizar cuadrícula de estanterías
    function renderizarCuadriculaEstanterias() {
        let html = '';
        
        if (Object.keys(treeData).length === 0) {
            html += `
                <div class="text-center p-5">
                    <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">No hay estanterías registradas</h5>
                    <p class="text-muted">Crea tu primera estantería para ver la vista en cuadrícula</p>
                    <button class="btn btn-modern-primary" id="btnPrimeraEstanteria">
                        <i class="fas fa-plus me-1"></i>Crear Primera Estantería
                    </button>
                </div>
            `;
        } else {
            // Controles de visualización
            html += `
                <div class="mb-4">
                    <div class="btn-group" role="group">
                        <button type="button" class="btn ${modoVisualizacion === 'arbol' ? 'btn-primary' : 'btn-outline-primary'}" id="btnModoArbol">
                            <i class="fas fa-sitemap me-1"></i> Vista Árbol
                        </button>
                        <button type="button" class="btn ${modoVisualizacion === 'cuadricula' ? 'btn-primary' : 'btn-outline-primary'}" id="btnModoCuadricula">
                            <i class="fas fa-th me-1"></i> Vista Cuadrícula
                        </button>
                    </div>
                </div>
                <div class="estanterias-grid">
            `;
            
            // Recorrer todas las estanterías
            Object.values(treeData).forEach(sucursal => {
                Object.values(sucursal.secciones).forEach(seccion => {
                    Object.values(seccion.estanterias).forEach(estanteria => {
                        html += renderizarEstanteriaCuadricula(estanteria, seccion, sucursal);
                    });
                });
            });
            
            html += '</div>';
        }
        
        $('#treeContainer').html(html);
    }
    
    // Renderizar una estantería en cuadrícula
    function renderizarEstanteriaCuadricula(estanteria, seccion, sucursal) {
        const estanteriaId = estanteria.id;
        const seccionId = seccion.id;
        const sucursalId = sucursal.id;
        
        let html = `
            <div class="estanteria-card card-modern">
                <div class="card-header card-header-modern">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">
                                <i class="fas fa-th-large me-2"></i>Estantería ${estanteriaId}
                            </h6>
                            <small class="text-white">Sección ${seccionId} - ${sucursal.nombre}</small>
                        </div>
                        <div>
                            <button class="btn btn-sm btn-modern-outline btn-agregar-estante"
                                    data-sucursal="${sucursalId}"
                                    data-seccion="${seccionId}"
                                    data-estanteria="${estanteriaId}">
                                <i class="fas fa-plus-circle me-1"></i>Agregar Estante
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-2">
                    <div class="estantes-grid">
        `;
        
        // Renderizar estantes en una fila (columnas)
        Object.values(estanteria.estantes).forEach(estante => {
            html += renderizarEstanteCuadricula(estante, estanteria, seccion, sucursal);
        });
        
        html += `
                    </div>
                </div>
            </div>
        `;
        
        return html;
    }
    
    // Renderizar un estante en cuadrícula (POSICIONES COMO COLUMNAS)
    // Renderizar un estante en cuadrícula (POSICIONES COMO COLUMNAS) - CORREGIDO
        function renderizarEstanteCuadricula(estante, estanteria, seccion, sucursal) {
            const estanteNumero = estante.id.split('_').pop().replace('Estante ', '');
            const estanteId = estante.id;
            
            // Determinar número máximo de filas basado en posiciones existentes
            let maxFila = 0;
            Object.keys(estante.posiciones).forEach(pos => {
                // Extraer número de la posición (ej: "1A" -> 1, "2B" -> 2)
                const numero = parseInt(pos.match(/\d+/)[0]);
                if (numero > maxFila) maxFila = numero;
            });
            
            // Mínimo 4 filas, máximo según posiciones existentes
            const filas = Math.max(4, maxFila);
            const columnas = 4; // Columnas A, B, C, D
            const letras = ['A', 'B', 'C', 'D'];
            
            let html = `
                <div class="estante-card">
                    <div class="estante-header">
                        <h6 class="mb-0">
                            <i class="fas fa-shelves me-1"></i>Estante ${estanteNumero}
                        </h6>
                        <button class="btn btn-sm btn-modern-outline btn-agregar-posicion-estante"
                                data-parent-id="${estanteId}"
                                data-parent-type="estante"
                                title="Agregar nueva posición">
                            <i class="fas fa-plus"></i> Posición
                        </button>
                    </div>
                    <div class="posiciones-cuadricula">
                        <div class="posiciones-cabecera">
                            <div class="celda-vacia"></div>
            `;
            
            // Encabezados de columnas (A, B, C, D)
            for (let col = 0; col < columnas; col++) {
                html += `<div class="columna-cabecera">${letras[col]}</div>`;
            }
            
            html += `</div>`;
            
            // Filas con posiciones
           for (let fila = 1; fila <= filas; fila++) {
                html += `<div class="posiciones-fila">`;
                html += `<div class="fila-cabecera">${fila}</div>`; // Número de fila
                
                for (let col = 0; col < columnas; col++) {
                    const posicionId = `${fila}${letras[col]}`; // Combinación: 1A, 1B, etc.
                    const posicion = estante.posiciones[posicionId];
                    
                    if (posicion) {
                        // Posición ocupada
                        const estadoColor = getEstadoColor(posicion.estado);
                        const descripcion = posicion.descripcion ? posicion.descripcion : 'Sin descripción';
                        const sucursalNombre = posicion.sucursal_nombre || sucursal.nombre;
                        const localidad = posicion.localidad || '';
                        
                        html += `
                            <div class="posicion-celda posicion-ocupada" 
                                data-id="${posicion.id}"
                                title="Posición: ${posicionId}
        Sucursal: ${sucursalNombre}${localidad ? ' (' + localidad + ')' : ''}
        Sección: ${posicion.seccion}
        Estantería: ${posicion.estanteria}
        Estante: ${posicion.estante}
        Estado: ${posicion.estado?.estado_registro || 'Sin estado'}
        ${descripcion}">
                                <div class="posicion-contenido">
                                    <div class="posicion-numero">${posicionId}</div>
                                    <div class="posicion-estado bg-${estadoColor}"></div>
                                    <div class="posicion-acciones">
                                        <button class="btn btn-sm btn-editar-posicion" 
                                                data-id="${posicion.id}"
                                                title="Editar posición ${posicionId}">
                                            <i class="fas fa-edit fa-xs"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    } else {
                        // Posición vacía - AÑADIR DATOS PARA CREAR NUEVA POSICIÓN
                        html += `
                            <div class="posicion-celda posicion-vacia btn-crear-posicion"
                                data-sucursal="${sucursal.id}"
                                data-seccion="${seccion.id}"
                                data-estanteria="${estanteria.id}"
                                data-estante="${estanteNumero}"
                                data-posicion="${posicionId}"
                                title="Crear posición ${posicionId} en:
        Sucursal: ${sucursal.nombre}${sucursal.localidad ? ' (' + sucursal.localidad + ')' : ''}
        Sección: ${seccion.id}
        Estantería: ${estanteria.id}
        Estante: ${estanteNumero}">
                                <div class="posicion-contenido">
                                    <div class="posicion-numero">${posicionId}</div>
                                    <div class="posicion-vacia-icono">
                                        <i class="fas fa-plus-circle"></i>
                                    </div>
                                </div>
                            </div>
                        `;
                    }
                }
                
                html += `</div>`;
            }
            
            // Agregar contador de posiciones ocupadas/vacías
            const posicionesOcupadas = Object.keys(estante.posiciones).length;
            const totalPosiciones = filas * columnas;
            
            html += `
                </div>
                <div class="estante-footer mt-3">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        ${posicionesOcupadas}/${totalPosiciones} posiciones ocupadas
                    </small>
                </div>
            </div>
            `;
            
            return html;
        }
    
    // Obtener el tipo de hijo
    function getChildType(parentType) {
        const hierarchy = {
            'sucursal': 'seccion',
            'seccion': 'estanteria',
            'estanteria': 'estante',
            'estante': 'posicion'
        };
        return hierarchy[parentType] || null;
    }
    
    // Obtener hijos de un nodo
    function getChildren(nodo, childType) {
        switch (childType) {
            case 'seccion': return nodo.secciones || {};
            case 'estanteria': return nodo.estanterias || {};
            case 'estante': return nodo.estantes || {};
            case 'posicion': return nodo.posiciones || {};
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
    
    // Actualizar estadísticas
    function actualizarEstadisticas() {
        $('#totalSucursales').text(estadisticas.sucursales);
        $('#totalSecciones').text(estadisticas.secciones);
        $('#totalEstanterias').text(estadisticas.estanterias);
        $('#totalEstantes').text(estadisticas.estantes);
        $('#totalPosiciones').text(estadisticas.posiciones);
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
    
    // Cargar valores por defecto según el padre
    function cargarValoresPorDefecto(parentType, parentId) {
        return new Promise((resolve, reject) => {
            $.get('sucursales_ubicaciones_ajax.php', {
                accion: 'obtener_valores_por_defecto',
                parent_type: parentType,
                parent_id: parentId,
                empresa_idx: empresa_idx
            }, function(valores){
                resolve(valores);
            }, 'json').fail(reject);
        });
    }
    
    // ==================== EVENT HANDLERS ====================
    
    // Expandir todo - MODIFICADO
    $('#btnExpandAll').click(function() {
        $('.tree-children').slideDown(300);
        $('.tree-node').each(function() {
            const $node = $(this);
            const $expander = $node.find('.tree-node-expander');
            const $icon = $expander.find('i');
            
            $node.removeClass('tree-node-collapsed').addClass('tree-node-expanded');
            $icon.css('transform', 'rotate(90deg)');
        });
    });

    // Contraer todo - MODIFICADO
    $('#btnCollapseAll').click(function() {
        $('.tree-children').slideUp(300);
        $('.tree-node').each(function() {
            const $node = $(this);
            const $expander = $node.find('.tree-node-expander');
            const $icon = $expander.find('i');
            
            $node.removeClass('tree-node-expanded').addClass('tree-node-collapsed');
            $icon.css('transform', 'rotate(0deg)');
        });
    });
    
    // Manejar agregar hijo desde árbol
    $(document).on('click', '.btn-agregar-hijo, .btn-agregar-posicion-estante', function(e){
        e.stopPropagation();
        const parentId = $(this).data('parent-id') || $(this).data('id');
        const parentType = $(this).data('parent-type') || $(this).data('type');
        
        console.log('Agregando desde:', parentType, parentId);
        
        // Cargar valores por defecto del padre
        cargarValoresPorDefecto(parentType, parentId).then(function(valores){
            console.log('Valores por defecto:', valores);
            
            resetModal();
            $('#parent_type').val(parentType);
            $('#parent_id').val(parentId);
            
            // Llenar campos con valores por defecto
            if (valores.sucursal_id) {
                $('#sucursal_id').val(valores.sucursal_id);
            }
            if (valores.seccion) {
                $('#seccion').val(valores.seccion);
            }
            if (valores.estanteria) {
                $('#estanteria').val(valores.estanteria);
            }
            if (valores.estante) {
                $('#estante').val(valores.estante);
            }
            if (valores.posicion) {
                $('#posicion').val(valores.posicion);
            }
            
            // Enfocar el campo correspondiente al siguiente nivel
            const nextField = getChildType(parentType);
            if (nextField && $('#' + nextField).val() === '') {
                $('#' + nextField).focus();
            } else if (parentType === 'estante') {
                $('#posicion').focus();
            }
            
            $('#modalLabel').html('<i class="fas fa-map-marker-alt me-2"></i>Nueva Ubicación');
            $('#modalSubtitle').text(`Agregar ${getChildType(parentType) || 'posición'} a ${parentType} seleccionado`);
            cargarEstados();
            
            var modal = new bootstrap.Modal(document.getElementById('modalSucursalUbicacion'));
            modal.show();
            
            actualizarRutaCompleta();
        }).catch(function(error){
            console.error('Error cargando valores por defecto:', error);
            // Si falla, abrir modal sin valores por defecto
            abrirModalNuevaUbicacion(parentType, parentId);
        });
    });
    
    // Manejar clic en nodo (para editar posición)
    $(document).on('click', '.tree-node-content', function(e) {
        if (!$(e.target).closest('.tree-node-action').length && !$(e.target).closest('.tree-node-expander').length) {
            const $node = $(this).closest('.tree-node');
            const type = $node.data('type');
            const id = $node.data('id');
            
            if (type === 'posicion') {
                cargarUbicacionParaEditar(id);
            }
        }
    });
    
    // Función auxiliar para abrir modal
    function abrirModalNuevaUbicacion(parentType, parentId) {
        resetModal();
        $('#parent_type').val(parentType);
        $('#parent_id').val(parentId);
        
        $('#modalLabel').html('<i class="fas fa-map-marker-alt me-2"></i>Nueva Ubicación');
        $('#modalSubtitle').text(`Agregar ${getChildType(parentType)}`);
        cargarEstados();
        
        var modal = new bootstrap.Modal(document.getElementById('modalSucursalUbicacion'));
        modal.show();
        
        actualizarRutaCompleta();
    }
    
    // Manejar agregar desde cuadrícula
    $(document).on('click', '.btn-crear-posicion, .btn-agregar-posicion-estante, .btn-agregar-estante', function(e){
        e.stopPropagation();
        
        const sucursalId = $(this).data('sucursal');
        const seccion = $(this).data('seccion');
        const estanteria = $(this).data('estanteria');
        const estante = $(this).data('estante');
        const posicion = $(this).data('posicion') || '01';
        
        resetModal();
        
        // Llenar campos con los valores proporcionados
        $('#sucursal_id').val(sucursalId);
        if (seccion) $('#seccion').val(seccion);
        if (estanteria) $('#estanteria').val(estanteria);
        if (estante) $('#estante').val(estante);
        if (posicion) $('#posicion').val(posicion);
        
        $('#modalLabel').html('<i class="fas fa-map-marker-alt me-2"></i>Nueva Ubicación');
        $('#modalSubtitle').text('Crear nueva ubicación');
        cargarEstados();
        
        var modal = new bootstrap.Modal(document.getElementById('modalSucursalUbicacion'));
        modal.show();
        
        actualizarRutaCompleta();
    });
    
    // Manejar editar desde cuadrícula
    $(document).on('click', '.btn-editar-posicion, .btn-editar', function(e){
        e.stopPropagation();
        const id = $(this).data('id');
        cargarUbicacionParaEditar(id);
    });
    
    // Cambiar modo de visualización
    $(document).on('click', '#btnModoArbol', function(){
        modoVisualizacion = 'arbol';
        $(this).removeClass('btn-outline-primary').addClass('btn-primary');
        $('#btnModoCuadricula').removeClass('btn-primary').addClass('btn-outline-primary');
        renderizarArbol();
    });
    
    $(document).on('click', '#btnModoCuadricula', function(){
        modoVisualizacion = 'cuadricula';
        $(this).removeClass('btn-outline-primary').addClass('btn-primary');
        $('#btnModoArbol').removeClass('btn-primary').addClass('btn-outline-primary');
        renderizarCuadriculaEstanterias();
    });
    
    // Buscar en el árbol
    $('#searchTree').on('input', function() {
        const searchTerm = $(this).val().toLowerCase().trim();
        
        if (modoVisualizacion === 'arbol') {
            // Filtro para vista árbol
            filtrarArbol(searchTerm);
        } else {
            // Filtro para vista cuadrícula
            filtrarCuadricula(searchTerm);
        }
    });
    // Función para filtrar la cuadrícula
    function filtrarCuadricula(searchTerm) {
        if (searchTerm.length > 0) {
            // Ocultar todas las tarjetas
            $('.estanteria-card, .estante-card').hide();
            
            // Mostrar solo las que coincidan
            $('.estante-card').each(function() {
                const $card = $(this);
                const text = $card.text().toLowerCase();
                
                if (text.includes(searchTerm)) {
                    $card.show();
                    // Mostrar también la estantería padre
                    $card.closest('.estanteria-card').show();
                }
            });
            
            $('.estanteria-card').each(function() {
                const $estanteria = $(this);
                const text = $estanteria.text().toLowerCase();
                
                if (text.includes(searchTerm)) {
                    $estanteria.show();
                    // Mostrar todos sus estantes
                    $estanteria.find('.estante-card').show();
                }
            });
            
            // Mostrar mensaje si no hay resultados
            const visibleEstanterias = $('.estanteria-card:visible').length;
            if (visibleEstanterias === 0) {
                $('#treeContainer').append(`
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        No se encontraron estanterías que coincidan con "${searchTerm}"
                    </div>
                `);
            }
        } else {
            // Mostrar todo
            $('.estanteria-card, .estante-card').show();
            $('.alert').remove();
        }
    }
    // Función para filtrar el árbol
    function filtrarArbol(searchTerm) {
        $('.tree-node').removeClass('tree-node-highlight').show();
        
        if (searchTerm.length > 0) {
            // Ocultar todos los nodos primero
            $('.tree-node').hide();
            
            // Mostrar solo los nodos que coincidan y sus ancestros
            $('.tree-node').each(function() {
                const $node = $(this);
                const text = $node.text().toLowerCase();
                
                if (text.includes(searchTerm)) {
                    $node.addClass('tree-node-highlight').show();
                    
                    // Mostrar todos los ancestros
                    $node.parentsUntil('.tree', '.tree-node').show();
                    
                    // Expandir padres
                    $node.parentsUntil('.tree', '.tree-node').each(function() {
                        $(this).addClass('tree-node-expanded');
                        $(this).find('.tree-children').show();
                        $(this).find('.tree-node-expander i').css('transform', 'rotate(90deg)');
                    });
                    
                    // Mostrar hijos inmediatos
                    $node.find('.tree-children').show();
                    $node.find('.tree-children .tree-node').show();
                }
            });
            
            // Mostrar mensaje si no hay resultados
            const visibleNodes = $('.tree-node:visible').length;
            if (visibleNodes === 0) {
                $('#treeContainer').append(`
                    <div class="alert alert-info mt-3">
                        <i class="fas fa-info-circle me-2"></i>
                        No se encontraron ubicaciones que coincidan con "${searchTerm}"
                    </div>
                `);
            }
        } else {
            // Si no hay término de búsqueda, mostrar todo
            $('.tree-node').show();
            $('.tree-children').show();
            $('.tree-node-expander i').css('transform', 'rotate(90deg)');
            $('.tree-node').addClass('tree-node-expanded');
            $('.alert').remove(); // Remover mensajes anteriores
        }
    }

    // Actualizar ruta completa en el modal
    function actualizarRutaCompleta() {
        const sucursalId = $('#sucursal_id').val();
        const seccion = $('#seccion').val();
        const estanteria = $('#estanteria').val();
        const estante = $('#estante').val();
        const posicion = $('#posicion').val();
        
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
        
        if (posicion) {
            ruta += ` &nbsp;&nbsp;<i class="fas fa-arrow-right text-muted"></i>&nbsp;&nbsp; <strong>Posición ${posicion}</strong>`;
        }
        
        $('#fullPath').html(ruta || '<span class="text-muted">Seleccione los datos para ver la ruta completa</span>');
    }
    
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
                
                setTimeout(() => {
                    $('#sucursal_id').val(res.sucursal_id);
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
    $(document).on('click', '#btnNuevo, #btnPrimeraUbicacion, #btnPrimeraEstanteria', function(e){
        e.preventDefault();
        resetModal();
        $('#modalLabel').html('<i class="fas fa-map-marker-alt me-2"></i>Nueva Ubicación');
        $('#modalSubtitle').text('Crear una nueva ubicación desde cero');
        
        cargarEstados();
        
        var modal = new bootstrap.Modal(document.getElementById('modalSucursalUbicacion'));
        modal.show();
        $('#seccion').focus();
    });
    
    // Manejador del filtro de sucursal
    $(document).on('change', '#filterSucursal', function() {
        cargarUbicaciones($(this).val());
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
        var posicion = $('#posicion').val().trim();
        
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
        
        if (!posicion) {
            $('#posicion').addClass('is-invalid');
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
                posicion: posicion,
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
                    
                    // Recargar las ubicaciones
                    const sucursalId = $('#filterSucursal').val();
                    cargarUbicaciones(sucursalId);
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
            cargarUbicaciones();
            cargarEstados();
            cargarBotonAgregar();
            
            // Agregar botones de cambio de vista en el header
            const $cardHeader = $('.card-header-modern:first');
            if ($cardHeader.length) {
                $cardHeader.find('.d-flex').append(`
                    <div class="btn-group btn-group-sm ms-2" role="group">
                        <button type="button" class="btn ${modoVisualizacion === 'arbol' ? 'btn-light' : 'btn-outline-light'}" id="btnModoArbolHeader">
                            <i class="fas fa-sitemap"></i>
                        </button>
                        <button type="button" class="btn ${modoVisualizacion === 'cuadricula' ? 'btn-light' : 'btn-outline-light'}" id="btnModoCuadriculaHeader">
                            <i class="fas fa-th"></i>
                        </button>
                    </div>
                `);
            }
            
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
    
    // Manejar botones de cambio de vista en el header
    $(document).on('click', '#btnModoArbolHeader', function(){
        modoVisualizacion = 'arbol';
        $(this).removeClass('btn-outline-light').addClass('btn-light');
        $('#btnModoCuadriculaHeader').removeClass('btn-light').addClass('btn-outline-light');
        renderizarArbol();
    });
    
    $(document).on('click', '#btnModoCuadriculaHeader', function(){
        modoVisualizacion = 'cuadricula';
        $(this).removeClass('btn-outline-light').addClass('btn-light');
        $('#btnModoArbolHeader').removeClass('btn-light').addClass('btn-outline-light');
        renderizarCuadriculaEstanterias();
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