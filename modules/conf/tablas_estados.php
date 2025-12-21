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
                <div class="col-sm-6"><h3 class="mb-0">Estados por Tabla</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Tablas - Estados</li>
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
                                        <button class="btn btn-primary mb-3" id="btnNuevo">Nueva Relación</button>
                                        <table id="tablaTablasEstados" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Tabla</th>
                                                    <th>Estado</th>
                                                    <th>Estado ID</th>
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
<div class="modal fade" id="modalTablaEstado" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabel">Relación Tabla - Estado</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <form id="formTablaEstado">
            <input type="hidden" id="tabla_estado_id" name="tabla_estado_id" />
             
            <div class="row g-3">
                <div class="col-md-12">
                    <label>Tabla *</label>
                    <select class="form-select" id="tabla_id" name="tabla_id" required>
                        <option value="">Seleccionar tabla</option>
                        <!-- Las opciones se cargarán dinámicamente -->
                    </select>
                    <div class="invalid-feedback">Debe seleccionar una tabla</div>
                </div>    
                <div class="col-md-12">
                    <label>Estado *</label>
                    <select class="form-select" id="estado_id" name="estado_registro_id" required>
                        <option value="">Seleccionar estado</option>
                        <!-- Las opciones se cargarán dinámicamente -->
                    </select>
                    <div class="invalid-feedback">Debe seleccionar un estado</div>
                </div>                
                <div class="col-md-12">
                    <label>Orden</label>
                    <input type="number" min="1" class="form-control" id="orden" name="orden" value="1"/>
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
    var tablasOptions = [];
    var estadosOptions = [];
    
    // Cargar opciones de tablas y estados
    function cargarOpciones() {
        $.get('tablas_estados_ajax.php', {accion: 'obtener_tablas'}, function(res){
            if(res && res.length > 0) {
                tablasOptions = res;
                $('#tabla_id').empty().append('<option value="">Seleccionar tabla</option>');
                $.each(res, function(i, tabla) {
                    $('#tabla_id').append($('<option>', {
                        value: tabla.tabla_id,
                        text: tabla.tabla_nombre || 'Tabla ' + tabla.tabla_id
                    }));
                });
            }
        }, 'json');
        
        $.get('tablas_estados_ajax.php', {accion: 'obtener_estados'}, function(res){
            if(res && res.length > 0) {
                estadosOptions = res;
                $('#estado_id').empty().append('<option value="">Seleccionar estado</option>'); // Cambiado a estado_id
                $.each(res, function(i, estado) {
                    $('#estado_id').append($('<option>', { // Cambiado a estado_id
                        value: estado.estado_registro_id,
                        text: estado.estado_nombre || 'Estado ' + estado.tabla_tipo +'-'+ estado.estado_registro
                    }));
                });
            }
        }, 'json');
    }
    
    // Configuración de DataTable
    var tabla = $('#tablaTablasEstados').DataTable({
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
                    columns: [0, 1, 2, 3]
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
                    columns: [0, 1, 2, 3]
                }
            }
        ],
        ajax: {
            url: 'tablas_estados_ajax.php',
            type: 'GET',
            data: {accion: 'listar'},
            dataSrc: ''
        },
        order: [[1, 'asc'], [3, 'asc']], // Ordenar por columna 1 (tabla) ascendente y luego por col        
        language: {
            "search": "Buscar:",
            "searchPlaceholder": "Buscar relaciones...",
            "lengthMenu": "Mostrar _MENU_ registros por página",
            "zeroRecords": "No se encontraron relaciones",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ relaciones",
            "infoEmpty": "Mostrando 0 a 0 de 0 relaciones",
            "infoFiltered": "(filtrado de _MAX_ relaciones totales)",
            "paginate": {
                "first": "Primero",
                "last": "Último",
                "next": "Siguiente",
                "previous": "Anterior"
            }
        },
        columns: [
            { data: 'tabla_estado_id' },
            { 
                data: 'tabla_nombre',
                render: function(data, type, row) {
                    if (data) {
                        var tabla = tablasOptions.find(t => t.tabla_id == data);
                        return tabla ? (tabla.tabla_nombre || 'Tabla ' + data) : data;
                    }
                    return 'N/A';
                }
            },
            { 
                data: 'estado_registro',
                render: function(data, type, row) {
                    if (data) {
                        var estado = estadosOptions.find(e => e.estado_registro_id == data);
                        return estado ? (estado.estado_nombre || 'Estado ' + data) : data;
                    }
                    return 'N/A';
                }
            },
            { 
                data: 'estado_registro_id',
                render: function(data, type, row) {
                    if (data) {
                        var estado = estadosOptions.find(e => e.estado_registro_id == data);
                        return estado ? (estado.estado_nombre || data) : data;
                    }
                    return 'N/A';
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
        $('#formTablaEstado')[0].reset();
        $('#tabla_estado_id').val('');
        $('#orden').val('1');
        $('#modalLabel').text('Nueva Relación Tabla - Estado');
        var modal = new bootstrap.Modal(document.getElementById('modalTablaEstado'));
        modal.show();
    });

    // Eliminar registro
    $('#tablaTablasEstados tbody').on('click', '.btnEliminar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        
        Swal.fire({
            title: '¿Eliminar relación?',
            text: '¿Estás seguro de querer eliminar esta relación?',
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: "Cancelar"
        }).then((result) => {
            if (result.isConfirmed) {
                $.get('tablas_estados_ajax.php', {
                    accion: 'eliminar', 
                    tabla_estado_id: data.tabla_estado_id
                }, function(res){
                    if(res.resultado){
                        tabla.ajax.reload();
                        Swal.fire({
                            icon: "success",
                            title: "¡Éxito!",
                            text: "Relación eliminada correctamente",
                            showConfirmButton: false,
                            timer: 1500
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || "Error al eliminar la relación"
                        });
                    }
                }, 'json');
            }
        });
    });

   $('#tablaTablasEstados tbody').on('click', '.btnEditar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        
        $.get('tablas_estados_ajax.php', {accion: 'obtener', tabla_estado_id: data.tabla_estado_id}, function(res){
            if(res){
                $('#tabla_estado_id').val(res.tabla_estado_id);
                $('#tabla_id').val(res.tabla_id);
                $('#estado_id').val(res.estado_registro_id); // Cambiado a estado_id
                $('#orden').val(res.orden);
                
                $('#modalLabel').text('Editar Relación Tabla - Estado');
                var modal = new bootstrap.Modal(document.getElementById('modalTablaEstado'));
                modal.show();
            } else {
                alert('Error al obtener datos');
            }
        }, 'json');
    });

    $('#btnGuardar').click(function(){
        var form = document.getElementById('formTablaEstado');
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }
        
        var id = $('#tabla_estado_id').val();
        var accion = id ? 'editar' : 'agregar';
        var formData = {
            accion: accion,
            tabla_estado_id: id,
            tabla_id: $('#tabla_id').val(),
            estado_registro_id: $('#estado_id').val(), // Cambiado a estado_id
            orden: $('#orden').val() || 1
        };

        $.ajax({
            url: 'tablas_estados_ajax.php',
            type: 'GET',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if(res.resultado) {
                    tabla.ajax.reload(null, false);
                    
                    var modal = bootstrap.Modal.getInstance(document.getElementById('modalTablaEstado'));
                    modal.hide();
                    
                    $('#formTablaEstado')[0].reset();
                    form.classList.remove('was-validated');
                    
                    Swal.fire({
                        icon: "success",
                        title: "¡Éxito!",
                        text: id ? "Relación actualizada correctamente" : "Relación creada correctamente",
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