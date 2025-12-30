<?php
// Configuración de la página
$pageTitle = "Gestión de Sucursales";
$currentPage = 'paginas';
$modudo_idx = 2;

// Definir constante para rutas
define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));

// Incluir header
require_once ROOT_PATH . '/templates/adminlte/header1.php';
// Incluir header
//require_once '../../templates/adminlte/header.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Sucursales</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Sucursales</li>
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
                                        <button class="btn btn-primary mb-3" id="btnNuevo">Nuevo sucursal</button>
                                        <table id="tablaLocales" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Empresa</th>
                                                    <th>Tipo</th>
                                                    <th>Nombre</th>
                                                    <th>Localidad</th>
                                                    <th>Dirección</th>
                                                    <th>Teléfono</th>
                                                    <th>Email</th>
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
            <div class="modal fade" id="modalLocal" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">sucursal</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formLocal">
                                <input type="hidden" id="sucursal_id" name="sucursal_id" />
                                
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label>Empresa *</label>
                                        <select class="form-control" id="empresa_id" name="empresa_id" required>
                                            <option value="">-- Seleccionar empresa --</option>
                                        </select>
                                        <div class="invalid-feedback">La empresa es obligatoria</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Tipo de sucursal *</label>
                                        <select class="form-control" id="sucursal_tipo_id" name="sucursal_tipo_id" required>
                                            <option value="">-- Seleccionar tipo --</option>
                                        </select>
                                        <div class="invalid-feedback">El tipo de sucursal es obligatorio</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Nombre *</label>
                                        <input type="text" class="form-control" id="sucursal_nombre" name="sucursal_nombre" required/>
                                        <div class="invalid-feedback">El nombre es obligatorio</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Localidad</label>
                                        <select class="form-control" id="localidad_id" name="localidad_id">
                                            <option value="">-- Seleccionar localidad --</option>
                                        </select>
                                    </div>
                                    <div class="col-md-12">
                                        <label>Descripción</label>
                                        <textarea class="form-control" id="descripcion" name="descripcion" rows="3"></textarea>
                                    </div>
                                    <div class="col-md-12">
                                        <label>Dirección</label>
                                        <textarea class="form-control" id="direccion" name="direccion" rows="2"></textarea>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Teléfono</label>
                                        <input type="text" class="form-control" id="telefono" name="telefono"/>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Email</label>
                                        <input type="email" class="form-control" id="email" name="email"/>
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
                var tabla = $('#tablaLocales').DataTable({
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
                        url: 'sucursales_ajax.php',
                        type: 'GET',
                        data: {accion: 'listar'},
                        dataSrc: ''
                    },
                    language: {
                        "search": "Buscar:",
                        "searchPlaceholder": "Buscar sucursales...",
                        "lengthMenu": "Mostrar _MENU_ registros por página",
                        "zeroRecords": "No se encontraron sucursales",
                        "info": "Mostrando _START_ a _END_ de _TOTAL_ sucursales",
                        "infoEmpty": "Mostrando 0 a 0 de 0 sucursales",
                        "infoFiltered": "(filtrado de _MAX_ sucursales totales)",
                        "paginate": {
                            "first": "Primero",
                            "last": "Último",
                            "next": "Siguiente",
                            "previous": "Anterior"
                        }
                    },
                    columns: [
                        { data: 'sucursal_id' },
                        { data: 'empresa_nombre' },
                        { data: 'sucursal_tipo_nombre' },
                        { data: 'sucursal_nombre' },
                        { 
                            data: 'localidad_nombre',
                            render: function(data) {
                                return data || '-';
                            }
                        },
                        { 
                            data: 'direccion',
                            render: function(data) {
                                return data || '-';
                            }
                        },
                        { 
                            data: 'telefono',
                            render: function(data) {
                                return data || '-';
                            }
                        },
                        { 
                            data: 'email',
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
                                            data-sucursal-id="${data.sucursal_id}" 
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

                // Cargar empresas disponibles
                function cargarEmpresas() {
                    $.get('sucursales_ajax.php', {accion: 'listar_empresas'}, function(empresas) {
                        var select = $('#empresa_id');
                        select.empty().append('<option value="">-- Seleccionar empresa --</option>');
                        
                        $.each(empresas, function(index, empresa) {
                            select.append($('<option>', {
                                value: empresa.empresa_id,
                                text: empresa.empresa
                            }));
                        });
                    }, 'json');
                }

                // Cargar tipos de sucursales disponibles
                function cargarLocalesTipos() {
                    $.get('sucursales_ajax.php', {accion: 'listar_sucursales_tipos'}, function(sucursales_tipos) {
                        var select = $('#sucursal_tipo_id');
                        select.empty().append('<option value="">-- Seleccionar tipo --</option>');
                        
                        $.each(sucursales_tipos, function(index, tipo) {
                            select.append($('<option>', {
                                value: tipo.sucursal_tipo_id,
                                text: tipo.sucursal_tipo
                            }));
                        });
                    }, 'json');
                }

                // Cargar localidades disponibles
                function cargarLocalidades() {
                    $.get('sucursales_ajax.php', {accion: 'listar_localidades'}, function(localidades) {
                        var select = $('#localidad_id');
                        select.empty().append('<option value="">-- Seleccionar localidad --</option>');
                        
                        $.each(localidades, function(index, localidad) {
                            select.append($('<option>', {
                                value: localidad.localidad_id,
                                text: localidad.localidad
                            }));
                        });
                    }, 'json');
                }

                // Manejar el cambio de estado con el interruptor
                $(document).on('change', '.toggle-estado', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var localId = $(this).data('sucursal-id');
                    var isChecked = $(this).is(':checked');
                    var nuevoEstado = isChecked ? 1 : 0;
                    var accionTexto = nuevoEstado == 1 ? 'activar' : 'desactivar';                    
                   
                    Swal.fire({
                        title: `¿${nuevoEstado == 1 ? 'Activar' : 'Desactivar'} sucursal?`,
                        text: `Está a punto de ${accionTexto} este sucursal`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: `Sí, ${accionTexto}`,
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.get('sucursales_ajax.php', {
                                accion: 'cambiar_estado', 
                                sucursal_id: localId,
                                nuevo_estado: nuevoEstado
                            }, function(res){
                                if(res.resultado){
                                    tabla.ajax.reload();
                                    Swal.fire({
                                        icon: "success",
                                        title: "¡Éxito!",
                                        text: `sucursal ${accionTexto}do correctamente`,
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                } else {
                                    // Revertir el cambio visual si hay error
                                    $(this).prop('checked', !isChecked);
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: res.error || `Error al ${accionTexto} el sucursal`
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
                    $('#formLocal')[0].reset();
                    $('#sucursal_id').val('');
                    $('#modalLabel').text('Nuevo sucursal');
                    $('#tabla_estado_registro_id').val('1');
                    cargarEmpresas();
                    cargarLocalesTipos();
                    cargarLocalidades();
                    var modal = new bootstrap.Modal(document.getElementById('modalLocal'));
                    modal.show();
                });

                $('#tablaLocales tbody').on('click', '.btnEditar', function(){
                    var data = tabla.row($(this).parents('tr')).data();
                    // Solo permitir editar si está activo
                    if (data.tabla_estado_registro_id != 1) {
                        Swal.fire({
                            icon: "warning",
                            title: "sucursal inactivo",
                            text: "No se puede editar un sucursal inactivo. Active el sucursal primero.",
                            showConfirmButton: false,
                            timer: 2000
                        });
                        return false;
                    }
                    
                    $.get('sucursales_ajax.php', {accion: 'obtener', sucursal_id: data.sucursal_id}, function(res){
                        if(res){
                            $('#sucursal_id').val(res.sucursal_id);
                            $('#sucursal_nombre').val(res.sucursal_nombre);
                            $('#descripcion').val(res.descripcion);
                            $('#direccion').val(res.direccion);
                            $('#telefono').val(res.telefono);
                            $('#email').val(res.email);
                            $('#usuario_creacion_id').val(res.usuario_creacion_id);
                            $('#tabla_estado_registro_id').val(res.tabla_estado_registro_id);
                            
                            // Cargar empresas, tipos y localidades, y seleccionar los correctos
                            cargarEmpresas();
                            cargarLocalesTipos();
                            cargarLocalidades();
                            setTimeout(function() {
                                $('#empresa_id').val(res.empresa_id);
                                $('#sucursal_tipo_id').val(res.sucursal_tipo_id);
                                $('#localidad_id').val(res.localidad_id);
                            }, 300);
                            
                            $('#modalLabel').text('Editar sucursal');
                            var modal = new bootstrap.Modal(document.getElementById('modalLocal'));
                            modal.show();
                        } else {
                            alert('Error al obtener datos');
                        }
                    }, 'json');
                });

                // Eliminar sucursal
                $('#tablaLocales tbody').on('click', '.btnEliminar', function(){
                    var data = tabla.row($(this).parents('tr')).data();
                    
                    Swal.fire({
                        title: '¿Eliminar sucursal?',
                        text: "Esta acción no se puede deshacer",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.get('sucursales_ajax.php', {
                                accion: 'eliminar', 
                                sucursal_id: data.sucursal_id
                            }, function(res){
                                if(res.resultado){
                                    tabla.ajax.reload();
                                    Swal.fire({
                                        icon: "success",
                                        title: "¡Éxito!",
                                        text: "sucursal eliminado correctamente",
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                } else {
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: res.error || "Error al eliminar el sucursal"
                                    });
                                }
                            }, 'json');
                        }
                    });
                });

                $('#btnGuardar').click(function(){
                    var form = document.getElementById('formLocal');
                    
                    if (!form.checkValidity()) {
                        form.classList.add('was-validated');
                        return false;
                    }
                    
                    var id = $('#sucursal_id').val();
                    var accion = id ? 'editar' : 'agregar';
                    var formData = {
                        accion: accion,
                        sucursal_id: id,
                        empresa_id: $('#empresa_id').val(),
                        sucursal_tipo_id: $('#sucursal_tipo_id').val(),
                        sucursal_nombre: $('#sucursal_nombre').val(),
                        descripcion: $('#descripcion').val(),
                        localidad_id: $('#localidad_id').val(),
                        direccion: $('#direccion').val(),
                        telefono: $('#telefono').val(),
                        email: $('#email').val(),
                        usuario_creacion_id: $('#usuario_creacion_id').val(),
                        tabla_estado_registro_id: $('#tabla_estado_registro_id').val()
                    };

                    $.ajax({
                        url: 'sucursales_ajax.php',
                        type: 'POST',
                        data: formData,
                        dataType: 'json',
                        success: function(res) {
                            if(res.resultado) {
                                tabla.ajax.reload(null, false);
                                
                                var modal = bootstrap.Modal.getInstance(document.getElementById('modalLocal'));
                                modal.hide();
                                
                                $('#formLocal')[0].reset();
                                form.classList.remove('was-validated');
                                
                                Swal.fire({
                                    icon: "success",
                                    title: "¡Éxito!",
                                    text: id ? "sucursal actualizado correctamente" : "sucursal creado correctamente",
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