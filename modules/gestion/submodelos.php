<?php
// Configuración de la página
require_once "conexion.php";
$pageTitle = "Gestión de Submodelos";
$currentPage = 'submodelos';
$modudo_idx = 2;
$pagina_idx = 42;
$sql = "SELECT * FROM conf__paginas WHERE pagina_id=$pagina_idx";
$res = mysqli_query($conexion, $sql);
$fila = mysqli_fetch_assoc($res);

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Submodelos</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Submodelos</li>
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
                                        <table id="tablaSubmodelos" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Marca</th>
                                                    <th>Modelo</th>
                                                    <th>Submodelo</th>
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
            <div class="modal fade" id="modalSubmodelo" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Submodelo</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formSubmodelo">
                                <input type="hidden" id="submodelo_id" name="submodelo_id" />
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label>Marca *</label>
                                        <select class="form-control" id="marca_id" name="marca_id" required>
                                            <option value="">Seleccionar marca</option>
                                        </select>
                                        <div class="invalid-feedback">La marca es obligatoria</div>
                                    </div>
                                    <div class="col-md-12">
                                        <label>Modelo *</label>
                                        <select class="form-control" id="modelo_id" name="modelo_id" required>
                                            <option value="">Seleccionar modelo</option>
                                        </select>
                                        <div class="invalid-feedback">El modelo es obligatorio</div>
                                    </div>
                                    <div class="col-md-12">
                                        <label>Nombre del Submodelo *</label>
                                        <input type="text" class="form-control" id="submodelo_nombre" name="submodelo_nombre" required />
                                        <div class="invalid-feedback">El nombre del submodelo es obligatorio</div>
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
        </div>
    </div>

    <script>
    $(document).ready(function(){
        // Configuración de DataTable
        var tabla = $('#tablaSubmodelos').DataTable({
            ajax: {
                url: 'submodelos_ajax.php',
                type: 'GET',
                data: {accion: 'listar'},
                dataSrc: ''
            },
            columns: [
                { data: 'submodelo_id' },
                { data: 'marca_nombre' },
                { data: 'modelo_nombre' },
                { data: 'submodelo_nombre' },
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
                                               data-id="${row.submodelo_id}" 
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

        // Cargar botón Agregar dinámicamente
        function cargarBotonAgregar() {
            console.log("Cargando botón agregar...");
            $.get('submodelos_ajax.php', {accion: 'obtener_boton_agregar'}, function(botonAgregar){
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
                    $('#contenedor-boton-agregar').html('<button class="btn btn-primary mb-3" id="btnNuevo">Nuevo Submodelo</button>');
                    console.log("Usando botón agregar por defecto");
                }
            }).fail(function(xhr, status, error) {
                console.error("Error cargando botón agregar:", error);
                $('#contenedor-boton-agregar').html('<button class="btn btn-primary mb-3" id="btnNuevo">Nuevo Submodelo</button>');
            });
        }

        // Cargar marcas para el select
        function cargarMarcas() {
            $.get('submodelos_ajax.php', {accion: 'obtener_marcas'}, function(marcas){
                var select = $('#marca_id');
                select.empty().append('<option value="">Seleccionar Marca</option>');
                marcas.forEach(function(marca){
                    select.append(`<option value="${marca.marca_id}">${marca.marca_nombre}</option>`);
                });
            }, 'json');
        }

        // Cargar modelos cuando se selecciona una marca
        $('#marca_id').change(function(){
            var marca_id = $(this).val();
            if(marca_id) {
                $.get('submodelos_ajax.php', {accion: 'obtener_modelos', marca_id: marca_id}, function(data){
                    $('#modelo_id').empty().append('<option value="">Seleccionar modelo</option>');
                    $.each(data, function(key, value){
                        $('#modelo_id').append('<option value="'+value.modelo_id+'">'+value.modelo_nombre+'</option>');
                    });
                }, 'json').fail(function(xhr, status, error) {
                    console.error("Error cargando modelos:", error);
                    $('#modelo_id').empty().append('<option value="">Error cargando modelos</option>');
                });
            } else {
                $('#modelo_id').empty().append('<option value="">Seleccionar modelo</option>');
            }
        });

        // Manejador para botón "Agregar"
        $(document).on('click', '#btnNuevo', function(){
            $('#formSubmodelo')[0].reset();
            $('#submodelo_id').val('');
            $('#modelo_id').empty().append('<option value="">Seleccionar modelo</option>');
            $('#modalLabel').text('Nuevo Submodelo');
            $('#submodelo_nombre').prop('readonly', false);
            $('#marca_id').prop('disabled', false);
            $('#modelo_id').prop('disabled', false);
            $('#btnGuardar').show();
            
            // Remover clases de validación
            $('#formSubmodelo').removeClass('was-validated');
            
            var modal = new bootstrap.Modal(document.getElementById('modalSubmodelo'));
            modal.show();
        });

        // Manejador para botones dinámicos
        $(document).on('click', '.btnFuncion', function(){
            var submodeloId = $(this).data('id');
            var accion = $(this).data('accion');
            var confirmable = $(this).data('confirmable');
            
            console.log("Ejecutando acción:", accion, "Para submodelo:", submodeloId);
            
            // Ejecutar la acción correspondiente
            switch(accion) {
                case 'editar':
                    cargarSubmodeloParaEditar(submodeloId);
                    break;
                case 'visualizar':
                    cargarSubmodeloParaEditar(submodeloId, true);
                    break;
                case 'agregar':
                    // Esto no debería pasar en botones de fila, pero por si acaso
                    $('#btnNuevo').click();
                    break;
                default:
                    // Para acciones como habilitar, inhabilitar, etc.
                    if (confirmable == 1) {
                        Swal.fire({
                            title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)} submodelo?`,
                            text: "Esta acción cambiará el estado del submodelo",
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            confirmButtonText: `Sí, ${accion}`,
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                ejecutarAccion(submodeloId, accion);
                            }
                        });
                    } else {
                        ejecutarAccion(submodeloId, accion);
                    }
            }
        });

        // Función para ejecutar cualquier acción del backend
        function ejecutarAccion(submodeloId, accion) {
            // Convertir acción JS a nombre de función para el backend
            var funcionBackend = accion.charAt(0).toUpperCase() + accion.slice(1);
            
            $.post('submodelos_ajax.php', {
                accion: 'ejecutar_funcion',
                submodelo_id: submodeloId,
                funcion_nombre: funcionBackend
            }, function(res){
                if (res.success) {
                    tabla.ajax.reload();
                    Swal.fire({
                        icon: "success",
                        title: `¡${funcionBackend}!`,
                        text: `Submodelo ${accion.toLowerCase()} correctamente`,
                        showConfirmButton: false,
                        timer: 1500
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || `Error al ${accion.toLowerCase()} el submodelo`,
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

        // Función para cargar submodelo en modal de edición
        function cargarSubmodeloParaEditar(submodeloId, soloLectura = false) {
            $.get('submodelos_ajax.php', {accion: 'obtener', submodelo_id: submodeloId}, function(res){
                if(res && res.submodelo_id){
                    console.log("Datos recibidos para edición:", res);
                    
                    $('#submodelo_id').val(res.submodelo_id);
                    $('#submodelo_nombre').val(res.submodelo_nombre);
                    
                    // Primero cargar todas las marcas y seleccionar la correcta
                    $.get('submodelos_ajax.php', {accion: 'obtener_marcas'}, function(marcas){
                        $('#marca_id').empty().append('<option value="">Seleccionar Marca</option>');
                        $.each(marcas, function(key, marca){
                            $('#marca_id').append('<option value="'+marca.marca_id+'" '+(marca.marca_id == res.marca_id ? 'selected' : '')+'>'+marca.marca_nombre+'</option>');
                        });
                        
                        // Una vez cargada la marca, cargar los modelos de esa marca
                        if(res.marca_id) {
                            $.get('submodelos_ajax.php', {accion: 'obtener_modelos', marca_id: res.marca_id}, function(modelos){
                                $('#modelo_id').empty().append('<option value="">Seleccionar modelo</option>');
                                $.each(modelos, function(key, modelo){
                                    $('#modelo_id').append('<option value="'+modelo.modelo_id+'" '+(modelo.modelo_id == res.modelo_id ? 'selected' : '')+'>'+modelo.modelo_nombre+'</option>');
                                });
                            }, 'json').fail(function(xhr, status, error) {
                                console.error("Error cargando modelos:", error);
                            });
                        }
                    }, 'json');
                    
                    if (soloLectura) {
                        $('#modalLabel').text('Visualizar Submodelo');
                        $('#submodelo_nombre').prop('readonly', true);
                        $('#marca_id').prop('disabled', true);
                        $('#modelo_id').prop('disabled', true);
                        $('#btnGuardar').hide();
                    } else {
                        $('#modalLabel').text('Editar Submodelo');
                        $('#submodelo_nombre').prop('readonly', false);
                        $('#marca_id').prop('disabled', false);
                        $('#modelo_id').prop('disabled', false);
                        $('#btnGuardar').show();
                    }
                    
                    var modal = new bootstrap.Modal(document.getElementById('modalSubmodelo'));
                    modal.show();
                    
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "Error al obtener datos del submodelo",
                        confirmButtonText: "Entendido"
                    });
                }
            }, 'json').fail(function(xhr, status, error) {
                console.error("Error en AJAX:", error);
                Swal.fire({
                    icon: "error",
                    title: "Error de conexión",
                    text: "No se pudieron cargar los datos del submodelo",
                    confirmButtonText: "Entendido"
                });
            });
        }

        // Validación del formulario
        $('#btnGuardar').click(function(){
            var form = document.getElementById('formSubmodelo');
            
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return false;
            }
            
            console.log("Click en guardar");
            var id = $('#submodelo_id').val();
            var accion = id ? 'editar' : 'agregar';
            console.log("Acción:", accion, "ID:", id);
            
            var formData = new FormData();
            formData.append('accion', accion);
            formData.append('submodelo_id', id);
            formData.append('modelo_id', $('#modelo_id').val());
            formData.append('submodelo_nombre', $('#submodelo_nombre').val());

            console.log("Datos del formulario:");
            console.log("modelo_id:", $('#modelo_id').val());
            console.log("submodelo_nombre:", $('#submodelo_nombre').val());

            $.ajax({
                url: 'submodelos_ajax.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res){
                    console.log("Respuesta del servidor:", res);
                    if(res.resultado){
                        tabla.ajax.reload();
                        var modalEl = document.getElementById('modalSubmodelo');
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
        cargarMarcas();
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>