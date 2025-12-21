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
          <h3 class="mb-0">Empresas</h3>
          <button class="btn btn-primary" onclick="abrirEmpresaNueva()">+ Nueva empresa</button>
        </div>

        <div class="card card-outline card-info">
          <div class="card-body">
            <table id="tablaEmpresas" class="table table-bordered table-striped">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Empresa</th>
                  <th>CUIT/DNI</th>
                  <th>Email</th>
                  <th>Estado</th>
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

<!-- MODAL: Alta / Edición de Empresa -->
<div class="modal fade" id="modalEmpresa" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary">
        <h5 class="modal-title text-white">Empresa</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <form id="formEmpresa">
          <input type="hidden" name="empresa_id" id="empresa_id">
          <div class="form-row">
            <div class="form-group col-md-6">
              <label>Empresa</label>
              <input type="text" name="empresa" id="empresa" class="form-control" required>
            </div>
            <div class="form-group col-md-3">
              <label>Tipo Doc.</label>
              <select name="documento_tipo_id" id="documento_tipo_id" class="form-control">
              </select>
            </div>
            <div class="form-group col-md-3">
              <label>CUIT/DNI</label>
              <input type="text" name="documento_numero" id="documento_numero" class="form-control">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group col-md-4">
              <label>Teléfono</label>
              <input type="text" name="telefono" id="telefono" class="form-control">
            </div>
            <div class="form-group col-md-4">
              <label>Email</label>
              <input type="email" name="email" id="email" class="form-control">
            </div>
            <div class="form-group col-md-4">
              <label>Localidad</label>
              <select name="localidad_id" id="localidad_id" class="form-control"></select>                          
          </div>
         
          <div class="form-group">
            <label>Domicilio</label>
            <input type="text" name="domicilio" id="domicilio" class="form-control">
          </div>
          <div class="form-group">
            <label>Base de datos de configuración</label>
            <input type="text" name="base_conf" id="base_conf" class="form-control">
          </div>
        </form>
        <hr>
        <h5>Módulos asignados</h5>
        <table class="table table-bordered" id="tablaModulosEnEdicion">
          <thead>
            <tr>
              <th>Módulo</th>
              <th>Estado</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" onclick="$('#modalEmpresa').modal('hide')">Cerrar</button>
        <button type="button" class="btn btn-primary" onclick="guardarEmpresa()">Guardar</button>
      </div>
    </div>
  </div>
</div>

<!-- MODAL: Asignar Módulos a Empresa -->
<div class="modal fade" id="modalAsignarModulos" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header bg-primary">
        <h5 class="modal-title text-white">Asignar módulos a empresa</h5>
        <button type="button" class="close text-white" data-dismiss="modal">
          <span>&times;</span>
        </button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="empresa_id_modulo" value="">
        <div class="form-group">
          <label>Módulo</label>
          <select id="modulo_id_seleccionado" class="form-control"></select>
        </div>
        <button class="btn btn-success mb-3" onclick="asignarModuloEmpresa()">Asignar módulo</button>
        <hr>
        <table class="table table-bordered" id="tablaModulosAsignados">
          <thead>
            <tr>
              <th>Módulo</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
function abrirEmpresaNueva() {
  $('#formEmpresa')[0].reset();
  $('#empresa_id').val('');
  cargarSelect('documento_tipo_id', 'conf__documento_tipos', 'documento_tipo_id', 'documento_tipo');
  cargarSelect('localidad_id', 'conf__localidades', 'localidad_id', 'localidad');
  
  
  $('#modalEmpresa').modal('show');
}

function editarEmpresa(id) {
  $.get('empresas_controlador.php', { accion: 'obtener', id }, function (data) {
    $('#empresa_id').val(data.empresa_id);
    $('#empresa').val(data.empresa);
    $('#documento_tipo_id').val(data.documento_tipo_id);
    $('#documento_numero').val(data.documento_numero);
    $('#telefono').val(data.telefono);
    $('#domicilio').val(data.domicilio);
    $('#localidad_id').val(data.localidad_id);
    $('#email').val(data.email);
    $('#base_conf').val(data.base_conf);
    $('#modalEmpresa').modal('show');
    cargarModulosEnEdicion(id);
  });
}

function cargarModulosEnEdicion(empresa_id) {
  $.get('empresas_controlador.php', { accion: 'listar_modulos', empresa_id }, function (data) {
    let html = '';
    data.forEach(mod => {
      html += `<tr>
        <td>${mod.modulo}</td>
        <td>${mod.estado_registro_id == 1 ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-secondary">Inactivo</span>'}</td>
      </tr>`;
    });
    $('#tablaModulosEnEdicion tbody').html(html);
  });
}

