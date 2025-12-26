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

/* Asegurar contraste para colores claros */
.color-preview {
    display: inline-block;
    width: 20px;
    height: 20px;
    border-radius: 3px;
    border: 1px solid #dee2e6;
    vertical-align: middle;
    margin-right: 5px;
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

/* Estilos para botones outline */
.border-primary { border-color: #007bff !important; color: #007bff !important; }
.border-secondary { border-color: #6c757d !important; color: #6c757d !important; }
</style>

<main class="app-main">
    <!--begin::App Content Header-->
    <div class="app-content-header">
        <!--begin::Container-->
        <div class="container-fluid">
        <!--begin::Row-->
        <div class="row">
            <div class="col-sm-6"><h3 class="mb-0">Funciones de Páginas</h3></div>
            <div class="col-sm-6">
            <ol class="breadcrumb float-sm-end">
                <li class="breadcrumb-item"><a href="#">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Funciones de Páginas</li>
            </ol>
            </div>
        </div>
        <!--end::Row-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::App Content Header-->
    <!--begin::App Content-->
    <div class="app-content">
        <!--begin::Container-->
        <div class="container-fluid">
<!-- Content Wrapper -->
        <div class="content-wrapper">
        
            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">                      
                    <div class="row">
                        <div class="col-12">
                            <div class="card">                                
                                <div class="card-body">
                                    <button class="btn btn-primary mb-3" id="btnNuevo">Nueva Función</button>
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
                                                <th>Vista Previa Botón</th>
                                                <th>Botón con Icono</th>
                                                <th>Botón con Texto</th>
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
            </section>
        </div>

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
                    <label>Página *</label>
                    <select class="form-control" id="pagina_id" name="pagina_id" required>
                        <option value="">Seleccionar Página</option>
                        <!-- Options will be populated by JavaScript -->
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Nombre Función *</label>
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
                        <!-- Options will be populated by JavaScript -->
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Icono</label>
                    <select class="form-control" id="icono_id" name="icono_id">
                        <option value="">Seleccionar Icono</option>
                        <!-- Options will be populated by JavaScript -->
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Color</label>
                    <select class="form-control" id="color_id" name="color_id">
                        <option value="">Seleccionar Color</option>
                        <!-- Options will be populated by JavaScript -->
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
                    <label>Estado Origen *</label>
                    <select class="form-control" id="tabla_estado_registro_origen_id" name="tabla_estado_registro_origen_id" required>
                        <option value="0">Sin estado (0)</option>
                        <!-- Options will be populated by JavaScript -->
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Estado Destino *</label>
                    <select class="form-control" id="tabla_estado_registro_destino_id" name="tabla_estado_registro_destino_id" required>
                        <option value="">Seleccionar Estado</option>
                        <!-- Options will be populated by JavaScript -->
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
                    <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                </div>
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h6 class="card-title mb-0">Vista previa del botón</h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 text-center">
                                    <p class="mb-2"><small>Botón con icono y texto:</small></p>
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

<script>
// Mapa de colores Bootstrap (mismo que el anterior)
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

// Función para aplicar clase de color al elemento
function applyColorClass(element, colorClass, isOutline = false) {
    // Remover todas las clases de color anteriores
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
    
    // Botón completo (icono + texto)
    applyColorClass($('#previewFullButton'), colorClass.replace('btn-', '').replace('outline-', ''), isOutline);
    $('#previewFullButton').html('<i class="fas ' + iconoClase + '"></i> ' + funcionNombre);
    
    // Solo icono
    applyColorClass($('#previewIconButton'), colorClass.replace('btn-', '').replace('outline-', ''), isOutline);
    $('#previewIconButton').html('<i class="fas ' + iconoClase + '"></i>');
    
    // Solo texto
    applyColorClass($('#previewTextButton'), colorClass.replace('btn-', '').replace('outline-', ''), isOutline);
    $('#previewTextButton').text(funcionNombre);
}

// Función para cargar páginas
function cargarPaginas(selectedId = null) {
    $.ajax({
        url: 'paginas_funciones_ajax.php',
        type: 'GET',
        data: {accion: 'obtenerPaginas'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Seleccionar página</option>';
                $.each(res, function(index, pagina) {
                    var selected = (selectedId == pagina.pagina_id) ? 'selected' : '';
                    // CORRECCIÓN: Usar pagina.pagina y pagina.url
                    options += `<option value="${pagina.pagina_id}" ${selected}>${pagina.pagina} (${pagina.url})</option>`;
                });
                $('#pagina_id').html(options);
            }
        },
        error: function() {
            console.error('Error al cargar páginas');
            $('#pagina_id').html('<option value="">Error al cargar páginas</option>');
        }
    });
}

// Función para cargar iconos
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
            console.error('Error al cargar iconos');
            $('#icono_id').html('<option value="">Error al cargar iconos</option>');
        }
    });
}

