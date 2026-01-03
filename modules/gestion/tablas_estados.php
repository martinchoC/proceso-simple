<?php
// Configuración de la página
$pageTitle = "Gestión de Estados por Tabla";
$currentPage = 'perfiles';
$modudo_idx = 2;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>
<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Estados por Tabla</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Tablas - Estados</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>
    <div class="app-content">
        <div class="container-fluid">
            <div class="content-wrapper">
                <section class="content">
                    <div class="container-fluid">                      
                        <div class="row">
                            <div class="col-12">
                                <div class="card">                                
                                    <div class="card-body">
                                        <button class="btn btn-primary mb-3" id="btnNuevo">Nuevo Estado</button>
                                        <table id="tablaTablasEstados" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Tabla</th>
                                                    <th>Estado</th>
                                                    <th>Código Estándar</th>
                                                    <th>Valor Estándar</th>
                                                    <th>Color</th>
                                                    <th>Orden</th>
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
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalLabel">Estado de Tabla</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <form id="formTablaEstado">
            <input type="hidden" id="tabla_estado_registro_id" name="tabla_estado_registro_id" />
             
            <div class="row g-3">
                <div class="col-md-12">
                    <label>Tabla *</label>
                    <select class="form-select" id="tabla_id" name="tabla_id" required>
                        <option value="">Seleccionar tabla</option>
                    </select>
                    <div class="invalid-feedback">Debe seleccionar una tabla</div>
                </div>    
                <div class="col-md-12">
                    <label>Estado *</label>
                    <input type="text" class="form-control" id="estado_registro" name="estado_registro" 
                        placeholder="Nombre del estado" required/>
                    <div class="invalid-feedback">Debe ingresar un nombre de estado</div>
                </div>
                <div class="col-md-6">
                    <label>Código Estándar</label>
                    <input type="text" class="form-control" id="codigo_estandar" name="codigo_estandar" 
                        placeholder="Código estándar"/>
                </div>
                <div class="col-md-6">
                    <label>Valor Estándar</label>
                    <input type="number" min="1" class="form-control" id="valor_estandar" name="valor_estandar" value="1"/>
                </div>
                <div class="col-md-12">
                    <label>Color</label>
                    <select class="form-select" id="color_id" name="color_id" style="padding: 0;">
                        <option value="">Seleccionar color</option>
                    </select>
                    <div class="form-text">El color se mostrará visualmente en el selector</div>
                </div>
                <div class="col-md-12">
                    <label>Orden</label>
                    <input type="number" min="1" class="form-control" id="orden" name="orden" value="1"/>
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

<style>
/* Estilos mejorados para el select de colores */
#color_id {
    background-image: none !important;
    padding: 8px 12px !important;
    height: auto !important;
    border: 1px solid #ced4da !important;
    border-radius: 0.375rem !important;
}

#color_id:focus {
    border-color: #86b7fe !important;
    outline: 0 !important;
    box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25) !important;
}

#color_id option {
    padding: 8px 12px !important;
    margin: 2px 0 !important;
    border-radius: 4px !important;
    border: 1px solid #dee2e6 !important;
    font-weight: 500 !important;
}

/* Estilos para los badges en la tabla */
.badge {
    font-size: 0.875em;
    font-weight: 500;
    min-width: 80px;
    display: inline-block;
    text-align: center;
    padding: 6px 12px !important;
    border-radius: 4px !important;
    border: 1px solid #dee2e6 !important;
}

/* Forzar la visualización de colores en el select */
select#color_id {
    -webkit-appearance: none !important;
    -moz-appearance: none !important;
    appearance: none !important;
}

select#color_id::-ms-expand {
    display: none !important;
}

</style>

