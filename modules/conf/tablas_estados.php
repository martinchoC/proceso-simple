<?php
// Configuración de la página
$pageTitle = "Gestión de Estados de Registros por Tabla";
$currentPage = 'tablas_estados';
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

/* Indicador de estado inicial */
.estado-inicial {
    background-color: #28a745;
    color: white;
    padding: 2px 6px;
    border-radius: 3px;
    font-size: 0.75em;
    margin-left: 5px;
}
</style>

<main class="app-main">
    <!--begin::App Content Header-->
    <div class="app-content-header">
        <!--begin::Container-->
        <div class="container-fluid">
        <!--begin::Row-->
        <div class="row">
            <div class="col-sm-6"><h3 class="mb-0">Estados de Registros por Tabla</h3></div>
            <div class="col-sm-6">
            <ol class="breadcrumb float-sm-end">
                <li class="breadcrumb-item"><a href="#">Home</a></li>
                <li class="breadcrumb-item active" aria-current="page">Estados Tablas</li>
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
                                    <table id="tablaEstados" class="table table-striped table-bordered">
                                        <thead>
                                            <tr>
                                                <th>ID</th>
                                                <th>Tabla</th>
                                                <th>Estado</th>
                                                <th>Nombre Estado</th>
                                                <th>Color</th>
                                                <th>Es Inicial</th>
                                                <th>Orden</th>
                                                <th>Estado Registro</th>
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
<div class="modal fade" id="modalTablaEstado" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabel">Estado de Tabla</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <form id="formTablaEstado">
            <input type="hidden" id="tabla_estado_registro_id" name="tabla_estado_registro_id" />
            
            <div class="row g-3">
                <div class="col-md-6">
                    <label>Tabla *</label>
                    <select class="form-control" id="tabla_id" name="tabla_id" required>
                        <option value="">Seleccionar Tabla</option>
                        <!-- Options will be populated by JavaScript -->
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Estado Registro *</label>
                    <select class="form-control" id="estado_registro_id" name="estado_registro_id" required>
                        <option value="">Seleccionar Estado</option>
                        <!-- Options will be populated by JavaScript -->
                    </select>
                </div>
                <div class="col-md-6">
                    <label>Nombre Estado *</label>
                    <input type="text" class="form-control" id="tabla_estado_registro" name="tabla_estado_registro" required/>
                </div>
                <div class="col-md-6">
                    <label>Color</label>
                    <select class="form-control" id="color_id" name="color_id">
                        <option value="">Seleccionar Color</option>
                        <!-- Options will be populated by JavaScript -->
                    </select>
                    <div class="mt-2">
                        <small class="text-muted">Vista previa del color:</small>
                        <div id="colorPreview" class="color-preview bg-primary"></div>
                        <span id="colorName">Primario</span>
                    </div>
                </div>
                <div class="col-md-6">
                    <label>Orden</label>
                    <input type="number" class="form-control" id="orden" name="orden" value="1" min="1"/>
                </div>
                
                <div class="col-md-6">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="es_inicial" name="es_inicial" value="1">
                        <label class="form-check-label" for="es_inicial">
                            ¿Es Estado Inicial?
                        </label>
                        <small class="text-muted d-block">Solo puede haber un estado inicial por tabla</small>
                    </div>
                </div>
                <div class="col-12">
                    <div class="alert alert-info" id="inicialWarning" style="display: none;">
                        <i class="fas fa-info-circle"></i> Ya existe un estado inicial para esta tabla. Si marca este como inicial, el anterior dejará de serlo.
                    </div>
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
    'btn-primary': { bg: 'primary', text: 'white', hex: '#007bff' },
    'btn-secondary': { bg: 'secondary', text: 'white', hex: '#6c757d' },
    'btn-success': { bg: 'success', text: 'white', hex: '#28a745' },
    'btn-danger': { bg: 'danger', text: 'white', hex: '#dc3545' },
    'btn-warning': { bg: 'warning', text: 'dark', hex: '#ffc107' },
    'btn-info': { bg: 'info', text: 'white', hex: '#17a2b8' },
    'btn-light': { bg: 'light', text: 'dark', hex: '#f8f9fa' },
    'btn-dark': { bg: 'dark', text: 'white', hex: '#343a40' },
    'btn-outline-primary': { bg: 'outline-primary', text: 'primary', outline: true, hex: '#007bff' },
    'btn-outline-secondary': { bg: 'outline-secondary', text: 'secondary', outline: true, hex: '#6c757d' }
};

// Función para actualizar la vista previa del color
function updateColorPreview(colorClass) {
    if (!colorClass) return;
    
    var colorInfo = bootstrapColors[colorClass] || bootstrapColors['btn-primary'];
    var previewClass = colorClass.includes('outline') ? 'border-' + colorClass.replace('btn-outline-', '') : 'bg-' + colorClass.replace('btn-', '');
    
    $('#colorPreview').removeClass().addClass('color-preview ' + previewClass);
    $('#colorName').text(colorClass.replace('btn-', '').replace('outline-', 'Outline '));
}

