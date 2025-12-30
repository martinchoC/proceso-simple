<?php
// Configuración de la página
$pageTitle = "Gestión de Submodelos";
$currentPage = 'submodelos';
$modudo_idx = 2;
// Definir constante para rutas
define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));

// Incluir header
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Submodelos</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Submodelos</li>
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
                                        <button class="btn btn-primary mb-3" id="btnNuevo">Nuevo Submodelo</button>
                                        <table id="tablaSubmodelos" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Marca</th>
                                                    <th>Modelo</th>
                                                    <th>Submodelo</th>
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
<div class="modal fade" id="modalSubmodelo" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabel">Submodelo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <form id="formSubmodelo">
            <input type="hidden" id="submodelo_id" name="submodelo_id" />
            <div class="row g-3">
                <div class="col-md-12">
                    <label>Marca</label>
                    <select class="form-control" id="marca_id" name="marca_id" required>
                        <option value="">Seleccionar marca</option>
                        <?php
                        require_once "submodelos_model.php";
                        $marcas = obtenerMarcas($conexion);
                        foreach ($marcas as $marca) {
                            echo "<option value='{$marca['marca_id']}'>{$marca['marca_nombre']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-12">
                    <label>Modelo</label>
                    <select class="form-control" id="modelo_id" name="modelo_id" required>
                        <option value="">Seleccionar modelo</option>
                    </select>
                </div>
                <div class="col-md-12">
                    <label>Nombre del Submodelo</label>
                    <input type="text" class="form-control" id="submodelo_nombre" name="submodelo_nombre" required />
                </div>
                <div class="col-md-12">
                    <label>Estado</label>
                    <select class="form-control" id="estado_registro_id" name="estado_registro_id">
                        <option value="1">Activo</option>
                        <option value="0">Inactivo</option>
                    </select>
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
    // Cargar modelos cuando se selecciona una marca
    $('#marca_id').change(function(){
        var marca_id = $(this).val();
        if(marca_id) {
            $.get('submodelos_ajax.php', {accion: 'obtener_modelos', marca_id: marca_id}, function(data){
                $('#modelo_id').html('<option value="">Seleccionar modelo</option>');
                $.each(data, function(key, value){
                    $('#modelo_id').append('<option value="'+value.modelo_id+'">'+value.modelo_nombre+'</option>');
                });
            }, 'json');
        } else {
            $('#modelo_id').html('<option value="">Seleccionar modelo</option>');
        }
    });

    var tabla = $('#tablaSubmodelos').DataTable({
        ajax: {
            url: 'submodelos_ajax.php',
            type: 'GET',
            data: {accion: 'listar'},
            dataSrc: ''
        },
        columns: [
            { data: 'submodelo_id' },
            { data: 'marca_nombre' },
            { data: 'modelo_nombre' },
            { data: 'submodelo_nombre' },
            { 
                data: 'estado_registro_id',
                render: function(data) {
                    return data == 1 ? 'Activo' : 'Inactivo';
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
        $('#formSubmodelo')[0].reset();
        $('#submodelo_id').val('');
        $('#modelo_id').html('<option value="">Seleccionar modelo</option>');
        $('#modalLabel').text('Nuevo Submodelo');
        var modal = new bootstrap.Modal(document.getElementById('modalSubmodelo'));
        modal.show();
    });

    $('#tablaSubmodelos tbody').on('click', '.btnEditar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        $.get('submodelos_ajax.php', {accion: 'obtener', submodelo_id: data.submodelo_id}, function(res){
            if(res){
                $('#submodelo_id').val(res.submodelo_id);
                $('#marca_id').val(res.marca_id);
                
                // Cargar modelos de la marca seleccionada
                $.get('submodelos_ajax.php', {accion: 'obtener_modelos', marca_id: res.marca_id}, function(modelos){
                    $('#modelo_id').html('<option value="">Seleccionar modelo</option>');
                    $.each(modelos, function(key, value){
                        $('#modelo_id').append('<option value="'+value.modelo_id+'" '+(value.modelo_id == res.modelo_id ? 'selected' : '')+'>'+value.modelo_nombre+'</option>');
                    });
                }, 'json');
                
                $('#submodelo_nombre').val(res.submodelo_nombre);
                $('#estado_registro_id').val(res.estado_registro_id);
                $('#modalLabel').text('Editar Submodelo');
                var modal = new bootstrap.Modal(document.getElementById('modalSubmodelo'));
                modal.show();
                
            } else {
                alert('Error al obtener datos');
            }
        }, 'json');
    });

    $('#tablaSubmodelos tbody').on('click', '.btnEliminar', function(){
        Swal.fire({
            title: "¿Estás seguro?",
            text: "¡No podrás revertir esta acción!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: "Sí, eliminar",
            cancelButtonText: "Cancelar"
        }).then((result) => {
            if (result.isConfirmed) {
                var data = tabla.row($(this).parents('tr')).data();
                $.get('submodelos_ajax.php', {accion: 'eliminar', submodelo_id: data.submodelo_id}, function(res){
                    if(res.resultado){
                        tabla.ajax.reload();
                        Swal.fire({                    
                            icon: "success",
                            title: "Submodelo eliminado!",
                            showConfirmButton: false,
                            timer: 1000
                        });    
                    } else {
                        alert('Error al eliminar');
                    }
                }, 'json');
            }
        });
    });

    $('#btnGuardar').click(function(){
        var id = $('#submodelo_id').val();
        var accion = id ? 'editar' : 'agregar';
        
        var formData = {
            accion: accion,
            submodelo_id: id,
            modelo_id: $('#modelo_id').val(),
            submodelo_nombre: $('#submodelo_nombre').val(),
            estado_registro_id: $('#estado_registro_id').val()
        };

        $.get('submodelos_ajax.php', formData, function(res){
            if(res.resultado){
                tabla.ajax.reload();
                var modalEl = document.getElementById('modalSubmodelo');
                var modal = bootstrap.Modal.getInstance(modalEl);
                Swal.fire({                    
                    icon: "success",
                    title: "Datos guardados!",
                    showConfirmButton: false,
                    timer: 1000
                });                
                modal.hide();
            } else {
                alert('Error al guardar');
            }
        }, 'json');
    });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>