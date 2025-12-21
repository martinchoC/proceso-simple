<?php
// Configuración de la página
$pageTitle = "Gestión de Iconos";
$currentPage = 'iconos';
$modudo_idx = 1;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';

?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Imágenes</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Imágenes</li>
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
                                        <button class="btn btn-primary mb-3" id="btnNuevo">Nueva Imagen</button>
                                        <table id="tablaImagenes" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Miniatura</th>
                                                    <th>Nombre</th>
                                                    <th>Ruta</th>
                                                    <th>Tipo</th>
                                                    <th>Tamaño (KB)</th>
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
            <div class="modal fade" id="modalImagen" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Imagen</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formImagen" enctype="multipart/form-data">
                                <input type="hidden" id="imagen_id" name="imagen_id" />
                                
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label>Nombre *</label>
                                        <input type="text" class="form-control" id="imagen_nombre" name="imagen_nombre" required/>
                                        <div class="invalid-feedback">El nombre es obligatorio</div>
                                    </div>
                                    <div class="col-md-12">
                                        <label>Seleccionar imagen *</label>
                                        <input type="file" class="form-control" id="archivo_imagen" name="archivo_imagen" accept="image/*" required/>
                                        <div class="invalid-feedback">Debe seleccionar una imagen</div>
                                        <div id="vista_previa" class="mt-3 text-center" style="display: none;">
                                            <img id="preview_img" src="#" alt="Vista previa" class="img-fluid rounded" style="max-height: 200px;">
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
                // Configuración de DataTable
                var tabla = $('#tablaImagenes').DataTable({
                    dom: '<"row"<"col-md-6"l><"col-md-6"fB>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
                    buttons: [
                        {
                            extend: 'excelHtml5',
                            text: '<i class="fas fa-file-excel"></i> Excel',
                            titleAttr: 'Exportar a Excel',
                            className: 'btn btn-success btn-sm me-2',
                            exportOptions: { columns: [0, 2, 3, 4, 5, 6, 7, 8] } // Excluir la columna de miniatura
                        },
                        {
                            extend: 'pdfHtml5',
                            text: '<i class="fas fa-file-pdf"></i> PDF',
                            titleAttr: 'Exportar a PDF',
                            className: 'btn btn-danger btn-sm',
                            orientation: 'portrait',
                            pageSize: 'A4',
                            exportOptions: { columns: [0, 2, 3, 4, 5, 6, 7, 8] } // Excluir la columna de miniatura
                        }
                    ],
                    ajax: {
                        url: 'imagenes_ajax.php',
                        type: 'GET',
                        data: {accion: 'listar'},
                        dataSrc: ''
                    },
                    language: {
                        "search": "Buscar:",
                        "searchPlaceholder": "Buscar imágenes...",
                        "lengthMenu": "Mostrar _MENU_ registros por página",
                        "zeroRecords": "No se encontraron imágenes",
                        "info": "Mostrando _START_ a _END_ de _TOTAL_ imágenes",
                        "infoEmpty": "Mostrando 0 a 0 de 0 imágenes",
                        "infoFiltered": "(filtrado de _MAX_ imágenes totales)",
                        "paginate": {
                            "first": "Primero",
                            "last": "Último",
                            "next": "Siguiente",
                            "previous": "Anterior"
                        }
                    },
                    columns: [
                        { data: 'imagen_id' },
                        {
                            data: 'imagen_ruta',
                            orderable: false,
                            searchable: false,
                            className: "text-center",
                            render: function(data) {
                                return `<img src="${data}" alt="Miniatura" class="img-thumbnail" style="max-width: 50px; max-height: 50px;">`;
                            }
                        },
                        { data: 'imagen_nombre' },
                        { 
                            data: 'imagen_ruta',
                            render: function(data) {
                                return `<a href="${data}" target="_blank" title="Ver imagen">${data.length > 30 ? data.substring(0, 30) + '...' : data}</a>`;
                            }
                        },
                        { data: 'imagen_tipo' },
                        { 
                            data: 'imagen_tamanio',
                            render: function(data) {
                                return (data / 1024).toFixed(2) + ' KB';
                            }
                        },
                        { 
                            data: 'imagen_creacion',
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
                                var estadoTexto = data.estado_registro_id == 1 ? 
                                    '<span class="badge bg-success">Activo</span>' : 
                                    '<span class="badge bg-secondary">Inactivo</span>';
                                
                                var botonEstado = 
                                    `<div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input toggle-estado"
                                            type="checkbox" 
                                            data-imagen-id="${data.imagen_id}" 
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
                        // Cambiar color de fondo según el estado
                        if (data.estado_registro_id != 1) {
                            $(row).addClass('table-secondary');
                            $(row).find('td').css('color', '#6c757d');
                        }
                    }
                });

                // Vista previa de imagen
                $('#archivo_imagen').change(function(){
                    var input = this;
                    var preview = $('#preview_img');
                    var container = $('#vista_previa');
                    
                    if (input.files && input.files[0]) {
                        var reader = new FileReader();
                        
                        reader.onload = function(e) {
                            preview.attr('src', e.target.result);
                            container.show();
                        }
                        
                        reader.readAsDataURL(input.files[0]);
                    } else {
                        container.hide();
                    }
                });

                // Manejar el cambio de estado con el interruptor
                $(document).on('change', '.toggle-estado', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var imagenId = $(this).data('imagen-id');
                    var isChecked = $(this).is(':checked');
                    var nuevoEstado = isChecked ? 1 : 0;
                    var accionTexto = nuevoEstado == 1 ? 'activar' : 'desactivar';                    
                   
                    Swal.fire({
                        title: `¿${nuevoEstado == 1 ? 'Activar' : 'Desactivar'} imagen?`,
                        text: `Está a punto de ${accionTexto} esta imagen`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: `Sí, ${accionTexto}`,
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.get('imagenes_ajax.php', {
                                accion: 'cambiar_estado', 
                                imagen_id: imagenId,
                                nuevo_estado: nuevoEstado
                            }, function(res){
                                if(res.resultado){
                                    tabla.ajax.reload();
                                    Swal.fire({
                                        icon: "success",
                                        title: "¡Éxito!",
                                        text: `Imagen ${accionTexto}da correctamente`,
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                } else {
                                    // Revertir el cambio visual si hay error
                                    $(this).prop('checked', !isChecked);
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: res.error || `Error al ${accionTexto} la imagen`
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
                    $('#formImagen')[0].reset();
                    $('#imagen_id').val('');
                    $('#vista_previa').hide();
                    $('#modalLabel').text('Nueva Imagen');
                    $('#archivo_imagen').prop('required', true);
                    var modal = new bootstrap.Modal(document.getElementById('modalImagen'));
                    modal.show();
                });

                $('#tablaImagenes tbody').on('click', '.btnEditar', function(){
                    var data = tabla.row($(this).parents('tr')).data();
                    // Solo permitir editar si está activo
                    if (data.estado_registro_id != 1) {
                        Swal.fire({
                            icon: "warning",
                            title: "Imagen inactiva",
                            text: "No se puede editar una imagen inactiva. Active la imagen primero.",
                            showConfirmButton: false,
                            timer: 2000
                        });
                        return false;
                    }
                    
                    $.get('imagenes_ajax.php', {accion: 'obtener', imagen_id: data.imagen_id}, function(res){
                        if(res){
                            $('#imagen_id').val(res.imagen_id);
                            $('#imagen_nombre').val(res.imagen_nombre);
                            
                            // Mostrar vista previa de la imagen existente
                            if (res.imagen_ruta) {
                                $('#preview_img').attr('src', res.imagen_ruta);
                                $('#vista_previa').show();
                            }
                            
                            $('#archivo_imagen').prop('required', false);
                            $('#modalLabel').text('Editar Imagen');
                            var modal = new bootstrap.Modal(document.getElementById('modalImagen'));
                            modal.show();
                        } else {
                            alert('Error al obtener datos');
                        }
                    }, 'json');
                });

                // Eliminar imagen
                $('#tablaImagenes tbody').on('click', '.btnEliminar', function(){
                    var data = tabla.row($(this).parents('tr')).data();
                    
                    Swal.fire({
                        title: '¿Eliminar imagen?',
                        text: "Esta acción no se puede deshacer",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.get('imagenes_ajax.php', {
                                accion: 'eliminar', 
                                imagen_id: data.imagen_id
                            }, function(res){
                                if(res.resultado){
                                    tabla.ajax.reload();
                                    Swal.fire({
                                        icon: "success",
                                        title: "¡Éxito!",
                                        text: "Imagen eliminada correctamente",
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                } else {
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: res.error || "Error al eliminar la imagen"
                                    });
                                }
                            }, 'json');
                        }
                    });
                });

                $('#btnGuardar').click(function(){
                    var form = document.getElementById('formImagen');
                    
                    if (!form.checkValidity()) {
                        form.classList.add('was-validated');
                        return false;
                    }
                    
                    var id = $('#imagen_id').val();
                    var accion = id ? 'editar' : 'agregar';
                    
                    // Crear FormData para enviar archivos
                    var formData = new FormData();
                    formData.append('accion', accion);
                    formData.append('imagen_id', id);
                    formData.append('imagen_nombre', $('#imagen_nombre').val());
                    
                    // Agregar archivo solo si se seleccionó uno
                    var archivoInput = $('#archivo_imagen')[0];
                    if (archivoInput.files.length > 0) {
                        formData.append('archivo_imagen', archivoInput.files[0]);
                    }

                    $.ajax({
                        url: 'imagenes_ajax.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function(res) {
                            if(res.resultado) {
                                tabla.ajax.reload(null, false);
                                
                                var modal = bootstrap.Modal.getInstance(document.getElementById('modalImagen'));
                                modal.hide();
                                
                                $('#formImagen')[0].reset();
                                form.classList.remove('was-validated');
                                $('#vista_previa').hide();
                                
                                Swal.fire({
                                    icon: "success",
                                    title: "¡Éxito!",
                                    text: id ? "Imagen actualizada correctamente" : "Imagen creada correctamente",
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
            
            /* Estilo para las miniaturas */
            .img-thumbnail {
                object-fit: cover;
            }
            </style>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

            <?php
            require_once ROOT_PATH . '/templates/adminlte/footer1.php';
            ?>
            </body>
            </html>