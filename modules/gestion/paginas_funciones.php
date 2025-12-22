<?php
// Configuración de la página
$pageTitle = "Gestión de Funciones de Páginas";
$currentPage = 'funciones_paginas';
$modudo_idx = 2;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>
<main class="app-main">
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
    <div class="app-content">
        <div class="container-fluid">
            <div class="content-wrapper">
                <section class="content">
                    <div class="container-fluid">                      
                        <div class="row">
                            <div class="col-12">
                                <div class="card">                                
                                    <div class="card-body">
                                        <button class="btn btn-primary mb-3" id="btnNuevo">Nueva Función</button>
                                        <div class="table-responsive">
                                            <table id="tablaPaginasFunciones" class="table table-striped table-bordered dt-responsive nowrap" style="width:100%">
                                                <thead>
                                                    <tr>
                                                        <th>ID</th>
                                                        <th>Página</th>
                                                        <th>Tabla</th>
                                                        <th>Función</th>
                                                        <th>Acción JS</th>
                                                        <th>Icono</th>
                                                        <th>Estado Origen</th>
                                                        <th>Estado Destino</th>
                                                        <th>Orden</th>
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
                    <select class="form-select" id="pagina_id" name="pagina_id" required>
                        <option value="">Seleccionar página</option>
                    </select>
                    <div class="invalid-feedback">La página es obligatoria</div>
                </div>
                <div class="col-md-6">
                    <label>Tabla</label>
                    <select class="form-select" id="tabla_id" name="tabla_id">
                        <option value="">Seleccionar tabla</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Icono</label>
                    <select class="form-select" id="icono_id" name="icono_id">
                        <option value="">Seleccionar icono</option>
                        <!-- Las opciones se cargarán dinámicamente -->
                    </select>
                    <div class="form-text" id="icono_preview">
                        <small>Vista previa del icono aparecerá aquí</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <label>Color</label>
                    <select class="form-select" id="color_id" name="color_id">
                        <option value="">Seleccionar color</option>
                        <!-- Las opciones se cargarán dinámicamente -->
                    </select>
                    <div class="form-text" id="color_preview">
                        <small>Vista previa del color aparecerá aquí</small>
                    </div>
                </div>
                <div class="col-md-12">
                    <label>Nombre de Función *</label>
                    <input type="text" class="form-control" id="nombre_funcion" name="nombre_funcion" required/>
                    <div class="invalid-feedback">El nombre de función es obligatorio</div>
                </div>
                <div class="col-md-12">
                    <label>Acción JS</label>
                    <input type="text" class="form-control" id="accion_js" name="accion_js" placeholder="Nombre de la función JavaScript"/>
                    <div class="form-text">Nombre de la función JavaScript a ejecutar (opcional)</div>
                </div>
                <div class="col-md-12">
                    <label>Descripción</label>
                    <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                </div>
                <div class="col-md-6">
                    <label>Estado Origen *</label>
                    <select class="form-select" id="estado_registro_origen_id" name="estado_registro_origen_id">
                        <option value="">Seleccionar estado origen</option>
                    </select>
                    
                </div>
                <div class="col-md-6">
                    <label>Estado Destino *</label>
                    <select class="form-select" id="estado_registro_destino_id" name="estado_registro_destino_id" required>
                        <option value="">Seleccionar estado destino</option>
                    </select>
                    <div class="invalid-feedback">El estado destino es obligatorio</div>
                </div>
                
                <div class="col-md-6">
                    <label>Orden</label>
                    <input type="number" min="0" class="form-control" id="orden" name="orden" value="0"/>
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

<style>
/* Estilos para mejorar la visualización responsive */
.table-responsive {
    border-radius: 5px;
    overflow: hidden;
}

#tablaPaginasFunciones {
    font-size: 0.875rem;
}

#tablaPaginasFunciones th {
    white-space: nowrap;
}

#tablaPaginasFunciones td {
    vertical-align: middle;
}

/* Estilos para la vista responsive de DataTables */
.dtr-title {
    font-weight: bold;
    min-width: 120px;
}

.dtr-data {
    word-break: break-word;
}

/* Estilos para el botón de icono */
.btn-icono-preview {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: auto;
    min-width: 80px;
    padding: 6px 12px;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    font-size: 0.8rem;
    transition: all 0.2s;
}

.btn-icono-preview:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.btn-icono-preview i {
    margin-right: 4px;
}

