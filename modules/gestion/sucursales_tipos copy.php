<?php
// Configuración de la página
$pageTitle = "Gestión de Locales";
$currentPage = 'sucursales_tipos';
$modudo_idx = 2;

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
                                        <button class="btn btn-primary mb-3" id="btnNuevo">Nuevo Tipo</button>
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
                                        <input type="text" class="form-control" id="nombre" name="nombre" required/>
                                        <div class="invalid-feedback">El nombre es obligatorio</div>
                                    </div>
                                    <div class="col-md-12">
                                        <label>Descripción</label>
                                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Usuario Creación ID</label>
                                        <input type="number" class="form-control" id="usuario_creacion_id" name="usuario_creacion_id"/>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Estado</label>
                                        <select class="form-control" id="tabla_estado_registro_id" name="tabla_estado_registro_id">
                                            <option value="1">Activo</option>
                                            <option value="0">Inactivo</option>
                                        </select>
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
                        { data: 'nombre' },
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
                                            data-sucursal-tipo-id="${data.sucursal_tipo_id}" 
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
                                
                                var botonEliminar = data.tabla_estado_registro_id == 1 ? 
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
                    
                    var sucursalTipoId = $(this).data('sucursal-tipo-id');
                    var isChecked = $(this).is(':checked');
                    var nuevoEstado = isChecked ? 1 : 0;
                    var accionTexto = nuevoEstado == 1 ? 'activar' : 'desactivar';                    
                   
                    Swal.fire({
                        title: `¿${nuevoEstado == 1 ? 'Activar' : 'Desactivar'} tipo de sucursal?`,
                        text: `Está a punto de ${accionTexto} este tipo de sucursal`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: `Sí, ${accionTexto}`,
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.get('sucursales_tipos_ajax.php', {
                                accion: 'cambiar_estado', 
                                sucursal_tipo_id: sucursalTipoId,
                                nuevo_estado: nuevoEstado
                            }, function(res){
                                if(res.resultado){
                                    tabla.ajax.reload();
                                    Swal.fire({
                                        icon: "success",
                                        title: "¡Éxito!",
                                        text: `Tipo de sucursal ${accionTexto}do correctamente`,
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                } else {
                                    // Revertir el cambio visual si hay error
                                    $(this).prop('checked', !isChecked);
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: res.error || `Error al ${accionTexto} el tipo de sucursal`
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
                    $('#formLocalTipo')[0].reset();
                    $('#sucursal_tipo_id').val('');
                    $('#modalLabel').text('Nuevo Tipo de sucursal');
                    $('#tabla_estado_registro_id').val('1');
                    var modal = new bootstrap.Modal(document.getElementById('modalLocalTipo'));
                    modal.show();
                });

                $('#tablaLocalesTipos tbody').on('click', '.btnEditar', function(){
                    var data = tabla.row($(this).parents('tr')).data();
                    // Solo permitir editar si está activo
                    if (data.tabla_estado_registro_id != 1) {
                        Swal.fire({
                            icon: "warning",
                            title: "Tipo de sucursal inactivo",
                            text: "No se puede editar un tipo de sucursal inactivo. Active el tipo primero.",
                            showConfirmButton: false,
                            timer: 2000
                        });
                        return false;
                    }
                    
                    $.get('sucursales_tipos_ajax.php', {accion: 'obtener', sucursal_tipo_id: data.sucursal_tipo_id}, function(res){
                        if(res){
                            $('#sucursal_tipo_id').val(res.sucursal_tipo_id);
                            $('#nombre').val(res.nombre);
                            $('#descripcion').val(res.descripcion);
                            $('#usuario_creacion_id').val(res.usuario_creacion_id);
                            $('#tabla_estado_registro_id').val(res.tabla_estado_registro_id);
                            
                            $('#modalLabel').text('Editar Tipo de sucursal');
                            var modal = new bootstrap.Modal(document.getElementById('modalLocalTipo'));
                            modal.show();
                        } else {
                            alert('Error al obtener datos');
                        }
                    }, 'json');
                });

                // Eliminar tipo de sucursal
                $('#tablaLocalesTipos tbody').on('click', '.btnEliminar', function(){
                    var data = tabla.row($(this).parents('tr')).data();
                    
                    Swal.fire({
                        title: '¿Eliminar tipo de sucursal?',
                        text: "Esta acción no se puede deshacer",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.get('sucursales_tipos_ajax.php', {
                                accion: 'eliminar', 
                                sucursal_tipo_id: data.sucursal_tipo_id
                            }, function(res){
                                if(res.resultado){
                                    tabla.ajax.reload();
                                    Swal.fire({
                                        icon: "success",
                                        title: "¡Éxito!",
                                        text: "Tipo de sucursal eliminado correctamente",
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                } else {
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: res.error || "Error al eliminar el tipo de sucursal"
                                    });
                                }
                            }, 'json');
                        }
                    });
                });

                $('#btnGuardar').click(function(){
                    var form = document.getElementById('formLocalTipo');
                    
                    if (!form.checkValidity()) {
                        form.classList.add('was-validated');
                        return false;
                    }
                    
                    var id = $('#sucursal_tipo_id').val();
                    var accion = id ? 'editar' : 'agregar';
                    var formData = {
                        accion: accion,
                        sucursal_tipo_id: id,
                        nombre: $('#nombre').val(),
                        descripcion: $('#descripcion').val(),
                        usuario_creacion_id: $('#usuario_creacion_id').val(),
                        tabla_estado_registro_id: $('#tabla_estado_registro_id').val()
                    };

                    $.ajax({
                        url: 'sucursales_tipos_ajax.php',
                        type: 'POST',
                        data: formData,
                        dataType: 'json',
                        success: function(res) {
                            if(res.resultado) {
                                tabla.ajax.reload(null, false);
                                
                                var modal = bootstrap.Modal.getInstance(document.getElementById('modalLocalTipo'));
                                modal.hide();
                                
                                $('#formLocalTipo')[0].reset();
                                form.classList.remove('was-validated');
                                
                                Swal.fire({
                                    icon: "success",
                                    title: "¡Éxito!",
                                    text: id ? "Tipo de sucursal actualizado correctamente" : "Tipo de sucursal creado correctamente",
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