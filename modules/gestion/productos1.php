<?php
// Configuración de la página
$pageTitle = "Gestión de Productos";
$currentPage = 'productos';
$modudo_idx = 2;//hola
$pagina_idx = 38; // ✅ ID de página para ubicaciones de sucursales

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Gestión de Productos</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Productos</li>
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
                                        <button class="btn btn-primary mb-3" id="btnNuevo">Nuevo Producto</button>
                                        <table id="tablaProductos" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Código</th>
                                                    <th>Nombre</th>
                                                    <th>Descripcion</th>
                                                    <th>Categoría</th>
                                                    <th>Material</th>
                                                    <th>Color</th>
                                                    <th>Unidad Medida</th>
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
                <div class="modal fade" id="modalProducto" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="modalLabel">Producto</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                            </div>
                            <div class="modal-body">
                                <ul class="nav nav-tabs mt-4" id="productoTabs" role="tablist">
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab">General</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="compatibilidad-tab" data-bs-toggle="tab" data-bs-target="#compatibilidad" type="button" role="tab">Compatibilidad</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="ubicaciones-tab" data-bs-toggle="tab" data-bs-target="#ubicaciones" type="button" role="tab">Ubicaciones</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="proveedores-tab" data-bs-toggle="tab" data-bs-target="#proveedores" type="button" role="tab">Proveedores</button>
                                    </li>
                                    <li class="nav-item" role="presentation">
                                        <button class="nav-link" id="fotos-tab" data-bs-toggle="tab" data-bs-target="#fotos" type="button" role="tab">Fotos</button>
                                    </li>
                                </ul>
                                
                                <div class="tab-content" id="productoTabContent">
                                    <!-- Pestaña General -->
                                    <div class="tab-pane fade show active" id="general" role="tabpanel">
                                        <form id="formProducto">
                                            <input type="hidden" id="producto_id" name="producto_id" />
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <label>Código *</label>
                                                    <input type="text" class="form-control" id="producto_codigo" name="producto_codigo" required/>
                                                    
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Nombre *</label>
                                                    <input type="text" class="form-control" id="producto_nombre" name="producto_nombre" required/>
                                                    
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Categoría *</label>
                                                    <select class="form-control" id="producto_categoria_id" name="producto_categoria_id" required>
                                                        <option value="">Seleccione una categoría</option>
                                                        <!-- Las opciones se cargarán dinámicamente -->
                                                    </select>
                                                    
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Unidad de Medida</label>
                                                    <select class="form-control select2" id="unidad_medida_id" name="unidad_medida_id" style="width: 100%;">
                                                        <option value="">Seleccione una unidad</option>
                                                        <!-- Las opciones se cargarán dinámicamente -->
                                                    </select>
                                                </div>
                                                <div class="col-md-12">
                                                    <label>Descripción</label>
                                                    <textarea class="form-control" id="producto_descripcion" name="producto_descripcion" rows="3"></textarea>
                                                </div>
                                                <div class="col-md-4">
                                                    <label>Lado</label>
                                                    <input type="text" class="form-control" id="lado" name="lado"/>
                                                </div>
                                                <div class="col-md-4">
                                                    <label>Material</label>
                                                    <input type="text" class="form-control" id="material" name="material"/>
                                                </div>
                                                <div class="col-md-4">
                                                    <label>Color</label>
                                                    <input type="text" class="form-control" id="color" name="color"/>
                                                </div>
                                                <div class="col-md-4">
                                                    <label>Peso (kg)</label>
                                                    <input type="number" step="0.01" class="form-control" id="peso" name="peso"/>
                                                </div>
                                                <div class="col-md-4">
                                                    <label>Dimensiones</label>
                                                    <input type="text" class="form-control" id="dimensiones" name="dimensiones" placeholder="Ej: 10x20x30"/>
                                                </div>
                                                <div class="col-md-4">
                                                    <label>Garantía</label>
                                                    <input type="text" class="form-control" id="garantia" name="garantia"/>
                                                </div>
                                                <div class="col-md-6">
                                                    <label>Estado</label>
                                                    <select class="form-control" id="estado_registro_id" name="estado_registro_id">
                                                        <option value="1">Activo</option>
                                                        <option value="0">Inactivo</option>
                                                    </select>
                                                </div>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                                 <!-- Pestaña Compatibilidad -->
                                <div class="tab-pane fade" id="compatibilidad" role="tabpanel">
                                    <div class="mt-3">
                                        <h5>Compatibilidad del Producto</h5>
                                        
                                        <!-- Formulario para agregar compatibilidad -->
                                        <form id="formCompatibilidad" class="row g-3 mb-3">
                                            <input type="hidden" id="compatibilidad_producto_id" name="producto_id" />
                                            
                                            <div class="col-md-3">
                                                <label>Marca *</label>
                                                <select class="form-control" id="marca_id" name="marca_id">
                                                    <option value="">Seleccione marca</option>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-3">
                                                <label>Modelo *</label>
                                                <select class="form-control" id="modelo_id" name="modelo_id" disabled>
                                                    <option value="">Seleccione modelo</option>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-3">
                                                <label>Submodelo</label>
                                                <select class="form-control" id="submodelo_id" name="submodelo_id" disabled>
                                                    <option value="">Seleccione submodelo</option>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-2">
                                                <label>Año Desde *</label>
                                                <input type="number" class="form-control" id="anio_desde" name="anio_desde" min="1900" max="2100"/>
                                            </div>
                                            
                                            <div class="col-md-2">
                                                <label>Año Hasta</label>
                                                <input type="number" class="form-control" id="anio_hasta" name="anio_hasta" min="1900" max="2100" />
                                            </div>
                                            
                                            <div class="col-md-2 d-flex align-items-end">
                                                <button type="submit" class="btn btn-success">Agregar</button>
                                            </div>
                                        </form>
                                        
                                        <!-- Tabla de compatibilidad -->
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped" id="tablaCompatibilidad">
                                                <thead>
                                                    <tr>
                                                        <th>Marca</th>
                                                        <th>Modelo</th>
                                                        <th>Submodelo</th>
                                                        <th>Año Desde</th>
                                                        <th>Año Hasta</th>
                                                        <th>Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <!-- Los datos se cargarán dinámicamente -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Pestaña Ubicaciones -->
                                <div class="tab-pane fade" id="ubicaciones" role="tabpanel">
                                    <div class="mt-3">
                                        <h5>Ubicaciones del Producto en Sucursales</h5>
                                        
                                        <!-- Formulario para agregar ubicación -->
                                        <form id="formUbicacion" class="row g-3 mb-3">
                                            <input type="hidden" id="ubicacion_producto_id" name="producto_id" />
                                            
                                            <div class="col-md-3">
                                                <label>Sucursal *</label>
                                                <select class="form-control" id="sucursal_id" name="sucursal_id">
                                                    <option value="">Seleccione sucursal</option>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-3">
                                                <label>Ubicación *</label>
                                                <select class="form-control" id="sucursal_ubicacion_id" name="sucursal_ubicacion_id" disabled>
                                                    <option value="">Seleccione ubicación</option>
                                                </select>
                                            </div>
                                            
                                            <div class="col-md-2">
                                                <label>Stock Mínimo</label>
                                                <input type="number" class="form-control" id="stock_minimo" name="stock_minimo" min="0" value="0"/>
                                            </div>
                                            
                                            <div class="col-md-2">
                                                <label>Stock Máximo</label>
                                                <input type="number" class="form-control" id="stock_maximo" name="stock_maximo" min="0"/>
                                            </div>
                                            
                                            
                                            <div class="col-md-2 d-flex align-items-end">
                                                <button type="submit" class="btn btn-success">Agregar</button>
                                            </div>
                                        </form>
                                        
                                        <!-- Tabla de ubicaciones -->
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped" id="tablaUbicaciones">
                                                <thead>
                                                    <tr>
                                                        <th>Sucursal</th>
                                                        <th>Ubicación</th>
                                                        <th>Descripción</th>
                                                        <th>Stock Mínimo</th>
                                                        <th>Stock Máximo</th>
                                                        <th>Acciones</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <!-- Los datos se cargarán dinámicamente -->
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Pestañas adicionales (vacías por ahora) -->
                                <div class="tab-pane fade" id="proveedores" role="tabpanel">
                                    <!-- Contenido de proveedores -->
                                </div>
                                
                                <div class="tab-pane fade" id="fotos" role="tabpanel">
                                    <!-- Contenido de fotos -->
                                </div>
                            </div>
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
                // Cargar opciones de categorías y unidades de medida
               function cargarOpcionesSelect() {
                    $.get('productos_ajax.php', {accion: 'obtener_opciones'}, function(res){
                        console.log('Opciones cargadas:', res); // Para depuración
                        
                        if(res.categorias) {
                            $('#producto_categoria_id').html('<option value="">Seleccione una categoría</option>');
                            res.categorias.forEach(function(categoria) {
                                $('#producto_categoria_id').append('<option value="'+categoria.id+'">'+categoria.nombre+'</option>');
                            });
                        }
                        
                        if(res.unidades_medida) {
                            $('#unidad_medida_id').html('<option value="">Seleccione una unidad</option>');
                            // Las unidades ya vienen como array de objetos
                            res.unidades_medida.forEach(function(unidad) {
                                $('#unidad_medida_id').append('<option value="'+unidad.id+'">'+unidad.nombre+'</option>');
                            });
                            
                            // Inicializar Select2 después de cargar opciones
                            $('#unidad_medida_id').select2({
                                placeholder: "Seleccione una unidad",
                                allowClear: true,
                                dropdownParent: $('#modalProducto')
                            });
                        }
                    }, 'json').fail(function(xhr, status, error) {
                        console.error('Error al cargar opciones:', error);
                    });
                }
                
                cargarOpcionesSelect();
                
                
                function cargarOpcionesCompatibilidad() {
                    $.get('productos_ajax.php', {accion: 'obtener_opciones_compatibilidad'}, function(res){
                        if(res.marcas) {
                            $('#marca_id').html('<option value="">Seleccione marca</option>');
                            $.each(res.marcas, function(key, value) {
                                $('#marca_id').append('<option value="'+key+'">'+value+'</option>');
                            });
                        }
                    }, 'json');
                }

                function cargarCompatibilidad(producto_id) {
                    $.get('productos_ajax.php', {accion: 'obtener_compatibilidad', producto_id: producto_id}, function(res){
                        $('#tablaCompatibilidad tbody').empty();
                        
                        if(res.length > 0) {
                            $.each(res, function(index, item) {
                                var modeloTexto = item.modelo_nombre || 'Toda la marca';
                                var submodeloTexto = item.submodelo_nombre || '-';
                                
                                var row = `<tr data-compatibilidad-id="${item.compatibilidad_id}">
                                    <td>${item.marca_nombre || '-'}</td>
                                    <td>${modeloTexto}</td>
                                    <td>${submodeloTexto}</td>
                                    <td>${item.anio_desde}</td>
                                    <td>${item.anio_hasta || 'Actual'}</td>
                                    <td>
                                        <button class="btn btn-sm btn-danger btnEliminarCompatibilidad" title="Eliminar">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>`;
                                $('#tablaCompatibilidad tbody').append(row);
                            });
                        } else {
                            $('#tablaCompatibilidad tbody').append('<tr><td colspan="6" class="text-center">No hay compatibilidades registradas</td></tr>');
                        }
                    }, 'json');
                }
                function cargarSucursales() {
                    $.get('productos_ajax.php', {accion: 'obtener_sucursales'}, function(res){
                        if(res.sucursales) {
                            $('#sucursal_id').html('<option value="">Seleccione sucursal</option>');
                            $.each(res.sucursales, function(key, value) {
                                $('#sucursal_id').append('<option value="'+key+'">'+value+'</option>');
                            });
                        }
                    }, 'json');
                }

                function cargarUbicacionesSucursal(sucursal_id) {
                    if(sucursal_id) {
                        $.get('productos_ajax.php', {accion: 'obtener_ubicaciones_sucursal', sucursal_id: sucursal_id}, function(res){
                            $('#sucursal_ubicacion_id').html('<option value="">Seleccione ubicación</option>').prop('disabled', false);
                            if(res.ubicaciones) {
                                // Convertir el objeto en un array de pares [clave, valor]
                                const ubicacionesArray = Object.entries(res.ubicaciones);
                                // Ordenar el array por el valor (nombre de la ubicación)
                                ubicacionesArray.sort((a, b) => a[1].localeCompare(b[1]));
                                // Iterar sobre el array ordenado
                                ubicacionesArray.forEach(([key, value]) => {
                                    $('#sucursal_ubicacion_id').append('<option value="'+key+'">'+value+'</option>');
                                });
                            }
                        }, 'json');
                    } else {
                        $('#sucursal_ubicacion_id').html('<option value="">Seleccione ubicación</option>').prop('disabled', true);
                    }
                }


                function cargarUbicacionesProducto(producto_id) {
                    if (!producto_id) return;
                    
                    $.get('productos_ajax.php', {accion: 'obtener_ubicaciones_producto', producto_id: producto_id}, function(res){
                        $('#tablaUbicaciones tbody').empty();
                        
                        if(res.length > 0) {
                            $.each(res, function(index, item) {
                                var row = `<tr data-ubicacion-id="${item.producto_ubicacion_id}">
                                    <td>${item.sucursal_nombre || '-'}</td>
                                    <td>${item.ubicacion_nombre || '-'}</td>
                                    <td>${item.ubicacion_descripcion || '-'}</td>
                                    <td>${item.stock_minimo || '0'}</td>
                                    <td>${item.stock_maximo || '-'}</td>
                                    <td>
                                        <button class="btn btn-sm btn-danger btnEliminarUbicacion" title="Eliminar">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>`;
                                $('#tablaUbicaciones tbody').append(row);
                            });
                        } else {
                            $('#tablaUbicaciones tbody').append('<tr><td colspan="6" class="text-center">No hay ubicaciones registradas</td></tr>');
                        }
                    }, 'json');
                }

                // Evento para cargar ubicaciones cuando cambia la sucursal
                $('#sucursal_id').change(function(){
                    var sucursal_id = $(this).val();
                    cargarUbicacionesSucursal(sucursal_id);
                });
                // Evento para el formulario de ubicación
                $('#formUbicacion').on('submit', function(e){
                    e.preventDefault();
                    
                    // Validar campos obligatorios
                    const sucursalId = $('#sucursal_id').val();
                    const ubicacionId = $('#sucursal_ubicacion_id').val();
                    const productoId = $('#ubicacion_producto_id').val();
                    
                    if (!sucursalId || !ubicacionId || !productoId) {
                        Swal.fire({
                            icon: "error",
                            title: "Error",
                            text: "Todos los campos obligatorios deben estar completos"
                        });
                        return false;
                    }
                    
                    // Preparar datos del formulario
                    const formData = $(this).serialize();
                    
                    // Mostrar indicador de carga
                    const submitBtn = $(this).find('button[type="submit"]');
                    const originalText = submitBtn.html();
                    submitBtn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Procesando...');
                    
                    // Enviar solicitud AJAX
                    $.ajax({
                        url: 'productos_ajax.php?accion=agregar_ubicacion_producto',
                        type: 'POST',
                        data: formData,
                        dataType: 'json',
                        success: function(res) {
                            // Restaurar botón
                            submitBtn.prop('disabled', false).html(originalText);
                            
                            if(res.resultado) {
                                // Recargar la tabla de ubicaciones
                                cargarUbicacionesProducto(productoId);
                                
                                // Limpiar formulario
                                $('#formUbicacion')[0].reset();
                                $('#sucursal_ubicacion_id').prop('disabled', true).html('<option value="">Seleccione ubicación</option>');
                                
                                // Mostrar mensaje de éxito
                                Swal.fire({
                                    icon: "success",
                                    title: "¡Éxito!",
                                    text: "Ubicación agregada correctamente",
                                    showConfirmButton: false,
                                    timer: 1500
                                });
                            } else {
                                // Mostrar error
                                Swal.fire({
                                    icon: "error",
                                    title: "Error",
                                    text: res.error || "Error al agregar ubicación"
                                });
                            }
                        },
                        error: function(xhr, status, error) {
                            // Restaurar botón
                            submitBtn.prop('disabled', false).html(originalText);
                            
                            // Mostrar error de conexión
                            Swal.fire({
                                icon: "error",
                                title: "Error de conexión",
                                text: "No se pudo conectar con el servidor. Intente nuevamente."
                            });
                            console.error("Error en la solicitud AJAX:", error);
                        }
                    });
                });
                // Eventos para los selects dependientes
                
                $('#marca_id').change(function(){
                    var marca_id = $(this).val();
                    if(marca_id) {
                        $.get('productos_ajax.php', {accion: 'obtener_modelos', marca_id: marca_id}, function(res){
                            $('#modelo_id').html('<option value="">Seleccione modelo</option>').prop('disabled', false);
                            $.each(res, function(key, value) {
                                $('#modelo_id').append('<option value="'+key+'">'+value+'</option>');
                            });
                        }, 'json');
                    } else {
                        $('#modelo_id').html('<option value="">Seleccione modelo</option>').prop('disabled', true);
                        $('#submodelo_id').html('<option value="">Seleccione submodelo</option>').prop('disabled', true);
                    }
                });

                $('#modelo_id').change(function(){
                    var modelo_id = $(this).val();
                    if(modelo_id) {
                        $.get('productos_ajax.php', {accion: 'obtener_submodelos', modelo_id: modelo_id}, function(res){
                            $('#submodelo_id').html('<option value="">Seleccione submodelo</option>').prop('disabled', false);
                            $.each(res, function(key, value) {
                                $('#submodelo_id').append('<option value="'+key+'">'+value+'</option>');
                            });
                        }, 'json');
                    } else {
                        $('#submodelo_id').html('<option value="">Seleccione submodelo</option>').prop('disabled', true);
                    }
                });
                // Eventos para los selects dependientes de sucursales
                    $('#sucursal_id').change(function(){
                        var sucursal_id = $(this).val();
                        cargarUbicacionesSucursal(sucursal_id);
                    });

                    // Agregar ubicación
                        $('#formUbicacion').submit(function(e){
                            e.preventDefault();
                            
                            var formData = $(this).serialize();
                            
                            $.ajax({
                                url: 'productos_ajax.php?accion=agregar_ubicacion_producto',
                                type: 'POST',
                                data: formData,
                                dataType: 'json',
                                success: function(res) {
                                    if(res.resultado) {
                                        cargarUbicacionesProducto($('#ubicacion_producto_id').val());
                                        $('#formUbicacion')[0].reset();
                                        $('#sucursal_ubicacion_id').prop('disabled', true).html('<option value="">Seleccione ubicación</option>');
                                    } else {
                                        Swal.fire({
                                            icon: "error",
                                            title: "Error",
                                            text: res.error || "Error al agregar ubicación"
                                        });
                                    }
                                }
                            });
                        });

                        // Eliminar ubicación
                        $(document).on('click', '.btnEliminarUbicacion', function(){
                            var producto_ubicacion_id = $(this).closest('tr').data('ubicacion-id');
                            var producto_id = $('#ubicacion_producto_id').val();
                            
                            Swal.fire({
                                title: '¿Eliminar ubicación?',
                                text: "Esta acción no se puede deshacer",
                                icon: 'warning',
                                showCancelButton: true,
                                confirmButtonColor: '#3085d6',
                                cancelButtonColor: '#d33',
                                confirmButtonText: 'Sí, eliminar',
                                cancelButtonText: 'Cancelar'
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    $.get('productos_ajax.php', {
                                        accion: 'eliminar_ubicacion_producto', 
                                        producto_ubicacion_id: producto_ubicacion_id
                                    }, function(res){
                                        if(res.resultado){
                                            cargarUbicacionesProducto(producto_id);
                                            Swal.fire({
                                                icon: "success",
                                                title: "¡Éxito!",
                                                text: "Ubicación eliminada correctamente",
                                                showConfirmButton: false,
                                                timer: 1500
                                            });
                                        } else {
                                            Swal.fire({
                                                icon: "error",
                                                title: "Error",
                                                text: "Error al eliminar la ubicación"
                                            });
                                        }
                                    }, 'json');
                                }
                            });
                        });
                // Agregar compatibilidad
                $('#formCompatibilidad').submit(function(e){
                    e.preventDefault();
                    
                    var formData = $(this).serialize();
                    
                    $.ajax({
                        url: 'productos_ajax.php?accion=agregar_compatibilidad',
                        type: 'POST',
                        data: formData,
                        dataType: 'json',
                        success: function(res) {
                            if(res.resultado) {
                                cargarCompatibilidad($('#compatibilidad_producto_id').val());
                                $('#formCompatibilidad')[0].reset();
                                $('#modelo_id, #submodelo_id').prop('disabled', true).html('<option value="">Seleccione...</option>');
                            } else {
                                Swal.fire({
                                    icon: "error",
                                    title: "Error",
                                    text: res.error || "Error al agregar compatibilidad"
                                });
                            }
                        }
                    });
                });

                // Eliminar compatibilidad
                $(document).on('click', '.btnEliminarCompatibilidad', function(){
                    var compatibilidad_id = $(this).closest('tr').data('compatibilidad-id');
                    var producto_id = $('#compatibilidad_producto_id').val();
                    
                    Swal.fire({
                        title: '¿Eliminar compatibilidad?',
                        text: "Esta acción no se puede deshacer",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.get('productos_ajax.php', {
                                accion: 'eliminar_compatibilidad', 
                                compatibilidad_id: compatibilidad_id
                            }, function(res){
                                if(res.resultado){
                                    cargarCompatibilidad(producto_id);
                                    Swal.fire({
                                        icon: "success",
                                        title: "¡Éxito!",
                                        text: "Compatibilidad eliminada correctamente",
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                } else {
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: "Error al eliminar la compatibilidad"
                                    });
                                }
                            }, 'json');
                        }
                    });
                });

                
                // Configuración de DataTable
                var tabla = $('#tablaProductos').DataTable({
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
                        url: 'productos_ajax.php',
                        type: 'GET',
                        data: {accion: 'listar'},
                        dataSrc: ''
                    },
                    language: {
                        "search": "Buscar:",
                        "searchPlaceholder": "Buscar productos...",
                        "lengthMenu": "Mostrar _MENU_ registros por página",
                        "zeroRecords": "No se encontraron productos",
                        "info": "Mostrando _START_ a _END_ de _TOTAL_ productos",
                        "infoEmpty": "Mostrando 0 a 0 de 0 productos",
                        "infoFiltered": "(filtrado de _MAX_ productos totales)",
                        "paginate": {
                            "first": "Primero",
                            "last": "Último",
                            "next": "Siguiente",
                            "previous": "Anterior"
                        }
                    },
                    columns: [
                        { data: 'producto_id' },
                        { data: 'producto_codigo' },
                        { data: 'producto_nombre' },
                        { data: 'producto_descripcion' },
                        { 
                            data: 'categoria_nombre',
                            render: function(data) {
                                return data || '-';
                            }
                        },
                        { 
                            data: 'material',
                            render: function(data) {
                                return data || '-';
                            }
                        },
                        { 
                            data: 'color',
                            render: function(data) {
                                return data || '-';
                            }
                        },
                        {
                            data: null,
                            render: function(data, type, row) {
                                if (row.unidad_medida_nombre) {
                                    return row.unidad_medida_nombre + 
                                        (row.unidad_abreviatura ? ' (' + row.unidad_abreviatura + ')' : '');
                                } else {
                                    return '-';
                                }
                            }
                        },
                        {
                            data: null,
                            orderable: false,
                            searchable: false,
                            className: "text-center",
                            render: function(data){
                                var estadoTexto = data.estado_registro_id == 1 ? 
                                    '<span class="badge bg-success">Activo</span>' : 
                                    '<span class="badge bg-secondary">Inactivo</span>';
                                
                                var botonEstado = 
                                    `<div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input toggle-estado"
                                            type="checkbox" 
                                            data-producto-id="${data.producto_id}" 
                                            ${data.estado_registro_id == 1 ? 'checked' : ''}>
                                    </div>`;
                                
                                return `<div class="d-flex flex-column align-items-center">                                            
                                            ${botonEstado}
                                        </div>`;
                            }
                        },
                        {
                            data: null,
                            orderable: false,
                            searchable: false,
                            className: "text-center",
                            render: function(data){
                                var botonEditar = data.estado_registro_id == 1 ? 
                                    `<button class="btn btn-sm btn-primary btnEditar" title="Editar">
                                        <i class="fa fa-edit"></i>
                                    </button>` : 
                                    `<button class="btn btn-sm btn-secondary" title="Editar no disponible" disabled>
                                        <i class="fa fa-edit"></i>
                                    </button>`;
                                
                                var botonEliminar = data.estado_registro_id == 1 ? 
                                    `<button class="btn btn-sm btn-danger btnEliminar" title="Eliminar">
                                        <i class="fa fa-trash"></i>
                                    </button>` : 
                                    `<button class="btn btn-sm btn-secondary" title="Eliminar no disponible" disabled>
                                        <i class="fa fa-trash"></i>
                                    </button>`;
                                
                                return `<div class="d-flex align-items-center justify-content-center gap-2">${botonEditar} ${botonEliminar}</div>`;
                            }
                        }
                    ],
                    createdRow: function(row, data, dataIndex) {
                        if (data.estado_registro_id != 1) {
                            $(row).addClass('table-secondary');
                            $(row).find('td').css('color', '#6c757d');
                        }
                    }
                });

                // Manejar el cambio de estado con el interruptor
                $(document).on('change', '.toggle-estado', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var productoId = $(this).data('producto-id');
                    var isChecked = $(this).is(':checked');
                    var nuevoEstado = isChecked ? 1 : 0;
                    var accionTexto = nuevoEstado == 1 ? 'activar' : 'desactivar';                    
                   
                    Swal.fire({
                        title: `¿${nuevoEstado == 1 ? 'Activar' : 'Desactivar'} producto?`,
                        text: `Está a punto de ${accionTexto} este producto`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: `Sí, ${accionTexto}`,
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.get('productos_ajax.php', {
                                accion: 'cambiar_estado', 
                                producto_id: productoId,
                                nuevo_estado: nuevoEstado
                            }, function(res){
                                if(res.resultado){
                                    tabla.ajax.reload();
                                    Swal.fire({
                                        icon: "success",
                                        title: "¡Éxito!",
                                        text: `Producto ${accionTexto}do correctamente`,
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                } else {
                                    // Revertir el cambio visual si hay error
                                    $(this).prop('checked', !isChecked);
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: res.error || `Error al ${accionTexto} el producto`
                                    });
                                }
                            }, 'json');
                        } else {
                            // Revertir visualmente si cancela
                            $(this).prop('checked', !isChecked);
                        }
                    });
                });

                $('#btnNuevo').click(function(){
                    $('#formProducto')[0].reset();
                    $('#producto_id').val('');
                    $('#modalLabel').text('Nuevo Producto');
                    $('#estado_registro_id').val('1');
                    cargarOpcionesSelect();
                    var modal = new bootstrap.Modal(document.getElementById('modalProducto'));
                    modal.show();
                    // Limpiar y preparar las secciones de ubicaciones y compatibilidad
                    $('#compatibilidad_producto_id').val('');
                    $('#ubicacion_producto_id').val('');
                    $('#tablaCompatibilidad tbody').empty();
                    $('#tablaUbicaciones tbody').empty();
                    // Cargar opciones necesarias
                        cargarOpcionesCompatibilidad();
                        cargarSucursales();

                });

                $('#tablaProductos tbody').on('click', '.btnEditar', function(){
                     
                    var data = tabla.row($(this).parents('tr')).data();
                    // Solo permitir editar si está activo
                    if (data.estado_registro_id != 1) {
                        Swal.fire({
                            icon: "warning",
                            title: "Producto inactivo",
                            text: "No se puede editar un producto inactivo. Active el producto primero.",
                            showConfirmButton: false,
                            timer: 2000
                        });
                        return false;
                    }
                    
                    $.get('productos_ajax.php', {accion: 'obtener', producto_id: data.producto_id}, function(res){
                        if(res){
                            $('#producto_id').val(res.producto_id);
                            $('#producto_codigo').val(res.producto_codigo);
                            $('#producto_nombre').val(res.producto_nombre);
                            $('#producto_descripcion').val(res.producto_descripcion);
                            $('#producto_categoria_id').val(res.producto_categoria_id);
                            $('#lado').val(res.lado);
                            $('#material').val(res.material);
                            $('#color').val(res.color);
                            $('#peso').val(res.peso);
                            $('#dimensiones').val(res.dimensiones);
                            $('#garantia').val(res.garantia);
                            $('#unidad_medida_id').val(res.unidad_medida_id);
                            $('#estado_registro_id').val(res.estado_registro_id);
                            
                            $('#modalLabel').text('Editar Producto');
                            var modal = new bootstrap.Modal(document.getElementById('modalProducto'));
                            modal.show();
                        } else {
                            alert('Error al obtener datos');
                        }
                    }, 'json');

                    // Cargar compatibilidad cuando se abre el modal de edición
                    $('#compatibilidad_producto_id').val(data.producto_id);
                    cargarCompatibilidad(data.producto_id);
                    cargarOpcionesCompatibilidad();
                    
                     // Cargar ubicaciones cuando se abre el modal de edición
                    $('#ubicacion_producto_id').val(data.producto_id);
                    cargarUbicacionesProducto(data.producto_id);
                    cargarSucursales();

                    // Mostrar la pestaña General por defecto
                    $('#general-tab').tab('show');
                });

                // Eliminar producto
                $('#tablaProductos tbody').on('click', '.btnEliminar', function(){
                    var data = tabla.row($(this).parents('tr')).data();
                    
                    Swal.fire({
                        title: '¿Eliminar producto?',
                        text: "Esta acción no se puede deshacer",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.get('productos_ajax.php', {
                                accion: 'eliminar', 
                                producto_id: data.producto_id
                            }, function(res){
                                if(res.resultado){
                                    tabla.ajax.reload();
                                    Swal.fire({
                                        icon: "success",
                                        title: "¡Éxito!",
                                        text: "Producto eliminado correctamente",
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                } else {
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: res.error || "Error al eliminar el producto"
                                    });
                                }
                            }, 'json');
                        }
                    });
                });

                $('#btnGuardar').click(function(){
                    var form = document.getElementById('formProducto');
                    
                    if (!form.checkValidity()) {
                        form.classList.add('was-validated');
                        return false;
                    }
                    
                    var id = $('#producto_id').val();
                    var accion = id ? 'editar' : 'agregar';
                    var formData = {
                        accion: accion,
                        producto_id: id,
                        producto_codigo: $('#producto_codigo').val(),
                        producto_nombre: $('#producto_nombre').val(),
                        producto_descripcion: $('#producto_descripcion').val(),
                        producto_categoria_id: $('#producto_categoria_id').val(),
                        lado: $('#lado').val(),
                        material: $('#material').val(),
                        color: $('#color').val(),
                        peso: $('#peso').val(),
                        dimensiones: $('#dimensiones').val(),
                        garantia: $('#garantia').val(),
                        unidad_medida_id: $('#unidad_medida_id').val(),
                        estado_registro_id: $('#estado_registro_id').val()
                    };

                    $.ajax({
                        url: 'productos_ajax.php',
                        type: 'POST',
                        data: formData,
                        dataType: 'json',
                        success: function(res) {
                            if(res.resultado) {
                                tabla.ajax.reload(null, false);
                                
                                var modal = bootstrap.Modal.getInstance(document.getElementById('modalProducto'));
                                modal.hide();
                                
                                $('#formProducto')[0].reset();
                                form.classList.remove('was-validated');
                                
                                Swal.fire({
                                    icon: "success",
                                    title: "¡Éxito!",
                                    text: id ? "Producto actualizado correctamente" : "Producto creado correctamente",
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
            .table-secondary td {
                color: #6c757d !important;
            }
            
            /* Estilos para el interruptor en la tabla */
            .form-check.form-switch.d-inline-block {
                padding-left: 0;
                margin-bottom: 0;
            }
            
            .form-check-input.toggle-estado {
                width: 3em;
                height: 1.5em;
            }
            
            .badge {
                font-size: 0.75rem;
            }
            .select2-container .select2-selection--single {
                height: 38px;
                padding: 5px;
            }
            .select2-container--default .select2-selection--single .select2-selection__arrow {
                height: 36px;
            }
            /* Posicionar modal-footer correctamente */
            .modal-xl .modal-content {
                display: flex;
                flex-direction: column;
                height: 90vh;
            }

            .modal-xl .modal-body {
                flex: 1;
                overflow-y: auto;
                max-height: 65vh;
            }

            .modal-xl .modal-footer {
                flex-shrink: 0;
                padding: 1rem;
                border-top: 1px solid #dee2e6;
            }

            .table-secondary td {
                color: #6c757d !important;
            }

            .form-check.form-switch.d-inline-block {
                padding-left: 0;
                margin-bottom: 0;
            }

            .form-check-input.toggle-estado {
                width: 3em;
                height: 1.5em;
            }

            .badge {
                font-size: 0.75rem;
            }

            .select2-container .select2-selection--single {
                height: 38px;
            }

            .select2-container--default .select2-selection--single .select2-selection__arrow {
                height: 36px;
            }

            /* Mejorar rendimiento de DataTable */
            #tablaProductos {
                width: 100% !important;
            }

            #tablaProductos_wrapper .dataTables_scroll {
                overflow-x: auto;
            }

            /* Estado visual más claro */
            .bg-success {
                background-color: #198754 !important;
            }

            .bg-secondary {
                background-color: #6c757d !important;
            }

            /* Botones de acción más compactos */
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.875rem;
            }
            
            </style>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

            <?php
            require_once ROOT_PATH . '/templates/adminlte/footer1.php';
            ?>
            </body>
            </html>