/* Botones responsivos */
@media (max-width: 768px) {
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
    
    .dataTables_wrapper .dataTables_filter input {
        width: 100% !important;
    }
    
    .btn-icono-preview {
        min-width: 60px;
        padding: 4px 8px;
        font-size: 0.7rem;
    }
}

/* Mejoras para dispositivos móviles */
@media (max-width: 576px) {
    .card-body {
        padding: 0.75rem;
    }
    
    .table-responsive {
        font-size: 0.8rem;
    }
    
    #btnNuevo {
        width: 100%;
        margin-bottom: 1rem !important;
    }
    
    .dataTables_wrapper .dataTables_info, 
    .dataTables_wrapper .dataTables_paginate {
        text-align: center;
        float: none !important;
        padding-top: 10px;
    }
}
</style>

<!-- ... resto del archivo HTML igual ... -->

<script>
    
$(document).ready(function(){
    // Variables globales para almacenar opciones
    var paginasOptions = [];
    var tablasOptions = [];
    var iconosOptions = [];
    var coloresOptions = [];
    var estadosOptions = {};

    // Función para mostrar vista previa del icono
    function actualizarVistaPreviaIcono() {
        var iconoId = $('#icono_id').val();
        var selectedOption = $('#icono_id option:selected');
        var iconoClase = selectedOption.data('clase');
        var colorId = $('#color_id').val();
        var colorOption = $('#color_id option:selected');
        var colorClase = colorOption.data('color-clase');
        var bgClase = colorOption.data('bg-clase');
        var textClase = colorOption.data('text-clase');
        
        if (iconoId && iconoClase) {
            var clases = 'btn-icono-preview';
            if (bgClase) {
                clases += ' ' + bgClase;
            }
            if (textClase) {
                clases += ' ' + textClase;
            } else if (colorClase) {
                clases += ' ' + colorClase;
            }
            $('#icono_preview').html(
                '<button type="button" class="' + clases + '">' +
                '<i class="' + iconoClase + '"></i>' +
                '<span>' + selectedOption.text().split(' (')[0] + '</span>' +
                '</button>'
            );
        } else {
            $('#icono_preview').html('<small>Seleccione un icono para ver la vista previa</small>');
        }
    }

    // Función para mostrar vista previa del color
    function actualizarVistaPreviaColor() {
        var colorId = $('#color_id').val();
        var selectedOption = $('#color_id option:selected');
        var colorClase = selectedOption.data('color-clase');
        var bgClase = selectedOption.data('bg-clase');
        var textClase = selectedOption.data('text-clase');
        var iconoId = $('#icono_id').val();
        var iconoOption = $('#icono_id option:selected');
        var iconoClase = iconoOption.data('clase');
        
        if (colorId) {
            var clases = 'btn-icono-preview';
            if (bgClase) {
                clases += ' ' + bgClase;
            }
            if (textClase) {
                clases += ' ' + textClase;
            } else if (colorClase) {
                clases += ' ' + colorClase;
            }
            
            var iconoHtml = '';
            if (iconoId && iconoClase) {
                iconoHtml = '<i class="' + iconoClase + ' me-1"></i>';
            }
            $('#color_preview').html(
                '<button type="button" class="' + clases + '">' +
                iconoHtml +
                '<span>' + selectedOption.text() + '</span>' +
                '</button>'
            );
            
            // Actualizar también la vista previa del icono si hay un icono seleccionado
            if (iconoId && iconoClase) {
                actualizarVistaPreviaIcono();
            }
        } else {
            $('#color_preview').html('<small>Seleccione un color para ver la vista previa</small>');
        }
    }
    
    // Cargar opciones de páginas, tablas, iconos y colores
    function cargarOpciones() {
        // Cargar páginas
        $.get('paginas_funciones_ajax.php', {accion: 'obtener_paginas'}, function(res){
            if(res && res.length > 0) {
                paginasOptions = res;
                $('#pagina_id').empty().append('<option value="">Seleccionar página</option>');
                $.each(res, function(i, pagina) {
                    $('#pagina_id').append($('<option>', {
                        value: pagina.pagina_id,
                        text: pagina.pagina || 'Página ' + pagina.pagina_id,
                        'data-tabla': pagina.tabla_id || ''
                    }));
                });
            }
        }, 'json');
        
        // Cargar tablas
        $.get('paginas_funciones_ajax.php', {accion: 'obtener_tablas'}, function(res){
            if(res && res.length > 0) {
                tablasOptions = res;
                $('#tabla_id').empty().append('<option value="">Seleccionar tabla</option>');
                $.each(res, function(i, tabla) {
                    $('#tabla_id').append($('<option>', {
                        value: tabla.tabla_id,
                        text: tabla.tabla_nombre
                    }));
                });
            }
        }, 'json');
        
        // Cargar iconos
        $.get('paginas_funciones_ajax.php', {accion: 'obtener_iconos'}, function(res){
            if(res && res.length > 0) {
                iconosOptions = res;
                $('#icono_id').empty().append('<option value="">Seleccionar icono</option>');
                $.each(res, function(i, icono) {
                    $('#icono_id').append($('<option>', {
                        value: icono.icono_id,
                        text: icono.icono_nombre + ' (' + icono.icono_clase + ')',
                        'data-clase': icono.icono_clase
                    }));
                });
            }
        }, 'json');

        // Cargar colores
        $.get('paginas_funciones_ajax.php', {accion: 'obtener_colores'}, function(res){
            if(res && res.length > 0) {
                coloresOptions = res;
                $('#color_id').empty().append('<option value="">Seleccionar color</option>');
                $.each(res, function(i, color) {
                    $('#color_id').append($('<option>', {
                        value: color.color_id,
                        text: color.nombre_color,
                        'data-color-clase': color.color_clase,
                        'data-bg-clase': color.bg_clase,
                        'data-text-clase': color.text_clase
                    }));
                });
            }
        }, 'json');
    }

    // Cargar estados por tabla
    function cargarEstadosPorTabla(tabla_id) {
        return new Promise((resolve, reject) => {
            if (!tabla_id) {
                $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">Seleccionar estado</option>');
                resolve([]);
                return;
            }
            
            console.log('Solicitando estados para tabla:', tabla_id);
            $.ajax({
                url: 'paginas_funciones_ajax.php',
                type: 'GET',
                data: {accion: 'obtener_estados_por_tabla', tabla_id: tabla_id},
                dataType: 'json',
                success: function(res) {
                    console.log('Estados recibidos:', res);
                    if(res && res.length > 0) {
                        estadosOptions[tabla_id] = res;
                        $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">Seleccionar estado</option>');
                        $.each(res, function(i, estado) {
                            $('#estado_registro_origen_id, #estado_registro_destino_id').append($('<option>', {
                                value: estado.tabla_estado_registro_id,
                                text: estado.estado_registro || 'Estado ' + estado.tabla_estado_registro_id
                            }));
                        });
                        resolve(res);
                    } else {
                        $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">No hay estados para esta tabla</option>');
                        resolve([]);
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('Error al obtener estados:', textStatus, errorThrown);
                    reject(errorThrown);
                }
            });
        });
    }
    
    // Evento cuando cambia la página
    $('#pagina_id').change(async function(){
        var pagina_id = $(this).val();
        var selectedOption = $(this).find('option:selected');
        var tabla_id = selectedOption.data('tabla');
        
        console.log('Página seleccionada:', pagina_id, 'Tabla asociada:', tabla_id);
        
        if (tabla_id) {
            $('#tabla_id').val(tabla_id);
            await cargarEstadosPorTabla(tabla_id);
        } else {
            try {
                const res = await $.get('paginas_funciones_ajax.php', {accion: 'obtener_tabla_por_pagina', pagina_id: pagina_id});
                if(res.tabla_id) {
                    $('#tabla_id').val(res.tabla_id);
                    await cargarEstadosPorTabla(res.tabla_id);
                } else {
                    $('#tabla_id').val('');
                    $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">Seleccionar estado</option>');
                }
            } catch (error) {
                console.error('Error al obtener tabla:', error);
            }
        }
    });
    
    // Evento cuando cambia la tabla manualmente
    $('#tabla_id').change(async function(){
        var tabla_id = $(this).val();
        await cargarEstadosPorTabla(tabla_id);
    });

    // Evento cuando cambia el icono
    $('#icono_id').change(function(){
        actualizarVistaPreviaIcono();
    });

    // Evento cuando cambia el color
    $('#color_id').change(function(){
        actualizarVistaPreviaColor();
    });

    // Configuración de DataTable
    var tabla = $('#tablaPaginasFunciones').DataTable({
        responsive: true,
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100, 200],        
        dom: '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6 d-flex justify-content-md-end"fB>>rt<"row"<"col-sm-12 col-md-6"i><"col-sm-12 col-md-6"p>>',
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel"></i> Excel',
                titleAttr: 'Exportar a Excel',
                className: 'btn btn-success btn-sm me-2',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
                }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                titleAttr: 'Exportar a PDF',
                className: 'btn btn-danger btn-sm',
                orientation: 'portrait',
                pageSize: 'A4',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6, 7, 8]
                }
            }
        ],
        ajax: {
            url: 'paginas_funciones_ajax.php',
            type: 'GET',
            data: {accion: 'listar'},
            dataSrc: ''
        },
        language: {
            "search": "Buscar:",
            "searchPlaceholder": "Buscar funciones...",
            "lengthMenu": "Mostrar _MENU_ registros por página",
            "zeroRecords": "No se encontraron funciones",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ funciones",
            "infoEmpty": "Mostrando 0 a 0 de 0 funciones",
            "infoFiltered": "(filtrado de _MAX_ funciones totales)",
            "paginate": {
                "first": "Primero",
                "last": "Último",
                "next": "Siguiente",
                "previous": "Anterior"
            }
        },
        columns: [
            { data: 'pagina_funcion_id' },
            { data: 'pagina' },
            { data: 'tabla_nombre' },
            { data: 'nombre_funcion' },
            { 
                data: 'accion_js',
                render: function(data) {
                    return data || '<span class="text-muted">No definida</span>';
                }
            },
            {
            data: null,
            render: function(data, type, row) {
                if (row.icono_clase && row.icono_nombre) {
                    var clases = 'btn-icono-preview';
                    if (row.bg_clase) {
                        clases += ' ' + row.bg_clase;
                    }
                    if (row.text_clase) {
                        clases += ' ' + row.text_clase;
                    } else if (row.color_clase) {
                        clases += ' ' + row.color_clase;
                    }
                    
                    return '<button type="button" class="' + clases + '">' +
                        '<i class="' + row.icono_clase + '"></i>' +
                        '<span>' + row.icono_nombre + '</span>' +
                        '</button>';
                }
                return '<span class="text-muted">Sin icono</span>';
            }
        },
            { data: 'estado_origen' },
            { data: 'estado_destino' },
            { data: 'orden' },
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

    // Cargar opciones al iniciar
    cargarOpciones();

    $('#btnNuevo').click(function(){
        $('#formPaginaFuncion')[0].reset();
        $('#pagina_funcion_id').val('');
        
        $('#orden').val('0');
        $('#tabla_id').val('');
        $('#icono_id').val('');
        $('#color_id').val('');
        $('#accion_js').val('');
        $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">Seleccionar estado</option>');
        $('#icono_preview').html('<small>Seleccione un icono para ver la vista previa</small>');
        $('#color_preview').html('<small>Seleccione un color para ver la vista previa</small>');
        $('#modalLabel').text('Nueva Función de Página');
        var modal = new bootstrap.Modal(document.getElementById('modalPaginaFuncion'));
        modal.show();
    });

    // Eliminar registro
    $('#tablaPaginasFunciones tbody').on('click', '.btnEliminar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        
        Swal.fire({
            title: '¿Eliminar función?',
            text: '¿Estás seguro de querer eliminar esta función?',
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: "Cancelar"
        }).then((result) => {
            if (result.isConfirmed) {
                $.get('paginas_funciones_ajax.php', {
                    accion: 'eliminar', 
                    pagina_funcion_id: data.pagina_funcion_id
                }, function(res){
                    if(res.resultado){
                        tabla.ajax.reload();
                        Swal.fire({
                            icon: "success",
                            title: "¡Éxito!",
                            text: "Función eliminada correctamente",
                            showConfirmButton: false,
                            timer: 1500
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || "Error al eliminar la función"
                        });
                    }
                }, 'json');
            }
        });
    });

   // EDITAR REGISTRO
