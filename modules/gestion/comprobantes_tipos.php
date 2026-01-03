<?php
// Configuración de la página
require_once __DIR__ . '/../../conexion.php';
$pageTitle = "Gestión de Tipos de Comprobantes";
$currentPage = 'comprobantes_tipos';
$modudo_idx = 2;
$empresa_idx = 2;
$pagina_idx = 45;

// Definir constante para rutas
define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));

// Incluir header
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Tipos de Comprobantes</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Tipos de Comprobantes</li>
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
                                        <!-- El botón Agregar se cargará dinámicamente -->
                                        <div id="contenedor-boton-agregar"></div>
                                        <table id="tablaComprobantesTipos" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>                                                    
                                                    <th>Grupo</th>
                                                    <th>Tipo de Comprobante</th>
                                                    <th>Orden</th>
                                                    <th>Fiscal</th>
                                                    <th>Código</th>
                                                    <th>Letra</th>
                                                    <th>Signo</th>
                                                    <th>Impactos</th>
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
            <div class="modal fade" id="modalComprobanteTipo" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Tipo de Comprobante</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formComprobanteTipo">
                                <input type="hidden" id="comprobante_tipo_id" name="comprobante_tipo_id" />
                                <input type="hidden" id="empresa_id" name="empresa_id" value="<?php echo $empresa_idx; ?>" />
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label>Grupo de Comprobante *</label>
                                        <select class="form-control" id="comprobante_grupo_id" name="comprobante_grupo_id" required>
                                            <option value="">Seleccionar grupo</option>
                                        </select>
                                        <div class="invalid-feedback">El grupo es obligatorio</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Tipo Fiscal</label>
                                        <select class="form-control" id="comprobante_fiscal_id" name="comprobante_fiscal_id">
                                            <option value="">Seleccionar tipo fiscal</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Nombre del Tipo *</label>
                                        <input type="text" class="form-control" id="comprobante_tipo" name="comprobante_tipo" required maxlength="100"/>
                                        <div class="invalid-feedback">El nombre del tipo es obligatorio</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Código *</label>
                                        <input type="text" class="form-control" id="codigo" name="codigo" required maxlength="10"/>
                                        <div class="invalid-feedback">El código es obligatorio</div>
                                    </div>
                                    <div class="col-md-3">
                                        <label>Orden</label>
                                        <input type="number" class="form-control" id="orden" name="orden" min="0" value="0"/>
                                        <small class="form-text text-muted">Número para ordenar</small>
                                    </div>
                                    <div class="col-md-3">
                                        <label>Letra</label>
                                        <input type="text" class="form-control" id="letra" name="letra" maxlength="1"/>
                                    </div>
                                    <div class="col-md-3">
                                        <label>Signo</label>
                                        <select class="form-control" id="signo" name="signo">
                                            <option value="+">+ (Positivo)</option>
                                            <option value="-">- (Negativo)</option>
                                            <option value="+/-">+/- (Ambos)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label>Estado</label>
                                        <select class="form-control" id="estado_registro_id" name="estado_registro_id" disabled>
                                            <option value="1">Activo</option>
                                            <option value="0">Inactivo</option>
                                        </select>
                                        <small class="form-text text-muted">El estado se gestiona con los botones de acción</small>
                                    </div>
                                    
                                    <div class="col-md-12">
                                        <label>Impactos</label>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="impacta_stock" name="impacta_stock" value="1">
                                                    <label class="form-check-label" for="impacta_stock">
                                                        Impacta Stock
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="impacta_contabilidad" name="impacta_contabilidad" value="1">
                                                    <label class="form-check-label" for="impacta_contabilidad">
                                                        Impacta Contabilidad
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" id="impacta_ctacte" name="impacta_ctacte" value="1">
                                                    <label class="form-check-label" for="impacta_ctacte">
                                                        Impacta Cta. Cte.
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-12">
                                        <label>Comentario</label>
                                        <textarea class="form-control" id="comentario" name="comentario" rows="3" maxlength="255"></textarea>
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
            $(document).ready(function(){
                // Cargar botón Agregar dinámicamente
                function cargarBotonAgregar() {
                    console.log("Cargando botón agregar...");
                    $.get('comprobantes_tipos_ajax.php', {accion: 'obtener_boton_agregar'}, function(botonAgregar){
                        console.log("Respuesta botón agregar:", botonAgregar);
                        if (botonAgregar && botonAgregar.nombre_funcion) {
                            var icono = botonAgregar.icono_clase ? `<i class="${botonAgregar.icono_clase}"></i> ` : '';
                            
                            // Usar bg_clase y text_clase si están disponibles, sino color_clase
                            var colorClase = 'btn-primary';
                            if (botonAgregar.bg_clase && botonAgregar.text_clase) {
                                colorClase = botonAgregar.bg_clase + ' ' + botonAgregar.text_clase;
                            } else if (botonAgregar.color_clase) {
                                colorClase = botonAgregar.color_clase;
                            }
                            
                            $('#contenedor-boton-agregar').html(
                                `<button class="btn ${colorClase} mb-3" id="btnNuevo">${icono}${botonAgregar.nombre_funcion}</button>`
                            );
                            console.log("Botón agregar creado correctamente");
                        } else {
                            // Botón por defecto si no hay configuración
                            $('#contenedor-boton-agregar').html('<button class="btn btn-primary mb-3" id="btnNuevo">Nuevo Tipo</button>');
                            console.log("Usando botón agregar por defecto");
                        }
                    }).fail(function(xhr, status, error) {
                        console.error("Error cargando botón agregar:", error);
                        $('#contenedor-boton-agregar').html('<button class="btn btn-primary mb-3" id="btnNuevo">Nuevo Tipo</button>');
                    });
                }

                // Cargar combos
                function cargarCombos() {
                    // Cargar grupos
                    $.get('comprobantes_tipos_ajax.php', {
                        accion: 'listar_grupos',
                        empresa_id: <?php echo $empresa_idx; ?>
                    }, function(res){
                        var options = '<option value="">Seleccionar grupo</option>';
                        $.each(res, function(index, grupo){
                            options += '<option value="' + grupo.comprobante_grupo_id + '">' + grupo.comprobante_grupo + '</option>';
                        });
                        $('#comprobante_grupo_id').html(options);
                    }, 'json');

                    // Cargar tipos fiscales
                    $.get('comprobantes_tipos_ajax.php', {
                        accion: 'listar_fiscales'
                    }, function(res){
                        var options = '<option value="">Seleccionar tipo fiscal</option>';
                        $.each(res, function(index, fiscal){
                            options += '<option value="' + fiscal.comprobante_fiscal_id + '">' + fiscal.comprobante_fiscal + '</option>';
                        });
                        $('#comprobante_fiscal_id').html(options);
                    }, 'json');
                }

                // Configuración de DataTable
                var tabla = $('#tablaComprobantesTipos').DataTable({
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
                            orientation: 'portrait',
                            pageSize: 'A4',
                            exportOptions: { columns: ':visible' }
                        }
                    ],
                    ajax: {
                        url: 'comprobantes_tipos_ajax.php',
                        type: 'GET',
                        data: {accion: 'listar', empresa_id: <?php echo $empresa_idx; ?>},
                        dataSrc: ''
                    },
                    ordering: false,
                    language: {
                        "search": "Buscar:",
                        "searchPlaceholder": "Buscar tipos...",
                        "lengthMenu": "Mostrar _MENU_ registros por página",
                        "zeroRecords": "No se encontraron tipos",
                        "info": "Mostrando _START_ a _END_ de _TOTAL_ tipos",
                        "infoEmpty": "Mostrando 0 a 0 de 0 tipos",
                        "infoFiltered": "(filtrado de _MAX_ tipos totales)",
                        "paginate": {
                            "first": "Primero",
                            "last": "Último",
                            "next": "Siguiente",
                            "previous": "Anterior"
                        }
                    },
                    columns: [
                        { data: 'comprobante_tipo_id' },
                        { data: 'comprobante_grupo' },
                        { data: 'comprobante_tipo' },
                        { 
                            data: 'orden',
                            className: "text-center"
                        },
                        { data: 'comprobante_fiscal' },
                        { data: 'codigo' },
                        { 
                            data: 'letra',
                            render: function(data){
                                return data ? data : '-';
                            }
                        },
                        { 
                            data: 'signo',
                            render: function(data){
                                var signos = {
                                    '+': '<span class="badge bg-success">+</span>',
                                    '-': '<span class="badge bg-danger">-</span>',
                                    '+/-': '<span class="badge bg-warning">+/-</span>'
                                };
                                return signos[data] || data;
                            }
                        },
                        {
                            data: null,
                            orderable: false,
                            searchable: false,
                            render: function(data){
                                var impactos = [];
                                if(data.impacta_stock == 1) impactos.push('<span class="badge bg-info">Stock</span>');
                                if(data.impacta_contabilidad == 1) impactos.push('<span class="badge bg-primary">Contab.</span>');
                                if(data.impacta_ctacte == 1) impactos.push('<span class="badge bg-secondary">Cta.Cte.</span>');
                                return impactos.length > 0 ? impactos.join(' ') : '<span class="text-muted">Ninguno</span>';
                            }
                        },
                        { 
                            data: 'estado_registro',
                            render: function(data, type, row) {
                                var clases = {
                                    '10': 'bg-warning', // Borrador
                                    '20': 'bg-success', // Confirmado  
                                    '100': 'bg-danger'  // Eliminado
                                };
                                var clase = clases[row.codigo_estandar] || 'bg-secondary';
                                return '<span class="badge ' + clase + '">' + data + '</span>';
                            }
                        },
                        {
                            data: 'botones',
                            orderable: false,
                            searchable: false,
                            className: "text-center",
                            render: function(data, type, row) {
                                var botones = '';
                                
                                if (data && data.length > 0) {
                                    data.forEach(boton => {
                                        // Usar bg_clase y text_clase si están disponibles, sino color_clase
                                        var claseBoton = 'btn-sm me-1 ';
                                        if (boton.bg_clase && boton.text_clase) {
                                            claseBoton += boton.bg_clase + ' ' + boton.text_clase;
                                        } else {
                                            claseBoton += boton.color_clase || 'btn-outline-primary';
                                        }
                                        
                                        var titulo = boton.descripcion || boton.nombre_funcion;
                                        var accionJs = boton.accion_js || boton.nombre_funcion.toLowerCase();
                                        
                                        var icono = boton.icono_clase ? `<i class="${boton.icono_clase}"></i>` : '';
                                        
                                        botones += `<button class="btn ${claseBoton} btnFuncion" 
                                                       title="${titulo}" 
                                                       data-id="${row.comprobante_tipo_id}" 
                                                       data-accion="${accionJs}"
                                                       data-confirmable="${boton.es_confirmable || 0}">
                                                    ${icono}
                                                </button>`;
                                    });
                                } else {
                                    botones = '<button class="btn btn-sm btn-secondary" disabled><i class="fas fa-ban"></i></button>';
                                }
                                
                                return `<div class="d-flex align-items-center justify-content-center">${botones}</div>`;
                            }
                        }
                    ],
                    createdRow: function(row, data, dataIndex) {
                        // Cambiar color de fondo según el código estándar
                        var clasesFila = {
                            '100': 'table-secondary', // Eliminado
                            '20': 'table-success',    // Confirmado
                            '10': 'table-warning'     // Borrador
                        };
                        
                        if (clasesFila[data.codigo_estandar]) {
                            $(row).addClass(clasesFila[data.codigo_estandar]);
                        }
                    }
                });

                // Manejador para botón "Agregar"
                $(document).on('click', '#btnNuevo', function(){
                    $('#formComprobanteTipo')[0].reset();
                    $('#comprobante_tipo_id').val('');
                    $('#modalLabel').text('Nuevo Tipo de Comprobante');
                    $('input[type="checkbox"]').prop('checked', false);
                    cargarCombos();
                    var modal = new bootstrap.Modal(document.getElementById('modalComprobanteTipo'));
                    modal.show();
                });

                // Manejador para botones dinámicos
                $(document).on('click', '.btnFuncion', function(){
                    var comprobanteTipoId = $(this).data('id');
                    var accion = $(this).data('accion');
                    var confirmable = $(this).data('confirmable');
                    
                    console.log("Ejecutando acción:", accion, "Para tipo de comprobante:", comprobanteTipoId);
                    
                    // Ejecutar la acción correspondiente
                    switch(accion) {
                        case 'editar':
                            cargarComprobanteTipoParaEditar(comprobanteTipoId);
                            break;
                        case 'visualizar':
                            cargarComprobanteTipoParaEditar(comprobanteTipoId, true);
                            break;
                        case 'agregar':
                            // Esto no debería pasar en botones de fila, pero por si acaso
                            $('#btnNuevo').click();
                            break;
                        default:
                            // Para acciones como habilitar, inhabilitar, etc.
                            if (confirmable == 1) {
                                Swal.fire({
                                    title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)} tipo de comprobante?`,
                                    text: "Esta acción cambiará el estado del tipo de comprobante",
                                    icon: 'question',
                                    showCancelButton: true,
                                    confirmButtonColor: '#3085d6',
                                    cancelButtonColor: '#d33',
                                    confirmButtonText: `Sí, ${accion}`,
                                    cancelButtonText: 'Cancelar'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        ejecutarAccion(comprobanteTipoId, accion);
                                    }
                                });
                            } else {
                                ejecutarAccion(comprobanteTipoId, accion);
                            }
                    }
                });

                // Función para ejecutar cualquier acción del backend
                function ejecutarAccion(comprobanteTipoId, accion) {
                    // Convertir acción JS a nombre de función para el backend
                    var funcionBackend = accion.charAt(0).toUpperCase() + accion.slice(1);
                    
                    // Guardar la página actual antes de la operación
                    var currentPage = tabla.page();
                    
                    $.post('comprobantes_tipos_ajax.php', {
                        accion: 'ejecutar_funcion',
                        comprobante_tipo_id: comprobanteTipoId,
                        funcion_nombre: funcionBackend
                    }, function(res){
                        if (res.success) {
                            // Recargar datos manteniendo la página actual
                            tabla.ajax.reload(function(){
                                // Después de recargar, volver a la página anterior
                                tabla.page(currentPage).draw('page');
                            }, false); // false = mantener página actual
                            
                            Swal.fire({
                                icon: "success",
                                title: `¡${funcionBackend}!`,
                                text: `Tipo de comprobante ${accion.toLowerCase()} correctamente`,
                                showConfirmButton: false,
                                timer: 1500
                            });
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: res.error || `Error al ${accion.toLowerCase()} el tipo de comprobante`,
                                confirmButtonText: "Entendido"
                            });
                        }
                    }, 'json').fail(function(xhr, status, error) {
                        Swal.fire({
                            icon: "error",
                            title: "Error de conexión",
                            text: "Error al comunicarse con el servidor",
                            confirmButtonText: "Entendido"
                        });
                    });
                }

                // Manejar el cambio de estado con el interruptor - CORREGIDO
                $(document).on('change', '.toggle-estado', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var tipoId = $(this).data('tipo-id');
                    var isChecked = $(this).is(':checked');
                    var nuevoEstado = isChecked ? 1 : 0;
                    var accionTexto = nuevoEstado == 1 ? 'activar' : 'desactivar';
                    
                    // Guardar la página actual antes de la operación
                    var currentPage = tabla.page();
                    
                    Swal.fire({
                        title: `¿${nuevoEstado == 1 ? 'Activar' : 'Desactivar'} tipo?`,
                        text: `Está a punto de ${accionTexto} este tipo de comprobante`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: `Sí, ${accionTexto}`,
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.get('comprobantes_tipos_ajax.php', {
                                accion: 'cambiar_estado', 
                                comprobante_tipo_id: tipoId,
                                nuevo_estado: nuevoEstado
                            }, function(res){
                                if(res.resultado){
                                    // Recargar datos manteniendo la página actual
                                    tabla.ajax.reload(function(){
                                        // Después de recargar, volver a la página anterior
                                        tabla.page(currentPage).draw('page');
                                    }, false); // false = mantener página actual
                                    
                                    Swal.fire({
                                        icon: "success",
                                        title: "¡Éxito!",
                                        text: `Tipo ${accionTexto}do correctamente`,
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                } else {
                                    // Revertir el cambio visual si hay error
                                    $(this).prop('checked', !isChecked);
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: res.error || `Error al ${accionTexto} el tipo`
                                    });
                                }
                            }, 'json');
                        } else {
                            // Revertir visualmente si cancela
                            $(this).prop('checked', !isChecked);
                        }
                    });
                });

                // Eliminar tipo - CORREGIDO
                $('#tablaComprobantesTipos tbody').on('click', '.btnEliminar', function(){
                    var data = tabla.row($(this).parents('tr')).data();
                    
                    // Guardar la página actual antes de la operación
                    var currentPage = tabla.page();
                    
                    Swal.fire({
                        title: '¿Eliminar tipo?',
                        text: "Esta acción no se puede deshacer",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.get('comprobantes_tipos_ajax.php', {
                                accion: 'eliminar', 
                                comprobante_tipo_id: data.comprobante_tipo_id
                            }, function(res){
                                if(res.resultado){
                                    // Recargar datos manteniendo la página actual
                                    tabla.ajax.reload(function(){
                                        // Después de recargar, volver a la página anterior
                                        tabla.page(currentPage).draw('page');
                                    }, false); // false = mantener página actual
                                    
                                    Swal.fire({
                                        icon: "success",
                                        title: "¡Éxito!",
                                        text: "Tipo eliminado correctamente",
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                } else {
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: res.error || "Error al eliminar el tipo"
                                    });
                                }
                            }, 'json');
                        }
                    });
                });

                // Función para cargar tipo de comprobante en modal de edición
                function cargarComprobanteTipoParaEditar(comprobanteTipoId, soloLectura = false) {
                    $.get('comprobantes_tipos_ajax.php', {accion: 'obtener', comprobante_tipo_id: comprobanteTipoId}, function(res){
                        if(res && res.comprobante_tipo_id){
                            $('#comprobante_tipo_id').val(res.comprobante_tipo_id);
                            $('#comprobante_tipo').val(res.comprobante_tipo);
                            $('#codigo').val(res.codigo);
                            $('#orden').val(res.orden);
                            $('#letra').val(res.letra);
                            $('#signo').val(res.signo);
                            $('#comentario').val(res.comentario);
                            
                            // Cargar combos y luego seleccionar valores
                            cargarCombos();
                            setTimeout(function(){
                                $('#comprobante_grupo_id').val(res.comprobante_grupo_id);
                                $('#comprobante_fiscal_id').val(res.comprobante_fiscal_id);
                                
                                // Checkboxes
                                $('#impacta_stock').prop('checked', res.impacta_stock == 1);
                                $('#impacta_contabilidad').prop('checked', res.impacta_contabilidad == 1);
                                $('#impacta_ctacte').prop('checked', res.impacta_ctacte == 1);
                            }, 500);
                            
                            if (soloLectura) {
                                $('#modalLabel').text('Visualizar Tipo de Comprobante');
                                $('#comprobante_tipo').prop('readonly', true);
                                $('#codigo').prop('readonly', true);
                                $('#orden').prop('readonly', true);
                                $('#letra').prop('readonly', true);
                                $('#signo').prop('disabled', true);
                                $('#comentario').prop('readonly', true);
                                $('#comprobante_grupo_id').prop('disabled', true);
                                $('#comprobante_fiscal_id').prop('disabled', true);
                                $('#impacta_stock').prop('disabled', true);
                                $('#impacta_contabilidad').prop('disabled', true);
                                $('#impacta_ctacte').prop('disabled', true);
                                $('#btnGuardar').hide();
                            } else {
                                $('#modalLabel').text('Editar Tipo de Comprobante');
                                $('#comprobante_tipo').prop('readonly', false);
                                $('#codigo').prop('readonly', false);
                                $('#orden').prop('readonly', false);
                                $('#letra').prop('readonly', false);
                                $('#signo').prop('disabled', false);
                                $('#comentario').prop('readonly', false);
                                $('#comprobante_grupo_id').prop('disabled', false);
                                $('#comprobante_fiscal_id').prop('disabled', false);
                                $('#impacta_stock').prop('disabled', false);
                                $('#impacta_contabilidad').prop('disabled', false);
                                $('#impacta_ctacte').prop('disabled', false);
                                $('#btnGuardar').show();
                            }
                            
                            var modal = new bootstrap.Modal(document.getElementById('modalComprobanteTipo'));
                            modal.show();
                            
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: "Error al obtener datos del tipo de comprobante",
                                confirmButtonText: "Entendido"
                            });
                        }
                    }, 'json');
                }

                // Guardar formulario
                $('#btnGuardar').click(function(){
                    var form = $('#formComprobanteTipo')[0];
                    
                    // Validación básica
                    if(!form.checkValidity()){
                        form.classList.add('was-validated');
                        return;
                    }
                    
                    var id = $('#comprobante_tipo_id').val();
                    var accion = id ? 'editar' : 'agregar';
                    
                    // Guardar la página actual y posición antes de la operación
                    var currentPage = tabla.page();
                    var currentPosition = tabla.row(':eq(0)').index();
                    
                    var formData = new FormData();
                    formData.append('accion', accion);
                    formData.append('comprobante_tipo_id', id);
                    formData.append('empresa_id', $('#empresa_id').val());
                    formData.append('comprobante_grupo_id', $('#comprobante_grupo_id').val());
                    formData.append('comprobante_fiscal_id', $('#comprobante_fiscal_id').val());
                    formData.append('orden', $('#orden').val());
                    formData.append('comprobante_tipo', $('#comprobante_tipo').val());
                    formData.append('codigo', $('#codigo').val());
                    formData.append('letra', $('#letra').val());
                    formData.append('signo', $('#signo').val());
                    formData.append('comentario', $('#comentario').val());
                    formData.append('impacta_stock', $('#impacta_stock').is(':checked') ? 1 : 0);
                    formData.append('impacta_contabilidad', $('#impacta_contabilidad').is(':checked') ? 1 : 0);
                    formData.append('impacta_ctacte', $('#impacta_ctacte').is(':checked') ? 1 : 0);

                    $.ajax({
                        url: 'comprobantes_tipos_ajax.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(res){
                            if(res.resultado){
                                // Recargar datos manteniendo la página actual
                                tabla.ajax.reload(function(){
                                    // Después de recargar, volver a la página anterior
                                    tabla.page(currentPage).draw('page');
                                    
                                    // Si estamos editando, buscar y resaltar el registro editado
                                    if (id) {
                                        var row = tabla.column(0).data().indexOf(parseInt(id));
                                        if (row !== -1) {
                                            tabla.row(row).select();
                                        }
                                    }
                                }, false); // false = mantener página actual
                                
                                var modalEl = document.getElementById('modalComprobanteTipo');
                                var modal = bootstrap.Modal.getInstance(modalEl);
                                
                                // Remover validación
                                form.classList.remove('was-validated');
                                
                                Swal.fire({                    
                                    icon: "success",
                                    title: "Datos guardados!",
                                    showConfirmButton: false,
                                    timer: 1000
                                });                
                                modal.hide();
                            } else {
                                Swal.fire({
                                    icon: "error",
                                    title: "Error",
                                    text: res.error || "Error al guardar los datos",
                                    confirmButtonText: "Entendido"
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error("Error en AJAX:", error);
                            Swal.fire({
                                icon: "error",
                                title: "Error de conexión",
                                text: "Error al comunicarse con el servidor",
                                confirmButtonText: "Entendido"
                            });
                        }
                    });
                });

                // Inicializar
                cargarBotonAgregar();
                cargarCombos();
            });
            </script>
            <style>
            .table-secondary td {
                color: #6c757d !important;
            }
            
            .badge {
                font-size: 0.75rem;
            }
            </style>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
        </div>
    </div>
</main>

<?php
// Incluir footer
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>