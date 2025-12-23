<?php
// Configuración de la página
$pageTitle = "Gestión de Tipos de Tablas";
$currentPage = 'tablas_tipos';
$modudo_idx = 1;

define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Tipos de Tablas</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Tipos de Tablas</li>
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
                                        <button class="btn btn-primary mb-3" id="btnNuevo">Nuevo Tipo de Tabla</button>
                                        <table id="tablaTipos" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Tipo de Tabla</th>
                                                    <th>Cantidad Estados</th>
                                                    <th>Estado</th>
                                                    <th>Fecha Creación</th>
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

            <!-- Modal Tipo de Tabla -->
            <div class="modal fade" id="modalTipoTabla" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Tipo de Tabla</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formTipoTabla">
                                <input type="hidden" id="tabla_tipo_id" name="tabla_tipo_id" />
                                
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label>Tipo de Tabla *</label>
                                        <input type="text" class="form-control" id="tabla_tipo" name="tabla_tipo" required maxlength="50"/>
                                        <div class="invalid-feedback">El tipo de tabla es obligatorio</div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button id="btnGuardarTipo" class="btn btn-success">Guardar</button>
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Estados del Tipo -->
            <div class="modal fade" id="modalEstadosTipo" tabindex="-1" aria-labelledby="modalEstadosLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalEstadosLabel">Estados del Tipo de Tabla: <span id="nombreTipoTabla"></span></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row mb-3">
                                <div class="col-12">
                                    <button class="btn btn-sm btn-success" id="btnAgregarEstado">
                                        <i class="fas fa-plus"></i> Agregar Estado
                                    </button>
                                </div>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-bordered" id="tablaEstados">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Estado</th>
                                            <th>Orden</th>
                                            <th>Estado Inicial</th>
                                            <th>Estado</th>
                                            <th>Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="cuerpoEstados">
                                        <!-- Estados se cargarán dinámicamente -->
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Agregar/Editar Estado -->
            <div class="modal fade" id="modalEstado" tabindex="-1" aria-labelledby="modalEstadoLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalEstadoLabel">Estado del Tipo</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formEstado">
                                <input type="hidden" id="tabla_tipo_estado_id" name="tabla_tipo_estado_id" />
                                <input type="hidden" id="tabla_tipo_id_estado" name="tabla_tipo_id" />
                                
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label>Estado del Registro *</label>
                                        <select class="form-control" id="estado_registro_id" name="estado_registro_id" required>
                                            <option value="">Seleccionar estado...</option>
                                            <!-- Opciones se cargarán dinámicamente -->
                                        </select>
                                        <div class="invalid-feedback">Debe seleccionar un estado</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Orden *</label>
                                        <input type="number" class="form-control" id="orden" name="orden" min="1" required/>
                                        <div class="invalid-feedback">El orden es obligatorio</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Estado Inicial</label>
                                        <div class="form-check mt-2">
                                            <input class="form-check-input" type="checkbox" id="es_inicial" name="es_inicial" value="1">
                                            <label class="form-check-label" for="es_inicial">
                                                Marcar como estado inicial
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button id="btnGuardarEstado" class="btn btn-success">Guardar</button>
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            $(document).ready(function(){
                var tablaTipoActual = null;
                var tablaTipos = $('#tablaTipos').DataTable({
                    dom: '<"row"<"col-md-6"l><"col-md-6"fB>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
                    buttons: [
                        {
                            extend: 'excelHtml5',
                            text: '<i class="fas fa-file-excel"></i> Excel',
                            titleAttr: 'Exportar a Excel',
                            className: 'btn btn-success btn-sm me-2'
                        },
                        {
                            extend: 'pdfHtml5',
                            text: '<i class="fas fa-file-pdf"></i> PDF',
                            titleAttr: 'Exportar a PDF',
                            className: 'btn btn-danger btn-sm',
                            orientation: 'portrait',
                            pageSize: 'A4'
                        }
                    ],
                    ajax: {
                        url: 'tablas_tipos_ajax.php',
                        type: 'GET',
                        data: {accion: 'listar_tipos'},
                        dataSrc: ''
                    },
                    language: {
                        "search": "Buscar:",
                        "searchPlaceholder": "Buscar tipos...",
                        "lengthMenu": "Mostrar _MENU_ registros por página",
                        "zeroRecords": "No se encontraron tipos de tabla",
                        "info": "Mostrando _START_ a _END_ de _TOTAL_ tipos",
                        "infoEmpty": "Mostrando 0 a 0 de 0 tipos",
                        "infoFiltered": "(filtrado de _MAX_ tipos totales)",
                        "paginate": {
                            "first": "Primero",
                            "last": "Último",
                            "next": "Siguiente",
                            "previous": "Anterior"
                        }
                    },
                    columns: [
                        { data: 'tabla_tipo_id' },
                        { data: 'tabla_tipo' },
                        { 
                            data: null,
                            render: function(data) {
                                return data.cantidad_estados || 0;
                            }
                        },
                        {
                            data: null,
                            orderable: false,
                            className: "text-center",
                            render: function(data) {
                                var estadoTexto = data.tabla_estado_registro_id == 1 ? 
                                    '<span class="badge bg-success">Activo</span>' : 
                                    '<span class="badge bg-secondary">Inactivo</span>';
                                
                                var botonEstado = 
                                    `<div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input toggle-estado-tipo"
                                            type="checkbox" 
                                            data-tabla-tipo-id="${data.tabla_tipo_id}" 
                                            ${data.tabla_estado_registro_id == 1 ? 'checked' : ''}>
                                    </div>`;
                                
                                return `<div class="d-flex flex-column align-items-center">                                            
                                            ${botonEstado}
                                        </div>`;
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
                                var botonEstados = 
                                    `<button class="btn btn-sm btn-info btnEstados" title="Gestionar Estados">
                                        <i class="fas fa-list-check"></i>
                                    </button>`;
                                
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
                                
                                return `<div class="d-flex align-items-center justify-content-center gap-2">${botonEstados} ${botonEditar} ${botonEliminar}</div>`;
                            }
                        }
                    ],
                    createdRow: function(row, data, dataIndex) {
                        if (data.tabla_estado_registro_id != 1) {
                            $(row).addClass('table-secondary');
                            $(row).find('td').css('color', '#6c757d');
                        }
                    }
                });

                // Función para cargar todos los estados en el select
                function cargarTodosEstadosEnSelect(estadoSeleccionado = 0) {
                    $.get('tablas_tipos_ajax.php', {accion: 'todos_estados'}, function(estados){
                        var html = '<option value="">Seleccionar estado...</option>';
                        if(estados && estados.length > 0) {
                            estados.forEach(function(estado) {
                                var selected = estado.estado_registro_id == estadoSeleccionado ? 'selected' : '';
                                html += `<option value="${estado.estado_registro_id}" ${selected}>${estado.estado_registro}</option>`;
                            });
                        }
                        $('#estado_registro_id').html(html);
                    }, 'json');
                }

                // Cambiar estado del tipo
                $(document).on('change', '.toggle-estado-tipo', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var tipoId = $(this).data('tabla-tipo-id');
                    var isChecked = $(this).is(':checked');
                    var nuevoEstado = isChecked ? 1 : 0;
                    var accionTexto = nuevoEstado == 1 ? 'activar' : 'desactivar';
                    
                    Swal.fire({
                        title: `¿${nuevoEstado == 1 ? 'Activar' : 'Desactivar'} tipo?`,
                        text: `Está a punto de ${accionTexto} este tipo de tabla`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: `Sí, ${accionTexto}`,
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.get('tablas_tipos_ajax.php', {
                                accion: 'cambiar_estado_tipo', 
                                tabla_tipo_id: tipoId,
                                nuevo_estado: nuevoEstado
                            }, function(res){
                                if(res.resultado){
                                    tablaTipos.ajax.reload();
                                    Swal.fire({
                                        icon: "success",
                                        title: "¡Éxito!",
                                        text: `Tipo ${accionTexto}do correctamente`,
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                } else {
                                    $(this).prop('checked', !isChecked);
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: res.error || `Error al ${accionTexto} el tipo`
                                    });
                                }
                            }, 'json');
                        } else {
                            $(this).prop('checked', !isChecked);
                        }
                    });
                });

                // Nuevo tipo
                $('#btnNuevo').click(function(){
                    $('#formTipoTabla')[0].reset();
                    $('#tabla_tipo_id').val('');
                    $('#modalLabel').text('Nuevo Tipo de Tabla');
                    var modal = new bootstrap.Modal(document.getElementById('modalTipoTabla'));
                    modal.show();
                });

                // Editar tipo
                $('#tablaTipos tbody').on('click', '.btnEditar', function(){
                    var data = tablaTipos.row($(this).parents('tr')).data();
                    
                    if (data.tabla_estado_registro_id != 1) {
                        Swal.fire({
                            icon: "warning",
                            title: "Tipo inactivo",
                            text: "No se puede editar un tipo inactivo. Active el tipo primero.",
                            showConfirmButton: false,
                            timer: 2000
                        });
                        return false;
                    }
                    
                    $.get('tablas_tipos_ajax.php', {accion: 'obtener_tipo', tabla_tipo_id: data.tabla_tipo_id}, function(res){
                        if(res){
                            $('#tabla_tipo_id').val(res.tabla_tipo_id);
                            $('#tabla_tipo').val(res.tabla_tipo);
                            $('#modalLabel').text('Editar Tipo de Tabla');
                            var modal = new bootstrap.Modal(document.getElementById('modalTipoTabla'));
                            modal.show();
                        } else {
                            alert('Error al obtener datos');
                        }
                    }, 'json');
                });

                // Eliminar tipo
                $('#tablaTipos tbody').on('click', '.btnEliminar', function(){
                    var data = tablaTipos.row($(this).parents('tr')).data();
                    
                    Swal.fire({
                        title: '¿Eliminar tipo de tabla?',
                        html: `Esta acción eliminará el tipo "<strong>${data.tabla_tipo}</strong>" y todos sus estados asociados.<br><br><strong>Esta acción no se puede deshacer</strong>`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.get('tablas_tipos_ajax.php', {
                                accion: 'eliminar_tipo', 
                                tabla_tipo_id: data.tabla_tipo_id
                            }, function(res){
                                if(res.resultado){
                                    tablaTipos.ajax.reload();
                                    Swal.fire({
                                        icon: "success",
                                        title: "¡Éxito!",
                                        text: "Tipo de tabla eliminado correctamente",
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                } else {
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: res.error || "Error al eliminar el tipo de tabla"
                                    });
                                }
                            }, 'json');
                        }
                    });
                });

                // Gestionar estados
                $('#tablaTipos tbody').on('click', '.btnEstados', function(){
                    var data = tablaTipos.row($(this).parents('tr')).data();
                    tablaTipoActual = data.tabla_tipo_id;
                    $('#nombreTipoTabla').text(data.tabla_tipo);
                    $('#tabla_tipo_id_estado').val(data.tabla_tipo_id);
                    
                    // Cargar estados existentes
                    cargarEstadosTipo(data.tabla_tipo_id);
                    
                    var modal = new bootstrap.Modal(document.getElementById('modalEstadosTipo'));
                    modal.show();
                });

                function cargarEstadosTipo(tipoId) {
                    $.get('tablas_tipos_ajax.php', {accion: 'listar_estados_tipo', tabla_tipo_id: tipoId}, function(res){
                        var html = '';
                        if(res && res.length > 0) {
                            res.forEach(function(estado, index) {
                                html += `
                                <tr>
                                    <td>${index + 1}</td>
                                    <td>${estado.estado_registro}</td>
                                    <td>${estado.orden}</td>
                                    <td class="text-center">${estado.es_inicial == 1 ? '<i class="fas fa-check text-success"></i>' : ''}</td>
                                    <td class="text-center">${estado.tabla_estado_registro_id == 1 ? 
                                        '<span class="badge bg-success">Activo</span>' : 
                                        '<span class="badge bg-secondary">Inactivo</span>'}</td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-primary btnEditarEstado" 
                                                data-id="${estado.tabla_tipo_estado_id}"
                                                title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-danger btnEliminarEstado" 
                                                data-id="${estado.tabla_tipo_estado_id}"
                                                data-estado="${estado.estado_registro}"
                                                title="Eliminar">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <button class="btn ${estado.tabla_estado_registro_id == 1 ? 'btn-warning' : 'btn-success'} btnToggleEstado" 
                                                data-id="${estado.tabla_tipo_estado_id}"
                                                data-actual="${estado.tabla_estado_registro_id}"
                                                title="${estado.tabla_estado_registro_id == 1 ? 'Desactivar' : 'Activar'}">
                                                <i class="fas fa-power-off"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>`;
                            });
                        } else {
                            html = '<tr><td colspan="6" class="text-center">No hay estados configurados</td></tr>';
                        }
                        $('#cuerpoEstados').html(html);
                    }, 'json');
                }

                // Agregar nuevo estado
                $('#btnAgregarEstado').click(function(){
                    $('#formEstado')[0].reset();
                    $('#tabla_tipo_estado_id').val('');
                    $('#modalEstadoLabel').text('Agregar Estado');
                    cargarTodosEstadosEnSelect();
                    var modal = new bootstrap.Modal(document.getElementById('modalEstado'));
                    modal.show();
                });

                // Editar estado
                $(document).on('click', '.btnEditarEstado', function(){
                    var estadoId = $(this).data('id');
                    
                    $.get('tablas_tipos_ajax.php', {accion: 'obtener_estado', tabla_tipo_estado_id: estadoId}, function(res){
                        if(res){
                            $('#tabla_tipo_estado_id').val(res.tabla_tipo_estado_id);
                            $('#tabla_tipo_id_estado').val(res.tabla_tipo_id);
                            $('#orden').val(res.orden);
                            $('#es_inicial').prop('checked', res.es_inicial == 1);
                            $('#modalEstadoLabel').text('Editar Estado');
                            
                            // Cargar todos los estados y marcar el actual
                            cargarTodosEstadosEnSelect(res.estado_registro_id);
                            
                            var modal = new bootstrap.Modal(document.getElementById('modalEstado'));
                            modal.show();
                        }
                    }, 'json');
                });

                // Eliminar estado
                $(document).on('click', '.btnEliminarEstado', function(){
                    var estadoId = $(this).data('id');
                    var estadoNombre = $(this).data('estado');
                    
                    Swal.fire({
                        title: '¿Eliminar estado?',
                        html: `Esta acción eliminará el estado "<strong>${estadoNombre}</strong>" del tipo.<br><br><strong>Esta acción no se puede deshacer</strong>`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: 'Sí, eliminar',
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.get('tablas_tipos_ajax.php', {
                                accion: 'eliminar_estado', 
                                tabla_tipo_estado_id: estadoId
                            }, function(res){
                                if(res.resultado){
                                    cargarEstadosTipo(tablaTipoActual);
                                    Swal.fire({
                                        icon: "success",
                                        title: "¡Éxito!",
                                        text: "Estado eliminado correctamente",
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                } else {
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: res.error || "Error al eliminar el estado"
                                    });
                                }
                            }, 'json');
                        }
                    });
                });

                // Cambiar estado del estado
                $(document).on('click', '.btnToggleEstado', function(){
                    var estadoId = $(this).data('id');
                    var estadoActual = $(this).data('actual');
                    var nuevoEstado = estadoActual == 1 ? 0 : 1;
                    var accionTexto = nuevoEstado == 1 ? 'activar' : 'desactivar';
                    
                    Swal.fire({
                        title: `¿${nuevoEstado == 1 ? 'Activar' : 'Desactivar'} estado?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#3085d6',
                        cancelButtonColor: '#d33',
                        confirmButtonText: `Sí, ${accionTexto}`,
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.get('tablas_tipos_ajax.php', {
                                accion: 'cambiar_estado_estado', 
                                tabla_tipo_estado_id: estadoId,
                                nuevo_estado: nuevoEstado
                            }, function(res){
                                if(res.resultado){
                                    cargarEstadosTipo(tablaTipoActual);
                                    Swal.fire({
                                        icon: "success",
                                        title: "¡Éxito!",
                                        text: `Estado ${accionTexto}do correctamente`,
                                        showConfirmButton: false,
                                        timer: 1500
                                    });
                                } else {
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: res.error || `Error al ${accionTexto} el estado`
                                    });
                                }
                            }, 'json');
                        }
                    });
                });

                // Guardar tipo
                $('#btnGuardarTipo').click(function(){
                    var form = document.getElementById('formTipoTabla');
                    
                    if (!form.checkValidity()) {
                        form.classList.add('was-validated');
                        return false;
                    }
                    
                    var id = $('#tabla_tipo_id').val();
                    var accion = id ? 'editar_tipo' : 'agregar_tipo';
                    
                    $.ajax({
                        url: 'tablas_tipos_ajax.php',
                        type: 'POST',
                        data: {
                            accion: accion,
                            tabla_tipo_id: id,
                            tabla_tipo: $('#tabla_tipo').val()
                        },
                        dataType: 'json',
                        success: function(res) {
                            if(res.resultado) {
                                tablaTipos.ajax.reload(null, false);
                                var modal = bootstrap.Modal.getInstance(document.getElementById('modalTipoTabla'));
                                modal.hide();
                                $('#formTipoTabla')[0].reset();
                                form.classList.remove('was-validated');
                                Swal.fire({
                                    icon: "success",
                                    title: "¡Éxito!",
                                    text: id ? "Tipo actualizado correctamente" : "Tipo creado correctamente",
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

                // Guardar estado
                $('#btnGuardarEstado').click(function(){
                    var form = document.getElementById('formEstado');
                    
                    if (!form.checkValidity()) {
                        form.classList.add('was-validated');
                        return false;
                    }
                    
                    var id = $('#tabla_tipo_estado_id').val();
                    var accion = id ? 'editar_estado' : 'agregar_estado';
                    
                    $.ajax({
                        url: 'tablas_tipos_ajax.php',
                        type: 'POST',
                        data: {
                            accion: accion,
                            tabla_tipo_estado_id: id,
                            tabla_tipo_id: $('#tabla_tipo_id_estado').val(),
                            estado_registro_id: $('#estado_registro_id').val(),
                            orden: $('#orden').val(),
                            es_inicial: $('#es_inicial').is(':checked') ? 1 : 0
                        },
                        dataType: 'json',
                        success: function(res) {
                            if(res.resultado) {
                                var modal = bootstrap.Modal.getInstance(document.getElementById('modalEstado'));
                                modal.hide();
                                $('#formEstado')[0].reset();
                                form.classList.remove('was-validated');
                                
                                // Recargar la tabla de estados
                                cargarEstadosTipo(tablaTipoActual);
                                
                                Swal.fire({
                                    icon: "success",
                                    title: "¡Éxito!",
                                    text: id ? "Estado actualizado correctamente" : "Estado creado correctamente",
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
            
            .form-check.form-switch.d-inline-block {
                padding-left: 0;
                margin-bottom: 0;
            }
            
            .form-check-input.toggle-estado-tipo {
                width: 3em;
                height: 1.5em;
            }
            
            .badge {
                font-size: 0.75rem;
            }
            
            #tablaEstados th, #tablaEstados td {
                vertical-align: middle;
            }
            </style>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

            <?php
            require_once ROOT_PATH . '/templates/adminlte/footer1.php';
            ?>
            </body>
            </html>