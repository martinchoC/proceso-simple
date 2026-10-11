<?php
// Consulta de productos: misma pantalla que productos.php pero SOLO LECTURA.
// No hay alta, edición, cambios de estado ni ABM de compatibilidad, imágenes,
// ubicaciones, proveedores o precios. Todas las llamadas van a
// productos_consulta_ajax.php, que solo acepta acciones de lectura.
// Configuración de la página
require_once __DIR__ . '/../../db.php';
require_once "productos_model.php";

$pageTitle = "Consulta de Productos";
$currentPage = 'productos_consulta';
$modudo_idx = 2;
$pagina_idx = 98; // ID de página en conf__paginas (ver sql/mantenimiento/20260926_productos_consulta.sql)
$empresa_idx = intval($_GET['empresa_id'] ?? $_SESSION['empresa_id'] ?? 0);

// Las listas de precios se traen acá (en el render de la página) y no por AJAX
// al cargar, para no sumar una vuelta de red extra antes de poder pedir los
// productos: el DataTable arranca directo con esto ya resuelto.
$listas_precios_iniciales = obtenerListasPrecios($conexion, $empresa_idx);

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">
                        <i class="fas fa-search me-2"></i>Consulta de Productos
                    </h3>
                </div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item"><a href="#">Gestión</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Consulta de Productos</li>
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
                                                    <li>
                                                        <hr class="dropdown-divider">
                                                    </li>
                                                    <li><a class="dropdown-item" href="exportar_productos_offline.php" target="_blank"><i
                                                                class="fas fa-paper-plane text-info"></i> Catálogo Offline & Enviar Email</a>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="card-body">
                                        <!-- Filtros -->
                                        <div class="row g-2 mb-3">
                                            <div class="col-md">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text"><i class="fas fa-tag"></i></span>
                                                    <input type="text" class="form-control" id="filtroCodigo" placeholder="Código...">
                                                    <span class="input-group-text" id="codigoSearchIcon" style="display:none;"><i class="fas fa-search text-primary"></i></span>
                                                </div>
                                            </div>
                                            <div class="col-md">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text"><i class="fas fa-toggle-on"></i></span>
                                                    <select class="form-select" id="filtroEstado" title="Filtrar por estado">
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text"><i class="fas fa-trademark"></i></span>
                                                    <select class="form-select" id="filtroMarca">
                                                        <option value="">Todas las marcas</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text"><i class="fas fa-car"></i></span>
                                                    <select class="form-select" id="filtroModelo" disabled>
                                                        <option value="">Todos los modelos</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text"><i class="fas fa-cube"></i></span>
                                                    <select class="form-select" id="filtroSubmodelo" disabled>
                                                        <option value="">Todos los submodelos</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Buscador por etiquetas (tags) -->
                                        <div class="row mb-3">
                                            <div class="col-md-12">
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text"><i class="fas fa-search"></i></span>
                                                    <div class="form-control p-1" id="buscadorTagsContainer" style="min-height: 38px; display: flex; flex-wrap: wrap; align-items: center; gap: 4px; cursor: text;">
                                                        <input type="text" id="buscadorTagsInput" class="border-0 flex-grow-1" style="min-width: 100px; outline: none; padding: 4px 8px; font-size: 14px;" placeholder="Escribí una palabra y presioná espacio...">
                                                    </div>
                                                    <button type="button" class="btn btn-outline-secondary" id="btnLimpiarTags" title="Limpiar todos los filtros">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- DataTable -->
                                        <table id="tablaProductos" class="table table-striped table-bordered"
                                            style="width:100%">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="100">Código</th>
                                                    <th>Nombre</th>
                                                    <th width="150">Marcas</th>
                                                    <th width="150">Modelos</th>
                                                    <th width="150">Submodelos</th>
                                                    <th width="100">Años</th>
                                                    <th width="200">Ubicaciones</th>
                                                    <th width="140">Cód. Proveedor</th>
                                                    <th width="80">Imagen</th>
                                                    <!-- Las columnas de cada lista de precios se agregan acá dinámicamente por JS (ver cargarListasPreciosYTabla) -->
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

            <!-- ========== MODALES ========== -->
            <!-- Modal para consultar producto (solo lectura) -->
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
                                    <button class="nav-link" id="nav-precios-tab" data-bs-toggle="tab"
                                        data-bs-target="#nav-precios" type="button" role="tab"
                                        aria-controls="nav-precios" aria-selected="false">
                                        <i class="fas fa-dollar-sign me-2"></i>Precios
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
                                                <input type="text" class="form-control form-control-sm"
                                                    id="producto_codigo" name="producto_codigo" maxlength="50"
                                                    placeholder="Código *" required>
                                                <div class="invalid-feedback">El código es obligatorio</div>
                                            </div>
                                            <div class="col-md-4">
                                                <input type="text" class="form-control form-control-sm"
                                                    id="codigo_barras" name="codigo_barras" maxlength="150"
                                                    placeholder="Código de Barras">
                                            </div>
                                            <div class="col-md-4">
                                                <select class="form-select form-select-sm" id="producto_tipo_id"
                                                    name="producto_tipo_id" required>
                                                    <option value="">Tipo *</option>
                                                </select>
                                                <div class="invalid-feedback">Seleccione un tipo</div>
                                            </div>
                                        </div>

                                        <div class="row g-2 mt-1">
                                            <div class="col-md-12">
                                                <input type="text" class="form-control form-control-sm"
                                                    id="producto_nombre" name="producto_nombre" maxlength="150"
                                                    placeholder="Nombre *" required>
                                                <div class="invalid-feedback">El nombre es obligatorio</div>
                                            </div>
                                        </div>

                                        <div class="row g-2 mt-1">
                                            <div class="col-md-12">
                                                <label for="producto_descripcion" class="form-label form-label-sm">Descripción
                                                    Extendida</label>
                                                <textarea class="form-control form-control-sm" id="producto_descripcion"
                                                    name="producto_descripcion" rows="2" style="min-height: 62px; resize: vertical;"></textarea>
                                            </div>
                                        </div>

                                        <div class="row g-2 mt-1">
                                            <div class="col-md-6">
                                                <select class="form-select form-select-sm" id="unidad_medida_id"
                                                    name="unidad_medida_id">
                                                    <option value="">Unidad de Medida</option>
                                                </select>
                                            </div>
                                        </div>

                                        <div class="row g-2 mt-1">
                                            <div class="col-md-4">
                                                <input type="text" class="form-control form-control-sm" id="lado"
                                                    name="lado" maxlength="10" placeholder="Lado">
                                            </div>
                                            <div class="col-md-4">
                                                <input type="text" class="form-control form-control-sm" id="material"
                                                    name="material" maxlength="50" placeholder="Material">
                                            </div>
                                            <div class="col-md-4">
                                                <input type="text" class="form-control form-control-sm" id="color"
                                                    name="color" maxlength="50" placeholder="Color">
                                            </div>
                                        </div>

                                        <div class="row g-2 mt-1">
                                            <div class="col-md-6">
                                                <input type="number" class="form-control form-control-sm" id="peso"
                                                    name="peso" min="0" step="0.01" placeholder="Peso (kg)">
                                            </div>
                                            <div class="col-md-6">
                                                <input type="text" class="form-control form-control-sm" id="garantia"
                                                    name="garantia" maxlength="50" placeholder="Garantía">
                                            </div>
                                        </div>
                                        <div class="row g-2 mt-1 align-items-center">
                                            <div class="col-md-4">
                                                <small class="text-muted" title="Los servicios y productos sin control de stock se administran desde Productos - Generales">
                                                    <i class="fas fa-boxes me-1"></i>Controla stock (siempre)
                                                </small>
                                            </div>
                                            <div class="col-md-4">
                                                <select class="form-select form-select-sm" id="iva_alicuota_id" name="iva_alicuota_id">
                                                    <option value="">IVA Alícuota</option>
                                                </select>
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
                                    </div>
                                    <div class="table-responsive" style="max-height: 300px;">
                                        <table id="tablaCompatibilidad" class="table table-sm table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="25%" class="py-1">Marca</th>
                                                    <th width="25%" class="py-1">Modelo</th>
                                                    <th width="25%" class="py-1">Submodelo</th>
                                                    <th width="15%" class="py-1 text-center">Años</th>
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
                                    </div>
                                    <div id="galeriaImagenes" class="row g-2">
                                        <div class="col-12 text-center py-5" id="sinImagenes">
                                            <i class="fas fa-image fa-3x text-muted mb-3"></i>
                                            <p class="text-muted">No hay imágenes para este producto</p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pestaña de Ubicaciones -->
                                <div class="tab-pane fade" id="nav-ubicaciones" role="tabpanel"
                                    aria-labelledby="nav-ubicaciones-tab">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <input type="hidden" id="producto_ubicacion_id_editar" name="producto_ubicacion_id_editar" value="" />
                                        <h6 class="mb-0">
                                            <i class="fas fa-map-marker-alt me-2 text-warning"></i>Ubicaciones del
                                            Producto
                                        </h6>
                                    </div>
                                    <!-- Tabla de ubicaciones - VERSIÓN MEJORADA CON SCROLL -->
                                    <div class="tabla-ubicaciones-container">
                                        <table id="tablaUbicaciones" class="table table-hover mb-0" style="width: 100%;">
                                            <thead class="table-light" style="position: sticky; top: 0; z-index: 10;">
                                                <tr>
                                                    <th style="width: 10%;">Sucursal</th>
                                                    <th style="width: 10%;">Boca</th>
                                                    <th style="width: 15%;">Sección</th>
                                                    <th style="width: 15%;">Estantería</th>
                                                    <th style="width: 10%;">Estante</th>
                                                    <th style="width: 10%;">Posición</th>
                                                    <th style="width: 20%;">Descripción</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Pestaña de Proveedores -->
                                <div class="tab-pane fade" id="nav-proveedores" role="tabpanel"
                                    aria-labelledby="nav-proveedores-tab">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">
                                            <i class="fas fa-truck me-2 text-info"></i>Proveedores del Producto
                                        </h6>
                                    </div>
                                    <div class="table-responsive" style="max-height: 300px;">
                                        <table id="tablaProveedores" class="table table-sm table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="35%" class="py-1">Proveedor</th>
                                                    <th width="20%" class="py-1">CUIT</th>
                                                    <th width="30%" class="py-1">Código de Proveedor</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Pestaña de Precios -->
                                <div class="tab-pane fade" id="nav-precios" role="tabpanel"
                                    aria-labelledby="nav-precios-tab">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0">
                                            <i class="fas fa-dollar-sign me-2 text-success"></i>Precios del Producto
                                        </h6>
                                    </div>
                                    <div class="table-responsive" style="max-height: 300px;">
                                        <table id="tablaPreciosProducto" class="table table-sm table-hover mb-0">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="30%" class="py-1">Lista</th>
                                                    <th width="20%" class="py-1 text-end">Precio Unitario</th>
                                                    <th width="20%" class="py-1 text-end">Precio c/IVA</th>
                                                    <th width="15%" class="py-1">Actualización</th>
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
                                <i class="fas fa-times me-1"></i>Cerrar
                            </button>
                        </div>
                    </div>
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
                    <div class="text-center py-5">
                        <i class="fas fa-spinner fa-spin fa-3x text-primary mb-3"></i>
                        <p class="text-muted">Cargando imágenes...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ========== ESTILOS ========== -->
    <style>
        /* Pie del listado: info centrada, paginado alineado a la derecha */
        #tablaProductos_wrapper .dataTables_info { text-align: center; }
        #tablaProductos_wrapper .dataTables_paginate .pagination { justify-content: flex-end; }

        /* Estilos para el buscador de etiquetas */
        .tag-item {
            display: inline-flex;
            align-items: center;
            background-color: #e9ecef;
            border-radius: 16px;
            padding: 2px 8px 2px 12px;
            font-size: 13px;
            font-weight: 500;
            color: #212529;
            transition: all 0.2s;
            margin: 2px 2px;
            white-space: nowrap;
            max-width: 200px;
            border: 1px solid #dee2e6;
        }

        .tag-item:hover {
            background-color: #dee2e6;
        }

        .tag-item .tag-remove {
            cursor: pointer;
            margin-left: 6px;
            font-size: 14px;
            color: #6c757d;
            transition: color 0.2s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            line-height: 1;
        }

        .tag-item .tag-remove:hover {
            color: #dc3545;
            background-color: rgba(220, 53, 69, 0.1);
        }

        .tag-item .tag-text {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        #buscadorTagsContainer {
            background-color: #fff;
            border: 1px solid #ced4da;
            border-radius: 0.25rem;
            min-height: 38px;
            padding: 4px 8px;
            cursor: text;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        #buscadorTagsContainer:focus-within {
            border-color: #86b7fe;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }

        #buscadorTagsInput {
            background: transparent;
            border: none;
            outline: none;
            padding: 4px 0;
            font-size: 14px;
            min-width: 80px;
            flex: 1;
        }

        /* Resto de estilos existentes... */
        .modal-xl { max-width: 1100px; }
        .modal-body .form-label-sm { font-size: 0.85rem; margin-bottom: 0.2rem; }
        .modal-body .form-control-sm, .modal-body .form-select-sm {
            font-size: 0.85rem;
            padding: 0.25rem 0.5rem;
            height: calc(1.5em + 0.5rem + 2px);
        }
        .modal-footer { padding: 0.5rem 1rem; }
        #tablaCompatibilidad { font-size: 0.8rem; width: 100% !important; }
        #tablaCompatibilidad th, #tablaCompatibilidad td { padding: 0.25rem 0.5rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 0; }
        #tablaProveedores, #tablaPreciosProducto { font-size: 0.8rem; width: 100% !important; }
        #tablaProveedores th, #tablaProveedores td,
        #tablaPreciosProducto th, #tablaPreciosProducto td { padding: 0.25rem 0.5rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 0; }
        .tab-content { min-height: 450px; background-color: white; transition: min-height 0.3s ease; }
        .tab-pane { height: 100%; overflow-y: auto; }
        .tab-pane .table-responsive { max-height: 300px; overflow-y: auto; }
        #nav-info { min-height: 320px; }
        #nav-imagenes { min-height: 400px; }
        .tab-pane { max-height: 500px; overflow-y: auto; padding-right: 5px; }
        .tab-pane::-webkit-scrollbar { width: 5px; }
        .tab-pane::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 10px; }
        .tab-pane::-webkit-scrollbar-thumb { background: #888; border-radius: 10px; }
        .tab-pane::-webkit-scrollbar-thumb:hover { background: #555; }
        .nav-tabs { background-color: #f8f9fa; padding: 0.5rem 1.5rem 0; }
        .nav-tabs .nav-link { border: none; color: #6c757d; font-weight: 500; padding: 0.75rem 1.5rem; margin-right: 0.5rem; border-radius: 0.5rem 0.5rem 0 0; }
        .nav-tabs .nav-link.active { background-color: white; color: #0d6efd; border-bottom: 3px solid #0d6efd; }
        .nav-tabs .nav-link:hover:not(.active) { background-color: rgba(0, 0, 0, 0.05); }
        .tab-content { background-color: white; }
        .btn-accion-alta { background-color: #28a745; color: white; border-color: #28a745; }
        .btn-accion-baja { background-color: #dc3545; color: white; border-color: #dc3545; }
        .btn-accion-suspender { background-color: #ffc107; color: #212529; border-color: #ffc107; }
        .badge-estado-activo { background-color: #28a745; color: white; }
        .badge-estado-inactivo { background-color: #6c757d; color: white; }
        .modal-content { border-radius: 0.5rem; }
        .modal-header { border-radius: 0.5rem 0.5rem 0 0; }
        .btn-close-white { filter: invert(1) grayscale(100%) brightness(200%); }
        .bg-gradient-primary { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .bg-gradient-success { background: linear-gradient(135deg, #42e695 0%, #3bb2b8 100%); }
        .bg-gradient-info { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .badge-compatibilidad { font-size: 0.75rem; padding: 0.25rem 0.5rem; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; }
        .card-imagen { transition: transform 0.2s; cursor: move; }
        .card-imagen:hover { transform: translateY(-5px); box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1); }
        .card-imagen-principal { border: 2px solid #28a745; }
        .imagen-miniatura { height: 120px; object-fit: cover; background-color: #f8f9fa; }
        .badge-imagen-principal { position: absolute; top: 10px; right: 10px; z-index: 1; }
        .sortable-ghost { opacity: 0.4; }
        .sortable-chosen { background-color: #f8f9fa; }
        .img-thumbnail { transition: transform 0.2s; border: 2px solid #dee2e6; }
        .img-thumbnail:hover { transform: scale(1.1); border-color: #0d6efd; box-shadow: 0 0 10px rgba(13, 110, 253, 0.5); }
        .badge-compatibilidad { font-size: 0.75rem; padding: 0.25rem 0.5rem; max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; display: inline-block; }
        .badge-ubicacion { font-size: 0.7rem; padding: 0.2rem 0.4rem; margin-bottom: 0.1rem; display: block; text-align: left; border-radius: 0.25rem; }
        .rounded-circle { border-radius: 50% !important; }
        .carousel-container { position: relative; background: #f8f9fa; border-radius: 8px; padding: 20px; }
        .carousel-imagen-principal { max-height: 500px; object-fit: contain; background: white; }
        .carousel-thumbnails { display: flex; justify-content: center; gap: 10px; margin-top: 15px; flex-wrap: wrap; }
        .thumbnail-item { width: 80px; height: 80px; cursor: pointer; border: 2px solid transparent; border-radius: 8px; overflow: hidden; transition: all 0.3s; }
        .thumbnail-item:hover { transform: scale(1.1); }
        .thumbnail-item.active { border-color: #007bff; box-shadow: 0 0 10px rgba(0,123,255,0.5); }
        .thumbnail-img { width: 100%; height: 100%; object-fit: cover; }
        .carousel-control-prev, .carousel-control-next { width: 10%; background: rgba(0,0,0,0.2); border-radius: 50%; height: 50px; top: 50%; transform: translateY(-50%); }
        .carousel-indicators { position: static; margin: 10px 0 0; }
        .carousel-indicators button { width: 12px; height: 12px; border-radius: 50%; background-color: #007bff; margin: 0 5px; }
        #carruselProducto .carousel-control-prev, #carruselProducto .carousel-control-next { opacity: 0.8; transition: opacity 0.3s; }
        #carruselProducto .carousel-control-prev:hover, #carruselProducto .carousel-control-next:hover { opacity: 1; }
        #carruselProducto .carousel-control-prev-icon, #carruselProducto .carousel-control-next-icon { background-size: 1.5rem; }
        #carruselProducto .carousel-indicators { margin-bottom: 0.5rem; }
        #carruselProducto .carousel-indicators button { width: 12px; height: 12px; border-radius: 50%; background-color: #007bff; opacity: 0.5; margin: 0 5px; border: none; }
        #carruselProducto .carousel-indicators button.active { opacity: 1; }
        .carousel-fade .carousel-item { opacity: 0; transition: opacity 0.5s ease; }
        .carousel-fade .carousel-item.active { opacity: 1; }
        .thumbnail-wrapper:hover { transform: scale(1.05); box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
        .imagen-contador { position: absolute; bottom: 10px; right: 10px; background: rgba(0,0,0,0.7); color: white; padding: 5px 10px; border-radius: 20px; font-size: 0.9rem; z-index: 10; }
        .badge-seccion { background-color: #dc3545 !important; color: #ffffff !important; }
        .badge-estanteria { background-color: #ffc107 !important; color: #000000 !important; }
        .badge-estante { background-color: #198754 !important; color: #ffffff !important; }
        .badge-posicion { background-color: #6c757d !important; color: #ffffff !important; }
        #tablaProductos td.ubicacion-columna { padding: 4px 6px; vertical-align: middle; min-width: 140px; max-width: 180px; }

        /* Compatibilidad: 4 columnas (Marcas/Modelos/Submodelos/Años), cada una
           con una mini-tabla de una fila por combinación real. Mismo font-size/
           padding en las 4 para que las filas se alineen entre columnas. */
        .compat-col-table { border-collapse: separate !important; border-spacing: 0 2px !important; font-size: 0.78rem; width: 100%; }
        .compat-col-table td { padding: 0.15rem 0.4rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 150px; border: none !important; border-radius: 0.2rem !important; font-weight: 700; line-height: 1rem; }
        .compat-td-marca { background-color: #0dcaf0; color: #000; }
        .compat-td-modelo { background-color: #198754; color: #fff; }
        .compat-td-submodelo { background-color: #6c757d; color: #fff; }
        .compat-td-anios { background-color: #ffc107; color: #000; text-align: center; }

        /* Columna Precio: neto grande + chip celeste compacto con el precio c/IVA */
        .precios-listas-container { display: flex; flex-direction: column; gap: 4px; align-items: flex-end; }
        .precio-lista-item {
            line-height: 1.2; padding: 2px 0;
            display: flex; flex-direction: column; align-items: flex-end; gap: 3px;
        }
        .precio-lista-item + .precio-lista-item { border-top: 1px dashed #dee2e6; padding-top: 6px; }
        .precio-neto { font-size: 0.9rem; font-weight: 700; color: #212529; }
        .precio-iva-chip {
            display: inline-block; white-space: nowrap;
            background-color: #cfe2ff; color: #084298;
            font-size: 0.9rem; font-weight: 700;
            padding: 1px 8px; border-radius: 10px;
        }

        /* DataTables Responsive: columnas ocultas se muestran en una fila expandible (+) */
        #tablaProductos.dtr-inline.collapsed > tbody > tr > td.dtr-control,
        #tablaProductos.dtr-inline.collapsed > tbody > tr > th.dtr-control {
            position: relative;
            padding-left: 27px;
            cursor: pointer;
        }
        #tablaProductos.dtr-inline.collapsed > tbody > tr > td.dtr-control::before,
        #tablaProductos.dtr-inline.collapsed > tbody > tr > th.dtr-control::before {
            top: 50%;
            left: 5px;
            height: 1em;
            width: 1em;
            margin-top: -9px;
            display: block;
            position: absolute;
            color: #fff;
            border: 2px solid #fff;
            border-radius: 14px;
            box-shadow: 0 0 3px #444;
            box-sizing: content-box;
            text-align: center;
            text-indent: 0 !important;
            font-family: 'Courier New', Courier, monospace;
            line-height: 1em;
            content: '+';
            background-color: #0d6efd;
        }
        #tablaProductos.dtr-inline.collapsed > tbody > tr.parent > td.dtr-control::before,
        #tablaProductos.dtr-inline.collapsed > tbody > tr.parent > th.dtr-control::before {
            content: '-';
            background-color: #dc3545;
        }
        table.dtr-inline.collapsed td.child ul.dtr-details { list-style: none; margin: 0; padding: 0; }
        table.dtr-inline.collapsed td.child ul.dtr-details > li { padding: 0.3rem 0; border-bottom: 1px solid #f0f0f0; }
        table.dtr-inline.collapsed td.child span.dtr-title { font-weight: 600; margin-right: 0.5rem; }
        @media (max-width: 767.98px) {
            #tablaProductos_wrapper .btn-group { flex-wrap: wrap; }
        }
        /* Estilos mejorados para la tabla de ubicaciones en el modal */
        #tablaUbicaciones {
            width: 100% !important;
            font-size: 0.85rem;
        }

        #tablaUbicaciones thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            padding: 8px 10px;
            white-space: nowrap;
        }

        #tablaUbicaciones tbody td {
            padding: 6px 10px;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
        }

        #tablaUbicaciones tbody tr:hover {
            background-color: #f8f9fa;
        }

        /* Badges de colores para la tabla de ubicaciones */
        #tablaUbicaciones .badge-sucursal {
            background-color: #0d6efd;
            color: #fff;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
        }

        #tablaUbicaciones .badge-boca {
            background-color: #0dcaf0;
            color: #000;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
        }

        #tablaUbicaciones .badge-seccion {
            background-color: #dc3545;
            color: #fff;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
        }

        #tablaUbicaciones .badge-estanteria {
            background-color: #ffc107;
            color: #000;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
        }

        #tablaUbicaciones .badge-estante {
            background-color: #198754;
            color: #fff;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
        }

        #tablaUbicaciones .badge-posicion {
            background-color: #6c757d;
            color: #fff;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
        }

        /* Contenedor con scroll */
        .tabla-ubicaciones-container {
            max-height: 350px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 4px;
        }

        .tabla-ubicaciones-container::-webkit-scrollbar {
            width: 6px;
        }

        .tabla-ubicaciones-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .tabla-ubicaciones-container::-webkit-scrollbar-thumb {
            background: #ccc;
            border-radius: 3px;
        }

        .tabla-ubicaciones-container::-webkit-scrollbar-thumb:hover {
            background: #aaa;
        }
        /* ============================================================
        ESTILOS PARA UBICACIONES EN MODAL - CON COLORES DESTACADOS
        ============================================================ */

        /* Colores para los badges de ubicación en el modal */
        .badge-sucursal-ubicacion {
            background-color: #0d6efd !important;
            color: #ffffff !important;
            padding: 4px 10px !important;
            border-radius: 12px !important;
            font-size: 0.78rem !important;
            font-weight: 500 !important;
            display: inline-block !important;
            border: 1px solid #0d6efd !important;
        }

        .badge-boca-ubicacion {
            background-color: #0dcaf0 !important;
            color: #000000 !important;
            padding: 4px 10px !important;
            border-radius: 12px !important;
            font-size: 0.78rem !important;
            font-weight: 500 !important;
            display: inline-block !important;
            border: 1px solid #0dcaf0 !important;
        }

        .badge-seccion-ubicacion {
            background-color: #dc3545 !important;
            color: #ffffff !important;
            padding: 4px 10px !important;
            border-radius: 12px !important;
            font-size: 0.78rem !important;
            font-weight: 500 !important;
            display: inline-block !important;
            border: 1px solid #dc3545 !important;
        }

        .badge-estanteria-ubicacion {
            background-color: #ffc107 !important;
            color: #000000 !important;
            padding: 4px 10px !important;
            border-radius: 12px !important;
            font-size: 0.78rem !important;
            font-weight: 500 !important;
            display: inline-block !important;
            border: 1px solid #ffc107 !important;
        }

        .badge-estante-ubicacion {
            background-color: #198754 !important;
            color: #ffffff !important;
            padding: 4px 10px !important;
            border-radius: 12px !important;
            font-size: 0.78rem !important;
            font-weight: 500 !important;
            display: inline-block !important;
            border: 1px solid #198754 !important;
        }

        .badge-posicion-ubicacion {
            background-color: #6c757d !important;
            color: #ffffff !important;
            padding: 4px 10px !important;
            border-radius: 12px !important;
            font-size: 0.78rem !important;
            font-weight: 500 !important;
            display: inline-block !important;
            border: 1px solid #6c757d !important;
        }

        .badge-descripcion-ubicacion {
            background-color: #f8f9fa !important;
            color: #212529 !important;
            padding: 4px 10px !important;
            border-radius: 12px !important;
            font-size: 0.78rem !important;
            font-weight: 400 !important;
            display: inline-block !important;
            border: 1px solid #dee2e6 !important;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* Estilos para la tabla de ubicaciones en el modal */
        #tablaUbicaciones {
            width: 100% !important;
            font-size: 0.85rem;
        }

        #tablaUbicaciones thead th {
            background-color: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            padding: 8px 10px;
            white-space: nowrap;
            color: #212529;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        #tablaUbicaciones thead th:first-child {
            border-radius: 4px 0 0 0;
        }

        #tablaUbicaciones thead th:last-child {
            border-radius: 0 4px 0 0;
        }

        #tablaUbicaciones tbody td {
            padding: 6px 10px;
            vertical-align: middle;
            border-bottom: 1px solid #f0f0f0;
        }

        #tablaUbicaciones tbody tr {
            transition: background-color 0.15s ease;
        }

        #tablaUbicaciones tbody tr:hover {
            background-color: #f0f7ff !important;
        }

        #tablaUbicaciones tbody tr:nth-child(even) {
            background-color: #fafafa;
        }

        #tablaUbicaciones tbody tr:nth-child(even):hover {
            background-color: #f0f7ff !important;
        }

        /* Contenedor con scroll */
        .tabla-ubicaciones-container {
            max-height: 350px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 6px;
        }

        .tabla-ubicaciones-container::-webkit-scrollbar {
            width: 6px;
        }

        .tabla-ubicaciones-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }

        .tabla-ubicaciones-container::-webkit-scrollbar-thumb {
            background: #c1c7cd;
            border-radius: 3px;
        }

        .tabla-ubicaciones-container::-webkit-scrollbar-thumb:hover {
            background: #a0a8b0;
        }

        /* Responsive: en pantallas pequeñas, reducir el tamaño de los badges */
        @media (max-width: 768px) {
            .badge-sucursal-ubicacion,
            .badge-boca-ubicacion,
            .badge-seccion-ubicacion,
            .badge-estanteria-ubicacion,
            .badge-estante-ubicacion,
            .badge-posicion-ubicacion,
            .badge-descripcion-ubicacion {
                font-size: 0.7rem !important;
                padding: 2px 6px !important;
            }
            
            #tablaUbicaciones thead th {
                font-size: 0.7rem !important;
                padding: 5px 6px !important;
            }
            
            #tablaUbicaciones tbody td {
                padding: 4px 6px !important;
            }
        }
    </style>

    <!-- ========== JAVASCRIPT ========== -->
    <script>
        $(document).ready(function () {
            // Variables de contexto MULTIEMPRESA
            const empresa_idx = window.EMPRESA_ID; // empresa activa (header1.php)
            const pagina_idx = <?php echo $pagina_idx; ?>;

            // Listas de precios activas, resueltas ya en el servidor al renderizar
            // la página (ver $listas_precios_iniciales en productos.php) para no
            // esperar una vuelta de AJAX extra antes de poder armar la tabla.
            const listasPreciosIniciales = <?php echo json_encode($listas_precios_iniciales, JSON_UNESCAPED_UNICODE); ?>;

            // Variables globales
            var tabla = null;
            var tablaCompatibilidad = null;
            var tablaUbicaciones = null;
            var tablaProveedores = null;
            var tablaPreciosProducto = null;
            var currentPage = 0;
            var currentOrder = [[1, 'asc']];
            var currentSearch = '';
            var productoActualCompatibilidad = null;
            var productoActualImagenes = null;
            var productoActualUbicaciones = null;
            var productoActualProveedores = null;
            var productoActualPrecios = null;
            var imagenesProductoActual = [];
            var datosUbicacionCompleta = null;
            var tags = [];
            var tagsInput = $('#buscadorTagsInput');
            var tagsContainer = $('#buscadorTagsContainer');
            var timeoutCodigo = null;

            // ========== FUNCIONES DE CARRUSEL ==========
            window.mostrarImagenGrande = function(url, titulo, productoId) {
                if (productoId) {
                    $.ajax({
                        url: 'productos_consulta_ajax.php',
                        type: 'GET',
                        data: {
                            accion: 'obtener_imagenes_producto',
                            producto_id: productoId,
                            empresa_idx: empresa_idx
                        },
                        dataType: 'json',
                        success: function(imagenes) {
                            if (imagenes && imagenes.length > 0) {
                                var indiceActual = 0;
                                var imagenIdActual = url.split('=')[1];
                                imagenes.forEach(function(img, idx) {
                                    var imgId = img.imagen_url ? img.imagen_url.split('=')[1] : null;
                                    if (imgId == imagenIdActual) indiceActual = idx;
                                });
                                mostrarCarruselImagenes(imagenes, indiceActual);
                            } else {
                                mostrarCarruselImagenes([{
                                    imagen_url: url,
                                    descripcion: titulo || 'Imagen del producto'
                                }], 0);
                            }
                        },
                        error: function() {
                            Swal.fire({ icon: 'error', title: 'Error', text: 'No se pudieron cargar las imágenes' });
                        }
                    });
                } else {
                    mostrarCarruselImagenes([{
                        imagen_url: url,
                        descripcion: titulo || 'Imagen del producto'
                    }], 0);
                }
            };

            function mostrarCarruselImagenes(imagenes, indiceInicial) {
                if (!imagenes || imagenes.length === 0) {
                    Swal.fire({ icon: 'info', title: 'Sin imágenes', text: 'Este producto no tiene imágenes para mostrar' });
                    return;
                }

                imagenesProductoActual = imagenes;
                imagenes = imagenes.map(img => {
                    if (!img.imagen_url) img.imagen_url = 'get_imagen.php?id=' + img.imagen_id;
                    return img;
                });

                var carouselHtml = `
                    <div id="carruselProducto" class="carousel slide carousel-fade" data-bs-ride="false" data-bs-interval="false">
                        <div class="carousel-indicators">
                            ${imagenes.map((_, idx) => `
                                <button type="button" data-bs-target="#carruselProducto" data-bs-slide-to="${idx}" 
                                    class="${idx === indiceInicial ? 'active' : ''}" aria-current="${idx === indiceInicial ? 'true' : 'false'}" 
                                    aria-label="Imagen ${idx + 1}"></button>
                            `).join('')}
                        </div>
                        <div class="carousel-inner bg-dark" style="border-radius: 10px; min-height: 500px; display: flex; align-items: center;">
                            ${imagenes.map((img, idx) => `
                                <div class="carousel-item ${idx === indiceInicial ? 'active' : ''}" style="text-align: center;">
                                    <img src="${img.imagen_url}" class="d-block mx-auto" 
                                        style="max-height: 500px; max-width: 100%; object-fit: contain;"
                                        alt="${img.descripcion || 'Imagen ' + (idx + 1)}"
                                        onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'500\' height=\'500\'><rect width=\'500\' height=\'500\' fill=\'#f8f9fa\'/><text x=\'250\' y=\'250\' text-anchor=\'middle\' fill=\'#6c757d\' font-family=\'Arial\' font-size=\'24\'>Error</text></svg>';">
                                    ${img.descripcion ? `
                                        <div class="carousel-caption d-none d-md-block bg-dark bg-opacity-50 rounded p-2" style="bottom: 20px;">
                                            <p class="mb-0 text-white">${img.descripcion}</p>
                                        </div>
                                    ` : ''}
                                </div>
                            `).join('')}
                        </div>
                        <button class="carousel-control-prev" type="button" data-bs-target="#carruselProducto" data-bs-slide="prev" style="width: 10%; background: linear-gradient(90deg, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0) 100%); border: none;">
                            <span class="carousel-control-prev-icon" aria-hidden="true" style="width: 3rem; height: 3rem; background-color: rgba(0,0,0,0.5); border-radius: 50%; padding: 1rem;"></span>
                            <span class="visually-hidden">Anterior</span>
                        </button>
                        <button class="carousel-control-next" type="button" data-bs-target="#carruselProducto" data-bs-slide="next" style="width: 10%; background: linear-gradient(270deg, rgba(0,0,0,0.3) 0%, rgba(0,0,0,0) 100%); border: none;">
                            <span class="carousel-control-next-icon" aria-hidden="true" style="width: 3rem; height: 3rem; background-color: rgba(0,0,0,0.5); border-radius: 50%; padding: 1rem;"></span>
                            <span class="visually-hidden">Siguiente</span>
                        </button>
                        <div class="position-absolute top-0 end-0 m-3 bg-dark bg-opacity-75 text-white px-3 py-2 rounded-pill" style="z-index: 10;">
                            <span id="imagenActual">${indiceInicial + 1}</span> / ${imagenes.length}
                        </div>
                    </div>
                    <div class="row mt-4 justify-content-center" id="thumbnailsContainer">
                        ${imagenes.map((img, idx) => `
                            <div class="col-auto mb-2">
                                <div class="thumbnail-wrapper" onclick="$('#carruselProducto').carousel(${idx})"
                                    style="cursor: pointer; border: 3px solid ${idx === indiceInicial ? '#007bff' : 'transparent'}; border-radius: 8px; overflow: hidden; transition: all 0.3s;">
                                    <img src="${img.imagen_url}" style="width: 80px; height: 80px; object-fit: cover;" 
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

                $('#contenidoCarrusel').html(carouselHtml);
                setTimeout(function() {
                    var carruselElement = document.getElementById('carruselProducto');
                    if (carruselElement) new bootstrap.Carousel(carruselElement, { interval: false, wrap: true, touch: true });
                    $('#carruselProducto').on('slid.bs.carousel', function(e) {
                        var currentIndex = $('.carousel-item.active').index();
                        $('#imagenActual').text(currentIndex + 1);
                        $('.thumbnail-wrapper').css('border-color', 'transparent');
                        $('.thumbnail-wrapper').eq(currentIndex).css('border-color', '#007bff');
                    });
                }, 100);

                var modalElement = document.getElementById('modalCarrusel');
                if (modalElement) new bootstrap.Modal(modalElement).show();
            }

            window.cerrarModalCarrusel = function() {
                var modalElement = document.getElementById('modalCarrusel');
                if (modalElement) {
                    var modal = bootstrap.Modal.getInstance(modalElement);
                    if (modal) modal.hide();
                }
            };

            // ========== FUNCIONES DE CARGAS DE DATOS ==========

            function cargarTiposProducto(valorPorDefecto) {
                $.get('productos_consulta_ajax.php', { accion: 'obtener_tipos_producto', empresa_idx: empresa_idx }, function(tipos) {
                    var select = $('#producto_tipo_id');
                    select.empty().append('<option value="">Tipo *</option>');
                    if (tipos && tipos.length > 0) {
                        tipos.forEach(function(tipo) {
                            select.append(`<option value="${tipo.producto_tipo_id}">${tipo.producto_tipo} (${tipo.producto_tipo_codigo})</option>`);
                        });
                    }
                    // Producto nuevo: tipo por defecto (producto_tipo_id = 1). En modo edición
                    // no se pasa valorPorDefecto acá — el valor real lo pisa el setTimeout
                    // de cargarProductoParaVer una vez resuelto el fetch del producto.
                    if (valorPorDefecto) select.val(String(valorPorDefecto));
                }, 'json');
            }

            function cargarUnidadesMedida() {
                $.get('productos_consulta_ajax.php', { accion: 'obtener_unidades_medida', empresa_idx: empresa_idx }, function(unidades) {
                    var select = $('#unidad_medida_id');
                    select.empty().append('<option value="">Unidad de Medida</option>');
                    if (unidades && unidades.length > 0) {
                        unidades.forEach(function(unidad) {
                            select.append(`<option value="${unidad.unidad_medida_id}">${unidad.unidad_nombre} (${unidad.unidad_abreviatura})</option>`);
                        });
                    }
                }, 'json');
            }

            function cargarIvaAlicuotas(valorPorDefecto) {
                $.get('productos_consulta_ajax.php', { accion: 'obtener_iva_alicuotas', empresa_idx: empresa_idx }, function(alicuotas) {
                    var select = $('#iva_alicuota_id');
                    select.empty().append('<option value="">IVA Alícuota</option>');
                    if (alicuotas && alicuotas.length > 0) {
                        alicuotas.forEach(function(alicuota) {
                            var porcentaje = alicuota.porcentaje ? parseFloat(alicuota.porcentaje).toFixed(2) + '%' : '';
                            var texto = alicuota.iva_alicuota + (porcentaje ? ' (' + porcentaje + ')' : '');
                            select.append(`<option value="${alicuota.iva_alicuota_id}" 
                                data-porcentaje="${alicuota.porcentaje || 0}"
                                data-gravado="${alicuota.es_gravado || 0}"
                                data-exento="${alicuota.es_exento || 0}"
                                data-no-gravado="${alicuota.es_no_gravado || 0}">${texto}</option>`);
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
                        if (exento == 1) infoHtml = '<span class="badge bg-success">EXENTO</span>';
                        else if (noGravado == 1) infoHtml = '<span class="badge bg-warning">NO GRAVADO</span>';
                        else if (gravado == 1) infoHtml = '<span class="badge bg-info">GRAVADO</span>';
                        else infoHtml = '<span class="text-muted">No especificado</span>';
                        $('#iva_info').html(infoHtml);
                    });
                    // Producto nuevo: IVA por defecto (iva_alicuota_id = 1). En modo edición
                    // no se pasa valorPorDefecto acá — el valor real lo pisa el setTimeout
                    // de cargarProductoParaVer una vez resuelto el fetch del producto.
                    if (valorPorDefecto) select.val(String(valorPorDefecto));
                    if (select.val()) select.trigger('change');
                }, 'json');
            }

            function cargarMarcas() {
                $.get('productos_consulta_ajax.php', { accion: 'obtener_marcas', empresa_idx: empresa_idx }, function(marcas) {
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

            // Filtro rápido de estado, al lado del botón "Agregar". Arranca siempre
            // en "Activo" — por eso se resuelve ANTES de inicializar el DataTable
            // (que dispara su primer pedido apenas se crea), pasando un callback
            // en vez de dejar que la primera carga salga sin filtro.
            function cargarFiltroEstado(callback) {
                $.get('productos_consulta_ajax.php', { accion: 'obtener_estados' }, function(estados) {
                    var $select = $('#filtroEstado');
                    $select.empty().append('<option value="">Todos los estados</option>');
                    (estados || []).forEach(function(estado) {
                        $select.append(`<option value="${estado.tabla_estado_registro_id}">${estado.estado_registro}</option>`);
                    });
                    // Arranca siempre en Activo (tabla_estado_registro_id = 1).
                    $select.val('1');
                    if (typeof callback === 'function') callback();
                }, 'json');
            }

            function cargarModelos(marcaId, targetId) {
                targetId = targetId || '#modelo_id';
                if (!marcaId) {
                    $(targetId).empty().append('<option value="">Seleccionar modelo...</option>').prop('disabled', true);
                    return;
                }
                $.get('productos_consulta_ajax.php', { accion: 'obtener_modelos', empresa_idx: empresa_idx, marca_id: marcaId }, function(modelos) {
                    var select = $(targetId);
                    select.empty().append('<option value="">Seleccionar modelo...</option>');
                    if (modelos && modelos.length > 0) {
                        select.prop('disabled', false);
                        modelos.forEach(function(modelo) {
                            select.append(`<option value="${modelo.modelo_id}">${modelo.modelo_nombre}</option>`);
                        });
                    } else select.prop('disabled', true);
                }, 'json');
            }

            function cargarSubmodelos(modeloId, targetId) {
                targetId = targetId || '#submodelo_id';
                if (!modeloId) {
                    $(targetId).empty().append('<option value="">Seleccionar submodelo...</option>').prop('disabled', true);
                    return;
                }
                $.get('productos_consulta_ajax.php', { accion: 'obtener_submodelos', empresa_idx: empresa_idx, modelo_id: modeloId }, function(submodelos) {
                    var select = $(targetId);
                    select.empty().append('<option value="">Seleccionar submodelo...</option>').append('<option value="0">Sin submodelo</option>');
                    if (submodelos && submodelos.length > 0) {
                        select.prop('disabled', false);
                        submodelos.forEach(function(submodelo) {
                            select.append(`<option value="${submodelo.submodelo_id}">${submodelo.submodelo_nombre}</option>`);
                        });
                    } else select.prop('disabled', false);
                }, 'json');
            }

            function formatBytes(bytes, decimals) {
                decimals = decimals || 2;
                if (bytes === 0) return '0 Bytes';
                const k = 1024;
                const dm = decimals < 0 ? 0 : decimals;
                const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                const i = Math.floor(Math.log(bytes) / Math.log(k));
                return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
            }

            // ========== FUNCIONES DEL BUSCADOR POR TAGS ==========

            function inicializarBuscadorTags() {
                tagsContainer.on('click', function(e) {
                    if (e.target === this || $(e.target).is('#buscadorTagsContainer')) tagsInput.focus();
                });

                // El espacio confirma la etiqueta (comportamiento por defecto).
                // Excepción: si hay una comilla doble sin cerrar en lo tecleado,
                // estamos armando una frase compuesta ("renault 18") y el espacio
                // se escribe normal — la etiqueta se confirma recién al cerrar la
                // comilla (o con Enter, que siempre confirma pase lo que pase).
                tagsInput.on('keydown', function(e) {
                    var valorActual = $(this).val();
                    var value = valorActual.trim();
                    var comillaAbierta = ((valorActual.match(/"/g) || []).length % 2) === 1;

                    if (e.key === ' ' && !comillaAbierta) {
                        e.preventDefault();
                        if (value.length > 0) {
                            agregarTag(value);
                            $(this).val('');
                            ejecutarBusquedaTags();
                        }
                    } else if (e.key === 'Enter') {
                        e.preventDefault();
                        if (value.length > 0) {
                            agregarTag(value);
                            $(this).val('');
                            ejecutarBusquedaTags();
                        }
                    } else if (e.key === 'Backspace' && value === '' && tags.length > 0) {
                        eliminarTag(tags.length - 1);
                        ejecutarBusquedaTags();
                    } else if (e.key === 'Escape') {
                        $(this).blur();
                    }
                });

                // Al pegar, el texto queda en el input (normalizado a una sola línea)
                // esperando confirmación (espacio o Enter) — si el texto pegado tiene
                // espacios y no se abrió con comillas, se va a partir en varias
                // etiquetas apenas el usuario siga escribiendo o confirme.
                tagsInput.on('paste', function() {
                    setTimeout(function() {
                        var value = tagsInput.val().replace(/\s+/g, ' ').trim();
                        tagsInput.val(value);
                    }, 10);
                });

            }

            function agregarTag(texto) {
                // Normalizamos espacios internos y sacamos las comillas dobles: el
                // término viaja al backend entrecomillado cuando tiene espacios
                // (ver ejecutarBusquedaTags), así que una comilla dentro del texto
                // rompería el parseo de la frase.
                texto = String(texto).replace(/"/g, '').replace(/\s+/g, ' ').trim();
                if (!texto) return;
                var duplicado = tags.some(function(tag) { return tag.toLowerCase() === texto.toLowerCase(); });
                if (duplicado) { tagsInput.val(''); return; }
                tags.push(texto);
                renderizarTags();
                tagsInput.val('');
                tagsInput.focus();
            }

            function eliminarTag(index) {
                if (index >= 0 && index < tags.length) {
                    tags.splice(index, 1);
                    renderizarTags();
                }
            }

            function limpiarTags() {
                tags = [];
                renderizarTags();
            }

            function renderizarTags() {
                tagsContainer.find('.tag-item').remove();
                tags.forEach(function(tag, index) {
                    var tagHtml = `
                        <span class="tag-item" data-index="${index}">
                            <span class="tag-text">${escapeHtml(tag)}</span>
                            <span class="tag-remove" data-index="${index}" title="Eliminar"><i class="fas fa-times"></i></span>
                        </span>
                    `;
                    tagsContainer.find('#buscadorTagsInput').before(tagHtml);
                });
                tagsContainer.find('.tag-remove').on('click', function(e) {
                    e.stopPropagation();
                    var index = parseInt($(this).data('index'));
                    eliminarTag(index);
                    ejecutarBusquedaTags();
                });
            }

            function escapeHtml(text) {
                var div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Arma el string que se manda al backend. Las etiquetas de una sola
            // palabra van sueltas; las compuestas van entre comillas para que el
            // modelo las trate como UNA frase y no como palabras sueltas en AND.
            function serializarTags(lista) {
                return lista.map(function(tag) {
                    return tag.indexOf(' ') >= 0 ? '"' + tag + '"' : tag;
                }).join(' ');
            }

            // Inversa de serializarTags(): reconstruye las etiquetas desde el string
            // de búsqueda (usado al restaurar el estado guardado del DataTable).
            function deserializarTags(texto) {
                var resultado = [];
                var re = /"([^"]*)"|(\S+)/g;
                var m;
                while ((m = re.exec(texto)) !== null) {
                    var termino = (m[1] !== undefined ? m[1] : m[2]).trim();
                    if (termino.length > 0) resultado.push(termino);
                }
                return resultado;
            }

            function ejecutarBusquedaTags() {
                var terminoBusqueda = serializarTags(tags);
                if (tabla) {
                    if (tags.length > 0) {
                        $('#buscadorTagsContainer').attr('title', 'Buscando ' + tags.length + ' palabra(s): ' + tags.join(', '));
                    } else {
                        $('#buscadorTagsContainer').removeAttr('title');
                    }
                    tabla.search(terminoBusqueda);
                    tabla.draw();
                    actualizarIndicadorFiltros();
                }
            }

            // ========== FUNCIONES DE UBICACIONES ==========

            function cargarUbicacionesProducto(productoId) {
                productoActualUbicaciones = productoId;
                if ($.fn.DataTable.isDataTable('#tablaUbicaciones')) {
                    $('#tablaUbicaciones').DataTable().destroy();
                    $('#tablaUbicaciones tbody').empty();
                }
                tablaUbicaciones = $('#tablaUbicaciones').DataTable({
                    ajax: {
                        url: 'productos_consulta_ajax.php',
                        type: 'GET',
                        data: { accion: 'obtener_ubicaciones_producto', producto_id: productoId, empresa_idx: empresa_idx },
                        dataSrc: ''
                    },
                    columns: [
                        { 
                            data: 'sucursal_nombre', 
                            className: 'align-middle',
                            render: function(data) { 
                                return data ? '<span class="badge badge-sucursal-ubicacion">' + data + '</span>' : '<span class="text-muted">-</span>'; 
                            }
                        },
                        { 
                            data: 'boca_nombre', 
                            className: 'align-middle',
                            render: function(data) { 
                                return data ? '<span class="badge badge-boca-ubicacion">' + data + '</span>' : '<span class="text-muted">-</span>'; 
                            }
                        },
                        { 
                            data: 'seccion', 
                            className: 'align-middle',
                            render: function(data) { 
                                return data ? '<span class="badge badge-seccion-ubicacion">' + data + '</span>' : '<span class="text-muted">-</span>'; 
                            }
                        },
                        { 
                            data: 'estanteria', 
                            className: 'align-middle',
                            render: function(data) { 
                                return data ? '<span class="badge badge-estanteria-ubicacion">' + data + '</span>' : '<span class="text-muted">-</span>'; 
                            }
                        },
                        { 
                            data: 'estante', 
                            className: 'align-middle',
                            render: function(data) { 
                                return data ? '<span class="badge badge-estante-ubicacion">' + data + '</span>' : '<span class="text-muted">-</span>'; 
                            }
                        },
                        { 
                            data: 'posicion', 
                            className: 'align-middle',
                            render: function(data) { 
                                return data ? '<span class="badge badge-posicion-ubicacion">' + data + '</span>' : '<span class="text-muted">-</span>'; 
                            }
                        },
                        { 
                            data: 'descripcion', 
                            className: 'align-middle',
                            render: function(data) { 
                                return data ? '<span class="badge badge-descripcion-ubicacion">' + data + '</span>' : '<span class="text-muted">-</span>'; 
                            }
                        }
                    ],
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' },
                    responsive: true,
                    pageLength: 10,
                    searching: false,
                    paging: false,
                    info: false,
                    scrollX: false,
                    autoWidth: false,
                    columnDefs: [
                        { width: '10%', targets: 0 },
                        { width: '10%', targets: 1 },
                        { width: '15%', targets: 2 },
                        { width: '15%', targets: 3 },
                        { width: '10%', targets: 4 },
                        { width: '10%', targets: 5 },
                        { width: '20%', targets: 6 }
                    ]
                });
            }

            // ========== FUNCIONES DE COMPATIBILIDAD ==========

            function cargarCompatibilidad(productoId) {
                productoActualCompatibilidad = productoId;
                if ($.fn.DataTable.isDataTable('#tablaCompatibilidad')) {
                    $('#tablaCompatibilidad').DataTable().destroy();
                    $('#tablaCompatibilidad tbody').empty();
                }
                tablaCompatibilidad = $('#tablaCompatibilidad').DataTable({
                    ajax: {
                        url: 'productos_consulta_ajax.php',
                        type: 'GET',
                        data: { accion: 'obtener_compatibilidad', producto_id: productoId, empresa_idx: empresa_idx },
                        dataSrc: ''
                    },
                    columns: [
                        { data: 'marca_nombre', responsivePriority: 2, render: function(data) { return '<span title="' + (data || '-') + '">' + (data || '-') + '</span>'; } },
                        { data: 'modelo_nombre', responsivePriority: 3, render: function(data) { return '<span title="' + (data || '-') + '">' + (data || '-') + '</span>'; } },
                        { data: 'submodelo_nombre', responsivePriority: 5, render: function(data) { return '<span title="' + (data || '-') + '">' + (data || '-') + '</span>'; } },
                        {
                            data: null, className: 'text-center', responsivePriority: 4,
                            render: function(data) {
                                var anioDesde = data.anio_desde || '';
                                var anioHasta = data.anio_hasta == '0' ? 'Actual' : (data.anio_hasta || '');
                                if (anioHasta && anioHasta !== 'Actual') return anioDesde + '-' + anioHasta;
                                return anioDesde || '-';
                            }
                        }
                    ],
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' },
                    responsive: true,
                    dom: 't',
                    buttons: [],
                    pageLength: 10,
                    searching: false,
                    paging: false,
                    info: false
                });
            }

            // ========== FUNCIONES DE IMÁGENES ==========

            function cargarImagenesProducto(productoId) {
                productoActualImagenes = productoId;
                $.get('productos_consulta_ajax.php', { accion: 'obtener_imagenes_producto', producto_id: productoId, empresa_idx: empresa_idx }, function(imagenes) {
                    var galeria = $('#galeriaImagenes');
                    var sinImagenes = $('#sinImagenes');
                    galeria.empty();
                    if (imagenes && imagenes.length > 0) {
                        sinImagenes.hide();
                        imagenes.forEach(function(imagen) {
                            var srcImagen = imagen.imagen_url;
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
                                            <h6 class="card-title mb-1 text-truncate" title="${imagen.descripcion || 'Sin descripción'}">${imagen.descripcion || 'Sin descripción'}</h6>
                                            <p class="card-text small text-muted mb-1"><i class="fas fa-sort-numeric-up me-1"></i>Orden: ${imagen.orden || 0}</p>
                                            <p class="card-text small text-muted mb-2"><i class="fas fa-weight-hanging me-1"></i>${formatBytes(imagen.imagen_tamanio)}</p>
                                            <p class="card-text small text-muted mb-2"><i class="fas fa-folder me-1"></i>${imagen.imagen_nombre}</p>
                                        </div>
                                    </div>
                                </div>
                            `;
                            galeria.append(cardHtml);
                        });
                    } else sinImagenes.show();
                }, 'json');
            }

            // ========== FUNCIONES DE PROVEEDORES ==========

            function cargarProveedoresProducto(productoId) {
                productoActualProveedores = productoId;
                if ($.fn.DataTable.isDataTable('#tablaProveedores')) {
                    $('#tablaProveedores').DataTable().destroy();
                    $('#tablaProveedores tbody').empty();
                }
                tablaProveedores = $('#tablaProveedores').DataTable({
                    ajax: {
                        url: 'productos_consulta_ajax.php',
                        type: 'GET',
                        data: { accion: 'obtener_proveedores_producto', producto_id: productoId, empresa_idx: empresa_idx },
                        dataSrc: ''
                    },
                    columns: [
                        { data: 'entidad_nombre', responsivePriority: 1, render: function(data) { return data || '-'; } },
                        { data: 'cuit', responsivePriority: 3, render: function(data) { return data || '-'; } },
                        { data: 'codigo_proveedor', responsivePriority: 2, render: function(data) { return data || '-'; } }
                    ],
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' },
                    responsive: true,
                    pageLength: 10,
                    searching: false,
                    paging: false,
                    info: false
                });
            }

            // ========== FUNCIONES DE PRECIOS (por lista de precios) ==========
            // Lee los precios de ESTE producto en cada lista con la acción de lectura
            // obtener_precios_producto de listas_precios_productos_ajax.php.

            function cargarPreciosProducto(productoId) {
                productoActualPrecios = productoId;
                if ($.fn.DataTable.isDataTable('#tablaPreciosProducto')) {
                    $('#tablaPreciosProducto').DataTable().destroy();
                    $('#tablaPreciosProducto tbody').empty();
                }
                tablaPreciosProducto = $('#tablaPreciosProducto').DataTable({
                    ajax: {
                        url: 'listas_precios_productos_ajax.php',
                        type: 'GET',
                        data: { accion: 'obtener_precios_producto', producto_id: productoId },
                        dataSrc: ''
                    },
                    columns: [
                        { data: 'lista_nombre', responsivePriority: 1, render: function(data) { return data || '-'; } },
                        {
                            data: 'precio_unitario', className: 'text-end', responsivePriority: 2,
                            render: data => '$ ' + parseFloat(data).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
                        },
                        {
                            data: 'precio_con_iva', className: 'text-end', responsivePriority: 3,
                            render: function(data, type, row) {
                                var iva = row.iva_porcentaje ? parseFloat(row.iva_porcentaje) : 0;
                                return '<span title="IVA ' + iva + '%">$ ' + parseFloat(data).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + '</span>';
                            }
                        },
                        { data: 'f_actualizacion', responsivePriority: 4, render: data => data ? new Date(data).toLocaleString() : '-' }
                    ],
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' },
                    responsive: true,
                    pageLength: 10,
                    searching: false,
                    paging: false,
                    info: false
                });
            }

            // ========== FUNCIONES DE CONSULTA ==========

            function cargarProductoParaVer(productoId) {
                $.get('productos_consulta_ajax.php', { accion: 'obtener', producto_id: productoId, empresa_idx: empresa_idx }, function(res) {
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
                        cargarTiposProducto();
                        cargarUnidadesMedida();
                        cargarIvaAlicuotas();
                        setTimeout(function() {
                            $('#producto_tipo_id').val(res.producto_tipo_id);
                            $('#unidad_medida_id').val(res.unidad_medida_id || '');
                            $('#iva_alicuota_id').val(res.iva_alicuota_id || '');
                            if (res.iva_alicuota_id) $('#iva_alicuota_id').trigger('change');
                        }, 500);
                        $('#modalLabel').html('<i class="fas fa-search me-2"></i>Consultar Producto');
                        // Solo lectura: se deshabilitan todos los campos del formulario
                        $('#formProducto').find('input, select, textarea').prop('disabled', true);
                        cargarCompatibilidad(productoId);
                        cargarImagenesProducto(productoId);
                        cargarUbicacionesProducto(productoId);
                        cargarProveedoresProducto(productoId);
                        cargarPreciosProducto(productoId);
                        new bootstrap.Modal(document.getElementById('modalProducto')).show();
                    } else Swal.fire({ icon: "error", title: "Error", text: "Error al obtener datos del producto" });
                }, 'json');
            }

            function resetModal() {
                $('#formProducto')[0].reset();
                $('#producto_id').val('');
                $('#formProducto').removeClass('was-validated');
                $('#iva_alicuota_id').empty().append('<option value="">IVA Alícuota</option>');
                $('#iva_info').html('<span class="text-muted">Seleccione una alícuota...</span>');
                $('#iva_porcentaje').html('<span class="text-muted">0%</span>');
                $('#producto_tipo_id').empty().append('<option value="">Tipo *</option>');
                $('#unidad_medida_id').empty().append('<option value="">Unidad de Medida</option>');
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
                if ($.fn.DataTable.isDataTable('#tablaPreciosProducto')) {
                    $('#tablaPreciosProducto').DataTable().destroy();
                    $('#tablaPreciosProducto tbody').empty();
                }
            }

            // ========== FUNCIONES DE FILTROS ==========

            function actualizarIndicadorFiltros() {
                var codigo = $('#filtroCodigo').val().trim();
                var marca = $('#filtroMarca').val();
                var modelo = $('#filtroModelo').val();
                var submodelo = $('#filtroSubmodelo').val();
                var totalFiltros = 0;
                
                if (codigo) totalFiltros++;
                if (marca) totalFiltros++;
                if (modelo) totalFiltros++;
                if (submodelo) totalFiltros++;
                if (tags.length > 0) totalFiltros++;
                
                if (totalFiltros > 0) {
                    $('#btnLimpiarTags').removeClass('btn-outline-secondary').addClass('btn-danger')
                        .attr('title', 'Limpiar todos los filtros (' + totalFiltros + ' activo' + (totalFiltros > 1 ? 's' : '') + ')');
                } else {
                    $('#btnLimpiarTags').removeClass('btn-danger').addClass('btn-outline-secondary')
                        .attr('title', 'Limpiar todos los filtros');
                }
            }

            // ========== FUNCIONES DE TABLA PRINCIPAL ==========

            function inicializarDataTable(listasPrecios) {
                listasPrecios = listasPrecios || [];

                if ($.fn.DataTable.isDataTable('#tablaProductos')) {
                    $('#tablaProductos').DataTable().destroy();
                    $('#tablaProductos tbody').empty();
                }

                // El contenedor de info de filtros ahora está en el HTML directamente

                // Paleta de colores para distinguir cada lista de precios (mismo color siempre para la misma lista)
                var paletaListas = [
                    { bg: '#0d6efd', text: '#fff' }, { bg: '#198754', text: '#fff' },
                    { bg: '#fd7e14', text: '#fff' }, { bg: '#6f42c1', text: '#fff' },
                    { bg: '#d63384', text: '#fff' }, { bg: '#20c997', text: '#000' },
                    { bg: '#0dcaf0', text: '#000' }, { bg: '#ffc107', text: '#000' },
                    { bg: '#6c757d', text: '#fff' }, { bg: '#dc3545', text: '#fff' }
                ];
                function colorPorLista(id, nombre) {
                    var key = (id !== null && id !== undefined && id !== '') ? String(id) : (nombre || '');
                    var hash = 0;
                    for (var i = 0; i < key.length; i++) {
                        hash = (hash * 31 + key.charCodeAt(i)) >>> 0;
                    }
                    return paletaListas[hash % paletaListas.length];
                }

                // Una columna de DataTable por cada lista de precios activa
                var columnasListasPrecios = listasPrecios.map(function(lista) {
                    return {
                        data: null, width: '150px', className: 'text-end', orderable: false, searchable: false, responsivePriority: 5,
                        render: function(data, type, row) {
                            var fmt = function(n) {
                                return parseFloat(n).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                            };
                            var entry = (row.precios_listas || []).find(function(p) {
                                return String(p.lista_precio_id) === String(lista.lista_precio_id);
                            });
                            if (!entry || entry.precio_neto === null || entry.precio_neto === undefined) {
                                return type === 'export' ? '' : '<span class="text-muted">-</span>';
                            }
                            if (type === 'export') {
                                return 'neto $' + fmt(entry.precio_neto) + ' / c-IVA $' + fmt(entry.precio_con_iva);
                            }
                            var iva = row.iva_porcentaje ? parseFloat(row.iva_porcentaje) : 0;
                            return '<div class="precio-lista-item" title="IVA ' + iva + '%">' +
                                        '<span class="precio-neto">$ ' + fmt(entry.precio_neto) + '</span>' +
                                        '<span class="precio-iva-chip">IVA $ ' + fmt(entry.precio_con_iva) + '</span>' +
                                    '</div>';
                        }
                    };
                });

                // Insertar en el thead un <th> coloreado por cada lista (siempre después de "Imagen" y antes de "Acciones")
                // Índice 8 = "Imagen" (0:Código, 1:Nombre, 2:Marcas, 3:Modelos, 4:Submodelos, 5:Años, 6:Ubicaciones, 7:Cód.Proveedor, 8:Imagen)
                $('#tablaProductos thead th.th-lista-precio').remove();
                var $thAnterior = $('#tablaProductos thead th').eq(8);
                listasPrecios.forEach(function(lista) {
                    var color = colorPorLista(lista.lista_precio_id, lista.lista_precio_nombre);
                    var $th = $('<th class="th-lista-precio text-center" width="120"></th>')
                        .css({ 'background-color': color.bg, color: color.text })
                        .text(lista.lista_precio_nombre);
                    $thAnterior.after($th);
                    $thAnterior = $th;
                });

                // Índices para exportar (todas las columnas visibles menos "Acciones", que siempre es la última)
                var totalColumnas = 10 + listasPrecios.length; // 9 fijas antes del precio (Código, Nombre, Marcas, Modelos, Submodelos, Años, Ubicaciones, Cód.Proveedor, Imagen) + N listas + Acciones
                var exportCols = [];
                for (var ie = 0; ie < totalColumnas - 1; ie++) exportCols.push(ie);

                tabla = $('#tablaProductos').DataTable({
                    processing: true,
                    serverSide: true,
                    ajax: {
                        url: 'productos_consulta_ajax.php',
                        type: 'GET',
                        data: function(d) {
                            d.accion = 'listar';
                            d.empresa_idx = empresa_idx;
                            d.pagina_idx = pagina_idx;
                            d.filtro_codigo = $('#filtroCodigo').val();
                            d.filtro_marca = $('#filtroMarca').val();
                            d.filtro_modelo = $('#filtroModelo').val();
                            d.filtro_submodelo = $('#filtroSubmodelo').val();
                            d.filtro_estado = $('#filtroEstado').val();
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
                    // Pie: cantidad de registros a la izquierda, info al medio, paginado a la derecha
                    // (sin el cuadro de búsqueda estándar: lo reemplaza el buscador por etiquetas)
                    dom: '<"row"<"col-sm-12"tr>>' +
                        '<"row align-items-center mt-3"<"col-12 col-md-4"l><"col-12 col-md-4 text-center"i><"col-12 col-md-4 d-flex justify-content-md-end justify-content-center"p>>',
                    pageLength: 10, // arranca en 10 (antes 50): carga inicial más rápida, sobre todo con el fan-out ya resuelto por página
                    lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Todos"]],
                    buttons: [
                        {
                            extend: 'excelHtml5',
                            text: '<i class="fas fa-file-excel text-success"></i> Excel',
                            className: 'btn btn-sm btn-outline-success',
                            title: 'Productos',
                            exportOptions: {
                                columns: exportCols
                            }
                        },
                        {
                            extend: 'pdfHtml5',
                            text: '<i class="fas fa-file-pdf text-danger"></i> PDF',
                            className: 'btn btn-sm btn-outline-danger',
                            title: 'Productos',
                            orientation: 'landscape',
                            pageSize: 'A4',
                            exportOptions: {
                                columns: exportCols
                            }
                        },
                        {
                            extend: 'csvHtml5',
                            text: '<i class="fas fa-file-csv text-primary"></i> CSV',
                            className: 'btn btn-sm btn-outline-primary',
                            title: 'Productos',
                            exportOptions: {
                                columns: exportCols
                            }
                        },
                        {
                            extend: 'print',
                            text: '<i class="fas fa-print text-secondary"></i> Imprimir',
                            className: 'btn btn-sm btn-outline-secondary',
                            title: 'Listado de Productos',
                            exportOptions: {
                                columns: exportCols
                            }
                        }
                    ],
                    columns: [
                        { data: 'producto_codigo', className: 'text-center fw-medium', width: '100px', responsivePriority: 2 },
                        {
                            data: 'producto_nombre',
                            responsivePriority: 3,
                            render: function(data, type) {
                                if (type === 'export') return data;
                                return '<div class="fw-medium">' + data + '</div>';
                            }
                        },
                        {
                            // Cada columna de compatibilidad usa la MISMA fuente de
                            // datos (compatibilidades_detalle) y arma una mini-tabla
                            // con una fila por combinación real. Como las 4 columnas
                            // recorren el mismo array en el mismo orden, sus filas se
                            // alinean entre sí (fila 1 de Marca = fila 1 de Modelo =
                            // fila 1 de Submodelo = fila 1 de Años, etc).
                            data: 'compatibilidades_detalle', width: '150px', responsivePriority: 7,
                            orderable: false, searchable: false,
                            render: function(data, type) {
                                if (type === 'export') {
                                    if (!data || !data.length) return '';
                                    return data.map(function(c) { return c.marca; }).join(' | ');
                                }
                                if (!data || !data.length) return '<span class="text-muted">-</span>';
                                var filas = data.map(function(c) {
                                    return '<tr><td class="compat-td-marca">' + c.marca + '</td></tr>';
                                }).join('');
                                return '<table class="compat-col-table"><tbody>' + filas + '</tbody></table>';
                            }
                        },
                        {
                            data: 'compatibilidades_detalle', width: '150px', responsivePriority: 8,
                            orderable: false, searchable: false,
                            render: function(data, type) {
                                if (type === 'export') {
                                    if (!data || !data.length) return '';
                                    return data.map(function(c) { return c.modelo; }).join(' | ');
                                }
                                if (!data || !data.length) return '<span class="text-muted">-</span>';
                                var filas = data.map(function(c) {
                                    return '<tr><td class="compat-td-modelo">' + c.modelo + '</td></tr>';
                                }).join('');
                                return '<table class="compat-col-table"><tbody>' + filas + '</tbody></table>';
                            }
                        },
                        {
                            data: 'compatibilidades_detalle', width: '150px', responsivePriority: 9,
                            orderable: false, searchable: false,
                            render: function(data, type) {
                                if (type === 'export') {
                                    if (!data || !data.length) return '';
                                    return data.map(function(c) { return c.submodelo || ''; }).join(' | ');
                                }
                                if (!data || !data.length) return '<span class="text-muted">-</span>';
                                var filas = data.map(function(c) {
                                    return '<tr><td class="compat-td-submodelo">' + (c.submodelo || '-') + '</td></tr>';
                                }).join('');
                                return '<table class="compat-col-table"><tbody>' + filas + '</tbody></table>';
                            }
                        },
                        {
                            data: 'compatibilidades_detalle', width: '100px', className: 'text-center', responsivePriority: 6,
                            orderable: false, searchable: false,
                            render: function(data, type) {
                                if (type === 'export') {
                                    if (!data || !data.length) return '';
                                    return data.map(function(c) { return c.anios; }).join(' | ');
                                }
                                if (!data || !data.length) return '<span class="text-muted">-</span>';
                                var filas = data.map(function(c) {
                                    return '<tr><td class="compat-td-anios">' + c.anios + '</td></tr>';
                                }).join('');
                                return '<table class="compat-col-table"><tbody>' + filas + '</tbody></table>';
                            }
                        },
                        {
                            data: 'ubicaciones_info', width: '200px', responsivePriority: 4,
                            render: function(data, type, row) {
                                if (type === 'export') return data || 'Sin ubicaciones';
                                if (!data) return '<span class="text-muted">Sin ubicación</span>';
                                if (row.ubicaciones_detalle && row.ubicaciones_detalle.length > 0) {
                                    var html = '<div class="ubicaciones-container">';
                                    var mostrar = row.ubicaciones_detalle.slice(0, 2);
                                    $.each(mostrar, function(idx, u) {
                                        var partes = [];
                                        if (u.seccion) partes.push('<span class="badge badge-ubicacion badge-seccion">' + u.seccion + '</span>');
                                        if (u.estanteria) partes.push('<span class="badge badge-ubicacion badge-estanteria">' + u.estanteria + '</span>');
                                        if (u.estante) partes.push('<span class="badge badge-ubicacion badge-estante">' + u.estante + '</span>');
                                        if (u.posicion) partes.push('<span class="badge badge-ubicacion badge-posicion">' + u.posicion + '</span>');
                                        if (partes.length > 0) {
                                            html += '<div class="ubicacion-item"><div class="d-flex flex-wrap align-items-center gap-1">' + partes.join(' ') + '</div></div>';
                                        }
                                    });
                                    html += '</div>';
                                    return html;
                                }
                                var ubicaciones = data.split('; ');
                                var html = '<div class="ubicaciones-container">';
                                var mostradas = ubicaciones.slice(0, 2);
                                $.each(mostradas, function(idx, ubicacion) {
                                    if (!ubicacion) return true;
                                    var seccion = '', estanteria = '', estante = '', posicion = '';
                                    var parts = ubicacion.split(': ');
                                    var detalle = parts[1] || '';
                                    var seccionMatch = detalle.match(/^([A-Za-zÁÉÍÓÚáéíóúÑñ\s]+)/);
                                    if (seccionMatch) seccion = seccionMatch[1].trim();
                                    var estMatch = detalle.match(/Est(?:\.|antería)?\s*[:]?\s*([A-Za-z0-9]+)/i);
                                    if (estMatch) estanteria = estMatch[1];
                                    var nroMatch = detalle.match(/Nro(?:\.)?\s*[:]?\s*([A-Za-z0-9]+)/i);
                                    if (nroMatch) estante = nroMatch[1];
                                    var posMatch = detalle.match(/Pos(?:\.)?\s*[:]?\s*([A-Za-z0-9]+)/i);
                                    if (posMatch) posicion = posMatch[1];
                                    var partes = [];
                                    if (seccion) partes.push('<span class="badge badge-ubicacion badge-seccion">' + seccion + '</span>');
                                    if (estanteria) partes.push('<span class="badge badge-ubicacion badge-estanteria">' + estanteria + '</span>');
                                    if (estante) partes.push('<span class="badge badge-ubicacion badge-estante">' + estante + '</span>');
                                    if (posicion) partes.push('<span class="badge badge-ubicacion badge-posicion">' + posicion + '</span>');
                                    if (partes.length > 0) {
                                        html += '<div class="ubicacion-item"><div class="d-flex flex-wrap align-items-center gap-1">' + partes.join(' ') + '</div></div>';
                                    }
                                });
                                html += '</div>';
                                return html;
                            }
                        },
                        {
                            data: 'proveedores_detalle', width: '140px', responsivePriority: 11,
                            orderable: false, searchable: false,
                            render: function(data, type) {
                                if (type === 'export') {
                                    if (!data || !data.length) return '';
                                    return data.map(function(p) { return p.codigo_proveedor; }).join(' | ');
                                }
                                if (!data || !data.length) return '<span class="text-muted">-</span>';
                                return data.map(function(p) {
                                    var titulo = escapeHtml(p.entidad_nombre || 'Proveedor');
                                    var codigo = escapeHtml(p.codigo_proveedor || '');
                                    return '<span class="badge bg-secondary d-block mb-1" title="' + titulo + '">' + codigo + '</span>';
                                }).join('');
                            }
                        },
                        {
                            data: 'imagen_id_principal', width: '80px', className: 'text-center', orderable: false, searchable: false, responsivePriority: 10,
                            render: function(data, type, row) {
                                if (type === 'export') return data ? 'Sí' : 'No';
                                if (data) {
                                    var rutaImagen = 'get_imagen.php?id=' + data;
                                    var titulo = (row.producto_nombre || '').replace(/'/g, "\\'");
                                    return '<img src="' + rutaImagen + '" alt="Prod" class="img-thumbnail rounded-circle" style="width: 50px; height: 50px; object-fit: cover; cursor: pointer;" onclick="mostrarImagenGrande(\'' + rutaImagen + '\', \'' + titulo + '\', ' + row.producto_id + ')" onerror="this.onerror=null; this.src=\'data:image/svg+xml;utf8,<svg xmlns=\\\'http://www.w3.org/2000/svg\\\' width=\\\'50\\\' height=\\\'50\\\'><rect width=\\\'50\\\' height=\\\'50\\\' fill=\\\'#f8f9fa\\\'/><text x=\\\'25\\\' y=\\\'25\\\' text-anchor=\\\'middle\\\' fill=\\\'#6c757d\\\' font-family=\\\'Arial\\\' font-size=\\\'10\\\'>Error</text></svg>\';">';
                                }
                                return '<div class="text-center text-muted"><i class="fas fa-image fa-lg"></i></div>';
                            }
                        },
                    ].concat(columnasListasPrecios, [
                        {
                            data: 'botones', orderable: false, searchable: false, className: "text-center", width: '120px', responsivePriority: 1,
                            render: function(data, type, row) {
                                if (type === 'export' || !data) return '';
                                return data.map(function(boton) {
                                    var clase = 'btn-xs me-1 ';
                                    var nombre = boton.accion_js || boton.nombre_funcion.toLowerCase();
                                    clase += nombre === 'editar' ? 'btn-outline-primary' :
                                            (nombre === 'eliminar' || nombre === 'baja') ? 'btn-outline-danger' :
                                            (nombre === 'alta' || nombre === 'activar') ? 'btn-outline-success' :
                                            (nombre === 'suspender' || nombre === 'bloquear') ? 'btn-outline-warning' : 'btn-outline-secondary';
                                    return '<button type="button" class="btn ' + clase + ' btn-accion" title="' + (boton.descripcion || boton.nombre_funcion) + '" data-id="' + row.producto_id + '" data-accion="' + boton.accion_js + '" data-confirmable="' + (boton.es_confirmable || 0) + '" data-producto="' + row.producto_nombre + '"><i class="' + (boton.icono_clase || 'fas fa-cog') + '"></i></button>';
                                }).join('') || '<span class="text-muted small">-</span>';
                            }
                        }
                    ]),
                    language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' },
                    order: currentOrder,
                    responsive: true,
                    createdRow: function(row, data) {
                        if (data.estado_info && data.estado_info.codigo_estandar === 'INACTIVO') $(row).addClass('table-secondary');
                        else if (data.estado_info && data.estado_info.codigo_estandar === 'BLOQUEADO') $(row).addClass('table-warning');
                    },
                    initComplete: function() {
                        this.api().buttons().container().appendTo('#exportButtonsContainer');
                        
                        if (currentSearch && currentSearch.length > 0) {
                            deserializarTags(currentSearch).forEach(function(termino) {
                                agregarTag(termino);
                            });
                            setTimeout(function() { ejecutarBusquedaTags(); }, 100);
                        }
                        inicializarEventos();
                    }
                });

                // Evento draw para actualizar el indicador de filtros activos (botón X)
                tabla.on('draw.dt', function() {
                    actualizarIndicadorFiltros();
                });
            }

            // ========== FUNCIONES DE EVENTOS ==========

            function inicializarEventos() {
                // Botón Recargar
                $('#btnRecargar').off('click').on('click', function() {
                    var btn = $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
                    tabla.ajax.reload(function() { btn.prop('disabled', false).html('<i class="fas fa-sync-alt"></i>'); });
                });

                // === FILTROS AUTOMÁTICOS ===
                
                // Filtro de código con debounce
                $('#filtroCodigo').off('keyup').on('keyup', function() {
                    clearTimeout(timeoutCodigo);
                    var valor = $(this).val();
                    if (valor.trim().length > 0) {
                        $('#codigoSearchIcon').show();
                    } else {
                        $('#codigoSearchIcon').hide();
                    }
                    timeoutCodigo = setTimeout(function() {
                        tabla.ajax.reload();
                    }, 400);
                });

                // Filtro de marca - automático al cambiar
                $('#filtroMarca').off('change').on('change', function() {
                    var marcaId = $(this).val();
                    if (marcaId) {
                        cargarModelos(marcaId, '#filtroModelo');
                    } else {
                        $('#filtroModelo, #filtroSubmodelo').val('').prop('disabled', true);
                    }
                    tabla.ajax.reload();
                });

                // Filtro de modelo - automático al cambiar
                $('#filtroModelo').off('change').on('change', function() {
                    var modeloId = $(this).val();
                    if (modeloId) {
                        cargarSubmodelos(modeloId, '#filtroSubmodelo');
                    } else {
                        $('#filtroSubmodelo').val('').prop('disabled', true);
                    }
                    tabla.ajax.reload();
                });

                // Filtro de submodelo - automático al cambiar
                $('#filtroSubmodelo').off('change').on('change', function() {
                    tabla.ajax.reload();
                });

                // Filtro rápido de estado - automático al cambiar
                $('#filtroEstado').off('change').on('change', function() {
                    tabla.ajax.reload();
                });

                // La X del buscador ahora limpia TODOS los filtros (antes
                // era un botón "Limpiar filtros" aparte, más la X de tags
                // por separado — se unificaron en un solo control).
                $('#btnLimpiarTags').off('click').on('click', function() {
                    $('#filtroCodigo').val('');
                    $('#codigoSearchIcon').hide();
                    $('#filtroMarca').val('');
                    $('#filtroModelo').val('').prop('disabled', true);
                    $('#filtroSubmodelo').val('').prop('disabled', true);
                    limpiarTags();
                    // ejecutarBusquedaTags() ya limpia el search y hace
                    // tabla.draw() (server-side: dispara el ajax reload con
                    // los filtros ya vacíos) — no hace falta un reload aparte.
                    ejecutarBusquedaTags();
                });

                // Acciones de productos: en la consulta solo existe "visualizar"
                $(document).on('click', '.btn-accion', function() {
                    if ($(this).data('accion') === 'visualizar') cargarProductoParaVer($(this).data('id'));
                });
            }

            // ========== INICIALIZACIÓN ==========

            // Inicializar buscador de tags
            inicializarBuscadorTags();

            // Inicializar DataTable (las listas de precios ya vinieron resueltas desde el
            // servidor; el filtro de estado se resuelve antes para que la primera carga
            // ya salga filtrada en "Activo", no sin filtro)
            cargarFiltroEstado(function() {
                inicializarDataTable(listasPreciosIniciales);
            });

            // Cargar datos iniciales
            cargarTiposProducto();
            cargarUnidadesMedida();
            cargarMarcas();
            cargarIvaAlicuotas();

            // Mostrar/ocultar ícono de búsqueda en el campo de código al cargar
            if ($('#filtroCodigo').val().trim().length > 0) {
                $('#codigoSearchIcon').show();
            }

            $('[title]').tooltip({ trigger: 'hover', placement: 'top' });
        });
    </script>

    <!-- Librerías necesarias -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css">
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.4.1/js/buttons.print.min.js"></script>

    <!-- Extensión Responsive de DataTables (necesaria para que responsive:true funcione) -->
    <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css">
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
    <script src="https://cdn.datatables.net/responsive/2.5.0/js/responsive.bootstrap5.min.js"></script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>