// Variable global para la tabla (opcional, pero útil si necesitas recargarla desde otras funciones)
var tablaEmpresas;

$(document).ready(function () {
  // Inicializar DataTables
  tablaEmpresas = $('#tablaEmpresas').DataTable({
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
        render: function(data) {
           return data == 1 ? '<span>Activo</span>' : '<span>Inactivo</span>';
        }
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
    // Configuración de idioma y búsqueda en footer
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

// --- FUNCIONES DEL CRUD ---

function abrirEmpresaNueva() {
  $('#formEmpresa')[0].reset();
  $('#empresa_id').val('');
  // Asegúrate de que 'opciones_controlador.php' exista y devuelva lo correcto
  cargarSelect('documento_tipo_id', 'conf__documento_tipos', 'documento_tipo_id', 'documento_tipo');
  cargarSelect('localidad_id', 'conf__localidades', 'localidad_id', 'localidad');
  $('#modalEmpresa').modal('show');
}

function editarEmpresa(id) {
  $.get('empresas_controlador.php', { accion: 'obtener', id }, function (data) {
    // Nota: Data debe venir en formato JSON desde el PHP
    $('#empresa_id').val(data.empresa_id);
    $('#empresa').val(data.empresa);
    // Vuelve a cargar los selects para que se pre-seleccionen correctamente si es necesario
    // O simplemente asigna el valor si las opciones ya están cargadas
    cargarSelect('documento_tipo_id', 'conf__documento_tipos', 'documento_tipo_id', 'documento_tipo', data.documento_tipo_id);
    cargarSelect('localidad_id', 'conf__localidades', 'localidad_id', 'localidad', data.localidad_id);
    
    $('#documento_numero').val(data.documento_numero);
    $('#telefono').val(data.telefono);
    $('#domicilio').val(data.domicilio);
    $('#email').val(data.email);
    $('#base_conf').val(data.base_conf);
    
    $('#modalEmpresa').modal('show');
    cargarModulosEnEdicion(id);
  }, 'json'); // Se fuerza tipo json
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
  }, 'json');
}

function guardarEmpresa() {
  const datos = $('#formEmpresa').serialize();
  // Se usa serialize string en lugar de Object.fromEntries para mayor compatibilidad con $.post simple
  $.post('empresas_controlador.php', datos + '&accion=guardar', function () {
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

// Se modificó ligeramente para aceptar un valor preseleccionado opcional
function cargarSelect(id, tabla, clave, valor, seleccionado = null) {
  $.get('opciones_controlador.php', { tabla, clave, valor }, function (data) {
    const $select = $('#' + id);
    $select.empty();
    $select.append('<option value="">Seleccione</option>');
    data.forEach(op => {
        let selectedAttr = (seleccionado && op.id == seleccionado) ? 'selected' : '';
        $select.append(`<option value="${op.id}" ${selectedAttr}>${op.nombre}</option>`);
    });
  }, 'json');
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
  }, 'json');
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