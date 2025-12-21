<?php
// Configuración de la página
$pageTitle = "Gestión de Tablas";
$currentPage = 'tablas';
$modudo_idx = 1;
// Definir constante para rutas
define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));

// Incluir header
require_once ROOT_PATH . '/templates/adminlte/header1.php';?>

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
                                        <button class="btn btn-primary mb-3" id="btnNuevoTipo">Nuevo Tipo</button>
                                        <table id="tablaTipos" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Tipo de Tabla</th>
                                                    <th>Cantidad de Estados</th>
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

            <!-- Modal para Tipo de Tabla -->
            <div class="modal fade" id="modalTipo" tabindex="-1" aria-labelledby="modalLabelTipo" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabelTipo">Tipo de Tabla</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formTipo">
                                <input type="hidden" id="tabla_tipo_id" name="tabla_tipo_id" />
                                
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label>Nombre del Tipo *</label>
                                        <input type="text" class="form-control" id="tabla_tipo" name="tabla_tipo" required/>
                                        <div class="invalid-feedback">El nombre es obligatorio</div>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="tabla_estado_registro_id_tipo" name="tabla_estado_registro_id" value="1" checked>
                                            <label class="form-check-label" for="tabla_estado_registro_id_tipo">Tipo activo</label>
                                        </div>
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

            <!-- Modal para Estados de Tipo de Tabla -->
            <div class="modal fade" id="modalEstados" tabindex="-1" aria-labelledby="modalLabelEstados" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabelEstados">Estados del Tipo: <span id="nombreTipo"></span></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" id="tabla_tipo_id_estados" />
                            <button class="btn btn-primary mb-3" id="btnNuevoEstado">Nuevo Estado</button>
                            <table id="tablaEstados" class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Estado</th>
                                        <th>Descripción</th>
                                        <th>Valor</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>                                            
                                </thead>
                            </table>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal para Estado Individual -->
            <div class="modal fade" id="modalEstado" tabindex="-1" aria-labelledby="modalLabelEstado" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabelEstado">Estado</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formEstado">
                                <input type="hidden" id="tabla_tipo_estado_id" name="tabla_tipo_estado_id" />
                                <input type="hidden" id="tabla_tipo_id_estado" name="tabla_tipo_id" />
                                
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label>Nombre del Estado *</label>
                                        <input type="text" class="form-control" id="tabla_tipo_estado" name="tabla_tipo_estado" required/>
                                        <div class="invalid-feedback">El nombre es obligatorio</div>
                                    </div>
                                    <div class="col-md-12">
                                        <label>Descripción</label>
                                        <input type="text" class="form-control" id="tabla_tipo_estado_descripcion" name="tabla_tipo_estado_descripcion"/>
                                    </div>
                                    <div class="col-md-12">
                                        <label>valor</label>
                                        <input type="number" class="form-control" id="valor" name="valor" min="0" value="0"/>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="tabla_estado_registro_id_estado" name="tabla_estado_registro_id" value="1" checked>
                                            <label class="form-check-label" for="tabla_estado_registro_id_estado">Estado activo</label>
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
                // Configuración de DataTable para Tipos
                var tablaTipos = $('#tablaTipos').DataTable({
                    pageLength: 25,
                    lengthMenu: [25, 50, 100, 200],
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
                        url: 'tablas_tipos_ajax.php',
                        type: 'GET',
                        data: {accion: 'listar_tipos'},
                        dataSrc: ''
                    },
                    language: {
                        "search": "Buscar:",
                        "searchPlaceholder": "Buscar tipos...",
                        "lengthMenu": "Mostrar _MENU_ registros por página",
                        "zeroRecords": "No se encontraron tipos",
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
                        { 
                            data: 'tabla_tipo',
                            render: function(data, type, row) {
                                // Hacer el nombre clickeable
                                return `<a href="#" class="text-primary fw-bold btnVerEstados" 
                                        data-tabla-tipo-id="${row.tabla_tipo_id}" 
                                        data-tabla-tipo="${data}">
                                        ${data}
                                    </a>`;
                            }
                        },
                        { 
                            data: 'cantidad_estados',
                            className: "text-center",
                            render: function(data) {
                                return `<span class="badge bg-info">${data} estados</span>`;
                            }
                        },
                        {
                            data: null,
                            orderable: false,
                            searchable: false,
                            className: "text-center",
                            render: function(data){
                                var botonEstado = 
                                    `<div class="form-check form-switch d-inline-block">
                                        <input class="form-check-input toggle-estado-tipo"
                                            type="checkbox" 
                                            data-tabla-tipo-id="${data.tabla_tipo_id}" 
                                            ${data.tabla_estado_registro_id == 1 ? 'checked' : ''}>
                                    </div>`;
                                
                                return `<div class="d-flex flex-column align-items-center">${botonEstado}</div>`;
                            }
                        },
                        {
                            data: null,
                            orderable: false,
                            searchable: false,
                            className: "text-center",
                            render: function(data){
                                var botonEditar = data.tabla_estado_registro_id == 1 ? 
                                    `<button class="btn btn-sm btn-primary btnEditarTipo" title="Editar">
                                        <i class="fa fa-edit"></i>
                                     </button>` : 
                                    `<button class="btn btn-sm btn-secondary" title="Editar no disponible" disabled>
                                        <i class="fa fa-edit"></i>
                                     </button>`;
                                
                                var botonEstados = 
                                    `<button class="btn btn-sm btn-info btnEstadosTipo ms-1" title="Gestionar Estados">
                                        <i class="fa fa-cogs"></i>
                                     </button>`;
                                
                                return `<div class="d-flex align-items-center justify-content-center">${botonEditar}${botonEstados}</div>`;
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

                // Configuración de DataTable para Estados (se inicializará cuando se abra el modal)
                var tablaEstados = null;

                // Manejar el cambio de estado para tipos
                $(document).on('change', '.toggle-estado-tipo', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var tablaTipoId = $(this).data('tabla-tipo-id');
                    var isChecked = $(this).is(':checked');
                    var nuevoEstado = isChecked ? 1 : 0;
                    var accionTexto = nuevoEstado == 1 ? 'activar' : 'desactivar';                    
                   
                    $.get('tablas_tipos_ajax.php', {
                        accion: 'cambiar_estado_tipo', 
                        tabla_tipo_id: tablaTipoId,
                        nuevo_estado: nuevoEstado
                    }, function(res){
                        if(res.resultado){
                            tablaTipos.ajax.reload();
                        } else {
                            $(this).prop('checked', !isChecked);
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: res.error || `Error al ${accionTexto} el tipo de tabla`
                            });
                        }
                    }, 'json');
                });
                $(document).on('click', '.btnVerEstados', function(e) {
                    e.preventDefault();
                    
                    var tablaTipoId = $(this).data('tabla-tipo-id');
                    var tablaTipoNombre = $(this).data('tabla-tipo');
                    
                    $('#tabla_tipo_id_estados').val(tablaTipoId);
                    $('#nombreTipo').text(tablaTipoNombre);
                    
                    // Inicializar o recargar la tabla de estados
                    if (tablaEstados) {
                        tablaEstados.ajax.reload();
                    } else {
                        tablaEstados = $('#tablaEstados').DataTable({
                            pageLength: 10,
                            lengthMenu: [10, 25, 50, 100],
                            dom: '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
                            ajax: {
                                url: 'tablas_tipos_ajax.php',
                                type: 'GET',
                                data: function() {
                                    return {
                                        accion: 'listar_estados',
                                        tabla_tipo_id: $('#tabla_tipo_id_estados').val()
                                    };
                                },
                                dataSrc: ''
                            },
                            language: {
                                "search": "Buscar:",
                                "searchPlaceholder": "Buscar estados...",
                                "lengthMenu": "Mostrar _MENU_ registros por página",
                                "zeroRecords": "No se encontraron estados",
                                "info": "Mostrando _START_ a _END_ de _TOTAL_ estados",
                                "infoEmpty": "Mostrando 0 a 0 de 0 estados",
                                "infoFiltered": "(filtrado de _MAX_ estados totales)",
                                "paginate": {
                                    "first": "Primero",
                                    "last": "Último",
                                    "next": "Siguiente",
                                    "previous": "Anterior"
                                }
                            },
                            columns: [
                                { data: 'tabla_tipo_estado_id' },
                                { data: 'tabla_tipo_estado' },
                                { data: 'tabla_tipo_estado_descripcion' },
                                { 
                                    data: 'valor',
                                    className: "text-center"
                                },
                                {
                                    data: null,
                                    orderable: false,
                                    searchable: false,
                                    className: "text-center",
                                    render: function(data){
                                        var botonEstado = 
                                            `<div class="form-check form-switch d-inline-block">
                                                <input class="form-check-input toggle-estado-estado"
                                                    type="checkbox" 
                                                    data-tabla-tipo-estado-id="${data.tabla_tipo_estado_id}" 
                                                    ${data.tabla_estado_registro_id == 1 ? 'checked' : ''}>
                                            </div>`;
                                        
                                        return `<div class="d-flex flex-column align-items-center">${botonEstado}</div>`;
                                    }
                                },
                                {
                                    data: null,
                                    orderable: false,
                                    searchable: false,
                                    className: "text-center",
                                    render: function(data){
                                        var botonEditar = data.tabla_estado_registro_id == 1 ? 
                                            `<button class="btn btn-sm btn-primary btnEditarEstado" title="Editar">
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
                                if (data.tabla_estado_registro_id != 1) {
                                    $(row).addClass('table-secondary');
                                    $(row).find('td').css('color', '#6c757d');
                                }
                            }
                        });
                    }
                    
                    var modal = new bootstrap.Modal(document.getElementById('modalEstados'));
                    modal.show();
                });
                // Nuevo Tipo
                $('#btnNuevoTipo').click(function(){
                    $('#formTipo')[0].reset();
                    $('#tabla_tipo_id').val('');
                    $('#tabla_estado_registro_id_tipo').prop('checked', true);
                    $('#modalLabelTipo').text('Nuevo Tipo de Tabla');
                    var modal = new bootstrap.Modal(document.getElementById('modalTipo'));
                    modal.show();
                });

                // Editar Tipo
                $('#tablaTipos tbody').on('click', '.btnEditarTipo', function(){
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
                            $('#tabla_estado_registro_id_tipo').prop('checked', res.tabla_estado_registro_id == 1);
                            
                            $('#modalLabelTipo').text('Editar Tipo de Tabla');
                            var modal = new bootstrap.Modal(document.getElementById('modalTipo'));
                            modal.show();
                        } else {
                            alert('Error al obtener datos');
                        }
                    }, 'json');
                });

                // Gestionar Estados de un Tipo
                $('#tablaTipos tbody').on('click', '.btnEstadosTipo', function(){
                    var data = tablaTipos.row($(this).parents('tr')).data();
                    $('#tabla_tipo_id_estados').val(data.tabla_tipo_id);
                    $('#nombreTipo').text(data.tabla_tipo);
                    
                    // Inicializar o recargar la tabla de estados
                    if (tablaEstados) {
                        tablaEstados.ajax.reload();
                    } else {
                        tablaEstados = $('#tablaEstados').DataTable({
                            pageLength: 10,
                            lengthMenu: [10, 25, 50, 100],
                            dom: '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
                            ajax: {
                                url: 'tablas_tipos_ajax.php',
                                type: 'GET',
                                data: function() {
                                    return {
                                        accion: 'listar_estados',
                                        tabla_tipo_id: $('#tabla_tipo_id_estados').val()
                                    };
                                },
                                dataSrc: ''
                            },
                            language: {
                                "search": "Buscar:",
                                "searchPlaceholder": "Buscar estados...",
                                "lengthMenu": "Mostrar _MENU_ registros por página",
                                "zeroRecords": "No se encontraron estados",
                                "info": "Mostrando _START_ a _END_ de _TOTAL_ estados",
                                "infoEmpty": "Mostrando 0 a 0 de 0 estados",
                                "infoFiltered": "(filtrado de _MAX_ estados totales)",
                                "paginate": {
                                    "first": "Primero",
                                    "last": "Último",
                                    "next": "Siguiente",
                                    "previous": "Anterior"
                                }
                            },
                            columns: [
                                { data: 'tabla_tipo_estado_id' },
                                { data: 'tabla_tipo_estado' },
                                { data: 'tabla_tipo_estado_descripcion' },
                                { 
                                    data: 'valor',
                                    className: "text-center"
                                },
                                {
                                    data: null,
                                    orderable: false,
                                    searchable: false,
                                    className: "text-center",
                                    render: function(data){
                                        var botonEstado = 
                                            `<div class="form-check form-switch d-inline-block">
                                                <input class="form-check-input toggle-estado-estado"
                                                    type="checkbox" 
                                                    data-tabla-tipo-estado-id="${data.tabla_tipo_estado_id}" 
                                                    ${data.tabla_estado_registro_id == 1 ? 'checked' : ''}>
                                            </div>`;
                                        
                                        return `<div class="d-flex flex-column align-items-center">${botonEstado}</div>`;
                                    }
                                },
                                {
                                    data: null,
                                    orderable: false,
                                    searchable: false,
                                    className: "text-center",
                                    render: function(data){
                                        var botonEditar = data.tabla_estado_registro_id == 1 ? 
                                            `<button class="btn btn-sm btn-primary btnEditarEstado" title="Editar">
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
                                if (data.tabla_estado_registro_id != 1) {
                                    $(row).addClass('table-secondary');
                                    $(row).find('td').css('color', '#6c757d');
                                }
                            }
                        });
                    }
                    
                    var modal = new bootstrap.Modal(document.getElementById('modalEstados'));
                    modal.show();
                });

                // Nuevo Estado
                $('#btnNuevoEstado').click(function(){
                    $('#formEstado')[0].reset();
                    $('#tabla_tipo_estado_id').val('');
                    $('#tabla_tipo_id_estado').val($('#tabla_tipo_id_estados').val());
                    $('#valor').val(0);
                    $('#tabla_estado_registro_id_estado').prop('checked', true);
                    $('#modalLabelEstado').text('Nuevo Estado');
                    var modal = new bootstrap.Modal(document.getElementById('modalEstado'));
                    modal.show();
                });

                // Editar Estado
                $(document).on('click', '.btnEditarEstado', function(){
                    var data = tablaEstados.row($(this).parents('tr')).data();
                    if (data.tabla_estado_registro_id != 1) {
                        Swal.fire({
                            icon: "warning",
                            title: "Estado inactivo",
                            text: "No se puede editar un estado inactivo. Active el estado primero.",
                            showConfirmButton: false,
                            timer: 2000
                        });
                        return false;
                    }
                    
                    $.get('tablas_tipos_ajax.php', {accion: 'obtener_estado', tabla_tipo_estado_id: data.tabla_tipo_estado_id}, function(res){
                        if(res){
                            $('#tabla_tipo_estado_id').val(res.tabla_tipo_estado_id);
                            $('#tabla_tipo_id_estado').val(res.tabla_tipo_id);
                            $('#tabla_tipo_estado').val(res.tabla_tipo_estado);
                            $('#tabla_tipo_estado_descripcion').val(res.tabla_tipo_estado_descripcion);
                            $('#valor').val(res.valor);
                            $('#tabla_estado_registro_id_estado').prop('checked', res.tabla_estado_registro_id == 1);
                            
                            $('#modalLabelEstado').text('Editar Estado');
                            var modal = new bootstrap.Modal(document.getElementById('modalEstado'));
                            modal.show();
                        } else {
                            alert('Error al obtener datos');
                        }
                    }, 'json');
                });

                // Manejar el cambio de estado para estados
                $(document).on('change', '.toggle-estado-estado', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    var tablaTipoEstadoId = $(this).data('tabla-tipo-estado-id');
                    var isChecked = $(this).is(':checked');
                    var nuevoEstado = isChecked ? 1 : 0;
                    var accionTexto = nuevoEstado == 1 ? 'activar' : 'desactivar';                    
                   
                    $.get('tablas_tipos_ajax.php', {
                        accion: 'cambiar_estado_estado', 
                        tabla_tipo_estado_id: tablaTipoEstadoId,
                        nuevo_estado: nuevoEstado
                    }, function(res){
                        if(res.resultado){
                            tablaEstados.ajax.reload();
                        } else {
                            $(this).prop('checked', !isChecked);
                            Swal.fire({
                                icon: "error",
                                title: "Error",
                                text: res.error || `Error al ${accionTexto} el estado`
                            });
                        }
                    }, 'json');
                });

                // Guardar Tipo
                $('#btnGuardarTipo').click(function(){
                    var form = document.getElementById('formTipo');
                    
                    if (!form.checkValidity()) {
                        form.classList.add('was-validated');
                        return false;
                    }
                    
                    var id = $('#tabla_tipo_id').val();
                    var accion = id ? 'editar_tipo' : 'agregar_tipo';
                    var formData = {
                        accion: accion,
                        tabla_tipo_id: id,
                        tabla_tipo: $('#tabla_tipo').val(),
                        tabla_estado_registro_id: $('#tabla_estado_registro_id_tipo').is(':checked') ? 1 : 0
                    };

                    $.ajax({
                        url: 'tablas_tipos_ajax.php',
                        type: 'GET',
                        data: formData,
                        dataType: 'json',
                        success: function(res) {
                            if(res.resultado) {
                                tablaTipos.ajax.reload(null, false);
                                
                                var modal = bootstrap.Modal.getInstance(document.getElementById('modalTipo'));
                                modal.hide();
                                
                                $('#formTipo')[0].reset();
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

                // Guardar Estado
                $('#btnGuardarEstado').click(function(){
                    var form = document.getElementById('formEstado');
                    
                    if (!form.checkValidity()) {
                        form.classList.add('was-validated');
                        return false;
                    }
                    
                    var id = $('#tabla_tipo_estado_id').val();
                    var accion = id ? 'editar_estado' : 'agregar_estado';
                    var formData = {
                        accion: accion,
                        tabla_tipo_estado_id: id,
                        tabla_tipo_id: $('#tabla_tipo_id_estado').val(),
                        tabla_tipo_estado: $('#tabla_tipo_estado').val(),
                        tabla_tipo_estado_descripcion: $('#tabla_tipo_estado_descripcion').val(),
                        valor: $('#valor').val(),
                        tabla_estado_registro_id: $('#tabla_estado_registro_id_estado').is(':checked') ? 1 : 0
                    };

                    $.ajax({
                        url: 'tablas_tipos_ajax.php',
                        type: 'GET',
                        data: formData,
                        dataType: 'json',
                        success: function(res) {
                            if(res.resultado) {
                                tablaEstados.ajax.reload(null, false);
                                
                                var modal = bootstrap.Modal.getInstance(document.getElementById('modalEstado'));
                                modal.hide();
                                
                                $('#formEstado')[0].reset();
                                form.classList.remove('was-validated');
                                
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
            
            .form-check-input.toggle-estado-tipo,
            .form-check-input.toggle-estado-estado {
                width: 3em;
                height: 1.5em;
            }
            </style>
            <style>
                .btnVerEstados {
                    text-decoration: none;
                    cursor: pointer;
                    transition: color 0.2s ease;
                }

                .btnVerEstados:hover {
                    color: #0056b3 !important;
                    text-decoration: underline;
                }

                .badge.bg-info {
                    font-size: 0.85em;
                }
                </style><?php     
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>