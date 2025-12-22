<?php
// Configuración de la página
$pageTitle = "Gestión de Tipos de Tablas";
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
                                    <div class="card-header">
                                        <h3 class="card-title">Listado de Tipos de Tablas</h3>
                                    </div>
                                    <div class="card-body">
                                        <button class="btn btn-primary mb-3" id="btnNuevoTipo">
                                            <i class="fas fa-plus"></i> Nuevo Tipo
                                        </button>
                                        <table id="tablaTipos" class="table table-striped table-bordered table-hover" style="width:100%">
                                            <thead>
                                                <tr>
                                                    <th>ID</th>
                                                    <th>Tipo de Tabla</th>
                                                    <th>Estados</th>
                                                    <th>Estado</th>
                                                    <th>Acciones</th>
                                                </tr>
                                            </thead>
                                            <tbody></tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
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
                    <form id="formTipo" class="needs-validation" novalidate>
                        <input type="hidden" id="tabla_tipo_id" name="tabla_tipo_id">
                        
                        <div class="mb-3">
                            <label for="tabla_tipo" class="form-label">Nombre del Tipo *</label>
                            <input type="text" class="form-control" id="tabla_tipo" name="tabla_tipo" required>
                            <div class="invalid-feedback">Por favor ingrese el nombre del tipo.</div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="tabla_estado_registro_id_tipo" name="tabla_estado_registro_id" value="1" checked>
                                <label class="form-check-label" for="tabla_estado_registro_id_tipo">Activo</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btnGuardarTipo" class="btn btn-primary">Guardar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Estados del Tipo -->
    <div class="modal fade" id="modalEstados" tabindex="-1" aria-labelledby="modalLabelEstados" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalLabelEstados">Estados del Tipo: <span id="nombreTipo"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="tabla_tipo_id_estados">
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <button class="btn btn-success" id="btnNuevoEstado">
                                <i class="fas fa-plus"></i> Agregar Estado
                            </button>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table id="tablaEstados" class="table table-striped table-bordered table-hover" style="width:100%">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Estado Registro</th>
                                    <th>Código</th>
                                    <th>Orden</th>
                                    <th>Es Inicial</th>
                                    <th>Estado</th>
                                    <th>Acciones</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal para Estado Individual -->
    <div class="modal fade" id="modalEstado" tabindex="-1" aria-labelledby="modalLabelEstado" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalLabelEstado">Estado del Tipo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
                </div>
                <div class="modal-body">
                    <form id="formEstado" class="needs-validation" novalidate>
                        <input type="hidden" id="tabla_tipo_estado_id" name="tabla_tipo_estado_id">
                        <input type="hidden" id="tabla_tipo_id_estado" name="tabla_tipo_id">
                        
                        <div class="mb-3">
                            <label for="estado_registro_id_modal" class="form-label">Estado Registro *</label>
                            <select class="form-control" id="estado_registro_id_modal" name="estado_registro_id" required disabled>
                                <option value="">Seleccionar...</option>
                            </select>
                            <div class="invalid-feedback">Por favor seleccione un estado registro.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="orden_modal" class="form-label">Orden *</label>
                            <input type="number" class="form-control" id="orden_modal" name="orden" min="1" value="1" required>
                            <div class="invalid-feedback">Por favor ingrese el orden.</div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="es_inicial_modal" name="es_inicial" value="1">
                                <label class="form-check-label" for="es_inicial_modal">Es Estado Inicial</label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="tabla_estado_registro_id_estado_modal" name="tabla_estado_registro_id" value="1" checked>
                                <label class="form-check-label" for="tabla_estado_registro_id_estado_modal">Activo</label>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" id="btnGuardarEstado" class="btn btn-primary">Guardar</button>
                </div>
            </div>
        </div>
    </div>
</main>