// Función para cargar colores
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
                    
                    // Crear preview visual en el option
                    var optionStyle = isOutline 
                        ? `style="border: 2px solid ${bgColor}; color: ${bgColor}; background-color: white;"`
                        : `style="background-color: ${bgColor}; color: ${textColor};"`;
                    
                    options += `<option value="${color.color_id}" ${selected} ${optionStyle}>${color.nombre_color}</option>`;
                });
                $('#color_id').html(options);
                
                // Si hay un color seleccionado, actualizar vista previa
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
            console.error('Error al cargar colores');
            $('#color_id').html('<option value="">Error al cargar colores</option>');
        }
    });
}

// Función para cargar funciones estándar
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
            console.error('Error al cargar funciones estándar');
            $('#funcion_estandar_id').html('<option value="">Error al cargar funciones</option>');
        }
    });
}

// Función para cargar estados de registro
function cargarEstadosRegistro(selectedId = null, elementId = null) {
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
                    var selectedOrigen = (selectedId == estado.estado_registro_id && elementId === 'origen') ? 'selected' : '';
                    var selectedDestino = (selectedId == estado.estado_registro_id && elementId === 'destino') ? 'selected' : '';
                    
                    optionsOrigen += `<option value="${estado.estado_registro_id}" ${selectedOrigen}>${estado.estado_registro}</option>`;
                    optionsDestino += `<option value="${estado.estado_registro_id}" ${selectedDestino}>${estado.estado_registro}</option>`;
                });
                
                if (elementId === 'origen') {
                    $('#tabla_estado_registro_origen_id').html(optionsOrigen);
                } else if (elementId === 'destino') {
                    $('#tabla_estado_registro_destino_id').html(optionsDestino);
                } else {
                    $('#tabla_estado_registro_origen_id').html(optionsOrigen);
                    $('#tabla_estado_registro_destino_id').html(optionsDestino);
                }
            }
        },
        error: function() {
            console.error('Error al cargar estados de registro');
            $('#tabla_estado_registro_origen_id').html('<option value="0">Sin estado (0)</option>');
            $('#tabla_estado_registro_destino_id').html('<option value="">Error al cargar estados</option>');
        }
    });
}

