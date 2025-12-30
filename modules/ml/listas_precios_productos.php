<?php
// Configuración de la página
$pageTitle = "Gestión de Listas de Precios - Productos";
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
                <div class="col-sm-6"><h3 class="mb-0">Listas de Precios - Productos</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Listas Precios Productos</li>
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
                                                <label>Filtrar por Lista de Precios</label>
                                                <select class="form-control" id="filtroLista">
                                                    <option value="">Todas las listas</option>
                                                </select>
                                            </div>
                                            <div class="col-md-4">
                                                <label>Filtrar por Producto</label>
                                                <input type="text" class="form-control" id="filtroProducto" placeholder="Buscar producto...">
                                            </div>
                                            <div class="col-md-4 d-flex align-items-end">
                                                <button class="btn btn-secondary me-2" id="btnLimpiarFiltros">Limpiar Filtros</button>
                                                <button class="btn btn-primary" id="btnNuevo">Nuevo Precio</button>
                                            </div>
                                        </div>
                                    </div>                               
                                    <div class="card-body">
                                        <table id="tablaListasPreciosProductos" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Lista de Precios</th>
                                                    <th>Código Producto</th>
                                                    <th>Nombre Producto</th>
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

            <!-- Modal -->
            <div class="modal fade" id="modalListaPrecioProducto" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Precio de Producto en Lista</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formListaPrecioProducto">
                                <input type="hidden" id="lista_precio_producto_id" name="lista_precio_producto_id" />
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label>Lista de Precios *</label>
                                        <select class="form-control" id="lista_id" name="lista_id" required>
                                            <option value="">Seleccionar lista...</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione una lista de precios</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Producto *</label>
                                        <select class="form-control" id="producto_id" name="producto_id" required>
                                            <option value="">Seleccionar producto...</option>
                                        </select>
                                        <div class="invalid-feedback">Seleccione un producto</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Precio Unitario *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="number" class="form-control" id="precio_unitario" 
                                                   name="precio_unitario" step="0.01" min="0" required />
                                        </div>
                                        <div class="invalid-feedback">El precio unitario es obligatorio</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Ajuste ID</label>
                                        <input type="number" class="form-control" id="ajuste_id" 
                                               name="ajuste_id" min="0" />
                                    </div>
                                    <div class="col-md-12">
                                        <div class="alert alert-info">
                                            <small>
                                                <strong>Información:</strong> Asigne precios específicos a productos dentro de las listas de precios.
                                            </small>
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
            $(document).ready(function(){
                // Cargar listas de precios para filtro y modal
                function cargarListasPrecios() {
                    $.get('listas_precios_productos_ajax.php', {accion: 'obtener_listas'}, function(res){
                        var optionsFiltro = '<option value="">Todas las listas</option>';
                        var optionsModal = '<option value="">Seleccionar lista...</option>';
                        
                        res.forEach(function(lista){
                            optionsFiltro += `<option value="${lista.lista_id}">${lista.nombre}</option>`;
                            optionsModal += `<option value="${lista.lista_id}">${lista.nombre}</option>`;
                        });
                        
                        $('#filtroLista').html(optionsFiltro);
                        $('#lista_id').html(optionsModal);
                    }, 'json');
                }

                // Cargar productos para modal
                function cargarProductos() {
                    $.get('listas_precios_productos_ajax.php', {accion: 'obtener_productos'}, function(res){
                        var options = '<option value="">Seleccionar producto...</option>';
                        
                        res.forEach(function(producto){
                            options += `<option value="${producto.producto_id}">${producto.producto_codigo} - ${producto.producto_nombre}</option>`;
                        });
                        
                        $('#producto_id').html(options);
                    }, 'json');
                }

                // Configuración de DataTable
                var tabla = $('#tablaListasPreciosProductos').DataTable({
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
                        data: function(d) {
                            d.accion = 'listar';
                            d.filtro_lista = $('#filtroLista').val();
                            d.filtro_producto = $('#filtroProducto').val();
                        },
                        dataSrc: ''
                    },
                    language: {
                        "search": "Buscar:",
                        "searchPlaceholder": "Buscar precios...",
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
                        { data: 'lista_nombre' },
                        { data: 'producto_codigo' },
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
                                return data ? new Date(data).toLocaleString() : '-';
                            }
                        },
                        {
                            data: null,
                            orderable: false,
                            searchable: false,
                            className: "text-center",
                            render: function(data){
                                return `<div class="d-flex align-items-center justify-content-center gap-2">
                                            <button class="btn btn-sm btn-primary btnEditar" title="Editar">
                                                <i class="fa fa-edit"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger btnEliminar" title="Eliminar">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </div>`;
                            }
                        }
                    ]
                });

                // Cargar datos iniciales
                cargarListasPrecios();
                cargarProductos();

                // Aplicar filtros
                $('#filtroLista, #filtroProducto').change(function(){
                    tabla.ajax.reload();
                });

                $('#filtroProducto').on('input', function(){
                    clearTimeout($(this).data('timeout'));
                    $(this).data('timeout', setTimeout(function(){
                        tabla.ajax.reload();
                    }, 500));
                });

                // Limpiar filtros
                $('#btnLimpiarFiltros').click(function(){
                    $('#filtroLista').val('');
                    $('#filtroProducto').val('');
                    tabla.ajax.reload();
                });

                // Nuevo registro
                $('#btnNuevo').click(function(){
                    $('#formListaPrecioProducto')[0].reset();
                    $('#lista_precio_producto_id').val('');
                    $('#modalLabel').text('Nuevo Precio de Producto');
                    var modal = new bootstrap.Modal(document.getElementById('modalListaPrecioProducto'));
                    modal.show();
                });

                // Editar registro
                $('#tablaListasPreciosProductos tbody').on('click', '.btnEditar', function(){
                    var data = tabla.row($(this).parents('tr')).data();
                    
                    $.get('listas_precios_productos_ajax.php', {
                        accion: 'obtener', 
                        lista_precio_producto_id: data.lista_precio_producto_id
                    }, function(res){
                        if(res){
                            $('#lista_precio_producto_id').val(res.lista_precio_producto_id);
                            $('#lista_id').val(res.lista_id);
                            $('#producto_id').val(res.producto_id);
                            $('#precio_unitario').val(res.precio_unitario);
                            $('#ajuste_id').val(res.ajuste_id);
                            
                            $('#modalLabel').text('Editar Precio de Producto');
                            var modal = new bootstrap.Modal(document.getElementById('modalListaPrecioProducto'));
                            modal.show();
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: "Error al obtener datos del precio"
                            });
                        }
                    }, 'json');
                });

                // Eliminar registro
                $('#tablaListasPreciosProductos tbody').on('click', '.btnEliminar', function(){
                    var data = tabla.row($(this).parents('tr')).data();
                    
                    Swal.fire({
                        title: '¿Eliminar precio de producto?',
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
                                    var currentPage = tabla.page();
                                    tabla.ajax.reload(function(){
                                        // Restaurar página después de recargar
                                        tabla.page(currentPage).draw('page');
                                    }, false);
                                    
                                    Swal.fire({
                                        icon: "success",
                                        title: "¡Éxito!",
                                        text: "Precio de producto eliminado correctamente",
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                } else {
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: res.error || "Error al eliminar el precio de producto"
                                    });
                                }
                            }, 'json');
                        }
                    });
                });

                // Guardar registro
                $('#btnGuardar').click(function(){
                    var form = document.getElementById('formListaPrecioProducto');
                    
                    if (!form.checkValidity()) {
                        form.classList.add('was-validated');
                        return false;
                    }
                    
                    var id = $('#lista_precio_producto_id').val();
                    var accion = id ? 'editar' : 'agregar';
                    var formData = {
                        accion: accion,
                        lista_precio_producto_id: id,
                        lista_id: $('#lista_id').val(),
                        producto_id: $('#producto_id').val(),
                        precio_unitario: $('#precio_unitario').val(),
                        ajuste_id: $('#ajuste_id').val() || null
                    };

                    $.ajax({
                        url: 'listas_precios_productos_ajax.php',
                        type: 'POST',
                        data: formData,
                        dataType: 'json',
                        success: function(res) {
                            if(res.resultado) {
                                // Guardar página actual
                                var currentPage = tabla.page();
                                tabla.ajax.reload(function(){
                                    // Restaurar página después de recargar
                                    tabla.page(currentPage).draw('page');
                                }, false);
                                
                                var modal = bootstrap.Modal.getInstance(document.getElementById('modalListaPrecioProducto'));
                                modal.hide();
                                
                                $('#formListaPrecioProducto')[0].reset();
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
            });
            </script>
            <style>
            .table td, .table th {
                vertical-align: middle;
            }
            
            .input-group-text {
                background-color: #e9ecef;
                border: 1px solid #ced4da;
            }
            </style>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

            <?php
            require_once ROOT_PATH . '/templates/adminlte/footer1.php';
            ?>
            </body>
            </html>