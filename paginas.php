<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>ABM de Empresas</title>
   <link rel="stylesheet" href="templates/adminlte4/css/adminlte.min.css" />
    <link rel="stylesheet" href="templates/adminlte4/plugins/fontawesome-free/css/all.min.css">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="templates/adminlte4/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
     
    <link rel="stylesheet" href="templates/adminlte4/plugins/datatables/datatables.min.css">  
    <script src="templates/adminlte4/plugins/datatables/datatables.js"></script>  
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
  <div class="content-wrapper p-4">
    <section class="content">
      <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h3 class="mb-0">Páginas</h3>
          <button class="btn btn-primary" onclick="abrirPaginaNueva()">+ Nueva página</button>
        </div>
        <div class="card card-outline card-info">
          <div class="card-body">
            <table id="tablaPaginas" class="table table-bordered table-striped" style="width:100%">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nombre</th>
                  <th>Ruta</th>
                  <th>Descripción</th>
                  <th>Módulo</th>
                  <th>Tabla</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </section>
  </div>
</div>

<!-- Modal Alta/Edición -->
<div class="modal fade" id="modalPagina" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary">
        <h5 class="modal-title text-white">Página</h5>
        <button type="button" class="close text-white" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <form id="formPagina">
          <input type="hidden" name="pagina_id" id="pagina_id" />
          <div class="form-group">
            <label>Nombre</label>
            <input type="text" name="pagina_nombre" id="pagina_nombre" class="form-control" required />
          </div>
          <div class="form-group">
            <label>Ruta</label>
            <input type="text" name="pagina_ruta" id="pagina_ruta" class="form-control" required />
          </div>
          <div class="form-group">
            <label>Descripción</label>
            <textarea name="pagina_descripcion" id="pagina_descripcion" class="form-control"></textarea>
          </div>
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Módulo</label>
              <select name="modulo_id" id="modulo_id" class="form-control"></select>
            </div>
            <div class="form-group col-md-6">
              <label>Tabla</label>
              <select name="tabla_id" id="tabla_id" class="form-control"></select>
            </div>
          </div>
        </form>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cerrar</button>
        <button type="button" class="btn btn-primary" onclick="guardarPagina()">Guardar</button>
      </div>
    </div>
  </div>
</div>

<script>
function cargarSelect(id, tabla, clave, valor) {
  $.get('opciones_controlador.php', { tabla, clave, valor }, function (data) {
    const $select = $('#' + id);
    $select.empty();
    $select.append('<option value="">Seleccione</option>');
    data.forEach(op => $select.append(`<option value="${op.id}">${op.nombre}</option>`));
  });
}

function abrirPaginaNueva() {
  $('#formPagina')[0].reset();
  $('#pagina_id').val('');
  cargarSelect('modulo_id', 'conf__modulos', 'modulo_id', 'modulo');
  cargarSelect('tabla_id', 'conf__tablas', 'tabla_id', 'tabla_nombre');
  $('#modalPagina').modal('show');
}

function editarPagina(id) {
  $.get('paginas_controlador.php', { accion: 'obtener', id }, function (data) {
    $('#pagina_id').val(data.pagina_id);
    $('#pagina_nombre').val(data.pagina_nombre);
    $('#pagina_ruta').val(data.pagina_ruta);
    $('#pagina_descripcion').val(data.pagina_descripcion);
    $('#modulo_id').val(data.modulo_id);
    $('#tabla_id').val(data.tabla_id);
    $('#modalPagina').modal('show');
  });
}

function guardarPagina() {
  const datos = $('#formPagina').serialize();
  $.post('paginas_controlador.php', { accion: 'guardar', ...Object.fromEntries(new URLSearchParams(datos)) }, function () {
    $('#modalPagina').modal('hide');
    $('#tablaPaginas').DataTable().ajax.reload(null, false);
  });
}

function cambiarEstado(id, estadoActual) {
  // Si querés un estado para páginas, lo implementás aquí
}

$(document).ready(function () {
  $('#tablaPaginas').DataTable({
    ajax: {
      url: 'paginas_controlador.php',
      type: 'GET',
      data: { accion: 'listar' },
      dataSrc: ''
    },
    columns: [
      { data: 'pagina_id' },
      { data: 'pagina_nombre' },
      { data: 'pagina_ruta' },
      { data: 'pagina_descripcion' },
      { data: 'modulo' },
      { data: 'tabla' },
      {
        data: null,
        render: function (data, type, row) {
          return `
            <button class="btn btn-sm btn-warning" onclick="editarPagina(${row.pagina_id})"><i class="fas fa-edit"></i></button>
          `;
        }
      }
    ],
    language: {
      decimal: ",",
      thousands: ".",
      lengthMenu: "Mostrar _MENU_ registros",
      zeroRecords: "No se encontraron resultados",
      info: "Mostrando _START_ a _END_ de _TOTAL_ registros",
      infoEmpty: "Mostrando 0 a 0 de 0 registros",
      infoFiltered: "(filtrado de _MAX_ registros totales)",
      search: "Buscar:",
      paginate: {
        first: "Primero",
        last: "Último",
        next: "Siguiente",
        previous: "Anterior"
      }
    },
    responsive: true
  });
});
</script>

</body>
</html>
