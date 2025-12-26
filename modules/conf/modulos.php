<?php
// Configuración de la página
$pageTitle = "Gestión de Tablas";
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
                <div class="col-sm-6"><h3 class="mb-0">Tablas</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Tablas</li>
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
                                        <button class="btn btn-primary mb-3" id="btnNuevo">Nueva Módulo</button>
                                        <table id="tablaModulos" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                     <th>ID</th>
                                                    <th>Módulo</th>
                                                    <th>Base Datos</th>
                                                    <th>URL Módulo</th>
                                                    <th>Email Envío</th>
                                                    <th>Layout</th>
                                                    
                                                    <th>Imagen ID</th>
                                                    <th>Depende ID</th>
                                                    <th>Estado Registro</th>
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
<div class="modal fade" id="modalModulo" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabel">Módulo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <form id="formModulo">
            <input type="hidden" id="modulo_id" name="modulo_id" />
            <div class="row g-3">
                <div class="col-md-6">
                    <label>Módulo</label>
                    <input type="text" class="form-control" id="modulo" name="modulo" />
                </div>
                <div class="col-md-6">
                    <label>Base Datos</label>
                    <input type="text" class="form-control" id="base_datos" name="base_datos" />
                </div>
                <div class="col-md-6">
                    <label>URL Módulo</label>
                    <input type="text" class="form-control" id="modulo_url" name="modulo_url" />
                </div>
                <div class="col-md-6">
                    <label>Email Envío Módulo</label>
                    <input type="email" class="form-control" id="email_envio_modulo" name="email_envio_modulo" />
                </div>
                <div class="col-md-6">
                    <label>Layout Nombre</label>
                    <input type="text" class="form-control" id="layout_nombre" name="layout_nombre" />
                </div>
                <div class="col-md-6">
                    <label>Usuario Temp</label>
                    <input type="number" step="any" class="form-control" id="usuario_temp" name="usuario_temp" />
                </div>
                <div class="col-md-6">
                    <label>Session Temp</label>
                    <input type="text" class="form-control" id="session_temp" name="session_temp" />
                </div>
                <div class="col-md-6">
                    <label>Imagen ID</label>
                    <input type="number" step="any" class="form-control" id="imagen_id" name="imagen_id" />
                </div>
                <div class="col-md-6">
                    <label>Depende ID</label>
                    <input type="number" class="form-control" id="depende_id" name="depende_id" />
                </div>
                <div class="col-md-6">
                    <label>Estado Registro ID</label>
                    <input type="number" class="form-control" id="tabla_estado_registro_id" name="tabla_estado_registro_id" />
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
    var tabla = $('#tablaModulos').DataTable({
        ajax: {
            url: 'modulos_ajax.php',
            type: 'GET',
            data: {accion: 'listar'},
            dataSrc: ''
        },
        columns: [
            { data: 'modulo_id' },
            { data: 'modulo' },
            { data: 'base_datos' },
            { data: 'modulo_url' },
            { data: 'email_envio_modulo' },
            { data: 'layout_nombre' },            
            { data: 'imagen_id' },
            { data: 'depende_id' },
            { data: 'tabla_estado_registro_id' },
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
        $('#formModulo')[0].reset();
        $('#modulo_id').val('');
        $('#modalLabel').text('Nuevo Módulo');
        var modal = new bootstrap.Modal(document.getElementById('modalModulo'));
        modal.show();
    });

    $('#tablaModulos tbody').on('click', '.btnEditar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        $.get('modulos_ajax.php', {accion: 'obtener', modulo_id: data.modulo_id}, function(res){
            if(res){
                $('#modulo_id').val(res.modulo_id);
                $('#modulo').val(res.modulo);
                $('#base_datos').val(res.base_datos);
                $('#modulo_url').val(res.modulo_url);
                $('#email_envio_modulo').val(res.email_envio_modulo);
                $('#layout_nombre').val(res.layout_nombre);
                $('#imagen_id').val(res.imagen_id);
                $('#depende_id').val(res.depende_id);
                $('#tabla_estado_registro_id').val(res.tabla_estado_registro_id);
                $('#modalLabel').text('Editar Módulo');
                var modal = new bootstrap.Modal(document.getElementById('modalModulo'));
                modal.show();
                
            } else {
                alert('Error al obtener datos');
            }
        }, 'json');
    });

    $('#tablaModulos tbody').on('click', '.btnEliminar', function(){
        
        Swal.fire({
            title: "Are you sure?",
            text: "You won't be able to revert this!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: "Yes, delete it!"
            }).then((result) => {
            if (result.isConfirmed) {
                var data = tabla.row($(this).parents('tr')).data();
                $.get('modulos_ajax.php', {accion: 'eliminar', modulo_id: data.modulo_id}, function(res){
                    if(res.resultado){
                        tabla.ajax.reload();
                    } else {
                        alert('Error al eliminar');                        
                    }
                }, 'json');
                Swal.fire({                    
                    icon: "success",
                    title: "Datos Eliminados!",
                    showConfirmButton: false,
                    timer: 1000
                });    
            }
            });
        
    });

    $('#btnGuardar').click(function(){
        var id = $('#modulo_id').val();
        var accion = id ? 'editar' : 'agregar';
        // Leer todos los campos del formulario
        var formData = {
            accion: accion,
            modulo_id: id,
            modulo: $('#modulo').val(),
            base_datos: $('#base_datos').val(),
            modulo_url: $('#modulo_url').val(),
            email_envio_modulo: $('#email_envio_modulo').val(),
            layout_nombre: $('#layout_nombre').val(),
            usuario_temp: $('#usuario_temp').val(),
            session_temp: $('#session_temp').val(),
            imagen_id: $('#imagen_id').val(),
            depende_id: $('#depende_id').val(),
            tabla_estado_registro_id: $('#tabla_estado_registro_id').val()
        };

        $.get('modulos_ajax.php', formData, function(res){
            if(res.resultado){
                tabla.ajax.reload();
                var modalEl = document.getElementById('modalModulo');
                var modal = bootstrap.Modal.getInstance(modalEl);
                Swal.fire({
                    
                    icon: "success",
                    title: "Datos Actualizados!",
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
