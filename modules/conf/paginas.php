<?php
// Configuración de la página
$pageTitle = "Gestión de Paginas";
$currentPage = 'paginas';
$modudo_idx = 1;

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
            <div class="col-sm-6"><h3 class="mb-0">paginas</h3></div>
            <div class="col-sm-6">
            <ol class="breadcrumb float-sm-end">
                <li class="breadcrumb-item"><a href="#">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">paginas</li>
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
                                    <button class="btn btn-primary mb-3" id="btnNuevo">Nueva pagina</button>
                                    <table id="tablapaginas" class="table table-striped table-bordered">
                                        <thead>
                                            <tr>
                                                <th>Id</th>
                                                <th>Modulo</th>
                                                <th>Pagina</th>
                                                <th>URL</th>
                                                <th>Icono</th>
                                                <th>Descripcion</th>
                                                <th>Padre</th>
                                                <th>Tabla</th>
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
            </section>
        </div>

<!-- Modal -->
<div class="modal fade" id="modalpagina" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabel">pagina</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <form id="formpagina">
            <input type="hidden" id="pagina_id" name="pagina_id" />
             
            <div class="row g-3">
                
                <div class="col-md-6">
                    <label>Nombre</label>
                    <input type="text" class="form-control" id="pagina" name="pagina" required/>                    
                </div>
                <div class="col-md-6">
                    <label>Modulo</label>
                    <select class="form-control" id="modulo_id" name="modulo_id" required>
                        <option value="">Seleccionar Modulo</option>
                        <!-- Options will be populated by JavaScript -->
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Url</label>
                    <input type="text" class="form-control" id="url" name="url" />
                </div>
                <div class="col-md-6">
                    <label>Descripcion</label>
                    <input type="text" class="form-control" id="pagina_descripcion" name="pagina_descripcion"/>
                </div>
                 
                <div class="col-md-6">
                    <label>Icono</label>
                    <select class="form-control" id="icono_id" name="icono_id">
                        <option value="">Seleccionar Icono</option>
                        <!-- Options will be populated by JavaScript -->
                    </select>
                </div> 
                <div class="col-md-6">
                    <label>Orden</label>
                    <input type="text" class="form-control" id="orden" name="orden" />
                </div>
                <div class="col-md-6">
                    <label>Tabla</label>
                    <select class="form-control" id="tabla_id" name="tabla_id">
                        <option value="">Seleccionar Tabla</option>
                        <!-- Options will be populated by JavaScript -->
                    </select>
                </div>   
               <div class="col-md-6">
                <label>Padre</label>
                <select class="form-control" id="padre_id" name="padre_id">
                    <option value="">Ninguno (página principal)</option>
                    <!-- Options will be populated by JavaScript -->
                </select>
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
// Función mejorada para cargar Modulos
function cargarModulos(selectedId = null, callback = null) {
    $.ajax({
        url: 'paginas_ajax.php',
        type: 'GET',
        data: {accion: 'obtener_Modulos'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Seleccionar modulo</option>';
                $.each(res, function(index, modulo) {
                    var selected = (selectedId == modulo.modulo_id) ? 'selected' : '';
                    options += `<option value="${modulo.modulo_id}" ${selected}>${modulo.modulo}</option>`;
                });
                $('#modulo_id').html(options);
                
                // Ejecutar callback si existe
                if (typeof callback === 'function') {
                    callback();
                }
            }
        },
        error: function() {
            console.error('Error al cargar Modulos');
            $('#modulo_id').html('<option value="">Error al cargar Modulos</option>');
        }
    });
}
function cargarPaginasPadre(selectedId = null) {
    $.ajax({
        url: 'paginas_ajax.php',
        type: 'GET',
        data: {accion: 'obtenerPadre'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Ninguno (página principal)</option>';
                $.each(res, function(index, pagina) {
                    // Excluir la página actual si estamos editando
                    var currentPageId = $('#pagina_id').val();
                    if (!currentPageId || pagina.pagina_id != currentPageId) {
                        var selected = (selectedId == pagina.pagina_id) ? 'selected' : '';
                        options += `<option value="${pagina.pagina_id}" ${selected}>${pagina.pagina}</option>`;
                    }
                });
                $('#padre_id').html(options);
            }
        },
        error: function() {
            console.error('Error al cargar páginas padre');
            $('#padre_id').html('<option value="">Error al cargar páginas padre</option>');
        }
    });
}

