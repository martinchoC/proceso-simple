<?php
// Configuración de la página
$pageTitle = "Gestión de Módulos";
$currentPage = 'modulos';
$modudo_idx = 2;
// Definir constante para rutas
define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));

// Incluir header
require_once ROOT_PATH . '/templates/adminlte/header1.php';
// Incluir header
//require_once '../../templates/adminlte/header.php';
?>
<main class="app-main">
    <!--begin::App Content Header-->
    <div class="app-content-header">
        <!--begin::Container-->
        <div class="container-fluid">
        <!--begin::Row-->
        <div class="row">
            <div class="col-sm-6"><h3 class="mb-0">Empresas</h3></div>
            <div class="col-sm-6">
            <ol class="breadcrumb float-sm-end">
                <li class="breadcrumb-item"><a href="#">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Empresas</li>
            </ol>
            </div>
        </div>
        <!--end::Row-->
        </div>
        <!--end::Container-->
    </div>
    <!--end::App Content Header-->
    <!--begin::App Content-->
    <div class="app-content">
        <!--begin::Container-->
        <div class="container-fluid">
<!-- Content Wrapper -->
        <div class="content-wrapper">
        
            <!-- Main content -->
            <section class="content">
                <div class="container-fluid">                      
                    <div class="row">
                        <div class="col-12">
                            <div class="card">                                
                                <div class="card-body">
                                    <button class="btn btn-primary mb-3" id="btnNuevo">Nueva Empresa</button>
                                    <table id="tablaempresas" class="table table-striped table-bordered">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Razon Social</th>
                                                <th>Tipo Doc.</th>
                                                <th>Nro.Doc</th>
                                                <th>Domicilio</th>
                                                <th>Localidad</th>
                                                <th>Estado</th>
                                                <th>Acciones</th>
                                            </tr>
                                            <tr class="filters">
                                                <th></th>
                                                <th><input type="text" class="form-control form-control-sm" placeholder="Filtrar Razón Social" /></th>
                                                <th><input type="text" class="form-control form-control-sm" placeholder="Filtrar Tipo Doc" /></th>
                                                <th><input type="text" class="form-control form-control-sm" placeholder="Filtrar Nro.Doc" /></th>
                                                <th><input type="text" class="form-control form-control-sm" placeholder="Filtrar Domicilio" /></th>
                                                <th><input type="text" class="form-control form-control-sm" placeholder="Filtrar Localidad" /></th>
                                                <th><input type="text" class="form-control form-control-sm" placeholder="Filtrar Estado" /></th>
                                                <th></th> <!-- Columna de acciones sin filtro -->
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
<div class="modal fade" id="modalempresa" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabel">Empresa</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <form id="formempresa">
            <input type="hidden" id="empresa_id" name="empresa_id" />
            <div class="row g-3">
                <div class="col-md-6">
                    <label>Razon social</label>
                    <input type="text" class="form-control" id="empresa" name="empresa" required/>
                    <div class="invalid-feedback">Por favor ingrese el Razon Social</div>
                </div>
                <div class="col-md-6">
                    <label>Documento Tipo</label>
                    <input type="text" class="form-control" id="documento_tipo_id" name="documento_tipo_id" />
                </div>
                <div class="col-md-6">
                    <label>Documento</label>
                    <input type="text" class="form-control" id="documento_numero" name="documento_numero" required/>
                </div>
                <div class="col-md-6">
                    <label>Telefono</label>
                    <input type="email" class="form-control" id="telefono" name="telefono" />
                </div>
                <div class="col-md-6">
                    <label>Domicilio</label>
                    <input type="text" class="form-control" id="domicilio" name="domicilio" />
                </div>
                <div class="col-md-6">
                    <label>Localidad</label>
                    <select class="form-control" id="localidad_id" name="localidad_id" required>
                        <option value="">Seleccionar Localidad</option>
                        <!-- Options will be populated by JavaScript -->
                    </select>
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
// Función mejorada para cargar localidades
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
    
    // Configuración de DataTable con filtros por columna
    var tabla = $('#tablaempresas').DataTable({
        dom: '<"row"<"col-md-6"l><"col-md-6"fB>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
        buttons: [
            {
                extend: 'excelHtml5',
                text: '<i class="fas fa-file-excel"></i> Excel',
                titleAttr: 'Exportar a Excel',
                className: 'btn btn-success btn-sm me-2',
                exportOptions: {
                    columns: ':visible'
                }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                titleAttr: 'Exportar a PDF',
                className: 'btn btn-danger btn-sm',
                orientation: 'landscape',
                pageSize: 'A4',
                exportOptions: {
                    columns: ':visible'
                }
            }
        ],
        initComplete: function() {
            // Aplicar los filtros
            this.api().columns().every(function() {
                var column = this;
                var header = $(column.header());
                
                // No aplicar filtro a la columna de acciones
                if (header.index() !== 7) {
                    var input = $('.filters th').eq(header.index()).find('input');
                    
                    input.on('keyup change', function() {
                        if (column.search() !== this.value) {
                            column.search(this.value).draw();
                        }
                    });
                    
                    // Manejar el evento de limpieza
                    input.on('click', function(e) {
                        if (e.target.value === '') {
                            column.search('').draw();
                        }
                    });
                }
            });
            
            // Mover los botones al contenedor del buscador
            $('.dt-buttons').appendTo($('.dataTables_filter'));
            
            // Aplicar estilos al contenedor
            $('.dataTables_filter').css({
                'display': 'flex',
                'align-items': 'center',
                'gap': '10px'
            });
            
            // Estilo para el input de búsqueda
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
            "searchPlaceholder": "Busqueda general...",
            "lengthMenu": "Mostrar _MENU_ registros por página",
            "zeroRecords": "No se encontraron resultados",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
            "infoEmpty": "Mostrando 0 a 0 de 0 registros",
            "infoFiltered": "(filtrado de _MAX_ registros totales)",
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
        $('#modalLabel').text('Nuevo Empresa');
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
                $('#localidad_id').val(res.localidad_id);
                $('#base_conf').val(res.base_conf);
                $('#tabla_estado_registro_id').val(res.tabla_estado_registro_id);
                $('#modalLabel').text('Editar Empresa');
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
        
        // Validar el formulario
        var form = $('#formempresa')[0];
        if (!form.checkValidity()) {
            // Si la validación falla, mostrar mensajes de error
            form.classList.add('was-validated');
            return false;
        }
        
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
            
            tabla_estado_registro_id: $('#tabla_estado_registro_id').val()
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script><?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
