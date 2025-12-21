<?php
// Configuración de la página
require_once "conexion.php";
$pageTitle = "Gestión de Locales";
$currentPage = 'sucursales_tipos';
$modudo_idx = 2;
$pagina_idx = 33;
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
                <div class="col-sm-6"><h3 class="mb-0">Tipos de Locales</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Tipos de Locales</li>
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
                                        <table id="tablaLocalesTipos" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Nombre</th>
                                                    <th>Descripción</th>
                                                    <th>Fecha Creación</th>
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
            <div class="modal fade" id="modalLocalTipo" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Tipo de sucursal</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formLocalTipo">
                                <input type="hidden" id="sucursal_tipo_id" name="sucursal_tipo_id" />
                                
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label>Nombre *</label>
                                        <input type="text" class="form-control" id="sucursal_tipo" name="sucursal_tipo" required/>
                                        <div class="invalid-feedback">El nombre es obligatorio</div>
                                    </div>
                                    <div class="col-md-12">
                                        <label>Descripción</label>
                                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
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
                // Configuración de DataTable
                var tabla = $('#tablaLocalesTipos').DataTable({
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
                        url: 'sucursales_tipos_ajax.php',
                        type: 'GET',
                        data: {accion: 'listar'},
                        dataSrc: ''
                    },
                    language: {
                        "search": "Buscar:",
                        "searchPlaceholder": "Buscar tipos de sucursales...",
                        "lengthMenu": "Mostrar _MENU_ registros por página",
                        "zeroRecords": "No se encontraron tipos de sucursales",
                        "info": "Mostrando _START_ a _END_ de _TOTAL_ tipos de sucursales",
                        "infoEmpty": "Mostrando 0 a 0 de 0 tipos de sucursales",
                        "infoFiltered": "(filtrado de _MAX_ tipos de sucursales totales)",
                        "paginate": {
                            "first": "Primero",
                            "last": "Último",
                            "next": "Siguiente",
                            "previous": "Anterior"
                        }
                    },
                    columns: [
                        { data: 'sucursal_tipo_id' },
                        { data: 'sucursal_tipo' },
                        { 
                            data: 'descripcion',
                            render: function(data) {
                                return data || '-';
                            }
                        },
                        { 
                            data: 'fecha_creacion',
                            render: function(data) {
                                return data ? new Date(data).toLocaleString() : '-';
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
                                                       data-id="${row.sucursal_tipo_id}" 
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
                    $.get('sucursales_tipos_ajax.php', {accion: 'obtener_boton_agregar'}, function(botonAgregar){
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

                // Manejador para botón "Agregar"
                $(document).on('click', '#btnNuevo', function(){
                    $('#formLocalTipo')[0].reset();
                    $('#sucursal_tipo_id').val('');
                    $('#modalLabel').text('Nuevo Tipo de sucursal');
                    
                    var modal = new bootstrap.Modal(document.getElementById('modalLocalTipo'));
                    modal.show();
                });

                // Manejador para botones dinámicos
                $(document).on('click', '.btnFuncion', function(){
                    var sucursalTipoId = $(this).data('id');
                    var accion = $(this).data('accion');
                    var confirmable = $(this).data('confirmable');
                    
                    console.log("Ejecutando acción:", accion, "Para tipo de sucursal:", sucursalTipoId);
                    
                    // Ejecutar la acción correspondiente
                    switch(accion) {
                        case 'editar':
                            cargarSucursalTipoParaEditar(sucursalTipoId);
                            break;
                        case 'visualizar':
                            cargarSucursalTipoParaEditar(sucursalTipoId, true);
                            break;
                        case 'agregar':
                            // Esto no debería pasar en botones de fila, pero por si acaso
                            $('#btnNuevo').click();
                            break;
                        default:
                            // Para acciones como habilitar, inhabilitar, etc.
                            if (confirmable == 1) {
                                Swal.fire({
                                    title: `¿${accion.charAt(0).toUpperCase() + accion.slice(1)} tipo de sucursal?`,
                                    text: "Esta acción cambiará el estado del tipo de sucursal",
                                    icon: 'question',
                                    showCancelButton: true,
                                    confirmButtonColor: '#3085d6',
                                    cancelButtonColor: '#d33',
                                    confirmButtonText: `Sí, ${accion}`,
                                    cancelButtonText: 'Cancelar'
                                }).then((result) => {
                                    if (result.isConfirmed) {
                                        ejecutarAccion(sucursalTipoId, accion);
                                    }
                                });
                            } else {
                                ejecutarAccion(sucursalTipoId, accion);
                            }
                    }
                });

                // Función para ejecutar cualquier acción del backend
                function ejecutarAccion(sucursalTipoId, accion) {
                    // Convertir acción JS a nombre de función para el backend
                    var funcionBackend = accion.charAt(0).toUpperCase() + accion.slice(1);
                    
                    $.post('sucursales_tipos_ajax.php', {
                        accion: 'ejecutar_funcion',
                        sucursal_tipo_id: sucursalTipoId,
                        funcion_nombre: funcionBackend
                    }, function(res){
                        if (res.success) {
                            tabla.ajax.reload();
                            Swal.fire({
                                icon: "success",
                                title: `¡${funcionBackend}!`,
                                text: `Tipo de sucursal ${accion.toLowerCase()} correctamente`,
                                showConfirmButton: false,
                                timer: 1500
                            });
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: res.error || `Error al ${accion.toLowerCase()} el tipo de sucursal`,
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

                // Función para cargar tipo de sucursal en modal de edición
                function cargarSucursalTipoParaEditar(sucursalTipoId, soloLectura = false) {
                    $.get('sucursales_tipos_ajax.php', {accion: 'obtener', sucursal_tipo_id: sucursalTipoId}, function(res){
                        if(res && res.sucursal_tipo_id){
                            $('#sucursal_tipo_id').val(res.sucursal_tipo_id);
                            $('#sucursal_tipo').val(res.sucursal_tipo);
                            $('#descripcion').val(res.descripcion);
                            
                            if (soloLectura) {
                                $('#modalLabel').text('Visualizar Tipo de Sucursal');
                                $('#sucursal_tipo').prop('readonly', true);
                                $('#descripcion').prop('readonly', true);
                                $('#btnGuardar').hide();
                            } else {
                                $('#modalLabel').text('Editar Tipo de Sucursal');
                                $('#sucursal_tipo').prop('readonly', false);
                                $('#descripcion').prop('readonly', false);
                                $('#btnGuardar').show();
                            }
                            
                            var modal = new bootstrap.Modal(document.getElementById('modalLocalTipo'));
                            modal.show();
                            
                        } else {
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: "Error al obtener datos del tipo de sucursal",
                                confirmButtonText: "Entendido"
                            });
                        }
                    }, 'json');
                }

                $('#btnGuardar').click(function(){
                    var form = document.getElementById('formLocalTipo');
                    
                    if (!form.checkValidity()) {
                        form.classList.add('was-validated');
                        return false;
                    }
                    
                    var id = $('#sucursal_tipo_id').val();
                    var accion = id ? 'editar' : 'agregar';
                    
                    var formData = new FormData();
                    formData.append('accion', accion);
                    formData.append('sucursal_tipo_id', id);
                    formData.append('sucursal_tipo', $('#sucursal_tipo').val());
                    formData.append('descripcion', $('#descripcion').val());

                    $.ajax({
                        url: 'sucursales_tipos_ajax.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        success: function(res){
                            if(res.resultado){
                                tabla.ajax.reload();
                                var modalEl = document.getElementById('modalLocalTipo');
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