$(document).ready(function(){
    // Cargar todos los selects
    cargarPaginas();
    cargarIconos();
    cargarColores();
    cargarFuncionesEstandar();
    cargarEstadosRegistro();
    
    // Event listeners para actualizar vista previa
    $('#color_id').change(function() {
        var colorId = $(this).val();
        if (colorId) {
            // Recargar colores para obtener datos actualizados
            cargarColores(colorId);
        }
    });
    
    $('#icono_id').change(function() {
        var iconoClase = $(this).find('option:selected').text().match(/fa-[\w-]+/)?.[0] || 'fa-cog';
        var funcionNombre = $('#nombre_funcion').val() || 'Función';
        var colorClass = $('#color_id option:selected').text().toLowerCase().includes('outline')
            ? 'btn-' + $('#color_id option:selected').text().toLowerCase().replace('outline ', 'outline-').replace(' primario', '-primary').replace(' secundario', '-secondary')
            : 'btn-' + $('#color_id option:selected').text().toLowerCase();
        
        updateButtonPreviews(colorClass, iconoClase, funcionNombre);
    });
    
    $('#nombre_funcion').on('input', function() {
        var funcionNombre = $(this).val() || 'Función';
        var iconoClase = $('#icono_id option:selected').text().match(/fa-[\w-]+/)?.[0] || 'fa-cog';
        var colorClass = $('#color_id option:selected').text().toLowerCase().includes('outline')
            ? 'btn-' + $('#color_id option:selected').text().toLowerCase().replace('outline ', 'outline-').replace(' primario', '-primary').replace(' secundario', '-secondary')
            : 'btn-' + $('#color_id option:selected').text().toLowerCase();
        
        updateButtonPreviews(colorClass, iconoClase, funcionNombre);
    });
    
    // Configuración de DataTable
    var tabla = $('#tablaPaginasFunciones').DataTable({
    pageLength: 25,
    lengthMenu: [25, 50, 100, 200],
    dom: '<"row"<"col-md-6"l><"col-md-6"fB>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
    responsive: true,  // ← Activar responsive
    autoWidth: false,  // ← Desactivar auto width para mejor control
    buttons: [
        {
            extend: 'excelHtml5',
            text: '<i class="fas fa-file-excel"></i> Excel',
            titleAttr: 'Exportar a Excel',
            className: 'btn btn-success btn-sm me-2',
            exportOptions: {
                columns: ':visible'
            }
        },
        {
            extend: 'pdfHtml5',
            text: '<i class="fas fa-file-pdf"></i> PDF',
            titleAttr: 'Exportar a PDF',
            className: 'btn btn-danger btn-sm',
            orientation: 'landscape',
            pageSize: 'A4',
            exportOptions: {
                columns: ':visible'
            }
        }
    ],
    initComplete: function() {
        // Mover los botones al contenedor del buscador
        $('.dt-buttons').appendTo($('.dataTables_filter'));
        
        // Aplicar estilos al contenedor
        $('.dataTables_filter').css({
            'display': 'flex',
            'align-items': 'center',
            'gap': '10px'
        });
        
        // Estilo para el input de búsqueda
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
                    } else {
                        return '<span class="text-muted">-</span>';
                    }
                }
            },
            { 
                data: 'icono_clase',
                className: "text-center",
                render: function(data) {
                    if (data) {
                        return `<div class="text-center"><i class="${data}" title="${data}" style="font-size: 1.2em;"></i></div>`;
                    } else {
                        return '<div class="text-center"><span class="text-muted">-</span></div>';
                    }
                }
            },
            { 
                data: 'color_nombre',
                render: function(data, type, row) {
                    if (row.color_clase) {
                        var colorInfo = bootstrapColors[row.color_clase] || bootstrapColors['btn-primary'];
                        var badgeClass = 'bg-' + colorInfo.bg;
                        return `<span class="badge ${badgeClass}">${data}</span>`;
                    } else if (data) {
                        return data;
                    } else {
                        return '<span class="text-muted">-</span>';
                    }
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
                render: function(data, type, row) {                    
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
                        
                        return `
                            <button class="${btnClass}">
                                <i class="${row.icono_clase}"></i> ${row.nombre_funcion}
                            </button>
                        `;
                    } else {
                        return '<span class="text-muted">No configurado</span>';
                    }
                }
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
                        
                        return `
                            <button class="${btnClass}">
                                <i class="${row.icono_clase}"></i>
                            </button>
                        `;
                    } else {
                        return '<span class="text-muted">-</span>';
                    }
                }
            },
            { 
                data: null,
                className: "text-center",
                render: function(data, type, row) {
                    if (row.color_clase) {
                        var colorInfo = bootstrapColors[row.color_clase] || bootstrapColors['btn-primary'];
                        var isOutline = colorInfo.outline || false;
                        var btnClass = isOutline 
                            ? 'btn btn-outline-' + colorInfo.bg.replace('outline-', '') + ' btn-sm'
                            : 'btn btn-' + colorInfo.bg + ' btn-sm';
                        
                        return `
                            <button class="${btnClass}">
                                ${row.nombre_funcion}
                            </button>
                        `;
                    } else {
                        return '<span class="text-muted">-</span>';
                    }
                }
            },
            { 
                data: 'estado_nombre',
                
                render: function(data, type, row) {
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

    $('#btnNuevo').click(function(){
        $('#formPaginaFuncion')[0].reset();
        $('#pagina_funcion_id').val('');
        $('#tabla_estado_registro_id').val('1');
        $('#tabla_estado_registro_origen_id').val('0');
        updateButtonPreviews('btn-primary', 'fa-cog', 'Función');
        updateColorPreview('btn-primary');
        $('#modalLabel').text('Nueva Función de Página');
        var modal = new bootstrap.Modal(document.getElementById('modalPaginaFuncion'));
        modal.show();
    });

   $('#tablaPaginasFunciones tbody').on('click', '.btnEditar', function(){
    var data = tabla.row($(this).parents('tr')).data();
    $.get('paginas_funciones_ajax.php', {accion: 'obtener', pagina_funcion_id: data.pagina_funcion_id}, function(res){
        if(res){
            $('#pagina_funcion_id').val(res.pagina_funcion_id);
            $('#nombre_funcion').val(res.nombre_funcion);
            $('#accion_js').val(res.accion_js);
            $('#descripcion').val(res.descripcion);
            $('#orden').val(res.orden);
            $('#tabla_estado_registro_id').val(res.tabla_estado_registro_id);
            
            // Cargar selects con valores actuales
            cargarPaginas(res.pagina_id);  // ← Usa res.pagina_id directamente
            cargarIconos(res.icono_id);
            cargarColores(res.color_id);
            cargarFuncionesEstandar(res.funcion_estandar_id);
            
            // Cargar estados específicos
            setTimeout(function() {
                $('#tabla_estado_registro_origen_id').val(res.tabla_estado_registro_origen_id);
                $('#tabla_estado_registro_destino_id').val(res.tabla_estado_registro_destino_id);
                
                // Actualizar vista previa después de cargar todo
                setTimeout(function() {
                    var colorClass = res.color_clase || 'btn-primary';
                    var iconoClase = res.icono_clase || 'fa-cog';
                    updateColorPreview(colorClass);
                    updateButtonPreviews(colorClass, iconoClase, res.nombre_funcion);
                }, 300);
            }, 500);

            $('#modalLabel').text('Editar Función de Página');
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
});

    $('#tablaPaginasFunciones tbody').on('click', '.btnEliminar', function(){
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
                var data = tabla.row($(this).parents('tr')).data();
                $.get('paginas_funciones_ajax.php', {accion: 'eliminar', pagina_funcion_id: data.pagina_funcion_id}, function(res){
                    if(res.resultado){
                        tabla.ajax.reload();
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
    });

    $('#btnGuardar').click(function(){
        // Validar campos obligatorios
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
                    tabla.ajax.reload(null, false);
                    
                    // Cerrar el modal correctamente
                    var modal = bootstrap.Modal.getInstance(document.getElementById('modalPaginaFuncion'));
                    modal.hide();
                    
                    // Resetear el formulario
                    $('#formPaginaFuncion')[0].reset();
                    $('#tabla_estado_registro_id').val('1');
                    $('#tabla_estado_registro_origen_id').val('0');
                    updateButtonPreviews('btn-primary', 'fa-cog', 'Función');
                    updateColorPreview('btn-primary');
                    $('#formPaginaFuncion').removeClass('was-validated');
                    
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>