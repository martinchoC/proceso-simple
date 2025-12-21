<?php
// Configuración de la página
$pageTitle = "Gestión de Iconos";
$currentPage = 'iconos';
$modudo_idx = 2;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Iconos</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Iconos</li>
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
                                        <button class="btn btn-primary mb-3" id="btnNuevo">Nuevo Icono</button>
                                        <table id="tablaIconos" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Nombre</th>
                                                    <th>Clase</th>
                                                    <th>Estado</th>
                                                    <th>Vista Previa</th>
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
<div class="modal fade" id="modalIcono" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabel">Icono</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <form id="formIcono">
            <input type="hidden" id="icono_id" name="icono_id" />
             
            <div class="row g-3">
                <div class="col-md-12">
                    <label>Nombre del Icono *</label>
                    <input type="text" class="form-control" id="icono_nombre" name="icono_nombre" required/>
                    <div class="invalid-feedback">El nombre es obligatorio</div>
                </div>
                <div class="col-md-12">
                    <label>Clase CSS *</label>
                    <input type="text" class="form-control" id="icono_clase" name="icono_clase" required/>
                    <small class="form-text text-muted">Ej: bi bi-house, fas fa-user, etc.</small>
                    <div class="invalid-feedback">La clase es obligatoria</div>
                </div>
                <div class="col-md-12">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" id="estado_registro_id" name="estado_registro_id" value="1" checked>
                        <label class="form-check-label" for="estado_registro_id">Icono activo</label>
                    </div>
                </div>
                <div class="col-md-12">
                    <label>Vista Previa:</label>
                    <div id="vistaPrevia" class="text-center p-3 border rounded mt-2">
                        <i id="iconoPreview" class="fs-1"></i>
                        <div id="nombrePreview" class="mt-2 small"></div>
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
$(document).ready(function(){
    
    // Vista previa del icono
    $('#icono_clase').on('input', function() {
        var clase = $(this).val();
        $('#iconoPreview').removeClass().addClass(clase);
        $('#nombrePreview').text(clase);
    });

    // Configuración de DataTable
    var tabla = $('#tablaIconos').DataTable({
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
            url: 'iconos_ajax.php',
            type: 'GET',
            data: {accion: 'listar'},
            dataSrc: ''
        },
        language: {
            "search": "Buscar:",
            "searchPlaceholder": "Buscar iconos...",
            "lengthMenu": "Mostrar _MENU_ registros por página",
            "zeroRecords": "No se encontraron iconos",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ iconos",
            "infoEmpty": "Mostrando 0 a 0 de 0 iconos",
            "infoFiltered": "(filtrado de _MAX_ iconos totales)",
            "paginate": {
                "first": "Primero",
                "last": "Último",
                "next": "Siguiente",
                "previous": "Anterior"
            }
        },
        columns: [
            { data: 'icono_id' },
            { data: 'icono_nombre' },
            { data: 'icono_clase' },
            {
                data: 'estado_registro_id',
                render: function(data) {
                    return data == 1 ? 
                        '<span class="badge bg-success">Activo</span>' : 
                        '<span class="badge bg-danger">Inactivo</span>';
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                render: function(data) {
                    return `<i class="${data.icono_clase} fs-4"></i>`;
                }
            },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: "text-center",
                render: function(data){
                    var botonEditar = data.estado_registro_id == 1 ? 
                        `<button class="btn btn-sm btn-primary btnEditar me-1" title="Editar">
                            <i class="fa fa-pencil-alt"></i>
                         </button>` : 
                        `<button class="btn btn-sm btn-primary btnEditar me-1" title="Editar" disabled>
                            <i class="fa fa-pencil-alt"></i>
                         </button>`;
                    
                    var botonEstado = data.estado_registro_id == 1 ? 
                        `<button class="btn btn-sm btn-warning btnToggleEstado" title="Desactivar">
                            <i class="fa fa-times"></i>
                         </button>` : 
                        `<button class="btn btn-sm btn-success btnToggleEstado" title="Activar">
                            <i class="fa fa-check"></i>
                         </button>`;
                    
                    return botonEditar + botonEstado;
                }
            }
        ]
    });

    $('#btnNuevo').click(function(){
        $('#formIcono')[0].reset();
        $('#icono_id').val('');
        $('#estado_registro_id').prop('checked', true);
        $('#iconoPreview').removeClass();
        $('#nombrePreview').text('');
        $('#modalLabel').text('Nuevo Icono');
        var modal = new bootstrap.Modal(document.getElementById('modalIcono'));
        modal.show();
    });

    // Toggle estado
    $('#tablaIconos tbody').on('click', '.btnToggleEstado', function(){
        var data = tabla.row($(this).parents('tr')).data();
        var nuevoEstado = data.estado_registro_id == 1 ? 0 : 1;
        var accionTexto = nuevoEstado == 1 ? 'activar' : 'desactivar';
        
        Swal.fire({
            title: `¿${nuevoEstado == 1 ? 'Activar' : 'Desactivar'} icono?`,
            text: `¿Estás seguro de querer ${accionTexto} este icono?`,
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: `Sí, ${accionTexto}`,
            cancelButtonText: "Cancelar"
        }).then((result) => {
            if (result.isConfirmed) {
                $.get('iconos_ajax.php', {
                    accion: 'cambiar_estado', 
                    icono_id: data.icono_id,
                    nuevo_estado: nuevoEstado
                }, function(res){
                    if(res.resultado){
                        tabla.ajax.reload();
                        Swal.fire({
                            icon: "success",
                            title: "¡Éxito!",
                            text: `Icono ${accionTexto}do correctamente`,
                            showConfirmButton: false,
                            timer: 1500
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || `Error al ${accionTexto} el icono`
                        });
                    }
                }, 'json');
            }
        });
    });

    $('#tablaIconos tbody').on('click', '.btnEditar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        // Solo permitir editar si está activo
        if (data.estado_registro_id != 1) {
            Swal.fire({
                icon: "warning",
                title: "Icono inactivo",
                text: "No se puede editar un icono inactivo. Active el icono primero.",
                showConfirmButton: false,
                timer: 2000
            });
            return false;
        }
        
        $.get('iconos_ajax.php', {accion: 'obtener', icono_id: data.icono_id}, function(res){
            if(res){
                $('#icono_id').val(res.icono_id);
                $('#icono_nombre').val(res.icono_nombre);
                $('#icono_clase').val(res.icono_clase);
                $('#estado_registro_id').prop('checked', res.estado_registro_id == 1);
                $('#iconoPreview').removeClass().addClass(res.icono_clase);
                $('#nombrePreview').text(res.icono_clase);
                
                $('#modalLabel').text('Editar Icono');
                var modal = new bootstrap.Modal(document.getElementById('modalIcono'));
                modal.show();
            } else {
                alert('Error al obtener datos');
            }
        }, 'json');
    });

    $('#btnGuardar').click(function(){
        var form = document.getElementById('formIcono');
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }
        
        var id = $('#icono_id').val();
        var accion = id ? 'editar' : 'agregar';
        var formData = {
            accion: accion,
            icono_id: id,
            icono_nombre: $('#icono_nombre').val(),
            icono_clase: $('#icono_clase').val(),
            estado_registro_id: $('#estado_registro_id').is(':checked') ? 1 : 0
        };

        $.ajax({
            url: 'iconos_ajax.php',
            type: 'GET',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if(res.resultado) {
                    tabla.ajax.reload(null, false);
                    
                    var modal = bootstrap.Modal.getInstance(document.getElementById('modalIcono'));
                    modal.hide();
                    
                    $('#formIcono')[0].reset();
                    form.classList.remove('was-validated');
                    $('#iconoPreview').removeClass();
                    $('#nombrePreview').text('');
                    
                    Swal.fire({
                        icon: "success",
                        title: "¡Éxito!",
                        text: id ? "Icono actualizado correctamente" : "Icono creado correctamente",
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