function guardarEmpresa() {
  const datos = $('#formEmpresa').serialize();
  $.post('empresas_controlador.php', { accion: 'guardar', ...Object.fromEntries(new URLSearchParams(datos)) }, function () {
    $('#modalEmpresa').modal('hide');
    $('#tablaEmpresas').DataTable().ajax.reload(null, false);
  });
}

function cambiarEstado(id, estadoActual) {
  const nuevoEstado = estadoActual == 1 ? 2 : 1;
  $.post('empresas_controlador.php', { accion: 'estado', id, estado: nuevoEstado }, function () {
    $('#tablaEmpresas').DataTable().ajax.reload(null, false);
  });
}

function cargarSelect(id, tabla, clave, valor) {
  $.get('opciones_controlador.php', { tabla, clave, valor }, function (data) {
    const $select = $('#' + id);
    $select.empty();
    $select.append('<option value="">Seleccione</option>');
    data.forEach(op => $select.append(`<option value="${op.id}">${op.nombre}</option>`));
  });
}

function abrirAsignarModulos(empresa_id) {
  $('#empresa_id_modulo').val(empresa_id);
  cargarSelect('modulo_id_seleccionado', 'conf__modulos', 'modulo_id', 'modulo');
  cargarModulosAsignados(empresa_id);
  $('#modalAsignarModulos').modal('show');
}

function cargarModulosAsignados(empresa_id) {
  $.get('empresas_controlador.php', { accion: 'listar_modulos', empresa_id }, function (data) {
    let html = '';
    data.forEach(mod => {
      html += `<tr>
        <td>${mod.modulo}</td>
        <td>${mod.estado_registro_id == 1 ? '<span class="badge badge-success">Activo</span>' : '<span class="badge badge-secondary">Inactivo</span>'}</td>
        <td>
          <button class="btn btn-sm btn-${mod.estado_registro_id == 1 ? 'secondary' : 'success'}" onclick="cambiarEstadoModulo(${mod.empresa_modulo_id}, ${mod.estado_registro_id})">
            <i class="fas fa-power-off"></i>
          </button>
        </td>
      </tr>`;
    });
    $('#tablaModulosAsignados tbody').html(html);
  });
}

function asignarModuloEmpresa() {
  const empresa_id = $('#empresa_id_modulo').val();
  const modulo_id = $('#modulo_id_seleccionado').val();
  if (!modulo_id) return alert('Seleccioná un módulo');
  $.post('empresas_controlador.php', { accion: 'asignar_modulo', empresa_id, modulo_id }, function () {
    cargarModulosAsignados(empresa_id);
  });
}

function cambiarEstadoModulo(empresa_modulo_id, estado_actual) {
  const nuevo_estado = estado_actual == 1 ? 2 : 1;
  $.post('empresas_controlador.php', { accion: 'cambiar_estado_modulo', empresa_modulo_id, estado: nuevo_estado }, function () {
    const empresa_id = $('#empresa_id_modulo').val();
    cargarModulosAsignados(empresa_id);
  });
}

$(document).ready(function () {
  const tabla = $('#tablaEmpresas').DataTable({
    ajax: {
      url: 'empresas_controlador.php',
      type: 'GET',
      data: { accion: 'listar' },
      dataSrc: ''
    },
    columns: [
      { data: 'empresa_id' },
      { data: 'empresa' },
      { data: 'documento_numero' },
      { data: 'email' },
      
      {
        data: 'estado_registro_id',
        render: estado => estado == 1 ? '<span>Activo</span>' : '<span>Inactivo</span>'
      },
      {
        data: null,
        render: function (data, type, row) {
          return `
            <button class="btn btn-sm btn-warning" onclick="editarEmpresa(${row.empresa_id})"><i class="fas fa-edit"></i></button>
            <button class="btn btn-sm btn-${row.estado_registro_id == 2 ? 'secondary' : 'success'}" onclick="cambiarEstado(${row.empresa_id}, ${row.estado_registro_id})">
              <i class="fas fa-power-off"></i>
            </button>
            <button class="btn btn-sm btn-info" onclick="abrirAsignarModulos(${row.empresa_id})">
              <i class="fas fa-link"></i>
            </button>
          `;
        }
      }
    ],
    initComplete: function () {
      this.api().columns().every(function () {
        var that = this;
        $('input, select', this.footer()).on('keyup change clear', function () {
          if (that.search() !== this.value) {
            that.search(this.value).draw();
          }
        });
      });
    },
    responsive: true,
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
    }
  });
});
</script>

</body>
</html>
