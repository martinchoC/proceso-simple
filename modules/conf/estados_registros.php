<?php
// Configuración de la página
$pageTitle = "Gestión de Estados de Registros";
$currentPage = 'estados_registros';
$modudo_idx = 1;

// Definir constante para rutas
define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));

// Incluir header
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>
<style>
/* Estilos para mejorar visibilidad */
.table th {
    background-color: #f8f9fa;
    color: #495057;
    font-weight: 600;
}

.badge {
    font-size: 0.85em;
    padding: 0.35em 0.65em;
}

.color-preview {
    display: inline-block;
    width: 20px;
    height: 20px;
    border-radius: 3px;
    border: 1px solid #dee2e6;
    vertical-align: middle;
    margin-right: 5px;
}

/* Estilos específicos para colores de Bootstrap */
.bg-primary { background-color: #007bff !important; color: white !important; }
.bg-secondary { background-color: #6c757d !important; color: white !important; }
.bg-success { background-color: #28a745 !important; color: white !important; }
.bg-danger { background-color: #dc3545 !important; color: white !important; }
.bg-warning { background-color: #ffc107 !important; color: #212529 !important; }
.bg-info { background-color: #17a2b8 !important; color: white !important; }
.bg-light { background-color: #f8f9fa !important; color: #212529 !important; border: 1px solid #dee2e6 !important; }
.bg-dark { background-color: #343a40 !important; color: white !important; }
</style>

<main class="app-main">
    <!--begin::App Content Header-->
    <div class="app-content-header">
        <!--begin::Container-->
        <div class="container-fluid">
        <!--begin::Row-->
        <div class="row">
            <div class="col-sm-6"><h3 class="mb-0">Estados de Registros</h3></div>
            <div class="col-sm-6">
            <ol class="breadcrumb float-sm-end">
                <li class="breadcrumb-item"><a href="#">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Estados de Registros</li>
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
                                    <button class="btn btn-primary mb-3" id="btnNuevo">Nuevo Estado</button>
                                    <table id="tablaEstadosRegistros" class="table table-striped table-bordered">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Estado</th>
                                                <th>Código Estándar</th>
                                                <th>Valor Estándar</th>
                                                <th>Color</th>
                                                <th>Orden Estándar</th>
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
<div class="modal fade" id="modalEstadoRegistro" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabel">Estado de Registro</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <form id="formEstadoRegistro">
            <input type="hidden" id="estado_registro_id" name="estado_registro_id" />
             
            <div class="row g-3">
                <div class="col-md-12">
                    <label>Estado *</label>
                    <input type="text" class="form-control" id="estado_registro" name="estado_registro" required/>
                </div>
                <div class="col-md-6">
                    <label>Código Estándar</label>
                    <input type="text" class="form-control" id="codigo_estandar" name="codigo_estandar" placeholder="Ej: ACTIVO"/>
                </div>
                <div class="col-md-6">
                    <label>Valor Estándar</label>
                    <input type="number" class="form-control" id="valor_estandar" name="valor_estandar" min="0"/>
                </div>
                <div class="col-md-6">
                    <label>Color</label>
                    <select class="form-control" id="color_id" name="color_id">
                        <option value="1" selected>Seleccionar Color</option>
                        <!-- Options will be populated by JavaScript -->
                    </select>
                    <div class="mt-2">
                        <small class="text-muted">Vista previa del color:</small>
                        <div id="colorPreview" class="color-preview bg-primary"></div>
                        <span id="colorName">Primario</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label>Orden Estándar</label>
                    <input type="number" class="form-control" id="orden_estandar" name="orden_estandar" min="0"/>
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
// Mapa de colores Bootstrap
const bootstrapColors = {
    '1': { bg: 'primary', text: 'white', hex: '#007bff', class: 'btn-primary' },
    '2': { bg: 'secondary', text: 'white', hex: '#6c757d', class: 'btn-secondary' },
    '3': { bg: 'success', text: 'white', hex: '#28a745', class: 'btn-success' },
    '4': { bg: 'danger', text: 'white', hex: '#dc3545', class: 'btn-danger' },
    '5': { bg: 'warning', text: 'dark', hex: '#ffc107', class: 'btn-warning' },
    '6': { bg: 'info', text: 'white', hex: '#17a2b8', class: 'btn-info' },
    '7': { bg: 'light', text: 'dark', hex: '#f8f9fa', class: 'btn-light' },
    '8': { bg: 'dark', text: 'white', hex: '#343a40', class: 'btn-dark' }
};

// Función para actualizar la vista previa del color
function updateColorPreview(colorId) {
    if (!colorId) colorId = '1';
    
    var colorInfo = bootstrapColors[colorId] || bootstrapColors['1'];
    $('#colorPreview').removeClass().addClass('color-preview bg-' + colorInfo.bg);
    $('#colorName').text(colorInfo.bg.charAt(0).toUpperCase() + colorInfo.bg.slice(1));
}

// Función para cargar colores
function cargarColores(selectedId = null) {
    $.ajax({
        url: 'estados_registros_ajax.php',
        type: 'GET',
        data: {accion: 'obtenerColores'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Seleccionar Color</option>';
                $.each(res, function(index, color) {
                    var selected = (selectedId == color.color_id) ? 'selected' : '';
                    var colorInfo = bootstrapColors[color.color_id] || bootstrapColors['1'];
                    var bgColor = colorInfo.hex;
                    var textColor = colorInfo.text === 'dark' ? '#212529' : 'white';
                    
                    options += `<option value="${color.color_id}" ${selected} 
                                style="background-color: ${bgColor}; color: ${textColor}">
                                ${color.nombre_color}
                              </option>`;
                });
                $('#color_id').html(options);
                
                // Actualizar vista previa si hay color seleccionado
                if (selectedId) {
                    updateColorPreview(selectedId);
                }
            }
        },
        error: function() {
            console.error('Error al cargar colores');
            $('#color_id').html('<option value="">Error al cargar colores</option>');
        }
    });
}

$(document).ready(function(){
    // Cargar colores al iniciar
    cargarColores();
    
    // Event listener para cambio de color
    $('#color_id').change(function() {
        var colorId = $(this).val() || '1';
        updateColorPreview(colorId);
    });
    
    // Configuración de DataTable
    var tabla = $('#tablaEstadosRegistros').DataTable({
        pageLength: 25,
        lengthMenu: [25, 50, 100, 200],
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
        order: [[5, 'asc'], [0, 'asc']],
        initComplete: function() {
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
            url: 'estados_registros_ajax.php',
            type: 'GET',
            data: {accion: 'listar'},
            dataSrc: ''
        },
        language: {
            "search": "Buscar:",
            "searchPlaceholder": "Buscar estados...",
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
            { data: 'estado_registro_id' },
            { 
                data: 'estado_registro',
                render: function(data, type, row) {
                    if (row.color_id) {
                        var colorInfo = bootstrapColors[row.color_id] || bootstrapColors['1'];
                        return `<span class="badge bg-${colorInfo.bg}">${data}</span>`;
                    }
                    return data;
                }
            },
            { 
                data: 'codigo_estandar',
                render: function(data) {
                    return data || '<span class="text-muted">-</span>';
                }
            },
            { 
                data: 'valor_estandar',
                className: "text-center",
                render: function(data) {
                    return data || '<span class="text-muted">-</span>';
                }
            },
            { 
                data: 'color_nombre',
                render: function(data, type, row) {
                    if (row.color_id) {
                        var colorInfo = bootstrapColors[row.color_id] || bootstrapColors['1'];
                        return `<span class="badge bg-${colorInfo.bg}">${data}</span>`;
                    }
                    return data || '<span class="text-muted">-</span>';
                }
            },
            { 
                data: 'orden_estandar',
                className: "text-center"
            },
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

    // Botón Nuevo
    $('#btnNuevo').click(function(){
        $('#formEstadoRegistro')[0].reset();
        $('#estado_registro_id').val('');
        $('#color_id').val('1');
        updateColorPreview('1');
        $('#modalLabel').text('Nuevo Estado de Registro');
        var modal = new bootstrap.Modal(document.getElementById('modalEstadoRegistro'));
        modal.show();
    });

    // Editar
    $('#tablaEstadosRegistros tbody').on('click', '.btnEditar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        $.get('estados_registros_ajax.php', {accion: 'obtener', estado_registro_id: data.estado_registro_id}, function(res){
            if(res){
                $('#estado_registro_id').val(res.estado_registro_id);
                $('#estado_registro').val(res.estado_registro);
                $('#codigo_estandar').val(res.codigo_estandar);
                $('#valor_estandar').val(res.valor_estandar);
                $('#orden_estandar').val(res.orden_estandar);
                
                // Cargar colores con el seleccionado
                cargarColores(res.color_id || '1');
                
                $('#modalLabel').text('Editar Estado de Registro');
                var modal = new bootstrap.Modal(document.getElementById('modalEstadoRegistro'));
                modal.show();
                
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'No se pudieron obtener los datos'
                });
            }
        }, 'json');
    });

    // Eliminar
    $('#tablaEstadosRegistros tbody').on('click', '.btnEliminar', function(){
        Swal.fire({
            title: "¿Estás seguro?",
            text: "¡Esta acción no se puede deshacer!",
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: "Sí, eliminar",
            cancelButtonText: "Cancelar"
        }).then((result) => {
            if (result.isConfirmed) {
                var data = tabla.row($(this).parents('tr')).data();
                $.get('estados_registros_ajax.php', {accion: 'eliminar', estado_registro_id: data.estado_registro_id}, function(res){
                    if(res.resultado){
                        tabla.ajax.reload();
                        Swal.fire({                    
                            icon: "success",
                            title: "¡Eliminado!",
                            text: "El registro ha sido eliminado",
                            showConfirmButton: false,
                            timer: 1000
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || "Error al eliminar el registro"
                        });
                    }
                }, 'json');
            }
        });
    });

    // Guardar
    $('#btnGuardar').click(function(){
        // Validar campos obligatorios
        if ($('#estado_registro').val().trim() === '') {
            $('#formEstadoRegistro').addClass('was-validated');
            Swal.fire({
                icon: 'warning',
                title: 'Campo obligatorio',
                text: 'El campo Estado es obligatorio'
            });
            return false;
        }
        
        var id = $('#estado_registro_id').val();
        var accion = id ? 'editar' : 'agregar';
        var formData = {
            accion: accion,
            estado_registro_id: id,
            estado_registro: $('#estado_registro').val(),
            codigo_estandar: $('#codigo_estandar').val(),
            valor_estandar: $('#valor_estandar').val() || null,
            color_id: $('#color_id').val() || '1',
            orden_estandar: $('#orden_estandar').val() || null
        };

        $.ajax({
            url: 'estados_registros_ajax.php',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if(res.resultado) {
                    tabla.ajax.reload(null, false);
                    
                    // Cerrar el modal
                    var modal = bootstrap.Modal.getInstance(document.getElementById('modalEstadoRegistro'));
                    modal.hide();
                    
                    // Resetear el formulario
                    $('#formEstadoRegistro')[0].reset();
                    $('#color_id').val('1');
                    updateColorPreview('1');
                    $('#formEstadoRegistro').removeClass('was-validated');
                    
                    Swal.fire({
                        icon: "success",
                        title: "¡Operación exitosa!",
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
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>