// Función para cargar tablas
function cargarTablas(selectedId = null) {
    $.ajax({
        url: 'paginas_ajax.php',
        type: 'GET',
        data: {accion: 'obtenerTablas'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Seleccionar tabla</option>';
                $.each(res, function(index, tabla) {
                    var selected = (selectedId == tabla.tabla_id) ? 'selected' : '';
                    options += `<option value="${tabla.tabla_id}" ${selected}>${tabla.tabla_nombre}</option>`;
                });
                $('#tabla_id').html(options);
            }
        },
        error: function() {
            console.error('Error al cargar tablas');
            $('#tabla_id').html('<option value="">Error al cargar tablas</option>');
        }
    });
}
// Función para cargar tablas
function cargarIconos(selectedId = null) {
    $.ajax({
        url: 'paginas_ajax.php',
        type: 'GET',
        data: {accion: 'obtenerIconos'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Seleccionar Icono</option>';
                $.each(res, function(index, tabla) {
                    var selected = (selectedId == tabla.icono_id) ? 'selected' : '';
                    options += `<option value="${tabla.icono_id}" ${selected}>${tabla.icono_nombre}</option>`;
                });
                $('#icono_id').html(options);
            }
        },
        error: function() {
            console.error('Error al cargar iconos');
            $('#icono_id').html('<option value="">Error al cargar iconos</option>');
        }
    });
}

$(document).ready(function(){
    cargarModulos();
    cargarPaginasPadre();
    cargarTablas();
    cargarIconos();
    
    // Configuración de DataTable con filtros por columna
    var tabla = $('#tablapaginas').DataTable({
        pageLength: 25, // Mostrar 25 registros por página como mínimo
        lengthMenu: [25, 50, 100, 200], // Opciones del menú de cantidad de registros
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
            url: 'paginas_ajax.php',
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
            { data: 'pagina_id' },
            { data: 'modulo' },
            { data: 'pagina' },
            { data: 'url' },
            { 
                data: 'icono_clase',
                className: "text-center", // Centrar contenido de la celda
                render: function(data, type, row) {
                    if (data) {
                        // Mostrar el icono centrado
                        return `<div class="text-center"><i class="${data}" title="${data}" style="font-size: 1.2em;"></i></div>`;
                    } else {
                        // Mostrar un guión centrado si no hay icono
                        return '<div class="text-center"><span class="text-muted">-</span></div>';
                    }
                }
            },            
            { data: 'pagina_descripcion' },
            { data: 'padre_nombre' },
            { data: 'tabla_nombre' },            
            { data: 'orden' },            
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
        $('#formpagina')[0].reset();
        $('#pagina_id').val('');
        $('#modalLabel').text('Nueva Pagina');
        var modal = new bootstrap.Modal(document.getElementById('modalpagina'));
        modal.show();
    });

    $('#tablapaginas tbody').on('click', '.btnEditar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        $.get('paginas_ajax.php', {accion: 'obtener', pagina_id: data.pagina_id}, function(res){
            if(res){
                $('#pagina_id').val(res.pagina_id);
                $('#pagina').val(res.pagina);
                $('#url').val(res.url);
                $('#pagina_descripcion').val(res.pagina_descripcion);
                $('#orden').val(res.orden);
                $('#tabla_id').val(res.tabla_id);
                $('#modulo_id').val(res.modulo_id);                
                $('#icono_id').val(res.icono_id);                
                $('#padre_id').val(res.padre_id);                
                $('#estado_registro_id').val(res.estado_registro_id);

                // Cargar selects con valores actuales
                cargarTablas(res.tabla_id);
                cargarIconos(res.icono_id);
                cargarPaginasPadre(res.padre_id);

                $('#modalLabel').text('Editar pagina');
                var modal = new bootstrap.Modal(document.getElementById('modalpagina'));
                modal.show();
                
            } else {
                alert('Error al obtener datos');
            }
        }, 'json');
    });

    $('#tablapaginas tbody').on('click', '.btnEliminar', function(){
        
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
                $.get('paginas_ajax.php', {accion: 'eliminar', pagina_id: data.pagina_id}, function(res){
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
    // Validar solo campos obligatorios
    if ($('#pagina').val().trim() === '' || $('#modulo_id').val() === '') {
        $('#formpagina').addClass('was-validated');
        return false;
    }
    
    var id = $('#pagina_id').val();
    var accion = id ? 'editar' : 'agregar';
    var formData = {
        accion: accion,
        pagina_id: id,
        pagina: $('#pagina').val(),
        url: $('#url').val(),
        pagina_descripcion: $('#pagina_descripcion').val(),
        orden: $('#orden').val(),
        tabla_id: $('#tabla_id').val(),
        icono_id: $('#icono_id').val(),
        padre_id: $('#padre_id').val() || null, // Envía null si está vacío
        modulo_id: $('#modulo_id').val(),
        estado_registro_id: $('#estado_registro_id').val() || 1
    };

    $.ajax({
        url: 'paginas_ajax.php',
        type: 'GET',
        data: formData,
        dataType: 'json',
        success: function(res) {
            if(res.resultado) {
                tabla.ajax.reload(null, false); // Recargar sin resetear paginación
                
                // Cerrar el modal correctamente
                var modal = bootstrap.Modal.getInstance(document.getElementById('modalpagina'));
                modal.hide();
                
                // Resetear el formulario
                $('#formpagina')[0].reset();
                form.classList.remove('was-validated');
                
                Swal.fire({
                    icon: "success",
                    title: "Operación exitosa!",
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
        },
        error: function() {
            Swal.fire({
                icon: "error",
                title: "Error",
                text: "Error de conexión con el servidor"
            });
        }
    });
});
});

</script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script><?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>
