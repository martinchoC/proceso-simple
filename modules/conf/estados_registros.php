<?php
// Configuración de la página
$pageTitle = "Estado de los Registros";
$currentPage = 'tablas';
$modudo_idx = 1;
// Definir constante para rutas
define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));

// Incluir header
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Estados de Registros</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Estados de Registros</li>
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
                                        <button class="btn btn-primary mb-3" id="btnNuevo">Nuevo Estado</button>
                                        <table id="tablaEstadosRegistros" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Tipo de Tabla</th>
                                                    <th>Estado</th>
                                                    <th>Descripción</th>
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
<div class="modal fade" id="modalEstadoRegistro" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabel">Estado de Registro</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <form id="formEstadoRegistro">
            <input type="hidden" id="estado_registro_id" name="estado_registro_id" />
             
            <div class="row g-3">
                <div class="col-md-12">
                    <label>Tipo de Tabla</label>
                    <select class="form-select" id="tabla_tipo_id" name="tabla_tipo_id">
                        <option value="">Seleccionar tipo de tabla (opcional)</option>
                        <!-- Las opciones se cargarán dinámicamente -->
                    </select>
                </div>    
                <div class="col-md-12">
                    <label>Nombre del Estado *</label>
                    <input type="text" class="form-control" id="estado_registro" name="estado_registro" required/>
                    <div class="invalid-feedback">El nombre es obligatorio</div>
                </div>
                <div class="col-md-12">
                    <label>Descripción</label>
                    <input type="text" class="form-control" id="estado_registro_descripcion" name="estado_registro_descripcion"/>
                </div>
                <div class="col-md-12">
                    <label>Orden</label>
                    <input type="number" class="form-control" id="orden" name="orden" value="0" min="0"/>
                    <div class="form-text">Define el orden de visualización (0 = sin orden específico)</div>
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
    var tablasTiposOptions = [];
    
    // Cargar opciones de tipos de tablas
    function cargarTablasTipos() {
        $.get('estados_registros_ajax.php', {accion: 'obtener_tablas_tipos'}, function(res){
            if(res && res.length > 0) {
                tablasTiposOptions = res;
                $('#tabla_tipo_id').empty().append('<option value="">Seleccionar tipo de tabla (opcional)</option>');
                $.each(res, function(i, tipo) {
                    $('#tabla_tipo_id').append($('<option>', {
                        value: tipo.tabla_tipo_id,
                        text: tipo.tabla_tipo || 'Tipo ' + tipo.tabla_tipo_id
                    }));
                });
            }
        }, 'json');
    }
    
    // Configuración de DataTable
    var tabla = $('#tablaEstadosRegistros').DataTable({
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
                    columns: [0, 1, 2, 3, 4]
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
                    columns: [0, 1, 2, 3, 4]
                }
            }
        ],
        ajax: {
            url: 'estados_registros_ajax.php',
            type: 'GET',
            data: {accion: 'listar'},
            dataSrc: ''
        },
        language: {
            "search": "Buscar:",
            "searchPlaceholder": "Buscar estados...",
            "lengthMenu": "Mostrar _MENU_ registros por página",
            "zeroRecords": "No se encontraron estados",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ estados",
            "infoEmpty": "Mostrando 0 a 0 de 0 estados",
            "infoFiltered": "(filtrado de _MAX_ estados totales)",
            "paginate": {
                "first": "Primero",
                "last": "Último",
                "next": "Siguiente",
                "previous": "Anterior"
            }
        },
        columns: [
            { data: 'estado_registro_id' },
            { 
                data: 'tabla_tipo',
                render: function(data) {
                    return data || '<span class="text-muted">Sin tipo específico</span>';
                }
            },
            { data: 'estado_registro' },
            { 
                data: 'estado_registro_descripcion',
                render: function(data) {
                    return data || '<span class="text-muted">Sin descripción</span>';
                }
            },
            { 
                data: 'orden',
                className: "text-center",
                render: function(data) {
                    return data > 0 ? data : '<span class="text-muted">-</span>';
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: "text-center",
                render: function(data){
                    var botonEditar = 
                        `<button class="btn btn-sm btn-primary btnEditar me-1" title="Editar">
                            <i class="fa fa-pencil-alt"></i>
                         </button>`;
                    
                    var botonEliminar = 
                        `<button class="btn btn-sm btn-danger btnEliminar" title="Eliminar">
                            <i class="fa fa-trash"></i>
                         </button>`;
                    
                    return `<div class="d-flex align-items-center justify-content-center">${botonEditar}${botonEliminar}</div>`;
                }
            }
        ]
    });

    // Cargar opciones al iniciar
    cargarTablasTipos();

    $('#btnNuevo').click(function(){
        $('#formEstadoRegistro')[0].reset();
        $('#estado_registro_id').val('');
        $('#orden').val(0);
        $('#modalLabel').text('Nuevo Estado de Registro');
        var modal = new bootstrap.Modal(document.getElementById('modalEstadoRegistro'));
        modal.show();
    });

    // Eliminar estado
    $('#tablaEstadosRegistros tbody').on('click', '.btnEliminar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        
        Swal.fire({
            title: '¿Eliminar estado?',
            text: `¿Estás seguro de querer eliminar el estado "${data.estado_registro}"?`,
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#d33",
            cancelButtonColor: "#3085d6",
            confirmButtonText: "Sí, eliminar",
            cancelButtonText: "Cancelar"
        }).then((result) => {
            if (result.isConfirmed) {
                $.get('estados_registros_ajax.php', {
                    accion: 'eliminar', 
                    estado_registro_id: data.estado_registro_id
                }, function(res){
                    if(res.resultado){
                        tabla.ajax.reload();
                        Swal.fire({
                            icon: "success",
                            title: "¡Éxito!",
                            text: "Estado eliminado correctamente",
                            showConfirmButton: false,
                            timer: 1500
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || "Error al eliminar el estado"
                        });
                    }
                }, 'json');
            }
        });
    });

    // Editar estado
    $('#tablaEstadosRegistros tbody').on('click', '.btnEditar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        
        $.get('estados_registros_ajax.php', {accion: 'obtener', estado_registro_id: data.estado_registro_id}, function(res){
            if(res){
                $('#estado_registro_id').val(res.estado_registro_id);
                $('#estado_registro').val(res.estado_registro);
                $('#tabla_tipo_id').val(res.tabla_tipo_id || '');
                $('#estado_registro_descripcion').val(res.estado_registro_descripcion || '');
                $('#orden').val(res.orden || 0);
                
                $('#modalLabel').text('Editar Estado de Registro');
                var modal = new bootstrap.Modal(document.getElementById('modalEstadoRegistro'));
                modal.show();
            } else {
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: "Error al obtener datos del estado"
                });
            }
        }, 'json');
    });

    $('#btnGuardar').click(function(){
        var form = document.getElementById('formEstadoRegistro');
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }
        
        var id = $('#estado_registro_id').val();
        var accion = id ? 'editar' : 'agregar';
        var formData = {
            accion: accion,
            estado_registro_id: id,
            tabla_tipo_id: $('#tabla_tipo_id').val() || null,
            estado_registro: $('#estado_registro').val(),
            estado_registro_descripcion: $('#estado_registro_descripcion').val(),
            orden: $('#orden').val() || 0
        };

        $.ajax({
            url: 'estados_registros_ajax.php',
            type: 'GET',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if(res.resultado) {
                    tabla.ajax.reload(null, false);
                    
                    var modal = bootstrap.Modal.getInstance(document.getElementById('modalEstadoRegistro'));
                    modal.hide();
                    
                    $('#formEstadoRegistro')[0].reset();
                    form.classList.remove('was-validated');
                    
                    Swal.fire({
                        icon: "success",
                        title: "¡Éxito!",
                        text: id ? "Estado actualizado correctamente" : "Estado creado correctamente",
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