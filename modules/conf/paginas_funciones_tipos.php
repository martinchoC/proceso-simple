<?php
// Configuración de la página
$pageTitle   = "Funciones por Página";
$currentPage = 'paginas_funciones_tipos';
$modudo_idx  = 1;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">

<div class="app-content-header">
  <div class="container-fluid">
    <h3>Funciones por Página</h3>
  </div>
</div>

<div class="app-content">
<div class="container-fluid">

<div class="card">
<div class="card-header d-flex justify-content-between">
  <h3 class="card-title">Acciones configuradas</h3>
  <button id="btnNuevo" class="btn btn-success">+ Nueva función</button>
</div>

<div class="card-body">
<div class="table-responsive">
<table id="tablaFunciones" class="table table-bordered table-striped">
<thead>
<tr>
  <th>ID</th>
  <th>Página</th>
  <th>Función</th>
  <th>Estado origen</th>
  <th>Estado destino</th>
  <th>Orden</th>
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

<!-- MODAL -->
<div class="modal fade" id="modalFuncion">
<div class="modal-dialog modal-xl modal-dialog-scrollable">
<div class="modal-content">

<div class="modal-header">
  <h5 class="modal-title" id="modalTitulo">Función</h5>
  <button class="btn-close" data-bs-dismiss="modal"></button>
</div>

<div class="modal-body">
<form id="formFuncion">
<input type="hidden" name="pagina_funcion_id" id="pagina_funcion_id">

<div class="row g-3">
  <div class="col-md-4">
    <label>Página *</label>
    <select name="pagina_id" id="pagina_id" class="form-select" required></select>
  </div>

  <div class="col-md-4">
    <label>Nombre función *</label>
    <input type="text" name="nombre_funcion" id="nombre_funcion" class="form-control" required>
  </div>

  <div class="col-md-4">
    <label>Acción JS</label>
    <input type="text" name="accion_js" id="accion_js" class="form-control">
  </div>

  <div class="col-md-4">
    <label>Ícono</label>
    <select name="icono_id" id="icono_id" class="form-select"></select>
  </div>

  <div class="col-md-4">
    <label>Color</label>
    <select name="color_id" id="color_id" class="form-select"></select>
  </div>

  <div class="col-md-4">
    <label>Orden</label>
    <input type="number" name="orden" id="orden" class="form-control" value="0">
  </div>

  <div class="col-md-6">
    <label>Estado origen *</label>
    <select name="tabla_estado_registro_origen_id" id="estado_origen" class="form-select" required></select>
  </div>

  <div class="col-md-6">
    <label>Estado destino *</label>
    <select name="tabla_estado_registro_destino_id" id="estado_destino" class="form-select" required></select>
  </div>

  <div class="col-12">
    <label>Descripción</label>
    <textarea name="descripcion" id="descripcion" class="form-control"></textarea>
  </div>
</div>

</form>
</div>

<div class="modal-footer">
  <button id="btnGuardar" class="btn btn-success">Guardar</button>
</div>

</div>
</div>
</div>

</main>

<script>
let tabla;

$(function(){

/* ===== COMBOS ===== */
function cargarCombos(){
  $.get('paginas_funciones_tipos_ajax.php',{accion:'combos'},function(r){
    $('#pagina_id').html(r.paginas);
    $('#icono_id').html(r.iconos);
    $('#color_id').html(r.colores);
    $('#estado_origen').html(r.estados);
    $('#estado_destino').html(r.estados);
  },'json');
}
cargarCombos();

/* ===== DATATABLE ===== */
tabla = $('#tablaFunciones').DataTable({
   ajax:{
    url:'paginas_funciones_tipos_ajax.php',
    data:{accion:'listar'},
    dataSrc:'',
    error:function(xhr){
      Swal.fire(
        'Error Ajax',
        xhr.responseText || 'Error desconocido',
        'error'
      );
    }
  },
  order:[[5,'asc']],
  columns:[
    {data:'pagina_funcion_id'},
    {data:'pagina'},
    {data:'nombre_funcion'},
    {data:'estado_origen'},
    {data:'estado_destino'},
    {data:'orden'},
    {data:'estado_nombre'},
    {
      data:null,
      render:()=>`
        <button class="btn btn-primary btn-sm btnEditar">✏️</button>
        <button class="btn btn-danger btn-sm btnEliminar">🗑️</button>
      `
    }
  ]
});

/* ===== NUEVO ===== */
$('#btnNuevo').click(()=>{
  $('#formFuncion')[0].reset();
  $('#pagina_funcion_id').val('');
  $('#modalTitulo').text('Nueva función');
  new bootstrap.Modal('#modalFuncion').show();
});

/* ===== EDITAR ===== */
$('#tablaFunciones').on('click','.btnEditar',function(){
  let d = tabla.row($(this).parents('tr')).data();
  $.get('paginas_funciones_tipos_ajax.php',{accion:'obtener',id:d.pagina_funcion_id},function(r){
    Object.keys(r).forEach(k=>$('#'+k).val(r[k]));
    $('#modalTitulo').text('Editar función');
    new bootstrap.Modal('#modalFuncion').show();
  },'json');
});

/* ===== GUARDAR ===== */
$('#btnGuardar').click(function(){
  let accion = $('#pagina_funcion_id').val() ? 'editar':'agregar';

  $.post('paginas_funciones_tipos_ajax.php',
    $('#formFuncion').serialize()+'&accion='+accion,
    function(r){
      if(r.resultado){
        bootstrap.Modal.getInstance('#modalFuncion').hide();
        tabla.ajax.reload(null,false);
        Swal.fire('OK',r.mensaje,'success');
      }else{
        Swal.fire('Error',r.mensaje,'error');
      }
    },'json'
  );
});

});
</script>

<?php require_once ROOT_PATH.'/templates/adminlte/footer1.php'; ?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>