<script>
$(document).ready(function(){
    // Variables globales para almacenar opciones
    var tablasOptions = [];
    var coloresOptions = [];
    
    // Función mejorada para determinar si un color es claro
    function isLightColor(color) {
        if (!color) return true;
        
        // Si es un nombre de color CSS común, convertir a hex
        var colorMap = {
            'red': '#ff0000', 'green': '#008000', 'blue': '#0000ff',
            'yellow': '#ffff00', 'orange': '#ffa500', 'purple': '#800080',
            'pink': '#ffc0cb', 'brown': '#a52a2a', 'black': '#000000',
            'white': '#ffffff', 'gray': '#808080', 'grey': '#808080',
            'lightgray': '#d3d3d3', 'lightgrey': '#d3d3d3', 'darkgray': '#a9a9a9',
            'darkgrey': '#a9a9a9', 'lightblue': '#add8e6', 'darkblue': '#00008b',
            'lightgreen': '#90ee90', 'darkgreen': '#006400',
            'success': '#28a745', 'primary': '#007bff', 'secondary': '#6c757d',
            'danger': '#dc3545', 'warning': '#ffc107', 'info': '#17a2b8',
            'light': '#f8f9fa', 'dark': '#343a40'
        };
        
        var hexColor = color.toLowerCase();
        if (colorMap[hexColor]) {
            hexColor = colorMap[hexColor];
        }
        
        // Si ya es un color hex, procesarlo
        if (hexColor.startsWith('#')) {
            // Expandir formato corto (#RGB a #RRGGBB)
            if (hexColor.length === 4) {
                hexColor = '#' + hexColor[1] + hexColor[1] + hexColor[2] + hexColor[2] + hexColor[3] + hexColor[3];
            }
            
            try {
                var r = parseInt(hexColor.substr(1, 2), 16);
                var g = parseInt(hexColor.substr(3, 2), 16);
                var b = parseInt(hexColor.substr(5, 2), 16);
                
                // Calcular luminosidad (fórmula de percepción humana)
                var luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
                return luminance > 0.5;
            } catch (e) {
                console.log('Error procesando color:', hexColor, e);
                return true;
            }
        }
        
        return true; // Por defecto, asumir que es claro
    }
    
    // Función para aplicar estilos al select de colores
    function aplicarEstilosSelectColores() {
        $('#color_id option').each(function() {
            var $option = $(this);
            var valor = $option.val();
            
            if (valor) {
                var colorInfo = coloresOptions.find(function(color) {
                    return color.color_id == valor;
                });
                
                if (colorInfo) {
                    var bgColor = colorInfo.bg_clase || colorInfo.color_clase || '#ffffff';
                    var textColor = colorInfo.text_clase || (isLightColor(bgColor) ? '#000000' : '#ffffff');
                    
                    $option.css({
                        'background-color': bgColor,
                        'color': textColor,
                        'padding': '8px 12px',
                        'margin': '2px 0',
                        'border-radius': '4px',
                        'border': '1px solid #dee2e6',
                        'font-weight': '500'
                    });
                }
            }
        });
    }
    
    // Cargar opciones de tablas y colores
    function cargarOpciones() {
        $.get('tablas_estados_ajax.php', {accion: 'obtener_tablas'}, function(res){
            if(res && res.length > 0) {
                tablasOptions = res;
                $('#tabla_id').empty().append('<option value="">Seleccionar tabla</option>');
                $.each(res, function(i, tabla) {
                    $('#tabla_id').append($('<option>', {
                        value: tabla.tabla_id,
                        text: tabla.tabla_nombre || 'Tabla ' + tabla.tabla_id
                    }));
                });
            }
        }, 'json');
        
        // Cargar colores con mejor visualización
        $.get('tablas_estados_ajax.php', {accion: 'obtener_colores'}, function(res){
            if(res && res.length > 0) {
                coloresOptions = res;
                $('#color_id').empty().append('<option value="">Seleccionar color</option>');
                
                $.each(res, function(i, color) {
                    var optionText = color.nombre_color + (color.descripcion ? ' - ' + color.descripcion : '');
                    $('#color_id').append($('<option>', {
                        value: color.color_id,
                        text: optionText
                    }));
                });
                
                // Aplicar estilos después de agregar las opciones
                setTimeout(aplicarEstilosSelectColores, 100);
            }
        }, 'json');
    }
    
    // Reaplicar estilos cuando se abre el modal
    $('#modalTablaEstado').on('show.bs.modal', function() {
        setTimeout(aplicarEstilosSelectColores, 300);
    });
    
    // Reaplicar estilos cuando cambia el select
    $('#color_id').on('focus', function() {
        setTimeout(aplicarEstilosSelectColores, 100);
    });
    
    // Configuración de DataTable
    var tabla = $('#tablaTablasEstados').DataTable({
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
                    columns: [0, 1, 2, 3, 4, 5, 6]
                }
            },
            {
                extend: 'pdfHtml5',
                text: '<i class="fas fa-file-pdf"></i> PDF',
                titleAttr: 'Exportar a PDF',
                className: 'btn btn-danger btn-sm',
                orientation: 'portrait',
                pageSize: 'A4',
                exportOptions: {
                    columns: [0, 1, 2, 3, 4, 5, 6]
                }
            }
        ],
        ajax: {
            url: 'tablas_estados_ajax.php',
            type: 'GET',
            data: {accion: 'listar'},
            dataSrc: ''
        },
        order: [[1, 'asc'], [6, 'asc']],
        language: {
            "search": "Buscar:",
            "searchPlaceholder": "Buscar estados...",
            "lengthMenu": "Mostrar _MENU_ registros por página",
            "zeroRecords": "No se encontraron estados",
            "info": "Mostrando _START_ a _END_ de _TOTAL_ estados",
            "infoEmpty": "Mostrando 0 a 0 de 0 estados",
            "infoFiltered": "(filtrado de _MAX_ estados totales)",
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
                render: function(data, type, row) {
                    return data || 'N/A';
                }
            },
            { 
                data: 'estado_registro',
                render: function(data, type, row) {
                    return data || 'N/A';
                }
            },
            { 
                data: 'codigo_estandar',
                render: function(data, type, row) {
                    return data || 'N/A';
                }
            },
            { 
                data: 'valor_estandar',
                render: function(data, type, row) {
                    return data || 'N/A';
                }
            },
            { 
                data: null,
                render: function(data, type, row) {
                    var colorName = row.nombre_color || 'N/A';
                    var bgColor = row.bg_clase || row.color_clase;
                    
                    if (bgColor && type === 'display') {
                        var textColor = row.text_clase || (isLightColor(bgColor) ? '#000000' : '#ffffff');
                        return '<span class="badge" style="background-color: ' + bgColor + '; color: ' + textColor + '; padding: 6px 12px; border-radius: 4px; border: 1px solid #dee2e6; font-weight: 500;">' + colorName + '</span>';
                    }
                    
                    return colorName;
                }
            },
            { data: 'orden' },
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
        ],
        drawCallback: function(settings) {
            // Reaplicar estilos después de que se dibuja la tabla
            setTimeout(function() {
                $('.badge').each(function() {
                    var $badge = $(this);
                    var bgColor = $badge.css('background-color');
                    if (bgColor && bgColor !== 'rgba(0, 0, 0, 0)') {
                        var textColor = isLightColor(bgColor) ? '#000000' : '#ffffff';
                        $badge.css('color', textColor);
                    }
                });
            }, 100);
        }
    });

    // Cargar opciones al iniciar
    cargarOpciones();

    $('#btnNuevo').click(function(){
        $('#formTablaEstado')[0].reset();
        $('#tabla_estado_registro_id').val('');
        $('#valor_estandar').val('1');
        $('#orden').val('1');
        $('#modalLabel').text('Nuevo Estado de Tabla');
        var modal = new bootstrap.Modal(document.getElementById('modalTablaEstado'));
        modal.show();
    });

    // Eliminar registro
    $('#tablaTablasEstados tbody').on('click', '.btnEliminar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        
        Swal.fire({
            title: '¿Eliminar estado?',
            text: '¿Estás seguro de querer eliminar este estado?',
            icon: "warning",
            showCancelButton: true,
            confirmButtonColor: "#3085d6",
            cancelButtonColor: "#d33",
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: "Cancelar"
        }).then((result) => {
            if (result.isConfirmed) {
                $.get('tablas_estados_ajax.php', {
                    accion: 'eliminar', 
                    tabla_estado_registro_id: data.tabla_estado_registro_id
                }, function(res){
                    if(res.resultado){
                        tabla.ajax.reload();
                        Swal.fire({
                            icon: "success",
                            title: "¡Éxito!",
                            text: "Estado eliminado correctamente",
                            showConfirmButton: false,
                            timer: 1500
                        });
                    } else {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: res.error || "Error al eliminar el estado"
                        });
                    }
                }, 'json');
            }
        });
    });

    $('#tablaTablasEstados tbody').on('click', '.btnEditar', function(){
        var data = tabla.row($(this).parents('tr')).data();
        
        $.get('tablas_estados_ajax.php', {accion: 'obtener', tabla_estado_registro_id: data.tabla_estado_registro_id}, function(res){
            if(res){
                $('#tabla_estado_registro_id').val(res.tabla_estado_registro_id);
                $('#tabla_id').val(res.tabla_id);
                $('#estado_registro').val(res.estado_registro || '');
                $('#codigo_estandar').val(res.codigo_estandar || '');
                $('#valor_estandar').val(res.valor_estandar || '1');
                $('#color_id').val(res.color_id || '');
                $('#orden').val(res.orden || '1');
                
                $('#modalLabel').text('Editar Estado de Tabla');
                var modal = new bootstrap.Modal(document.getElementById('modalTablaEstado'));
                modal.show();
                
                // Reaplicar estilos después de cargar los datos
                setTimeout(aplicarEstilosSelectColores, 300);
            } else {
                alert('Error al obtener datos');
            }
        }, 'json');
    });

    $('#btnGuardar').click(function(){
        var form = document.getElementById('formTablaEstado');
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return false;
        }
        
        var id = $('#tabla_estado_registro_id').val();
        var accion = id ? 'editar' : 'agregar';
        var formData = {
            accion: accion,
            tabla_estado_registro_id: id,
            tabla_id: $('#tabla_id').val(),
            estado_registro: $('#estado_registro').val(),
            codigo_estandar: $('#codigo_estandar').val(),
            valor_estandar: $('#valor_estandar').val() || 1,
            color_id: $('#color_id').val() || 1,
            orden: $('#orden').val() || 1
        };

        $.ajax({
            url: 'tablas_estados_ajax.php',
            type: 'GET',
            data: formData,
            dataType: 'json',
            success: function(res) {
                if(res.resultado) {
                    tabla.ajax.reload(null, false);
                    
                    var modal = bootstrap.Modal.getInstance(document.getElementById('modalTablaEstado'));
                    modal.hide();
                    
                    $('#formTablaEstado')[0].reset();
                    form.classList.remove('was-validated');
                    
                    Swal.fire({
                        icon: "success",
                        title: "¡Éxito!",
                        text: id ? "Estado actualizado correctamente" : "Estado creado correctamente",
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