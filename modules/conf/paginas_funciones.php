<?php
// Configuración de la página
$pageTitle = "Gestión de Perfiles";
$currentPage = 'perfiles';
$modudo_idx = 1;

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
                                                        <th>Estado Origen</th>
                                                        <th>Estado Destino</th>
                                                        <th>Confirmable</th>
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
                <div class="col-md-12">
                    <label>Nombre de Función *</label>
                    <input type="text" class="form-control" id="nombre_funcion" name="nombre_funcion" required/>
                    <div class="invalid-feedback">El nombre de función es obligatorio</div>
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
                    <select class="form-select" id="estado_registro_destino_id" name="estado_registro_destino_id">
                        <option value="">Seleccionar estado destino</option>
                    </select>
                    
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" id="es_confirmable" name="es_confirmable" value="1" checked>
                        <label class="form-check-label" for="es_confirmable">Es confirmable</label>
                    </div>
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

/* Botones responsivos */
@media (max-width: 768px) {
    .btn-sm {
        padding: 0.25rem 0.5rem;
        font-size: 0.75rem;
    }
    
    .dataTables_wrapper .dataTables_filter input {
        width: 100% !important;
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

<script>
$(document).ready(function(){
    // Variables globales para almacenar opciones
    var paginasOptions = [];
    var tablasOptions = [];
    var estadosOptions = {};
    
    // Cargar opciones de páginas y tablas
    function cargarOpciones() {
       $.get('paginas_funciones_ajax.php', {accion: 'obtener_paginas'}, function(res){
        if(res && res.length > 0) {
            paginasOptions = res;
            $('#pagina_id').empty().append('<option value="">Seleccionar página</option>');
            $.each(res, function(i, pagina) {
                $('#pagina_id').append($('<option>', {
                    value: pagina.pagina_id,
                    text: pagina.pagina || 'Página ' + pagina.pagina_id, // Cambiado de pagina_nombre a pagina
                    'data-tabla': pagina.tabla_id || '' // Almacenar tabla_id en data attribute
                }));
            });
        }
    }, 'json');
        
        $.get('paginas_funciones_ajax.php', {accion: 'obtener_tablas'}, function(res){
            if(res && res.length > 0) {
                tablasOptions = res;
            }
        }, 'json');
    }
    
    // Cargar estados por tabla
    function cargarEstadosPorTabla(tabla_id) {
        if (!tabla_id) {
            $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">Seleccionar estado</option>');
            return;
        }
        
        $.get('paginas_funciones_ajax.php', {accion: 'obtener_estados_por_tabla', tabla_id: tabla_id}, function(res){
            if(res && res.length > 0) {
                estadosOptions[tabla_id] = res;
                $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">Seleccionar estado</option>');
                $.each(res, function(i, estado) {
                    $('#estado_registro_origen_id, #estado_registro_destino_id').append($('<option>', {
                        value: estado.estado_registro_id,
                        text: estado.estado_registro
                    }));
                });
            } else {
                $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">No hay estados para esta tabla</option>');
            }
        }, 'json');
    }
    
    // Evento cuando cambia la página
    $('#pagina_id').change(function(){
        var pagina_id = $(this).val();
        var selectedOption = $(this).find('option:selected');
        var tabla_id = selectedOption.data('tabla');
        
        if (tabla_id) {
            // Si la página tiene una tabla asociada, seleccionarla y cargar estados
            $('#tabla_id').val(tabla_id);
            cargarEstadosPorTabla(tabla_id);
        } else {
            // Si no tiene tabla asociada, obtenerla del servidor
            $.get('paginas_funciones_ajax.php', {accion: 'obtener_tabla_por_pagina', pagina_id: pagina_id}, function(res){
                if(res.tabla_id) {
                    $('#tabla_id').val(res.tabla_id);
                    cargarEstadosPorTabla(res.tabla_id);
                } else {
                    $('#tabla_id').val('');
                    $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">Seleccionar estado</option>');
                }
            }, 'json');
        }
    });
    
    // Evento cuando cambia la tabla manualmente
    $('#tabla_id').change(function(){
        var tabla_id = $(this).val();
        cargarEstadosPorTabla(tabla_id);
    });
    
    // Configuración de DataTable con responsive
    var tabla = $('#tablaPaginasFunciones').DataTable({
        responsive: {
            details: {
                display: $.fn.dataTable.Responsive.display.modal({
                    header: function (row) {
                        var data = row.data();
                        return 'Detalles: ' + data.nombre_funcion;
                    }
                }),
                renderer: $.fn.dataTable.Responsive.renderer.tableAll({
                    tableClass: 'table'
                })
            }
        },
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
                    columns: [0, 1, 2, 3, 4, 5, 6, 7]
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
                    columns: [0, 1, 2, 3, 4, 5, 6, 7]
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
            { data: 'estado_origen' },
            { data: 'estado_destino' },
            {
                data: 'es_confirmable',
                render: function(data) {
                    return data == 1 ? 
                        '<span class="badge bg-success">Sí</span>' : 
                        '<span class="badge bg-secondary">No</span>';
                }
            },
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
        $('#es_confirmable').prop('checked', true);
        $('#orden').val('0');
        $('#tabla_id').val('');
        $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">Seleccionar estado</option>');
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

    $('#tablaPaginasFunciones tbody').on('click', '.btnEditar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        
        $.get('paginas_funciones_ajax.php', {accion: 'obtener', pagina_funcion_id: data.pagina_funcion_id}, function(res){
            if(res){
                $('#pagina_funcion_id').val(res.pagina_funcion_id);
                $('#pagina_id').val(res.pagina_id);
                $('#nombre_funcion').val(res.nombre_funcion);
                $('#descripcion').val(res.descripcion || '');
                $('#es_confirmable').prop('checked', res.es_confirmable == 1);
                $('#orden').val(res.orden || 0);
                
                // Primero cargar la tabla y luego los estados
                if (res.tabla_id) {
                    $('#tabla_id').val(res.tabla_id);
                    cargarEstadosPorTabla(res.tabla_id).then(function() {
                        $('#estado_registro_origen_id').val(res.estado_registro_origen_id);
                        $('#estado_registro_destino_id').val(res.estado_registro_destino_id);
                    });
                } else {
                    // Si no hay tabla_id en el registro, obtenerla de la página
                    $.get('paginas_funciones_ajax.php', {accion: 'obtener_tabla_por_pagina', pagina_id: res.pagina_id}, function(tablaRes){
                        if(tablaRes.tabla_id) {
                            $('#tabla_id').val(tablaRes.tabla_id);
                            cargarEstadosPorTabla(tablaRes.tabla_id).then(function() {
                                $('#estado_registro_origen_id').val(res.estado_registro_origen_id);
                                $('#estado_registro_destino_id').val(res.estado_registro_destino_id);
                            });
                        } else {
                            $('#tabla_id').val('');
                            $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">Seleccionar estado</option>');
                        }
                    }, 'json');
                }
                
                $('#modalLabel').text('Editar Función de Página');
                var modal = new bootstrap.Modal(document.getElementById('modalPaginaFuncion'));
                modal.show();
            } else {
                alert('Error al obtener datos');
            }
        }, 'json');
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
            nombre_funcion: $('#nombre_funcion').val(),
            descripcion: $('#descripcion').val(),
            estado_registro_origen_id: $('#estado_registro_origen_id').val(),
            estado_registro_destino_id: $('#estado_registro_destino_id').val(),
            es_confirmable: $('#es_confirmable').is(':checked') ? 1 : 0,
            orden: $('#orden').val() || 0
        };

        $.ajax({
            url: 'paginas_funciones_ajax.php',
            type: 'GET',
            data: formData,
            dataType: 'json',
            success: function(res) {
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
$('#pagina_id').change(function(){
    var pagina_id = $(this).val();
    var selectedOption = $(this).find('option:selected');
    var tabla_id = selectedOption.data('tabla');
    
    console.log('Página seleccionada:', pagina_id, 'Tabla asociada:', tabla_id);
    
    if (tabla_id) {
        // Si la página tiene una tabla asociada, seleccionarla y cargar estados
        $('#tabla_id').val(tabla_id);
        console.log('Cargando estados para tabla:', tabla_id);
        cargarEstadosPorTabla(tabla_id);
    } else {
        // Si no tiene tabla asociada, obtenerla del servidor
        console.log('Obteniendo tabla del servidor para página:', pagina_id);
        $.get('paginas_funciones_ajax.php', {accion: 'obtener_tabla_por_pagina', pagina_id: pagina_id}, function(res){
            console.log('Respuesta del servidor:', res);
            if(res.tabla_id) {
                $('#tabla_id').val(res.tabla_id);
                cargarEstadosPorTabla(res.tabla_id);
            } else {
                $('#tabla_id').val('');
                $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">Seleccionar estado</option>');
            }
        }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
            console.error('Error al obtener tabla:', textStatus, errorThrown);
        });
    }
});

// Cargar estados por tabla - añadir debug
function cargarEstadosPorTabla(tabla_id) {
    if (!tabla_id) {
        $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">Seleccionar estado</option>');
        return;
    }
    
    console.log('Solicitando estados para tabla:', tabla_id);
    $.get('paginas_funciones_ajax.php', {accion: 'obtener_estados_por_tabla', tabla_id: tabla_id}, function(res){
        console.log('Estados recibidos:', res);
        if(res && res.length > 0) {
            estadosOptions[tabla_id] = res;
            $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">Seleccionar estado</option>');
            $.each(res, function(i, estado) {
                $('#estado_registro_origen_id, #estado_registro_destino_id').append($('<option>', {
                    value: estado.estado_registro_id,
                    text: estado.estado_registro || 'Estado ' + estado.estado_registro_id
                }));
            });
        } else {
            $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">No hay estados para esta tabla</option>');
        }
    }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
        console.error('Error al obtener estados:', textStatus, errorThrown);
    });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
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
                                        <table id="tablaPaginasFunciones" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Página</th>
                                                    <th>Tabla</th>
                                                    <th>Función</th>
                                                    <th>Estado Origen</th>
                                                    <th>Estado Destino</th>
                                                    <th>Confirmable</th>
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
                <div class="col-md-12">
                    <label>Nombre de Función *</label>
                    <input type="text" class="form-control" id="nombre_funcion" name="nombre_funcion" required/>
                    <div class="invalid-feedback">El nombre de función es obligatorio</div>
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
                    <select class="form-select" id="estado_registro_destino_id" name="estado_registro_destino_id">
                        <option value="">Seleccionar estado destino</option>
                    </select>
                    
                </div>
                <div class="col-md-6">
                    <div class="form-check form-switch mt-4">
                        <input class="form-check-input" type="checkbox" id="es_confirmable" name="es_confirmable" value="1" checked>
                        <label class="form-check-label" for="es_confirmable">Es confirmable</label>
                    </div>
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

<script>
$(document).ready(function(){
    // Variables globales para almacenar opciones
    var paginasOptions = [];
    var tablasOptions = [];
    var estadosOptions = {};
    
    // Cargar opciones de páginas y tablas
    function cargarOpciones() {
       $.get('paginas_funciones_ajax.php', {accion: 'obtener_paginas'}, function(res){
        if(res && res.length > 0) {
            paginasOptions = res;
            $('#pagina_id').empty().append('<option value="">Seleccionar página</option>');
            $.each(res, function(i, pagina) {
                $('#pagina_id').append($('<option>', {
                    value: pagina.pagina_id,
                    text: pagina.pagina || 'Página ' + pagina.pagina_id, // Cambiado de pagina_nombre a pagina
                    'data-tabla': pagina.tabla_id || '' // Almacenar tabla_id en data attribute
                }));
            });
        }
    }, 'json');
        
        $.get('paginas_funciones_ajax.php', {accion: 'obtener_tablas'}, function(res){
            if(res && res.length > 0) {
                tablasOptions = res;
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
        $.get('paginas_funciones_ajax.php', {accion: 'obtener_estados_por_tabla', tabla_id: tabla_id}, function(res){
            console.log('Estados recibidos:', res);
            if(res && res.length > 0) {
                estadosOptions[tabla_id] = res;
                $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">Seleccionar estado</option>');
                $.each(res, function(i, estado) {
                    $('#estado_registro_origen_id, #estado_registro_destino_id').append($('<option>', {
                        value: estado.estado_registro_id,
                        text: estado.estado_registro || 'Estado ' + estado.estado_registro_id
                    }));
                });
                resolve(res);
            } else {
                $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">No hay estados para esta tabla</option>');
                resolve([]);
            }
        }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
            console.error('Error al obtener estados:', textStatus, errorThrown);
            reject(errorThrown);
        });
    });
}    
    // Evento cuando cambia la página
    $('#pagina_id').change(function(){
        var pagina_id = $(this).val();
        var selectedOption = $(this).find('option:selected');
        var tabla_id = selectedOption.data('tabla');
        
        if (tabla_id) {
            // Si la página tiene una tabla asociada, seleccionarla y cargar estados
            $('#tabla_id').val(tabla_id);
            cargarEstadosPorTabla(tabla_id);
        } else {
            // Si no tiene tabla asociada, obtenerla del servidor
            $.get('paginas_funciones_ajax.php', {accion: 'obtener_tabla_por_pagina', pagina_id: pagina_id}, function(res){
                if(res.tabla_id) {
                    $('#tabla_id').val(res.tabla_id);
                    cargarEstadosPorTabla(res.tabla_id);
                } else {
                    $('#tabla_id').val('');
                    $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">Seleccionar estado</option>');
                }
            }, 'json');
        }
    });
    
    // Evento cuando cambia la tabla manualmente
    $('#tabla_id').change(function(){
        var tabla_id = $(this).val();
        cargarEstadosPorTabla(tabla_id);
    });
    
    // Configuración de DataTable
    var tabla = $('#tablaPaginasFunciones').DataTable({
        pageLength: 25,
        lengthMenu: [25, 50, 100, 200],        
        dom: '<"row"<"col-md-6"l><"col-md-6"fB>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel"></i> Excel',
                titleAttr: 'Exportar a Excel',
                className: 'btn btn-success btn-sm me-2',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6, 7]
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
                    columns: [0, 1, 2, 3, 4, 5, 6, 7]
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
            { data: 'estado_origen' },
            { data: 'estado_destino' },
            {
                data: 'es_confirmable',
                render: function(data) {
                    return data == 1 ? 
                        '<span class="badge bg-success">Sí</span>' : 
                        '<span class="badge bg-secondary">No</span>';
                }
            },
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
        $('#es_confirmable').prop('checked', true);
        $('#orden').val('0');
        $('#tabla_id').val('');
        $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">Seleccionar estado</option>');
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
    
    $('#tablaPaginasFunciones tbody').on('click', '.btnEditar', async function(){
    var data = tabla.row($(this).parents('tr')).data();
    
    $.get('paginas_funciones_ajax.php', {accion: 'obtener', pagina_funcion_id: data.pagina_funcion_id}, async function(res){
        if(res){
            $('#pagina_funcion_id').val(res.pagina_funcion_id);
            $('#pagina_id').val(res.pagina_id);
            $('#nombre_funcion').val(res.nombre_funcion);
            $('#descripcion').val(res.descripcion || '');
            $('#es_confirmable').prop('checked', res.es_confirmable == 1);
            $('#orden').val(res.orden || 0);
            
            let tabla_id = res.tabla_id;
            
            // Si no hay tabla_id en el registro, obtenerla de la página
            if (!tabla_id) {
                try {
                    const tablaRes = await $.get('paginas_funciones_ajax.php', {accion: 'obtener_tabla_por_pagina', pagina_id: res.pagina_id});
                    tabla_id = tablaRes.tabla_id;
                    $('#tabla_id').val(tabla_id);
                } catch (error) {
                    console.error('Error al obtener tabla:', error);
                }
            } else {
                $('#tabla_id').val(tabla_id);
            }
            
            // Cargar estados y esperar a que termine
            if (tabla_id) {
                try {
                    await cargarEstadosPorTabla(tabla_id);
                    
                    // Ahora establecer los valores después de que los estados se hayan cargado
                    $('#estado_registro_origen_id').val(res.estado_registro_origen_id);
                    $('#estado_registro_destino_id').val(res.estado_registro_destino_id);
                } catch (error) {
                    console.error('Error al cargar estados:', error);
                }
            } else {
                $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">Seleccionar estado</option>');
            }
            
            $('#modalLabel').text('Editar Función de Página');
            var modal = new bootstrap.Modal(document.getElementById('modalPaginaFuncion'));
            modal.show();
        } else {
            alert('Error al obtener datos');
        }
    }, 'json');
});

$('#pagina_id').change(async function(){
    var pagina_id = $(this).val();
    var selectedOption = $(this).find('option:selected');
    var tabla_id = selectedOption.data('tabla');
    
    console.log('Página seleccionada:', pagina_id, 'Tabla asociada:', tabla_id);
    
    if (tabla_id) {
        // Si la página tiene una tabla asociada, seleccionarla y cargar estados
        $('#tabla_id').val(tabla_id);
        console.log('Cargando estados para tabla:', tabla_id);
        await cargarEstadosPorTabla(tabla_id);
    } else {
        // Si no tiene tabla asociada, obtenerla del servidor
        console.log('Obteniendo tabla del servidor para página:', pagina_id);
        try {
            const res = await $.get('paginas_funciones_ajax.php', {accion: 'obtener_tabla_por_pagina', pagina_id: pagina_id});
            console.log('Respuesta del servidor:', res);
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
            nombre_funcion: $('#nombre_funcion').val(),
            descripcion: $('#descripcion').val(),
            estado_registro_origen_id: $('#estado_registro_origen_id').val(),
            estado_registro_destino_id: $('#estado_registro_destino_id').val(),
            es_confirmable: $('#es_confirmable').is(':checked') ? 1 : 0,
            orden: $('#orden').val() || 0
        };

        $.ajax({
            url: 'paginas_funciones_ajax.php',
            type: 'GET',
            data: formData,
            dataType: 'json',
            success: function(res) {
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
$('#pagina_id').change(function(){
    var pagina_id = $(this).val();
    var selectedOption = $(this).find('option:selected');
    var tabla_id = selectedOption.data('tabla');
    
    console.log('Página seleccionada:', pagina_id, 'Tabla asociada:', tabla_id);
    
    if (tabla_id) {
        // Si la página tiene una tabla asociada, seleccionarla y cargar estados
        $('#tabla_id').val(tabla_id);
        console.log('Cargando estados para tabla:', tabla_id);
        cargarEstadosPorTabla(tabla_id);
    } else {
        // Si no tiene tabla asociada, obtenerla del servidor
        console.log('Obteniendo tabla del servidor para página:', pagina_id);
        $.get('paginas_funciones_ajax.php', {accion: 'obtener_tabla_por_pagina', pagina_id: pagina_id}, function(res){
            console.log('Respuesta del servidor:', res);
            if(res.tabla_id) {
                $('#tabla_id').val(res.tabla_id);
                cargarEstadosPorTabla(res.tabla_id);
            } else {
                $('#tabla_id').val('');
                $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">Seleccionar estado</option>');
            }
        }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
            console.error('Error al obtener tabla:', textStatus, errorThrown);
        });
    }
});

// Cargar estados por tabla - añadir debug
function cargarEstadosPorTabla(tabla_id) {
    if (!tabla_id) {
        $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">Seleccionar estado</option>');
        return;
    }
    
    console.log('Solicitando estados para tabla:', tabla_id);
    $.get('paginas_funciones_ajax.php', {accion: 'obtener_estados_por_tabla', tabla_id: tabla_id}, function(res){
        console.log('Estados recibidos:', res);
        if(res && res.length > 0) {
            estadosOptions[tabla_id] = res;
            $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">Seleccionar estado</option>');
            $.each(res, function(i, estado) {
                $('#estado_registro_origen_id, #estado_registro_destino_id').append($('<option>', {
                    value: estado.estado_registro_id,
                    text: estado.estado_registro || 'Estado ' + estado.estado_registro_id
                }));
            });
        } else {
            $('#estado_registro_origen_id, #estado_registro_destino_id').empty().append('<option value="">No hay estados para esta tabla</option>');
        }
    }, 'json').fail(function(jqXHR, textStatus, errorThrown) {
        console.error('Error al obtener estados:', textStatus, errorThrown);
    });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>