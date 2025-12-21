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
        data: 'tabla_estado_registro_id',
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

// Función para cargar localidades
function cargarLocalidades(selectedId = null, callback = null) {
    $.ajax({
        url: 'empresas_ajax.php',
        type: 'GET',
        data: {accion: 'obtener_localidades'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Seleccionar localidad</option>';
                $.each(res, function(index, localidad) {
                    var selected = (selectedId == localidad.localidad_id) ? 'selected' : '';
                    options += `<option value="${localidad.localidad_id}" ${selected}>${localidad.localidad}</option>`;
                });
                $('#localidad_id').html(options);
                
                // Ejecutar callback si existe
                if (typeof callback === 'function') {
                    callback();
                }
            }
        },
        error: function() {
            console.error('Error al cargar localidades');
            $('#localidad_id').html('<option value="">Error al cargar localidades</option>');
        }
    });
}

$(document).ready(function(){
    cargarLocalidades();
    
    // Configuración de DataTable
    var tabla = $('#tablaempresas').DataTable({
        dom: '<"row"<"col-md-6"l><"col-md-6"fB>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel"></i> Excel',
                titleAttr: 'Exportar a Excel',
                className: 'btn btn-success btn-sm me-2',
                exportOptions: { columns: ':visible' }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                titleAttr: 'Exportar a PDF',
                className: 'btn btn-danger btn-sm',
                orientation: 'landscape',
                pageSize: 'A4',
                exportOptions: { columns: ':visible' }
            }
        ],
        initComplete: function() {
            // Aplicar los filtros
            this.api().columns().every(function() {
                var column = this;
                var header = $(column.header());
                
                // No aplicar filtro a la columna de acciones (índice 7)
                if (header.index() !== 7) {
                    var input = $('.filters th').eq(header.index()).find('input');
                    
                    input.on('keyup change', function() {
                        if (column.search() !== this.value) {
                            column.search(this.value).draw();
                        }
                    });
                    
                    input.on('click', function(e) {
                        if (e.target.value === '') {
                            column.search('').draw();
                        }
                    });
                }
            });
            
            // Ajustes visuales de botones
            $('.dt-buttons').appendTo($('.dataTables_filter'));
            $('.dataTables_filter').css({
                'display': 'flex',
                'align-items': 'center',
                'gap': '10px'
            });
            $('.dataTables_filter input').addClass('form-control form-control-sm');
        },
        ajax: {
            url: 'empresas_ajax.php',
            type: 'GET',
            data: {accion: 'listar'},
            dataSrc: ''
        },
        language: {
            "search": "Buscar:",
            "lengthMenu": "Mostrar _MENU_ registros",
            "zeroRecords": "No se encontraron resultados",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
            "infoEmpty": "0 registros",
            "infoFiltered": "(filtrado de _MAX_ totales)",
            "paginate": {
                "first": "Primero",
                "last": "Último",
                "next": "Siguiente",
                "previous": "Anterior"
            }
        },
        columns: [
            { data: 'empresa_id' },
            { data: 'empresa' },
            { data: 'documento_tipo_id' },
            { data: 'documento_numero' },
            { data: 'domicilio' },            
            { data: 'localidad' },
            { data: 'tabla_estado_registro_id' },
            {
                data: null,
                orderable: false,
                searchable: false,
                className: "text-center",
                render: function(data){
                  return `
                    <button class="btn btn-sm btn-primary btnEditar me-1" title="Editar">
                      <i class="fas fa-pencil-alt"></i>
                    </button>
                    <button class="btn btn-sm btn-danger btnEliminar" title="Eliminar">
                      <i class="fas fa-trash"></i>
                    </button>
                  `;
                }
            }
        ]
    });

    // Botón Nuevo
    $('#btnNuevo').click(function(){
        $('#formempresa')[0].reset();
        $('#empresa_id').val('');
        $('#modalLabel').text('Nueva Empresa');
        var modal = new bootstrap.Modal(document.getElementById('modalempresa'));
        modal.show();
    });

    // Botón Editar
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
                
                // Cargamos localidades y seleccionamos la correcta
                cargarLocalidades(res.localidad_id);
                
                $('#email').val(res.email); // Corregido ID según tu HTML original
                $('#base_conf').val(res.base_conf);
                $('#estado_registro_id').val(res.estado_registro_id);
                
                $('#modalLabel').text('Editar Empresa');
                var modal = new bootstrap.Modal(document.getElementById('modalempresa'));
                modal.show();
            } else {
                alert('Error al obtener datos');
            }
        }, 'json');
    });

    // Botón Eliminar
    $('#tablaempresas tbody').on('click', '.btnEliminar', function(){
        var tr = $(this).parents('tr');
        var data = tabla.row(tr).data();
        
        Swal.fire({
            title: "¿Estás seguro?",
            text: "No podrás revertir esto",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: "Sí, eliminar",
            cancelButtonText: "Cancelar"
        }).then((result) => {
            if (result.isConfirmed) {
                $.get('empresas_ajax.php', {accion: 'eliminar', empresa_id: data.empresa_id}, function(res){
                    if(res.resultado){
                        tabla.ajax.reload();
                        Swal.fire({                    
                            icon: "success",
                            title: "Eliminado!",
                            showConfirmButton: false,
                            timer: 1000
                        });
                    } else {
                        Swal.fire("Error", "No se pudo eliminar el registro", "error");
                    }
                }, 'json');
            }
        });
    });

    // Botón Guardar
    $('#btnGuardar').click(function(){
        var form = $('#formempresa')[0];
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }
        
        var id = $('#empresa_id').val();
        var accion = id ? 'editar' : 'agregar';
        
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
                modal.hide();
                
                Swal.fire({
                    icon: "success",
                    title: "Datos Guardados!",
                    showConfirmButton: false,
                    timer: 1000
                });                
            } else {
                Swal.fire("Error", "Error al guardar: " + (res.error || "Desconocido"), "error");
            }
        }, 'json');
    });
});