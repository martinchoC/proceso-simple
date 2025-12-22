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
      <div class="col-sm-6">
        <h3 class="mb-0">Iconos</h3>
      </div>
      <div class="col-sm-6">
        <ol class="breadcrumb float-sm-end">
          <li class="breadcrumb-item"><a href="#">Inicio</a></li>
          <li class="breadcrumb-item active">Iconos</li>
        </ol>
      </div>
    </div>
  </div>
</div>

<div class="app-content">
<div class="container-fluid">

<div class="card">
<div class="card-header d-flex justify-content-between">
  <h3 class="card-title">Diccionario de Iconos</h3>
  <button id="btnNuevo" class="btn btn-success">
    <i class="fa fa-plus"></i> Nuevo
  </button>
</div>

<div class="card-body">
<div class="table-responsive">
<table id="tablaIconos" class="table table-bordered table-striped align-middle">
<thead>
<tr>
  <th>ID</th>
  <th>Nombre</th>
  <th>Clase CSS</th>
  <th>Estado</th>
  <th class="text-center">Acciones</th>
</tr>
</thead>
</table>
</div>
</div>
</div>

</div>
</div>

<!-- MODAL -->
<div class="modal fade" id="modalIcono">
<div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
<div class="modal-content">

<div class="modal-header">
  <h5 class="modal-title" id="modalTitulo">Icono</h5>
  <button class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
<form id="formIcono">
<input type="hidden" id="icono_id" name="icono_id">

<div class="row g-3">
  <div class="col-md-6">
    <label>Nombre *</label>
    <input type="text" name="icono_nombre" id="icono_nombre" class="form-control" required>
  </div>

  <div class="col-md-6">
    <label>Clase CSS *</label>
    <input type="text" name="icono_clase" id="icono_clase" class="form-control" required>
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

</main>

<script>
let tablaIconos;

$(function(){

/* ===== DATATABLE ===== */
tablaIconos = $('#tablaIconos').DataTable({
  ajax: {
        url: 'iconos_ajax.php',
        type: 'GET',
        data: {accion: 'listar'},
        dataSrc: function(response) {
            return response.success ? response.data : [];
        }
    },
  order:[[1,'asc']],
  columns:[
    {data:'icono_id'},
    {data:'icono_nombre'},
    {data:'icono_clase'},
    {data:'estado_nombre'},
    {
      data:null,
      orderable:false,
      className:'text-center',
      render:function(row){
        if(row.valor_estandar === 'ACTIVO'){
          return `
            <button class="btn btn-primary btn-sm btnEditar">✏️</button>
            <button class="btn btn-warning btn-sm btnInactivar">🚫</button>
          `;
        }
        if(row.valor_estandar === 'INACTIVO'){
          return `
            <button class="btn btn-primary btn-sm btnEditar">✏️</button>
            <button class="btn btn-success btn-sm btnActivar">✅</button>
          `;
        }
        return '';
      }
    }
  ]
});

/* ===== NUEVO ===== */
$('#btnNuevo').click(()=>{
  $('#formIcono')[0].reset();
  $('#icono_id').val('');
  $('#modalTitulo').text('Nuevo Icono');
  new bootstrap.Modal('#modalIcono').show();
});

/* ===== EDITAR ===== */
$('#tablaIconos').on('click','.btnEditar',function(){
  let d = tablaIconos.row($(this).parents('tr')).data();
  $('#icono_id').val(d.icono_id);
  $('#icono_nombre').val(d.icono_nombre);
  $('#icono_clase').val(d.icono_clase);
  $('#modalTitulo').text('Editar Icono');
  new bootstrap.Modal('#modalIcono').show();
});

/* ===== GUARDAR ===== */
$('#btnGuardar').click(function(){
  let accion = $('#icono_id').val() ? 'editar' : 'agregar';

  $.ajax({
    url:'iconos_ajax.php',
    type:'POST',
    data: $('#formIcono').serialize()+'&accion='+accion,
    dataType:'json',
    success: function(res) {
        if(res.success) {
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
                text: res.message || (id ? "Icono actualizado correctamente" : "Icono creado correctamente"),
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
    }
  });
});

/* ===== ACTIVAR / INACTIVAR ===== */
$('#tablaIconos').on('click','.btnActivar,.btnInactivar',function(){
  let d = tablaIconos.row($(this).parents('tr')).data();
  let accion = $(this).hasClass('btnActivar') ? 'activar' : 'inactivar';

  Swal.fire({
    title:'Confirmar',
    text:'¿Desea cambiar el estado?',
    icon:'question',
    showCancelButton:true,
    confirmButtonText:'Sí',
    cancelButtonText:'No'
  }).then(res=>{
    if(res.isConfirmed){
      $.get('iconos_ajax.php',{accion:accion,icono_id:d.icono_id},r=>{
        if(r.resultado){
          tablaIconos.ajax.reload(null,false);
          Swal.fire('OK',r.mensaje,'success');
        }else{
          Swal.fire('Error',r.mensaje,'error');
        }
      },'json');
    }
  });
});

});
</script>

<?php require_once ROOT_PATH.'/templates/adminlte/footer1.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
