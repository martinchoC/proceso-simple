<?php
// Configuración de la página
$pageTitle = "Gestión de Listas de Precios - Productos";
$currentPage = 'paginas';
$modudo_idx = 2;
$empresa_idx = 2;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
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
                        <li class="breadcrumb-item active">Listas Precios Productos</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <!-- Fila 1: Filtros avanzados + botones -->
                            <div class="row mb-2">
                                <div class="col-md-3">
                                    <label>Marca</label>
                                    <select class="form-control" id="filtroMarca">
                                        <option value="">Todas las marcas</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label>Modelo</label>
                                    <select class="form-control" id="filtroModelo" disabled>
                                        <option value="">Todos los modelos</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label>Submodelo</label>
                                    <select class="form-control" id="filtroSubmodelo" disabled>
                                        <option value="">Todos los submodelos</option>
                                    </select>
                                </div>
                                <div class="col-md-3 d-flex align-items-end justify-content-end">
                                    <button class="btn btn-secondary me-2" id="btnLimpiarFiltros">Limpiar Filtros</button>
                                    <button class="btn btn-primary" id="btnNuevo">Nuevo Precio</button>
                                </div>
                            </div>
                            <!-- Fila 2: Filtros de lista y producto -->
                            <div class="row">
                                <div class="col-md-4">
                                    <label>Lista de Precios</label>
                                    <select class="form-control" id="filtroLista">
                                        <option value="">Todas las listas</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label>Producto</label>
                                    <input type="text" class="form-control" id="filtroProducto" placeholder="Buscar producto...">
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <table id="tablaListasPreciosProductos" class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Lista</th>
                                        <th>Código</th>
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
    </div>

    <!-- Modal -->
    <div class="modal fade" id="modalListaPrecioProducto" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Precio de Producto en Lista</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="formListaPrecioProducto">
                        <input type="hidden" id="lista_precio_producto_id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label>Lista de Precios *</label>
                                <select class="form-control" id="lista_precio_id" required>
                                    <option value="">Seleccionar lista...</option>
                                </select>
                                <div class="invalid-feedback">Seleccione una lista</div>
                            </div>
                            <div class="col-md-6">
                                <label>Producto *</label>
                                <select class="form-control" id="producto_id" required>
                                    <option value="">Seleccionar producto...</option>
                                </select>
                                <div class="invalid-feedback">Seleccione un producto</div>
                            </div>
                            <div class="col-md-6">
                                <label>Precio Unitario *</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="precio_unitario" step="0.01" min="0" required>
                                </div>
                                <div class="invalid-feedback">El precio es obligatorio</div>
                            </div>
                            <div class="col-md-6">
                                <label>Ajuste ID</label>
                                <input type="number" class="form-control" id="ajuste_id" min="0">
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
        const empresa_idx = 2;

        function cargarListasPrecios() {
            $.get('listas_precios_productos_ajax.php', {accion: 'obtener_listas'}, function(res){
                let optionsFiltro = '<option value="">Todas las listas</option>';
                let optionsModal = '<option value="">Seleccionar lista...</option>';
                res.forEach(lista => {
                    optionsFiltro += `<option value="${lista.lista_precio_id}">${lista.nombre}</option>`;
                    optionsModal += `<option value="${lista.lista_precio_id}">${lista.nombre}</option>`;
                });
                $('#filtroLista').html(optionsFiltro);
                $('#lista_precio_id').html(optionsModal);
            });
        }

        function cargarProductos() {
            $.get('listas_precios_productos_ajax.php', {accion: 'obtener_productos'}, function(res){
                let options = '<option value="">Seleccionar producto...</option>';
                res.forEach(p => {
                    options += `<option value="${p.producto_id}">${p.producto_codigo} - ${p.producto_nombre}</option>`;
                });
                $('#producto_id').html(options);
            });
        }

        function cargarMarcas() {
            $.get('productos_ajax.php', {accion: 'obtener_marcas', empresa_idx}, function(marcas){
                let select = $('#filtroMarca');
                select.html('<option value="">Todas las marcas</option>');
                marcas.forEach(m => select.append(`<option value="${m.marca_id}">${m.marca_nombre}</option>`));
            });
        }

        function cargarModelos(marcaId) {
            if (!marcaId) {
                $('#filtroModelo').html('<option value="">Todos los modelos</option>').prop('disabled', true);
                $('#filtroSubmodelo').html('<option value="">Todos los submodelos</option>').prop('disabled', true);
                return;
            }
            $.get('productos_ajax.php', {accion: 'obtener_modelos', empresa_idx, marca_id: marcaId}, function(modelos){
                let select = $('#filtroModelo');
                select.html('<option value="">Todos los modelos</option>');
                if (modelos.length) {
                    select.prop('disabled', false);
                    modelos.forEach(m => select.append(`<option value="${m.modelo_id}">${m.modelo_nombre}</option>`));
                } else select.prop('disabled', true);
                $('#filtroSubmodelo').html('<option value="">Todos los submodelos</option>').prop('disabled', true);
            });
        }

        function cargarSubmodelos(modeloId) {
            if (!modeloId) {
                $('#filtroSubmodelo').html('<option value="">Todos los submodelos</option>').prop('disabled', true);
                return;
            }
            $.get('productos_ajax.php', {accion: 'obtener_submodelos', empresa_idx, modelo_id: modeloId}, function(submodelos){
                let select = $('#filtroSubmodelo');
                select.html('<option value="">Todos los submodelos</option>');
                if (submodelos.length) {
                    select.prop('disabled', false);
                    submodelos.forEach(s => select.append(`<option value="${s.submodelo_id}">${s.submodelo_nombre}</option>`));
                } else select.prop('disabled', false);
            });
        }

        var tabla = $('#tablaListasPreciosProductos').DataTable({
            dom: '<"row"<"col-md-6"l><"col-md-6"fB>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
            buttons: [
                { extend: 'excelHtml5', text: '<i class="fas fa-file-excel"></i> Excel', className: 'btn btn-success btn-sm me-2' },
                { extend: 'pdfHtml5', text: '<i class="fas fa-file-pdf"></i> PDF', className: 'btn btn-danger btn-sm' }
            ],
            ajax: {
                url: 'listas_precios_productos_ajax.php',
                type: 'GET',
                data: function(d) {
                    d.accion = 'listar';
                    d.filtro_lista = $('#filtroLista').val();
                    d.filtro_producto = $('#filtroProducto').val();
                    d.filtro_marca = $('#filtroMarca').val();
                    d.filtro_modelo = $('#filtroModelo').val();
                    d.filtro_submodelo = $('#filtroSubmodelo').val();
                },
                dataSrc: ''
            },
            language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-ES.json' },
            columns: [
                { data: 'lista_precio_producto_id' },
                { data: 'lista_nombre' },
                { data: 'producto_codigo' },
                { data: 'producto_nombre' },
                { data: 'precio_unitario', render: data => '$ ' + parseFloat(data).toFixed(2) },
                { data: 'f_actualizacion', render: data => data ? new Date(data).toLocaleString() : '-' },
                {
                    data: null, orderable: false, className: "text-center",
                    render: data => `<div class="btn-group btn-group-sm">
                                        <button class="btn btn-primary btnEditar"><i class="fa fa-edit"></i></button>
                                        <button class="btn btn-danger btnEliminar"><i class="fa fa-trash"></i></button>
                                    </div>`
                }
            ]
        });

        cargarListasPrecios();
        cargarProductos();
        cargarMarcas();

        $('#filtroMarca').change(function(){ cargarModelos($(this).val()); tabla.ajax.reload(); });
        $('#filtroModelo').change(function(){ cargarSubmodelos($(this).val()); tabla.ajax.reload(); });
        $('#filtroSubmodelo').change(() => tabla.ajax.reload());
        $('#filtroLista, #filtroProducto').change(() => tabla.ajax.reload());

        $('#filtroProducto').on('input', function(){
            clearTimeout($(this).data('timeout'));
            $(this).data('timeout', setTimeout(() => tabla.ajax.reload(), 500));
        });

        $('#btnLimpiarFiltros').click(function(){
            $('#filtroLista, #filtroProducto, #filtroMarca').val('');
            $('#filtroModelo, #filtroSubmodelo').val('').prop('disabled', true);
            tabla.ajax.reload();
        });

        $('#btnNuevo').click(function(){
            $('#formListaPrecioProducto')[0].reset();
            $('#lista_precio_producto_id').val('');
            new bootstrap.Modal(document.getElementById('modalListaPrecioProducto')).show();
        });

        $('#tablaListasPreciosProductos tbody').on('click', '.btnEditar', function(){
            let data = tabla.row($(this).parents('tr')).data();
            $.get('listas_precios_productos_ajax.php', {accion: 'obtener', lista_precio_producto_id: data.lista_precio_producto_id}, function(res){
                if(res){
                    $('#lista_precio_producto_id').val(res.lista_precio_producto_id);
                    $('#lista_precio_id').val(res.lista_precio_id);
                    $('#producto_id').val(res.producto_id);
                    $('#precio_unitario').val(res.precio_unitario);
                    $('#ajuste_id').val(res.ajuste_id);
                    new bootstrap.Modal(document.getElementById('modalListaPrecioProducto')).show();
                } else Swal.fire('Error', 'Error al obtener datos', 'error');
            });
        });

        $('#tablaListasPreciosProductos tbody').on('click', '.btnEliminar', function(){
            let data = tabla.row($(this).parents('tr')).data();
            Swal.fire({
                title: '¿Eliminar precio?', text: 'No se puede deshacer', icon: 'warning',
                showCancelButton: true, confirmButtonColor: '#3085d6', cancelButtonColor: '#d33',
                confirmButtonText: 'Sí, eliminar'
            }).then((result) => {
                if(result.isConfirmed){
                    $.get('listas_precios_productos_ajax.php', {accion: 'eliminar', lista_precio_producto_id: data.lista_precio_producto_id}, function(res){
                        if(res.resultado){
                            let page = tabla.page();
                            tabla.ajax.reload(() => tabla.page(page).draw('page'));
                            Swal.fire('Eliminado', '', 'success');
                        } else Swal.fire('Error', res.error || 'No se pudo eliminar', 'error');
                    });
                }
            });
        });

        $('#btnGuardar').click(function(){
            let form = document.getElementById('formListaPrecioProducto');
            if(!form.checkValidity()) return form.classList.add('was-validated');
            let id = $('#lista_precio_producto_id').val();
            $.post('listas_precios_productos_ajax.php', {
                accion: id ? 'editar' : 'agregar',
                lista_precio_producto_id: id,
                lista_precio_id: $('#lista_precio_id').val(),
                producto_id: $('#producto_id').val(),
                precio_unitario: $('#precio_unitario').val(),
                ajuste_id: $('#ajuste_id').val() || null
            }, function(res){
                if(res.resultado){
                    let page = tabla.page();
                    tabla.ajax.reload(() => tabla.page(page).draw('page'));
                    bootstrap.Modal.getInstance(document.getElementById('modalListaPrecioProducto')).hide();
                    Swal.fire('Guardado', '', 'success');
                } else Swal.fire('Error', res.error || 'Error al guardar', 'error');
            });
        });
    });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>