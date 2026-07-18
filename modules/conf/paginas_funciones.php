<?php
// Configuración de la página
$pageTitle = "Gestión de Funciones de Páginas";
$currentPage = 'paginas_funciones';
$modudo_idx = 1;

// Definir constante para rutas
define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));

// Incluir header
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>
<style>
/* Estilos para mejorar visibilidad */
.table th {
    background-color: #f8f9fa;
    color: #495057;
    font-weight: 600;
}

.badge {
    font-size: 0.85em;
    padding: 0.35em 0.65em;
}

.btn-preview {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 6px 12px;
    border-radius: 4px;
    font-size: 0.875rem;
    border: 1px solid transparent;
}

.btn-preview-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 4px;
    border: 1px solid transparent;
}

.btn-preview-outline {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 11px;
    border-radius: 4px;
    font-size: 0.875rem;
    background-color: transparent;
}

.color-preview {
    display: inline-block;
    width: 20px;
    height: 20px;
    border-radius: 3px;
    border: 1px solid #dee2e6;
    vertical-align: middle;
    margin-right: 5px;
}

/* Estilos para jstree */
.jstree-default .jstree-node {
    margin-left: 0;
}

.jstree-default .jstree-anchor {
    line-height: 28px;
    height: 28px;
    padding: 0 4px 0 2px;
}

.jstree-default .jstree-icon {
    line-height: 28px;
    height: 28px;
    width: 28px;
}

.jstree-default .jstree-wholerow {
    height: 28px;
}

.jstree-default .jstree-clicked {
    background: #bee5eb !important;
    border-radius: 3px;
}

.jstree-default .jstree-hovered {
    background: #e8f0fe !important;
    border-radius: 3px;
}

.jstree-default .jstree-node[aria-level="1"] > .jstree-anchor .jstree-icon {
    color: #f39c12;
    font-size: 1.1em;
}

