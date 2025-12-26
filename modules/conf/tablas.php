<?php
// Configuración de la página
$pageTitle = "Gestión de Tablas";
$currentPage = 'tablas';
$modudo_idx = 1;
// Definir constante para rutas
define('ROOT_PATH', dirname(dirname(dirname(__FILE__))));

// Incluir header
require_once ROOT_PATH . '/templates/adminlte/header1.php';
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6"><h3 class="mb-0">Tablas</h3></div>
                <div class="col-sm-6">
                    <ol class="breadcrumb float-sm-end">
                        <li class="breadcrumb-item"><a href="#">Home</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Tablas</li>
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
                                        <button class="btn btn-primary mb-3" id="btnNuevo">Nueva Tabla</button>
                                        <table id="tablaTablas" class="table table-striped table-bordered">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Módulo</th>
                                                    <th>Nombre</th>
                                                    <th>Tipo</th>
                                                    <th>Descripción</th>
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

            <!-- Modal para Tabla -->
            <div class="modal fade" id="modalTabla" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalLabel">Tabla</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <form id="formTabla">
                                <input type="hidden" id="tabla_id" name="tabla_id" />
                                
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label>Nombre de Tabla *</label>
                                        <input type="text" class="form-control" id="tabla_nombre" name="tabla_nombre" required/>
                                        <div class="invalid-feedback">El nombre es obligatorio</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Módulo *</label>
                                        <select class="form-control" id="modulo_id" name="modulo_id" required>
                                            <option value="">Seleccionar Módulo</option>
                                        </select>
                                        <div class="invalid-feedback">El módulo es obligatorio</div>
                                    </div>
                                    <div class="col-md-6">
                                        <label>Tipo de Tabla *</label>
                                        <select class="form-control" id="tabla_tipo_id" name="tabla_tipo_id" required>
                                            <option value="">Seleccionar Tipo</option>
                                        </select>
                                        <div class="invalid-feedback">El tipo es obligatorio</div>
                                    </div>
                                    <div class="col-md-12">
                                        <label>Descripción</label>
                                        <textarea class="form-control" id="tabla_descripcion" name="tabla_descripcion" rows="3"></textarea>
                                    </div>
                                    <div class="col-md-12">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="tabla_estado_registro_id" name="tabla_estado_registro_id" value="1" checked>
                                            <label class="form-check-label" for="tabla_estado_registro_id">Tabla activa</label>
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

            <!-- Modal para Estados Patrón -->
            <div class="modal fade" id="modalEstadosPatron" tabindex="-1" aria-labelledby="modalEstadosLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalEstadosLabel">Estados Patrón para Tipo de Tabla</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                        </div>
                        <div class="modal-body">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Los siguientes estados están configurados como patrón para este tipo de tabla. 
                                Puedes agregarlos automáticamente a la tabla actual.
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-bordered" id="tablaEstadosPatron">
                                    <thead>
                                        <tr>
                                            <th>Estado</th>
                                            <th>Orden</th>
                                            <th>Es Inicial</th>
                                            <th>Estado Actual</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbodyEstadosPatron">
                                        <!-- Los estados se cargarán aquí -->
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="chkAgregarTodos" checked>
                                    <label class="form-check-label" for="chkAgregarTodos">
                                        Agregar todos los estados patrón a la tabla
                                    </label>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-success" id="btnAgregarEstados">
                                <i class="fas fa-plus"></i> Agregar Estados
                            </button>
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            // Función para cargar módulos
            function cargarModulos(selectedId = null) {
                $.ajax({
                    url: 'tablas_ajax.php',
                    type: 'GET',
                    data: {accion: 'obtener_Modulos'},
                    dataType: 'json',
                    success: function(res) {
                        if(res && res.length > 0) {
                            var options = '<option value="">Seleccionar módulo</option>';
                            $.each(res, function(index, modulo) {
                                var selected = (selectedId == modulo.modulo_id) ? 'selected' : '';
                                options += `<option value="${modulo.modulo_id}" ${selected}>${modulo.modulo}</option>`;
                            });
                            $('#modulo_id').html(options);
                        }
                    },
                    error: function() {
                        console.error('Error al cargar módulos');
                        $('#modulo_id').html('<option value="">Error al cargar módulos</option>');
                    }
                });
            }

            // Función para cargar tipos de tabla
            function cargarTiposTabla(selectedId = null) {
                $.ajax({
                    url: 'tablas_ajax.php',
                    type: 'GET',
                    data: {accion: 'obtener_Tipos_Tabla'},
                    dataType: 'json',
                    success: function(res) {
                        if(res && res.length > 0) {
                            var options = '<option value="">Seleccionar tipo</option>';
                            $.each(res, function(index, tipo) {
                                var selected = (selectedId == tipo.tabla_tipo_id) ? 'selected' : '';
                                options += `<option value="${tipo.tabla_tipo_id}" ${selected}>${tipo.tabla_tipo}</option>`;
                            });
                            $('#tabla_tipo_id').html(options);
                        }
                    },
                    error: function() {
                        console.error('Error al cargar tipos de tabla');
                        $('#tabla_tipo_id').html('<option value="">Error al cargar tipos</option>');
                    }
                });
            }

            // Función para verificar si una tabla tiene estados patrón disponibles
            function verificarEstadosPatron(tablaId, tablaTipoId, tablaNombre) {
                if (!tablaTipoId) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Sin tipo de tabla',
                        text: 'Esta tabla no tiene un tipo asignado, por lo que no hay estados patrón disponibles.'
                    });
                    return;
                }
                
                $.ajax({
                    url: 'tablas_ajax.php',
                    type: 'GET',
                    data: {accion: 'obtener_estados_patron', tabla_tipo_id: tablaTipoId},
                    dataType: 'json',
                    success: function(res) {
                        if(res && res.length > 0) {
                            // Mostrar modal con estados patrón
                            mostrarEstadosPatron(res, tablaId, tablaNombre);
                        } else {
                            Swal.fire({
                                icon: 'info',
                                title: 'Sin estados patrón',
                                text: 'No hay estados patrón configurados para este tipo de tabla.'
                            });
                        }
                    },
                    error: function() {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'Error al verificar estados patrón'
                        });
                    }
                });
            }

            // Función para mostrar los estados patrón en el modal
           function mostrarEstadosPatron(estados, tablaId, tablaNombre) {
            var tbody = $('#tbodyEstadosPatron');
            tbody.empty();
            
            $.each(estados, function(index, estado) {
                // Formatear código estándar si está disponible
                var codigoEstandar = estado.codigo_estandar ? 
                    `<div><small class="text-muted">Código: ${estado.codigo_estandar}</small></div>` : '';
                
                // Formatear valor estándar si está disponible
                var valorEstandar = estado.valor_estandar ? 
                    `<div><small class="text-muted">Valor: ${estado.valor_estandar}</small></div>` : '';
                
                var fila = `
                    <tr>
                        <td>
                            <div><strong>${estado.estado_registro}</strong></div>
                            ${codigoEstandar}
                            ${valorEstandar}
                        </td>
                        <td class="text-center">${estado.orden}</td>
                        <td class="text-center">
                            ${estado.es_inicial == 1 ? 
                                '<span class="badge bg-success"><i class="fas fa-check"></i> Sí</span>' : 
                                '<span class="badge bg-secondary">No</span>'}
                        </td>
                        <td class="text-center">
                            ${estado.tabla_estado_registro_id == 1 ? 
                                '<span class="badge bg-success">Activo</span>' : 
                                '<span class="badge bg-danger">Inactivo</span>'}
                        </td>
                    </tr>
                `;
                tbody.append(fila);
            });
    
    $('#modalEstadosLabel').html(`<i class="fas fa-clone me-2"></i>Estados Patrón para: <strong>${tablaNombre}</strong>`);
    $('#btnAgregarEstados').data('tabla-id', tablaId);
    $('#btnAgregarEstados').data('tabla-tipo-id', estados[0].tabla_tipo_id);
    
    var modal = new bootstrap.Modal(document.getElementById('modalEstadosPatron'));
    modal.show();
}

            // Función para mostrar los estados actuales de una tabla
           function mostrarEstadosActualesTabla(tablaId, tablaNombre) {
    $.ajax({
        url: 'tablas_ajax.php',
        type: 'GET',
        data: {accion: 'obtener_estados_tabla', tabla_id: tablaId},
        dataType: 'json',
        success: function(res) {
            if(res && res.length > 0) {
                var contenido = '<div class="table-responsive mt-3"><table class="table table-sm table-bordered table-hover">';
                contenido += '<thead><tr>' +
                    '<th>ID Estado</th>' +
                    '<th>Estado</th>' +
                    '<th>Nombre en Tabla</th>' +
                    '<th>Código Estándar</th>' +
                    '<th>Valor Estándar</th>' +
                    '<th>Color</th>' +
                    '<th>Es Inicial</th>' +
                    '<th>Orden</th>' +
                    '</tr></thead><tbody>';
                
                $.each(res, function(index, estado) {
                    var colorBadge = estado.color_clase ? 
                        `<span class="badge" style="background-color: ${estado.color_clase}; color: ${estado.color_clase.includes('light') ? '#212529' : 'white'}">${estado.nombre_color}</span>` :
                        `<span class="badge bg-secondary">Sin color</span>`;
                    
                    // Formatear código estándar
                    var codigoEstandar = estado.codigo_estandar ? 
                        `<code class="bg-light p-1 rounded">${estado.codigo_estandar}</code>` : 
                        '<span class="text-muted">-</span>';
                    
                    // Formatear valor estándar
                    var valorEstandar = estado.valor_estandar ? 
                        `<span class="badge bg-info text-dark">${estado.valor_estandar}</span>` : 
                        '<span class="text-muted">-</span>';
                    
                    contenido += `
                        <tr>
                            <td class="text-center"><strong>${estado.tabla_estado_registro_id}</strong></td>
                            <td><strong>${estado.estado_registro}</strong></td>
                            <td>${estado.tabla_estado_registro}</td>
                            <td class="text-center">${codigoEstandar}</td>
                            <td class="text-center">${valorEstandar}</td>
                            <td class="text-center">${colorBadge}</td>
                            <td class="text-center">
                                ${estado.es_inicial == 1 ? 
                                    '<span class="badge bg-success"><i class="fas fa-check"></i> Sí</span>' : 
                                    '<span class="badge bg-secondary">No</span>'}
                            </td>
                            <td class="text-center"><span class="badge bg-dark">${estado.orden}</span></td>
                        </tr>
                    `;
                });
                
                contenido += '</tbody></table></div>';
                
                Swal.fire({
                    title: `<i class="fas fa-sitemap me-2"></i>Estados actuales: ${tablaNombre}`,
                    html: contenido,
                    width: 1100,
                    showCloseButton: true,
                    showConfirmButton: true,
                    confirmButtonText: 'Cerrar',
                    customClass: {
                        popup: 'swal-wide'
                    }
                });
            } else {
                Swal.fire({
                    icon: 'info',
                    title: 'Sin estados configurados',
                    text: 'Esta tabla no tiene estados configurados actualmente.',
                    showConfirmButton: true,
                    confirmButtonText: 'Aceptar'
                });
            }
        },
        error: function() {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Error al obtener los estados de la tabla',
                showConfirmButton: true,
                confirmButtonText: 'Aceptar'
            });
        }
    });
}

            $(document).ready(function(){
                cargarModulos();
                cargarTiposTabla();
                
                // Configuración de DataTable
                var tabla = $('#tablaTablas').DataTable({
                    pageLength: 25, // Mostrar 25 registros por página como mínimo
                    lengthMenu: [25, 50, 100, 200], // Opciones del menú de cantidad de registros        
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
                        url: 'tablas_ajax.php',
                        type: 'GET',
                        data: {accion: 'listar'},
                        dataSrc: ''
                    },
                    language: {
                        "search": "Buscar:",
                        "searchPlaceholder": "Buscar tablas...",
                        "lengthMenu": "Mostrar _MENU_ registros por página",
                        "zeroRecords": "No se encontraron tablas",
                        "info": "Mostrando _START_ a _END_ de _TOTAL_ tablas",
                        "infoEmpty": "Mostrando 0 a 0 de 0 tablas",
                        "infoFiltered": "(filtrado de _MAX_ tablas totales)",
                        "paginate": {
                            "first": "Primero",
                            "last": "Último",
                            "next": "Siguiente",
                            "previous": "Anterior"
                        }
                    },
                    columns: [
                        { data: 'tabla_id' },
                        { data: 'modulo' },
                        { data: 'tabla_nombre' },
                        { 
                            data: 'tabla_tipo',
                            render: function(data) {
                                return data || '<span class="text-muted">Sin tipo</span>';
                            }
                        },
                        { 
                            data: 'tabla_descripcion',
                            render: function(data) {
                                return data || '<span class="text-muted">Sin descripción</span>';
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
                                        <input class="form-check-input toggle-estado"
                                            type="checkbox" 
                                            data-tabla-id="${data.tabla_id}" 
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
                                    `<button class="btn btn-sm btn-primary btnEditar me-1" title="Editar" data-tabla-id="${data.tabla_id}">
                                        <i class="fa fa-edit"></i>
                                     </button>` : 
                                    `<button class="btn btn-sm btn-secondary me-1" title="Editar no disponible" disabled>
                                        <i class="fa fa-edit"></i>
                                     </button>`;
                                
                                var botonEstados = data.tabla_estado_registro_id == 1 && data.tabla_tipo_id ? 
                                    `<button class="btn btn-sm btn-info me-1 btnEstadosPatron" 
                                            title="Gestionar estados patrón"
                                            data-tabla-id="${data.tabla_id}"
                                            data-tabla-tipo-id="${data.tabla_tipo_id}"
                                            data-tabla-nombre="${data.tabla_nombre}">
                                        <i class="fas fa-sitemap"></i>
                                    </button>` : 
                                    `<button class="btn btn-sm btn-secondary me-1" title="No disponible" disabled>
                                        <i class="fas fa-sitemap"></i>
                                    </button>`;
                                
                                var botonVerEstados = 
                                    `<button class="btn btn-sm btn-warning btnVerEstados" 
                                            title="Ver estados actuales"
                                            data-tabla-id="${data.tabla_id}"
                                            data-tabla-nombre="${data.tabla_nombre}">
                                        <i class="fas fa-eye"></i>
                                    </button>`;
                                
                                return `<div class="d-flex align-items-center justify-content-center">
                                            ${botonEditar} 
                                            ${botonEstados}
                                            ${botonVerEstados}
                                        </div>`;
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
                    
                    var tablaId = $(this).data('tabla-id');
                    var isChecked = $(this).is(':checked');
                    var nuevoEstado = isChecked ? 1 : 0;
                    var accionTexto = nuevoEstado == 1 ? 'activar' : 'desactivar';                    
                   
                    Swal.fire({
                        title: `¿${accionTexto.charAt(0).toUpperCase() + accionTexto.slice(1)} tabla?`,
                        text: `¿Estás seguro de que quieres ${accionTexto} esta tabla?`,
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonText: `Sí, ${accionTexto}`,
                        cancelButtonText: 'Cancelar'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            $.get('tablas_ajax.php', {
                                accion: 'cambiar_estado', 
                                tabla_id: tablaId,
                                nuevo_estado: nuevoEstado
                            }, function(res){
                                if(res.resultado){
                                    tabla.ajax.reload();
                                    Swal.fire({
                                        icon: 'success',
                                        title: 'Éxito',
                                        text: `Tabla ${accionTexto}ada correctamente`,
                                        timer: 1500,
                                        showConfirmButton: false
                                    });
                                } else {
                                    // Revertir el cambio visual si hay error
                                    $(this).prop('checked', !isChecked);
                                    Swal.fire({
                                        icon: "error",
                                        title: "Error",
                                        text: res.error || `Error al ${accionTexto} la tabla`
                                    });
                                }
                            }, 'json');
                        } else {
                            // Revertir si canceló
                            $(this).prop('checked', !isChecked);
                        }
                    });
                });

                // Evento para botón de estados patrón
                $(document).on('click', '.btnEstadosPatron', function() {
                    var tablaId = $(this).data('tabla-id');
                    var tablaTipoId = $(this).data('tabla-tipo-id');
                    var tablaNombre = $(this).data('tabla-nombre');
                    
                    verificarEstadosPatron(tablaId, tablaTipoId, tablaNombre);
                });

                // Evento para botón ver estados actuales
                $(document).on('click', '.btnVerEstados', function() {
                    var tablaId = $(this).data('tabla-id');
                    var tablaNombre = $(this).data('tabla-nombre');
                    
                    mostrarEstadosActualesTabla(tablaId, tablaNombre);
                });

                // Evento para agregar estados desde el modal
                $('#btnAgregarEstados').click(function() {
                    var tablaId = $(this).data('tabla-id');
                    var tablaTipoId = $(this).data('tabla-tipo-id');
                    var agregarTodos = $('#chkAgregarTodos').is(':checked');
                    
                    Swal.fire({
                        title: '¿Agregar estados?',
                        text: agregarTodos ? 
                            'Se agregarán todos los estados patrón a la tabla.' :
                            'Se agregarán solo los estados activos a la tabla.',
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonText: 'Sí, agregar',
                        cancelButtonText: 'Cancelar',
                        showLoaderOnConfirm: true,
                        preConfirm: () => {
                            return $.ajax({
                                url: 'tablas_ajax.php',
                                type: 'GET',
                                data: {
                                    accion: 'agregar_estados_tabla',
                                    tabla_id: tablaId,
                                    tabla_tipo_id: tablaTipoId,
                                    agregar_todos: agregarTodos ? 1 : 0
                                },
                                dataType: 'json'
                            }).then(response => {
                                return response;
                            }).catch(error => {
                                Swal.showValidationMessage(`Error: ${error}`);
                            });
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            if(result.value.resultado) {
                                Swal.fire({
                                    icon: 'success',
                                    title: '¡Éxito!',
                                    html: `Se agregaron <strong>${result.value.estados_agregados}</strong> estados a la tabla.<br><br>
                                           <small>${result.value.mensaje}</small>`,
                                    showConfirmButton: true,
                                    confirmButtonText: 'Aceptar'
                                }).then(() => {
                                    // Cerrar modal
                                    var modal = bootstrap.Modal.getInstance(document.getElementById('modalEstadosPatron'));
                                    if (modal) {
                                        modal.hide();
                                    }
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Error',
                                    text: result.value.error || 'Error al agregar estados'
                                });
                            }
                        }
                    });
                });

                $('#btnNuevo').click(function(){
                    $('#formTabla')[0].reset();
                    $('#tabla_id').val('');
                    $('#tabla_estado_registro_id').prop('checked', true);
                    $('#modalLabel').text('Nueva Tabla');
                    var modal = new bootstrap.Modal(document.getElementById('modalTabla'));
                    modal.show();
                });

                $('#tablaTablas tbody').on('click', '.btnEditar', function(){
                    var data = tabla.row($(this).parents('tr')).data();
                    // Solo permitir editar si está activo
                    if (data.tabla_estado_registro_id != 1) {
                        Swal.fire({
                            icon: "warning",
                            title: "Tabla inactiva",
                            text: "No se puede editar una tabla inactiva. Active la tabla primero.",
                            showConfirmButton: false,
                            timer: 2000
                        });
                        return false;
                    }
                    
                    $.get('tablas_ajax.php', {accion: 'obtener', tabla_id: data.tabla_id}, function(res){
                        if(res){
                            $('#tabla_id').val(res.tabla_id);
                            $('#tabla_nombre').val(res.tabla_nombre);
                            $('#tabla_descripcion').val(res.tabla_descripcion);
                            $('#modulo_id').val(res.modulo_id);
                            $('#tabla_tipo_id').val(res.tabla_tipo_id);
                            $('#tabla_estado_registro_id').prop('checked', res.tabla_estado_registro_id == 1);
                            
                            cargarModulos(res.modulo_id);
                            cargarTiposTabla(res.tabla_tipo_id);

                            $('#modalLabel').text('Editar Tabla');
                            var modal = new bootstrap.Modal(document.getElementById('modalTabla'));
                            modal.show();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'Error al obtener los datos de la tabla'
                            });
                        }
                    }, 'json');
                });

                $('#btnGuardar').click(function(){
                    var form = document.getElementById('formTabla');
                    
                    if (!form.checkValidity()) {
                        form.classList.add('was-validated');
                        return false;
                    }
                    
                    var id = $('#tabla_id').val();
                    var accion = id ? 'editar' : 'agregar';
                    var formData = {
                        accion: accion,
                        tabla_id: id,
                        tabla_nombre: $('#tabla_nombre').val(),
                        tabla_descripcion: $('#tabla_descripcion').val(),
                        modulo_id: $('#modulo_id').val(),
                        tabla_tipo_id: $('#tabla_tipo_id').val(),
                        tabla_estado_registro_id: $('#tabla_estado_registro_id').is(':checked') ? 1 : 0
                    };

                    $.ajax({
                        url: 'tablas_ajax.php',
                        type: 'GET',
                        data: formData,
                        dataType: 'json',
                        success: function(res) {
                            if(res.resultado) {
                                tabla.ajax.reload(null, false);
                                
                                var modal = bootstrap.Modal.getInstance(document.getElementById('modalTabla'));
                                if (modal) {
                                    modal.hide();
                                }
                                
                                $('#formTabla')[0].reset();
                                form.classList.remove('was-validated');
                                
                                Swal.fire({
                                    icon: "success",
                                    title: "¡Éxito!",
                                    text: id ? "Tabla actualizada correctamente" : "Tabla creada correctamente",
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
                cursor: pointer;
            }
            
            .badge {
                font-size: 0.75rem;
            }
            
            /* Estilos para botones de acciones */
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.875rem;
                line-height: 1.5;
                min-width: 32px;
            }
            
            /* Espacio entre botones */
            .me-1 {
                margin-right: 0.25rem !important;
            }
            
            /* Ajustes para el modal de estados */
            #tablaEstadosPatron th {
                background-color: #f8f9fa;
                font-weight: 600;
            }
            
            /* Estilos para la tabla de estados en SweetAlert */
            .swal2-popup .table-sm {
                font-size: 0.875rem;
            }
            
            .swal2-popup .table-sm th {
                background-color: #f8f9fa;
                font-weight: 600;
            }
            .swal-wide {
                max-width: 1100px !important;
            }

            .table-hover tbody tr:hover {
                background-color: rgba(0, 123, 255, 0.05);
            }

            .table-sm th {
                font-size: 0.85rem;
                padding: 0.5rem;
            }

            .table-sm td {
                font-size: 0.85rem;
                padding: 0.5rem;
                vertical-align: middle;
            }

            /* Estilos para badges personalizados */
            .badge-code {
                font-family: 'Courier New', monospace;
                background-color: #f8f9fa;
                color: #495057;
                border: 1px solid #dee2e6;
            }

            .badge-value {
                background-color: #cfe2ff;
                color: #084298;
            }

            /* Estilos para la tabla en el modal de estados patrón */
            #tablaEstadosPatron td {
                vertical-align: middle;
            }

            #tablaEstadosPatron .text-muted {
                font-size: 0.8rem;
            }
            </style>
            <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

            <?php
            require_once ROOT_PATH . '/templates/adminlte/footer1.php';
            ?>
            </body>
            </html>