// Función para cargar tablas
function cargarTablas(selectedId = null) {
    $.ajax({
        url: 'tablas_estados_ajax.php',
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

// Función para cargar estados de registro
function cargarEstadosRegistro(selectedId = null) {
    $.ajax({
        url: 'tablas_estados_ajax.php',
        type: 'GET',
        data: {accion: 'obtenerEstadosRegistro'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Seleccionar estado</option>';
                $.each(res, function(index, estado) {
                    var selected = (selectedId == estado.estado_registro_id) ? 'selected' : '';
                    options += `<option value="${estado.estado_registro_id}" ${selected}>${estado.estado_registro}</option>`;
                });
                $('#estado_registro_id').html(options);
            }
        },
        error: function() {
            console.error('Error al cargar estados de registro');
            $('#estado_registro_id').html('<option value="">Error al cargar estados</option>');
        }
    });
}

// Función para cargar colores
function cargarColores(selectedId = null) {
    $.ajax({
        url: 'tablas_estados_ajax.php',
        type: 'GET',
        data: {accion: 'obtenerColores'},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var options = '<option value="">Seleccionar Color</option>';
                $.each(res, function(index, color) {
                    var selected = (selectedId == color.color_id) ? 'selected' : '';
                    var colorInfo = bootstrapColors[color.color_clase] || bootstrapColors['btn-primary'];
                    var textColor = colorInfo.text === 'dark' ? '#212529' : 'white';
                    var bgColor = colorInfo.hex;
                    var isOutline = color.color_clase.includes('outline');
                    
                    // Crear preview visual en el option
                    var optionStyle = isOutline 
                        ? `style="border: 2px solid ${bgColor}; color: ${bgColor}; background-color: white;"`
                        : `style="background-color: ${bgColor}; color: ${textColor};"`;
                    
                    options += `<option value="${color.color_id}" ${selected} ${optionStyle}>${color.nombre_color}</option>`;
                });
                $('#color_id').html(options);
                
                // Si hay un color seleccionado, actualizar vista previa
                if (selectedId) {
                    var selectedColor = res.find(function(color) {
                        return color.color_id == selectedId;
                    });
                    if (selectedColor) {
                        updateColorPreview(selectedColor.color_clase);
                    }
                }
            }
        },
        error: function() {
            console.error('Error al cargar colores');
            $('#color_id').html('<option value="">Error al cargar colores</option>');
        }
    });
}

// Función para verificar si ya existe un estado inicial
function verificarEstadoInicial(tablaId, estadoId = 0) {
    if (!tablaId || tablaId == '') return;
    
    $.ajax({
        url: 'tablas_estados_ajax.php',
        type: 'GET',
        data: {
            accion: 'verificarEstadoInicial',
            tabla_id: tablaId,
            excluir_id: estadoId
        },
        dataType: 'json',
        success: function(res) {
            if (res.existe) {
                $('#inicialWarning').show();
            } else {
                $('#inicialWarning').hide();
            }
        }
    });
}