/* Estilos específicos para colores de Bootstrap */
.bg-primary { background-color: #007bff !important; color: white !important; }
.bg-secondary { background-color: #6c757d !important; color: white !important; }
.bg-success { background-color: #28a745 !important; color: white !important; }
.bg-danger { background-color: #dc3545 !important; color: white !important; }
.bg-warning { background-color: #ffc107 !important; color: #212529 !important; }
.bg-info { background-color: #17a2b8 !important; color: white !important; }
.bg-light { background-color: #f8f9fa !important; color: #212529 !important; border: 1px solid #dee2e6 !important; }
.bg-dark { background-color: #343a40 !important; color: white !important; }

.border-primary { border-color: #007bff !important; color: #007bff !important; }
.border-secondary { border-color: #6c757d !important; color: #6c757d !important; }

/* Estilos para el árbol de funciones */
.funcion-icon {
    margin-right: 8px;
    font-size: 1.1em;
}

.funcion-estado {
    font-size: 0.75em;
    padding: 2px 8px;
    border-radius: 10px;
    margin-left: 8px;
}

.funcion-accion {
    font-size: 0.8em;
    color: #6c757d;
    margin-left: 8px;
}
</style>

<main class="app-main">
    <!--begin::App Content Header-->
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Funciones de Páginas</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Funciones de Páginas</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <!--end::App Content Header-->
    
    <!--begin::App Content-->
    <div class="app-content">
        <div class="container-fluid">
            <div class="content-wrapper">
                <section class="content">
                    <div class="container-fluid">
                        <div class="row">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <h4 class="card-title">Estructura de Funciones por Página</h4>
                                            <div>
                                                <button class="btn btn-primary btn-sm" id="btnNuevo">
                                                    <i class="fas fa-plus"></i> Nueva Función
                                                </button>
                                                <button class="btn btn-success btn-sm" id="btnExpandirTodo">
                                                    <i class="fas fa-expand"></i> Expandir Todo
                                                </button>
                                                <button class="btn btn-warning btn-sm" id="btnColapsarTodo">
                                                    <i class="fas fa-compress"></i> Colapsar Todo
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                       <!-- Filtros -->
                                        <div class="row mb-3">
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Filtrar por Módulo:</label>
                                                    <select class="form-control form-control-sm" id="filtroModulo">
                                                        <option value="">Todos los módulos</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Filtrar por Página:</label>
                                                    <select class="form-control form-control-sm" id="filtroPagina">
                                                        <option value="">Todas las páginas</option>
                                                    </select>
                                                </div>
                                            </div>
                                            <div class="col-md-3">
                                                <div class="form-group">
                                                    <label>Buscar función:</label>
                                                    <input type="text" class="form-control form-control-sm" id="buscarFuncion" placeholder="Escriba para buscar...">
                                                </div>
                                            </div>
                                            <div class="col-md-3 d-flex align-items-end">
                                                <div class="btn-group">
                                                    <button class="btn btn-outline-info btn-sm" id="vistaArbol">
                                                        <i class="fas fa-sitemap"></i> Vista Árbol
                                                    </button>
                                                    <button class="btn btn-outline-secondary btn-sm" id="vistaTabla">
                                                        <i class="fas fa-table"></i> Vista Tabla
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Vista de Árbol -->
                                        <div id="vistaArbolContainer">
                                            <div id="arbolFunciones" class="jstree">
                                                <div class="text-center py-4">
                                                    <div class="spinner-border text-primary" role="status">
                                                        <span class="visually-hidden">Cargando...</span>
                                                    </div>
                                                    <p class="mt-2 text-muted">Cargando estructura de funciones...</p>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Vista de Tabla (oculta por defecto) -->
                                        <div id="vistaTablaContainer" style="display: none;">
                                            <table id="tablaPaginasFunciones" class="table table-striped table-bordered">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Página</th>
                                                        <th>Icono</th>
                                                        <th>Color</th>
                                                        <th>Nombre Función</th>
                                                        <th>Función Estándar</th>
                                                        <th>Acción JS</th>
                                                        <th>Descripción</th>
                                                        <th>Estado Origen</th>
                                                        <th>Estado Destino</th>
                                                        <th>Orden</th>
                                                        <th>Vista Previa</th>
                                                        <th>Estado</th>
                                                        <th>Acciones</th>
                                                    </tr>
                                                </thead>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</main>

<!-- Modal -->
<div class="modal fade" id="modalPaginaFuncion" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalLabel">Función de Página</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <form id="formPaginaFuncion">
                    <input type="hidden" id="pagina_funcion_id" name="pagina_funcion_id" />
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label>Página <span class="text-danger">*</span></label>
                            <select class="form-control" id="pagina_id" name="pagina_id" required>
                                <option value="">Seleccionar Página</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label>Nombre Función <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="nombre_funcion" name="nombre_funcion" required/>
                        </div>
                        <div class="col-md-6">
                            <label>Acción JS</label>
                            <input type="text" class="form-control" id="accion_js" name="accion_js" placeholder="Ej: funcionEditar"/>
                        </div>
                        <div class="col-md-6">
                            <label>Función Estándar</label>
                            <select class="form-control" id="funcion_estandar_id" name="funcion_estandar_id">
                                <option value="">Seleccionar Función Estándar</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label>Icono</label>
                            <select class="form-control" id="icono_id" name="icono_id">
                                <option value="">Seleccionar Icono</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label>Color</label>
                            <select class="form-control" id="color_id" name="color_id">
                                <option value="">Seleccionar Color</option>
                            </select>
                            <div class="mt-2">
                                <small class="text-muted">Vista previa del color:</small>
                                <div id="colorPreview" class="color-preview bg-primary"></div>
                                <span id="colorName">Primario</span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label>Orden</label>
                            <input type="number" class="form-control" id="orden" name="orden" value="0" min="0"/>
                        </div>
                        <div class="col-md-6">
                            <label>Estado Origen <span class="text-danger">*</span></label>
                            <select class="form-control" id="tabla_estado_registro_origen_id" name="tabla_estado_registro_origen_id" required>
                                <option value="0">Sin estado (0)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label>Estado Destino <span class="text-danger">*</span></label>
                            <select class="form-control" id="tabla_estado_registro_destino_id" name="tabla_estado_registro_destino_id" required>
                                <option value="">Seleccionar Estado</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label>Estado Registro</label>
                            <select class="form-control" id="tabla_estado_registro_id" name="tabla_estado_registro_id">
                                <option value="1" selected>Activo</option>
                                <option value="0">Inactivo</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label>Descripción</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="2"></textarea>
                        </div>
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="card-title mb-0">Vista previa del botón</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-4 text-center">
                                            <p class="mb-2"><small>Con icono y texto:</small></p>
                                            <button id="previewFullButton" class="btn btn-primary btn-sm">
                                                <i class="fas fa-cog"></i> Función
                                            </button>
                                        </div>
                                        <div class="col-md-4 text-center">
                                            <p class="mb-2"><small>Solo icono:</small></p>
                                            <button id="previewIconButton" class="btn btn-primary btn-sm">
                                                <i class="fas fa-cog"></i>
                                            </button>
                                        </div>
                                        <div class="col-md-4 text-center">
                                            <p class="mb-2"><small>Solo texto:</small></p>
                                            <button id="previewTextButton" class="btn btn-primary btn-sm">
                                                Función
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button id="btnGuardar" class="btn btn-success">Guardar</button>
                <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/jstree.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jstree/3.3.12/themes/default/style.min.css" />
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
// Variables globales
var tabla;
var arbolInstance = null;
var vistaActual = 'arbol';

// Mapa de colores Bootstrap
const bootstrapColors = {
    'btn-primary': { bg: 'primary', text: 'white', hex: '#007bff' },
    'btn-secondary': { bg: 'secondary', text: 'white', hex: '#6c757d' },
    'btn-success': { bg: 'success', text: 'white', hex: '#28a745' },
    'btn-danger': { bg: 'danger', text: 'white', hex: '#dc3545' },
    'btn-warning': { bg: 'warning', text: 'dark', hex: '#ffc107' },
    'btn-info': { bg: 'info', text: 'white', hex: '#17a2b8' },
    'btn-light': { bg: 'light', text: 'dark', hex: '#f8f9fa' },
    'btn-dark': { bg: 'dark', text: 'white', hex: '#343a40' },
    'btn-outline-primary': { bg: 'outline-primary', text: 'primary', outline: true, hex: '#007bff' },
    'btn-outline-secondary': { bg: 'outline-secondary', text: 'secondary', outline: true, hex: '#6c757d' }
};

// Función para aplicar clase de color
function applyColorClass(element, colorClass, isOutline = false) {
    element.removeClass('btn-primary btn-secondary btn-success btn-danger btn-warning btn-info btn-light btn-dark btn-outline-primary btn-outline-secondary');
    if (isOutline) {
        element.addClass('btn btn-outline-' + colorClass);
    } else {
        element.addClass('btn btn-' + colorClass);
    }
}

// Función para actualizar la vista previa del color
function updateColorPreview(colorClass) {
    if (!colorClass) return;
    var colorInfo = bootstrapColors[colorClass] || bootstrapColors['btn-primary'];
    var previewClass = colorClass.includes('outline') ? 'border-' + colorClass.replace('btn-outline-', '') : 'bg-' + colorClass.replace('btn-', '');
    $('#colorPreview').removeClass().addClass('color-preview ' + previewClass);
    $('#colorName').text(colorClass.replace('btn-', '').replace('outline-', 'Outline '));
}

// Función para actualizar la vista previa de botones
function updateButtonPreviews(colorClass, iconoClase, funcionNombre) {
    if (!colorClass) colorClass = 'btn-primary';
    if (!iconoClase) iconoClase = 'fa-cog';
    if (!funcionNombre) funcionNombre = 'Función';
    
    var colorInfo = bootstrapColors[colorClass] || bootstrapColors['btn-primary'];
    var isOutline = colorInfo.outline || false;
    var cleanClass = colorClass.replace('btn-', '').replace('outline-', '');
    
    applyColorClass($('#previewFullButton'), cleanClass, isOutline);
    $('#previewFullButton').html('<i class="fas ' + iconoClase + '"></i> ' + funcionNombre);
    
    applyColorClass($('#previewIconButton'), cleanClass, isOutline);
    $('#previewIconButton').html('<i class="fas ' + iconoClase + '"></i>');
    
    applyColorClass($('#previewTextButton'), cleanClass, isOutline);
    $('#previewTextButton').text(funcionNombre);
}

// Cargar páginas para el filtro y el formulario
function cargarPaginas(selectedId = null) {
    $.ajax({
        url: 'paginas_funciones_ajax.php',
        type: 'GET',
        data: {accion: 'obtenerPaginas'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Seleccionar página</option>';
                var filtroOptions = '<option value="">Todas las páginas</option>';
                $.each(res, function(index, pagina) {
                    var selected = (selectedId == pagina.pagina_id) ? 'selected' : '';
                    options += `<option value="${pagina.pagina_id}" ${selected}>${pagina.pagina} (${pagina.url})</option>`;
                    filtroOptions += `<option value="${pagina.pagina_id}">${pagina.pagina}</option>`;
                });
                $('#pagina_id').html(options);
                $('#filtroPagina').html(filtroOptions);
            }
        },
        error: function() {
            $('#pagina_id').html('<option value="">Error al cargar páginas</option>');
            $('#filtroPagina').html('<option value="">Error al cargar páginas</option>');
        }
    });
}

// Cargar iconos
function cargarIconos(selectedId = null) {
    $.ajax({
        url: 'paginas_funciones_ajax.php',
        type: 'GET',
        data: {accion: 'obtenerIconos'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Seleccionar Icono</option>';
                $.each(res, function(index, icono) {
                    var selected = (selectedId == icono.icono_id) ? 'selected' : '';
                    var iconHtml = icono.icono_clase ? `<i class="${icono.icono_clase}"></i> ` : '';
                    options += `<option value="${icono.icono_id}" ${selected}>${iconHtml}${icono.icono_nombre}</option>`;
                });
                $('#icono_id').html(options);
            }
        },
        error: function() {
            $('#icono_id').html('<option value="">Error al cargar iconos</option>');
        }
    });
}

// Cargar colores
function cargarColores(selectedId = null) {
    $.ajax({
        url: 'paginas_funciones_ajax.php',
        type: 'GET',
        data: {accion: 'obtenerColores'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Seleccionar Color</option>';
                $.each(res, function(index, color) {
                    var selected = (selectedId == color.color_id) ? 'selected' : '';
                    var colorInfo = bootstrapColors[color.color_clase] || bootstrapColors['btn-primary'];
                    var textColor = colorInfo.text === 'dark' ? '#212529' : 'white';
                    var bgColor = colorInfo.hex;
                    var isOutline = color.color_clase.includes('outline');
                    var optionStyle = isOutline 
                        ? `style="border: 2px solid ${bgColor}; color: ${bgColor}; background-color: white;"`
                        : `style="background-color: ${bgColor}; color: ${textColor};"`;
                    options += `<option value="${color.color_id}" ${selected} ${optionStyle}>${color.nombre_color}</option>`;
                });
                $('#color_id').html(options);
                
                if (selectedId) {
                    var selectedColor = res.find(function(color) {
                        return color.color_id == selectedId;
                    });
                    if (selectedColor) {
                        updateColorPreview(selectedColor.color_clase);
                        updateButtonPreviews(
                            selectedColor.color_clase,
                            $('#icono_id option:selected').text().match(/fa-[\w-]+/)?.[0] || 'fa-cog',
                            $('#nombre_funcion').val() || 'Función'
                        );
                    }
                }
            }
        },
        error: function() {
            $('#color_id').html('<option value="">Error al cargar colores</option>');
        }
    });
}

// Cargar funciones estándar
function cargarFuncionesEstandar(selectedId = null) {
    $.ajax({
        url: 'paginas_funciones_ajax.php',
        type: 'GET',
        data: {accion: 'obtenerFuncionesEstandar'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Seleccionar Función Estándar</option>';
                $.each(res, function(index, funcion) {
                    var selected = (selectedId == funcion.pagina_funcion_id) ? 'selected' : '';
                    options += `<option value="${funcion.pagina_funcion_id}" ${selected}>${funcion.nombre_funcion}</option>`;
                });
                $('#funcion_estandar_id').html(options);
            }
        },
        error: function() {
            $('#funcion_estandar_id').html('<option value="">Error al cargar funciones</option>');
        }
    });
}

// Cargar estados de registro
function cargarEstadosRegistro(selectedOrigen = null, selectedDestino = null) {
    $.ajax({
        url: 'paginas_funciones_ajax.php',
        type: 'GET',
        data: {accion: 'obtenerEstadosRegistro'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var optionsOrigen = '<option value="0">Sin estado (0)</option>';
                var optionsDestino = '<option value="">Seleccionar Estado</option>';
                
                $.each(res, function(index, estado) {
                    var selectedOrigenAttr = (selectedOrigen == estado.estado_registro_id) ? 'selected' : '';
                    var selectedDestinoAttr = (selectedDestino == estado.estado_registro_id) ? 'selected' : '';
                    optionsOrigen += `<option value="${estado.estado_registro_id}" ${selectedOrigenAttr}>${estado.estado_registro}</option>`;
                    optionsDestino += `<option value="${estado.estado_registro_id}" ${selectedDestinoAttr}>${estado.estado_registro}</option>`;
                });
                
                $('#tabla_estado_registro_origen_id').html(optionsOrigen);
                $('#tabla_estado_registro_destino_id').html(optionsDestino);
            }
        },
        error: function() {
            $('#tabla_estado_registro_origen_id').html('<option value="0">Sin estado (0)</option>');
            $('#tabla_estado_registro_destino_id').html('<option value="">Error al cargar estados</option>');
        }
    });
}
// Cargar módulos para el filtro
function cargarModulosFiltro(selectedId = null) {
    $.ajax({
        url: 'paginas_funciones_ajax.php',
        type: 'GET',
        data: {accion: 'obtenerModulos'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Todos los módulos</option>';
                $.each(res, function(index, modulo) {
                    var selected = (selectedId == modulo.modulo_id) ? 'selected' : '';
                    options += `<option value="${modulo.modulo_id}" ${selected}>${modulo.modulo}</option>`;
                });
                $('#filtroModulo').html(options);
            }
        },
        error: function() {
            $('#filtroModulo').html('<option value="">Error al cargar módulos</option>');
        }
    });
}
// Función para cargar el árbol de funciones
// Función para cargar el árbol de funciones - CORREGIDA (sin duplicación de iconos)
function cargarArbol(filtroModulo = null, filtroPagina = null, textoBusqueda = null) {
    $.ajax({
        url: 'paginas_funciones_ajax.php',
        type: 'GET',
        data: {
            accion: 'obtenerArbolFunciones',
            modulo_id: filtroModulo || '',
            pagina_id: filtroPagina || '',
            busqueda: textoBusqueda || ''
        },
        dataType: 'json',
        success: function(data) {
            if ($('#arbolFunciones').length) {
                var container = $('#arbolFunciones');
                
                if (container.hasClass('jstree')) {
                    container.jstree('destroy');
                    container.empty();
                }
                
                if (!data || data.length === 0) {
                    container.html('<div class="text-center py-4 text-muted">No hay funciones disponibles</div>');
                    return;
                }
                
                container.jstree({
                    core: {
                        data: data,
                        animation: 200,
                        check_callback: true,
                        themes: {
                            name: 'default',
                            responsive: true,
                            variant: 'large'
                        },
                        multiple: false
                    },
                    plugins: ['search', 'state', 'types', 'contextmenu'],
                    search: {
                        case_insensitive: true,
                        show_only_matches: true
                    },
                    types: {
                        modulo: {
                            icon: 'fas fa-folder-open text-warning',
                            valid_children: ['pagina']
                        },
                        pagina: {
                            icon: 'fas fa-file-alt text-primary',
                            valid_children: ['activa', 'inactiva']
                        },
                        activa: {
                            icon: 'fas fa-check-circle text-success',
                            valid_children: []
                        },
                        inactiva: {
                            icon: 'fas fa-times-circle text-danger',
                            valid_children: []
                        }
                    },
                    contextmenu: {
                        items: function(node) {
                            var items = {};
                            
                            if (node.type === 'modulo') {
                                items = {
                                    addPage: {
                                        label: 'Agregar página',
                                        action: function() {
                                            window.location.href = 'paginas.php';
                                        },
                                        icon: 'fas fa-plus'
                                    }
                                };
                            } else if (node.type === 'pagina') {
                                var paginaId = node.id.replace('pagina_', '');
                                items = {
                                    addFunction: {
                                        label: 'Agregar función',
                                        action: function() {
                                            $('#pagina_id').val(paginaId);
                                            $('#pagina_funcion_id').val('');
                                            $('#formPaginaFuncion')[0].reset();
                                            $('#tabla_estado_registro_id').val('1');
                                            $('#tabla_estado_registro_origen_id').val('0');
                                            updateButtonPreviews('btn-primary', 'fa-cog', 'Función');
                                            updateColorPreview('btn-primary');
                                            $('#modalLabel').text('Nueva Función');
                                            var modal = new bootstrap.Modal(document.getElementById('modalPaginaFuncion'));
                                            modal.show();
                                        },
                                        icon: 'fas fa-plus'
                                    },
                                    viewPage: {
                                        label: 'Ver página',
                                        action: function() {
                                            // Mostrar información de la página
                                            var paginaNombre = node.text;
                                            var paginaData = node.data || {};
                                            Swal.fire({
                                                title: paginaNombre,
                                                html: `
                                                    <p><strong>URL:</strong> ${paginaData.url || '-'}</p>
                                                    <p><strong>Descripción:</strong> ${paginaData.descripcion || '-'}</p>
                                                `,
                                                icon: 'info'
                                            });
                                        },
                                        icon: 'fas fa-info-circle'
                                    }
                                };
                            } else if (node.type === 'activa' || node.type === 'inactiva') {
                                var funcionId = node.id.replace('funcion_', '');
                                items = {
                                    edit: {
                                        label: 'Editar',
                                        action: function() {
                                            editarFuncion(funcionId);
                                        },
                                        icon: 'fas fa-edit'
                                    },
                                    delete: {
                                        label: 'Eliminar',
                                        action: function() {
                                            eliminarFuncion(funcionId);
                                        },
                                        icon: 'fas fa-trash'
                                    },
                                    viewFunction: {
                                        label: 'Ver detalles',
                                        action: function() {
                                            var funcionData = node.data || {};
                                            var funcionNombre = funcionData.nombre || node.text;
                                            Swal.fire({
                                                title: funcionNombre,
                                                html: `
                                                    <p><strong>Acción JS:</strong> ${funcionData.accion_js || '-'}</p>
                                                    <p><strong>Descripción:</strong> ${funcionData.descripcion || '-'}</p>
                                                    <p><strong>Orden:</strong> ${funcionData.orden || '0'}</p>
                                                    <p><strong>Color:</strong> ${funcionData.color || '-'}</p>
                                                    <p><strong>Estado Origen:</strong> ${funcionData.estado_origen || '0'}</p>
                                                    <p><strong>Estado Destino:</strong> ${funcionData.estado_destino || '-'}</p>
                                                `,
                                                icon: 'info'
                                            });
                                        },
                                        icon: 'fas fa-info-circle'
                                    }
                                };
                            }
                            
                            return items;
                        }
                    }
                });
                
                container.on('select_node.jstree', function(e, data) {
                    var node = data.node;
                    if (node.type === 'activa' || node.type === 'inactiva') {
                        var funcionId = node.id.replace('funcion_', '');
                        editarFuncion(funcionId);
                    }
                });
                
                container.on('loaded.jstree', function() {
                    container.jstree('open_all');
                });
                
                arbolInstance = container;
            }
        },
        error: function(xhr, status, error) {
            console.error('Error cargando árbol:', error);
            $('#arbolFunciones').html(`
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> Error al cargar la estructura de funciones: ${error}
                </div>
            `);
        }
    });
}

// Función para editar una función
function editarFuncion(funcionId) {
    $.get('paginas_funciones_ajax.php', {accion: 'obtener', pagina_funcion_id: funcionId}, function(res){
        if(res){
            $('#pagina_funcion_id').val(res.pagina_funcion_id);
            $('#nombre_funcion').val(res.nombre_funcion);
            $('#accion_js').val(res.accion_js);
            $('#descripcion').val(res.descripcion);
            $('#orden').val(res.orden);
            $('#tabla_estado_registro_id').val(res.tabla_estado_registro_id);
            
            cargarPaginas(res.pagina_id);
            cargarIconos(res.icono_id);
            cargarColores(res.color_id);
            cargarFuncionesEstandar(res.funcion_estandar_id);
            
            setTimeout(function() {
                $('#tabla_estado_registro_origen_id').val(res.tabla_estado_registro_origen_id);
                $('#tabla_estado_registro_destino_id').val(res.tabla_estado_registro_destino_id);
            }, 300);
            
            $('#modalLabel').text('Editar Función');
            var modal = new bootstrap.Modal(document.getElementById('modalPaginaFuncion'));
            modal.show();
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudieron obtener los datos'
            });
        }
    }, 'json');
}

// Función para eliminar una función
function eliminarFuncion(funcionId) {
    Swal.fire({
        title: "¿Estás seguro?",
        text: "¡Esta acción no se puede deshacer!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonColor: "#3085d6",
        cancelButtonColor: "#d33",
        confirmButtonText: "Sí, eliminar",
        cancelButtonText: "Cancelar"
    }).then((result) => {
        if (result.isConfirmed) {
            $.get('paginas_funciones_ajax.php', {accion: 'eliminar', pagina_funcion_id: funcionId}, function(res){
                if(res.resultado){
                    cargarArbol($('#filtroPagina').val(), $('#buscarFuncion').val());
                    if (typeof tabla !== 'undefined' && tabla) {
                        tabla.ajax.reload();
                    }
                    Swal.fire({
                        icon: "success",
                        title: "¡Eliminado!",
                        text: "El registro ha sido eliminado",
                        showConfirmButton: false,
                        timer: 1000
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || "Error al eliminar el registro"
                    });
                }
            }, 'json');
        }
    });
}

$(document).ready(function(){
    // Inicializar selects
    cargarPaginas();
    cargarIconos();
    cargarColores();
    cargarFuncionesEstandar();
    cargarEstadosRegistro();
    
    // Cargar el árbol inicial
    // Cargar módulos para el filtro
cargarModulosFiltro();

// Cargar el árbol inicial
cargarArbol();

// Eventos de filtro
$('#filtroModulo').change(function() {
    if (vistaActual === 'arbol') {
        // Al cambiar el módulo, actualizar las páginas disponibles
        var moduloId = $(this).val();
        cargarPaginasFiltro(moduloId);
        cargarArbol(moduloId, $('#filtroPagina').val(), $('#buscarFuncion').val());
    } else {
        tabla.ajax.reload();
    }
});

$('#filtroPagina').change(function() {
    if (vistaActual === 'arbol') {
        cargarArbol($('#filtroModulo').val(), $(this).val(), $('#buscarFuncion').val());
    } else {
        tabla.ajax.reload();
    }
});

$('#buscarFuncion').on('keyup', function() {
    if (vistaActual === 'arbol') {
        var texto = $(this).val();
        if (texto.length > 2 || texto.length === 0) {
            cargarArbol($('#filtroModulo').val(), $('#filtroPagina').val(), texto);
        }
    }
});

// Función para cargar páginas según el módulo seleccionado
function cargarPaginasFiltro(moduloId = null) {
    $.ajax({
        url: 'paginas_funciones_ajax.php',
        type: 'GET',
        data: {accion: 'obtenerPaginas', modulo_id: moduloId},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Todas las páginas</option>';
                $.each(res, function(index, pagina) {
                    options += `<option value="${pagina.pagina_id}">${pagina.pagina}</option>`;
                });
                $('#filtroPagina').html(options);
            } else {
                $('#filtroPagina').html('<option value="">No hay páginas</option>');
            }
        },
        error: function() {
            $('#filtroPagina').html('<option value="">Error al cargar páginas</option>');
        }
    });
}
    
    // Event listeners para vista previa
    $('#color_id').change(function() {
        var colorId = $(this).val();
        if (colorId) {
            cargarColores(colorId);
        }
    });
    
    $('#icono_id, #nombre_funcion').on('change input', function() {
        var iconoClase = $('#icono_id option:selected').text().match(/fa-[\w-]+/)?.[0] || 'fa-cog';
        var funcionNombre = $('#nombre_funcion').val() || 'Función';
        var colorClass = $('#color_id option:selected').text().toLowerCase().includes('outline')
            ? 'btn-' + $('#color_id option:selected').text().toLowerCase().replace('outline ', 'outline-').replace(' primario', '-primary').replace(' secundario', '-secondary')
            : 'btn-' + $('#color_id option:selected').text().toLowerCase();
        
        updateButtonPreviews(colorClass, iconoClase, funcionNombre);
    });
    
    // Configuración de DataTable
    tabla = $('#tablaPaginasFunciones').DataTable({
        pageLength: 25,
        lengthMenu: [25, 50, 100, 200],
        dom: '<"row"<"col-md-6"l><"col-md-6"fB>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
        responsive: true,
        autoWidth: false,
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel"></i> Excel',
                titleAttr: 'Exportar a Excel',
                className: 'btn btn-success btn-sm me-2',
                exportOptions: { columns: ':visible' }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                titleAttr: 'Exportar a PDF',
                className: 'btn btn-danger btn-sm',
                orientation: 'landscape',
                pageSize: 'A4',
                exportOptions: { columns: ':visible' }
            }
        ],
        initComplete: function() {
            $('.dt-buttons').appendTo($('.dataTables_filter'));
            $('.dataTables_filter').css({
                'display': 'flex',
                'align-items': 'center',
                'gap': '10px'
            });
            $('.dataTables_filter input').addClass('form-control form-control-sm');
        },
        ajax: {
            url: 'paginas_funciones_ajax.php',
            type: 'GET',
            data: {accion: 'listar'},
            dataSrc: ''
        },
        language: {
            "search": "Buscar:",
            "searchPlaceholder": "Busqueda general...",
            "lengthMenu": "Mostrar _MENU_ registros por página",
            "zeroRecords": "No se encontraron resultados",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
            "infoEmpty": "Mostrando 0 a 0 de 0 registros",
            "infoFiltered": "(filtrado de _MAX_ registros totales)",
            "paginate": {
                "first": "Primero",
                "last": "Último",
                "next": "Siguiente",
                "previous": "Anterior"
            }
        },
        columns: [
            { data: 'pagina_funcion_id' },
            { 
                data: 'nombre_pagina',
                render: function(data, type, row) {
                    if (data) {
                        return data + '<br><small class="text-muted">' + (row.ruta_pagina || '') + '</small>';
                    }
                    return '<span class="text-muted">-</span>';
                }
            },
            { 
                data: 'icono_clase',
                className: "text-center",
                render: function(data) {
                    if (data) {
                        return `<div class="text-center"><i class="${data}" style="font-size: 1.2em;"></i></div>`;
                    }
                    return '<div class="text-center"><span class="text-muted">-</span></div>';
                }
            },
            { 
                data: 'color_nombre',
                render: function(data, type, row) {
                    if (row.color_clase) {
                        var colorInfo = bootstrapColors[row.color_clase] || bootstrapColors['btn-primary'];
                        var badgeClass = 'bg-' + colorInfo.bg;
                        return `<span class="badge ${badgeClass}">${data}</span>`;
                    }
                    return data || '<span class="text-muted">-</span>';
                }
            },
            { data: 'nombre_funcion' },
            { 
                data: 'funcion_estandar_nombre',
                render: function(data) {
                    return data || '<span class="text-muted">-</span>';
                }
            },
            { 
                data: 'accion_js',
                render: function(data) {
                    return data || '<span class="text-muted">-</span>';
                }
            },
            { 
                data: 'descripcion',
                render: function(data) {
                    if (data && data.length > 50) {
                        return data.substring(0, 50) + '...';
                    }
                    return data || '<span class="text-muted">-</span>';
                }
            },
            { 
                data: 'estado_origen',
                render: function(data) {
                    return data || '<span class="text-muted">-</span>';
                }
            },
            { 
                data: 'estado_destino',
                render: function(data) {
                    return data || '<span class="text-muted">-</span>';
                }
            },
            { 
                data: 'orden',
                className: "text-center"
            },
            { 
                data: null,
                className: "text-center",
                render: function(data, type, row) {
                    if (row.color_clase && row.icono_clase) {
                        var colorInfo = bootstrapColors[row.color_clase] || bootstrapColors['btn-primary'];
                        var isOutline = colorInfo.outline || false;
                        var btnClass = isOutline 
                            ? 'btn btn-outline-' + colorInfo.bg.replace('outline-', '') + ' btn-sm'
                            : 'btn btn-' + colorInfo.bg + ' btn-sm';
                        return `<button class="${btnClass}"><i class="${row.icono_clase}"></i> ${row.nombre_funcion}</button>`;
                    }
                    return '<span class="text-muted">-</span>';
                }
            },
            { 
                data: 'estado_nombre',
                render: function(data) {
                    return data || '<span class="text-muted">-</span>';
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: "text-center",
                render: function(data){
                    return `
                        <button class="btn btn-sm btn-primary btnEditar me-1" title="Editar">
                            <i class="fa fa-pencil-alt"></i>
                        </button>
                        <button class="btn btn-sm btn-danger btnEliminar" title="Eliminar">
                            <i class="fa fa-trash"></i>
                        </button>
                    `;
                }
            }
        ]
    });

    // Cambiar entre vista árbol y tabla
    $('#vistaArbol').click(function() {
        vistaActual = 'arbol';
        $('#vistaArbolContainer').show();
        $('#vistaTablaContainer').hide();
        $(this).removeClass('btn-outline-secondary').addClass('btn-outline-info');
        $('#vistaTabla').removeClass('btn-outline-info').addClass('btn-outline-secondary');
        cargarArbol($('#filtroPagina').val(), $('#buscarFuncion').val());
    });
    
    $('#vistaTabla').click(function() {
        vistaActual = 'tabla';
        $('#vistaArbolContainer').hide();
        $('#vistaTablaContainer').show();
        $(this).removeClass('btn-outline-secondary').addClass('btn-outline-info');
        $('#vistaArbol').removeClass('btn-outline-info').addClass('btn-outline-secondary');
        tabla.ajax.reload();
    });

    // Filtros
    $('#filtroPagina').change(function() {
        if (vistaActual === 'arbol') {
            cargarArbol($(this).val(), $('#buscarFuncion').val());
        } else {
            tabla.ajax.reload();
        }
    });
    
    $('#buscarFuncion').on('keyup', function() {
        if (vistaActual === 'arbol') {
            var texto = $(this).val();
            if (texto.length > 2) {
                cargarArbol($('#filtroPagina').val(), texto);
            } else if (texto.length === 0) {
                cargarArbol($('#filtroPagina').val());
            }
        }
    });
    
    // Expandir/Colapsar
    $('#btnExpandirTodo').click(function() {
        if (arbolInstance) {
            arbolInstance.jstree('open_all');
        }
    });
    
    $('#btnColapsarTodo').click(function() {
        if (arbolInstance) {
            arbolInstance.jstree('close_all');
        }
    });
    
    // Botón nueva función
    $('#btnNuevo').click(function(){
        $('#formPaginaFuncion')[0].reset();
        $('#pagina_funcion_id').val('');
        $('#tabla_estado_registro_id').val('1');
        $('#tabla_estado_registro_origen_id').val('0');
        updateButtonPreviews('btn-primary', 'fa-cog', 'Función');
        updateColorPreview('btn-primary');
        $('#modalLabel').text('Nueva Función de Página');
        
        cargarPaginas();
        cargarIconos();
        cargarColores();
        cargarFuncionesEstandar();
        cargarEstadosRegistro();
        
        var modal = new bootstrap.Modal(document.getElementById('modalPaginaFuncion'));
        modal.show();
    });

    // Eventos de la tabla
    $('#tablaPaginasFunciones tbody').on('click', '.btnEditar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        editarFuncion(data.pagina_funcion_id);
    });

    $('#tablaPaginasFunciones tbody').on('click', '.btnEliminar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        eliminarFuncion(data.pagina_funcion_id);
    });

    // Guardar función
    $('#btnGuardar').click(function(){
        if ($('#nombre_funcion').val().trim() === '' || 
            $('#pagina_id').val() === '' ||
            $('#tabla_estado_registro_destino_id').val() === '') {
            $('#formPaginaFuncion').addClass('was-validated');
            Swal.fire({
                icon: 'warning',
                title: 'Campos obligatorios',
                text: 'Los campos marcados con * son obligatorios'
            });
            return false;
        }
        
        var id = $('#pagina_funcion_id').val();
        var accion = id ? 'editar' : 'agregar';
        var formData = {
            accion: accion,
            pagina_funcion_id: id,
            nombre_funcion: $('#nombre_funcion').val(),
            pagina_id: $('#pagina_id').val(),
            accion_js: $('#accion_js').val(),
            descripcion: $('#descripcion').val(),
            orden: $('#orden').val(),
            icono_id: $('#icono_id').val() || null,
            color_id: $('#color_id').val() || null,
            funcion_estandar_id: $('#funcion_estandar_id').val() || null,
            tabla_estado_registro_origen_id: $('#tabla_estado_registro_origen_id').val(),
            tabla_estado_registro_destino_id: $('#tabla_estado_registro_destino_id').val(),
            tabla_estado_registro_id: $('#tabla_estado_registro_id').val() || 1
        };

        $.ajax({
            url: 'paginas_funciones_ajax.php',
            type: 'GET',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if(res.resultado) {
                    var modal = bootstrap.Modal.getInstance(document.getElementById('modalPaginaFuncion'));
                    modal.hide();
                    
                    $('#formPaginaFuncion')[0].reset();
                    $('#tabla_estado_registro_id').val('1');
                    $('#tabla_estado_registro_origen_id').val('0');
                    updateButtonPreviews('btn-primary', 'fa-cog', 'Función');
                    updateColorPreview('btn-primary');
                    $('#formPaginaFuncion').removeClass('was-validated');
                    
                    cargarArbol($('#filtroPagina').val(), $('#buscarFuncion').val());
                    if (typeof tabla !== 'undefined' && tabla) {
                        tabla.ajax.reload(null, false);
                    }
                    
                    Swal.fire({
                        icon: "success",
                        title: "¡Operación exitosa!",
                        showConfirmButton: false,
                        timer: 1500
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || "Error al guardar los datos"
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error de conexión con el servidor"
                });
            }
        });
    });
});
</script>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>