<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>ABM de Módulos</title>
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
          <h3 class="mb-0">Módulos del Sistema</h3>
          <button class="btn btn-primary" onclick="abrirNuevo()">+ Nuevo módulo</button>
        </div>

        <div class="card card-outline card-info">
          <div class="card-body">
            <table id="tablaModulos" class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Nombre</th>
                  <th>Base de datos</th>
                  <th>URL</th>
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

  <!-- MODAL -->
  <div class="modal fade" id="modalModulo" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content">
        <form id="formModulo">
          <div class="modal-header bg-info">
            <h5 class="modal-title text-white" id="modalTitulo">Nuevo Módulo</h5>
            <button type="button" class="close text-white" data-dismiss="modal">
              <span>&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <input type="hidden" id="modulo_id" name="modulo_id">
            <div class="form-group">
              <label>Nombre del módulo</label>
              <input type="text" name="modulo" id="modulo" class="form-control" required>
            </div>
            <div class="form-group">
              <label>Base de datos</label>
              <input type="text" name="base_datos" id="base_datos" class="form-control">
            </div>
            <div class="form-group">
              <label>URL del módulo</label>
              <input type="text" name="modulo_url" id="modulo_url" class="form-control">
            </div>
            <div class="form-group">
              <label>Email de envío</label>
              <input type="email" name="email_envio_modulo" id="email_envio_modulo" class="form-control">
            </div>
            <div class="form-group">
              <label>Layout</label>
              <input type="text" name="layout_nombre" id="layout_nombre" class="form-control" value="default">
            </div>
            <div class="form-group">
              <label>Usuario temp</label>
              <input type="number" name="usuario_temp" id="usuario_temp" class="form-control">
            </div>
            <div class="form-group">
              <label>Session temp</label>
              <input type="text" name="session_temp" id="session_temp" class="form-control">
            </div>
            <div class="form-group">
              <label>Imagen ID</label>
              <input type="number" name="imagen_id" id="imagen_id" class="form-control">
            </div>
            <div class="form-group">
              <label>Depende de (ID)</label>
              <input type="number" name="depende_id" id="depende_id" class="form-control" value="0">
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary">Guardar</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<script>
function abrirNuevo() {
  $('#formModulo')[0].reset();
  $('#modulo_id').val('');
  $('#modalTitulo').text('Nuevo Módulo');
  $('#modalModulo').modal('show');
}

function editar(id) {
  $.get('modulos_controlador.php', { accion: 'obtener', id }, function (m) {
    for (let campo in m) {
      $(`#${campo}`).val(m[campo]);
    }
    $('#modalTitulo').text('Editar Módulo');
    $('#modalModulo').modal('show');
  });
}

function eliminar(id) {
  if (confirm('¿Eliminar módulo?')) {
    $.post('modulos_controlador.php', { accion: 'eliminar', id }, function () {
      $('#tablaModulos').DataTable().ajax.reload();
    });
  }
}

$('#formModulo').submit(function (e) {
  e.preventDefault();
  const datos = $(this).serialize() + '&accion=guardar';
  $.post('modulos_controlador.php', datos, function () {
    $('#modalModulo').modal('hide');
    $('#formModulo')[0].reset();
    $('#tablaModulos').DataTable().ajax.reload();
  });
});

$(document).ready(function () {
  $('#tablaModulos').DataTable({
    ajax: {
      url: 'modulos_controlador.php',
      type: 'GET',
      data: { accion: 'listar' },
      dataSrc: ''
    },
    columns: [
      { data: 'modulo_id' },
      { data: 'modulo' },
      { data: 'base_datos' },
      { data: 'modulo_url' },
      {
        data: null,
        render: function (data, type, row) {
          return `
            <button class="btn btn-sm btn-warning" onclick="editar(${row.modulo_id})"><i class="fas fa-edit"></i></button>
            <button class="btn btn-sm btn-danger" onclick="eliminar(${row.modulo_id})"><i class="fas fa-trash"></i></button>
          `;
        }
      }
    ],
    responsive: true,
    language: {
      url: '//cdn.datatables.net/plug-ins/1.13.5/i18n/es-ES.json'
    }
  });
});
function editarEmpresa(id) {
  // Por ahora solo mostramos el modal vacío
  alert('Editar empresa ID: ' + id);
  // Aquí deberías cargar los datos de la empresa con AJAX y mostrarlos en el modal
  // $('#modalEmpresa').modal('show');
}
</script>
</body>
</html>
