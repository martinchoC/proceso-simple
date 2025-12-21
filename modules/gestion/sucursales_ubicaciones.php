<?php
// Configuración de la página
$pageTitle = "Gestión de Ubicaciones de Locales";
$currentPage = 'sucursales_ubicaciones';
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
                <div class="col-sm-6"><h3 class="mb-0">Ubicaciones de Locales</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Ubicaciones</li>
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
                                        <button class="btn btn-primary mb-3" id="btnNuevo">Nueva Ubicación</button>
                                        <table id="tablaUbicaciones" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>sucursal</th>
                                                    <th>Sección</th>
                                                    <th>Estantería</th>
                                                    <th>Estante</th>
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
            <div class="modal fade" id="modalUbicacion" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Ubicación de sucursal</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formUbicacion">
                                <input type="hidden" id="sucursal_ubicacion_id" name="sucursal_ubicacion_id" />
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label>sucursal *</label>
                                        <select class="form-control" id="sucursal_id" name="sucursal_id" required>
                                            <option value="">-- Seleccionar sucursal --</option>
                                        </select>
                                        <div class="invalid-feedback">El sucursal es obligatorio</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Sección *</label>
                                        <input type="text" class="form-control" id="seccion" name="seccion" required placeholder="Ej: A, B, C, Zona Norte"/>
                                        <div class="invalid-feedback">La sección es obligatoria</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Estantería *</label>
                                        <input type="text" class="form-control" id="estanteria" name="estanteria" required placeholder="Ej: Rack 1, Rack 2"/>
                                        <div class="invalid-feedback">La estantería es obligatoria</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Estante *</label>
                                        <input type="text" class="form-control" id="estante" name="estante" required placeholder="Ej: Nivel 1, Nivel 2"/>
                                        <div class="invalid-feedback">El estante es obligatorio</div>
                                    </div>
                                    <div class="col-md-12">
                                        <label>Descripción</label>
                                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3" placeholder="Detalles adicionales de la ubicación"></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Usuario Creación ID</label>
                                        <input type="number" class="form-control" id="usuario_creacion_id" name="usuario_creacion_id"/>
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
                var tabla = $('#tablaUbicaciones').DataTable({
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
                        url: 'sucursales_ubicaciones_ajax.php',
                        type: 'GET',
                        data: {accion: 'listar'},
                        dataSrc: ''
                    },
                    language: {
                        "search": "Buscar:",
                        "searchPlaceholder": "Buscar ubicaciones...",
                        "lengthMenu": "Mostrar _MENU_ registros por página",
                        "zeroRecords": "No se encontraron ubicaciones",
                        "info": "Mostrando _START_ a _END_ de _TOTAL_ ubicaciones",
                        "infoEmpty": "Mostrando 0 a 0 de 0 ubicaciones",
                        "infoFiltered": "(filtrado de _MAX_ ubicaciones totales)",
                        "paginate": {
                            "first": "Primero",
                            "last": "Último",
                            "next": "Siguiente",
                            "previous": "Anterior"
                        }
                    },
                    columns: [
                        { data: 'sucursal_ubicacion_id' },
                        { data: 'sucursal_nombre' },
                        { data: 'seccion' },
                        { data: 'estanteria' },
                        { data: 'estante' },
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
                                var estadoTexto = data.estado_registro_id == 1 ? 
                                    '<span class="badge bg-success">Activo</span>' : 
                                    '<span class="badge bg-secondary">Inactivo</span>';
                                
                                var botonEstado = 
                                    `<div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input toggle-estado"
                                            type="checkbox" 
                                            data-sucursal-ubicacion-id="${data.sucursal_ubicacion_id}" 
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
                    order: [[1, 'asc'], [2, 'asc'], [3, 'asc'], [4, 'asc']], // Ordenar por sucursal, Sección, Estantería, Estante
                    columnDefs: [
                        { orderable: false, targets: [0, 5, 6, 7, 8] } // Columnas que no se pueden ordenar
                    ],
                    createdRow: function(row, data, dataIndex) {
                        // Cambiar color de fondo según el estado
                        if (data.estado_registro_id != 1) {
                            $(row).addClass('table-secondary');
                            $(row).find('td').css('color', '#6c757d');
                        }
                    }
                });

                // Cargar sucursales disponibles
                function cargarLocales() {
                    $.get('sucursales_ubicaciones_ajax.php', {accion: 'listar_sucursales'}, function(sucursales) {
                        var select = $('#sucursal_id');
                        select.empty().append('<option value="">-- Seleccionar sucursal --</option>');
                        
                        $.each(sucursales, function(index, sucursal) {
                            select.append($('<option>', {
                                value: sucursal.sucursal_id,
                                text: sucursal.sucursal_nombre
                            }));
                        });
                    }, 'json');
                }

                // Manejar el cambio de estado con el interruptor
                $(document).on('change', '.toggle-estado', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var ubicacionId = $(this).data('sucursal-ubicacion-id');
                    var isChecked = $(this).is(':checked');
                    var nuevoEstado = isChecked ? 1 : 0;
                    var accionTexto = nuevoEstado == 1 ? 'activar' : 'desactivar';                    
                   
                    Swal.fire({
                        title: `¿${nuevoEstado == 1 ? 'Activar' : 'Desactivar'} ubicación?`,
                        text: `Está a punto de ${accionTexto} esta ubicación`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: `Sí, ${accionTexto}`,
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.get('sucursales_ubicaciones_ajax.php', {
                                accion: 'cambiar_estado', 
                                sucursal_ubicacion_id: ubicacionId,
                                nuevo_estado: nuevoEstado
                            }, function(res){
                                if(res.resultado){
                                    tabla.ajax.reload();
                                    Swal.fire({
                                        icon: "success",
                                        title: "¡Éxito!",
                                        text: `Ubicación ${accionTexto}da correctamente`,
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                } else {
                                    // Revertir el cambio visual si hay error
                                    $(this).prop('checked', !isChecked);
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: res.error || `Error al ${accionTexto} la ubicación`
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
                    $('#formUbicacion')[0].reset();
                    $('#sucursal_ubicacion_id').val('');
                    $('#modalLabel').text('Nueva Ubicación');
                    $('#estado_registro_id').val('1');
                    cargarLocales();
                    var modal = new bootstrap.Modal(document.getElementById('modalUbicacion'));
                    modal.show();
                });

                $('#tablaUbicaciones tbody').on('click', '.btnEditar', function(){
                    var data = tabla.row($(this).parents('tr')).data();
                    // Solo permitir editar si está activo
                    if (data.estado_registro_id != 1) {
                        Swal.fire({
                            icon: "warning",
                            title: "Ubicación inactiva",
                            text: "No se puede editar una ubicación inactiva. Active la ubicación primero.",
                            showConfirmButton: false,
                            timer: 2000
                        });
                        return false;
                    }
                    
                    $.get('sucursales_ubicaciones_ajax.php', {accion: 'obtener', sucursal_ubicacion_id: data.sucursal_ubicacion_id}, function(res){
                        if(res){
                            $('#sucursal_ubicacion_id').val(res.sucursal_ubicacion_id);
                            $('#seccion').val(res.seccion);
                            $('#estanteria').val(res.estanteria);
                            $('#estante').val(res.estante);
                            $('#descripcion').val(res.descripcion);
                            $('#usuario_creacion_id').val(res.usuario_creacion_id);
                            $('#estado_registro_id').val(res.estado_registro_id);
                            
                            // Cargar sucursales y seleccionar el correcto
                            cargarLocales();
                            setTimeout(function() {
                                $('#sucursal_id').val(res.sucursal_id);
                            }, 300);
                            
                            $('#modalLabel').text('Editar Ubicación');
                            var modal = new bootstrap.Modal(document.getElementById('modalUbicacion'));
                            modal.show();
                        } else {
                            alert('Error al obtener datos');
                        }
                    }, 'json');
                });

                // Eliminar ubicación
                $('#tablaUbicaciones tbody').on('click', '.btnEliminar', function(){
                    var data = tabla.row($(this).parents('tr')).data();
                    
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
                            $.get('sucursales_ubicaciones_ajax.php', {
                                accion: 'eliminar', 
                                sucursal_ubicacion_id: data.sucursal_ubicacion_id
                            }, function(res){
                                if(res.resultado){
                                    tabla.ajax.reload();
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
                                        text: res.error || "Error al eliminar la ubicación"
                                    });
                                }
                            }, 'json');
                        }
                    });
                });

                $('#btnGuardar').click(function(){
                    var form = document.getElementById('formUbicacion');
                    
                    if (!form.checkValidity()) {
                        form.classList.add('was-validated');
                        return false;
                    }
                    
                    var id = $('#sucursal_ubicacion_id').val();
                    var accion = id ? 'editar' : 'agregar';
                    var formData = {
                        accion: accion,
                        sucursal_ubicacion_id: id,
                        sucursal_id: $('#sucursal_id').val(),
                        seccion: $('#seccion').val(),
                        estanteria: $('#estanteria').val(),
                        estante: $('#estante').val(),
                        descripcion: $('#descripcion').val(),
                        usuario_creacion_id: $('#usuario_creacion_id').val(),
                        estado_registro_id: $('#estado_registro_id').val()
                    };

                    $.ajax({
                        url: 'sucursales_ubicaciones_ajax.php',
                        type: 'POST',
                        data: formData,
                        dataType: 'json',
                        success: function(res) {
                            if(res.resultado) {
                                tabla.ajax.reload(null, false);
                                
                                var modal = bootstrap.Modal.getInstance(document.getElementById('modalUbicacion'));
                                modal.hide();
                                
                                $('#formUbicacion')[0].reset();
                                form.classList.remove('was-validated');
                                
                                Swal.fire({
                                    icon: "success",
                                    title: "¡Éxito!",
                                    text: id ? "Ubicación actualizada correctamente" : "Ubicación creada correctamente",
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