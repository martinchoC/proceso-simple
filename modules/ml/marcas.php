<?php
// Configuración de la página
$pageTitle = "Gestión de Marcas";
$currentPage = 'marcas';
$modudo_idx = 2;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Marcas</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Marcas</li>
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
                                        <table id="tablaMarcas" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Nombre</th>
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
            <div class="modal fade" id="modalMarca" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Marca</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formMarca">
                                <input type="hidden" id="marca_id" name="marca_id" />
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label>Nombre de la Marca *</label>
                                        <input type="text" class="form-control" id="marca_nombre" name="marca_nombre" required />
                                        <div class="invalid-feedback">El nombre de la marca es obligatorio</div>
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
        var tabla = $('#tablaMarcas').DataTable({
            ajax: {
                url: 'marcas_ajax.php',
                type: 'GET',
                data: {accion: 'listar'},
                dataSrc: ''
            },
            columns: [
                { data: 'marca_id' },
                { data: 'marca_nombre' },
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
                                               data-id="${row.marca_id}" 
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
            $.get('marcas_ajax.php', {accion: 'obtener_boton_agregar'}, function(botonAgregar){
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
                    $('#contenedor-boton-agregar').html('<button class="btn btn-primary mb-3" id="btnNuevo">Nueva Marca</button>');
                    console.log("Usando botón agregar por defecto");
                }
            }).fail(function(xhr, status, error) {
                console.error("Error cargando botón agregar:", error);
                $('#contenedor-boton-agregar').html('<button class="btn btn-primary mb-3" id="btnNuevo">Nueva Marca</button>');
            });
        }

        // Manejador para botón "Agregar"
        $(document).on('click', '#btnNuevo', function(){
            $('#formMarca')[0].reset();
            $('#marca_id').val('');
            $('#modalLabel').text('Nueva Marca');
            
            var modal = new bootstrap.Modal(document.getElementById('modalMarca'));
            modal.show();
        });

        // Manejador para botones dinámicos
        $(document).on('click', '.btnFuncion', function(){
            var marcaId = $(this).data('id');
            var accion = $(this).data('accion');
            var confirmable = $(this).data('confirmable');
            
            console.log("Ejecutando acción:", accion, "Para marca:", marcaId);
            
            // Ejecutar la acción correspondiente
            switch(accion) {
                case 'editar':
                    cargarMarcaParaEditar(marcaId);
                    break;
                case 'visualizar':
                    cargarMarcaParaEditar(marcaId, true);
                    break;
                case 'agregar':
                    // Esto no debería pasar en botones de fila, pero por si acaso
                    $('#btnNuevo').click();
                    break;
                default:
                    // Para acciones como confirmar, eliminar, habilitar, etc.
                    if (confirmable == 1) {
                        Swal.fire({
                            title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)} marca?`,
                            text: "Esta acción cambiará el estado de la marca",
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            confirmButtonText: `Sí, ${accion}`,
                            cancelButtonText: 'Cancelar'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                ejecutarAccion(marcaId, accion);
                            }
                        });
                    } else {
                        ejecutarAccion(marcaId, accion);
                    }
            }
        });

        // Función para ejecutar cualquier acción del backend
        function ejecutarAccion(marcaId, accion) {
            // Convertir acción JS a nombre de función para el backend
            var funcionBackend = accion.charAt(0).toUpperCase() + accion.slice(1);
            
            $.post('marcas_ajax.php', {
                accion: 'ejecutar_funcion',
                marca_id: marcaId,
                funcion_nombre: funcionBackend
            }, function(res){
                if (res.success) {
                    tabla.ajax.reload();
                    Swal.fire({
                        icon: "success",
                        title: `¡${funcionBackend}!`,
                        text: `Marca ${accion.toLowerCase()} correctamente`,
                        showConfirmButton: false,
                        timer: 1500
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: res.error || `Error al ${accion.toLowerCase()} la marca`,
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

        // Función para cargar marca en modal de edición
        function cargarMarcaParaEditar(marcaId, soloLectura = false) {
            $.get('marcas_ajax.php', {accion: 'obtener', marca_id: marcaId}, function(res){
                if(res){
                    $('#marca_id').val(res.marca_id);
                    $('#marca_nombre').val(res.marca_nombre);
                    
                    if (soloLectura) {
                        $('#modalLabel').text('Visualizar Marca');
                        $('#marca_nombre').prop('readonly', true);
                        $('#btnGuardar').hide();
                    } else {
                        $('#modalLabel').text('Editar Marca');
                        $('#marca_nombre').prop('readonly', false);
                        $('#btnGuardar').show();
                    }
                    
                    var modal = new bootstrap.Modal(document.getElementById('modalMarca'));
                    modal.show();
                    
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Error",
                        text: "Error al obtener datos de la marca",
                        confirmButtonText: "Entendido"
                    });
                }
            }, 'json');
        }

        // Validación del formulario
        $('#btnGuardar').click(function(){
            var form = document.getElementById('formMarca');
            
            if (!form.checkValidity()) {
                form.classList.add('was-validated');
                return false;
            }
            
            console.log("Click en guardar");
            var id = $('#marca_id').val();
            var accion = id ? 'editar' : 'agregar';
            console.log("Acción:", accion, "ID:", id);
            
            var formData = new FormData();
            formData.append('accion', accion);
            formData.append('marca_id', id);
            formData.append('marca_nombre', $('#marca_nombre').val());

            console.log("Datos del formulario:");
            console.log("marca_nombre:", $('#marca_nombre').val());

            $.ajax({
                url: 'marcas_ajax.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(res){
                    console.log("Respuesta del servidor:", res);
                    if(res.resultado){
                        tabla.ajax.reload();
                        var modalEl = document.getElementById('modalMarca');
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
    });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</main>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>