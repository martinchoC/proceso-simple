<?php
// Configuración de la página
$pageTitle = "Gestión de Modelos";
$currentPage = 'modelos';
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
                <div class="col-sm-6"><h3 class="mb-0">Modelos</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Modelos</li>
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
                                        <button class="btn btn-primary mb-3" id="btnNuevo">Nuevo Modelo</button>
                                        <table id="tablaModelos" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Marca</th>
                                                    <th>Modelo</th>
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
<div class="modal fade" id="modalModelo" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabel">Modelo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <form id="formModelo">
            <input type="hidden" id="modelo_id" name="modelo_id" />
            <div class="row g-3">
                <div class="col-md-12">
                    <label>Marca</label>
                    <select class="form-control" id="marca_id" name="marca_id" required>
                        <option value="">Seleccionar marca</option>
                        <?php
                        require_once "modelos_model.php";
                        $marcas = obtenerMarcas($conexion);
                        foreach ($marcas as $marca) {
                            echo "<option value='{$marca['marca_id']}'>{$marca['marca_nombre']}</option>";
                        }
                        ?>
                    </select>
                </div>
                <div class="col-md-12">
                    <label>Nombre del Modelo</label>
                    <input type="text" class="form-control" id="modelo_nombre" name="modelo_nombre" required />
                </div>
                <div class="col-md-12">
                    <label>Estado</label>
                    <select class="form-control" id="tabla_estado_registro_id" name="tabla_estado_registro_id">
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
    var tabla = $('#tablaModelos').DataTable({
        ajax: {
            url: 'modelos_ajax.php',
            type: 'GET',
            data: {accion: 'listar'},
            dataSrc: ''
        },
        columns: [
            { data: 'modelo_id' },
            { data: 'marca_nombre' },
            { data: 'modelo_nombre' },
            { 
                data: 'tabla_estado_registro_id',
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
                    <button class="btn btn-sm btn-danger btnEliminar" title="Eeliminar">
                      <i class="fa fa-trash"></i>
                    </button>
                  `;
                }
            }
        ]
    });

    $('#btnNuevo').click(function(){
        $('#formModelo')[0].reset();
        $('#modelo_id').val('');
        $('#modalLabel').text('Nuevo Modelo');
        var modal = new bootstrap.Modal(document.getElementById('modalModelo'));
        modal.show();
    });

    $('#tablaModelos tbody').on('click', '.btnEditar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        $.get('modelos_ajax.php', {accion: 'obtener', modelo_id: data.modelo_id}, function(res){
            if(res){
                $('#modelo_id').val(res.modelo_id);
                $('#marca_id').val(res.marca_id);
                $('#modelo_nombre').val(res.modelo_nombre);
                $('#tabla_estado_registro_id').val(res.tabla_estado_registro_id);
                $('#modalLabel').text('Editar Modelo');
                var modal = new bootstrap.Modal(document.getElementById('modalModelo'));
                modal.show();
                
            } else {
                alert('Error al obtener datos');
            }
        }, 'json');
    });

    $('#tablaModelos tbody').on('click', '.btnEliminar', function(){
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
                $.get('modelos_ajax.php', {accion: 'eliminar', modelo_id: data.modelo_id}, function(res){
                    if(res.resultado){
                        tabla.ajax.reload();
                        Swal.fire({                    
                            icon: "success",
                            title: "Modelo eliminado!",
                            showConfirmButton: false,
                            timer: 1000
                        });    
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "No se pudo eliminar el modelo. Asegúrate de que no tenga submodelos asociados."
                        });
                    }
                }, 'json');
            }
        });
    });

    $('#btnGuardar').click(function(){
        var id = $('#modelo_id').val();
        var accion = id ? 'editar' : 'agregar';
        
        var formData = {
            accion: accion,
            modelo_id: id,
            marca_id: $('#marca_id').val(),
            modelo_nombre: $('#modelo_nombre').val(),
            tabla_estado_registro_id: $('#tabla_estado_registro_id').val()
        };

        $.get('modelos_ajax.php', formData, function(res){
            if(res.resultado){
                tabla.ajax.reload();
                var modalEl = document.getElementById('modalModelo');
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