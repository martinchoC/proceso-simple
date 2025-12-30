<?php
// Configuración de la página
$pageTitle = "Gestión de Usuarios";
$currentPage = 'usuarios';
$modudo_idx = 2;
// Definir constante para rutas
define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));

// Incluir header
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Usuarios</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Usuarios</li>
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
                                        <button class="btn btn-primary mb-3" id="btnNuevo">Nuevo Usuario</button>
                                        <table id="tablaUsuarios" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Nombre</th>
                                                    <th>Usuario</th>
                                                    <th>Email</th>
                                                    <th>Duración SID (min)</th>
                                                    <th>Creado en</th>
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
            <div class="modal fade" id="modalUsuario" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Usuario</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formUsuario">
                                <input type="hidden" id="usuario_id" name="usuario_id" />
                                
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label>Nombre completo *</label>
                                        <input type="text" class="form-control" id="usuario_nombre" name="usuario_nombre" required/>
                                        <div class="invalid-feedback">El nombre es obligatorio</div>
                                    </div>
                                    <div class="col-md-12">
                                        <label>Nombre de usuario *</label>
                                        <input type="text" class="form-control" id="usuario" name="usuario" required/>
                                        <div class="invalid-feedback">El nombre de usuario es obligatorio</div>
                                    </div>
                                    <div class="col-md-12">
                                        <label>Email *</label>
                                        <input type="email" class="form-control" id="email" name="email" required/>
                                        <div class="invalid-feedback">El email es obligatorio y debe ser válido</div>
                                    </div>
                                    <div class="col-md-12">
                                        <label>Contraseña <?php echo isset($_GET['usuario_id']) ? '(dejar en blanco para no cambiar)' : '*' ?></label>
                                        <input type="password" class="form-control" id="password" name="password" <?php echo !isset($_GET['usuario_id']) ? 'required' : '' ?>/>
                                        <div class="invalid-feedback">La contraseña es obligatoria</div>
                                    </div>
                                    <div class="col-md-12">
                                        <label>Duración de sesión (minutos)</label>
                                        <input type="number" class="form-control" id="duracion_sid_minutos" name="duracion_sid_minutos" value="60" min="1"/>
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
                var tabla = $('#tablaUsuarios').DataTable({
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
                        url: 'usuarios_ajax.php',
                        type: 'GET',
                        data: {accion: 'listar'},
                        dataSrc: ''
                    },
                    language: {
                        "search": "Buscar:",
                        "searchPlaceholder": "Buscar usuarios...",
                        "lengthMenu": "Mostrar _MENU_ registros por página",
                        "zeroRecords": "No se encontraron usuarios",
                        "info": "Mostrando _START_ a _END_ de _TOTAL_ usuarios",
                        "infoEmpty": "Mostrando 0 a 0 de 0 usuarios",
                        "infoFiltered": "(filtrado de _MAX_ usuarios totales)",
                        "paginate": {
                            "first": "Primero",
                            "last": "Último",
                            "next": "Siguiente",
                            "previous": "Anterior"
                        }
                    },
                    columns: [
                        { data: 'usuario_id' },
                        { data: 'usuario_nombre' },
                        { data: 'usuario' },
                        { data: 'email' },
                        { data: 'duracion_sid_minutos' },
                        { 
                            data: 'creado_en',
                            render: function(data) {
                                return new Date(data).toLocaleString();
                            }
                        },
                        {
                            data: null,
                            orderable: false,
                            searchable: false,
                            className: "text-center",
                            render: function(data){
                                var estadoTexto = data.tabla_estado_registro_id == 1 ? 
                                    '<span class="badge bg-success">Activo</span>' : 
                                    '<span class="badge bg-secondary">Inactivo</span>';
                                
                                var botonEstado = 
                                    `<div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input toggle-estado"
                                            type="checkbox" 
                                            data-usuario-id="${data.usuario_id}" 
                                            ${data.tabla_estado_registro_id == 1 ? 'checked' : ''}>
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
                                var botonEditar = data.tabla_estado_registro_id == 1 ? 
                                    `<button class="btn btn-sm btn-primary btnEditar" title="Editar">
                                        <i class="fa fa-edit"></i>
                                     </button>` : 
                                    `<button class="btn btn-sm btn-secondary" title="Editar no disponible" disabled>
                                        <i class="fa fa-edit"></i>
                                     </button>`;
                                
                                return `<div class="d-flex align-items-center justify-content-center">${botonEditar}</div>`;
                            }
                        }
                    ],
                    createdRow: function(row, data, dataIndex) {
                        // Cambiar color de fondo según el estado
                        if (data.tabla_estado_registro_id != 1) {
                            $(row).addClass('table-secondary');
                            $(row).find('td').css('color', '#6c757d');
                        }
                    }
                });

                // Manejar el cambio de estado con el interruptor
                $(document).on('change', '.toggle-estado', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var usuarioId = $(this).data('usuario-id');
                    var isChecked = $(this).is(':checked');
                    var nuevoEstado = isChecked ? 1 : 0;
                    var accionTexto = nuevoEstado == 1 ? 'activar' : 'desactivar';                    
                   
                    Swal.fire({
                        title: `¿${nuevoEstado == 1 ? 'Activar' : 'Desactivar'} usuario?`,
                        text: `Está a punto de ${accionTexto} este usuario`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: `Sí, ${accionTexto}`,
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.get('usuarios_ajax.php', {
                                accion: 'cambiar_estado', 
                                usuario_id: usuarioId,
                                nuevo_estado: nuevoEstado
                            }, function(res){
                                if(res.resultado){
                                    tabla.ajax.reload();
                                    Swal.fire({
                                        icon: "success",
                                        title: "¡Éxito!",
                                        text: `Usuario ${accionTexto}do correctamente`,
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                } else {
                                    // Revertir el cambio visual si hay error
                                    $(this).prop('checked', !isChecked);
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: res.error || `Error al ${accionTexto} el usuario`
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
                    $('#formUsuario')[0].reset();
                    $('#usuario_id').val('');
                    $('#modalLabel').text('Nuevo Usuario');
                    $('#password').prop('required', true);
                    var modal = new bootstrap.Modal(document.getElementById('modalUsuario'));
                    modal.show();
                });

                $('#tablaUsuarios tbody').on('click', '.btnEditar', function(){
                    var data = tabla.row($(this).parents('tr')).data();
                    // Solo permitir editar si está activo
                    if (data.tabla_estado_registro_id != 1) {
                        Swal.fire({
                            icon: "warning",
                            title: "Usuario inactivo",
                            text: "No se puede editar un usuario inactivo. Active el usuario primero.",
                            showConfirmButton: false,
                            timer: 2000
                        });
                        return false;
                    }
                    
                    $.get('usuarios_ajax.php', {accion: 'obtener', usuario_id: data.usuario_id}, function(res){
                        if(res){
                            $('#usuario_id').val(res.usuario_id);
                            $('#usuario_nombre').val(res.usuario_nombre);
                            $('#usuario').val(res.usuario);
                            $('#email').val(res.email);
                            $('#duracion_sid_minutos').val(res.duracion_sid_minutos);
                            $('#password').prop('required', false);
                            
                            $('#modalLabel').text('Editar Usuario');
                            var modal = new bootstrap.Modal(document.getElementById('modalUsuario'));
                            modal.show();
                        } else {
                            alert('Error al obtener datos');
                        }
                    }, 'json');
                });

                $('#btnGuardar').click(function(){
                    var form = document.getElementById('formUsuario');
                    
                    if (!form.checkValidity()) {
                        form.classList.add('was-validated');
                        return false;
                    }
                    
                    var id = $('#usuario_id').val();
                    var accion = id ? 'editar' : 'agregar';
                    var formData = {
                        accion: accion,
                        usuario_id: id,
                        usuario_nombre: $('#usuario_nombre').val(),
                        usuario: $('#usuario').val(),
                        email: $('#email').val(),
                        password: $('#password').val(),
                        duracion_sid_minutos: $('#duracion_sid_minutos').val()
                    };

                    $.ajax({
                        url: 'usuarios_ajax.php',
                        type: 'POST',
                        data: formData,
                        dataType: 'json',
                        success: function(res) {
                            if(res.resultado) {
                                tabla.ajax.reload(null, false);
                                
                                var modal = bootstrap.Modal.getInstance(document.getElementById('modalUsuario'));
                                modal.hide();
                                
                                $('#formUsuario')[0].reset();
                                form.classList.remove('was-validated');
                                
                                Swal.fire({
                                    icon: "success",
                                    title: "¡Éxito!",
                                    text: id ? "Usuario actualizado correctamente" : "Usuario creado correctamente",
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
            </style>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

            <?php
            require_once ROOT_PATH . '/templates/adminlte/footer1.php';
            ?>
            </body>
            </html>