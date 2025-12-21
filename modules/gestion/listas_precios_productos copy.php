<?php
// Configuración de la página
$pageTitle = "Gestión de Precios por Lista";
$currentPage = 'paginas';
$modudo_idx = 2;
$empresa_idx = 2;

// Definir constante para rutas
define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));

// Incluir header
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Precios por Lista</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Precios por Lista</li>
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
                                    <div class="card-header">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <label>Seleccionar Lista de Precios</label>
                                                <select class="form-control" id="selectListaPrecios">
                                                    <option value="">-- Seleccionar lista --</option>
                                                </select>
                                            </div>
                                            <div class="col-md-8 d-flex align-items-end">
                                                <div class="btn-group">
                                                    <button class="btn btn-primary" id="btnNuevoPrecio" disabled>
                                                        <i class="fa fa-plus"></i> Nuevo Precio
                                                    </button>
                                                    <button class="btn btn-success" id="btnAjusteLote" disabled>
                                                        <i class="fa fa-cogs"></i> Ajuste por Lote
                                                    </button>
                                                    <button class="btn btn-info" id="btnVerHistorial" disabled>
                                                        <i class="fa fa-history"></i> Ver Historial
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <table id="tablaPreciosProductos" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Producto</th>
                                                    <th>Precio Unitario</th>
                                                    <th>Última Actualización</th>
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

            <!-- Modal Precio Individual -->
            <div class="modal fade" id="modalPrecioProducto" tabindex="-1" aria-labelledby="modalPrecioLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalPrecioLabel">Precio del Producto</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formPrecioProducto">
                                <input type="hidden" id="lista_precio_producto_id" name="lista_precio_producto_id" />
                                <input type="hidden" id="lista_id_modal" name="lista_id" />
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label>Producto *</label>
                                        <select class="form-control" id="producto_id" name="producto_id" required>
                                            <option value="">-- Seleccionar producto --</option>
                                        </select>
                                        <div class="invalid-feedback">El producto es obligatorio</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Precio Unitario *</label>
                                        <input type="number" class="form-control" id="precio_unitario" 
                                               name="precio_unitario" step="0.01" min="0" required />
                                        <div class="invalid-feedback">El precio es obligatorio</div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="alert alert-info">
                                            <small>
                                                <strong>Información:</strong> Este precio se aplicará en la lista de precios seleccionada.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button id="btnGuardarPrecio" class="btn btn-success">Guardar</button>
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Ajuste por Lote -->
            <div class="modal fade" id="modalAjusteLote" tabindex="-1" aria-labelledby="modalAjusteLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalAjusteLabel">Ajuste de Precios por Lote</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formAjusteLote">
                                <input type="hidden" id="lista_id_ajuste" name="lista_id" />
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label>Tipo de Ajuste *</label>
                                        <select class="form-control" id="tipo_ajuste" name="tipo_ajuste" required>
                                            <option value="lote">Por Lote</option>
                                            <option value="automatico">Automático</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Criterio *</label>
                                        <select class="form-control" id="criterio" name="criterio" required>
                                            <option value="aumento">Aumento</option>
                                            <option value="reduccion">Reducción</option>
                                            <option value="reemplazo">Reemplazo</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Porcentaje (%)</label>
                                        <input type="number" class="form-control" id="porcentaje" 
                                               name="porcentaje" step="0.0001" min="0" max="1000" />
                                        <small class="text-muted">Dejar en 0 si no se usa porcentaje</small>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Monto Fijo</label>
                                        <input type="number" class="form-control" id="monto_fijo" 
                                               name="monto_fijo" step="0.01" min="0" />
                                        <small class="text-muted">Dejar en 0 si no se usa monto fijo</small>
                                    </div>
                                    <div class="col-md-12">
                                        <label>Descripción del Ajuste</label>
                                        <textarea class="form-control" id="descripcion_ajuste" 
                                                  name="descripcion" rows="3" placeholder="Descripción del ajuste aplicado..."></textarea>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="alert alert-warning">
                                            <small>
                                                <strong>Advertencia:</strong> Esta acción modificará los precios de todos los productos en la lista seleccionada.
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button id="btnAplicarAjuste" class="btn btn-success">Aplicar Ajuste</button>
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Historial -->
            <div class="modal fade" id="modalHistorial" tabindex="-1" aria-labelledby="modalHistorialLabel" aria-hidden="true">
                <div class="modal-dialog modal-xl">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalHistorialLabel">Historial de Precios</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <table id="tablaHistorial" class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Producto</th>
                                        <th>Precio Anterior</th>
                                        <th>Tipo Ajuste</th>
                                        <th>Descripción</th>
                                    </tr>
                                </thead>
                            </table>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            $(document).ready(function(){
                var tablaPrecios, tablaHistorial;
                var listaSeleccionada = null;

                // Cargar listas de precios
                function cargarListasPrecios() {
                    $.get('listas_precios_productos_ajax.php', {accion: 'obtener_listas'}, function(res){
                        var options = '<option value="">-- Seleccionar lista --</option>';
                        $.each(res, function(index, lista){
                            options += `<option value="${lista.lista_id}">${lista.nombre} (${lista.tipo})</option>`;
                        });
                        $('#selectListaPrecios').html(options);
                    }, 'json');
                }

                // Cargar productos
                function cargarProductos() {
                    $.get('listas_precios_productos_ajax.php', {accion: 'obtener_productos'}, function(res){
                        var options = '<option value="">-- Seleccionar producto --</option>';
                        $.each(res, function(index, producto){
                            options += `<option value="${producto.producto_id}">${producto.producto_codigo}.${producto.producto_nombre}</option>`;
                        });
                        $('#producto_id').html(options);
                    }, 'json');
                }

                // Configuración de DataTable para precios
                function inicializarTablaPrecios(listaId) {
                    if ($.fn.DataTable.isDataTable('#tablaPreciosProductos')) {
                        tablaPrecios.destroy();
                    }

                    tablaPrecios = $('#tablaPreciosProductos').DataTable({
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
                            url: 'listas_precios_productos_ajax.php',
                            type: 'GET',
                            data: {accion: 'listar', lista_id: listaId},
                            dataSrc: ''
                        },
                        language: {
                            "search": "Buscar:",
                            "searchPlaceholder": "Buscar productos...",
                            "lengthMenu": "Mostrar _MENU_ registros por página",
                            "zeroRecords": "No se encontraron precios",
                            "info": "Mostrando _START_ a _END_ de _TOTAL_ precios",
                            "infoEmpty": "Mostrando 0 a 0 de 0 precios",
                            "infoFiltered": "(filtrado de _MAX_ precios totales)",
                            "paginate": {
                                "first": "Primero",
                                "last": "Último",
                                "next": "Siguiente",
                                "previous": "Anterior"
                            }
                        },
                        columns: [
                            { data: 'lista_precio_producto_id' },
                            { data: 'producto_nombre' },
                            { 
                                data: 'precio_unitario',
                                render: function(data) {
                                    return '$ ' + parseFloat(data).toFixed(2);
                                }
                            },
                            { 
                                data: 'f_actualizacion',
                                render: function(data) {
                                    return data ? new Date(data).toLocaleString() : 'N/A';
                                }
                            },
                            {
                                data: null,
                                orderable: false,
                                searchable: false,
                                className: "text-center",
                                render: function(data){
                                    return `
                                        <div class="d-flex align-items-center justify-content-center gap-2">
                                            <button class="btn btn-sm btn-primary btnEditarPrecio" title="Editar">
                                                <i class="fa fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger btnEliminarPrecio" title="Eliminar">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </div>`;
                                }
                            }
                        ]
                    });
                }

                // Inicializar
                cargarListasPrecios();
                cargarProductos();

                // Evento cambio de lista
                $('#selectListaPrecios').change(function(){
                    listaSeleccionada = $(this).val();
                    if (listaSeleccionada) {
                        $('#btnNuevoPrecio').prop('disabled', false);
                        $('#btnAjusteLote').prop('disabled', false);
                        $('#btnVerHistorial').prop('disabled', false);
                        inicializarTablaPrecios(listaSeleccionada);
                    } else {
                        $('#btnNuevoPrecio').prop('disabled', true);
                        $('#btnAjusteLote').prop('disabled', true);
                        $('#btnVerHistorial').prop('disabled', true);
                        if (tablaPrecios) {
                            tablaPrecios.clear().draw();
                        }
                    }
                });

                // Nuevo precio
                $('#btnNuevoPrecio').click(function(){
                    if (!listaSeleccionada) return;
                    
                    $('#formPrecioProducto')[0].reset();
                    $('#lista_precio_producto_id').val('');
                    $('#lista_id_modal').val(listaSeleccionada);
                    $('#modalPrecioLabel').text('Nuevo Precio');
                    var modal = new bootstrap.Modal(document.getElementById('modalPrecioProducto'));
                    modal.show();
                });

                    // Editar precio
               $('#tablaPreciosProductos tbody').on('click', '.btnEditarPrecio', function(){
                var data = tablaPrecios.row($(this).parents('tr')).data();
                
                if (!data) {
                    console.error('No se pudieron obtener los datos de la fila');
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "No se pudieron cargar los datos para editar"
                    });
                    return;
                }
                
                console.log('Datos para editar:', data);
                
                // DEBUG: Verificar que el modal existe
                var modalElement = document.getElementById('modalPrecioProducto');
                if (!modalElement) {
                    console.error('Modal no encontrado en el DOM');
                    return;
                }
                
                // Llenar el formulario
                $('#lista_precio_producto_id').val(data.lista_precio_producto_id);
                $('#lista_id_modal').val(listaSeleccionada);
                $('#precio_unitario').val(parseFloat(data.precio_unitario).toFixed(2));
                
                // Buscar y seleccionar el producto
                var $productoSelect = $('#producto_id');
                $productoSelect.val(data.producto_id);
                
                // DEBUG: Verificar que los valores se están estableciendo
                console.log('Valores establecidos:', {
                    id: data.lista_precio_producto_id,
                    lista: listaSeleccionada,
                    precio: data.precio_unitario,
                    producto: data.producto_id
                });
                
                // Hacer que el campo producto sea de solo lectura en edición
                $productoSelect.prop('disabled', true);
                
                $('#modalPrecioLabel').text('Editar Precio');
                
                // DEBUG: Verificar que Bootstrap está cargado
                if (typeof bootstrap === 'undefined') {
                    console.error('Bootstrap no está cargado');
                    $('#modalPrecioProducto').modal('show'); // Intentar con jQuery
                } else {
                    var modal = new bootstrap.Modal(document.getElementById('modalPrecioProducto'));
                    modal.show();
                }
            });

                // Restaurar el select cuando se cierra el modal
                $('#modalPrecioProducto').on('hidden.bs.modal', function () {
                    $('#producto_id').prop('disabled', false);
                });
                // Eliminar precio
                $('#tablaPreciosProductos tbody').on('click', '.btnEliminarPrecio', function(){
                    var data = tablaPrecios.row($(this).parents('tr')).data();
                    
                    Swal.fire({
                        title: '¿Eliminar precio?',
                        text: "Esta acción no se puede deshacer",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.get('listas_precios_productos_ajax.php', {
                                accion: 'eliminar', 
                                lista_precio_producto_id: data.lista_precio_producto_id
                            }, function(res){
                                if(res.resultado){
                                    // Guardar página actual
                                    var currentPage = tablaPrecios.page();
                                    tablaPrecios.ajax.reload(function(){
                                        // Restaurar página después de recargar
                                        tablaPrecios.page(currentPage).draw('page');
                                    }, false);
                                    Swal.fire({
                                        icon: "success",
                                        title: "¡Éxito!",
                                        text: "Precio eliminado correctamente",
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                } else {
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: res.error || "Error al eliminar el precio"
                                    });
                                }
                            }, 'json');
                        }
                    });
                });

                // Guardar precio
                $('#btnGuardarPrecio').click(function(){
                    var form = document.getElementById('formPrecioProducto');
                    
                    if (!form.checkValidity()) {
                        form.classList.add('was-validated');
                        return false;
                    }
                    
                    var id = $('#lista_precio_producto_id').val();
                    var accion = id ? 'editar' : 'agregar';
                    var formData = {
                        accion: accion,
                        lista_precio_producto_id: id,
                        lista_id: $('#lista_id_modal').val(),
                        producto_id: $('#producto_id').val(),
                        precio_unitario: $('#precio_unitario').val()
                    };

                    $.ajax({
                        url: 'listas_precios_productos_ajax.php',
                        type: 'POST',
                        data: formData,
                        dataType: 'json',
                        success: function(res) {
                            if(res.resultado) {
                                // Guardar página actual
                                var currentPage = tablaPrecios.page();
                                tablaPrecios.ajax.reload(function(){
                                    // Restaurar página después de recargar
                                    tablaPrecios.page(currentPage).draw('page');
                                }, false);
                                
                                var modal = bootstrap.Modal.getInstance(document.getElementById('modalPrecioProducto'));
                                modal.hide();
                                
                                $('#formPrecioProducto')[0].reset();
                                form.classList.remove('was-validated');
                                
                                Swal.fire({
                                    icon: "success",
                                    title: "¡Éxito!",
                                    text: id ? "Precio actualizado correctamente" : "Precio creado correctamente",
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

                // Ajuste por lote
                $('#btnAjusteLote').click(function(){
                    if (!listaSeleccionada) return;
                    
                    $('#formAjusteLote')[0].reset();
                    $('#lista_id_ajuste').val(listaSeleccionada);
                    var modal = new bootstrap.Modal(document.getElementById('modalAjusteLote'));
                    modal.show();
                });

                // Aplicar ajuste
                $('#btnAplicarAjuste').click(function(){
                    var form = document.getElementById('formAjusteLote');
                    
                    if (!form.checkValidity()) {
                        form.classList.add('was-validated');
                        return false;
                    }
                    
                    var formData = {
                        accion: 'aplicar_ajuste',
                        lista_id: $('#lista_id_ajuste').val(),
                        tipo_ajuste: $('#tipo_ajuste').val(),
                        descripcion: $('#descripcion_ajuste').val(),
                        porcentaje: $('#porcentaje').val(),
                        monto_fijo: $('#monto_fijo').val(),
                        criterio: $('#criterio').val()
                    };

                    $.ajax({
                        url: 'listas_precios_productos_ajax.php',
                        type: 'POST',
                        data: formData,
                        dataType: 'json',
                        success: function(res) {
                            if(res.resultado) {
                                var modal = bootstrap.Modal.getInstance(document.getElementById('modalAjusteLote'));
                                modal.hide();
                                
                                $('#formAjusteLote')[0].reset();
                                form.classList.remove('was-validated');
                                
                                // Recargar tabla de precios
                                if (tablaPrecios) {
                                    var currentPage = tablaPrecios.page();
                                    tablaPrecios.ajax.reload(function(){
                                        tablaPrecios.page(currentPage).draw('page');
                                    }, false);
                                }
                                
                                Swal.fire({
                                    icon: "success",
                                    title: "¡Éxito!",
                                    text: "Ajuste aplicado correctamente",
                                    showConfirmButton: false,
                                    timer: 1500
                                });
                            } else {
                                Swal.fire({
                                    icon: "error",
                                    title: "Error",
                                    text: res.error || "Error al aplicar el ajuste"
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

                // Ver historial
                $('#btnVerHistorial').click(function(){
                    if (!listaSeleccionada) return;
                    
                    // Inicializar tabla de historial
                    if ($.fn.DataTable.isDataTable('#tablaHistorial')) {
                        tablaHistorial.destroy();
                    }

                    tablaHistorial = $('#tablaHistorial').DataTable({
                        ajax: {
                            url: 'listas_precios_productos_ajax.php',
                            type: 'GET',
                            data: {accion: 'obtener_historial', lista_id: listaSeleccionada},
                            dataSrc: ''
                        },
                        language: {
                            "search": "Buscar:",
                            "zeroRecords": "No se encontraron registros",
                            "info": "Mostrando _START_ a _END_ de _TOTAL_ registros",
                        },
                        columns: [
                            { 
                                data: 'f_alta',
                                render: function(data) {
                                    return data ? new Date(data).toLocaleString() : 'N/A';
                                }
                            },
                            { data: 'producto_nombre' },
                            { 
                                data: 'precio_unitario',
                                render: function(data) {
                                    return '$ ' + parseFloat(data).toFixed(2);
                                }
                            },
                            { data: 'tipo_ajuste' },
                            { data: 'descripcion' }
                        ]
                    });

                    var modal = new bootstrap.Modal(document.getElementById('modalHistorial'));
                    modal.show();
                });
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

            <?php
            require_once ROOT_PATH . '/templates/adminlte/footer1.php';
            ?>
            </body>
            </html>