$(document).ready(function(){
    // Cargar todos los selects
    cargarTablas();
    cargarEstadosRegistro();
    cargarColores();
    
    // Event listener para cambio de tabla
    $('#tabla_id').change(function() {
        var tablaId = $(this).val();
        var estadoId = $('#tabla_estado_registro_id').val() || 0;
        verificarEstadoInicial(tablaId, estadoId);
    });
    
    // Event listener para cambio de color
    $('#color_id').change(function() {
        var colorId = $(this).val();
        if (colorId) {
            cargarColores(colorId);
        }
    });
    
    // Configuración de DataTable
    var tabla = $('#tablaEstados').DataTable({
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
            url: 'tablas_estados_ajax.php',
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
            { data: 'tabla_estado_registro_id' },
            { 
                data: 'tabla_nombre',
                render: function(data) {
                    return data || '<span class="text-muted">-</span>';
                }
            },
            { 
                data: 'estado_registro',
                render: function(data) {
                    return data || '<span class="text-muted">-</span>';
                }
            },
            { 
                data: 'tabla_estado_registro',
                render: function(data, type, row) {
                    var html = data || '<span class="text-muted">-</span>';
                    if (row.es_inicial == 1) {
                        html += ' <span class="estado-inicial">Inicial</span>';
                    }
                    return html;
                }
            },
            { 
                data: 'color_nombre',
                render: function(data, type, row) {
                    if (row.color_clase) {
                        var colorInfo = bootstrapColors[row.color_clase] || bootstrapColors['btn-primary'];
                        var badgeClass = 'bg-' + colorInfo.bg;
                        return `<span class="badge ${badgeClass}">${data}</span>`;
                    } else if (data) {
                        return data;
                    } else {
                        return '<span class="text-muted">-</span>';
                    }
                }
            },
            { 
                data: 'es_inicial_nombre',
                className: "text-center",
                render: function(data, type, row) {
                    if (row.es_inicial == 1) {
                        return '<span class="badge badge-success">Sí</span>';
                    } else {
                        return '<span class="badge badge-secondary">No</span>';
                    }
                }
            },
            { 
                data: 'orden',
                className: "text-center"
            },
            { 
                data: 'estado_nombre',
                className: "text-center",
                render: function(data, type, row) {
                    var badgeClass = row.tabla_estado_registro_id == 1 ? 'badge-success' : 'badge-danger';
                    return `<span class="badge ${badgeClass}">${data}</span>`;
                }
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

   $('#btnNuevo').click(function(){
    $('#formTablaEstado')[0].reset();
    $('#tabla_estado_registro_id').val('');
    // $('#estado_registro').val('1'); // Eliminar esta línea
    $('#orden').val('1');
    $('#es_inicial').prop('checked', false);
    $('#inicialWarning').hide();
    updateColorPreview('btn-primary');
    $('#modalLabel').text('Nuevo Estado de Tabla');
    var modal = new bootstrap.Modal(document.getElementById('modalTablaEstado'));
    modal.show();
});

    $('#tablaEstados tbody').on('click', '.btnEditar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        $.get('tablas_estados_ajax.php', {accion: 'obtener', tabla_estado_registro_id: data.tabla_estado_registro_id}, function(res){
            if(res){
                $('#tabla_estado_registro_id').val(res.tabla_estado_registro_id);
                $('#tabla_estado_registro').val(res.tabla_estado_registro);
                $('#orden').val(res.orden);
                $('#tabla_estado_registro_id').val(res.tabla_estado_registro_id || 1);
                $('#es_inicial').prop('checked', res.es_inicial == 1);
                
                // Cargar selects con valores actuales
                cargarTablas(res.tabla_id);
                cargarEstadosRegistro(res.estado_registro_id);
                cargarColores(res.color_id);
                
                // Verificar estado inicial
                verificarEstadoInicial(res.tabla_id, res.tabla_estado_registro_id);
                
                // Actualizar vista previa del color después de cargar
                setTimeout(function() {
                    if (res.color_id) {
                        cargarColores(res.color_id);
                    } else {
                        updateColorPreview('btn-primary');
                    }
                }, 300);

                $('#modalLabel').text('Editar Estado de Tabla');
                var modal = new bootstrap.Modal(document.getElementById('modalTablaEstado'));
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

    $('#tablaEstados tbody').on('click', '.btnEliminar', function(){
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
                $.get('tablas_estados_ajax.php', {accion: 'eliminar', tabla_estado_registro_id: data.tabla_estado_registro_id}, function(res){
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

   $('#btnGuardar').click(function(){
    // Validar campos obligatorios
    if ($('#tabla_id').val() === '' || 
        $('#estado_registro_id').val() === '' || 
        $('#tabla_estado_registro').val().trim() === '') {
        $('#formTablaEstado').addClass('was-validated');
        Swal.fire({
            icon: 'warning',
            title: 'Campos obligatorios',
            text: 'Los campos marcados con * son obligatorios'
        });
        return false;
    }
    
    var id = $('#tabla_estado_registro_id').val();
    var accion = id ? 'editar' : 'agregar';
    var formData = {
        accion: accion,
        tabla_estado_registro_id: id,
        tabla_id: $('#tabla_id').val(),
        estado_registro_id: $('#estado_registro_id').val(),
        tabla_estado_registro: $('#tabla_estado_registro').val(),
        color_id: $('#color_id').val() || 1,
        es_inicial: $('#es_inicial').is(':checked') ? 1 : 0,
        orden: $('#orden').val() || 1
        // NOTA: No enviamos 'estado_registro' porque no existe en la tabla
    };

    $.ajax({
        url: 'tablas_estados_ajax.php',
        type: 'GET',
        data: formData,
        dataType: 'json',
        success: function(res) {
            console.log('Respuesta del servidor:', res); // Para depuración
            
            if(res.resultado === true || res.resultado === 1) {
                tabla.ajax.reload(null, false);
                
                // Cerrar el modal correctamente
                var modal = bootstrap.Modal.getInstance(document.getElementById('modalTablaEstado'));
                if (modal) {
                    modal.hide();
                }
                
                // Resetear el formulario
                $('#formTablaEstado')[0].reset();
                $('#tabla_estado_registro_id').val('');
                $('#orden').val('1');
                $('#es_inicial').prop('checked', false);
                $('#inicialWarning').hide();
                updateColorPreview('btn-primary');
                $('#formTablaEstado').removeClass('was-validated');
                
                Swal.fire({
                    icon: "success",
                    title: "¡Operación exitosa!",
                    showConfirmButton: false,
                    timer: 1500
                });
            } else {
                // Para depurar, muestra el error del servidor
                console.error('Error del servidor:', res);
                Swal.fire({
                    icon: "error",
                    title: "Error",
                    text: res.error || "Error al guardar los datos. Código: " + res.resultado
                });
            }
        },
        error: function(xhr, status, error) {
            // Para depurar, muestra detalles del error
            console.error('Error AJAX:', status, error);
            console.error('Respuesta:', xhr.responseText);
            Swal.fire({
                icon: "error",
                title: "Error de conexión",
                text: "Error de conexión con el servidor. Ver consola para más detalles."
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