<!-- Incluir SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
$(document).ready(function() {
    // Variables globales
    var tablaTipos = null;
    var tablaEstados = null;
    var modalEstados = null;
    var modalEstado = null;
    var modalTipo = null;
    
    // Inicializar modales de Bootstrap
    modalEstados = new bootstrap.Modal(document.getElementById('modalEstados'));
    modalEstado = new bootstrap.Modal(document.getElementById('modalEstado'));
    modalTipo = new bootstrap.Modal(document.getElementById('modalTipo'));
    
    // ========== INICIALIZACIÓN DE DATATABLE PARA TIPOS ==========
    function inicializarTablaTipos() {
        tablaTipos = $('#tablaTipos').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
            },
            responsive: true,
            processing: true,
            serverSide: false,
            ajax: {
                url: 'tablas_tipos_ajax.php',
                type: 'GET',
                data: { accion: 'listar_tipos' },
                dataSrc: function(json) {
                    if (json.success) {
                        return json.data;
                    } else {
                        mostrarError('Error al cargar tipos: ' + (json.error || 'Error desconocido'));
                        return [];
                    }
                },
                error: function(xhr, error, thrown) {
                    mostrarError('Error en la petición AJAX: ' + error);
                }
            },
            columns: [
                { data: 'tabla_tipo_id', className: 'text-center' },
                { data: 'tabla_tipo' },
                { 
                    data: 'cantidad_estados',
                    className: 'text-center',
                    render: function(data) {
                        return '<span class="badge bg-info">' + data + '</span>';
                    }
                },
                {
                    data: 'tabla_estado_registro_id',
                    className: 'text-center',
                    render: function(data) {
                        if (data == 1) {
                            return '<span class="badge bg-success">Activo</span>';
                        } else {
                            return '<span class="badge bg-danger">Inactivo</span>';
                        }
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function(data) {
                        var html = '<div class="btn-group" role="group">';
                        
                        // Botón editar
                        html += '<button type="button" class="btn btn-sm btn-warning btnEditarTipo" title="Editar" data-id="' + data.tabla_tipo_id + '">';
                        html += '<i class="fas fa-edit"></i>';
                        html += '</button>';
                        
                        // Botón estados
                        html += '<button type="button" class="btn btn-sm btn-info btnVerEstados ms-1" title="Ver Estados" data-id="' + data.tabla_tipo_id + '" data-nombre="' + data.tabla_tipo + '">';
                        html += '<i class="fas fa-list"></i>';
                        html += '</button>';
                        
                        // Botón activar/desactivar
                        if (data.tabla_estado_registro_id == 1) {
                            html += '<button type="button" class="btn btn-sm btn-danger btnCambiarEstado ms-1" title="Desactivar" data-id="' + data.tabla_tipo_id + '">';
                            html += '<i class="fas fa-ban"></i>';
                            html += '</button>';
                        } else {
                            html += '<button type="button" class="btn btn-sm btn-success btnCambiarEstado ms-1" title="Activar" data-id="' + data.tabla_tipo_id + '">';
                            html += '<i class="fas fa-check"></i>';
                            html += '</button>';
                        }
                        
                        html += '</div>';
                        return html;
                    }
                }
            ],
            order: [[0, 'asc']]
        });
    }
    
    // ========== INICIALIZACIÓN DE DATATABLE PARA ESTADOS ==========
    function inicializarTablaEstados(tablaTipoId) {
        if (tablaEstados) {
            tablaEstados.destroy();
        }
        
        tablaEstados = $('#tablaEstados').DataTable({
            language: {
                url: '//cdn.datatables.net/plug-ins/1.13.6/i18n/es-ES.json'
            },
            responsive: true,
            processing: true,
            serverSide: false,
            ajax: {
                url: 'tablas_tipos_ajax.php',
                type: 'GET',
                data: { 
                    accion: 'listar_estados',
                    tabla_tipo_id: tablaTipoId
                },
                dataSrc: function(json) {
                    if (json.success) {
                        return json.data;
                    } else {
                        mostrarError('Error al cargar estados: ' + (json.error || 'Error desconocido'));
                        return [];
                    }
                }
            },
            columns: [
                { data: 'tabla_tipo_estado_id', className: 'text-center' },
                { data: 'estado_registro' },
                { data: 'codigo_estandar', className: 'text-center' },
                { data: 'orden', className: 'text-center' },
                {
                    data: 'es_inicial',
                    className: 'text-center',
                    render: function(data) {
                        return data == 1 ? '<i class="fas fa-check text-success"></i>' : '<i class="fas fa-times text-danger"></i>';
                    }
                },
                {
                    data: 'tabla_estado_registro_id',
                    className: 'text-center',
                    render: function(data) {
                        if (data == 1) {
                            return '<span class="badge bg-success">Activo</span>';
                        } else {
                            return '<span class="badge bg-danger">Inactivo</span>';
                        }
                    }
                },
                {
                    data: null,
                    className: 'text-center',
                    orderable: false,
                    render: function(data) {
                        var html = '<div class="btn-group" role="group">';
                        
                        // Botón editar
                        if (data.tabla_estado_registro_id == 1) {
                            html += '<button type="button" class="btn btn-sm btn-warning btnEditarEstado" title="Editar" data-id="' + data.tabla_tipo_estado_id + '">';
                            html += '<i class="fas fa-edit"></i>';
                            html += '</button>';
                        }
                        
                        // Botón activar/desactivar
                        if (data.tabla_estado_registro_id == 1) {
                            html += '<button type="button" class="btn btn-sm btn-danger btnCambiarEstadoEstado ms-1" title="Desactivar" data-id="' + data.tabla_tipo_estado_id + '">';
                            html += '<i class="fas fa-ban"></i>';
                            html += '</button>';
                        } else {
                            html += '<button type="button" class="btn btn-sm btn-success btnCambiarEstadoEstado ms-1" title="Activar" data-id="' + data.tabla_tipo_estado_id + '">';
                            html += '<i class="fas fa-check"></i>';
                            html += '</button>';
                        }
                        
                        html += '</div>';
                        return html;
                    }
                }
            ],
            order: [[3, 'asc']]
        });
    }
    
    // ========== FUNCIONES UTILITARIAS ==========
    function mostrarError(mensaje) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: mensaje,
            confirmButtonText: 'Aceptar'
        });
    }
    
    function mostrarExito(mensaje) {
        Swal.fire({
            icon: 'success',
            title: 'Éxito',
            text: mensaje,
            confirmButtonText: 'Aceptar',
            timer: 2000
        });
    }
    
    // ========== EVENTOS PARA TIPOS DE TABLAS ==========
    
    // Nuevo Tipo
    $('#btnNuevoTipo').click(function() {
        $('#formTipo')[0].reset();
        $('#tabla_tipo_id').val('');
        $('#tabla_estado_registro_id_tipo').prop('checked', true);
        $('#modalLabelTipo').text('Nuevo Tipo de Tabla');
        modalTipo.show();
    });
    
    // Editar Tipo
    $(document).on('click', '.btnEditarTipo', function() {
        var tipoId = $(this).data('id');
        
        $.ajax({
            url: 'tablas_tipos_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_tipo', tabla_tipo_id: tipoId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    var tipo = response.data;
                    $('#tabla_tipo_id').val(tipo.tabla_tipo_id);
                    $('#tabla_tipo').val(tipo.tabla_tipo);
                    $('#tabla_estado_registro_id_tipo').prop('checked', tipo.tabla_estado_registro_id == 1);
                    $('#modalLabelTipo').text('Editar Tipo: ' + tipo.tabla_tipo);
                    modalTipo.show();
                } else {
                    mostrarError(response.error || 'Error al cargar tipo');
                }
            },
            error: function() {
                mostrarError('Error de conexión');
            }
        });
    });
    
    // Ver Estados de un Tipo
    $(document).on('click', '.btnVerEstados', function() {
        var tipoId = $(this).data('id');
        var tipoNombre = $(this).data('nombre');
        
        $('#tabla_tipo_id_estados').val(tipoId);
        $('#nombreTipo').text(tipoNombre);
        inicializarTablaEstados(tipoId);
        modalEstados.show();
    });
    
    // Cambiar Estado de un Tipo
    $(document).on('click', '.btnCambiarEstado', function() {
        var tipoId = $(this).data('id');
        var estaActivo = $(this).hasClass('btn-danger'); // Si es btn-danger, está activo
        
        Swal.fire({
            title: '¿Está seguro?',
            text: estaActivo ? '¿Desea desactivar este tipo?' : '¿Desea activar este tipo?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                var nuevoEstado = estaActivo ? 0 : 1;
                
                $.ajax({
                    url: 'tablas_tipos_ajax.php',
                    type: 'GET',
                    data: {
                        accion: 'cambiar_estado_tipo',
                        tabla_tipo_id: tipoId,
                        nuevo_estado: nuevoEstado
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            tablaTipos.ajax.reload();
                            mostrarExito(response.message || 'Estado cambiado correctamente');
                        } else {
                            mostrarError(response.error || 'Error al cambiar estado');
                        }
                    },
                    error: function() {
                        mostrarError('Error de conexión');
                    }
                });
            }
        });
    });
    
    // Guardar Tipo
    $('#btnGuardarTipo').click(function() {
        var form = $('#formTipo')[0];
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }
        
        var tipoId = $('#tabla_tipo_id').val();
        var accion = tipoId ? 'editar_tipo' : 'agregar_tipo';
        
        var datos = {
            accion: accion,
            tabla_tipo_id: tipoId,
            tabla_tipo: $('#tabla_tipo').val(),
            tabla_estado_registro_id: $('#tabla_estado_registro_id_tipo').is(':checked') ? 1 : 0
        };
        
        $.ajax({
            url: 'tablas_tipos_ajax.php',
            type: 'GET',
            data: datos,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    tablaTipos.ajax.reload();
                    modalTipo.hide();
                    mostrarExito(response.message || 'Tipo guardado correctamente');
                } else {
                    mostrarError(response.error || 'Error al guardar tipo');
                }
            },
            error: function() {
                mostrarError('Error de conexión');
            }
        });
    });
    
    // ========== EVENTOS PARA ESTADOS DE TIPOS ==========
    
    // Nuevo Estado
    $('#btnNuevoEstado').click(function() {
        var tablaTipoId = $('#tabla_tipo_id_estados').val();
        
        $.ajax({
            url: 'tablas_tipos_ajax.php',
            type: 'GET',
            data: {
                accion: 'obtener_estados_registros_disponibles',
                tabla_tipo_id: tablaTipoId
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    var select = $('#estado_registro_id_modal');
                    select.empty();
                    select.append('<option value="">Seleccionar...</option>');
                    
                    $.each(response.data, function(index, estado) {
                        select.append($('<option>', {
                            value: estado.estado_registro_id,
                            text: estado.estado_registro + ' (' + estado.codigo_estandar + ')'
                        }));
                    });
                    
                    $('#formEstado')[0].reset();
                    $('#tabla_tipo_estado_id').val('');
                    $('#tabla_tipo_id_estado').val(tablaTipoId);
                    $('#estado_registro_id_modal').prop('disabled', false);
                    $('#tabla_estado_registro_id_estado_modal').prop('checked', true);
                    $('#modalLabelEstado').text('Nuevo Estado');
                    modalEstado.show();
                } else {
                    mostrarError(response.error || 'Error al cargar estados disponibles');
                }
            },
            error: function() {
                mostrarError('Error de conexión');
            }
        });
    });
    
    // Editar Estado
    $(document).on('click', '.btnEditarEstado', function() {
        var estadoId = $(this).data('id');
        
        $.ajax({
            url: 'tablas_tipos_ajax.php',
            type: 'GET',
            data: { accion: 'obtener_estado', tabla_tipo_estado_id: estadoId },
            dataType: 'json',
            success: function(response) {
                if (response.success && response.data) {
                    var estado = response.data;
                    
                    // Cargar todos los estados para mostrar el seleccionado
                    $.ajax({
                        url: 'tablas_tipos_ajax.php',
                        type: 'GET',
                        data: { accion: 'obtener_estados_registros' },
                        dataType: 'json',
                        success: function(estadosResponse) {
                            if (estadosResponse.success) {
                                var select = $('#estado_registro_id_modal');
                                select.empty();
                                
                                $.each(estadosResponse.data, function(index, est) {
                                    select.append($('<option>', {
                                        value: est.estado_registro_id,
                                        text: est.estado_registro + ' (' + est.codigo_estandar + ')',
                                        selected: est.estado_registro_id == estado.estado_registro_id
                                    }));
                                });
                                
                                $('#tabla_tipo_estado_id').val(estado.tabla_tipo_estado_id);
                                $('#tabla_tipo_id_estado').val(estado.tabla_tipo_id);
                                $('#orden_modal').val(estado.orden);
                                $('#es_inicial_modal').prop('checked', estado.es_inicial == 1);
                                $('#tabla_estado_registro_id_estado_modal').prop('checked', estado.tabla_estado_registro_id == 1);
                                $('#estado_registro_id_modal').prop('disabled', true);
                                $('#modalLabelEstado').text('Editar Estado: ' + estado.estado_registro);
                                modalEstado.show();
                            }
                        }
                    });
                } else {
                    mostrarError(response.error || 'Error al cargar estado');
                }
            },
            error: function() {
                mostrarError('Error de conexión');
            }
        });
    });
    
    // Cambiar Estado de un Estado
    $(document).on('click', '.btnCambiarEstadoEstado', function() {
        var estadoId = $(this).data('id');
        var estaActivo = $(this).hasClass('btn-danger'); // Si es btn-danger, está activo
        
        Swal.fire({
            title: '¿Está seguro?',
            text: estaActivo ? '¿Desea desactivar este estado?' : '¿Desea activar este estado?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí',
            cancelButtonText: 'Cancelar'
        }).then((result) => {
            if (result.isConfirmed) {
                var nuevoEstado = estaActivo ? 0 : 1;
                
                $.ajax({
                    url: 'tablas_tipos_ajax.php',
                    type: 'GET',
                    data: {
                        accion: 'cambiar_estado_estado',
                        tabla_tipo_estado_id: estadoId,
                        nuevo_estado: nuevoEstado
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            tablaEstados.ajax.reload();
                            mostrarExito(response.message || 'Estado cambiado correctamente');
                        } else {
                            mostrarError(response.error || 'Error al cambiar estado');
                        }
                    },
                    error: function() {
                        mostrarError('Error de conexión');
                    }
                });
            }
        });
    });
    
    // Guardar Estado
    $('#btnGuardarEstado').click(function() {
        var form = $('#formEstado')[0];
        
        if (!form.checkValidity()) {
            form.classList.add('was-validated');
            return;
        }
        
        var estadoId = $('#tabla_tipo_estado_id').val();
        var accion = estadoId ? 'editar_estado' : 'agregar_estado';
        
        var datos = {
            accion: accion,
            tabla_tipo_estado_id: estadoId,
            tabla_tipo_id: $('#tabla_tipo_id_estado').val(),
            estado_registro_id: $('#estado_registro_id_modal').val(),
            orden: $('#orden_modal').val(),
            es_inicial: $('#es_inicial_modal').is(':checked') ? 1 : 0,
            tabla_estado_registro_id: $('#tabla_estado_registro_id_estado_modal').is(':checked') ? 1 : 0
        };
        
        $.ajax({
            url: 'tablas_tipos_ajax.php',
            type: 'GET',
            data: datos,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    tablaEstados.ajax.reload();
                    modalEstado.hide();
                    mostrarExito(response.message || 'Estado guardado correctamente');
                } else {
                    mostrarError(response.error || 'Error al guardar estado');
                }
            },
            error: function() {
                mostrarError('Error de conexión');
            }
        });
    });
    
    // ========== INICIALIZAR APLICACIÓN ==========
    inicializarTablaTipos();
    
    // Cerrar modales al hacer clic fuera
    $('.modal').on('click', function(e) {
        if ($(e.target).hasClass('modal')) {
            $(this).modal('hide');
        }
    });
});
</script>

<style>
.table td, .table th {
    vertical-align: middle;
}

.badge {
    font-size: 0.85em;
    padding: 0.35em 0.65em;
}

.btn-group .btn {
    padding: 0.25rem 0.5rem;
    font-size: 0.875rem;
}

.modal-xl {
    max-width: 90%;
}
</style>

<?php
require_once ROOT_PATH . '/templates/adminlte/footer1.php';
?>
</body>
</html>