$('#tablaPaginasFunciones tbody').on('click', '.btnEditar', async function(){
    var data = tabla.row($(this).parents('tr')).data();
    
    try {
        const response = await $.ajax({
            url: 'paginas_funciones_ajax.php',
            type: 'GET',
            data: {accion: 'obtener', pagina_funcion_id: data.pagina_funcion_id},
            dataType: 'json'
        });
        
        console.log('Datos recibidos para editar:', response);
        
        // Verificar si hay error en la respuesta
        if (response.error) {
            throw new Error(response.error);
        }
        
        if(response) {
            // Llenar campos básicos
            $('#pagina_funcion_id').val(response.pagina_funcion_id);
            $('#pagina_id').val(response.pagina_id);
            $('#nombre_funcion').val(response.nombre_funcion);
            $('#accion_js').val(response.accion_js || '');
            $('#descripcion').val(response.descripcion || '');
            $('#icono_id').val(response.icono_id || '');
            $('#color_id').val(response.color_id || '');
            
            $('#orden').val(response.orden || 0);
            
            // Actualizar vistas previas
            actualizarVistaPreviaIcono();
            actualizarVistaPreviaColor();
            
            let tabla_id = response.tabla_id;
            
            // Si no hay tabla_id, obtenerla de la página
            if (!tabla_id) {
                try {
                    const tablaRes = await $.ajax({
                        url: 'paginas_funciones_ajax.php',
                        type: 'GET',
                        data: {accion: 'obtener_tabla_por_pagina', pagina_id: response.pagina_id},
                        dataType: 'json'
                    });
                    tabla_id = tablaRes.tabla_id;
                } catch (error) {
                    console.log('No se pudo obtener tabla:', error);
                }
            }
            
            // Establecer tabla_id y cargar estados
            if (tabla_id) {
                $('#tabla_id').val(tabla_id);
                await cargarEstadosPorTabla(tabla_id);
                
                // Establecer valores de estados después de cargarlos
                setTimeout(() => {
                    console.log('Estableciendo estados:', {
                        origen: response.tabla_estado_registro_origen_id,
                        destino: response.tabla_estado_registro_destino_id
                    });
                    $('#estado_registro_origen_id').val(response.tabla_estado_registro_origen_id);
                    $('#estado_registro_destino_id').val(response.tabla_estado_registro_destino_id);
                }, 300);
            } else {
                $('#tabla_id').val('');
                $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">Seleccionar estado</option>');
            }
            
            $('#modalLabel').text('Editar Función de Página');
            var modal = new bootstrap.Modal(document.getElementById('modalPaginaFuncion'));
            modal.show();
        }
    } catch (error) {
        console.error('Error al cargar datos:', error);
        Swal.fire({
            icon: "error",
            title: "Error",
            text: "Error al cargar los datos para editar: " + error.message
        });
    }
});

    $('#btnGuardar').click(function(){
        var form = document.getElementById('formPaginaFuncion');
    
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }
        
        var id = $('#pagina_funcion_id').val();
        var accion = id ? 'editar' : 'agregar';
        var formData = {
            accion: accion,
            pagina_funcion_id: id,
            pagina_id: $('#pagina_id').val(),
            tabla_id: $('#tabla_id').val() || null,
            icono_id: $('#icono_id').val() || null,
            color_id: $('#color_id').val() || null,
            nombre_funcion: $('#nombre_funcion').val(),
            accion_js: $('#accion_js').val() || null,
            descripcion: $('#descripcion').val(),
            tabla_estado_registro_origen_id: $('#estado_registro_origen_id').val(),
            tabla_estado_registro_destino_id: $('#estado_registro_destino_id').val(),
            orden: $('#orden').val() || 0
        };

        console.log('Enviando datos:', formData); // Para depuración

        $.ajax({
            url: 'paginas_funciones_ajax.php',
            type: 'GET',
            data: formData,
            dataType: 'json',
            success: function(res) {
                console.log('Respuesta recibida:', res); // Para depuración
                if(res.resultado) {
                    tabla.ajax.reload(null, false);
                    
                    var modal = bootstrap.Modal.getInstance(document.getElementById('modalPaginaFuncion'));
                    modal.hide();
                    
                    $('#formPaginaFuncion')[0].reset();
                    form.classList.remove('was-validated');
                    
                    Swal.fire({
                        icon: "success",
                        title: "¡Éxito!",
                        text: id ? "Función actualizada correctamente" : "Función creada correctamente",
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
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Error en la solicitud:', textStatus, errorThrown);
                Swal.fire({
                    icon: "error",
                    title: "Error de conexión",
                    text: "Error de conexión con el servidor: " + errorThrown
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