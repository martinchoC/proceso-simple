<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <title>ABM Módulos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://cdn.jsdelivr.net/npm/@sweetalert2/theme-bootstrap-4/bootstrap-4.min.css" rel="stylesheet" />

</head>
<body class="p-4">
<div class="container">
    <h1>Gestión de Módulos</h1>
    <button class="btn btn-primary mb-3" id="btnNuevo">Nuevo Módulo</button>
    <table id="tablaempresas" class="table table-striped table-bordered">
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


<!-- Modal -->
<div class="modal fade" id="modalempresa" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabel">Módulo</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <form id="formempresa">
            <input type="hidden" id="empresa_id" name="empresa_id" />
            <div class="row g-3">
                <div class="col-md-6">
                    <label>Módulo</label>
                    <input type="text" class="form-control" id="empresa" name="empresa" />
                </div>
                <div class="col-md-6">
                    <label>Base Datos</label>
                    <input type="text" class="form-control" id="documento_tipo_id" name="documento_tipo_id" />
                </div>
                <div class="col-md-6">
                    <label>URL Módulo</label>
                    <input type="text" class="form-control" id="documento_numero" name="documento_numero" />
                </div>
                <div class="col-md-6">
                    <label>Email Envío Módulo</label>
                    <input type="email" class="form-control" id="telefono" name="telefono" />
                </div>
                <div class="col-md-6">
                    <label>Layout Nombre</label>
                    <input type="text" class="form-control" id="domicilio" name="domicilio" />
                </div>
                <div class="col-md-6">
                    <label>Usuario Temp</label>
                    <input type="number" step="any" class="form-control" id="localidad_id" name="localidad_id" />
                </div>
                <div class="col-md-6">
                    <label>Session Temp</label>
                    <input type="text" class="form-control" id="email" name="email" />
                </div>
                <div class="col-md-6">
                    <label>Imagen ID</label>
                    <input type="number" step="any" class="form-control" id="base_conf" name="base_conf" />
                </div>
               
                <div class="col-md-6">
                    <label>Estado Registro ID</label>
                    <input type="number" class="form-control" id="estado_registro_id" name="estado_registro_id" />
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
    var tabla = $('#tablaempresas').DataTable({
        ajax: {
            url: 'empresas_ajax.php',
            type: 'GET',
            data: {accion: 'listar'},
            dataSrc: ''
        },
        columns: [
            { data: 'empresa_id' },
            { data: 'empresa' },
            { data: 'documento_tipo_id' },
            { data: 'documento_numero' },
            { data: 'telefono' },
            { data: 'domicilio' },            
            { data: 'base_conf' },
            
            { data: 'estado_registro_id' },
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
        $('#formempresa')[0].reset();
        $('#empresa_id').val('');
        $('#modalLabel').text('Nuevo Módulo');
        var modal = new bootstrap.Modal(document.getElementById('modalempresa'));
        modal.show();
    });

    $('#tablaempresas tbody').on('click', '.btnEditar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        $.get('empresas_ajax.php', {accion: 'obtener', empresa_id: data.empresa_id}, function(res){
            if(res){
                $('#empresa_id').val(res.empresa_id);
                $('#empresa').val(res.empresa);
                $('#documento_tipo_id').val(res.documento_tipo_id);
                $('#documento_numero').val(res.documento_numero);
                $('#telefono').val(res.telefono);
                $('#domicilio').val(res.domicilio);
                $('#base_conf').val(res.base_conf);
                
                $('#estado_registro_id').val(res.estado_registro_id);
                $('#modalLabel').text('Editar Módulo');
                var modal = new bootstrap.Modal(document.getElementById('modalempresa'));
                modal.show();
                
            } else {
                alert('Error al obtener datos');
            }
        }, 'json');
    });

    $('#tablaempresas tbody').on('click', '.btnEliminar', function(){
        
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
                $.get('empresas_ajax.php', {accion: 'eliminar', empresa_id: data.empresa_id}, function(res){
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
        var id = $('#empresa_id').val();
        var accion = id ? 'editar' : 'agregar';
        // Leer todos los campos del formulario
        var formData = {
            accion: accion,
            empresa_id: id,
            empresa: $('#empresa').val(),
            documento_tipo_id: $('#documento_tipo_id').val(),
            documento_numero: $('#documento_numero').val(),
            telefono: $('#telefono').val(),
            domicilio: $('#domicilio').val(),
            localidad_id: $('#localidad_id').val(),
            email: $('#email').val(),
            base_conf: $('#base_conf').val(),
            
            estado_registro_id: $('#estado_registro_id').val()
        };

        $.get('empresas_ajax.php', formData, function(res){
            if(res.resultado){
                tabla.ajax.reload();
                var modalEl = document.getElementById('modalempresa');
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
</body>
</html>
